<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The mark is four bars, one per voice, soprano to bass (web/tool/icons.py): every committed copy,
 * drawn or rendered, shows all four (Hillel saw an old three-bar one in a link preview).
 */
final class BrandMarkTest extends TestCase
{
    private const COLOURS = ['#ff9139', '#ffea6b', '#45eca4', '#968dfd'];

    public function test_every_mark_drawn_as_svg_has_the_four_bars_in_order(): void
    {
        $web = dirname(base_path()).'/web';
        $marks = [
            'icon.svg' => (string) file_get_contents($web.'/icons/icon.svg'),
            'favicon.svg' => (string) file_get_contents($web.'/icons/favicon.svg'),
            'logo-mark.js' => (string) file_get_contents($web.'/js/logo-mark.js'),
        ];

        foreach ($marks as $name => $svg) {
            preg_match_all('/<rect [^>]*fill=[\'"](#[0-9a-f]{6})[\'"]/i', $svg, $fills);
            $bars = array_values(array_filter(array_map('strtolower', $fills[1]), static fn (string $fill): bool => $fill !== '#000000'));
            $this->assertSame(self::COLOURS, $bars, $name);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pictures(): iterable
    {
        $web = dirname(__DIR__, 4).'/web/icons';
        foreach ([...glob($web.'/*.png') ?: [], ...glob($web.'/og/*.png') ?: []] as $path) {
            yield substr($path, strlen($web) + 1) => [$path];
        }
    }

    /**
     * The app icons, the landing's card and each page's card: the mark's corner (the whole square
     * of an icon, the top left of a card) shows every voice's colour.
     */
    #[DataProvider('pictures')]
    public function test_every_rendered_mark_shows_the_four_bars(string $path): void
    {
        $image = imagecreatefrompng($path);
        $this->assertNotFalse($image, $path);
        imagepalettetotruecolor($image);
        [$width, $height] = [imagesx($image), imagesy($image)];
        $square = abs($width - $height) < 10;
        [$right, $bottom] = $square ? [$width, $height] : [(int) ($width * 0.3), (int) ($height * 0.35)];

        $seen = [];
        for ($y = 0; $y < $bottom; $y += 2) {
            for ($x = 0; $x < $right; $x += 2) {
                $rgb = imagecolorat($image, $x, $y);
                foreach (self::COLOURS as $colour) {
                    [$r, $g, $b] = sscanf($colour, '#%02x%02x%02x') ?? [0, 0, 0];
                    if (abs(($rgb >> 16 & 255) - $r) + abs(($rgb >> 8 & 255) - $g) + abs(($rgb & 255) - $b) < 60) {
                        $seen[$colour] = true;
                    }
                }
            }
        }

        $this->assertSame(self::COLOURS, array_values(array_filter(self::COLOURS, static fn (string $c): bool => isset($seen[$c]))), basename($path).': a bar is missing from the mark');
    }
}
