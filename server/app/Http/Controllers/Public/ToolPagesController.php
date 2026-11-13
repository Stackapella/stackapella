<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Pages\Landing;
use App\Support\Site\AppWords;
use App\Support\Site\SitePages;
use App\Support\Tools\NoteChart;
use App\Support\Tools\ToolPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

/**
 * Free tools for musicians (docs/tools-plan.md): /tools lists them and /tools/<tool> is one tool, above
 * the fold, with how to use it, a short FAQ and one way into the app written for it (?ref=tool-<tool>).
 * No session; cached like the other public pages. Only the tools that are built are here (SitePages::TOOLS).
 */
final class ToolPagesController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if (SitePages::listed() === []) {
            return response()->view('public.page-gone', ['links' => ['app' => '/features']], 404, SongPagesController::headers());
        }
        // Until there are enough tools for a hub (SitePages::HUB_FROM), /tools leads to the first one, for now.
        if (! SitePages::hubLive()) {
            return redirect(SitePages::toolsHome(), 302);
        }

        $words = (array) Lang::get('site.tools.index');

        return response()->view('site.tools', [
            'title' => SitePages::title((string) $words['title']),
            'meta' => ['title' => SitePages::title((string) $words['title']), 'description' => (string) $words['description'], 'url' => Hosts::publicUrl().'/tools'],
            'area' => 'tools',
            'words' => $words,
            'tools' => SitePages::tools(),
            // Hillel's tools page (2026-11-12): every live tool in its group, and Most used.
            'groups' => SitePages::directory()['groups'],
            'most' => self::mostUsed(),
            // Tagged even for a visitor with no ?ref= (one from search), so the brand row's Open the app is counted.
            'links' => Landing::links(Landing::ref($request->query('ref')) ?? 'tools'),
            'try' => self::tryUrl((string) $words['try_to'], 'tools'),
        ], 200, SongPagesController::headers());
    }

    /**
     * The hub's Most used, in SitePages::MOST_USED's order, live ones only.
     *
     * @return list<array<string, mixed>>
     */
    private static function mostUsed(): array
    {
        $live = array_column(SitePages::tools(), null, 'slug');

        return array_values(array_filter(array_map(static fn (string $slug): ?array => $live[$slug] ?? null, SitePages::MOST_USED)));
    }

    /**
     * /tools/embed: a free tool for a choir's, school's or teacher's website (docs/seo-growth-plan.md, item 2), one line
     * of code with a live preview and the look chosen from the themes. Only the tools that are both live and embeddable.
     */
    public function embed(Request $request): Response
    {
        $tools = array_values(array_intersect((array) config('tools.embeddable'), SitePages::TOOLS));
        if ($tools === []) {
            return response()->view('public.page-gone', ['links' => ['app' => '/tools']], 404, SongPagesController::headers());
        }

        $words = (array) Lang::get('site.tools.embed');
        $themes = [];
        foreach (['stage', 'warm', 'midnight', 'graphite'] as $family) {
            foreach (['dark', 'light'] as $mode) {
                $themes[$family.'-'.$mode] = AppWords::get('appearance.colour.'.$family.'.'.$mode);
            }
        }

        return response()->view('site.tools-embed', [
            'title' => SitePages::title((string) $words['title']),
            'meta' => ['title' => SitePages::title((string) $words['title']), 'description' => (string) $words['description'], 'url' => Hosts::publicUrl().'/tools/embed'],
            'area' => 'tools',
            'words' => $words,
            'maker' => [
                // The code carries the site's public address; the preview loads it from this page's own.
                'base' => Hosts::publicUrl().'/embed/tools/',
                'preview' => '/embed/tools/',
                'tools' => array_map(static fn (string $slug): array => [
                    'slug' => $slug,
                    'name' => (string) SitePages::tool($slug)['name'],
                    'listens' => in_array($slug, (array) config('tools.listening'), true),
                ], $tools),
                'themes' => $themes,
                'title' => (string) $words['preview_title'],
                'copied' => (string) $words['copied'],
            ],
            'links' => Landing::links(Landing::ref($request->query('ref')) ?? 'tools-embed'),
            'try' => self::tryUrl('/learn/1-comfortable', 'tools-embed'),
        ], 200, SongPagesController::headers());
    }

    public function show(Request $request, string $tool, ToolPageData $data): Response
    {
        // A tool, or a variant of one (SitePages::TOOL_VARIANTS: the tool with a preset for one search).
        $variant = in_array($tool, SitePages::variants(), true);
        if (! $variant && ! in_array($tool, SitePages::TOOLS, true)) {
            return response()->view('public.page-gone', ['links' => ['app' => '/tools']], 404, SongPagesController::headers());
        }

        $page = $variant ? SitePages::variant($tool) : SitePages::tool($tool);
        $base = $variant ? (string) $page['tool'] : $tool;

        return response()->view('site.tool', [
            'title' => SitePages::title((string) $page['title']),
            'meta' => ['title' => SitePages::title((string) $page['title']), 'description' => (string) $page['description'], 'url' => Hosts::publicUrl().'/tools/'.$tool],
            'area' => 'tools',
            'page' => $page,
            'directory' => SitePages::directory($tool),
            'links' => Landing::links(Landing::ref($request->query('ref')) ?? 'tool-'.$tool),
            'try' => self::tryUrl((string) $page['try_to'], 'tool-'.$tool),
            'result' => self::result($page),
            'lesson' => self::lesson($page, $tool),
            'data' => [...$data->for($base), ...($variant ? ['preset' => $page['preset']] : [])],
            'chart' => is_array($page['chart'] ?? null) ? NoteChart::rows() : null,
            // Its variants under the how-to (on a variant, the other variants of its tool).
            'also' => array_values(array_filter(SitePages::variantsOf($base), static fn (array $other): bool => $other['slug'] !== $tool)),
        ], 200, SongPagesController::headers());
    }

    /**
     * A tool that reports a result (the range test's voice type) has a second way in, for that result: the band's
     * line and link, the app address, the part for each result, and the result's own words.
     *
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>|null
     */
    private static function result(array $page): ?array
    {
        $result = $page['result'] ?? null;
        if (! is_array($result)) {
            return null;
        }

        return ['line' => $result['line'], 'link' => $result['link'], 'to' => self::tryUrl((string) $result['to'], 'tool-'.$page['slug']), 'parts' => (array) ($result['parts'] ?? []), 'types' => (array) ($page['card'] ?? [])];
    }

    /**
     * The free lesson a tool leads to once it has a result (js/public/tools/page.js shows it in the tool's bar): its line,
     * its link's words and the app address with the tool's ?ref= tag; null for a tool with none (the metronome).
     *
     * @param  array<string, mixed>  $page
     * @return array{line: string, link: string, to: string}|null
     */
    private static function lesson(array $page, string $tool): ?array
    {
        $lesson = $page['lesson'] ?? null;
        if (! is_array($lesson)) {
            return null;
        }

        return ['line' => (string) $lesson['line'], 'link' => (string) $lesson['link'], 'to' => self::tryUrl((string) $lesson['to'], 'tool-'.$tool)];
    }

    /** The tool's one way into the app (a lesson or a song, free with no account), carrying its ?ref= tag. */
    private static function tryUrl(string $path, string $ref): string
    {
        return Hosts::app($path.(str_contains($path, '?') ? '&' : '?').http_build_query(['ref' => $ref]));
    }
}
