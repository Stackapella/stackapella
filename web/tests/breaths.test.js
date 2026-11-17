// The breaths of a recorded voice, ducked (js/breaths.js, the showreel's own code shared as it is; js/soften-breaths.js is how the app and the site call it): only the
// unpitched air in the gaps a part's notes leave, every sung note untouched, and nothing clicks where a gap starts or ends.
import { assert, test } from './harness.js';
import { duckBreaths, gaps } from '/js/breaths.js';
import { breathWindows, softenBreaths, softened } from '/js/soften-breaths.js';

const RATE = 48000;
const rms = (x, from, to) => {
    let sum = 0;
    for (let i = Math.round(from * RATE); i < Math.round(to * RATE); i++) sum += x[i] * x[i];
    return Math.sqrt(sum / Math.round((to - from) * RATE));
};

/** Three seconds of a voice: a note, a rest with a breath in it (noise, 1.3 to 1.7 s), a note. The same every time. */
function voice() {
    const samples = new Float32Array(3 * RATE);
    let seed = 12345;
    const noise = () => ((seed = (seed * 1103515245 + 12345) % 2147483648) / 1073741824) - 1;
    for (let i = 0; i < samples.length; i++) {
        const t = i / RATE;
        if (t < 1 || t >= 2) samples[i] = 0.3 * Math.sin(2 * Math.PI * 220 * t);
        else if (t >= 1.3 && t < 1.7) samples[i] = 0.1 * noise();
    }
    return samples;
}
// Its part: a note of two beats, two beats' rest, a note of two beats, at 120 a minute.
const NOTES = [{ beat: 0, length: 2 }, { beat: 4, length: 2 }];

test('breaths: a part\'s gaps are its rests and its new phrases, a little wider than written, and its lead-in', () => {
    const near = (a, b) => Math.abs(a - b) < 1e-9;
    const rest = gaps(NOTES, 0.5);
    assert(rest.length === 1 && near(rest[0][0], 0.65) && near(rest[0][1], 2.1), `the rest: ${JSON.stringify(rest)}`);
    // Notes that touch leave no gap, unless the next starts a phrase (its `br`).
    assert(gaps([{ beat: 0, length: 2 }, { beat: 2, length: 2 }], 0.5).length === 0, 'legato: no gap');
    const phrase = gaps([{ beat: 0, length: 2 }, { beat: 2, length: 2, br: true }], 0.5);
    assert(phrase.length === 1 && near(phrase[0][0], 0.65) && near(phrase[0][1], 1.1), `a new phrase: ${JSON.stringify(phrase)}`);
    // A part that comes in late: everything before its first note.
    const late = gaps([{ beat: 4, length: 2 }], 0.5);
    assert(late.length === 1 && late[0][0] === 0 && near(late[0][1], 2.1), `the lead-in: ${JSON.stringify(late)}`);
});

test('breaths: a breath in a rest is ducked, the sung notes are untouched, and nothing clicks at the gap\'s edges', () => {
    const before = voice();
    const after = duckBreaths(Float32Array.from(before), RATE, gaps(NOTES, 0.5));
    // The breath: some 30 dB down.
    const was = rms(before, 1.35, 1.65);
    const now = rms(after, 1.35, 1.65);
    assert(was > 0.05 && now < was * 0.05, `the breath is ducked: ${was.toFixed(4)} then ${now.toFixed(5)}`);
    // Outside the gap not one sample changes.
    let changed = 0;
    for (let i = 0; i < before.length; i++) {
        const t = i / RATE;
        if ((t < 0.65 || t > 2.1) && after[i] !== before[i]) changed += 1;
    }
    assert(changed === 0, `outside the gap nothing changes: ${changed} samples did`);
    // Inside it, the notes' own ends and starts (the gap is wider than the rest) keep their level.
    assert(rms(after, 0.68, 0.98) > rms(before, 0.68, 0.98) * 0.97, `the note's end is kept: ${rms(after, 0.68, 0.98)} of ${rms(before, 0.68, 0.98)}`);
    assert(rms(after, 2.0, 2.09) > rms(before, 2.0, 2.09) * 0.97, 'the next note\'s start is kept');
    // No click: the gain only ever moves through its fade, so no step is bigger than the voice's own biggest.
    const step = (x) => {
        let most = 0;
        for (let i = Math.round(0.6 * RATE); i < Math.round(2.15 * RATE); i++) most = Math.max(most, Math.abs(x[i] - x[i - 1]));
        return most;
    };
    assert(step(after) <= step(before) + 1e-6, `no step bigger than the voice's own: ${step(after)} against ${step(before)}`);
    // And the same samples always give the same result.
    const again = duckBreaths(Float32Array.from(before), RATE, gaps(NOTES, 0.5));
    assert(again.every((v, i) => v === after[i]), 'the same every time');
});

