<?php

declare(strict_types=1);

namespace App\Actions\Voices;

use App\Enums\CreditMode;
use App\Enums\SubmissionStatus;
use App\Exceptions\Refusal;
use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\CourseClip;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\Accounts\Age;
use App\Support\Flags;
use App\Support\Gallery\Gallery;
use App\Support\Templates\Staging;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lend your voice (docs/lend-your-voice-plan.md): the Wanted voices (songs' parts and lesson clips
 * with no real voice yet, that nobody holds), joining as a volunteer (a beta singer, unpaid, with
 * the volunteers' agreement), and claims (an assignment they took, held a while). From a claim on,
 * it's the beta singers' pipeline: their takes, the checks, Hillel's review, publishing, credit.
 */
final class LendVoice
{
    /** A volunteer's account must be at least this old (never in their first week). */
    public const ACCOUNT_DAYS = 7;

    public function __construct(private readonly Flags $flags) {}

    public function ensureOpen(): void
    {
        if (! $this->flags->on('lend_voice')) {
            throw new Refusal(404, 'not_found');
        }
    }

    /**
     * voices.summary, for marketing's public page (slice 5): how many song parts and lesson clips
     * still want a real voice, and how many volunteers' voices are out there now. Counts only.
     *
     * @return array{songParts: int, clips: int, lent: int}
     */
    public function summary(): array
    {
        $items = collect($this->wanted(null, null)['items']);
        $volunteers = BetaSinger::query()->where('volunteer', true)->pluck('id');
        $submissions = BetaSubmission::query()->whereHas('assignment', fn ($q) => $q->whereIn('beta_singer_id', $volunteers))->select('id');
        $lent = TemplateGuide::query()->whereIn('beta_submission_id', $submissions)->count()
            + CourseClip::query()->whereNotNull('media_key')->whereIn('beta_submission_id', $submissions)->count();

        return ['songParts' => $items->where('kind', 'song')->count(), 'clips' => $items->where('kind', 'clip')->count(), 'lent' => $lent];
    }

    /**
     * What's wanted, optionally only songs or clips, or one skill; and the clips' skills, in the
     * words a claimant sees.
     *
     * @return array{items: list<array<string, mixed>>, skills: list<array{id: string, words: string}>}
     */
    public function wanted(?string $kind, ?string $skill): array
    {
        $this->ensureOpen();
        $items = [];
        // Held: every open claim on a lesson clip, read once. A song's part is never held: several singers may record it,
        // and Hillel chooses (docs/open-guide-voices-plan.md).
        $heldClips = self::open()->whereNotNull('course_clip_id')->pluck('course_clip_id')->all();

        if ($kind !== 'clips' && ($skill === null || $skill === 'any')) {
            $templates = Template::query()->official()->listed()->with(['currentVersion', 'stagedVersion'])->whereNotNull('current_version_id')->orderBy('title')->get();
            $voiced = TemplateGuide::query()->whereIn('template_version_id', $templates->map(static fn (Template $t): ?int => $t->recordingVersion()?->id)->filter())->where('is_default', true)->get(['template_version_id', 'part_key'])
                ->map(static fn (TemplateGuide $g): string => $g->template_version_id.':'.$g->part_key)->all();

            foreach ($templates as $template) {
                // A staged next version is what's recorded, and only its changed parts are wanted (Staging).
                $version = $template->recordingVersion();

                if ($version === null || ! Staging::recordable($template, $version)) {
                    continue;
                }
                $changed = $template->staged_version_id !== null ? Staging::changed($template) : null;

                foreach ((array) ($version->data['parts'] ?? []) as $part) {
                    if (! is_array($part) || ! is_string($part['key'] ?? null) || ($changed !== null && ! in_array($part['key'], $changed, true)) || in_array($version->id.':'.$part['key'], $voiced, true)) {
                        continue;
                    }

                    $items[] = ['id' => 'song:'.$template->slug.':'.$part['key'], 'kind' => 'song', 'templateId' => $template->slug, 'title' => $template->title, 'partKey' => $part['key'], 'partName' => (string) ($part['name'] ?? $part['key']), 'skill' => 'any'];
                }
            }
        }

        if ($kind !== 'songs') {
            foreach (CourseClip::query()->whereNull('retired_at')->whereNull('media_key')->orderBy('slot')->get() as $clip) {
                $clipSkill = (string) ($clip->brief['skill'] ?? 'any');

                if (($skill !== null && $clipSkill !== $skill) || in_array($clip->id, $heldClips, true)) {
                    continue;
                }

                $items[] = ['id' => 'clip:'.$clip->slot, 'kind' => 'clip', 'clipId' => $clip->slot, 'course' => $clip->course_id,
                    'sing' => (string) ($clip->brief['sing'][app()->getLocale()] ?? $clip->brief['sing']['en'] ?? ''), 'seconds' => (float) ($clip->brief['seconds'] ?? 0),
                    'style' => $clip->brief['style'] ?? null, 'voices' => $clip->brief['voices'] ?? 'any', 'skill' => $clipSkill];
            }
        }

        return ['items' => $items, 'skills' => self::skills()];
    }

    /**
     * Join as a volunteer: the agreement accepted (its version and when kept), with their credit.
     */
    public function join(User $user, int $version, CreditMode $credit, bool $linksProfile): BetaSinger
    {
        $this->ensureOpen();
        $this->ensureMayLend($user);

        if ($version !== (int) config('beta.volunteer_agreement_version')) {
            throw new Refusal(409, 'agreement_changed');
        }

        return DB::transaction(function () use ($user, $version, $credit, $linksProfile): BetaSinger {
            $singer = BetaSinger::query()->where('user_id', $user->id)->lockForUpdate()->first();

            if ($singer?->volunteer_blocked_at !== null) {
                throw new Refusal(403, 'volunteer_blocked');
            }

            // An invited (paid) beta singer records what Hillel assigns them, not claims.
            if ($singer !== null && ! $singer->volunteer) {
                throw new Refusal(409, 'beta_singer');
            }

            $singer ??= new BetaSinger;
            $singer->forceFill([
                'user_id' => $user->id, 'name' => $user->name, 'volunteer' => true, 'accepted_at' => $singer->accepted_at ?? now(),
                'token_hash' => $singer->token_hash ?? hash('sha256', Str::random(40)),
                'consent_version' => $version, 'consented_at' => now(), 'credit_mode' => $credit, 'credit_links_profile' => $linksProfile,
                'revoked_at' => null,
            ])->save();

            return $singer;
        });
    }

    /**
     * Claim one wanted item ("song:<id>:<part>" or "clip:<slot>"), held for a while.
     */
    public function claim(User $user, string $item): BetaAssignment
    {
        $this->ensureOpen();
        $this->ensureMayLend($user);
        $singer = BetaSinger::query()->where('user_id', $user->id)->where('volunteer', true)->first();

        // A take refused, or a voice removed, for abuse: no more claims (the plan's moderation).
        if ($singer?->volunteer_blocked_at !== null) {
            throw new Refusal(403, 'volunteer_blocked');
        }

        if ($singer === null || ! $singer->isLive() || ! $singer->hasConsented($singer->agreementVersion())) {
            throw new Refusal(403, 'not_volunteer');
        }

        return DB::transaction(function () use ($singer, $item): BetaAssignment {
            BetaSinger::query()->whereKey($singer->id)->lockForUpdate()->first();

            [$what, $id, $part] = array_pad(explode(':', $item, 3), 3, null);

            // A song's part is an open take now (docs/open-guide-voices-plan.md): no limit on how many a singer records at
            // once. A lesson clip's slot is still one singer's claim, three at a time.
            if ($what !== 'song' && self::open()->where('beta_singer_id', $singer->id)->whereNotNull('course_clip_id')->count() >= (int) config('beta.claims_at_once')) {
                throw new Refusal(409, 'claim_limit');
            }
            $assignment = new BetaAssignment;

            if ($what === 'song' && is_string($id) && is_string($part)) {
                $template = Template::query()->official()->listed()->with(['currentVersion', 'stagedVersion'])->where('slug', $id)->lockForUpdate()->first() ?? throw new Refusal(404, 'not_found');
                $version = $template->recordingVersion();
                $parts = $template->staged_version_id !== null ? Staging::changed($template) : array_column(array_filter((array) ($version->data['parts'] ?? []), 'is_array'), 'key');

                if ($version === null || ! Staging::recordable($template, $version) || ! in_array($part, $parts, true) || TemplateGuide::query()->where('template_version_id', $version->id)->where('part_key', $part)->where('is_default', true)->exists()) {
                    throw new Refusal(409, 'not_wanted');
                }

                // Several singers may record the same part, and Hillel chooses; one open take a part a person.
                $mine = self::forSong($template->id, $part)->where('beta_singer_id', $singer->id)->whereIn('id', self::open()->select('id'))->first();
                if ($mine !== null) {
                    return $mine;
                }

                $assignment->forceFill(['template_id' => $template->id, 'part_key' => $part]);
            } elseif ($what === 'clip' && is_string($id)) {
                $clip = CourseClip::query()->where('slot', $id)->whereNull('retired_at')->lockForUpdate()->first() ?? throw new Refusal(404, 'not_found');

                if ($clip->media_key !== null || self::held(self::forClip($clip->id))) {
                    throw new Refusal(409, 'not_wanted');
                }

                $assignment->forceFill(['course_clip_id' => $clip->id]);
            } else {
                throw new Refusal(404, 'not_found');
            }

            $assignment->forceFill(['beta_singer_id' => $singer->id, 'claimed_at' => now(), 'expires_at' => now()->addDays((int) config('beta.claim_days'))])->save();

            return $assignment;
        });
    }

    /**
     * Their claims still held: each with what it is and when it ends.
     *
     * @return list<BetaAssignment>
     */
    public function claims(User $user): array
    {
        $this->ensureOpen();
        $singer = BetaSinger::query()->where('user_id', $user->id)->where('volunteer', true)->first();

        return $singer === null ? [] : array_values(self::open()->with(['template', 'clip', 'latestSubmission'])->where('beta_singer_id', $singer->id)->orderBy('expires_at')->get()->all());
    }

    /**
     * Give a claim back: gone if nothing was sent, else ended (its takes stay for the review).
     */
    public function release(User $user, int $id): void
    {
        $this->ensureOpen();
        $assignment = BetaAssignment::query()->whereKey($id)->whereNotNull('claimed_at')->whereHas('singer', fn ($q) => $q->where('user_id', $user->id))->first() ?? throw new Refusal(404, 'not_found');

        if ($assignment->submissions()->exists()) {
            $assignment->forceFill(['expires_at' => now()])->save();
        } else {
            $assignment->delete();
        }
    }

    /**
     * The nightly tidy: claims that ran out with nothing sent are let go.
     */
    public static function releaseExpired(): int
    {
        return (int) BetaAssignment::query()->whereNotNull('claimed_at')->where('expires_at', '<=', now())->whereDoesntHave('submissions')->delete();
    }

    /**
     * Signed in, a confirmed email, 18 or over (asked, never assumed), past their first week, not
     * suspended.
     */
    public function ensureMayLend(User $user): void
    {
        if ($user->isSuspended()) {
            throw new Refusal(403, 'suspended');
        }

        if ($user->email_verified_at === null) {
            throw new Refusal(403, 'email_unverified');
        }

        Gallery::ensureAdult($user);
        Age::ensureMayClaimAdult($user);

        if ($user->created_at !== null && $user->created_at->gt(now()->subDays(self::ACCOUNT_DAYS))) {
            throw new Refusal(403, 'account_too_new');
        }
    }

    /**
     * Claims still held, and every invited singer's assignment: a claim until it ends, or while a
     * take sent for it waits for review; an invited singer's while they're live.
     *
     * @return Builder<BetaAssignment>
     */
    public static function open(): Builder
    {
        $waiting = [SubmissionStatus::Submitted->value, SubmissionStatus::InReview->value, SubmissionStatus::Processing->value];

        return BetaAssignment::query()->where(static function (Builder $q) use ($waiting): void {
            $q->where(static function (Builder $claims) use ($waiting): void {
                $claims->whereNotNull('claimed_at')->where(static function (Builder $held) use ($waiting): void {
                    $held->where('expires_at', '>', now())->orWhereHas('submissions', static fn (Builder $s) => $s->whereIn('status', $waiting));
                });
            })->orWhere(static function (Builder $invited): void {
                $invited->whereNull('claimed_at')->whereHas('singer', static fn (Builder $s) => $s->whereNull('revoked_at')->whereNull('forgotten_at'));
            });
        });
    }

    /**
     * @param  Builder<BetaAssignment>  $query
     */
    private static function held(Builder $query): bool
    {
        return $query->whereIn('id', self::open()->select('id'))->exists();
    }

    /**
     * @return Builder<BetaAssignment>
     */
    private static function forSong(int $templateId, string $part): Builder
    {
        return BetaAssignment::query()->where('template_id', $templateId)->where('part_key', $part);
    }

    /**
     * @return Builder<BetaAssignment>
     */
    private static function forClip(int $clipId): Builder
    {
        return BetaAssignment::query()->where('course_clip_id', $clipId);
    }

    /**
     * The clips' skills, in the words a claimant sees (web/course/plus/clips.json `skills`).
     *
     * @return list<array{id: string, words: string}>
     */
    public static function skills(): array
    {
        $file = dirname(base_path()).'/web/course/plus/clips.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $skills = [];

        foreach (is_array($data) ? (array) ($data['skills'] ?? []) : [] as $key => $words) {
            if (is_string($key) && is_array($words)) {
                $skills[] = ['id' => $key, 'words' => (string) ($words[app()->getLocale()] ?? $words['en'] ?? $key)];
            }
        }

        return $skills;
    }
}
