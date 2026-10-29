<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TemplateRecording;
use App\Services\Audio\Ffmpeg;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A creator's recorded guide, checked to be the WAV it was declared to be (as ProcessTake does),
 * then kept as AAC like an official guide. It replaces that part's earlier recording.
 */
final class ProcessTemplateRecording implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 300;

    public function __construct(public readonly int $recordingId)
    {
        $this->onQueue('long');
    }

    public function handle(Ffmpeg $ffmpeg): void
    {
        $recording = TemplateRecording::query()->find($this->recordingId);

        if ($recording === null || $recording->status !== TemplateRecording::PROCESSING) {
            return;
        }

        $wav = (string) tempnam(sys_get_temp_dir(), 'recording');
        $aac = $wav.'.m4a';
        file_put_contents($wav, Storage::disk('private')->readStream($recording->upload_key));

        try {
            $probe = $ffmpeg->probe($wav);
            $failure = match (true) {
                $probe === null => 'not_audio',
                ! str_contains($probe['format'], 'wav') || $probe['codec'] !== 'pcm_s16le' || $probe['channels'] !== 1 || $probe['sampleRate'] !== $recording->sample_rate => 'wrong_format',
                $probe['frames'] === null || abs($probe['frames'] - $recording->frames) > 1 => 'wrong_format',
                $recording->frames > (int) config('usage.take_max_seconds') * $recording->sample_rate => 'too_long',
                default => null,
            };

            if ($failure !== null) {
                $recording->forceFill(['status' => TemplateRecording::FAILED, 'failure' => $failure])->save();
                Storage::disk('private')->delete($recording->upload_key);

                return;
            }

            $ffmpeg->encodeGuide($wav, $aac, $recording->sample_rate, 0, $recording->frames, 0.0);
            $key = 'recordings/'.Str::lower(Str::random(32)).'.m4a';
            Storage::disk('private')->put($key, (string) file_get_contents($aac));

            $replaced = DB::transaction(function () use ($recording, $key, $aac): array {
                $saved = TemplateRecording::query()->whereKey($recording->id)->where('status', TemplateRecording::PROCESSING)->update(['status' => TemplateRecording::READY, 'file_key' => $key, 'stored_bytes' => (int) filesize($aac), 'ready_at' => now(), 'updated_at' => now()]);

                if ($saved === 0) {
                    return [$key];
                }

                $older = TemplateRecording::query()->where('template_id', $recording->template_id)->where('part_key', $recording->part_key)->whereKeyNot($recording->id)->where('status', TemplateRecording::READY)->get();
                TemplateRecording::query()->whereKey($older->pluck('id'))->delete();

                return $older->pluck('file_key')->filter()->values()->all();
            });

            Storage::disk('private')->delete([$recording->upload_key, ...$replaced]);
        } finally {
            @unlink($wav);
            @unlink($aac);
        }
    }

    public function failed(): void
    {
        TemplateRecording::query()->whereKey($this->recordingId)->where('status', TemplateRecording::PROCESSING)->update(['status' => TemplateRecording::FAILED, 'failure' => 'processing_error', 'updated_at' => now()]);
    }
}
