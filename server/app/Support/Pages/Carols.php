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
     * @return list<array{slug: string, title: string, url: string, from: string|null, line: array<string, mixed>|null, parts: list<array{key: string, name: string, melody: bool, lowName: string, highName: string, url: string}>}>
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

            $url = '/songs/'.$template->slug;
            $carols[] = [
                'slug' => (string) $template->slug,
                'title' => (string) $template->title,
                'url' => $url,
                'from' => self::from((string) $template->slug, (string) $template->title),
                // Its opening line to play, as /songs plays a song's: every open song plays for guests (Hillel,
                // 2026-11-08, "Yes, play for guests").
                'line' => Landing::line($template),
                'parts' => array_map(static fn (array $part): array => [
                    'key' => $part['key'], 'name' => $part['name'], 'melody' => $part['melody'],
                    'lowName' => $part['lowName'], 'highName' => $part['highName'], 'url' => $url.'/'.$part['key'],
                ], SongParts::parts($template)),
            ];
        }

        return $carols;
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
     * @param  list<array{slug: string, title: string}>  $carols
     * @return array{slug: string, title: string}|null
     */
    public static function tonight(array $carols): ?array
    {
        if ($carols === []) {
            return null;
        }
        $carol = $carols[now()->dayOfYear % count($carols)];

        return ['slug' => $carol['slug'], 'title' => $carol['title']];
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
