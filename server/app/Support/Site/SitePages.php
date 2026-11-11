<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Http\Controllers\Public\DocumentPagesController;
use App\Support\Flags;
use App\Support\Http\Hosts;
use App\Support\Legal\LegalText;
use App\Support\Pages\Carols;
use App\Support\Pages\Landing;
use App\Support\Pages\Reference;
use App\Support\Pages\SongCollections;
use App\Support\Pages\Warmups;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\View;

/**
 * The public site's feature and "for" pages (docs/public-site-plan.md): which exist, in the order
 * the site lists them, and each page's words (lang/en/site.php) with whether it's coming soon
 * (config/site.php) and the cards it links on to.
 */
final class SitePages
{
    /** The feature pages, open ones first as the overview lists them. */
    public const FEATURES = ['learn-a-part', 'course', 'studio', 'real-voices', 'share', 'create', 'choirs', 'your-tune', 'coaching', 'gallery', 'leagues', 'library', 'virtual-choir', 'song-requests', 'apps'];

    /**
     * The Plus courses' pages (docs/plus-courses-plan.md, in its order; Hillel: "All 27 now").
     * A course has a page once its words are written (courses()).
     */
    /**
     * Courses taken down, each to where its old address now leads (301): Beatboxing (Hillel, 2026-11-08: "lets remove the
     * beatboxing course, I dont think we can do it well with our current tech"), then Vocal percussion, Overtone singing,
     * Character voices and Accents (Hillel, 2026-11-08: "yes"). Vocal percussion's address leads to its guide, which
     * answers the search Google found the course for (docs/search-content-plan.md, item 1).
     */
    public const REMOVED_COURSES = ['beatboxing' => '/courses', 'vocal-percussion' => '/guides/vocal-percussion', 'overtone-singing' => '/courses', 'character-voices' => '/courses', 'accents' => '/courses'];

    public const COURSES = [
        'counterpoint', 'vibrato', 'range-and-registers', 'belting', 'breath-and-stamina', 'diction', 'sight-singing', 'ear-training', 'perfect-your-pitch',
        'scat', 'barbershop', 'gospel-runs', 'yodeling', 'folk-and-early-music', 'musical-theatre', 'pop-and-country', 'a-cappella-bass', 'other-languages',
        'whistling', 'humming-and-lip-trills', 'live-looping', 'improvising-harmony', 'backing-vocals', 'sea-shanties-and-work-songs', 'classical-singing-basics', 'blues-singing',
        'learn-your-choir-part',
    ];

    /**
     * The courses with their words written, in order, once their pages' views are there too (the
     * landing agent's words and views can arrive in either order).
     *
     * @return list<string>
     */
    public static function courses(): array
    {
        if (! View::exists('site.course') || ! View::exists('site.courses')) {
            return [];
        }

        return array_values(array_filter(self::COURSES, static fn (string $slug): bool => Lang::has('site.courses.pages.'.$slug.'.headline')));
    }

    /** Who it's for. */
    /** The other apps with a page under /integrations (IntegrationPagesController), in the hub's order. */
    public const INTEGRATIONS = ['event-schedule', 'invoice-ninja'];

    public const FOR = ['beginners', 'choir-singers', 'choir-directors', 'worship-teams', 'music-teachers', 'voice-teachers', 'a-cappella', 'songwriters', 'instrumentalists', 'friends-and-family', 'coaches'];

    /**
     * Every "for" page: the audiences above, and the page for organizations (the outreach partner
     * offer), which stays out of the "Who it's for" band so a new singer never meets it.
     */
    public const FOR_PAGES = [...self::FOR, 'organizations'];

    /**
     * The free tools that are built, in the hub's order (docs/tools-plan.md). Every slug here, in TOOL_MOUNTS and in
     * TOOL_MODULES is one of config('tools.slugs'), the one list the counts, manifests and mic policy read
     * (ToolsOfflineTest checks it). A tool's words are at
     * site.tools.pages.<slug>; its script is js/public/tools/<slug>.js, which exports MOUNTS[<slug>].
     */
    public const TOOLS = ['tuner', 'metronome', 'vocal-range-test', 'pitch-pipe', 'tuning-forks', 'drone', 'chord-transposer', 'four-part-harmony', 'pitch-matching', 'note-frequency-chart', 'tap-tempo', 'mic-test', 'vocal-key-finder', 'just-intonation-tuner', 'audio-latency-test', 'interval-ear-training', 'chord-ear-training'];

    /**
     * The listed tools as a list that grows one landing at a time, so a check like "none yet" reads the same with
     * any number listed.
     *
     * @return list<string>
     */
    public static function listed(): array
    {
        return self::TOOLS;
    }

    /** The hub opens once this many tools are live (Hillel, 2026-11-03); until then /tools leads to the first tool. */
    public const HUB_FROM = 4;

