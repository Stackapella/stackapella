<?php

declare(strict_types=1);

namespace App\Http\Resources\Worker;

use App\Models\OwnChoirRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * A claimed own-choir job for the renderer (contract/schemas/worker.choir.claim.json): its claim token, the song's notes,
 * and exactly one of a new clip or the singer's kept voice, each behind a 30-minute link. Needs `templateVersion.template`
 * and `voice` loaded.
 *
 * @property OwnChoirRequest $resource
 */
final class ChoirJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $job = $this->resource;
        $version = $job->templateVersion ?? throw new LogicException('A claimed job has its song.');

        return [
            'id' => $job->sqid(),
            // Sent back with every call on this job: only this claim acts on it.
            'claim' => (string) $job->claim_token,
            'template' => (string) $version->template->slug,
            'version' => $version->version,
            'title' => (string) $version->template->title,
            'data' => (object) (array) $version->data,
            'clip' => $job->from_voice ? null : [
                'url' => Storage::disk('private')->temporaryUrl((string) $job->clip_key, now()->addMinutes(30)),
                'sampleRate' => $job->sample_rate,
                'frames' => $job->frames,
                'sha256' => $job->sha256,
                'part' => $job->part_key,
                'offsetFrames' => $job->offset_frames,
            ],
            'reference' => $job->from_voice && $job->voice !== null ? [
                'id' => $job->voice->sqid(),
                'url' => Storage::disk('private')->temporaryUrl((string) $job->voice->reference_key, now()->addMinutes(30)),
                'sampleRate' => $job->voice->sample_rate,
                'frames' => $job->voice->frames,
                'sha256' => $job->voice->sha256,
                'meta' => (object) $job->voice->meta,
            ] : null,
            'parts' => array_values(array_column(array_filter((array) ($version->data['parts'] ?? []), 'is_array'), 'key')),
        ];
    }
}
