<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Pages\Lessons;

/**
 * The Live looping course page's pieces (site/courses/live-looping, Hillel's design of 2026-11-18): a song built from
 * tracks on repeat, one colour per track.
 *
 * - rings(): the hero's loop, four rings round one 8-bar cycle, from the course's own notes of Are You Sleeping (Frère
 *   Jacques), unit 3: the "ding, dang, dong" bass and the held "oo" pad (lesson ll-3-frere), the tune, and the tune again
 *   two bars late (ll-3-round's two entries). Pressed, each track joins in turn, as a looper builds one: the bass from the
 *   first beat, the pad a bar later, the tune after another bar, the round two bars after the tune; they end together on
 *   the round's last "dong". Each ring holds the arcs one 8-bar cycle shows (where each note sits round the circle) and
 *   the notes it plays (one loop of them, repeated from `from` to `to`).
 * - units(): the course's units as tracks, every lesson a block linked to the page that teaches it (a lesson that shares a
 *   page, like Your first loop, links to that page).
 * - lessonPages(): every lesson page, by unit, for "Every lesson, to read".
 *
 * A course file that's missing gives empty rings and unlinked blocks, never an error.
 */
final class LiveLoopingPage
{
    /** The beats one turn of the rings shows: the tune's 8 bars. */
    public const SPAN = 32;

    /** The beats the pressed loop plays, joins included, ending on the round's last note. */
    public const LENGTH = 48;

    /** Do, the course's home note, as MIDI (C4): every step is semitones from it. */
    public const DOH = 60;

    /** The rings, outside in: the lesson and play step each track's notes come from, where it joins and ends, and its loop. */
    private const TRACKS = [
        'bass' => ['lesson' => 'll-3-frere', 'step' => 1, 'from' => 0, 'to' => 48, 'loop' => 4],
        'pad' => ['lesson' => 'll-3-frere', 'step' => 2, 'from' => 4, 'to' => 48, 'loop' => 4],
        'tune' => ['lesson' => 'll-3-round', 'step' => 2, 'from' => 8, 'to' => 40, 'loop' => 32],
        'round' => ['lesson' => 'll-3-round', 'step' => 2, 'from' => 16, 'to' => 48, 'loop' => 32],
    ];

    /** Each ring's radius in the 400-wide drawing. */
    private const RADII = ['bass' => 170, 'pad' => 135, 'tune' => 100, 'round' => 65];

    /** The sample take's missed notes, by place (21 of 24 on pitch). */
    private const MISSED = [7, 15, 20];

    /** The gap left after each note's arc, in beats, so repeated notes read as two. */
    private const GAP = 0.14;

    /**
     * @return array{tempo: int, span: int, length: int, turn: float, rings: list<array{key: string, v: int, r: int, from: int, to: int, loop: int, notes: list<array{at: float, len: float, midi: int}>, sound: string, arcs: list<array{pos: float, len: float, dash: float}>}>}
     */
    public static function rings(): array
    {
        $course = self::course();
        $tempo = 96;
        $rings = [];

        foreach (self::TRACKS as $key => $track) {
            $exercise = self::exercise($course, $track['lesson'], $track['step']);
            $tempo = (int) ($exercise['tempo'] ?? $tempo);
            $notes = [];
            foreach ((array) ($exercise['notes'] ?? []) as $note) {
                if (is_array($note) && (float) ($note['beat'] ?? 0) < $track['loop']) {
                    $notes[] = ['at' => (float) $note['beat'], 'len' => (float) ($note['length'] ?? 1), 'midi' => self::DOH + (int) ($note['step'] ?? 0)];
                }
            }

            // Where each note sits on one turn of the circle: every repeat of the loop that one turn holds.
            $arcs = [];
            for ($k = 0; $k < intdiv(self::SPAN, $track['loop']); $k++) {
                foreach ($notes as $note) {
                    $pos = fmod($track['from'] + $k * $track['loop'] + $note['at'], self::SPAN);
                    $arcs[] = ['pos' => round($pos, 3), 'len' => $note['len'], 'dash' => round(max($note['len'] - self::GAP, 0.1), 3)];
                }
            }
            usort($arcs, static fn (array $a, array $b): int => $a['pos'] <=> $b['pos']);

            // What it sings, for js/public/live-looping.js: [beat, length, midi] for each note of one loop.
            $sound = (string) json_encode(array_map(static fn (array $n): array => [$n['at'], $n['len'], $n['midi']], $notes));
            $rings[] = ['key' => $key, 'v' => count($rings) + 1, 'r' => self::RADII[$key], 'from' => $track['from'], 'to' => $track['to'], 'loop' => $track['loop'], 'notes' => $notes, 'sound' => $sound, 'arcs' => $arcs];
        }

        // One turn of the rings in seconds, at the song's own speed, for the idle playhead.
        return ['tempo' => $tempo, 'span' => self::SPAN, 'length' => self::LENGTH, 'turn' => round(self::SPAN * 60 / max($tempo, 1), 3), 'rings' => $rings];
    }

