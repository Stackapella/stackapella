<?php

declare(strict_types=1);

namespace App\Actions\Sharing;

use App\Actions\Boards\Boards;
use App\Actions\Choirs\Choirs;
use App\Actions\Ideas\Ideas;
use App\Actions\Notices\Notices;
use App\Actions\Requests\SongRequests;
use App\Actions\Templates\Library;
use App\Enums\SecurityEventType;
use App\Exceptions\Refusal;
use App\Models\Choir;
use App\Models\ChoirVoice;
use App\Models\Comment;
use App\Models\Harmony;
use App\Models\Idea;
use App\Models\Notice;
use App\Models\Report;
use App\Models\SongRequest;
use App\Models\Template;
use App\Models\User;
use App\Support\Gallery\Gallery;
use App\Support\Ids;
use App\Support\Sharing\HarmonyFiles;
use App\Support\Trust\IpHash;
use App\Support\Trust\SecurityLog;
use App\Support\Trust\TrustLevel;
use Illuminate\Support\Facades\DB;

/**
 * reports.create: anyone may report a shared harmony, signed in or not (the DSA's notice route,
 * docs/trust-and-safety-plan.md). One report per person (account, or network when signed out);
 * your own harmony's are ignored. Reports from `sharing.reports_to_hide` different people hide the
 * page until Hillel looks (counted by network, see people()). The answer is the same whatever happened, so nothing can be learned
 * from it.
 */
final class FileReport
{
    /** How many people (networks) reporting a comment hold it for review. */
    private const COMMENT_REPORTS_TO_HOLD = 2;

    public function __construct(private readonly SecurityLog $securityLog, private readonly HarmonyFiles $files, private readonly TrustLevel $trust, private readonly Notices $notices) {}

    /**
     * @param  array{boardEntryId?: string|null, harmonyId?: string|null, commentId?: string|null, handle?: string|null, templateId?: string|null, ideaId?: string|null, choirVoiceId?: string|null, requestId?: string|null, reason: string, note?: string|null, website?: mixed}  $data
     */
    public function handle(?User $user, array $data, string $ip): void
    {
        if (($data['harmonyId'] ?? null) === null) {
            $this->other($user, $data, $ip);

            return;
        }

        $harmony = Harmony::query()->with('user')->where('public_id', $data['harmonyId'])->whereNull('deleted_at')->first();

        // Only a page someone can see: a hidden one answers as if it weren't there, as its page does.
        if ($harmony === null || ! $harmony->isLive()) {
            throw new Refusal(404, 'not_found');
        }

        // The honeypot: a bot filled the hidden field (with anything at all).
        if ((($data['website'] ?? null) !== null && $data['website'] !== '') || $user?->id === $harmony->user_id) {
            return;
        }

        $report = new Report(['reason' => $data['reason'], 'note' => trim((string) ($data['note'] ?? ''))]);
        $report->forceFill([
            'reportable_type' => $harmony->getMorphClass(),
            'reportable_id' => $harmony->id,
            'reporter_id' => $user?->id,
            'reporter_key' => $user !== null ? hash_hmac('sha256', 'user|'.$user->id, (string) config('app.key')) : IpHash::of($ip),
            'ip_hash' => IpHash::of($ip),
            'status' => Report::OPEN,
        ]);

        $hidden = DB::transaction(function () use ($report, $harmony): bool {
            // A repeat from the same person changes nothing.
            if (Report::query()->insertOrIgnore([array_merge($report->getAttributes(), ['created_at' => now(), 'updated_at' => now()])]) === 0) {
                return false;
            }

            $locked = Harmony::query()->whereKey($harmony->id)->lockForUpdate()->firstOrFail();

            if ($locked->hidden_at !== null || $locked->removed_at !== null || ! $this->hides($locked)) {
                return false;
            }

            $locked->forceFill(['hidden_at' => now(), 'hidden_reason' => Harmony::HIDDEN_REPORTS])->save();

            // Hidden automatically: the owner is told, and may ask for a review.
            if (($owner = User::query()->find($locked->user_id)) !== null) {
                $this->notices->give($owner, Notice::HARMONY_HIDDEN, $locked, 'reports', null, automatic: true);
            }

            return true;
        });

        $this->securityLog->record(SecurityEventType::Report, $user, $ip, ['harmony' => $harmony->public_id, 'reason' => $data['reason']]);

        if ($hidden) {
            $this->files->sync($harmony);
        }
    }