test('breaths: a held note through a gap, and a gap too short to judge, are left alone', () => {
    const held = new Float32Array(3 * RATE);
    for (let i = 0; i < held.length; i++) held[i] = 0.3 * Math.sin(2 * Math.PI * 330 * (i / RATE));
    const after = duckBreaths(Float32Array.from(held), RATE, [[0.65, 2.1]]);
    assert(rms(after, 1.2, 1.8) > rms(held, 1.2, 1.8) * 0.97, 'pitched sound in a gap is kept');
    const short = voice();
    assert(duckBreaths(Float32Array.from(short), RATE, [[1.4, 1.42]]).every((v, i) => v === short[i]), 'a gap shorter than a frame changes nothing');
});

test('breaths: a voice that starts part way into the song has its gaps moved by its offset, and a part with no notes has none', () => {
    const near = (a, b) => Math.abs(a - b) < 1e-9;
    const plain = breathWindows(NOTES, 0.5);
    assert(plain.length === 1 && near(plain[0][0], 0.65) && near(plain[0][1], 2.1), `at the song's start: ${JSON.stringify(plain)}`);
    const late = breathWindows(NOTES, 0.5, 0.5);
    assert(near(late[0][0], 0.15) && near(late[0][1], 1.6), `half a second in: ${JSON.stringify(late)}`);
    assert(breathWindows([], 0.5).length === 0 && breathWindows(null, 0.5).length === 0 && breathWindows(NOTES, 0).length === 0, 'no notes or no tempo: no gaps');
});

test('breaths: softened in place while a voice loads, or as a copy that leaves a take as it was', async () => {
    const original = voice();
    // In place, for a voice just decoded: the same array, its breath ducked.
    const decoded = Float32Array.from(original);
    const same = await softenBreaths(decoded, RATE, NOTES, 0.5);
    assert(same === decoded && rms(decoded, 1.35, 1.65) < rms(original, 1.35, 1.65) * 0.05, 'in place, the breath ducked');
    // A copy, for a take: the take's own samples are never changed, and the two ways agree sample for sample.
    const take = Float32Array.from(original);
    const copy = softened(take, RATE, NOTES, 0.5);
    assert(copy !== take && take.every((v, i) => v === original[i]), 'the take is as it was');
    assert(copy.every((v, i) => v === decoded[i]), 'the copy is what softening in place gives');
    // A take recorded from half a second into the song: the same breath, half a second earlier in its samples.
    const shifted = original.subarray(RATE / 2);
    const moved = softened(shifted, RATE, NOTES, 0.5, 0.5);
    assert(rms(moved, 0.85, 1.15) < rms(shifted, 0.85, 1.15) * 0.05 && rms(moved, 0.2, 0.45) > rms(shifted, 0.2, 0.45) * 0.97, 'the gaps follow the take\'s start');
    // No notes to tell breaths by: the very same array, untouched.
    assert(softened(take, RATE, [], 0.5) === take && (await softenBreaths(take, RATE, null, 0.5)) === take && take.every((v, i) => v === original[i]), 'a part nobody wrote is left alone');
});

test('breaths: a library voice is softened as the app decodes it, by its part\'s notes, and left alone without them', async () => {
    const { loadRecordedGuide } = await import('/js/recorded-guides.js');
    const original = voice();
    const recording = { url: '/never/library-voice.m4a', sampleRate: RATE, frames: original.length, offsetFrames: 0 };
    const fetchImpl = async () => new Response(new Uint8Array(8));
    const decode = async () => ({ getChannelData: () => Float32Array.from(original) });
    const plain = await loadRecordedGuide(recording, { key: 'test-breaths@1:plain', sampleRate: RATE, decode, fetchImpl });
    assert(plain.samples.every((v, i) => v === original[i]), 'without notes the voice is as recorded');
    const soft = await loadRecordedGuide(recording, { key: 'test-breaths@1:soft', sampleRate: RATE, decode, fetchImpl, notes: NOTES, secondsPerBeat: 0.5 });
    assert(rms(soft.samples, 1.35, 1.65) < rms(original, 1.35, 1.65) * 0.05, `with its notes the breath is ducked: ${rms(soft.samples, 1.35, 1.65)}`);
    assert(rms(soft.samples, 0.1, 0.6) === rms(original, 0.1, 0.6) && soft.offsetSeconds === 0, 'and its notes are as sung');
    // A voice that starts after the song does: the gaps are placed by where it starts.
    const late = { ...recording, url: '/never/library-voice-late.m4a', frames: original.length - RATE / 2, offsetFrames: RATE / 2 };
    const lateDecode = async () => ({ getChannelData: () => Float32Array.from(original.subarray(RATE / 2)) });
    const moved = await loadRecordedGuide(late, { key: 'test-breaths@1:late', sampleRate: RATE, decode: lateDecode, fetchImpl, notes: NOTES, secondsPerBeat: 0.5 });
    assert(moved.offsetSeconds === 0.5 && rms(moved.samples, 0.85, 1.15) < 0.005, `placed by its offset: ${rms(moved.samples, 0.85, 1.15)}`);
});
