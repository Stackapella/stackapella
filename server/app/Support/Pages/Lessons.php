<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Actions\Courses\CourseClips;
use App\Models\CourseClip;
use App\Support\Http\Hosts;
use App\Support\Site\SitePages;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * The courses' public lesson pages (docs/seo-lesson-pages-plan.md): /courses/<course>/<page>, each course's teaching as
 * readable text, from the course files the app reads (web/course/, the pinned content repo), so Google can see it.
 * A fuller course gets a page per lesson, each unit's challenge folded into the unit's last lesson page; the courses
 * whose lessons are too short alone (and the free course) get a page per unit (the courses lead, 2026-11-07). Only a
 * course that's open (catalogue status `live`, SitePages::courseOpen) and has its own course page; the free course
 * always, under /courses/harmony. A lesson is shown as written, never an experiment's arm; nothing is shown of the
 * measures ("Scored on ...") or stars. Titles and descriptions are marketing's (lang/en/lessons.php), else the
 * content's title.
 */
final class Lessons
{
    /** The free course's place under /courses (its own page is /features/course). */
    public const FREE = 'harmony';

    /** Courses whose lessons are too short to stand alone: one page per unit (the courses lead). Sight-singing left this
     *  list on 2026-11-08 once its lessons were rebuilt (content d0def31e8), then Vibrato and Range and registers once
     *  theirs grew (afd0bd985): a course's old unit pages 301 to their first lesson. */
    public const PER_UNIT = ['choir-part'];

    /** The steps a singer does, each with its one line (learn steps teach; their cards are shown apart). `home` (find
     *  your easy note) and `use` (sing a song's lines) are sung too (the courses lead, 2026-11-11: seven beginner lessons
     *  showed no step). */
    private const SUNG = ['play', 'try', 'wave', 'bullseye', 'choose', 'selfcheck', 'breathhold', 'record', 'home', 'use'];

    /** @var array<string, list<array<string, mixed>>>|null */
    private static ?array $built = null;

    /**
     * The courses with lesson pages, in the site's order: course slug => its catalogue facts.
     *
     * @return array<string, array{id: string, name: string, file: string, safety: string|null, up: string, minutes: int|null}>
     */
    public static function courses(): array
    {
        $root = self::root();
        $courses = [self::FREE => ['id' => 'free', 'name' => (string) __('site.lessons.free'), 'file' => $root.'/course.json', 'safety' => null, 'up' => '/features/course', 'minutes' => null]];
        $list = self::json($root.'/plus/courses.json');
        $live = SitePages::courses();

        foreach ((array) ($list['courses'] ?? []) as $entry) {
            if (! is_array($entry) || ! is_string($entry['slug'] ?? null) || ! is_string($entry['file'] ?? null) || str_contains($entry['file'], '..')) {
                continue;
            }
            $slug = $entry['slug'];
            // Open (the catalogue says live) and with its own course page to link up to.
            if (! in_array($slug, $live, true) || ! SitePages::courseOpen($slug) || ! is_file($root.'/plus/'.$entry['file'])) {
                continue;
            }
            $courses[$slug] = [
                'id' => (string) ($entry['id'] ?? $slug),
                'name' => self::words($entry['title'] ?? ''),
                'file' => $root.'/plus/'.$entry['file'],
                'safety' => self::words($entry['safety'] ?? '') ?: null,
                'up' => '/courses/'.$slug,
                'minutes' => is_int($entry['minutes'] ?? null) ? $entry['minutes'] : null,
            ];
        }

        return $courses;
    }

    /**
     * A course's pages in order.
     *
     * @return list<array<string, mixed>>
     */
    public static function pages(string $course): array
    {
        self::$built ??= [];

        // Built once per language a request: the slugs are the English's, the words the page's language.
        return self::$built[app()->getLocale().':'.$course] ??= self::build($course);
    }

