// Views loaded later come from the page's own version (js/lazy.js): a newer build on the server, or
// a view that fails to load, reloads the page instead of mixing two versions.
import { lazyView } from '/js/lazy.js';
import { session as tabStorage } from '/js/storage.js';
import { assert, test } from './harness.js';

const quickly = (promise) => Promise.race([promise, new Promise((resolve) => setTimeout(() => resolve('pending'), 50))]);

test('lazy: the same build loads the view', async () => {
    let reloads = 0;
    const load = lazyView(async () => 'view', { reload: () => reloads++, check: async () => {}, state: { ready: false } });
    assert((await load()) === 'view' && reloads === 0, 'loaded, no reload');
});

test('lazy: a newer build on the server reloads instead of importing into an old page', async () => {
    let reloads = 0;
    let imported = false;
    const load = lazyView(async () => (imported = true), { reload: () => reloads++, check: async () => {}, state: { ready: true } });
    assert((await quickly(load())) === 'pending' && reloads === 1 && !imported, 'reloaded, nothing imported');
});

test('lazy: a view that fails to load reloads once, then says so', async () => {
    tabStorage.remove('stackapella.reloadedFor');
    let reloads = 0;
    const options = { reload: () => reloads++, check: async () => {}, state: { ready: false }, path: () => '/t/x' };
    const load = lazyView(async () => { throw new SyntaxError("The requested module doesn't provide an export"); }, options);
    assert((await quickly(load())) === 'pending' && reloads === 1, 'the first failure reloads');
    let thrown = null;
    await load().catch((error) => (thrown = error));
    assert(thrown instanceof SyntaxError && reloads === 1, 'the second throws, no loop');
    tabStorage.remove('stackapella.reloadedFor');
});
