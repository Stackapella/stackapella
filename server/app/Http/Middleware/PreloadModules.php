<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Site\ToolPreloads;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A public page's module scripts, fetched at once (docs/performance.md, "Public modules at once"). Our modules are
 * no-cache (nginx: every view revalidates them, so a deploy swaps them cleanly), and each imports the next, one round
 * trip a level on a phone. For every <script type="module" src="/js/..."> an HTML page carries (site.js on every
 * page, a tool's page.js, a course page's demo), this names the script's whole static import graph in the page's
 * <head> as <link rel="modulepreload"> (web/js/public/tools/preloads.json, via ToolPreloads), so the chain comes at
 * once beside the stylesheet. The same unversioned addresses the imports ask for, so nothing about a deploy changes;
 * a link the page already has is never doubled, and a page with no module script, or anything but a 200 HTML page,
 * is left exactly as it was.
 */
final class PreloadModules
{
    private const ENTRY = '#<script type="module" src="(/js/[A-Za-z0-9/_.-]+\.js)(?:\?[^"]*)?"#';

    private const HAVE = '#<link rel="modulepreload" href="(/js/[A-Za-z0-9/_.-]+\.js)(?:\?[^"]*)?"#';

    /** Enough for every graph a page has today; a runaway list never fills a head. */
    private const MOST = 80;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200 || ! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $html = $response->getContent();

        if (! is_string($html) || ! str_contains($html, '<script type="module" src="/js/') || ! str_contains($html, '</head>')) {
            return $response;
        }

        $links = self::links($html);

        if ($links !== []) {
            $at = (int) strpos($html, '</head>');
            // The view stays the response's original (what tests and anything after read), only its bytes gain the links.
            $original = $response instanceof IlluminateResponse ? $response->original : null;
            $response->setContent(substr($html, 0, $at).implode('', $links).substr($html, $at));
            if ($response instanceof IlluminateResponse) {
                $response->original = $original;
            }
            $response->headers->remove('Content-Length');
        }

        return $response;
    }

    /**
     * The modulepreload links the page doesn't have yet, for its module scripts' graphs, in the scripts' order.
     *
     * @return list<string>
     */
    public static function links(string $html): array
    {
        preg_match_all(self::ENTRY, $html, $entries);
        preg_match_all(self::HAVE, $html, $have);
        $seen = array_fill_keys($have[1], true);
        $links = [];

        foreach ($entries[1] as $entry) {
            foreach (ToolPreloads::for($entry) as $module) {
                if (isset($seen[$module]) || count($links) >= self::MOST) {
                    continue;
                }
                $seen[$module] = true;
                $links[] = '<link rel="modulepreload" href="'.e($module).'">'."\n";
            }
        }

        return $links;
    }
}
