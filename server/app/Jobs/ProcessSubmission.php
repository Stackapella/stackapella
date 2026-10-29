<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SubmissionStatus;
use App\Models\BetaSubmission;
use App\Services\Audio\Ffmpeg;
use App\Support\Audio\Peaks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/**
 * Check a beta take really is what the device said (a mono 16-bit WAV of exactly those frames, at
 * that rate, at most 10 minutes), then work out its outline and loudness for the review. Anything
 * else is refused (status failed, with a reason the singer sees in their own words).
 */
final class ProcessSubmission implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 300;

    public function __construct(public readonly int $submissionId)
    {
        $this->onQueue('long');
    }

    public function handle(Ffmpeg $ffmpeg): void
    {
        $submission = BetaSubmission::query()->find($this->submissionId);

        if ($submission === null || $submission->status !== SubmissionStatus::Processing) {
            return;
        }

        $local = (string) tempnam(sys_get_temp_dir(), 'take');
        file_put_contents($local, Storage::disk('private')->readStream($submission->master_key));

        try {
            $probe = $ffmpeg->probe($local);
            $failure = match (true) {
                $probe === null => 'not_audio',
                ! str_contains($probe['format'], 'wav') || $probe['codec'] !== 'pcm_s16le' || $probe['channels'] !== 1 || $probe['sampleRate'] !== $submission->sample_rate => 'wrong_format',
                $probe['frames'] === null || abs($probe['frames'] - $submission->frames) > 1 || $submission->frames > (int) config('beta.max_seconds') * $submission->sample_rate => 'wrong_length',
                default => null,
            };

            if ($failure !== null) {
                $submission->forceFill(['status' => SubmissionStatus::Failed, 'failure' => $failure])->save();

                return;
            }

            $volume = $ffmpeg->volume($local);

            $submission->forceFill([
                'status' => SubmissionStatus::Submitted,
                'peaks' => Peaks::fromWav($local, $submission->sample_rate),
                'loudness_dbfs' => $volume['mean'],
                'peak_dbfs' => $volume['peak'],
            ])->save();
        } finally {
            @unlink($local);
        }
    }

    /**
     * Every try failed (ffmpeg, storage or the timeout): the take says so, instead of staying
     * "processing" for good. The singer can send it again.
     */
    public function failed(): void
    {
        BetaSubmission::query()
            ->whereKey($this->submissionId)
            ->where('status', SubmissionStatus::Processing->value)
            ->update(['status' => SubmissionStatus::Failed->value, 'failure' => 'processing_error']);
    }
}
