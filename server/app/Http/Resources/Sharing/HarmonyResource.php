<?php

declare(strict_types=1);

namespace App\Http\Resources\Sharing;

use App\Actions\Gallery\Consents;
use App\Actions\Gallery\OpenParts;
use App\Models\Harmony;
use App\Support\Http\Hosts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * contract/schemas/harmony.json: the owner's view. Needs project, part and user loaded.
 *
 * @property Harmony $resource
 */
final class HarmonyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fields(Harmony $harmony): array
    {
        $ready = $harmony->status === Harmony::READY && $harmony->audio_key !== null;

        return [
            'id' => $harmony->public_id,
            'projectId' => $harmony->project?->uuid,
            'partId' => $harmony->part?->uuid,
            'title' => $harmony->title,
            'visibility' => $harmony->visibility,
            'mayFeature' => $harmony->may_feature_at !== null,
            'inGallery' => $harmony->gallery_opt_in_at !== null,
            // The gallery (N1): waiting for review, said they may share it, comments off, and
            // each friend's answer.
            'galleryHeld' => $harmony->gallery_held_at !== null,
            'rightsConfirmed' => $harmony->rights_confirmed_at !== null,
            'commentsOff' => $harmony->comments_off_at !== null,
            'singers' => Consents::singers($harmony),
            // Open parts (N5): those left open, a fill's original, and the fill featured on it.
            'openParts' => app(OpenParts::class)->partNames($harmony),
            'openPartsGuide' => (bool) ($harmony->getAttributes()['open_parts_guide'] ?? true),
            'parent' => OpenParts::parentOf($harmony),
            'featuredFill' => app(OpenParts::class)->featuredFillOf($harmony),
            'status' => $harmony->status,
            'failure' => $harmony->failure,
            'sampleRate' => $harmony->sample_rate,
            'channels' => $harmony->channels,
            'frames' => $harmony->frames,
            'durationSeconds' => $harmony->durationSeconds(),
            'moderation' => $harmony->moderation(),
            'titleHeld' => $harmony->title_held_at !== null,
            'pageUrl' => Hosts::public('h/'.$harmony->public_id),
            'audioUrl' => $ready ? Storage::disk('private')->temporaryUrl((string) $harmony->audio_key, now()->addMinutes(15)) : null,
            'cardUrl' => $ready && $harmony->card_key !== null ? Storage::disk('private')->temporaryUrl($harmony->card_key, now()->addMinutes(15)) : null,
            'createdAt' => $harmony->created_at?->utc()->toIso8601ZuluString(),
            'updatedAt' => $harmony->updated_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return self::fields($this->resource);
    }
}
