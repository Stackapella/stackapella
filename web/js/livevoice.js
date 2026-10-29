// Your voice, live (the Sing redesign, Hillel's call 3): readings from the mic become a steady line
// on the Note lane, and the notes you hit light up. The judgement is the scorer's own (js/scoring.js:
// a clear pitch, within the "standard" tolerance, in any octave), so the light and the score agree,
// and it reads the raw readings (a lone jump held back until the next agrees). The line is drawn
// through the shared display smoother (js/pitch-display.js): an unclear moment holds and fades,
// never drops; a sudden octave waits to be confirmed; the rest is a median, eased. Pure,
// unit-tested in tests/livevoice.test.js.

import { hzToMidi } from './dsp.js';
import { pitchDisplay, stepDisplay } from './pitch-display.js';
import { STRICTNESS } from './scoring.js';

const KEEP_SECONDS = 6; // the lane shows about this much
const REGISTER_WINDOW = 3; // seconds of evidence for the octave you sing in
const REGISTER_PATIENCE = 1.5; // seconds of disagreement before it changes
const JUMP = 1; // semitones: a reading this far from the last is held until confirmed

/** A fresh follower: the line's points, the notes lit, and the last reading held back. */
export function voiceFollower({ offset = null } = {}) {
    return { points: [], lit: new Set(), hits: new Map(), held: null, display: pitchDisplay(), at: null, offset, evidence: [], disagreeSince: null, hint: null, hintSince: null };
}

const HINT_SHOW = 0.4; // seconds outside the tolerance before "A little higher" (or lower) shows
const HINT_CLEAR = 0.2; // seconds back within 25 cents before it goes
const HINT_NEAR = 25; // cents

/**
 * The words' hint, with hysteresis so vibrato never makes it flicker (the pitch display research,
 * 6.1 step 9): 'higher' or 'lower' after 0.4 s outside the tolerance the same way, cleared after
 * 0.2 s back within 25 cents. `cents` is the line's centre against the note (null: no note or no voice).
 */
export function hintStep(state, { cents, dt, tolerance = STRICTNESS.standard }) {
    if (cents === null || cents === undefined) return { ...state, hint: null, hintSince: null };
    const way = cents < -tolerance ? 'higher' : cents > tolerance ? 'lower' : null;
    if (state.hint) {
        const near = Math.abs(cents) <= HINT_NEAR;
        const since = near ? (state.hintSince ?? 0) + dt : 0;
        if (since >= HINT_CLEAR) return { ...state, hint: null, hintSince: null };
        return { ...state, hintSince: since }; // it keeps its way until it clears: no flip on a vibrato
    }
    if (!way) return { ...state, hintSince: null, hintWay: null };
    const since = state.hintWay === way ? (state.hintSince ?? 0) + dt : dt;
    return since >= HINT_SHOW ? { ...state, hint: way, hintSince: 0, hintWay: null } : { ...state, hintSince: since, hintWay: way };
}

/**
 * The octave you sing in against the part (a man on a treble part: -12), learned once and taken
 * off every reading, inside notes and out. Folding each reading to the note sung now (as this did)
 * left readings between notes in their own octave, so the line jumped 12 at every note's edge
 * (the pitch display research). From the first clear reading in a note; then the median of the
 * last 3 s, changed only after 1.5 s of disagreement.
 */
function register(state, raw, target, seconds) {
    if (!target) return state;
    const offset = 12 * Math.round((raw - target.pitch) / 12);
    const evidence = [...state.evidence.filter((e) => e.seconds > seconds - REGISTER_WINDOW), { seconds, offset }];
    if (state.offset === null || state.offset === undefined) return { ...state, evidence, offset, disagreeSince: null };
    const sorted = evidence.map((e) => e.offset).sort((a, b) => a - b);
    const usual = sorted[sorted.length >> 1];
    const enough = evidence[evidence.length - 1].seconds - evidence[0].seconds >= REGISTER_PATIENCE;
    if (usual === state.offset || !enough) return { ...state, evidence, disagreeSince: null };
    const since = state.disagreeSince ?? seconds;
    return seconds - since >= REGISTER_PATIENCE ? { ...state, evidence, offset: usual, disagreeSince: null } : { ...state, evidence, disagreeSince: since };
}

