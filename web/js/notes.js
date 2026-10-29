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

/** A template chord ({ root, quality, bass } as written) named for the key it's sung in: "G", "D/F♯", "Em7". */
export function chordName(chord, transpose = 0, key = 'C') {
    const { tonic, minor } = parseKey(key);
    const names = (minor ? FLAT_MINOR : FLAT_MAJOR).has(tonic) ? FLATS : SHARPS;
    const pc = (n) => names[(((n + transpose) % 12) + 12) % 12];
    const bass = chord.bass === null || chord.bass === undefined || chord.bass === chord.root ? '' : `/${pc(chord.bass)}`;
    return `${pc(chord.root)}${SUFFIX[chord.quality] ?? ''}${bass}`;
}
