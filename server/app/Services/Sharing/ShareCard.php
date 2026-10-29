<?php

declare(strict_types=1);

namespace App\Services\Sharing;

use RuntimeException;

/**
 * A harmony's share card (docs/backend-plan.md D6): 1200 x 630, a JPEG under 300 KB (no WebP, as
 * WhatsApp needs), with the title, who sang it, the song, and the parts' waveform in their
 * colours, in our font.
 */
final class ShareCard
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private const MAX_BYTES = 300_000;

    /**
     * @param  list<array{name: string, color: string, peaks: string}>  $tracks
     */
    public function render(string $title, string $singers, string $song, array $tracks): string
    {
        $look = Drawing::LOOKS['dark'];
        $image = Drawing::canvas(self::WIDTH, self::HEIGHT, $look['background']);
        $bold = Drawing::font('bold');
        $medium = Drawing::font('medium');

        $y = 130;
        foreach (Drawing::wrap($title, 54, $bold, self::WIDTH - 140, 2) as $line) {
            Drawing::text($image, $line, 54, 70, $y, Drawing::color($image, $look['text']), $bold);
            $y += 72;
        }

        Drawing::text($image, Drawing::wrap($singers, 28, $medium, self::WIDTH - 140, 1)[0] ?? '', 28, 70, $y + 4, Drawing::color($image, $look['text']), $medium);

        if ($song !== '') {
            Drawing::text($image, Drawing::wrap($song, 24, $medium, self::WIDTH - 140, 1)[0] ?? '', 24, 70, $y + 48, Drawing::color($image, $look['muted']), $medium);
        }

        $this->waveform($image, $tracks, 70, 450, self::WIDTH - 140, 100);
        Drawing::text($image, 'Stackapella', 26, 70, self::HEIGHT - 40, Drawing::color($image, $look['muted']), $bold);

        foreach ([86, 78, 70, 60] as $quality) {
            ob_start();
            imagejpeg($image, null, $quality);
            $jpeg = (string) ob_get_clean();

            if (strlen($jpeg) <= self::MAX_BYTES) {
                return $jpeg;
            }
        }

        throw new RuntimeException('The share card stayed over 300 KB.');
    }

    /**
     * The parts stacked in each bar, each in its colour: the harmony's shape at a glance.
     *
     * @param  list<array{name: string, color: string, peaks: string}>  $tracks
     */
    private function waveform(\GdImage $image, array $tracks, int $x0, int $centre, int $width, int $halfHeight): void
    {
        $bars = 150;
        $step = intdiv($width, $bars);
        $outlines = array_map(static fn (array $track): array => array_values(unpack('C*', (string) base64_decode($track['peaks'], true)) ?: []), $tracks);
        $longest = max(1, ...array_map('count', $outlines ?: [[]]));
        $parts = max(1, count($tracks));

        for ($i = 0; $i < $bars; $i++) {
            $from = intdiv($i * $longest, $bars);
            $to = max($from + 1, intdiv(($i + 1) * $longest, $bars));
            $top = $centre;
            $bottom = $centre;

            foreach ($tracks as $t => $track) {
                $peak = max(0, ...array_slice($outlines[$t], $from, $to - $from) ?: [0]) / 255;
                $height = max(1, (int) round($peak * $halfHeight / $parts));
                $color = Drawing::color($image, $track['color']);
                $x = $x0 + $i * $step;
                imagefilledrectangle($image, $x, $top - $height, $x + $step - 3, $top, $color);
                imagefilledrectangle($image, $x, $bottom, $x + $step - 3, $bottom + $height, $color);
                $top -= $height;
                $bottom += $height;
            }
        }
    }
}
