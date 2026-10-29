// Hum a tune (docs/hum-a-tune-plan.md): a tune hummed in free time, with no click, becomes a melody
// with a tempo, a metre and bars. Its notes come from the transcriber's free-time events (onsets and
// ends in seconds, a pitch each); from them:
// - an accent signal: each onset a short bump, a long note's bump the bigger (long notes mark the beat);
// - the tempo: a few candidates from the signal's periodicity, between 50 and 160 a minute, the
//   ones near 100 preferred;
// - the beat: for each candidate, a beat tracker (a dynamic programme over 10 ms frames) that lets
//   each beat land a little early or late, so a singer who slows or rushes still gets bars;
// - duple or compound: whether the notes between beats fall on halves or on thirds (6/8);
// - the notes: each onset placed between its beats and snapped to the simplest grid that fits (an
//   eighth, a sixteenth only where the take clearly has one), each note held to the next, a phrase
//   ending at a breath held to the next bar line or the next note;
// - the metre and the pickup: 4/4, 3/4 or 6/8 and the bar lines, from where the long notes fall
//   and how long the phrases are;
// - the candidate with the cleanest fit and the simplest rhythm wins, and how sure the choice was.
// With a click or Tap along the beats are known, and only the placing and the metre are worked out.
// Every note sings "mm" (Hillel). Pure: the tests hum the library's tunes at it.

import { findKey } from './transcribe.js';

const FRAME = 0.01; // the accent signal's step, in seconds
const BREATH = 0.25; // a silence this long (seconds) ends a phrase
const BPM_LOW = 50;
const BPM_HIGH = 160;
const BPM_LIKED = 100; // the prior's centre
const MAX_BARS = 32; // Hillel: "32 bars or a minute"
const LOOSE_FIT = 0.025; // onsets this far off the grid, on average (beats): the beat may be wrong
const DEAR_GRID = 1.8; // a best grid costing more than this was a poor fit anyway: the beat may be wrong
export const SYLLABLE = 'mm';

/**
 * The accent signal: a bump per onset, sized by the note's length (a long note is more often on a
 * beat, a downbeat even), normalised to a peak of 1.
 *
 * @param {{ start: number, end: number }[]} events
 */
export function accents(events) {
    const end = Math.max(0, ...events.map((e) => e.end)) + 1;
    const out = new Float32Array(Math.ceil(end / FRAME));
    const spread = 3; // frames either side (30 ms)
    for (let i = 0; i < events.length; i++) {
        const e = events[i];
        const next = events[i + 1]?.start ?? e.end;
        const held = Math.min(1.5, Math.max(0.1, next - e.start));
        const at = Math.round(e.start / FRAME);
        for (let k = -spread * 2; k <= spread * 2; k++) {
            const f = at + k;
            if (f >= 0 && f < out.length) out[f] += Math.sqrt(held) * Math.exp(-(k * k) / (2 * spread * spread));
        }
    }
    const peak = Math.max(1e-9, ...out);
    for (let f = 0; f < out.length; f++) out[f] /= peak;
    return out;
}

const prior = (bpm) => Math.exp(-0.5 * (Math.log2(bpm / BPM_LIKED) / 0.8) ** 2);

/** Beat periods (seconds) worth trying: the autocorrelation's best peaks, weighted by the prior. */
export function tempoCandidates(signal, count = 4) {
    const lo = Math.round(60 / BPM_HIGH / FRAME);
    const hi = Math.round(60 / BPM_LOW / FRAME);
    const scores = [];
    for (let lag = lo; lag <= hi; lag++) {
        let s = 0;
        for (let f = lag; f < signal.length; f++) s += signal[f] * signal[f - lag];
        scores.push({ period: lag * FRAME, score: s * prior(60 / (lag * FRAME)) });
    }
    const peaks = scores.filter((p, i) => i > 0 && i < scores.length - 1 && p.score >= scores[i - 1].score && p.score >= scores[i + 1].score);
    peaks.sort((a, b) => b.score - a.score);
    const chosen = [];
    for (const p of peaks) {
        if (chosen.every((c) => Math.abs(Math.log(c.period / p.period)) > 0.06)) chosen.push(p);
        if (chosen.length === count) break;
    }
    return chosen.map((p) => p.period);
}

