<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Models\Template;
use App\Support\Flags;
use App\Support\Http\Hosts;
use App\Support\Languages\PartNames;
use App\Support\Languages\SongWords;
use App\Support\Outreach\Outreach;
use App\Support\Site\ComingSoon;
use App\Support\Site\Drawings;
use App\Support\Site\SitePages;
use App\Support\Templates\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;

/**
 * The landing page's data (direction A, board-a2): the hero's song to play, the links into the app
 * (carrying where the visitor came from), and what's on the way (each by
 * its flag, config/site.php), and the plans' numbers from config. No list of songs (Hillel): Start
 * singing leads into the app, where the songs are.
 */
final class Landing
{
    /** The hero's song and the beat its first line ends on ("you" is its melody, as on every song page). */
    public const HERO_SONG = 'amazing-grace';

    /** The courses the landing shows after the beginner course, in this order (pages/landing/_free): all open. */
    public const FEATURED_COURSES = ['vibrato', 'perfect-your-pitch', 'ear-training', 'barbershop', 'range-and-registers', 'breath-and-stamina', 'counterpoint'];

    /** The tools the landing shows as chips, in the hub's order (pages/landing/_free). */
    public const FEATURED_TOOLS = ['tuner', 'metronome', 'vocal-range-test', 'pitch-pipe', 'drone', 'interval-ear-training'];

    /**
     * The landing's courses after the beginner course's Start here card: the first six of FEATURED_COURSES with a
     * page, three by two (landing v2).
     *
     * @return list<string>
     */
    public static function featuredCourses(): array
    {
        $pages = SitePages::courses();

        return array_slice(array_values(array_filter(self::FEATURED_COURSES, static fn (string $slug): bool => in_array($slug, $pages, true))), 0, 6);
    }

    /**
     * The landing's tool chips: FEATURED_TOOLS's cards, in the hub's order.
     *
     * @return list<array{slug: string, name: string, summary: string, url: string, icon: string, tag: string, group: string}>
     */
    public static function featuredTools(): array
    {
        return array_values(array_filter(SitePages::tools(), static fn (array $tool): bool => in_array($tool['slug'], self::FEATURED_TOOLS, true)));
    }

    public const HERO_END_BEAT = 24;

    /** A song page's line: at least this long where the phrases allow, and never longer than the max. */
    private const LINE_SECONDS = 12.0;

    private const LINE_MAX_SECONDS = 30.0;

    /** On the way: each planned feature (config/site.php) with its icon, in the page's order. */
    public const ON_THE_WAY = [
        'your-tune' => 'hum',
        'apps' => 'device',
        'gallery' => 'grid',
        'open-parts' => 'slot',
        'virtual-choir' => 'users',
        'leagues' => 'flag',
        'library' => 'book',
        'coach-directory' => 'coach',
        'song-requests' => 'vote',
    ];

    /**
     * Where the visitor came from (`?ref=`, as the go-to-market plan's links carry it), when it's
     * one of ours in shape; anything else is dropped.
     */
    public static function ref(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[a-z0-9-]{1,32}$/', $value) === 1 ? $value : null;
    }

    /**
     * A path on our own site with the visitor's query kept, for a 301 to a page's new address: a shared link's
     * `?ref=` still counts where it lands.
     */
    public static function withQuery(Request $request, string $path): string
    {
        $query = $request->getQueryString();

        return $query === null || $query === '' ? $path : $path.'?'.$query;
    }

    /**
     * The links into the app, carrying `ref` so the app records it at sign-up, whether the footer
     * links the comparison pages (the compare_pages switch), and, for someone who came by an
     * organization's outreach link (a known o- tag), the page with the partner offer, keeping the tag.
     *
     * @return array{start: string, app: string, plus: string, create: string, compare: bool, partner: string|null}
     */
    public static function links(?string $ref): array
    {
        $with = static fn (string $path): string => Hosts::app($ref !== null ? $path.(str_contains($path, '?') ? '&' : '?').http_build_query(['ref' => $ref]) : $path);

        return [
            'start' => $with('/learn/1-comfortable'),
            'app' => $with('/'),
            'plus' => $with('/plus?from=landing'),
            'create' => $with('/create'),
            'compare' => app(Flags::class)->on('compare_pages'),
            'partner' => $ref !== null && Outreach::known($ref) ? '/for/organizations?'.http_build_query(['ref' => $ref]) : null,
        ];
    }

