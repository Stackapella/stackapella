<?php

declare(strict_types=1);

namespace App\Actions\OwnChoir;

use App\Actions\Counts\RecordCounts;
use App\Enums\OwnChoirStatus;
use App\Exceptions\Refusal;
use App\Jobs\EncodeChoirPart;
use App\Jobs\KeepVoiceClip;
use App\Models\AuditLog;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\OwnChoirVoice;
use App\Notifications\Admin\OpsAlert;
use App\Services\Audio\Ffmpeg;
use App\Support\Admin\Admins;
use App\Support\OwnChoir\ChoirCounts;
use App\Support\OwnChoir\ChoirWait;
use App\Support\OwnChoir\OwnChoir;
use App\Support\OwnChoir\VoicingTransform;
use App\Support\Storage\ReceivedUploads;
use App\Support\Storage\Uploads;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The renderer's side of Hear yourself as a choir (worker.choir.*, track B phase 3): claiming the next song, sending the
 * reference prepared from a new clip and each part, and finishing. A claim lasts own_choir.claim_minutes and carries a
 * token: only the claim holding it acts on the job. A song not finished in time is claimed again, at most
 * own_choir.max_tries times, then fails. However a song ends, its clip goes, and a failed song's parts go with it.
 */
final class ChoirJobs
{
    public function __construct(private readonly Uploads $uploads) {}

    /**
     * The next song to render, claimed (its claim_token set); null when none waits.
     */
    public function claim(): ?OwnChoirRequest
    {
        $this->expireStale();

        // Today's renders across everyone at the ceiling (the GPU review): nothing more until tomorrow, the admins told once.
        if (OwnChoir::busyToday()) {
            self::tellBusy();

            return null;
        }

        for ($tried = 0; $tried < 20; $tried++) {
            $claimed = DB::transaction(function (): OwnChoirRequest|false|null {
                $stale = now()->subMinutes((int) config('own_choir.claim_minutes'));
                $request = OwnChoirRequest::query()
                    ->where(fn ($query) => $query->where('status', OwnChoirStatus::Queued->value)->where(fn ($ready) => $ready->whereNotNull('clip_checked_at')->orWhere('from_voice', true))
                        ->orWhere(fn ($again) => $again->where('status', OwnChoirStatus::Rendering->value)->where('claimed_at', '<', $stale)))
                    ->where('tries', '<', (int) config('own_choir.max_tries'))->whereNotNull('template_version_id')
                    ->where(fn ($query) => $query->whereNull('starts_after')->orWhere('starts_after', '<=', now()))
                    // A song the app started by itself waits out its hold (the choir wait, 2026-11-24).
                    ->where(fn ($query) => $query->whereNull('hold_until')->orWhere('hold_until', '<=', now()))
                    // A friend's parts wait for the owner's part-minutes (ChoirTogether::release).
                    ->where('held_for_minutes', false)
                    // Paid Plus first, then in turn.
                    ->orderByDesc('priority')->orderBy('id')->lockForUpdate()->first();

                if ($request === null) {
                    return null;
                }

                // Made from a kept voice since deleted: nothing to render with.
                if ($request->from_voice && ($request->own_choir_voice_id === null || ! OwnChoirVoice::query()->whereKey($request->own_choir_voice_id)->where('status', 'ready')->exists())) {
                    self::failAndClean($request, 'reference_gone');

                    return false;
                }

                $first = $request->first_claimed_at === null;
                $request->forceFill(['status' => OwnChoirStatus::Rendering, 'claimed_at' => now(), 'claim_started_at' => now(), 'hold_until' => null, 'claim_token' => Str::random(32), 'claim_gpu_seconds' => 0,
                    'first_claimed_at' => $request->first_claimed_at ?? now(), 'tries' => $request->tries + 1])->save();

                if ($first) {
                    ChoirCounts::claimed($request);
                }

                if ($request->from_voice && $request->own_choir_voice_id !== null) {
                    OwnChoirVoice::query()->whereKey($request->own_choir_voice_id)->update(['last_used_at' => now()]);
                }

                return $request;
            });

            if ($claimed !== false) {
                return $claimed;
            }
        }

        return null;
    }

