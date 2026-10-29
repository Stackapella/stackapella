// A lesson's results, as Hillel's mockups have them (ux-spec 5.8): the level word and a line that
// says what was done ("You held G3 steady for 6.2 seconds."), the take as dots against the target
// band, the keyboard around your comfortable note, the unit's path and the end screen's lines.
// Pure, tested in tests/results.test.js; components/results.js draws them.

import { midiName } from './dsp.js';
import { renderPart } from './synth.js';
import { tk } from './i18n.js';

export const WORDS = { 3: tk('results.word.brilliant'), 2: tk('results.word.great'), close: tk('results.word.close'), keepGoing: tk('results.word.keepGoing') };
const CLOSE_PERCENT = 50; // one star from this: close; under it, keep going

/** The level word for a result: Brilliant (3 stars), Great (2), Close, or Keep going. */
export function levelWord(stars, percent = null) {
    if (stars >= 3) return WORDS[3];
    if (stars === 2) return WORDS[2];
    return percent !== null && percent >= CLOSE_PERCENT ? WORDS.close : WORDS.keepGoing;
}

/** The distance from `midi` to `target` in semitones, in the nearest octave (a man on a treble line). */
export function folded(midi, target) {
    return ((((midi - target) % 12) + 18) % 12) - 6;
}

/**
 * The take's readings against the target: `[{ t, midi, in }]`, `t` seconds from the take's start,
 * one every `every` seconds (the median of those readings, so a chart stays light), `in` when it's
 * within `cents` of the note sounding then. `track`: `{ rate, hz }` (trackInWorker's); `target`:
 * `[{ start, end, pitch }]` in seconds. The voice is drawn in its own octave folded to the target's
 * (the chart shows the target's octave).
 */
export function chartDots(track, target, { cents, every = 0.1 } = {}) {
    const step = Math.max(1, Math.round(track.rate * every));
    const out = [];
    for (let i = 0; i < track.hz.length; i += step) {
        const heard = [];
        for (let k = i; k < Math.min(track.hz.length, i + step); k++) if (track.hz[k] > 0) heard.push(12 * Math.log2(track.hz[k] / 440) + 69);
        if (!heard.length) continue;
        heard.sort((a, b) => a - b);
        const sung = heard[heard.length >> 1];
        const t = (i + step / 2) / track.rate;
        const note = target.find((n) => t >= n.start && t < n.end) ?? nearest(target, t);
        if (!note) continue;
        const off = folded(sung, note.pitch);
        out.push({ t, midi: note.pitch + off, in: Math.abs(off * 100) <= cents });
    }
    return out;
}

const nearest = (target, t) => target.reduce((best, n) => (!best || Math.abs((n.start + n.end) / 2 - t) < Math.abs((best.start + best.end) / 2 - t) ? n : best), null);

/** The longest run of readings in tune, in seconds (an unheard gap up to `gap` doesn't break it; a reading out of tune does). */
export function longestHold(dots, { every = 0.1, gap = 0.25 } = {}) {
    let best = 0;
    let from = null;
    let last = null;
    for (const dot of dots) {
        if (!dot.in) {
            from = null;
            continue;
        }
        if (from === null || dot.t - last > gap + every) from = dot.t;
        last = dot.t;
        best = Math.max(best, last - from + every);
    }
    return Math.round(best * 10) / 10;
}

/**
 * Hold scoring (the course review's M3): how much of the note you held on pitch in one go (most of
 * the stars), and how steadily you sat on it while sounding (the rest). Null when nothing was heard.
 */
export function holdPercent(dots, target) {
    const length = target.reduce((sum, n) => sum + (n.end - n.start), 0);
    const during = dots.filter((d) => target.some((n) => d.t >= n.start && d.t < n.end));
    if (!length || !during.length) return null;
    const held = Math.min(1, longestHold(during) / length);
    const steady = during.filter((d) => d.in).length / during.length;
    return Math.round(100 * (0.7 * held + 0.3 * steady));
}

/**
 * The line under the word: one held note says how long it was held ("You held G3 steady for 6.2
 * seconds."); several say how many lit ("You lit 7 of 8 notes."). `{ key, values }` for t().
 */
export function resultLine({ target, lit, hold }) {
    const pitches = new Set(target.map((n) => n.pitch));
    if (pitches.size === 1 && hold > 0) return { key: tk('results.line.held'), values: { note: midiName(target[0].pitch), seconds: hold } };
    return { key: tk('results.line.lit'), values: { lit, count: target.length } };
}

/**
 * The easy range shown on the keyboard: the range check's (or the course's) when there is one,
 * else a first guess around the comfortable note (a few notes under it, more above), said so.
 */
export function easyRange(home, range = null) {
    if (range && range.high - range.low >= 4 && home >= range.low && home <= range.high) return { low: range.low, high: range.high, guess: false };
    return { low: home - 3, high: home + 5, guess: true };
}

const BLACK = new Set([1, 3, 6, 8, 10]);

