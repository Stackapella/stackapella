<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OwnChoirPart;
use App\Services\Audio\Ffmpeg;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One part of a song in a singer's own voice (track B), checked and encoded as the guides are (levelled, AAC), kept on
 * the private bucket for that singer alone; its master deleted. Not the audio declared: failed, nothing kept.
 */
final class EncodeChoirPart implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $partId)
    {
        $this->onQueue('long');
    }

    public function handle(Ffmpeg $ffmpeg): void
    {
        $part = OwnChoirPart::query()->find($this->partId);

        if ($part === null || $part->status !== 'processing' || $part->master_key === null) {
            return;
        }

        $in = (string) tempnam(sys_get_temp_dir(), 'choirpart');
        $out = sys_get_temp_dir().'/choir-'.Str::lower(Str::random(16)).'.m4a';
        file_put_contents($in, Storage::disk('private')->readStream($part->master_key));

        try {
            $probe = $ffmpeg->probe($in);

            if ($probe === null || ! str_contains($probe['format'], 'wav') || $probe['codec'] !== 'pcm_s16le' || $probe['channels'] !== 1 || $probe['sampleRate'] !== $part->sample_rate || $probe['frames'] === null || abs($probe['frames'] - $part->frames) > 1) {
                Storage::disk('private')->delete($part->master_key);
                $part->forceFill(['status' => 'failed', 'failure' => 'bad_audio', 'master_key' => null])->save();

                return;
            }

            $volume = $ffmpeg->volume($in);
            $gain = $volume['mean'] !== null ? (float) config('beta.guide_rms_dbfs') - $volume['mean'] : 0.0;
            $gain = max(-24.0, min(24.0, $volume['peak'] !== null ? min($gain, -2.0 - $volume['peak']) : $gain));
            $ffmpeg->encodeGuide($in, $out, $part->sample_rate, 0, $part->frames, $gain);

            $key = 'choir/parts/'.Str::lower(Str::random(32)).'.m4a';
            $stream = fopen($out, 'rb');
            Storage::disk('private')->writeStream($key, $stream === false ? throw new \RuntimeException("Couldn't open {$out}.") : $stream, ['ContentType' => 'audio/mp4']);
            is_resource($stream) && fclose($stream);

            $master = $part->master_key;
            // Deleted meanwhile (the singer deleted the song, or everything): nothing kept.
            if (OwnChoirPart::query()->whereKey($part->id)->update(['status' => 'ready', 'media_key' => $key, 'master_key' => null]) === 0) {
                Storage::disk('private')->delete($key);
            }
            Storage::disk('private')->delete($master);
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }

    public function failed(): void
    {
        OwnChoirPart::query()->whereKey($this->partId)->where('status', 'processing')->update(['status' => 'failed', 'failure' => 'publish_failed']);
    }
}
