// Transcription for the template creator (docs/template-creator-plan.md, "Transcribe"): a line
// sung to a click becomes notes. The pitch track (js/pitchtrack.js: YIN every 10 ms, octave slips
// repaired, a median filter) has the singer's overall drift removed, is split into notes at
// silences, where the pitch moves by more than about 0.6 of a semitone for 60 ms, and at new
// onsets (a repeated note on one pitch); scoops shorter than a sixteenth fold into the next note.
// Each note's pitch is the median of its steady middle; times snap to the grid. Also: the key (a
// pitch-class profile weighted by length), how far ahead of the click the line was, whether it sped
// up, and how sure each note is (the "notes to check"). Pure: tests feed it synthesized singing.

import { hzToMidi } from './dsp.js';

const MOVE_SEMITONES = 0.6;
const MOVE_FRAMES = 6; // 60 ms at 100 frames a second
const ONSET_DB = 6; // a rise this sharp after a dip starts a new note on the same pitch
const MIN_DB = -45; // quieter than this is a rest
const MIN_CLARITY = 0.6;

const MAJOR = [6.35, 2.23, 3.48, 2.33, 4.38, 4.09, 2.52, 5.19, 2.39, 3.66, 2.29, 2.88];
const MINOR = [6.33, 2.68, 3.52, 5.38, 2.6, 3.53, 2.54, 4.75, 3.98, 2.69, 3.34, 3.17];
const NAMES = ['C', 'C#', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B'];

const median = (values) => {
    const sorted = [...values].sort((a, b) => a - b);
    const mid = sorted.length >> 1;
    return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
};

/** The singer's overall offset from the semitones, in cents (singers drift flat). */
export function driftCents(midis) {
    if (!midis.length) return 0;
    // The circular mean of the fractional parts (a semitone is a full turn), so vibrato that
    // crosses the half-semitone doesn't wrap the answer toward 0.
    let x = 0;
    let y = 0;
    for (const m of midis) {
        x += Math.cos(2 * Math.PI * m);
        y += Math.sin(2 * Math.PI * m);
    }
    return (Math.atan2(y, x) / (2 * Math.PI)) * 100;
}

/** The key from notes ({ pitch, length }): `{ tonic, mode, name }`, by the Krumhansl profiles. */
export function findKey(notes) {
    const weights = new Array(12).fill(0);
    for (const n of notes) weights[((n.pitch % 12) + 12) % 12] += n.length;
    let best = null;
    for (let tonic = 0; tonic < 12; tonic++) {
        for (const [mode, profile] of [['major', MAJOR], ['minor', MINOR]]) {
            const rotated = profile.map((_, i) => profile[(i - tonic + 12) % 12]);
            const score = correlation(weights, rotated);
            if (!best || score > best.score) best = { tonic, mode, score };
        }
    }
    return { tonic: best.tonic, mode: best.mode, name: NAMES[best.tonic] + (best.mode === 'minor' ? 'm' : '') };
}

function correlation(a, b) {
    const ma = a.reduce((s, v) => s + v, 0) / a.length;
    const mb = b.reduce((s, v) => s + v, 0) / b.length;
    let num = 0;
    let da = 0;
    let db = 0;
    for (let i = 0; i < a.length; i++) {
        num += (a[i] - ma) * (b[i] - mb);
        da += (a[i] - ma) ** 2;
        db += (b[i] - mb) ** 2;
    }
    return da && db ? num / Math.sqrt(da * db) : 0;
}

/**
 * The pitch track's runs of voiced frames, split where the pitch moves and stays or at a new onset
 * (`{ from, to, midis }`, frames), with the singer's drift and whether the take was noisy: what
 * `transcribe` and `noteEvents` both start from.
 */
function runsOf(track) {
    const { hz, clarity, loudness } = track;
    const voiced = (i) => hz[i] > 0 && clarity[i] >= MIN_CLARITY && loudness[i] > MIN_DB;
    const midis = [];
    for (let i = 0; i < hz.length; i++) if (voiced(i)) midis.push(hzToMidi(hz[i]));
    const drift = driftCents(midis);
    const noisy = hz.length > 0 && midis.length / Math.max(1, [...loudness].filter((l) => l > MIN_DB).length) < 0.5;
    const midiAt = (i) => hzToMidi(hz[i]) - drift / 100;

    // Runs of voiced frames, split where the pitch moves and stays, or at a new onset.
    const runs = [];
    let run = null;
    for (let i = 0; i < hz.length; i++) {
        if (!voiced(i)) {
            if (run) runs.push(run);
            run = null;
            continue;
        }
        const midi = midiAt(i);
        if (!run) {
            run = { from: i, to: i + 1, midis: [midi] };
            continue;
        }
        const centre = median(run.midis.slice(-12));
        // A move that holds for 60 ms starts a new note, from where the move began.
        let moved = Math.abs(midi - centre) > MOVE_SEMITONES;
        if (moved) {
            let held = 0;
            for (let k = i; k < Math.min(hz.length, i + MOVE_FRAMES) && voiced(k) && Math.abs(midiAt(k) - centre) > MOVE_SEMITONES; k++) held++;
            moved = held >= MOVE_FRAMES;
        }
        // An onset: loudness rising sharply after a dip (the same pitch sung again).
        const onset = i >= 3 && loudness[i] - Math.min(loudness[i - 1], loudness[i - 2], loudness[i - 3]) > ONSET_DB && run.to - run.from > MOVE_FRAMES;
        if (moved || onset) {
            runs.push(run);
            run = { from: i, to: i + 1, midis: [midi] };
        } else {
            run.to = i + 1;
            run.midis.push(midi);
        }
    }
    if (run) runs.push(run);
    return { runs, drift, noisy };
}

/**
 * Notes from a line's pitch track (`trackTake`'s `{ rate, hz, clarity, loudness }`):
 * - `beatSeconds`: a beat; `startSeconds`: where beat `startBeat` falls in the take;
 * - `grid`: the snap in beats (an eighth by default); `octave`: semitones to move the result
 *   (a part sung an octave away on purpose).
 * Returns `{ notes: [{ beat, length, pitch, sure }], aheadMs, spedUp, driftCents, empty, noisy }`.
 */
export function transcribe(track, { beatSeconds, startSeconds = 0, startBeat = 0, grid = 0.5, octave = 0 }) {
    const { rate } = track;
    const { runs, drift, noisy } = runsOf(track);

    // Scoops under a sixteenth fold into the next note; other short blips go.
    const sixteenth = (beatSeconds / 4) * rate;
    const kept = [];
    for (let k = 0; k < runs.length; k++) {
        const r = runs[k];
        const next = runs[k + 1];
        if (r.to - r.from < sixteenth) {
            if (next && next.from - r.to <= 2) {
                // A scoop into the next: it starts the note, and its readings come along, so the
                // note's readings still cover its whole length (without them a long scoop into a
                // short note left the note's middle empty, and its pitch NaN).
                next.from = r.from;
                next.midis = [...r.midis, ...next.midis];
            }
            continue;
        }
        kept.push(r);
    }

    const toBeat = (frame) => startBeat + (frame / rate - startSeconds) / beatSeconds;
    const snap = (beat) => Math.round(beat / grid) * grid;
    const offsets = [];
    const notes = kept.map((r) => {
        const length = r.to - r.from;
        const count = r.midis.length; // the readings (a breath of a frame or two inside a run has none)
        const middle = r.midis.slice(Math.floor(count * 0.2), Math.max(Math.floor(count * 0.2) + 1, Math.ceil(count * 0.8)));
        const exact = median(middle);
        if (!Number.isFinite(exact)) return null; // no pitch to give it: never a note without one
        const pitch = Math.round(exact) + octave;
        const spread = Math.sqrt(middle.reduce((s, m) => s + (m - exact) ** 2, 0) / middle.length) * 100;
        const rawStart = toBeat(r.from);
        const beat = snap(rawStart);
        const end = Math.max(beat + grid, snap(toBeat(r.to)));
        offsets.push({ at: rawStart, ms: (rawStart - beat) * beatSeconds * 1000 });
        // Sure: steady, on a semitone, and long enough to judge.
        const sure = spread < 35 && Math.abs(exact - Math.round(exact)) < 0.3 && length >= sixteenth * 1.5;
        return { beat, length: end - beat, pitch, sure };
    }).filter(Boolean);
    // Notes snapped onto each other: the earlier one gives way.
    for (let k = 0; k < notes.length - 1; k++) {
        if (notes[k].beat + notes[k].length > notes[k + 1].beat) notes[k].length = Math.max(grid / 2, notes[k + 1].beat - notes[k].beat);
    }
    const kept2 = notes.filter((n, k) => k === 0 || n.beat > notes[k - 1].beat);
    // Ahead of the click (negative: behind), and whether it sped up through the line.
    const aheadMs = offsets.length ? -median(offsets.map((o) => o.ms)) : 0;
    const half = Math.floor(offsets.length / 2);
    const early = offsets.slice(0, half).map((o) => o.ms);
    const late = offsets.slice(half).map((o) => o.ms);
    const spedUp = offsets.length >= 6 && median(early) - median(late) > 60;
    return { notes: kept2, aheadMs, spedUp, driftCents: drift, empty: kept2.length === 0, noisy };
}

/**
 * A hummed take in free time (Hum a tune, docs/hum-a-tune-plan.md): its notes as events in seconds,
 * not snapped to any beat (`js/hum.js` finds the beat): `{ events: [{ start, end, pitch, sure }],
 * driftCents, empty, noisy }`. Notes split as `transcribe` splits them; a blip under `minSeconds`
 * folds into the next note as a scoop, or goes. `octave`: semitones to move the result.
 */
export function noteEvents(track, { octave = 0, minSeconds = 0.09 } = {}) {
    const { rate } = track;
    const { runs, drift, noisy } = runsOf(track);
    const shortest = minSeconds * rate;
    const kept = [];
    for (let k = 0; k < runs.length; k++) {
        const r = runs[k];
        const next = runs[k + 1];
        if (r.to - r.from < shortest) {
            // A scoop into the next note starts it: a copy, so the runs given stay as they were.
            if (next && next.from - r.to <= 2) runs[k + 1] = { ...next, from: r.from, midis: [...r.midis, ...next.midis] };
            continue;
        }
        kept.push(r);
    }
    const events = kept.map((r) => {
        // A hummed note's pitch from its steady middle: humming scoops in and fades out more than words.
        const count = r.midis.length;
        const middle = r.midis.slice(Math.floor(count * 0.25), Math.max(Math.floor(count * 0.25) + 1, Math.ceil(count * 0.75)));
        const exact = median(middle);
        if (!Number.isFinite(exact)) return null;
        const spread = Math.sqrt(middle.reduce((s, m) => s + (m - exact) ** 2, 0) / middle.length) * 100;
        const sure = spread < 35 && Math.abs(exact - Math.round(exact)) < 0.3 && r.to - r.from >= shortest * 1.5;
        return { start: r.from / rate, end: r.to / rate, pitch: Math.round(exact) + octave, sure };
    }).filter(Boolean);
    return { events, driftCents: drift, empty: events.length === 0, noisy };
}