    /**
     * @param  array{part: string, sampleRate: int, frames: int, bytes: int, sha256: string, checks: array<string, mixed>, renderer: array<string, mixed>}  $master
     * @return array{part: OwnChoirPart, put: array{url: string, method: string, headers: array<string, string>, expiresAt: \DateTimeInterface}}
     */
    public function startPart(OwnChoirRequest $request, string $claim, array $master): array
    {
        // A friend's job renders only their parts (choir together).
        $parts = $request->only_parts ?? array_column(array_filter((array) ($request->templateVersion->data['parts'] ?? []), 'is_array'), 'key');

        if (! in_array($master['part'], $parts, true)) {
            throw ValidationException::withMessages(['part' => __('validation.choir_part')]);
        }

        [$part, $old] = DB::transaction(function () use ($request, $claim, $master): array {
            // Read again under its lock (a delete or another claim may have come meanwhile).
            self::lockHeld($request->id, $claim);
            // Sent again (a retry): the earlier copy of this part goes.
            $old = OwnChoirPart::query()->where('own_choir_request_id', $request->id)->where('part_key', $master['part'])->lockForUpdate()->first();
            $files = $old === null ? [] : self::partFiles($old);
            $old?->delete();

            $part = new OwnChoirPart(['part_key' => $master['part']]);
            $part->forceFill([
                'own_choir_request_id' => $request->id,
                'status' => 'uploading',
                'upload_key' => 'uploads/choir/'.Str::lower(Str::random(32)).'.wav',
                'sample_rate' => $master['sampleRate'],
                'frames' => $master['frames'],
                'bytes' => $master['bytes'],
                'sha256' => strtolower($master['sha256']),
                'checks' => $master['checks'],
                'renderer' => $master['renderer'],
            ])->save();

            return [$part, $files];
        });
        Storage::disk('private')->delete($old);

        return ['part' => $part, 'put' => $this->uploads->presign($part->upload_key, 'audio/wav', $part->bytes)];
    }

    /**
     * The reference the renderer prepared from a job's new clip: it becomes the singer's kept voice once it's in.
     *
     * @param  array{sampleRate: int, frames: int, bytes: int, sha256: string, meta: array<string, mixed>, octave?: int|null, sungRange?: list<int>|null}  $reference
     * @return array{voice: OwnChoirVoice, put: array{url: string, method: string, headers: array<string, string>, expiresAt: \DateTimeInterface}}
     */
    public function startReference(OwnChoirRequest $request, string $claim, array $reference): array
    {
        return DB::transaction(function () use ($request, $claim, $reference): array {
            $request = self::lockHeld($request->id, $claim);

            if ($request->from_voice || $request->upload_key === null) {
                throw new Refusal(409, 'no_clip');
            }

            return $this->newReference($request, $reference);
        });
    }

    /**
     * @param  array{sampleRate: int, frames: int, bytes: int, sha256: string, meta: array<string, mixed>, octave?: int|null, sungRange?: list<int>|null}  $reference
     * @return array{voice: OwnChoirVoice, put: array{url: string, method: string, headers: array<string, string>, expiresAt: \DateTimeInterface}}
     */
    private function newReference(OwnChoirRequest $request, array $reference): array
    {
        $voice = new OwnChoirVoice;
        $voice->forceFill([
            'user_id' => $request->user_id,
            'status' => 'uploading',
            'upload_key' => 'uploads/choir/'.Str::lower(Str::random(32)).'.wav',
            'sample_rate' => $reference['sampleRate'],
            'frames' => $reference['frames'],
            'bytes' => $reference['bytes'],
            'sha256' => strtolower($reference['sha256']),
            'meta' => $reference['meta'],
            'wordless' => self::wordless((array) ($request->clipVersion()->data ?? []), (string) $request->part_key),
        ]);
        // The octave the tune was sung in and the notes actually sung (the worker's report): the voice's range later.
        $range = array_values((array) ($reference['sungRange'] ?? []));

        if (count($range) === 2 && $range[0] <= $range[1]) {
            $voice->forceFill(['sung_low' => (int) $range[0], 'sung_high' => (int) $range[1]]);
        }

        $voice->forceFill(['octave' => $reference['octave'] ?? null])->save();
        $request->forceFill(['own_choir_voice_id' => $voice->id])->save();

        // A check on the octave the app measured before the friend's yes: logged when the worker heard otherwise (the
        // agreed parts render as agreed).
        if ($request->octave !== null && isset($reference['octave']) && (int) $reference['octave'] !== $request->octave) {
            AuditLog::record('own_choir.octave_differs', null, null, after: ['request' => $request->sqid(), 'app' => $request->octave, 'worker' => (int) $reference['octave']]);
        }

        return ['voice' => $voice, 'put' => $this->uploads->presign($voice->upload_key, 'audio/wav', $voice->bytes)];
    }

