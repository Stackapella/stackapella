// What the free metronome plays, step by step (docs/tools/competitors-tuner-metronome-range.md,
// "UX spec (metronome)"), kept apart from its card and its sounds so it can be tested without audio:
// the bar's beats and their accents (accent, normal, silent), groupings for odd metres (7/8 as
// 2+2+3), subdivisions, a count-in, the speed trainer, silent bars and the timer. The card asks
// nextStep() for each click just before it sounds (js/public/ticker.js), so a change lands on the
// next click. The tempo's Italian names and tap tempo live here too. Pure: tests/metronome-beats.test.js.

import { median } from '../dsp.js';

export const TEMPO = { min: 20, max: 300, extended: 400, standard: 100 };
export const BEATS = { min: 1, max: 16 };
export const SUBDIVISIONS = { none: 1, eighths: 2, triplets: 3, sixteenths: 4 };

// The tempo markings (names that are the same in every language), each with where it starts and
// the tempo a tap on it jumps to: the middle of its usual range.
export const MARKINGS = [
    { name: 'Grave', from: 0, jump: 35 }, // i18n-ignore: Italian tempo names
    { name: 'Largo', from: 40, jump: 50 }, // i18n-ignore
    { name: 'Larghetto', from: 60, jump: 63 }, // i18n-ignore
    { name: 'Adagio', from: 66, jump: 70 }, // i18n-ignore
    { name: 'Andante', from: 76, jump: 92 }, // i18n-ignore
    { name: 'Moderato', from: 108, jump: 114 }, // i18n-ignore
    { name: 'Allegro', from: 120, jump: 138 }, // i18n-ignore
    { name: 'Vivace', from: 156, jump: 166 }, // i18n-ignore
    { name: 'Presto', from: 176, jump: 184 }, // i18n-ignore
    { name: 'Prestissimo', from: 200, jump: 208 }, // i18n-ignore
];

/** The tempo marking a tempo falls under ("Andante" for 100). */
export function markingOf(bpm) {
    let found = MARKINGS[0];
    for (const m of MARKINGS) if (bpm >= m.from) found = m;
    return found;
}

/** A metre's usual grouping: 6/8 as 3+3, 7/8 as 2+2+3, 5/8 as 3+2; quarter-note metres beat by beat. */
export function defaultGroups(beats, unit) {
    if (unit !== 8 || beats < 5) return Array(beats).fill(1);
    if (beats % 3 === 0) return Array(beats / 3).fill(3);
    // Twos, ending on a three for an odd count (7/8: 2+2+3, 5/8: 3+2 by custom).
    if (beats === 5) return [3, 2];
    const groups = Array(Math.floor(beats / 2)).fill(2);
    if (beats % 2) groups[groups.length - 1] = 3;
    return groups;
}

/** The other ways to group an odd metre, for the picker (7/8: 2+2+3, 3+2+2, 2+3+2). */
export function groupingsOf(beats, unit) {
    const first = defaultGroups(beats, unit);
    if (first.every((g) => g === 1) || first.every((g) => g === first[0])) return [first];
    const seen = new Set();
    const out = [];
    // Every rotation of the usual grouping.
    for (let i = 0; i < first.length; i++) {
        const g = [...first.slice(i), ...first.slice(0, i)];
        const key = g.join('+');
        if (!seen.has(key)) {
            seen.add(key);
            out.push(g);
        }
    }
    return out;
}

/** The bar's starting accents: the one accented, the rest normal. */
export function defaultAccents(beats) {
    return Array.from({ length: beats }, (_, i) => (i === 0 ? 'accent' : 'normal'));
}

const CYCLE = { accent: 'normal', normal: 'silent', silent: 'accent' };
/** A beat dot tapped: accent, then normal, then silent, then accent again. */
export const nextAccent = (accent) => CYCLE[accent] ?? 'normal';

/** Where the metronome is: nothing played yet. */
export function startPosition() {
    return { step: 0, bar: 0, beat: 0, sub: 0, elapsed: 0, played: 0 };
}

/**
 * The next click and how long until the one after, from `pos` and the settings:
 * `{ beats, unit, bpm, subdivision, accents, groups, countIn (bars), trainer: { on, add, every, max },
 * silent: { on, play, mute }, minutes }`. Returns `{ sound, level, gap, beat, bar, countIn, bpm, end, pos }`:
 * `sound` is 'accent', 'beat', 'sub' or null (a silent beat, a muted bar), `level` 0 to 1, `gap` the
 * seconds to the next step, `beat` the dot to light (null on a subdivision), `bar` the bar counted
 * from the first after the count-in (negative during it), `end` true once the timer is up.
 */
