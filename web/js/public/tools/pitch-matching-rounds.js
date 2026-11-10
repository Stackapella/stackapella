// The pitch matching game's rounds (docs/tools/competitors-tap-tempo-mic-test-pitch-memory.md, section 3),
// pure and tested in tests/pitch-matching.test.js: hear a note, hold it in your head through a silence that
// grows by level, then sing it back. A note is judged once it's held steady (the scoop at its start
// doesn't count), in any octave unless strict, then scored as Perfect your pitch's Bullseye scores it
// (js/public/listen.js dart(), the lessons' own landing). The tool's screen (js/public/tools/pitch-matching.js)
// plays and listens.

import { median } from '../../dsp.js';

/** The levels: the silence, in seconds, between hearing the note and singing it. */
export const LEVELS = [{ gap: 0 }, { gap: 2 }, { gap: 5 }, { gap: 10 }];
/** Where the notes come from, for a lower or a higher voice (MIDI, the easy middle of each). */
export const VOICES = { low: { low: 48, high: 60 }, high: { low: 57, high: 72 } };
export const ROUND = 10; // notes a round
export const NOTE_SECONDS = 1.2; // how long the note sounds
export const HOLD_SECONDS = 0.6; // held steady this long, it's judged
export const STEADY_CENTS = 30; // steady: every reading in the hold within this of their middle
export const SILENT_SECONDS = 6; // nothing sung this long after the cue to sing: say we heard no note

/** The verdict for a note `cents` off (signed): 'spot' within 15, 'close' within 35, 'nearly' within 50, else 'off'. */
export function judge(cents) {
    const off = Math.abs(cents);
    if (off <= 15) return 'spot';
    if (off <= 35) return 'close';
    if (off <= 50) return 'nearly';
    return 'off';
}

/** A note for the next round: in the voice's range, never the one just sung. `random` is 0 to 1. */
export function pickNote({ low, high }, last = null, random = Math.random) {
    for (let i = 0; i < 8; i++) {
        const note = low + Math.floor(random() * (high - low + 1));
        if (note !== last) return note;
    }
    return last === low ? low + 1 : low;
}

/** `cents` from `target` folded to the nearest octave (a voice an octave down isn't wrong), unless strict. */
export function fold(cents, strict = false) {
    return strict ? cents : cents - 1200 * Math.round(cents / 1200);
}

/**
 * Whether the voice is held steady now: the readings (live-mic frames, with `cents` from the target)
 * over the last `hold` seconds, every one heard and within ± STEADY_CENTS of their middle. Returns the
 * share of the hold done so far (0 to 1, for the ring that fills) and, once done, the middle in cents.
 */
export function steadiness(frames, { hold = HOLD_SECONDS, strict = false } = {}) {
    const voiced = frames.filter((f) => f.clear && Number.isFinite(f.cents));
    if (!voiced.length) return { share: 0, cents: null };
    const end = voiced.at(-1).at;
    // The run of steady readings back from now: it breaks at a gap or a reading off the middle.
    let start = end;
    const run = [];
    for (let i = voiced.length - 1; i >= 0; i--) {
        const f = voiced[i];
        if (i < voiced.length - 1 && voiced[i + 1].at - f.at > 0.15) break;
        const next = [...run, fold(f.cents, strict)];
        const middle = median(next);
        if (next.some((c) => Math.abs(c - middle) > STEADY_CENTS)) break;
        run.push(fold(f.cents, strict));
        start = f.at;
    }
    const share = Math.min(1, (end - start) / hold);
    return { share, cents: share >= 1 ? Math.round(median(run)) : null };
}