    /**
     * The address of a course's first lesson in the app, tagged with where the visitor came from; null for a course
     * without lesson pages.
     */
    public static function firstLesson(string $course, string $ref): ?string
    {
        $id = self::pages($course)[0]['lessons'][0]['id'] ?? null;

        return is_string($id) && $id !== '' ? Hosts::app('/learn/'.$id.'?'.http_build_query(['ref' => $ref])) : null;
    }

    /**
     * One page by its slug, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function page(string $course, string $slug): ?array
    {
        foreach (self::pages($course) as $page) {
            if ($page['slug'] === $slug) {
                return $page;
            }
        }

        return null;
    }

    /** The page slug a lesson or unit id (or a unit's old page) is on, so that address 301s to it; or null. */
    public static function slugFor(string $course, string $id): ?string
    {
        foreach (self::pages($course) as $page) {
            if (in_array($id, [...(array) $page['ids'], ...(array) $page['aliases']], true)) {
                return (string) $page['slug'];
            }
        }

        return null;
    }

    /**
     * Every lesson page's address, for the sitemap.
     *
     * @return list<string>
     */
    public static function paths(): array
    {
        $paths = [];
        foreach (array_keys(self::courses()) as $course) {
            foreach (self::pages($course) as $page) {
                $paths[] = (string) $page['url'];
            }
        }

        return $paths;
    }

    /**
     * Which lesson pages a guide or a reference page points to (marketing's map, 2026-11-08), by the page's path: the
     * guides and the reference indexes by name, each interval, chord and voice type by its group.
     */
    private const ACROSS = [
        '/guides/a-round-for-beginners' => ['counterpoint/be-a-round', 'counterpoint/the-second-voice', 'harmony/your-first-harmony'],
        '/guides/arranging-a-cappella' => ['counterpoint/a-slow-line-under-a-tune', 'improvising-harmony/sing-the-chord', 'counterpoint/a-pattern-that-repeats'],
        '/guides/breathing-basics' => ['breath-and-stamina/keep-the-ribs-wide', 'breath-and-stamina/plan-your-breaths', 'breath-and-stamina/the-quick-breath'],
        '/guides/ear-training' => ['ear-training/song-starts', 'ear-training/bright-or-dark', 'ear-training/wheres-home'],
        '/guides/find-your-part' => ['harmony/find-your-voice', 'range-and-registers/easy-range-and-chest-voice', 'harmony/low-and-inner-voices'],
        '/guides/find-your-voice-type' => ['range-and-registers/easy-range-and-chest-voice', 'range-and-registers/mapping-your-break', 'harmony/find-your-voice'],
        '/guides/hold-your-part' => ['perfect-your-pitch/dont-get-pulled', 'improvising-harmony/hold-then-step-off', 'counterpoint/be-a-round'],
        '/guides/how-to-sing-harmony' => ['harmony/your-first-harmony', 'improvising-harmony/a-part-above', 'improvising-harmony/a-part-below'],
        '/guides/practise-a-choir-part' => ['learn-your-choir-part/your-line-in-the-sound', 'learn-your-choir-part/fix-the-hard-bars', 'sight-singing/a-part-of-its-own'],
        '/guides/practise-intonation-with-a-drone' => ['perfect-your-pitch/hear-the-ring', 'perfect-your-pitch/the-octave-and-the-fifth', 'counterpoint/be-the-drone'],
        '/guides/satb-explained' => ['harmony/low-and-inner-voices', 'range-and-registers/easy-range-and-chest-voice', 'learn-your-choir-part/your-line-in-the-sound'],
        '/guides/singing-in-tune' => ['perfect-your-pitch/bullseye', 'perfect-your-pitch/scoop-or-clean', 'perfect-your-pitch/no-sag'],
        '/guides/what-is-harmony' => ['harmony/your-first-harmony', 'ear-training/three-or-five', 'improvising-harmony/harmony-or-the-tune'],
        // The guides that had none (marketing2, 2026-11-13, the coordinator's item 6), each to lessons that teach its skill;
        // sight-reading and vibrato already link theirs in their text, and the tag guide's swipe, post and tag lessons too.
        '/guides/choir-voicings' => ['harmony/low-and-inner-voices', 'learn-your-choir-part/your-line-in-the-sound', 'harmony/find-your-voice'],
        '/guides/what-is-a-descant' => ['counterpoint/float-above-it', 'improvising-harmony/a-part-above', 'perfect-your-pitch/dont-get-pulled'],
        '/guides/find-your-starting-note' => ['perfect-your-pitch/your-note-cold', 'perfect-your-pitch/from-your-anchor', 'sight-singing/do-mi-so'],
        '/guides/vocal-percussion' => ['a-cappella-bass/keep-the-time', 'a-cappella-bass/steady-or-rushing', 'a-cappella-bass/under-the-group'],
        '/guides/hanukkah-songs-for-choir' => ['learn-your-choir-part/your-line-in-the-sound', 'learn-your-choir-part/fix-the-hard-bars', 'learn-your-choir-part/ready-for-rehearsal'],
        '/guides/how-to-blend-in-a-choir' => ['perfect-your-pitch/hear-the-ring', 'perfect-your-pitch/the-chords-change-you-dont', 'learn-your-choir-part/your-line-in-the-sound'],
        '/guides/how-to-run-a-sectional' => ['learn-your-choir-part/fix-the-hard-bars', 'learn-your-choir-part/guide-on-guide-off', 'learn-your-choir-part/ready-for-rehearsal'],
        '/guides/how-to-start-an-a-cappella-group' => ['a-cappella-bass/under-the-group', 'harmony/find-your-voice', 'counterpoint/a-slow-line-under-a-tune'],
        '/guides/barbershop-tags' => ['barbershop/a-chord-that-rings', 'barbershop/the-barbershop-seventh', 'barbershop/the-lead'],
        '/intervals' => ['ear-training/song-starts', 'ear-training/up-or-down-step-or-leap', 'ear-training/leaps-down'],
        '/chords' => ['ear-training/bright-or-dark', 'improvising-harmony/sing-the-chord', 'ear-training/the-chords-root'],
        '/voice-types' => ['range-and-registers/easy-range-and-chest-voice', 'range-and-registers/mapping-your-break'],
    ];

