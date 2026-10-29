<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SubmissionStatus;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\CourseClip;
use App\Models\TemplateGuide;
use App\Services\Audio\Ffmpeg;
use App\Services\DigitalOcean\CdnPurge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * An accepted take becomes a guide: Hillel's nudge applied (the device already removed its latency,
 * so frame 0 of the take is beat 0), the trims and 10 ms fades, the gain (levelled to about -20 dBFS), as an AAC on the CDN at an immutable key. The
 * guide's frame 0 sits offsetFrames after the template's beat 0 (contract/api.md, "A take's
 * timing"). Safe to run again: a take already published isn't published twice.
 */
final class PublishGuide implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 300;

    public function __construct(public readonly int $submissionId, public readonly bool $makeDefault = true)
    {
        $this->onQueue('long');
    }

    public function handle(Ffmpeg $ffmpeg): void
    {
        $submission = BetaSubmission::query()->find($this->submissionId);

        if ($submission === null || $submission->status !== SubmissionStatus::Accepted) {
            return;
        }

        // A course clip, not a song's part: published to its slot instead.
        if ($submission->assignment->course_clip_id !== null) {
            $this->publishClip($ffmpeg, $submission);

            return;
        }

        // Published again (new trims): the take's old guide is replaced, keeping its place as the
        // default if it had it.
        $previous = TemplateGuide::query()->where('beta_submission_id', $submission->id)->first();

        $rate = $submission->sample_rate;
        $ms = static fn (int|float $milliseconds): int => (int) round($milliseconds * $rate / 1000);

        // The song time where the guide starts (after the trimmed start), and the take's sample that
        // sounds then. Frame 0 of the take is beat 0; a positive nudge plays it later.
        $startTime = $ms($submission->trim_start_ms);
        $from = $startTime - $ms($submission->offset_ms);
        $offsetFrames = $startTime;

        if ($from < 0) {
            $offsetFrames += -$from;
            $from = 0;
        }

        $to = $submission->frames - $ms($submission->trim_end_ms);
        $target = (float) config('beta.guide_rms_dbfs');
        $level = $submission->loudness_dbfs !== null ? $target - (float) $submission->loudness_dbfs : 0.0;
        $gain = max(-24.0, min(24.0, $level + (float) $submission->gain_db));

        // Never louder than the take's loudest sample allows: at most -2 dBFS after the gain, which
        // leaves room for the AAC encoder's overshoot (about 0.7 dB on a pure tone).
        if ($submission->peak_dbfs !== null) {
            $gain = min($gain, -2.0 - (float) $submission->peak_dbfs);
        }

        $in = (string) tempnam(sys_get_temp_dir(), 'master');
        $out = sys_get_temp_dir().'/guide-'.Str::lower(Str::random(16)).'.m4a';
        file_put_contents($in, Storage::disk('private')->readStream($submission->master_key));

        try {
            $ffmpeg->encodeGuide($in, $out, $rate, $from, max($from + 1, $to), $gain);

            $key = 'guides/'.Str::lower(Str::random(32)).'.m4a';
            Storage::disk('media')->put($key, (string) file_get_contents($out), ['visibility' => 'public', 'ContentType' => 'audio/mp4', 'CacheControl' => 'public, max-age=31536000, immutable']);

            $published = DB::transaction(function () use ($submission, $key, $rate, $from, $to, $offsetFrames, $previous): bool {
                // Forgotten meanwhile (the singer asked to be removed): nothing is published.
                $singer = BetaSinger::query()->whereKey($submission->assignment->beta_singer_id)->lockForUpdate()->first();

                if ($singer === null || $singer->forgotten_at !== null) {
                    return false;
                }

                $wasDefault = $previous !== null && $previous->is_default;
                // A republish keeps when Hillel chose this voice; choosing it now is a new choice.
                $defaultedAt = $this->makeDefault ? now() : $previous?->defaulted_at;
                $previous?->delete();

                $hasDefault = TemplateGuide::query()->where('template_version_id', $submission->template_version_id)->where('part_key', $submission->part_key)->where('is_default', true)->lockForUpdate()->exists();
                $isDefault = $this->makeDefault || $wasDefault || ! $hasDefault;

                if ($isDefault) {
                    TemplateGuide::query()->where('template_version_id', $submission->template_version_id)->where('part_key', $submission->part_key)->update(['is_default' => false]);
                }

                $guide = new TemplateGuide(['part_key' => $submission->part_key]);
                $guide->forceFill([
                    'template_version_id' => $submission->template_version_id,
                    'beta_submission_id' => $submission->id,
                    'is_default' => $isDefault,
                    'defaulted_at' => $defaultedAt,
                    'media_key' => $key,
                    'sample_rate' => $rate,
                    'frames' => max(1, $to - $from),
                    'offset_frames' => $offsetFrames,
                    'gain' => 1,
                    'published_at' => now(),
                ])->save();

                $submission->forceFill(['publish_status' => 'published'])->save();

                return true;
            });

            if (! $published) {
                Storage::disk('media')->delete($key);

                return;
            }

            if ($previous !== null) {
                app(CdnPurge::class)->forget([$previous->media_key]);
                Storage::disk('media')->delete($previous->media_key);
            }
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }

    /**
     * A course clip (CourseClips): trimmed and levelled as a guide is, then the slot's recording,
     * replacing any earlier one; the singer is credited from their submission.
     */
    private function publishClip(Ffmpeg $ffmpeg, BetaSubmission $submission): void
    {
        $rate = $submission->sample_rate;
        $from = (int) round($submission->trim_start_ms * $rate / 1000);
        $to = $submission->frames - (int) round($submission->trim_end_ms * $rate / 1000);
        $target = (float) config('beta.guide_rms_dbfs');
        $gain = max(-24.0, min(24.0, ($submission->loudness_dbfs !== null ? $target - (float) $submission->loudness_dbfs : 0.0) + (float) $submission->gain_db));

        if ($submission->peak_dbfs !== null) {
            $gain = min($gain, -2.0 - (float) $submission->peak_dbfs);
        }

        $in = (string) tempnam(sys_get_temp_dir(), 'master');
        $out = sys_get_temp_dir().'/clip-'.Str::lower(Str::random(16)).'.m4a';
        file_put_contents($in, Storage::disk('private')->readStream($submission->master_key));

        try {
            $ffmpeg->encodeGuide($in, $out, $rate, $from, max($from + 1, $to), $gain);
            $key = 'clips/'.Str::lower(Str::random(32)).'.m4a';
            Storage::disk('media')->put($key, (string) file_get_contents($out), ['visibility' => 'public', 'ContentType' => 'audio/mp4', 'CacheControl' => 'public, max-age=31536000, immutable']);

            $previous = DB::transaction(function () use ($submission, $key, $rate, $from, $to): ?string {
                $singer = BetaSinger::query()->whereKey($submission->assignment->beta_singer_id)->lockForUpdate()->first();
                $clip = CourseClip::query()->whereKey($submission->assignment->course_clip_id)->lockForUpdate()->first();

                if ($singer === null || $singer->forgotten_at !== null || $clip === null) {
                    return $key;
                }

                $old = $clip->media_key;
                $clip->forceFill(['beta_submission_id' => $submission->id, 'media_key' => $key, 'sample_rate' => $rate, 'frames' => max(1, $to - $from), 'published_at' => now()])->save();
                $submission->forceFill(['publish_status' => 'published'])->save();

                return $old;
            });

            if ($previous !== null) {
                app(CdnPurge::class)->forget([$previous]);
                Storage::disk('media')->delete($previous);
            }
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }

    /**
     * Every try failed: the queue shows it, and Hillel can publish it again.
     */
    public function failed(): void
    {
        BetaSubmission::query()->whereKey($this->submissionId)->update(['publish_status' => 'failed']);
    }
}
