<?php

declare(strict_types=1);

namespace App\Support\Push;

use App\Actions\Boards\Boards;
use App\Models\AppDevice;
use App\Models\AppPush;
use App\Models\BellItem;
use App\Models\User;
use App\Services\Push\ApnsClient;
use App\Services\Push\FcmClient;
use App\Support\Gallery\Gallery;
use App\Support\Gallery\GalleryViews;
use App\Support\Sharing\HarmonyFacts;
use Illuminate\Support\Carbon;

/**
 * The bell, pushed to the native apps (contract/api.md, "account.devices.push.update"): each new
 * bell item is one push (a grouped like or voice pushes once, when its group starts), to each of
 * the person's devices with a push token, if they want that kind. Quiet hours hold it until they
 * end; one that couldn't go within 12 hours is dropped. Its words are made when it's sent, in the
 * person's language, with an app path to open.
 */
final class AppPushes
{
    /** The bell's kinds (contract/schemas/bellItem.json), each one a push setting. */
    public const KINDS = ['like', 'comment', 'follow', 'consent_request', 'consent_answer', 'featured', 'idea_voice', 'idea_complete', 'fill', 'request_ready', 'board_beaten'];

    public const STALE_HOURS = 12;

    public function __construct(private readonly ApnsClient $apns, private readonly FcmClient $fcm, private readonly HarmonyFacts $facts) {}

    public static function enabled(): bool
    {
        return config('services.app_push.enabled') === true;
    }

