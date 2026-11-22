<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OwnChoirVoice;
use App\Services\Audio\Ffmpeg;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The singer's original clip kept with the voice made from it (Hillel, 2026-11-23: "useful for people to compare the
 * before/after"): an AAC copy, private, served to them alone; the WAV goes either way. A voice deleted meanwhile keeps
 * nothing.
 */
final class KeepVoiceClip implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $voiceId, public readonly string $wavKey) {}

    public function handle(Ffmpeg $ffmpeg): void
    {
        $disk = Storage::disk('private');
        $in = tempnam(sys_get_temp_dir(), 'clip').'.wav';
        $out = tempnam(sys_get_temp_dir(), 'clip').'.m4a';

        try {
            if ($disk->exists($this->wavKey) && OwnChoirVoice::query()->whereKey($this->voiceId)->where('status', 'ready')->exists()) {
                file_put_contents($in, (string) $disk->get($this->wavKey));
                $ffmpeg->encodeMix($in, $out, 1);
                $key = 'choir/voices/'.Str::lower(Str::random(32)).'.m4a';
                $disk->put($key, (string) file_get_contents($out));

                // Kept only if the voice is still there: else the copy goes too.
                $old = DB::transaction(function () use ($key): ?string {
                    $voice = OwnChoirVoice::query()->whereKey($this->voiceId)->where('status', 'ready')->lockForUpdate()->first();

                    if ($voice === null) {
                        return $key;
                    }

                    $previous = $voice->clip_key;
                    $voice->forceFill(['clip_key' => $key])->save();

                    return $previous;
                });

                if ($old !== null) {
                    $disk->delete($old);
                }
            }

            $disk->delete($this->wavKey);
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }
}