/**
 * Beat times (seconds) at about `period` apart, each allowed to drift, placed on the accents: the
 * best path through the signal where every beat is 0.75 to 1.33 periods after the last.
 */
export function trackBeats(signal, period, from, to) {
    const P = period / FRAME;
    const lo = Math.max(1, Math.round(P * 0.75));
    const hi = Math.round(P * 1.33);
    const first = Math.max(0, Math.floor(from / FRAME) - Math.round(P));
    const last = Math.min(signal.length - 1, Math.ceil(to / FRAME) + 1);
    const score = new Float64Array(signal.length).fill(-Infinity);
    const back = new Int32Array(signal.length).fill(-1);
    const tight = 20; // how much a beat's drift costs against the signal
    for (let f = first; f <= last; f++) {
        let best = f - first < hi ? 0 : -Infinity;
        let from_ = -1;
        for (let lag = lo; lag <= hi; lag++) {
            const g = f - lag;
            if (g < first || score[g] === -Infinity) continue;
            const s = score[g] - tight * Math.log(lag / P) ** 2;
            if (s > best) {
                best = s;
                from_ = g;
            }
        }
        if (best === -Infinity) continue;
        score[f] = best + signal[f];
        back[f] = from_;
    }
    let end = last;
    for (let f = Math.max(first, last - hi); f <= last; f++) if (score[f] > score[end]) end = f;
    const beats = [];
    for (let f = end; f >= 0; f = back[f]) beats.push(f * FRAME);
    beats.reverse();
    return extend(beats, period, from, to);
}

/** Beats carried on at the edge spacing until they cover `from` to `to`. */
function extend(beats, period, from, to) {
    const out = [...beats];
    if (!out.length) out.push(from);
    while (out[0] > from - 1e-6) out.unshift(out[0] - (out.length > 1 ? out[1] - out[0] : period));
    while (out[out.length - 1] < to + 1e-6) out.push(out[out.length - 1] + (out.length > 1 ? out[out.length - 1] - out[out.length - 2] : period));
    return out;
}

/** Where a time falls, in beats (fractional), between the beats around it. */
export function beatAt(beats, time) {
    let j = 0;
    while (j < beats.length - 2 && beats[j + 1] <= time) j++;
    return j + (time - beats[j]) / (beats[j + 1] - beats[j]);
}

const nearest = (x, grid) => Math.round(x / grid) * grid;

/**
 * The notes placed on the beats: each onset snapped to an eighth (a sixteenth only where an eighth
 * is clearly wrong and a sixteenth clearly right), or to thirds of the beat when the tune is
 * compound. Returns positions in beats from the first beat, with how far each was moved.
 */
function place(events, beats, compound) {
    const grid = compound ? 1 / 3 : 1 / 2;
    const fine = compound ? 1 / 6 : 1 / 4;
    const raws = events.map((e) => beatAt(beats, e.start));
    return events.map((e, i) => {
        const raw = raws[i];
        const coarse = nearest(raw, grid);
        const sharp = nearest(raw, fine);
        // A run of quick notes (under 0.4 of a beat apart) is on the fine grid; elsewhere the fine
        // grid only where the coarse one is clearly wrong and the fine one clearly right.
        const quick = Math.min(i > 0 ? raw - raws[i - 1] : Infinity, i + 1 < raws.length ? raws[i + 1] - raw : Infinity) < 0.4;
        const at = quick || (Math.abs(raw - coarse) > grid * 0.3 && Math.abs(raw - sharp) < fine * 0.25) ? sharp : coarse;
        return { ...e, raw, at, endRaw: beatAt(beats, e.end), off: Math.abs(raw - at) };
    });
}

