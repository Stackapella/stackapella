<?php

declare(strict_types=1);

namespace App\Actions\OwnChoir;

use App\Actions\Counts\RecordCounts;
use App\Actions\Projects\Owned;
use App\Actions\Sharing\DeleteHarmony;
use App\Enums\OwnChoirStatus;
use App\Exceptions\Refusal;
use App\Jobs\CheckChoirClip;
use App\Jobs\MakeChoirLead;
use App\Models\AuditLog;
use App\Models\OwnChoirConsent;
use App\Models\OwnChoirMember;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\OwnChoirVoice;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\Cloudflare\Turnstile;
use App\Support\OwnChoir\ChoirCounts;
use App\Support\OwnChoir\ChoirSettings;
use App\Support\OwnChoir\ClipTune;
use App\Support\OwnChoir\OwnChoir;
use App\Support\OwnChoir\VoicingTransform;
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
     * @param  array{templateId: string, clipTemplateId?: string|null, clipTranspose?: int|null, guide?: bool|null, octave?: int|null, partKey?: string|null, offsetFrames?: int|null, sampleRate?: int|null, frames?: int|null, bytes?: int|null, sha256?: string|null, turnstileToken?: string|null}  $clip
     * @return array{request: OwnChoirRequest, put: array{url: string, method: string, headers: array<string, string>, expiresAt: \DateTimeInterface}|null, leadPut: array{url: string, method: string, headers: array<string, string>, expiresAt: \DateTimeInterface}|null}
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

        $withClip = isset($clip['sampleRate'], $clip['frames'], $clip['bytes'], $clip['sha256'], $clip['partKey']);

        if ($template === null || $version === null) {
            throw new Refusal(404, 'not_found');
        }

        // The tune the clip sang: Happy Birthday by default, or the song's own (2026-11-23).
        $tune = $withClip ? ClipTune::version($clip['clipTemplateId'] ?? null, $version) : null;

        // (partKey isn't checked: the clip is recorded as its tune's melody, whatever part the app named.)

        // The free song is the song they sang (Hillel, 2026-11-23): their new clip's tune, or the one their saved voice
        // sang; Plus makes any song.
        // (A staff end-to-end test picks its song.)
        if (OwnChoir::tier($user) === 'free' && ! $staffTest) {
            $sang = $tune?->template->slug ?? self::voiceTune($user);

            if ($sang !== null && ClipTune::choirSong($sang) !== $template->slug) {
                throw new Refusal(409, 'free_song_is_your_clip', ['songId' => ClipTune::choirSong($sang)]);
            }
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

        // The lead (2026-11-23): the whole song sung (a clip of its own tune), or a clip tune whose first section is
        // this whole song (Happy Birthday from voice-capture). Either way the song is rendered in the key they sang.
        $lead = $withClip && $tune !== null ? self::lead($clip, $tune, $template, $version) : null;
        $keyShift = $lead === null ? 0 : (int) $lead['keyShift'] + (int) ($clip['clipTranspose'] ?? 0);

        // The low-voice version (2026-11-23): their range (measured, else the tune they just sang in the octave the app
        // heard, else their saved voice's) suits the song's tune an octave down better than as written.
        $octave = $withClip ? (int) ($clip['octave'] ?? 0) : 0;
        $transpose = $withClip ? (int) ($clip['clipTranspose'] ?? 0) : 0;
        $range = $tune !== null ? ChoirTogether::range($user, ClipTune::melody($tune), ChoirTogether::versionNotes($tune), $octave + $transpose) : ChoirTogether::range($user, null, [], 0);
        $melody = ChoirTogether::versionNotes($version)[self::melody((array) $version->data) ?? ''] ?? [];
        $voicing = $range !== null && $melody !== [] && VoicingTransform::isLow($melody, $range) ? 'low' : 'written';

        return DB::transaction(function () use ($user, $clip, $version, $withClip, $cost, $tune, $octave, $transpose, $voicing, $lead, $keyShift): array {
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
                $request->forceFill(['user_id' => $user->id, 'template_version_id' => $version->id, 'own_choir_voice_id' => $voice->id, 'from_voice' => true, 'status' => OwnChoirStatus::Queued, 'voicing' => $voicing, ...$cost])->save();
                $this->ensureFree($user, $request);
                $this->countFriendOwn($user, $request->id);
                app(RecordCounts::class)->server('own_choir_requested', true, null);
                app(RecordCounts::class)->server('own_choir_again', true, null);
                $this->countOwn($version->id);

                return ['request' => $request, 'put' => null, 'leadPut' => null];
            }

            // The clip is always a tune with words (Hillel, 2026-11-23: a bass part of "oo"s gives a voice none), Happy
            // Birthday by default: its melody, sung in any octave the singer likes, whatever part the app named.
            $request = new OwnChoirRequest(['part_key' => ($tune !== null ? ClipTune::melody($tune) : null) ?? $clip['partKey']]);
            $this->uploadBytes->count($user, (int) $clip['bytes'] + (int) ($lead['take']['bytes'] ?? 0));
            $request->forceFill([
                'user_id' => $user->id,
                'template_version_id' => $version->id,
                'clip_template_version_id' => $tune->id,
                'offset_frames' => $clip['offsetFrames'] ?? null,
                'octave' => $octave,
                'clip_transpose' => $transpose,
                'guide' => ($clip['guide'] ?? true) !== false,
                'voicing' => $voicing,
                'lead' => $lead === null ? null : array_diff_key($lead, ['keyShift' => true]),
                'lead_upload_key' => isset($lead['take']) ? 'uploads/choir/'.Str::lower(Str::random(32)).'.wav' : null,
                'key_shift' => $keyShift,
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

            return [
                'request' => $request,
                'put' => $this->uploads->presign((string) $request->upload_key, 'audio/wav', (int) $request->bytes),
                'leadPut' => $request->lead_upload_key === null ? null : $this->uploads->presign($request->lead_upload_key, 'audio/wav', (int) ($lead['take']['bytes'] ?? 0)),
            ];
        });
    }

    public function complete(User $user, OwnChoirRequest $request): OwnChoirRequest
    {
        OwnChoir::ensureOn($user);
        $this->ensureMine($user, $request);

        if ($request->status !== OwnChoirStatus::Uploading) {
            return $request;
        }

        // The whole song sung (the lead) goes up beside the clip: both are in before the song is queued.
        $take = is_array($request->lead['take'] ?? null) ? $request->lead['take'] : null;

        if ($take !== null && $request->lead_upload_key !== null && ! Storage::disk('private')->exists($request->lead_upload_key)) {
            throw new Refusal(409, 'upload_missing');
        }

        $received = app(ReceivedUploads::class)->receive((string) $request->upload_key, (int) $request->bytes, (string) $request->sha256, 'choir/clips');

        if ($received === null) {
            return $request->refresh();
        }

        $leadReceived = $take !== null && $request->lead_upload_key !== null ? app(ReceivedUploads::class)->receive($request->lead_upload_key, (int) $take['bytes'], (string) $take['sha256'], 'choir/leads') : null;

        $claimed = OwnChoirRequest::query()->whereKey($request->id)->where('status', OwnChoirStatus::Uploading->value)
            ->update(['status' => OwnChoirStatus::Queued->value, 'clip_key' => $received, 'lead_upload_key' => $leadReceived]);

        if ($claimed === 1) {
            CheckChoirClip::dispatch($request->id);

            // The lead, levelled to the parts and kept as AAC: the whole take, or the clip's first section.
            if ($leadReceived !== null || isset($request->lead['fromClip'])) {
                MakeChoirLead::dispatch($request->id);
            }
            app(RecordCounts::class)->server('own_choir_requested', true, null);
            if ($request->template_version_id !== null) {
                $this->countOwn($request->template_version_id);
            }
        } else {
            Storage::disk('private')->delete(array_values(array_filter([$received, $leadReceived])));
        }

        return $request->refresh();
    }

    public function seen(User $user, OwnChoirRequest $request): OwnChoirRequest
    {
        // The owner sees their friends' leaving too.
        if ($request->user_id === $user->id) {
            OwnChoirMember::query()->where('own_choir_request_id', $request->id)->whereNotNull('left_at')->whereNull('owner_seen_at')->update(['owner_seen_at' => now()]);
        }

        // A song they joined (choir together): their parts in it are seen.
        $member = OwnChoirMember::query()->where('own_choir_request_id', $request->id)->where('user_id', $user->id)->whereNull('left_at')->first();

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
    /** Their songs' leads (their own recordings of the tune), and the shares that carried them. */
    public static function dropLeads(User $user): void
    {
        $songs = OwnChoirRequest::query()->where('user_id', $user->id)->where(fn ($q) => $q->whereNotNull('lead_key')->orWhereNotNull('lead_upload_key'))->get();
        app(DeleteHarmony::class)->withRealVoice(takes: $songs->modelKeys());
        Storage::disk('private')->delete(array_values(array_filter($songs->flatMap(static fn (OwnChoirRequest $s): array => [$s->lead_key, $s->lead_upload_key])->all())));
        OwnChoirRequest::query()->whereKey($songs->modelKeys())->update(['lead' => null, 'lead_key' => null, 'lead_upload_key' => null]);
    }

    public static function dropVoices(User $user): void
    {
        // Their voice gone: their parts in every choir they joined go with it (the owners' play again).
        ChoirTogether::leaveAll($user);

        // Their own recordings go with it: each song's lead, and every share that carried their real voice.
        self::dropLeads($user);

        [$files, $waiting] = DB::transaction(function () use ($user): array {
            $voices = OwnChoirVoice::query()->where('user_id', $user->id)->lockForUpdate()->get();
            app(DeleteHarmony::class)->withRealVoice(clips: $voices->modelKeys());
            $waiting = OwnChoirRequest::query()->whereIn('own_choir_voice_id', $voices->modelKeys())->whereIn('status', [OwnChoirStatus::Queued->value, OwnChoirStatus::Rendering->value])->pluck('id')->all();
            OwnChoirVoice::query()->whereKey($voices->modelKeys())->delete();

            return [array_values($voices->flatMap(static fn (OwnChoirVoice $v): array => $v->files())->all()), $waiting];
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
    /**
     * A friend's render on another's song, ended: its files go; its row stays, emptied, so what it cost still counts
     * toward the owner's minutes (the review).
     */
    public static function purgeJob(OwnChoirRequest $job): void
    {
        self::purge($job);
    }

    private static function purge(OwnChoirRequest $request): void
    {
        // The owner's song going: everyone's part in it goes too, and any share that carried its lead.
        if ($request->parent_request_id === null) {
            ChoirTogether::songGone($request);
            app(DeleteHarmony::class)->withRealVoice(takes: [$request->id]);
        }

        $files = DB::transaction(function () use ($request): array {
            $locked = OwnChoirRequest::query()->whereKey($request->id)->lockForUpdate()->first();

            if ($locked === null) {
                return [];
            }

            $parts = OwnChoirPart::query()->where('own_choir_request_id', $locked->id)->lockForUpdate()->get();
            $preparing = OwnChoirVoice::query()->whereKey($locked->own_choir_voice_id ?? 0)->where('status', '!=', 'ready')->lockForUpdate()->get();
            $files = [
                ...array_filter([$locked->upload_key, $locked->clip_key, $locked->lead_key, $locked->lead_upload_key]),
                ...$parts->flatMap(static fn (OwnChoirPart $p): array => ChoirJobs::partFiles($p))->all(),
                ...$preparing->flatMap(static fn (OwnChoirVoice $v): array => $v->files())->all(),
            ];
            OwnChoirPart::query()->whereKey($parts->modelKeys())->delete();
            OwnChoirVoice::query()->whereKey($preparing->modelKeys())->delete();
            $locked->forceFill(['status' => OwnChoirStatus::Deleted, 'upload_key' => null, 'clip_key' => null, 'lead' => null, 'lead_key' => null, 'lead_upload_key' => null, 'claimed_at' => null, 'claim_token' => null, 'part_key' => null, 'offset_frames' => null, 'sha256' => null])->save();
            OwnChoir::releaseFree($locked);

            return array_values($files);
        });
        Storage::disk('private')->delete($files);
    }

    /** A song of their own from the creator (own_choir_own), counted beside the library's. */
    /**
     * Choir settings' mix (Plus): saved at once, free.
     *
     * @param  array{room: string, balance?: list<array{partKey: string, db: float|int}>, leadBlend?: float|int|null, recordingLevel?: float|int|null}  $sound
     */
    public function setSound(User $user, OwnChoirRequest $song, array $sound): OwnChoirRequest
    {
        $this->ownSong($user, $song);
        $song->forceFill(['sound' => [
            'room' => $sound['room'],
            'balance' => array_values(array_map(static fn (array $b): array => ['partKey' => (string) $b['partKey'], 'db' => (float) $b['db']], $sound['balance'] ?? [])),
            'leadBlend' => isset($sound['leadBlend']) ? (float) $sound['leadBlend'] : null,
            'recordingLevel' => isset($sound['recordingLevel']) ? (float) $sound['recordingLevel'] : null,
        ]])->save();

        return $song;
    }

    /**
     * Choir settings' singing (Plus): the song made again in their saved voice, its owner's parts only, charged like a
     * song (fine at its real GPU cost); the song plays as it is until the new parts replace the old.
     *
     * @param  array{humanize: int, voicing: string, breaths: string, quality: string, ensembleTiming: string, pitchSpread: string}  $sung
     */
    public function remake(User $user, OwnChoirRequest $song, array $sung): OwnChoirRequest
    {
        $this->ownSong($user, $song);

        if ($song->status !== OwnChoirStatus::Ready) {
            throw new Refusal(404, 'not_found');
        }

        $voice = OwnChoir::voice($user) ?? throw new Refusal(409, 'voice_required');
        $full = $sung['voicing'] === 'full' ? 'full' : 'written';
        $parts = ChoirSettings::remakeParts($song, $full);
        $minutes = ChoirSettings::remakeMinutes($song, $sung['quality'], $parts, $full);
        // auto: the low-voice version when their range suits it; high renders as written (no higher version yet).
        $range = ChoirTogether::range($user, null, [], 0);
        $melody = ChoirTogether::partNotes($song)[self::melody((array) $song->templateVersion?->data) ?? ''] ?? [];
        $voicing = match ($sung['voicing']) {
            'low' => 'low',
            'full' => 'full',
            'auto' => $range !== null && $melody !== [] && VoicingTransform::isLow($melody, $range) ? 'low' : 'written',
            default => 'written',
        };

        DB::transaction(function () use ($user, $song, $sung, $voice, $minutes, $parts, $voicing): void {
            Owned::lockAccount($user);

            if (OwnChoirRequest::query()->where('parent_request_id', $song->id)->where('remake', true)->whereIn('status', [OwnChoirStatus::Queued->value, OwnChoirStatus::Rendering->value])->exists()) {
                throw new Refusal(409, 'remake_running');
            }

            if ($parts === []) {
                throw new Refusal(409, 'remake_running');
            }

            if (($which = OwnChoir::limitReached($user, $minutes)) !== null) {
                throw new Refusal(403, 'quota_reached', ['which' => $which, ...($which === 'minutes' ? ['needed' => $minutes] : [])]);
            }

            ChoirCounts::remade(ChoirSettings::sung($song), $sung);
            $remake = new OwnChoirRequest;
            $remake->forceFill([
                'user_id' => $user->id,
                'parent_request_id' => $song->id,
                'bill_to_user_id' => $user->id,
                'remake' => true,
                'template_version_id' => $song->template_version_id,
                'own_choir_voice_id' => $voice->id,
                'from_voice' => true,
                'status' => OwnChoirStatus::Queued,
                'only_parts' => $parts,
                'part_minutes' => $minutes,
                'own' => $song->own,
                'priority' => OwnChoir::tier($user) === 'paid' ? 1 : 0,
                'starts_after' => OwnChoir::busyToday() ? now()->addDay()->startOfDay() : null,
                'sung' => $sung,
                'voicing' => $voicing,
            ])->save();
        });

        return $song;
    }

    /**
     * The song's lead, if it has one: the whole song they sang (`lead`, a clip of the song's own tune), or the clip tune's
     * first section when it's this whole song. Its key shift moves the song to the key they sang.
     *
     * @param  array<string, mixed>  $clip
     * @return array{keyShift: int, take?: array{sampleRate: int, frames: int, bytes: int, sha256: string, offsetFrames: int}, fromClip?: array{beats: float}}|null
     */
    private static function lead(array $clip, TemplateVersion $tune, Template $template, TemplateVersion $version): ?array
    {
        $fromClip = ((array) config('own_choir.clip_leads'))[(string) $tune->template?->slug] ?? null;

        if (is_array($fromClip) && ($fromClip['song'] ?? null) === $template->slug) {
            return ['keyShift' => (int) ($fromClip['keyShift'] ?? 0), 'fromClip' => ['beats' => (float) ($fromClip['beats'] ?? 0)]];
        }

        $take = $clip['lead'] ?? null;

        if (! is_array($take)) {
            return null;
        }

        // The whole song, sung to its own tune: no longer than it and a little, and the WAV the numbers describe.
        $seconds = (int) $take['frames'] / (int) $take['sampleRate'];
        $song = self::songSeconds((array) $version->data);

        if ($tune->id !== $version->id || (int) $take['bytes'] !== 44 + 2 * (int) $take['frames'] || $seconds > min($song + 5, (int) config('usage.take_max_seconds'))) {
            throw ValidationException::withMessages(['lead' => __('validation.choir_clip')]);
        }

        return ['keyShift' => 0, 'take' => ['sampleRate' => (int) $take['sampleRate'], 'frames' => (int) $take['frames'], 'bytes' => (int) $take['bytes'], 'sha256' => strtolower((string) $take['sha256']), 'offsetFrames' => (int) $take['offsetFrames']]];
    }

    /**
     * How long a song runs: beat 0 to its last note's end, at its tempo.
     *
     * @param  array<string, mixed>  $data
     */
    public static function songSeconds(array $data): float
    {
        $end = 0.0;

        foreach ((array) ($data['parts'] ?? []) as $part) {
            foreach ((array) (is_array($part) ? ($part['guide']['notes'] ?? []) : []) as $n) {
                if (is_array($n)) {
                    $end = max($end, (float) ($n['beat'] ?? 0) + (float) ($n['length'] ?? 0));
                }
            }
        }

        return $end * 60 / max(1.0, (float) ($data['tempo'] ?? 0));
    }

    /** The tune their saved voice was sung to, if the voice remembers it. */
    private static function voiceTune(User $user): ?string
    {
        $voice = OwnChoir::voice($user);

        return ($voice === null ? null : OwnChoir::voiceOrigin($voice))?->clipVersion()?->template?->slug;
    }

    /** Their own song (not a friend's job, nor one they joined); Plus to change its settings. */
    private function ownSong(User $user, OwnChoirRequest $song): void
    {
        if ($song->user_id !== $user->id || $song->parent_request_id !== null || $song->status === OwnChoirStatus::Deleted) {
            throw new Refusal(404, 'not_found');
        }

        if (OwnChoir::tier($user) === 'free') {
            throw new Refusal(403, 'plus_required');
        }
    }

    /**
     * A song's melody part: the one marked `melody` (the template schema allows one), else its first.
     *
     * @param  array<string, mixed>  $data
     */
    public static function melody(array $data): ?string
    {
        $parts = array_values(array_filter((array) ($data['parts'] ?? []), 'is_array'));
        $marked = collect($parts)->first(static fn (array $p): bool => ($p['melody'] ?? false) === true);
        $key = ($marked ?? ($parts[0] ?? []))['key'] ?? null;

        return is_string($key) ? $key : null;
    }

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
        app(RecordCounts::class)->serverAmount('choir_download', 1);

        // The song as it plays: each part's active render, a friend's included (and gone the moment they leave).
        return array_values(ChoirTogether::activeParts($request)
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
