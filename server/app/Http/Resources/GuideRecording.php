<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TemplateGuide;
use App\Support\Gallery\GalleryViews;
use Illuminate\Support\Facades\Storage;

/**
 * A voice as the app plays it (contract/schemas/guide.recording.json). The credit is the
 * singer's choice now, not when it was published: changing it shows at once. An AI voice is never
 * credited as a singer: `source` and `aiBasedOn` label it instead (docs/ai-voices-build-plan.md).
 */
final class GuideRecording
{
    /**
     * Needs the guide's submission.assignment.singer.user loaded.
     *
     * @return array{id: string, partKey: string, url: string, sampleRate: int, frames: int, offsetFrames: int, credit: string|null, creditHandle: string|null, source: string, aiBasedOn: string|null}
     */
    public static function of(TemplateGuide $guide): array
    {
        $ai = $guide->isAi();

        return [
            // The version's own id: a learner's choice of voice names it (Hillel's decision).
            'id' => $guide->sqid(),
            'partKey' => $guide->part_key,
            'url' => Storage::disk('media')->url($guide->media_key),
            'sampleRate' => $guide->sample_rate,
            'frames' => $guide->frames,
            'offsetFrames' => $guide->offset_frames,
            // A beta singer's chosen credit, or a gallery singer's profile name.
            'credit' => $ai ? null : ($guide->submission !== null ? $guide->submission->assignment->singer->credit() : ($guide->creditUser !== null ? GalleryViews::person($guide->creditUser)['name'] : null)),
            // Their public profile, for the credit to link to (null without one).
            'creditHandle' => $ai ? null : ($guide->submission !== null ? $guide->submission->assignment->singer->creditHandle() : ($guide->creditUser !== null ? GalleryViews::person($guide->creditUser)['handle'] : null)),
            'source' => $guide->source->value,
            // "AI voice based on <name>'s singing, with permission"; null for a recorded voice or one not based on a person.
            'aiBasedOn' => $guide->aiBasedOn(),
        ];
    }
}
