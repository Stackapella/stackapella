// "A new version is ready" (docs/roadmap.md, L; Hillel's request): the build the server runs (/up,
// public, contract/api.md "The version") against the one this page started with, asked on opening,
// when the app comes back to the front, and every few minutes while it's open (never more than once
// in 30 seconds). A service worker taking over, or the API saying this app is too old (426), says
// the same. The prompt (components/version.js) waits while someone sings, and Refresh saves first.

import { reactive } from 'vue';

/** `loaded`: the build this page started with; `now`: the server's; `ready`: a newer one is there. */
export const version = reactive({ loaded: null, now: null, builtAt: null, ready: false });

const EVERY_MS = 5 * 60 * 1000;
const AT_MOST_MS = 30 * 1000;
let asked = 0;

/** The server's build now: `{ build, builtAt }`, or null (offline, or no server). */
async function serverBuild() {
    try {
        const response = await fetch('/up', { cache: 'no-store', headers: { Accept: 'application/json' } });
        if (!response.ok) return null;
        const answer = await response.json();
        return answer?.build ? answer : null;
    } catch (error) {
        console.debug('The version was not read', error); // offline: asked again later
        return null;
    }
}

/** Asks the server which build it runs (at most every 30 seconds unless `now`). */
export async function checkVersion({ now = false } = {}) {
    if (!now && Date.now() - asked < AT_MOST_MS) return;
    asked = Date.now();
    const found = await serverBuild();
    if (!found) return;
    version.loaded ??= found.build;
    version.now = found.build;
    version.builtAt = found.builtAt ?? null;
    if (found.build !== version.loaded) version.ready = true;
}

/** A newer version found another way (a service worker took over, or the API said 426). */
export function newVersionFound() {
    version.ready = true;
}

let started = false;

/** Starts asking: now, on coming back to the front, and every few minutes. */
export function watchVersion() {
    if (started) return;
    started = true;
    checkVersion({ now: true });
    document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && checkVersion());
    window.addEventListener('focus', () => checkVersion());
    setInterval(() => document.visibilityState === 'visible' && checkVersion(), EVERY_MS);
}
