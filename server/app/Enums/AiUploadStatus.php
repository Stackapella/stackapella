<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * An AI master on its way to becoming a guide (the worker API): sent, being checked and published, published, or
 * failed with a reason.
 */
enum AiUploadStatus: string
{
    case Uploading = 'uploading';
    case Processing = 'processing';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return __('enums.ai_upload_status.'.$this->value);
    }
}
