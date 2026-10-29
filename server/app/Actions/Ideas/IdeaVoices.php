<?php

declare(strict_types=1);

namespace App\Actions\Ideas;

use App\Exceptions\Refusal;
use App\Jobs\ProcessIdeaVoice;
use App\Models\IdeaVoice;
use App\Models\User;
use App\Support\Storage\ReceivedUploads;
use App\Support\Storage\Uploads;
use App\Support\Usage\UploadBytes;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A singer's voice on an idea: uploaded as a take is (a presigned PUT of a 16-bit mono WAV,
 * checked at complete), made into AAC by ProcessIdeaVoice. Each singer keeps one per part; the
 * writer is told by the bell and the daily email.
 */
final class IdeaVoices
{
    public function __construct(private readonly Ideas $ideas, private readonly Uploads $uploads, private readonly UploadBytes $bytes) {}

    /**
     * @param  array{id: string, partKey: string, sampleRate: int, frames: int, offsetFrames: int, bytes: int, sha256: string}  $data
     * @return array{voice: IdeaVoice, upload: array{url: string, method: string, headers: array<string, string>, expiresAt: DateTimeInterface}|null}
     */
    public function start(User $user, string $ideaId, array $data): array
    {
        $this->ideas->ensureOpen();
        $this->ideas->ensureMayTakePart($user);
        $idea = $this->ideas->find($ideaId, $user);

        // Listed (anyone), or its writer singing their own tune.
        if ($idea->user_id !== $user->id && $idea->status() !== 'listed') {
            throw new Refusal(404, 'not_found');
        }

        $voice = DB::transaction(function () use ($user, $idea, $data): IdeaVoice {
            $existing = IdeaVoice::query()->where('uuid', $data['id'])->first();

            if ($existing !== null) {
                if ($existing->user_id !== $user->id || $existing->idea_id !== $idea->id || $existing->sha256 !== strtolower($data['sha256'])) {
                    throw ValidationException::withMessages(['id' => __('validation.take_id')]);
                }

                return $existing;
            }

            if (! in_array($data['partKey'], $idea->part_keys, true)) {
                throw ValidationException::withMessages(['partKey' => __('validation.template_part')]);
            }

            if ($data['frames'] > (int) config('usage.take_max_seconds') * $data['sampleRate']) {
                throw ValidationException::withMessages(['frames' => __('validation.take_too_long')]);
            }

            // The same daily cap on uploaded bytes as a take.
            $this->bytes->count($user, $data['bytes']);
            $voice = new IdeaVoice;
            $voice->forceFill([
                'uuid' => $data['id'],
                'idea_id' => $idea->id,
                'user_id' => $user->id,
                'part_key' => $data['partKey'],
                'status' => IdeaVoice::UPLOADING,
                'sample_rate' => $data['sampleRate'],
                'frames' => $data['frames'],
                'offset_frames' => $data['offsetFrames'],
                'upload_bytes' => $data['bytes'],
                'sha256' => strtolower($data['sha256']),
                'upload_key' => 'uploads/voices/'.Str::lower(Str::random(32)).'.wav',
                'rights_confirmed_at' => now(),
            ])->save();

            return $voice;
        });

        return ['voice' => $voice, 'upload' => $voice->status === IdeaVoice::UPLOADING ? $this->uploads->presign($voice->upload_key, 'audio/wav', $voice->upload_bytes, minutes: 10) : null];
    }

    public function complete(User $user, string $uuid): IdeaVoice
    {
        $voice = IdeaVoice::query()->where('uuid', $uuid)->where('user_id', $user->id)->first() ?? throw new Refusal(404, 'not_found');

        if ($voice->status !== IdeaVoice::UPLOADING) {
            return $voice;
        }

        $received = app(ReceivedUploads::class)->receive($voice->upload_key, $voice->upload_bytes, $voice->sha256);

        // Another complete is checking it this moment: as it is.
        if ($received === null) {
            return $voice->refresh();
        }

        if (IdeaVoice::query()->whereKey($voice->id)->where('status', IdeaVoice::UPLOADING)->update(['status' => IdeaVoice::PROCESSING, 'upload_key' => $received, 'updated_at' => now()]) === 1) {
            ProcessIdeaVoice::dispatch($voice->id);
        } else {
            Storage::disk('private')->delete($received);
        }

        return $voice->refresh();
    }

    /**
     * By its singer, or the idea's writer.
     */
    public function delete(User $user, string $uuid): void
    {
        $voice = IdeaVoice::query()->with('idea')->where('uuid', $uuid)->first();

        if ($voice === null || ($voice->user_id !== $user->id && $voice->idea->user_id !== $user->id)) {
            return;
        }

        $this->remove($voice);
    }

    public function remove(IdeaVoice $voice): void
    {
        // Its keys read under the lock processing takes, so one finishing meanwhile leaves no file.
        $keys = DB::transaction(function () use ($voice): array {
            $locked = IdeaVoice::query()->whereKey($voice->id)->lockForUpdate()->first();
            $locked?->delete();

            return $locked !== null ? array_values(array_filter([$locked->file_key, $locked->upload_key])) : [];
        });

        Storage::disk('private')->delete($keys);
        $this->ideas->recount($voice->idea);
    }

    /**
     * Every voice this person sang goes (their account is being deleted).
     */
    public function removeFor(User $user): void
    {
        foreach (IdeaVoice::query()->with('idea')->where('user_id', $user->id)->get() as $voice) {
            $this->remove($voice);
        }
    }
}
