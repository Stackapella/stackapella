// Pitch skills, measured (docs/courses/perfect-pitch.md, section 2): where a note lands and whether
// it slid in, how long a held note stays locked to its target, how much it sags or creeps, and how
// close a note sung from memory came. Then the stars' percent for each kind of step. Pure,
// unit-tested in tests/pitch-skills.test.js. Readings are cents from the target (centsOf in
// vibrato.js), folded to the nearest octave, so a man singing a line an octave down isn't off.
// Every threshold is a named constant, tuned on real voices on the beta before stars depend on it.

import { centsOf } from './vibrato.js';

const BEFORE = 0.25; // a note's readings start this early, to catch an early entry
const ONSET_SKIPPED = 0.02; // the tracker's first reading of a sound can be wild
export const SETTLE_SECONDS = 0.15; // landed: the first window this long...
export const SETTLE_SPREAD = 25; // ...that stays within ± this of itself
export const LAND_WITHIN = 0.8; // never settled by then: the note was a slide, not a landing
export const CLEAN_SECONDS = 0.06; // settled this soon after the first sound is a clean start
export const SLIDE_CENTS = 50; // or the first sound was this close to where it landed (the usual scoop is 30 to 80)
export const LANDING_FULL = 25; // a landing this close earns full credit (three stars)...
export const LANDING_GOOD = 35; // ...this close two stars' worth (full in the generous first lesson)...
export const LANDING_ZERO = 100; // ...falling to none at a semitone off
export const NAMED_MISS = 50; // `named`: further than this is nearer another semitone, a different note
export const SLID_COST = 0.5; // a note that slid in keeps this share of its credit

const TRIM_START = 0.25; // a hold's onset, judged by landing instead
const TRIM_END = 0.2; // the release
const SMOOTH = 0.4; // a rolling median over about two vibrato cycles: a natural wave or jitter isn't a tuning error
export const LOCK_FULL = 10; // a held centre this close is locked (three stars)
export const LOCK_PARTS = [[15, 0.8], [20, 0.6], [50, 0.3]]; // nearer than these still earns this share
const LOCKED_STEPS = [0, 7]; // only do and so (and their octaves) lock: a third tuned to a drone sits 14 cents under equal temperament
export const DRIFT_SPAN = 1; // drift: the last second's centre against the first's
export const DRIFT_FULL = 10; // sagging or creeping under this costs nothing (each end's centre carries a few cents of noise)...
export const DRIFT_PART = 20; // ...under this still earns most...
export const DRIFT_ZERO = 40; // ...and this much earns no drift credit
export const CENTRE_FULL = 20; // a note's centre within this is on it
export const MEMORY_FULL = 100; // a note from memory within a semitone earns everything (Levitin: most people are within two)
export const MEMORY_NEAR = 200; // within two semitones, two stars; any other heard try, one

const median = (xs) => {
    const s = [...xs].sort((a, b) => a - b);
    const mid = s.length >> 1;
    return s.length % 2 ? s[mid] : (s[mid - 1] + s[mid]) / 2;
};
const mean = (xs) => xs.reduce((a, b) => a + b, 0) / xs.length;
const clamp01 = (x) => Math.max(0, Math.min(1, x));
const fold = (c, around = 0) => c - 1200 * Math.round((c - around) / 1200);
/** Credit falling from 1 at `full` to 0 at `zero`. */
const falling = (off, full, zero) => clamp01(1 - (Math.abs(off) - full) / (zero - full));

/**
 * Where a note landed (`cents`, readings at `rate`, from a little before the note): the first
 * settled window's centre (folded to the nearest octave, or with `exact` as sung: a leap asked for by
 * name must be that leap, not the same note an octave off), the seconds from the first sound to it,
 * and the start: 'clean', 'scoop'
 * (slid up into it), 'fall' (slid down) or 'slide' (never settled). Null when nothing was heard.
 */
export function landing(cents, rate, { exact = false } = {}) {
    const first = cents.findIndex((c) => c !== null && Number.isFinite(c));
    if (first < 0) return null;
    const from = first + Math.round(ONSET_SKIPPED * rate);
    const window = Math.max(3, Math.round(SETTLE_SECONDS * rate));
    const last = Math.min(cents.length - window, first + Math.round(LAND_WITHIN * rate));
    for (let i = from; i <= last; i++) {
        const part = cents.slice(i, i + window).filter((c) => c !== null);
        if (part.length < window * 0.8) continue;
        const folded = part.map((c) => fold(c, median(part)));
        if (Math.max(...folded) - Math.min(...folded) > 2 * SETTLE_SPREAD) continue;
        const landed = exact ? median(folded) : fold(median(folded));
        const after = (i - first) / rate;
        const start = fold(cents[from] ?? cents[first], landed);
        const slid = after > CLEAN_SECONDS && Math.abs(start - landed) >= SLIDE_CENTS;
        return { cents: Math.round(landed), after, start: slid ? (start < landed ? 'scoop' : 'fall') : 'clean' };
    }
    const heard = cents.slice(from).filter((c) => c !== null);
    if (!heard.length) return null;
    return { cents: Math.round(exact ? median(heard) : fold(median(heard))), after: null, start: 'slide' };
}

