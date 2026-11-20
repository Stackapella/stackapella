<?php

declare(strict_types=1);

namespace App\Support\OwnChoir;

use App\Enums\OwnChoirStatus;
use App\Exceptions\Refusal;
use App\Models\OwnChoirConsent;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\OwnChoirVoice;
use App\Models\User;
use App\Support\Flags;
use App\Support\Legal\LegalText;
use App\Support\Usage\Plans;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Hear yourself as a choir (docs/ai-voices-build-plan.md, track B phase 3), its rules in one place: who may (the
 * own_choir switch for the person), the terms, the quotas (config own_choir: one free song ever, more a month with Plus,
 * a daily cap for everyone; a failed song gives its place back), and the state the app shows (contract ownChoir.json).
 */
final class OwnChoir
{
    public const FLAG = 'own_choir';

    public const TERMS = 'own-choir';

    public static function on(?User $user): bool
    {
        $flags = app(Flags::class);

        return $flags->exists(self::FLAG) && $flags->onFor(self::FLAG, $user);
    }

    /** Off for this person: every operation answers as if it weren't there. */
    /** Their own songs from the creator too (own_choir_songs). */
    public static function songsOn(?User $user): bool
    {
        $flags = app(Flags::class);

        return self::on($user) && $flags->exists('own_choir_songs') && $flags->onFor('own_choir_songs', $user);
    }

    public static function ensureOn(User $user): void
    {
        if (! self::on($user)) {
            throw new Refusal(404, 'not_found');
        }
    }

    public static function termsVersion(): int
    {
        return once(static fn (): int => app(LegalText::class)->exists(self::TERMS) ? (int) app(LegalText::class)->read(self::TERMS)['version'] : 1);
    }

    public static function consented(User $user): bool
    {
        return OwnChoirConsent::query()->where('user_id', $user->id)->where('version', self::termsVersion())->exists();
    }

    /**
     * @return array{freeLeft: int, monthLeft: int, todayLeft: int, plus: bool}
     */
    public static function quota(User $user): array
    {
        $counted = self::counted($user);
        $plus = app(Plans::class)->isPlus($user);

        return [
            'freeLeft' => max(0, (int) config('own_choir.free_songs') - (clone $counted)->count()),
            'monthLeft' => $plus ? max(0, (int) config('own_choir.plus_month') - (clone $counted)->where('created_at', '>=', now()->startOfMonth())->count()) : 0,
            'todayLeft' => max(0, (int) config('own_choir.daily') - (clone $counted)->where('created_at', '>=', now()->subDay())->count()),
            'plus' => $plus,
        ];
    }

    /**
     * The songs that count toward a singer's limits (the reviews: no way to get a render back by forcing a failure):
     * every song that reached the renderer (tries > 0), whether made, deleted after, or failed through anything the
     * singer could cause (an unusable clip, their voice deleted mid-render); and every song queued for it. A song gives
     * its place back only when it never reached the renderer (never sent, a clip that failed its check, deleted before its
     * turn) or failed through ours (the renderer's own failure, its gates).
     *
     * @return Builder<OwnChoirRequest>
     */
    public static function counted(User $user): Builder
    {
        return OwnChoirRequest::query()->where('user_id', $user->id)->where(fn (Builder $query) => $query
            ->whereIn('status', [OwnChoirStatus::Queued->value, OwnChoirStatus::Rendering->value, OwnChoirStatus::Ready->value])
            ->orWhere(fn (Builder $tried) => $tried->where('tries', '>', 0)->where(fn (Builder $why) => $why
                ->where('status', OwnChoirStatus::Deleted->value)
                ->orWhere(fn (Builder $failed) => $failed->where('status', OwnChoirStatus::Failed->value)->whereNotIn('failure', self::OURS)))));
    }

    /** Failures that are ours, not the singer's: their song is given back. */
    public const OURS = ['render_failed', 'gates_failed'];