    /**
     * The reference is in: checked (size, hash, a 16-bit mono WAV of its frames) and kept as their voice, replacing the
     * one before; only while its song is still being made and the voice wasn't deleted meanwhile, else nothing is kept.
     */
    public function completeReference(OwnChoirVoice $voice, Ffmpeg $ffmpeg): OwnChoirVoice
    {
        if ($voice->status !== 'uploading') {
            return $voice;
        }

        $received = app(ReceivedUploads::class)->receive($voice->upload_key, $voice->bytes, $voice->sha256, 'choir/voices');

        if ($received === null) {
            return $voice->refresh();
        }

        $local = (string) tempnam(sys_get_temp_dir(), 'voice');
        file_put_contents($local, Storage::disk('private')->readStream($received));

        try {
            $probe = $ffmpeg->probe($local);
            $good = $probe !== null && str_contains($probe['format'], 'wav') && $probe['codec'] === 'pcm_s16le' && $probe['channels'] === 1 && $probe['sampleRate'] === $voice->sample_rate && $probe['frames'] !== null && abs($probe['frames'] - $voice->frames) <= 1;
        } finally {
            @unlink($local);
        }

        $drop = DB::transaction(function () use ($voice, $received, $good): array {
            // Read again under their locks (dropVoices takes the voice's too): deleted meanwhile, or its song gone, keeps
            // nothing. The song first, then the voice: the order purging a song takes them in, so the two never deadlock.
            $job = OwnChoirRequest::query()->where('own_choir_voice_id', $voice->id)->where('status', OwnChoirStatus::Rendering->value)->lockForUpdate()->first();
            $locked = OwnChoirVoice::query()->whereKey($voice->id)->where('status', 'uploading')->lockForUpdate()->first();
            $live = $job !== null && $job->claimHeld();

            if ($locked === null || ! $live || ! $good) {
                $locked?->forceFill(['status' => 'failed'])->save();

                return [$received];
            }

            $old = OwnChoirVoice::query()->where('user_id', $voice->user_id)->whereKeyNot($voice->id)->lockForUpdate()->get();
            OwnChoirVoice::query()->whereKey($old->modelKeys())->delete();
            $locked->forceFill(['status' => 'ready', 'reference_key' => $received, 'saved_at' => now(), 'last_used_at' => now()])->save();

            return array_values($old->flatMap(static fn (OwnChoirVoice $v): array => $v->files())->all());
        });
        Storage::disk('private')->delete($drop);

        return $voice->refresh();
    }

    public function completePart(OwnChoirPart $part): OwnChoirPart
    {
        if ($part->status !== 'uploading') {
            return $part;
        }

        $received = app(ReceivedUploads::class)->receive($part->upload_key, $part->bytes, $part->sha256, 'choir/masters');

        if ($received === null) {
            return $part->refresh();
        }

        // Its job must still be rendering under a live claim (read under its lock): else nothing is kept.
        $kept = DB::transaction(function () use ($part, $received): bool {
            $job = OwnChoirRequest::query()->whereKey($part->own_choir_request_id)->lockForUpdate()->first();

            return $job !== null && $job->claimHeld()
                && OwnChoirPart::query()->whereKey($part->id)->where('status', 'uploading')->update(['status' => 'processing', 'master_key' => $received]) === 1;
        });

        if (! $kept) {
            Storage::disk('private')->delete($received);
            $now = OwnChoirPart::query()->find($part->id);

            if ($now === null || $now->status === 'uploading') {
                throw new Refusal(409, 'job_not_claimed');
            }

            return $now;
        }

        EncodeChoirPart::dispatch($part->id);

        return $part->refresh();
    }