/** One landing's credit: full within ±25 cents (±35 when generous), 0.8 within ±35, none at a semitone. */
function landingCredit(cents, generous) {
    const off = Math.abs(cents);
    if (off <= (generous ? LANDING_GOOD : LANDING_FULL)) return 1;
    if (off <= LANDING_GOOD) return 0.8;
    return 0.8 * falling(off, LANDING_GOOD, LANDING_ZERO);
}

/**
 * Landings to a percent: within ±25 cents is full credit, ±35 two stars' worth, none at a
 * semitone, and a note that slid in keeps half. Unheard notes cost nothing. `generous` (the
 * course's first lesson) gives full credit to ±35 and lifts a fair try. `named` (Ear training,
 * where a leap is asked for by name): a note nearer another semitone than the target, or not sung, is
 * a miss.
 */
export function landingPercent(landings, { generous = false, named = false } = {}) {
    const heard = landings.filter(Boolean);
    if (!heard.length) return null;
    // A leap asked for by name and not sung is a miss; elsewhere an unheard note costs nothing.
    const judged = named ? landings : heard;
    let score = mean(judged.map((l) => (!l || (named && Math.abs(l.cents) > NAMED_MISS) ? 0 : landingCredit(l.cents, generous) * (l.start === 'clean' ? 1 : SLID_COST))));
    if (generous) score = Math.min(1, 0.35 + score * 0.75);
    return Math.round(100 * score);
}

/** A hold's readings, trimmed of onset and release, folded, as a rolling median over SMOOTH. */
function held(cents, rate) {
    const part = cents.slice(Math.round(TRIM_START * rate), cents.length - Math.round(TRIM_END * rate)).filter((c) => c !== null);
    if (part.length < rate * 0.5) return null;
    const centre = median(part);
    const folded = part.map((c) => fold(c, centre));
    const half = Math.max(1, Math.floor(Math.round(SMOOTH * rate) / 2));
    return folded.map((_, i) => median(folded.slice(Math.max(0, i - half), i + half + 1)));
}

/**
 * A hold against its target: `{ percent, longest, centre }`. Each smoothed reading earns full
 * credit within ±10 cents, 0.8 within 15, 0.6 within 20, a little to ±50; `longest` is the
 * longest locked stretch in seconds.
 */
export function lock(cents, rate) {
    const line = held(cents, rate);
    if (!line) return { percent: null, longest: 0, centre: null };
    const credit = (c) => (Math.abs(c) <= LOCK_FULL ? 1 : (LOCK_PARTS.find(([within]) => Math.abs(c) <= within)?.[1] ?? 0));
    let run = 0;
    let longest = 0;
    for (const c of line) {
        run = Math.abs(c) <= LOCK_FULL ? run + 1 : 0;
        longest = Math.max(longest, run);
    }
    return { percent: Math.round(100 * mean(line.map(credit))), longest: longest / rate, centre: Math.round(median(line)) };
}

/**
 * A long hold's sag or creep: the last second's centre less the first's, in cents (negative is
 * flat), with the stars' percent from the centre and the drift together. Null under 2.5 seconds.
 */
export function drift(cents, rate) {
    const line = held(cents, rate);
    const span = Math.round(DRIFT_SPAN * rate);
    if (!line || line.length < 2 * span + rate * 0.5) return { percent: null, drift: null, centre: null };
    const amount = median(line.slice(-span)) - median(line.slice(0, span));
    const centre = median(line);
    const steady = Math.abs(amount) <= DRIFT_FULL ? 1 : Math.abs(amount) <= DRIFT_PART ? 0.75 : 0.75 * falling(amount, DRIFT_PART, DRIFT_ZERO);
    const percent = Math.round(100 * (0.5 * falling(centre, CENTRE_FULL, LANDING_ZERO) + 0.5 * steady));
    return { percent, drift: Math.round(amount), centre: Math.round(centre) };
}

