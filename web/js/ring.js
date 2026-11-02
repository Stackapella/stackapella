// How close a held chord note came to ringing (the Barbershop course, docs/courses/barbershop.md,
// section 2): its centre over the hold's last 1.5 seconds (the first 300 ms left out) against its
// pure target, the note's own `cents` off the piano's pitch (a barbershop third sits 14 cents low, a
// seventh 31). Never rewards the piano's own third: inside 15 cents only counts on the pure side of
// it. Pure, unit-tested in tests/barbershop.test.js. From courses-harmony's draft.

import { centsOf } from './vibrato.js';

export const RING = { from: 0.3, last: 1.5, locked: 6, close: 10, near: 15, seventhNear: -18, generous: 12, held: 1, straight: 10, waved: 40 };
const MIN_SECONDS = 0.4; // this much of the window heard, or it isn't judged

const median = (xs) => {
    const s = [...xs].sort((a, b) => a - b);
    const mid = s.length >> 1;
    return s.length % 2 ? s[mid] : (s[mid - 1] + s[mid]) / 2;
};
const fold = (c, around = 0) => c - 1200 * Math.round((c - around) / 1200);

/**
 * A held note's centre in cents from its pure target (`{ start, end, pitch }`, seconds and the
 * fractional MIDI pitch with its `cents` in it), over the last 1.5 seconds, the first 300 ms left out;
 * folded to the nearest octave. Null when too little was heard.
 */
export function ringCents(track, note) {
    return ringOf(track, note)?.off ?? null;
}

/**
 * A held note's centre (as ringCents) and how wide it wandered: `{ off, spread }`, the spread being
 * half the range of its middle half (a vibrato or a glide spreads wide; a straight tone doesn't).
 */
export function ringOf(track, note) {
    const from = Math.max(note.start + RING.from, note.end - RING.last);
    const heard = centsOf(track, { from, to: note.end, midi: note.pitch }).filter((c) => c !== null);
    if (heard.length < MIN_SECONDS * track.rate) return null;
    const centre = median(heard);
    const folded = heard.map((c) => fold(c, centre)).sort((a, b) => a - b);
    const quarter = (q) => folded[Math.min(folded.length - 1, Math.floor(q * folded.length))];
    return { off: Math.round(fold(median(folded))), spread: Math.round((quarter(0.75) - quarter(0.25)) / 2) };
}

/**
 * A held note's ring as a percent the stars read: within 6 cents of pure rings (100), within 10 is
 * close (75; 12 in the first lesson; a low third only when it's at least halfway down from the piano's
 * pitch), within 15 only on the pure side of the piano's pitch (40), else 20. A seventh tuned low (`offset` -25 or lower) is close anywhere from 18 cents under the piano's
 * pitch down to just past pure. `off` is cents from the pure target; `offset` the target's own cents.
 */
export function ringPercent(off, offset, { generous = false } = {}) {
    if (off === null) return null;
    const away = Math.abs(off);
    if (away <= RING.locked) return 100;
    const fromPiano = off + offset;
    // A low target (a third) is close only on its low side of halfway from the piano: a near-piano third never is.
    const lowEnough = offset >= 0 || offset <= -25 || fromPiano <= offset / 2;
    if (away <= (generous ? RING.generous : RING.close) && lowEnough) return 75;
    if (offset <= -25 && fromPiano <= RING.seventhNear && fromPiano >= offset - RING.locked) return 75;
    const pureSide = offset === 0 || Math.sign(fromPiano) === Math.sign(offset);
    return away <= RING.near && pureSide ? 40 : 20;
}

/**
 * Every held note (a second or more) of a take, judged on its ring: `{ percent, notes }`, each note
 * `{ off, offset, percent }`, null for a note too short to ring or skipped (`skip[i]`: a diminished
 * chord has no pure tuning). `offsets` are each sung note's own `cents` (its pure target's distance
 * from the piano's pitch).
 */
export function ringTake(track, target, offsets, { generous = false, skip = [] } = {}) {
    const notes = target.map((n, i) => {
        if (n.end - n.start < RING.held || skip[i]) return null; // too short, or a chord with no pure tuning
        const heard = ringOf(track, n);
        const offset = offsets[i] ?? 0;
        if (!heard) return { off: null, offset, percent: null, waved: false };
        // A wave or a glide can't ring, however well centred: capped.
        const waved = heard.spread > RING.straight;
        const percent = ringPercent(heard.off, offset, { generous });
        return { off: heard.off, offset, spread: heard.spread, waved, percent: waved ? Math.min(percent, RING.waved) : percent };
    });
    const ringable = notes.filter(Boolean);
    if (!ringable.some((m) => m.percent !== null)) return { percent: null, notes };
    // A ringable note not sung is a miss: skipping the hard seventh doesn't earn its stars.
    return { percent: Math.round(ringable.reduce((a, m) => a + (m.percent ?? 0), 0) / ringable.length), notes };
}

// Just intonation (docs/tune-chord-plan.md): each chord tone's ratio over the root, by the chord's
// quality and the tone's semitones above the root. A seventh chord's seventh is the barbershop 7:4.
const RATIOS = {
    maj: { 4: 5 / 4, 7: 3 / 2 }, min: { 3: 6 / 5, 7: 3 / 2 }, dim: { 3: 6 / 5, 6: 7 / 5 }, aug: { 4: 5 / 4, 8: 25 / 16 },
    sus2: { 2: 9 / 8, 7: 3 / 2 }, sus4: { 5: 4 / 3, 7: 3 / 2 }, maj7: { 4: 5 / 4, 7: 3 / 2, 11: 15 / 8 },
    min7: { 3: 6 / 5, 7: 3 / 2, 10: 9 / 5 }, dom7: { 4: 5 / 4, 7: 3 / 2, 10: 7 / 4 }, dim7: { 3: 6 / 5, 6: 7 / 5, 9: 5 / 3 },
    hdim7: { 3: 6 / 5, 6: 7 / 5, 10: 9 / 5 }, maj6: { 4: 5 / 4, 7: 3 / 2, 9: 5 / 3 }, min6: { 3: 6 / 5, 7: 3 / 2, 9: 5 / 3 },
    add9: { 2: 9 / 8, 4: 5 / 4, 7: 3 / 2 },
};

/** A chord's tones as semitones above its root (0 first), from the just-intonation table. */
export function chordTones(quality) {
    return [0, ...Object.keys(RATIOS[quality] ?? RATIOS.maj).map(Number)];
}

/**
 * A note's pure target in a chord (`{ root, quality }`, the root a pitch class): its cents from the
 * piano's pitch (a major third -13.7, a barbershop seventh -31.2, the root 0), or null when the note
 * isn't in the chord (a passing note gets no target).
 */
export function pureOffset(pitch, chord) {
    const semis = (((Math.round(pitch) - chord.root) % 12) + 12) % 12;
    if (semis === 0) return 0;
    const ratio = (RATIOS[chord.quality] ?? RATIOS.maj)[semis];
    return ratio ? 1200 * Math.log2(ratio) - 100 * semis : null;
}
