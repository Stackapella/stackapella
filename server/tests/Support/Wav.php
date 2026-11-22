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

    /**
     * The worker's AI marking after a WAV's audio (EU AI Act Article 50): LIST/INFO ICMT and an iXML chunk, the RIFF
     * size covering them.
     */
    public static function marked(string $wav): string
    {
        $chunk = static fn (string $id, string $body): string => $id.pack('V', strlen($body)).$body.(strlen($body) % 2 === 1 ? "\0" : '');
        $info = 'INFO'.$chunk('ICMT', (string) config('ai_marking.text')."\0").$chunk('ISFT', "Stackapella voices worker\0");
        $xml = '<BWFXML><IXML_VERSION>1.61</IXML_VERSION><NOTE>AI-generated singing voice</NOTE><USER>ai_generated=true;digital_source_type='.config('ai_marking.digital_source_type').';generator='.config('ai_marking.generator').'</USER></BWFXML>';
        $out = $wav.$chunk('LIST', $info).$chunk('iXML', $xml);

        return substr($out, 0, 4).pack('V', strlen($out) - 8).substr($out, 8);
    }
}
