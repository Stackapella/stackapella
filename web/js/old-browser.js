// A browser too old for the app: Safari before 16.4 (every iPhone browser before iOS 16.4) can't
// read the import map, so the app's modules never start and the page would stay blank. Instead it
// says so, plainly, in the page's light or dark look, with the way on. A classic script (deferred,
// so the page is parsed), in the oldest JavaScript, so an old browser can run it; its words are
// English only, as the app's translations load with the app itself.
(function () {
    var script = window.HTMLScriptElement;
    if (script && typeof script.supports === 'function' && script.supports('importmap')) return;

    var app = document.getElementById('app');
    if (app) app.hidden = true; // in case the modules start after all, they stay out of sight
    var theme = document.documentElement.getAttribute('data-theme') || '';
    var dark = theme ? theme.indexOf('light') === -1 : !(window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches);
    var ink = dark ? '#000000' : '#faf6f0';
    var text = dark ? '#ffffff' : '#211a15';
    var muted = dark ? '#cfcfcf' : '#5c5249';
    var accent = dark ? '#ff79da' : '#a3236f';

    // The public site: the app host without its "app." (app.stackapella.com), else this host's landing.
    var host = location.hostname;
    var site = host.indexOf('app.') === 0 ? location.protocol + '//' + host.slice(4) + (location.port ? ':' + location.port : '') + '/' : '/?landing';

    function el(tag, words, css) {
        var node = document.createElement(tag);
        if (words) node.appendChild(document.createTextNode(words));
        for (var key in css) node.style[key] = css[key];
        return node;
    }

    document.body.style.background = ink;
    document.body.style.color = text;
    var box = el('main', null, { maxWidth: '32rem', margin: '0 auto', padding: '4rem 1.5rem', fontFamily: 'Figtree, -apple-system, system-ui, sans-serif', lineHeight: '1.5' }); // i18n-ignore: the app's translations never load here
    box.setAttribute('data-old-browser', '');
    box.appendChild(el('h1', 'Stackapella needs a newer browser', { fontSize: '1.75rem', fontWeight: '800', margin: '0 0 1rem', color: text })); // i18n-ignore: the app's translations never load here
    box.appendChild(el('p', 'Update iOS or Safari to 16.4 or later, or open Stackapella in Chrome.', { fontSize: '1.125rem', margin: '0 0 1.5rem', color: muted })); // i18n-ignore: the app's translations never load here
    var link = el('a', 'Go to the Stackapella website', { color: accent, fontWeight: '700', fontSize: '1.125rem' }); // i18n-ignore: the app's translations never load here
    link.href = site;
    box.appendChild(link);
    document.body.appendChild(box);
    document.title = 'Stackapella needs a newer browser'; // i18n-ignore: the app's translations never load here
})();
