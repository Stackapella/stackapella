<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Support\Languages\Dates;
use App\Support\Languages\SongWords;
use App\Support\Rights\RightsHere;
use App\Support\Rights\SongRights;
use App\Support\Templates\Catalogue;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

/**
 * The songs in the public domain, and the songs that will be (/songs/public-domain; Hillel, 2026-11-18, and on 2026-11-19:
 * "we should be more celebrating their legacy than mourning their passing"). Our public domain songs first, then each
 * 1 January as a release: the songs newly free in most of the world (seventy years after their writers' deaths, the term
 * most countries use), each song first and its writer after it, from the years ahead down to the songs free for
 * generations.
 *
 * Built on SongRights from two sources: the open songs' own makers and first printings (each song links to its page,
 * and says where it's still protected and from when it's free there, by every country's row in config/copyright_terms),
 * and resources/data/public-domain/coming.json, works we don't have yet, each maker with a cited death year. A maker's
 * birth year shows only where resources/data/public-domain/lives.json cites it. Withhold rather than guess: a maker of
 * unknown death is never listed, and a low-confidence country's row is never named (the strictest rule stands for it).
 */
final class PublicDomain
{
    public const PATH = '/songs/public-domain';

    public const VIEW = 'site.public-domain';

    /** The term most of the world uses: the 1 January after the seventieth year since the last maker's death. */
    public const TERM = 70;

    /** The roll shows a heading for each 1 January from this one on; earlier makers are grouped by the century they died in. */
    public const YEARLY_FROM = 1996;

    /** The roll looks this far ahead; the coming works beyond it are left for a later year's page. */
    public const AHEAD = 70;

    /** The countries named first when a list of places is cut short (where most of our singers are). */
    private const FIRST = ['US', 'GB', 'CA', 'AU', 'IE', 'NZ', 'MX', 'ES', 'CO', 'AR', 'DE', 'FR', 'IL', 'ZA', 'IN', 'JP', 'BR'];

    /** How a maker's part in a song is said, in the order it's said. */
    public const ROLES = ['words', 'music', 'translation', 'arrangement', 'edition'];

    public function __construct(private readonly Catalogue $catalogue, private readonly RightsHere $rights) {}

