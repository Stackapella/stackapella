<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ReviewMedia;
use App\Services\Audio\Ffmpeg;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A coach's voice note or demonstration take for a review, checked to be the WAV it was declared to
 * be (as ProcessTake does), then kept as AAC in `private` (docs/coaching-marketplace-plan.md F4).
 */
final class ProcessReviewMedia implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 300;

    public function __construct(public readonly int $mediaId)
    {
        $this->onQueue('long');
    }

    public function handle(Ffmpeg $ffmpeg): void
    {
        $media = ReviewMedia::query()->find($this->mediaId);

        if ($media === null || $media->status !== ReviewMedia::PROCESSING) {
            return;
        }

        $wav = (string) tempnam(sys_get_temp_dir(), 'review-media');
        $aac = $wav.'.m4a';
        file_put_contents($wav, Storage::disk('private')->readStream($media->upload_key));

        try {
            $probe = $ffmpeg->probe($wav);
            $failure = match (true) {
                $probe === null => 'not_audio',
                ! str_contains($probe['format'], 'wav') || $probe['codec'] !== 'pcm_s16le' || $probe['channels'] !== 1 || $probe['sampleRate'] !== $media->sample_rate => 'wrong_format',
                $probe['frames'] === null || abs($probe['frames'] - $media->frames) > 1 => 'wrong_format',
                $media->frames > (int) config('usage.take_max_seconds') * $media->sample_rate => 'too_long',
                default => null,
            };

            if ($failure !== null) {
                $media->forceFill(['status' => ReviewMedia::FAILED, 'failure' => $failure])->save();
                Storage::disk('private')->delete($media->upload_key);

                return;
            }

            $ffmpeg->encodeGuide($wav, $aac, $media->sample_rate, 0, $media->frames, 0.0);
            $key = 'reviews/'.Str::lower(Str::random(32)).'.m4a';
            Storage::disk('private')->put($key, (string) file_get_contents($aac));

            if (ReviewMedia::query()->whereKey($media->id)->where('status', ReviewMedia::PROCESSING)->update(['status' => ReviewMedia::READY, 'file_key' => $key, 'updated_at' => now()]) === 0) {
                Storage::disk('private')->delete($key);
            }

            Storage::disk('private')->delete($media->upload_key);
        } finally {
            @unlink($wav);
            @unlink($aac);
        }
    }

    public function failed(): void
    {
        ReviewMedia::query()->whereKey($this->mediaId)->where('status', ReviewMedia::PROCESSING)->update(['status' => ReviewMedia::FAILED, 'failure' => 'processing_error', 'updated_at' => now()]);
    }
}
