// Whistle mode (docs/courses/whistling.md; the frontend's study, 2026-11-08). A step with `"whistle": true` is tracked
// over a whistle's range (pitchtrack.js WHISTLE_RANGE), and its take is moved down by whole octaves onto the written
// line before any scorer reads it: a whistle sits two or three octaves above a voice, while the line is laid and its
// guide played where voices sing (a guide at whistle height through a speaker would be heard as the whistle). The fold
// is the take's median offset, rounded to whole octaves, so a whole line whistled high costs nothing, and a note that
// slips an octave in the middle of one still costs (foldTrack's `slips`). A course laid from the whistle
// (`base: "whistle"`) keeps each singer's own whistle note (`personal.whistle`), found by lesson 1's held note
// (lesson-scores.js whistleNote).

import { local } from './storage.js';

export const WHISTLE_CLEAN = 0.1; // a whistle's start scoops for 50 to 100 ms (a whistling teacher, the course review): still clean
export const WHISTLE_SCOOP = 0.25; // the whistle note's finder leaves out this much after the first sound: beginners scoop up
export const WHISTLE_STEADY = 35; // a held whistle's readings stay within about this (cents) either side of its middle
export const WHISTLE_FOUND_SECONDS = 1; // a steady second of it finds the whistle note
export const WHISTLE_READ = 0.5; // of that second read (a breathy whistle reads 60% of its frames)

const median = (xs) => {
    const s = [...xs].sort((a, b) => a - b);
    return s.length ? s[s.length >> 1] : null;
};
const midiOf = (hz) => 69 + 12 * Math.log2(hz / 440);

/** Whole octaves a take sits above `target` ([{ start, end, pitch }] in seconds): its median reading's, rounded. */
export function octavesAbove(track, target) {
    const off = [];
    for (const n of target) {
        const first = Math.max(0, Math.ceil(n.start * track.rate));
        const last = Math.min(track.hz.length - 1, Math.floor(n.end * track.rate));
        for (let i = first; i <= last; i++) if (track.hz[i] > 0) off.push(midiOf(track.hz[i]) - n.pitch);
    }
    const middle = median(off);
    return middle === null ? 0 : Math.round(middle / 12);
}

/**
 * The take moved down onto `target` by whole octaves (`{ track, octaves, slips }`; a copy, its clarity and loudness as
 * they were). A voice's measures forgive a note an octave off (a man dropping the high notes); a whistle doesn't drop
 * an octave that way, so with `slips` a note whose own middle still sits a whole octave off after the fold is moved a
 * further tritone, which no measure folds back: it counts as a wrong note. `slips` is how many.
 */
export function foldTrack(track, target, { slips: marking = true } = {}) {
    const octaves = octavesAbove(track, target);
    const scale = 2 ** -octaves;
    const hz = Float32Array.from(track.hz, (v) => v * scale);
    let slips = 0;
    if (marking) {
        for (const n of target) {
            const first = Math.max(0, Math.ceil(n.start * track.rate));
            const last = Math.min(hz.length - 1, Math.floor(n.end * track.rate));
            const off = [];
            for (let i = first; i <= last; i++) if (hz[i] > 0) off.push(midiOf(hz[i]) - n.pitch);
            const middle = median(off);
            if (middle === null || Math.round(middle / 12) === 0) continue;
            slips++;
            for (let i = first; i <= last; i++) hz[i] *= 2 ** 0.5;
        }
    }
    if (!octaves && !slips) return { track, octaves, slips };
    return { track: { ...track, hz }, octaves, slips };
}

/** A live reading (MIDI) moved by whole octaves to the one nearest `around` (the note now), for the lane's dot. */
export const foldLive = (midi, around) => (midi === null || around === null || around === undefined ? midi : midi - 12 * Math.round((midi - around) / 12));

/**
 * The whistle note of a held take, from `from` to `to` seconds: its steadiest second, the scoop at its start left out.
 * A second is steady when half or more of it is read (a breathy whistle reads 60% of its frames) and the middle 80% of
 * its readings span no more than twice WHISTLE_STEADY: a held whistle with a little vibrato is, a shake isn't, since
 * every step of the course is laid from this note. Null until such a second is heard. Returns `{ midi, seconds }`, midi
 * to the semitone.
 */
export function whistleNoteOf(track, { from = 0, to = Infinity }) {
    const first = Math.max(0, Math.ceil(from * track.rate));
    const last = Math.min(track.hz.length - 1, Math.floor(to * track.rate));
    const start = track.hz.findIndex((hz, i) => i >= first && i <= last && hz > 0);
    if (start < 0) return null;
    const size = Math.round(WHISTLE_FOUND_SECONDS * track.rate);
    let best = null;
    for (let a = start + Math.round(WHISTLE_SCOOP * track.rate); a + size <= last + 1; a += Math.max(1, Math.round(track.rate / 10))) {
        const heard = [];
        for (let i = a; i < a + size; i++) if (track.hz[i] > 0) heard.push(midiOf(track.hz[i]));
        if (heard.length < size * WHISTLE_READ) continue;
        heard.sort((x, y) => x - y);
        const spread = (heard[Math.floor(heard.length * 0.9)] - heard[Math.floor(heard.length * 0.1)]) * 100;
        if (spread <= 2 * WHISTLE_STEADY && (!best || spread < best.spread)) best = { spread, midi: heard[heard.length >> 1] };
    }
    return best ? { midi: Math.round(best.midi), seconds: WHISTLE_FOUND_SECONDS } : null;
}

/**
 * The step's note when it's laid from the whistle (`base: "whistle"`): the singer's whistle note, moved by the key, at
 * the octave nearest the voice's note (`base`), so the line keeps its place in the whistle's range and its guide sounds
 * where voices sing. Before the whistle note is found, the voice's note.
 */
export function whistleBase(whistle, base, keyShift = 0) {
    if (!Number.isFinite(whistle)) return base;
    const note = whistle + keyShift;
    return note - 12 * Math.round((note - base) / 12);
}

export const WHISTLE_HEIGHT = 88; // E6 (1319 Hz): where a whistle sits, until the singer's own note is found

/** Whole octaves (in semitones) that lift `notes` to whistle height: one shift for every line of a clip, so its intervals stay. */
export function whistleShift(notes, whistle = null) {
    if (!notes.length) return 0;
    const pitches = notes.map((n) => n.pitch).sort((a, b) => a - b);
    return 12 * Math.round(((Number.isFinite(whistle) ? whistle : WHISTLE_HEIGHT) - pitches[pitches.length >> 1]) / 12);
}

/**
 * Notes to hear where no mic is open (a choose step's clips, a self-check's model): moved by whole octaves to whistle
 * height (around the singer's whistle note, else WHISTLE_HEIGHT) and played as a whistle (synth.js `voice: 'whistle'`).
 * A step with a mic open plays its guide where voices sing instead, so the guide is never heard as the whistle.
 */
export function asWhistled(notes, whistle = null, shift = whistleShift(notes, whistle)) {
    return notes.map((n) => ({ ...n, pitch: n.pitch + shift, voice: 'whistle', vibrato: n.vibrato ?? { cents: 0 } }));
}

const FOLD_SAID = 'stackapella.whistleFoldSaid'; // this device: the fold explained once

/** Whether to say, this once, why a whistled take was moved down (`octaves` it was): the first folded take on this device. */
export function sayFoldOnce(octaves) {
    if (!octaves || local.get(FOLD_SAID) === '1') return false;
    local.set(FOLD_SAID, '1');
    return true;
}
