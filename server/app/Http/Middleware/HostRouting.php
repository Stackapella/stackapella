<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Http\Hosts;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Each page on its own host (docs/app-subdomain-plan.md): a public page asked for on the app's
 * host goes to the public site, and an app page asked for on the public site goes to the app,
 * keeping the path and query (301 for GET and HEAD, 308 otherwise, so a POST stays a POST).
 * Health, the webhooks and the error tunnel answer on either. Until the beta's old installs have
 * moved, the API answers on the public site too, and an app page there shows the moving page
 * (the frontend's `public.moving`), which brings the device's songs along.
 */
final class HostRouting
{
    /** App pages that are never an old install's: these always just redirect. */
    private const REDIRECTED = ['admin', 'admin/*', 'auth/*', 'signin', 'verify', 'reset', 'email/*', 'notice', 'beta/*', 'early/*', 'invite/*', 'preview/*', 'join/*', 'choir/*', 'teacher/*', 'plus/return', 'connect/*'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Hosts::split() || $request->is(...Hosts::SHARED_PATHS)) {
            return $next($request);
        }

        // The public site's old host (beta.stackapella.com after launch): its public pages (and the
        // landing) move to the public site; the API and the moving page stay for old installs.
        if (Hosts::onOldPublicHost($request)) {
            if ($request->is('api', 'api/*')) {
                return $next($request);
            }

            $appPage = ! ($request->path() === '/' && ! $request->query->has('app')) && ! Hosts::isPublicPath($request);

            if ($appPage && $request->isMethod('GET') && ! $request->is(...self::REDIRECTED) && ! $request->expectsJson() && is_file(resource_path('views/public/moving.blade.php'))) {
                return response()->view('public.moving', ['appUrl' => Hosts::appUrl().$request->getRequestUri()], 200, ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
            }

            return $this->away($appPage ? Hosts::appUrl() : Hosts::publicUrl(), $request);
        }

        if (Hosts::onPublicHost($request)) {
            // An old install opens `/?app` (its start_url): that's the app, not the landing.
            if (($request->path() === '/' && ! $request->query->has('app')) || Hosts::isPublicPath($request) || $request->is('api', 'api/*')) {
                return $next($request);
            }

            if ($request->isMethod('GET') && ! $request->is(...self::REDIRECTED) && ! $request->expectsJson() && is_file(resource_path('views/public/moving.blade.php'))) {
                return response()->view('public.moving', ['appUrl' => Hosts::appUrl().$request->getRequestUri()], 200, ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
            }

            return $this->away(Hosts::appUrl(), $request);
        }

        if (Hosts::onAppHost($request) && Hosts::isPublicPath($request)) {
            return $this->away(Hosts::publicUrl(), $request);
        }

        return $next($request);
    }

    private function away(string $base, Request $request): Response
    {
        return redirect()->away($base.$request->getRequestUri(), $request->isMethod('GET') || $request->isMethod('HEAD') ? 301 : 308);
    }
}
