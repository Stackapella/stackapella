<?php

declare(strict_types=1);

namespace App\Support\Pages;

/**
 * What the Vibrato course page (site/courses/vibrato, Hillel's design of 2026-11-18) shows beyond its words: its waves,
 * each drawn to one scale from its rate (waves a second) and width (± cents, half the swing), as js/public/vibrato.js
 * draws and plays them; the app's own measure of straight, vibrato and wobble (web/js/vibrato.js, kept equal by
 * VibratoPageTest); the guide waves each unit's lessons sing (the course file's wave steps); the units' lessons as links
 * to their pages (Lessons::pages); the years told in the course's own lesson facts; and the one Sources list.
 * The words are lang/en/site.php courses.pages.vibrato.
 */
final class VibratoPage
{
    /** The app's measure (web/js/vibrato.js): a wave narrower than ± this many cents is straight. */
    public const STRAIGHT = 12;

    /** A vibrato's speed (waves a second) and width (± cents) to the app (VIBRATO_RATE, VIBRATO_WIDTH). */
    public const VIBRATO_RATE = [4.5, 7.5];

    public const VIBRATO_WIDTH = [15, 120];

    /** The page's even wave: Prame's ten singers averaged 6.0 a second; ± 50 cents is lesson 1's first swing. */
    public const WAVE = ['rate' => 6, 'cents' => 50];

    /** The page's wobble, uneven: its speed and width wander between these, always a wobble to the app. */
    public const WOBBLE = ['rate' => [3.5, 4.4], 'cents' => [120, 150]];

    /** Classical singers' usual vibrato, a second (Prame 1994; Sundberg 1995): the readout's natural range. */
    public const NATURAL = [5.5, 7];

    /** The hero's held note: straight, then the wave blooms from this second, nearing its width with this time constant. */
    public const BLOOM = ['from' => 0.9, 'tau' => 0.35, 'secs' => 3];

    /** The dividers down the page, each wider: its width in ± cents, drawn at 0.3 px a cent. */
    public const DIVIDERS = [0, 10, 25, 50];

    /** The voices the page's sounds are sung in (MIDI): low, mid (A3, the default) and high. */
    public const PITCHES = ['low' => 50, 'mid' => 57, 'high' => 64];

    /** The lessons whose fact tells a year, in the years' order (the course file's lesson ids). */
    public const YEARS = ['vib-4-switch', 'vib-3-yours', 'vib-4-style', 'vib-1-hear', 'vib-1-laser', 'vib-2-echo', 'vib-4-bloom'];

    private const SLUG = 'vibrato';

