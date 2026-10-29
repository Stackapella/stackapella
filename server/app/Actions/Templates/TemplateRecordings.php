<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Actions\Projects\Owned;
use App\Exceptions\Refusal;
use App\Jobs\ProcessTemplateRecording;
use App\Models\TemplateRecording;
use App\Models\User;
use App\Support\Storage\ReceivedUploads;
use App\Support\Storage\Uploads;
use App\Support\Usage\Plans;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A creator's own recorded guide for one part of their template (Plus; docs/pricing-plan.md):
 * uploaded as takes are (a presigned PUT of a 16-bit mono WAV, checked at complete), made into AAC
 * by ProcessTemplateRecording, and counted toward their storage.
 */
final class TemplateRecordings
{
    public function __construct(private readonly OwnTemplates $templates, private readonly Plans $plans, private readonly Uploads $uploads) {}

    /**
     * @param  array{id: string, partKey: string, sampleRate: int, frames: int, offsetFrames: int, bytes: int, sha256: string}  $data
     * @return array{recording: TemplateRecording, upload: array{url: string, method: string, headers: array<string, string>, expiresAt: DateTimeInterface}|null}
     */
    public function start(User $user, string $templateUuid, array $data): array
    {
        $recording = DB::transaction(function () use ($user, $templateUuid, $data): TemplateRecording {
            Owned::lockAccount($user);
            $template = $this->templates->mine($user, $templateUuid);
            $existing = TemplateRecording::query()->where('uuid', $data['id'])->first();

            if ($existing !== null) {
                if ($existing->user_id !== $user->id || $existing->template_id !== $template->id || $existing->sha256 !== strtolower($data['sha256'])) {
                    throw ValidationException::withMessages(['id' => __('validation.take_id')]);
                }

                return $existing;
            }

            if (! $this->plans->isPlus($user)) {
                throw new Refusal(403, 'plus_required');
            }

            $parts = array_column((array) ($template->currentVersion->data['parts'] ?? []), 'key');

            if (! in_array($data['partKey'], $parts, true)) {
                throw ValidationException::withMessages(['partKey' => __('validation.template_part')]);
            }

            if ($this->plans->storageUsed($user) + $data['bytes'] > $this->plans->storageLimit($user)) {
                throw new Refusal(403, 'storage_limit');
            }

            $recording = new TemplateRecording(['part_key' => $data['partKey'], 'sample_rate' => $data['sampleRate'], 'frames' => $data['frames'], 'offset_frames' => $data['offsetFrames'], 'upload_bytes' => $data['bytes'], 'sha256' => strtolower($data['sha256'])]);
            $recording->forceFill([
                'uuid' => $data['id'],
                'template_id' => $template->id,
                'user_id' => $user->id,
                'status' => TemplateRecording::UPLOADING,
                'upload_key' => 'uploads/recordings/'.Str::lower(Str::random(32)).'.wav',
            ])->save();

            return $recording;
        });

        return ['recording' => $recording, 'upload' => $recording->status === TemplateRecording::UPLOADING ? $this->uploads->presign($recording->upload_key, 'audio/wav', $recording->upload_bytes, minutes: 10) : null];
    }

    public function complete(User $user, string $uuid): TemplateRecording
    {
        $recording = TemplateRecording::query()->where('uuid', $uuid)->where('user_id', $user->id)->first() ?? throw new Refusal(404, 'not_found');

        if ($recording->status !== TemplateRecording::UPLOADING) {
            return $recording;
        }

        $received = app(ReceivedUploads::class)->receive($recording->upload_key, $recording->upload_bytes, $recording->sha256);

        // Another complete is checking it this moment: as it is.
        if ($received === null) {
            return $recording->refresh();
        }

        if (TemplateRecording::query()->whereKey($recording->id)->where('status', TemplateRecording::UPLOADING)->update(['status' => TemplateRecording::PROCESSING, 'upload_key' => $received, 'updated_at' => now()]) === 1) {
            ProcessTemplateRecording::dispatch($recording->id);
        } else {
            Storage::disk('private')->delete($received);
        }

        return $recording->refresh();
    }

    public function delete(User $user, string $uuid): void
    {
        $recording = TemplateRecording::query()->where('uuid', $uuid)->where('user_id', $user->id)->first();

        if ($recording !== null) {
            $recording->delete();
            Storage::disk('private')->delete(array_values(array_filter([$recording->file_key, $recording->upload_key])));
        }
    }
}
