<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A synced recording's state: a draft until someone else plays it through and approves it; only approved ones show.
 */
enum SongVideoStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';

    public function label(): string
    {
        return __('enums.song_video_status.'.$this->value);
    }
}
