<?php

declare(strict_types=1);

namespace App\Support\OwnChoir;

use App\Actions\OwnChoir\ChoirTogether;
use App\Actions\OwnChoir\OwnChoirRequests;
use App\Enums\OwnChoirStatus;
use App\Exceptions\Refusal;
use App\Models\OwnChoirConsent;
use App\Models\OwnChoirFreeSong;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\OwnChoirVoice;
use App\Models\User;
use App\Support\Flags;
use App\Support\Legal\LegalText;
use App\Support\Usage\Plans;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
     * Who pays (the GPU review, 2026-11-21): `paid` Plus (Stripe past any trial, the stores, an invoice), `comped` Plus
     * not paid for (a trial, a school code, a coach, a grant), or `free`.
     *
     * @return 'paid'|'comped'|'free'
     */
    public static function tier(User $user): string
    {
        // Their own Plus only: a choir seat covers singing with the group, not AI songs (the coordinator for Hillel).
        if (app(Plans::class)->ownPlan($user) !== 'plus') {
            return 'free';
        }

        $now = now()->toDateTimeString();
        $store = $user->getAttributes()['store_plus_until'] ?? null;
        $invoice = $user->getAttributes()['plus_paid_until'] ?? null;
        $stripe = $user->subscription('default');

        return ($stripe !== null && $stripe->valid() && ! $stripe->onTrial()) || (is_string($store) && $store > $now) || (is_string($invoice) && $invoice > $now) ? 'paid' : 'comped';
    }

    /**
     * A song's part-minutes: each part's last note's end (beat 0 to it) at the song's tempo, summed over its parts.
     *
     * @param  array<string, mixed>  $data
     */
    public static function partMinutes(array $data): float
    {
        $tempo = max(1.0, (float) ($data['tempo'] ?? 0));
        $beats = 0.0;

        foreach (array_filter((array) ($data['parts'] ?? []), 'is_array') as $part) {
            $end = 0.0;

            foreach ((array) ($part['guide']['notes'] ?? []) as $note) {
                if (is_array($note)) {
                    $end = max($end, (float) ($note['beat'] ?? 0) + (float) ($note['length'] ?? 0));
                }
            }

            $beats += $end;
        }

        return round($beats / $tempo, 2);
    }

    /** The email a free song is counted against, as a keyed hash: dots and +tags never make a new address. */
    public static function emailKey(User $user): ?string
    {
        $email = strtolower(trim((string) $user->email));

        if (! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = explode('+', $local, 2)[0];

        $dotless = array_values((array) config('own_choir.dotless_domains'));

        if (in_array($domain, $dotless, true)) {
            [$local, $domain] = [str_replace('.', '', $local), (string) $dotless[0]];
        }

        return hash_hmac('sha256', $local.'@'.$domain, (string) config('app.key'));
    }

    /**
     * Every key the free song is counted against (the coordinator for Hillel: once per email address, ever): the
     * normalised address, and the Apple and Google account ids, each as a keyed one-way hash.
     *
     * @return list<string>
     */
    public static function freeKeys(User $user): array
    {
        $key = (string) config('app.key');
        $google = $user->getAttributes()['google_id'] ?? null;
        $apple = $user->getAttributes()['apple_id'] ?? null;

        return array_values(array_filter([
            self::emailKey($user),
            is_string($google) && $google !== '' ? hash_hmac('sha256', 'google:'.$google, $key) : null,
            is_string($apple) && $apple !== '' ? hash_hmac('sha256', 'apple:'.$apple, $key) : null,
        ]));
    }

    /**
     * The free song taken by this request, under each of the singer's keys: false when one is already taken (another
     * account, or one since deleted, had it). Inside the request's transaction.
     */
    public static function takeFree(User $user, OwnChoirRequest $request): bool
    {
        try {
            foreach (self::freeKeys($user) as $key) {
                $row = new OwnChoirFreeSong(['key_hash' => $key]);
                $row->forceFill(['own_choir_request_id' => $request->id])->save();
            }
        } catch (UniqueConstraintViolationException) {
            // Another account (or one since deleted) had it.
            return false;
        }

        return true;
    }

    /**
     * The free song given back, as the quota gives a song back: it never reached the renderer, or failed through ours
     * (a gates failure on the singer's own song stays theirs).
     */
    public static function releaseFree(OwnChoirRequest $request): void
    {
        $ours = $request->status === OwnChoirStatus::Failed && in_array($request->failure, self::OURS, true) && ! ($request->own && $request->failure === 'gates_failed');
        // Failed before any GPU work (an unusable clip, a voice gone): never counted (Hillel, 2026-11-23).
        $unspent = $request->status === OwnChoirStatus::Failed && $request->gpu_seconds <= 0;

        if ($request->tries === 0 || $ours || $unspent) {
            OwnChoirFreeSong::query()->where('own_choir_request_id', $request->id)->delete();
        }
    }

    /** The free song already used by this address or sign-in, on this account or one since deleted. */
    public static function freeUsed(User $user): bool
    {
        return OwnChoirFreeSong::query()->whereIn('key_hash', self::freeKeys($user))->exists();
    }

    /**
     * Paid Plus's month (the coordinator for Hillel, 2026-11-21): a month of the subscription's own billing, anchored on
     * the day it renews (or its paid-until day from the stores or an invoice), never the calendar month.
     */
    public static function paidMonthStart(User $user): Carbon
    {
        $attributes = $user->getAttributes();
        $anchor = collect([$attributes['plus_renews_at'] ?? null, $attributes['store_plus_until'] ?? null, $attributes['plus_paid_until'] ?? null])
            ->first(static fn (mixed $at): bool => is_string($at));
        $start = is_string($anchor) ? Carbon::parse($anchor) : now()->startOfMonth();

        while ($start->gt(now())) {
            $start->subMonthNoOverflow();
        }

        while ($start->copy()->addMonthNoOverflow()->lte(now())) {
            $start->addMonthNoOverflow();
        }

        return $start;
    }

    /**
     * @return array{freeLeft: int, monthLeft: int, todayLeft: int, plus: bool, unit: 'songs'|'minutes', minutesLeft: float|null, minutesPerMonth: float|null, resetsAt: string|null}
     */
    public static function quota(User $user): array
    {
        $tier = self::tier($user);
        $since = $tier === 'paid' ? self::paidMonthStart($user) : now()->subDays((int) config('own_choir.window_days'));
        $when = DB::raw('coalesce(first_claimed_at, created_at)');
        $window = $tier === 'paid' ? self::attempted($user) : self::counted($user);
        $window->where($when, '>=', $since);
        $oldest = (clone $window)->min($when);
        $resets = $tier === 'paid' ? $since->copy()->addMonthNoOverflow() : (is_string($oldest) ? Carbon::parse($oldest)->addDays((int) config('own_choir.window_days')) : null);

        return [
            'freeLeft' => self::freeUsed($user) ? 0 : max(0, (int) config('own_choir.free_songs') - self::counted($user, byEmail: true)->count()),
            // Plus not paid for: a friend's parts on their song count as one of its songs too.
            'monthLeft' => $tier === 'comped' ? max(0, (int) config('own_choir.comped_songs') - (clone $window)->count() - self::attempted($user)->where('bill_to_user_id', $user->id)->where($when, '>=', $since)->count()) : 0,
            'todayLeft' => max(0, (int) config('own_choir.daily') - self::attempted($user)->where('created_at', '>=', now()->subDay())->count()),
            'plus' => $tier !== 'free',
            'unit' => $tier === 'paid' ? 'minutes' : 'songs',
            'minutesLeft' => $tier === 'paid' ? max(0.0, round((float) config('own_choir.plus_minutes') - (float) (clone $window)->sum('part_minutes'), 1)) : null,
            'minutesPerMonth' => $tier === 'paid' ? (float) config('own_choir.plus_minutes') : null,
            'resetsAt' => $tier !== 'free' && $resets !== null ? $resets->utc()->toIso8601ZuluString() : null,
        ];
    }

    /** Today's renders across everyone are at the ceiling: new songs wait for tomorrow. */
    public static function busyToday(): bool
    {
        return (float) OwnChoirRequest::query()->where('first_claimed_at', '>=', now()->startOfDay())->sum('part_minutes') >= (float) config('own_choir.daily_part_minutes');
    }

    /** Turnstile before a person's first song, where it's set up. */
    public static function checkNeeded(User $user): bool
    {
        return (string) config('services.turnstile.site_key') !== '' && ! OwnChoirRequest::query()->where('user_id', $user->id)->whereNull('parent_request_id')->exists();
    }

    /**
     * The songs that count toward a singer's limits (the reviews: no way to get a render back by forcing a failure):
     * every song that reached the renderer (tries > 0), whether made, deleted after, or failed through anything the
     * singer could cause (an unusable clip, their voice deleted mid-render); and every song queued for it. A song gives
     * its place back only when it never reached the renderer (never sent, a clip that failed its check, deleted before its
     * turn), failed through ours (the renderer's own failure, its gates), or failed before any GPU work was spent on it
     * (`gpu_seconds` 0: an unusable clip, a voice gone; Hillel, 2026-11-23: "it shouldn't count as my free song"). The
     * daily cap and the minutes (attempted) give back the same failures that spent nothing.
     *
     * @return Builder<OwnChoirRequest>
     */
    public static function counted(User $user, bool $byEmail = false): Builder
    {
        $key = $byEmail ? self::emailKey($user) : null;

        return OwnChoirRequest::query()->whereNull('parent_request_id')->where(fn (Builder $whose) => $whose->where('user_id', $user->id)->when($key !== null, fn (Builder $q) => $q->orWhere('email_key', $key)))->where(fn (Builder $query) => $query
            ->whereIn('status', [OwnChoirStatus::Queued->value, OwnChoirStatus::Rendering->value, OwnChoirStatus::Ready->value])
            ->orWhere(fn (Builder $tried) => $tried->where('tries', '>', 0)->where(fn (Builder $why) => $why
                ->where('status', OwnChoirStatus::Deleted->value)
                // A gates failure on the singer's own song is theirs (the GPU review): never given back.
                ->orWhere(fn (Builder $failed) => $failed->where('status', OwnChoirStatus::Failed->value)->where('gpu_seconds', '>', 0)->where(fn (Builder $why) => $why->whereNotIn('failure', self::OURS)->orWhere(fn (Builder $own) => $own->where('own', true)->where('failure', 'gates_failed')))))));
    }

    /**
     * The songs that count toward the daily cap: every song waiting for the renderer or that reached it (tries > 0),
     * however it ended, ours included. A singer can cause even our failures (a song at the limit expiring after its
     * tries), so only the free and monthly allowances give those back; the cap on what one person costs a day never does.
     *
     * @return Builder<OwnChoirRequest>
     */
    public static function attempted(User $user): Builder
    {
        // Their own songs, and friends' parts on their songs (charged to them: choir together).
        // (A friend's parts waiting for the minutes aren't spent yet: they count once released.)
        return OwnChoirRequest::query()->where('held_for_minutes', false)->where(fn (Builder $whose) => $whose->where(fn (Builder $own) => $own->where('user_id', $user->id)->whereNull('parent_request_id'))->orWhere('bill_to_user_id', $user->id))->where(fn (Builder $query) => $query
            ->whereIn('status', [OwnChoirStatus::Queued->value, OwnChoirStatus::Rendering->value, OwnChoirStatus::Ready->value])
            ->orWhere(fn (Builder $tried) => $tried->where('tries', '>', 0)->where(fn (Builder $spent) => $spent->where('status', '!=', OwnChoirStatus::Failed->value)->orWhere('gpu_seconds', '>', 0))));
    }

    /** Failures that are ours, not the singer's: their song is given back to the free and monthly allowances. */
    public const OURS = ['render_failed', 'gates_failed'];

    /**
     * The limit a new song would pass, or null.
     *
     * @return 'free'|'month'|'day'|'minutes'|null
     */
    public static function limitReached(User $user, float $needed = 0.0): ?string
    {
        $quota = self::quota($user);

        return match (true) {
            $quota['todayLeft'] === 0 => 'day',
            $quota['unit'] === 'minutes' => ($quota['minutesLeft'] ?? 0.0) <= 0.0 || ($quota['minutesLeft'] ?? 0.0) < $needed ? 'minutes' : null,
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
                // Their original clip, to compare before and after (2026-11-23): theirs alone, a short-lived link.
                'clipListen' => $voice->clip_key === null ? null : [
                    'url' => Storage::disk('private')->temporaryUrl($voice->clip_key, now()->addHour()),
                    'expiresAt' => now()->addHour()->utc()->toIso8601ZuluString(),
                    // Where it sits, to place it under a song it was sung to (the recording level).
                    ...self::clipPlace($voice),
                ],
            ],
            'termsVersion' => self::termsVersion(),
            'quota' => $quota = self::quota($user),
            'busyToday' => self::busyToday(),
            'checkNeeded' => self::checkNeeded($user),
            'requests' => array_values(OwnChoirRequest::query()->with(['templateVersion.template', 'parts'])->where('user_id', $user->id)->whereNull('parent_request_id')->where('status', '!=', OwnChoirStatus::Deleted->value)->orderByDesc('id')->get()
                ->map(static fn (OwnChoirRequest $r): array => self::request($r, $quota['plus'], $user))->all()),
            // Choirs they sing in (another singer's song they joined).
            'joined' => ChoirTogether::joined($user),
            // The tunes they may sing their clip to, the first the default (Hillel, 2026-11-23).
            'clipSongs' => ClipTune::songs(),
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
    /**
     * Where a voice's kept clip sits: the tune it sang, its numbers, beat 0 in it, the tune's move to their first note.
     *
     * @return array{templateId: string, sampleRate: int, frames: int, offsetFrames: int|null, clipTranspose: int}
     */
    private static function clipPlace(OwnChoirVoice $voice): array
    {
        $origin = self::voiceOrigin($voice);

        return [
            'templateId' => (string) $origin?->clipVersion()?->template?->slug,
            'sampleRate' => (int) ($origin->sample_rate ?? 48000),
            'frames' => (int) ($origin->frames ?? 0),
            'offsetFrames' => $origin?->offset_frames,
            'clipTranspose' => (int) ($origin->clip_transpose ?? 0),
        ];
    }

    /**
     * A song's lead as the app plays it (a part's shape): its singer's own, levelled, in the key it was rendered in.
     *
     * @return array{id: string, partKey: string, url: string, sampleRate: int, frames: int, offsetFrames: int}|null
     */
    public static function leadTake(OwnChoirRequest $request, User $viewer): ?array
    {
        $made = $request->lead['made'] ?? null;

        if ($viewer->id !== $request->user_id || $request->lead_key === null || ! is_array($made)) {
            return null;
        }

        return [
            'id' => 'lead-'.$request->sqid(),
            'partKey' => OwnChoirRequests::melody((array) $request->templateVersion?->data) ?? 'soprano',
            'url' => Storage::disk('private')->temporaryUrl($request->lead_key, now()->addHours(2)),
            'sampleRate' => (int) $made['sampleRate'],
            'frames' => (int) $made['frames'],
            'offsetFrames' => (int) $made['offsetFrames'],
        ];
    }

    /** The song a voice was made from (its clip, the tune it sang), read once a request. */
    public static function voiceOrigin(OwnChoirVoice $voice): ?OwnChoirRequest
    {
        return self::originOf($voice->id);
    }

    /** (Keyed by the voice's id, so every copy of it read in a request shares the one query.) */
    private static function originOf(int $voiceId): ?OwnChoirRequest
    {
        return once(static fn (): ?OwnChoirRequest => OwnChoirRequest::query()->where('own_choir_voice_id', $voiceId)->where('from_voice', false)->whereNotNull('part_key')->oldest('id')->first());
    }

    public static function voice(User $user): ?OwnChoirVoice
    {
        return OwnChoirVoice::query()->where('user_id', $user->id)->where('status', 'ready')->orderByDesc('id')->first();
    }

    /**
     * @param  bool|null  $plus  whether the singer has Plus, when the caller knows already
     * @return array<string, mixed>
     */
    public static function request(OwnChoirRequest $request, ?bool $plus = null, ?User $viewer = null): array
    {
        $viewer ??= $request->loadMissing('user')->user;
        // Downloading a song is Plus's (Hillel, 2026-11-21), decided per song so a later rule needs no contract change:
        // the owner's, with their own Plus; never a friend's on a song they joined.
        $plus = $viewer->id === $request->user_id && ($plus ?? self::tier($viewer) !== 'free');
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
            // Why its clip couldn't be used, for its singer alone (2026-11-23).
            'failureDetail' => $viewer->id === $request->user_id ? $request->failure_detail : null,
            'createdAt' => $request->created_at?->utc()->toIso8601ZuluString(),
            'readyAt' => $request->ready_at?->utc()->toIso8601ZuluString(),
            'startsAfter' => $request->starts_after?->utc()->toIso8601ZuluString(),
            'seen' => $request->seen_at !== null,
            'canDownload' => $plus,
            // Each part's active render: the owner's, or a friend's once theirs is in (choir together).
            'parts' => $ready ? array_values(ChoirTogether::activeParts($request)->map(static fn (OwnChoirPart $p): array => self::recording($p))->all()) : [],
            'progress' => self::progress($request),
            // Their own recording as the lead (2026-11-23), theirs alone.
            'leadTake' => self::leadTake($request, $viewer),
            'canUseRecordingAsLead' => $viewer->id === $request->user_id && $request->lead !== null,
            // "Arranged for a low voice" (2026-11-23).
            'voicing' => $request->voicing,
            // Plus's choir settings (2026-11-23): the mix, how it's sung, what a remake would use, and one on its way.
            'settings' => ['sound' => ChoirSettings::sound($request), 'sung' => ChoirSettings::sung($request)],
            'remakeMinutes' => [
                'standard' => ChoirSettings::remakeMinutes($request, 'standard', $remakeParts = ChoirSettings::remakeParts($request)),
                'fine' => ChoirSettings::remakeMinutes($request, 'fine', $remakeParts),
                // Both octaves: the treble parts twice.
                'fullStandard' => ChoirSettings::remakeMinutes($request, 'standard', $fullParts = ChoirSettings::remakeParts($request, 'full'), 'full'),
                'fullFine' => ChoirSettings::remakeMinutes($request, 'fine', $fullParts, 'full'),
            ],
            'remake' => ($remake = ChoirSettings::remakeOf($request)) === null ? null : [
                'status' => match ($remake->status) {
                    OwnChoirStatus::Rendering => 'rendering',
                    OwnChoirStatus::Failed => 'failed',
                    default => 'queued',
                },
                'progress' => self::progress($remake),
                'settings' => ChoirSettings::sung($remake),
            ],
            'voices' => ChoirTogether::voices($request, $viewer),
            'members' => ChoirTogether::members($request, $viewer),
            'left' => ChoirTogether::left($request, $viewer),
            'invite' => ChoirTogether::inviteHint($request, $viewer),
        ];
    }

    /**
     * Each part's real state while a song is being made (the waiting card): all waiting before the renderer claims it,
     * then making until that part's render is in; [] otherwise. A friend's job lists only their parts.
     *
     * @return list<array{partKey: string, status: string}>
     */
    public static function progress(OwnChoirRequest $request): array
    {
        if (! in_array($request->status, [OwnChoirStatus::Queued, OwnChoirStatus::Rendering], true)) {
            return [];
        }

        $keys = $request->only_parts ?? array_column(array_filter((array) ($request->templateVersion->data['parts'] ?? []), 'is_array'), 'key');
        $ready = $request->status === OwnChoirStatus::Rendering ? OwnChoirPart::query()->where('own_choir_request_id', $request->id)->where('status', 'ready')->pluck('part_key')->all() : [];

        return array_values(array_map(static fn (mixed $key): array => ['partKey' => (string) $key, 'status' => $request->status === OwnChoirStatus::Queued ? 'waiting' : (in_array($key, $ready, true) ? 'ready' : 'making')], $keys));
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
            // The listening copy (the billing review): full quality is ownChoir.requests.download's, with Plus.
            'url' => Storage::disk('private')->temporaryUrl((string) ($part->listen_key ?? $part->media_key), now()->addMinutes((int) config('own_choir.link_minutes'))),
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
