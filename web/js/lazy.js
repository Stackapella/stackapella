// Views loaded when first opened (main.js). A page left open across a deploy holds the old version's
// shared modules in memory; a view imported into it now would come from the new version and mix the
// two (an export that isn't there, or worse, something that half works: Hillel's silent Play). So
// before a view loads, the server's build is asked: newer than this page's, and the page reloads at
// the view's address instead, as one whole version. A view that still fails to load (a deploy half
// done) reloads once. A page that doesn't know its build yet (just opened: the home, say, loading
// as the app starts) skips the question, since a check then only learns the build it already has.
// After a first visit's lesson 1 words (js/i18n.js), a view waits for the rest of them.
//
// A view can also be fetched ahead (prefetch, main.js: the screens most often opened next, once the
// first is up and the browser idle). One already imported opens at once, with no question: it is
// this page's own version, imported while nothing newer was known.

import { checkVersion, version } from './version.js';
import { allWords } from './i18n.js';
import { session as tabStorage } from './storage.js';

const RELOADED = 'stackapella.reloadedFor';
const never = () => new Promise(() => {}); // the page is going: nothing renders meanwhile

/** A loader for defineAsyncComponent: the view, from the same version as the page, or a reload. Its `prefetch()` imports it ahead. */
export function lazyView(load, { reload = () => location.reload(), check = checkVersion, state = version, path = () => location.pathname, words = allWords } = {}) {
    let view = null; // once imported, opened or fetched ahead
    const loader = async () => {
        if (view) return view;
        if (state.loaded != null) await check({ now: true });
        if (state.ready) {
            reload();
            return never();
        }
        try {
            [view] = await Promise.all([load(), words()]);
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
    /** Imports the view ahead, unless a newer version is waiting (then the next open reloads, as above). A failure is left for that open. */
    loader.prefetch = async () => {
        if (view || state.ready) return;
        try {
            view = await load();
        } catch (error) {
            console.info('A view was not fetched ahead; it loads when opened', error?.message ?? error);
        }
    };
    return loader;
}
