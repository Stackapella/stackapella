<?php

declare(strict_types=1);

namespace App\Support\OwnChoir;

use App\Actions\Counts\RecordCounts;
use App\Enums\OwnChoirStatus;
use App\Jobs\SendChoirEmail;
use App\Models\OwnChoirRequest;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The choir wait (Hillel, 2026-11-24: start the choir after the take, show it while it's made, email when it's ready):
 * where a song is in line and roughly how long it'll be, whether it's slower than usual, the app's last look (so someone
 * watching isn't emailed), the one email when it's ready or couldn't be made, and how long after it was ready it was
 * heard. Counts are anonymous daily totals.
 */
final class ChoirWait
{
    /** Seconds of GPU a part-minute takes, until enough songs say otherwise. */
    private const DEFAULT_SECONDS_PER_MINUTE = 30.0;

    /** The app looked at its songs this recently: an email waits EMAIL_DELAY_MINUTES. */
    public const LOOKED_SECONDS = 120;

    public const EMAIL_DELAY_MINUTES = 10;

    /** The app asked for the songs (ownChoir.show): marked in the cache only, for the email's wait. */
    public static function looked(User $user): void
    {
        Cache::put(self::lookKey($user->id), now()->getTimestamp(), self::LOOKED_SECONDS);
    }

    public static function lookedRecently(int $userId): bool
    {
        return Cache::has(self::lookKey($userId));
    }

    /**
     * A song's wait as the app shows it: its place in line, roughly how many minutes, and whether it's past twice that.
     *
     * @return array{queuePosition: int|null, etaMinutes: int|null, slow: bool}
     */
    public static function of(OwnChoirRequest $song): array
    {
        $own = self::seconds((float) $song->part_minutes);
        $waitsForTomorrow = $song->starts_after !== null && $song->starts_after->isFuture();

        if ($song->status === OwnChoirStatus::Rendering) {
            $since = ($song->first_claimed_at ?? $song->claimed_at ?? now())->diffInSeconds(now());
            $left = max(60.0, $own - $since);

            return ['queuePosition' => null, 'etaMinutes' => (int) ceil($left / 60), 'slow' => $since > 2 * max($own, 120.0)];
        }

        if ($song->status !== OwnChoirStatus::Queued || $waitsForTomorrow) {
            return ['queuePosition' => null, 'etaMinutes' => null, 'slow' => false];
        }

        $held = $song->hold_until !== null && $song->hold_until->isFuture();
        $line = self::line();
        $place = array_search($song->id, array_keys($line), true);
        $ahead = $place === false ? 0.0 : array_sum(array_slice($line, 0, $place));
        $waited = ($song->created_at ?? now())->diffInSeconds(now());
        $expected = $ahead + $own;

        return [
            'queuePosition' => $held || $place === false ? null : $place + 1,
            'etaMinutes' => $ahead < 30 && ! $held ? null : (int) ceil($expected / 60),
            'slow' => ! $held && $waited > 2 * max($expected, 120.0),
        ];
    }

    /**
     * The renderer's line now, in the order it takes songs (paid Plus first, then in turn), each with its estimated
     * seconds: read once a request.
     *
     * @return array<int, float>
     */
    private static function line(): array
    {
        return once(static fn (): array => OwnChoirRequest::query()->where('status', OwnChoirStatus::Queued->value)->where('held_for_minutes', false)
            ->where(static fn ($q) => $q->whereNull('starts_after')->orWhere('starts_after', '<=', now()))
            ->orderByDesc('priority')->orderBy('id')->limit(200)->pluck('part_minutes', 'id')
            ->map(static fn (mixed $minutes): float => self::seconds((float) $minutes))->all());
    }

    /** A song's render, estimated from its part-minutes at the recent pace. */
    private static function seconds(float $partMinutes): float
    {
        return max(60.0, $partMinutes * self::pace());
    }

    /** GPU seconds a part-minute took over the last 30 made songs (kept 10 minutes), else the default. */
    private static function pace(): float
    {
        return (float) Cache::remember('own_choir:pace', 600, static function (): float {
            $recent = OwnChoirRequest::query()->where('status', OwnChoirStatus::Ready->value)->where('part_minutes', '>', 0)->where('gpu_seconds', '>', 0)
                ->latest('ready_at')->limit(30)->get(['part_minutes', 'gpu_seconds']);
            $minutes = (float) $recent->sum('part_minutes');

            return $recent->count() >= 5 && $minutes > 0 ? (float) $recent->sum('gpu_seconds') / $minutes : self::DEFAULT_SECONDS_PER_MINUTE;
        });
    }

    /**
     * A song of the singer's own ready or not made: its one email, sent at once, or after EMAIL_DELAY_MINUTES when the
     * app looked at it in the last two (SendChoirEmail checks again when it runs).
     */
    public static function finished(OwnChoirRequest $song): void
    {
        if ($song->parent_request_id !== null || $song->remake || ! in_array($song->status, [OwnChoirStatus::Ready, OwnChoirStatus::Failed], true)) {
            return;
        }

        $job = SendChoirEmail::dispatch($song->id);

        if (self::lookedRecently((int) $song->user_id)) {
            $job->delay(now()->addMinutes(self::EMAIL_DELAY_MINUTES));
        }
    }

    /** Heard (seen) at last: how long after it was ready, a count by band. */
    public static function heard(OwnChoirRequest $song): void
    {
        if ($song->status !== OwnChoirStatus::Ready || $song->ready_at === null || $song->parent_request_id !== null) {
            return;
        }

        $minutes = $song->ready_at->diffInMinutes(now());
        $after = match (true) {
            $minutes <= 10 => '10m',
            $minutes <= 60 => '1h',
            $minutes <= 1440 => '1d',
            default => 'later',
        };

        app(RecordCounts::class)->serverAmount('choir_heard_after_'.$after, 1);
    }

    /**
     * The kinds counted here (config counts.server_kinds).
     *
     * @return list<string>
     */
    public static function serverKinds(): array
    {
        return ['choir_email_sent_ready', 'choir_email_sent_failed', 'choir_email_off', 'choir_heard_after_10m', 'choir_heard_after_1h', 'choir_heard_after_1d', 'choir_heard_after_later'];
    }

    /**
     * What the app counts (config counts.kinds).
     *
     * @return list<string>
     */
    public static function appKinds(): array
    {
        return ['choir_auto_started', 'choir_hold_listened', 'choir_hold_sang_again', 'choir_start_now', 'choir_stopped_after_claim', 'choir_banner_closed', 'choir_email_opened',
            ...array_map(static fn (string $state): string => 'choir_banner_tap_'.$state, ['queued', 'tomorrow', 'making', 'slow', 'ready', 'failed'])];
    }

    private static function lookKey(int $userId): string
    {
        return 'own_choir:looked:'.$userId;
    }
}
