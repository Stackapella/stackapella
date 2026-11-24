// The singing test's measures (docs/singing-test-v1.md): a sung answer's pitch track (js/pitchtrack.js `trackTake`:
// `{ rate, hz }`, 0 where unvoiced) to the numbers the contract keeps, never audio. Each takes the track of the answer
// and the time the prompt ended in it (`promptEnd`, seconds): only what's sung after it is measured.
// - Match a note: pitch-skills.js `landing` (exact) for its landing time; its cents from the steady middle; an octave
//   answer shown, never penalised.
// - Sing it back: transcribe.js `noteEvents` (the singer's own onsets, no grid), each note against the pattern in the
//   singer's own fitted key (the median offset of the notes they sang), so one wrong note counts once; the key's offset
//   reported apart.
// - Hold it: spread with vibrato removed, and pitch-skills.js `drift`.

import { drift as driftOf, landing } from '../pitch-skills.js';
import { noteEvents } from '../transcribe.js';

/** Under this much voiced singing an item is unheard (offered again; never scored as wrong). */
export const HEARD_SECONDS = { match: 0.4, hold: 2.5 }; // a hold's drift needs 2.5 s (pitch-skills.js drift)
/** Gaps this short inside a sung note don't split it (a tracker's dropped frame). */
const GAP_FRAMES = 3;
/** A vibrato's cycle is about 0.15 to 0.2 s (5 to 7 Hz): a line averaged over this keeps the hold and drops the wobble. */
const VIBRATO_SECONDS = 0.18;
/** The start and end of a hold aren't its steadiness (the onset settling, the release). */
const HOLD_TRIM = { start: 0.3, end: 0.2 };

const heard = (x) => x !== null && Number.isFinite(x);
const median = (xs) => {
    const s = [...xs].sort((a, b) => a - b);
    const mid = s.length >> 1;
    return s.length % 2 ? s[mid] : (s[mid - 1] + s[mid]) / 2;
};
const spread = (xs) => {
    const m = xs.reduce((a, b) => a + b, 0) / xs.length;
    return Math.sqrt(xs.reduce((s, x) => s + (x - m) ** 2, 0) / xs.length);
};
const round1 = (x) => Math.round(x * 10) / 10;
const midiOf = (hz) => 69 + 12 * Math.log2(hz / 440);

/** Frames unvoiced in a row that end the prompt's tail. */
const TAIL_GAP = 3;
/** A loudness rise this big over a fading tail is the singer starting over it (dB, within 5 frames). */
const REONSET_DB = 6;
/** A prompt's tail (the synth's release and the room) is over by then: anything still sounding is the singer's. */
const TAIL_MAX_SECONDS = 0.6;

/**
 * Where the singer's answer can begin (a frame): past the prompt's own tail. On speakers the mic still hears the prompt
 * as the answer window opens (the synth's release, the room, a latency estimate a little off), at exactly the target
 * pitch: a silent singer must never read as heard, nor the tail as their onset. Anything sounding in the first frames
 * after the prompt's end is that tail (no one answers within 30 ms), skipped to its first gap or to a fresh onset over it.
 */
export function answerStart(track, promptEnd) {
    const from = Math.max(0, Math.floor(promptEnd * track.rate));
    const on = (k) => track.hz[k] > 0;
    if (![0, 1, 2].some((d) => on(from + d))) return from;
    const loud = track.loudness;
    let gap = 0;
    const until = Math.min(track.hz.length, from + Math.round(TAIL_MAX_SECONDS * track.rate));
    for (let k = from; k < until; k++) {
        if (!on(k)) {
            if (++gap >= TAIL_GAP) return k;
            continue;
        }
        gap = 0;
        if (loud && k - 5 >= from && loud[k] - Math.min(...Array.from(loud.slice(k - 5, k))) >= REONSET_DB) return Math.max(from, k - 2);
    }
    return until;
}

/** The answer's frames as MIDI from the prompt's end, the prompt's tail blanked (null where unvoiced or tail). */
function answerMidis(track, promptEnd) {
    const notes = midis(track, promptEnd);
    const skip = answerStart(track, promptEnd) - Math.floor(promptEnd * track.rate);
    for (let k = 0; k < Math.min(skip, notes.length); k++) notes[k] = null;
    return notes;
}