/** Whether the notes between the beats fall on thirds (compound time) rather than halves. */
function isCompound(events, beats) {
    const fracs = events.map((e) => beatAt(beats, e.start) % 1).map((f) => (f < 0 ? f + 1 : f));
    const off = fracs.filter((f) => f > 0.12 && f < 0.88);
    if (off.length < Math.max(3, fracs.length * 0.15)) return false;
    const err = (grid) => off.reduce((s, f) => s + Math.abs(f - nearest(f, grid)), 0) / off.length;
    // Against the duple grid's sixteenths too, so a dotted eighth and sixteenth isn't heard as thirds.
    return err(1 / 3) < err(1 / 4) * 0.5;
}

/**
 * The metre and the bar lines: for 4, 3 or 2 beats a bar, every phase, how much more weight (the
 * long notes) falls on the bar's first beat than elsewhere, and how many phrases are whole bars.
 */
function metre(notes, phrases, compound, only = null, key = findKey(notes)) {
    const sizes = only ? [only] : compound ? [2] : [4, 3];
    const step = compound ? 1 / 3 : 1 / 2;
    const end = notes.at(-1).beat + notes.at(-1).length;
    const onsets = new Map(notes.map((n) => [Math.round(n.beat / step), n]));
    const options = [];
    for (const size of sizes) {
        for (let phase = 0; phase < size; phase++) {
            // Every grid slot: how likely an onset is there, by its place in the bar.
            let fit = 0;
            let slots = 0;
            for (let slot = 0; slot * step < end; slot++) {
                const beat = slot * step;
                const pos = (((beat - phase) % size) + size) % size;
                const p = onsetChance(pos, size, step);
                const n = onsets.get(slot);
                fit += Math.log(n ? p : 1 - p);
                slots++;
                // A long note starts on a strong beat (the bar's first, or the third in 4/4).
                if (n && n.length >= 1.5 && !(pos < 1e-6 || (size === 4 && Math.abs(pos - 2) < 1e-6))) fit -= 1.2;
            }
            const pickup = ((phase % size) + size) % size;
            const pickupPrior = Math.log(pickup <= 1 ? 0.3 : pickup <= 2 ? 0.15 : 0.08);
            const parts = {
                phrases: 2 * phraseBars(phrases, size),
                fit: fit / slots * 4,
                pickup: pickupPrior * 0.15,
                rep: 1.5 * repeats(notes, size, compound),
                harm: 2 * harmonic(notes, size, phase, key),
            };
            options.push({ size, phase, score: Object.values(parts).reduce((x, y) => x + y, 0) });
        }
    }
    options.sort((a, b) => b.score - a.score);
    const best = options[0];
    const rival = options.find((o) => o.size !== best.size || o.phase !== best.phase);
    return { ...best, margin: rival ? best.score - rival.score : Infinity, strength: best.score };
}

/**
 * Whether the phrases (breath to breath, start to start) run 1, 2, 4 or 8 whole bars of `size`
 * beats: the strongest sign of the bar's length, since tunes are built of 2- and 4-bar phrases.
 */
function phraseBars(phrases, size) {
    const lengths = [];
    for (let k = 0; k + 1 < phrases.length; k++) lengths.push(phrases[k + 1][0] - phrases[k][0]);
    if (!lengths.length) return 0;
    let score = 0;
    for (const l of lengths) {
        const bars = l / size;
        const whole = Math.abs(bars - Math.round(bars)) < 0.15;
        score += whole && [1, 2, 4, 8].includes(Math.round(bars)) ? 1 : whole ? 0.4 : 0;
    }
    return score / lengths.length;
}

/** How likely a note starts at a place in the bar (a beat from the bar line), by its strength. */
function onsetChance(pos, size, step) {
    const near = (x) => Math.abs(pos - x) < 1e-6;
    if (near(0)) return 0.9;
    if (size === 4 && near(2)) return 0.75;
    if (Math.abs(pos - Math.round(pos)) < 1e-6) return 0.6;
    return Math.abs(pos / step - Math.round(pos / step)) < 1e-6 ? 0.3 : 0.1;
}

/**
 * The tune on the beats found: notes in beats from the first one (beat 0), their lengths, the
 * phrases, the metre and the pickup, and the fit (how far the onsets had to move, on average).
 */
