<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Pages\Lessons;

/**
 * The Humming and lip trills course page (site/courses/humming-and-lip-trills, Hillel's design of 2026-11-18): the
 * course's four sounds as ribbons, each drawn from the lessons' own exercises (web/course/plus/humming/course.json), so
 * the picture is the course.
 *
 * - hero(): one ribbon through the four sounds, each from its first lesson: the lip trill's roller coaster (A roller
 *   coaster), the "ng" siren (Bigger sirens), the buzzing hum (A buzzing hum) and the hum opened into "ah" (Hum to
 *   open). Each sound has a quarter of the width; its samples go to js/public/humming-and-lip-trills.js, which sings
 *   the same line on voices.js when pressed.
 * - units(): each unit's card, its shape from one of its own lessons, its line from the page's `learn`, and every lesson
 *   linked to the page it's on (a challenge is on its unit's third lesson's page).
 * - facts() and sources(): the "Did you know?" facts, each led by its big word and cited from the lesson that tells it.
 * - take(): a sample take, plainly a sample: a ribbon from Siren loops, 24 notes with three off pitch and one stop.
 * - grace(): Amazing Grace's first line, its four parts as the beginner course sings them (its template).
 * - reads(): every lesson page, by unit.
 *
 * A shape is up to three SVG paths: `line` (stroked), `dots` (the trill's bubbles) and `fill` (the "ah" widening). A
 * missing file gives empty shapes, never an error.
 */
final class HummingRibbons
{
    public const COURSE = 'humming-and-lip-trills';

    /** The note the hero's ribbon is sung on, a G below middle C: a singer's own note stands in for it in the app. */
    public const BASE = 55;

    /** The hero's four sounds: its kind, and the lesson and exercise (in the lesson's order) it's drawn from. */
    private const HERO = [
        ['trill', 'hum-1-coaster', 0, null],
        ['siren', 'hum-2-bigger', 0, null],
        ['hum', 'hum-3-buzz', 0, null],
        ['ah', 'hum-3-open', 0, 'ah'],
    ];

    /** Each unit's shape: its kind, its lesson, and the exercises drawn one after another. */
    private const UNITS = [
        ['trill', 'hum-1-challenge', [0]],
        ['siren', 'hum-2-bigger', [0, 1]],
        ['hum', 'hum-3-tune', [0]],
        ['ah', 'hum-4-trill-line', [1]],
    ];

    /** The facts' lessons (in the page's `history` order), their part colours and the language of their big word. */
    private const FACTS = [
        ['hum-4-words', 3, 'it'],
        ['hum-2-open', 2, 'mi'],
        ['hum-3-open', 4, null],
    ];

    /** The sample take: Siren loops' ribbon, 24 notes, which are off pitch, and where the sound stopped. */
    private const TAKE = ['hum-2-challenge', 24, [5, 12, 20], 16];

    /**
     * Everything the page draws, in one call so the view holds no logic: the page's own words (`hum`), the headline's
     * pieces, the hero, the units, the facts and their sources, what the app listens for (first sentence and the rest), the
     * sample take, Amazing Grace's parts, every lesson page by unit, and the group's other courses ([slug, name]).
     *
     * @param  array<array-key, mixed>  $page
     * @param  array{units: list<array{title: string, lessons: list<string>}>, lessons: int}|null  $outline
     * @param  array<array-key, mixed>  $group
     * @return array<string, mixed>
     */
    public static function view(array $page, ?array $outline, array $group): array
    {
        $hum = (array) ($page['hum'] ?? []);
        $sources = self::sources();
        $more = [];
        foreach ((array) ($group['courses'] ?? []) as $slug) {
            if (is_string($slug) && $slug !== ($page['slug'] ?? null)) {
                $more[] = [$slug, (string) __('site.courses.pages.'.$slug.'.name')];
            }
        }

        return [
            'hum' => $hum,
            'headline' => self::marked((string) ($hum['headline'] ?? '')),
            'hero' => self::hero(),
            'units' => $outline !== null ? self::units($outline, (array) ($page['learn'] ?? [])) : [],
            'lessons' => $outline['lessons'] ?? 0,
            'facts' => array_map(static fn (array $fact): array => [...$fact, 'cited' => isset($sources[$fact['key']])], self::facts((array) ($page['history']['body'] ?? []), (array) ($hum['words'] ?? []))),
            'sources' => $sources,
            'hears' => self::lead((string) ($page['measures'] ?? '')),
            'take' => self::take(),
            'grace' => self::grace(),
            'reads' => self::reads(),
            'more' => $more,
        ];
    }

