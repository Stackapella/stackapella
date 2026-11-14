// sw.js in a sandbox (the PWA update pass's watch, 2026-11-14): a kept file that's broken is fetched from the network,
// and sw_cache_broken is counted once a worker (anonymous: no id, signedIn false), however many such files.
import { assert, test } from './harness.js';

test('sw: a broken kept file comes from the network, and sw_cache_broken is counted once a worker', async () => {
    const source = await (await fetch('/sw.js')).text();
    const posts = [];
    const broken = new Response('', { status: 500 });
    const cache = { match: async () => broken.clone(), put: async () => {} };
    const fakeFetch = async (request, init) => {
        const url = typeof request === 'string' ? request : request.url;
        if (url.endsWith('/api/counts')) {
            posts.push({ init, body: JSON.parse(init.body) });
            return new Response(null, { status: 204 });
        }
        return new Response('export const ok = 1;', { status: 200, headers: { 'Content-Type': 'text/javascript' } });
    };
    const self = { location: { href: `${location.origin}/sw.js?build=test`, origin: location.origin }, addEventListener() {}, registration: {}, clients: { matchAll: async () => [] } };
    const caches = { open: async () => cache, keys: async () => [], match: async () => null, delete: async () => true };
    const { fromBuild } = new Function('self', 'caches', 'fetch', 'indexedDB', `${source}\nreturn { fromBuild };`)(self, caches, fakeFetch, null);
    const event = { waitUntil() {} };
    for (const path of ['/js/a.js', '/js/b.js']) {
        const answer = await fromBuild(event, new Request(location.origin + path), new Request(location.origin + path));
        assert(answer.ok && (await answer.text()).includes('ok'), `${path} came from the network`);
    }
    assert(posts.length === 1, `counted ${posts.length} times`);
    assert(JSON.stringify(posts[0].body) === JSON.stringify({ events: [{ kind: 'sw_cache_broken' }], signedIn: false }), JSON.stringify(posts[0].body));
    assert(posts[0].init.keepalive === true && posts[0].init.credentials === 'omit', 'keepalive, no credentials');
});
