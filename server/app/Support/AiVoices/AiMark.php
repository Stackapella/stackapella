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

    /** The machine-readable line: the worker's iXML USER, and an MP4's description. */
    public static function line(): string
    {
        return 'ai_generated=true;digital_source_type='.config('ai_marking.digital_source_type').';generator='.config('ai_marking.generator');
    }

    /**
     * ffmpeg's arguments for an MP4 holding AI audio: the iTunes comment (©cmt) the text and the description (desc) the
     * line. Standard atoms only: ffmpeg's +use_metadata_tags would move even the comment into mdta keys no iTunes-style
     * reader sees, and without it a custom key is dropped (tested with ffmpeg 7, 2026-11-24).
     *
     * @return list<string>
     */
    public static function mp4Metadata(): array
    {
        return ['-metadata', 'comment='.config('ai_marking.text'), '-metadata', 'description='.self::line()];
    }

    /**
     * Whether an MP4's tags (Ffmpeg::tags) carry the marking: the line in its description, or the custom key the server
     * wrote from 2026-11-23 to 24.
     *
     * @param  array<string, string>  $tags
     */
    public static function mp4Marked(array $tags): bool
    {
        return str_starts_with($tags['description'] ?? '', 'ai_generated=true;') || ($tags['ai_generated'] ?? null) === 'true';
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