    /**
     * A comment in the gallery, or a public profile (N1): recorded for Hillel. A comment that two
     * different people report, by the same rules as a page (one per network, never a new
     * account, one of them signed in and verified), waits for review in the gallery's queue, out
     * of sight.
     *
     * @param  array{boardEntryId?: string|null, commentId?: string|null, handle?: string|null, templateId?: string|null, ideaId?: string|null, choirVoiceId?: string|null, requestId?: string|null, reason: string, note?: string|null, website?: mixed}  $data
     */
    private function other(?User $user, array $data, string $ip): void
    {
        if (($data['boardEntryId'] ?? null) !== null) {
            app(Boards::class)->report($user, (string) $data['boardEntryId'], $data, $ip);

            return;
        }

        if (($data['templateId'] ?? null) !== null) {
            $this->template($user, $data, $ip);

            return;
        }

        if (($data['ideaId'] ?? null) !== null) {
            $this->idea($user, $data, $ip);

            return;
        }

        if (($data['choirVoiceId'] ?? null) !== null) {
            $this->choirVoice($user, $data, $ip);

            return;
        }

        if (($data['requestId'] ?? null) !== null) {
            $this->songRequest($user, $data, $ip);

            return;
        }

        $gallery = app(Gallery::class);
        $subject = match (true) {
            ($data['commentId'] ?? null) !== null => Comment::query()->whereKey(Ids::decode((string) $data['commentId']) ?? 0)->whereNull('hidden_at')->whereNull('held_at')->first(),
            default => User::query()->where('handle', strtolower((string) ($data['handle'] ?? '')))->first(),
        };
        $owner = $subject instanceof Comment ? $subject->user_id : $subject?->id;

        if ($subject === null || ! $gallery->isOpen() || ($subject instanceof User && ! Gallery::hasPublicProfile($subject))) {
            throw new Refusal(404, 'not_found');
        }

        if ((($data['website'] ?? null) !== null && $data['website'] !== '') || $user?->id === $owner) {
            return;
        }

        $report = new Report(['reason' => $data['reason'], 'note' => trim((string) ($data['note'] ?? ''))]);
        $report->forceFill([
            'reportable_type' => $subject->getMorphClass(),
            'reportable_id' => $subject->getKey(),
            'reporter_id' => $user?->id,
            'reporter_key' => $user !== null ? hash_hmac('sha256', 'user|'.$user->id, (string) config('app.key')) : IpHash::of($ip),
            'ip_hash' => IpHash::of($ip),
            'status' => Report::OPEN,
        ]);

        DB::transaction(function () use ($report, $subject): void {
            if (Report::query()->insertOrIgnore([array_merge($report->getAttributes(), ['created_at' => now(), 'updated_at' => now()])]) === 0 || ! $subject instanceof Comment) {
                return;
            }

            if ($this->hides($subject, self::COMMENT_REPORTS_TO_HOLD) && Comment::query()->whereKey($subject->id)->whereNull('held_at')->update(['held_at' => now(), 'updated_at' => now()]) > 0) {
                Harmony::query()->whereKey($subject->harmony_id)->where('comments_count', '>', 0)->decrement('comments_count');
            }
        });

        $this->securityLog->record(SecurityEventType::Report, $user, $ip, [$subject instanceof Comment ? 'comment' : 'profile' => $subject instanceof Comment ? $subject->sqid() : $subject->handle, 'reason' => $data['reason']]);
    }

