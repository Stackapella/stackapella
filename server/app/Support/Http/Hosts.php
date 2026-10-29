<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * The app's host and the public site's (docs/app-subdomain-plan.md). Links between them are
 * absolute; within one, relative. With PUBLIC_URL unset (or the same as APP_URL), both are one.
 */
final class Hosts
{
    /** Paths that belong to the public site (everything else is the app's). */
    public const PUBLIC_PATHS = ['songs', 'songs/*', 'guides/*', 'legal/*', 'pricing', 'how-it-works', 'courses', 'courses/*', 'help', 'help/*', 'features', 'features/*', 'for/*', 'guides', 'about', 'press', 'open-source', 'selfhost', 'self-host', 'self-host-stackapella', 'whats-new', 'h/*', 'embed/*', 'oembed', 'coaches', 'coach/*', '@*', 'explore', 'ideas/*', 'choirs/*', 'sitemap.xml', 'unsubscribe/*'];

    /** Paths either host answers: health, webhooks, the error tunnel, robots, security.txt (RFC
     * 9116 scopes it to the host it's fetched from), and the static files the public pages load
     * too (styles, scripts, icons). */
    public const SHARED_PATHS = ['up', 'webhooks/*', 'log/errors', 'robots.txt', '.well-known/security.txt', 'dev/upload/*', 'css/*', 'js/*', 'icons/*', 'favicon.ico'];

    public static function appUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function publicUrl(): string
    {
        $public = (string) config('hosts.public_url');

        return rtrim($public !== '' ? $public : (string) config('app.url'), '/');
    }

    /**
     * Whether a browser sent this from one of our own pages (the app's or the public site's): an
     * Origin header must be one of ours, and failing that, Sec-Fetch-Site mustn't say another
     * site. A request with neither (not from a browser) passes.
     */
    public static function fromOurPages(Request $request): bool
    {
        $origin = $request->headers->get('Origin');

        if (is_string($origin) && $origin !== '' && $origin !== 'null') {
            return in_array(rtrim($origin, '/'), [self::appUrl(), self::publicUrl()], true);
        }

        return $request->headers->get('Sec-Fetch-Site') !== 'cross-site' && $origin !== 'null';
    }

    public static function split(): bool
    {
        return self::host(self::publicUrl()) !== self::host(self::appUrl());
    }

    public static function appHost(): string
    {
        return self::host(self::appUrl());
    }

    public static function publicHost(): string
    {
        return self::host(self::publicUrl());
    }

    /**
     * A link into the app from a public page: absolute when the hosts are apart.
     */
    public static function app(string $path): string
    {
        return self::split() ? self::appUrl().'/'.ltrim($path, '/') : '/'.ltrim($path, '/');
    }

    /**
     * A public page's address: always absolute, on the public site.
     */
    public static function public(string $path): string
    {
        return self::publicUrl().'/'.ltrim($path, '/');
    }

    public static function onPublicHost(Request $request): bool
    {
        return self::split() && strtolower($request->getHost()) === self::publicHost();
    }

    /**
     * One of the public site's old hosts (hosts.old_public_hosts).
     */
    public static function onOldPublicHost(Request $request): bool
    {
        return self::split() && in_array(strtolower($request->getHost()), (array) config('hosts.old_public_hosts'), true);
    }

    public static function onAppHost(Request $request): bool
    {
        return ! self::split() || strtolower($request->getHost()) === self::appHost();
    }

    public static function isPublicPath(Request $request): bool
    {
        return $request->is(...self::PUBLIC_PATHS);
    }

    private static function host(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }
}
