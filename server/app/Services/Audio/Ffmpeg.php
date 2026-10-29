<?php

declare(strict_types=1);

namespace App\Services\Audio;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * ffmpeg and ffprobe (installed through the Aptfile), for the work on the `long` queue: checking
 * an upload is the audio it claims to be, measuring loudness, and making the CDN's AAC files.
 */
final class Ffmpeg
{
    /**
     * Before an input that's someone's upload: local files only, so a file ffmpeg reads as a
     * playlist can never make it fetch a URL (docs/security-review.md).
     */
    private const LOCAL_IN = ['-protocol_whitelist', 'file'];

    /** Before an uploaded WAV: read as a WAV, never as whatever its bytes look like. */
    private const WAV_IN = ['-protocol_whitelist', 'file', '-f', 'wav'];

    /**
     * What a file really is, as ffprobe reads it (never what its name or the upload said). An
     * upload (the default) is read only as a WAV.
     *
     * @return array{format: string, codec: string, channels: int, sampleRate: int, frames: int|null}|null null when it isn't audio at all
     */
    public function probe(string $path, bool $upload = true): ?array
    {
        // An upload is read as the WAV it must be; one of our own files (a test checking an encode) as it is.
        $result = Process::timeout(60)->run(['ffprobe', '-v', 'error', ...($upload ? self::WAV_IN : self::LOCAL_IN), '-select_streams', 'a:0', '-show_entries', 'stream=codec_name,channels,sample_rate,duration_ts:format=format_name', '-of', 'json', $path]);

        if (! $result->successful()) {
            return null;
        }

        /** @var array{streams?: list<array{codec_name?: string, channels?: int, sample_rate?: string, duration_ts?: int}>, format?: array{format_name?: string}} $data */
        $data = (array) json_decode($result->output(), true);
        $stream = $data['streams'][0] ?? null;

        if ($stream === null) {
            return null;
        }

        return [
            'format' => (string) ($data['format']['format_name'] ?? ''),
            'codec' => (string) ($stream['codec_name'] ?? ''),
            'channels' => (int) ($stream['channels'] ?? 0),
            'sampleRate' => (int) ($stream['sample_rate'] ?? 0),
            'frames' => isset($stream['duration_ts']) ? (int) $stream['duration_ts'] : null,
        ];
    }

    /**
     * The mean loudness (RMS) and the loudest sample, in dBFS, from ffmpeg's volumedetect. Null
     * for silence.
     *
     * @return array{mean: float|null, peak: float|null}
     */
    public function volume(string $path): array
    {
        $result = Process::timeout(120)->run(['ffmpeg', '-hide_banner', '-nostats', ...self::LOCAL_IN, '-i', $path, '-af', 'volumedetect', '-f', 'null', '-']);
        $read = static fn (string $name): ?float => preg_match('/'.$name.':\s*(-?[\d.]+|-inf) dB/', $result->errorOutput(), $match) === 1 && $match[1] !== '-inf' ? (float) $match[1] : null;

        return ['mean' => $read('mean_volume'), 'peak' => $read('max_volume')];
    }

    /**
     * A guide for the CDN: frames [$from, $to) of the WAV, with short fades and a gain, as AAC
     * at 128 kbps in an MP4 whose edit list hides the encoder's priming, so it lines up exactly.
     */
    public function encodeGuide(string $in, string $out, int $sampleRate, int $from, int $to, float $gainDb, int $fadeMs = 10): void
    {
        $length = max(1, $to - $from);
        $fade = round(min($fadeMs / 1000, $length / $sampleRate / 4), 4);
        $outStart = round(($length / $sampleRate) - $fade, 6);

        $filter = implode(',', [
            "atrim=start_sample={$from}:end_sample={$to}",
            'asetpts=PTS-STARTPTS',
            "afade=t=in:st=0:d={$fade}",
            "afade=t=out:st={$outStart}:d={$fade}",
            'volume='.round($gainDb, 2).'dB',
        ]);

        $result = Process::timeout(200)->run(['ffmpeg', '-hide_banner', '-nostats', '-y', ...self::LOCAL_IN, '-i', $in, '-af', $filter, '-c:a', 'aac', '-b:a', '128k', '-ar', (string) $sampleRate, '-ac', '1', '-movflags', '+faststart', '-f', 'mp4', $out]);

        if (! $result->successful() || ! is_file($out)) {
            throw new RuntimeException('ffmpeg could not make the guide: '.mb_strimwidth($result->errorOutput(), 0, 500, '...'));
        }
    }

    /**
     * A shared mix for the CDN, as it was rendered (no gain, no filter): AAC in an MP4, 160 kbps
     * for stereo and 128 for mono, at its own rate.
     */
    public function encodeMix(string $in, string $out, int $channels): void
    {
        $result = Process::timeout(200)->run(['ffmpeg', '-hide_banner', '-nostats', '-y', ...self::WAV_IN, '-i', $in, '-c:a', 'aac', '-b:a', $channels > 1 ? '160k' : '128k', '-ac', (string) $channels, '-movflags', '+faststart', '-f', 'mp4', $out]);

        if (! $result->successful() || ! is_file($out) || filesize($out) === 0) {
            throw new RuntimeException('ffmpeg could not make the mix: '.mb_strimwidth($result->errorOutput(), 0, 300));
        }
    }