    /**
     * A template in the library (N3): reports from enough people (the page rule) hold it out of
     * the library until Hillel looks.
     *
     * @param  array{templateId?: string|null, reason: string, note?: string|null, website?: mixed}  $data
     */
    private function template(?User $user, array $data, string $ip): void
    {
        $template = Template::query()->where('slug', (string) ($data['templateId'] ?? ''))->whereNotNull('owner_id')->where('visibility', 'public')->whereNull('hidden_at')->whereNull('deleted_at')->first();

        if ($template === null || ! app(Library::class)->isOpen()) {
            throw new Refusal(404, 'not_found');
        }

        if ((($data['website'] ?? null) !== null && $data['website'] !== '') || $user?->id === $template->owner_id) {
            return;
        }

        $report = new Report(['reason' => $data['reason'], 'note' => trim((string) ($data['note'] ?? ''))]);
        $report->forceFill([
            'reportable_type' => $template->getMorphClass(),
            'reportable_id' => $template->id,
            'reporter_id' => $user?->id,
            'reporter_key' => $user !== null ? hash_hmac('sha256', 'user|'.$user->id, (string) config('app.key')) : IpHash::of($ip),
            'ip_hash' => IpHash::of($ip),
            'status' => Report::OPEN,
        ]);

        DB::transaction(function () use ($report, $template): void {
            if (Report::query()->insertOrIgnore([array_merge($report->getAttributes(), ['created_at' => now(), 'updated_at' => now()])]) > 0 && $this->hides($template)) {
                Template::query()->whereKey($template->id)->whereNull('held_at')->update(['held_at' => now()]);
            }
        });

        $this->securityLog->record(SecurityEventType::Report, $user, $ip, ['template' => $template->slug, 'reason' => $data['reason']]);
    }

    /**
     * An idea (Ideas): reports from enough people (the page rule) hold it for Hillel's review.
     *
     * @param  array{ideaId?: string|null, reason: string, note?: string|null, website?: mixed}  $data
     */
    private function idea(?User $user, array $data, string $ip): void
    {
        $idea = Idea::query()->where('public_id', (string) ($data['ideaId'] ?? ''))->whereNull('hidden_at')->first();

        if ($idea === null || ! app(Ideas::class)->isOpen()) {
            throw new Refusal(404, 'not_found');
        }

        if ((($data['website'] ?? null) !== null && $data['website'] !== '') || $user?->id === $idea->user_id) {
            return;
        }

        $report = new Report(['reason' => $data['reason'], 'note' => trim((string) ($data['note'] ?? ''))]);
        $report->forceFill([
            'reportable_type' => $idea->getMorphClass(),
            'reportable_id' => $idea->id,
            'reporter_id' => $user?->id,
            'reporter_key' => $user !== null ? hash_hmac('sha256', 'user|'.$user->id, (string) config('app.key')) : IpHash::of($ip),
            'ip_hash' => IpHash::of($ip),
            'status' => Report::OPEN,
        ]);

        DB::transaction(function () use ($report, $idea): void {
            if (Report::query()->insertOrIgnore([array_merge($report->getAttributes(), ['created_at' => now(), 'updated_at' => now()])]) > 0 && $this->hides($idea)) {
                Idea::query()->whereKey($idea->id)->whereNull('held_at')->update(['held_at' => now()]);
                Template::query()->whereKey($idea->template_id)->update(['idea_review_at' => now()]);
            }
        });

        $this->securityLog->record(SecurityEventType::Report, $user, $ip, ['idea' => $idea->public_id, 'reason' => $data['reason']]);
    }

