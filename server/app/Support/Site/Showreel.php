<?php

declare(strict_types=1);

namespace App\Support\Site;

/**
 * The landing's film, "See it in action" (server/resources/promo/showreel/README.md): its files, by
 * cut (dark and light) and size, at config('site.showreel.url'). It shows once manifest.json (in
 * git, beside the posters) is there, so the landing never shows an empty frame before the renders
 * are uploaded. Each URL carries the manifest's hash as its version: on the CDN as a folder
 * (app:showreel:upload puts each render in showreel/<version>/, cached for a year), locally as a
 * query.
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
        return is_file(self::manifest()) && (self::origin() !== null || is_file(public_path('videos/showreel/'.self::NAME.'.mp4')));
    }

    /**
     * The files the frame needs, for each cut.
     *
     * @return array{dark: array<string, string>, light: array<string, string>, captions: string}
     */
    public static function files(): array
    {
        $cut = static fn (string $suffix): array => [
            'webm' => self::url(self::NAME.$suffix.'.webm'),
            'mp4' => self::url(self::NAME.$suffix.'.mp4'),
            'mobile' => self::url(self::NAME.$suffix.'-mobile.mp4'),
            'poster' => self::url(self::NAME.$suffix.'-poster.jpg'),
            'posterSet' => implode(', ', array_map(static fn (int $w): string => self::url(self::NAME.$suffix.'-poster-'.$w.'.webp').' '.$w.'w', [960, 1440, 1920])),
        ];

        // The captions come from the site itself (committed beside the manifest): a <track> from
        // another origin needs CORS on the CDN, and without it the captions silently never show.
        return ['dark' => $cut(''), 'light' => $cut('-light'), 'captions' => '/videos/showreel/'.self::NAME.'.vtt?v='.self::version()];
    }

    /** The files' host, for the pages' Content-Security-Policy when it isn't this one. */
    public static function origin(): ?string
    {
        $url = (string) config('site.showreel.url');
        $parts = parse_url($url);

        return isset($parts['scheme'], $parts['host']) ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '') : null;
    }

    private static function url(string $file): string
    {
        return self::origin() !== null
            ? config('site.showreel.url').'/'.self::version().'/'.$file
            : config('site.showreel.url').'/'.$file.'?v='.self::version();
    }

    /** The render's version: the start of its manifest's hash. */
    public static function version(): string
    {
        return is_file(self::manifest()) ? substr((string) hash_file('sha256', self::manifest()), 0, 10) : '';
    }

    /** The manifest in git, which says which render the pages show. */
    public static function manifest(): string
    {
        return public_path('videos/showreel/manifest.json');
    }
}
