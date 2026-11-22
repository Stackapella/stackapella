<?php

declare(strict_types=1);

namespace App\Actions\OwnChoir;

use App\Actions\Counts\RecordCounts;
use App\Actions\Projects\Owned;
use App\Enums\OwnChoirStatus;
use App\Exceptions\Refusal;
use App\Jobs\CheckChoirClip;
use App\Models\AuditLog;
use App\Models\OwnChoirConsent;
use App\Models\OwnChoirMember;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\OwnChoirVoice;
use App\Models\Template;
use App\Models\User;
use App\Services\Cloudflare\Turnstile;
use App\Support\OwnChoir\OwnChoir;
use App\Support\Storage\ReceivedUploads;
use App\Support\Storage\Uploads;
use App\Support\Templates\Catalogue;
use App\Support\Trust\TrustLevel;
use App\Support\Trust\WordLists;
use App\Support\Usage\UploadBytes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The singer's side of Hear yourself as a choir (ownChoir.*, track B phase 3): consenting, asking for a song with a clip
 * recorded in the app, seeing it, and deleting a song or everything. Keep nothing: a clip belongs to its one request
 * and goes the moment that song finishes (FinishChoirJob, CheckChoirClip).
 */
final class OwnChoirRequests
{
    public function __construct(private readonly Uploads $uploads, private readonly Catalogue $catalogue, private readonly UploadBytes $uploadBytes) {}

    public function consent(User $user, int $version, ?string $ip = null): void
    {
        OwnChoir::ensureOn($user);

        if ($version !== OwnChoir::termsVersion()) {
            throw new Refusal(409, 'agreement_changed');
        }

        // Under the account's lock: two consents at once write one row, never a unique-index error.
        DB::transaction(function () use ($user, $version): void {
            Owned::lockAccount($user);
            $consent = OwnChoirConsent::query()->where('user_id', $user->id)->lockForUpdate()->first() ?? new OwnChoirConsent;
            $consent->forceFill(['user_id' => $user->id, 'version' => $version, 'accepted_at' => now()])->save();
        });
        AuditLog::record('own_choir.consent', $user, $user, after: ['version' => $version], ip: $ip);
    }

