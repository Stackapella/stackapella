<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A song in a singer's own voice (track B): its clip on the way, waiting for the renderer, being rendered, ready, or
 * failed with a reason.
 */
enum OwnChoirStatus: string
{
    case Uploading = 'uploading';
    case Queued = 'queued';
    case Rendering = 'rendering';
    case Ready = 'ready';
    case Failed = 'failed';
    // Deleted by the singer: nothing of it is kept but the fact it was asked for, which still counts toward the quota.
    case Deleted = 'deleted';

    public function label(): string
    {
        return __('enums.own_choir_status.'.$this->value);
    }

    /** Still to finish: it holds the singer's one open song. */
    public function open(): bool
    {
        return in_array($this, [self::Uploading, self::Queued, self::Rendering], true);
    }
}
