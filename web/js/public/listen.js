// The opt-in mic for a course page's demo (Perfect your pitch's Bullseye): listen() asks for the mic
// only when the visitor taps, reads its pitch with the app's own tracker (in a worker), and stop()
// lets it go; a deviceId picks the mic (js/public/mic-pick.js), and use() moves to another mid-round.
// dart() says where a sung note landed, judged as the Bullseye lesson judges it. Nothing
// is recorded or sent: the readings live in the page until it stops. Tested in tests/public-listen.test.js.

import { audio, hz } from './voices.js';
import { landing, LANDING_FULL } from '../pitch-skills.js';

const RATE = 100; // readings a second, as the lessons score them
const WINDOW = 2048;

/** Why the mic couldn't be had, in a word the page can branch on: 'denied', 'no-mic' or 'unsupported'. */
export function reasonOf(error) {
    if (['NotAllowedError', 'SecurityError', 'PermissionDeniedError'].includes(error?.name)) return 'denied';
    if (['NotFoundError', 'OverconstrainedError', 'NotReadableError', 'DevicesNotFoundError'].includes(error?.name)) return 'no-mic';
    return 'unsupported';
}

const refused = (reason) => Object.assign(new Error(reason), { reason });

// The raw voice, from the mic asked for: one that's gone (unplugged, or an id from another visit) falls
// back to the default rather than failing.
async function open(deviceId) {
    const raw = { echoCancellation: false, noiseSuppression: false, autoGainControl: false };
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
 * The mic, opened (call it from a tap). `onFrame` hears each reading: `{ at, hz, midi, cents, clear }`
 * (`at` on the audio clock, `midi` fractional and null when unvoiced, `cents` from the target set
 * with `target(midi)` or else from the nearest note). Resolves to `{ stop, target, since }`, where
 * `since(at)` is every reading from audio time `at` on, for dart(). Rejects with an error whose
 * `reason` is 'denied', 'no-mic' or 'unsupported', so the page can fall back to a demo without it.
 */
export async function listen({ onFrame = null, deviceId = '' } = {}) {
    if (!navigator.mediaDevices?.getUserMedia || typeof Worker === 'undefined' || typeof AnalyserNode === 'undefined') throw refused('unsupported');
    let stream;
    try {
        stream = await open(deviceId);
    } catch (error) {
        throw refused(reasonOf(error));
    }
    const c = audio();
    let source = c.createMediaStreamSource(stream);
    const analyser = c.createAnalyser();
    analyser.fftSize = WINDOW;
    source.connect(analyser); // never to the speakers
    const worker = new Worker(new URL('./listen-worker.js', import.meta.url), { type: 'module' });
    const frames = [];
    let goal = null;
    let busy = false;
    let raf = 0;
    worker.onmessage = ({ data }) => {
        busy = false;
        const voiced = data.hz > 0;
        const midi = voiced ? 69 + 12 * Math.log2(data.hz / 440) : null;
        const cents = midi === null ? null : Math.round(100 * (midi - (goal ?? Math.round(midi))));
        const frame = { at: data.at, hz: voiced ? data.hz : 0, midi, cents, clear: voiced };
        frames.push(frame);
        onFrame?.(frame);
    };
    // A reading each animation frame, skipped while the worker is still on the last.
    const tick = () => {
        raf = requestAnimationFrame(tick);
        if (busy || c.state !== 'running') return;
        const samples = new Float32Array(WINDOW);
        analyser.getFloatTimeDomainData(samples);
        busy = true;
        worker.postMessage({ samples, sampleRate: c.sampleRate, at: c.currentTime }, [samples.buffer]);
    };
    raf = requestAnimationFrame(tick);
    return {
        target(midi) {
            goal = midi;
        },
        /** The mic in use now (its deviceId, '' when the browser doesn't say). */
        deviceId() {
            return stream.getAudioTracks()[0]?.getSettings?.().deviceId ?? '';
        },
        /** Moves to another mic, mid-round too: the readings carry on from the new one. */
        async use(id) {
            const next = await open(id);
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

/**
 * Where a sung note landed against `midi` (any octave), from listen()'s readings: `{ cents, landed,
 * clean, start }`, `landed` within the lesson's ±25 cents, `clean` when it started on the note
 * rather than sliding in ('scoop', 'fall' or 'slide' in `start`). Null when nothing was heard.
 */
export function dart(frames, midi) {
    if (!frames.length) return null;
    // The readings come as fast as the screen draws: laid on the lessons' even 100 a second.
    const from = frames[0].at;
    const cents = new Array(Math.max(1, Math.round((frames.at(-1).at - from) * RATE) + 1)).fill(null);
    for (const f of frames) if (f.clear && f.hz > 0) cents[Math.round((f.at - from) * RATE)] = 1200 * Math.log2(f.hz / hz(midi));
    // The gaps between readings (a slot or two), held from the one before.
    for (let i = 1; i < cents.length; i++) if (cents[i] === null && cents[i - 1] !== null && cents.slice(i + 1, i + 3).some((x) => x !== null)) cents[i] = cents[i - 1];
    const l = landing(cents, RATE);
    if (!l) return null;
    return { cents: l.cents, landed: Math.abs(l.cents) <= LANDING_FULL, clean: l.start === 'clean', start: l.start };
}
