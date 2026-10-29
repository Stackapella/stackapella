<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Choirs\Choirs;
use App\Models\Choir;
use App\Models\ChoirVoice;
use App\Services\Audio\Ffmpeg;
use App\Support\Audio\Hall;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * A choir's mix (N6), the one place the server mixes audio (docs/decisions.md): the newest
 * MAX_PER_PART voices of each part, levelled, spread across the stereo field by part, in the hall
 * "Make it sound good" uses, written to the CDN at a new key. No voices: no mix.
 */
final class BuildChoirMix implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 550;

    /** One mix of a choir at a time, queued or running. */
    public int $uniqueFor = 3600;

    public function __construct(public readonly int $choirId)
    {
        $this->onQueue('long');
    }

    public function uniqueId(): string
    {
        return (string) $this->choirId;
    }

    public function handle(Ffmpeg $ffmpeg): void
    {
        $choir = Choir::query()->with('template.currentVersion')->find($this->choirId);

        if ($choir === null) {
            return;
        }

        $started = now();
        $order = array_values(array_filter(array_map(static fn (mixed $p): ?string => is_array($p) && is_string($p['key'] ?? null) ? $p['key'] : null, (array) ($choir->template->currentVersion->data['parts'] ?? []))));
        $disk = Storage::disk('private');
        $temp = sys_get_temp_dir().'/choir-'.Str::lower(Str::random(12));
        mkdir($temp);
        $parts = [];
        $used = [];
        // Every ffmpeg step shares the job's time, so a big choir fails clearly rather than overrun.
        $budget = max(30, intdiv($this->timeout - 50, max(1, count($order)) + 1));
        $key = null;
        $uploaded = null;

        try {
            foreach ($order as $index => $key) {
                $voices = Choirs::heard($choir)->where('part_key', $key)->whereNotNull('file_key')
                    ->orderByDesc('id')->limit(Choirs::MAX_PER_PART)->get();
                $inputs = [];

                foreach ($voices as $n => $voice) {
                    $local = $temp.'/v'.$voice->id.'.'.pathinfo((string) $voice->file_key, PATHINFO_EXTENSION);

                    $at = ($voice->offset_frames + $voice->trim_start_frames) / max(1, $voice->sample_rate);

                    if (! ChoirVoice::inSong($at, $voice->frames / max(1, $voice->sample_rate)) || ! $disk->exists((string) $voice->file_key)) {
                        continue;
                    }

                    file_put_contents($local, $disk->readStream((string) $voice->file_key));
                    // The part's place, and each voice a little either side of it, the same every time.
                    $pan = Hall::spread($index, count($order)) + (($n % 5) - 2) * 0.03;
                    // Placed where its first kept frame falls.
                    $inputs[] = ['path' => $local, 'gainDb' => (float) $voice->gain_db, 'offset' => $at, 'pan' => $pan, 'trimStart' => $voice->trim_start_frames, 'trimEnd' => $voice->trim_end_frames, 'frames' => $voice->frames];
                    $used[] = $voice->id;
                }

                if ($inputs !== []) {
                    $parts[] = $out = $temp.'/part-'.$index.'.wav';
                    $ffmpeg->mixChoirPart($inputs, $out, $budget);
                }

                // Each part's voices go as soon as it's mixed, so the disk holds one part at a time.
                foreach ($inputs as $input) {
                    @unlink($input['path']);
                }
            }

            if ($parts !== []) {
                Hall::write($hall = $temp.'/hall.wav', 48000);
                $ffmpeg->finishChoir($parts, $hall, Hall::WET, $mix = $temp.'/choir.m4a', $budget);
                $key = 'choirs/'.Str::lower(Str::random(32)).'.m4a';
                $stream = fopen($mix, 'rb');
                Storage::disk('media')->writeStream($key, $stream === false ? throw new \RuntimeException("Couldn't open {$mix}.") : $stream, ['visibility' => 'public', 'ContentType' => 'audio/mp4', 'CacheControl' => 'public, max-age=31536000, immutable']);
                is_resource($stream) && fclose($stream);
                $uploaded = $key;
            }

            $old = DB::transaction(function () use ($choir, $key, $used, $started): ?string {
                $locked = Choir::query()->whereKey($choir->id)->lockForUpdate()->firstOrFail();
                $old = $locked->mix_key;
                $locked->forceFill(['mix_key' => $key, 'mixed_at' => $started, 'voices_count' => count($used)])->save();
                ChoirVoice::query()->where('choir_id', $choir->id)->whereKey($used)->update(['in_mix_at' => $started]);
                ChoirVoice::query()->where('choir_id', $choir->id)->whereKeyNot($used)->update(['in_mix_at' => null]);

                return $old;
            });
            // Recorded: it's the choir's mix now, whatever happens next.
            $uploaded = null;

            // The old mix off the CDN too: it's cached for a year, with voices that may have left.
            if ($old !== null && $old !== $key) {
                Storage::disk('media')->delete($old);
                PurgeCdn::dispatch([$old]);
            }
        } catch (Throwable $e) {
            // Up but never recorded: not left on the CDN.
            if ($uploaded !== null) {
                Storage::disk('media')->delete($uploaded);
            }

            throw $e;
        } finally {
            foreach ((array) glob($temp.'/*') as $file) {
                @unlink((string) $file);
            }

            @rmdir($temp);
        }
    }
}
