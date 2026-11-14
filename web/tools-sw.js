// The free tools' own service worker (docs/tools-plan.md, "Offline and installable"): registered by
// the tools' pages with scope /tools, so it serves the hub and every tool page and nothing else, and
// never the app's (web/sw.js). Its caches are named tools-*; it makes and deletes only those.
// - Pages: from the network, the cached copy when offline.
// - A file asked for with ?v=<its content hash>: from the cache once kept (that address never changes).
// - Any other file of ours a tool's page uses: from the network, the cached copy when offline, so an
//   old module is never mixed with a new one while online.
// - Never /api/, never another site, nothing but GET. Nothing is recorded or sent.
// A page can keep the modules it loads only on a tap (the pitch tracker when the mic opens) before
// they're first used: navigator.serviceWorker.controller.postMessage({ type: 'tools-warm', urls }).

const VERSION = 1;
const CACHE = `tools-v${VERSION}`;
const PREFIX = 'tools-';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith(PREFIX) && key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

/**
 * One of ours, a GET, and not the API: the only requests this worker answers. A media element's Range request is left
 * to the browser: answered here it ended in a network error (a 206 can't be kept), and offline it would get a whole file
 * where it asked for a part (the Safari audit, 2026-11-14; Safari asks in ranges even for short files).
 */
function ours(request) {
    const url = new URL(request.url);
    return request.method === 'GET' && url.origin === self.location.origin && !url.pathname.startsWith('/api/') && !request.headers.has('range');
}

/** Kept only when whole and ours (no errors, redirects or opaque answers). */
async function keep(request, response) {
    if (response.ok && response.status !== 206 && response.type === 'basic') {
        const cache = await caches.open(CACHE);
        await cache.put(request, response.clone());
    }
    return response;
}

async function networkFirst(request) {
    try {
        return await keep(request, await fetch(request));
    } catch (error) {
        // A page opened offline with another ?ref= or ?key= is still the same page.
        const hit = await caches.match(request, { cacheName: CACHE, ignoreSearch: request.mode === 'navigate' });
        if (hit) return hit;
        throw error;
    }
}

async function cacheFirst(request) {
    const hit = await caches.match(request, { cacheName: CACHE });
    return hit ?? keep(request, await fetch(request));
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (!ours(request)) return;
    const versioned = new URL(request.url).searchParams.has('v');
    event.respondWith(versioned && request.mode !== 'navigate' ? cacheFirst(request) : networkFirst(request));
});

self.addEventListener('message', (event) => {
    const data = event.data;
    if (!data || data.type !== 'tools-warm' || !Array.isArray(data.urls)) return;
    const requests = data.urls
        .filter((url) => typeof url === 'string')
        .slice(0, 200)
        .map((url) => new Request(new URL(url, self.location.origin).href))
        .filter(ours);
    event.waitUntil(
        Promise.all(
            requests.map((request) =>
                caches
                    .match(request, { cacheName: CACHE })
                    .then((hit) => hit ?? fetch(request).then((response) => keep(request, response)))
                    .catch(() => null),
            ),
        ),
    );
});
