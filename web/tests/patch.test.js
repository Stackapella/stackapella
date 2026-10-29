// Re-sing a phrase (js/patch.js): joins at quiet points (never mid-note), loudness matched, the
// splice sample-exact with no click at either join, and a clear difference in the room noticed.
import { energy, FADE_SECONDS, joinPoints, matchGain, rmsDb, soundsDifferent, splice } from '/js/patch.js';
import { assert, test } from './harness.js';

const SR = 48000;
const BEAT = 0.5;

/** A sung line: each note a voice-like tone with a soft attack and release, silence in the rests;
 * notes that touch are sung legato (no dip between them), with the phase carried across. */
function sing(notes, seconds, { gain = 0.3, phase = 0, noise = 0.0005, seed = 1 } = {}) {
    let r = seed;
    const random = () => ((r = (r * 16807) % 2147483647) / 2147483647) * 2 - 1;
    const out = new Float32Array(Math.round(seconds * SR));
    for (let i = 0; i < out.length; i++) out[i] = noise * random();
    let theta = phase;
    notes.forEach((n, k) => {
        const hz = 440 * 2 ** ((n.pitch - 69) / 12);
        const from = Math.round(n.start * SR);
        const to = Math.round(n.end * SR);
        const tiedIn = k > 0 && notes[k - 1].end >= n.start - 1e-6;
        const tiedOut = k < notes.length - 1 && notes[k + 1].start <= n.end + 1e-6;
        if (!tiedIn) theta = phase;
        for (let i = from; i < to && i < out.length; i++) {
            const t = (i - from) / SR;
            const env = Math.min(1, tiedIn ? 1 : t / 0.03, tiedOut ? 1 : (to - i) / SR / 0.04);
            theta += (2 * Math.PI * hz) / SR;
            let v = 0;
            for (let h = 1; h <= 5; h++) v += Math.sin(theta * h) / h;
            out[i] += gain * env * v * 0.5;
        }
    });
    return out;
}

// Two phrases with a rest between (2.0 to 2.25 s), the second phrase "I once was lost".
const NOTES = [
    { start: 0.0, end: 0.45, pitch: 62, lyric: 'that' }, { start: 0.5, end: 0.95, pitch: 64, lyric: 'saved' },
    { start: 1.0, end: 1.95, pitch: 62, lyric: 'me.' },
    { start: 2.25, end: 2.7, pitch: 62, lyric: 'I' }, { start: 2.75, end: 3.2, pitch: 64, lyric: 'once' },
    { start: 3.25, end: 3.7, pitch: 66, lyric: 'was' }, { start: 3.75, end: 4.6, pitch: 64, lyric: 'lost,' },
    { start: 4.9, end: 5.4, pitch: 62, lyric: 'but' },
];

test('patch: the joins go in the rests around the phrase', () => {
    const take = sing(NOTES, 6);
    const j = joinPoints({ take, sampleRate: SR, takeStartSeconds: 0, notes: NOTES, startSeconds: 2.25, endSeconds: 4.6, beatSeconds: BEAT });
    assert(j.from > 1.95 && j.from < 2.25, `start join in the rest: ${j.from.toFixed(3)}`);
    assert(j.to > 4.6 && j.to < 4.9, `end join in the rest: ${j.to.toFixed(3)}`);
    assert(j.singFrom === null && j.singTo === null, 'nothing to sing on to');
});

test('patch: written legato, the join goes at the quietest boundary between notes near the edge', () => {
    // As the arrangements are written: no gaps, so the join is between two notes.
    const legato = [...NOTES.slice(0, 6), { start: 3.75, end: 4.6, pitch: 64, lyric: 'lost,' }, { start: 4.6, end: 5.1, pitch: 62, lyric: 'but' }, { start: 5.1, end: 5.6, pitch: 64, lyric: 'now' }];
    const take = sing(legato, 6);
    const j = joinPoints({ take, sampleRate: SR, takeStartSeconds: 0, notes: legato, startSeconds: 2.25, endSeconds: 4.6, beatSeconds: BEAT });
    assert(Math.abs(j.to - 4.6) < 0.08 && j.singTo === null, `between "lost," and "but": ${j.to.toFixed(3)}, ${j.singTo}`);
});

