<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a recording's harmony compares with our arrangement, as the person who synced it heard it: the app says so on
 * the recording's card when it differs (docs/youtube-sync-plan.md, "Notes that differ").
 */
enum SongVideoHarmony: string
{
    case Same = 'same';
    case Similar = 'similar';
    case Different = 'different';

    public function label(): string
    {
        return __('enums.song_video_harmony.'.$this->value);
    }
}
