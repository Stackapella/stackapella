// The breaths of a recorded voice, ducked (js/public/breaths.js, the showreel's own code shared as it is): only the
// unpitched air in the gaps a part's notes leave, every sung note untouched, and nothing clicks where a gap starts or ends.
import { assert, test } from './harness.js';
import { duckBreaths, gaps } from '/js/public/breaths.js';

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
