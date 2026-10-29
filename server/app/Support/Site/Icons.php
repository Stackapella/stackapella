<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * The public site's icons (24 by 24, drawn with a stroke in the text's colour), as board-a2 has them:
 * markup we make, so `{{ }}` prints it as it is.
 */
final class Icons
{
    private const PATHS = [
        'play' => '<path d="M8 5.5v13l11-6.5z" fill="currentColor" stroke="none"/>',
        'stop' => '<rect x="6.5" y="6.5" width="11" height="11" rx="2" fill="currentColor" stroke="none"/>',
        'pause' => '<rect x="7" y="5.5" width="3.5" height="13" rx="1" fill="currentColor" stroke="none"/><rect x="13.5" y="5.5" width="3.5" height="13" rx="1" fill="currentColor" stroke="none"/>',
        'sound' => '<path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4z" fill="currentColor" stroke="none"/><path d="M15.5 9a4 4 0 0 1 0 6M18 6.5a7.5 7.5 0 0 1 0 11"/>',
        'muted' => '<path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4z" fill="currentColor" stroke="none"/><path d="m16 9.5 5 5M21 9.5l-5 5"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M4.6 4.6l1.4 1.4M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4 6 18M18 6l1.4-1.4"/>',
        'moon' => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'mic' => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'video' => '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10.5 5-3v9l-5-3"/>',
        'spark' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
        'hum' => '<path d="M3 12c2-4 3-4 4.5 0s2.5 4 4.5 0 3-4 4.5 0 2.5 4 4.5 0"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'device' => '<rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
        'grid' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
        'slot' => '<path d="M4 7h16M4 12h9M4 17h16"/><path d="M16 12h4" stroke-dasharray="1.5 2.5"/>',
        'flag' => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
        'book' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5zM4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5"/>',
        'coach' => '<circle cx="9" cy="7.5" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M17 9.5c1.5 1 1.5 4 0 5M19.5 7.5c3 2.5 3 7.5 0 10"/>',
        'vote' => '<path d="M7 11V20M7 11l4-7c1.5 0 2.5 1 2.2 2.6L12.7 9H19a1.5 1.5 0 0 1 1.5 1.8l-1.6 7.5A2 2 0 0 1 17 20H7M3 11h4v9H3z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'star' => '<path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z" fill="currentColor" stroke="none"/>',
        'headphones' => '<path d="M4 15v-3a8 8 0 0 1 16 0v3"/><rect x="3" y="14" width="5" height="7" rx="2"/><rect x="16" y="14" width="5" height="7" rx="2"/>',
        'clock' => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9.5 2.5h5"/>',
        'chevron-end' => '<path d="m9 6 6 6-6 6"/>',
        // The networks' own marks (Simple Icons, CC0), filled.
        'x' => '<path fill="currentColor" stroke="none" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>',
        'bluesky' => '<path fill="currentColor" stroke="none" d="M5.202 2.857C7.954 4.922 10.913 9.11 12 11.358c1.087-2.247 4.046-6.436 6.798-8.501C20.783 1.366 24 .213 24 3.883c0 .732-.42 6.156-.667 7.037-.856 3.061-3.978 3.842-6.755 3.37 4.854.826 6.089 3.562 3.422 6.299-5.065 5.196-7.28-1.304-7.847-2.97-.104-.305-.152-.448-.153-.327 0-.121-.05.022-.153.327-.568 1.666-2.782 8.166-7.847 2.97-2.667-2.737-1.432-5.473 3.422-6.3-2.777.473-5.899-.308-6.755-3.369C.42 10.04 0 4.615 0 3.883c0-3.67 3.217-2.517 5.202-1.026"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14a5 5 0 0 1 5.5 5"/>',
    ];

    public static function svg(string $name, int $size = 20): HtmlString
    {
        if (! isset(self::PATHS[$name])) {
            throw new InvalidArgumentException("No icon [{$name}].");
        }

        return new HtmlString(sprintf('<svg width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>', $size, $size, self::PATHS[$name]));
    }

    /**
     * Stackapella's mark (web/icons/icon.svg, drawn by web/tool/icons.py: four bars, one a voice, on
     * a black square), its own colours in every theme, the corners rounded as the app's header has it.
     */
    public static function mark(int $size = 28): HtmlString
    {
        $svg = (string) file_get_contents(dirname(base_path()).'/web/icons/icon.svg');
        $svg = preg_replace('/<svg [^>]*viewBox="0 0 512 512">/', sprintf('<svg width="%1$d" height="%1$d" viewBox="0 0 512 512" aria-hidden="true">', $size), $svg, 1) ?? '';
        $svg = preg_replace('/<rect width="512" height="512"/', '<rect width="512" height="512" rx="86"', $svg, 1) ?? '';

        return new HtmlString($svg);
    }
}
