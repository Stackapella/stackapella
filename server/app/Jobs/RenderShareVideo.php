<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Harmony;
use App\Models\ShareVideo;
use App\Services\Sharing\ShareVideoRenderer;
use App\Support\Flags;
use App\Support\Http\Hosts;
use App\Support\Sharing\HarmonyFacts;
use App\Support\Sharing\Karaoke;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Share as a video, on the `long` queue (docs/more-features-plan.md, feature 4). At most
 * `sharing.videos.concurrent` render at once across everyone (the global cap protects the bill):
 * a video that finds no free slot, or videos paused, waits and tries again. One that can't start
 * within the hour fails as timed_out.
 */
final class RenderShareVideo implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Waiting for a slot is many short tries; a render itself is tried once. */
    public int $tries = 0;

    public int $timeout = 290;

    /** A render past its time fails (failed() below), never left `rendering`. */
    public bool $failOnTimeout = true;

    public function __construct(public readonly int $videoId)
    {
        $this->onQueue('long');
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes((int) config('sharing.videos.wait_minutes') + 10);
    }

    public function handle(Flags $flags, ShareVideoRenderer $renderer, HarmonyFacts $facts): void
    {
        $video = ShareVideo::query()->with('harmony.user', 'harmony.templateVersion.template')->find($this->videoId);

        if ($video === null || $video->status !== ShareVideo::QUEUED) {
            return;
        }

        if ($video->created_at !== null && $video->created_at->lt(now()->subMinutes((int) config('sharing.videos.wait_minutes')))) {
            $video->forceFill(['status' => ShareVideo::FAILED, 'failure' => 'timed_out'])->save();

            return;
        }

        if ($flags->pausing('videos') !== null) {
            $this->release(30);

            return;
        }

        $slot = $this->slot();

        if ($slot === null) {
            $this->release(10);

            return;
        }

        try {
            $this->render($video, $renderer, $facts);
        } finally {
            $slot->release();
        }
    }

    private function render(ShareVideo $video, ShareVideoRenderer $renderer, HarmonyFacts $facts): void
    {
        // Claimed in one statement, so a second copy of this job never renders it twice.
        if (ShareVideo::query()->whereKey($video->id)->where('status', ShareVideo::QUEUED)->update(['status' => ShareVideo::RENDERING, 'started_at' => now(), 'updated_at' => now()]) !== 1) {
            return;
        }

        $harmony = $video->harmony;
        $audio = (string) tempnam(sys_get_temp_dir(), 'video-mix');
        $out = $audio.'.mp4';

        try {
            // Hidden, made private or deleted since it was asked for: its link is no longer one to share.
            if ($harmony->audio_key === null || ! $harmony->isLive()) {
                $this->markFailed($video);

                return;
            }

            file_put_contents($audio, Storage::disk('private')->readStream($harmony->audio_key));
            $start = $video->start_ms / 1000;
            $end = $video->end_ms / 1000;
            $lastSaved = 0.0;

            $renderer->render([
                'audio' => $audio,
                'out' => $out,
                'start' => $start,
                'end' => $end,
                'look' => $video->look,
                'title' => $facts->title($harmony),
                'singers' => $facts->singersLine($harmony),
                'song' => $facts->songLine($harmony),
                'link' => preg_replace('#^https?://#', '', Hosts::public('h/'.$harmony->public_id)) ?? '',
                'tracks' => $harmony->tracks,
                'lines' => $video->words ? $this->lines($harmony, $start, $end) : [],
            ], static function (float $progress) use ($video, &$lastSaved): void {
                // Often enough for the bar, without writing on every line ffmpeg prints.
                if ($progress - $lastSaved >= 0.04) {
                    $lastSaved = $progress;
                    ShareVideo::query()->whereKey($video->id)->update(['progress' => round($progress, 3)]);
                }
            });

            $key = 'exports/videos/'.Str::lower(Str::random(32)).'.mp4';
            Storage::disk('private')->put($key, (string) file_get_contents($out));

            $video->forceFill(['status' => ShareVideo::READY, 'progress' => 1, 'file_key' => $key, 'bytes' => (int) filesize($out), 'expires_at' => now()->addDays((int) config('sharing.videos.keep_days'))])->save();
        } catch (Throwable $e) {
            report($e);
            $this->markFailed($video);
        } finally {
            @unlink($audio);
            @unlink($out);
        }
    }

    /**
     * The words sung in the section, from the template, at the project's tempo.
     *
     * @return list<list<array{text: string, start: float, end: float, joins: bool}>>
     */
    private function lines(Harmony $harmony, float $start, float $end): array
    {
        if ($harmony->templateVersion === null || $harmony->words_part_key === null || $harmony->beat_zero_ms === null || $harmony->bpm === null) {
            return [];
        }

        $lines = Karaoke::lines($harmony->templateVersion->data, $harmony->words_part_key, (float) $harmony->bpm, $harmony->beat_zero_ms / 1000);

        return Karaoke::section($lines, $start, $end);
    }

    /**
     * One of the shared render slots, or null when they're all taken.
     */
    private function slot(): ?Lock
    {
        for ($i = 0; $i < (int) config('sharing.videos.concurrent'); $i++) {
            $lock = Cache::lock("share-video-slot:{$i}", $this->timeout + 30);

            if ($lock->get()) {
                return $lock;
            }
        }

        return null;
    }

    private function markFailed(ShareVideo $video): void
    {
        ShareVideo::query()->whereKey($video->id)->where('status', ShareVideo::RENDERING)->update(['status' => ShareVideo::FAILED, 'failure' => 'render_error', 'updated_at' => now()]);
    }

    /**
     * Gone past its time (or killed): never left rendering for ever.
     */
    public function failed(): void
    {
        ShareVideo::query()->whereKey($this->videoId)->whereIn('status', [ShareVideo::QUEUED, ShareVideo::RENDERING])->update(['status' => ShareVideo::FAILED, 'failure' => 'render_error', 'updated_at' => now()]);
    }
}
