// A new build reaching a page (the PWA update pass, 2026-11-14). sw.js serves a page its own build's files and a new
// build's worker waits beside it, so a page never runs two builds at once. That worker takes over here:
// - Refresh in "A new version is ready" (components/version.js): the waiting worker takes over, then the reload is the
//   new build. With none ready in time, the worker is let go and the reload comes from the network: never the old
//   build again (Hillel once had to click Refresh twice).
// - The app just opened while a new build waits: if it's ready within SILENT_MS of opening and nothing has happened yet
//   (no tap, key, scroll, and nothing playing), it takes over and the page reloads at once, so the app opens on the new
//   build with no prompt. Later, or once someone's reading or listening, "A new version is ready" asks instead (a reload
//   42 s in, measured on production, could land mid-read: the coordinator, 2026-11-14).

const READY_MS = 20000; // a new build's precache, on a slow line
const SWITCH_MS = 5000;
export const SILENT_MS = 8000; // the silent reload only this soon after opening

let touched = false;
for (const kind of ['pointerdown', 'keydown', 'scroll', 'wheel', 'touchstart']) addEventListener(kind, () => (touched = true), { once: true, capture: true, passive: true });

/** Something sounding: the app's engine (it tells the system it's playing) or any media element. */
const sounding = () => navigator.mediaSession?.playbackState === 'playing' || [...document.querySelectorAll('audio, video')].some((media) => !media.paused);

/** Whether a new build may take over without asking: soon after opening, before anything was done or heard. */
export function silentAllowed({ elapsedMs, touched: done, playing }) {
    return elapsedMs <= SILENT_MS && !done && !playing;
}

/** The build this page's files came from: its worker's (sw.js?build=), or null for a page the network served. */
export function servedBuild(controller = navigator.serviceWorker?.controller) {
    const build = controller ? new URL(controller.scriptURL).searchParams.get('build') : null;
    return build && build !== 'dev' ? build : null;
}

/** The new build's worker once it's installed and waiting (asking the server for it first), or null in `ms`. */
async function waitingWorker(registration, ms = READY_MS) {
    if (!registration) return null;
    await registration.update().catch((error) => console.debug('No update check', error?.message ?? error));
    // One still installing is newer than one waiting (two deploys while the page was open): that's the one to wait for,
    // or the reload would land on the build between and ask again.
    const installing = registration.installing;
    if (!installing) return registration.waiting ?? null;
    return new Promise((resolve) => {
        const done = (worker) => {
            clearTimeout(timer);
            resolve(worker);
        };
        const timer = setTimeout(() => done(null), ms);
        installing.addEventListener('statechange', () => {
            if (installing.state === 'installed') done(registration.waiting ?? installing);
            if (installing.state === 'redundant') done(null);
        });
    });
}

/** Has `worker` take over this page; true once it's in charge. */
function takeOver(worker) {
    return new Promise((resolve) => {
        const timer = setTimeout(() => resolve(false), SWITCH_MS);
        navigator.serviceWorker.addEventListener(
            'controllerchange',
            () => {
                clearTimeout(timer);
                resolve(true);
            },
            { once: true },
        );
        worker.postMessage({ type: 'skip-waiting' });
    });
}

/** Reloads into the newest build: its waiting worker first, else without a worker (from the network). */
export async function reloadIntoNew({ reload = () => location.reload() } = {}) {
    const registration = await navigator.serviceWorker?.getRegistration().catch(() => null);
    const worker = await waitingWorker(registration);
    if (!(worker && (await takeOver(worker))) && registration) {
        // No new worker in time: let this one go, so the reload asks the server; the page registers the new build again.
        await registration.unregister().catch((error) => console.warn('The old worker was not let go', error));
    }
    reload();
}

/**
 * The app just opened on an older build than the server's, with its new worker coming: ready within SILENT_MS and nothing
 * done or playing, it takes over and the page reloads on it. Otherwise the prompt asks, as on a page in use.
 */
export async function updateAtLaunch(registration, { reload = () => location.reload() } = {}) {
    const worker = await waitingWorker(registration, Math.max(0, SILENT_MS - performance.now()));
    if (!worker || !silentAllowed({ elapsedMs: performance.now(), touched, playing: sounding() })) return false;
    // Once the new worker is in charge the page reloads, touched or not: running on, it would be served the new build.
    if (!(await takeOver(worker))) return false;
    reload();
    return true;
}
