// The Sing screens' arithmetic (docs/singing-ux-plan.md, "Pure helpers"): where each part is at a
// moment (singing, resting, or getting ready for an entry), the "now" texts, each part's status,
// the phrase path's progress, and the staff's layout. Pure functions in beats, unit-tested in
// tests/sing.test.js and tests/staff.test.js; the screens turn seconds into beats.

import { midiName } from './dsp.js';
import { tk } from './i18n.js';

/** A note's word: its own syllable, or (for a held note with none) the syllable it continues. */
function syllableAt(notes, index) {
    for (let i = index; i >= 0; i--) {
        if (notes[i].lyric) return notes[i].lyric;
    }
    return null;
}

/**
 * Where a part is at `beat`: `{ state, index, note, syllable, next, entry, beatsLeft }`.
 *
 * - `singing`: `note` (at `index`) is sounding.
 * - `getReady`: within a bar of an entry (`entry`, `beatsLeft` beats away) that follows a rest of
 *   a bar or more (or the song's start): the moment to breathe in. A round's late entry counts in
 *   the same way.
 * - `resting`: otherwise between notes, or after the last.
 *
 * `next` is the two notes after the current one (or after the beat, when resting).
 */
export function timelineAt(notes, beat, { beatsPerBar = 4 } = {}) {
    const index = notes.findIndex((n) => beat >= n.beat && beat < n.beat + n.length);
    if (index >= 0) {
        return { state: 'singing', index, note: notes[index], syllable: syllableAt(notes, index), next: notes.slice(index + 1, index + 3), entry: null, beatsLeft: null };
    }
    const upcoming = notes.findIndex((n) => n.beat > beat);
    if (upcoming < 0) return { state: 'resting', index: -1, note: null, syllable: null, next: [], entry: null, beatsLeft: null };
    const entry = notes[upcoming];
    const before = notes[upcoming - 1];
    const restBeats = entry.beat - (before ? before.beat + before.length : 0);
    const beatsLeft = entry.beat - beat;
    // The song's start counts as a rest: a part that begins in the first bar gets ready too.
    const ready = (restBeats >= beatsPerBar - 1e-9 || !before) && beatsLeft <= beatsPerBar + 1e-9;
    return { state: ready ? 'getReady' : 'resting', index: -1, note: null, syllable: null, next: notes.slice(upcoming, upcoming + 2), entry, beatsLeft };
}

/**
 * Every part's moment, for the chips, cards and rail: `[{ slot, partKey, state, note, name,
 * syllable }]` in the slots' order. `name` is the note's name in the key being sung (transposed).
 */
export function nowParts(slots, template, beat, { transpose = 0, beatsPerBar = 4 } = {}) {
    return slots
        .filter((slot) => slot.templatePartKey)
        .map((slot) => {
            const part = template.parts.find((p) => p.key === slot.templatePartKey);
            const at = part ? timelineAt(part.guide.notes, beat, { beatsPerBar }) : { state: 'resting', note: null, syllable: null };
            return { slot, partKey: slot.templatePartKey, state: at.state, note: at.note, name: at.note ? midiName(at.note.pitch + transpose) : null, syllable: at.syllable };
        });
}

/** "up", "down" or "same" from one pitch to the next. */
export function direction(from, to) {
    return to > from ? 'up' : to < from ? 'down' : 'same';
}

/**
 * What the "now" element says, as a translation key and its values:
 * - singing: "Now D4, next D4, then C4 (down)";
 * - before an entry: "Coming up, you start on B3";
 * - resting with nothing to come: "You're resting".
 */
export function nowText(at, { transpose = 0, name = (note) => midiName(note.pitch + transpose) } = {}) {
    if (at.state === 'singing') {
        const [next, then] = at.next;
        if (!next) return [tk('sing.now.last'), { now: name(at.note) }];
        if (!then) return [tk('sing.now.next'), { now: name(at.note), next: name(next), direction: direction(at.note.pitch, next.pitch) }];
        return [tk('sing.now.nextThen'), { now: name(at.note), next: name(next), then: name(then), direction: direction(next.pitch, then.pitch) }];
    }
    if (at.entry) return [tk('sing.now.comingUp'), { note: name(at.entry) }];
    return [tk('sing.now.resting'), {}];
}

/** "Alto sings D4 on 'grace'", for a part card read aloud. */
export function partSaying(partName, part) {
    if (!part.note) return [tk('sing.part.resting'), { part: partName }];
    return part.syllable ? [tk('sing.part.singsOn'), { part: partName, note: part.name, syllable: part.syllable.replace(/[-,.;:!?]+$/, '') }] : [tk('sing.part.sings'), { part: partName, note: part.name }];
}

/**
 * One status per part: `recording`, `recorded`, `you` (your part, playing its guide or your take),
 * `muted`, `singing` or `resting`.
 */
export function partStatus(slot, { focusId = null, recordingId = null, singing = false, audible = true } = {}) {
    if (slot.id === recordingId) return 'recording';
    if (slot.id === focusId) return 'you';
    if (!audible) return 'muted';
    if (slot.frames > 0 && !singing) return 'recorded';
    return singing ? 'singing' : 'resting';
}