    /**
     * One part of a choir (N6): each voice levelled by its gain, placed by its offset (from the
     * song's beat zero, in seconds), spread to its pan, and summed, quieter by the number of
     * voices so a big part doesn't drown a small one. A 32-bit float WAV at 48 kHz.
     *
     * Each voice keeps only frames [trimStart, frames - trimEnd), as its singer trimmed it.
     *
     * @param  list<array{path: string, gainDb: float, offset: float, pan: float, trimStart: int, trimEnd: int, frames: int}>  $voices
     */
    public function mixChoirPart(array $voices, string $out, int $seconds = 280): void
    {
        $args = ['ffmpeg', '-hide_banner', '-nostats', '-y'];
        $chains = [];
        $labels = '';

        // Never placed outside a song (ChoirVoice::inSong): BuildChoirMix leaves such a voice out.
        $voices = array_values(array_filter($voices, static fn (array $voice): bool => $voice['offset'] < (int) config('usage.take_max_seconds')));

        foreach ($voices as $i => $voice) {
            array_push($args, '-i', $voice['path']);
            $angle = (max(-1.0, min(1.0, $voice['pan'])) + 1) * M_PI / 4;
            $place = $voice['offset'] >= 0 ? 'adelay='.(int) round($voice['offset'] * 1000).':all=1' : 'atrim=start='.round(-$voice['offset'], 4).',asetpts=PTS-STARTPTS';
            $trim = 'atrim=start_sample='.max(0, $voice['trimStart']).':end_sample='.max($voice['trimStart'] + 1, $voice['frames'] - $voice['trimEnd']).',asetpts=PTS-STARTPTS,';
            $chains[] = "[{$i}:a]".$trim.'aresample=48000,aformat=channel_layouts=mono,volume='.round($voice['gainDb'], 2).'dB,'.$place.',pan=stereo|c0='.round(cos($angle), 4).'*c0|c1='.round(sin($angle), 4)."*c0[v{$i}]";
            $labels .= "[v{$i}]";
        }

        $count = count($voices);
        $chains[] = $labels.'amix=inputs='.$count.':normalize=0:duration=longest,volume='.round(-10 * log10(max(1, $count)), 2).'dB[out]';
        array_push($args, '-filter_complex', implode(';', $chains), '-map', '[out]', '-c:a', 'pcm_f32le', '-ar', '48000', '-f', 'wav', $out);
        $result = Process::timeout($seconds)->run($args);

        if (! $result->successful() || ! is_file($out)) {
            throw new RuntimeException('ffmpeg could not mix the part: '.mb_strimwidth($result->errorOutput(), 0, 500, '...'));
        }
    }

    /**
     * The whole choir: its parts summed, in the hall (the impulse response at $hall, mixed in at
     * $wet), a ceiling at -1 dBFS, as AAC at 160 kbps for the CDN.
     *
     * @param  list<string>  $parts
     */
    public function finishChoir(array $parts, string $hall, float $wet, string $out, int $seconds = 280): void
    {
        $args = ['ffmpeg', '-hide_banner', '-nostats', '-y'];
        $labels = '';

        foreach (array_values($parts) as $i => $part) {
            array_push($args, '-i', $part);
            $labels .= "[{$i}:a]";
        }

        array_push($args, '-i', $hall);
        $ir = count($parts);
        $filter = $labels.'amix=inputs='.count($parts).':normalize=0:duration=longest[m];[m]asplit[d][w];'
            ."[w][{$ir}:a]afir[r];[d][r]amix=inputs=2:weights='1 ".round($wet, 3)."':normalize=0:duration=first,alimiter=limit=0.891:level=false[out]";
        array_push($args, '-filter_complex', $filter, '-map', '[out]', '-c:a', 'aac', '-b:a', '160k', '-ac', '2', '-movflags', '+faststart', '-f', 'mp4', $out);
        $result = Process::timeout($seconds)->run($args);

        if (! $result->successful() || ! is_file($out) || filesize($out) === 0) {
            throw new RuntimeException('ffmpeg could not finish the choir: '.mb_strimwidth($result->errorOutput(), 0, 500, '...'));
        }
    }

    /**
     * The encoders the work needs that this ffmpeg doesn't have (H.264 for share videos, AAC for
     * everything heard): an empty list when all is well, or every one when ffmpeg won't run.
     *
     * @return list<string>
     */
    public function missingEncoders(): array
    {
        $needed = ['libx264', 'aac'];
        $result = Process::timeout(20)->run(['ffmpeg', '-hide_banner', '-encoders']);

        if (! $result->successful()) {
            return $needed;
        }

        return array_values(array_filter($needed, static fn (string $encoder): bool => preg_match('/^\s*[VAS][^\s]*\s+'.preg_quote($encoder, '/').'\s/m', $result->output()) !== 1));
    }

    /**
     * A take kept losslessly: 16-bit FLAC at its own rate, mono.
     */
    public function encodeFlac(string $in, string $out): void
    {
        $result = Process::timeout(200)->run(['ffmpeg', '-hide_banner', '-nostats', '-y', ...self::WAV_IN, '-i', $in, '-c:a', 'flac', '-sample_fmt', 's16', '-ac', '1', '-f', 'flac', $out]);

        if (! $result->successful() || ! is_file($out) || filesize($out) === 0) {
            throw new RuntimeException('ffmpeg could not make the FLAC: '.mb_strimwidth($result->errorOutput(), 0, 300));
        }
    }
}
