<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Http\Controllers\Public\DocumentPagesController;
use App\Support\Flags;
use App\Support\Http\Hosts;
use App\Support\Legal\LegalText;
use App\Support\Pages\Landing;
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
    public const COURSES = [
        'counterpoint', 'vibrato', 'range-and-registers', 'belting', 'breath-and-stamina', 'diction', 'sight-singing', 'ear-training', 'perfect-your-pitch',
        'scat', 'barbershop', 'gospel-runs', 'yodeling', 'folk-and-early-music', 'musical-theatre', 'pop-and-country', 'a-cappella-bass', 'other-languages',
        'whistling', 'beatboxing', 'vocal-percussion', 'humming-and-lip-trills', 'overtone-singing', 'live-looping', 'improvising-harmony', 'character-voices', 'accents',
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
    public const FOR = ['beginners', 'choir-singers', 'choir-directors', 'a-cappella', 'songwriters', 'instrumentalists', 'friends-and-family', 'coaches'];

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
     * Whether a Plus course is open to learn now: the Plus courses are on, and the course's own list
     * (web/course/plus/courses.json, by the site's `slug`, else its `id`) marks it live.
     */
    public static function courseOpen(string $slug): bool
    {
        if (! app(Flags::class)->on('plus_courses')) {
            return false;
        }

        $path = dirname(base_path()).'/web/course/plus/courses.json';
        $list = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return collect(is_array($list) ? (array) ($list['courses'] ?? []) : [])->contains(static fn (mixed $c): bool => is_array($c) && ($c['slug'] ?? $c['id'] ?? null) === $slug && ($c['status'] ?? null) === 'live');
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

            $fromCatalogue = $catalogue->filter(static fn (array $c): bool => $c['group'] === $key)->map(static fn (array $c): string => (string) ($c['slug'] ?? $c['id']))->values()->all();
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
        $entry = collect(is_array($list) ? (array) ($list['courses'] ?? []) : [])->first(static fn (mixed $c): bool => is_array($c) && ($c['slug'] ?? $c['id'] ?? null) === $slug);
        $file = is_array($entry) && is_string($entry['file'] ?? null) ? $entry['file'] : null;

        // Only a file inside the courses' folder.
        if ($file === null || str_contains($file, '..') || ! is_file($root.'/'.$file)) {
            return null;
        }

        $course = json_decode((string) file_get_contents($root.'/'.$file), true);

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

        return ['units' => $units, 'lessons' => array_sum(array_map(static fn (array $u): int => count($u['lessons']), $units)), 'minutes' => is_int($entry['minutes'] ?? null) ? $entry['minutes'] : null];
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
    private static function fill(array $words, array $values): array
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
    private const FOR_ICONS = ['beginners' => 'spark', 'choir-singers' => 'book', 'choir-directors' => 'users', 'a-cappella' => 'mic', 'songwriters' => 'hum', 'instrumentalists' => 'slot', 'friends-and-family' => 'link', 'coaches' => 'coach'];

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

    /**
     * A page's <title>: its own words, with the name after them unless they already say it.
     */
    public static function title(string $title): string
    {
        $brand = (string) __('meta.brand');

        return str_contains($title, $brand) ? $title : $title.' | '.$brand;
    }

    /**
     * Each page's link card (web/icons/og/<key>.png, made by `php artisan app:site:link-cards`
     * and tool/link_cards.py from the page's own eyebrow and headline), or the landing's.
     */
    public static function image(string $url): string
    {
        $key = str_replace('/', '-', trim((string) parse_url($url, PHP_URL_PATH), '/'));

        return $key !== '' && is_file(dirname(base_path()).'/web/icons/og/'.$key.'.png')
            ? Hosts::public('/icons/og/'.$key.'.png')
            : Hosts::public('/icons/og-landing.png');
    }

    /**
     * The pages that get their own link card: each one's key, eyebrow and headline, the page's own
     * words (no claims of the card's own).
     *
     * @return list<array{key: string, eyebrow: string, headline: string}>
     */
    public static function linkCards(): array
    {
        $card = static fn (string $path, string $eyebrow, string $headline): array => ['key' => str_replace('/', '-', trim($path, '/')), 'eyebrow' => $eyebrow, 'headline' => $headline];

        return [
            $card('/features', (string) __('pages.site.features'), (string) __('site.features.index.headline')),
            ...array_map(static fn (string $s): array => $card('/features/'.$s, (string) __('pages.site.features'), (string) __('site.features.pages.'.$s.'.headline')), self::FEATURES),
            ...array_map(static fn (string $s): array => $card('/for/'.$s, (string) __('pages.site.for'), (string) __('site.for.pages.'.$s.'.headline')), self::FOR),
            // A course's card once its words are there (the landing agent's, with their eyebrow).
            ...array_map(static fn (string $s): array => $card('/courses/'.$s, (string) Lang::get('pages.site.course_card'), (string) Lang::get('site.courses.pages.'.$s.'.headline')), self::courses()),
            $card('/pricing', (string) __('pages.nav.pricing'), (string) __('pages.pricing.headline')),
            $card('/help', (string) __('pages.nav.help'), (string) __('pages.help.meta_title')),
            $card('/guides', (string) __('pages.guides.eyebrow'), (string) __('pages.guides.lead')),
            ...array_map(static fn (string $slug): array => $card('/guides/'.$slug, (string) __('pages.guides.eyebrow'), app(LegalText::class)->read($slug, folder: 'guides')['title']), DocumentPagesController::guides()),
            ...array_map(static fn (string $slug): array => $card('/help/'.$slug, (string) __('pages.nav.help'), (string) __('pages.help.pages.'.$slug.'.title')), HelpGuide::slugs()),
            $card('/about', (string) __('pages.nav.about'), (string) __('site.about.lead')),
            $card('/press', (string) __('pages.nav.press'), (string) __('site.press.one_liner')),
            $card('/whats-new', (string) __('pages.nav.whats_new'), (string) __('site.whats_new.lead')),
        ];
    }

    /**
     * Every feature, "for", about, press and what's new page's address, for the sitemap (coming-soon ones too: Hillel wants
     * them indexed so they rank by launch).
     *
     * @return list<string>
     */
    public static function paths(): array
    {
        $courses = self::courses();
        $how = self::hasHowItWorks() ? ['/how-it-works'] : [];

        return ['/features', ...array_map(static fn (string $s): string => '/features/'.$s, self::FEATURES), ...array_map(static fn (string $s): string => '/for/'.$s, self::FOR),
            ...($courses === [] ? [] : ['/courses', ...array_map(static fn (string $s): string => '/courses/'.$s, $courses)]), '/about', ...$how, '/press', '/whats-new', '/guides'];
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
