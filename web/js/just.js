// Just intonation (docs/tune-chord-plan.md): each chord tone's ratio over the root, by the chord's
// quality and the tone's semitones above the root. A seventh chord's seventh is the barbershop 7:4.
// No imports, so a public page (the drone tool) can use it without the scoring code ring.js carries;
// ring.js re-exports it for the app. Unit-tested in tests/just.test.js.

/** How close counts as ringing, in cents from pure (ring.js scores the courses' holds with it; the intonation meter shows it live). */
export const RING = { from: 0.3, last: 1.5, locked: 6, close: 10, near: 15, seventhNear: -18, generous: 12, held: 1, straight: 10, waved: 40 };

export const RATIOS = {
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

/** A chord tone's pure ratio over the root (`semis` above it, any octave), or null when it isn't in the chord. */
export function pureRatio(semis, quality) {
    const pc = ((Math.round(semis) % 12) + 12) % 12;
    if (pc === 0) return 1;
    return (RATIOS[quality] ?? RATIOS.maj)[pc] ?? null;
}

/**
 * A note's pure target in a chord (`{ root, quality }`, the root a pitch class): its cents from the
 * piano's pitch (a major third -13.7, a barbershop seventh -31.2, the root 0), or null when the note
 * isn't in the chord (a passing note gets no target).
 */
export function pureOffset(pitch, chord) {
    const semis = (((Math.round(pitch) - chord.root) % 12) + 12) % 12;
    const ratio = pureRatio(semis, chord.quality);
    return ratio === null ? null : 1200 * Math.log2(ratio) - 100 * semis;
}

/**
 * Every pure interval within the octave, as the just intonation tuner's page tables it: its semitones,
 * its ratio as [over, under], and a name key. The seventh comes twice: the barbershop 7:4 (a dominant
 * seventh chord's) and 9:5 (a minor seventh chord's). The chord tones above are drawn from these.
 */
export const INTERVALS = [
    { semis: 1, ratio: [16, 15], name: 'minorSecond' },
    { semis: 2, ratio: [9, 8], name: 'majorSecond' },
    { semis: 3, ratio: [6, 5], name: 'minorThird' },
    { semis: 4, ratio: [5, 4], name: 'majorThird' },
    { semis: 5, ratio: [4, 3], name: 'fourth' },
    { semis: 6, ratio: [7, 5], name: 'tritone' },
    { semis: 7, ratio: [3, 2], name: 'fifth' },
    { semis: 8, ratio: [8, 5], name: 'minorSixth' },
    { semis: 9, ratio: [5, 3], name: 'majorSixth' },
    { semis: 10, ratio: [7, 4], name: 'harmonicSeventh' },
    { semis: 10, ratio: [9, 5], name: 'minorSeventh' },
    { semis: 11, ratio: [15, 8], name: 'majorSeventh' },
];

/** A pure interval's size in cents and its difference from the piano's: { cents, offset }. */
export function intervalCents({ semis, ratio: [over, under] }) {
    const cents = 1200 * Math.log2(over / under);
    return { cents, offset: cents - 100 * semis };
}
