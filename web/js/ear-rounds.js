// Listen-and-choose rounds, shared by the app's lessons (components/choose.js, the "Major or minor?"
// steps) and the public ear trainers (js/public/tools/interval-trainer.js, chord-trainer.js, through the
// card in js/public/tools/ear-quiz.js): a round is `{ clips: [{ notes: [{ beat, length, step }], voices?,
// drone?, chords? }], options?, answer }`, sung by the guide voice (js/synth.js), and scored the same
// way. Interval and chord rounds can be made here as well as written in a course. No framework.

import { asWhistled, whistleShift } from './whistle.js';
import { renderBacking, renderPart } from './synth.js';
import { styleBend } from './style-scores.js';
import { QUALITIES } from './theory.js';

/**
 * A clip as one sound: its notes (in steps from `base`), over its drone or chords if it has them. A
 * note's `style` (a scoop, a fall: Pop and country) is sung as the style measure hears it
 * (style-scores.js styleBend), so the model and the measure share one shape. `voices` are more sung
 * lines at once (a harmonic interval, a chord), each as its own guide voice, since one voice sings one
 * note at a time.
 */
export function renderClip(clip, base, { tempo = 72, sampleRate, exercise = {}, whistle = null }) {
    const sung = (list) => list.map((n, i) => ({ beat: n.beat, length: n.length, pitch: base + n.step, vibrato: n.vibrato ?? null, bend: n.bend ?? styleBend(n, list[i + 1] ?? null, { ...exercise, tempo }, list[i - 1] ?? null) ?? null, voice: n.voice ?? null }));
    let lines = [clip.notes ?? [], ...(clip.voices ?? [])].filter((line) => line.length).map(sung);
    // A whistle step's clips are whistled, at whistle height (js/whistle.js): no mic is open while they play.
    if (exercise.whistle) {
        const shift = whistleShift(lines.flat(), whistle);
        lines = lines.map((line) => asWhistled(line, whistle, shift));
    }
    const end = Math.max(...lines.flat().map((n) => n.beat + n.length));
    const share = 1 / Math.sqrt(lines.length); // several voices together sound about as loud as one
    const layers = lines.map((notes) => renderPart(notes, { bpm: tempo, sampleRate }).map((v) => v * share));
    if (clip.drone) layers.push(renderPart([{ beat: 0, length: end, pitch: base + clip.drone.step, vibrato: { cents: 0 } }], { bpm: tempo, sampleRate }).map((v) => v * 0.5));
    if (clip.chords) layers.push(renderBacking(clip.chords, { bpm: tempo, sampleRate, endBeat: end, transpose: ((base % 12) + 12) % 12 }));
    const out = new Float32Array(Math.max(...layers.map((l) => l.length)));
    for (const layer of layers) for (let i = 0; i < layer.length; i++) out[i] += layer[i];
    return out;
}

/**
 * The rounds that can be played now: a clip plays a real singer's published recording (`recorded`,
 * by slot) or else its own notes, so a round whose clip has neither (a slot not yet recorded, with no
 * notes to fall back on: clips.json `fallback.skip`) is left out until it's recorded.
 */
export function playableRounds(rounds, recorded = {}) {
    return rounds.filter((r) => r.clips.every((c) => c.notes?.length || (c.clip && recorded[c.clip])));
}

/** The percent for `answers` (each round's first answer) against the rounds. */
export function choosePercent(rounds, answers) {
    if (!rounds.length) return null;
    const right = rounds.filter((r, i) => answers[i] === r.answer).length;
    return Math.round((100 * right) / rounds.length);
}

// ---- Rounds made here (the ear trainers) -------------------------------------------------------

/** Each interval's key and size in semitones, smallest first. */
export const INTERVALS = { m2: 1, M2: 2, m3: 3, M3: 4, P4: 5, TT: 6, P5: 7, m6: 8, M6: 9, m7: 10, M7: 11, P8: 12 };
/** The chord qualities a trainer offers, in the order a learner meets them (shapes: theory.js QUALITIES). */
export const CHORDS = ['maj', 'min', 'dim', 'aug', 'dom7', 'maj7', 'min7', 'hdim7', 'dim7'];

/** A root for a shape spanning `span` semitones, so all of it sits in [low, high]; `random` is 0 to 1. */
export function rootFor(span, { low, high, random }) {
    const top = Math.max(low, high - span);
    return low + Math.floor(random() * (top - low + 1));
}

/**
 * One of `keys`, weighted toward the ones missed (`missed`: key -> times wrong this visit), so weak
 * spots come up more often; `random` is 0 to 1.
 */
export function pickKey(keys, { missed = {}, random = Math.random } = {}) {
    const weights = keys.map((key) => 1 + 2 * Math.min(missed[key] ?? 0, 3));
    let at = random() * weights.reduce((a, b) => a + b, 0);
    for (let i = 0; i < keys.length; i++) {
        at -= weights[i];
        if (at < 0) return keys[i];
    }
    return keys.at(-1);
}

/**
 * An interval round: `key` (else one of `chosen`), up, down or together (`way`), from `base` (else a
 * root that keeps it in [low, high]). `{ base, key, way, clips, options, answer }`, tempo 60 (a beat a
 * second). The same base and way with another key is the learner's answer, to hear beside the right one.
 */
export function intervalRound({ key = null, chosen = [key], way = 'up', base = null, low = 48, high = 72, random = Math.random }) {
    key ??= pickKey(chosen, { random });
    const size = INTERVALS[key];
    base ??= rootFor(size, { low, high, random });
    const [first, second] = way === 'down' ? [size, 0] : [0, size];
    const clip = way === 'together'
        ? { notes: [{ beat: 0, length: 1.8, step: 0 }], voices: [[{ beat: 0, length: 1.8, step: size }]] }
        : { notes: [{ beat: 0, length: 0.9, step: first }, { beat: 1.05, length: 0.9, step: second }] };
    return { base, key, way, clips: [clip], options: chosen, answer: chosen.indexOf(key) };
}

/** A chord's steps from its lowest note: root position, or turned over `inversion` times. */
export function inverted(shape, inversion = 0) {
    const steps = [...shape];
    for (let i = 0; i < inversion % shape.length; i++) steps.push(steps.shift() + 12);
    return steps.map((step) => step - steps[0]);
}

/**
 * A chord round: `key` (else one of `chosen`), together, or broken up or down (`style`), in root
 * position or turned over (`inversion`: a number, or 'any' for a random one), from `base` (else a root
 * that keeps it in [low, high]). The same shape as intervalRound.
 */
export function chordRound({ key = null, chosen = [key], style = 'together', inversion = 0, base = null, low = 48, high = 69, random = Math.random }) {
    key ??= pickKey(chosen, { random });
    const turn = inversion === 'any' ? Math.floor(random() * QUALITIES[key].length) : inversion;
    const shape = inverted(QUALITIES[key], turn);
    base ??= rootFor(shape.at(-1), { low, high, random });
    const STEP = 0.42;
    const order = style === 'down' ? [...shape].reverse() : shape;
    const clip = style === 'together'
        ? { notes: [{ beat: 0, length: 1.8, step: shape[0] }], voices: shape.slice(1).map((step) => [{ beat: 0, length: 1.8, step }]) }
        : { notes: order.map((step, i) => ({ beat: i * STEP, length: STEP * 1.1, step })) };
    return { base, key, way: style, inversion: turn, clips: [clip], options: chosen, answer: chosen.indexOf(key) };
}
