<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TemplateGuide;
use App\Support\Gallery\GalleryViews;
use Illuminate\Support\Facades\Storage;

/**
 * A real voice as the app plays it (contract/schemas/guide.recording.json). The credit is the
 * singer's choice now, not when it was published: changing it shows at once.
 */
final class GuideRecording
{
    /**
     * Needs the guide's submission.assignment.singer.user loaded.
     *
     * @return array{id: string, partKey: string, url: string, sampleRate: int, frames: int, offsetFrames: int, credit: string|null, creditHandle: string|null}
     */
    public static function of(TemplateGuide $guide): array
    {
        return [
            // The version's own id: a learner's choice of voice names it (Hillel's decision).
            'id' => $guide->sqid(),
            'partKey' => $guide->part_key,
            'url' => Storage::disk('media')->url($guide->media_key),
            'sampleRate' => $guide->sample_rate,
            'frames' => $guide->frames,
            'offsetFrames' => $guide->offset_frames,
            // A beta singer's chosen credit, or a gallery singer's profile name.
            'credit' => $guide->submission !== null ? $guide->submission->assignment->singer->credit() : ($guide->creditUser !== null ? GalleryViews::person($guide->creditUser)['name'] : null),
            // Their public profile, for the credit to link to (null without one).
            'creditHandle' => $guide->submission !== null ? $guide->submission->assignment->singer->creditHandle() : ($guide->creditUser !== null ? GalleryViews::person($guide->creditUser)['handle'] : null),
        ];
    }
}