    /**
     * A song in their voice: from a new clip (the voice prepared from it replaces their kept one), or with `templateId`
     * alone from their kept voice (queued at once: a singer back for more).
     *
     * @param  array{templateId: string, partKey?: string|null, offsetFrames?: int|null, sampleRate?: int|null, frames?: int|null, bytes?: int|null, sha256?: string|null, turnstileToken?: string|null}  $clip
     * @return array{request: OwnChoirRequest, put: array{url: string, method: string, headers: array<string, string>, expiresAt: \DateTimeInterface}|null}
     */
    public function start(User $user, array $clip, ?string $ip = null, bool $staffTest = false): array
    {
        OwnChoir::ensureOn($user);

        if (! OwnChoir::consented($user)) {
            throw new Refusal(403, 'consent_required');
        }

        // A confirmed, lasting address before anything is rendered (the GPU review: no farming the free song).
        if (app(TrustLevel::class)->of($user->fresh() ?? $user) < TrustLevel::VERIFIED) {
            if ($user->email !== null && WordLists::isDisposableEmail($user->email)) {
                throw ValidationException::withMessages(['email' => __('validation.own_choir_lasting_email')]);
            }

            throw new Refusal(403, 'email_unverified');
        }

        // And Turnstile before their first song, where it's set up.
        // (Staff's console test, app:own-choir:test-request, has no browser to show Turnstile: it's run by hand on the server.)
        if (! $staffTest && OwnChoir::checkNeeded($user)) {
            $token = $clip['turnstileToken'] ?? null;

            if (! is_string($token) || $token === '') {
                throw new Refusal(403, 'turnstile_required');
            }

            if (! app(Turnstile::class)->passes($token, $ip)) {
                throw new Refusal(403, 'turnstile_failed');
            }
        }

        // A song from the library, or (own_choir_songs) one of their own from the creator, hummed or with words they typed;
        // someone else's own song isn't there for them.
        $template = Template::query()->with('currentVersion')->where('slug', $clip['templateId'])->whereNull('deleted_at')
            ->where(fn ($query) => $query->whereNull('owner_id')->when(OwnChoir::songsOn($user), fn ($own) => $own->orWhere('owner_id', $user->id)))->first();
        $version = $template?->currentVersion;
        $parts = array_column(array_filter((array) ($version->data['parts'] ?? []), 'is_array'), 'key');

        $withClip = isset($clip['sampleRate'], $clip['frames'], $clip['bytes'], $clip['sha256'], $clip['partKey']);

        if ($template === null || $version === null || ($withClip && ! in_array($clip['partKey'], $parts, true))) {
            throw new Refusal(404, 'not_found');
        }

        if ($template->isOfficial() && $this->catalogue->grant($template, $user) === null) {
            throw new Refusal(403, 'account_required');
        }

        // What it would cost the renderer: an own song can be any length, so it's capped.
        if (self::tooLong((array) $version->data)) {
            throw new Refusal(409, 'song_too_long');
        }

        // What it costs, and how it's queued: paid Plus first, and on a busy day it waits for tomorrow.
        $cost = [
            'part_minutes' => OwnChoir::partMinutes((array) $version->data),
            'own' => ! $template->isOfficial(),
            'priority' => OwnChoir::tier($user) === 'paid' ? 1 : 0,
            'starts_after' => OwnChoir::busyToday() ? now()->addDay()->startOfDay() : null,
            'email_key' => OwnChoir::emailKey($user),
        ];

        return DB::transaction(function () use ($user, $clip, $version, $withClip, $cost): array {
            Owned::lockAccount($user);

            if (OwnChoirRequest::query()->where('user_id', $user->id)->whereNull('parent_request_id')->whereIn('status', [OwnChoirStatus::Uploading->value, OwnChoirStatus::Queued->value, OwnChoirStatus::Rendering->value])->exists()) {
                throw new Refusal(409, 'request_open');
            }

            if (($which = OwnChoir::limitReached($user, $cost['part_minutes'])) !== null) {
                throw new Refusal(403, 'quota_reached', ['which' => $which, ...($which === 'minutes' ? ['needed' => $cost['part_minutes']] : [])]);
            }

            if (! $withClip) {
                $voice = OwnChoir::voice($user);

                if ($voice === null) {
                    throw new Refusal(409, 'voice_required');
                }

                $request = new OwnChoirRequest;
                $request->forceFill(['user_id' => $user->id, 'template_version_id' => $version->id, 'own_choir_voice_id' => $voice->id, 'from_voice' => true, 'status' => OwnChoirStatus::Queued, ...$cost])->save();
                $this->ensureFree($user, $request);
                $this->countFriendOwn($user, $request->id);
                app(RecordCounts::class)->server('own_choir_requested', true, null);
                app(RecordCounts::class)->server('own_choir_again', true, null);
                $this->countOwn($version->id);

                return ['request' => $request, 'put' => null];
            }

            $request = new OwnChoirRequest(['part_key' => $clip['partKey']]);
            $this->uploadBytes->count($user, (int) $clip['bytes']);
            $request->forceFill([
                'user_id' => $user->id,
                'template_version_id' => $version->id,
                'offset_frames' => $clip['offsetFrames'] ?? null,
                'status' => OwnChoirStatus::Uploading,
                // Under uploads/, which the private bucket's lifecycle expires after a day.
                'upload_key' => 'uploads/choir/'.Str::lower(Str::random(32)).'.wav',
                'sample_rate' => $clip['sampleRate'],
                'frames' => $clip['frames'],
                'bytes' => $clip['bytes'],
                'sha256' => strtolower((string) $clip['sha256']),
                ...$cost,
            ])->save();
            $this->ensureFree($user, $request);
            $this->countFriendOwn($user, $request->id);

            return ['request' => $request, 'put' => $this->uploads->presign((string) $request->upload_key, 'audio/wav', (int) $request->bytes)];
        });
    }

    public function complete(User $user, OwnChoirRequest $request): OwnChoirRequest
    {
        OwnChoir::ensureOn($user);
        $this->ensureMine($user, $request);

        if ($request->status !== OwnChoirStatus::Uploading) {
            return $request;
        }

        $received = app(ReceivedUploads::class)->receive((string) $request->upload_key, (int) $request->bytes, (string) $request->sha256, 'choir/clips');

        if ($received === null) {
            return $request->refresh();
        }

        $claimed = OwnChoirRequest::query()->whereKey($request->id)->where('status', OwnChoirStatus::Uploading->value)
            ->update(['status' => OwnChoirStatus::Queued->value, 'clip_key' => $received]);

        if ($claimed === 1) {
            CheckChoirClip::dispatch($request->id);
            app(RecordCounts::class)->server('own_choir_requested', true, null);
            if ($request->template_version_id !== null) {
                $this->countOwn($request->template_version_id);
            }
        } else {
            Storage::disk('private')->delete($received);
        }

        return $request->refresh();
    }

