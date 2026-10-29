// Two lines, measured (the Counterpoint course, docs/courses/counterpoint.md): how they move against
// each other (contrary, similar, parallel or oblique), the parallel fifths and octaves the rules
// avoid, which of a note's `choices` the singer took, and the notes where the singer was pulled onto
// a partner's pitch instead of their own. Pure: pitches are MIDI numbers, notes are in beats from 0
// (as the exercise lays them out), a track is the pitch tracker's { rate, hz }.

import { hzToMidi } from './dsp.js';
import { holdPercent } from './results.js';
import { median } from './scoring.js';

const PERFECT = { 0: 'octave', 7: 'fifth' }; // interval classes, in semitones
const SIZE = [1, 2, 2, 3, 3, 4, 4, 5, 6, 6, 7, 7]; // an interval class's size: a minor and a major sixth are both sixths (a tritone counts as a fourth)
const MIDDLE_FROM = 0.2; // a note's middle: its first and last fifth left out (scoops, releases)
const MIN_READINGS = 3; // fewer voiced readings than this: the note wasn't heard
const PULL_REACH = 400; // a partner note further than a major third (in cents, folded) can't be what pulled you

/**
 * The semitones between two pitches, from the lower to the upper whichever voice is higher, folded
 * into one octave (0 to 11): a fifth below a cantus is a fifth, never a fourth.
 */
export function intervalClass(a, b) {
    return Math.abs(Math.round(b - a)) % 12;
}

/** An interval's size in steps (a third, a sixth, a tenth is 10), counting octaves. */
function sizeOf(lower, upper) {
    const semitones = Math.round(upper - lower);
    const octaves = Math.floor(Math.abs(semitones) / 12);
    return SIZE[Math.abs(semitones) % 12] + 7 * octaves;
}

/**
 * How two voices move from one note to the next: `oblique` (one holds), `contrary`, `parallel` (the
 * same way, keeping the interval's size: sixths to sixths) or `similar` (the same way, the interval
 * changing).
 */
export function motionOf(upperFrom, upperTo, lowerFrom, lowerTo) {
    const up = Math.sign(Math.round(upperTo - upperFrom));
    const low = Math.sign(Math.round(lowerTo - lowerFrom));
    if (up === 0 && low === 0) return 'none';
    if (up === 0 || low === 0) return 'oblique';
    if (up !== low) return 'contrary';
    return sizeOf(lowerTo, upperTo) === sizeOf(lowerFrom, upperFrom) ? 'parallel' : 'similar';
}

/**
 * Two lines note against note (arrays of pitches, one per beat they share): each move's motion, the
 * counts, and every pair of perfect intervals in a row with both voices moving the same way (parallel
 * fifths or octaves, unisons counted as octaves), by the index of the second.
 */
export function compareLines(upper, lower) {
    const moves = [];
    const counts = { contrary: 0, similar: 0, parallel: 0, oblique: 0, none: 0 };
    const parallels = [];
    for (let i = 1; i < Math.min(upper.length, lower.length); i++) {
        const motion = motionOf(upper[i - 1], upper[i], lower[i - 1], lower[i]);
        moves.push(motion);
        counts[motion]++;
        const before = intervalClass(lower[i - 1], upper[i - 1]);
        const now = intervalClass(lower[i], upper[i]);
        if ((motion === 'parallel' || motion === 'similar') && before === now && PERFECT[now]) parallels.push({ index: i, kind: PERFECT[now] });
    }
    return { moves, counts, parallels, contraryShare: moves.length ? counts.contrary / moves.length : 0 };
}

/** The pitch a line (notes in beats) sounds at `beat`, or null in a rest. */
export function pitchAt(notes, beat) {
    const note = notes.find((n) => beat >= n.beat && beat < n.beat + n.length);
    return note ? note.pitch : null;
}

