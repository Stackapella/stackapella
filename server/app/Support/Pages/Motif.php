<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Support\HtmlString;

/**
 * A collection's motif drawn as a whole object (design rules, "A motif draws its whole object"): the thistle on the
 * Scottish songs, the star lantern on the villancicos, the wheat sheaf on the Thanksgiving and harvest hymns, an oval box on the Shaker songs. Each is two drawings of one size in server/resources/motifs, the
 * serious one (gradients, quiet) and the arcade one (pixel-crisp on a 4-unit grid), put in one svg so the skin picks
 * (input.css, .s-obj); their ids start with the collection's key, so two never clash on a page. Drawn on the
 * collection's hero, its /songs panel and its link card (server/tool/link_cards.py asks for this same drawing). Marks
 * only, no drawn people; still, as every motif is. The Jewish songs' hanukkiah is its own (Hanukkiah), as its lights
 * count the night.
 */
final class Motif
{
    /** The collections with a drawing here. */
    public const KEYS = ['scottish-songs', 'villancicos', 'thanksgiving-hymns', 'shaker-songs'];

    public static function has(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    /** The motif's svg with `class`, or an empty string for a key without one. */
    public static function svg(string $key, string $class, bool $game = true): HtmlString
    {
        if (! self::has($key)) {
            return new HtmlString('');
        }
        $serious = self::read($key, 'serious');
        $box = preg_match('/viewBox="0 0 (\d+) (\d+)"/', $serious, $m) === 1 ? [(int) $m[1], (int) $m[2]] : [150, 152];
        $inner = '<g class="s-obj-serious">'.self::inner($serious).'</g>';
        if ($game) {
            $inner .= '<g class="s-obj-game" shape-rendering="crispEdges">'.self::inner(self::read($key, 'game')).'</g>';
        }

        return new HtmlString('<svg class="'.e($class).'" viewBox="0 0 '.$box[0].' '.$box[1].'" style="aspect-ratio: '.$box[0].' / '.$box[1].'" preserveAspectRatio="xMaxYMin meet" aria-hidden="true" focusable="false">'.$inner.'</svg>');
    }

    private static function read(string $key, string $look): string
    {
        $path = resource_path('motifs/'.$key.'.'.$look.'.svg');

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    /** What's inside a drawing's outer svg. */
    private static function inner(string $svg): string
    {
        return preg_match('/^\s*<svg\b[^>]*>(.*)<\/svg>\s*$/s', $svg, $m) === 1 ? $m[1] : '';
    }
}