function onBeats(events, beats, { compound = isCompound(events, beats), timeSignature = null } = {}) {
    const placed = place(events, beats, compound);
    // Counted from the first note's beat, so the beats stay whole numbers; the first note is moved
    // to beat 0 at the end (a pickup on the "and" is half a beat).
    const origin = Math.floor(placed[0].at + 1e-9);
    const grid = compound ? 1 / 3 : 1 / 2;
    // Phrases: a breath (a silence over BREATH seconds) ends one.
    const notes = [];
    const starts = [0];
    for (let i = 0; i < placed.length; i++) {
        const p = placed[i];
        const next = placed[i + 1];
        const breath = next && next.start - p.end >= BREATH;
        if (breath) starts.push(i + 1);
        const beat = round(p.at - origin);
        let length;
        if (next && !breath) length = round(next.at - origin) - beat;
        else length = Math.max(grid, nearest(p.endRaw - origin, grid) - beat);
        notes.push({ beat, length: Math.max(grid, length), pitch: p.pitch, sure: p.sure !== false, breathAfter: !!breath });
    }
    // Two onsets snapped onto one place: the second moves on to the next free fine step.
    for (let i = 1; i < notes.length; i++) {
        if (notes[i].beat <= notes[i - 1].beat) notes[i].beat = round(notes[i - 1].beat + grid / 2);
        notes[i - 1].length = Math.min(notes[i - 1].length, round(notes[i].beat - notes[i - 1].beat)) || grid;
    }
    const phrases = starts.map((s, k) => [notes[s].beat, k + 1 < starts.length ? notes[starts[k + 1] - 1].beat + notes[starts[k + 1] - 1].length : notes.at(-1).beat + notes.at(-1).length]);
    const found = timeSignature ? { size: perBar(timeSignature), phase: null, margin: Infinity } : metre(notes, phrases, compound);
    const size = found.size;
    // With a known metre, the bar lines fall where the metre's own scoring puts them.
    const phase = found.phase ?? bestPhase(notes, size);
    // The first note to beat 0; the pickup is the beats before the first bar line.
    const lead = notes[0].beat;
    for (const n of notes) n.beat = round(n.beat - lead);
    for (const p of phrases) {
        p[0] = round(p[0] - lead);
        p[1] = round(p[1] - lead);
    }
    const pickup = round((((phase - lead) % size) + size) % size);
    // A phrase's last note holds to the next bar line, or to the next note, whichever comes first;
    // the song's last note to the end of its bar.
    for (let i = 0; i < notes.length; i++) {
        const n = notes[i];
        if (!n.breathAfter && i < notes.length - 1) continue;
        const end = n.beat + n.length;
        const bar = barLineAfter(end, pickup, size);
        const target = i < notes.length - 1 ? Math.min(bar, notes[i + 1].beat) : bar;
        if (target > end) n.length = round(target - n.beat);
    }
    const fit = placed.reduce((s, p) => s + p.off, 0) / placed.length;
    const fine = notes.filter((n) => Math.abs(n.beat / grid - Math.round(n.beat / grid)) > 1e-6).length / notes.length;
    return { offset: round(origin + lead), strength: found.strength ?? 0, notes, phrases: phrases.map(([a, b], k) => [a, k + 1 < phrases.length ? phrases[k + 1][0] : barLineAfter(b, pickup, size)]), size, pickup, compound, fit, fine, margin: found.margin };
}

/**
 * How much of the tune comes back 2 and 4 bars later, note for note (an onset on the same pitch):
 * tunes repeat their phrases a whole number of bars apart, so the right bar length finds more.
 */
function repeats(notes, size, compound) {
    const step = compound ? 1 / 3 : 1 / 2;
    const at = new Map(notes.map((n) => [Math.round(n.beat / step), n.pitch]));
    let best = 0;
    for (const bars of [2, 4]) {
        const lag = Math.round((size * bars) / step);
        let same = 0;
        let count = 0;
        for (const [slot, pitch] of at) {
            if (!at.has(slot - lag)) continue;
            count++;
            if (at.get(slot - lag) === pitch) same++;
        }
        best = Math.max(best, count ? (same / count) * Math.min(1, count / 8) : 0);
    }
    return best;
}

