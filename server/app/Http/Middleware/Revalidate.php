<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public, read-mostly reads (the catalogue, the gallery, the ideas shelf, a choir, the prices):
 * kept in the browser's own cache but always checked again, by an ETag of the answer, so a repeat
 * read costs a 304 instead of the whole body (docs/performance.md). Only the viewer's browser keeps
 * it (`private`), as the answer can name what this viewer liked or unlocked.
 */
final class Revalidate
{
    public const ATTRIBUTE = 'cache.revalidate';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200 || $response->headers->has('ETag')) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return $response;
        }

        $request->attributes->set(self::ATTRIBUTE, true);
        $response->setEtag(hash('xxh128', $content));

        // A match becomes a 304 with no body (keeping the headers a cache needs).
        $response->isNotModified($request);

        return $response;
    }
}
