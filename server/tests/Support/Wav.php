<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * A 16-bit WAV made in the test: a sine tone, so a take has something to process.
 */
final class Wav
{
    public static function tone(float $seconds, int $sampleRate = 48000, float $hz = 220.0, float $level = 0.3): string
    {
        $frames = (int) round($seconds * $sampleRate);
        $data = '';

        for ($i = 0; $i < $frames; $i++) {
            $data .= pack('v', (int) round(sin(2 * M_PI * $hz * $i / $sampleRate) * $level * 32767) & 0xFFFF);
        }

        return self::wrap($data, $sampleRate, 1);
    }

    /**
     * A stereo mix: the tone in both channels (a shared harmony's upload).
     */
    public static function stereo(float $seconds, int $sampleRate = 48000, float $hz = 220.0, float $level = 0.3): string
    {
        $frames = (int) round($seconds * $sampleRate);
        $data = '';

        for ($i = 0; $i < $frames; $i++) {
            $sample = pack('v', (int) round(sin(2 * M_PI * $hz * $i / $sampleRate) * $level * 32767) & 0xFFFF);
            $data .= $sample.$sample;
        }

        return self::wrap($data, $sampleRate, 2);
    }

    private static function wrap(string $data, int $sampleRate, int $channels): string
    {
        return 'RIFF'.pack('V', 36 + strlen($data)).'WAVE'
            .'fmt '.pack('VvvVVvv', 16, 1, $channels, $sampleRate, $sampleRate * 2 * $channels, 2 * $channels, 16)
            .'data'.pack('V', strlen($data)).$data;
    }
}
