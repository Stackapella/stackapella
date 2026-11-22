<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\OwnChoirStatus;
use App\Models\OwnChoirRequest;
use App\Services\Audio\Ffmpeg;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A choir song's lead, its singer's own voice (Hillel, 2026-11-23): the whole song they sang, or their clip's first
 * section when that's the whole song (Happy Birthday from voice-capture), levelled to the parts (−20 dB mean, peaks at
 * most −3 dBFS) and kept privately as AAC, beat 0 where it was. The WAV of a whole take goes once it's made.
 */
final class MakeChoirLead implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $requestId) {}

    public function handle(Ffmpeg $ffmpeg): void
    {
        $song = OwnChoirRequest::query()->with('templateVersion')->find($this->requestId);

        if ($song === null || $song->lead === null || $song->lead_key !== null || in_array($song->status, [OwnChoirStatus::Deleted, OwnChoirStatus::Failed], true)) {
            return;
        }

        $fromClip = is_array($song->lead['fromClip'] ?? null);
        $source = $fromClip ? $song->clip_key : $song->lead_upload_key;

        if ($source === null || ! Storage::disk('private')->exists($source)) {
            return;
        }

        $rate = $fromClip ? (int) $song->sample_rate : (int) ($song->lead['take']['sampleRate'] ?? 48000);
        $frames = $fromClip ? (int) $song->frames : (int) ($song->lead['take']['frames'] ?? 0);
        $offset = $fromClip ? (int) ($song->offset_frames ?? 0) : (int) ($song->lead['take']['offsetFrames'] ?? 0);
        // The clip's first section: to its last beat and a second of tail, at the tune's tempo.
        $to = $fromClip ? min($frames, $offset + (int) round(((float) $song->lead['fromClip']['beats'] * 60 / max(1.0, (float) ($song->clipVersion()->data['tempo'] ?? 100)) + 1) * $rate)) : $frames;

        $in = tempnam(sys_get_temp_dir(), 'lead').'.wav';
        $out = tempnam(sys_get_temp_dir(), 'lead').'.m4a';

        try {
            file_put_contents($in, (string) Storage::disk('private')->get($source));
            $level = $ffmpeg->volume($in);
            $gain = ($level['mean'] === null || $level['peak'] === null) ? 0.0 : min(-20.0 - $level['mean'], -3.0 - $level['peak']);
            $ffmpeg->encodeGuide($in, $out, $rate, 0, $to, $gain);
            $key = 'choir/leads/'.Str::lower(Str::random(32)).'.m4a';
            Storage::disk('private')->put($key, (string) file_get_contents($out));

            // Kept only while the song still is: else the copy goes too.
            $kept = DB::transaction(function () use ($key, $rate, $to, $offset): bool {
                $locked = OwnChoirRequest::query()->whereKey($this->requestId)->lockForUpdate()->first();

                if ($locked === null || $locked->lead === null || in_array($locked->status, [OwnChoirStatus::Deleted, OwnChoirStatus::Failed], true)) {
                    return false;
                }

                $locked->forceFill(['lead_key' => $key, 'lead' => [...$locked->lead, 'made' => ['sampleRate' => $rate, 'frames' => $to, 'offsetFrames' => $offset]]])->save();

                return true;
            });

            if (! $kept) {
                Storage::disk('private')->delete($key);
            }

            if (! $fromClip) {
                Storage::disk('private')->delete($source);
                OwnChoirRequest::query()->whereKey($this->requestId)->update(['lead_upload_key' => null]);
            }
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }
}
