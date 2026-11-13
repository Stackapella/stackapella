// The app's first screen, fetched while the visitor reads the landing or a song's page (docs/first-session-review-
// 2026-11-13.md, item 3), so "Start singing" and a song's sheet open from the browser's cache: the page lists them
// (App\Support\Site\AppPrefetch, from the app's own preload lists), and this asks for them as low-priority prefetches once
// the page has loaded and gone quiet. Never on Save-Data or a 2g connection, never before the page's own media, and
// nothing on screen. A page timer at most, never for audio.

const list = document.querySelector('[data-app-prefetch]');
const connection = navigator.connection;
const slow = connection?.saveData === true || /(^|-)2g$/.test(String(connection?.effectiveType ?? ''));

function prefetch() {
    let urls = [];
    try {
        urls = JSON.parse(list.textContent);
    } catch (error) {
        console.info('App prefetch list unreadable', error?.message ?? error);
        return;
    }
    for (const href of urls) {
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = href;
        link.crossOrigin = 'anonymous';
        document.head.append(link);
    }
}

function whenQuiet() {
    if (typeof window.requestIdleCallback === 'function') window.requestIdleCallback(prefetch, { timeout: 4000 });
    else setTimeout(prefetch, 1500);
}

if (list && !slow) {
    if (document.readyState === 'complete') whenQuiet();
    else window.addEventListener('load', whenQuiet, { once: true });
}
