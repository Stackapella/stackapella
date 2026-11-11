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
    public const PUBLIC_PATHS = ['songs', 'songs/*', 'guides/*', 'legal/*', 'pricing', 'how-it-works', 'courses', 'courses/*', 'help', 'help/*', 'features', 'features/*', 'compare', 'compare/*', 'integrations', 'integrations/*', 'for/*', 'guides', 'about', 'press', 'open-source', 'selfhost', 'self-host', 'self-host-stackapella', 'whats-new', 'h/*', 'embed/*', 'oembed', 'coaches', 'coach/*', '@*', 'explore', 'ideas/*', 'choirs/*', 'sitemap.xml', 'unsubscribe/*', 'tools', 'tools/*', 'warmups', 'warmups/*', 'intervals', 'intervals/*', 'chords', 'chords/*', 'voice-types', 'voice-types/*', 'es', 'es/*', 'de', 'de/*', 'today'];

    /** Paths either host answers: health, webhooks, the error tunnel, robots, security.txt (RFC
     * 9116 scopes it to the host it's fetched from), and the static files the public pages load
     * too (styles, scripts, icons, and a page's course stylesheet, Public\CourseCssController). */
    public const SHARED_PATHS = ['up', 'up/scheduler', 'webhooks/*', 'log/errors', 'robots.txt', '*.txt', '.well-known/security.txt', 'dev/upload/*', 'css/*', 'course-css/*', 'js/*', 'icons/*', 'favicon.ico', 'tools-sw.js'];

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

    /**
     * The cookie domain both hosts read (".stackapella.com" for app.stackapella.com and
     * stackapella.com): the theme, the mic's name, the beta pass and mudeo's arrival are shared by
     * it. Null on one host, or when the two share no parent domain of at least two labels.
     */
    public static function sharedCookieDomain(?string $appUrl, ?string $publicUrl): ?string
    {
        $app = parse_url((string) $appUrl, PHP_URL_HOST);
        $public = parse_url((string) $publicUrl, PHP_URL_HOST);

        if (! is_string($app) || ! is_string($public) || $app === $public) {
            return null;
        }

        $a = explode('.', strtolower($app));
        $p = explode('.', strtolower($public));
        $common = [];
        while ($a !== [] && $p !== [] && end($a) === end($p)) {
            array_unshift($common, array_pop($a));
            array_pop($p);
        }

        return count($common) >= 2 ? '.'.implode('.', $common) : null;
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
     * An app page's address, always absolute on the app's host: for an email or anything else read
     * away from our pages (url() would follow whichever host the request came in on, and the public
     * host shows an app page as "moving").
     */
    public static function appLink(string $path): string
    {
        return self::appUrl().'/'.ltrim($path, '/');
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
