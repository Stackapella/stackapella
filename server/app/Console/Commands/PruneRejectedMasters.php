<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SubmissionStatus;
use App\Models\BetaSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * A rejected beta take's WAV master is deleted after 90 days; accepted masters are kept, so a guide
 * can be published again with other trims (docs/beta-singers-plan.md, "Storage housekeeping").
 * Uploads never completed are removed after an hour (the private bucket has no lifecycle rule for
 * them: they're at their final keys). Runs hourly.
 */
final class PruneRejectedMasters extends Command
{
    protected $signature = 'app:beta:prune-masters';

    protected $description = 'Delete rejected beta masters after 90 days, uploads never completed after an hour,';

    public function handle(): int
    {
        $deleted = 0;

        BetaSubmission::query()
            ->whereIn('status', [SubmissionStatus::Rejected->value, SubmissionStatus::Failed->value])
            ->where('updated_at', '<', now()->subDays((int) config('beta.rejected_master_days')))
            ->whereNot('master_key', '')
            ->chunkById(200, function ($submissions) use (&$deleted): void {
                foreach ($submissions as $submission) {
                    /** @var BetaSubmission $submission */
                    Storage::disk('private')->delete($submission->master_key);
                    $submission->forceFill(['master_key' => ''])->saveQuietly();
                    $deleted++;
                }
            });

        // A take presigned but never completed (the app gave up, or someone sent a bigger file the
        // presigned PUT didn't stop): its file, if any arrived, and its row go after an hour. The
        // URL is valid for 30 minutes, so nothing real is still on its way.
        $orphans = 0;
        BetaSubmission::query()
            ->where('status', SubmissionStatus::Uploading->value)
            ->where('created_at', '<', now()->subHour())
            ->chunkById(200, function ($submissions) use (&$orphans): void {
                foreach ($submissions as $submission) {
                    /** @var BetaSubmission $submission */
                    Storage::disk('private')->delete($submission->master_key);
                    $submission->delete();
                    $orphans++;
                }
            });

        $this->info("Deleted {$deleted} masters and {$orphans} unfinished uploads.");

        return self::SUCCESS;
    }
}