    /** Whether the hub is live: enough tools to be worth a page. */
    public static function hubLive(): bool
    {
        return count(self::listed()) >= self::HUB_FROM;
    }

    /** Whether /tools/embed has a tool to offer: one that's both live and embeddable (config tools.embeddable). */
    public static function embedLive(): bool
    {
        return array_intersect((array) config('tools.embeddable'), self::TOOLS) !== [];
    }

    /** Where "Free tools" leads: the hub once it's live, else the first tool. */
    public static function toolsHome(): string
    {
        return self::hubLive() ? '/tools' : '/tools/'.(self::listed()[0] ?? '');
    }

    /** Each tool's mount function, as its module exports it (frontend's engines and the synth tools alike). */
    public const TOOL_MOUNTS = ['tuner' => 'mountTuner', 'metronome' => 'mountMetronome', 'vocal-range-test' => 'mountRangeTest', 'pitch-pipe' => 'mountPitchPipe', 'tuning-forks' => 'mountTuningForks', 'drone' => 'mountDrone', 'audio-latency-test' => 'mountLatencyTest', 'interval-ear-training' => 'mountIntervalTrainer', 'chord-ear-training' => 'mountChordTrainer', 'chord-transposer' => 'mountChordTransposer', 'four-part-harmony' => 'mountSatbVoicer', 'pitch-matching' => 'mountPitchMatching', 'note-frequency-chart' => 'mountNoteFrequencies', 'tap-tempo' => 'mountTapTempo', 'mic-test' => 'mountMicTest', 'vocal-key-finder' => 'mountKeyFinder', 'just-intonation-tuner' => 'mountIntonationMeter'];

    /** A tool whose page address isn't its module's name (js/public/tools/<slug>.js): the module's own address. */
    public const TOOL_MODULES = ['audio-latency-test' => '/js/public/tools/latency-test.js', 'interval-ear-training' => '/js/public/tools/interval-trainer.js', 'chord-ear-training' => '/js/public/tools/chord-trainer.js', 'four-part-harmony' => '/js/public/tools/satb-voicer.js', 'note-frequency-chart' => '/js/public/tools/note-frequencies.js', 'just-intonation-tuner' => '/js/public/tools/intonation-meter.js'];

    /** The tools whose module draws its own header (the H1, its level and stats, from words.title): the page leaves its own out. */
    public const TOOL_OWN_HEADER = ['interval-ear-training', 'chord-ear-training'];

    /**
     * Tool variants (docs/seo-growth-plan.md item 3, docs/tools-plan.md "Variant pages"): a live tool's page opened for
     * one search, with a `preset` for the visit (never saved over the person's own settings). Each slug is one of
     * config('tools.slugs'); its words are site.tools.variants.<slug>, its card, module and way into the app its tool's.
     * Kept out of TOOLS, so the hub, the directory and the tool count stay about the tools themselves.
     */
    public const TOOL_VARIANTS = [
        'guitar-tuner' => ['tool' => 'tuner', 'preset' => ['instrument' => 'guitar', 'tuning' => 'standard']],
        'bass-tuner' => ['tool' => 'tuner', 'preset' => ['instrument' => 'bass4']],
        'ukulele-tuner' => ['tool' => 'tuner', 'preset' => ['instrument' => 'ukulele']],
        'violin-tuner' => ['tool' => 'tuner', 'preset' => ['instrument' => 'violin']],
        'banjo-tuner' => ['tool' => 'tuner', 'preset' => ['instrument' => 'banjo']],
        'mandolin-tuner' => ['tool' => 'tuner', 'preset' => ['instrument' => 'mandolin']],
        'cello-tuner' => ['tool' => 'tuner', 'preset' => ['instrument' => 'cello']],
        'viola-tuner' => ['tool' => 'tuner', 'preset' => ['instrument' => 'viola']],
        'voice-tuner' => ['tool' => 'tuner', 'preset' => ['mode' => 'voice']],
        'tanpura' => ['tool' => 'drone', 'preset' => ['mode' => 'tanpura']],
        '432-hz-tuning-fork' => ['tool' => 'tuning-forks', 'preset' => ['first' => 'a432', 'shelf' => ['a432', 'c256', 'a440']]],
    ];

    /**
     * The variants with a page: their tool is live and their words are written.
     *
     * @return list<string>
     */
    public static function variants(): array
    {
        return array_values(array_filter(array_keys(self::TOOL_VARIANTS), static fn (string $slug): bool => in_array(self::TOOL_VARIANTS[$slug]['tool'], self::listed(), true)
            && Lang::has('site.tools.variants.'.$slug.'.headline')));
    }

    /**
     * A tool's live variants, for the quiet "Also" line on its page: each one's slug and name.
     *
     * @return list<array{slug: string, name: string}>
     */
    public static function variantsOf(string $tool): array
    {
        return array_values(array_map(static fn (string $slug): array => ['slug' => $slug, 'name' => (string) __('site.tools.variants.'.$slug.'.name')],
            array_filter(self::variants(), static fn (string $slug): bool => self::TOOL_VARIANTS[$slug]['tool'] === $tool)));
    }