/** The sung pitch (MIDI) over a note's middle, or null when too little of it was heard. */
export function sungPitch(track, note, beatSeconds) {
    const length = note.length * beatSeconds;
    const from = note.beat * beatSeconds + length * MIDDLE_FROM;
    const to = (note.beat + note.length) * beatSeconds - length * MIDDLE_FROM;
    const readings = [];
    for (let i = Math.max(0, Math.ceil(from * track.rate)); i <= Math.min(track.hz.length - 1, Math.floor(to * track.rate)); i++) {
        if (track.hz[i]) readings.push(hzToMidi(track.hz[i]));
    }
    return readings.length >= MIN_READINGS ? median(readings) : null;
}

/** How far a sung pitch is from a target, in cents, in the target's nearest octave (a man on a treble line). */
export function foldedCents(sung, target) {
    const off = sung - target;
    return (off - 12 * Math.round(off / 12)) * 100;
}

/**
 * A take against notes with `choices` (pitches, any of which is right): each such note becomes the
 * choice nearest what was sung, so the shared scorer judges it as an ordinary note, and the line the
 * singer made can be read. A note not heard keeps its first choice.
 */
export function resolveChoices(track, target, beatSeconds) {
    return target.map((note) => {
        if (!note.choices?.length) return note;
        const sung = sungPitch(track, note, beatSeconds);
        if (sung === null) return { ...note, pitch: note.choices[0], chosen: null };
        const pitch = note.choices.reduce((best, c) => (Math.abs(foldedCents(sung, c)) < Math.abs(foldedCents(sung, best)) ? c : best));
        return { ...note, pitch, chosen: pitch };
    });
}

/**
 * The notes where the singer was pulled onto a partner's line: the note's middle sat off its own pitch
 * (by more than `cents`) and within `cents` of a pitch a partner sounded at that moment, when that
 * pitch is a different note no more than a major third from the singer's (folded to the nearest
 * octave). Further away, a wrong note is just off pitch: three voices outlining a chord would
 * otherwise make nearly every slip a "pull". Your own previous or next note never counts, so singing
 * early or late isn't a pull. Returns each pull's note index and the partner pitch. Only for takes
 * where the partners were heard (never on a speaker).
 */
export function pulls(track, target, partners, { beatSeconds, cents = 35 }) {
    const out = [];
    target.forEach((note, index) => {
        const sung = sungPitch(track, note, beatSeconds);
        if (sung === null || Math.abs(foldedCents(sung, note.pitch)) <= cents) return;
        const middle = note.beat + note.length / 2;
        const neighbours = [target[index - 1], target[index + 1]].filter(Boolean).map((n) => n.pitch);
        const onto = partners
            .map((line) => pitchAt(line, middle))
            .filter((p) => p !== null && intervalClass(p, note.pitch) !== 0 && Math.abs(foldedCents(p, note.pitch)) <= PULL_REACH)
            .filter((p) => !neighbours.some((n) => intervalClass(n, p) === 0))
            .find((p) => Math.abs(foldedCents(sung, p)) <= cents);
        if (onto !== undefined) out.push({ index, partner: onto });
    });
    return out;
}

/** A leap the rules avoid in a singing line: a tritone, a major sixth, a seventh, or wider than an octave. */
export function awkwardLeap(from, to) {
    const size = Math.abs(Math.round(to - from));
    return size === 6 || size === 9 || size === 10 || size === 11 || size > 12;
}

/**
 * The choices left lit after the note just sung, so a lit note never costs a star: a fifth or octave
 * over the cantus reached with both voices moving the same way (a parallel, or a direct fifth or
 * octave) is dropped; then an awkward leap from your last note is dropped too, as long as another
 * choice stays lit (thirds and sixths never make a parallel, so one always does). Before the first
 * note, all of them.
 */