    /**
     * The hero's first line: each part's notes up to HERO_END_BEAT (cut to end there) with its
     * range, and the melody's syllables with their beats, for js/public/mixer.js to sing in the
     * app's guide voices. Null when the song isn't in the library.
     *
     * @return array{song: string, title: string, version: int, tempo: float, beats: float, length: string, you: string, parts: list<array{key: string, name: string, color: int, range: array{low: int, high: int}, notes: list<array{beat: float, length: float, pitch: int}>}>, words: list<array{beat: float, text: string, joins: bool}>}|null
     */
    public static function hero(): ?array
    {
        $template = Template::query()->official()->with('currentVersion')->where('slug', self::HERO_SONG)->first();

        return $template !== null ? self::line($template, self::HERO_END_BEAT) : null;
    }

    /**
     * Any official song's opening line in the hero's shape (a feature page's demo; a view may not
     * query), or null when there's no such song.
     *
     * @return array{song: string, title: string, version: int, tempo: float, beats: float, length: string, you: string, parts: list<array{key: string, name: string, color: int, range: array{low: int, high: int}, notes: list<array{beat: float, length: float, pitch: int}>}>, words: list<array{beat: float, text: string, joins: bool}>}|null
     */
    public static function lineOf(string $slug): ?array
    {
        $template = Template::query()->official()->with('currentVersion')->where('slug', $slug)->first();

        return $template !== null ? self::line($template) : null;
    }

    /**
     * A song's opening line in the hero's shape, for any song's page: its first phrases, up to the
     * first phrase end at least LINE_SECONDS in (or `$end`), with "you" the part asked for, else
     * the melody (Hillel: "Melody first", as the app and both heroes do), else the first part.
     * Null when the song has no parts. Without an `$end` a press plays the whole song (Hillel, 2026-11-12: "let's
     * always play the full songs"): `whole`, and `songBeats` where it ends; the page carries only the opening line's
     * notes, and js/public/mixer.js fetches the rest (and `length` is the whole song's). The landing's hero keeps its
     * one line (its words and its title say so).
     *
     * @return array{song: string, title: string, version: int, tempo: float, beats: float, songBeats: float, whole: bool, length: string, you: string, parts: list<array{key: string, name: string, color: int, range: array{low: int, high: int}, notes: list<array{beat: float, length: float, pitch: int}>}>, words: list<array{beat: float, text: string, joins: bool}>}|null
     */
    public static function line(Template $template, ?float $end = null, ?string $you = null): ?array
    {
        $data = (array) ($template->currentVersion->data ?? []);
        $parts = array_values(array_filter((array) ($data['parts'] ?? []), 'is_array'));
        if ($parts === []) {
            return null;
        }

        $tempo = (float) ($data['tempo'] ?? 80);
        $whole = $end === null;
        $end ??= self::lineEnd($data, $tempo);
        $songBeats = 0.0;
        foreach ($parts as $part) {
            foreach (array_filter((array) ($part['guide']['notes'] ?? []), 'is_array') as $note) {
                $songBeats = max($songBeats, (float) ($note['beat'] ?? 0) + (float) ($note['length'] ?? 1));
            }
        }
        // A song no longer than its line is whole already.
        $whole = $whole && $songBeats > $end;
        $line = static fn (array $part): array => array_values(array_filter(
            array_filter((array) ($part['guide']['notes'] ?? []), 'is_array'),
            static fn (array $note): bool => (float) ($note['beat'] ?? 0) < $end,
        ));
        $notes = static fn (array $part): array => array_map(static fn (array $note): array => [
            'beat' => (float) $note['beat'],
            'length' => min((float) ($note['length'] ?? 1), $end - (float) $note['beat']),
            'pitch' => (int) ($note['pitch'] ?? 60),
        ], $line($part));
        $melody = collect($parts)->first(fn (array $p): bool => ($p['melody'] ?? false) === true) ?? $parts[0];
        $keys = array_map(static fn (array $part): string => (string) ($part['key'] ?? ''), $parts);
        $you = in_array($you, $keys, true) ? $you : (string) ($melody['key'] ?? $keys[0]);

        return [
            'song' => $template->slug,
            'title' => SongWords::title((string) $template->title),
            'version' => (int) ($template->currentVersion->version ?? 1),
            'tempo' => $tempo,
            'beats' => $end,
            'songBeats' => max($songBeats, $end),
            'whole' => $whole,
            'length' => self::clock(($whole ? $songBeats : $end) * 60 / $tempo),
            'you' => $you,
            // Coloured as the studio colours a song's parts: in their order.
            'parts' => array_map(static fn (array $part, int $i): array => [
                'key' => (string) ($part['key'] ?? ''),
                'name' => PartNames::name((string) ($part['key'] ?? ''), (string) ($part['name'] ?? '')),
                'color' => $i % 8 + 1,
                'range' => ['low' => (int) ($part['range']['low'] ?? 48), 'high' => (int) ($part['range']['high'] ?? 72)],
                'notes' => $notes($part),
            ], $parts, array_keys($parts)),
            'words' => array_values(array_map(static fn (array $note): array => [
                'beat' => (float) $note['beat'],
                'text' => rtrim((string) $note['lyric'], '-'),
                'joins' => str_ends_with((string) $note['lyric'], '-'),
            ], array_filter($line($melody), static fn (array $note): bool => is_string($note['lyric'] ?? null) && $note['lyric'] !== ''))),
        ];
    }