/**
 * The keyboard strip: about two octaves from the C below the easy range, `{ whites, blacks }`, each
 * key `{ midi, name, inRange, isNote }`; a black key's `after` is the white key it sits right of.
 */
export function keyboard(home, range) {
    const from = Math.floor(Math.min(range.low, home - 5) / 12) * 12;
    const to = Math.max(from + 23, Math.ceil((Math.max(range.high, home + 5) + 1) / 12) * 12 - 1);
    const whites = [];
    const blacks = [];
    for (let midi = from; midi <= to; midi++) {
        const key = { midi, name: midiName(midi), inRange: midi >= range.low && midi <= range.high, isNote: midi === Math.round(home) };
        if (BLACK.has(midi % 12)) blacks.push({ ...key, after: whites.length - 1 });
        else whites.push(key);
    }
    return { whites, blacks };
}

const VOICES = { soprano: tk('results.voice.soprano'), alto: tk('results.voice.alto'), tenor: tk('results.voice.tenor'), bass: tk('results.voice.bass') };
const SUITS = { low: tk('results.note.low'), lowMiddle: tk('results.note.lowMiddle'), highMiddle: tk('results.note.highMiddle'), high: tk('results.note.high') };

/**
 * The line on what a comfortable note means (Hillel: one note doesn't make a voice type): the parts
 * notes around it suit, or with a range (the range check's, course.js voiceType), the voice it suits.
 * `{ key, values, voiceKey }`: the voice's word is translated too.
 */
export function noteLine(home, voice = null) {
    if (voice && VOICES[voice]) return { key: tk('results.note.voice'), values: {}, voiceKey: VOICES[voice] };
    const suits = home < 48 ? 'low' : home < 53 ? 'lowMiddle' : home < 58 ? 'highMiddle' : 'high';
    return { key: SUITS[suits], values: { note: midiName(Math.round(home)) } };
}

/** About how many minutes a lesson takes, from its steps (a Learn step is quick, a song longer). */
const STEP_MINUTES = { learn: 0.5, try: 1, play: 1, home: 1.5, use: 3, record: 3 };
export function lessonMinutes(lesson) {
    return Math.max(1, Math.round(lesson.steps.reduce((sum, s) => sum + (STEP_MINUTES[s.kind] ?? 1), 0)));
}

/**
 * The unit's path on the end screen: each lesson `{ id, title, state }`, state 'done' (stars kept),
 * 'now' (just finished), 'next' (the one to do next) or 'later'.
 */
export function unitPath(unit, progress, nowId) {
    let nextGiven = false;
    return unit.lessons.map((lesson) => {
        let state = 'later';
        if (lesson.id === nowId) state = 'now';
        else if ((progress.lessons[lesson.id]?.stars ?? 0) > 0) state = 'done';
        else if (!nextGiven) state = 'next';
        if (state === 'next') nextGiven = true;
        return { id: lesson.id, title: lesson.title, state };
    });
}

/**
 * The line under the end screen's word: a first-try perfect score, a step up, or what to play again
 * for. `before`: the stars kept before this run (0 the first time).
 */
export function endLine({ stars, before, scored }) {
    if (!scored) return tk('results.end.done');
    if (stars >= 3) return before === 0 ? tk('results.end.perfectFirst') : before < 3 ? tk('results.end.upToThree') : tk('results.end.three');
    if (stars > before && before > 0) return tk('results.end.up');
    return stars === 2 ? tk('results.end.two') : tk('results.end.one');
}

/** Days in a row with some singing, up to `at` (today, or yesterday when today has none yet). */
export function daysInARow(progress, at = Date.now()) {
    const sang = (day) => (progress.practice?.[day] ?? 0) > 0 || (progress.xp?.days?.[day] ?? 0) > 0;
    const day = (offset) => {
        const d = new Date(at);
        d.setDate(d.getDate() - offset);
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    };
    let offset = sang(day(0)) ? 0 : 1;
    let count = 0;
    while (sang(day(offset))) {
        count++;
        offset++;
    }
    return count;
}

export const GUIDE_UNDER = 0.35; // With the guide: the guide's level under your take
const MIX_PEAK = 0.95; // and the mix never clips

/**
 * With the guide: your take (`{ samples, sampleRate }`) with the guide singing the notes you sang
 * (`target`: `{ start, end, pitch }` in seconds on the take's own timeline, frame 0 its start) mixed
 * in under it, brought down together if the two would pass full scale.
 */
export function withGuide(take, target, { level = GUIDE_UNDER } = {}) {
    const samples = Float32Array.from(take.samples);
    const guide = renderPart(target.map((n) => ({ beat: n.start, length: n.end - n.start, pitch: n.pitch })), { bpm: 60, sampleRate: take.sampleRate });
    for (let i = 0; i < Math.min(samples.length, guide.length); i++) samples[i] += guide[i] * level;
    const peak = samples.reduce((max, v) => Math.max(max, Math.abs(v)), 0);
    if (peak > MIX_PEAK) for (let i = 0; i < samples.length; i++) samples[i] *= MIX_PEAK / peak;
    return { samples, sampleRate: take.sampleRate };
}
