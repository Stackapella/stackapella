<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Support\HtmlString;

/**
 * The Jewish songs' lamp, a hanukkiah (Hillel, 2026-11-12: "I see the lights but no menora itself", then "make the
 * menorah look nicer, you can do better"): eight arms in even half rings off one stem, their tips level, a cup and a short
 * candle on each, the shamash raised on the stem, two knops and a stepped foot. Warm gold metal and two-tone flames with a
 * soft glow in the serious skin; the same lamp in the arcade's colours, square and pixel-crisp, in game mode. Drawn on the
 * collection's hero, its /songs panel and its link card (server/tool/link_cards.py asks for this same drawing).
 *
 * During Hanukkah the page lights the shamash and that night's candles (js/public/hanukkiah.js, Hillel: "have it so the
 * right number of lights are lit when you view it for the day of the holiday"): candle 1 is the far right as you face
 * the lamp, each night adds the next to its left. Without the script, and outside Hanukkah, every candle is lit.
 * Marks only, no drawn people; still, as every motif is.
 */
final class Hanukkiah
{
    /** The stem's line, the arms' tips' level, and the arms' radii, inner to outer. */
    private const C = 120;

    private const TIP = 66;

    private const RADII = [24, 48, 72, 96];

    /** How deep each half ring is against its width (a true half circle would be 1). */
    private const DEPTH = .62;

    /** The pixel lamp's cell. */
    private const CELL = 4;

    /**
     * The lamp's SVG. `nights` adds the first evenings the page's script counts the night from; the link card has none, so
     * it stays all lit.
     */
    public static function svg(string $class, bool $nights = true): HtmlString
    {
        $firsts = $nights ? implode(' ', JewishSongs::firsts('hanukkah')) : '';
        $data = $firsts !== '' ? ' data-hanukkiah="'.e($firsts).'"' : '';

        return new HtmlString('<svg class="'.e($class).'" viewBox="0 -2 240 152" preserveAspectRatio="xMaxYMin meet" aria-hidden="true" focusable="false"'.$data.'>'
            .self::defs().'<g class="s-hk-serious">'.self::serious().'</g><g class="s-hk-game" shape-rendering="crispEdges">'.self::pixel().'</g></svg>');
    }

    /**
     * Each branch candle's x, candle 1 (the far right as you face the lamp) first.
     *
     * @return array<int, int>
     */
    public static function candles(): array
    {
        $out = [];
        foreach (array_reverse(self::RADII) as $i => $r) {
            $out[$i + 1] = self::C + $r;
        }
        foreach (self::RADII as $i => $r) {
            $out[$i + 5] = self::C - $r;
        }

        return $out;
    }

    private static function defs(): string
    {
        return '<defs>'
            .'<linearGradient id="hk-metal" gradientUnits="userSpaceOnUse" x1="0" y1="20" x2="0" y2="150"><stop offset="0" stop-color="#FFF0BE"/><stop offset=".3" stop-color="#F3D27A"/><stop offset=".72" stop-color="#C99A3A"/><stop offset="1" stop-color="#94661D"/></linearGradient>'
            .'<linearGradient id="hk-flame" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFD66B"/><stop offset="1" stop-color="#F08A24"/></linearGradient>'
            .'<radialGradient id="hk-glow"><stop offset="0" stop-color="#FFD98A" stop-opacity=".6"/><stop offset=".45" stop-color="#FFC45C" stop-opacity=".22"/><stop offset="1" stop-color="#FFC45C" stop-opacity="0"/></radialGradient>'
            .'</defs>';
    }

    private static function serious(): string
    {
        $c = self::C;
        $svg = '<g fill="none" stroke="url(#hk-metal)" stroke-width="4" stroke-linecap="round">';
        foreach (self::RADII as $r) {
            $svg .= '<path d="M'.($c - $r).' '.self::TIP.' A '.$r.' '.round($r * self::DEPTH, 1).' 0 0 0 '.($c + $r).' '.self::TIP.'"/>';
        }
        $svg .= '</g><g fill="url(#hk-metal)">'
            // The stem, its two knops, and the stepped foot: heavier than the arms.
            .'<rect x="'.($c - 3).'" y="40" width="6" height="96" rx="1.5"/>'
            .'<ellipse cx="'.$c.'" cy="98" rx="6.5" ry="3.5"/><ellipse cx="'.$c.'" cy="124" rx="7.5" ry="4"/>'
            .'<path d="M'.($c - 12).' 134 H'.($c + 12).' L'.($c + 18).' 141 H'.($c - 18).' Z"/>'
            .'<path d="M'.($c - 24).' 141 H'.($c + 24).' L'.($c + 30).' 148 H'.($c - 30).' Z"/>'
            .self::cup($c, 40);
        foreach (self::candles() as $x) {
            $svg .= self::cup($x, self::TIP);
        }
        $svg .= '</g>'.self::light($c, 40, 0);
        foreach (self::candles() as $n => $x) {
            $svg .= self::light($x, self::TIP, $n);
        }

        return $svg;
    }

    /** A cup whose rim is `top - 6` and foot `top`, on an arm's tip or the stem's. */
    private static function cup(int $x, int $top): string
    {
        return '<path d="M'.($x - 6).' '.($top - 6).' H'.($x + 6).' L'.($x + 3.5).' '.$top.' H'.($x - 3.5).' Z"/>';
    }