    /**
     * The page, in the request's language: kept a day under the catalogue, the terms table, the data files, the year
     * and the build (Catalogue::kept), so a new song, a changed term or a new 1 January reads it afresh.
     *
     * @return array<string, mixed>
     */
    public function page(?int $year = null): array
    {
        $year ??= (int) now()->year;
        $key = 'public-domain.'.App::getLocale().'.'.$year.'.'.hash('xxh128', $this->catalogue->fingerprint().'|'.self::sourcesHash().'|'.(string) config('app.build'));

        /** @var array<string, mixed> */
        return [...Catalogue::kept($key, fn (): array => self::present($this->build($year))), 'enforced' => $this->rights->on()];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(int $year): array
    {
        $lives = self::lives();
        $people = [];
        $unsigned = [];
        $songs = [];
        $librarySongs = 0;

        foreach ($this->catalogue->open() as $template) {
            $data = (array) $template->currentVersion?->data;
            $slug = (string) $template->slug;
            $song = [
                'title' => SongWords::title((string) $template->title, $slug),
                'url' => '/songs/'.$slug,
                'data' => $data,
            ];
            $librarySongs++;
            $makers = self::makers($data);
            $songs[] = ['song' => $song, 'writers' => self::writers($data)];
            // A maker living or of unknown death is never on the roll: the song's other makers carry it, saying it waits.
            $song['unknown'] = self::unknown($data);
            if ($makers === [] && $song['unknown'] !== []) {
                continue;
            }
            // A song whose only named makers are off the roll (an editor whose dates our sources dispute) is listed with the
            // songs passed down without a name: its words and tune have no maker we can name.
            $listed = array_filter($makers, static fn (array $m): bool => self::life($lives, $m['name'], $m['died']) !== false);
            if ($listed === []) {
                $printed = is_int($data['firstPublished']['year'] ?? null) ? $data['firstPublished']['year'] : null;
                $unsigned[] = ['title' => $song['title'], 'url' => $song['url'], 'printed' => $printed, 'sort' => Str::ascii($song['title'])];

                continue;
            }
            foreach (self::byPerson($makers) as $person) {
                $life = self::life($lives, $person['name'], $person['died']);
                if ($life === false) {
                    continue;
                }
                $key = $life['key'];
                $people[$key] ??= ['name' => $life['name'], 'born' => $life['born'], 'died' => $person['died'], 'source' => $life['source'], 'works' => [], 'library' => false];
                $people[$key]['library'] = true;
                $people[$key]['works'][] = ['song' => $song, 'roles' => $person['roles'], 'makers' => $makers];
            }
        }

        $coming = self::coming();
        $comingWorks = 0;
        $comingLast = 0;
        foreach ($coming['works'] as $work) {
            $makers = [];
            $unknown = [];
            foreach ($work['makers'] as $maker) {
                $who = $coming['people'][$maker['person']] ?? null;
                if ($who !== null) {
                    $makers[] = ['name' => $who['name'], 'role' => $maker['role'], 'died' => $who['died'], 'id' => $maker['person']];
                } elseif (isset($coming['unknown'][$maker['person']])) {
                    $unknown[$maker['person']] = $coming['unknown'][$maker['person']];
                }
            }
            if ($makers === [] || count($makers) + count(array_filter($work['makers'], static fn (array $m): bool => isset($unknown[$m['person']]))) !== count($work['makers'])) {
                continue;
            }
            $last = max(array_column($makers, 'died'));
            if ($last + self::TERM + 1 > $year + self::AHEAD) {
                continue;
            }
            $comingWorks++;
            $comingLast = max($comingLast, $last + self::TERM + 1);
            // A co-maker whose death we can't confirm keeps the work protected everywhere (SongRights reads "unknown").
            $song = ['title' => $work['title'], 'url' => null, 'unknown' => array_values($unknown), 'data' => ['makers' => [...$makers, ...array_map(static fn (string $name): array => ['name' => $name, 'role' => 'words', 'died' => 'unknown'], array_values($unknown))], 'firstPublished' => $work['firstPublished']]];
            foreach (self::byPerson($makers) as $person) {
                $who = $coming['people'][$person['id'] ?? ''] ?? null;
                if ($who === null) {
                    continue;
                }
                $key = self::key($who['name'], $who['died']);
                $people[$key] ??= ['name' => $who['name'], 'born' => $who['born'], 'died' => $who['died'], 'source' => $who['source'], 'works' => [], 'library' => false];
                $people[$key]['works'][] = ['song' => $song, 'roles' => $person['roles'], 'makers' => $makers];
            }
        }

        $places = self::places();
        // Our songs as a list by title (what most people searching "public domain songs" want): the writers, the first
        // printing, free now, and in how many places a longer term still holds it.
        $list = [];
        foreach ($songs as $item) {
            $longer = 0;
            foreach (self::held($item['song']['data'], $year, $places) as $held) {
                $longer += $held['year'] !== null ? count($held['places']) + count($held['more']) : 0;
            }
            $printed = $item['song']['data']['firstPublished']['year'] ?? null;
            $list[] = ['title' => $item['song']['title'], 'url' => $item['song']['url'], 'writers' => $item['writers'], 'printed' => is_int($printed) ? $printed : null, 'longer' => $longer, 'sort' => Str::ascii($item['song']['title'])];
        }
        usort($list, static fn (array $a, array $b): int => strcasecmp($a['sort'], $b['sort']));
        $groups = [];
        foreach ($people as $key => $person) {
            $joins = $person['died'] + self::TERM + 1;
            $entry = $this->entry($key, $person, $year, $places);
            $groups[$joins][] = $entry;
        }
        ksort($groups);
        $comingGroups = [];
        $joined = [];
        $long = [];
        foreach ($groups as $joins => $entries) {
            usort($entries, static fn (array $a, array $b): int => [$a['sort']] <=> [$b['sort']]);
            if ($joins > $year) {
                $comingGroups[] = ['year' => $joins, 'died' => $joins - self::TERM - 1, 'people' => $entries];
            } elseif ($joins >= self::YEARLY_FROM) {
                $joined[] = ['year' => $joins, 'died' => $joins - self::TERM - 1, 'people' => $entries];
            } else {
                foreach ($entries as $entry) {
                    $long[intdiv($entry['died'], 100) * 100][] = $entry;
                }
            }
        }
        usort($comingGroups, static fn (array $a, array $b): int => $a['year'] <=> $b['year']);
        $joined = array_reverse($joined);
        krsort($long);
        $longGroups = [];
        foreach ($long as $century => $entries) {
            usort($entries, static fn (array $a, array $b): int => [$b['died'], $a['sort']] <=> [$a['died'], $b['sort']]);
            $longGroups[] = ['century' => $century, 'people' => $entries];
        }
        usort($unsigned, static fn (array $a, array $b): int => strcasecmp($a['sort'], $b['sort']));

        // This coming 1 January, or, when no maker on the roll died seventy years before it, the next one that has a name.
        $next = $comingGroups[0] ?? null;
        $all = array_merge(...array_values(array_map(static fn (array $g): array => $g['people'], [...$comingGroups, ...$joined, ...$longGroups])));

        return [
            'year' => $year,
            'next' => $next,
            'nextIsThisYear' => $next !== null && $next['year'] === $year + 1,
            'coming' => $comingGroups,
            'joined' => $joined,
            'long' => $longGroups,
            'songs' => array_map(static fn (array $s): array => array_diff_key($s, ['sort' => true]), $list),
            'unsigned' => array_map(static fn (array $s): array => array_diff_key($s, ['sort' => true]), $unsigned),
            'terms' => self::terms(),
            'counts' => [
                'people' => count($all),
                'coming' => count(array_filter($all, static fn (array $p): bool => $p['state'] === 'coming')),
                'joined' => count(array_filter($all, static fn (array $p): bool => $p['state'] !== 'coming')),
                'library' => count(array_filter($all, static fn (array $p): bool => $p['library'])),
                'songs' => $librarySongs,
                'works' => $comingWorks,
                'worksBy' => $comingLast,
            ],
        ];
    }

    /**
     * The roll's words in the page's language, so the view only places them: each year's date and caption, each writer's
     * life, each song's parts and where it differs (each note marked free now or free from a later year), and Public
     * Domain Day's card: its songs, each with its writer.
     *
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    public static function present(array $page): array
    {
        $w = 'site.public_domain.';
        $group = static function (array $group) use ($w): array {
            $group['date'] = Dates::dayMonthYear(Carbon::parse($group['year'].'-01-01'));
            $group['line'] = (string) __($w.'year_line', ['died' => $group['died']]);
            $group['people'] = array_map(self::person(...), $group['people']);

            return $group;
        };
        $page['coming'] = array_map($group, $page['coming']);
        $page['joined'] = array_map($group, $page['joined']);
        if ($page['next'] !== null) {
            $next = $group($page['next']);
            // Public Domain Day's card: each writer's best-known songs (two at most) first, the writer after them (Hillel:
            // "I recognize the song names more than the authors").
            $page['next'] = [...$next,
                'nextLine' => (string) __($w.'next.line', ['date' => $next['date'], 'names' => self::listed(array_column($next['people'], 'name'), [])]),
                'newly' => array_map(static fn (array $p): array => ['key' => $p['key'], 'name' => $p['name'], 'songs' => array_slice(array_map(static fn (array $work): array => ['title' => $work['title'], 'url' => $work['url']], $p['works']), 0, 2)], $next['people']),
            ];
        }
        $page['longBefore'] = self::YEARLY_FROM - self::TERM - 1;
        $page['usBefore'] = self::usBefore();
        // The filter bar's Year row, in the roll's order: the years ahead, then the years already shared, then the earlier ones.
        $page['years'] = [
            ...array_map(static fn (array $g): array => ['href' => '#y'.$g['year'], 'label' => (string) $g['year'], 'part' => 'coming'], $page['coming']),
            ...array_map(static fn (array $g): array => ['href' => '#y'.$g['year'], 'label' => (string) $g['year'], 'part' => 'joined'], $page['joined']),
            ...($page['long'] === [] ? [] : [['href' => '#long', 'label' => (string) __($w.'year_long'), 'part' => 'long']]),
        ];
        $page['long'] = array_map(static fn (array $century): array => [
            ...$century,
            'label' => (string) __($w.'century', ['century' => $century['century'].'s']),
            'people' => array_map(self::person(...), $century['people']),
        ], $page['long']);
        $page['songs'] = array_map(static fn (array $song): array => [
            ...$song,
            'by' => $song['writers'] === [] ? (string) __($w.'traditional') : self::listed($song['writers'], []),
            'printedText' => $song['printed'] !== null ? (string) __($w.'printed', ['year' => $song['printed']]) : '',
            'longerText' => $song['longer'] > 0 ? trans_choice($w.'longer', $song['longer'], ['count' => $song['longer']]) : '',
            'search' => mb_strtolower(implode(' ', [$song['title'], ...$song['writers'], (string) $song['printed']])),
        ], $page['songs'] ?? []);
        $page['unsigned'] = array_map(static fn (array $song): array => [...$song, 'printedText' => $song['printed'] !== null ? (string) __($w.'printed', ['year' => $song['printed']]) : '', 'search' => mb_strtolower($song['title'].' '.$song['printed'])], $page['unsigned']);
        $page['terms'] = array_map(static fn (array $row): array => [
            'term' => $row['life'] === null ? (string) __($w.'term_us', ['before' => self::usBefore()]) : (string) __($w.'term_life', ['years' => $row['life']]),
            'places' => trans_choice($w.'term_places', $row['count'], ['count' => $row['count'], 'names' => self::listed($row['places'], [])]),
        ], $page['terms']);

        return $page;
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    private static function person(array $person): array
    {
        $w = 'site.public_domain.';
        $person['life'] = $person['born'] !== null ? (string) __($w.'life', ['born' => $person['born'], 'died' => $person['died']]) : (string) __($w.'life_died', ['died' => $person['died']]);
        $person['sourceLabel'] = (string) __($w.'source_label', ['name' => $person['name']]);
        $person['works'] = array_map(static function (array $work) use ($w): array {
            $notes = [];
            if ($work['waits'] !== null) {
                $notes[] = ['kind' => 'later', 'text' => $work['waits']['year'] === null ? (string) __($w.'waits_unknown', ['name' => $work['waits']['name']]) : (string) __($w.'waits', ['year' => $work['waits']['year'], 'name' => $work['waits']['name']]), 'more' => []];
            }
            foreach ($work['elsewhere'] as $held) {
                $notes[] = [
                    'kind' => $held['year'] === null ? 'now' : 'later',
                    'text' => $held['year'] === null ? (string) __($w.'free_now', ['places' => self::listed($held['places'], $held['more'])]) : (string) __($w.'free_from', ['year' => $held['year'], 'places' => self::listed($held['places'], $held['more'])]),
                    'more' => $held['more'],
                ];
            }

            return [...$work, 'roles' => array_map(static fn (string $role): string => (string) __($w.'roles.'.$role), $work['roles']), 'notes' => $notes];
        }, $person['works']);

        return $person;
    }

    /**
     * Places as a sentence says them: "Mexico", "Mexico and Colombia", "Mexico, Colombia and Spain", and "and 4 more"
     * when the list goes on (the rest are in the note's own list).
     *
     * @param  list<string>  $names
     * @param  list<string>  $more
     */
    public static function listed(array $names, array $more): string
    {
        $w = 'site.public_domain.';
        if ($more !== []) {
            return implode(', ', $names).' '.trans_choice($w.'more', count($more), ['count' => count($more)]);
        }
        if (count($names) < 2) {
            return implode('', $names);
        }
        $last = array_pop($names);

        return (string) __($w.'list_and', ['rest' => implode(', ', $names), 'last' => $last]);
    }

    /**
     * One writer's entry on the roll: their songs first, each with its own state where it differs from
     * theirs (a co-maker who died later, a country with a longer term).
     *
     * @param  array{name: string, born: int|null, died: int, source: string|null, works: list<array<string, mixed>>, library: bool}  $person
     * @param  array<string, array{name: string, rule: array<string, mixed>}>  $places
     * @return array<string, mixed>
     */
    private function entry(string $key, array $person, int $year, array $places): array
    {
        $joins = $person['died'] + self::TERM + 1;
        $works = [];
        $seen = [];
        foreach ($person['works'] as $work) {
            $id = $work['song']['url'] ?? $work['song']['title'];
            if (isset($seen[$id])) {
                $works[$seen[$id]]['roles'] = array_values(array_unique([...$works[$seen[$id]]['roles'], ...$work['roles']]));

                continue;
            }
            $last = self::lastDeath($work['makers']);
            $waits = null;
            if (($work['song']['unknown'] ?? []) !== []) {
                $waits = ['name' => implode(', ', $work['song']['unknown']), 'year' => null];
            } elseif ($last > $person['died'] && $last + self::TERM + 1 > $year) {
                $later = array_values(array_filter($work['makers'], static fn (array $m): bool => (int) $m['died'] === $last))[0];
                $waits = ['name' => (string) $later['name'], 'year' => $last + self::TERM + 1];
            }
            $headline = max($joins, $last + self::TERM + 1);
            // A song on our site says everywhere its state differs (where it's still protected, and until when); a work we
            // don't have yet only the United States', which counts from publication (the terms table says the rest).
            $where = $work['song']['url'] !== null ? $places : array_intersect_key($places, ['US' => true]);
            $elsewhere = array_values(array_filter(self::held($work['song']['data'], $year, $where), static fn (array $h): bool => $h['year'] !== $headline && ! ($h['year'] === null && $headline <= $year)));
            $seen[$id] = count($works);
            $works[] = [
                'title' => $work['song']['title'],
                'url' => $work['song']['url'],
                'roles' => $work['roles'],
                'waits' => $waits,
                'elsewhere' => $elsewhere,
            ];
        }
        // Songs on our site first, then the rest as the data lists them, the best known first (the sort is stable).
        usort($works, static fn (array $a, array $b): int => ($a['url'] === null) <=> ($b['url'] === null));
        $works = array_map(static fn (array $work): array => [...$work, 'roles' => self::ordered($work['roles'])], $works);

        $state = $joins > $year ? 'coming' : ($joins >= self::YEARLY_FROM ? 'joined' : 'long');

        return [
            'key' => Str::slug($person['name']).'-'.$person['died'],
            'name' => $person['name'],
            'born' => $person['born'],
            'died' => $person['died'],
            'joins' => $joins,
            'source' => $person['source'],
            'library' => $person['library'],
            'state' => $state,
            'works' => $works,
            'sort' => Str::ascii(self::surname($person['name'])),
            'search' => mb_strtolower(implode(' ', [$person['name'], ...array_column($works, 'title'), (string) $person['died'], (string) $joins, (string) ($person['born'] ?? '')])),
        ];
    }

    /**
     * Where a song's state differs by country: each year it becomes free and the places that year (null: free now),
     * high- and medium-confidence rows only, by the country's own name in the page's language.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, array{name: string, rule: array<string, mixed>}>  $places
     * @return list<array{year: int|null, places: list<string>, more: list<string>}>
     */
    public static function held(array $data, int $year, array $places): array
    {
        $byYear = [];
        foreach ($places as $code => $place) {
            $status = SongRights::status($data, $code, $year);
            if ($status['reason'] !== 'term' || (! $status['free'] && $status['until'] === null)) {
                continue;
            }
            $byYear[$status['free'] ? 0 : (int) $status['until']][] = $code;
        }
        ksort($byYear);
        $out = [];
        foreach ($byYear as $when => $codes) {
            usort($codes, static function (string $a, string $b) use ($places): int {
                $ra = array_search($a, self::FIRST, true);
                $rb = array_search($b, self::FIRST, true);

                return [$ra === false ? 99 : $ra, Str::ascii($places[$a]['name'])] <=> [$rb === false ? 99 : $rb, Str::ascii($places[$b]['name'])];
            });
            $names = array_map(static fn (string $c): string => $places[$c]['name'], $codes);
            $out[] = ['year' => $when === 0 ? null : $when, 'places' => array_slice($names, 0, 3), 'more' => array_slice($names, 3)];
        }

        return $out;
    }

    /**
     * Every country's row we may name, by code, with its name in the page's language: the low-confidence rows (a law we
     * couldn't read for certain, which takes the strictest rule) are left out.
     *
     * @return array<string, array{name: string, rule: array<string, mixed>}>
     */
    public static function places(): array
    {
        $places = [];
        foreach ((array) config('copyright_terms.countries') as $code => $rule) {
            if (! is_array($rule) || ($rule['confidence'] ?? 'high') === 'low') {
                continue;
            }
            $places[(string) $code] = ['name' => self::country((string) $code, (string) ($rule['name'] ?? $code)), 'rule' => $rule];
        }

        return $places;
    }

    /** A country's name in the page's language (Intl), else the table's English name. */
    public static function country(string $code, string $english): string
    {
        if (App::getLocale() === 'en' || ! class_exists(\Locale::class)) {
            return $english;
        }
        $name = \Locale::getDisplayRegion('-'.$code, App::getLocale());

        return $name === '' || $name === $code ? $english : $name;
    }

    /**
     * The terms in our table, longest first: each term in years after death with how many places use it and a few of
     * their names, and the United States' by publication. Low-confidence rows aren't counted.
     *
     * @return list<array{life: int|null, count: int, places: list<string>}>
     */
    public static function terms(): array
    {
        $byLife = [];
        $us = [];
        foreach (self::places() as $code => $place) {
            if (is_array($place['rule']['publicationBased'] ?? null)) {
                $us[] = $code;

                continue;
            }
            $byLife[(int) ($place['rule']['life'] ?? 0)][] = $code;
        }
        krsort($byLife);
        $places = self::places();
        usort($us, static fn (string $a, string $b): int => [array_search($a, self::FIRST, true) === false, $a] <=> [array_search($b, self::FIRST, true) === false, $b]);
        $rows = [];
        foreach ($byLife as $life => $codes) {
            usort($codes, static function (string $a, string $b) use ($places): int {
                $ra = array_search($a, self::FIRST, true);
                $rb = array_search($b, self::FIRST, true);

                return [$ra === false ? 99 : $ra, Str::ascii($places[$a]['name'])] <=> [$rb === false ? 99 : $rb, Str::ascii($places[$b]['name'])];
            });
            $rows[] = ['life' => $life, 'count' => count($codes), 'places' => array_map(static fn (string $c): string => $places[$c]['name'], array_slice($codes, 0, 3))];
        }
        if ($us !== []) {
            $rows[] = ['life' => null, 'count' => count($us), 'places' => array_map(static fn (string $c): string => $places[$c]['name'], array_slice($us, 0, 3))];
        }

        return $rows;
    }

    /**
     * A song's makers with a known death year, as SongRights reads them (one living or of unknown death is self::unknown's).
     *
     * @param  array<string, mixed>  $data
     * @return list<array{name: string, role: string, died: int}>
     */
    private static function makers(array $data): array
    {
        $makers = [];
        foreach ((array) ($data['makers'] ?? []) as $maker) {
            if (! is_array($maker) || ! is_string($maker['name'] ?? null)) {
                continue;
            }
            if (! is_int($maker['died'] ?? null)) {
                continue;
            }
            $makers[] = ['name' => $maker['name'], 'role' => (string) ($maker['role'] ?? ''), 'died' => $maker['died']];
        }

        return $makers;
    }

    /** The year the United States' row frees everything published before (copyright-terms.json, the US's publicationBased). */
    public static function usBefore(): int
    {
        return (int) config('copyright_terms.countries.US.publicationBased.freeBefore', 1931);
    }

    /**
     * Who wrote a song's words and music (and the words we sing, when a translation), as the songs list names them; none
     * for a traditional song.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function writers(array $data): array
    {
        $names = [];
        foreach ((array) ($data['makers'] ?? []) as $maker) {
            if (is_array($maker) && is_string($maker['name'] ?? null) && in_array($maker['role'] ?? null, ['words', 'music', 'translation'], true)) {
                $names[] = $maker['name'];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * The year the last of a song's makers died (each has a known death here; a work always has one).
     *
     * @param  list<array<string, mixed>>  $makers
     */
    private static function lastDeath(array $makers): int
    {
        $last = 0;
        foreach ($makers as $maker) {
            $last = max($last, (int) $maker['died']);
        }

        return $last;
    }

    /**
     * A song's makers who are living or whose death we don't know, by name.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function unknown(array $data): array
    {
        $names = [];
        foreach ((array) ($data['makers'] ?? []) as $maker) {
            if (is_array($maker) && is_string($maker['name'] ?? null) && ! is_int($maker['died'] ?? null)) {
                $names[] = $maker['name'];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * A song's makers by person (one name can write the words and the music), with every part they had in it.
     *
     * @param  list<array<string, mixed>>  $makers
     * @return list<array{name: string, died: int, roles: list<string>, id: string|null}>
     */
    private static function byPerson(array $makers): array
    {
        $people = [];
        foreach ($makers as $maker) {
            $key = $maker['name'].'|'.$maker['died'];
            $people[$key] ??= ['name' => (string) $maker['name'], 'died' => (int) $maker['died'], 'roles' => [], 'id' => isset($maker['id']) ? (string) $maker['id'] : null];
            if (in_array($maker['role'], self::ROLES, true)) {
                $people[$key]['roles'][] = (string) $maker['role'];
            }
        }

        return array_values($people);
    }

    /**
     * @param  list<string>  $roles
     * @return list<string>
     */
    private static function ordered(array $roles): array
    {
        return array_values(array_filter(self::ROLES, static fn (string $role): bool => in_array($role, $roles, true)));
    }

    /** A maker's key on the roll: the name and the year of death. */
    private static function key(string $name, int $died): string
    {
        return $name.'|'.$died;
    }

    /** The name a roll sorts by: the last word before any bracket ("Vaughan Williams" sorts under W, as a roll would). */
    private static function surname(string $name): string
    {
        $plain = trim((string) preg_replace('/\s*[(\[].*$/', '', $name));
        $words = preg_split('/\s+/', $plain) ?: [$plain];

        return end($words).' '.$plain;
    }

    /**
     * A library maker's life as the page shows it: the name it's listed under (a second spelling of the same person
     * joins the first), the cited birth year, or false for one left off the roll (a death year that is a century).
     *
     * @param  array<string, array<string, mixed>>  $lives
     * @return array{key: string, name: string, born: int|null, source: string|null}|false
     */
    private static function life(array $lives, string $name, int $died): array|false
    {
        $key = self::key($name, $died);
        $life = $lives[$key] ?? [];
        if (($life['listed'] ?? true) === false) {
            return false;
        }
        if (is_string($life['same'] ?? null) && isset($lives[$life['same']])) {
            $key = $life['same'];
            $name = explode('|', $key)[0];
            $life = $lives[$key];
        }

        return [
            'key' => $key,
            'name' => $name,
            'born' => is_int($life['born'] ?? null) && is_string($life['source'] ?? null) ? $life['born'] : null,
            'source' => is_string($life['source'] ?? null) ? $life['source'] : null,
        ];
    }

    /**
     * The cited lives of our songs' makers (resources/data/public-domain/lives.json), by "name|died".
     *
     * @return array<string, array<string, mixed>>
     */
    public static function lives(): array
    {
        $lives = json_decode(self::file('lives.json'), true);

        return is_array($lives) ? array_filter($lives, 'is_array') : [];
    }

    /**
     * The works we don't have yet (resources/data/public-domain/coming.json): their people by id, each with a cited
     * death year (anyone without one is left out, and so is any work of theirs), a co-maker whose death we couldn't
     * confirm (`died: null`: never listed, and the work is never shown as free), and the works.
     *
     * @return array{people: array<string, array{name: string, born: int|null, died: int, source: string}>, unknown: array<string, string>, works: list<array{title: string, makers: list<array{person: string, role: string}>, firstPublished: array<string, mixed>|null}>}
     */
    public static function coming(): array
    {
        $file = json_decode(self::file('coming.json'), true);
        $file = is_array($file) ? $file : [];
        $people = [];
        $unknown = [];
        foreach ((array) ($file['people'] ?? []) as $person) {
            if (! is_array($person) || ! is_string($person['id'] ?? null) || ! is_string($person['name'] ?? null)) {
                continue;
            }
            if (is_int($person['died'] ?? null) && is_string($person['source'] ?? null) && $person['source'] !== '') {
                $people[$person['id']] = ['name' => $person['name'], 'born' => is_int($person['born'] ?? null) ? $person['born'] : null, 'died' => $person['died'], 'source' => $person['source']];
            } elseif (($person['died'] ?? null) === null) {
                $unknown[$person['id']] = $person['name'];
            }
        }
        $works = [];
        foreach ((array) ($file['works'] ?? []) as $work) {
            if (! is_array($work) || ! is_string($work['title'] ?? null) || ! is_array($work['makers'] ?? null)) {
                continue;
            }
            $makers = [];
            foreach ($work['makers'] as $maker) {
                if (is_array($maker) && is_string($maker['person'] ?? null) && is_string($maker['role'] ?? null)) {
                    $makers[] = ['person' => $maker['person'], 'role' => $maker['role']];
                }
            }
            $published = is_array($work['firstPublished'] ?? null) && is_int($work['firstPublished']['year'] ?? null) ? ['year' => $work['firstPublished']['year'], 'country' => (string) ($work['firstPublished']['country'] ?? '')] : null;
            $works[] = ['title' => $work['title'], 'makers' => $makers, 'firstPublished' => $published];
        }

        return ['people' => $people, 'unknown' => $unknown, 'works' => $works];
    }

    /** What the page is made of besides the songs: the two data files and the terms table. */
    private static function sourcesHash(): string
    {
        static $hash = null;

        return $hash ??= hash('xxh128', implode('|', [
            self::file('lives.json'),
            self::file('coming.json'),
            (string) json_encode(config('copyright_terms.countries')),
        ]));
    }

    /** One of the page's data files (resources/data/public-domain/), or empty when it isn't there. */
    private static function file(string $name): string
    {
        $path = resource_path('data/public-domain/'.$name);

        return is_file($path) ? (string) file_get_contents($path) : '';
    }
}
