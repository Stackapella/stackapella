<?php

declare(strict_types=1);

namespace App\Actions\OwnChoir;

use App\Actions\Counts\RecordCounts;
use App\Enums\OwnChoirStatus;
use App\Exceptions\Refusal;
use App\Jobs\EncodeChoirPart;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\OwnChoirVoice;
use App\Services\Audio\Ffmpeg;
use App\Support\Storage\ReceivedUploads;
use App\Support\Storage\Uploads;
use Illuminate\Support\Facades\DB;
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

        for ($tried = 0; $tried < 20; $tried++) {
            $claimed = DB::transaction(function (): OwnChoirRequest|false|null {
                $stale = now()->subMinutes((int) config('own_choir.claim_minutes'));
                $request = OwnChoirRequest::query()
                    ->where(fn ($query) => $query->where('status', OwnChoirStatus::Queued->value)->where(fn ($ready) => $ready->whereNotNull('clip_checked_at')->orWhere('from_voice', true))
                        ->orWhere(fn ($again) => $again->where('status', OwnChoirStatus::Rendering->value)->where('claimed_at', '<', $stale)))
                    ->where('tries', '<', (int) config('own_choir.max_tries'))->whereNotNull('template_version_id')
                    ->orderBy('id')->lockForUpdate()->first();

                if ($request === null) {
                    return null;
                }

                // Made from a kept voice since deleted: nothing to render with.
                if ($request->from_voice && ($request->own_choir_voice_id === null || ! OwnChoirVoice::query()->whereKey($request->own_choir_voice_id)->where('status', 'ready')->exists())) {
                    self::failAndClean($request, 'reference_gone');

                    return false;
                }

                $request->forceFill(['status' => OwnChoirStatus::Rendering, 'claimed_at' => now(), 'claim_token' => Str::random(32), 'tries' => $request->tries + 1])->save();

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
        $parts = array_column(array_filter((array) ($request->templateVersion->data['parts'] ?? []), 'is_array'), 'key');

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
     * @param  array{sampleRate: int, frames: int, bytes: int, sha256: string, meta: array<string, mixed>}  $reference
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
     * @param  array{sampleRate: int, frames: int, bytes: int, sha256: string, meta: array<string, mixed>}  $reference
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
            'wordless' => self::wordless((array) ($request->templateVersion->data ?? []), (string) $request->part_key),
        ])->save();
        $request->forceFill(['own_choir_voice_id' => $voice->id])->save();

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
            // Read again under its lock (dropVoices takes it too): deleted meanwhile, or its song gone, keeps nothing.
            $locked = OwnChoirVoice::query()->whereKey($voice->id)->where('status', 'uploading')->lockForUpdate()->first();
            $job = OwnChoirRequest::query()->where('own_choir_voice_id', $voice->id)->where('status', OwnChoirStatus::Rendering->value)->lockForUpdate()->first();
            $live = $job !== null && $job->claimHeld();

            if ($locked === null || ! $live || ! $good) {
                $locked?->forceFill(['status' => 'failed'])->save();

                return [$received];
            }

            $old = OwnChoirVoice::query()->where('user_id', $voice->user_id)->whereKeyNot($voice->id)->lockForUpdate()->get();
            OwnChoirVoice::query()->whereKey($old->modelKeys())->delete();
            $locked->forceFill(['status' => 'ready', 'reference_key' => $received, 'saved_at' => now(), 'last_used_at' => now()])->save();

            return array_values(array_filter($old->flatMap(static fn (OwnChoirVoice $v): array => [$v->upload_key, (string) $v->reference_key])->all()));
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
    public function finish(OwnChoirRequest $request, string $claim, ?string $failed): OwnChoirRequest
    {
        if ($failed !== null) {
            DB::transaction(static fn (): OwnChoirRequest => self::lockHeld($request->id, $claim));

            return self::failAndClean($request, $failed);
        }

        $wanted = array_column(array_filter((array) ($request->templateVersion->data['parts'] ?? []), 'is_array'), 'key');

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

        if ($clip !== null) {
            Storage::disk('private')->delete($clip);
        }

        app(RecordCounts::class)->server('own_choir_ready', true, null);

        return $request->refresh();
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
     * A song ends without being made: failed with its reason, its clip and every part deleted (rows locked, files after
     * the commit, so an encode finishing meanwhile keeps nothing).
     */
    public static function failAndClean(OwnChoirRequest $request, string $failure): OwnChoirRequest
    {
        $files = DB::transaction(function () use ($request, $failure): array {
            $locked = OwnChoirRequest::query()->whereKey($request->id)->lockForUpdate()->first();

            // Only a song still on its way: one just made ready, or deleted, is left as it is.
            if ($locked === null || ! $locked->status->open()) {
                return [];
            }

            $parts = OwnChoirPart::query()->where('own_choir_request_id', $locked->id)->lockForUpdate()->get();
            $files = [...array_filter([$locked->clip_key]), ...$parts->flatMap(static fn (OwnChoirPart $p): array => self::partFiles($p))->all()];
            OwnChoirPart::query()->whereKey($parts->modelKeys())->delete();
            $locked->forceFill(['status' => OwnChoirStatus::Failed, 'failure' => $failure, 'clip_key' => null, 'claimed_at' => null, 'claim_token' => null])->save();

            return array_values($files);
        });
        Storage::disk('private')->delete($files);

        return $request->refresh();
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
        return array_values(array_filter([$part->upload_key, $part->master_key, $part->media_key], static fn (?string $k): bool => $k !== null && $k !== ''));
    }
}