    /**
     * The limit a new song would pass, or null.
     *
     * @return 'free'|'month'|'day'|null
     */
    public static function limitReached(User $user): ?string
    {
        $quota = self::quota($user);

        return match (true) {
            $quota['todayLeft'] === 0 => 'day',
            $quota['plus'] && $quota['monthLeft'] === 0 => 'month',
            ! $quota['plus'] && $quota['freeLeft'] === 0 => 'free',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function state(User $user): array
    {
        $consent = OwnChoirConsent::query()->where('user_id', $user->id)->first();

        $voice = self::voice($user);

        return [
            'consent' => $consent === null ? null : ['version' => $consent->version, 'acceptedAt' => $consent->accepted_at->utc()->toIso8601ZuluString()],
            'voice' => $voice === null ? null : [
                'savedAt' => $voice->saved_at?->utc()->toIso8601ZuluString(),
                'lastUsedAt' => ($voice->last_used_at ?? $voice->saved_at)?->utc()->toIso8601ZuluString(),
                'expiresAt' => $voice->expiresAt()->utc()->toIso8601ZuluString(),
                'wordless' => $voice->wordless,
            ],
            'termsVersion' => self::termsVersion(),
            'quota' => self::quota($user),
            'requests' => array_values(OwnChoirRequest::query()->with(['templateVersion.template', 'parts'])->where('user_id', $user->id)->where('status', '!=', OwnChoirStatus::Deleted->value)->orderByDesc('id')->get()
                ->map(static fn (OwnChoirRequest $r): array => self::request($r))->all()),
        ];
    }

    /** Anything of theirs kept: consent, a kept voice, or a song. */
    public static function hasData(User $user): bool
    {
        return OwnChoirConsent::query()->where('user_id', $user->id)->exists()
            || OwnChoirVoice::query()->where('user_id', $user->id)->exists()
            || OwnChoirRequest::query()->where('user_id', $user->id)->where('status', '!=', OwnChoirStatus::Deleted->value)->exists();
    }

    /** Their kept voice, ready to use; null without one. */
    public static function voice(User $user): ?OwnChoirVoice
    {
        return OwnChoirVoice::query()->where('user_id', $user->id)->where('status', 'ready')->orderByDesc('id')->first();
    }

    /**
     * @return array<string, mixed>
     */
    public static function request(OwnChoirRequest $request): array
    {
        $ready = $request->status === OwnChoirStatus::Ready;
        // Only a deleted song outlives its version, and deleted songs are never shown.
        $version = $request->templateVersion ?? throw new \LogicException('A shown song has its version.');

        return [
            'id' => $request->sqid(),
            'templateId' => (string) $version->template->slug,
            'title' => (string) $version->template->title,
            'version' => $version->version,
            'status' => $request->status->value,
            'failure' => $request->failure,
            'createdAt' => $request->created_at?->utc()->toIso8601ZuluString(),
            'readyAt' => $request->ready_at?->utc()->toIso8601ZuluString(),
            'seen' => $request->seen_at !== null,
            'parts' => $ready ? array_values($request->parts->where('status', 'ready')->whereNotNull('media_key')->map(static fn (OwnChoirPart $p): array => self::recording($p))->all()) : [],
        ];
    }

    /**
     * A part as the app plays it (guide.recording.json): an AI voice, never credited, at a private link.
     *
     * @return array<string, mixed>
     */
    public static function recording(OwnChoirPart $part): array
    {
        return [
            'id' => $part->sqid(),
            'partKey' => $part->part_key,
            'url' => Storage::disk('private')->temporaryUrl((string) $part->media_key, now()->addMinutes((int) config('own_choir.link_minutes'))),
            'sampleRate' => $part->sample_rate,
            'frames' => $part->frames,
            'offsetFrames' => 0,
            'credit' => null,
            'creditHandle' => null,
            'source' => 'ai',
            'aiBasedOn' => null,
        ];
    }
}