    /**
     * A headline with marked words, "[1 bubble]" in part 1's colour (no colon, which a translation would read as a placeholder), as its pieces.
     *
     * @return list<array{text: string, part: int|null}>
     */
    public static function marked(string $text): array
    {
        $bits = [];
        $at = 0;
        preg_match_all('/\[([1-6]) ([^\]]+)\]/u', $text, $marks, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($marks as $mark) {
            if ($mark[0][1] > $at) {
                $bits[] = ['text' => substr($text, $at, $mark[0][1] - $at), 'part' => null];
            }
            $bits[] = ['text' => $mark[2][0], 'part' => (int) $mark[1][0]];
            $at = $mark[0][1] + strlen($mark[0][0]);
        }
        if ($at < strlen($text)) {
            $bits[] = ['text' => substr($text, $at), 'part' => null];
        }

        return $bits;
    }

    /**
     * A paragraph as its first sentence and the rest ('' when it's one sentence).
     *
     * @return array{0: string, 1: string}
     */
    public static function lead(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+/u', trim($text), 2) ?: [$text];

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * The hero's ribbon in a 560 by 220 box: each sound a quarter of it, step 0 on y 150 and a semitone 11 high, and
     * what the script sings: each sound's start and length in seconds, its place (0 to 1 of the box's width) and its
     * pitch every tenth of a second, in semitones over BASE.
     *
     * @return array{segments: list<array{kind: string, part: int, line: string, dots: string, fill: string}>, sing: string, seconds: float}
     */
    public static function hero(): array
    {
        $course = self::course();
        $segments = [];
        $sing = [];
        $at = 0.0;
        foreach (self::HERO as $i => [$kind, $id, $index, $lyric]) {
            $contour = self::contour(self::exercises($course, $id)[$index] ?? [], $lyric);
            $x0 = 20 + $i * 130;
            $segments[] = ['kind' => $kind, 'part' => $i + 1, ...self::shape($kind, $contour, $x0, $x0 + 130, static fn (float $s): float => 150 - 11 * $s, 40.0)];
            $sing[] = [
                'kind' => $kind,
                'at' => round($at, 3),
                'len' => round($contour['seconds'], 3),
                'x0' => round($x0 / 560, 4),
                'x1' => round(($x0 + 130) / 560, 4),
                'bend' => array_map(static fn (array $p): array => [round($p[0], 2), round($p[1], 2)], self::sample($contour, max(2, (int) ceil($contour['seconds'] * 10)))),
            ];
            $at += $contour['seconds'];
        }

        return ['segments' => $segments, 'sing' => (string) json_encode(['base' => self::BASE, 'sounds' => $sing]), 'seconds' => round($at, 3)];
    }

    /**
     * Each unit of the outline (in the page's language) with its part colour, its labels, its line from `learn` ("The
     * lip trill: a roller coaster..." gives "A roller coaster..."), its shape in a 280 by 80 box, and its lessons, each
     * with the address of the page it's on (null if none).
     *
     * @param  array{units: list<array{title: string, lessons: list<string>}>, lessons: int}  $outline
     * @param  array<array-key, mixed>  $learn
     * @return list<array{n: int, kind: string, part: int, unit: string, stage: string, title: string, learn: string, line: string, dots: string, fill: string, lessons: list<array{n: int, name: string, url: string|null, start: bool}>}>
     */
    public static function units(array $outline, array $learn): array
    {
        $course = self::course();
        $ids = self::lessonIds($course);
        $units = [];
        foreach ($outline['units'] as $u => $unit) {
            [$kind, $id, $which] = self::UNITS[$u % 4];
            $contour = self::join(array_map(static fn (int $i): array => self::contour(self::exercises($course, $id)[$i] ?? [], null, 0.0), $which));
            [$low, $high] = self::range($contour);
            // A tune hummed or opened on "ah" keeps its line gentler, so the buzz and the widening read along it.
            $tall = in_array($kind, ['hum', 'ah'], true) ? 28.0 : 48.0;
            $y = static fn (float $s): float => $high > $low ? 40 + $tall / 2 - ($s - $low) / ($high - $low) * $tall : 40.0;
            $line = is_string($learn[$u] ?? null) ? self::afterColon($learn[$u]) : '';
            $lessons = [];
            foreach ($unit['lessons'] as $i => $name) {
                $slug = isset($ids[$u][$i]) ? Lessons::slugFor(self::COURSE, $ids[$u][$i]) : null;
                $lessons[] = ['n' => $i + 1, 'name' => $name, 'url' => $slug !== null ? '/courses/'.self::COURSE.'/'.$slug : null, 'start' => $u === 0 && $i === 0];
            }
            $units[] = [
                'n' => $u + 1,
                'kind' => $kind,
                'part' => $u % 4 + 1,
                'unit' => (string) __('pages.site.course_unit', ['n' => $u + 1]),
                'stage' => (string) __('pages.site.course_stage', ['n' => $u + 1]),
                'title' => $unit['title'],
                'learn' => $line,
                ...self::shape($kind, $contour, 14, 266, $y, 22.0),
                'lessons' => $lessons,
            ];
        }

        return $units;
    }

    /**
     * The "Did you know?" facts: the page's `history` sentences, each with its big word, part colour, language and the
     * key of its source.
     *
     * @param  array<array-key, mixed>  $body
     * @param  array<array-key, mixed>  $words
     * @return list<array{text: string, word: string, part: int, lang: string|null, key: string}>
     */
    public static function facts(array $body, array $words): array
    {
        $facts = [];
        foreach (array_values($body) as $i => $text) {
            if (! is_string($text) || ! isset(self::FACTS[$i])) {
                continue;
            }
            [$id, $part, $lang] = self::FACTS[$i];
            $facts[] = ['text' => $text, 'word' => is_string($words[$i] ?? null) ? $words[$i] : '', 'part' => $part, 'lang' => $lang, 'key' => $id];
        }

        return $facts;
    }

    /**
     * The facts' sources, from the lessons that tell them (each lesson's own checked fact), keyed by lesson id: [label,
     * url]. A lesson without a sourced fact is left out.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function sources(): array
    {
        $facts = [];
        foreach (Lessons::pages(self::COURSE) as $page) {
            foreach ((array) ($page['lessons'] ?? []) as $lesson) {
                $fact = is_array($lesson) ? ($lesson['fact'] ?? null) : null;
                if (is_array($fact) && is_string($fact['source'] ?? null) && is_string($fact['url'] ?? null)) {
                    $facts[(string) $lesson['id']] = [$fact['source'], $fact['url']];
                }
            }
        }
        $sources = [];
        foreach (self::FACTS as [$id]) {
            if (isset($facts[$id])) {
                $sources[$id] = $facts[$id];
            }
        }

        return $sources;
    }

    /**
     * The sample take in a 520 by 200 box: the target ribbon, the sung line (broken where the sound stopped), the notes on
     * and off pitch as circles, the stop's two marks and where its word sits (in percent of the box), and the count.
     *
     * @return array{target: string, sung: string, on: string, off: string, gap: string, stopX: float, stopY: float, hit: int, all: int}
     */
    public static function take(): array
    {
        [$id, $count, $offs, $stop] = self::TAKE;
        $contour = self::contour(self::exercises(self::course(), $id)[0] ?? [], null);
        [$low, $high] = self::range($contour);
        $y = static fn (float $s): float => $high > $low ? 168 - ($s - $low) / ($high - $low) * 128 : 104.0;
        $x = static fn (float $f): float => 20 + $f * 480;
        $seconds = max($contour['seconds'], 0.001);

        // The notes, evenly through the take; one off pitch sits a little under the ribbon.
        $on = [];
        $off = [];
        $times = [];
        for ($i = 0; $i < $count; $i++) {
            $f = ($i + 0.5) / $count;
            $times[] = $f;
            $point = [$x($f), $y(self::at($contour, $f * $seconds))];
            if (in_array($i, $offs, true)) {
                $off[] = [$point[0], $point[1] + 17];
            } else {
                $on[] = $point;
            }
        }

        // The sung line: the ribbon, a hair off it, stopping between the stop's two notes.
        $g0 = $times[$stop] + 0.25 / $count;
        $g1 = $times[$stop + 1] - 0.25 / $count;
        $sung = static fn (float $a, float $b): string => self::path(array_map(static fn (int $k): array => [$x($a + ($b - $a) * $k / 80), $y(self::at($contour, ($a + ($b - $a) * $k / 80) * $seconds)) + 2.5 * sin(($a + ($b - $a) * $k / 80) * 31)], range(0, 80)));
        $top = min($y(self::at($contour, $g0 * $seconds)), $y(self::at($contour, $g1 * $seconds))) - 30;
        $gap = '';
        foreach ([$g0, $g1] as $f) {
            $gap .= 'M'.self::n($x($f)).','.self::n($top + 8).'L'.self::n($x($f)).','.self::n($y(self::at($contour, $f * $seconds)) + 16);
        }

        return [
            'target' => self::path(array_map(static fn (int $k): array => [$x($k / 200), $y(self::at($contour, $k / 200 * $seconds))], range(0, 200))),
            'sung' => $sung(0.0, $g0).$sung($g1, 1.0),
            'on' => self::circles($on, 5.0),
            'off' => self::circles($off, 5.5),
            'gap' => $gap,
            'stopX' => round(($x($g0) + $x($g1)) / 2 / 520 * 100, 2),
            'stopY' => round($top / 200 * 100, 2),
            'hit' => $count - count($offs),
            'all' => $count,
        ];
    }

    /**
     * Amazing Grace's first line (12 beats), its four parts soprano to bass, each a smooth line in a 430 by 56 box around
     * its own middle, 3 high a semitone, from the template the beginner course sings.
     *
     * @return list<string>
     */
    public static function grace(): array
    {
        $song = self::json(rtrim((string) config('app.web_path'), '/').'/templates/amazing-grace.json');
        $span = 12.0;
        $lines = [];
        foreach (['soprano', 'alto', 'tenor', 'bass'] as $key) {
            $notes = [];
            foreach ((array) ($song['parts'] ?? []) as $part) {
                if (is_array($part) && ($part['key'] ?? null) === $key) {
                    foreach ((array) ($part['guide']['notes'] ?? []) as $note) {
                        if (is_array($note) && (float) ($note['beat'] ?? 0) < $span) {
                            $notes[] = ['beat' => (float) $note['beat'], 'length' => (float) ($note['length'] ?? 1), 'step' => (float) ($note['pitch'] ?? 60)];
                        }
                    }
                }
            }
            if ($notes === []) {
                $lines[] = '';

                continue;
            }
            $contour = self::notesContour($notes, 1.0, null, 0.0);
            $mid = array_sum(array_column($notes, 'step')) / count($notes);
            $from = $notes[0]['beat'];
            $lines[] = self::path(array_map(static fn (array $p): array => [($from + $p[0]) / $span * 430, 28 - ($p[1] - $mid) * 3], self::sample($contour, 160)));
        }

        return $lines;
    }

    /**
     * Every lesson page, by unit: its number, part colour, the unit's name and its pages (name, title, address).
     *
     * @return list<array{n: int, part: int, unit: string, pages: list<array{name: string, title: string, url: string}>}>
     */
    public static function reads(): array
    {
        $units = [];
        foreach (Lessons::pages(self::COURSE) as $page) {
            if (($page['kind'] ?? '') !== 'lesson') {
                continue;
            }
            $n = (int) ($page['unitNumber'] ?? 1);
            $units[$n] ??= ['n' => $n, 'part' => ($n - 1) % 4 + 1, 'unit' => (string) ($page['unit'] ?? ''), 'pages' => []];
            $units[$n]['pages'][] = ['name' => (string) $page['name'], 'title' => (string) $page['title'], 'url' => (string) $page['url']];
        }

        return array_values($units);
    }

    /**
     * A shape's paths for a contour between x0 and x1, its pitch placed by `$y`: the trill a faint line with its bubbles,
     * the siren a line, the hum a buzzing line, the "ah" a ribbon widening to `$wide` either side.
     *
     * @param  array{seconds: float, points: list<array{0: float, 1: float}>}  $contour
     * @param  callable(float): float  $y
     * @return array{line: string, dots: string, fill: string}
     */
    private static function shape(string $kind, array $contour, float $x0, float $x1, callable $y, float $wide): array
    {
        $at = static fn (array $p): array => [$x0 + ($x1 - $x0) * $p[0] / max($contour['seconds'], 0.001), $y($p[1])];
        $width = $x1 - $x0;

        return match ($kind) {
            'trill' => [
                'line' => self::path(array_map($at, self::sample($contour, 120))),
                'dots' => self::circles(array_map($at, self::sample($contour, (int) round($width / 12))), $wide > 30 ? 5.5 : 4.5),
                'fill' => '',
            ],
            'hum' => [
                'line' => self::path(array_map(static function (array $p) use ($at, $contour, $width, $wide): array {
                    [$px, $py] = $at($p);

                    // The buzz: a fine zigzag, one every 14 or so across.
                    return [$px, $py + $wide / 6.5 * sin(2 * M_PI * $p[0] / max($contour['seconds'], 0.001) * $width / 14)];
                }, self::sample($contour, (int) round($width * 1.6)))),
                'dots' => '',
                'fill' => '',
            ],
            'ah' => (static function () use ($at, $contour, $wide): array {
                $samples = self::sample($contour, 60);
                $seconds = max($contour['seconds'], 0.001);
                $h = static fn (float $t): float => 1.5 + ($wide - 1.5) * ($t / $seconds) ** 1.6;
                $top = array_map(static fn (array $p): array => [$at($p)[0], $at($p)[1] - $h($p[0])], $samples);
                $bottom = array_reverse(array_map(static fn (array $p): array => [$at($p)[0], $at($p)[1] + $h($p[0])], $samples));

                return ['line' => '', 'dots' => '', 'fill' => self::path([...$top, ...$bottom]).'Z'];
            })(),
            default => ['line' => self::path(array_map($at, self::sample($contour, 120))), 'dots' => '', 'fill' => ''],
        };
    }

    /**
     * An exercise's pitch over time: a ribbon's own points (seconds, semitones), or a tune's notes (those sung on
     * `$lyric` if given) as held steps with a glide between (`$glide` seconds, or 0 for a third of each note, to draw a
     * tune as a flowing line), in seconds at its tempo.
     *
     * @param  array<array-key, mixed>  $exercise
     * @return array{seconds: float, points: list<array{0: float, 1: float}>}
     */
    private static function contour(array $exercise, ?string $lyric, float $glide = 0.08): array
    {
        $target = $exercise['target'][0] ?? null;
        if (is_array($target) && is_array($target['points'] ?? null)) {
            $points = [];
            foreach ($target['points'] as $point) {
                if (is_array($point) && count($point) >= 2) {
                    $points[] = [(float) $point[0], (float) $point[1]];
                }
            }

            return ['seconds' => (float) ($target['seconds'] ?? ($points === [] ? 0 : end($points)[0])), 'points' => $points];
        }

        $notes = array_values(array_filter((array) ($exercise['notes'] ?? []), static fn (mixed $n): bool => is_array($n) && ($lyric === null || ($n['lyric'] ?? null) === $lyric)));
        $notes = array_map(static fn (array $n): array => ['beat' => (float) ($n['beat'] ?? 0), 'length' => (float) ($n['length'] ?? 1), 'step' => (float) ($n['step'] ?? 0)], $notes);

        return self::notesContour($notes, 60 / max(1, (float) ($exercise['tempo'] ?? 60)), null, $glide);
    }

    /**
     * Notes as a contour: each held from a little after its start to a little before its end (by `$glide` seconds, or a
     * third of the note when 0), gliding between.
     *
     * @param  list<array{beat: float, length: float, step: float}>  $notes
     * @return array{seconds: float, points: list<array{0: float, 1: float}>}
     */
    private static function notesContour(array $notes, float $beat, ?float $from, float $glide): array
    {
        if ($notes === []) {
            return ['seconds' => 0.0, 'points' => []];
        }
        $from ??= $notes[0]['beat'];
        $points = [];
        $end = 0.0;
        foreach ($notes as $note) {
            $start = ($note['beat'] - $from) * $beat;
            $end = ($note['beat'] + $note['length'] - $from) * $beat;
            $g = $glide > 0 ? min($glide, ($end - $start) / 4) : ($end - $start) / 3;
            $points[] = [$start + ($points === [] ? 0 : $g), $note['step']];
            $points[] = [$end - $g, $note['step']];
        }
        // The last note holds to its very end.
        $last = array_pop($points);
        $points[] = [$end, $last[1]];

        return ['seconds' => $end, 'points' => $points];
    }

    /**
     * Contours one after another.
     *
     * @param  list<array{seconds: float, points: list<array{0: float, 1: float}>}>  $contours
     * @return array{seconds: float, points: list<array{0: float, 1: float}>}
     */
    private static function join(array $contours): array
    {
        $points = [];
        $at = 0.0;
        foreach ($contours as $contour) {
            foreach ($contour['points'] as [$t, $s]) {
                $points[] = [$at + $t, $s];
            }
            $at += $contour['seconds'];
        }

        return ['seconds' => $at, 'points' => $points];
    }

    /**
     * The pitch at `$t` seconds, easing between the points as the app's ribbon does (smoothstep, js/synth.js bendAt).
     *
     * @param  array{seconds: float, points: list<array{0: float, 1: float}>}  $contour
     */
    private static function at(array $contour, float $t): float
    {
        $points = $contour['points'];
        if ($points === []) {
            return 0.0;
        }
        if ($t <= $points[0][0]) {
            return $points[0][1];
        }
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            [$t0, $s0] = $points[$i - 1];
            [$t1, $s1] = $points[$i];
            if ($t < $t1) {
                $x = $t1 > $t0 ? ($t - $t0) / ($t1 - $t0) : 1.0;

                return $s0 + ($s1 - $s0) * $x * $x * (3 - 2 * $x);
            }
        }

        return $points[count($points) - 1][1];
    }