    /** An interval's, chord's or voice type's own lessons, by its group; the rest of its family take the family's default. */
    private const ACROSS_ITEMS = [
        'intervals' => [
            'minor-second' => ['ear-training/small-and-smaller'], 'major-second' => ['ear-training/small-and-smaller'],
            'minor-third' => ['ear-training/big-and-small-threes', 'improvising-harmony/major-and-minor-thirds'], 'major-third' => ['ear-training/big-and-small-threes', 'improvising-harmony/major-and-minor-thirds'],
            'perfect-fourth' => ['ear-training/four-or-five', 'perfect-your-pitch/the-octave-and-the-fifth'], 'perfect-fifth' => ['ear-training/four-or-five', 'perfect-your-pitch/the-octave-and-the-fifth'],
            'minor-sixth' => ['ear-training/six-and-eight'], 'major-sixth' => ['ear-training/six-and-eight'], 'octave' => ['ear-training/six-and-eight'],
            '*' => ['ear-training/song-starts'],
        ],
        'chords' => [
            'major' => ['ear-training/bright-or-dark', 'ear-training/sing-the-third'], 'minor' => ['ear-training/bright-or-dark', 'ear-training/sing-the-third'],
            '*' => ['ear-training/the-chords-root', 'improvising-harmony/sing-the-chord'],
        ],
        'voice-types' => [
            'bass' => ['range-and-registers/easy-range-and-chest-voice', 'range-and-registers/mapping-your-break', 'harmony/low-and-inner-voices'],
            'baritone' => ['range-and-registers/easy-range-and-chest-voice', 'range-and-registers/mapping-your-break', 'harmony/low-and-inner-voices'],
            'tenor' => ['range-and-registers/easy-range-and-chest-voice', 'range-and-registers/mapping-your-break', 'harmony/low-and-inner-voices'],
            '*' => ['range-and-registers/easy-range-and-chest-voice', 'range-and-registers/mapping-your-break'],
        ],
    ];