// ---- The phrase path's progress -------------------------------------------------------------------

/** The coach's steps, in order. Record hands over to the whole song's recording. */
export const STEPS = ['listen', 'singAlong', 'own', 'record'];

/** A part's progress: `{ step, phrases: { index: count } }`, counts for the current step. */
export function progressOf(practice, partKey) {
    return practice?.progress?.[partKey] ?? { step: 'listen', phrases: {} };
}

/**
 * Counts one practice of phrase `index` in the current step. What counts (the caller decides):
 * Listen, the phrase played to its end; Sing along, a round with the guide quieter; On your own, a
 * finished Listen-then-repeat turn. Loops count each round. Returns a new progress.
 */
export function practised(progress, index) {
    return { ...progress, phrases: { ...progress.phrases, [index]: (progress.phrases[index] ?? 0) + 1 } };
}

/** Every phrase practised at least once in this step. Next and Skip to record are always allowed. */
export function stepDone(progress, phraseCount) {
    return phraseCount > 0 && Array.from({ length: phraseCount }, (_, i) => progress.phrases[i] ?? 0).every((count) => count > 0);
}

/** The progress moved to `step` (its counts start again). */
export function toStep(progress, step) {
    return { step, phrases: {} };
}

/** The step after `step`, or null after Record. */
export function nextStep(step) {
    return STEPS[STEPS.indexOf(step) + 1] ?? null;
}

/** The first phrase not yet practised in this step (the "you are here"), or the last. */
export function currentPhrase(progress, phraseCount) {
    for (let i = 0; i < phraseCount; i++) {
        if (!progress.phrases[i]) return i;
    }
    return Math.max(0, phraseCount - 1);
}

// ---- The staff ------------------------------------------------------------------------------------

const LETTERS = ['C', 'D', 'E', 'F', 'G', 'A', 'B'];
const SHARP_ORDER = [3, 0, 4, 1, 5, 2, 6]; // F C G D A E B
const FLAT_ORDER = [6, 2, 5, 1, 4, 0, 3]; // B E A D G C F
// Sharps (positive) or flats (negative) in each key's signature; a minor key has its relative major's.
const SIGNATURES = {
    C: 0, G: 1, D: 2, A: 3, E: 4, B: 5, 'F#': 6, 'C#': 7, F: -1, Bb: -2, Eb: -3, Ab: -4, Db: -5, Gb: -6,
    Am: 0, Em: 1, Bm: 2, 'F#m': 3, 'C#m': 4, 'G#m': 5, Abm: -7, 'D#m': 6, Ebm: -6, Dm: -1, Gm: -2, Cm: -3, Fm: -4, Bbm: -5,
};
// The diatonic step of each clef's bottom line (C4 is 28: letter + 7 x octave).
const BOTTOM_LINE = { treble: 30, treble8: 23, bass: 18 }; // E4, E3 (sounding; written an octave up), G2

/** Treble for soprano and alto, treble with an 8 below for tenor, bass clef for bass. */
export function clefFor(partKey) {
    return partKey === 'bass' ? 'bass' : partKey === 'tenor' ? 'treble8' : 'treble';
}

/** A key's signature: `{ type: 'sharp' | 'flat' | null, count, letters }` (letters as 0 = C). */
export function keySignature(key) {
    const n = SIGNATURES[key] ?? 0;
    if (n > 0) return { type: 'sharp', count: n, letters: SHARP_ORDER.slice(0, n) };
    if (n < 0) return { type: 'flat', count: -n, letters: FLAT_ORDER.slice(0, -n) };
    return { type: null, count: 0, letters: [] };
}

const NATURAL_PC = [0, 2, 4, 5, 7, 9, 11]; // C D E F G A B
const MAJOR = [0, 2, 4, 5, 7, 9, 11];
const MINOR = [0, 2, 3, 5, 7, 8, 10];