    /**
     * A voice in a virtual choir (N6): reports from enough people (the page rule) take it out of
     * the next mix until Hillel looks.
     *
     * @param  array{choirVoiceId?: string|null, requestId?: string|null, reason: string, note?: string|null, website?: mixed}  $data
     */
    private function choirVoice(?User $user, array $data, string $ip): void
    {
        $voice = ChoirVoice::query()->where('uuid', (string) ($data['choirVoiceId'] ?? ''))->where('status', ChoirVoice::READY)->whereNull('hidden_at')->whereNull('held_at')->first();

        if ($voice === null || ! app(Choirs::class)->isOpen()) {
            throw new Refusal(404, 'not_found');
        }

        if ((($data['website'] ?? null) !== null && $data['website'] !== '') || $user?->id === $voice->user_id) {
            return;
        }

        $report = new Report(['reason' => $data['reason'], 'note' => trim((string) ($data['note'] ?? ''))]);
        $report->forceFill([
            'reportable_type' => $voice->getMorphClass(),
            'reportable_id' => $voice->id,
            'reporter_id' => $user?->id,
            'reporter_key' => $user !== null ? hash_hmac('sha256', 'user|'.$user->id, (string) config('app.key')) : IpHash::of($ip),
            'ip_hash' => IpHash::of($ip),
            'status' => Report::OPEN,
        ]);

        DB::transaction(function () use ($report, $voice): void {
            if (Report::query()->insertOrIgnore([array_merge($report->getAttributes(), ['created_at' => now(), 'updated_at' => now()])]) > 0 && $this->hides($voice)) {
                ChoirVoice::query()->whereKey($voice->id)->whereNull('held_at')->update(['held_at' => now()]);
                Choir::query()->whereKey($voice->choir_id)->update(['changed_at' => now()]);
            }
        });

        $this->securityLog->record(SecurityEventType::Report, $user, $ip, ['choirVoice' => $voice->uuid, 'reason' => $data['reason']]);
    }

    /**
     * A song request (N7): reports from enough people (the page rule) hold it until Hillel looks.
     *
     * @param  array{requestId?: string|null, reason: string, note?: string|null, website?: mixed}  $data
     */
    private function songRequest(?User $user, array $data, string $ip): void
    {
        $request = app(SongRequests::class)->find((string) ($data['requestId'] ?? ''));

        if ((($data['website'] ?? null) !== null && $data['website'] !== '') || $user?->id === $request->user_id) {
            return;
        }

        $report = new Report(['reason' => $data['reason'], 'note' => trim((string) ($data['note'] ?? ''))]);
        $report->forceFill([
            'reportable_type' => $request->getMorphClass(),
            'reportable_id' => $request->id,
            'reporter_id' => $user?->id,
            'reporter_key' => $user !== null ? hash_hmac('sha256', 'user|'.$user->id, (string) config('app.key')) : IpHash::of($ip),
            'ip_hash' => IpHash::of($ip),
            'status' => Report::OPEN,
        ]);

        DB::transaction(function () use ($report, $request): void {
            if (Report::query()->insertOrIgnore([array_merge($report->getAttributes(), ['created_at' => now(), 'updated_at' => now()])]) > 0 && $this->hides($request)) {
                SongRequest::query()->whereKey($request->id)->whereNull('held_at')->update(['held_at' => now()]);
            }
        });

        $this->securityLog->record(SecurityEventType::Report, $user, $ip, ['request' => $request->sqid(), 'reason' => $data['reason']]);
    }

    /**
     * Whether the open reports hide the page: enough different people (one per network, so
     * throwaway accounts, or one person signed in and out, count once; never a new account that
     * hasn't verified its email), and one of them signed in and verified. Reports from signed-out
     * networks alone go to Hillel's queue but never hide a page (three networks are cheap).
     */
    private function hides(Harmony|Comment|Template|Idea|ChoirVoice|SongRequest $subject, ?int $needed = null): bool
    {
        $counted = Report::query()
            ->with('reporter')
            ->whereMorphedTo('reportable', $subject)
            ->where('status', Report::OPEN)
            ->get()
            ->filter(fn (Report $report): bool => $report->reporter === null || $this->trust->of($report->reporter) > TrustLevel::NEW);

        return $counted->unique('ip_hash')->count() >= ($needed ?? (int) config('sharing.reports_to_hide'))
            && $counted->contains(fn (Report $report): bool => $report->reporter !== null);
    }
}
