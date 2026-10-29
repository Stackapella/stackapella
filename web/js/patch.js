// Re-sing a phrase (docs/more-features-plan.md, feature 11, with the join rules Hillel asked for):
// where the new phrase joins the take, how loud it should be, the splice itself, and whether it
// still sounds different. Pure functions over samples, unit-tested in tests/patch.test.js.
//
// - Joins go where the take is quietest: a rest around the phrase, else the quietest point between
//   two notes within a beat of the phrase's edge (a breath, a consonant). Never mid-note: when a
//   note is held right across the edge, the join moves out to the end of that note, and the singer
//   is told to sing on to (or start from) that word.
// - The new phrase is gain-matched to the take around it (within 6 dB), so moving nearer or further
//   from the mic doesn't jump in level.
// - A 20 ms equal-power crossfade at each join, at exact sample positions: the take is unchanged
//   outside the joins, and the new phrase is exactly in place inside them.

export const FADE_SECONDS = 0.02;
const FRAME_SECONDS = 0.01; // energy is measured in 10 ms frames
const MAX_GAIN_DB = 6;
const VOICED_DB = -45; // frames louder than this are singing, for the loudness match

/** RMS of samples[from, to), in dBFS (-Infinity for silence). */
function rmsDb(samples, from = 0, to = samples.length) {
    let sum = 0;
    const end = Math.min(to, samples.length);
    const start = Math.max(0, from);
    for (let i = start; i < end; i++) sum += samples[i] * samples[i];
    const n = Math.max(1, end - start);
    return sum > 0 ? 10 * Math.log10(sum / n) : -Infinity;
}

/** Energy (RMS, linear) in 10 ms frames: `{ frames, hop }`. */
export function energy(samples, sampleRate) {
    const hop = Math.max(1, Math.round(sampleRate * FRAME_SECONDS));
    const frames = new Float32Array(Math.ceil(samples.length / hop));
    for (let f = 0; f < frames.length; f++) {
        let sum = 0;
        const end = Math.min(samples.length, (f + 1) * hop);
        for (let i = f * hop; i < end; i++) sum += samples[i] * samples[i];
        frames[f] = Math.sqrt(sum / Math.max(1, end - f * hop));
    }
    return { frames, hop };
}

/**
 * Where to join, in project seconds, for the phrase [startSeconds, endSeconds) of a part whose
 * notes are `notes` ([{ start, end, lyric }] in project seconds, in order), on a take that starts
 * at `takeStartSeconds`. Returns `{ from, to, singFrom, singTo }`: `singFrom` / `singTo` name the
 * lyric to start at or sing on to when a join had to move past a held note (null when it didn't).
 */
export function joinPoints({ take, sampleRate, takeStartSeconds, notes, startSeconds, endSeconds, beatSeconds }) {
    const { frames, hop } = energy(take, sampleRate);
    const level = (seconds) => frames[Math.max(0, Math.min(frames.length - 1, Math.floor(((seconds - takeStartSeconds) * sampleRate) / hop)))] ?? 0;

    // The gaps between the part's notes: rests (and the breath points between legato notes).
    const gaps = [];
    for (let i = 0; i <= notes.length; i++) {
        const after = i > 0 ? notes[i - 1].end : -Infinity;
        const before = i < notes.length ? notes[i].start : Infinity;
        gaps.push({ from: after, to: before, rest: before - after >= 0.08 });
    }
    // The quietest moment inside a gap, a little either side of a boundary between touching notes
    // (where a singer breathes or a consonant falls), within the take.
    const quietest = (gap) => {
        const slack = gap.rest ? 0 : 0.06;
        const from = Math.max(Number.isFinite(gap.from) ? gap.from - slack : -Infinity, takeStartSeconds);
        const to = Math.min(Number.isFinite(gap.to) ? gap.to + slack : Infinity, takeStartSeconds + take.length / sampleRate);
        if (!(to > from)) return Number.isFinite(gap.from) ? gap.from : gap.to;
        let best = from;
        for (let t = from; t <= to; t += FRAME_SECONDS) if (level(t) < level(best)) best = t;
        return best;
    };
    const inside = (g) => Number.isFinite(g.from) && Number.isFinite(g.to);
    const pick = (edge, outward) => {
        const near = gaps.filter((g) => inside(g) && (outward < 0 ? g.from <= edge + 0.05 && g.to >= edge - beatSeconds : g.to >= edge - 0.05 && g.from <= edge + beatSeconds));
        const rests = near.filter((g) => g.rest).map((g) => ({ g, at: quietest(g) }));
        if (rests.length) {
            rests.sort((a, b) => Math.abs(a.at - edge) - Math.abs(b.at - edge));
            return { at: rests[0].at, moved: null };
        }
        const joins = near.map((g) => ({ g, at: quietest(g) }));
        if (joins.length) {
            // The quietest boundary; about as quiet (within 30%), the nearer to the edge.
            joins.sort((a, b) => {
                const la = level(a.at);
                const lb = level(b.at);
                return Math.abs(la - lb) <= 0.3 * Math.max(la, lb) ? Math.abs(a.at - edge) - Math.abs(b.at - edge) : la - lb;
            });
            return { at: joins[0].at, moved: null };
        }
        // A note held right across the edge: out to its end (or start), and that word to sing.
        const held = notes.find((n) => n.start < edge && n.end > edge);
        if (!held) return { at: edge, moved: null };
        const gap = gaps.find((g) => (outward < 0 ? g.to === held.start : g.from === held.end));
        const at = gap && inside(gap) ? quietest(gap) : outward < 0 ? held.start : held.end;
        const word = (held.lyric ?? [...notes].reverse().find((n) => n.start <= held.start && n.lyric)?.lyric ?? '').replace(/[-,.;:!?]+$/, '');
        return { at, moved: word || null };
    };
    const start = pick(startSeconds, -1);
    const end = pick(endSeconds, 1);
    return { from: start.at, to: end.at, singFrom: start.moved, singTo: end.moved };
}

