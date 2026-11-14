<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Support\HtmlString;

/**
 * Game mode's first paint without a wait (game's LCP trace, 2026-11-13: 470 to 670 ms on a slow phone): a tiny inline
 * script at the top of a public page's head that, for game mode only, starts the game sheet (the page's
 * <meta name="game-css">) and VT323 (on the arcade screen) downloading at once, instead of after theme-boot.js has been
 * fetched and run. It reads the skin as theme-boot.js does (this address's stored choice or the newest `sa_theme`
 * cookie; game until one says serious), so serious mode fetches nothing more. The same bytes on every page, so one CSP
 * hash covers it (SecurityHeaders) and the page can be cached at the edge as before.
 */
final class GamePreload
{
    public const SCRIPT = <<<'JS'
(function () {
    var c = null;
    try { c = JSON.parse(localStorage.getItem('stackapella.appearance') || 'null'); } catch (e) { /* no storage: the cookie or game's default */ }
    document.cookie.split('; ').forEach(function (k) {
        if (k.indexOf('sa_theme=') !== 0) return;
        try { var v = JSON.parse(decodeURIComponent(k.slice(9))); if (v && (!c || (v.at || 0) > (c.at || 0))) c = v; } catch (e) { /* an unreadable cookie: as theme-boot.js, skipped */ }
    });
    if (c && c.skin === 'serious') return;
    var h = document.head, m = document.querySelector('meta[name="game-css"]'), add = function (a) { var l = document.createElement('link'); for (var k in a) l[k] = a[k]; h.appendChild(l); };
    if (m && m.content) add({ rel: 'preload', as: 'style', href: m.content });
    if (document.documentElement.hasAttribute('data-gs')) add({ rel: 'preload', as: 'font', type: 'font/woff2', href: '/fonts/vt323-latin.woff2', crossOrigin: 'anonymous' });
})();
JS;

    /** The script, ready for the head (its bytes exactly SCRIPT's, so the CSP hash matches). */
    public static function tag(): HtmlString
    {
        return new HtmlString('<script>'.self::SCRIPT.'</script>');
    }

    /** Its CSP source, 'sha256-...', for the public pages' script-src. */
    public static function hash(): string
    {
        return "'sha256-".base64_encode(hash('sha256', self::SCRIPT, true))."'";
    }
}
