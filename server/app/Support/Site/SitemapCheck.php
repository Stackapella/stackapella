<?php

declare(strict_types=1);

namespace App\Support\Site;

use Closure;
use DOMDocument;
use DOMElement;

/**
 * Our sitemap, checked the way a search engine reads it (app:sitemap:check), after Search Console's
 * "New reasons prevent pages from being indexed" (2026-11-09). Each address in it must answer 200
 * with no redirect, name itself as its canonical (same scheme, host and trailing slash), carry no
 * noindex (meta or X-Robots-Tag), be allowed by robots.txt and not share its canonical with another.
 * The other way round: every page of ours linked from a sitemap page (the hubs, /, /songs, /courses
 * and /tools, the footer every page carries, and each page's own links) is in the sitemap, or says
 * noindex on purpose; a link to a page that's gone (a song waiting for its Monday) is named with the
 * pages that link to it. Cloudflare's own /cdn-cgi/ addresses are robots-disallowed, so skipped.
 *
 * The pages come through $fetch, one at a time: the command's polite HTTP client, or a test's
 * requests to the app itself.
 */
final class SitemapCheck
{
    /** A problem's kind, and what it means, in the order the report gives them. */
    public const KINDS = [
        'sitemap' => "The sitemap or robots.txt couldn't be read",
        'not_200' => "In the sitemap, but doesn't answer 200 (a redirect or an error)",
        'off_site' => 'In the sitemap, but on another host',
        'no_canonical' => 'In the sitemap, but names no canonical',
        'canonical_elsewhere' => 'In the sitemap, but its canonical is another address',
        'noindex' => 'In the sitemap, but says noindex',
        'disallowed' => 'In the sitemap, but robots.txt disallows it',
        'shared_canonical' => 'Two or more sitemap addresses share one canonical',
        'linked_missing' => 'Linked from a sitemap page, indexable, but not in the sitemap',
        'linked_redirect' => 'Linked from a sitemap page, but redirects',
        'linked_broken' => "Linked from a sitemap page, but doesn't answer",
    ];

    /** A link to a file, not a page. */
    private const FILE = '/\.(css|js|mjs|json|xml|txt|ico|png|jpe?g|gif|svg|webp|avif|woff2?|mp3|m4a|ogg|wav|mp4|webm|pdf|webmanifest|zip)$/i';

    /**
     * @param  Closure(string): array{status: int, headers: array<string, string>, body: string}  $fetch  one GET, never following a redirect; header names lower case
     */
    public function __construct(private readonly Closure $fetch) {}

    /**
     * @return array<string, list<array{url: string, detail: string}>> problems by kind, only kinds that have some
     */
    public function run(string $base): array
    {
        $base = rtrim($base, '/');
        $problems = [];
        $add = static function (string $kind, string $url, string $detail = '') use (&$problems): void {
            $problems[$kind][] = ['url' => $url, 'detail' => $detail];
        };

        $robots = ($this->fetch)($base.'/robots.txt');
        $rules = $robots['status'] === 200 ? self::robotsRules($robots['body']) : [];
        if ($robots['status'] !== 200) {
            $add('sitemap', $base.'/robots.txt', 'answered '.$robots['status']);
        }

        $sitemap = ($this->fetch)($base.'/sitemap.xml');
        $locs = $sitemap['status'] === 200 ? self::locs($sitemap['body']) : [];
        if ($locs === []) {
            $add('sitemap', $base.'/sitemap.xml', $sitemap['status'] === 200 ? 'no addresses in it' : 'answered '.$sitemap['status']);

            return self::ordered($problems);
        }

        $listed = array_fill_keys($locs, true);
        $canonicals = [];
        /** @var array<string, list<string>> $linkedFrom each linked page of ours, and which pages link to it */
        $linkedFrom = [];

        foreach ($locs as $loc) {
            if (self::origin($loc) !== self::origin($base)) {
                $add('off_site', $loc);

                continue;
            }

            if (self::disallowed($rules, self::path($loc))) {
                $add('disallowed', $loc);
            }

            $page = ($this->fetch)($loc);

            if ($page['status'] !== 200) {
                $add('not_200', $loc, $page['status'].(isset($page['headers']['location']) ? ' to '.$page['headers']['location'] : ''));

                continue;
            }

            $html = self::parse($page['body']);
            $canonical = self::canonical($html, $loc);

            if (self::noindex($html, $page['headers'])) {
                $add('noindex', $loc);
            }

            if ($canonical === null) {
                $add('no_canonical', $loc);
            } elseif ($canonical !== $loc) {
                $add('canonical_elsewhere', $loc, $canonical);
            }

            $canonicals[$canonical ?? $loc][] = $loc;

            foreach (array_unique(self::links($html, $loc, $base)) as $link) {
                $linkedFrom[$link][] = $loc;
            }
        }

        foreach ($canonicals as $canonical => $sharing) {
            if (count($sharing) > 1) {
                $add('shared_canonical', $canonical, implode(', ', $sharing));
            }
        }

        // The pages' links: each page of ours they lead to that the sitemap doesn't list, once.
        foreach ($linkedFrom as $link => $from) {
            if (isset($listed[$link]) || self::disallowed($rules, self::path($link))) {
                continue;
            }

            $page = ($this->fetch)($link);
            $where = ', from '.implode(', ', array_slice($from, 0, 3)).(count($from) > 3 ? ' and '.(count($from) - 3).' more' : '');

            if ($page['status'] >= 300 && $page['status'] < 400) {
                $add('linked_redirect', $link, $page['status'].(isset($page['headers']['location']) ? ' to '.$page['headers']['location'] : '').$where);
            } elseif ($page['status'] !== 200) {
                $add('linked_broken', $link, $page['status'].$where);
            } elseif (! self::noindex(self::parse($page['body']), $page['headers'])) {
                $add('linked_missing', $link, ltrim($where, ', '));
            }
        }

        return self::ordered($problems);
    }

