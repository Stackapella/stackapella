// Each screen named for screen readers (WCAG 2.4.2 and 2.4.3): the tab's title is the screen's
// heading, and after a move within the app the heading takes focus, so a screen reader says where
// you are and Tab starts from the top of the new screen. A screen that places focus itself (a
// lesson's step, an opening sheet) keeps it; the first load leaves focus where the browser put it.

import { nextTick, watch } from 'vue';
import { route } from './router.js';

const NAME = 'Stackapella'; // i18n-ignore: the product's name
const WAIT_MS = 10000; // a lazy screen and its data, at the latest (a timer for the page, never for audio)

function heading() {
    return document.querySelector('#app main h1') ?? document.querySelector('#app h1');
}

/** The heading's words as the tab's title, and focus on it after a move (unless focus was placed). */
function name(h1, moved) {
    const words = h1.textContent.trim().replace(/\s+/g, ' ');
    document.title = words && words !== NAME ? `${words} · ${NAME}` : NAME;
    const lost = !document.activeElement || document.activeElement === document.body || !document.activeElement.isConnected;
    if (!moved || !lost) return;
    if (!h1.hasAttribute('tabindex')) {
        h1.setAttribute('tabindex', '-1');
        h1.style.outline = 'none'; // a heading isn't a control: no ring round it
    }
    h1.focus({ preventScroll: true });
}

let waiting = null;

function nameWhenShown(moved) {
    waiting?.();
    const found = heading();
    if (found) return name(found, moved);
    // A lazy screen, or one loading its data: named once its heading arrives.
    const app = document.getElementById('app');
    if (!app) return;
    const observer = new MutationObserver(() => {
        const h1 = heading();
        if (!h1) return;
        stop();
        name(h1, moved);
    });
    const gaveUp = setTimeout(() => stop(), WAIT_MS);
    function stop() {
        observer.disconnect();
        clearTimeout(gaveUp);
        waiting = null;
    }
    waiting = stop;
    observer.observe(app, { childList: true, subtree: true });
}

/** Names every screen as the route changes, from the first. */
export function nameScreens() {
    let first = true;
    watch(
        () => `${route.name}:${JSON.stringify(route.params)}`,
        () => {
            const moved = !first;
            first = false;
            nextTick(() => nameWhenShown(moved));
        },
        { immediate: true },
    );
}