    /**
     * The course's units, each lesson with its number ("1.1"), its title and the page that teaches it.
     *
     * @param  array{units: list<array{title: string, lessons: list<string>}>, lessons: int}  $outline
     * @return list<array{title: string, lessons: list<array{number: string, tag: string, title: string, url: string|null, first: bool, last: bool}>}>
     */
    public static function units(string $slug, array $outline): array
    {
        // Every lesson's page in the course's order (a page can teach two lessons): the outline lists them in the same
        // order, so the nth lesson is the nth here. Its title too, for a list that doesn't line up.
        $byOrder = [];
        $byTitle = [];
        foreach (Lessons::pages($slug) as $page) {
            foreach ((array) ($page['lessons'] ?? []) as $lesson) {
                $byOrder[] = (string) $page['url'];
                $byTitle[(string) ($lesson['name'] ?? '')] = (string) $page['url'];
            }
        }
        $aligned = count($byOrder) === (int) $outline['lessons'];

        $units = [];
        $n = 0;
        $last = count($outline['units']) - 1;
        foreach ($outline['units'] as $u => $unit) {
            $lessons = [];
            foreach ($unit['lessons'] as $l => $title) {
                $number = (string) __('site.courses.pages.live-looping.ll.number', ['unit' => $u + 1, 'lesson' => $l + 1]);
                $first = $n === 0;
                $end = $u === $last && $l === count($unit['lessons']) - 1;
                // The first lesson's block says Start here, the last's All tracks, beside its number.
                $tag = $first ? (string) __('pages.site.course_free') : ($end ? (string) __('site.courses.pages.live-looping.ll.all_tracks') : null);
                $lessons[] = [
                    'number' => $number,
                    'tag' => $tag === null ? $number : (string) __('site.courses.pages.live-looping.ll.tagged', ['number' => $number, 'tag' => $tag]),
                    'title' => $title,
                    'url' => ($aligned ? $byOrder[$n] : null) ?? $byTitle[$title] ?? null,
                    'first' => $first,
                    'last' => $end,
                ];
                $n++;
            }
            $units[] = ['title' => $unit['title'], 'lessons' => $lessons];
        }

        return $units;
    }

    /**
     * Every lesson page, by unit: its number, its unit's name and its pages (name, title, address).
     *
     * @return list<array{number: int, unit: string, pages: list<array{name: string, title: string, url: string}>}>
     */
    public static function lessonPages(string $slug): array
    {
        $units = [];
        foreach (Lessons::pages($slug) as $page) {
            $number = (int) ($page['unitNumber'] ?? 0);
            $units[$number] ??= ['number' => $number, 'unit' => (string) ($page['unit'] ?? ''), 'pages' => []];
            $units[$number]['pages'][] = ['name' => (string) $page['name'], 'title' => (string) $page['title'], 'url' => (string) $page['url']];
        }

        return array_values($units);
    }

    /**
     * The sample take's 24 notes, six on each track (its part colour, 1 to 4), three of them missed: a picture of how a
     * take is said, never a singer's score.
     *
     * @return list<array{v: int, miss: bool}>
     */
    public static function sampleDots(): array
    {
        return array_map(static fn (int $i): array => ['v' => intdiv($i, 6) + 1, 'miss' => in_array($i, self::MISSED, true)], range(0, 23));
    }

    /** A list's place as two digits ("01"). */
    public static function place(int $index): string
    {
        return str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
    }

    /**
     * One play step's exercise of a lesson ([] when there's none).
     *
     * @param  array<array-key, mixed>  $course
     * @return array<array-key, mixed>
     */
    private static function exercise(array $course, string $id, int $step): array
    {
        foreach ((array) ($course['units'] ?? []) as $unit) {
            foreach ((array) (is_array($unit) ? ($unit['lessons'] ?? []) : []) as $lesson) {
                if (is_array($lesson) && ($lesson['id'] ?? null) === $id) {
                    $exercise = $lesson['steps'][$step]['exercise'] ?? null;

                    return is_array($exercise) ? $exercise : [];
                }
            }
        }

        return [];
    }

    /** @return array<array-key, mixed> */
    private static function course(): array
    {
        $path = rtrim((string) config('app.web_path'), '/').'/course/plus/live-looping/course.json';
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : [];
    }
}
