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

test('sw update: Refresh says Saving first only with changes waiting for an account, else Updating', async () => {
    const { refreshStage } = await import('/js/components/version.js');
    assert(refreshStage({ signedIn: true, pending: 2 }) === 'saving', 'changes waiting');
    assert(refreshStage({ signedIn: true, pending: 0 }) === 'updating', 'nothing changed: Hillel saw Saving first');
    assert(refreshStage({ signedIn: false, pending: 3 }) === 'updating', 'a guest saves nothing to an account');
});

test('sw update: Refresh is instant: a waiting new build takes over at once, else straight from the network, never the old', async () => {
    const { reloadIntoNew, COMING_MS } = await import('/js/sw-update.js');
    const timed = async (registration, build) => {
        let reloaded = null;
        const started = performance.now();
        await reloadIntoNew({ registration, build, reload: () => (reloaded = performance.now() - started) });
        return reloaded;
    };
    // The new build waiting: told to take over, it does (controllerchange), and the page reloads on it.
    const waiting = { scriptURL: `${location.origin}/sw.js?build=new`, postMessage: () => setTimeout(() => navigator.serviceWorker.dispatchEvent(new Event('controllerchange')), 20) };
    let unregistered = 0;
    const ready = { waiting, installing: null, update: () => new Promise(() => {}), unregister: async () => unregistered++ };
    const took = await timed(ready, 'new');
    assert(took !== null && took < 300 && unregistered === 0, `the waiting build: reload after ${took} ms, let go ${unregistered}`);
    // Still installing (its precache under way): never waited on; told to take over, it ends its install and does.
    let hurried = 0;
    const installing = { waiting: null, installing: { scriptURL: `${location.origin}/sw.js?build=new`, postMessage: () => (hurried++, waiting.postMessage()) }, update: () => new Promise(() => {}), unregister: async () => unregistered++ };
    const quick = await timed(installing, 'new');
    assert(quick !== null && quick < 300 && hurried === 1 && unregistered === 0, `still installing: reload after ${quick} ms, told ${hurried}, let go ${unregistered}`);
    // One that doesn't take over in time (a worker stuck starting): let go, and the reload asks the server.
    const stuck = { waiting: null, installing: { scriptURL: `${location.origin}/sw.js?build=new`, postMessage() {} }, update: () => new Promise(() => {}), unregister: async () => unregistered++ };
    const fallback = await timed(stuck, 'new');
    assert(fallback !== null && fallback < 300 + 100 && unregistered === 1, `a stuck worker: reload after ${fallback} ms, let go ${unregistered}`);
    // A waiting build older than the server's (two deploys): not taken, so the reload never lands between; the server's
    // build's worker, starting a moment later, is.
    let told = false;
    const newer = { scriptURL: `${location.origin}/sw.js?build=new`, postMessage: () => waiting.postMessage() };
    const older = new EventTarget();
    Object.assign(older, { waiting: { scriptURL: `${location.origin}/sw.js?build=between`, postMessage: () => (told = true) }, installing: null, unregister: async () => unregistered++ });
    setTimeout(() => {
        older.installing = newer;
        older.dispatchEvent(new Event('updatefound'));
    }, 60);
    const skipped = await timed(older, 'new');
    assert(skipped !== null && skipped < 60 + 300 && !told && unregistered === 1, `an older waiting build: reload after ${skipped} ms, told ${told}, let go ${unregistered}`);
    // Nothing coming (its script never came): let go once COMING_MS is up, and the reload asks the server.
    const none = Object.assign(new EventTarget(), { waiting: null, installing: null, unregister: async () => unregistered++ });
    const late = await timed(none, 'new');
    assert(late !== null && late >= COMING_MS && late < COMING_MS + 300 && unregistered === 2, `nothing coming: reload after ${late} ms, let go ${unregistered}`);
});
