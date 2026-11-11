// Note names your way (docs/more-features-plan.md, feature 14): letters (D4), movable-do solfège
// (do re mi, with la-based minor), scale numbers (1 to 7, flats for the notes outside a major
// scale), or none. Pure, unit-tested in tests/notes.test.js.

import { midiName } from './dsp.js';

export const NOTE_STYLES = ['letters', 'solfege', 'numbers', 'off'];
const PITCH_CLASS = { C: 0, D: 2, E: 4, F: 5, G: 7, A: 9, B: 11 };
const SOLFEGE = ['do', 'di', 're', 'ri', 'mi', 'fa', 'fi', 'so', 'si', 'la', 'li', 'ti'];
const NUMBERS = ['1', '♭2', '2', '♭3', '3', '4', '♯4', '5', '♭6', '6', '♭7', '7'];

/** A key ("G", "F#m", "Bb") as its tonic's pitch class (0 to 11) and whether it's minor. */
export function parseKey(key) {
    const m = /^([A-G])([#b]?)(m?)$/.exec(key ?? '');
    if (!m) return { tonic: 0, minor: false };
    const tonic = (PITCH_CLASS[m[1]] + (m[2] === '#' ? 1 : m[2] === 'b' ? -1 : 0) + 12) % 12;
    return { tonic, minor: m[3] === 'm' };
}

/**
 * A note typed with its octave ("F#3", "f♯3", "Bb2", "B♭2", "c4", "A 4") as MIDI (middle C, C4, is 60),
 * or null when it isn't one. A lone "b" is the note B; "bb" is B flat.
 */
export function parsePitch(text) {
    const m = /^\s*([A-Ga-g])\s*([#♯b♭]?)\s*(-?\d)\s*$/.exec(text ?? '');
    if (!m) return null;
    const shift = m[2] === '#' || m[2] === '♯' ? 1 : m[2] ? -1 : 0;
    return 12 * (Number(m[3]) + 1) + PITCH_CLASS[m[1].toUpperCase()] + shift;
}

/** A note (MIDI, rounded) named in `style` for a song in `key`; '' for off. */
export function noteLabel(midi, style = 'letters', key = 'C') {
    const pitch = Math.round(midi);
    if (style === 'off') return '';
    if (style === 'letters') return midiName(pitch);
    const { tonic, minor } = parseKey(key);
    if (style === 'solfege') {
        // Movable do, la-based minor: a minor key's tonic is "la", so do is three semitones above.
        const doPc = minor ? (tonic + 3) % 12 : tonic;
        return SOLFEGE[(((pitch - doPc) % 12) + 12) % 12];
    }
    return NUMBERS[(((pitch - tonic) % 12) + 12) % 12];
}

const SHARPS = ['C', 'C♯', 'D', 'D♯', 'E', 'F', 'F♯', 'G', 'G♯', 'A', 'A♯', 'B'];
const FLATS = ['C', 'D♭', 'D', 'E♭', 'E', 'F', 'G♭', 'G', 'A♭', 'A', 'B♭', 'B'];
// Keys written with flats, as tonic pitch classes: F, B♭, E♭, A♭, D♭, G♭ major; D, G, C, F, B♭, E♭ minor.
const FLAT_MAJOR = new Set([5, 10, 3, 8, 1, 6]);
const FLAT_MINOR = new Set([2, 7, 0, 5, 10, 3]);
const SUFFIX = { maj: '', min: 'm', dim: 'dim', aug: 'aug', sus2: 'sus2', sus4: 'sus4', maj7: 'maj7', min7: 'm7', dom7: '7', dim7: 'dim7', hdim7: 'm7♭5', maj6: '6', min6: 'm6', add9: 'add9' };

/** Whether a key (its tonic, 0 to 11, and minor or not) is written with flats: F, B♭, E♭... major; D, G, C... minor. */
export function flatKey(tonic, minor = false) {
    return (minor ? FLAT_MINOR : FLAT_MAJOR).has(((tonic % 12) + 12) % 12);
}

/** A pitch class spelled with sharps or flats, in plain letters as chord charts write them ("F#", "Bb"). */
export function noteSpelling(pitchClass, flats = false) {
    const names = flats ? ['C', 'Db', 'D', 'Eb', 'E', 'F', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B'] : ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B']; // i18n-ignore: note names
    return names[((pitchClass % 12) + 12) % 12];
}

// What may follow a chord's root: the chart-writer's suffixes (m, maj7, sus4, add9, dim, °, ø, +, 7#9,
// (b13), alt...). Anything else (a word that starts with A to G: "Be", "Dear") isn't a chord.
const CHORD_SUFFIX = /^(?:m|min|maj|M|Δ|dim|°|o|ø|aug|\+|-|sus|add|alt|no|omit|[0-9]|[#b♯♭]|[(),])*$/;

/**
 * A chord symbol as a chart writes it ("F#m7/C#", "Bbmaj9", "C7#9", "G/B", "D♭add9") read as `{ root,
 * suffix, bass }`: the root and slash bass as pitch classes (bass null when none), the suffix exactly
 * as written, so a transposer moves only the letters. Null when it isn't a chord.
 */
export function parseChordSymbol(text) {
    const m = /^([A-G])([#b♯♭]?)([^/\s]*)(?:\/([A-G])([#b♯♭]?))?$/.exec(text ?? '');
    if (!m || !CHORD_SUFFIX.test(m[3])) return null;
    const shift = (a) => (a === '#' || a === '♯' ? 1 : a ? -1 : 0);
    const root = (PITCH_CLASS[m[1]] + shift(m[2]) + 12) % 12;
    const bass = m[4] ? (PITCH_CLASS[m[4]] + shift(m[5]) + 12) % 12 : null;
    return { root, suffix: m[3], bass };
}

/**
 * A chord suffix as the arranger's quality (js/arrange.js TONES): "" maj, "m" min, "7" dom7, "maj7",
 * "m7", "dim", "dim7", "m7b5" (ø) hdim7, "aug" (+), "sus2", "sus4" (sus), "6", "m6", "add9"; richer ones
 * as the chord a choir sings under them (9 and 13 as 7, maj9 as maj7, m9 as m7). Null when unknown.
 */
export function chordQuality(suffix) {
    const s = (suffix ?? '').replace(/[()]/g, '').replace('♭', 'b').replace('♯', '#');
    const table = [
        [/^$/, 'maj'], [/^(m|min|-)$/, 'min'], [/^(7|9|11|13|7(b|#)(5|9|11|13)|9sus4|7sus4|alt|7alt)$/, 'dom7'],
        [/^(maj7|M7|Δ7?|maj9|M9|maj13|Δ9)$/, 'maj7'], [/^(m7|min7|-7|m9|min9|m11|m13)$/, 'min7'], [/^(dim|°|o)$/, 'dim'],
        [/^(dim7|°7|o7)$/, 'dim7'], [/^(m7b5|ø7?|min7b5|-7b5)$/, 'hdim7'], [/^(aug|\+|#5|\+5)$/, 'aug'], [/^sus2$/, 'sus2'],
        [/^(sus4?|sus)$/, 'sus4'], [/^(6|maj6|M6|69|6\/9)$/, 'maj6'], [/^(m6|min6|-6)$/, 'min6'], [/^(add9|add2|2)$/, 'add9'],
    ];
    return table.find(([re]) => re.test(s))?.[1] ?? null;
}

/** A template chord ({ root, quality, bass } as written) named for the key it's sung in: "G", "D/F♯", "Em7". */
export function chordName(chord, transpose = 0, key = 'C') {
    const { tonic, minor } = parseKey(key);
    const names = (minor ? FLAT_MINOR : FLAT_MAJOR).has(tonic) ? FLATS : SHARPS;
    const pc = (n) => names[(((n + transpose) % 12) + 12) % 12];
    const bass = chord.bass === null || chord.bass === undefined || chord.bass === chord.root ? '' : `/${pc(chord.bass)}`;
    return `${pc(chord.root)}${SUFFIX[chord.quality] ?? ''}${bass}`;
}

// A tuner's note names (docs/tools/competitors-tuner-metronome-range.md): letters (C D E), fixed do
// (Do Re Mi, the C is always Do, as taught in much of Europe and Latin America) or German (H for B, B
// for B flat), with sharps or flats for the black keys.
export const NAMINGS = ['letters', 'fixed-do', 'german'];
const NAMED = {
    letters: [SHARPS, FLATS],
    'fixed-do': [
        ['Do', 'Do♯', 'Re', 'Re♯', 'Mi', 'Fa', 'Fa♯', 'Sol', 'Sol♯', 'La', 'La♯', 'Si'], // i18n-ignore: note names are symbols
        ['Do', 'Re♭', 'Re', 'Mi♭', 'Mi', 'Fa', 'Sol♭', 'Sol', 'La♭', 'La', 'Si♭', 'Si'], // i18n-ignore: note names are symbols
    ],
    german: [
        ['C', 'Cis', 'D', 'Dis', 'E', 'F', 'Fis', 'G', 'Gis', 'A', 'Ais', 'H'], // i18n-ignore: note names are symbols
        ['C', 'Des', 'D', 'Es', 'E', 'F', 'Ges', 'G', 'As', 'A', 'B', 'H'], // i18n-ignore: note names are symbols
    ],
};

/**
 * The naming a page's language reads notes in (its <html lang>): fixed Do on Spanish pages, German's on German ones,
 * letters otherwise; one of `among`, the namings a tool offers (else letters). A visitor's own choice, kept by the
 * tool, still wins: this is only the default.
 */
export function pageNaming(among = NAMINGS) {
    const lang = String(globalThis.document?.documentElement?.lang || 'en').split('-')[0];
    const wanted = { es: 'fixed-do', de: 'german' }[lang] ?? 'letters';
    return among.includes(wanted) ? wanted : 'letters';
}

/** A note (MIDI, rounded) as its name and octave number (middle C is C4): { name: 'F♯', octave: 3 }. */
export function pitchName(midi, { naming = 'letters', flats = false } = {}) {
    const m = Math.round(midi);
    const names = (NAMED[naming] ?? NAMED.letters)[flats ? 1 : 0];
    return { name: names[((m % 12) + 12) % 12], octave: Math.floor(m / 12) - 1 };
}