/** A key's tonic: its letter (0 = C) and pitch class, and whether it's minor ("F#", "Bbm"). */
function tonicOf(key) {
    const match = /^([A-G])([#b]?)(m?)$/.exec(key ?? 'C') ?? ['', 'C', '', ''];
    const letter = 'CDEFGAB'.indexOf(match[1]);
    const pc = (NATURAL_PC[letter] + (match[2] === '#' ? 1 : match[2] === 'b' ? -1 : 0) + 12) % 12;
    return { letter, pc, minor: match[3] === 'm' };
}

/** The alteration that makes `letter` sound as pitch class `pc` (-2 to 2). */
function alterFor(letter, pc) {
    let alter = (pc - NATURAL_PC[letter] + 12) % 12;
    if (alter > 6) alter -= 12;
    return alter;
}

// The notes outside a key, by their distance from the tonic: which scale degree (0 = the tonic) they
// alter. Major: the raised 1st and 4th, the lowered 3rd, 6th and 7th (as melodies borrow them).
// Minor: the lowered 2nd, and the raised 3rd, 4th, 6th and 7th (harmonic and melodic minor).
const CHROMATIC = { major: { 1: 0, 3: 2, 6: 3, 8: 5, 10: 6 }, minor: { 1: 1, 4: 2, 6: 3, 9: 5, 11: 6 } };

/**
 * How `pitch` is written in `key`: `{ letter, octave, alter, step }` (alter: 1 sharp, -1 flat, 2
 * and -2 double). A note of the key's scale takes its scale letter (E# in F# major, Cb in Gb
 * major); any other alters a scale degree (CHROMATIC): F natural in G major, C# in D minor.
 */
export function spell(pitch, key) {
    const tonic = tonicOf(key);
    const pc = ((pitch % 12) + 12) % 12;
    const offset = (pc - tonic.pc + 12) % 12;
    const steps = tonic.minor ? MINOR : MAJOR;
    const degree = steps.includes(offset) ? steps.indexOf(offset) : CHROMATIC[tonic.minor ? 'minor' : 'major'][offset];
    const letter = (tonic.letter + degree) % 7;
    const alter = alterFor(letter, pc);
    const octave = Math.floor((pitch - alter) / 12) - 1;
    return { letter, octave, alter, step: letter + 7 * octave };
}

/**
 * The pitch a staff position stands for (0 the bottom line, as staffLayout gives), in `key`'s
 * signature: where a click on the note editor's staff puts a note. The inverse of staffLayout's
 * position for a note in the key.
 */
export function staffPitch(position, { key = 'C', clef = 'treble' } = {}) {
    const step = position + (BOTTOM_LINE[clef] ?? BOTTOM_LINE.treble); // the sounding step (treble8's is written an octave up)
    const letter = ((step % 7) + 7) % 7;
    const signature = keySignature(key);
    const alter = signature.letters.includes(letter) ? (signature.type === 'sharp' ? 1 : -1) : 0;
    return 12 * (Math.floor(step / 7) + 1) + [0, 2, 4, 5, 7, 9, 11][letter] + alter;
}

/**
 * The staff for a line of notes: the clef, the key signature, and each note's position (0 is the
 * bottom line, 1 the space above it, 8 the top line), accidental (`sharp`, `flat`, `natural` or
 * null, against the signature and earlier accidentals in the bar), ledger lines, head (`hollow`
 * for two beats or more) and stem (up below the middle line), plus the bar lines' beats.
 */
export function staffLayout(notes, { key = 'C', transpose = 0, clef = 'treble', beatsPerBar = 4, pickupBeats = 0 } = {}) {
    const signature = keySignature(key);
    const inKey = (letter) => (signature.letters.includes(letter) ? (signature.type === 'sharp' ? 1 : -1) : 0);
    const bottom = BOTTOM_LINE[clef] ?? BOTTOM_LINE.treble;
    const barOf = (beat) => Math.floor((beat - pickupBeats) / beatsPerBar);
    const seen = new Map(); // `${bar}:${step}` -> the alteration written last in that bar
    const laid = notes.map((note) => {
        const sounding = note.pitch + transpose;
        const written = clef === 'treble8' ? sounding + 12 : sounding;
        const s = spell(written, key);
        const position = s.step - (clef === 'treble8' ? bottom + 7 : bottom);
        const where = `${barOf(note.beat)}:${s.step}`;
        const expected = seen.has(where) ? seen.get(where) : inKey(s.letter);
        const accidental = s.alter === expected ? null : s.alter === 2 ? 'doubleSharp' : s.alter === -2 ? 'doubleFlat' : s.alter === 1 ? 'sharp' : s.alter === -1 ? 'flat' : 'natural';
        seen.set(where, s.alter);
        const ledgers = [];
        for (let p = -2; p >= position; p -= 2) ledgers.push(p);
        for (let p = 10; p <= position; p += 2) ledgers.push(p);
        return {
            beat: note.beat,
            length: note.length,
            pitch: sounding,
            lyric: note.lyric ?? null,
            name: `${LETTERS[s.letter]}${s.alter > 0 ? '#'.repeat(s.alter) : 'b'.repeat(-s.alter)}${s.octave}`,
            position,
            accidental,
            ledgers,
            hollow: note.length >= 2 - 1e-9,
            dotted: [0.75, 1.5, 3].some((length) => Math.abs(note.length - length) < 1e-6),
            flags: note.length < 0.5 - 1e-6 ? 2 : note.length < 1 - 1e-6 ? 1 : 0,
            stem: position < 4 ? 'up' : 'down',
        };
    });
    const bars = [];
    if (notes.length) {
        const from = notes[0].beat;
        const to = notes[notes.length - 1].beat + notes[notes.length - 1].length;
        for (let b = Math.ceil((from - pickupBeats) / beatsPerBar) * beatsPerBar + pickupBeats; b <= to + 1e-9; b += beatsPerBar) {
            if (b > from + 1e-9) bars.push(b);
        }
    }
    return { clef, signature, notes: laid, bars };
}