    /**
     * Where a song's opening line ends: the first phrase end at least LINE_SECONDS in, never past
     * LINE_MAX_SECONDS (then the last phrase end before it); 24 beats when the song has no phrases.
     *
     * @param  array<string, mixed>  $data
     */
    private static function lineEnd(array $data, float $tempo): float
    {
        $ends = [];
        foreach ((array) ($data['sections'] ?? []) as $section) {
            foreach ((array) (is_array($section) ? ($section['phrases'] ?? []) : []) as $phrase) {
                if (is_array($phrase) && isset($phrase[1])) {
                    $ends[] = (float) $phrase[1];
                }
            }
        }
        sort($ends);
        $seconds = static fn (float $beat): float => $beat * 60 / $tempo;
        $fits = array_values(array_filter($ends, static fn (float $end): bool => $seconds($end) <= self::LINE_MAX_SECONDS));

        foreach ($fits as $end) {
            if ($seconds($end) >= self::LINE_SECONDS) {
                return $end;
            }
        }

        return $fits !== [] ? end($fits) : 24.0;
    }

    /**
     * What's on the way: each planned feature, whether it's still coming, and where it is once it
     * isn't. The section goes when everything in it is open.
     *
     * @return list<array{key: string, icon: string, soon: bool, href: string}>
     */
    public static function onTheWay(): array
    {
        // Each goes to its own feature page, which says where to find it once it's open.
        $pages = ['open-parts' => 'gallery', 'coach-directory' => 'coaching'];

        return array_map(static fn (string $key): array => [
            'key' => $key,
            'icon' => self::ON_THE_WAY[$key],
            'soon' => ComingSoon::soon($key),
            'href' => '/features/'.($pages[$key] ?? $key),
        ], array_keys(self::ON_THE_WAY));
    }

    /**
     * The plans' numbers, from config (docs/features.md is their list).
     *
     * `songs` is the library, every song of it free with an account; `freeSongs` the ones a guest
     * sings without one; `projects` null since no plan limits them (2026-11-07).
     *
     * @return array{songs: int, freeSongs: int, projects: int|null, storage: string, freeStorage: string, trialDays: int, checkoutOpen: bool}
     */
    public static function plans(): array
    {
        $projects = config('usage.plans.free.projects');

        return [
            'songs' => app(Catalogue::class)->songCount(),
            'freeSongs' => count((array) config('templates.free')),
            'projects' => is_int($projects) ? $projects : null,
            'freeStorage' => round((int) config('usage.plans.free.storage_bytes') / 1024 ** 3).' GB',
            'storage' => round((int) config('usage.plans.plus.storage_bytes') / 1024 ** 3).' GB',
            'trialDays' => (int) config('billing.trial_days'),
            'checkoutOpen' => filled(config('billing.prices.month')) && filled(config('billing.prices.year')),
        ];
    }

