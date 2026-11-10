// The app that never starts (Hillel's PH-eve check, 2026-11-02): when Vue or another of the app's modules can't load
// (the CDN unreachable, a dropped connection), the module graph fails without a word and the page stays blank. main.js
// marks itself started on its first line, which only runs once every import has loaded; if that hasn't happened a moment
// after the page's load event, this says so, calmly, with Reload. A classic script in old JavaScript, its words English
// only (the translations load with the app); an old browser without import maps is old-browser.js's to explain.
(function () {
    var script = window.HTMLScriptElement;
    if (!script || typeof script.supports !== 'function' || !script.supports('importmap')) return;

    function check() {
        if (window.stackapellaStarted || document.querySelector('[data-boot-failed]')) return;
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
        button.onclick = function () { location.reload(); };
        box.appendChild(title);
        box.appendChild(line);
        box.appendChild(button);
        document.body.appendChild(box);
    }

    // A moment past the load event: a started app has set its mark well before then.
    window.addEventListener('load', function () { setTimeout(check, 3000); });
})();