    public function seen(User $user, OwnChoirRequest $request): OwnChoirRequest
    {
        // A song they joined (choir together): their parts in it are seen.
        $member = OwnChoirMember::query()->where('own_choir_request_id', $request->id)->where('user_id', $user->id)->first();

        if ($member !== null) {
            $member->seen_at ??= now();
            $member->save();

            return $request;
        }

        // Whatever the switch: a singer can always see and delete what's theirs.
        $this->ensureMine($user, $request);

        if ($request->seen_at === null && $request->status === OwnChoirStatus::Ready) {
            $request->forceFill(['seen_at' => now()])->save();
        }

        return $request;
    }

    public function delete(User $user, OwnChoirRequest $request): void
    {
        // Whatever the switch: a singer can always see and delete what's theirs.
        $this->ensureMine($user, $request);
        self::purge($request);
    }

    /** Their kept voice, deleted at once; songs being made from it fail `reference_gone`. */
    public function deleteVoice(User $user, ?string $ip = null): void
    {
        // Whatever the switch: a singer can always see and delete what's theirs.

        if (! OwnChoirVoice::query()->where('user_id', $user->id)->exists()) {
            throw new Refusal(404, 'not_found');
        }

        self::dropVoices($user);
        AuditLog::record('own_choir.voice_deleted', $user, $user, ip: $ip);
    }

    /**
     * Every voice of theirs, files and all, read under their locks (completeReference takes them too, so a reference
     * arriving meanwhile is never kept); a song waiting on one fails `reference_gone`, its clip and parts gone.
     */
    public static function dropVoices(User $user): void
    {
        // Their voice gone: their parts in every choir they joined go with it (the owners' play again).
        ChoirTogether::leaveAll($user);

        [$files, $waiting] = DB::transaction(function () use ($user): array {
            $voices = OwnChoirVoice::query()->where('user_id', $user->id)->lockForUpdate()->get();
            $waiting = OwnChoirRequest::query()->whereIn('own_choir_voice_id', $voices->modelKeys())->whereIn('status', [OwnChoirStatus::Queued->value, OwnChoirStatus::Rendering->value])->pluck('id')->all();
            OwnChoirVoice::query()->whereKey($voices->modelKeys())->delete();

            return [array_values(array_filter($voices->flatMap(static fn (OwnChoirVoice $v): array => [$v->upload_key, (string) $v->reference_key])->all())), $waiting];
        });
        Storage::disk('private')->delete($files);

        foreach (OwnChoirRequest::query()->whereKey($waiting)->get() as $request) {
            ChoirJobs::failAndClean($request, 'reference_gone');
        }
    }

    /** Everything: every song, part and clip, their kept voice, and the consent. */
    public function forget(User $user, ?string $ip = null): void
    {
        // Whatever the switch: a singer can always see and delete what's theirs.
        self::forgetAll($user);
        AuditLog::record('own_choir.forgotten', $user, $user, ip: $ip);
    }

    /** As forget(), switch or not: an account's deletion takes it all too (DeleteAccount). */
    public static function forgetAll(User $user): void
    {
        foreach (OwnChoirRequest::query()->where('user_id', $user->id)->where('status', '!=', OwnChoirStatus::Deleted->value)->get() as $request) {
            self::purge($request);
        }

        self::dropVoices($user);
        OwnChoirConsent::query()->where('user_id', $user->id)->delete();
    }

