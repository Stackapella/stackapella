<?php

declare(strict_types=1);

namespace App\Http\Resources\Projects;

use App\Models\Take;
use App\Support\Audio\Peaks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * contract/schemas/take.json. Needs part loaded (for its UUID). With a download `url` only where
 * asked (takes.show, takes.complete): signing is cheap, but lists don't need it. The peaks come as
 * `peaks8` (one byte each, base64; contract/api.md, "Peaks").
 *
 * @property Take $resource
 */
final class TakeResource extends JsonResource
{
    public bool $withUrl = false;

    /**
     * @return array<string, mixed>
     */
    public static function fields(Take $take, bool $withUrl = false): array
    {
        $fields = [
            'id' => $take->uuid,
            'partId' => $take->part?->uuid,
            'status' => $take->status,
            'failure' => $take->failure,
            'sampleRate' => $take->sample_rate,
            'frames' => $take->frames,
            'offsetFrames' => $take->offset_frames,
            'bytes' => $take->bytes(),
            'sha256' => $take->sha256,
            'framesPerPeak' => $take->frames_per_peak,
            'peaks8' => Peaks::toBase64($take->peaks),
            'createdAt' => $take->created_at?->utc()->toIso8601ZuluString(),
            'binnedAt' => $take->binned_at?->utc()->toIso8601ZuluString(),
        ];

        if ($withUrl) {
            $fields['url'] = $take->status === Take::READY && $take->file_key !== null
                ? Storage::disk('private')->temporaryUrl($take->file_key, now()->addMinutes(15))
                : null;
        }

        return $fields;
    }

    public function withUrl(): self
    {
        $this->withUrl = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return self::fields($this->resource, $this->withUrl);
    }
}