/** The track from `from` seconds on, as MIDI (null where unvoiced). */
export function midis(track, from = 0) {
    const start = Math.max(0, Math.floor(from * track.rate));
    return Array.from(track.hz.slice(start), (hz) => (hz > 0 ? midiOf(hz) : null));
}

/** The longest stretch of voiced frames, gaps of a few frames bridged: `{ from, to }` (indices), or null. */
function longestRun(values) {
    let best = null;
    let from = -1;
    let gap = 0;
    for (let i = 0; i <= values.length; i++) {
        const on = i < values.length && heard(values[i]);
        if (on) {
            if (from < 0) from = i;
            gap = 0;
        } else if (from >= 0 && (++gap > GAP_FRAMES || i === values.length)) {
            const to = i - gap + 1;
            if (!best || to - from > best.to - best.from) best = { from, to };
            from = -1;
            gap = 0;
        }
    }
    return best;
}

/** A middle shorter than this can't tell a voice's own wobble from a synth's flatness: its jitter isn't given. */
const JITTER_SECONDS = 0.15;

/**
 * A run's steady middle (its middle half): its centre, and its spread in cents (vibrato kept; null when the middle is
 * too short to read, so the boards' "flat" check counts only notes long enough to show a voice's own wobble).
 */
function middleOf(cents, rate) {
    const voiced = cents.filter(heard);
    const middle = voiced.slice(Math.floor(voiced.length * 0.25), Math.max(Math.floor(voiced.length * 0.25) + 1, Math.ceil(voiced.length * 0.75)));
    return { centre: median(middle), jitter: middle.length >= JITTER_SECONDS * rate ? spread(middle) : null };
}

/** The octave an answer sits in against its target (-1, 0 or 1): shown, never penalised. */
function octaveOf(cents) {
    const voiced = cents.filter(heard);
    return Math.max(-1, Math.min(1, Math.round(median(voiced) / 1200)));
}

/**
 * Match a note: `{ target, centsOff, octave, landingMs, jitterCents, startAfterPromptMs }` (the contract's), with
 * centsOff null when unheard.
 */
export function matchItem(track, target, { promptEnd = 0 } = {}) {
    const notes = answerMidis(track, promptEnd);
    const first = notes.findIndex(heard);
    const unheard = { target, centsOff: null, octave: 0, landingMs: null, jitterCents: null, startAfterPromptMs: first >= 0 ? Math.round((first / track.rate) * 1000) : null };
    const run = longestRun(notes);
    if (!run || run.to - run.from < HEARD_SECONDS.match * track.rate) return unheard;
    const cents = notes.map((m) => (heard(m) ? (m - target) * 100 : null));
    const octave = octaveOf(cents.slice(run.from, run.to));
    const folded = cents.map((c) => (heard(c) ? c - octave * 1200 : null));
    const { centre, jitter } = middleOf(folded.slice(run.from, run.to), track.rate);
    const landed = landing(folded.slice(first), track.rate, { exact: true });
    return { target, centsOff: round1(centre), octave, landingMs: landed?.after == null ? null : Math.round(landed.after * 1000), jitterCents: jitter === null ? null : round1(jitter), startAfterPromptMs: Math.round((first / track.rate) * 1000) };
}

/** Every order-keeping way to place `count` sung notes on four pattern slots. */
function placements(count, slots = 4) {
    const out = [];
    const walk = (start, chosen) => {
        if (chosen.length === count) return out.push(chosen);
        for (let s = start; s < slots; s++) walk(s + 1, [...chosen, s]);
    };
    walk(0, []);
    return out;
}

/**
 * Sing it back: `{ pattern, notes: [{ centsOff, jitterCents }] (4), startOffsetCents, startAfterPromptMs }` (the
 * contract's). The singer's notes are found by their own onsets; when they sang more than four, the four longest (in
 * order); fewer, placed on the pattern where they fit best. Each is judged against the pattern moved to the singer's own
 * key (the median of their notes' offsets), so a whole pattern sung a tone high costs nothing but its reported offset.
 */
