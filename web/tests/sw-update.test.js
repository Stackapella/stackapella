// A new build reaching a page (js/sw-update.js, the PWA update pass, 2026-11-14): the build a page runs is the one its
// worker serves it, not the server's latest, so a page opened after a deploy knows it's older and updates.
import { servedBuild } from '/js/sw-update.js';
import { assert, test } from './harness.js';

test('sw update: a page served by a worker runs that worker\'s build; with none, or the unknown dev build, the server says', () => {
    assert(servedBuild({ scriptURL: 'https://app.example.com/sw.js?build=abc123' }) === 'abc123', 'the worker\'s build');
    assert(servedBuild({ scriptURL: 'https://app.example.com/sw.js?build=dev' }) === null, 'dev is no build');
    assert(servedBuild({ scriptURL: 'https://app.example.com/sw.js' }) === null, 'a worker with no build');
    assert(servedBuild(null) === null, 'no worker: the network served the page');
});

test('sw update: the silent reload only right after opening, with nothing done and nothing playing; else the prompt', async () => {
    const { silentAllowed, SILENT_MS } = await import('/js/sw-update.js');
    assert(silentAllowed({ elapsedMs: 3000, touched: false, playing: false }), 'soon, untouched, silent');
    assert(!silentAllowed({ elapsedMs: SILENT_MS + 1, touched: false, playing: false }), 'too late: someone may be reading');
    assert(!silentAllowed({ elapsedMs: 3000, touched: true, playing: false }), 'a tap, key or scroll');
    assert(!silentAllowed({ elapsedMs: 3000, touched: false, playing: true }), 'something playing');
});
