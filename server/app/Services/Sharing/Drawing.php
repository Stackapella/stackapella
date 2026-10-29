<?php

declare(strict_types=1);

namespace App\Services\Sharing;

use GdImage;
use RuntimeException;

/**
 * What the share card and the video draw with: our font through GD (iaw's SocialCard), the two
 * looks' colours, wrapped text, and the parts' waveforms from their outlines.
 */
final class Drawing
{
    /** The two looks: the part colours on dark (the default), or on light. */
    public const LOOKS = [
        'dark' => ['background' => '#141218', 'text' => '#f4efe6', 'muted' => '#a49c90', 'line' => '#f4efe6'],
        'light' => ['background' => '#f7f3ec', 'text' => '#1d1a22', 'muted' => '#6c6459', 'line' => '#1d1a22'],
    ];

    public static function canvas(int $width, int $height, ?string $background = null): GdImage
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));

        if (! $image instanceof GdImage) {
            throw new RuntimeException('GD could not make an image.');
        }

        imagesavealpha($image, true);

        if ($background === null) {
            imagealphablending($image, false);
            imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        } else {
            imagefill($image, 0, 0, self::color($image, $background));
        }

        imagealphablending($image, true);

        return $image;
    }

    /**
     * A #rrggbb colour, with alpha from 0 (solid) to 127 (clear).
     */
    public static function color(GdImage $image, string $hex, int $alpha = 0): int
    {
        $hex = ltrim($hex, '#');
        $channel = static fn (int $at): int => max(0, min(255, (int) hexdec(substr($hex, $at, 2))));

        return (int) imagecolorallocatealpha($image, $channel(0), $channel(2), $channel(4), max(0, min(127, $alpha)));
    }

    public static function font(string $weight = 'bold'): string
    {
        return (string) config("sharing.fonts.{$weight}");
    }

    public static function width(string $text, float $size, string $font): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box === false ? 0 : (int) abs($box[2] - $box[0]);
    }

    /**
     * Text broken into at most $maxLines lines no wider than $width, the last ending in "..." when
     * it doesn't fit.
     *
     * @return list<string>
     */
    public static function wrap(string $text, float $size, string $font, int $width, int $maxLines): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $try = $line === '' ? $word : $line.' '.$word;

            if ($line !== '' && self::width($try, $size, $font) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] .= '...';
        }

        // A single word too long for the width: cut it.
        return array_values(array_map(static function (string $line) use ($size, $font, $width): string {
            while (mb_strlen($line) > 1 && self::width($line, $size, $font) > $width) {
                $line = mb_substr($line, 0, -4).'...';
            }

            return $line;
        }, $lines));
    }

    public static function text(GdImage $image, string $text, float $size, int $x, int $baseline, int $color, string $font): void
    {
        imagettftext($image, $size, 0, $x, $baseline, $color, $font, $text);
    }

    /**
     * Mirrored bars of an outline (a byte, 0 to 255, per 50 ms), centred on $centre.
     */
    public static function bars(GdImage $image, string $outline, int $x0, int $centre, int $halfHeight, int $step, int $barWidth, int $color, int $from = 0, ?int $count = null): void
    {
        $bytes = array_values(unpack('C*', $outline) ?: []);
        $count ??= count($bytes) - $from;

        for ($i = 0; $i < $count; $i++) {
            $peak = ($bytes[$from + $i] ?? 0) / 255;
            $height = max(1, (int) round($peak * $halfHeight));
            $x = $x0 + $i * $step;
            imagefilledrectangle($image, $x, $centre - $height, $x + $barWidth - 1, $centre + $height, $color);
        }
    }
}
