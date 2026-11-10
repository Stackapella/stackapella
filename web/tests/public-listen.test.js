// The course pages' opt-in mic (js/public/listen.js): why the mic was refused, in a word; the worker
// reading a sung pitch; and dart() judging a landing as the Bullseye lesson does, from readings that
// come as fast as the screen draws.
import { dart, reasonOf } from '/js/public/listen.js';
import { assert, test } from './harness.js';

/** Readings at 60 a second for `seconds`, the pitch `cents` from A3 at each moment (null: unvoiced). */
const readings = (seconds, cents) => Array.from({ length: Math.round(seconds * 60) }, (_, i) => {
    const t = i / 60;
    const c = cents(t);
    return c === null ? { at: 10 + t, hz: 0, clear: false } : { at: 10 + t, hz: 220 * 2 ** (c / 1200), clear: true };
});

test('site mic: a refused mic says why in a word the page can branch on', () => {
    assert(reasonOf({ name: 'NotAllowedError' }) === 'denied' && reasonOf({ name: 'SecurityError' }) === 'denied', 'denied');
    assert(reasonOf({ name: 'NotFoundError' }) === 'no-mic' && reasonOf({ name: 'NotReadableError' }) === 'no-mic', 'no mic');
    assert(reasonOf({ name: 'TypeError' }) === 'unsupported' && reasonOf(undefined) === 'unsupported', 'anything else');
});

test('site mic: the worker reads a sung A3 with the app\'s tracker', async () => {
    const worker = new Worker(new URL('/js/public/listen-worker.js', location.href), { type: 'module' });
    const rate = 48000;
    const samples = Float32Array.from({ length: 2048 }, (_, i) => 0.3 * Math.sin((2 * Math.PI * 220 * i) / rate) + 0.1 * Math.sin((2 * Math.PI * 440 * i) / rate));
    const frame = await new Promise((resolve) => {
        worker.onmessage = ({ data }) => resolve(data);
        worker.postMessage({ samples, sampleRate: rate, at: 3 });
    });
    worker.terminate();
    assert(frame.at === 3 && Math.abs(frame.hz - 220) < 2, `heard ${frame.hz} at ${frame.at}`);
});

test('site mic: a dart lands within the lesson\'s 25 cents, a scoop isn\'t clean, and nothing heard is null', () => {
    const on = dart(readings(1, () => 12), 57);
    assert(on && on.landed && on.clean && Math.abs(on.cents - 12) <= 2, `on: ${JSON.stringify(on)}`);
    const octave = dart(readings(1, () => -1200 + 5), 57);
    assert(octave?.landed, `an octave down is the same note: ${JSON.stringify(octave)}`);
    const off = dart(readings(1, () => 45), 57);
    assert(off && !off.landed && off.cents >= 40, `off: ${JSON.stringify(off)}`);
    const scoop = dart(readings(1, (t) => (t < 0.25 ? -120 + 480 * t : 0)), 57);
    assert(scoop && scoop.landed && !scoop.clean && scoop.start === 'scoop', `scoop: ${JSON.stringify(scoop)}`);
    const late = dart(readings(1, (t) => (t < 0.3 ? null : -8)), 57);
    assert(late?.landed && late.clean, `silence first, then the note: ${JSON.stringify(late)}`);
    assert(dart([], 57) === null && dart(readings(1, () => null), 57) === null, 'nothing heard');
});

test('site mic: raw by default; a tool that sounds while it listens can ask for the browser\'s echo cancellation', async () => {
    const { listen } = await import('/js/public/live-mic.js');
    const media = navigator.mediaDevices;
    const real = media.getUserMedia;
    const asked = [];
    // A stand-in mic: records what was asked for, then refuses, so nothing opens.
    media.getUserMedia = async (constraints) => {
        asked.push(constraints.audio);
        throw Object.assign(new Error('test'), { name: 'NotAllowedError' });
    };
    try {
        for (const cancelEcho of [false, true]) {
            try {
                await listen({ cancelEcho });
            } catch (error) {
                assert(error.reason === 'denied' && error.cause?.name === 'NotAllowedError', `refused: ${error.reason}`);
            }
        }
    } finally {
        media.getUserMedia = real;
    }
    assert(asked[0].echoCancellation === false && asked[0].noiseSuppression === false && asked[0].autoGainControl === false, `raw: ${JSON.stringify(asked[0])}`);
    assert(asked[1].echoCancellation === true && asked[1].noiseSuppression === false && asked[1].autoGainControl === false, `echo cancelled, nothing else: ${JSON.stringify(asked[1])}`);
});
