<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Pages\Lessons;

/**
 * The Perfect your pitch course page's drawings (site/courses/perfect-your-pitch, Hillel's design of 2026-11-18), worked
 * out here so the view only places them, and drawn from the music itself:
 *
 * - ruler(): the cents ruler under the hero, 50 flat to 50 sharp, a tick every 5 cents.
 * - ladder(): the anchor point's scale, do to high do, each step at its real height in semitones (the half steps, mi to
 *   fa and ti to do, one row apart) and in its own column, so every step's tap area is its own.
 * - beatPath(): one second of two tones a gap apart (in hertz) sounding together: the envelope swells `gap` times a
 *   second, as the beat rate is exactly the gap (HyperPhysics, "Beats"). js/public/perfect-your-pitch.js draws the same
 *   curve as the slider moves.
 * - traces(): a scoop (sliding up from 95 cents flat) and a clean start, as pitch over time, the gold band the board's
 *   gold (within 10 cents).
 * - units(): every lesson by unit, each linked to the lesson page it's on (a unit's last song, its challenge, is folded
 *   into the page before it), so the page lists every lesson and the shared list below isn't repeated.
 * - SAMPLE and meters(): the sample round the board shows before anyone sings, and the sample readout, both labelled as
 *   samples on the page.
 */
final class PerfectYourPitchPage
{
    /** The scale's eight steps, do to high do, in semitones from the anchor. */
    public const SCALE = [0, 2, 4, 5, 7, 9, 11, 12];

    /** The sample round's five darts, in cents (four of them within 25). */
    public const SAMPLE = [34, -17, 9, -6, 3];

    /** The ring a dart in cents lands in: the gold within 10, the red within 25, the blue within 50, else the black. */
    public static function ring(int $cents): string
    {
        $off = abs($cents);

        return match (true) {
            $off <= 10 => 'g',
            $off <= 25 => 'r',
            $off <= 50 => 'b',
            default => 'k',
        };
    }

    /**
     * A heading without its closing full stop (the hero draws it as a bullseye), and whether it had one.
     *
     * @return array{0: string, 1: bool}
     */
    public static function stop(string $title): array
    {
        $trimmed = rtrim($title);

        return str_ends_with($trimmed, '.') ? [substr($trimmed, 0, -1), true] : [$trimmed, false];
    }

    /**
     * The ruler's ticks, 50 flat to 50 sharp: [cents, kind], kind "zero" for in tune, "quarter" at 25, else "tick".
     *
     * @return list<array{0: int, 1: string}>
     */
    public static function ruler(): array
    {
        $ticks = [];
        for ($c = -50; $c <= 50; $c += 5) {
            $ticks[] = [$c, $c === 0 ? 'zero' : ($c % 25 === 0 ? 'quarter' : 'tick')];
        }

        return $ticks;
    }

    /**
     * The ladder: a row per semitone, high do at the top, each step's name, its semitones and its column.
     *
     * @param  array<array-key, mixed>  $names  the eight steps' names, do to high do
     * @return array{rows: list<int>, steps: list<array{name: string, semis: int, col: int, row: int}>}
     */
    public static function ladder(array $names): array
    {
        $names = array_values($names);
        $steps = [];
        foreach (self::SCALE as $i => $semis) {
            $steps[] = ['name' => (string) ($names[$i] ?? ''), 'semis' => $semis, 'col' => $i, 'row' => 12 - $semis];
        }

        return ['rows' => range(12, 0), 'steps' => $steps];
    }

    /** One second of two tones `gap` hertz apart, drawn on a 560 by 100 box (js/public/perfect-your-pitch.js, beatPath). */
    public static function beatPath(float $gap): string
    {
        $d = '';
        for ($i = 0; $i <= 560; $i += 2) {
            $u = $i / 560;
            $y = 50 - 44 * cos(M_PI * $gap * $u) * cos(2 * M_PI * 38 * $u);
            $d .= ($i === 0 ? 'M' : 'L').$i.' '.number_format($y, 1, '.', '');
        }

        return $d;
    }

    /**
     * The scoop and the clean start as pitch over time on a 260 by 110 box, the note at y 34, three quarters of a pixel
     * a cent (the gold band, 10 cents either side, is 26.5 to 41.5).
     *
     * @return array{scoop: string, clean: string}
     */
    public static function traces(): array
    {
        $scoop = '';
        $clean = '';
        for ($i = 0; $i <= 260; $i += 4) {
            $u = $i / 260;
            $s = -95 * (1 - min($u / 0.45, 1)) ** 2 + ($u > 0.45 ? 3 * sin($u * 30) : 0);
            $c = 2.5 * sin($u * 20);
            $scoop .= ($i === 0 ? 'M' : 'L').$i.' '.number_format(34 - $s * 0.75, 1, '.', '');
            $clean .= ($i === 0 ? 'M' : 'L').$i.' '.number_format(34 - $c * 0.75, 1, '.', '');
        }

        return ['scoop' => $scoop, 'clean' => $clean];
    }

    /** Cents as the board writes them: "+9¢", "−17¢" (a true minus), "0¢". */
    public static function cents(int $cents): string
    {
        return ($cents > 0 ? '+'.$cents : ($cents < 0 ? '−'.abs($cents) : '0')).'¢';
    }

    /** How many of the sample round's darts landed within 25 cents (the serious skin's plain measure). */
    public static function sampleLanded(): int
    {
        return count(array_filter(self::SAMPLE, static fn (int $c): bool => abs($c) <= 25));
    }

    /**
     * The sample readout's four meters, in the order of their words (where it lands, slid in, drone lock, long-note
     * sag): the marker's place on a 50 flat to 50 sharp bar, and the reading in cents, or null for "clean" (no scoop).
     *
     * @return list<array{at: int, text: string|null}>
     */
    public static function meters(): array
    {
        return [
            ['at' => 44, 'text' => self::cents(-6)],
            ['at' => 50, 'text' => null],
            ['at' => 53, 'text' => self::cents(3)],
            ['at' => 36, 'text' => self::cents(-14)],
        ];
    }

    /**
     * Every lesson by unit, numbered within its unit, each with the lesson page it's on (null when the course has none).
     *
     * @param  array{units: list<array{title: string, lessons: list<string>}>, lessons: int}  $outline
     * @return list<array{title: string, lessons: list<array{n: string, name: string, url: string|null}>}>
     */
    public static function units(array $outline, string $course): array
    {
        // The pages' lessons, in the course's order: one page holds a lesson, and a challenge rides on the page before it.
        $urls = [];
        foreach (Lessons::pages($course) as $page) {
            $url = is_string($page['url'] ?? null) ? $page['url'] : null;
            array_push($urls, ...array_fill(0, count((array) ($page['lessons'] ?? [])), $url));
        }

        $units = [];
        $at = 0;
        foreach ($outline['units'] as $unit) {
            $rows = [];
            foreach ($unit['lessons'] as $l => $name) {
                $rows[] = ['n' => str_pad((string) ($l + 1), 2, '0', STR_PAD_LEFT), 'name' => $name, 'url' => $urls[$at++] ?? null];
            }
            $units[] = ['title' => $unit['title'], 'lessons' => $rows];
        }

        return $units;
    }
}
