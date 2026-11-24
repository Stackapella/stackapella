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
use Illuminate\Support\Facades\DB;
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

    /**
     * The new mix becomes the share's: its audio, card and what it holds (tracks, guides, AI and real voices, takes), at
     * the share's own address; the old files go (the public copies off the CDN), and the new row with them.
     *
     * @param  array{audio_key: string, card_key: string, stored_bytes: int, peaks: string|false}  $media
     * @param  list<string>  $made
     */
    private function fold(Harmony $new, HarmonyFiles $files, array $media, array $made): void
    {
        $old = DB::transaction(function () use ($new, $media): ?array {
            $share = Harmony::query()->whereKey($new->replaces_id)->where('status', Harmony::READY)->whereNull('deleted_at')->whereNull('removed_at')->lockForUpdate()->first();
            $still = Harmony::query()->whereKey($new->id)->where('status', Harmony::PROCESSING)->lockForUpdate()->first();

            if ($share === null || $still === null) {
                return null;
            }

            $gone = [$share->audio_key, $share->card_key];
            $share->forceFill([
                ...$media,
                // As values, not raw columns: a JSON column copied raw would be encoded twice.
                ...collect(['tracks', 'guide_ids', 'ai_parts', 'own_voice_parts', 'own_voice_friends', 'real_voice', 'sample_rate', 'channels', 'frames', 'take_ids', 'singer_ids', 'beat_zero_ms', 'template_version_id', 'words_part_key', 'bpm', 'part_id', 'part_name', 'sha256', 'upload_bytes'])->mapWithKeys(static fn (string $key): array => [$key => $still->getAttributeValue($key)])->all(),
                ...(($still->title ?? '') !== '' ? ['title' => $still->title, 'title_held_at' => $still->title_held_at] : []),
                'ready_at' => now(),
            ])->save();
            Harmony::query()->whereKey($still->id)->delete();

            return ['share' => $share, 'files' => array_values(array_filter($gone))];
        });

        Storage::disk('private')->delete((string) $new->upload_key);

        if ($old === null) {
            Storage::disk('private')->delete($made);

            return;
        }

        Storage::disk('private')->delete($old['files']);
        $files->republish($old['share']);
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

            // A mix holding an AI voice is marked so (EU AI Act Article 50).
            $ffmpeg->encodeMix($wav, $aac, $harmony->channels, ai: $harmony->holdsAi());
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

            // "Update that link": a new mix for a share folds into it, at the same address.
            if ($harmony->replaces_id !== null) {
                $this->fold($harmony, $files, ['audio_key' => "{$base}.m4a", 'card_key' => "{$base}.jpg", 'stored_bytes' => (int) filesize($aac), 'peaks' => json_encode(array_slice(Peaks::everyFrames($wav, (int) ceil($harmony->frames / self::PEAKS) * $harmony->channels), 0, self::PEAKS))], $made);

                return;
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