/** A note judged on its centre alone (a third, or a short note): full within ±20 cents, none at a semitone. */
export function centre(cents, rate) {
    const line = held(cents, rate);
    if (!line) return { percent: null, centre: null };
    const middle = median(line);
    return { percent: Math.round(100 * falling(middle, CENTRE_FULL, LANDING_ZERO)), centre: Math.round(middle) };
}

/**
 * A note sung from memory, with nothing heard first: how many cents from the target it landed (in
 * the nearest octave, so an octave down isn't a miss). Within a semitone
 * earns everything, within two semitones two stars, any other heard try one. Never wrong.
 */
export function memory(cents, rate) {
    const landed = landing(cents, rate);
    if (!landed) return { percent: null, cents: null };
    const off = Math.abs(landed.cents);
    return { percent: off <= MEMORY_FULL ? 100 : off <= MEMORY_NEAR ? 80 : 50, cents: landed.cents };
}

/** Whether a note `step` semitones from do may be judged on lock: do and so, in any octave. */
export const lockable = (step) => LOCKED_STEPS.includes(((step % 12) + 12) % 12);

/**
 * A step's stars for its `score` ('landing', 'lock', 'drift', 'centre', 'memory', 'line', 'release',
 * 'entry' or 'cold'), from the
 * take's pitch track and the notes sung (`{ beat, length, pitch, cents? }`, beats from the take's
 * start; `cents` a pure-tuning offset from the piano's pitch).
 * With `steps` (each note's semitones from do), 'lock' judges only do and so on lock and every
 * other note on its centre. Every note is judged and the percent is their average; `notes` holds
 * each one's measure for Hear yourself.
 */
export function skillPercent(kind, track, target, beatSeconds, { generous = false, named = false, steps = null } = {}) {
    // A landing is looked for a little before its note, but never inside the note before (joined notes).
    const early = kind === 'landing' || kind === 'memory' || kind === 'line' || kind === 'entry' ? BEFORE : 0;
    const readings = target.map((n, i) => {
        const before = target[i - 1];
        const from = Math.max(0, n.beat * beatSeconds - early, before ? (before.beat + before.length) * beatSeconds : 0);
        return centsOf(track, { from, to: (n.beat + n.length) * beatSeconds + (kind === 'release' ? RELEASE_AFTER : 0), midi: n.pitch + (n.cents ?? 0) / 100 });
    });
    if (kind === 'landing') {
        const notes = readings.map((c) => landing(c, track.rate, { exact: named }));
        return { percent: landingPercent(notes, { generous, named }), notes };
    }
    if (kind === 'entry') {
        // Back in after a breath (Breath and stamina): each note after a rest, on time and on pitch.
        // The first note isn't an entry after a breath; a later one is when a rest comes before it.
        const raw = target.map((n, i) => {
            const before = target[i - 1];
            if (!before || n.beat - (before.beat + before.length) < ENTRY_REST) return null;
            const from = Math.max(0, n.beat * beatSeconds - early, (before.beat + before.length) * beatSeconds);
            return entry(readings[i], track.rate, n.beat * beatSeconds - from);
        });
        // Judged against your own usual offset (a consonant like the t of "'Tis" starts unvoiced), never more than 0.1 s of it.
        const offs = raw.filter((m) => m && m.off !== null && !m.through).map((m) => m.off);
        const usual = offs.length >= 2 ? Math.max(-ENTRY_USUAL, Math.min(ENTRY_USUAL, median(offs))) : 0;
        const notes = raw.map((m) => (m && m.percent !== null && !m.through ? { ...m, percent: Math.round(100 * (0.6 * falling(m.off - usual, ENTRY_ON_TIME, ENTRY_ZERO) + 0.4 * landingCredit(m.landed?.cents ?? 999, false))) } : m));
        const judged = notes.filter((m) => m && m.percent !== null);
        return { percent: judged.length ? Math.round(mean(judged.map((m) => m.percent))) : null, notes, usual };
    }
    if (kind === 'release') {
        // A note that ends on time (Breath and stamina): when it stopped against its written end, and its ending steady.
        const notes = readings.map((c, i) => release(c, track.rate, target[i].length * beatSeconds));
        const heard = notes.filter((m) => m.percent !== null);
        return { percent: heard.length ? Math.round(mean(heard.map((m) => m.percent))) : null, notes };
    }
    if (kind === 'line') {
        // A phrase (Breath and stamina): landing on its notes, then whether its last note ended steady.
        const notes = readings.map((c) => landing(c, track.rate));
        const landed = landingPercent(notes, { generous });
        const end = sustain(readings.at(-1), track.rate);
        if (landed === null) return { percent: null, notes, tail: end.tail };
        return { percent: Math.round(LINE_LANDING * landed + (1 - LINE_LANDING) * (end.tail === 'steady' ? 100 : end.tail ? 30 : 0)), notes, tail: end.tail };
    }
    if (kind === 'cold') {
        // A tune started from memory (Perfect your pitch): the first note on how close it came, every
        // note after it on where it landed from that first note, so a do 60 cents off with the tune
        // true to it is right about the tune.
        const landed = readings.map((c) => landing(c, track.rate));
        const anchor = landed[0]?.cents ?? null;
        if (!landed.some(Boolean)) return { percent: null, notes: landed };
        // Every note counts: one not sung is a miss (singing only the do isn't the tune).
        const credits = readings.map((c, i) => (i === 0 ? memory(c, track.rate).percent ?? 0 : landed[i] ? landingPercent([{ ...landed[i], cents: fold(landed[i].cents - (anchor ?? 0)) }], { generous }) : 0));
        return { percent: Math.round(mean(credits)), notes: landed };
    }
    const measures = { lock, drift, centre, memory };
    const notes = readings.map((c, i) => {
        const measured = (kind === 'lock' && steps && !lockable(steps[i % steps.length]) ? centre : measures[kind])(c, track.rate);
        // A note too short to judge as a hold (under about a second) is judged on where it landed.
        if (measured.percent === null && kind !== 'memory') return { ...measured, percent: landingPercent([landing(c, track.rate)]) };
        return measured;
    });
    const heard = notes.filter((m) => m.percent !== null);
    return { percent: heard.length ? Math.round(mean(heard.map((m) => m.percent))) : null, notes };
}

