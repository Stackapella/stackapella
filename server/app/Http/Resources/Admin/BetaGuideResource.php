<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Http\Resources\GuideRecording;
use App\Models\TemplateGuide;
use App\Support\Ids;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One real voice, for Hillel (contract/schemas/admin.beta.guide.json). Needs
 * submission.assignment.singer.user loaded.
 *
 * @property TemplateGuide $resource
 */
final class BetaGuideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return self::fields($this->resource);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fields(TemplateGuide $guide): array
    {
        $singer = $guide->submission?->assignment->singer;

        return [
            'id' => $guide->sqid(),
            'partKey' => $guide->part_key,
            'isDefault' => $guide->is_default,
            'recording' => GuideRecording::of($guide),
            'submissionId' => $guide->beta_submission_id !== null ? Ids::encode($guide->beta_submission_id) : null,
            'singer' => $singer !== null ? ['id' => $singer->sqid(), 'name' => $singer->name] : null,
            'publishedAt' => $guide->published_at->utc()->toIso8601ZuluString(),
            // That version's own take score, to compare a part's versions at a glance.
            'pitchPercent' => is_numeric($pitch = $guide->submission?->scores['pitchPercent'] ?? null) ? (int) round((float) $pitch) : null,
        ];
    }
}
