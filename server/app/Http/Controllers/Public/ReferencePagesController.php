<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Pages\Landing;
use App\Support\Pages\PageGone;
use App\Support\Pages\Reference;
use App\Support\Pages\SongParts;
use App\Support\Site\SitePages;
use App\Support\Site\StructuredData;
use App\Support\Site\ToolPreloads;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

/**
 * The reference pages (docs/seo-growth-plan.md item 4): /intervals, /chords and /voice-types list their family, and
 * /<family>/<slug> is one interval, chord type or voice type: the searched heading, frontend's card to hear it and see
 * it (js/public/reference-cards.js, mounted by js/public/tools/page.js), a short guide, the family's other pages, a
 * short FAQ, About Stackapella and the free tools. Each family's links carry its ?ref= tag (counts.sources). No session;
 * cached like the other public pages.
 */
final class ReferencePagesController extends Controller
{
    public function index(Request $request, string $kind): Response
    {
        if (Reference::slugs($kind) === []) {
            return PageGone::response('/tools');
        }

        $words = Reference::index($kind);
        $meta = ['title' => SitePages::title((string) $words['title']), 'description' => (string) $words['description'], 'url' => Hosts::publicUrl().'/'.$kind];
        $rows = Reference::rows($kind);

        return response()->view('site.reference-index', [
            'title' => $meta['title'],
            'meta' => $meta,
            // The family as a list with its trail, and its questions, for search.
            'ld' => [...StructuredData::tools((string) $words['eyebrow'], $meta['url'], array_map(static fn (array $row): array => ['name' => $row['name'], 'url' => $row['url']], $rows)), ...self::faq(self::pairs($words['faq'] ?? []))],
            'area' => 'tools',
            'kind' => $kind,
            'words' => $words,
            'rows' => $rows,
            'directory' => SitePages::directory(null, Reference::related($kind)),
            'links' => Landing::links(Landing::ref($request->query('ref'))),
            'try' => self::tryUrl($kind),
        ], 200, SongPagesController::headers());
    }

    /** The page's {slug} comes first: Laravel hands the address's parameters over before the route's defaults (kind). */
    public function show(Request $request, string $slug, string $kind, SongParts $parts): Response
    {
        if (! in_array($slug, Reference::slugs($kind), true)) {
            return PageGone::response('/'.$kind);
        }

        $index = Reference::index($kind);
        $page = Reference::page($kind, $slug);
        $meta = ['title' => SitePages::title((string) $page['title']), 'description' => (string) $page['description'], 'url' => Hosts::publicUrl().'/'.$kind.'/'.$slug];
        $trail = StructuredData::breadcrumbs([[(string) $index['eyebrow'], Hosts::publicUrl().'/'.$kind], [(string) $page['name'], $meta['url']]]);

        return response()->view('site.reference', [
            'title' => $meta['title'],
            'meta' => $meta,
            'ld' => [...self::faq(self::pairs($page['faq'] ?? [])), $trail],
            'area' => 'tools',
            'kind' => $kind,
            'index' => $index,
            'page' => $page,
            'card' => Reference::card($kind, $slug),
            'mount' => Reference::FAMILIES[$kind]['mount'],
            'module' => Reference::MODULE,
            'preloads' => ToolPreloads::for(Reference::MODULE),
            'data' => Reference::data($kind, $slug),
            // A voice type's page lists song parts that sit inside its range, each with its own page.
            'songs' => $kind === 'voice-types' && SongParts::open() ? $parts->within(Reference::VOICE_TYPES[$slug][2], Reference::VOICE_TYPES[$slug][3], Reference::VOICE_PARTS[$slug]) : [],
            'others' => array_values(array_filter(Reference::rows($kind), static fn (array $row): bool => $row['url'] !== $page['url'])),
            'directory' => SitePages::directory(null, Reference::related($kind)),
            'links' => Landing::links(Landing::ref($request->query('ref'))),
            'try' => self::tryUrl($kind),
        ], 200, SongPagesController::headers());
    }

    /**
     * A page's questions as question and answer pairs.
     *
     * @return list<array{string, string}>
     */
    private static function pairs(mixed $faq): array
    {
        $pairs = [];
        foreach (is_array($faq) ? $faq : [] as $qa) {
            if (is_array($qa) && is_string($qa[0] ?? null) && is_string($qa[1] ?? null)) {
                $pairs[] = [$qa[0], $qa[1]];
            }
        }

        return $pairs;
    }

    /**
     * The questions as search reads them (a FAQPage), when there are any.
     *
     * @param  list<array{string, string}>  $faq
     * @return list<array<string, mixed>>
     */
    private static function faq(array $faq): array
    {
        return $faq === [] ? [] : [[
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $qa): array => ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]], $faq),
        ]];
    }

    /** The family's way into the app (the tools' own, the free course), carrying the family's ?ref= tag. */
    private static function tryUrl(string $kind): string
    {
        $to = (string) Lang::get('site.tools.index.try_to');

        return Hosts::app($to.(str_contains($to, '?') ? '&' : '?').http_build_query(['ref' => $kind]));
    }
}
