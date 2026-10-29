<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Ideas\Ideas;
use App\Models\IdeaVoice;
use App\Services\Audio\Ffmpeg;
use App\Support\Audio\Peaks;
use App\Support\Gallery\Bell;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * A voice on an idea, checked to be the WAV it was declared to be (as ProcessTake does), its
 * outline taken, then kept as AAC. It replaces that singer's earlier voice on the same part, and
 * the writer's bell hears of it.
 */
final class ProcessIdeaVoice implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 300;

    public function __construct(public readonly int $voiceId)
    {
        $this->onQueue('long');
    }

    public function handle(Ffmpeg $ffmpeg, Ideas $ideas): void
    {
        $voice = IdeaVoice::query()->with(['idea', 'user'])->find($this->voiceId);

        if ($voice === null || $voice->status !== IdeaVoice::PROCESSING) {
            return;
        }

        $wav = (string) tempnam(sys_get_temp_dir(), 'voice');
        $aac = $wav.'.m4a';
        file_put_contents($wav, Storage::disk('private')->readStream($voice->upload_key));

        try {
            $probe = $ffmpeg->probe($wav);
            $failure = match (true) {
                $probe === null => 'not_audio',
                ! str_contains($probe['format'], 'wav') || $probe['codec'] !== 'pcm_s16le' || $probe['channels'] !== 1 || $probe['sampleRate'] !== $voice->sample_rate => 'wrong_format',
                $probe['frames'] === null || abs($probe['frames'] - $voice->frames) > 1 => 'wrong_format',
                default => null,
            };

            if ($failure !== null) {
                $voice->forceFill(['status' => IdeaVoice::FAILED, 'failure' => $failure])->save();
                Storage::disk('private')->delete($voice->upload_key);

                return;
            }

            $perPeak = Peaks::framesForTake($voice->frames);
            $peaks = Peaks::everyFrames($wav, $perPeak);
            $ffmpeg->encodeGuide($wav, $aac, $voice->sample_rate, 0, $voice->frames, 0.0);
            $key = 'voices/'.Str::lower(Str::random(32)).'.m4a';
            Storage::disk('private')->put($key, (string) file_get_contents($aac));

            $replaced = DB::transaction(function () use ($voice, $key, $aac, $perPeak, $peaks): ?array {
                $saved = IdeaVoice::query()->whereKey($voice->id)->where('status', IdeaVoice::PROCESSING)->update([
                    'status' => IdeaVoice::READY, 'file_key' => $key, 'stored_bytes' => (int) filesize($aac),
                    'frames_per_peak' => $perPeak, 'peaks' => json_encode($peaks), 'ready_at' => now(), 'updated_at' => now(),
                ]);

                // Gone meanwhile (deleted, or its idea): nothing to keep.
                if ($saved === 0) {
                    return null;
                }

                // A voice Hillel hid stays as it is, and keeps the new one out of sight too.
                if (IdeaVoice::query()->where('idea_id', $voice->idea_id)->where('user_id', $voice->user_id)->where('part_key', $voice->part_key)->whereKeyNot($voice->id)->whereNotNull('hidden_at')->exists()) {
                    IdeaVoice::query()->whereKey($voice->id)->update(['hidden_at' => now()]);
                }

                $older = IdeaVoice::query()->where('idea_id', $voice->idea_id)->where('user_id', $voice->user_id)->where('part_key', $voice->part_key)->whereKeyNot($voice->id)->whereNull('hidden_at')->get();
                IdeaVoice::query()->whereKey($older->pluck('id'))->delete();

                return $older->flatMap(fn (IdeaVoice $v): array => array_values(array_filter([$v->file_key, $v->upload_key])))->all();
            });

            if ($replaced === null) {
                Storage::disk('private')->delete([$key, $voice->upload_key]);

                return;
            }

            Storage::disk('private')->delete([$voice->upload_key, ...$replaced]);
            $ideas->recount($voice->idea);
            if (IdeaVoice::query()->whereKey($voice->id)->whereNull('hidden_at')->exists()) {
                Bell::tell($voice->idea->user_id, 'idea_voice', $voice->user, idea: $voice->idea);
            }
        } finally {
            @unlink($wav);
            @unlink($aac);
        }
    }

    public function failed(?Throwable $e): void
    {
        IdeaVoice::query()->whereKey($this->voiceId)->where('status', IdeaVoice::PROCESSING)->update(['status' => IdeaVoice::FAILED, 'failure' => 'processing_error', 'updated_at' => now()]);
    }
}
