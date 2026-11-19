<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why YouTube's last check hid a recording: the video went, went private, stopped allowing embeds, is made for kids,
 * or is age-restricted (none of which we may show; docs/youtube-sync-plan.md).
 */
enum SongVideoHidden: string
{
    case Removed = 'removed';
    case Private = 'private';
    case NotEmbeddable = 'not_embeddable';
    case MadeForKids = 'made_for_kids';
    case AgeRestricted = 'age_restricted';

    public function label(): string
    {
        return __('enums.song_video_hidden.'.$this->value);
    }
}