    /**
     * A variant's page: its tool's page (card, module, mount, icon, group, tie and way into the app) with the variant's
     * own words over it, its own slug, its tool as `tool` and its `preset`.
     *
     * @return array<string, mixed>
     */
    public static function variant(string $slug): array
    {
        $variant = self::TOOL_VARIANTS[$slug];

        return [...self::tool($variant['tool']), 'guide' => [], 'faq' => [], 'related' => null, ...(array) Lang::get('site.tools.variants.'.$slug),
            'slug' => $slug, 'tool' => $variant['tool'], 'preset' => $variant['preset']];
    }

    /**
     * The hub's cards: each built tool's name, one line and address.
     *
     * @return list<array{slug: string, name: string, summary: string, url: string, icon: string, tag: string, group: string}>
     */
    public static function tools(): array
    {
        return array_map(static fn (string $slug): array => [
            'slug' => $slug,
            'name' => (string) __('site.tools.pages.'.$slug.'.name'),
            'summary' => (string) __('site.tools.pages.'.$slug.'.summary'),
            'url' => '/tools/'.$slug,
            'icon' => (string) __('site.tools.pages.'.$slug.'.icon'),
            'tag' => (string) __('site.tools.pages.'.$slug.'.tag'),
            'group' => (string) __('site.tools.pages.'.$slug.'.group'),
        ], self::TOOLS);
    }

    /**
     * The tools' groups, in the order every page lists them, each with the part colour its icons wear
     * (docs/tools/below-the-fold.md, "More free tools"). Names are site.tools.index.groups.<key>.
     */
    public const TOOL_GROUPS = ['pitch' => 1, 'voice' => 4, 'ear' => 3, 'rhythm' => 2, 'theory' => 6];

    /**
     * "More free tools" below a tool, or the hub's list: the featured next tool (this tool's `related`, when it's live),
     * then every other live tool in its group, never this tool and never the featured one twice; empty groups left out.
     * `count` is the live tools' count, for "See all :count tools".
     *
     * @param  array<int, string>|null  $related
     * @return array{featured: array<string, string>|null, groups: list<array{key: string, colour: int, tools: list<array<string, string>>}>, count: int}
     */
    public static function directory(?string $slug = null, ?array $related = null): array
    {
        $live = self::tools();
        // A page that isn't a tool (a reference page) can name its own featured tool: [line, tool].
        $related = $related ?? ($slug !== null ? (array) (isset(self::TOOL_VARIANTS[$slug]) ? Lang::get('site.tools.variants.'.$slug.'.related') : Lang::get('site.tools.pages.'.$slug.'.related')) : []);
        $featured = null;
        foreach ($live as $tool) {
            if (($related[1] ?? null) === $tool['slug'] && $tool['slug'] !== $slug) {
                $featured = [...$tool, 'line' => (string) $related[0], 'colour' => (string) (self::TOOL_GROUPS[$tool['group']] ?? 1)];
            }
        }

        $groups = [];
        foreach (self::TOOL_GROUPS as $key => $colour) {
            $tools = array_values(array_filter($live, static fn (array $tool): bool => $tool['group'] === $key
                && $tool['slug'] !== $slug && $tool['slug'] !== ($featured['slug'] ?? null)));
            if ($tools !== []) {
                $groups[] = ['key' => $key, 'colour' => $colour, 'tools' => $tools];
            }
        }

        return ['featured' => $featured, 'groups' => $groups, 'count' => count($live)];
    }

    /**
     * One tool's page words, with its slug and mount.
     *
     * @return array<string, mixed>
     */
    public static function tool(string $slug): array
    {
        $module = self::TOOL_MODULES[$slug] ?? '/js/public/tools/'.$slug.'.js';

        return [...(array) Lang::get('site.tools.pages.'.$slug), 'slug' => $slug, 'mount' => self::TOOL_MOUNTS[$slug] ?? null,
            'module' => $module, 'own_header' => in_array($slug, self::TOOL_OWN_HEADER, true),
            // Its module's whole import graph, for the head's modulepreload links (ToolPreloads).
            'preloads' => ToolPreloads::for($module)];
    }

    /** The feature pages that play a song's line in the mixer, as the landing's hero. */
    public const WITH_MIXER = ['learn-a-part', 'studio'];

    /**
     * Whether a feature page is still coming: its own feature, and for Hum a tune also Ideas
     * (both halves must be open).
     */
    public static function soon(string $feature): bool
    {
        // Hum a tune opens on its own: sharing a tune as an idea is a network feature, and its page
        // says that part is coming (docs/launch-plan.md).
        return ComingSoon::soon($feature);
    }

