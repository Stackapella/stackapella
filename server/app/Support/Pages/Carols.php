<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Models\Template;
use Illuminate\Support\Carbon;

/**
 * The Christmas carols page, /songs/christmas-carols (the coordinator, 2026-11-08: the carol searches open in early
 * November): every carol in the library that's open, each with where it comes from and a link to each part's page.
 * A carol is a song whose file's genre says christmas or carol, and it's open when its part pages are (SongParts::songs:
 * live, reviewed, public domain), so a carol joins the page by itself on the Monday it opens, and a hidden one is never
 * named. The page is marketing's view (site.carols).
 */
final class Carols
{
    public const PATH = '/songs/christmas-carols';

    public const VIEW = 'site.carols';

    /** The genres that make a song a carol. */
    private const GENRES = ['christmas', 'carol'];

    public function __construct(private readonly SongParts $parts) {}

    /**
     * Each open carol: its title and page, where it comes from (the first sentence of its part pages' About, checked
     * by the courses lead), and its parts, each to its own page.
     *
     * @return list<array{slug: string, title: string, url: string, from: string|null, line: array<string, mixed>|null, meta: list<string>, parts: list<array{key: string, name: string, melody: bool, lowName: string, highName: string, url: string}>}>
     */
    public function songs(): array
    {
        if (! SongParts::open()) {
            return [];
        }

        $carols = [];

        foreach ($this->parts->songs() as $template) {
            if (! self::isCarol($template)) {
                continue;
            }

            $carols[] = self::card($template);
        }

        return $carols;
    }

    /**
     * A song as a card in a songbook (this page, and the collections, SongCollections): its title and page, where it
     * comes from, its opening line to play, its line from the song file, and its parts, each to its own page.
     *
     * @return array{slug: string, title: string, url: string, from: string|null, line: array<string, mixed>|null, meta: list<string>, parts: list<array{key: string, name: string, melody: bool, lowName: string, highName: string, url: string}>}
     */
    public static function card(Template $template): array
    {
        $url = '/songs/'.$template->slug;
        // In a round every voice sings the tune, so no one part is tagged the melody (the file marks its first voice).
        $round = SongParts::round(array_values(array_filter((array) (((array) $template->currentVersion?->data)['genre'] ?? []), 'is_string')));

        return [
            'slug' => (string) $template->slug,
            // A round's without its "(round)", as /songs puts it: its card says round already.
            'title' => $round ? SongParts::searchTitle((string) $template->title) : (string) $template->title,
            'url' => $url,
            'from' => self::from((string) $template->slug, (string) $template->title),
            // Its opening line to play, as /songs plays a song's: every open song plays for guests (Hillel,
            // 2026-11-08, "Yes, play for guests").
            'line' => Landing::line($template),
            // Its key, how much of it there is and its level, from the song file: "G major · Verse 1 · Beginner"
            // (the UX review of Hillel's second mockup, 2026-11-10). Never a length: the files don't give one.
            'meta' => self::meta((array) $template->currentVersion?->data),
            'parts' => array_map(static fn (array $part): array => [
                'key' => $part['key'], 'name' => $part['name'], 'melody' => $part['melody'] && ! $round,
                'lowName' => self::signs($part['lowName']), 'highName' => self::signs($part['highName']), 'url' => $url.'/'.$part['key'],
            ], SongParts::parts($template)),
        ];
    }

    /**
     * A carol's card line: its key ("E♭ major", "G minor"), how much of the song it has ("Verse 1", "Verse and refrain",
     * "Verse and chorus", or none when its sections are named otherwise) and its level, each only when the file says.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function meta(array $data): array
    {
        $line = [];
        if (is_string($data['key'] ?? null) && preg_match('/^([A-G])([b#]?)(m?)$/', $data['key'], $m) === 1) {
            $line[] = (string) __($m[3] === 'm' ? 'site.carols.key_minor' : 'site.carols.key_major', ['note' => self::signs($m[1].$m[2])]);
        }
        $sections = array_values(array_filter(array_map(static fn (mixed $s): ?string => is_array($s) && is_string($s['name'] ?? null) ? $s['name'] : null, (array) ($data['sections'] ?? []))));
        $verses = count(array_filter($sections, static fn (string $name): bool => preg_match('/^Verse \d+$/', $name) === 1));
        $rest = array_values(array_diff($sections, array_filter($sections, static fn (string $name): bool => preg_match('/^Verse \d+$/', $name) === 1)));
        $coverage = match (true) {
            $verses === 1 && $rest === [] => 'verse',
            $verses === 1 && $rest === ['Refrain'] => 'verse_refrain',
            $verses === 1 && $rest === ['Chorus'] => 'verse_chorus',
            default => null,
        };
        if ($coverage !== null) {
            $line[] = (string) __('site.carols.coverage.'.$coverage);
        }
        if (is_string($data['difficulty'] ?? null) && trans()->has('site.carols.levels.'.$data['difficulty'])) {
            $line[] = (string) __('site.carols.levels.'.$data['difficulty']);
        }

        return $line;
    }

    /** A note's name with real flat and sharp signs ("Eb5" to "E♭5", "F#2" to "F♯2"), as the music font draws them. */
    public static function signs(string $note): string
    {
        return (string) preg_replace(['/([A-G])b/', '/([A-G])#/'], ['$1♭', '$1♯'], $note);
    }