    /**
     * A new bell item: a push for it, if push is on and the person has a device to get it and
     * wants this kind.
     */
    public static function queue(BellItem $item): void
    {
        if (! self::enabled()) {
            return;
        }

        $user = User::query()->find($item->user_id);

        if ($user === null || ! (self::settings($user)['types'][$item->kind] ?? false)) {
            return;
        }

        if (! AppDevice::query()->where('user_id', $user->id)->whereNull('revoked_at')->where('refresh_expires_at', '>', now())->whereNotNull('push_token_hash')->exists()) {
            return;
        }

        AppPush::query()->insertOrIgnore([
            'user_id' => $user->id,
            'bell_item_id' => $item->id,
            'send_after' => self::sendAfter($user, now()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The person's push settings, every kind named (on unless turned off), and their quiet hours.
     *
     * @return array{types: array<string, bool>, quietHours: array{start: string, end: string}|null}
     */
    public static function settings(User $user): array
    {
        $saved = (array) ($user->push_settings ?? []);
        $types = [];

        foreach (self::KINDS as $kind) {
            $types[$kind] = (bool) ($saved['types'][$kind] ?? true);
        }

        $quiet = $saved['quietHours'] ?? null;

        return ['types' => $types, 'quietHours' => is_array($quiet) && isset($quiet['start'], $quiet['end']) ? ['start' => (string) $quiet['start'], 'end' => (string) $quiet['end']] : null];
    }

    /**
     * When a push made now may go: now, or the end of the person's quiet hours (in their time zone)
     * if it's within them. Quiet hours may cross midnight ("22:00" to "08:00").
     */
    public static function sendAfter(User $user, Carbon $now): Carbon
    {
        $quiet = self::settings($user)['quietHours'];

        if ($quiet === null || $quiet['start'] === $quiet['end']) {
            return $now->copy();
        }

        $zone = $user->timezone ?? $user->reminder_timezone ?? 'UTC';
        $local = $now->copy()->setTimezone($zone);
        $start = $local->copy()->setTimeFromTimeString($quiet['start']);
        $end = $local->copy()->setTimeFromTimeString($quiet['end']);

        if ($start->lt($end)) {
            $within = $local->gte($start) && $local->lt($end);
        } else {
            // Across midnight: quiet from the start to midnight, and from midnight to the end.
            $within = $local->gte($start) || $local->lt($end);

            if ($local->gte($start)) {
                $end->addDay();
            }
        }

        return $within ? $end->utc() : $now->copy();
    }

    /**
     * Sends the pushes that are due (a minute's worth at most), drops the stale ones, and forgets
     * the tokens a service says are gone.
     *
     * @return array{sent: int, dropped: int, gone: int}
     */
    public function sendDue(int $limit = 200): array
    {
        $done = ['sent' => 0, 'dropped' => 0, 'gone' => 0];

        if (! self::enabled()) {
            return $done;
        }

        $due = AppPush::query()->with(['user', 'bellItem.actor', 'bellItem.harmony', 'bellItem.idea.template', 'bellItem.songRequest.template', 'bellItem.partScore.template', 'bellItem.partScore.templateVersion'])
            ->whereNull('sent_at')->whereNull('dropped_at')->where('send_after', '<=', now())->orderBy('id')->limit($limit)->get();

        foreach ($due as $push) {
            $expires = $push->created_at?->copy()->addHours(self::STALE_HOURS) ?? now();

            if ($expires->isPast()) {
                $push->forceFill(['dropped_at' => now(), 'outcome' => 'stale'])->save();
                $done['dropped']++;

                continue;
            }

            $words = $this->words($push->bellItem, $push->user);
            $devices = AppDevice::query()->where('user_id', $push->user_id)->whereNull('revoked_at')->where('refresh_expires_at', '>', now())->whereNotNull('push_token_hash')->get();

            if ($words === null || $devices->isEmpty()) {
                $push->forceFill(['dropped_at' => now(), 'outcome' => $words === null ? 'gone' : 'no_devices'])->save();
                $done['dropped']++;

                continue;
            }

            $sent = 0;

            foreach ($devices as $device) {
                $result = $device->push_service === 'apns'
                    ? ($this->apns->configured() ? $this->apns->send((string) $device->push_token, (string) $device->push_environment, $words, $expires) : ApnsClient::FAILED)
                    : ($this->fcm->configured() ? $this->fcm->send((string) $device->push_token, $words, $expires) : ApnsClient::FAILED);

                if ($result === ApnsClient::GONE) {
                    $device->forceFill(AppDevice::NO_PUSH)->save();
                    $done['gone']++;
                }

                $sent += $result === ApnsClient::SENT ? 1 : 0;
            }

            $push->forceFill(['sent_at' => now(), 'outcome' => "sent:{$sent}/{$devices->count()}"])->save();
            $done['sent'] += $sent > 0 ? 1 : 0;
        }

        return $done;
    }

    /**
     * What the push says, and the app path it opens, or null when what it's about is gone.
     *
     * @return array{title: string, body: string, type: string, url: string}|null
     */
    public function words(BellItem $item, User $user): ?array
    {
        $locale = $user->locale ?: 'en';
        // Named as the bell names them (a hidden profile is Anonymous; a friend who beat a score as on
        // the friends' board), never by the raw account name.
        $name = $item->actor === null ? '' : ($item->kind === 'board_beaten' ? Boards::friendName($item->actor) : GalleryViews::person($item->actor)['name']);
        $harmony = $item->harmony;
        $idea = $item->idea;
        $request = $item->songRequest;

        [$title, $url] = match (true) {
            in_array($item->kind, ['like', 'comment', 'consent_request', 'consent_answer', 'featured', 'fill'], true) => $harmony !== null ? [$this->facts->title($harmony), '/h/'.$harmony->public_id] : [null, null],
            in_array($item->kind, ['idea_voice', 'idea_complete'], true) => $idea !== null ? [$idea->template->title, '/ideas/'.$idea->public_id] : [null, null],
            $item->kind === 'request_ready' => $request !== null ? [$request->template->title ?? $request->title, $request->template !== null ? '/songs/'.$request->template->slug : '/'] : [null, null],
            $item->kind === 'follow' => ['', $item->actor !== null && Gallery::hasPublicProfile($item->actor) ? '/@'.$item->actor->handle : '/'],
            $item->kind === 'board_beaten' => $item->partScore !== null ? [$item->partScore->template->title, '/songs/'.$item->partScore->template->slug] : [null, null],
            default => [null, null],
        };

        if ($title === null || $url === null || ($item->kind !== 'featured' && $item->kind !== 'idea_complete' && $item->kind !== 'request_ready' && $item->actor === null)) {
            return null;
        }

        $words = ['name' => $name, 'title' => $title, 'count' => $item->others];

        if ($item->kind === 'board_beaten' && $item->partScore !== null && $item->actor !== null) {
            $board = Boards::bellBoard($item->partScore);
            $words = [...$words, 'name' => Boards::friendName($item->actor), 'part' => $board['partName'], 'score' => $board['score']];
        }
        $key = $item->kind === 'like' && $item->others > 0 ? 'like_others' : $item->kind;

        return [
            'title' => (string) __('push.'.$item->kind.'.title', $words, $locale),
            'body' => (string) trans_choice('push.'.$key.'.body', $item->others, $words, $locale),
            'type' => $item->kind,
            'url' => $url,
        ];
    }
}
