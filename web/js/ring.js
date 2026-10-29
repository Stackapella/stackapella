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