export function backItem(track, pattern, { promptEnd = 0 } = {}) {
    // From the singer's start, past the prompt's tail (a pattern's last note must never become their first).
    const start = answerStart(track, promptEnd);
    const lead = (start - Math.floor(promptEnd * track.rate)) / track.rate;
    const part = { ...track, hz: track.hz.slice(start), clarity: track.clarity?.slice(start), loudness: track.loudness?.slice(start) };
    const empty = { pattern, notes: pattern.map(() => ({ centsOff: null, jitterCents: null })), startOffsetCents: null, startAfterPromptMs: null };
    let events;
    try {
        events = noteEvents(part).events;
    } catch {
        return empty; // too little to read at all: unheard
    }
    const all = midis(part);
    const sung = events.map((e) => {
        const frames = all.slice(Math.round(e.start * track.rate), Math.round(e.end * track.rate));
        const { centre, jitter } = middleOf(frames.map((m) => (heard(m) ? m * 100 : null)), track.rate);
        return { start: e.start, length: e.end - e.start, cents: centre, jitter };
    }).filter((n) => heard(n.cents));
    if (!sung.length) return empty;
    const kept = sung.length > 4 ? [...sung].sort((a, b) => b.length - a.length).slice(0, 4).sort((a, b) => a.start - b.start) : sung;
    // Where the sung notes sit on the pattern: the placing whose offsets agree best.
    let best = null;
    for (const slots of placements(kept.length)) {
        const offsets = kept.map((n, k) => n.cents - pattern[slots[k]] * 100);
        const offset = median(offsets);
        const cost = offsets.reduce((s, o) => s + Math.abs(o - offset), 0);
        if (!best || cost < best.cost) best = { slots, offsets, offset, cost };
    }
    const notes = pattern.map(() => ({ centsOff: null, jitterCents: null }));
    best.slots.forEach((slot, k) => {
        notes[slot] = { centsOff: round1(best.offsets[k] - best.offset), jitterCents: kept[k].jitter === null ? null : round1(kept[k].jitter) };
    });
    // The key they sang it in, against the pattern: folded to the nearest octave (a lower voice an octave down is fine).
    const offset = best.offset - 1200 * Math.round(best.offset / 1200);
    return { pattern, notes, startOffsetCents: round1(offset), startAfterPromptMs: Math.round((lead + kept[0].start) * 1000) };
}

/** A line averaged over one vibrato cycle: what's left is the hold itself, without its wobble. */
function smoothed(cents, rate) {
    const half = Math.max(1, Math.round((VIBRATO_SECONDS * rate) / 2));
    return cents.map((_, i) => {
        const near = cents.slice(Math.max(0, i - half), i + half + 1).filter(heard);
        return near.length ? near.reduce((a, b) => a + b, 0) / near.length : null;
    });
}

/** The spread of a line around its own straight trend: steadiness, with a slow sag or creep left to drift. */
function detrendedSpread(line) {
    const n = line.length;
    const mx = (n - 1) / 2;
    const my = line.reduce((a, b) => a + b, 0) / n;
    let num = 0;
    let den = 0;
    line.forEach((y, x) => {
        num += (x - mx) * (y - my);
        den += (x - mx) ** 2;
    });
    const slope = den ? num / den : 0;
    return spread(line.map((y, x) => y - (my + slope * (x - mx))));
}

/** Hold it: `{ target, spreadCents, driftCents }` (the contract's), both null when unheard. */
export function holdItem(track, target, { promptEnd = 0 } = {}) {
    const notes = answerMidis(track, promptEnd);
    const run = longestRun(notes);
    const unheard = { target, spreadCents: null, driftCents: null };
    if (!run || run.to - run.from < HEARD_SECONDS.hold * track.rate) return unheard;
    const cents = notes.slice(run.from, run.to).map((m) => (heard(m) ? (m - target) * 100 : null));
    const octave = octaveOf(cents);
    const folded = cents.map((c) => (heard(c) ? c - octave * 1200 : null));
    const trimmed = folded.slice(Math.round(HOLD_TRIM.start * track.rate), folded.length - Math.round(HOLD_TRIM.end * track.rate));
    const line = smoothed(trimmed, track.rate).filter(heard);
    const sag = driftOf(folded, track.rate).drift;
    if (!line.length || sag === null) return unheard;
    return { target, spreadCents: round1(detrendedSpread(line)), driftCents: sag };
}