function median(values) {
    if (!values.length) return 0;
    const sorted = [...values].sort((a, b) => a - b);
    return sorted[sorted.length >> 1];
}

/**
 * The gain (linear) that brings `patch`'s singing to the loudness of `around` (the take's singing
 * near the phrase), within 6 dB either way. Only frames that are singing count.
 */
export function matchGain(around, patch, sampleRate) {
    const loud = (samples) => {
        const { frames } = energy(samples, sampleRate);
        const voiced = Array.from(frames).filter((v) => v > 0 && 20 * Math.log10(v) > VOICED_DB);
        return voiced.length ? 20 * Math.log10(Math.sqrt(voiced.reduce((s, v) => s + v * v, 0) / voiced.length)) : null;
    };
    const a = loud(around);
    const p = loud(patch);
    if (a === null || p === null) return 1;
    return 10 ** (Math.max(-MAX_GAIN_DB, Math.min(MAX_GAIN_DB, a - p)) / 20);
}

/**
 * The take with [fromFrame, toFrame) replaced by the patch: `patch[i - patchStart]` is the take's
 * frame i. Equal-power crossfades of `fadeFrames`, centred on each join. Returns a new array the
 * take's length; the take itself is untouched.
 */
export function splice(take, patch, { fromFrame, toFrame, patchStart, gain = 1, fadeFrames }) {
    const out = Float32Array.from(take);
    const half = Math.floor(fadeFrames / 2);
    const p = (i) => {
        const j = i - patchStart;
        return j >= 0 && j < patch.length ? patch[j] * gain : 0;
    };
    const lo = Math.max(0, fromFrame - half);
    const hi = Math.min(take.length, toFrame + half);
    for (let i = lo; i < hi; i++) {
        let w; // the patch's weight, 0 to 1
        if (i < fromFrame + half) w = (i - (fromFrame - half) + 0.5) / fadeFrames;
        else if (i >= toFrame - half) w = 1 - (i - (toFrame - half) + 0.5) / fadeFrames;
        else w = 1;
        w = Math.max(0, Math.min(1, w));
        out[i] = take[i] * Math.cos((w * Math.PI) / 2) + p(i) * Math.sin((w * Math.PI) / 2);
    }
    return out;
}

/** High-frequency share (dB): a simple first difference against the signal, for tone. */
function tilt(samples) {
    let low = 0;
    let high = 0;
    for (let i = 1; i < samples.length; i++) {
        low += samples[i] * samples[i];
        const d = samples[i] - samples[i - 1];
        high += d * d;
    }
    return low > 0 && high > 0 ? 10 * Math.log10(high / low) : 0;
}

/** The room's noise: the quietest tenth of the frames, in dBFS. */
function noiseFloor(samples, sampleRate) {
    const { frames } = energy(samples, sampleRate);
    const sorted = Array.from(frames).sort((a, b) => a - b);
    const v = sorted[Math.floor(sorted.length * 0.1)] ?? 0;
    return v > 0 ? 20 * Math.log10(v) : -120;
}

/**
 * Whether the new phrase clearly sounds different from the take around it, in what gain can't fix:
 * the room's noise (more than 10 dB apart) or the tone (the balance of highs, more than 6 dB).
 */
export function soundsDifferent(around, patch, sampleRate) {
    const noise = Math.abs(noiseFloor(around, sampleRate) - noiseFloor(patch, sampleRate));
    const tone = Math.abs(tilt(around) - tilt(patch));
    return noise > 10 || tone > 6;
}

export { rmsDb };