    /**
     * Done: ready once every part is in, or failed with the renderer's reason. The clip goes either way.
     */
    /** A made remake's parts replace its song's; the song takes how it was sung; the remake's row stays, emptied, for what it cost. */
    private static function foldRemake(OwnChoirRequest $remake): void
    {
        $files = DB::transaction(function () use ($remake): array {
            $song = OwnChoirRequest::query()->whereKey($remake->parent_request_id)->lockForUpdate()->first();
            $new = OwnChoirPart::query()->where('own_choir_request_id', $remake->id)->where('status', 'ready')->lockForUpdate()->get();

            if ($song === null || $song->status !== OwnChoirStatus::Ready) {
                return [];
            }

            // The song's own parts (a friend's live on their job): those remade, and any the new voicing no longer has.
            $old = OwnChoirPart::query()->where('own_choir_request_id', $song->id)->where(fn ($q) => $q->whereIn('part_key', $new->pluck('part_key')->all())->orWhereNotIn('part_key', VoicingTransform::keys((array) ($song->templateVersion->data ?? []), (string) $remake->voicing)))->lockForUpdate()->get();
            OwnChoirPart::query()->whereKey($old->modelKeys())->delete();
            OwnChoirPart::query()->whereKey($new->modelKeys())->update(['own_choir_request_id' => $song->id]);
            $song->forceFill(['sung' => $remake->sung, 'voicing' => $remake->voicing, 'seen_at' => null])->save();
            $remake->forceFill(['status' => OwnChoirStatus::Deleted])->save();

            return array_values($old->flatMap(static fn (OwnChoirPart $p): array => self::partFiles($p))->all());
        });

        Storage::disk('private')->delete($files);
    }

    /**
     * @param  array{detail?: string|null, level?: string|null, partTries?: list<array{partKey: string, tries: int}>|null, gateFailures?: list<array{partKey: string, reason?: string|null, cents?: list<float|int>|null}>|null, partWorst?: list<array{partKey: string, cents: list<float|int>}>|null, detailNumbers?: list<array{name: string, value: float|int|string}>|null}  $report
     */
    public function finish(OwnChoirRequest $request, string $claim, ?string $failed, ?float $gpuSeconds = null, array $report = []): OwnChoirRequest
    {
        $done = $this->finishJob($request, $claim, $failed, $gpuSeconds);

        // Why its clip couldn't be used, for its singer alone (Hillel, 2026-11-23).
        if ($failed === 'clip_unusable' && $done->status === OwnChoirStatus::Failed && ($report['detail'] ?? null) !== null) {
            $done->forceFill(['failure_detail' => $report['detail']])->save();
        }

        // The worst notes the worker measured, for Admin > Choir (2026-11-24): partKeys and signed cents, numbers only.
        $gates = self::gateNumbers($report);

        if ($gates !== null) {
            $done->forceFill(['gate_numbers' => $gates])->save();
        }

        // Its measures, for Admin > Choir to tune the checks: numbers only (2026-11-23).
        if ($failed === 'clip_unusable' && $done->status === OwnChoirStatus::Failed && ($report['detailNumbers'] ?? []) !== []) {
            $done->forceFill(['failure_numbers' => array_map(static fn (array $n): array => ['name' => (string) $n['name'], 'value' => (float) $n['value']], (array) $report['detailNumbers'])])->save();
        }

        ChoirCounts::finished($done->loadMissing(['clipTemplateVersion.template', 'voice']), $report);

        return $done;
    }

