// Note names by language (docs/languages-plan.md, slice 1): letters (C D E, the default), fixed Do (Do Re Mi, as Spanish
// choirs read: Do is always C) and German's (C D E ... A H, with B for B flat, Es and Fis). Settings' Note names row
// picks one; "auto" follows the language (Spanish Do Re Mi, German H, letters otherwise). Every note name the app shows
// goes through dsp.js midiName, which asks this module, so a choice changes them everywhere. Octaves stay scientific
// in every system: middle C is C4, Do4, C4 (the glossary: never the Do3 some Spanish books use).

import { reactive } from 'vue';
import { letterName, setNoteNamer } from './dsp.js';
import { locale } from './i18n.js';
import { local } from './storage.js';

export const NAMINGS = ['auto', 'letters', 'fixed', 'german'];
const KEY = 'stackapella.noteNames';
const FIXED = ['Do', 'Do#', 'Re', 'Mib', 'Mi', 'Fa', 'Fa#', 'Sol', 'Lab', 'La', 'Sib', 'Si']; // i18n-ignore: note names
const GERMAN = ['C', 'Cis', 'D', 'Es', 'E', 'F', 'Fis', 'G', 'As', 'A', 'B', 'H']; // i18n-ignore: note names
// A language's own names when "auto": the rest read letters.
const BY_LANGUAGE = { es: 'fixed', de: 'german' };

const stored = local.get(KEY);
export const noteNames = reactive({ choice: NAMINGS.includes(stored) ? stored : 'auto' });

/** The naming in use: the person's choice, or their language's. */
export function naming() {
    return noteNames.choice === 'auto' ? (BY_LANGUAGE[locale.lang.split('-')[0]] ?? 'letters') : noteNames.choice;
}

/** Settings: a naming kept on this device ("auto" follows the language). */
export function chooseNaming(choice) {
    noteNames.choice = NAMINGS.includes(choice) ? choice : 'auto';
    local.set(KEY, noteNames.choice);
}

/** A MIDI note in `system` (the one in use by default): "C4", "Do4", "H3". */
export function nameNote(midi, system = naming()) {
    const m = Math.round(midi);
    const names = system === 'fixed' ? FIXED : system === 'german' ? GERMAN : null;
    return names ? `${names[((m % 12) + 12) % 12]}${Math.floor(m / 12) - 1}` : letterName(m);
}

// A key's note ("F#", "Bb", "C") in each system, its own spelling kept (Reb stays Reb, never Do#): fixed Do as Spanish
// writes keys ("Sib mayor"), German's with -is and -es (Fis, Es, B for B flat, H for B).
const FIXED_LETTERS = { C: 'Do', D: 'Re', E: 'Mi', F: 'Fa', G: 'Sol', A: 'La', B: 'Si' }; // i18n-ignore: note names
const GERMAN_KEYS = { Bb: 'B', B: 'H', Eb: 'Es', Ab: 'As' }; // i18n-ignore: note names

/** A key's note name, "F#" read in `system` (the one in use by default): "Fa#", "Fis". Anything else as it came. */
export function nameKeyNote(note, system = naming()) {
    const match = /^([A-G])([#b]?)$/.exec(String(note));
    if (!match || system === 'letters') return note;
    const [, letter, accidental] = match;
    if (system === 'fixed') return FIXED_LETTERS[letter] + accidental;
    if (system === 'german') return GERMAN_KEYS[note] ?? letter + (accidental === '#' ? 'is' : accidental === 'b' ? 'es' : '');
    return note;
}

/** The first notes of a scale in `system`, for Settings' choices ("C D E", "Do Re Mi", "C D E ... H"). */
export function namingSample(system) {
    if (system === 'german') return 'C D E … A H'; // i18n-ignore: note names
    return [60, 62, 64].map((m) => nameNote(m, system).replace(/-?\d+$/, '')).join(' ');
}

// Moveable do in a fixed-Do language (the courses lead and the glossary, 2026-11-11): a course note sung on a solfège
// syllable over the singer's home note is that syllable, never a fixed name ("mi" at any pitch, so never "Fa#4" under
// a sung "mi"). Song lanes keep their names: real lyrics say "la" and "mi" too.
const SOLFA = new Set(['do', 'di', 'ra', 're', 'ri', 'me', 'mi', 'fa', 'fi', 'se', 'so', 'sol', 'si', 'le', 'la', 'li', 'te', 'ti']);
export function isSolfa(lyric) {
    const word = String(lyric ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z]/gi, '').toLowerCase();
    return SOLFA.has(word);
}

setNoteNamer((midi) => nameNote(midi));
