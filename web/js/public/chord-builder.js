// The chord builder's engine for the Songwriters and Create pages (docs/demo-pages-plan.md): a short
// tune with a chord a bar, harmonised in 2 to 4 parts by the app's own arranger (js/arrange.js), so a
// visitor can swap a chord and hear the parts move at once. Marketing's page draws the lane and the
// chord buttons; this gives it the slots, the parts and the sound.
// - slotsOf: each slot's chords to choose from (the arranger's own pick first, then the next best that
//   still hold the tune), with their names in the key and their Roman numerals.
// - harmonise: the parts for the chords chosen, every one of them kept as chosen (the app's "Try
//   another chord" plans the neighbours around a change; here only the chord tapped changes), voiced
//   for 2, 3 or 4 singers as the app's songs are.
// - play: the parts sung with voices.js on the audio clock, from any beat, in demo-player's shape.
// No import map (public_imports_check.py): arrange.js and theory.js import nothing that needs one.

import { chooseChords, harmonize } from '../arrange.js';
import { chordName } from '../theory.js';
import { audio, hz, voice } from './voices.js';

/**
 * The demo's tune: Amazing Grace's first line ("Amazing grace ... like me"), from the free song
 * (templates/free/amazing-grace.json, public domain), in G, 3/4, with one beat of pickup, and the
 * full beat's breath before "that" the song has had since v4.
 */
export const TUNE = Object.freeze({
    title: 'Amazing Grace', // i18n-ignore: a song's title
    key: { tonic: 7, mode: 'major' },
    timeSignature: '3/4',
    pickupBeats: 1,
    tempo: 80,
    phrases: [[0, 12], [12, 24]],
    melody: [
        { beat: 0, length: 1, pitch: 62, lyric: 'A-' },
        { beat: 1, length: 2, pitch: 67, lyric: 'maz-' },
        { beat: 3, length: 0.5, pitch: 71, lyric: 'ing' },
        { beat: 3.5, length: 0.5, pitch: 67 },
        { beat: 4, length: 2, pitch: 71, lyric: 'grace,' },
        { beat: 6, length: 1, pitch: 69, lyric: 'how' },
        { beat: 7, length: 2, pitch: 67, lyric: 'sweet' },
        { beat: 9, length: 1, pitch: 64, lyric: 'the' },
        { beat: 10, length: 1, pitch: 62, lyric: 'sound' },
        { beat: 12, length: 1, pitch: 62, lyric: 'that', br: true },
        { beat: 13, length: 2, pitch: 67, lyric: 'saved' },
        { beat: 15, length: 0.5, pitch: 71, lyric: 'a' },
        { beat: 15.5, length: 0.5, pitch: 67 },
        { beat: 16, length: 2, pitch: 71, lyric: 'wretch' },
        { beat: 18, length: 1, pitch: 69, lyric: 'like' },
        { beat: 19, length: 4, pitch: 74, lyric: 'me.' },
    ],
});

const pc = (n) => ((n % 12) + 12) % 12;
// Scale steps from the tonic as numerals (major-key spelling; a minor key's iii is bIII).
const DEGREE = { 0: 'I', 1: 'bII', 2: 'II', 3: 'bIII', 4: 'III', 5: 'IV', 6: '#IV', 7: 'V', 8: 'bVI', 9: 'VI', 10: 'bVII', 11: 'VII' };
const LOWER = ['min', 'dim', 'min7', 'hdim7', 'dim7', 'min6'];
const NUMERAL_SUFFIX = { dom7: '7', min7: '7', maj7: 'maj7', sus2: 'sus2', sus4: 'sus4', maj6: '6', min6: '6', dim: '°', dim7: '°7', hdim7: 'ø7', aug: '+', add9: 'add9' };

/** A chord's Roman numeral in `key`: G's C is IV, Em is vi, D7 is V7. */
export function numeralOf(chord, key) {
    const roman = DEGREE[pc(chord.root - key.tonic)];
    const cased = LOWER.includes(chord.quality) ? roman.replace(/[IV]+/, (r) => r.toLowerCase()) : roman;
    return cased + (NUMERAL_SUFFIX[chord.quality] ?? '');
}

/** A chord with its name and numeral for the buttons: `{ root, quality, name, numeral }`. */
function labelled({ root, quality }, key) {
    const numeral = numeralOf({ root, quality }, key);
    // chordName spells the root by the numeral's degree (G's bVII is F, not E#).
    return { root, quality, name: chordName({ root, quality, numeral }, key), numeral };
}

/** The tune as a one-part template, the shape harmonize() takes. */
function templateOf(tune) {
    return {
        timeSignature: tune.timeSignature,
        pickupBeats: tune.pickupBeats,
        sections: [{ name: 'Verse', startBeat: 0, endBeat: endOf(tune), phrases: tune.phrases }], // i18n-ignore: never shown
        parts: [{ key: 'soprano', name: 'Soprano', melody: true, guide: { type: 'notes', notes: tune.melody } }], // i18n-ignore: never shown
    };
}

