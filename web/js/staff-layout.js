// The staff's layout (once in js/sing.js): a part's clef, a key's signature, how a pitch is spelled in a key, and each
// note's place on the staff with its accidental, ledger lines, head and stem. Pure, no Vue and no words, so the public
// song pages' score (js/public/score.js) loads it as the app's screens do. Unit-tested in tests/staff.test.js.

import { barAt, bars as barsOf, meterChanges } from './theory.js';

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
 * for two beats or more) and stem (up below the middle line), plus the bar lines' beats. A song whose time signature
 * changes (`timeSignature` and `meters`, docs/meters-plan.md) has its bar lines where its bars fall, and `changes` says
 * where a new time signature is written.
 */
export function staffLayout(notes, { key = 'C', transpose = 0, clef = 'treble', beatsPerBar = 4, pickupBeats = 0, timeSignature = null, meters = null } = {}) {
    const song = meters?.length ? { timeSignature, pickupBeats, meters } : null;
    const signature = keySignature(key);
    const inKey = (letter) => (signature.letters.includes(letter) ? (signature.type === 'sharp' ? 1 : -1) : 0);
    const bottom = BOTTOM_LINE[clef] ?? BOTTOM_LINE.treble;
    const barOf = song ? (beat) => barAt(song, beat).start : (beat) => Math.floor((beat - pickupBeats) / beatsPerBar);
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
    const changes = [];
    if (notes.length) {
        const from = notes[0].beat;
        const to = notes[notes.length - 1].beat + notes[notes.length - 1].length;
        if (song) {
            for (const bar of barsOf(song, to + 1e-9, Math.max(0, from))) if (bar.start > from + 1e-9 && bar.start <= to + 1e-9) bars.push(bar.start);
            for (const m of meterChanges(song)) if (m.beat >= from - 1e-9 && m.beat <= to + 1e-9) changes.push(m);
        } else {
            for (let b = Math.ceil((from - pickupBeats) / beatsPerBar) * beatsPerBar + pickupBeats; b <= to + 1e-9; b += beatsPerBar) {
                if (b > from + 1e-9) bars.push(b);
            }
        }
    }
    return { clef, signature, notes: laid, bars, changes };
}

const NOTES = ['C', 'C#', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B'];

/** "G" moved up 2 semitones is "A"; minor keys keep their "m". */
export function transposeKey(key, semitones) {
    if (!key || !semitones) return key;
    const minor = key.endsWith('m');
    const index = NOTES.indexOf(minor ? key.slice(0, -1) : key);
    if (index < 0) return key;
    return NOTES[(((index + semitones) % 12) + 12) % 12] + (minor ? 'm' : '');
}