    private function finishJob(OwnChoirRequest $request, string $claim, ?string $failed, ?float $gpuSeconds): OwnChoirRequest
    {
        if ($gpuSeconds !== null) {
            DB::transaction(static fn () => self::spend(self::lockHeld($request->id, $claim), $gpuSeconds));
        }

        if ($failed !== null) {
            // The claim checked in the same transaction, under the same lock, as the failure.
            return self::failAndClean($request, $failed, $claim);
        }

        $wanted = $request->only_parts ?? array_column(array_filter((array) ($request->templateVersion->data['parts'] ?? []), 'is_array'), 'key');

        // Read again under its lock: a song deleted (or claimed by another worker) meanwhile is never made ready.
        $clip = DB::transaction(function () use ($request, $claim, $wanted): ?string {
            $locked = self::lockHeld($request->id, $claim);
            $ready = OwnChoirPart::query()->where('own_choir_request_id', $locked->id)->where('status', 'ready')->lockForUpdate()->pluck('part_key')->all();

            if (array_diff($wanted, $ready) !== []) {
                throw new Refusal(409, 'parts_missing');
            }

            $clip = $locked->clip_key;
            $locked->forceFill(['status' => OwnChoirStatus::Ready, 'ready_at' => now(), 'clip_key' => null, 'claimed_at' => null, 'claim_token' => null])->save();

            return $clip;
        });

        // The clip that made their voice is kept with it, as AAC, for them to compare (Hillel, 2026-11-23); else it goes.
        $voice = $request->from_voice ? null : OwnChoirVoice::query()->whereKey($request->own_choir_voice_id ?? 0)->where('user_id', $request->user_id)->where('status', 'ready')->value('id');

        if ($clip !== null && $voice !== null) {
            KeepVoiceClip::dispatch((int) $voice, $clip);
        } elseif ($clip !== null) {
            Storage::disk('private')->delete($clip);
        }

        // A remake made (choir settings): its parts replace the song's, and the song takes its settings.
        if ($request->remake && $request->parent_request_id !== null) {
            self::foldRemake($request);

            return $request->refresh();
        }

        app(RecordCounts::class)->server('own_choir_ready', true, null);
        ChoirCounts::made($request);
        // Its one email, if they haven't heard it (the choir wait).
        ChoirWait::finished(OwnChoirRequest::query()->findOrFail($request->id));

        // A friend's parts are in the owner's song: the owner's quiet card shows again, and the song's voices counted.
        if ($request->parent_request_id !== null) {
            OwnChoirRequest::query()->whereKey($request->parent_request_id)->update(['seen_at' => null]);
            $voices = 1 + OwnChoirRequest::query()->where('parent_request_id', $request->parent_request_id)->where('status', OwnChoirStatus::Ready->value)->count();
            app(RecordCounts::class)->server('own_choir_friend_ready', true, null);
            app(RecordCounts::class)->server('own_choir_voices_'.min(4, $voices), true, null);
        }

        return $request->refresh();
    }

    /**
     * The claim kept alive while its renders run (worker.choir.jobs.extend): its time starts again, never past
     * own_choir.claim_limit_minutes from when it was claimed (`409 claim_limit`), and its GPU time recorded.
     */
    public function extend(OwnChoirRequest $request, string $claim, float $gpuSeconds): int
    {
        return DB::transaction(static function () use ($request, $claim, $gpuSeconds): int {
            $locked = self::lockHeld($request->id, $claim);
            self::spend($locked, $gpuSeconds);

            if (($locked->claim_started_at ?? $locked->claimed_at)?->lt(now()->subMinutes((int) config('own_choir.claim_limit_minutes'))) === true) {
                throw new Refusal(409, 'claim_limit');
            }

            $locked->forceFill(['claimed_at' => now()])->save();

            return (int) config('own_choir.claim_minutes') * 60;
        });
    }

    /** A claim's GPU time so far: the largest it reported is kept, and the job's claims are summed. Locked already. */
    private static function spend(OwnChoirRequest $locked, float $gpuSeconds): void
    {
        if ($gpuSeconds > $locked->claim_gpu_seconds) {
            $locked->forceFill(['gpu_seconds' => $locked->gpu_seconds + ($gpuSeconds - $locked->claim_gpu_seconds), 'claim_gpu_seconds' => $gpuSeconds])->save();
        }
    }

    /** Today's ceiling reached: the admins are told once a day. */
    private static function tellBusy(): void
    {
        if (Cache::add('own-choir:busy:'.now()->toDateString(), true, 86400) && ($admins = app(Admins::class)->all())->isNotEmpty()) {
            Notification::send($admins, new OpsAlert(__('ops.own_choir_busy.subject'), __('ops.own_choir_busy.title'), [__('ops.own_choir_busy.line', ['minutes' => (int) config('own_choir.daily_part_minutes')])]));
        }
    }

    /**
     * The job, locked, still rendering under the claim holding this token; else 409 job_not_claimed. Inside a transaction.
     */
    private static function lockHeld(int $requestId, string $claim): OwnChoirRequest
    {
        $locked = OwnChoirRequest::query()->whereKey($requestId)->lockForUpdate()->first();

        if ($locked === null || ! $locked->claimHeld($claim)) {
            throw new Refusal(409, 'job_not_claimed');
        }

        return $locked;
    }

