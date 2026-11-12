<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Http\Resources\GuideRecording;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Support\Http\Hosts;
use App\Support\Languages\PartNames;
use App\Support\Languages\SongDescriptions;
use App\Support\Templates\Catalogue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The song part pages (docs/seo-growth-plan.md, item 1): /songs/<song>/<part> for each part of
 * every live, reviewed, public-domain library song (Catalogue::songs: our own, listed, never
 * withdrawn or a person's; its current version reviewed; its words and tune both public domain).
 * Each page's payload holds what the song page already shows, never the notes or audio.
 * The page is marketing's view (site.song-part); until it exists there are no pages at all.
 */
final class SongParts
{
    public const VIEW = 'site.song-part';

    /** A part's key as its page's address takes it. */
    public const KEY = '/^[a-z0-9-]{1,40}$/';

    /** Arrivals from a part page's Learn this part. */
    public const SOURCE = 'part-page';

    public function __construct(private readonly Catalogue $catalogue) {}

    public static function open(): bool
    {
        return Views::has(self::VIEW);
    }

    /**
     * A song's words and tune are both public domain, as its file says (licence.words, .tune).
     *
     * @param  array<string, mixed>  $data
     */
    public static function publicDomain(array $data): bool
    {
        $licence = (array) ($data['licence'] ?? []);

        foreach (['words', 'tune'] as $what) {
            if (! is_string($licence[$what] ?? null) || ! str_starts_with(strtolower(trim($licence[$what])), 'public domain')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Collection<int, Template>
     */
    public function songs(): Collection
    {
        return $this->catalogue->open();
    }

    /**
     * Whether a song has part pages: the pages are open, and it's live (ours, listed, not
     * withdrawn), its current version reviewed, its words and tune public domain.
     */
    public static function hasPages(Template $template): bool
    {
        return self::open() && Catalogue::isOpen($template);
    }

    /**
     * Every part page's path.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        $paths = [];

        foreach ($this->songs() as $template) {
            foreach (self::parts($template) as $part) {
                $paths[] = '/songs/'.$template->slug.'/'.$part['key'];
            }
        }

        return $paths;
    }

    /**
     * Each part page's lastmod: the later of its song version's date and its real voice's.
     *
     * @return array<string, string>
     */
    public function lastModified(): array
    {
        $songs = $this->songs();
        $voices = $this->voices($songs);
        $dates = [];

        foreach ($songs as $template) {
            foreach (self::parts($template) as $part) {
                $dates['/songs/'.$template->slug.'/'.$part['key']] = $this->changed($template, $voices->get($template->current_version_id.':'.$part['key'])?->first());
            }
        }

        return $dates;
    }

    /** The song file's levels, easiest first (its `difficulty`); a song without one comes after them. */
    public const LEVELS = ['beginner', 'intermediate', 'advanced'];

    /**
     * Song parts that sit inside a range (a voice type's usual range, MIDI), for its reference page: one per song, a
     * part named in `$prefer` first (the type's own choir parts), else the one whose middle is nearest the range's. The
     * songs whose part is one of `$prefer` come first, then the easiest (the file's level, LEVELS), then the catalogue's
     * order, at most `$limit`. Each with its part's ends (MIDI) for the page's bar of it inside the range.
     *
     * @param  list<string>  $prefer
     * @return list<array{title: string, name: string, url: string, low: int, high: int, lowName: string, highName: string}>
     */
    public function within(int $low, int $high, array $prefer = [], int $limit = 6): array
    {
        $middle = ($low + $high) / 2;
        $found = [];

        foreach ($this->songs()->values() as $i => $template) {
            $fits = array_values(array_filter(self::parts($template), static fn (array $p): bool => $p['low'] > 0 && $p['low'] >= $low && $p['high'] <= $high));
            if ($fits === []) {
                continue;
            }
            usort($fits, static fn (array $a, array $b): int => [! in_array($a['key'], $prefer, true), abs(($a['low'] + $a['high']) / 2 - $middle)] <=> [! in_array($b['key'], $prefer, true), abs(($b['low'] + $b['high']) / 2 - $middle)]);
            $level = array_search(((array) $template->currentVersion?->data)['difficulty'] ?? null, self::LEVELS, true);
            $found[] = [
                'order' => [! in_array($fits[0]['key'], $prefer, true), $level === false ? count(self::LEVELS) : $level, $i],
                'row' => ['title' => (string) $template->title, 'name' => $fits[0]['name'], 'url' => '/songs/'.$template->slug.'/'.$fits[0]['key'], 'low' => $fits[0]['low'], 'high' => $fits[0]['high'], 'lowName' => $fits[0]['lowName'], 'highName' => $fits[0]['highName']],
            ];
        }
        usort($found, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return array_slice(array_column($found, 'row'), 0, $limit);
    }

    /** The choir's four parts, highest first: a song whose parts are these is SATB, and its pages say so. */
    public const SATB = ['soprano', 'alto', 'tenor', 'bass'];

    /**
     * Whether a song is a round (its file's genre says so): everyone sings the same tune, entering in turn, so its pages
     * say round, not SATB or harmony (the search plan: "row row row your boat round").
     *
     * @param  list<string>  $genres
     */
    public static function round(array $genres): bool
    {
        return in_array('round', array_map(static fn (string $g): string => strtolower(trim($g)), $genres), true);
    }

    /** A round's title as search puts it, without its "(round)" (the page says round itself). */
    public static function searchTitle(string $title): string
    {
        return trim((string) preg_replace('/\s*\(round\)\s*$/i', '', $title));
    }

    /**
     * Whether a song's parts are SATB's four (SongParts::SATB), in order.
     *
     * @param  list<string>  $keys
     */
    public static function satb(array $keys): bool
    {
        return $keys === self::SATB;
    }

    /**
     * The page's payload, or null when there's no such page.
     *
     * @return array<string, mixed>|null
     */
    public function page(string $slug, string $key): ?array
    {
        $template = $this->songs()->first(static fn (Template $t): bool => $t->slug === $slug);
        $parts = $template !== null ? self::parts($template) : [];
        $part = array_values(array_filter($parts, static fn (array $p): bool => $p['key'] === $key))[0] ?? null;

        if ($template === null || $part === null) {
            return null;
        }

        $data = (array) $template->currentVersion?->data;
        $voice = $this->voices(collect([$template]))->get($template->current_version_id.':'.$key)?->first();
        $credit = $voice !== null ? GuideRecording::of($voice) : null;
        $base = Hosts::publicUrl().'/songs/'.$template->slug;
        $credits = (array) ($data['credits'] ?? []);
        $about = 'song_about.'.$template->slug;

        return [
            'canonical' => $base.'/'.$key,
            'song' => [
                'slug' => (string) $template->slug,
                'title' => (string) $template->title,
                'key' => (string) ($data['key'] ?? ''),
                'difficulty' => (string) ($data['difficulty'] ?? ''),
                'genre' => array_values(array_filter((array) ($data['genre'] ?? []), 'is_string')),
                'description' => SongDescriptions::of((string) ($data['description'] ?? '')),
                'about' => trans()->has($about) ? (string) __($about) : null,
                'melodyPart' => array_values(array_filter($parts, static fn (array $p): bool => $p['melody']))[0]['key'] ?? null,
                'pageUrl' => $base,
                // Whether a visitor may hear it here, signed out: every open song (Hillel, 2026-11-08, "Yes, play for
                // guests"; the API serves its current version signed out by the same rule). Before, a non-bundled
                // song's player met a guest's 403 and collapsed, a layout shift of 0.12.
                'free' => Catalogue::isOpen($template),
                'credits' => array_values(array_filter([
                    isset($credits['words']) ? __('pages.song.words', ['who' => $credits['words']]) : null,
                    isset($credits['tune']) ? __('pages.song.tune', ['who' => $credits['tune']]) : null,
                ])),
            ],
            'part' => $part,
            // Whether its parts are SATB's four, so the page can say so (docs/search-content-plan.md, item 4).
            'satb' => self::satb(array_column($parts, 'key')) && ! self::round(array_values(array_filter((array) ($data['genre'] ?? []), 'is_string'))),
            'round' => self::round(array_values(array_filter((array) ($data['genre'] ?? []), 'is_string'))),
            'parts' => array_map(static fn (array $p): array => [
                'key' => $p['key'], 'name' => $p['name'], 'melody' => $p['melody'], 'lowName' => $p['lowName'], 'highName' => $p['highName'],
                'url' => $base.'/'.$p['key'], 'current' => $p['key'] === $key,
            ], $parts),
            'guide' => ['synth' => true, 'realVoice' => $voice !== null, 'credit' => $credit['credit'] ?? null, 'creditHandle' => $credit['creditHandle'] ?? null],
            'startUrl' => Hosts::app('/?'.http_build_query(['song' => $template->slug, 'part' => $key, 'ref' => self::SOURCE])),
            'lastModified' => $this->changed($template, $voice),
        ];
    }

    /**
     * A song's parts as the pages name them (each with a key; ranges as MIDI and as names).
     *
     * @return list<array{key: string, name: string, melody: bool, low: int, high: int, lowName: string, highName: string}>
     */
    public static function parts(Template $template): array
    {
        $parts = [];

        foreach ((array) (((array) $template->currentVersion?->data)['parts'] ?? []) as $part) {
            if (! is_array($part) || ! is_string($part['key'] ?? null) || preg_match(self::KEY, $part['key']) !== 1) {
                continue;
            }

            $low = (int) ($part['range']['low'] ?? 0);
            $high = (int) ($part['range']['high'] ?? 0);
            $parts[] = [
                'key' => $part['key'],
                'name' => PartNames::name((string) $part['key'], (string) ($part['name'] ?? $part['key'])),
                'melody' => ($part['melody'] ?? false) === true,
                'low' => $low,
                'high' => $high,
                'lowName' => SongPage::note($low),
                'highName' => SongPage::note($high),
            ];
        }

        return $parts;
    }

    /**
     * Each part's real voices, the default first ("versionId:part").
     *
     * @param  Collection<int, Template>  $songs
     * @return Collection<string, Collection<int, TemplateGuide>>
     */
    private function voices(Collection $songs): Collection
    {
        return $this->catalogue->guides($songs->pluck('current_version_id')->filter()->map(static fn (mixed $id): int => (int) $id)->values()->all());
    }

    private function changed(Template $template, ?TemplateGuide $voice): string
    {
        $song = $template->currentVersion->created_at ?? $template->created_at ?? now();
        $when = $voice !== null && $voice->published_at->greaterThan($song) ? $voice->published_at : $song;

        return Carbon::parse($when)->utc()->toDateString();
    }
}
