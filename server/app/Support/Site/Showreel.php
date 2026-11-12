<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Support\Facades\App;

/**
 * The landing's film, "See it in action" (server/resources/promo/showreel/README.md): its files, by
 * cut (dark and light) and size, at config('site.showreel.url'). It shows once manifest.json (in
 * git, beside the posters) is there, so the landing never shows an empty frame before the renders
 * are uploaded. Each URL carries the manifest's hash as its version: on the CDN as a folder
 * (app:showreel:upload puts each render in showreel/<version>/, cached for a year), locally as a
 * query. Each language has its own film (the showreel agent, 2026-11-12): showreel/<lang>/manifest.json and its
 * renders beside it, the same names, its own version; a page shows its language's film, else the English one.
 */
final class Showreel
{
    private const NAME = 'stackapella-showreel';

    /**
     * Shown once the manifest is in place and the videos can be reached: from the CDN once its
     * address is set (SHOWREEL_URL), or here when they've been rendered into public/ locally.
     */
    public static function available(): bool
    {
        return is_file(self::manifest()) && (self::origin() !== null || is_file(dirname(self::manifest()).'/'.self::NAME.'.mp4'));
    }

    /**
     * The film's language for this page: the page's own when that language has a manifest, else English. A language's
     * film shows only once it's in git (and uploaded), so nothing breaks before.
     */
    public static function language(?string $language = null): string
    {
        $language ??= App::getLocale();

        return $language !== 'en' && preg_match('/^[a-z]{2}$/', $language) === 1 && is_file(public_path('videos/showreel/'.$language.'/manifest.json')) ? $language : 'en';
    }

    /**
     * The files the frame needs, for each cut.
     *
     * @return array{dark: array<string, string>, light: array<string, string>, captions: string, language: string}
     */
    public static function files(?string $language = null): array
    {
        $language = self::language($language);
        $cut = static fn (string $suffix): array => [
            'webm' => self::url(self::NAME.$suffix.'.webm', $language),
            'mp4' => self::url(self::NAME.$suffix.'.mp4', $language),
            'mobile' => self::url(self::NAME.$suffix.'-mobile.mp4', $language),
            'poster' => self::url(self::NAME.$suffix.'-poster.jpg', $language),
            'posterSet' => implode(', ', array_map(static fn (int $w): string => self::url(self::NAME.$suffix.'-poster-'.$w.'.webp', $language).' '.$w.'w', [960, 1440, 1920])),
        ];

        // The captions come from the site itself (committed beside the manifest): a <track> from
        // another origin needs CORS on the CDN, and without it the captions silently never show.
        return ['dark' => $cut(''), 'light' => $cut('-light'), 'captions' => '/videos/showreel/'.self::folder($language).self::NAME.'.vtt?v='.self::version($language), 'language' => $language];
    }

    /** The files' host, for the pages' Content-Security-Policy when it isn't this one. */
    public static function origin(): ?string
    {
        $url = (string) config('site.showreel.url');
        $parts = parse_url($url);

        return isset($parts['scheme'], $parts['host']) ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '') : null;
    }

    private static function url(string $file, string $language): string
    {
        // On the CDN each render is its own version's folder whatever its language; here, its language's folder.
        return self::origin() !== null
            ? config('site.showreel.url').'/'.self::version($language).'/'.$file
            : config('site.showreel.url').'/'.self::folder($language).$file.'?v='.self::version($language);
    }

    /** A language's folder under showreel/: none for English (its files are where they always were), else "<lang>/". */
    private static function folder(string $language): string
    {
        return $language === 'en' ? '' : $language.'/';
    }

    /** The render's version: the start of its manifest's hash. */
    public static function version(?string $language = null): string
    {
        $manifest = self::manifest($language);

        return is_file($manifest) ? substr((string) hash_file('sha256', $manifest), 0, 10) : '';
    }

    /** The manifest in git, which says which render the pages show: the page's language's, else English. */
    public static function manifest(?string $language = null): string
    {
        return public_path('videos/showreel/'.self::folder(self::language($language)).'manifest.json');
    }
}
