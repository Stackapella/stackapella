// Listening for a held note (the course's "Find your note", and anywhere the app needs one steady
// note): live readings from the mic go in, one at a time, and out comes how long a steady note has
// been held, its pitch, and, when it isn't working, why: too quiet, not steady yet, or nothing
// heard at all. Pure, unit-tested in tests/listen.test.js on singing-like voices (vibrato, breath,
// soft voices, octave slips).

import { hzToMidi } from './dsp.js';

export const HOLD_SECONDS = 2; // a note held this long is "found"
export const LISTEN_CLARITY = 0.6; // a real voice through a laptop mic: clear enough to name its note
const VIBRATO_SEMITONES = 1.2; // one reading may swing this far from the recent centre (vibrato)
const DRIFT_SEMITONES = 0.7; // the recent centre may wander this far from the note (a slide doesn't)
const RECENT = 24; // readings in the recent centre: about 0.4 s, more than a vibrato cycle
const GAP_SECONDS = 0.3; // a breath or a dropped frame this short doesn't break the hold
export const QUIET_DBFS = -48; // quieter than this: sing up, or come closer (and the meters' "voice detected" line)
const SILENT_DBFS = -70; // quieter than this for a while: the mic isn't hearing anything

/** A fresh listener. */
export function listener() {
    return { held: 0, notes: [], raw: [], gap: 0, loudest: -Infinity, voicedFor: 0, heardFor: 0, total: 0 };
}

/** The distance from `midi` to `anchor` in semitones, folded to the nearest octave. */
function folded(midi, anchor) {
    return ((((midi - anchor) % 12) + 18) % 12) - 6;
}

const median = (values) => {
    const sorted = [...values].sort((a, b) => a - b);
    return sorted[Math.floor(sorted.length / 2)];
};

/**
 * One reading (`{ hz, loudness }` from trackFrame, hz 0 when no pitch) `dt` seconds after the last.
 * Returns the new state; `held` is the seconds of steady singing so far, `note` its pitch (MIDI).
 */
export function hear(state, { hz, loudness }, dt) {
    const s = { ...state, total: state.total + dt, loudest: Math.max(state.loudest, loudness) };
    if (loudness > QUIET_DBFS) s.heardFor += dt;
    if (hz > 0) {
        s.voicedFor += dt;
        const midi = hzToMidi(hz);
        const centre = s.notes.length ? median(s.notes.slice(-RECENT)) : midi;
        const off = folded(midi, centre); // kept in the note's octave: an octave slip doesn't move it
        if (Math.abs(off) <= VIBRATO_SEMITONES) {
            s.notes = [...s.notes.slice(-400), centre + off];
            s.raw = [...(s.raw ?? []).slice(-400), midi];
            const recent = median(s.notes.slice(-RECENT));
            if (s.notes.length > RECENT && Math.abs(recent - median(s.notes)) > DRIFT_SEMITONES) {
                s.notes = s.notes.slice(-RECENT); // it has moved: hold from here
                s.raw = s.raw.slice(-RECENT);
                s.held = RECENT * dt;
            } else {
                s.held += dt;
            }
        } else {
            s.notes = [midi]; // a new note: start holding again
            s.raw = [midi];
            s.held = dt;
        }
        s.gap = 0;
    } else {
        s.gap += dt;
        if (s.gap > GAP_SECONDS) {
            s.held = 0;
            s.notes = [];
            s.raw = [];
        }
    }
    // The note is judged in one octave (a slip doesn't break the hold), but sits in the octave most
    // readings were in: a first reading an octave out (a note's onset) doesn't set it.
    s.note = s.notes.length ? median(s.notes) + 12 * Math.round((median(s.raw) - median(s.notes)) / 12) : null;
    s.found = s.held >= HOLD_SECONDS;
    return s;
}

/**
 * Why a try didn't find a note: 'silent' (the mic hears nothing: check it), 'quiet' (sing up, or
 * come closer), or 'unsteady' (heard, but not one note held). Null while it's going fine.
 */
export function trouble(state) {
    if (state.found) return null;
    if (state.loudest < SILENT_DBFS) return 'silent';
    if (state.heardFor < 0.5) return 'quiet';
    return 'unsteady';
}