    /** A candle in its cup (its foot at `top - 6`), its wick, and its flame and glow; `n` 0 is the shamash, always lit. */
    private static function light(int $x, int $top, int $n): string
    {
        $foot = $top - 6;
        $head = $foot - 14;
        $flame = '<g class="s-hk-flame"><circle cx="'.$x.'" cy="'.($head - 9).'" r="11" fill="url(#hk-glow)"/>'
            .'<path d="M'.$x.' '.($head - 1).' C '.($x - 5).' '.($head - 4).', '.($x - 4.5).' '.($head - 11).', '.$x.' '.($head - 18).' C '.($x + 4.5).' '.($head - 11).', '.($x + 5).' '.($head - 4).', '.$x.' '.($head - 1).' Z" fill="url(#hk-flame)"/>'
            .'<path d="M'.$x.' '.($head - 2).' C '.($x - 2.2).' '.($head - 4).', '.($x - 2).' '.($head - 7).', '.$x.' '.($head - 10).' C '.($x + 2).' '.($head - 7).', '.($x + 2.2).' '.($head - 4).', '.$x.' '.($head - 2).' Z" fill="#FFF8E4"/></g>';

        return '<g'.($n > 0 ? ' data-hk="'.$n.'"' : '').'>'
            .'<rect x="'.($x - 2.4).'" y="'.$head.'" width="4.8" height="14" rx=".8" fill="#FBF3E2"/><rect x="'.($x + .6).'" y="'.$head.'" width="1.8" height="14" fill="#E3D3B2"/>'
            .'<path d="M'.$x.' '.$head.' V'.($head - 3).'" stroke="#4A3520" stroke-width="1.2" stroke-linecap="round"/>'.$flame.'</g>';
    }

    /** The arcade lamp: the same shape on a 4-unit grid, gold with a darker underside, flames in orange and yellow. */
    private static function pixel(): string
    {
        $s = self::CELL;
        $c = self::C;
        $cells = [];
        foreach (self::RADII as $r) {
            for ($i = 0; $i <= 180; $i++) {
                $a = M_PI * $i / 180;
                $x = (int) (floor(($c - $r * cos($a)) / $s) * $s);
                $y = (int) (floor((self::TIP + $r * self::DEPTH * sin($a)) / $s) * $s);
                $cells[$x.','.$y] = [$x, $y];
            }
        }
        // Each row's cells as runs, one rect a run.
        $rows = [];
        foreach ($cells as [$x, $y]) {
            $rows[$y][] = $x;
        }
        $svg = '<g fill="#FFC93C">';
        foreach ($rows as $y => $xs) {
            sort($xs);
            $from = $xs[0];
            foreach ($xs as $i => $x) {
                if (! isset($xs[$i + 1]) || $xs[$i + 1] !== $x + $s) {
                    $svg .= '<rect x="'.($from - 2).'" y="'.$y.'" width="'.($x - $from + $s).'" height="'.$s.'"/>';
                    $from = $xs[$i + 1] ?? 0;
                }
            }
        }
        $svg .= '<rect x="'.($c - 4).'" y="32" width="8" height="104"/><rect x="'.($c - 8).'" y="96" width="16" height="4"/><rect x="'.($c - 8).'" y="122" width="16" height="4"/>'
            .'<rect x="'.($c - 16).'" y="136" width="32" height="4"/><rect x="'.($c - 28).'" y="140" width="56" height="8"/>';
        foreach ([$c => 32] + array_fill_keys(array_values(self::candles()), self::TIP) as $x => $top) {
            $svg .= '<rect x="'.($x - 8).'" y="'.($top - 4).'" width="16" height="4"/>';
        }
        $svg .= '</g><g fill="#C77D0A"><rect x="'.($c - 28).'" y="144" width="56" height="4"/><rect x="'.$c.'" y="32" width="4" height="104"/></g>'
            .self::pixelLight($c, 28, 0);
        foreach (self::candles() as $n => $x) {
            $svg .= self::pixelLight($x, self::TIP - 4, $n);
        }

        return $svg;
    }

    private static function pixelLight(int $x, int $foot, int $n): string
    {
        $head = $foot - 12;

        return '<g'.($n > 0 ? ' data-hk="'.$n.'"' : '').'><rect x="'.($x - 2).'" y="'.$head.'" width="4" height="12" fill="#F7F1E6"/><rect x="'.($x - 2).'" y="'.($head - 4).'" width="4" height="4" fill="#3A2A1A"/>'
            // A pixel flame: an orange tip and body round a yellow core, a white-hot heart at its foot.
            .'<g class="s-hk-flame"><rect x="'.($x - 2).'" y="'.($head - 18).'" width="4" height="4" fill="#FF9139"/><rect x="'.($x - 4).'" y="'.($head - 14).'" width="8" height="10" fill="#FF9139"/>'
            .'<rect x="'.($x - 2).'" y="'.($head - 12).'" width="4" height="8" fill="#FFEA6B"/><rect x="'.($x - 2).'" y="'.($head - 8).'" width="4" height="4" fill="#FFF8D6"/></g></g>';
    }
}
