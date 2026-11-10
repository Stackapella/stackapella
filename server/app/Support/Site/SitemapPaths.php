<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Actions\Choirs\Choirs;
use App\Actions\Ideas\Ideas;
use App\Http\Controllers\Public\DocumentPagesController;
use App\Models\Choir;
use App\Models\PageVersion;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Pages\Carols;
use App\Support\Pages\Lessons;
use App\Support\Pages\Reference;
use App\Support\Pages\SongParts;
use App\Support\Pages\Warmups;
use App\Support\Templates\Catalogue;
use Illuminate\Support\Carbon;

/**
 * The sitemap's pages (docs/go-to-market-plan.md, "Search"), as paths on the public site, and when
 * each last changed in words (its lastmod): a song's page from its current version, an idea's not
 * at all, and every other page from app:sitemap:dates' hash of what a reader sees (page_versions).
 */
final class SitemapPaths
{
    /** Ideas in the sitemap, newest first, leaving room under its 50,000 for everything else. */
    public const MOST_IDEAS = 45000;

    public function __construct(private readonly Ideas $ideas, private readonly Choirs $choirs) {}

    /**
     * @return list<string>
     */
    public function all(): array
    {
        $paths = ['/', '/songs', '/pricing', '/help', ...array_map(static fn (string $slug): string => '/help/'.$slug, HelpGuide::slugs())];

        // The public site's own pages (docs/public-site-plan.md), coming-soon ones too, and the
        // comparison pages once their switch is on.
        $paths = [...$paths, ...SitePages::paths(), ...SitePages::comparePaths()];
        // The free warm-ups (docs/seo-growth-plan.md item 8), each one that can play.
        // The reference pages (docs/seo-growth-plan.md item 4): each family's index and pages, once written.
        $paths = [...$paths, ...Reference::paths()];
        // The courses' lesson pages (docs/seo-lesson-pages-plan.md), each open course's.
        $paths = [...$paths, ...Lessons::paths()];
        $warmups = Warmups::ids();
        if ($warmups !== []) {
            $paths = [...$paths, '/warmups', ...array_map(static fn (string $id): string => '/warmups/'.$id, $warmups)];
        }

        // The songs the site may show (Catalogue::open()): one waiting for its Monday isn't listed.
        foreach (app(Catalogue::class)->open()->pluck('slug')->sort()->values() as $slug) {
            $paths[] = '/songs/'.$slug;
        }
        // Each part of a live, reviewed, public-domain song, once its page exists.
        if (SongParts::open()) {
            $paths = [...$paths, ...app(SongParts::class)->paths()];
        }
        // The Christmas carols page, while a carol is open.
        if (app(Carols::class)->songs() !== []) {
            $paths[] = Carols::PATH;
        }

        // Ideas on the shelf and the choirs people have sung in, while each is open.
        // One sitemap holds at most 50,000 addresses: the newest ideas fill what's left.
        if ($this->ideas->isOpen()) {
            foreach ($this->ideas->listed()->orderByDesc('id')->limit(self::MOST_IDEAS)->pluck('public_id') as $id) {
                $paths[] = '/ideas/'.$id;
            }
        }

        if ($this->choirs->isOpen()) {
            $open = app(Catalogue::class)->open()->pluck('slug')->all();
            $slugs = Template::query()->listed()->whereNull('deleted_at')->whereIn('id', Choir::query()->whereHas('voices', fn ($voices) => $voices->whereNotNull('in_mix_at'))->select('template_id'))->orderBy('slug')->pluck('slug')
                ->intersect($open);

            foreach ($slugs as $slug) {
                $paths[] = '/choirs/'.$slug;
            }
        }

        foreach (DocumentPagesController::guides() as $guide) {
            $paths[] = '/guides/'.$guide;
        }

        // Open source's pages, once the flag is on (docs/open-source-plan.md, 5.1).
        $legal = DocumentPagesController::LEGAL;
        if (OpenSource::on()) {
            $paths = [...$paths, ...OpenSource::PAGES];
            $legal = [...$legal, ...DocumentPagesController::OPEN_SOURCE_LEGAL];
        }

        foreach ($legal as $page) {
            if (is_file(resource_path("legal/en/{$page}.md"))) {
                $paths[] = '/legal/'.$page;
            }
        }

        return array_values(array_map('strval', $paths));
    }

    /**
     * The pages app:sitemap:dates reads: all but a song's (its version says when it changed) and an
     * idea's (tens of thousands, and no lastmod is better than a guessed one).
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    public static function hashed(array $paths): array
    {
        return array_values(array_filter($paths, static fn (string $path): bool => ! str_starts_with($path, '/songs/') && ! str_starts_with($path, '/ideas/')));
    }

    /**
     * Each page's lastmod, a W3C date (UTC), for the pages that have one.
     *
     * @return array<string, string>
     */
    public function lastModified(): array
    {
        $dates = [];

        foreach (PageVersion::query()->get(['path', 'changed_at']) as $page) {
            $dates[$page->path] = $page->changed_at->copy()->utc()->toDateString();
        }

        $songs = TemplateVersion::query()->join('templates', 'templates.current_version_id', '=', 'template_versions.id')
            ->whereNotNull('template_versions.created_at')->pluck('template_versions.created_at', 'templates.slug');

        foreach ($songs as $slug => $created) {
            $dates['/songs/'.$slug] = Carbon::parse((string) $created, 'UTC')->toDateString();
        }

        if (SongParts::open()) {
            $dates = [...$dates, ...app(SongParts::class)->lastModified()];
        }
        $carols = app(Carols::class)->lastModified();
        if ($carols !== null) {
            $dates[Carols::PATH] = $carols;
        }

        return $dates;
    }
}