    /**
     * A group's courses split as the Plus page lists them: those open now (courseOpen), and the names of
     * those still to come, joined for one line.
     *
     * @param  list<string>  $slugs
     * @return array{open: list<string>, soon: string}
     */
    public static function courseSplit(array $slugs): array
    {
        $open = array_values(array_filter($slugs, static fn (string $slug): bool => self::courseOpen($slug)));
        $soon = array_values(array_diff($slugs, $open));

        return ['open' => $open, 'soon' => implode(' · ', array_map(static fn (string $slug): string => (string) __('site.courses.pages.'.$slug.'.name'), $soon))];
    }

    /**
     * Whether a catalogue entry is the course with this page: by its `slug` (else its `id`), or by the page's own `app`
     * (site.courses.pages.<slug>.app), so a course whose page has a longer slug than its id is right before the
     * catalogue names the slug.
     */
    private static function isEntry(mixed $entry, string $slug): bool
    {
        if (! is_array($entry)) {
            return false;
        }
        $app = Lang::get('site.courses.pages.'.$slug.'.app');

        return ($entry['slug'] ?? $entry['id'] ?? null) === $slug || (is_string($app) && ($entry['id'] ?? null) === $app);
    }

    /**
     * Whether a course is open to learn now: the courses are on, and the course's own list
     * (web/course/plus/courses.json, matched by isEntry) marks it live.
     */
    public static function courseOpen(string $slug): bool
    {
        if (! app(Flags::class)->on('plus_courses')) {
            return false;
        }

        $path = dirname(base_path()).'/web/course/plus/courses.json';
        $list = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return collect(is_array($list) ? (array) ($list['courses'] ?? []) : [])->contains(static fn (mixed $c): bool => self::isEntry($c, $slug) && is_array($c) && ($c['status'] ?? null) === 'live');
    }

    /**
     * The course groups as the site lists them (Lang site.courses.groups' order, names and icons),
     * each with its courses by the app's own catalogue (web/course/plus/courses.json: `group` and
     * `order`, matched by `slug`, else `id`), so the site and Home never drift. A course the
     * catalogue doesn't list yet keeps its place from Lang, after the catalogue's.
     *
     * @return array<string, array{name: string, icon: string, courses: list<string>}>
     */
    public static function courseGroups(): array
    {
        $path = dirname(base_path()).'/web/course/plus/courses.json';
        $list = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $catalogue = collect(is_array($list) ? (array) ($list['courses'] ?? []) : [])
            ->filter(static fn (mixed $c): bool => is_array($c) && is_string($c['slug'] ?? $c['id'] ?? null) && is_string($c['group'] ?? null))
            ->sortBy(static fn (array $c): int => (int) ($c['order'] ?? PHP_INT_MAX));
        $listed = $catalogue->map(static fn (array $c): string => (string) ($c['slug'] ?? $c['id']))->all();
        $groups = [];

        foreach ((array) Lang::get('site.courses.groups') as $key => $group) {
            if (! is_array($group)) {
                continue;
            }

            // Only a course with its own page (site.courses.pages.<slug>) is listed: one the app has but the site
            // doesn't yet (a new course before marketing writes its page) would be a link to a 404.
            $fromCatalogue = $catalogue->filter(static fn (array $c): bool => $c['group'] === $key)->map(static fn (array $c): string => (string) ($c['slug'] ?? $c['id']))
                ->filter(static fn (string $slug): bool => in_array($slug, self::courses(), true))->values()->all();
            $fromLang = array_values(array_diff(array_map('strval', (array) ($group['courses'] ?? [])), $listed));
            $groups[(string) $key] = ['name' => (string) ($group['name'] ?? ''), 'icon' => (string) ($group['icon'] ?? ''), 'courses' => [...$fromCatalogue, ...$fromLang]];
        }

        return $groups;
    }

    /**
     * A Plus course's outline for its page: each unit's title and its lessons' titles, how many
     * lessons, and the catalogue's minutes. Read from the course's own file (the catalogue entry's
     * `file`, under web/course/plus/), so the page never drifts from the course. Null when it has
     * no file yet or the file doesn't parse.
     *
     * @return array{units: list<array{title: string, lessons: list<string>}>, lessons: int, minutes: int|null}|null
     */
    public static function courseOutline(string $slug): ?array
    {
        $root = dirname(base_path()).'/web/course/plus';
        $list = is_file($root.'/courses.json') ? json_decode((string) file_get_contents($root.'/courses.json'), true) : null;
        $entry = collect(is_array($list) ? (array) ($list['courses'] ?? []) : [])->first(static fn (mixed $c): bool => self::isEntry($c, $slug));
        $file = is_array($entry) && is_string($entry['file'] ?? null) ? $entry['file'] : null;

        // Only a file inside the courses' folder.
        if ($file === null || str_contains($file, '..') || ! is_file($root.'/'.$file)) {
            return null;
        }

        $outline = self::outlineOf($root.'/'.$file);

        return $outline === null ? null : [...$outline, 'minutes' => is_int($entry['minutes'] ?? null) ? $entry['minutes'] : null];
    }

