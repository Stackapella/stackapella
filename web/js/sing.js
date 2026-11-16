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

// ---- The staff: js/staff-layout.js (no Vue, so a public page's score may load it), re-exported for the screens ----

export { clefFor, keySignature, spell, staffLayout, staffPitch } from './staff-layout.js';
