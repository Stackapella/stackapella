<?php

declare(strict_types=1);

namespace App\Support\Web;

use App\Support\Http\Hosts;
use RuntimeException;

/**
 * The PWA's index.html, served for every app route (/, /p/..., /sing, /admin...). Its inline
 * scripts (the import map) are allowed by their sha256 in the CSP, never by 'unsafe-inline'.
 */
final class AppShell
{
    /** @var array{html: string, hashes: list<string>, cdn: list<string>}|null */
    private ?array $shell = null;

    /**
     * The shell, with the domain the theme cookie is shared on (both hosts read it, so the look
     * follows the person), when there is one: theme-boot reads it, and stays on this host without.
     */
    /**
     * @param  array{title: string, description: string, image: string, imageAlt: string, url: string}|null  $link  what the page shows when its link is pasted into a chat (AppMeta)
     */
    public function html(?array $link = null): string
    {
        $html = $this->load()['html'];
        $domain = (string) config('hosts.pass_domain');

        // The public site's address, for the logo's link and the credits' profile links.
        $meta = '<meta name="public-url" content="'.htmlspecialchars(Hosts::publicUrl(), ENT_QUOTES).'">';

        if ($domain !== '') {
            $meta .= '<meta name="theme-cookie-domain" content="'.htmlspecialchars($domain, ENT_QUOTES).'">';
        }

        $html = (string) preg_replace('#(\s*)(<script src="/js/theme-boot\.js"></script>)#', '$1'.$meta.'$1$2', $html, 1);

        // The link's preview, read by chat apps without running a script: its description in
        // place of the shell's own, then Open Graph and X's card.
        if ($link !== null) {
            $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES);
            $tags = implode("\n    ", [
                '<meta name="description" content="'.$e($link['description']).'">',
                '<meta property="og:site_name" content="Stackapella">',
                '<meta property="og:type" content="website">',
                '<meta property="og:title" content="'.$e($link['title']).'">',
                '<meta property="og:description" content="'.$e($link['description']).'">',
                '<meta property="og:url" content="'.$e($link['url']).'">',
                '<meta property="og:image" content="'.$e($link['image']).'">',
                '<meta property="og:image:alt" content="'.$e($link['imageAlt']).'">',
                '<meta property="og:image:width" content="1200">',
                '<meta property="og:image:height" content="630">',
                '<meta name="twitter:card" content="summary_large_image">',
                '<meta name="twitter:title" content="'.$e($link['title']).'">',
                '<meta name="twitter:description" content="'.$e($link['description']).'">',
                '<meta name="twitter:image" content="'.$e($link['image']).'">',
                '<meta name="twitter:image:alt" content="'.$e($link['imageAlt']).'">',
            ]);
            $html = (string) preg_replace_callback('#<meta name="description"[^>]*>#', static fn (): string => $tags, $html, 1);
        }

        return $html;
    }

    /**
     * @return list<string> CSP sources such as 'sha256-...'
     */
    public function scriptHashes(): array
    {
        return $this->load()['hashes'];
    }

    /**
     * The pinned CDN packages the shell loads, as CSP sources (https://cdn.jsdelivr.net/npm/vue@3.5.44/),
     * so the policy allows those packages and nothing else on the CDN's host.
     *
     * @return list<string>
     */
    public function cdnSources(): array
    {
        return $this->load()['cdn'];
    }

    public function webPath(): string
    {
        return rtrim((string) config('app.web_path'), '/');
    }

    /**
     * @return array{html: string, hashes: list<string>, cdn: list<string>}
     */
    private function load(): array
    {
        if ($this->shell !== null) {
            return $this->shell;
        }

        $file = $this->webPath().'/index.html';
        $html = is_file($file) ? (string) file_get_contents($file) : throw new RuntimeException("The app's index.html is missing at [{$file}]: set WEB_PATH.");

        preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#si', $html, $matches);
        $hashes = array_values(array_unique(array_map(
            static fn (string $code): string => "'sha256-".base64_encode(hash('sha256', $code, true))."'",
            array_filter($matches[1], static fn (string $code): bool => trim($code) !== ''),
        )));

        preg_match_all('#https://cdn\.jsdelivr\.net/npm/(?:@[\w.-]+/)?[\w.-]+@\d+\.\d+\.\d+/#', $html, $packages);
        $cdn = array_values(array_unique($packages[0]));

        return $this->shell = ['html' => $html, 'hashes' => $hashes, 'cdn' => $cdn];
    }
}
