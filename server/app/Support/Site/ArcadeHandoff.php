<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Support\HtmlString;

/**
 * The home in game mode is the arcade (Hillel, 2026-11-24: "on the main home page when you enable game mode switch to
 * the view we used to show with the in-app game experience"): a tiny inline script at the top of the home's head and
 * /arcade's that sends the visitor to the page their look asks for, before the first paint. Only a stored choice of game
 * mode counts, never the default game look a first visit gets (docs/decisions.md, 2026-11-18: the part pages' rule, "the
 * default game look is not a choice"), so a fresh visitor and a crawler always stay on the home, and /arcade sends anyone
 * without that choice back. The choice is read as theme-boot.js's chosen() reads it: the newer of this address's stored
 * appearance and the shared `sa_theme` cookie. It goes with location.replace, the query kept, so Back never bounces;
 * the Game mode switch, which stores its choice and then tells the page (stackapella:theme), sends the visitor the same
 * way. Without a script the home is the home. The same bytes on both pages, so one CSP hash covers it.
 */
final class ArcadeHandoff
{
    public const SCRIPT = <<<'JS'
(function () {
    var chosen = function () {
        var own = {}, shared = {};
        try { own = JSON.parse(localStorage.getItem('stackapella.appearance') || 'null') || {}; } catch (e) { own = {}; }
        document.cookie.split('; ').forEach(function (k) {
            if (k.indexOf('sa_theme=') !== 0) return;
            try { var v = JSON.parse(decodeURIComponent(k.slice(9))) || {}; if ((v.at || 0) > (shared.at || 0)) shared = v; } catch (e) { /* unreadable: as theme-boot.js, skipped */ }
        });
        return (shared.at || 0) > (own.at || 0) ? shared : own;
    };
    var es = /^\/es(\/|$)/.test(location.pathname), arcade = /\/arcade\/?$/.test(location.pathname);
    var go = function () {
        var game = chosen().skin === 'game';
        if (game !== arcade) location.replace((es ? '/es' : '') + (game ? '/arcade' : (es ? '' : '/')) + location.search + location.hash);
    };
    go();
    document.addEventListener('stackapella:theme', go);
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