    /**
     * The addresses in a sitemap.
     *
     * @return list<string>
     */
    public static function locs(string $xml): array
    {
        preg_match_all('#<loc>\s*([^<\s]+)\s*</loc>#', $xml, $matches);

        return array_values(array_unique(array_map(static fn (string $loc): string => html_entity_decode($loc, ENT_XML1 | ENT_QUOTES), $matches[1])));
    }

    /**
     * The Allow and Disallow lines that apply to every crawler (the groups naming `*`).
     *
     * @return list<array{allow: bool, path: string}>
     */
    public static function robotsRules(string $body): array
    {
        $rules = [];
        $agents = [];
        $inRules = false;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            if (! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                // A user-agent line after rules starts a new group.
                if ($inRules) {
                    $agents = [];
                    $inRules = false;
                }
                $agents[] = $value;
            } elseif ($field === 'allow' || $field === 'disallow') {
                $inRules = true;
                if (in_array('*', $agents, true) && $value !== '') {
                    $rules[] = ['allow' => $field === 'allow', 'path' => $value];
                }
            }
        }

        return $rules;
    }

    /**
     * Whether robots.txt keeps crawlers from a path: the longest matching rule wins, Allow on a tie.
     *
     * @param  list<array{allow: bool, path: string}>  $rules
     */
    public static function disallowed(array $rules, string $path): bool
    {
        $best = null;

        foreach ($rules as $rule) {
            $pattern = '#^'.str_replace(['\*', '\$'], ['.*', '$'], preg_quote($rule['path'], '#')).'#';

            if (preg_match($pattern, $path) === 1 && ($best === null || strlen($rule['path']) > strlen($best['path']) || (strlen($rule['path']) === strlen($best['path']) && $rule['allow']))) {
                $best = $rule;
            }
        }

        return $best !== null && ! $best['allow'];
    }

    private static function parse(string $body): DOMDocument
    {
        $html = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $html->loadHTML('<?xml encoding="utf-8"?>'.$body, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $html;
    }

    private static function canonical(DOMDocument $html, string $url): ?string
    {
        foreach ($html->getElementsByTagName('link') as $link) {
            if (in_array('canonical', explode(' ', strtolower($link->getAttribute('rel'))), true) && $link->getAttribute('href') !== '') {
                return self::absolute($link->getAttribute('href'), $url);
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private static function noindex(DOMDocument $html, array $headers): bool
    {
        if (str_contains(strtolower($headers['x-robots-tag'] ?? ''), 'noindex')) {
            return true;
        }

        foreach ($html->getElementsByTagName('meta') as $meta) {
            if (in_array(strtolower($meta->getAttribute('name')), ['robots', 'googlebot'], true) && str_contains(strtolower($meta->getAttribute('content')), 'noindex')) {
                return true;
            }
        }

        return false;
    }

    /**
     * A page's links to pages of ours on the same host: no query, no fragment, no files.
     *
     * @return list<string>
     */
    private static function links(DOMDocument $html, string $url, string $base): array
    {
        $links = [];

        foreach ($html->getElementsByTagName('a') as $a) {
            /** @var DOMElement $a */
            $href = trim($a->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $href) === 1 && ! preg_match('#^https?://#i', $href)) {
                continue;
            }

            $link = self::absolute($href, $url);
            // Filtered views (?level=) name their page as canonical; a fragment is the same page.
            if (str_contains($link, '?') || self::origin($link) !== self::origin($base) || preg_match(self::FILE, self::path($link)) === 1) {
                continue;
            }

            $links[] = (string) preg_replace('/#.*$/', '', $link);
        }

        return $links;
    }

    private static function absolute(string $href, string $url): string
    {
        if (preg_match('#^https?://#i', $href) === 1) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            return (string) parse_url($url, PHP_URL_SCHEME).':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return self::origin($url).$href;
        }

        $path = self::path($url);

        return self::origin($url).substr($path, 0, (int) strrpos($path, '/') + 1).$href;
    }

    private static function origin(string $url): string
    {
        $parts = parse_url($url);

        return strtolower(($parts['scheme'] ?? '').'://'.($parts['host'] ?? '')).(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private static function path(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_PATH) ?? '/');
    }

    /**
     * @param  array<string, list<array{url: string, detail: string}>>  $problems
     * @return array<string, list<array{url: string, detail: string}>>
     */
    private static function ordered(array $problems): array
    {
        return array_filter(array_map(static fn (string $kind): array => $problems[$kind] ?? [], array_combine(array_keys(self::KINDS), array_keys(self::KINDS))));
    }
}
