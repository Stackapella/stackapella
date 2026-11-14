// The site's opt-in mic, read live (the free tools, the course pages' demos): listen() asks for the
// mic only when the visitor taps, reads its pitch with the app's own tracker in a worker
// (listen-worker.js, pitchtrack.js), and stop() lets it go; a deviceId picks the mic
// (js/public/mic-pick.js), and use() moves to another mid-round. Nothing is recorded or sent: the
// readings live in the page until it stops. Lean on purpose (no scoring code), so a tool that only
// reads pitch loads just this; listen.js adds dart() for the course demos. Tested in
// tests/public-listen.test.js.

import { audio } from './voices.js';
import { hzToMidi } from '../dsp.js';

/**
 * The pitch tracker's files, which load only when the mic opens (the worker and what it imports): a tool
 * exports them as WARM, and the tools' page has the service worker keep them, so a tool works offline
 * before its first Start (js/public/tools/page.js, web/tools-sw.js "tools-warm").
 */
export const TRACKER_FILES = ['./listen-worker.js', '../listen.js', '../pitchtrack.js', '../dsp.js'].map((path) => new URL(path, import.meta.url).pathname);

const WINDOW = 2048;
const KEEP_SECONDS = 30; // since() looks back this far at most (a round is a few seconds)

/** Why the mic couldn't be had, in a word the page can branch on: 'denied', 'no-mic' or 'unsupported'. */
export function reasonOf(error) {
    if (['NotAllowedError', 'SecurityError', 'PermissionDeniedError'].includes(error?.name)) return 'denied';
    if (['NotFoundError', 'OverconstrainedError', 'NotReadableError', 'DevicesNotFoundError'].includes(error?.name)) return 'no-mic';
    return 'unsupported';
}

// The browser's own error rides along as the standard `cause`, for a page that says more (the mic test:
// a NotReadableError is another app holding the mic).
const refused = (reason, error = null) => Object.assign(new Error(reason, error ? { cause: error } : undefined), { reason });

// The raw voice, from the mic asked for: one that's gone (unplugged, or an id from another visit) falls
// back to the default rather than failing.
async function open(deviceId, { cancelEcho = false } = {}) {
    // Raw, as the app hears a voice; a tool that sounds through the speaker while it listens (a drone)
    // can ask the browser to take its own sound back out (`cancelEcho`), leaving the rest untouched.
    const raw = { echoCancellation: cancelEcho, noiseSuppression: false, autoGainControl: false };
    if (!deviceId) return navigator.mediaDevices.getUserMedia({ audio: raw });
    try {
        return await navigator.mediaDevices.getUserMedia({ audio: { ...raw, deviceId: { exact: deviceId } } });
    } catch (error) {
        if (!['OverconstrainedError', 'NotFoundError', 'NotReadableError'].includes(error?.name)) throw error;
        console.warn('The chosen mic is not available; using the default', error);
        return navigator.mediaDevices.getUserMedia({ audio: raw });
    }
}

/**
 * The mic, opened (call it from a tap). `onFrame` hears each reading:
 * `{ at, hz, midi, cents, clear, loudness, clarity, peak }` (`peak` the loudest sample, 0 to 1) (`at` on the audio clock, `midi` fractional and
 * null when unvoiced, `cents` from the target set with `target(midi)` or else from the nearest note,
 * `loudness` in dBFS). The tracker's reach can be changed: `minHz` and `maxHz`, `window` (samples
 * per reading: 4096 for an instrument's low strings) and `quietDb` (readings quieter than this are
 * unvoiced). `cancelEcho` turns on the browser's echo cancellation, for a tool that plays through the
 * speaker while it listens (the intonation meter's drone), so the mic hears the singer, not the drone. Resolves to `{ stop, target, since, deviceId, use }`, where `since(at)` is every reading
 * from audio time `at` on (the last 30 seconds at most). Rejects with an error whose `reason` is
 * 'denied', 'no-mic' or 'unsupported', so the page can fall back without it.
 */
export async function listen({ onFrame = null, deviceId = '', minHz = null, maxHz = null, window = WINDOW, quietDb = null, cancelEcho = false } = {}) {
    if (!navigator.mediaDevices?.getUserMedia || typeof Worker === 'undefined' || typeof AnalyserNode === 'undefined') throw refused('unsupported');
    let stream;
    try {
        stream = await open(deviceId, { cancelEcho });
    } catch (error) {
        throw refused(reasonOf(error), error);
    }
    const c = audio();
    let source = c.createMediaStreamSource(stream);
    const analyser = c.createAnalyser();
    analyser.fftSize = window;
    source.connect(analyser); // never to the speakers
    const worker = new Worker(new URL('./listen-worker.js', import.meta.url), { type: 'module' });
    const frames = [];
    const reach = { minHz, maxHz, quietDb };
    let goal = null;
    let busy = false;
    let raf = 0;
    let peak = 0; // the loudest sample of the window in flight (one at a time), so a clip shows however short
    worker.onmessage = ({ data }) => {
        busy = false;
        const voiced = data.hz > 0;
        const midi = voiced ? hzToMidi(data.hz) : null;
        const cents = midi === null ? null : Math.round(100 * (midi - (goal ?? Math.round(midi))));
        const frame = { at: data.at, hz: voiced ? data.hz : 0, midi, cents, clear: voiced, loudness: data.loudness, clarity: data.clarity, peak };
        frames.push(frame);
        while (frames.length && frame.at - frames[0].at > KEEP_SECONDS) frames.shift();
        onFrame?.(frame);
    };
    // A reading each animation frame, skipped while the worker is still on the last.
    const tick = () => {
        raf = requestAnimationFrame(tick);
        if (busy || c.state !== 'running') return;
        const samples = new Float32Array(window);
        analyser.getFloatTimeDomainData(samples);
        peak = 0;
        for (const x of samples) if (Math.abs(x) > peak) peak = Math.abs(x);
        busy = true;
        worker.postMessage({ samples, sampleRate: c.sampleRate, at: c.currentTime, ...reach }, [samples.buffer]);
    };
    raf = requestAnimationFrame(tick);
    return {
        target(midi) {
            goal = midi;
        },
        /** The open stream itself (the mic test's Record 5 s records it; nothing else should keep it). use() swaps it, so take it again after. */
        stream() {
            return stream;
        },
        /** The mic in use now (its deviceId, '' when the browser doesn't say). */
        deviceId() {
            return stream.getAudioTracks()[0]?.getSettings?.().deviceId ?? '';
        },
        /** Moves to another mic, mid-round too: the readings carry on from the new one. */
        async use(id) {
            const next = await open(id, { cancelEcho });
            source.disconnect();
            stream.getTracks().forEach((track) => track.stop());
            stream = next;
            source = c.createMediaStreamSource(stream);
            source.connect(analyser);
        },
        since(at) {
            return frames.filter((f) => f.at >= at);
        },
        stop() {
            cancelAnimationFrame(raf);
            worker.terminate();
            source.disconnect();
            stream.getTracks().forEach((track) => track.stop());
            frames.length = 0;
        },
    };
}
