<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Http\Hosts;
use App\Support\Site\Showreel;
use App\Support\Web\AppShell;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers and the Content-Security-Policy on every response (after iaw's).
 *
 * Three kinds of page:
 * - the app (the PWA's index.html, marked by WebAppController): Vue compiles its templates in the
 *   browser, so 'unsafe-eval' is allowed here and only here (docs/conventions.md, "Templates and
 *   CSP"), with the pinned CDN, Google Fonts, and the microphone for our own origin;
 * - the server's own pages (admin sign-in, the second factor; later the public Blade pages): no
 *   'unsafe-eval', scripts from 'self' only;
 * - the API: JSON that is never a document, so nothing at all.
 *
 * The admin (the app at /admin and the server's admin pages) can never be framed; the embedded
 * player (/embed/*) may be framed by any https site, and nothing else by another site.
 */
final class SecurityHeaders
{
    public const APP_PAGE = 'stackapella.app_page';

    public function __construct(private readonly AppShell $shell) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;
        // The service worker runs under its own script's policy: the app's, so it can precache the
        // pinned libraries from the CDN, the fonts and the media (a page's 'self' alone made every
        // new version's install fail, leaving browsers on an old one).
        $isApp = $request->attributes->get(self::APP_PAGE) === true || $request->is('sw.js');
        $isAdmin = $request->is('admin', 'admin/*', 'api/admin/*');
        $isEmbed = $request->is('embed/*');

        $headers->set('X-Content-Type-Options', 'nosniff');
        if (! $headers->has('Referrer-Policy')) {
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $headers->set('Permissions-Policy', ($isApp ? 'microphone=(self)' : 'microphone=()').', camera=(), geolocation=(), payment=(), publickey-credentials-get=(self), publickey-credentials-create=(self)');
        if (! $isEmbed) {
            $headers->set('X-Frame-Options', $isAdmin ? 'DENY' : 'SAMEORIGIN');
        }

        if (($build = (string) config('app.build')) !== '') {
            $headers->set('X-Stackapella-Build', $build);
        }

        if ($request->isSecure()) {
            // Every subdomain too (Hillel, 2026-10-29): all of stackapella.com is served over https.
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', match (true) {
                $request->is('api/*') => "default-src 'none'; frame-ancestors 'none'",
                $isApp => $this->appPolicy($isAdmin),
                default => $this->pagePolicy($isAdmin, $isEmbed),
            });
        }

        return $response;
    }

    private function appPolicy(bool $isAdmin): string
    {
        $media = $this->origin((string) config('filesystems.disks.media.url'));
        $private = $this->privateOrigin();

        return $this->build([
            'default-src' => ["'self'"],
            // Only the pinned packages, never the whole CDN host: with 'unsafe-eval' here, any other
            // file on jsDelivr would be someone else's script.
            // Turnstile's script and frame too, for shield mode (an attack switch): without them,
            // switching it on would lock everyone out of signing in.
            'script-src' => ["'self'", ...$this->shell->cdnSources(), "'unsafe-eval'", ...$this->shell->scriptHashes(), 'https://challenges.cloudflare.com'],
            'frame-src' => ['https://challenges.cloudflare.com'],
            'style-src' => ["'self'", 'https://fonts.googleapis.com'],
            'style-src-attr' => ["'unsafe-inline'"],
            'font-src' => ["'self'", 'https://fonts.gstatic.com'],
            // A harmony's own card (and admin's queue) is a signed link to the private bucket.
            'img-src' => array_filter(["'self'", 'data:', 'blob:', $media, $private]),
            'media-src' => array_filter(["'self'", 'blob:', $media, $private]),
            // The service worker caches the pinned CDN files and the fonts; uploads go straight to
            // the private bucket by a presigned PUT.
            'connect-src' => array_filter(["'self'", ...$this->shell->cdnSources(), 'https://fonts.googleapis.com', 'https://fonts.gstatic.com', $media, $private]),
            'worker-src' => ["'self'", 'blob:'],
            'manifest-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            // Sign in with Google or Apple posts here and is redirected on; browsers apply
            // form-action to that redirect.
            'form-action' => ["'self'", 'https://accounts.google.com', 'https://appleid.apple.com'],
            'frame-ancestors' => $isAdmin ? ["'none'"] : ["'self'"],
        ]);
    }

    private function pagePolicy(bool $isAdmin, bool $isEmbed = false): string
    {
        $media = $this->origin((string) config('filesystems.disks.media.url'));
        // A shared page's Report form shows Turnstile in shield mode, so reports keep coming in
        // during an attack (not the embed, which links to the page, nor the admin).
        $turnstile = $isAdmin || $isEmbed ? [] : ['https://challenges.cloudflare.com'];

        return $this->build([
            'default-src' => ["'self'"],
            'script-src' => ["'self'", ...$turnstile],
            'frame-src' => $turnstile === [] ? ["'none'"] : $turnstile,
            'style-src' => ["'self'", 'https://fonts.googleapis.com'],
            'style-src-attr' => ["'unsafe-inline'"],
            'font-src' => ["'self'", 'https://fonts.gstatic.com'],
            // The landing's film and its poster may come from their own CDN host (site.showreel).
            'img-src' => array_values(array_unique(array_filter(["'self'", 'data:', $media, Showreel::origin()]))),
            'media-src' => array_values(array_unique(array_filter(["'self'", $media, Showreel::origin()]))),
            // The public site's pages send Report to the app's host (docs/app-subdomain-plan.md).
            'connect-src' => array_values(array_unique(["'self'", ...(Hosts::split() ? [Hosts::appUrl()] : [])])),
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            // Signing in posts here and is redirected to Google; browsers apply form-action to it.
            'form-action' => ["'self'", 'https://accounts.google.com', 'https://appleid.apple.com'],
            'frame-ancestors' => match (true) {
                $isAdmin => ["'none'"],
                $isEmbed => ["'self'", 'https:'],
                default => ["'self'"],
            },
        ]);
    }

    /**
     * @param  array<string, array<int, string>>  $directives  (array_filter keeps the keys, so not lists)
     */
    private function build(array $directives): string
    {
        if (! app()->environment('local', 'testing')) {
            $directives['upgrade-insecure-requests'] = [];
        }

        return collect($directives)
            ->map(fn (array $sources, string $name): string => $sources === [] ? $name : $name.' '.implode(' ', array_unique($sources)))
            ->implode('; ');
    }

    private function privateOrigin(): ?string
    {
        if (config('filesystems.disks.private.driver') !== 's3') {
            return null;
        }

        $host = parse_url((string) config('filesystems.disks.private.endpoint'), PHP_URL_HOST);

        return is_string($host) ? 'https://'.config('filesystems.disks.private.bucket').'.'.$host : null;
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
