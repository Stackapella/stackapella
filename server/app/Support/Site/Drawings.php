<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Support\HtmlString;

/**
 * The public site's pictures of a song, drawn by the server as SVG from the song's real notes
 * (board-a2's kit): a part's waveform (louder mid-note, a dip at each syllable, quiet in the rests),
 * a part's notes as bars (the mixer's rows), and a part's note lane with a sung line traced over it. Deterministic, so a page renders the same every time. Colour comes from the page (`--c`).
 * Markup we make from numbers, so `{{ }}` prints it as it is.
 */
final class Drawings
{
    /** How many glass drawings this page has drawn: each one's gradient ids are its own. */
    private static int $drawn = 0;

    /**
     * @param  list<array{beat: float, length: float, pitch: int}>  $notes
     * @param  array{low: int, high: int}  $range
     */
    public static function wave(array $notes, array $range, float $end, int $seed, int $w = 300, int $h = 40, int $step = 3, int $bw = 2, bool $hum = false, float $gain = 1.0): HtmlString
    {
        $random = self::random($seed);
        $bars = '';

        for ($x = 1; $x < $w - 1; $x += $step) {
            $b = ($x / $w) * $end;
            $note = self::noteAt($notes, $b);
            $a = 0.04 * $random();

            if ($note !== null && $b < $end - 0.4) {
                $t = ($b - $note['beat']) / $note['length'];
                $attack = min(1, ($b - $note['beat']) / 0.25);
                $lift = 0.72 + 0.28 * min(1, max(0, ($note['pitch'] - $range['low']) / 12));
                $swell = 0.7 + 0.3 * sin(M_PI * (fmod($b, 12) / 12));
                $a = (0.3 + 0.7 * $attack) * (1 - 0.32 * $t) * $lift * $swell * (0.72 + 0.28 * $random());
                if ($hum) {
                    $a *= 0.55 + 0.45 * abs(sin($b * 2.3)) * $random() + 0.2;
                }
            }

            $half = max(0.8, $a * $gain * $h * 0.47);
            $bars .= sprintf('<rect x="%d" y="%.1f" width="%d" height="%.1f" rx="%s"/>', $x, $h / 2 - $half, $bw, $half * 2, $bw / 2);
        }

        return new HtmlString(sprintf('<svg viewBox="0 0 %d %d" preserveAspectRatio="none" aria-hidden="true" fill="var(--c)">%s</svg>', $w, $h, $bars));
    }

    /**
     * A part's own notes as bars, a small piano roll in the part's colour: across is time, up is
     * pitch (within the part's range), each bar as long as its note. Each bar carries its beat and
     * length, so the player lights it while it sounds and marks it once sung.
     *
     * @param  list<array{beat: float, length: float, pitch: int}>  $notes
     * @param  array{low: int, high: int}  $range
     */
    public static function roll(array $notes, array $range, float $end, int $w = 300, int $h = 40): HtmlString
    {
        $low = min($range['low'], ...array_column($notes, 'pitch') ?: [$range['low']]);
        $high = max($range['high'], ...array_column($notes, 'pitch') ?: [$range['high']]);
        $steps = max(1, $high - $low);
        // Bars a quarter of the row tall, so a part's shape reads even on a small phone.
        $bar = $h * 0.26;
        // Glass (the app's gem recipe, web/js/glass.js): the body in the part's colour, then a
        // colour-free depth and a gloss over it. The gradients' ids are this drawing's own, so a
        // drawing in a hidden place never takes another's.
        $id = 'g'.(++self::$drawn);
        $out = sprintf(
            '<defs><linearGradient id="%1$s-s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".38"/><stop offset=".48" stop-color="#fff" stop-opacity="0"/><stop offset=".5" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".32"/></linearGradient>'
            .'<linearGradient id="%1$s-g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".75"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient></defs>',
            $id,
        );

        foreach ($notes as $n) {
            $x = $n['beat'] / $end * $w + 0.5;
            $y = ($high - $n['pitch']) / $steps * ($h - $bar);
            $width = max(2, $n['length'] / $end * $w - 1.5);
            $out .= sprintf(
                '<g data-beat="%1$s" data-length="%2$s"><rect class="b" x="%3$.1f" y="%4$.1f" width="%5$.1f" height="%6$.1f" rx="%7$.1f"/>'
                .'<rect x="%3$.1f" y="%4$.1f" width="%5$.1f" height="%6$.1f" rx="%7$.1f" fill="url(#%8$s-s)"/>'
                .'<rect x="%9$.1f" y="%10$.1f" width="%11$.1f" height="%12$.1f" rx="%13$.1f" fill="url(#%8$s-g)"/></g>',
                $n['beat'], $n['length'], $x, $y, $width, $bar, $bar / 2, $id,
                $x + min(2.5, $width * 0.1), $y + $bar * 0.12, $width * 0.78, $bar * 0.44, $bar * 0.4,
            );
        }

        return new HtmlString(sprintf('<svg class="s-roll" viewBox="0 0 %d %d" preserveAspectRatio="none" aria-hidden="true">%s</svg>', $w, $h, $out));
    }

