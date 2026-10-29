// Transcription (js/transcribe.js): synthesized singing comes back as its notes, line by line,
// through drift, vibrato, noise, a scoop, repeated notes and rests; singing ahead of the click is
// measured; the key is found.
import { trackTake } from '/js/pitchtrack.js';
import { findKey, transcribe } from '/js/transcribe.js';
import { assert, close, test } from './harness.js';

const SR = 16000;
const BPM = 80;
const BEAT = 60 / BPM;

/** A voice singing `notes` ({ beat, length, pitch }), detached a little, with options. */
function sing(notes, { drift = 0, vibrato = 0, noise = 0.002, aheadMs = 0, scoop = false, seed = 3, lead = 0.25 } = {}) {
    let r = seed;
    const random = () => ((r = (r * 16807) % 2147483647) / 2147483647) * 2 - 1;
    const end = Math.max(...notes.map((n) => n.beat + n.length));
    const out = new Float32Array(Math.ceil((lead + end * BEAT + 0.5) * SR));
    for (let i = 0; i < out.length; i++) out[i] = noise * random();
    for (const n of notes) {
        const from = Math.round((lead + n.beat * BEAT - aheadMs / 1000) * SR);
        const to = Math.round((lead + (n.beat + n.length) * BEAT - 0.06 - aheadMs / 1000) * SR);
        let phase = 0;
        for (let i = from; i < to && i < out.length; i++) {
            const t = (i - from) / SR;
            const bend = scoop && t < 0.05 ? -1.5 * (1 - t / 0.05) : 0; // a scoop up from 1.5 semitones under
            const cents = drift + vibrato * Math.sin(2 * Math.PI * 5.5 * t);
            const hz = 440 * 2 ** ((n.pitch + bend + cents / 100 - 69) / 12);
            phase += (2 * Math.PI * hz) / SR;
            const env = Math.min(1, t / 0.03, (to - i) / SR / 0.04);
            let v = 0;
            for (let h = 1; h <= 4; h++) v += Math.sin(phase * h) / h;
            out[i] += 0.25 * env * v;
        }
    }
    return out;
}

const grace = [
    { beat: 0, length: 1, pitch: 62 }, { beat: 1, length: 2, pitch: 67 }, { beat: 3, length: 1, pitch: 71 }, { beat: 4, length: 2, pitch: 71 },
    { beat: 6, length: 1, pitch: 69 }, { beat: 7, length: 2, pitch: 67 }, { beat: 9, length: 1, pitch: 64 }, { beat: 10, length: 2, pitch: 62 },
];

function check(found, wanted, label) {
    const got = found.notes.map((n) => `${n.beat}:${n.length}:${n.pitch}`).join(' ');
    const want = wanted.map((n) => `${n.beat}:${n.length}:${n.pitch}`).join(' ');
    assert(got === want, `${label}\n got ${got}\nwant ${want}`);
}

const read = (samples, options = {}) => transcribe(trackTake(samples, SR), { beatSeconds: BEAT, startSeconds: 0.25, ...options });

test('transcribe: Amazing Grace\'s first line comes back note for note', () => {
    check(read(sing(grace)), grace, 'plain');
    const key = findKey(grace);
    assert(key.name === 'G', `the key: ${key.name}`);
});

test('transcribe: a 30-cent flat drift, vibrato and noise still give the notes, and the drift is measured', () => {
    const found = read(sing(grace, { drift: -30, vibrato: 35, noise: 0.01 }));
    check(found, grace, 'drift, vibrato, noise');
    close(found.driftCents, -30, 8, 'the drift');
});

test('transcribe: a scoop folds into its note, and repeated notes on one pitch stay apart', () => {
    const repeated = [{ beat: 0, length: 1, pitch: 67 }, { beat: 1, length: 1, pitch: 67 }, { beat: 2, length: 2, pitch: 67 }, { beat: 5, length: 1, pitch: 69 }];
    check(read(sing(repeated, { scoop: true })), repeated, 'scoops and repeats, with a rest');
});

test('transcribe: singing ahead of the click is measured, and an octave choice moves the notes', () => {
    const found = read(sing(grace, { aheadMs: 90 }));
    check(found, grace, 'ahead');
    close(found.aheadMs, 90, 30, 'how far ahead');
    const down = read(sing(grace), { octave: -12 });
    assert(down.notes[0].pitch === 50, `an octave down: ${down.notes[0].pitch}`);
    assert(read(new Float32Array(SR * 2)).empty, 'silence: no notes');
});

test('transcribe: a long scoop into a short note never leaves a note without a pitch', () => {
    // Read straight from a track: 12 frames at 55, one dropped frame, then 2 frames at 60.
    const rate = 100;
    const frames = [...Array(20).fill(0), ...Array(12).fill(55), 0, ...Array(2).fill(60), ...Array(20).fill(0)];
    const hz = Float32Array.from(frames, (m) => (m ? 440 * 2 ** ((m - 69) / 12) : 0));
    const track = { rate, hz, clarity: Float32Array.from(frames, (m) => (m ? 0.95 : 0)), loudness: Float32Array.from(frames, (m) => (m ? -20 : -90)) };
    const { notes } = transcribe(track, { beatSeconds: 0.5, startSeconds: 0, startBeat: 0, grid: 0.25 });
    assert(notes.length >= 1 && notes.every((n) => Number.isInteger(n.pitch)), JSON.stringify(notes));
});