    /**
     * The free course's outline, as courseOutline() gives a Plus course's, from web/course/course.json.
     * Null when it doesn't parse.
     *
     * @return array{units: list<array{title: string, lessons: list<string>}>, lessons: int}|null
     */
    public static function freeCourseOutline(): ?array
    {
        return self::outlineOf(dirname(base_path()).'/web/course/course.json');
    }

    /**
     * Each unit's title and its lessons' titles (in English), and how many lessons, from a course file.
     *
     * @return array{units: list<array{title: string, lessons: list<string>}>, lessons: int}|null
     */
    private static function outlineOf(string $path): ?array
    {
        $course = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($course) || ! is_array($course['units'] ?? null)) {
            return null;
        }

        $title = static fn (mixed $t): string => is_array($t) ? (string) ($t['en'] ?? '') : (is_string($t) ? $t : '');
        $units = [];

        foreach ($course['units'] as $unit) {
            if (is_array($unit)) {
                $units[] = ['title' => $title($unit['title'] ?? ''), 'lessons' => array_values(array_map(static fn (mixed $l): string => is_array($l) ? $title($l['title'] ?? '') : '', array_filter((array) ($unit['lessons'] ?? []), 'is_array')))];
            }
        }

        return ['units' => $units, 'lessons' => array_sum(array_map(static fn (array $u): int => count($u['lessons']), $units))];
    }

    /**
     * The group a course is in (courseGroups()), or null.
     */
    public static function courseGroup(string $slug): ?string
    {
        foreach (self::courseGroups() as $key => $group) {
            if (in_array($slug, $group['courses'], true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * A feature's words as they read once it's open: its `now` words over the coming ones (the
     * landing agent's, so a launch flips by config). Top-level keys replace the page's; `now.sections`,
     * keyed by a section's first heading, replace its heading, body and soon, or drop it (`hide`).
     *
     * @param  array<array-key, mixed>  $page
     * @return array<array-key, mixed>
     */
    private static function now(array $page): array
    {
        $now = $page['now'] ?? null;
        unset($page['now']);

        if (! is_array($now)) {
            return $page;
        }

        $sections = is_array($now['sections'] ?? null) ? $now['sections'] : [];
        unset($now['sections']);
        $page = [...$page, ...array_intersect_key($now, array_flip(['title', 'summary', 'description', 'lead', 'end']))];

        if (is_array($page['sections'] ?? null)) {
            $page['sections'] = array_values(array_filter(array_map(static function (mixed $section) use ($sections): ?array {
                if (! is_array($section)) {
                    return null;
                }
                $change = $sections[(string) ($section['heading'] ?? '')] ?? null;

                if (! is_array($change)) {
                    return $section;
                }

                return ($change['hide'] ?? false) === true ? null : [...$section, ...array_intersect_key($change, array_flip(['heading', 'body', 'soon']))];
            }, $page['sections'])));
        }

        return $page;
    }

    /**
     * A feature or "for" page's words, with its slug and, for a feature, whether it's coming.
     *
     * @param  'features'|'for'|'courses'  $area
     * @return array<string, mixed>
     */
    public static function page(string $area, string $slug): array
    {
        $words = (array) Lang::get('site.'.$area.'.pages.'.$slug);
        $page = self::fill($area === 'features' && ! self::soon($slug) ? self::now($words) : [...array_diff_key($words, ['now' => true])], [
            ':commission' => config('coaching.commission_percent').'%',
            ':email' => (string) config('site.contact_email'),
            ':partners_email' => (string) config('site.partners_email'),
        ]);

        return [
            ...$page,
            'slug' => $slug,
            'soon' => $area === 'features' && self::soon($slug),
            // Where a coming feature stands, in one honest line (its page's `soon`).
            'soon_line' => isset($page['soon']) && is_string($page['soon']) ? $page['soon'] : null,
            'sections' => $sections = array_values(array_filter((array) ($page['sections'] ?? []), 'is_array')),
            // Direction A: the sections with a drawing of the app are rows of words beside it; the
            // rest are plain cards.
            'rows' => array_values(array_filter($sections, static fn (array $section): bool => is_string($section['pic'] ?? null))),
            'notes' => array_values(array_filter($sections, static fn (array $section): bool => ! is_string($section['pic'] ?? null) && ! ($section['notice'] ?? false))),
            // A limit worth knowing ("for" pages), said quietly rather than as a card of its own.
            'notices' => array_values(array_filter($sections, static fn (array $section): bool => (bool) ($section['notice'] ?? false))),
        ];
    }

    /**
     * A page's words with its numbers and addresses in place, at any depth.
     *
     * @param  array<array-key, mixed>  $words
     * @param  array<string, string>  $values
     * @return array<array-key, mixed>
     */
    public static function fill(array $words, array $values): array
    {
        return array_map(static fn (mixed $word): mixed => match (true) {
            is_string($word) => strtr($word, $values),
            is_array($word) => self::fill($word, $values),
            default => $word,
        }, $words);
    }

    /** The features whose tile takes two columns on /features. */
    public const WIDE_TILES = ['learn-a-part', 'choirs'];

    /** Each "for" page's icon in the "Who it's for" band. */
    private const FOR_ICONS = ['beginners' => 'spark', 'choir-singers' => 'book', 'choir-directors' => 'users', 'worship-teams' => 'sound', 'music-teachers' => 'book', 'voice-teachers' => 'mic', 'a-cappella' => 'mic', 'songwriters' => 'hum', 'instrumentalists' => 'slot', 'friends-and-family' => 'link', 'coaches' => 'coach'];

    /**
     * The "Who it's for" band: each "for" page's name, one line, address and icon.
     *
     * @return list<array{slug: string, name: string, summary: string, url: string, icon: string}>
     */
    public static function who(): array
    {
        return array_map(static fn (string $slug): array => [
            'slug' => $slug,
            'name' => (string) __('site.for.pages.'.$slug.'.name'),
            'summary' => (string) __('site.for.pages.'.$slug.'.summary'),
            'url' => '/for/'.$slug,
            'icon' => self::FOR_ICONS[$slug],
        ], self::FOR);
    }

    /**
     * What the feature drawings are drawn from (components/site/pic): the hero's song and its
     * pictures, or nulls when the song isn't in the library.
     *
     * @return array{hero: array<string, mixed>|null, pics: array<string, mixed>|null}
     */
    public static function draw(): array
    {
        $hero = Landing::hero();

        return ['hero' => $hero, 'pics' => $hero !== null ? Landing::pictures($hero) : null];
    }

    /**
     * Feature cards: the name, one line, the page, whether it's coming and its picture.
     *
     * @param  list<string>  $slugs
     * @return list<array{slug: string, name: string, summary: string, url: string, soon: bool, pic: string}>
     */
    public static function cards(array $slugs): array
    {
        return array_values(array_map(static fn (string $slug): array => [
            'slug' => $slug,
            'name' => (string) __('site.features.pages.'.$slug.'.name'),
            // An open feature's card says it as it is now (its `now` summary), a coming one as coming.
            'summary' => (string) (! self::soon($slug) && Lang::has('site.features.pages.'.$slug.'.now.summary') ? Lang::get('site.features.pages.'.$slug.'.now.summary') : __('site.features.pages.'.$slug.'.summary')),
            'url' => '/features/'.$slug,
            'soon' => self::soon($slug),
            'pic' => (string) __('site.features.pages.'.$slug.'.pic'),
        ], array_values(array_filter($slugs, static fn (string $slug): bool => in_array($slug, self::FEATURES, true)))));
    }

    /**
     * The other feature pages to go on to: open ones first, then what's coming, up to `limit`, leaving
     * out any whose picture the page already shows (`shown`).
     *
     * @param  list<string>  $shown
     * @return list<array{slug: string, name: string, summary: string, url: string, soon: bool, pic: string}>
     */
    public static function related(string $slug, int $limit = 6, array $shown = []): array
    {
        $others = array_values(array_filter(self::FEATURES, static fn (string $s): bool => $s !== $slug));

        return array_slice(self::unshown(self::cards($others), $shown), 0, $limit);
    }

    /**
     * The pictures a page draws itself (its own and its sections'), so no card repeats one.
     *
     * @param  array<string, mixed>  $page
     * @return list<string>
     */
    public static function picturesOf(array $page): array
    {
        $pictures = [(string) ($page['pic'] ?? ''), ...array_map(static fn (array $section): string => (string) ($section['pic'] ?? ''), (array) ($page['sections'] ?? []))];

        return array_values(array_filter($pictures, static fn (string $pic): bool => $pic !== '' && ! str_starts_with($pic, 'icon:')));
    }

    /**
     * The cards whose picture isn't already on the page (Hillel: never the same picture twice).
     *
     * @param  list<array{slug: string, name: string, summary: string, url: string, soon: bool, pic: string}>  $cards
     * @param  list<string>  $shown
     * @return list<array{slug: string, name: string, summary: string, url: string, soon: bool, pic: string}>
     */
    public static function unshown(array $cards, array $shown): array
    {
        return array_values(array_filter($cards, static fn (array $card): bool => ! in_array($card['pic'], $shown, true)));
    }

    /** Search results show about 60 characters of a title before cutting it off. */
    public const TITLE_FITS = 60;

    /**
     * A page's <title>: its own words, with the name after them unless they already say it, or the
     * name would push the title past what search results show (they show the site's name anyway).
     */
    public static function title(string $title): string
    {
        $brand = (string) __('meta.brand');
        $branded = $title.' | '.$brand;

        return str_contains($title, $brand) || mb_strlen($branded) > self::TITLE_FITS ? $title : $branded;
    }

    /**
     * Each page's link card (web/icons/og/<key>.png, made by `php artisan app:site:link-cards`
     * and tool/link_cards.py from the page's own eyebrow and headline); a page without one takes the nearest page above
     * it that has one (a lesson its course's, a warm-up the warm-ups'; the coordinator's audit, 2026-11-11), else the
     * landing's.
     */
    public static function image(string $url): string
    {
        $parts = array_values(array_filter(explode('/', trim((string) parse_url($url, PHP_URL_PATH), '/')), static fn (string $p): bool => $p !== ''));
        for ($n = count($parts); $n > 0; $n--) {
            $key = implode('-', array_slice($parts, 0, $n));
            if (is_file(dirname(base_path()).'/web/icons/og/'.$key.'.png')) {
                return Hosts::public('/icons/og/'.$key.'.png');
            }
        }

        return Hosts::public('/icons/og-landing.png');
    }

    /**
     * The pages that get their own link card: each one's key, eyebrow and headline, the page's own
     * words (no claims of the card's own). A song collection's card is designed for its page (its `theme`, with the
     * serif words and the promise its hero has: collectionCards).
     *
     * @return list<array{key: string, eyebrow: string, headline: string, em?: string, lead?: string, theme?: string}>
     */
    public static function linkCards(): array
    {
        $card = static fn (string $path, string $eyebrow, string $headline): array => ['key' => str_replace('/', '-', trim($path, '/')), 'eyebrow' => $eyebrow, 'headline' => $headline];

        return [
            $card('/features', (string) __('pages.site.features'), (string) __('site.features.index.headline')),
            ...array_map(static fn (string $s): array => $card('/features/'.$s, (string) __('pages.site.features'), (string) __('site.features.pages.'.$s.'.headline')), self::FEATURES),
            ...array_map(static fn (string $s): array => $card('/for/'.$s, (string) __('pages.site.for'), (string) __('site.for.pages.'.$s.'.headline')), self::FOR_PAGES),
            // A course's card once its words are there (the landing agent's, with their eyebrow).
            ...array_map(static fn (string $s): array => $card('/courses/'.$s, (string) Lang::get('pages.site.course_card'), (string) Lang::get('site.courses.pages.'.$s.'.headline')), self::courses()),
            ...(self::hubLive() ? [$card('/tools', (string) __('site.tools.index.eyebrow'), (string) __('site.tools.index.headline'))] : []),
            // /tools/embed, once a live tool can be embedded.
            ...(self::embedLive() ? [$card('/tools/embed', (string) __('site.tools.embed.eyebrow'), (string) __('site.tools.embed.headline'))] : []),
            ...array_map(static fn (string $s): array => $card('/tools/'.$s, (string) __('site.tools.index.eyebrow'), (string) __('site.tools.pages.'.$s.'.headline')), self::TOOLS),
            ...array_map(static fn (string $s): array => $card('/tools/'.$s, (string) __('site.tools.index.eyebrow'), (string) __('site.tools.variants.'.$s.'.headline')), self::variants()),
            // The reference pages (Reference): each family's index and pages, under the family's name.
            ...array_merge(...array_map(static fn (string $family): array => Reference::slugs($family) === [] ? [] : [
                $card('/'.$family, (string) Reference::index($family)['eyebrow'], (string) Reference::index($family)['headline']),
                ...array_map(static fn (string $slug): array => $card('/'.$family.'/'.$slug, (string) Reference::index($family)['eyebrow'], (string) Reference::page($family, $slug)['headline']), Reference::slugs($family)),
            ], array_keys(Reference::FAMILIES))),
            $card('/songs', (string) __('pages.songs.eyebrow'), (string) __('pages.songs.headline')),
            ...self::collectionCards(),
            ...(self::courses() === [] ? [] : [$card('/courses', (string) __('pages.site.courses'), (string) __('site.courses.index.headline'))]),
            ...(self::hasHowItWorks() ? [$card('/how-it-works', (string) __('site.how.eyebrow'), (string) __('site.how.headline'))] : []),
            $card('/pricing', (string) __('pages.nav.pricing'), (string) __('pages.pricing.headline')),
            $card('/help', (string) __('pages.nav.help'), (string) __('pages.help.meta_title')),
            $card('/guides', (string) __('pages.guides.eyebrow'), (string) __('pages.guides.lead')),
            ...array_map(static fn (string $slug): array => $card('/guides/'.$slug, (string) __('pages.guides.eyebrow'), app(LegalText::class)->read($slug, folder: 'guides')['title']), DocumentPagesController::guides()),
            ...array_map(static fn (string $slug): array => $card('/help/'.$slug, (string) __('pages.nav.help'), (string) __('pages.help.pages.'.$slug.'.title')), HelpGuide::slugs()),
            $card('/about', (string) __('pages.nav.about'), (string) __('site.about.lead')),
            $card('/press', (string) __('pages.nav.press'), (string) __('site.press.one_liner')),
            $card('/whats-new', (string) __('pages.nav.whats_new'), (string) __('site.whats_new.lead')),
            // The warm-ups and the integrations (the coordinator's audit, 2026-11-11: they were on the landing's card).
            ...(Warmups::ids() === [] ? [] : [
                $card('/warmups', (string) __('site.warmups.index.eyebrow'), (string) __('site.warmups.index.headline')),
                ...array_map(static fn (string $id): array => $card('/warmups/'.$id, (string) __('site.warmups.index.eyebrow'), (string) __('site.warmups.pages.'.$id.'.headline')), Warmups::ids()),
            ]),
            $card('/integrations', (string) __('site.integrations.index.eyebrow'), (string) __('site.integrations.index.headline')),
            ...array_map(static fn (string $s): array => $card('/integrations/'.$s, (string) __('site.integrations.index.eyebrow'), (string) __('site.integrations.pages.'.$s.'.headline')), self::INTEGRATIONS),
        ];
    }

    /**
     * The song collections' cards (Hillel, 2026-11-11: "give these special pages a special meta image"): the carols'
     * while it has a carol open, and each collection that's a page (SongCollections::open), each with its hero's words
     * and its own theme (tool/link_cards.py THEMES). No song count, so a card never goes stale.
     *
     * @return list<array{key: string, eyebrow: string, headline: string, em: string, lead: string, theme: string}>
     */
    private static function collectionCards(): array
    {
        $cards = app(Carols::class)->songs() === [] ? [] : [[
            'key' => trim(str_replace('/', '-', Carols::PATH), '-'), 'eyebrow' => (string) __('site.carols.crumb'), 'headline' => (string) __('site.carols.headline'),
            'em' => (string) __('site.carols.headline_em'), 'lead' => (string) __('site.carols.lead'), 'theme' => 'carols',
        ]];
        foreach (app(SongCollections::class)->open() as $open) {
            $words = 'site.collections.'.$open['slug'];
            $cards[] = [
                'key' => trim(str_replace('/', '-', $open['path']), '-'), 'eyebrow' => $open['name'], 'headline' => (string) __($words.'.headline'),
                'em' => (string) __($words.'.headline_em'), 'lead' => (string) __($words.'.lead'), 'theme' => $open['slug'],
            ];
        }

        return $cards;
    }

    /**
     * Every feature, "for", about, press, what's new and integrations page's address, for the sitemap (coming-soon ones too: Hillel wants
     * them indexed so they rank by launch).
     *
     * @return list<string>
     */
    public static function paths(): array
    {
        $courses = self::courses();
        $how = self::hasHowItWorks() ? ['/how-it-works'] : [];

        return ['/features', ...array_map(static fn (string $s): string => '/features/'.$s, self::FEATURES), ...array_map(static fn (string $s): string => '/for/'.$s, self::FOR_PAGES),
            ...(self::hubLive() ? ['/tools'] : []), ...(self::embedLive() ? ['/tools/embed'] : []), ...array_map(static fn (string $s): string => '/tools/'.$s, [...self::TOOLS, ...self::variants()]),
            ...($courses === [] ? [] : ['/courses', ...array_map(static fn (string $s): string => '/courses/'.$s, $courses)]), '/about', ...$how, '/press', '/whats-new', '/guides',
            '/integrations', ...array_map(static fn (string $s): string => '/integrations/'.$s, self::INTEGRATIONS)];
    }

    /**
     * The comparison pages' slugs, from their words (site.compare.pages), in that order; none until
     * the words are there.
     *
     * @return list<string>
     */
    public static function compareSlugs(): array
    {
        $pages = Lang::get('site.compare.pages');

        return is_array($pages) ? array_values(array_filter(array_keys($pages), static fn (mixed $slug): bool => is_string($slug) && preg_match('/^[a-z0-9-]{1,40}$/', $slug) === 1)) : [];
    }

    /**
     * The comparison pages' addresses for the sitemap: only once the compare_pages switch is on.
     *
     * @return list<string>
     */
    public static function comparePaths(): array
    {
        $slugs = app(Flags::class)->on('compare_pages') ? self::compareSlugs() : [];

        return $slugs === [] ? [] : ['/compare', ...array_map(static fn (string $s): string => '/compare/'.$s, $slugs)];
    }

    /**
     * Whether How it works has its page yet (the landing agent's view and words can arrive first or
     * second).
     */
    public static function hasHowItWorks(): bool
    {
        return View::exists('site.how-it-works') && Lang::has('site.how.title');
    }
}
