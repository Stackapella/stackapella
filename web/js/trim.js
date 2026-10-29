// Trimming a take's start and end (docs/more-features-plan.md, feature 17): never the recording
// itself, only two numbers on the part (`trimStartFrames`, `trimEndFrames`, at the take's rate). The
// engine plays the kept stretch with a 10 ms fade at each cut edge, placed so the singing keeps its
// time against the others. "Trim the silence" works from the take's loudness outline (its peaks),
// and "Line up the ends" gives every part the same start or end.

export const FADE_SECONDS = 0.01;
export const ROOM_SECONDS = 0.1; // kept before the first and after the last sung sound

/** A part's trims, never overlapping, never past the take. */
export function trimsOf(part) {
    const frames = part.frames ?? 0;
    const start = Math.max(0, Math.min(frames, Math.round(part.trimStartFrames ?? 0)));
    const end = Math.max(0, Math.min(frames - start, Math.round(part.trimEndFrames ?? 0)));
    return { start, end };
}

/**
 * The kept stretch of a take, faded in and out over 10 ms where it was cut (an uncut edge plays
 * exactly as recorded). A copy: the take itself is never changed.
 */
export function trimmedSamples(samples, sampleRate, { start, end }) {
    if (!start && !end) return samples;
    const kept = samples.slice(start, Math.max(start, samples.length - end));
    const fade = Math.min(Math.round(FADE_SECONDS * sampleRate), Math.floor(kept.length / 2));
    for (let i = 0; i < fade; i++) {
        const gain = i / fade;
        if (start) kept[i] *= gain;
        if (end) kept[kept.length - 1 - i] *= gain;
    }
    return kept;
}

/** Where the part sounds on the project's timeline, in seconds: its first and last kept moments. */
export function heardSpan(part) {
    const { start, end } = trimsOf(part);
    const at = (part.offsetSeconds ?? 0) + (part.nudgeSeconds ?? 0);
    return { from: at + start / part.sampleRate, to: at + (part.frames - end) / part.sampleRate };
}

/**
 * Where the singing starts and stops in a take, in frames, from its peaks: the first and last
 * outline buckets above a threshold set by the take's own level (a soft singer's soft start counts;
 * breaths and room noise don't). Null for a take with nothing sung.
 */
export function sungSpan(peaks, framesPerPeak, frames) {
    if (!peaks?.length) return null;
    const sorted = [...peaks].sort((a, b) => a - b);
    const level = sorted[Math.floor(sorted.length * 0.95)];
    const floor = sorted[Math.floor(sorted.length * 0.1)];
    if (level < 0.01 || level < floor * 4) return null; // silence, or noise all through
    const threshold = Math.max(floor * 3, level * 0.12);
    const first = peaks.findIndex((p) => p >= threshold);
    let last = peaks.length - 1;
    while (last > first && peaks[last] < threshold) last--;
    return { start: first * framesPerPeak, end: Math.min(frames, (last + 1) * framesPerPeak) };
}

/** "Trim the silence": the trims that keep the singing with a little room either side. */
export function silenceTrims(part) {
    const sung = sungSpan(part.peaks, part.framesPerPeak ?? 512, part.frames);
    if (!sung) return null;
    const room = Math.round(ROOM_SECONDS * part.sampleRate);
    return { start: Math.max(0, sung.start - room), end: Math.max(0, part.frames - Math.min(part.frames, sung.end + room)) };
}

/** A project time moved to the nearest beat (Snap to the beat). */
export function snap(seconds, beatSeconds) {
    return beatSeconds > 0 ? Math.round(seconds / beatSeconds) * beatSeconds : seconds;
}

/** The trims that make a part start (`from`) or end (`to`) at a project time, within its take. */
export function trimsTo(part, { from = null, to = null }) {
    const { start, end } = trimsOf(part);
    const at = (part.offsetSeconds ?? 0) + (part.nudgeSeconds ?? 0);
    const frame = (seconds) => Math.round((seconds - at) * part.sampleRate);
    const next = { start, end };
    // A take that starts after `from` (or ends before `to`) keeps its own edge, trimmed or not.
    if (from !== null && frame(from) > 0) next.start = Math.min(part.frames - end, frame(from));
    if (to !== null && frame(to) < part.frames) next.end = Math.max(0, Math.min(part.frames - next.start, part.frames - frame(to)));
    return next;
}

/**
 * "Line up the ends" for the whole project: 'start' starts every part where the first singing
 * starts (on a beat at or before it, when there's a tempo); 'end' ends every part where the song
 * ends (the template's last note, or else the last singing, on a beat at or after it). A part that
 * begins later or ends sooner keeps its own edge: trimming never adds. Returns part id -> trims.
 */
export function lineUp(parts, mode, { beatSeconds = 0, songEnd = null } = {}) {
    const recorded = parts.filter((p) => p.frames > 0);
    const sung = recorded
        .map((p) => {
            const span = sungSpan(p.peaks, p.framesPerPeak ?? 512, p.frames);
            const at = (p.offsetSeconds ?? 0) + (p.nudgeSeconds ?? 0);
            return span && { from: at + span.start / p.sampleRate, to: at + span.end / p.sampleRate };
        })
        .filter(Boolean);
    if (!sung.length) return new Map();
    const down = (s) => (beatSeconds > 0 ? Math.floor(s / beatSeconds + 1e-9) * beatSeconds : s);
    const up = (s) => (beatSeconds > 0 ? Math.ceil(s / beatSeconds - 1e-9) * beatSeconds : s);
    const target = mode === 'start' ? Math.max(0, down(Math.min(...sung.map((s) => s.from)) - ROOM_SECONDS / 2)) : songEnd ?? up(Math.max(...sung.map((s) => s.to)));
    return new Map(recorded.map((p) => [p.id, trimsTo(p, mode === 'start' ? { from: target } : { to: target })]));
}
