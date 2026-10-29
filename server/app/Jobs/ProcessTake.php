<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Projects\BinTakes;
use App\Models\Take;
use App\Services\Audio\Ffmpeg;
use App\Support\Audio\Peaks;
use App\Support\Sync\SyncSeq;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A take's WAV, checked to be what the device said (ffprobe: a mono 16-bit WAV of those frames,
 * at that rate, at most 10 minutes), then kept as FLAC with its outline (docs/backend-plan.md C4).
 * The WAV goes once the FLAC is safe. Anything else is refused: `failed`, with why.
 */
final class ProcessTake implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 300;

    public function __construct(public readonly int $takeId)
    {
        $this->onQueue('long');
    }

    public function handle(Ffmpeg $ffmpeg, SyncSeq $seq, BinTakes $bin): void
    {
        $take = Take::query()->with('part')->find($this->takeId);

        if ($take === null || $take->status !== Take::PROCESSING) {
            return;
        }

        $wav = (string) tempnam(sys_get_temp_dir(), 'take');
        $flac = $wav.'.flac';
        file_put_contents($wav, Storage::disk('private')->readStream($take->upload_key));

        try {
            $probe = $ffmpeg->probe($wav);
            $failure = match (true) {
                $probe === null => 'not_audio',
                ! str_contains($probe['format'], 'wav') || $probe['codec'] !== 'pcm_s16le' || $probe['channels'] !== 1 || $probe['sampleRate'] !== $take->sample_rate => 'wrong_format',
                $probe['frames'] === null || abs($probe['frames'] - $take->frames) > 1 => 'wrong_format',
                $take->frames > (int) config('usage.take_max_seconds') * $take->sample_rate => 'too_long',
                default => null,
            };

            if ($failure !== null) {
                // The change number and its row in one transaction, so a pull never passes it.
                DB::transaction(fn () => $take->forceFill(['status' => Take::FAILED, 'failure' => $failure, 'seq' => $seq->next($take->user_id)])->save());
                Storage::disk('private')->delete($take->upload_key);

                return;
            }

            $ffmpeg->encodeFlac($wav, $flac);
            $key = 'takes/'.Str::lower(Str::random(32)).'.flac';
            $stream = fopen($flac, 'rb');

            if ($stream === false) {
                throw new RuntimeException("Couldn't open the FLAC at {$flac}.");
            }

            Storage::disk('private')->writeStream($key, $stream);
            is_resource($stream) && fclose($stream);

            $perPeak = Peaks::framesForTake($take->frames);
            $peaks = Peaks::everyFrames($wav, $perPeak);

            // Saved only if it's still waiting for this: a take deleted meanwhile (its project or
            // account) leaves no FLAC behind.
            $kept = DB::transaction(function () use ($take, $key, $flac, $peaks, $perPeak, $seq, $bin): bool {
                $locked = Take::query()->whereKey($take->id)->lockForUpdate()->first(['id', 'status']);

                if ($locked === null || $locked->status !== Take::PROCESSING) {
                    return false;
                }

                $take->forceFill([
                    'status' => Take::READY,
                    'file_key' => $key,
                    'stored_bytes' => (int) filesize($flac),
                    'frames_per_peak' => $perPeak,
                    'peaks' => $peaks,
                    'ready_at' => now(),
                    'seq' => $seq->next($take->user_id),
                ])->save();
                $bin->replacementReady($take);

                return true;
            });

            if (! $kept) {
                Storage::disk('private')->delete($key);
            }

            Storage::disk('private')->delete($take->upload_key);
        } finally {
            @unlink($wav);
            @unlink($flac);
        }
    }

    /**
     * Every try failed (ffmpeg, storage or the timeout): final, and the app sends it again under
     * a new id.
     */
    public function failed(): void
    {
        DB::transaction(function (): void {
            $take = Take::query()->whereKey($this->takeId)->where('status', Take::PROCESSING)->lockForUpdate()->first();

            $take?->forceFill(['status' => Take::FAILED, 'failure' => 'processing_error', 'seq' => app(SyncSeq::class)->next($take->user_id)])->save();
        });
    }
}