// The key's chords, by scale step: a bar of a folk tune mostly sits on one of them.
const CHORDS = {
    major: [[0, 4, 7], [2, 5, 9], [4, 7, 11], [5, 9, 0], [7, 11, 2], [9, 0, 4]],
    minor: [[0, 3, 7], [5, 8, 0], [7, 11, 2], [7, 10, 2], [8, 0, 3], [3, 7, 10], [10, 2, 5]],
};

/**
 * How well the bars fit one chord each, with bar lines at `phase` + k bars: each bar's notes
 * (by how long they sound in it, the bar's first note counting more) against its best chord of the
 * key. Harmony changes on the bar line, so the right bar lines fit best.
 */
function harmonic(notes, size, phase, key) {
    const chords = CHORDS[key.mode].map((c) => c.map((x) => (x + key.tonic) % 12));
    const end = notes.at(-1).beat + notes.at(-1).length;
    let sum = 0;
    let bars = 0;
    for (let start = phase - size; start < end; start += size) {
        const weights = new Array(12).fill(0);
        let total = 0;
        for (const n of notes) {
            const from = Math.max(start, n.beat);
            const to = Math.min(start + size, n.beat + n.length);
            if (to <= from) continue;
            const w = (to - from) * (Math.abs(n.beat - start) < 1e-6 ? 1.5 : 1);
            weights[((n.pitch % 12) + 12) % 12] += w;
            total += w;
        }
        if (!total) continue;
        sum += Math.max(...chords.map((c) => c.reduce((s, pc) => s + weights[pc], 0))) / total;
        bars++;
    }
    return bars ? sum / bars : 0;
}

/** The bar lines' phase for a known number of beats a bar. */
const bestPhase = (notes, size) => metre(notes, [], false, size).phase;

const round = (x) => Math.round(x * 1000) / 1000;
const perBar = (ts) => ({ '4/4': 4, '3/4': 3, '2/4': 2, '6/8': 2 })[ts] ?? 4;
const signature = (size, compound) => (compound ? '6/8' : size === 3 ? '3/4' : size === 2 ? '2/4' : '4/4');

/** The first bar line at or after `beat`, with bar lines at `pickup` + k bars. */
function barLineAfter(beat, pickup, size) {
    const k = Math.ceil((beat - pickup - 1e-6) / size);
    return round(pickup + Math.max(0, k) * size);
}

/**
 * A hummed take's events (`{ start, end, pitch, sure }`, seconds) as a melody:
 * `{ notes: [{ beat, length, pitch, lyric, br, sure }], tempo, timeSignature, pickupBeats, key,
 * phrases: [{ fromNote, toNote, startBeat, endBeat, startSeconds, endSeconds }], sure: { beat, metre },
 * cut, grid }`. `beats` (seconds, from a click or Tap along) skip the
 * beat finding; `timeSignature` skips the metre.
 */
