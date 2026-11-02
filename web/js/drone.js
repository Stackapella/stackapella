// Drones to tune against (docs/tune-chord-plan.md, slices 1 and 3): steady straight tones under a
// song, rendered by the synth and played on the AudioContext clock with the guides. "Key note": the
// key's tonic and its pure fifth, the whole song long. "The chord": each chord of the song, its root,
// third, fifth (and seventh) tuned pure around the root, the "build the chord" practice of the
// Barbershop course. Pure: the notes for renderPart, unit-tested in tests/drone.test.js.

import { chordTones, pureOffset } from './ring.js';
import { lengthBeats } from './templates.js';

export const DRONE_MODES = ['off', 'key', 'chord'];
const STRAIGHT = { cents: 0 }; // no vibrato: a drone is a straight tone
const NAMES = { C: 0, D: 2, E: 4, F: 5, G: 7, A: 9, B: 11 };

/** A key's tonic as a pitch class ("Bb" 10, "F#m" 6). */
export function tonicOf(key) {
    const m = /^([A-G])([#b]?)/.exec(key || 'C');
    return (NAMES[m[1]] + (m[2] === '#' ? 1 : m[2] === 'b' ? -1 : 0) + 12) % 12;
}

/** The pitch of class `pc` at or below `around`, kept between C2 and C4 (a drone sits low). */
function under(pc, around) {
    let pitch = around - ((((around - pc) % 12) + 12) % 12);
    while (pitch > 60) pitch -= 12;
    while (pitch < 36) pitch += 12;
    return pitch;
}

/**
 * The drone's notes for `mode` ('key' or 'chord'), in the template's own pitches (the guides'
 * transpose moves them too). `around`: where it sits, the singer's part's lowest note (the drone goes
 * at or under it). Each note `{ beat, length, pitch, vibrato }`, the pitch fractional for pure tuning.
 */
export function droneNotes(template, mode, { around = 55 } = {}) {
    const end = lengthBeats(template);
    if (mode === 'key') {
        const tonic = under(tonicOf(template.key), around);
        return [
            { beat: 0, length: end, pitch: tonic, vibrato: STRAIGHT },
            { beat: 0, length: end, pitch: tonic + 7 + pureOffset(tonic + 7, { root: tonic % 12, quality: 'maj' }) / 100, vibrato: STRAIGHT },
        ];
    }
    if (mode !== 'chord') return [];
    const chords = [...(template.chords ?? [])].sort((a, b) => a.beat - b.beat);
    return chords.flatMap((chord, i) => {
        const from = chord.beat;
        const to = Math.min(end, chords[i + 1]?.beat ?? end);
        if (to <= from) return [];
        const root = under(chord.root, around);
        // The root, then the chord's tones above it, each tuned pure; a different bass below.
        const pitches = chordTones(chord.quality).map((semis) => root + semis + pureOffset(root + semis, chord) / 100);
        if (chord.bass !== null && chord.bass !== undefined && chord.bass !== chord.root) {
            const below = root - ((((root - chord.bass) % 12) + 12) % 12 || 12);
            const offset = pureOffset(below, chord);
            pitches.unshift(below + (offset ?? 0) / 100);
        }
        return pitches.map((pitch) => ({ beat: from, length: to - from, pitch, vibrato: STRAIGHT }));
    });
}
