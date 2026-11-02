// Tune the chord's meter (docs/tune-chord-plan.md, slice 2): on a note held a beat or more inside a
// chord, where your voice sits against that note's pure target (a major third 13.7 cents under the
// piano, a barbershop seventh 31.2), from its centre over the last half second, judged as the
// Barbershop course's ring is (js/ring.js: ringing within 6 cents, close within 10, too wide a
// vibrato to tune past 40). Pure, unit-tested in tests/tuning.test.js; views/sing.js shows it.

import { chordTones, pureOffset, RING } from './ring.js';

// The qualities tried, simplest first, when a song has no chords and the other parts say which.
const QUALITIES = ['maj', 'min', 'dom7', 'min7', 'maj7', 'dim', 'sus4', 'sus2', 'aug', 'hdim7', 'dim7', 'maj6', 'min6'];
const DEGREES = { 0: 'root', 2: 'ninth', 3: 'third', 4: 'third', 5: 'fourth', 6: 'fifth', 7: 'fifth', 8: 'fifth', 9: 'sixth', 10: 'seventh', 11: 'seventh' };
const WINDOW = 0.5; // seconds of your voice the centre is taken over
const ENOUGH = 0.3; // this much of it voiced, or there's no reading yet
const EPSILON = 1e-6;

const median = (xs) => {
    const s = [...xs].sort((a, b) => a - b);
    const mid = s.length >> 1;
    return s.length % 2 ? s[mid] : (s[mid - 1] + s[mid]) / 2;
};

/**
 * The chord sounding at `beat` (`{ root, quality, bass }`, pitch classes): the song's own chords
 * when it has them, else what the other parts (not `focusKey`) sing there, read as the simplest
 * chord that holds them all, its lowest note the root where it can be. Null when there's no chord.
 */
export function chordAt(template, beat, { focusKey = null } = {}) {
    const chords = [...(template.chords ?? [])].sort((a, b) => a.beat - b.beat);
    if (chords.length) return chords.filter((c) => c.beat <= beat + EPSILON).at(-1) ?? null;
    const sounding = template.parts
        .filter((p) => p.key !== focusKey)
        .map((p) => (p.guide?.notes ?? []).find((n) => n.beat <= beat + EPSILON && beat < n.beat + n.length - EPSILON))
        .filter(Boolean);
    if (sounding.length < 2) return null;
    const pcs = [...new Set(sounding.map((n) => n.pitch % 12))];
    const bass = Math.min(...sounding.map((n) => n.pitch)) % 12;
    for (const root of [bass, ...pcs.filter((pc) => pc !== bass)]) {
        for (const quality of QUALITIES) {
            const tones = chordTones(quality).map((s) => (root + s) % 12);
            if (pcs.every((pc) => tones.includes(pc))) return { root, quality, bass: bass === root ? null : bass };
        }
    }
    return null;
}

/**
 * Your voice on `note` (`{ start, end, pitch }`: seconds and the sounding MIDI pitch) in `chord`
 * (its root moved by `transpose` to the key sung), at `now`: `{ degree, pure, cents, off, state }`,
 * with `cents` your centre against the piano's pitch (folded to the nearest octave, so singing it an
 * octave away is fine), `pure` the target's, `off` the difference, and `state` 'ringing', 'close',
 * 'off' or 'waved'. Null before there's enough to judge, or for a note not in the chord.
 */
export function ringReading(points, { now, note, chord, transpose = 0 }) {
    if (!chord || now - note.start < RING.from) return null;
    const root = (((chord.root + transpose) % 12) + 12) % 12;
    const pure = pureOffset(note.pitch, { root, quality: chord.quality });
    if (pure === null) return null;
    const recent = points.filter((p) => p.seconds > Math.max(now - WINDOW, note.start + RING.from) && p.seconds <= now && p.alpha === 1 && p.midi > 0);
    if (recent.length < 2 || recent.at(-1).seconds - recent[0].seconds < ENOUGH - 0.1 || now - recent.at(-1).seconds > 0.15) return null;
    const cents = recent.map((p) => {
        const c = (p.midi - note.pitch) * 100;
        return c - 1200 * Math.round(c / 1200);
    });
    const centre = median(cents);
    const spread = Math.max(...cents) - Math.min(...cents);
    const off = centre - pure;
    const semis = (((Math.round(note.pitch) - root) % 12) + 12) % 12;
    const state = spread > RING.waved ? 'waved' : Math.abs(off) <= RING.locked ? 'ringing' : Math.abs(off) <= RING.close ? 'close' : 'off';
    return { degree: DEGREES[semis] ?? 'root', pure, cents: centre, off, state };
}