export function openChoices(choices, cantus, previous) {
    if (!previous || previous.sung === null) return choices;
    const ruled = choices.filter((c) => {
        if (!PERFECT[intervalClass(cantus, c)]) return true;
        const motion = motionOf(previous.sung, c, previous.cantus, cantus);
        return motion !== 'parallel' && motion !== 'similar';
    });
    const smooth = ruled.filter((c) => !awkwardLeap(previous.sung, c));
    return smooth.length ? smooth : ruled;
}

/**
 * A first-species line checked by the course's rules (docs/courses/counterpoint.md, unit 5): the
 * contrary moves, any parallel fifths or octaves, and the cadence (the last note an octave or unison
 * over the cantus, approached from a half step below: ti to do).
 */
export function speciesChecks(line, cantus) {
    const { counts, moves, parallels } = compareLines(line, cantus);
    const last = line.length - 1;
    const cadence = last > 0 && intervalClass(cantus[last], line[last]) === 0 && Math.round(line[last] - line[last - 1]) === 1;
    // A direct (hidden) fifth or octave: into a perfect interval with both voices moving the same way, from a different interval.
    const directs = moves.map((m, k) => ({ m, k: k + 1 })).filter(({ m, k }) => m === 'similar' && PERFECT[intervalClass(cantus[k], line[k])]).map(({ k }) => ({ index: k, kind: PERFECT[intervalClass(cantus[k], line[k])] }));
    const leaps = line.slice(1).map((p, k) => (awkwardLeap(line[k], p) ? k + 1 : null)).filter((k) => k !== null);
    return { contrary: counts.contrary, moves: moves.length, parallels, directs, leaps, cadence };
}

/**
 * Several held notes with breaths between (a pedal sung as four holds): each judged as a hold of its
 * own (how long on pitch, how steadily: results.js holdPercent) and averaged, so a breath between two
 * holds never counts against them; a hold not sung counts as none. Null when none was heard.
 */
export function holdsPercent(dots, target) {
    const each = target.map((n) => holdPercent(dots, [n]));
    if (each.every((p) => p === null)) return null;
    return Math.round(each.reduce((sum, p) => sum + (p ?? 0), 0) / each.length); // a hold not sung counts as none
}

export const STARS_3 = 90; // course.js: three stars from 90%
const CLOSE_MOVE = 4; // a close move: a held note, a step or a third (chord tones are a third apart)
const CLOSE_SHARE = 0.8; // a close harmony part: this share of its moves close
const MOVING_SHARE = 0.3; // ...and at least this share of them actually moving

/**
 * A first-species line you chose (`choices` against the first partner, the cantus): its notes on
 * pitch, with three stars only when the lesson's rules hold. `rules: 'contrary'` needs at least
 * three moves in opposite ways; `'all'` needs half the moves contrary, no parallel or direct fifths
 * or octaves, and ti to do at the end; `'close'` (a harmony part, Improvising) needs four moves in five
 * to be a step or a third (a held note counts), with some real moving (not one held note); `'against'`
 * needs half the moves that move to go the opposite way from the tune. When a rule fails the percent stops just short of three stars, never lower:
 * landing on a lit note always counts. `percent` is the take's own pitch percent.
 */
export function speciesPercent(percent, line, cantus, rules) {
    if (percent === null) return null;
    const checks = speciesChecks(line, cantus);
    const steps = line.slice(1).map((p, k) => Math.abs(Math.round(p - line[k])));
    const close = steps.filter((d) => d <= CLOSE_MOVE).length;
    const moving = steps.filter((d) => d > 0).length;
    const kept = rules === 'all' ? checks.contrary * 2 >= checks.moves && !checks.parallels.length && !checks.directs.length && checks.cadence
        : rules === 'close' ? close >= CLOSE_SHARE * Math.max(1, steps.length) && moving >= MOVING_SHARE * steps.length
        : rules === 'against' ? moving > 0 && checks.contrary * 2 >= moving
        : checks.contrary >= 3;
    return kept ? percent : Math.min(percent, STARS_3 - 1);
}
