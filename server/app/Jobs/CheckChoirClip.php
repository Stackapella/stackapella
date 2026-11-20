<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\OwnChoir\ChoirJobs;
use App\Enums\OwnChoirStatus;
use App\Models\OwnChoirRequest;
use App\Services\Audio\Ffmpeg;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/**
 * A singer's clip, checked before the renderer may claim it (track B): the 16-bit mono WAV of the length declared, 5 to
 * 30 seconds. Not that: the song fails `bad_clip` and the clip goes at once.
 */
final class CheckChoirClip implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $requestId) {}

    public function handle(Ffmpeg $ffmpeg): void
    {
        $request = OwnChoirRequest::query()->find($this->requestId);

        if ($request === null || $request->status !== OwnChoirStatus::Queued || $request->clip_key === null || $request->clip_checked_at !== null) {
            return;
        }

        $local = (string) tempnam(sys_get_temp_dir(), 'clip');
        file_put_contents($local, Storage::disk('private')->readStream($request->clip_key));

        try {
            $probe = $ffmpeg->probe($local);
            $seconds = $request->frames / max(1, $request->sample_rate);
            $good = $probe !== null && str_contains($probe['format'], 'wav') && $probe['codec'] === 'pcm_s16le' && $probe['channels'] === 1
                && $probe['sampleRate'] === $request->sample_rate && $probe['frames'] !== null && abs($probe['frames'] - $request->frames) <= 1
                && $seconds >= (float) config('own_choir.clip_min_seconds') && $seconds <= (float) config('own_choir.clip_max_seconds') + 0.05;

            if ($good) {
                $request->forceFill(['clip_checked_at' => now()])->save();

                return;
            }

            ChoirJobs::failAndClean($request, 'bad_clip');
        } finally {
            @unlink($local);
        }
    }

    /** Every try failed (storage, ffprobe, the timeout): the song fails and its clip goes, so nothing waits forever. */
    public function failed(): void
    {
        $request = OwnChoirRequest::query()->whereKey($this->requestId)->where('status', OwnChoirStatus::Queued->value)->whereNull('clip_checked_at')->first();

        if ($request !== null) {
            ChoirJobs::failAndClean($request, 'bad_clip');
        }
    }
}