    /**
     * The questions people ask first, with the free plan's numbers filled in from config. Once
     * Stackapella is open source, the privacy answer says the code can be checked, and one more
     * question says so.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function faq(int $freeSongs, string $price, bool $openSource = false): array
    {
        $faq = (array) Lang::get('pages.landing.faq');
        if ($openSource) {
            $faq['privacy'][1] .= ' '.__('pages.landing.faq_privacy_check');
            $faq['open_source'] = (array) Lang::get('pages.landing.faq_open_source');
        }

        return array_values(array_map(static fn (array $qa): array => [
            (string) $qa[0],
            str_replace([':free_songs', ':price'], [(string) $freeSongs, $price], (string) $qa[1]),
        ], $faq));
    }

    /**
     * A list of lines from the landing's words (the plans' lists).
     *
     * @param  array<string, string>  $replace
     * @return list<mixed>
     */
    public static function list(string $key, array $replace = []): array
    {
        $lines = array_values((array) Lang::get('pages.landing.'.$key));

        return array_map(static fn (mixed $line): mixed => is_string($line) ? strtr($line, $replace) : $line, $lines);
    }

    /**
     * The mudeo letter's paragraphs (shown only to mudeo's singers, MudeoArrival): the words
     * escaped, then our address alone made a link.
     *
     * @return list<HtmlString>
     */
    public static function mudeoLetter(): array
    {
        $address = 'contact@stackapella.com';

        return array_values(array_map(
            static fn (mixed $p): HtmlString => new HtmlString(str_replace(e($address), '<a class="s-lk" href="mailto:'.$address.'">'.$address.'</a>', e((string) $p))),
            (array) Lang::get('pages.landing.mudeo.body'),
        ));
    }

    /**
     * The four steps' pictures, drawn from the hero's song: the soprano's note lane part sung, the
     * alto's waveform, the parts stacked while the tenor records, and the whole song.
     *
     * @param  array{beats: float, parts: list<array{key: string, range: array{low: int, high: int}, notes: list<array{beat: float, length: float, pitch: int}>}>}  $hero
     * @return array{lane: HtmlString|null, alto: HtmlString|null, stack: array<string, HtmlString>, whole: array<string, HtmlString>}
     */
    public static function pictures(array $hero): array
    {
        $parts = array_column($hero['parts'], null, 'key');
        $end = (float) $hero['beats'];
        $wave = static fn (string $key, int $seed, int $w, int $h): ?HtmlString => isset($parts[$key]) ? Drawings::wave($parts[$key]['notes'], $parts[$key]['range'], $end, $seed, $w, $h) : null;

        return [
            'lane' => isset($parts['soprano']) ? Drawings::lane($parts['soprano']['notes'], 0, 12, 7.6) : null,
            'alto' => $wave('alto', 6, 320, 44),
            'stack' => array_filter(['soprano' => $wave('soprano', 2, 300, 22), 'alto' => $wave('alto', 3, 300, 22), 'tenor' => $wave('tenor', 4, 300, 22)]),
            'whole' => array_filter(['soprano' => $wave('soprano', 1, 300, 14), 'alto' => $wave('alto', 2, 300, 14), 'tenor' => $wave('tenor', 3, 300, 14), 'bass' => $wave('bass', 4, 300, 14)]),
        ];
    }

    /** "0:18". */
    private static function clock(float $seconds): string
    {
        $whole = (int) round($seconds);

        return sprintf('%d:%02d', intdiv($whole, 60), $whole % 60);
    }

    /**
     * The hero's words as one line of text ("Amazing grace, how sweet the sound..."), for its text
     * alternative.
     *
     * @param  list<array{beat: float, text: string, joins: bool}>  $words
     */
    public static function sentence(array $words): string
    {
        return trim(array_reduce($words, static fn (string $line, array $w): string => $line.$w['text'].($w['joins'] ? '' : ' '), ''));
    }
}
