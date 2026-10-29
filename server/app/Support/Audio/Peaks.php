<?php

declare(strict_types=1);

namespace App\Support\Audio;

/**
 * The outline of a 16-bit mono WAV for the review's waveform: the loudest sample in each window,
 * from 0 to 1, about 100 a second (rounded to 3 places, to keep it small).
 */
final class Peaks
{
    /**
     * A take's outline has at most this many peaks, so a sync page of long takes stays small.
     */
    public const MOST_FOR_A_TAKE = 16384;

    /**
     * A take's framesPerPeak: 512, doubled until $frames fits in MOST_FOR_A_TAKE peaks
     * (contract/api.md, "Audio").
     */
    public static function framesForTake(int $frames): int
    {
        $per = 512;

        while ((int) ceil($frames / $per) > self::MOST_FOR_A_TAKE) {
            $per *= 2;
        }

        return $per;
    }

    /**
     * A finer outline made coarser by $factor (the loudest of each run of $factor peaks).
     *
     * @param  list<float|int>  $peaks
     * @return list<float>
     */
    public static function thin(array $peaks, int $factor): array
    {
        return array_values(array_map(static fn (array $run): float => (float) max($run), array_chunk($peaks, max(1, $factor))));
    }

    /**
     * @return list<float>
     */
    public static function fromWav(string $path, int $sampleRate, int $perSecond = 100): array
    {
        return self::everyFrames($path, max(1, intdiv($sampleRate, $perSecond)));
    }

    /**
     * The loudest sample (0 to 1) in each run of $frames frames, as the app works out a take's
     * outline (its framesPerPeak).
     *
     * @return list<float>
     */
    public static function everyFrames(string $path, int $frames): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $remaining = self::seekToData($handle);

        if ($remaining === null) {
            fclose($handle);

            return [];
        }

        $window = max(1, $frames) * 2;
        $peaks = [];
        $buffer = '';

        // Only the data chunk's own bytes (a chunk may follow it).
        while ($remaining > 0 && ! feof($handle)) {
            $read = (string) fread($handle, min($remaining, $window * 512));
            $remaining -= strlen($read);
            $buffer .= $read;
            $offset = 0;

            while (strlen($buffer) - $offset >= $window) {
                $peaks[] = self::peak(substr($buffer, $offset, $window));
                $offset += $window;
            }

            $buffer = substr($buffer, $offset);
        }

        if (strlen($buffer) >= 2) {
            $peaks[] = self::peak($buffer);
        }

        fclose($handle);

        return $peaks;
    }

    private static function peak(string $bytes): float
    {
        // WAV is little-endian, whatever this machine is: read unsigned, then make it signed.
        /** @var array<int, int> $words */
        $words = unpack('v*', substr($bytes, 0, strlen($bytes) - strlen($bytes) % 2)) ?: [];
        $max = 0;
        foreach ($words as $word) {
            $max = max($max, $word >= 32768 ? 65536 - $word : $word);
        }

        return round(min(1, $max / 32767), 3);
    }

    /**
     * Move to the start of the data chunk's samples.
     *
     * @param  resource  $handle
     * @return int|null the data chunk's size in bytes, or null when there isn't one
     */
    private static function seekToData($handle): ?int
    {
        if (strlen((string) fread($handle, 12)) < 12) {
            return null;
        }

        while (! feof($handle)) {
            $header = (string) fread($handle, 8);
            if (strlen($header) < 8) {
                return null;
            }

            /** @var array{id: string, size: int} $chunk */
            $chunk = unpack('a4id/Vsize', $header);

            if ($chunk['id'] === 'data') {
                return $chunk['size'];
            }

            fseek($handle, $chunk['size'] + ($chunk['size'] % 2), SEEK_CUR);
        }

        return null;
    }

    /**
     * Peaks as `peaks8` (contract/api.md, "Peaks"): one byte per peak, 0 to 255 for 0 to 1, in
     * base64; about a quarter of the JSON numbers' size.
     *
     * @param  list<float|int>|null  $peaks
     */
    public static function toBase64(?array $peaks): ?string
    {
        if ($peaks === null) {
            return null;
        }

        return base64_encode(pack('C*', ...array_map(static fn (float|int $peak): int => (int) round(max(0.0, min(1.0, (float) $peak)) * 255), $peaks)));
    }
}
