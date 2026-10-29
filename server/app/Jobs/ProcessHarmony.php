<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Harmony;
use App\Services\Audio\Ffmpeg;
use App\Services\Sharing\ShareCard;
use App\Support\Audio\Peaks;
use App\Support\Sharing\HarmonyFacts;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * A shared mix, checked to be the WAV the device declared (ffprobe: 16-bit, those channels, that
 * rate and length, at most 10 minutes), then kept as AAC with its waveform and share card
 * (docs/backend-plan.md D6). The server never remixes it. The page goes live once it's ready.
 */
final class ProcessHarmony implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** The page's waveform: this many bars across the whole mix. */
    public const PEAKS = 600;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 300;

    public function __construct(public readonly int $harmonyId)
    {
        $this->onQueue('long');
    }

    public function handle(Ffmpeg $ffmpeg, ShareCard $card, HarmonyFacts $facts, HarmonyFiles $files): void
    {
        $harmony = Harmony::query()->with('user', 'templateVersion.template')->find($this->harmonyId);

        if ($harmony === null || $harmony->deleted_at !== null || $harmony->removed_at !== null) {
            return;
        }

        // A try after the files were saved but the page wasn't published: publish it now.
        if ($harmony->status === Harmony::READY) {
            $files->sync($harmony);

            return;
        }

        if ($harmony->status !== Harmony::PROCESSING) {
            return;
        }

        $wav = (string) tempnam(sys_get_temp_dir(), 'harmony');
        $aac = $wav.'.m4a';
        file_put_contents($wav, Storage::disk('private')->readStream($harmony->upload_key));

        try {
            $probe = $ffmpeg->probe($wav);
            $failure = match (true) {
                $probe === null => 'not_audio',
                ! str_contains($probe['format'], 'wav') || $probe['codec'] !== 'pcm_s16le' || $probe['channels'] !== $harmony->channels || $probe['sampleRate'] !== $harmony->sample_rate => 'wrong_format',
                $probe['frames'] === null || abs($probe['frames'] - $harmony->frames) > 1 => 'wrong_format',
                $harmony->frames > (int) config('sharing.max_seconds') * $harmony->sample_rate => 'too_long',
                default => null,
            };

            if ($failure !== null) {
                $harmony->forceFill(['status' => Harmony::FAILED, 'failure' => $failure])->save();
                Storage::disk('private')->delete($harmony->upload_key);

                return;
            }

            $ffmpeg->encodeMix($wav, $aac, $harmony->channels);
            $base = 'harmonies/'.Str::lower(Str::random(32));
            $made = ["{$base}.m4a", "{$base}.jpg"];

            try {
                Storage::disk('private')->put("{$base}.m4a", (string) file_get_contents($aac));
                Storage::disk('private')->put("{$base}.jpg", $card->render($facts->title($harmony), $facts->singersLine($harmony), $facts->songLine($harmony), $harmony->tracks));
            } catch (Throwable $e) {
                // A failed try leaves nothing behind; the next starts again.
                Storage::disk('private')->delete($made);

                throw $e;
            }

            // Only if it's still wanted: deleted or removed while this ran, the new files go too.
            $saved = Harmony::query()->whereKey($harmony->id)->where('status', Harmony::PROCESSING)->whereNull('deleted_at')->whereNull('removed_at')->update([
                'status' => Harmony::READY,
                'audio_key' => "{$base}.m4a",
                'card_key' => "{$base}.jpg",
                'stored_bytes' => (int) filesize($aac),
                'peaks' => json_encode(array_slice(Peaks::everyFrames($wav, (int) ceil($harmony->frames / self::PEAKS) * $harmony->channels), 0, self::PEAKS)),
                'ready_at' => now(),
                'updated_at' => now(),
            ]);

            if ($saved === 0) {
                Storage::disk('private')->delete($made);

                return;
            }

            Storage::disk('private')->delete($harmony->upload_key);
            $files->sync($harmony);
        } finally {
            @unlink($wav);
            @unlink($aac);
        }
    }

    /**
     * Every try failed (ffmpeg, storage or the timeout): final, and the app shares it again.
     */
    public function failed(): void
    {
        $harmony = Harmony::query()->whereKey($this->harmonyId)->where('status', Harmony::PROCESSING)->first();

        if ($harmony !== null) {
            $harmony->forceFill(['status' => Harmony::FAILED, 'failure' => 'processing_error'])->save();
            Storage::disk('private')->delete($harmony->upload_key);
        }
    }
}
