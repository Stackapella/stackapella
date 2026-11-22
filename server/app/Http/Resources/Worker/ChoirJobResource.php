<?php

declare(strict_types=1);

namespace App\Http\Resources\Worker;

use App\Models\OwnChoirRequest;
use App\Support\OwnChoir\ChoirSettings;
use App\Support\OwnChoir\ClipTune;
use App\Support\OwnChoir\VoicingTransform;
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
        $voiced = match ($job->voicing) {
            'low' => VoicingTransform::low((array) $version->data),
            'full' => VoicingTransform::full((array) $version->data),
            default => ['data' => VoicingTransform::inRange((array) $version->data), 'shifts' => array_fill_keys(array_column(array_filter((array) ($version->data['parts'] ?? []), 'is_array'), 'key'), 0)],
        };

        return [
            'id' => $job->sqid(),
            // Sent back with every call on this job: only this claim acts on it.
            'claim' => (string) $job->claim_token,
            'template' => (string) $version->template->slug,
            'version' => $version->version,
            'title' => (string) $version->template->title,
            // The singer's own song: no gates re-render (the GPU review).
            'own' => $this->resource->own,
            // The low-voice version (2026-11-23): the transformed song, rendered as given.
            'data' => (object) $voiced['data'],
            'voicing' => $job->voicing,
            // Plus's choir settings (2026-11-23): how it's sung; today's sound unless set. Voicing is in data.
            'settings' => (object) array_diff_key(ChoirSettings::sung($job), ['voicing' => true]),
            'shifts' => array_map(static fn (string $key, int $shift): array => ['partKey' => $key, 'shift' => $shift], array_keys($voiced['shifts']), array_values($voiced['shifts'])),
            'clip' => $job->from_voice ? null : [
                'url' => Storage::disk('private')->temporaryUrl((string) $job->clip_key, now()->addMinutes(30)),
                'sampleRate' => $job->sample_rate,
                'frames' => $job->frames,
                'sha256' => $job->sha256,
                'part' => $job->part_key,
                'offsetFrames' => $job->offset_frames,
                // The tune the clip sang (voice-capture by default, 2026-11-23): prepare the voice against it.
                'template' => (string) ($job->clipVersion() ?? $version)->template->slug,
                'version' => ($job->clipVersion() ?? $version)->version,
                'data' => (object) ClipTune::sung($job->clipVersion() ?? $version, $job->clip_transpose),
                'transpose' => $job->clip_transpose,
                // false: sung with no headphones, the tempo kept by eye: align phrase by phrase.
                'guide' => $job->guide,
            ],
            'reference' => $job->from_voice && $job->voice !== null ? [
                'id' => $job->voice->sqid(),
                'url' => Storage::disk('private')->temporaryUrl((string) $job->voice->reference_key, now()->addMinutes(30)),
                'sampleRate' => $job->voice->sample_rate,
                'frames' => $job->voice->frames,
                'sha256' => $job->voice->sha256,
                'meta' => (object) $job->voice->meta,
            ] : null,
            // Every part, or a friend's only (choir together): the worker renders exactly these.
            'parts' => array_values($this->resource->only_parts ?? array_column(array_filter((array) ($version->data['parts'] ?? []), 'is_array'), 'key')),
        ];
    }
}
