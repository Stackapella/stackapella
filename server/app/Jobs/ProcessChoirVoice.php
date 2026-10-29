<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Choir;
use App\Models\ChoirVoice;
use App\Models\Take;
use App\Services\Audio\Ffmpeg;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A voice joining a choir (N6): its take copied in (it's the choir's now, whatever happens to the
 * project), and its level measured so every voice sits at the same loudness in the mix.
 */
final class ProcessChoirVoice implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Where each voice is levelled to before the mix (RMS, dBFS). */
    private const TARGET_DB = -20.0;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 300;

    public function __construct(public readonly int $voiceId)
    {
        $this->onQueue('long');
    }

    public function handle(Ffmpeg $ffmpeg): void
    {
        $voice = ChoirVoice::query()->find($this->voiceId);
        $take = $voice?->take_id !== null ? Take::query()->find($voice->take_id) : null;

        if ($voice === null || $voice->status !== ChoirVoice::PROCESSING) {
            return;
        }

        $disk = Storage::disk('private');

        if ($take === null || $take->file_key === null || ! $disk->exists($take->file_key)) {
            $voice->forceFill(['status' => ChoirVoice::FAILED])->save();

            return;
        }

        // Measured first, from the take itself: a failure here leaves no copy behind.
        $local = (string) tempnam(sys_get_temp_dir(), 'choir');
        file_put_contents($local, $disk->readStream($take->file_key));

        try {
            $volume = $ffmpeg->volume($local);
        } finally {
            @unlink($local);
        }

        $key = 'choirs/'.Str::lower(Str::random(32)).'.'.pathinfo($take->file_key, PATHINFO_EXTENSION);
        $disk->copy($take->file_key, $key);

        $gain = $volume['mean'] !== null ? self::TARGET_DB - $volume['mean'] : 0.0;
        $gain = max(-24.0, min(24.0, $volume['peak'] !== null ? min($gain, -1.0 - $volume['peak']) : $gain));

        $kept = DB::transaction(function () use ($voice, $key, $gain): bool {
            $saved = ChoirVoice::query()->whereKey($voice->id)->where('status', ChoirVoice::PROCESSING)->lockForUpdate()
                ->update(['status' => ChoirVoice::READY, 'file_key' => $key, 'gain_db' => round($gain, 2), 'updated_at' => now()]);

            if ($saved === 1) {
                Choir::query()->whereKey($voice->choir_id)->update(['changed_at' => now()]);
            }

            return $saved === 1;
        });

        // Left or replaced meanwhile: the copy isn't wanted.
        if (! $kept) {
            $disk->delete($key);
        }
    }

    public function failed(): void
    {
        ChoirVoice::query()->whereKey($this->voiceId)->where('status', ChoirVoice::PROCESSING)->update(['status' => ChoirVoice::FAILED, 'updated_at' => now()]);
    }
}
