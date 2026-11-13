<?php

declare(strict_types=1);

namespace App\Support\Beta;

use App\Enums\SubmissionStatus;
use App\Models\BetaSubmission;

/**
 * What My parts (and the admin) shows for an assigned part, from its latest take: To record,
 * Submitted, In review, Hillel asked for another take, Now the guide, accepted as an alternate,
 * or not used.
 */
final class PartStatus
{
    /**
     * @param  bool  $isTheVoice  a take of this part is the default real voice on the current version
     * @param  bool  $askedAgain  Hillel asked for the part again (the song changed), and no take came since
     */
    public static function of(?BetaSubmission $latest, bool $isTheVoice, bool $askedAgain = false): string
    {
        if ($askedAgain) {
            return 'retake';
        }

        return match ($latest?->status) {
            null, SubmissionStatus::Uploading, SubmissionStatus::Failed => 'to_record',
            SubmissionStatus::Processing, SubmissionStatus::Submitted => 'submitted',
            SubmissionStatus::InReview => 'in_review',
            // Shared before the account's email was confirmed (voices.share): sent on once it is.
            SubmissionStatus::Held => 'held',
            SubmissionStatus::Retake => 'retake',
            SubmissionStatus::Rejected => 'rejected',
            SubmissionStatus::NotChosen => 'not_chosen',
            // Accepted, and either the voice learners hear, or kept as an alternate.
            SubmissionStatus::Accepted => $isTheVoice ? 'guide' : 'accepted',
        };
    }
}