export function humToMelody(events, { beats = null, timeSignature = null } = {}) {
    const sorted = [...events].filter((e) => e.end > e.start).sort((a, b) => a.start - b.start);
    if (!sorted.length) return null;
    const from = sorted[0].start;
    const to = sorted.at(-1).end;
    let best;
    if (beats) {
        best = { ...onBeats(sorted, extend(beats, beats[1] - beats[0], from, to), { timeSignature }), beats };
    } else {
        const signal = accents(sorted);
        const periods = [];
        for (const p of tempoCandidates(signal)) {
            for (const q of [p, p * 2, p / 2]) {
                const bpm = 60 / q;
                if (bpm >= BPM_LOW && bpm <= BPM_HIGH && periods.every((r) => Math.abs(Math.log(r / q)) > 0.06)) periods.push(q);
            }
        }
        for (const period of periods) {
            const found = trackBeats(signal, period, from, to);
            // The tracker can settle on the off-beats of a syncopated tune: the beats half a beat
            // later are tried too.
            for (const tracked of [found, found.map((t, i) => (i + 1 < found.length ? (t + found[i + 1]) / 2 : t + (t - found[i - 1]) / 2))]) {
            const result = onBeats(sorted, tracked, { timeSignature });
            const bpm = 60 / median(gaps(tracked));
            // Cleaner fit, simpler rhythm, a liked tempo.
            // About one note a beat is how tunes are written (half as many: the beat is too fast).
            const perBeat = result.notes.length / Math.max(1, result.notes.at(-1).beat + result.notes.at(-1).length);
            const density = Math.log2(perBeat / 0.75) ** 2;
            const cost = result.fit * 60 + result.fine * 0.5 + density * 0.2 - Math.log(prior(bpm) + 1e-6) * 0.25;
            if (!best || cost < best.cost) best = { ...result, beats: tracked, cost };
            }
        }
    }
    const tempo = Math.round(60 / median(gaps(best.beats)));
    // Hillel's limit: 32 bars.
    const limit = best.pickup + MAX_BARS * best.size;
    const kept = best.notes.filter((n) => n.beat < limit);
    const cut = kept.length < best.notes.length;
    if (cut) kept.at(-1).length = Math.min(kept.at(-1).length, round(limit - kept.at(-1).beat));
    const phraseStarts = new Set(best.phrases.map(([a]) => a));
    const notes = kept.map((n) => ({ beat: n.beat, length: n.length, pitch: n.pitch, lyric: SYLLABLE, ...(phraseStarts.has(n.beat) ? { br: true } : {}), sure: n.sure }));
    // Each note is one event of the take, in order, so a phrase knows its notes and its seconds.
    const phrases = best.phrases.filter(([a]) => a < limit).map(([startBeat, endBeat]) => {
        const fromNote = notes.findIndex((n) => n.beat >= startBeat - 1e-6);
        let toNote = fromNote;
        while (toNote + 1 < notes.length && notes[toNote + 1].beat < endBeat - 1e-6) toNote++;
        return { fromNote, toNote, startBeat, endBeat: Math.min(endBeat, limit), startSeconds: sorted[fromNote].start, endSeconds: sorted[toNote].end };
    });
    return {
        notes,
        tempo,
        timeSignature: timeSignature ?? signature(best.size, best.compound),
        pickupBeats: best.pickup,
        key: keyOf(notes, { size: best.size, pickup: best.pickup }),
        phrases,
        // Unsure of the beat when the onsets sit loosely on it or the best grid is a poor fit even so,
        // so the app can ask for Tap along. On the labelled takes (tests/fixtures/hum-unsure.json) it
        // catches 5 of 7 wrong gentle takes and about 2 in 3 wrong rough ones, and asks on about one
        // right gentle take in fourteen.
        sure: { beat: beats ? true : best.fit < LOOSE_FIT && best.cost <= DEAR_GRID, metre: timeSignature ? true : best.margin > 0.15 },
        cut,
        // The beat grid (each beat's time in the take, seconds; the tune's beat 0 is beat `offset`), for rehum.
        grid: { beats: best.beats, offset: best.offset, compound: best.compound },
    };
}

/**
 * One phrase hummed again ("Again" for a line): its new events (seconds, the new take's own clock)
 * take the phrase's place on the melody's beat grid, keeping the tempo, the metre and the key. The
 * new take's first note lands where the phrase's first note was; notes that would run into the next
 * phrase are cut off there, and its last note holds to the next bar line or phrase. Pure: returns a
 * new melody.
 */
