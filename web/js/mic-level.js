// The mic's level, read the same way everywhere (the app's mic check, js/mic-check.js, and the public
// mic test, js/public/tools/mic-test.js): where a level fills the meter, the voice line, a watch that
// says when a voice has been heard, and where a check stands. Pure (no framework, no audio), tested
// in tests/mic-check.test.js.

import { QUIET_DBFS } from './listen.js';

/** The voice line: louder than this is a voice (the same line listen.js counts a note from). */
export const VOICE_DBFS = QUIET_DBFS;
const FLOOR_DBFS = -72; // the meter's empty end: a quiet room
const CEIL_DBFS = -12; // its full end: singing up, close
const RELEASE_SECONDS = 0.25; // the meter stays lit this long after the voice dips under the line
export const HEARD_SECONDS = 0.4; // this much voice in all: the mic works
export const FLAT_SECONDS = 5; // this long with nothing over the line: suggest another mic
export const SIGNAL_DBFS = -66; // over this but under the voice line: the mic hears something, quietly (an interface at low gain)

/** Where `db` (RMS dBFS) fills the meter, 0 to 1; `ceil` is its full end (the mic test reads to 0, to show too loud). */
export function meterLevel(db, ceil = CEIL_DBFS) {
    if (db === null || db === undefined || !Number.isFinite(db)) return 0;
    return Math.max(0, Math.min(1, (db - FLOOR_DBFS) / (ceil - FLOOR_DBFS)));
}

/** The voice line's place on the meter, 0 to 1. */
export const VOICE_LINE = meterLevel(VOICE_DBFS);

/** A fresh voice watch: nothing heard yet. */
export function voiceWatch() {
    return { hearing: false, since: Infinity, voiced: 0, quiet: 0, total: 0 };
}

/**
 * The watch after a reading of `db`, `dt` seconds on: `hearing` (over the line, held a moment so it
 * doesn't flicker), `voiced` (seconds over the line in all) and `total` (seconds watched). Silence
 * counts for nothing: only a voice moves it on.
 */
export function stepVoice(state, db, dt) {
    const over = Number.isFinite(db) && db > VOICE_DBFS;
    const quiet = !over && Number.isFinite(db) && db > SIGNAL_DBFS;
    const since = over ? 0 : state.since + dt;
    return { hearing: since <= RELEASE_SECONDS, since, voiced: state.voiced + (over ? dt : 0), quiet: (state.quiet ?? 0) + (quiet ? dt : 0), total: state.total + dt };
}

/**
 * Where a check stands: 'ok' once a voice has been heard; after a while with none, 'quiet' when
 * the mic heard something under the line (turn its gain up), else 'flat' (try another mic).
 */
export function checkState(state) {
    if (state.voiced >= HEARD_SECONDS) return 'ok';
    if (state.total < FLAT_SECONDS || state.voiced > 0) return 'listening';
    return (state.quiet ?? 0) >= HEARD_SECONDS ? 'quiet' : 'flat';
}

/** An audio interface, by the name it gives (Things to check speaks of inputs, gain and 48V). */
export function looksLikeInterface(label) {
    return /scarlett|focusrite|clarett|audient|\bevo\b|\bid\d{1,2}\b|motu|apollo|universal audio|presonus|studio ?\d{2}|behringer|umc\d|u-phoria|steinberg|\bur\d{2}|tascam|\bzoom\b|\bssl ?\d|\brme\b|babyface|fireface|m-audio|komplete audio|arturia|minifuse|apogee|rubix|volt \d|audio interface/i.test(label ?? '');
}