export function nextStep(at, settings) {
    const beats = Math.max(BEATS.min, Math.min(BEATS.max, settings.beats));
    const per = SUBDIVISIONS[settings.subdivision] ?? 1;
    // The metre or subdivision changed under it: carry on from the next beat (or the next bar).
    let pos = at;
    if (pos.sub >= per) pos = { ...pos, sub: 0, beat: pos.beat + 1 };
    if (pos.beat >= beats) pos = { ...pos, sub: 0, beat: 0, bar: pos.bar + 1 };
    const countIn = settings.countIn ?? 0;
    const bar = pos.bar - countIn; // negative while counting in
    const bpm = tempoAt(settings, bar);
    // A count-in beat has no subdivisions, so its step is the whole beat.
    const gap = bar < 0 ? 60 / bpm : 60 / bpm / per;
    const end = settings.minutes > 0 && pos.elapsed >= settings.minutes * 60;
    let sound = null;
    let level = 1;
    if (bar < 0) {
        // The count-in: every beat, the one accented, no subdivisions, whatever the dots say.
        if (pos.sub === 0) sound = pos.beat === 0 ? 'accent' : 'beat';
    } else if (!mutedBar(settings, bar)) {
        if (pos.sub === 0) {
            const accent = settings.accents?.[pos.beat] ?? (pos.beat === 0 ? 'accent' : 'normal');
            if (accent === 'accent') sound = 'accent';
            else if (accent === 'normal') {
                sound = 'beat';
                // A group's first beat (7/8's 1, 3 and 5) a little stronger than the rest.
                level = groupStarts(settings.groups, beats).has(pos.beat) ? 1 : 0.72;
            }
        } else {
            sound = 'sub';
            level = 0.55;
        }
    }
    const next = { ...pos, step: pos.step + 1, elapsed: pos.elapsed + gap, played: pos.played + 1, sub: pos.sub + 1 };
    if (next.sub >= per || bar < 0) {
        next.sub = 0;
        next.beat = pos.beat + 1;
        if (next.beat >= beats) {
            next.beat = 0;
            next.bar = pos.bar + 1;
        }
    }
    return { sound, level, gap, beat: pos.sub === 0 ? pos.beat : null, bar, countIn: bar < 0, bpm, end, pos: next };
}

/** The tempo in bar `bar` (from 0 after the count-in): the speed trainer adds `add` every `every` bars up to `max`. */
export function tempoAt(settings, bar) {
    const t = settings.trainer;
    if (!t?.on || bar < 0) return settings.bpm;
    const steps = Math.floor(bar / Math.max(1, t.every));
    return Math.min(Math.max(settings.bpm, t.max ?? TEMPO.max), settings.bpm + steps * t.add);
}

/** Silent bars: play `play` bars, then `mute` bars muted, over and over (a test of keeping time). */
export function mutedBar(settings, bar) {
    const s = settings.silent;
    if (!s?.on || bar < 0 || !(s.mute > 0)) return false;
    return bar % (s.play + s.mute) >= s.play;
}

function groupStarts(groups, beats) {
    const starts = new Set([0]);
    if (!groups) return starts;
    let at = 0;
    for (const g of groups) {
        starts.add(at);
        at += g;
    }
    return new Set([...starts].filter((b) => b < beats));
}

// ---- Tap tempo ----------------------------------------------------------------------------------

export const TAP = { show: 3, settle: 6, resetSeconds: 2 };

/** A fresh tap tempo. */
export const tapTempo = () => ({ taps: [] });

/**
 * A tap at `at` seconds: the tempo shows after 3 taps and settles over about 6 (the median of the
 * last few gaps), and starts again after 2 seconds without one. Returns { state, bpm } (bpm null
 * until there's enough). `keep` is how many taps it reads (the tap tempo tool keeps more, for a
 * finer number; the metronome's Tap button the default).
 */
export function tap(state, at, { keep = TAP.settle } = {}) {
    const last = state.taps[state.taps.length - 1];
    const taps = last !== undefined && at - last <= TAP.resetSeconds ? [...state.taps, at].slice(-keep) : [at];
    if (taps.length < TAP.show) return { state: { taps }, bpm: null };
    const reading = tapReading(taps);
    return { state: { taps }, bpm: Math.max(TEMPO.min, Math.min(TEMPO.extended, Math.round(reading.bpm))) };
}

/**
 * What a run of taps (seconds) says: `bpm` unrounded (the median gap, so one fumbled tap doesn't
 * move it), `steadiness` ('steady' when the gaps vary under 3% of a beat, 'fair' under 7%, else
 * 'uneven', as the median of each gap's distance from the middle one), and `count`. Null under 3 taps.
 */
export function tapReading(taps) {
    if (taps.length < TAP.show) return null;
    const gaps = taps.slice(1).map((t, i) => t - taps[i]);
    const middle = median(gaps);
    const spread = median(gaps.map((g) => Math.abs(g - middle))) / middle;
    return { bpm: 60 / middle, steadiness: spread < 0.03 ? 'steady' : spread < 0.07 ? 'fair' : 'uneven', spread, count: taps.length };
}

