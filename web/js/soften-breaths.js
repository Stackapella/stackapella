// A recorded voice's breaths softened wherever it's heard apart (Hillel, 2026-11-16: "can we aply thsi techinique
// inegenral ,maybe as an aoption to autop remove breathign?"; his choice: "Library voices on, own takes optional").
// The work is js/breaths.js's (the showreel's own code, kept identical to it): only the unpitched air in the gaps a
// part's notes leave, every sung note untouched. This is how the app and the site call it: with the part's notes (the
// template's, with their phrase starts), the song's seconds a beat, and where the voice's first sample sits in the
// song, since a recording or a take needn't start at the song's start.
// It runs once on a voice's decoded samples, before anything plays, never while audio plays, and in a worker
// (js/soften-worker.js), so a long gap (a part that comes in late: half a second of work on a slow phone) never holds
// the page still. Where a worker can't be had, it runs here a gap at a time with the page let in between.

import { duckBreaths, gaps } from './breaths.js';

/** The page's own work let in (a paint, a tap) before the next piece. Not audio timing. */
function breathe() {
    return new Promise((resolve) => {
        const channel = new MessageChannel();
        channel.port1.onmessage = () => resolve();
        channel.port2.postMessage(null);
    });
}

/**
 * The gaps of a part as seconds into a voice that starts `offsetSeconds` into the song: [[from, to]], none when the
 * part has no notes (a part nobody wrote has no gaps to tell breaths by). Pure.
 */
export function breathWindows(notes, secondsPerBeat, offsetSeconds = 0) {
    if (!Array.isArray(notes) || !notes.length || !(secondsPerBeat > 0)) return [];
    return gaps(notes, secondsPerBeat).map(([from, to]) => [from - offsetSeconds, to - offsetSeconds]);
}

let worker = null; // the worker, false where module workers can't be had
let asked = 0;
const waiting = new Map(); // each ask's answer, by its number

/** A copy of `samples` softened in the worker; null where there's no worker (or it failed), for the caller to do it here. */
function inWorker(samples, rate, windows) {
    if (worker === null) {
        try {
            worker = new Worker(new URL('./soften-worker.js', import.meta.url), { type: 'module' });
            worker.onmessage = ({ data }) => {
                waiting.get(data.id)?.(data.samples);
                waiting.delete(data.id);
            };
            // A worker that can't start (an old browser, a blocked script): every ask is answered here instead.
            worker.onerror = () => {
                worker = false;
                for (const answer of waiting.values()) answer(null);
                waiting.clear();
            };
        } catch {
            worker = false;
        }
    }
    if (!worker) return Promise.resolve(null);
    return new Promise((resolve) => {
        const id = ++asked;
        const copy = Float32Array.from(samples); // its own buffer, handed over
        waiting.set(id, resolve);
        worker.postMessage({ id, samples: copy, rate, windows }, [copy.buffer]);
    });
}

/**
 * `samples` (at `rate`) with its breaths softened, in place: for a voice just decoded, while it loads. Resolves to
 * `samples`.
 */
export async function softenBreaths(samples, rate, notes, secondsPerBeat, offsetSeconds = 0) {
    const windows = breathWindows(notes, secondsPerBeat, offsetSeconds);
    if (!windows.length) return samples;
    const soft = await inWorker(samples, rate, windows);
    if (soft) {
        samples.set(soft);
        return samples;
    }
    for (const window of windows) {
        duckBreaths(samples, rate, [window]);
        await breathe();
    }
    return samples;
}

/**
 * A copy of `samples` with its breaths softened, made off the main thread: for a singer's own take, whose original is
 * never changed. The same array when the part has no notes to tell breaths by.
 */
export async function softenedCopy(samples, rate, notes, secondsPerBeat, offsetSeconds = 0) {
    const windows = breathWindows(notes, secondsPerBeat, offsetSeconds);
    if (!windows.length) return samples;
    return (await inWorker(samples, rate, windows)) ?? duckBreaths(Float32Array.from(samples), rate, windows);
}

/**
 * A copy of `samples` with its breaths softened, at once: for a singer's own take, whose original is never changed.
 * The same array when the part has no notes to tell breaths by. Pure.
 */
export function softened(samples, rate, notes, secondsPerBeat, offsetSeconds = 0) {
    const windows = breathWindows(notes, secondsPerBeat, offsetSeconds);
    return windows.length ? duckBreaths(Float32Array.from(samples), rate, windows) : samples;
}