    /**
     * The lesson pages a guide or a reference page points to, those that exist (an open course's), at most three:
     * each one's name, marketing's title and its address.
     *
     * @return list<array{name: string, title: string, url: string}>
     */
    public static function across(string $path): array
    {
        $path = '/'.trim($path, '/');
        $targets = self::ACROSS[$path] ?? null;
        if ($targets === null && preg_match('#^/(intervals|chords|voice-types)/([a-z0-9-]+)$#', $path, $m) === 1) {
            $targets = self::ACROSS_ITEMS[$m[1]][$m[2]] ?? self::ACROSS_ITEMS[$m[1]]['*'];
        }
        $found = [];
        foreach ($targets ?? [] as $target) {
            [$course, $slug] = explode('/', $target, 2);
            // A unit slug of a course that moved to a page per lesson leads to the unit's first lesson page.
            $page = isset(self::courses()[$course]) ? (self::page($course, $slug) ?? self::page($course, (string) self::slugFor($course, $slug))) : null;
            if ($page !== null) {
                $found[] = ['name' => (string) $page['name'], 'title' => (string) $page['title'], 'url' => (string) $page['url']];
            }
        }

        return array_slice($found, 0, 3);
    }

    /**
     * The real-voice clips a page's lessons play (web/course/plus/clips.json, recorded by beta singers and published),
     * by lesson id: each one's address, length and its singer's credit, as the app's courses.clips.list gives them. A
     * clip with no recording yet (a description only) is never shown.
     *
     * @param  list<string>  $lessonIds
     * @return array<string, list<array{slot: string, url: string, durationSeconds: float, credit: string|null, creditHandle: string|null, creditStyle: string|null}>>
     */
    public static function clips(array $lessonIds): array
    {
        $byLesson = [];
        $published = [];
        $clips = CourseClip::query()->whereNotNull('media_key')->whereNull('retired_at')->orderBy('slot')->get(['slot', 'course_id', 'brief']);

        foreach ($clips as $clip) {
            $used = array_values(array_intersect(array_map('strval', (array) ($clip->brief['used'] ?? [])), $lessonIds));
            if ($used === []) {
                continue;
            }
            $published[$clip->course_id] ??= collect(app(CourseClips::class)->published($clip->course_id))->keyBy('slot')->all();
            $shown = $published[$clip->course_id][$clip->slot] ?? null;
            if ($shown === null) {
                continue;
            }
            foreach ($used as $id) {
                $byLesson[$id][] = $shown;
            }
        }

        return $byLesson;
    }

    /** A title as an address: lower case, ASCII, words joined by hyphens, apostrophes dropped. */
    public static function slug(string $title): string
    {
        return Str::slug(str_replace(["'", '’'], '', $title));
    }

