// Views loaded when first opened (main.js). A page left open across a deploy holds the old version's
// shared modules in memory; a view imported into it now would come from the new version and mix the
// two (an export that isn't there, or worse, something that half works: Hillel's silent Play). So
// before a view loads, the server's build is asked: newer than this page's, and the page reloads at
// the view's address instead, as one whole version. A view that still fails to load (a deploy half
// done) reloads once.

import { checkVersion, version } from './version.js';
import { session as tabStorage } from './storage.js';

const RELOADED = 'stackapella.reloadedFor';
const never = () => new Promise(() => {}); // the page is going: nothing renders meanwhile

/** A loader for defineAsyncComponent: the view, from the same version as the page, or a reload. */
export function lazyView(load, { reload = () => location.reload(), check = checkVersion, state = version, path = () => location.pathname } = {}) {
    return async () => {
        await check({ now: true });
        if (state.ready) {
            reload();
            return never();
        }
        try {
            const view = await load();
            tabStorage.remove(RELOADED);
            return view;
        } catch (error) {
            if (tabStorage.get(RELOADED) === path()) throw error; // reloaded once already: say so
            console.warn('A view did not load: reloading as one version', error);
            tabStorage.set(RELOADED, path());
            reload();
            return never();
        }
    };
}
