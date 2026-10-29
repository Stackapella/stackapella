<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Never cached anywhere: the admin, the API and anything personal. A public read marked to
 * revalidate (Revalidate) is kept only by the viewer's browser, and always checked again.
 */
final class NoStore
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Cache-Control', $request->attributes->get(Revalidate::ATTRIBUTE) === true ? 'private, no-cache' : 'no-store, private');

        return $response;
    }
}
