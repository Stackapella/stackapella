// Trimming (js/trim.js, docs/more-features-plan.md feature 17): the kept stretch is exact to the
// frame with a click-free fade at each cut, the singing keeps its time, "Trim the silence" finds the
// first and last sung notes, and "Line up the ends" gives every part the same start or end.
import { peaks } from '/js/dsp.js';
import { FADE_SECONDS, heardSpan, lineUp, ROOM_SECONDS, silenceTrims, snap, sungSpan, trimmedSamples, trimsOf, trimsTo } from '/js/trim.js';
import { assert, close, test } from './harness.js';

const SR = 48000;

/** A take: room noise, then a sung tone from `from` to `to` seconds (soft attack and release). */
function take(seconds, from, to, { gain = 0.3, noise = 0.002 } = {}) {
    let r = 7;
    const random = () => ((r = (r * 16807) % 2147483647) / 2147483647) * 2 - 1;
    const out = new Float32Array(Math.round(seconds * SR));
    for (let i = 0; i < out.length; i++) {
        const t = i / SR;
        const env = t < from || t > to ? 0 : Math.min(1, (t - from) / 0.04, (to - t) / 0.06);
        out[i] = noise * random() + gain * env * Math.sin(2 * Math.PI * 220 * t);
    }
    return out;
}

const partOf = (samples, fields = {}) => ({ id: 'p', frames: samples.length, sampleRate: SR, framesPerPeak: 512, peaks: peaks(samples, 512), offsetSeconds: 0, nudgeSeconds: 0, ...fields });

test('trim: the kept stretch starts and ends at the exact frames, and the take itself is untouched', () => {
    const samples = take(3, 0.5, 2.5);
    const copy = samples.slice();
    const kept = trimmedSamples(samples, SR, { start: 12345, end: 6789 });
    assert(kept.length === samples.length - 12345 - 6789, `${kept.length} frames`);
    const fade = Math.round(FADE_SECONDS * SR);
    assert(kept[fade + 10] === samples[12345 + fade + 10], 'the middle is the take, sample for sample');
    assert(samples.every((v, i) => v === copy[i]), 'the recording is never changed');
    assert(trimmedSamples(samples, SR, { start: 0, end: 0 }) === samples, 'no trims: the take as it is');
});

test('trim: each cut edge fades over 10 ms, so it never clicks', () => {
    const loud = new Float32Array(SR).fill(0.8);
    const kept = trimmedSamples(loud, SR, { start: 1000, end: 1000 });
    let jump = Math.abs(kept[0]) + Math.abs(kept[kept.length - 1]);
    for (let i = 1; i < kept.length; i++) jump = Math.max(jump, Math.abs(kept[i] - kept[i - 1]));
    assert(jump < 0.01, `the biggest step is ${jump.toFixed(4)}`);
    close(kept[Math.round(FADE_SECONDS * SR)], 0.8, 1e-6, 'full level after the fade');
});

test('trim: trimming the start leaves the rest of the part where it was in time', () => {
    const part = partOf(take(3, 0.5, 2.5), { offsetSeconds: 1.25, nudgeSeconds: 0.01, trimStartFrames: 24000, trimEndFrames: 4800 });
    const span = heardSpan(part);
    close(span.from, 1.26 + 0.5, 1e-9, 'heard from the trim, at its old time');
    close(span.to, 1.26 + 3 - 0.1, 1e-9, 'heard to the end trim');
    const kept = trimsTo(part, { from: 2, to: 3.5 });
    close(heardSpan({ ...part, trimStartFrames: kept.start, trimEndFrames: kept.end }).from, 2, 1 / SR, 'starts where asked');
    const over = trimsOf({ frames: 100, trimStartFrames: 80, trimEndFrames: 50 });
    assert(over.start === 80 && over.end === 20, `trims never overlap: ${JSON.stringify(over)}`);
});

test('trim: Trim the silence finds the first and last sung notes within 20 ms, with room kept', () => {
    for (const [from, to, gain] of [[1.2, 3.4, 0.3], [0.35, 2.05, 0.05]]) {
        const part = partOf(take(4, from, to, { gain }));
        const sung = sungSpan(part.peaks, 512, part.frames);
        close(sung.start / SR, from, 0.02, `the singing starts (${gain})`);
        close(sung.end / SR, to, 0.02, `the singing stops (${gain})`);
        const trims = silenceTrims(part);
        close(trims.start / SR, from - ROOM_SECONDS, 0.02, 'room before');
        close((part.frames - trims.end) / SR, to + ROOM_SECONDS, 0.02, 'room after');
    }
    assert(silenceTrims(partOf(take(2, 9, 9))) === null, 'a take with nothing sung is left alone');
});

test('trim: Line up the ends gives every part the same start or end, on the beat; a shorter part keeps its edge', () => {
    const a = { ...partOf(take(6, 1.1, 5.2)), id: 'a' };
    const b = { ...partOf(take(6, 1.4, 4.3)), id: 'b' };
    const c = { ...partOf(take(3, 0.2, 2.8)), id: 'c', offsetSeconds: 2.5, trimEndFrames: 4800 }; // starts late, ends early
    const starts = lineUp([a, b, c], 'start', { beatSeconds: 0.5 });
    const heard = (p) => heardSpan({ ...p, trimStartFrames: starts.get(p.id).start, trimEndFrames: starts.get(p.id).end });
    close(heard(a).from, 1, 1 / SR, 'a starts on the beat before the first singing');
    close(heard(b).from, 1, 1 / SR, 'b too');
    close(heard(c).from, 2.5, 1 / SR, 'c begins later: it keeps its start');
    assert(starts.get('c').end === 4800, 'and its own end trim');
    const ends = lineUp([a, b], 'end', { songEnd: 5.5 });
    close(heardSpan({ ...a, trimEndFrames: ends.get('a').end }).to, 5.5, 1 / SR, 'a ends where the song ends');
    close(heardSpan({ ...b, trimEndFrames: ends.get('b').end }).to, 5.5, 1 / SR, 'b too');
    close(snap(1.26, 0.5), 1.5, 1e-9, 'snap to the nearest beat');
});
