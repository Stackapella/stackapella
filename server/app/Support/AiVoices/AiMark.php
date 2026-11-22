<?php

declare(strict_types=1);

namespace App\Support\AiVoices;

/**
 * The machine-readable marking of AI-made audio (config ai_marking; EU AI Act Article 50): whether a master carries the
 * worker's marking, and the metadata every AAC/M4A made from AI audio carries.
 */
final class AiMark
{
    /** Whether a WAV carries the worker's iXML marking after its audio. */
    public static function marked(string $path): bool
    {
        $file = fopen($path, 'rb');

        if ($file === false) {
            return false;
        }

        try {
            if (fread($file, 12) === false) {
                return false;
            }

            // Walk the chunks: the iXML one says so.
            while (! feof($file)) {
                $head = fread($file, 8);

                if ($head === false || strlen($head) < 8) {
                    return false;
                }

                $id = substr($head, 0, 4);
                $size = (int) (unpack('V', substr($head, 4, 4))[1] ?? 0);

                if ($id === 'iXML') {
                    $xml = $size > 0 && $size <= 65536 ? (string) fread($file, $size) : '';

                    return str_contains($xml, '<USER>ai_generated=true;');
                }

                if (fseek($file, $size + ($size % 2), SEEK_CUR) !== 0) {
                    return false;
                }
            }

            return false;
        } finally {
            fclose($file);
        }
    }

    /**
     * ffmpeg's arguments for an MP4 holding AI audio: its comment, and custom keys for the generator and the IPTC
     * digital source type.
     *
     * @return list<string>
     */
    public static function mp4Metadata(): array
    {
        return [
            '-metadata', 'comment='.config('ai_marking.text'),
            '-metadata', 'ai_generated=true',
            '-metadata', 'ai_generator='.config('ai_marking.generator'),
            '-metadata', 'digital_source_type='.config('ai_marking.digital_source_type'),
        ];
    }

    /**
     * The strings the app writes into its own exports (auth.config).
     *
     * @return array{text: string, generator: string, digitalSourceType: string}
     */
    public static function forApp(): array
    {
        return ['text' => (string) config('ai_marking.text'), 'generator' => (string) config('ai_marking.generator'), 'digitalSourceType' => (string) config('ai_marking.digital_source_type')];
    }
}