export function rehum(melody, index, events) {
    const phrase = melody.phrases[index];
    const sorted = [...events].filter((e) => e.end > e.start).sort((a, b) => a.start - b.start);
    if (!phrase || !sorted.length) return melody;
    const shift = phrase.startSeconds - sorted[0].start;
    const moved = sorted.map((e) => ({ ...e, start: e.start + shift, end: e.end + shift }));
    const { beats, offset, compound } = melody.grid;
    const grid = compound ? 1 / 3 : 1 / 2;
    const size = perBar(melody.timeSignature);
    const next = melody.phrases[index + 1];
    const stop = next ? next.startBeat : null;
    const placed = place(moved, extend(beats, beats[1] - beats[0], moved[0].start, moved.at(-1).end), compound).map((p) => ({ ...p, at: round(p.at - offset), endRaw: p.endRaw - offset }));
    const notes = [];
    for (let i = 0; i < placed.length; i++) {
        const p = placed[i];
        let beat = Math.max(p.at, notes.length ? round(notes.at(-1).beat + grid / 2) : phrase.startBeat);
        if (i === 0) beat = phrase.startBeat;
        if (stop !== null && beat >= stop - 1e-6) break;
        notes.push({ beat, length: 0, pitch: p.pitch, lyric: SYLLABLE, ...(i === 0 ? { br: true } : {}), sure: p.sure !== false, endRaw: p.endRaw });
    }
    for (let i = 0; i < notes.length; i++) {
        const n = notes[i];
        const after = notes[i + 1]?.beat;
        if (after !== undefined) n.length = round(after - n.beat);
        else {
            const end = Math.max(n.beat + grid, nearest(n.endRaw, grid));
            const bar = barLineAfter(end, melody.pickupBeats, size);
            n.length = round(Math.max(grid, (stop !== null ? Math.min(bar, stop) : bar) - n.beat));
        }
        delete n.endRaw;
    }
    const before = melody.notes.slice(0, phrase.fromNote);
    const later = melody.notes.slice(phrase.toNote + 1);
    const delta = notes.length - (phrase.toNote - phrase.fromNote + 1);
    const phrases = melody.phrases.map((ph, k) => {
        if (k < index) return ph;
        if (k === index) return { ...ph, toNote: ph.fromNote + notes.length - 1, endBeat: next ? ph.endBeat : round(notes.at(-1).beat + notes.at(-1).length), startSeconds: moved[0].start, endSeconds: moved[notes.length - 1].end };
        return { ...ph, fromNote: ph.fromNote + delta, toNote: ph.toNote + delta };
    });
    return { ...melody, notes: [...before, ...notes, ...later], phrases };
}

/** Beat times from Tap along: the taps (seconds), carried on either side at their own spacing. */
export function beatsFromTaps(taps) {
    const sorted = [...taps].sort((a, b) => a - b);
    return sorted.length >= 2 ? sorted : null;
}

/**
 * The key: the transcriber's profile match, and the mode settled by the tonic's third (a tune with G
 * and no G-sharp over E is in E minor, Dorian or not). A whole tune (12 bars or more) gives its last
 * note, most often the tonic, a sixth of the weight. A short take often stops on the fifth (two
 * lines of Amazing Grace end on D), so there the metre helps instead: a note on a downbeat counts
 * double, and the first downbeat's note and the last note a sixth each.
 */
export function keyOf(notes, { size = 0, pickup = 0 } = {}) {
    const total = notes.reduce((sum, n) => sum + n.length, 0);
    const end = notes.at(-1).beat + notes.at(-1).length;
    const short = size > 0 && (end - pickup) / size < 12;
    const onDownbeat = (n) => Math.abs((((n.beat - pickup) % size) + size) % size) < 1e-6;
    const first = short ? (notes.find((n) => n.beat >= pickup - 1e-6 && onDownbeat(n)) ?? notes[0]) : null;
    const weighted = short ? notes.map((n) => ({ pitch: n.pitch, length: n.length * (onDownbeat(n) ? 2 : 1) })) : notes;
    const extra = [{ pitch: notes.at(-1).pitch, length: total / 6 }, ...(first ? [{ pitch: first.pitch, length: total / 6 }] : [])];
    const { tonic } = findKey([...weighted, ...extra]);
    const weights = new Array(12).fill(0);
    for (const n of notes) weights[((n.pitch % 12) + 12) % 12] += n.length;
    const minor = weights[(tonic + 3) % 12] > weights[(tonic + 4) % 12];
    return NAMES[tonic] + (minor ? 'm' : '');
}

const NAMES = ['C', 'C#', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B'];

function gaps(beats) {
    const out = [];
    for (let i = 1; i < beats.length; i++) out.push(beats[i] - beats[i - 1]);
    return out;
}

function median(values) {
    const sorted = [...values].sort((a, b) => a - b);
    const mid = sorted.length >> 1;
    return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
}
