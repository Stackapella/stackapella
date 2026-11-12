<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Admin\StaffDevice;
use App\Support\Http\Hosts;
use App\Support\Languages\Languages;
use App\Support\Languages\SiteLanguages;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * A public page in a language (SiteLanguages): a page under /<lang>/ answers only while its language's switch is on
 * (locally and in tests, always, to check a draft), in that language. Its links to other pages the language serves
 * stay in it (so are its canonical and redirects), and every such page, English too, names its other languages
 * (hreflang, with English as x-default) once more than English is open.
 */
final class SiteLanguage
{
    public function __construct(private readonly Languages $languages) {}

    public function handle(Request $request, Closure $next, string $language = 'en'): Response
    {
        // A language not yet open answers only locally, in tests, and on an admin's device (a preview, Hillel's review
        // before the switch: StaffDevice::previews). Its 404 is never kept, so the admin's next look isn't a stored one.
        $preview = $language !== 'en' && ! $this->languages->isOffered($language);

        if ($language !== 'en') {
            if ($preview && ! app()->environment(['local', 'testing']) && ! StaffDevice::previews($request)) {
                abort(404, '', ['Cache-Control' => 'no-store, private']);
            }
            // A page written as a whole file in each language (a guide) answers here only once it's written in this one.
            abort_unless(SiteLanguages::serves($language, SiteLanguages::englishPath($request->getPathInfo(), $language)), 404);
        }

        // The page's language for this request only: a long-running worker serves the next one as it was.
        $before = App::getLocale();
        App::setLocale($language);

        try {
            $response = $this->render($request, $next, $language);

            return $preview ? self::preview($response, $language) : $response;
        } finally {
            App::setLocale($before);
        }
    }

    /**
     * A page in a language not yet open: kept by nobody (it's one admin's look), and saying so at its top. Its
     * alternates and the sitemap name it only once it's open (SiteLanguages::alternatesOf reads the open languages).
     */
    private static function preview(Response $response, string $language): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex');
        $html = $response->getContent();

        if ($response->getStatusCode() === 200 && is_string($html)) {
            $note = '<div role="note" style="position:sticky;top:0;z-index:999;padding:6px 12px;background:#1f2937;color:#f9fafb;font:600 13px/1.4 system-ui,sans-serif;text-align:center">'
                .e(__('site.language_preview', ['language' => strtoupper($language)], 'en')).'</div>';
            $with = (string) preg_replace('#(<body[^>]*>)#i', '$1'.$note, $html, 1);

            if ($with !== $html) {
                $original = $response instanceof \Illuminate\Http\Response ? $response->original : null;
                $response->setContent($with);
                if ($response instanceof \Illuminate\Http\Response) {
                    $response->original = $original;
                }
            }
        }

        return $response;
    }

    private function render(Request $request, Closure $next, string $language): Response
    {
        return self::finish($next($request), $language, SiteLanguages::englishPath($request->getPathInfo(), $language));
    }

    /**
     * A page's response in a language: its links and redirects kept in it, and its alternates named. Public for the
     * landing at /, which the app's shell serves rather than a route.
     */
    public static function finish(Response $response, string $language, string $english): Response
    {
        if ($language !== 'en' && $response->isRedirection() && is_string($location = $response->headers->get('Location'))) {
            $response->headers->set('Location', self::localize($location, $language));
        }

        // A page: HTML by its type, or by its start when it has no type yet (a controller's own response, before
        // Laravel prepares it).
        $type = (string) $response->headers->get('Content-Type');
        $html = $response->getContent();
        if ($response->getStatusCode() !== 200 || ! is_string($html) || ! ($type !== '' ? str_contains($type, 'text/html') : preg_match('/^\s*<!DOCTYPE html/i', $html) === 1)) {
            return $response;
        }

        if ($language !== 'en') {
            $html = (string) preg_replace_callback('/\b(href|content|action)="([^"]*)"/', static fn (array $m): string => $m[1].'="'.self::localize(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5), $language, escape: true).'"', $html);
            $response->headers->set('Content-Language', $language);
        }

        $alternates = SiteLanguages::alternatesOf($english);

        if ($alternates !== []) {
            $links = array_map(static fn (string $lang): string => '<link rel="alternate" hreflang="'.$lang.'" href="'.e(SiteLanguages::url($lang, $english)).'">', $alternates);
            $links[] = '<link rel="alternate" hreflang="x-default" href="'.e(SiteLanguages::url('en', $english)).'">';
            $html = (string) preg_replace('#</head>#i', implode('', $links).'</head>', $html, 1);
        }

        // Only when something changed, and the view it came from kept (tests and anything after read it).
        if ($html !== $response->getContent()) {
            $original = $response instanceof \Illuminate\Http\Response ? $response->original : null;
            $response->setContent($html);
            if ($response instanceof \Illuminate\Http\Response) {
                $response->original = $original;
            }
        }

        return $response;
    }

    /**
     * An address on our public site, in the language when the language serves its page; any other as it is.
     */
    private static function localize(string $url, string $language, bool $escape = false): string
    {
        $public = Hosts::publicUrl();
        $relative = str_starts_with($url, '/') && ! str_starts_with($url, '//');
        $ours = $public !== '' && ($url === $public || str_starts_with($url, $public.'/'));

        if (! $relative && ! $ours) {
            return $escape ? e($url) : $url;
        }

        $rest = $relative ? $url : substr($url, strlen($public));
        $path = (string) strtok($rest === '' ? '/' : $rest, '?#');
        $tail = substr($rest, strlen($path));
        // The app's own link to a song (/?song=, SongPage's "Learn this song") opens the app, which picks its own
        // language: /es/ is only ever the landing, so it never opened the song (frontend's Spanish walk, 2026-11-12).
        $app = $path === '/' && preg_match('/[?&]song=/', $tail) === 1;
        $done = ! $app && SiteLanguages::serves($language, $path) && ! str_starts_with($path, '/'.$language.'/')
            ? ($relative ? '' : $public).'/'.$language.$path.$tail
            : $url;

        return $escape ? e($done) : $done;
    }
}
