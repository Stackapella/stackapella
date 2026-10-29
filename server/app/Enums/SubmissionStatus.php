<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a beta take is: waiting for its upload, being processed, waiting for Hillel, and his
 * decision. "failed" means the upload wasn't the audio it claimed to be.
 */
enum SubmissionStatus: string
{
    case Uploading = 'uploading';
    case Processing = 'processing';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Retake = 'retake';
    case Rejected = 'rejected';
    case Accepted = 'accepted';
    case Failed = 'failed';

    public function label(): string
    {
        return __('enums.submission_status.'.$this->value);
    }

    /**
     * Still waiting for Hillel: the queue.
     */
    public function isWaiting(): bool
    {
        return in_array($this, [self::Submitted, self::InReview], true);
    }
}
