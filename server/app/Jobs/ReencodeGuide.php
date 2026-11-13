<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SubmissionStatus;
use App\Models\TemplateGuide;
use App\Services\Audio\Ffmpeg;
use App\Services\DigitalOcean\CdnPurge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A published guide encoded again at beta.guide_kbps from its take's master, in place: the same guide (its id, which the
 * apps keep as a chosen voice, its version, default and timing), a new file. Skipped when it's already at that bitrate,
 * has no master, or would come out with other timing (then it's left as it is and said in the log). Safe to run again.
 */
final class ReencodeGuide implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 300;

    public function __construct(public readonly int $guideId)
    {
        $this->onQueue('long');
    }

    public function handle(Ffmpeg $ffmpeg): void
    {
        $kbps = (int) config('beta.guide_kbps');
        $guide = TemplateGuide::query()->with('submission')->find($this->guideId);
        $submission = $guide?->submission;

        if ($guide === null || $submission === null || ($guide->kbps ?? 128) === $kbps || $submission->status !== SubmissionStatus::Accepted
            || $submission->master_key === null || ! Storage::disk('private')->exists($submission->master_key)) {
            return;
        }

        $out = sys_get_temp_dir().'/reencode-'.Str::lower(Str::random(16)).'.m4a';

        try {
            $cut = (new PublishGuide($submission->id))->encode($ffmpeg, $submission, $out);
            if (max(1, $cut['to'] - $cut['from']) !== $guide->frames || $cut['offsetFrames'] !== $guide->offset_frames) {
                Log::warning('A guide would re-encode with other timing; left as it is.', ['guide' => $guide->id]);

                return;
            }

            $key = 'guides/'.Str::lower(Str::random(32)).'.m4a';
            Storage::disk('media')->put($key, (string) file_get_contents($out), ['visibility' => 'public', 'ContentType' => 'audio/mp4', 'CacheControl' => 'public, max-age=31536000, immutable']);

            // The file no guide uses now: the old one, or this upload when another run got there first.
            $unused = DB::transaction(function () use ($guide, $key, $kbps): string {
                $row = TemplateGuide::query()->whereKey($guide->id)->lockForUpdate()->first();
                if ($row === null || ($row->kbps ?? 128) === $kbps) {
                    return $key;
                }
                $was = $row->media_key;
                $row->forceFill(['media_key' => $key, 'kbps' => $kbps])->save();

                return $was;
            });

            app(CdnPurge::class)->forget([$unused]);
            Storage::disk('media')->delete($unused);
        } finally {
            @unlink($out);
        }
    }
}
