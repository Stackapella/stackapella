<?php

declare(strict_types=1);

namespace App\Actions\Beta;

use App\Enums\SubmissionStatus;
use App\Exceptions\Refusal;
use App\Jobs\ProcessSubmission;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Support\Storage\ReceivedUploads;
use Illuminate\Support\Facades\Storage;

/**
 * The PUT is done: the stored file must be exactly the declared size and SHA-256, then it's
 * processed on the `long` queue. Safe to call again: a take past `uploading` is left as it is.
 */
final class CompleteSubmission
{
    public function handle(BetaSinger $singer, BetaSubmission $submission): BetaSubmission
    {
        if ($submission->assignment->beta_singer_id !== $singer->id) {
            throw new Refusal(404, 'not_found');
        }

        if ($submission->status !== SubmissionStatus::Uploading) {
            return $submission;
        }

        // The checked copy is the master Hillel reviews and publishes: no upload link points at it.
        $received = app(ReceivedUploads::class)->receive($submission->master_key, $submission->bytes, $submission->sha256, 'beta/masters');

        // Another complete is checking it this moment: as it is.
        if ($received === null) {
            return $submission->refresh();
        }

        // Claimed in one statement: two completes at once dispatch the processing once.
        $claimed = BetaSubmission::query()->whereKey($submission->id)->where('status', SubmissionStatus::Uploading->value)->update(['status' => SubmissionStatus::Processing->value, 'master_key' => $received]);

        if ($claimed === 1) {
            ProcessSubmission::dispatch($submission->id);
        } else {
            Storage::disk('private')->delete($received);
        }

        $submission->refresh();

        return $submission;
    }
}
