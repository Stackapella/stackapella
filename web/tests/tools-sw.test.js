// The free tools' service worker (web/tools-sw.js), run in a sandbox: a media element's Range request is left to the
// browser and a partial answer is never kept (the Safari audit, 2026-11-14: a 206 kept ended in a network error).
import { assert, test } from './harness.js';

async function worker() {
    const source = await (await fetch('/tools-sw.js')).text();
    const kept = [];
    const self = { location: { origin: location.origin }, addEventListener() {} };
    const caches = { open: async () => ({ put: async (request) => kept.push(request) }), keys: async () => [], match: async () => null };
    return { ...new Function('self', 'caches', `${source}\nreturn { ours, keep };`)(self, caches), kept };
}

test('tools worker: a Range request is the browser\'s, and a partial answer is never kept', async () => {
    const { ours, keep, kept } = await worker();
    const url = `${location.origin}/tools/sound.m4a`;
    assert(ours(new Request(url)), 'an ordinary request of ours');
    assert(!ours(new Request(url, { headers: { Range: 'bytes=0-1' } })), 'a Range request is answered here');
    const partial = { ok: true, status: 206, type: 'basic', clone: () => partial };
    assert((await keep(new Request(url), partial)) === partial && kept.length === 0, 'a 206 was kept');
    const whole = { ok: true, status: 200, type: 'basic', clone: () => whole };
    await keep(new Request(url), whole);
    assert(kept.length === 1, 'a whole answer is kept');
});