    /** Songs whose every try ran out: failed, their clip and parts gone. */
    public function expireStale(): void
    {
        $stale = now()->subMinutes((int) config('own_choir.claim_minutes'));

        foreach (OwnChoirRequest::query()->where('status', OwnChoirStatus::Rendering->value)->where('claimed_at', '<', $stale)->where('tries', '>=', (int) config('own_choir.max_tries'))->get() as $request) {
            self::failAndClean($request, 'render_failed');
        }
    }

    /**
     * The worker's pitch numbers for a job: each gate failure's worst notes and each part's as made, or null without any.
     *
     * @param  array<string, mixed>  $report
     * @return array{gateFailures: list<array{partKey: string, reason: string|null, cents: list<float>}>, partWorst: list<array{partKey: string, cents: list<float>}>}|null
     */
    public static function gateNumbers(array $report): ?array
    {
        $cents = static fn (mixed $list): array => array_values(array_map(static fn (mixed $c): float => round((float) $c, 1), array_filter((array) $list, 'is_numeric')));
        $failures = array_values(array_map(static fn (array $f): array => ['partKey' => (string) ($f['partKey'] ?? ''), 'reason' => isset($f['reason']) ? (string) $f['reason'] : null, 'cents' => $cents($f['cents'] ?? [])],
            array_filter((array) ($report['gateFailures'] ?? []), static fn (mixed $f): bool => is_array($f) && ($f['cents'] ?? []) !== [] && ($f['cents'] ?? null) !== null)));
        $worst = array_values(array_map(static fn (array $p): array => ['partKey' => (string) ($p['partKey'] ?? ''), 'cents' => $cents($p['cents'] ?? [])], array_filter((array) ($report['partWorst'] ?? []), 'is_array')));

        return $failures === [] && $worst === [] ? null : ['gateFailures' => $failures, 'partWorst' => $worst];
    }

    /**
     * A song ends without being made: failed with its reason, its clip and every part deleted (rows locked, files after
     * the commit, so an encode finishing meanwhile keeps nothing).
     */
    public static function failAndClean(OwnChoirRequest $request, string $failure, ?string $claim = null): OwnChoirRequest
    {
        $failedNow = false;
        $files = DB::transaction(function () use ($request, $failure, $claim, &$failedNow): array {
            // A worker's failure (a claim given) only while its claim still holds, checked under this lock.
            $locked = $claim !== null ? self::lockHeld($request->id, $claim) : OwnChoirRequest::query()->whereKey($request->id)->lockForUpdate()->first();

            // Only a song still on its way: one just made ready, or deleted, is left as it is.
            if ($locked === null || ! $locked->status->open()) {
                return [];
            }

            $parts = OwnChoirPart::query()->where('own_choir_request_id', $locked->id)->lockForUpdate()->get();
            $files = [...array_filter([$locked->clip_key]), ...$parts->flatMap(static fn (OwnChoirPart $p): array => self::partFiles($p))->all()];
            OwnChoirPart::query()->whereKey($parts->modelKeys())->delete();
            $locked->forceFill(['status' => OwnChoirStatus::Failed, 'failure' => $failure, 'clip_key' => null, 'claimed_at' => null, 'claim_token' => null])->save();
            OwnChoir::releaseFree($locked);
            ChoirCounts::failed($failure);
            $failedNow = true;

            return array_values($files);
        });
        Storage::disk('private')->delete($files);
        $failed = $request->refresh();

        // Failed by this call: its one email, if they haven't seen it (the choir wait).
        if ($failedNow) {
            ChoirWait::finished($failed);
        }

        return $failed;
    }

    /**
     * Whether a part has no words to sing (a hummed tune's): every note without a lyric.
     *
     * @param  array<string, mixed>  $data
     */
    public static function wordless(array $data, string $partKey): bool
    {
        foreach ((array) ($data['parts'] ?? []) as $part) {
            if (is_array($part) && ($part['key'] ?? null) === $partKey) {
                foreach ((array) ($part['guide']['notes'] ?? []) as $note) {
                    if (is_array($note) && is_string($note['lyric'] ?? null) && trim($note['lyric']) !== '') {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public static function partFiles(OwnChoirPart $part): array
    {
        return array_values(array_filter([$part->upload_key, $part->master_key, $part->media_key, $part->listen_key], static fn (?string $k): bool => $k !== null && $k !== ''));
    }
}
