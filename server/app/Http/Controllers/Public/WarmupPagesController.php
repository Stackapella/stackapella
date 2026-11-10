<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Pages\Landing;
use App\Support\Pages\PageGone;
use App\Support\Pages\Warmups;
use App\Support\Site\SitePages;
use App\Support\Site\StructuredData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

/**
 * The free warm-ups (docs/seo-growth-plan.md item 8): /warmups lists them and /warmups/<warm-up> is one,
 * the player above the fold (js/public/tools/warmup-player.js, on the tools' layout) with the courses
 * lead's instruction and stop line, then how to sing it, a short FAQ and one way into the app
 * (?ref=warmups, one source in the counts). No session; cached like the other public pages.
 */
final class WarmupPagesController extends Controller
{
    public function index(Request $request): Response
    {
        $ids = Warmups::ids();
        if ($ids === []) {
            return PageGone::response('/tools');
        }

        $words = (array) Lang::get('site.warmups.index');
        $meta = ['title' => SitePages::title((string) $words['title']), 'description' => (string) $words['description'], 'url' => Hosts::publicUrl().'/warmups'];
        $warmups = array_map(static fn (string $id): array => [...self::words($id), 'url' => '/warmups/'.$id], $ids);
        $faq = self::pairs($words['faq'] ?? []);

        return response()->view('site.warmups', [
            'title' => $meta['title'],
            'meta' => $meta,
            // The warm-ups as a list with its trail, and its questions, for search.
            'ld' => [...StructuredData::tools((string) $words['eyebrow'], $meta['url'], array_map(static fn (array $w): array => ['name' => (string) $w['name'], 'url' => $w['url']], $warmups)), ...self::faq($faq)],
            'area' => 'tools',
            'words' => $words,
            'warmups' => $warmups,
            'links' => Landing::links(Landing::ref($request->query('ref'))),
            'try' => self::tryUrl((string) $words['try_to'], 'warmups'),
        ], 200, SongPagesController::headers());
    }

    public function show(Request $request, string $warmup): Response
    {
        $found = Warmups::find($warmup);
        if ($found === null) {
            return PageGone::response('/warmups');
        }

        $page = self::words($warmup);
        $index = (array) Lang::get('site.warmups.index');
        $meta = ['title' => SitePages::title((string) $page['title']), 'description' => (string) $page['description'], 'url' => Hosts::publicUrl().'/warmups/'.$warmup];
        // A free web app with its questions, as a tool page tells search; its trail runs through /warmups.
        $ld = StructuredData::tool((string) $page['name'], $meta, self::pairs($page['faq'] ?? []));
        $trail = StructuredData::breadcrumbs([[(string) $index['eyebrow'], Hosts::publicUrl().'/warmups'], [(string) $page['name'], $meta['url']]]);
        $ld = array_map(static fn (array $item): array => ($item['@type'] ?? null) === 'BreadcrumbList' ? $trail : $item, $ld);

        return response()->view('site.warmup', [
            'title' => $meta['title'],
            'meta' => $meta,
            'ld' => $ld,
            'area' => 'tools',
            'page' => $page,
            'index' => $index,
            'card' => (array) Lang::get('site.warmups.card'),
            'others' => array_values(array_map(static fn (string $id): array => [...self::words($id), 'url' => '/warmups/'.$id], array_filter(Warmups::ids(), static fn (string $id): bool => $id !== $warmup))),
            'directory' => SitePages::directory(),
            'links' => Landing::links(Landing::ref($request->query('ref'))),
            'try' => self::tryUrl((string) $index['try_to'], 'warmups'),
            'data' => ['warmup' => $found],
        ], 200, SongPagesController::headers());
    }

    /**
     * A warm-up's page words, with its slug.
     *
     * @return array<string, mixed>
     */
    private static function words(string $id): array
    {
        return [...(array) Lang::get('site.warmups.pages.'.$id), 'slug' => $id];
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

    /** The page's one way into the app (the free course, no account), carrying its ?ref= tag. */
    private static function tryUrl(string $path, string $ref): string
    {
        return Hosts::app($path.(str_contains($path, '?') ? '&' : '?').http_build_query(['ref' => $ref]));
    }
}