    public static function isCarol(Template $template): bool
    {
        $genres = array_filter((array) (((array) $template->currentVersion?->data)['genre'] ?? []), 'is_string');

        return array_intersect(array_map(static fn (string $genre): string => strtolower(trim($genre)), $genres), self::GENRES) !== [];
    }

    /**
     * Whether the page still says a new carol opens each Monday: until the last Monday before Christmas this year
     * (config site.carols_weekly_until), never after, so the line can't outlive the plan.
     */
    public static function weekly(): bool
    {
        $until = config('site.carols_weekly_until');

        return is_string($until) && now()->toDateString() <= $until;
    }

    /** Whether the choir pages and the landing point here: while the carols are pushed (promoted) and a carol is open. */
    public static function linked(): bool
    {
        return self::promoted() && app(self::class)->songs() !== [];
    }

    /**
     * Whether the carols are pushed today: between config site.carols_promoted's month-days (1 November to 26
     * December), every year, so the landing's line and the choir pages' seasonal links hide by themselves.
     */
    public static function promoted(): bool
    {
        $window = config('site.carols_promoted');
        if (! is_array($window) || ! is_string($window['from'] ?? null) || ! is_string($window['until'] ?? null)) {
            return false;
        }
        $today = now()->format('m-d');

        return $today >= $window['from'] && $today <= $window['until'];
    }

    /** Whether a song is one of the open carols, so its part pages point back here. */
    public static function has(string $slug): bool
    {
        return in_array($slug, array_column(app(self::class)->songs(), 'slug'), true);
    }

    /**
     * The countdown on the page's hero (Hillel's redesign, 2026-11-10): the days left until Christmas Day from the start
     * of the carols' push (config site.carols_promoted's from) to Christmas Day itself, or null otherwise, so after the
     * 25th it's gone until next year. The server's day is the first guess; js/public/carols.js counts again in the
     * visitor's own time zone (nothing about them is kept).
     *
     * @return array{days: int, christmas: bool}|null
     */
    public static function countdown(): ?array
    {
        $window = config('site.carols_promoted');
        $from = is_array($window) && is_string($window['from'] ?? null) ? $window['from'] : null;
        $today = now()->startOfDay();
        if ($from === null || $today->format('m-d') < $from || $today->format('m-d') > '12-25') {
            return null;
        }
        $christmas = $today->copy()->setDate($today->year, 12, 25);

        return ['days' => (int) $today->diffInDays($christmas), 'christmas' => $today->equalTo($christmas)];
    }

    /**
     * Tonight's carol: one of the open carols by the day of the year, the same for everyone on a day (no visitor data).
     *
     * @param  list<array{slug: string, title: string, line: array<string, mixed>|null}>  $carols
     * @return array{slug: string, title: string, line: array<string, mixed>|null}|null
     */
    public static function tonight(array $carols): ?array
    {
        if ($carols === []) {
            return null;
        }
        $carol = $carols[now()->dayOfYear % count($carols)];

        return ['slug' => $carol['slug'], 'title' => $carol['title'], 'line' => $carol['line']];
    }

    /**
     * The songbook's totals, counted from the open carols: how many, how many parts in all, and how many parts each has
     * when they all have the same (else null).
     *
     * @param  list<array{parts: list<array<string, mixed>>}>  $carols
     * @return array{carols: int, parts: int, each: int|null}
     */
    public static function totals(array $carols): array
    {
        $sizes = array_map(static fn (array $carol): int => count($carol['parts']), $carols);
        $each = array_unique($sizes);

        return ['carols' => count($carols), 'parts' => array_sum($sizes), 'each' => count($each) === 1 ? (int) reset($each) : null];
    }

    /** The carol season, November to Twelfth Night: when /songs points to the page. */
    public static function inSeason(): bool
    {
        $today = now();

        return $today->month >= 11 || ($today->month === 1 && $today->day <= 6);
    }

    /**
     * The page's lastmod: the newest of its carols' current versions, or null with none.
     */
    public function lastModified(): ?string
    {
        $newest = null;

        foreach ($this->parts->songs() as $template) {
            $when = $template->currentVersion->created_at ?? null;
            if (self::isCarol($template) && $when !== null && ($newest === null || $when->greaterThan($newest))) {
                $newest = $when;
            }
        }

        return $newest !== null ? Carbon::parse($newest)->utc()->toDateString() : null;
    }

    /**
     * Where a carol comes from: the page's own line (site.carols.from), else the first sentence of its About (song_about),
     * or null. A sentence never ends inside the carol's own title at its start ("Hark! The Herald Angels Sing began...").
     */
    private static function from(string $slug, string $title = ''): ?string
    {
        if (trans()->has('site.carols.from.'.$slug)) {
            return (string) __('site.carols.from.'.$slug);
        }

        $key = 'song_about.'.$slug;
        if (! trans()->has($key)) {
            return null;
        }

        $about = trim((string) __($key));
        $skip = $title !== '' && str_starts_with($about, $title) ? mb_strlen($title) : 0;

        return preg_match('/^(.{'.$skip.'}.*?[.!?])(?:\s|$)/us', $about, $m) === 1 ? $m[1] : $about;
    }
}
