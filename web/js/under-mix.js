// Live looping (docs/courses/live-looping.md, "The engine piece"): earlier takes of this visit played
// beneath the step being sung (`exercise.under: { steps: [1, 3], loop: 8 }`, a loop per layer as
// `{ steps: [{ step: 1, loop: 4 }, { step: 3, loop: 32 }] }`, or one `{ step }` as before). A take's frame 0
// is its exercise's beat 0 (the engine records from beat 0's frame plus the measured round trip), so a
// layer starts at offset 0. With a loop (beats), a layer is cut to that stretch and repeated sample for
// sample to the end, a short fade at each seam so it never clicks; with none, it plays once. The layers
// fall off in loudness in the order listed, so your own voices never swamp the take being sung. The result
// is one buffer, played as one part from beat 0 on the audio clock (no timers), mixed the same way into
// Hear it together, and rendered out for Keep this as a song. Pure: tests/under-mix.test.js.

export const SEAM_SECONDS = 0.004; // the fade in and out at a loop's seam
export const LAYER_VOLUMES = [0.7, 0.55, 0.45]; // the first layer loudest; the third and any after at the last

/** `exercise.under`'s layers as `{ step, loopBeats }`, in the order listed (a bare step number or `{ step, loop }`). */
export function underEntries(under) {
    if (!under) return [];
    const entries = Array.isArray(under.steps) ? under.steps : under.step != null ? [under.step] : [];
    return entries.map((entry) => (typeof entry === 'object' && entry ? entry : { step: entry })).map((entry) => ({ step: entry.step, loopBeats: entry.loop ?? under.loop ?? null }));
}

/**
 * `exercise.under` read against this visit's takes (`takes`: step → { samples, sampleRate }): the layers,
 * each `{ samples, sampleRate, loopBeats, volume }`, in the order listed; a step with no take is left out.
 */
export function underLayers(under, takes) {
    const layers = underEntries(under)
        .map((entry) => ({ take: takes?.[entry.step], loopBeats: entry.loopBeats }))
        .filter(({ take }) => take?.samples?.length);
    return layers.map(({ take, loopBeats }, i) => ({ samples: take.samples, sampleRate: take.sampleRate, loopBeats, volume: LAYER_VOLUMES[Math.min(i, LAYER_VOLUMES.length - 1)] }));
}

/** The longest stretch the layers make (seconds): the longest loop, or a once-through layer's length. */
export function layersSeconds(layers, beatSeconds) {
    return Math.max(0, ...(layers ?? []).map((layer) => (layer.loopBeats > 0 ? layer.loopBeats * beatSeconds : layer.samples.length / layer.sampleRate)));
}

/** `samples` at `from` Hz, read at `to` Hz (linear), `frames` long (zeros past its end). */
function resampled(samples, from, to, frames) {
    const out = new Float32Array(frames);
    if (from === to) {
        out.set(samples.subarray(0, Math.min(frames, samples.length)));
        return out;
    }
    const ratio = from / to;
    for (let i = 0; i < frames; i++) {
        const at = i * ratio;
        const k = Math.floor(at);
        if (k + 1 >= samples.length) break;
        out[i] = samples[k] + (samples[k + 1] - samples[k]) * (at - k);
    }
    return out;
}

/**
 * The layers (`[{ samples, sampleRate, loopBeats, volume }]`, volume 1 when not given) mixed at
 * `sampleRate`: a looped layer cut to its `loopBeats` (`beatSeconds` long each) and repeated to `seconds`
 * (at least its loop), a once-through layer at its own length. Brought under full scale together when
 * they'd pass it. An empty list is an empty buffer.
 */
export function underMix(layers, { beatSeconds, sampleRate, seconds = 0 }) {
    const takes = (layers ?? []).filter((take) => take?.samples?.length && take.sampleRate > 0);
    if (!takes.length) return new Float32Array(0);
    const framesOf = (take) => (take.loopBeats > 0 ? Math.max(1, Math.round(take.loopBeats * beatSeconds * sampleRate)) : 0);
    const length = Math.max(Math.round(seconds * sampleRate), ...takes.map((take) => framesOf(take) || Math.round((take.samples.length * sampleRate) / take.sampleRate)));
    const mix = new Float32Array(length);
    for (const take of takes) {
        const volume = take.volume ?? 1;
        const loopFrames = framesOf(take);
        if (!loopFrames) {
            const once = resampled(take.samples, take.sampleRate, sampleRate, length);
            for (let i = 0; i < length; i++) mix[i] += once[i] * volume;
            continue;
        }
        const cell = resampled(take.samples, take.sampleRate, sampleRate, loopFrames);
        const seam = Math.min(Math.round(SEAM_SECONDS * sampleRate), Math.floor(loopFrames / 4));
        for (let i = 0; i < seam; i++) {
            const g = i / seam;
            cell[i] *= g;
            cell[loopFrames - 1 - i] *= g;
        }
        for (let at = 0; at < length; at += loopFrames) {
            const n = Math.min(loopFrames, length - at);
            for (let i = 0; i < n; i++) mix[at + i] += cell[i] * volume;
        }
    }
    const peak = mix.reduce((max, v) => Math.max(max, Math.abs(v)), 0);
    if (peak > 1) for (let i = 0; i < length; i++) mix[i] /= peak;
    return mix;
}