    /**
     * A part's notes between two beats as glossy bricks, those sung by `litTo` lit, with a sung line
     * traced over them up to it, as Sing's note lane draws them.
     *
     * @param  list<array{beat: float, length: float, pitch: int}>  $notes
     */
    public static function lane(array $notes, float $from, float $to, float $litTo, int $w = 320, int $h = 96, int $noteHeight = 15): HtmlString
    {
        $notes = array_values(array_filter($notes, static fn (array $n): bool => $n['beat'] < $to && $from < $n['beat'] + $n['length']));
        $pitches = array_column($notes, 'pitch');
        if ($pitches === []) {
            return new HtmlString('');
        }
        $low = min($pitches) - 3;
        $high = max($pitches) + 3;
        $x = static fn (float $b): float => ($b - $from) / ($to - $from) * $w;
        $y = static fn (float $p): float => ($high - $p) / ($high - $low) * $h;
        $out = '';

        for ($p = $low + 1; $p < $high; $p += 2) {
            $out .= sprintf('<line x1="0" x2="%d" y1="%.1f" y2="%.1f" stroke="var(--color-line)" stroke-width="1" opacity=".55"/>', $w, $y($p), $y($p));
        }

        // Each note a glossy brick as long as its note (the app's lane, 46c3a1f9): the body in the
        // part's colour, a colour-free depth and a gloss along its top, under this drawing's own ids.
        $id = 'g'.(++self::$drawn);
        $out .= sprintf(
            '<defs><linearGradient id="%1$s-s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".38"/><stop offset=".48" stop-color="#fff" stop-opacity="0"/><stop offset=".5" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".32"/></linearGradient>'
            .'<linearGradient id="%1$s-g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".7"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient></defs>',
            $id,
        );
        foreach ($notes as $n) {
            $start = max($from, $n['beat']);
            $lit = $litTo >= $n['beat'] + $n['length'];
            $left = $x($start) + 1;
            $top = $y($n['pitch']) - $noteHeight / 2;
            $width = max(5, $x(min($to, $n['beat'] + $n['length'])) - $x($start) - 3);
            $out .= sprintf(
                '<g opacity="%5$s"><rect x="%1$.1f" y="%2$.1f" width="%3$.1f" height="%4$d" rx="3.5" fill="var(--c)"/>'
                .'<rect x="%1$.1f" y="%2$.1f" width="%3$.1f" height="%4$d" rx="3.5" fill="url(#%6$s-s)"/>'
                .'<rect x="%7$.1f" y="%8$.1f" width="%9$.1f" height="%10$.1f" rx="2" fill="url(#%6$s-g)"/></g>',
                $left, $top, $width, $noteHeight, $lit ? '1' : '.4', $id,
                $left + 2, $top + 1.5, max(1, $width - 4), $noteHeight * 0.4,
            );
        }

        // Your voice, as Sing shows it (ux-changes 2026-10-28, "Your pitch on the lane"): a trail
        // with a little wobble and a scoop into each note, fading toward where it started, and a dot
        // in the part's colour with a cream ring and a soft glow where you are now.
        $random = self::random(11);
        $d = '';
        $first = $last = null;
        for ($b = max($from, 1); $b <= $litTo; $b += 0.1) {
            $n = self::noteAt($notes, $b) ?? self::noteAt($notes, $b - 0.3);
            if ($n === null) {
                continue;
            }
            $wobble = ($random() - 0.5) * 0.35 + ($b - $n['beat'] < 0.15 ? -0.6 : 0);
            $last = [$x($b), $y($n['pitch'] + $wobble)];
            $first ??= $last;
            $d .= sprintf('%s%.1f,%.1f', $d === '' ? 'M' : 'L', $last[0], $last[1]);
        }
        // The playhead under the dot.
        $out .= sprintf('<line x1="%.1f" x2="%.1f" y1="0" y2="%d" stroke="var(--color-cream)" stroke-width="2"/>', $x($litTo), $x($litTo), $h);
        if ($first !== null && $last !== null) {
            $out .= sprintf(
                '<defs><linearGradient id="%1$s-t" gradientUnits="userSpaceOnUse" x1="%2$.1f" y1="0" x2="%3$.1f" y2="0"><stop offset="0" stop-color="var(--color-cream)" stop-opacity="0"/><stop offset="1" stop-color="var(--color-cream)" stop-opacity=".9"/></linearGradient></defs>'
                .'<path d="%4$s" fill="none" stroke="url(#%1$s-t)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
                .'<circle cx="%5$.1f" cy="%6$.1f" r="10" fill="var(--c)" opacity=".25"/>'
                .'<circle cx="%5$.1f" cy="%6$.1f" r="5.5" fill="var(--c)" stroke="var(--color-cream)" stroke-width="1.5"/>',
                $id, $first[0], max($first[0] + 1, $last[0]), $d, $last[0], $last[1],
            );
        }

        return new HtmlString(sprintf('<svg class="s-lane" viewBox="0 0 %d %d" preserveAspectRatio="none" aria-hidden="true">%s</svg>', $w, $h, $out));
    }

    /**
     * @param  list<array{beat: float, length: float, pitch: int}>  $notes
     * @return array{beat: float, length: float, pitch: int}|null
     */
    private static function noteAt(array $notes, float $beat): ?array
    {
        foreach ($notes as $n) {
            if ($beat >= $n['beat'] && $beat < $n['beat'] + $n['length']) {
                return $n;
            }
        }

        return null;
    }

    /**
     * The mockups' small seeded generator, so the drawings match board-a2.
     *
     * @return callable(): float
     */
    private static function random(int $seed): callable
    {
        $s = $seed * 9301 + 49297;

        return static function () use (&$s): float {
            $s = ($s * 9301 + 49297) % 233280;

            return $s / 233280;
        };
    }
}
