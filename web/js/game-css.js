// Game mode's own stylesheet (docs/css-split-plan.md, step 3): the game skin's rules, css/app-game.css or
// css/site-game.css, are out of the page's own sheet, so serious mode never fetches them. A plain script placed right
// after that sheet's <link>: in game mode (theme-boot.js has set <html data-skin> by now) it writes the game sheet's
// <link> here, so it cascades exactly where its rules stood in the whole build and, written by the parser, holds the
// first paint as the page's own sheet does: no flash of serious mode's look. theme-boot.js has already started the
// download (a preload), and loads the sheet itself when game mode is turned on later. The address is the page's
// <meta name="game-css">.
(function () {
    const href = document.querySelector('meta[name="game-css"]')?.getAttribute('content');
    if (!href || document.documentElement.dataset.skin !== 'game' || document.querySelector('link[data-game-css]')) return;
    const safe = href.replace(/[&"<>]/g, (c) => ({ '&': '&amp;', '"': '&quot;', '<': '&lt;', '>': '&gt;' })[c]);
    document.write('<link rel="stylesheet" href="' + safe + '" data-game-css>');
})();
