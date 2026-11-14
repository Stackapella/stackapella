// The app that never starts (Hillel's PH-eve check, 2026-11-02): when Vue or another of the app's modules can't load
// (the CDN unreachable, a dropped connection), the module graph fails without a word and the page stays blank. main.js
// marks itself started on its first line, which only runs once every import has loaded; if that hasn't happened a moment
// after the page's load event, this says so, calmly, with Reload. A classic script in old JavaScript, its words English
// only (the translations load with the app); an old browser without import maps is old-browser.js's to explain.
(function () {
    var script = window.HTMLScriptElement;
    if (!script || typeof script.supports !== 'function' || !script.supports('importmap')) return;

    // Counted, anonymous, once a page each (boot_failed, boot_reset): how often the app fails to start, and the way back
    // is taken (the PWA update pass's watch, 2026-11-14). A beacon to our own server, nothing about who.
    // As js/automated.js does for the app: never for a robot, and only once someone has pressed, typed, touched or wheeled
    // (held till then; a page closed untouched sends nothing).
    var touched = false;
    var held = [];
    function onInput(event) {
        if (!event.isTrusted || touched) return;
        touched = true;
        var now = held;
        held = [];
        for (var i = 0; i < now.length; i++) send(now[i]);
    }
    ['pointerdown', 'keydown', 'touchstart', 'wheel'].forEach(function (kind) { window.addEventListener(kind, onInput, { capture: true, passive: true }); });
    function count(kind, now) {
        if (navigator.webdriver === true) return; // a robot counts nothing (js/automated.js)
        if (now || touched || window.__stackapellaPerson) send(kind);
        else held.push(kind);
    }
    function send(kind) {
        try { if (navigator.sendBeacon) navigator.sendBeacon('/api/counts', new Blob([JSON.stringify({ events: [{ kind: kind }], signedIn: false })], { type: 'application/json' })); } catch (error) { if (window.console) console.info('Not counted', kind); }
    }

    function check() {
        if (window.stackapellaStarted || document.querySelector('[data-boot-failed]')) return;
        // At once, while the page is seen: the person this failed for is the one most likely to leave untouched, so
        // waiting for a touch would hide the very failures this counts (backend and the coordinator, 2026-11-14).
        if (document.visibilityState === 'visible') count('boot_failed', true);
        var theme = document.documentElement.getAttribute('data-theme') || '';
        var dark = theme ? theme.indexOf('light') === -1 : true;
        var box = document.createElement('main');
        box.setAttribute('data-boot-failed', '');
        box.setAttribute('role', 'alert');
        var app = document.getElementById('app');
        if (app) app.hidden = true; // the waiting logo (index.html) goes, so it never sits over this
        box.style.cssText = 'max-width:32rem;margin:0 auto;padding:4rem 1.5rem;line-height:1.5;font-family:Figtree,-apple-system,system-ui,sans-serif;color:' + (dark ? '#ffffff' : '#211a15');
        var title = document.createElement('h1');
        title.appendChild(document.createTextNode("Stackapella couldn't load")); // i18n-ignore: the app's translations never load here
        title.style.cssText = 'font-size:1.75rem;font-weight:800;margin:0 0 1rem';
        var line = document.createElement('p');
        line.appendChild(document.createTextNode('Check your connection, then reload the page.')); // i18n-ignore: the app's translations never load here
        line.style.cssText = 'font-size:1.125rem;margin:0 0 1.5rem;color:' + (dark ? '#cfcfcf' : '#5c5249');
        var button = document.createElement('button');
        button.type = 'button';
        button.appendChild(document.createTextNode('Reload')); // i18n-ignore: the app's translations never load here
        button.style.cssText = 'font:inherit;font-weight:700;font-size:1.125rem;padding:0.75rem 1.5rem;border-radius:999px;border:0;cursor:pointer;background:' + (dark ? '#ffffff' : '#211a15') + ';color:' + (dark ? '#000000' : '#ffffff');
        var reset = false;
        button.onclick = function () {
            if (!reset) { reset = true; count('boot_reset'); }
            // Online, the app's worker and its kept copies go first, so the reload comes whole from the network: sw.js serves
            // a page its build's kept files, and a broken copy would only load again (the PWA update pass, 2026-11-14).
            // Offline they stay, for the app to open without a connection.
            if (!navigator.onLine || !navigator.serviceWorker || !window.caches) { location.reload(); return; }
            navigator.serviceWorker.getRegistrations()
                .then(function (all) { return Promise.all(all.filter(function (r) { var w = r.active || r.waiting || r.installing; return w && w.scriptURL.indexOf('/sw.js') !== -1; }).map(function (r) { return r.unregister(); })); })
                .then(function () { return caches.keys(); })
                .then(function (keys) { return Promise.all(keys.filter(function (k) { return k.indexOf('stackapella-') === 0; }).map(function (k) { return caches.delete(k); })); })
                .then(function () { location.reload(); }, function () { location.reload(); });
        };
        box.appendChild(title);
        box.appendChild(line);
        box.appendChild(button);
        document.body.appendChild(box);
    }

    // A moment past the load event: a started app has set its mark well before then.
    window.addEventListener('load', function () { setTimeout(check, 3000); });
})();
