<?php

declare(strict_types=1);

namespace App\Services\Sharing;

use GdImage;

/**
 * A song's link card (the previews of its invite, its page and the app's links to it): 1200 x 630,
 * a PNG in the light look, with the mark, what the link does ("Join in", "Listen"), the song's
 * title and its parts, and the four part colours stacked as the mark has them. Nothing about who
 * sent the link.
 */
final class SongCard
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /** The four parts' colours, as the mark and the site's own link cards stack them. */
    private const STACK = [['#ff9139', 1.0], ['#ffea6b', 0.74], ['#45eca4', 0.88], ['#968dfd', 0.62]];

    /** The eyebrow's colour: the site's green, darkened to read on the light background. */
    private const ACCENT = '#0b7a62';

    public function render(string $eyebrow, string $title, string $line): string
    {
        $look = Drawing::LOOKS['light'];
        $image = Drawing::canvas(self::WIDTH, self::HEIGHT, $look['background']);
        $bold = Drawing::font('bold');
        $medium = Drawing::font('medium');
        $text = Drawing::color($image, $look['text']);

        // The mark: four short bars, then the name.
        foreach (self::STACK as $i => [$colour, $share]) {
            $this->pill($image, 80, 74 + $i * 13, (int) round(48 * $share), 9, $colour);
        }
        Drawing::text($image, 'Stackapella', 34, 146, 116, $text, $bold);

        Drawing::text($image, mb_strtoupper($eyebrow), 24, 80, 228, Drawing::color($image, self::ACCENT), $bold);

        $y = 318;
        foreach (Drawing::wrap($title, 76, $bold, self::WIDTH - 160, 2) as $row) {
            Drawing::text($image, $row, 76, 80, $y, $text, $bold);
            $y += 90;
        }

        if ($line !== '') {
            Drawing::text($image, Drawing::wrap($line, 30, $medium, 760, 1)[0] ?? '', 30, 80, $y + 6, Drawing::color($image, $look['muted']), $medium);
        }

        foreach (self::STACK as $i => [$colour, $share]) {
            $this->pill($image, self::WIDTH - 80 - 220, self::HEIGHT - 72 - (3 - $i) * 32 - 20, (int) round(220 * $share), 20, $colour);
        }

        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }

    /** A bar with round ends. */
    private function pill(GdImage $image, int $x, int $y, int $width, int $height, string $colour): void
    {
        $fill = Drawing::color($image, $colour);
        $radius = intdiv($height, 2);
        imagefilledrectangle($image, $x + $radius, $y, $x + $width - $radius, $y + $height - 1, $fill);
        imagefilledellipse($image, $x + $radius, $y + $radius, $height, $height, $fill);
        imagefilledellipse($image, $x + $width - $radius, $y + $radius, $height, $height, $fill);
    }
}