test('patch: a note held across the edge moves the join to its end, and names the word', () => {
    // "lost," is held from 3.75 to 5.4 s, right across the phrase's edge at 4.6 s, into "but".
    const held = [...NOTES.slice(0, 6), { start: 3.75, end: 5.4, pitch: 64, lyric: 'lost,' }, { start: 5.4, end: 5.9, pitch: 62, lyric: 'but' }];
    const take = sing(held, 6.5);
    const j = joinPoints({ take, sampleRate: SR, takeStartSeconds: 0, notes: held, startSeconds: 2.25, endSeconds: 4.6, beatSeconds: BEAT });
    assert(j.to > 5.3 && j.to < 5.5, `out to the end of "lost,": ${j.to.toFixed(3)}`);
    assert(j.singTo === 'lost', `sing on to: ${j.singTo}`);
});

test('patch: the new phrase is matched in loudness, within 6 dB', () => {
    const around = sing(NOTES, 6, { gain: 0.3 });
    const quieter = sing(NOTES, 6, { gain: 0.3 * 10 ** (-4 / 20), phase: 1.3 });
    const g = 20 * Math.log10(matchGain(around, quieter, SR));
    assert(Math.abs(g - 4) < 0.5, `+4 dB: ${g.toFixed(2)}`);
    const whisper = sing(NOTES, 6, { gain: 0.3 * 10 ** (-15 / 20), phase: 0.4 });
    assert(Math.abs(20 * Math.log10(matchGain(around, whisper, SR)) - 6) < 0.01, 'capped at +6 dB');
});

test('patch: the splice is sample-exact and has no click at either join', () => {
    const take = sing(NOTES, 6);
    const again = sing(NOTES, 6, { gain: 0.3 * 10 ** (-3 / 20), phase: 2.1, seed: 9 }); // the phrase sung again
    const j = joinPoints({ take, sampleRate: SR, takeStartSeconds: 0, notes: NOTES, startSeconds: 2.25, endSeconds: 4.6, beatSeconds: BEAT });
    const fromFrame = Math.round(j.from * SR);
    const toFrame = Math.round(j.to * SR);
    // The new recording starts 0.4 s before the join (with its count-in), so it's offset.
    const patchStart = fromFrame - Math.round(0.4 * SR);
    const patch = again.subarray(patchStart, toFrame + Math.round(0.3 * SR));
    const gain = matchGain(take.subarray(fromFrame - SR, toFrame + SR), patch, SR);
    const fade = Math.round(FADE_SECONDS * SR);
    const out = splice(take, patch, { fromFrame, toFrame, patchStart, gain, fadeFrames: fade });
    assert(out.length === take.length, 'the same length');
    const half = fade >> 1;
    for (const i of [0, 1000, fromFrame - half - 1, toFrame + half, take.length - 1]) assert(out[i] === take[i], `untouched outside at ${i}`);
    for (const i of [fromFrame + half, Math.round((fromFrame + toFrame) / 2), toFrame - half - 1]) assert(Math.abs(out[i] - patch[i - patchStart] * gain) < 1e-6, `the patch inside at ${i}`);
    // No click: no step at a join bigger than the signals' own steps.
    const step = (a, from, to) => { let m = 0; for (let i = Math.max(1, from); i < to; i++) m = Math.max(m, Math.abs(a[i] - a[i - 1])); return m; };
    const usual = Math.max(step(take, 0, take.length), step(patch, 0, patch.length) * gain);
    for (const join of [fromFrame, toFrame]) {
        const s = step(out, join - fade, join + fade);
        assert(s <= usual * 1.05, `a step of ${s.toFixed(4)} at ${join} (usual ${usual.toFixed(4)})`);
        // No energy spike across the crossfade.
        const around = Math.max(rmsDb(out, join - 3 * fade, join - fade), rmsDb(out, join + fade, join + 3 * fade));
        assert(rmsDb(out, join - half, join + half) <= around + 1, `no bump at ${join}`);
    }
});

test('patch: a clearly different room is noticed; the same room is not', () => {
    const take = sing(NOTES, 6);
    const same = sing(NOTES, 6, { phase: 0.7, seed: 3 });
    const noisy = sing(NOTES, 6, { phase: 0.7, noise: 0.02, seed: 4 });
    assert(!soundsDifferent(take, same, SR), 'the same room');
    assert(soundsDifferent(take, noisy, SR), 'a noisier room');
    assert(energy(take, SR).frames.length === 600, 'frames of 10 ms');
});