    /**
     * More parts, or a part longer, than one song may cost the renderer (own_choir.max_parts, max_part_seconds).
     *
     * @param  array<string, mixed>  $data
     */
    public static function tooLong(array $data): bool
    {
        $parts = array_values(array_filter((array) ($data['parts'] ?? []), 'is_array'));
        $beat = 60 / max(1.0, (float) ($data['tempo'] ?? 0));

        if (count($parts) > (int) config('own_choir.max_parts')) {
            return true;
        }

        // All its singing together, at most own_choir.max_total_seconds, so one song fits one claim (the GPU review).
        if (OwnChoir::partMinutes($data) * 60 > (float) config('own_choir.max_total_seconds')) {
            return true;
        }

        foreach ($parts as $part) {
            foreach ((array) ($part['guide']['notes'] ?? []) as $note) {
                if (is_array($note) && ((float) ($note['beat'] ?? 0) + (float) ($note['length'] ?? 0)) * $beat > (float) config('own_choir.max_part_seconds')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Every song on these template versions purged (their template is being deleted for good): files gone, rows kept.
     *
     * @param  list<int>  $versionIds
     */
    public static function purgeForVersions(array $versionIds): void
    {
        if ($versionIds === []) {
            return;
        }

        foreach (OwnChoirRequest::query()->whereIn('template_version_id', $versionIds)->where('status', '!=', OwnChoirStatus::Deleted->value)->get() as $request) {
            self::purge($request);
        }
    }

    /**
     * A song deleted: its clip, every part and a voice still being prepared from it go (rows locked, files after the
     * commit, so an encode finishing meanwhile keeps nothing). The row stays, emptied, so it still counts toward the
     * quota; the account's deletion takes it.
     */
    /** A friend's render on another's song, gone for good: its files, then its row. */
    public static function purgeJob(OwnChoirRequest $job): void
    {
        self::purge($job);
        OwnChoirRequest::query()->whereKey($job->id)->delete();
    }

    private static function purge(OwnChoirRequest $request): void
    {
        // The owner's song going: everyone's part in it goes too.
        if ($request->parent_request_id === null) {
            ChoirTogether::songGone($request);
        }

        $files = DB::transaction(function () use ($request): array {
            $locked = OwnChoirRequest::query()->whereKey($request->id)->lockForUpdate()->first();

            if ($locked === null) {
                return [];
            }

            $parts = OwnChoirPart::query()->where('own_choir_request_id', $locked->id)->lockForUpdate()->get();
            $preparing = OwnChoirVoice::query()->whereKey($locked->own_choir_voice_id ?? 0)->where('status', '!=', 'ready')->lockForUpdate()->get();
            $files = [
                ...array_filter([$locked->upload_key, $locked->clip_key]),
                ...$parts->flatMap(static fn (OwnChoirPart $p): array => ChoirJobs::partFiles($p))->all(),
                ...$preparing->flatMap(static fn (OwnChoirVoice $v): array => array_filter([$v->upload_key, $v->reference_key]))->all(),
            ];
            OwnChoirPart::query()->whereKey($parts->modelKeys())->delete();
            OwnChoirVoice::query()->whereKey($preparing->modelKeys())->delete();
            $locked->forceFill(['status' => OwnChoirStatus::Deleted, 'upload_key' => null, 'clip_key' => null, 'claimed_at' => null, 'claim_token' => null, 'part_key' => null, 'offset_frames' => null, 'sha256' => null])->save();
            OwnChoir::releaseFree($locked);

            return array_values($files);
        });
        Storage::disk('private')->delete($files);
    }

    /** A song of their own from the creator (own_choir_own), counted beside the library's. */
    /** A joiner's own first song later (choir together's loop): counted once. */
    private function countFriendOwn(User $user, int $requestId): void
    {
        if (OwnChoirMember::query()->where('user_id', $user->id)->exists() && ! OwnChoirRequest::query()->where('user_id', $user->id)->whereNull('parent_request_id')->whereKeyNot($requestId)->exists()) {
            app(RecordCounts::class)->server('own_choir_friend_own', true, null);
        }
    }

    private function countOwn(int $versionId): void
    {
        if (Template::query()->whereNotNull('owner_id')->whereHas('versions', fn ($query) => $query->whereKey($versionId))->exists()) {
            app(RecordCounts::class)->server('own_choir_own', true, null);
        }
    }

    /**
     * A made song's parts at full quality, for Download: Plus only (the billing review; the state's parts are the
     * listening copy), each link for own_choir.download_minutes.
     *
     * @return list<array{partKey: string, url: string, sampleRate: int, frames: int, offsetFrames: int}>
     */
    public function download(User $user, OwnChoirRequest $request): array
    {
        $this->ensureMine($user, $request);

        if ($request->status !== OwnChoirStatus::Ready) {
            throw new Refusal(404, 'not_found');
        }

        // Their own Plus: a choir seat doesn't cover AI songs.
        if (OwnChoir::tier($user) === 'free') {
            throw new Refusal(403, 'plus_required');
        }

        $until = now()->addMinutes((int) config('own_choir.download_minutes'));

        return array_values($request->parts()->where('status', 'ready')->whereNotNull('media_key')->orderBy('part_key')->get()
            ->map(static fn (OwnChoirPart $part): array => ['partKey' => $part->part_key, 'url' => Storage::disk('private')->temporaryUrl((string) $part->media_key, $until), 'sampleRate' => $part->sample_rate, 'frames' => $part->frames, 'offsetFrames' => 0])->all());
    }

    /**
     * A free singer's song that uses up the free allowance (the one song, in production) is kept against their address
     * and sign-in, once ever; one already had there refuses (it rolls back).
     */
    private function ensureFree(User $user, OwnChoirRequest $request): void
    {
        if (OwnChoir::tier($user) !== 'free' || OwnChoir::counted($user, byEmail: true)->count() < (int) config('own_choir.free_songs')) {
            return;
        }

        if (! OwnChoir::takeFree($user, $request)) {
            throw new Refusal(403, 'quota_reached', ['which' => 'free']);
        }
    }

    private function ensureMine(User $user, OwnChoirRequest $request): void
    {
        if ($request->user_id !== $user->id || $request->status === OwnChoirStatus::Deleted) {
            throw new Refusal(404, 'not_found');
        }
    }
}