const folded = (midi, target) => target + ((((midi - target) % 12) + 18) % 12) - 6;

/**
 * One reading (`hz`, 0 for none clear) at `seconds` (song time, latency taken off), while `target`
 * (a note: { beat, pitch } with the song's transposition applied, or null) should sound.
 * Returns the new follower; the old one is untouched.
 */
const LOUD_RANGE = 24; // dB under your own recent peak that the dot and ribbon span (quietest to loudest)
const PEAK_FALL = 3; // dB a second: your peak follows you down slowly
const LEVEL_EASE = 0.08; // seconds: how fast the size follows your loudness

/**
 * How loud you're singing, 0 to 1, against your own recent peak (never the mic's absolute level),
 * eased: the dot's size and the ribbon's thickness.
 */
function loudnessStep(state, loudness, voiced, dt) {
    if (!voiced || !Number.isFinite(loudness)) return { peak: state.peak ?? null, level: state.level ?? 0.5 };
    const peak = state.peak === null || state.peak === undefined ? loudness : Math.max(loudness, state.peak - PEAK_FALL * dt);
    const target = Math.max(0, Math.min(1, (loudness - (peak - LOUD_RANGE)) / LOUD_RANGE));
    const level = (state.level ?? target) + (target - (state.level ?? target)) * Math.min(1, dt / LEVEL_EASE);
    return { peak, level };
}

/**
 * `shownHz` (default `hz`) is what the line shows: the screen keeps a voice through a borderline
 * reading (clarity hysteresis), while the light and the score count only clear ones (`hz`).
 * `loudness` (dB) sizes the dot and the ribbon.
 */
export function follow(state, { seconds, hz, shownHz = hz, loudness = null }, target, { tolerance = STRICTNESS.standard, mode = 'sing' } = {}) {
    const points = state.points.filter((p) => p.seconds > seconds - KEEP_SECONDS);
    const dt = state.at === null || state.at === undefined ? 0.033 : Math.max(0, Math.min(0.25, seconds - state.at));
    const raw = hz > 0 ? hzToMidi(hz) : null;
    const known = raw === null ? { ...state, evidence: state.evidence ?? [] } : register({ ...state, evidence: state.evidence ?? [] }, raw, target, seconds);
    // The line: in your own octave taken off (the same for every reading), smoothed for showing.
    const shownMidi = shownHz > 0 ? hzToMidi(shownHz) - (known.offset ?? 0) : null;
    const display = stepDisplay(state.display ?? pitchDisplay(), { midi: shownMidi, dt, mode });
    const loud = loudnessStep(state.loud ?? {}, loudness, shownMidi !== null, dt);
    const drawn = display.shown !== null && display.alpha > 0 ? [...points, { seconds, midi: display.shown, alpha: display.alpha, level: loud.level }] : points;
    const next = { ...known, points: drawn, display, loud, at: seconds };
    if (raw === null) return { ...next, held: null };
    // The light: the reading folded onto the note sung now (any octave counts, as in the score).
    const midi = target ? folded(raw, target.pitch) : shownMidi;
    // The light: the raw reading, a jump standing only when the next reading agrees with it.
    const last = state.lastRaw;
    if (last && seconds - last.seconds < 0.12 && Math.abs(midi - last.midi) > JUMP && !(state.held && Math.abs(state.held.midi - midi) <= JUMP)) {
        return { ...next, held: { seconds, midi } };
    }
    const inTune = !!target && Math.abs((midi - target.pitch) * 100) <= tolerance;
    const lit = inTune ? new Set([...state.lit, target.beat]) : state.lit;
    // Where you were in tune on each note (the lane fills the bar there): spans, joined across a dropped reading.
    let hits = state.hits ?? new Map();
    if (inTune) {
        hits = new Map(hits);
        const spans = [...(hits.get(target.beat) ?? [])];
        const lastSpan = spans.at(-1);
        if (lastSpan && seconds - lastSpan[1] < 0.08) spans[spans.length - 1] = [lastSpan[0], seconds];
        else spans.push([seconds - dt, seconds]);
        hits.set(target.beat, spans);
    }
    return { ...next, lit, hits, held: null, lastRaw: { seconds, midi } };
}
