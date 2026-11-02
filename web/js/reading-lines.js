// Endless reading practice (docs/sight-singing-plan.md, slice 5): new two-bar lines to read, drawn
// from a seed within the rules of the units the singer has reached, inside their range. Sharpen up's
// Fresh lines plays them one after another; the workout puts one in its review place on alternate days.
// Only for someone who has started Sight-singing, and never in a new singer's first week. Pure:
// unit-tested in tests/reading-lines.test.js; the lines are lesson exercises the Exercise plays.

export const COURSE = 'sight-singing';
const PREFIX = /^ss-(\d+)-/;
const FIRST_WEEK = 7 * 86400000;

// The major scale's degrees as semitones from do, the so below to the so above.
const DEGREES = [-5, -3, -1, 0, 2, 4, 5, 7];
const CHORD = new Set([-5, 0, 4, 7]); // do, mi, so and the so below: the leaps unit 2 teaches
// A bar's rhythms (in beats; a negative length is a rest), by the unit that teaches them.
const BARS = {
    1: [[1, 1, 1, 1], [1, 1, 2], [2, 1, 1], [2, 2]],
    3: [[1, 1, 1, 1], [1, 1, 2], [2, 1, 1], [1.5, 0.5, 1, 1], [0.5, 0.5, 1, 1, 1], [1, 1, 1, -1], [1, 0.5, 0.5, 2], [3, 1]],
};
const ENDS = { 1: [[1, 1, 2], [2, 2], [4]], 3: [[1, 1, 2], [2, 2], [3, -1], [1, -1, 2]] };

/**
 * The reading level reached: the highest Sight-singing unit with a lesson sung (1 to 4), or null
 * when the course hasn't been started. Never in the singer's first week (from their course start).
 */
export function readingLevel(progress, now = Date.now()) {
    const lessons = progress?.courses?.[COURSE]?.lessons ?? {};
    const units = Object.entries(lessons).filter(([, kept]) => kept?.stars > 0).map(([id]) => Number(PREFIX.exec(id)?.[1])).filter(Number.isFinite);
    if (!units.length) return null;
    if (progress.startedAt && now - progress.startedAt < FIRST_WEEK) return null;
    return Math.min(4, Math.max(...units));
}

/** Today's reading for the workout (Home's strip and the workout agree): `{ level, day }`, or null. */
export function readingToday(progress, now = Date.now()) {
    const level = readingLevel(progress, now);
    return level ? { level, day: Math.floor(now / 86400000) } : null;
}

/** A seeded random number from 0 to 1 (mulberry32): the same seed, the same line. */
function seeded(seed) {
    let a = seed >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

/**
 * A new line to read: a lesson exercise of two bars of 4/4, from `seed`, at `level` (1: steps from do
 * to so, names faint; 2: the chord's leaps and the so below; 3 and 4: rhythms, rests, a count-in and
 * its timing scored), every note inside `range` ({ low, high }, MIDI) around `home` (the singer's do).
 * It starts on a note of the chord and ends on do, held.
 */
export function readingLine({ level = 1, seed = 1, home = 57, range = null }) {
    const random = seeded(seed);
    const pick = (list) => list[Math.floor(random() * list.length)];
    const fits = (step) => !range || (home + step >= range.low && home + step <= range.high);
    const allowed = DEGREES.filter((d) => (level >= 2 ? true : d >= 0) && fits(d));
    const usable = allowed.length >= 3 ? allowed : DEGREES.filter((d) => d >= 0 && d <= 4); // a narrow range: do to mi
    const rhythm = level >= 3 ? 3 : 1;
    const lengths = [...pick(BARS[rhythm]), ...pick(ENDS[rhythm])];
    const sounding = lengths.filter((l) => l > 0).length;
    // The pitches: a walk over the allowed degrees, by steps (and from unit 2 leaps between the chord's notes).
    const index = (d) => usable.indexOf(d);
    let at = level >= 2 ? pick(usable.filter((d) => CHORD.has(d) && d >= 0)) ?? 0 : 0;
    if (index(at) < 0) at = usable.includes(0) ? 0 : usable[0];
    const pitches = [at];
    while (pitches.length < sounding - 1) {
        const i = index(at);
        const moves = [usable[i - 1], usable[i + 1]].filter((d) => d !== undefined);
        if (level >= 2 && CHORD.has(at)) moves.push(...usable.filter((d) => CHORD.has(d) && d !== at));
        at = pick(moves);
        pitches.push(at);
    }
    pitches.push(usable.includes(0) ? 0 : usable[0]); // home to do
    let beat = 0;
    let k = 0;
    const notes = [];
    for (const length of lengths) {
        if (length > 0) notes.push({ beat, length, step: pitches[k++] });
        beat += Math.abs(length);
    }
    const rhythmic = level >= 3;
    return {
        tempo: level === 1 ? 60 : level === 2 ? 72 : 76,
        turn: false,
        guide: false,
        hearFirst: true,
        score: 'read',
        drone: { step: 0, fadeAfter: 4 },
        staff: { names: level === 1 ? 'faint' : 'off', look: 4, ...(rhythmic ? { count: 4, time: '4/4', rhythm: true } : {}) },
        notes,
    };
}
