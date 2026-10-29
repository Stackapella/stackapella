// Design tokens the canvas needs (the same values as css/input.css), and the words for musical
// things (keys, default part names), in the person's language.

import { t } from './i18n.js';

export const PART_COLORS = ['#f2a06b', '#c9b456', '#93c26e', '#4ccfb0', '#3ec5e2', '#7db0f7', '#b9a3f5', '#e58fcb'];


/** The name a new part gets: Melody, Harmony 1... then "Part 7". The singer can rename it. */
export function defaultPartName(index) {
    // Hillel: "don't auto name tracks melody, harmony, etc. Just name the Track 1 by default and
    // enable the user to rename the tracks."
    return t('parts.default.track', { number: index + 1 });
}

/** "G" as "G", "Em" as "E minor". */
export function keyLabel(key) {
    return key.endsWith('m') ? t('music.minorKey', { note: key.slice(0, -1) }) : key;
}

/** "G major" or "E minor", for the key picker. */
export function keyChoiceLabel(key) {
    return key.endsWith('m') ? t('music.minorKey', { note: key.slice(0, -1) }) : t('music.majorKey', { note: key });
}

export const KEYS = ['C', 'C#', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B'].flatMap((note) => [note, `${note}m`]);

export const TIME_SIGNATURES = ['4/4', '3/4', '6/8', '2/4'];

/** Beats per bar for counting in and the metronome (6/8 counts in dotted quarters: 2). */
export function beatsPerBar(timeSignature) {
    return { '4/4': 4, '3/4': 3, '6/8': 2, '2/4': 2 }[timeSignature] ?? 4;
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

export function formatTime(seconds) {
    const s = Math.max(0, Math.floor(seconds));
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}