// ---- Breath and stamina (docs/courses/breath.md, section 2) ----

export const LINE_LANDING = 0.7; // a phrase: its landings' share of the stars; the rest is how its last note ended
export const HOLD_ENDED = 0.3; // a gap this long ends the hold
export const STEADY_BAND = 20; // steady: within this of the hold's own centre (on the rolling median)...
export const ON_PITCH = 50; // ...and within this of the note
export const TAIL_SECONDS = 1; // the tail: the hold's last second (its swing)...
export const SAG_SECONDS = 0.5; // ...and its last half second (its centre, for a sag)
export const RELEASE_TRIM = 0.2; // the last moment of a hold, its release, isn't part of how it ended
export const SAG = -20; // the tail's centre this far under the hold's: it sagged
export const WOBBLE = 35; // the tail's line swinging more than this either side: it wobbled
const TAIL_SMOOTH = 0.1; // the tail's swing is read on this lighter smoothing
export const GOAL_MAX = 15; // a hold's goal is never over this (normal maximum phonation time is 15 to 25 s at full effort)

/**
 * A hold's goal in seconds: the learner's own baseline plus `plus`, never over 15, and never a floor
 * (an easy 5 seconds is a goal of 5 plus a little, not 8). No baseline yet, no goal.
 */
export function holdGoal(baseline, plus = 0) {
    if (baseline === null || baseline === undefined) return null; // no goal until your own first hold sets one
    return Math.min(GOAL_MAX, baseline + plus);
}

/**
 * One long note, from the take's readings (cents from the note, `rate` a second): where the voice
 * held (its first sound to the first gap over 0.3 s), its steady seconds (on the note within ±50
 * cents and within ±20 of its own centre, on a rolling median, the onset left out), and how it
 * ended: `steady` (stopped on purpose), `sagged` or `wobbled` (the air ran out). With a `goal` in
 * seconds, the percent: the ending half, steadiness 30%, the goal met 20% (nothing more for going
 * past it). `{ percent, held, steady, tail, centre }`, percent null when nothing was held.
 */