    private const NAMES = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];

    /**
     * A line with a word set apart in it (`:wave` in the headline): the words before and after it, so the view prints
     * both escaped around its own mark.
     *
     * @return array{0: string, 1: string}
     */
    public static function around(string $words, string $mark): array
    {
        if ($mark === '') {
            return [$words, ''];
        }
        $parts = explode($mark, $words, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * Singing it safely's points: the course's three safety lines with the drills' "five minutes, then a rest" second.
     *
     * @param  array<string, mixed>  $page
     * @return list<string>
     */
    public static function safety(array $page): array
    {
        $lines = array_values(array_map('strval', (array) ($page['safety'] ?? [])));
        $rest = (string) ($page['gentle']['points'][2] ?? '');
        if ($rest !== '') {
            array_splice($lines, 1, 0, [$rest]);
        }

        return $lines;
    }

    /**
     * Lesson 1's steps with the shape each sets on the deck (waves a second, ± cents): a soft hum held straight, the
     * lesson's first swing (its guide: 2 a second at ± 50), settled straight again, then a small wave at a natural speed.
     *
     * @return list<array{rate: float, cents: int}>
     */
    public static function stepShapes(): array
    {
        return [['rate' => 6.0, 'cents' => 0], ['rate' => 2.0, 'cents' => 50], ['rate' => 6.0, 'cents' => 0], ['rate' => 6.0, 'cents' => 25]];
    }

    /**
     * The three surfaces: straight, the even wave and the wobble, each with its wave on one scale (1.5 seconds across,
     * 0.4375 px a cent, so the wobble's ± 150 fills its box and the wave's ± 50 is a third of it) and its reading.
     *
     * @param  array<string, mixed>  $page
     * @return list<array{key: string, name: string, what: string, tag: string, spec_words: string, spec: array<string, float|int|string>}>
     */
    public static function kinds(array $page): array
    {
        $box = ['w' => 400, 'h' => 140, 'secs' => 1.5, 'scale' => 0.4375, 'speed' => 1.2, 'n' => 240];
        $range = static fn (array $pair): string => str_replace([':a', ':b'], [self::number((float) $pair[0]), self::number((float) $pair[1])], (string) $page['units_guide']['range']);
        $specs = [
            ['shape' => 'even', 'rate' => 0, 'cents' => 0],
            ['shape' => 'even', 'rate' => self::WAVE['rate'], 'cents' => self::WAVE['cents']],
            ['shape' => 'wobble'],
        ];
        $words = [
            (string) $page['kinds_spec']['still'],
            str_replace([':rate', ':cents'], [(string) self::WAVE['rate'], (string) self::WAVE['cents']], (string) $page['kinds_spec']['wave']),
            str_replace([':rate', ':cents'], [$range(self::WOBBLE['rate']), $range(self::WOBBLE['cents'])], (string) $page['kinds_spec']['wobble']),
        ];
        $kinds = [];
        foreach (array_values((array) $page['kinds']) as $i => [$name, $what]) {
            $kinds[] = [
                'key' => ['straight', 'wave', 'wobble'][$i] ?? 'straight',
                'name' => (string) $name,
                'what' => (string) $what,
                'tag' => (string) ($page['kinds_tag'][$i] ?? ''),
                'spec_words' => $words[$i] ?? '',
                'spec' => [...$box, ...($specs[$i] ?? $specs[0])],
            ];
        }

        return $kinds;
    }

    /**
     * Each outcome's small wave, on one scale (3 seconds, 0.16 px a cent): straight; straight, the wave and the wobble
     * in turn; unit 2's ladder, quickening and narrowing; and straight, then the wave blooming.
     *
     * @return list<array<string, float|int|string>>
     */
    public static function aims(): array
    {
        $box = ['w' => 120, 'h' => 56, 'secs' => 3, 'scale' => 0.16, 'speed' => 0.5, 'n' => 180];

        return [
            [...$box, 'shape' => 'even', 'cents' => 0],
            [...$box, 'shape' => 'three'],
            [...$box, 'shape' => 'steer'],
            [...$box, 'shape' => 'bloom', 'rate' => self::WAVE['rate'], 'cents' => self::WAVE['cents'], 'from' => 1.1, 'tau' => self::BLOOM['tau']],
        ];
    }

    /**
     * The deck's readout: each state's words and its small shape, so a state is never told by colour alone.
     *
     * @param  array<string, mixed>  $page
     * @return array<string, array{0: string, 1: string}>
     */
    public static function verdicts(array $page): array
    {
        $glyphs = [
            'straight' => 'M1 6H23',
            'natural' => 'M1 6Q2.5 1 4 6T7 6T10 6T13 6T16 6T19 6T22 6',
            'steered' => 'M1 6Q4 1 7 6T13 6T19 6T23 6',
            'wobble' => 'M1 6Q4 -3 8 6T14 9T23 4',
            'tremble' => 'M1 6L2.5 4.5L4 7.5L5.5 4.5L7 7.5L8.5 4.5L10 7.5L11.5 4.5L13 7.5L14.5 4.5L16 7.5L17.5 4.5L19 7.5L20.5 4.5L22 7.5L23 6',
        ];
        $out = [];
        foreach ((array) $page['deck']['verdict'] as $state => $words) {
            $out[(string) $state] = [(string) $words, $glyphs[$state] ?? $glyphs['straight']];
        }

        return $out;
    }

    /**
     * What the app counts as straight, vibrato, wobble and tremble, in the page's words with the measure's numbers.
     *
     * @param  array<string, mixed>  $page
     */
    public static function measure(array $page): string
    {
        return str_replace(
            [':straight', ':r1', ':r2', ':w1', ':w2'],
            [(string) self::STRAIGHT, self::number(self::VIBRATO_RATE[0]), self::number(self::VIBRATO_RATE[1]), (string) self::VIBRATO_WIDTH[0], (string) self::VIBRATO_WIDTH[1]],
            (string) $page['app_measure'],
        );
    }

    /**
     * Whether the units list every lesson as a link to its page, so the shared list of lesson pages needn't repeat them.
     *
     * @param  list<array{lessons: list<array{url: string|null}>}>  $units
     */
    public static function linksEveryLesson(array $units): bool
    {
        $lessons = array_merge(...array_map(static fn (array $unit): array => $unit['lessons'], $units ?: [['lessons' => []]]));

        return $lessons !== [] && array_filter($lessons, static fn (array $lesson): bool => $lesson['url'] === null) === [];
    }

    /** A MIDI note's name, as the hero's trace labels it (A3). */
    public static function noteName(int $midi): string
    {
        return self::NAMES[$midi % 12].(intdiv($midi, 12) - 1);
    }

    /**
     * A wave's spec with its defaults: its shape, the box it's drawn in (w by h, from x0 to x1), the seconds across it,
     * its rate and width, the scale (px a cent), the bloom's or growth's start and end, and how fast its phase drifts.
     *
     * @param  array<string, float|int|string>  $spec
     * @return array{shape: string, w: float, h: float, x0: float, x1: float, secs: float, rate: float, cents: float, scale: float, from: float, to: float, tau: float, speed: float, n: int}
     */
    public static function wave(array $spec): array
    {
        $w = (float) ($spec['w'] ?? 600);

        return [
            'shape' => (string) ($spec['shape'] ?? 'even'),
            'w' => $w,
            'h' => (float) ($spec['h'] ?? 120),
            'x0' => (float) ($spec['x0'] ?? 0),
            'x1' => (float) ($spec['x1'] ?? $w),
            'secs' => (float) ($spec['secs'] ?? 1),
            'rate' => (float) ($spec['rate'] ?? 0),
            'cents' => (float) ($spec['cents'] ?? 0),
            'scale' => (float) ($spec['scale'] ?? 1),
            'from' => (float) ($spec['from'] ?? 0),
            'to' => (float) ($spec['to'] ?? 1),
            'tau' => (float) ($spec['tau'] ?? 0.35),
            'speed' => (float) ($spec['speed'] ?? 1),
            'n' => (int) ($spec['n'] ?? 240),
        ];
    }

    /**
     * Where the wave is at `sec` seconds (`u` of the way across): its width there (± cents) and the waves gone by.
     * The same sums as js/public/vibrato.js `at`.
     *
     * @param  array{shape: string, secs: float, rate: float, cents: float, from: float, to: float, tau: float}  $s
     * @return array{0: float, 1: float}
     */
    public static function at(array $s, float $sec, float $u): array
    {
        return match ($s['shape']) {
            'bloom' => [$sec < $s['from'] ? 0.0 : $s['cents'] * (1 - exp(-($sec - $s['from']) / $s['tau'])), $s['rate'] * $sec],
            'grow' => [$s['cents'] * self::smooth(($u - $s['from']) / max(0.001, $s['to'] - $s['from'])), $s['rate'] * $sec],
            'wobble' => self::wobble($sec),
            // Unit 2's ladder: 2 waves a second at ± 50 cents, quickening to 5 at ± 20.
            'steer' => [50 - 30 * $u, $s['secs'] * (2 * $u + 1.5 * $u * $u)],
            // Straight, then the even wave, then the wobble, a third each.
            'three' => $u < 1 / 3 ? [0.0, 0.0] : ($u < 2 / 3 ? [(float) self::WAVE['cents'], self::WAVE['rate'] * $sec] : self::wobble($sec)),
            default => [$s['cents'], $s['rate'] * $sec],
        };
    }

    /**
     * The wobble at `sec`: its width wanders 120 to 150 cents and its speed 3.5 to 4.4 a second (the waves gone by are
     * that speed's sum), so the app always calls it a wobble: slower than its vibrato's 4.5 a second, and as wide as one
     * ever is (± 120) or wider.
     *
     * @return array{0: float, 1: float}
     */
    public static function wobble(float $sec): array
    {
        $mid = (self::WOBBLE['rate'][0] + self::WOBBLE['rate'][1]) / 2;
        $swing = (self::WOBBLE['rate'][1] - self::WOBBLE['rate'][0]) / 2;
        $width = (self::WOBBLE['cents'][0] + self::WOBBLE['cents'][1]) / 2;
        $spread = (self::WOBBLE['cents'][1] - self::WOBBLE['cents'][0]) / 2;

        return [$width + $spread * sin(2 * M_PI * 0.45 * $sec + 1), $mid * $sec - $swing / (2 * M_PI * 0.6) * cos(2 * M_PI * 0.6 * $sec)];
    }

    /**
     * The wave's path at time `t` (its phase drifted by speed × t), for the page's first paint and a still page.
     *
     * @param  array<string, float|int|string>  $spec
     */
    public static function path(array $spec, float $t = 0.0): string
    {
        $s = self::wave($spec);
        $cy = $s['h'] / 2;
        $d = '';
        for ($i = 0; $i <= $s['n']; $i++) {
            $u = $i / $s['n'];
            $sec = $u * $s['secs'];
            [$cents, $cycles] = self::at($s, $sec, $u);
            $x = $s['x0'] + $u * ($s['x1'] - $s['x0']);
            $y = $cy - $cents * $s['scale'] * sin(2 * M_PI * ($cycles - $s['speed'] * $t));
            $d .= ($i ? 'L' : 'M').round($x, 1).' '.round($y, 1);
        }

        return $d;
    }

    /**
     * Each unit's guide waves, as the course file's wave steps sing them: the slowest to fastest rate and narrowest to
     * widest width, or null for a unit that only holds straight or lets its own wave come.
     *
     * @return list<array{rate: array{0: float, 1: float}, cents: array{0: float, 1: float}}|null>
     */
    public static function guides(): array
    {
        $file = Lessons::courses()[self::SLUG]['file'] ?? null;
        $data = is_string($file) && is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $guides = [];
        foreach (array_values(array_filter((array) ($data['units'] ?? []), 'is_array')) as $unit) {
            $rates = [];
            $widths = [];
            foreach ((array) ($unit['lessons'] ?? []) as $lesson) {
                foreach ((array) (is_array($lesson) ? $lesson['steps'] ?? [] : []) as $step) {
                    foreach ((array) (is_array($step) ? $step['exercise']['target'] ?? [] : []) as $target) {
                        if (is_array($target) && ($target['kind'] ?? null) === 'wave' && is_numeric($target['hz'] ?? null) && is_numeric($target['cents'] ?? null)) {
                            $rates[] = (float) $target['hz'];
                            $widths[] = (float) $target['cents'];
                        }
                    }
                }
            }
            $guides[] = $rates === [] || $widths === [] ? null : ['rate' => [min($rates), max($rates)], 'cents' => [min($widths), max($widths)]];
        }

        return $guides;
    }

    /**
     * A unit's guide line in the page's words (`guide`: ":rate a second · ±:cents¢"), each a single number or a range
     * (`range`: ":a to :b"); `own` for a unit with no guide wave.
     *
     * @param  array{rate: array{0: float, 1: float}, cents: array{0: float, 1: float}}|null  $guide
     * @param  array<string, mixed>  $words  the page's `units_guide` words
     */
    public static function guideLine(?array $guide, array $words): string
    {
        if ($guide === null) {
            return (string) $words['own'];
        }
        $span = static function (array $pair) use ($words): string {
            [$a, $b] = [self::number($pair[0]), self::number($pair[1])];

            return $a === $b ? $a : str_replace([':a', ':b'], [$a, $b], (string) $words['range']);
        };

        return str_replace([':rate', ':cents'], [$span($guide['rate']), $span([$guide['cents'][1], $guide['cents'][0]])], (string) $words['guide']);
    }

    /** A number as the page writes it: whole numbers plain, else to one place, in the page's language. */
    public static function number(float $value): string
    {
        $text = floor($value) === $value ? (string) (int) $value : number_format($value, 1);

        return app()->getLocale() === 'es' ? str_replace('.', ',', $text) : $text;
    }

    /**
     * Each unit's title, its line, its guide and its lessons, numbered through the course (01 to 17): each lesson's
     * name, its page's search title (a challenge's own line, from `challenges` by lesson id) and its address (a
     * challenge's, its place on the page it's folded into); a course without lesson pages lists the names alone.
     *
     * @param  array<string, mixed>  $page  the course's words
     * @param  array{units: list<array{title: string, lessons: list<string>}>, lessons: int}|null  $outline
     * @return list<array{title: string, say: string, guide: string, lessons: list<array{n: string, name: string, line: string, url: string|null}>}>
     */
    public static function units(array $page, ?array $outline): array
    {
        $byUnit = [];
        foreach (Lessons::pages(self::SLUG) as $lessonPage) {
            $byUnit[(int) $lessonPage['unitNumber']][] = $lessonPage;
        }
        $challenges = (array) ($page['challenges'] ?? []);
        $says = array_values((array) ($page['units_say'] ?? []));
        $guides = self::guides();
        $units = [];
        $n = 0;

        foreach (array_values($outline['units'] ?? []) as $u => $unit) {
            $lessons = [];
            foreach ($byUnit[$u + 1] ?? [] as $lessonPage) {
                $group = array_values((array) $lessonPage['lessons']);
                foreach ($group as $i => $lesson) {
                    $lessons[] = [
                        'n' => sprintf('%02d', ++$n),
                        'name' => (string) $lesson['name'],
                        'line' => $i === 0 ? (string) $lessonPage['title'] : (string) ($challenges[$lesson['id']] ?? ''),
                        'url' => $lessonPage['url'].(count($group) > 1 ? '#lesson-'.($i + 1) : ''),
                    ];
                }
            }
            if ($lessons === []) {
                $lessons = array_map(static function (string $name) use (&$n): array {
                    return ['n' => sprintf('%02d', ++$n), 'name' => $name, 'line' => '', 'url' => null];
                }, $unit['lessons']);
            }
            $units[] = [
                'title' => $unit['title'],
                'say' => (string) ($says[$u] ?? ''),
                'guide' => self::guideLine($guides[$u] ?? null, (array) ($page['units_guide'] ?? ['own' => '', 'range' => '', 'guide' => ''])),
                'lessons' => $lessons,
            ];
        }

        return $units;
    }

    /**
     * The years the course's own lesson facts tell, in order: the year (`years`, by lesson id), the fact and its source,
     * and the lesson it's told in, linked to its page. A lesson without its fact is left out.
     *
     * @param  array<string, mixed>  $page
     * @return list<array{id: string, year: string, text: string, source: string, url: string|null, lesson: string, at: string}>
     */
    public static function years(array $page): array
    {
        $found = [];
        foreach (Lessons::pages(self::SLUG) as $lessonPage) {
            foreach ((array) $lessonPage['lessons'] as $lesson) {
                if (in_array($lesson['id'], self::YEARS, true) && is_array($lesson['fact'] ?? null)) {
                    $found[$lesson['id']] = [
                        'id' => (string) $lesson['id'],
                        'year' => (string) ($page['years'][$lesson['id']] ?? ''),
                        'text' => (string) $lesson['fact']['text'],
                        'source' => (string) ($lesson['fact']['source'] ?? ''),
                        'url' => $lesson['fact']['url'] ?? null,
                        'lesson' => (string) $lesson['name'],
                        'at' => (string) $lessonPage['url'],
                    ];
                }
            }
        }

        return array_values(array_filter(array_map(static fn (string $id): ?array => $found[$id] ?? null, self::YEARS)));
    }

    /**
     * The page's one Sources list, numbered in order: its own (`sources`: key => [label, url or null]), then each
     * year's, keyed by its lesson id.
     *
     * @param  array<string, mixed>  $page
     * @param  list<array{id: string, source: string, url: string|null}>  $years
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function sources(array $page, array $years): array
    {
        $sources = [];
        foreach ((array) ($page['sources'] ?? []) as $key => [$label, $url]) {
            $sources[(string) $key] = [(string) $label, is_string($url) && $url !== '' ? $url : null];
        }
        foreach ($years as $year) {
            $sources[$year['id']] = [$year['source'], $year['url']];
        }

        return $sources;
    }

    private static function smooth(float $x): float
    {
        $x = max(0.0, min(1.0, $x));

        return $x * $x * (3 - 2 * $x);
    }
}