const endOf = (tune) => Math.max(...tune.melody.map((n) => n.beat + n.length));

/**
 * The chord slots (the pickup, then a bar each; half a bar in 4/4), each
 * `{ beat, endBeat, options: [{ root, quality, name, numeral }], pick }`: up to 4 options, best
 * first, and `pick` the arranger's own choice (its index in `options`). `style` 'warm' (the default)
 * offers ii and vi too; 'simple' only I, IV, V and V7.
 */
export function slotsOf(tune = TUNE, { style = 'warm' } = {}) {
    const { slots } = chooseChords(tune.melody, { key: tune.key, timeSignature: tune.timeSignature, pickupBeats: tune.pickupBeats, style, phraseEnds: tune.phrases.map(([, end]) => end) });
    return slots.map((s) => ({ beat: s.beat, endBeat: s.endBeat, options: s.options.map((o) => labelled(o, tune.key)), pick: s.choice }));
}

/**
 * The parts for the chords chosen, one `{ root, quality }` a slot (slotsOf's order; a missing one
 * keeps the arranger's pick): `{ chords, parts: [{ key, notes: [{ beat, length, pitch, lyric }] }] }`,
 * the tune first. 2 singers sing soprano and alto, 3 add the bass, 4 the tenor too (the app's
 * voicings). Every chord stays as chosen, voiced as the app voices a song. Pure and quick (a few ms).
 */
export function harmonise(tune = TUNE, chosen = [], { singers = 4, style = 'warm' } = {}) {
    const slots = slotsOf(tune, { style });
    const chords = slots.map((s, i) => {
        const c = chosen[i] ?? s.options[s.pick];
        return { beat: s.beat, root: pc(c.root), quality: c.quality };
    });
    const { template, result } = harmonize(templateOf(tune), { style, key: tune.key, chords });
    const keys = template.voicings[Math.min(4, Math.max(2, singers))];
    // Score order, top down: soprano, alto, tenor, bass.
    const parts = ['soprano', 'alto', 'tenor', 'bass'].filter((k) => keys.includes(k)).map((k) => ({ key: k, notes: template.parts.find((p) => p.key === k).guide.notes }));
    return { chords: result.chords.map((c) => ({ ...labelled(c, tune.key), beat: c.beat, bass: c.bass })), parts };
}

/** Seconds from the start for a beat at `tempo` (beats a minute). */
export const beatToSeconds = (beat, tempo = TUNE.tempo) => (beat * 60) / tempo;

/**
 * Sings an arrangement (harmonise's) on the audio clock, from beat `from` to `to`, starting at `at`
 * (the AudioContext's time; soon, by default). The tune sings a little louder than the harmony.
 * Returns `{ voices, length, start }` in demo-player's shape: one voice a part, each with `ended`
 * and `stop(after)`, `length` the seconds it lasts and `start` its time on the audio clock (for
 * drawing a playhead). Call it inside the tap (demo-player's play does).
 */
export function play(arrangement, { tempo = TUNE.tempo, from = 0, to = Infinity, at = null, level = 0.08 } = {}) {
    const c = audio();
    const start = at ?? c.currentTime + 0.06;
    const end = Math.min(to, Math.max(...arrangement.parts.flatMap((p) => p.notes.map((n) => n.beat + n.length))));
    const voices = arrangement.parts.map((part) => partVoice(part.notes, { from, to: end, start, tempo, level: part.key === 'soprano' ? level * 1.3 : level }));
    return { voices, length: beatToSeconds(end - from, tempo), start };
}

/** One part as a voice-like handle: a voices.js voice a note, each started and stopped on the clock. */
function partVoice(notes, { from, to, start, tempo, level }) {
    const c = audio();
    const sung = notes
        .filter((n) => n.beat < to - 1e-6 && n.beat + n.length > from + 1e-6)
        .map((n) => {
            const on = start + beatToSeconds(Math.max(n.beat, from) - from, tempo);
            const off = start + beatToSeconds(Math.min(n.beat + n.length, to) - from, tempo);
            const v = voice(hz(n.pitch), { level, at: on, vibrato: { rate: 5.2, depth: 12 } });
            v.stop(Math.max(0, off - c.currentTime));
            return { v, off };
        });
    return {
        ended: Promise.all(sung.map(({ v }) => v.ended)),
        // Fade out `after` seconds from now: only the notes that would still sound then.
        stop(after = 0) {
            const t = c.currentTime + after;
            for (const { v, off } of sung) if (off > t) v.stop(after);
        },
    };
}