    /**
     * `$n` + 1 points [seconds, semitones] evenly through the contour.
     *
     * @param  array{seconds: float, points: list<array{0: float, 1: float}>}  $contour
     * @return list<array{0: float, 1: float}>
     */
    private static function sample(array $contour, int $n): array
    {
        $n = max(1, $n);

        return array_map(static fn (int $k): array => [$contour['seconds'] * $k / $n, self::at($contour, $contour['seconds'] * $k / $n)], range(0, $n));
    }

    /**
     * The lowest and highest pitch a contour reaches.
     *
     * @param  array{seconds: float, points: list<array{0: float, 1: float}>}  $contour
     * @return array{0: float, 1: float}
     */
    private static function range(array $contour): array
    {
        $steps = array_column($contour['points'], 1);

        return $steps === [] ? [0.0, 0.0] : [(float) min($steps), (float) max($steps)];
    }

    /** "The lip trill: a roller coaster on one bubble" gives "A roller coaster on one bubble." */
    private static function afterColon(string $text): string
    {
        $parts = explode(': ', $text, 2);
        $line = trim($parts[1] ?? $parts[0]);

        return $line === '' ? '' : mb_strtoupper(mb_substr($line, 0, 1)).mb_substr($line, 1).(preg_match('/[.!?]$/u', $line) === 1 ? '' : '.');
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private static function path(array $points): string
    {
        return $points === [] ? '' : 'M'.implode('L', array_map(static fn (array $p): string => self::n($p[0]).','.self::n($p[1]), $points));
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private static function circles(array $points, float $r): string
    {
        return implode('', array_map(static fn (array $p): string => 'M'.self::n($p[0] - $r).','.self::n($p[1]).'a'.$r.','.$r.' 0 1,0 '.(2 * $r).',0a'.$r.','.$r.' 0 1,0 '.(-2 * $r).',0', $points));
    }

    private static function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }

    /**
     * A lesson's exercises (its sung steps' exercises), in order.
     *
     * @param  array<array-key, mixed>  $course
     * @return list<array<array-key, mixed>>
     */
    private static function exercises(array $course, string $id): array
    {
        foreach ((array) ($course['units'] ?? []) as $unit) {
            foreach ((array) (is_array($unit) ? ($unit['lessons'] ?? []) : []) as $lesson) {
                if (is_array($lesson) && ($lesson['id'] ?? null) === $id) {
                    return array_values(array_filter(array_map(static fn (mixed $step): mixed => is_array($step) ? ($step['exercise'] ?? null) : null, (array) ($lesson['steps'] ?? [])), 'is_array'));
                }
            }
        }

        return [];
    }

    /**
     * Each unit's lesson ids, in order.
     *
     * @param  array<array-key, mixed>  $course
     * @return list<list<string>>
     */
    private static function lessonIds(array $course): array
    {
        $ids = [];
        foreach ((array) ($course['units'] ?? []) as $unit) {
            $ids[] = array_values(array_map(static fn (mixed $l): string => is_array($l) ? (string) ($l['id'] ?? '') : '', (array) (is_array($unit) ? ($unit['lessons'] ?? []) : [])));
        }

        return $ids;
    }

    /** @return array<array-key, mixed> */
    private static function course(): array
    {
        return self::json(rtrim((string) config('app.web_path'), '/').'/course/plus/humming/course.json');
    }

    /** @return array<array-key, mixed> */
    private static function json(string $path): array
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : [];
    }
}