    /** Forgets the pages built this request (tests that change the content). */
    public static function forget(): void
    {
        self::$built = null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function build(string $course): array
    {
        $facts = self::courses()[$course] ?? null;
        $file = $facts === null ? null : self::json($facts['file']);
        if ($facts === null || ! is_array($file['units'] ?? null)) {
            return [];
        }

        $perUnit = $course === self::FREE || in_array($facts['id'], self::PER_UNIT, true);
        $pages = [];
        $taken = [];

        foreach (array_values(array_filter($file['units'], 'is_array')) as $u => $unit) {
            $lessons = array_values(array_filter((array) ($unit['lessons'] ?? []), 'is_array'));
            $groups = $perUnit ? [$lessons] : self::foldChallenges($lessons);

            foreach ($groups as $g => $group) {
                if ($group === []) {
                    continue;
                }
                // The address is the English title's in every language; the page says it in its own.
                $slug = self::slug($perUnit ? self::en($unit['title'] ?? '') : self::en($group[0]['title'] ?? ''));
                $title = $perUnit ? self::words($unit['title'] ?? '') : self::words($group[0]['title'] ?? '');
                if ($slug === '' || isset($taken[$slug])) {
                    $slug = trim($slug.'-'.($u + 1), '-');
                }
                $taken[$slug] = true;
                $words = (array) Lang::get('lessons.'.$course.'.'.$slug);

                $pages[] = [
                    'course' => $course,
                    'courseName' => $facts['name'],
                    'up' => $facts['up'],
                    'slug' => $slug,
                    'url' => '/courses/'.$course.'/'.$slug,
                    'kind' => $perUnit ? 'unit' : 'lesson',
                    'name' => $title,
                    'unit' => self::words($unit['title'] ?? ''),
                    'unitNumber' => $u + 1,
                    'title' => is_string($words['title'] ?? null) ? $words['title'] : $title,
                    'description' => is_string($words['description'] ?? null) ? $words['description'] : null,
                    'ids' => array_values(array_filter([...($perUnit && is_string($unit['id'] ?? null) ? [$unit['id']] : []), ...array_map(static fn (array $l): mixed => $l['id'] ?? null, $group)], 'is_string')),
                    // A unit's own addresses (its id, and its title's slug from when it had a page of its own) lead to
                    // its first lesson page, so a course that moved from a page per unit to a page per lesson keeps its links.
                    'aliases' => ! $perUnit && $g === 0 ? array_values(array_filter([is_string($unit['id'] ?? null) ? $unit['id'] : null, self::slug(self::en($unit['title'] ?? ''))])) : [],
                    'lessons' => array_map(self::lesson(...), $group),
                    // Into the app at this page's own lesson, tagged so Growth counts who came from a lesson page. Anyone may
                    // sing it once from here (Hillel, 2026-11-08): the app knows by ref=lesson-page and our site as the
                    // referrer, so this stays a plain link, and the site's Referrer-Policy strict-origin-when-cross-origin.
                    'start' => Hosts::app('/learn/'.(string) ($group[0]['id'] ?? '').'?ref=lesson-page'),
                    'safety' => $facts['safety'],
                    'belting' => $facts['id'] === 'belting',
                    'minutes' => array_sum(array_map(static fn (array $l): int => is_int($l['minutes'] ?? null) ? $l['minutes'] : 0, $group)) ?: null,
                ];
            }
        }

        // Each page's neighbours in the course.
        foreach ($pages as $i => $page) {
            $pages[$i]['previous'] = $i > 0 ? ['name' => $pages[$i - 1]['name'], 'url' => $pages[$i - 1]['url']] : null;
            $pages[$i]['next'] = $i < count($pages) - 1 ? ['name' => $pages[$i + 1]['name'], 'url' => $pages[$i + 1]['url']] : null;
        }

        return $pages;
    }

    /**
     * A unit's lessons, one page each, with its challenge (too short alone) folded into the last lesson before it.
     *
     * @param  list<array<string, mixed>>  $lessons
     * @return list<list<array<string, mixed>>>
     */
    private static function foldChallenges(array $lessons): array
    {
        $groups = [];
        foreach ($lessons as $lesson) {
            if (($lesson['challenge'] ?? false) && $groups !== []) {
                $groups[count($groups) - 1][] = $lesson;
            } else {
                $groups[] = [$lesson];
            }
        }

        return $groups;
    }

    /**
     * A lesson as the page shows it: what it teaches (each learn step's headline and cards), what you sing (each sung
     * step's one line), and its "Did you know?" with its source. Never the measures, never a note's name.
     *
     * @param  array<string, mixed>  $lesson
     * @return array<string, mixed>
     */
    private static function lesson(array $lesson): array
    {
        $teach = [];
        $sing = [];
        $choose = null;
        foreach (array_filter((array) ($lesson['steps'] ?? []), 'is_array') as $step) {
            $kind = (string) ($step['kind'] ?? '');
            if ($kind === 'learn') {
                $cards = [];
                foreach (array_filter((array) ($step['steps'] ?? []), 'is_array') as $card) {
                    $cards[] = ['title' => self::words($card['title'] ?? ''), 'detail' => self::words($card['detail'] ?? '')];
                }
                $teach[] = ['headline' => self::words($step['headline'] ?? ''), 'cards' => $cards];
            } elseif (in_array($kind, self::SUNG, true) && self::words($step['text'] ?? '') !== '') {
                $sing[] = self::words($step['text']);
            }
            if ($kind === 'choose') {
                $choose ??= self::round($step);
            }
        }
        $fact = is_array($lesson['fact'] ?? null) ? $lesson['fact'] : null;
        $url = is_string($fact['url'] ?? null) && str_starts_with($fact['url'], 'https://') ? $fact['url'] : null;

        return [
            'id' => (string) ($lesson['id'] ?? ''),
            'name' => self::words($lesson['title'] ?? ''),
            'challenge' => (bool) ($lesson['challenge'] ?? false),
            'teach' => $teach,
            'sing' => $sing,
            // The first listen-and-choose round it has, for a page's "Try one round" (lesson_try).
            'choose' => $choose,
            'fact' => $fact === null || self::words($fact['text'] ?? '') === '' ? null : ['text' => self::words($fact['text']), 'source' => self::words($fact['source'] ?? '') ?: null, 'url' => $url],
        ];
    }

    /**
     * A choose step's first round a page can play on its own (docs/lesson-page-try-it-plan.md): every clip made of
     * notes (never a recorded clip, which needs the app), with its prompt, its options (or none: then the clips are the
     * choices), its answer and why. Only the notes' own fields go to the page.
     *
     * @param  array<array-key, mixed>  $step
     * @return array{text: string, tempo: int, prompt: string, clips: list<array<string, mixed>>, options: list<string>|null, answer: int, why: string}|null
     */
    private static function round(array $step): ?array
    {
        $exercise = is_array($step['exercise'] ?? null) ? $step['exercise'] : [];
        foreach (array_filter((array) ($exercise['rounds'] ?? []), 'is_array') as $round) {
            $clips = array_values(array_filter((array) ($round['clips'] ?? []), 'is_array'));
            $playable = $clips !== [] && array_filter($clips, static fn (array $clip): bool => ! is_array($clip['notes'] ?? null) || $clip['notes'] === [] || isset($clip['recorded']) || isset($clip['clip'])) === [];
            $options = isset($round['options']) ? array_values(array_map(self::words(...), (array) $round['options'])) : null;
            $answer = $round['answer'] ?? null;
            $choices = $options === null ? count($clips) : count($options);
            if (! $playable || $choices < 2 || ! is_int($answer) || $answer < 0 || $answer >= $choices || ($options !== null && in_array('', $options, true))) {
                continue;
            }

            return [
                'text' => self::words($step['text'] ?? ''),
                'tempo' => is_int($exercise['tempo'] ?? null) ? $exercise['tempo'] : 72,
                'prompt' => self::words($round['prompt'] ?? ''),
                'clips' => array_map(static fn (array $clip): array => array_intersect_key($clip, array_flip(['notes', 'voices', 'drone', 'chords'])), $clips),
                'options' => $options,
                'answer' => $answer,
                'why' => self::words($round['why'] ?? ''),
            ];
        }

        return null;
    }

    /** A word as the content keeps it ({ "en": "...", "es": "..." }, or a plain string), in the page's language, or
     *  in English while it has none (docs/languages-plan.md: never half a sentence). */
    private static function words(mixed $words): string
    {
        $here = is_array($words) ? $words[app()->getLocale()] ?? null : null;

        return is_string($here) && trim($here) !== '' ? trim($here) : self::en($words);
    }

    /** A word as the content keeps it ({ "en": "..." }, or a plain string), in English. */
    private static function en(mixed $words): string
    {
        return trim(is_array($words) ? (string) ($words['en'] ?? '') : (is_string($words) ? $words : ''));
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function json(string $path): ?array
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : null;
    }

    private static function root(): string
    {
        return dirname(base_path()).'/web/course';
    }
}
