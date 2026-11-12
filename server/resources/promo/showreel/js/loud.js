// How loud each real voice is at any beat (0 to 1), for v5's lanes: a lane that has joined glows with its
// own singer's loudness, so the picture breathes with the sound. Read once from the same recordings the
// soundtrack mixes (voices/, render.py --voices): decoded, the encoder's priming skipped as audio.js
// skips it, an RMS every 20 ms, each part scaled to its own loud singing. Pure after loading.

import { JOIN_LEAD, JOIN_RAMP, PARTS, S, SPB, VERSE, joinAt, noteAt } from './score.js';

const RATE = 48000, HOP = 0.02, PRIMING_FRAMES = 1024;
// a voice is singing once its loudness, as the mix lets it in, crosses this (of its own loud singing)
export const ONSET = 0.3;
let env = null;
const onsets = [];

export async function loadLoud() {
    if (!S.voices) return;
    const ctx = new OfflineAudioContext(1, 1, RATE);
    env = await Promise.all(PARTS.map(async (key) => {
        const v = S.voices[key];
        const d = (await ctx.decodeAudioData(await (await fetch(`voices/${key}.m4a`)).arrayBuffer())).getChannelData(0);
        const scale = RATE / v.sampleRate, priming = Math.round(PRIMING_FRAMES * scale), expected = Math.round(v.frames * scale);
        const start = d.length >= expected + priming ? priming : 0, hop = Math.round(HOP * RATE);
        const out = new Float32Array(Math.ceil(expected / hop));
        for (let k = 0; k < out.length; k++) {
            let s = 0, n = 0;
            for (let i = start + k * hop; i < Math.min(d.length, start + (k + 1) * hop); i++) { s += d[i] * d[i]; n++; }
            out[k] = Math.sqrt(s / Math.max(1, n));
        }
        // scaled so this singer's loud singing (the 90th percentile) is 1
        const sorted = [...out].sort((a, b) => a - b), top = sorted[Math.floor(sorted.length * 0.9)] || 1;
        for (let k = 0; k < out.length; k++) out[k] = Math.min(1, out[k] / top);
        return { out, offset: v.offsetFrames / v.sampleRate };
    }));
}

/** Part `i`'s loudness at song beat `beat` (either time through the verse), smoothed over 60 ms. */
export function loud(i, beat) {
    // without recordings (the carols cut's guide voice): each note sounds, and settles
    if (!env) { const n = noteAt(S.parts[i], beat); return n ? 0.45 + 0.45 * Math.exp(-(beat - n.beat) * 0.9) : 0.06; }
    const b = beat >= VERSE ? beat - VERSE : beat, e = env[i], k = Math.round((b * SPB - e.offset) / HOP);
    return smooth(e, k);
}

const smooth = (e, k) => { let s = 0; for (let j = k - 1; j <= k + 1; j++) s += e.out[Math.max(0, Math.min(e.out.length - 1, j))] ?? 0; return s / 3; };

/**
 * When part `i` is first heard singing after it joins, in song seconds: the first 20 ms frame where its
 * loudness, faded in by the soundtrack's gate (score.js joinAt), crosses ONSET. Where the singer is
 * already mid-note when the gate opens (the social's bass), that is the gate itself. Without the voices,
 * the first note.
 */
export function onsetAt(i) {
    if (onsets[i] != null) return onsets[i];
    const g = joinAt(i);
    if (!env) return g + JOIN_LEAD;
    const e = env[i];
    for (let k = Math.max(0, Math.floor((g - e.offset) / HOP)); k < e.out.length; k++) {
        const t = k * HOP + e.offset, gate = Math.min(1, Math.max(0, (t - g) / JOIN_RAMP));
        if (smooth(e, k) * gate >= ONSET) return (onsets[i] = t);
    }
    return (onsets[i] = g + JOIN_LEAD);
}
