<?php

declare(strict_types=1);

namespace App\Support\Templates;

use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Languages\SongWords;
use App\Support\Rights\RightsHere;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A song's versions (Hillel, 2026-11-18, "One song, US/UK switch"): where the UK sings a carol to another tune or
 * harmony, that version is its own song file (its own notes, makers, first printing and so its own rights, guides and
 * progress) whose `versionOf` names the song and the version's name: {"song": "it-came-upon-the-midnight-clear",
 * "name": "uk"}. The song keeps one page and one place in every list; a version is never listed on its own
 * (Catalogue::songs leaves it out), and its own address leads to its song's page with `?version=`.
 *
 * Which one a visitor starts on: the one they asked for (`?version=`, or the app's pick kept on the device), else the
 * one their country starts on (config song_versions.starts; Cloudflare's country, read per request, never stored),
 * else the song's own; never one withheld where they are while another is free there.
 */
final class SongVersions
{
    /** The page's query that picks a version. */
    public const PARAM = 'version';

    /** A version's name, as the query and the file write it. */
    public const NAME = '/^[a-z]{2,8}$/';

    public function __construct(private readonly Catalogue $catalogue, private readonly RightsHere $rights) {}

    /** The name a song's own file goes by beside its versions ("us"). */
    public static function base(): string
    {
        return (string) config('song_versions.base', 'us');
    }

    /**
     * The song a version belongs to and its name, from its file's `versionOf`; null for a song in its own right.
     *
     * @param  array<string, mixed>  $data  a song file's data
     * @return array{song: string, name: string}|null
     */
    public static function link(array $data): ?array
    {
        $of = $data['versionOf'] ?? null;

        return is_array($of) && is_string($of['song'] ?? null) && is_string($of['name'] ?? null) && preg_match(self::NAME, $of['name']) === 1
            ? ['song' => $of['song'], 'name' => $of['name']]
            : null;
    }

    /**
     * The song this template is a version of, by its current version's file; null for a song in its own right.
     *
     * @return array{song: string, name: string}|null
     */
    public static function of(Template $template): ?array
    {
        return $template->owner_id === null ? self::link((array) $template->currentVersion?->data) : null;
    }

    /** Whether a country starts on this version (config song_versions.starts). */
    public static function startsIn(string $name, string $country): bool
    {
        return $country !== '' && in_array($country, (array) config('song_versions.starts.'.$name, []), true);
    }

    /**
     * Every song's other versions, by the song's slug: each one's name and its own file's slug, in name order. Kept a
     * day under the catalogue's fingerprint (Catalogue::kept), so a new version shows at once.
     *
     * @return array<string, list<array{name: string, slug: string}>>
     */
    public function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }
        // One light read (a slug and its file's link per version, never their notes): a song page asks on every visit.
        $rows = DB::table('templates')->join('template_versions', 'template_versions.id', '=', 'templates.current_version_id')
            ->whereNull('templates.owner_id')->whereNull('templates.withdrawn_at')->whereNotNull('template_versions.data->versionOf')
            ->orderBy('templates.slug')->get(['templates.slug', 'template_versions.data->versionOf as of']);

        return $this->map = self::grouped(array_values($rows->map(static fn (object $row): array => ['slug' => (string) $row->slug, 'link' => self::link(['versionOf' => json_decode((string) $row->of, true)])])->all()));
    }

    /**
     * Each song's other versions, by the song's slug, in name order.
     *
     * @param  list<array{slug: string, link: array{song: string, name: string}|null}>  $versions
     * @return array<string, list<array{name: string, slug: string}>>
     */
    private static function grouped(array $versions): array
    {
        $map = [];
        foreach ($versions as $version) {
            if ($version['link'] !== null && $version['link']['name'] !== self::base()) {
                $map[$version['link']['song']][] = ['name' => $version['link']['name'], 'slug' => $version['slug']];
            }
        }
        foreach ($map as &$others) {
            usort($others, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);
        }

        return $map;
    }

    /** @var array<string, list<array{name: string, slug: string}>>|null */
    private ?array $map = null;

    /**
     * A song's versions, its own first ({name: "us", slug: the song's}), or [] when it has none.
     *
     * @return list<array{name: string, slug: string}>
     */
    public function all(string $slug): array
    {
        $others = $this->map()[$slug] ?? [];

        return $others === [] ? [] : [['name' => self::base(), 'slug' => $slug], ...$others];
    }

    /**
     * The song's versions as templates (the song's own first), each live with a current version; the song alone when it
     * has none.
     *
     * @return list<array{name: string, template: Template}>
     */
    public function templates(Template $song): array
    {
        $all = $this->all((string) $song->slug);
        if ($all === []) {
            return [['name' => self::base(), 'template' => $song]];
        }
        // Only this song's versions, their current versions read by id (not every version in the catalogue).
        $others = Template::query()->listed()->whereNotNull('current_version_id')->whereIn('slug', array_column(array_slice($all, 1), 'slug'))->get()->keyBy('slug');
        $current = TemplateVersion::query()->whereIn('id', $others->pluck('current_version_id')->all())->get()->keyBy('id');
        $others->each(static fn (Template $template) => $template->setRelation('currentVersion', $current->get($template->current_version_id)));

        return [['name' => self::base(), 'template' => $song], ...array_values(array_filter(array_map(
            static fn (array $version): ?array => ($template = $others->get($version['slug'])) instanceof Template ? ['name' => $version['name'], 'template' => $template] : null,
            array_slice($all, 1),
        )))];
    }

    /**
     * The version a visitor sees: `$asked` when it's one of them; else the one their country starts on, where it isn't
     * withheld; else the song's own, where it isn't; else the first that's free where they are; else the song's own.
     *
     * @param  list<array{name: string, template: Template}>  $versions  self::templates()
     * @return array{name: string, template: Template}
     */
    public function choose(array $versions, ?string $asked, string $country): array
    {
        $byName = array_column($versions, null, 'name');
        if ($asked !== null && isset($byName[$asked])) {
            return $byName[$asked];
        }
        $free = array_values(array_filter($versions, fn (array $version): bool => $this->rights->withheld($version['template'], $country) === null));
        foreach ($free as $version) {
            if (self::startsIn($version['name'], $country)) {
                return $version;
            }
        }

        return in_array($versions[0], $free, true) ? $versions[0] : ($free[0] ?? $versions[0]);
    }

    /**
     * The app's catalogue (templates.catalogue) with each song's versions: a song with another version free here carries
     * `songVersions`, every one free here with its id, name and whether it's the one people here start on (`here`); a song
     * withheld here whose version is free here is listed as that version (its own entry, still with `songVersions` when more
     * than one is free). Admins and editors (`$everything`) see every version.
     *
     * @param  list<array<string, mixed>>  $songs  Catalogue::listing(), before songs withheld here are left out
     * @param  list<string>  $withheld  the songs withheld here (RightsHere::withheldSlugs)
     * @return list<array<string, mixed>>
     */
    public function catalogue(array $songs, array $withheld, string $country, bool $everything): array
    {
        // From the catalogue's own kept read (its versions' rows carry their link), never a read of its own.
        $rows = $this->catalogue->versionListing();
        $map = self::grouped(array_values(array_map(static fn (string $slug, array $row): array => ['slug' => $slug, 'link' => $row['_versionOf'] ?? null], array_keys($rows), $rows)));
        // Their files, for their rights here, only where it can matter: the switch on, and a version to check.
        $templates = $map === [] || $everything || ! $this->rights->on() ? collect() : $this->catalogue->versions()->keyBy('slug');
        $free = fn (string $slug): bool => $everything || ! $this->rights->on() || (($template = $templates->get($slug)) instanceof Template && $this->rights->withheld($template, $country) === null);

        $listed = [];
        foreach ($songs as $song) {
            $id = (string) $song['id'];
            $own = $everything || ! in_array($id, $withheld, true);
            $others = array_values(array_filter($map[$id] ?? [], static fn (array $version): bool => isset($rows[$version['slug']]) && $free($version['slug'])));
            if ($others === []) {
                if ($own) {
                    $listed[] = $song;
                }

                continue;
            }
            $all = [...($own ? [['name' => self::base(), 'slug' => $id]] : []), ...$others];
            $start = array_values(array_filter($all, static fn (array $version): bool => self::startsIn($version['name'], $country)))[0] ?? $all[0];
            $entry = $own ? $song : $rows[$all[0]['slug']];
            unset($entry['_versionOf']);
            if (count($all) > 1) {
                $entry['songVersions'] = array_map(static fn (array $version): array => ['id' => $version['slug'], 'name' => $version['name'], 'here' => $version['slug'] === $start['slug']], $all);
            }
            $listed[] = $entry;
        }

        return $listed;
    }

    /**
     * A version's own address (/songs/<version>, or one of its parts') leads to its song's, showing it: for good (301),
     * keeping the page's language prefix and part.
     *
     * @param  array{song: string, name: string}  $link
     */
    public static function redirect(Request $request, string $slug, array $link): RedirectResponse
    {
        $path = (string) preg_replace('#/songs/'.preg_quote($slug, '#').'(?=/|$)#', '/songs/'.$link['song'], $request->getPathInfo(), 1);

        return redirect()->to($path.'?'.http_build_query([self::PARAM => $link['name']]), 301);
    }

    /**
     * The name the request asks for (`?version=`), or null.
     */
    public static function asked(mixed $value): ?string
    {
        return is_string($value) && preg_match(self::NAME, $value) === 1 ? $value : null;
    }

    /**
     * The versions as a page's switch shows them (and its structured data names them): each one's name and label ("US",
     * "UK"), the page's address with `?version=`, whether it's the one shown, its tune's credit and its first printing's
     * year. [] for a song with one version.
     *
     * @param  list<array{name: string, template: Template}>  $versions  self::templates()
     * @param  string  $url  the page's own address, without a query
     * @return list<array{name: string, label: string, url: string, current: bool, tune: string|null, year: int|null}>
     */
    public static function forPage(array $versions, string $chosen, string $url): array
    {
        if (count($versions) < 2) {
            return [];
        }

        return array_map(static function (array $version) use ($chosen, $url): array {
            $data = (array) $version['template']->currentVersion?->data;
            $tune = $data['credits']['tune'] ?? null;
            $year = $data['firstPublished']['year'] ?? null;

            return [
                'name' => $version['name'],
                'label' => trans()->has('pages.song.versions.'.$version['name']) ? (string) __('pages.song.versions.'.$version['name']) : strtoupper($version['name']),
                'url' => $url.'?'.http_build_query([self::PARAM => $version['name']]),
                'current' => $version['name'] === $chosen,
                'tune' => is_string($tune) ? SongWords::credit($tune) : null,
                'year' => is_int($year) ? $year : null,
            ];
        }, $versions);
    }
}
