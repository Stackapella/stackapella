<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Support\Http\Hosts;
use App\Support\Pages\JewishSongs;
use App\Support\Pages\Landing;
use App\Support\Pages\PageGone;
use App\Support\Pages\SongCollections;
use App\Support\Site\LinkCardLanes;
use Illuminate\Http\Response;

/**
 * A song collection (/songs/<collection>, App\Support\Pages\SongCollections): its open songs as cards, as the carols
 * page shows them. With too few open songs, or none, a real 404 that points to /songs. No session; cached five minutes
 * like the song pages.
 */
final class SongCollectionPageController extends Controller
{
    public function __invoke(string $collection, SongCollections $collections): Response
    {
        $songs = $collections->songs($collection);

        if ($songs === []) {
            return PageGone::response('/songs');
        }

        $jewish = $collection === JewishSongs::SLUG;
        $festival = $jewish ? JewishSongs::festival() : null;
        $chips = $jewish ? self::festivalChips(array_column($songs, 'slug')) : null;
        // The coming festival's songs first (Hanukkah's in the weeks before it), the rest in their order.
        if ($festival !== null && $chips !== null) {
            $name = (string) __('site.collections.jewish-songs.festivals.'.$festival['key']);
            usort($songs, static fn (array $a, array $b): int => (($chips[$b['slug']] ?? '') === $name) <=> (($chips[$a['slug']] ?? '') === $name));
        }

        return response()->view(SongCollections::VIEW, [
            'slug' => $collection,
            'songs' => $songs,
            'canonical' => Hosts::publicUrl().SongCollections::path($collection),
            // The Jewish songs page's own: its countdown to the next festival, and each card's festival from its genre.
            'festival' => $festival,
            'chips' => $chips,
            // Beside the heading (the width design): the song on its link card, its opening in four parts to switch,
            // that card's part lit. The Jewish page has its countdown there instead.
            'try' => $festival === null ? self::tryLine($collection) : null,
        ], 200, SongPagesController::headers());
    }

    /**
     * The collection's own song to try (LinkCardLanes::SONGS, the one its link card draws), its part as "you", or null
     * when that song isn't in the library.
     *
     * @return array<string, mixed>|null
     */
    private static function tryLine(string $collection): ?array
    {
        [$slug, $part] = LinkCardLanes::SONGS[$collection] ?? [null, null];
        $template = $slug === null ? null : Template::query()->official()->with('currentVersion')->where('slug', $slug)->first();

        return $template === null ? null : Landing::line($template, null, $part);
    }

    /**
     * Each song's festival, in the page's words, by slug.
     *
     * @param  list<string>  $slugs
     * @return array<string, string>
     */
    private static function festivalChips(array $slugs): array
    {
        return Template::query()->with('currentVersion')->whereIn('slug', $slugs)->get()
            ->mapWithKeys(static fn (Template $t): array => [(string) $t->slug => (string) __('site.collections.jewish-songs.festivals.'.JewishSongs::tag(array_values(array_filter((array) (((array) $t->currentVersion?->data)['genre'] ?? []), 'is_string'))))])
            ->all();
    }
}