export function sustain(cents, rate, { goal = null, generous = false } = {}) {
    const first = cents.findIndex((c) => c !== null && Number.isFinite(c));
    if (first < 0) return { percent: null, held: 0, steady: 0, tail: null, centre: null };
    let last = first;
    let gap = 0;
    for (let i = first; i < cents.length; i++) {
        if (cents[i] === null) {
            if (++gap > HOLD_ENDED * rate) break;
        } else {
            gap = 0;
            last = i;
        }
    }
    const held = (last - first + 1) / rate;
    const onset = Math.round(TRIM_START * rate);
    // The release (the pitch's natural fall as a note stops, and the tracker's last frames) is left out of the ending.
    const part = cents.slice(first + onset, Math.max(first + onset + 1, last + 1 - Math.round(RELEASE_TRIM * rate))).filter((c) => c !== null);
    if (held < 1 || part.length < rate * 0.5) return { percent: null, held, steady: 0, tail: null, centre: null };
    const centre = median(part);
    const folded = part.map((c) => fold(c, centre));
    const half = Math.max(1, Math.floor(Math.round(SMOOTH * rate) / 2));
    const line = folded.map((_, i) => median(folded.slice(Math.max(0, i - half), i + half + 1)));
    const middle = median(line);
    const steady = line.filter((c) => Math.abs(c - middle) <= STEADY_BAND && Math.abs(fold(c)) <= ON_PITCH).length / rate;
    const tailPart = line.slice(-Math.round(SAG_SECONDS * rate)); // a sag shows most in the last half second
    const tailCentre = median(tailPart);
    // The swing on a light smoothing (a wobble of 3 a second would vanish in the rolling median).
    const light = Math.max(1, Math.floor(Math.round(TAIL_SMOOTH * rate) / 2));
    const tailRaw = folded.slice(-Math.round(TAIL_SECONDS * rate));
    const tailLine = tailRaw.map((_, i) => median(tailRaw.slice(Math.max(0, i - light), i + light + 1)));
    const swing = (Math.max(...tailLine) - Math.min(...tailLine)) / 2;
    const tail = tailCentre - middle <= SAG ? 'sagged' : swing > WOBBLE ? 'wobbled' : 'steady';
    const steadyShare = clamp01(steady / Math.max(0.1, held - TRIM_START));
    const goalMet = goal ? clamp01(steady / goal) : 1;
    let score = 0.5 * (tail === 'steady' ? 1 : 0.3) + 0.3 * steadyShare + 0.2 * goalMet;
    if (generous) score = Math.min(1, 0.35 + score * 0.75);
    return { percent: Math.round(100 * score), held, steady: Math.round(steady * 10) / 10, tail, centre: Math.round(fold(middle)) };
}

export const RELEASE_AFTER = 1; // a release is looked for up to this long after the note's written end
export const RELEASE_ON_TIME = 0.12; // stopping within this of the written end is on time...
export const RELEASE_ZERO = 0.6; // ...and this far off earns no timing credit

/**
 * A note's release (`cents` from its start to a second past its written end of `seconds`): when the
 * voice stopped (the first gap over 0.15 s once it had begun), how far that was from the written end,
 * and whether its last second was steady. `{ percent, off, tail }`: timing 60%, the steady ending 40%.
 */
export function release(cents, rate, seconds) {
    const first = cents.findIndex((c) => c !== null && Number.isFinite(c));
    if (first < 0) return { percent: null, off: null, tail: null };
    let stopped = cents.length;
    let gap = 0;
    for (let i = first; i < cents.length; i++) {
        if (cents[i] === null) {
            if (++gap > 0.15 * rate) {
                stopped = i - gap + 1;
                break;
            }
        } else gap = 0;
    }
    const off = stopped / rate - seconds;
    const end = sustain(cents.slice(0, stopped), rate);
    const timing = falling(off, RELEASE_ON_TIME, RELEASE_ZERO);
    return { percent: Math.round(100 * (0.6 * timing + 0.4 * (end.tail === 'steady' ? 1 : 0.3))), off: Math.round(off * 100) / 100, tail: end.tail };
}

export const ENTRY_REST = 0.25; // a note this many beats after the one before is an entry after a breath
export const ENTRY_ON_TIME = 0.08; // starting within this of the beat is on time...
export const ENTRY_ZERO = 0.35; // ...and this far off earns no timing credit
export const ENTRY_USUAL = 0.1; // your own usual offset is allowed for, up to this
export const ENTRY_THROUGH = 0.2; // still sounding this long into the breath: sung straight through it

/**
 * A note's entry after a breath (`cents` from `lead` seconds before its beat): when its first sound
 * came against the beat (negative is early) and where it landed. `{ percent, off, landed, through }`:
 * timing 60%, landing 40%; `through` (no credit) when the voice never stopped for the breath.
 */
export function entry(cents, rate, lead) {
    const first = cents.findIndex((c) => c !== null && Number.isFinite(c));
    if (first < 0) return { percent: null, off: null, landed: null, through: false };
    // Already sounding when the breath should have been: sung straight through it, no entry to judge.
    const through = first === 0 && cents.slice(0, Math.max(1, Math.round(ENTRY_THROUGH * rate))).every((c) => c !== null);
    if (through) return { percent: 0, off: null, landed: null, through: true };
    const off = first / rate - lead;
    const landed = landing(cents, rate);
    const timing = falling(off, ENTRY_ON_TIME, ENTRY_ZERO);
    return { percent: Math.round(100 * (0.6 * timing + 0.4 * landingCredit(landed?.cents ?? 999, false))), off: Math.round(off * 100) / 100, landed, through: false };
}
