// What a lesson shows only after a result: the share button with its card (share-result.js, result-card.js) and the
// session's wrap. Loaded when first drawn, from the page's own version (js/lazy.js), and fetched ahead once the first
// screen is up and the words are in (main.js fetchAhead), so a first visit's lesson 1 doesn't wait for them on a slow
// phone (2026-11-08) and a result never does either.
import { defineAsyncComponent } from 'vue';
import { lazyView } from '../lazy.js';

const shareLoader = lazyView(() => import('./share-result.js').then((m) => m.ShareResult));
const wrapLoader = lazyView(() => import('./session-wrap.js').then((m) => m.SessionWrap));

export const ShareResult = defineAsyncComponent(shareLoader);
export const SessionWrap = defineAsyncComponent(wrapLoader);

/** Both fetched ahead (a failure is left for their first use). */
export function prefetchResults() {
    return Promise.all([shareLoader.prefetch(), wrapLoader.prefetch()]);
}
