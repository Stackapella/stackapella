// The timing check's measurement, shared by the app (js/audio.js checkTiming, which keeps the result
// for lining up takes) and the public latency test (js/public/tools/latency-test.js, which keeps nothing):
// play the click train, hear it back, find how late it came, a few times, and take the middle. No
// framework, so a public page can load it.

import { analyzeTrain, clickTrain, evenTrain, median } from './dsp.js';

/**
 * `measure(signal, captureLength)` plays `signal` and resolves to what the mic heard from that moment
 * (the engine's measure). Resolves to `{ frames, ms, spreadMs, runs }`, the median delay over the runs
 * that were heard, or null when none was.
 */
export async function measureLatency({ measure, sampleRate, runs = 3, onProgress = () => {} }) {
    const train = clickTrain(sampleRate);
    const maxLag = sampleRate; // up to a second, for Bluetooth
    const lags = [];
    for (let run = 0; run < runs; run++) {
        onProgress(run / runs);
        const heard = await measure(train.signal, train.signal.length + maxLag);
        const found = analyzeTrain(heard, train, { maxLag });
        if (found.reliable) lags.push(found.lagFrames);
    }
    onProgress(1);
    if (!lags.length) return null;
    const frames = Math.round(median(lags));
    return { frames, ms: (frames * 1000) / sampleRate, spreadMs: ((Math.max(...lags) - Math.min(...lags)) * 1000) / sampleRate, runs: lags.length };
}

// ---- Clap along (wireless): docs/wireless-timing-plan.md --------------------------------------------------------------
// Earbuds stop playing when they leave the ear, so the click test can't hear them. Instead the singer hears steady
// clicks in the earbuds and claps (or taps the table) on each; the phone's or laptop's own mic hears the claps. How late
// a clap comes after its click, as scheduled, is the whole round trip as the singer lives it: the click reaching the ear,
// the clap reaching the mic. People clap a little ahead of a beat they've locked onto (Repp 2005; Repp & Su 2013: a few
// tens of ms), so CLAP_LEAD_MS is added back. The first claps find the beat and are left out; the median of the rest is
// the delay, and a spread too wide asks for another go.

export const CLAP_GAP_SECONDS = 0.55; // between clicks: a short gap keeps the lead small (it grows with the gap)
export const CLAP_CLICKS = 12; // the first CLAP_LOCK_ON find the beat, then nine to clap on
export const CLAP_LOCK_ON = 3; // people lock on after two or three (Fraisse 1966)
export const CLAP_NEEDED = 6; // claps after the lock-on, at least
export const CLAP_LEAD_MS = 25; // the usual lead, added back (±20 ms; drummers about 20, singers and others about 50 at 800 ms)
export const CLAP_SPREAD_MS = 60; // the claps' middle half wider than this asks for another go, right on the click
const CLAP_MIN_LAG = 0.03; // no wireless round trip is shorter; claps are read from here to one gap past it
const CLAP_REFRACTORY = 0.15; // one clap's ring and echo aren't another clap
const BLOCK = 0.002; // the envelope's step, seconds

/** The clicks to clap along with: `{ signal, positions }`, positions in frames, one every CLAP_GAP_SECONDS. */
export function clapClicks(sampleRate, { count = CLAP_CLICKS, gap = CLAP_GAP_SECONDS } = {}) {
    return evenTrain(sampleRate, { count, intervalSeconds: gap });
}

/**
 * Where claps start in a recording (seconds): a block of the envelope rising well over the room's own level (the median
 * block) and over a third of the loud blocks' level, each clap once (CLAP_REFRACTORY), its start found where it first
 * passed half that.
 */
export function clapOnsets(samples, sampleRate) {
    const size = Math.max(1, Math.round(BLOCK * sampleRate));
    const blocks = new Float32Array(Math.floor(samples.length / size));
    for (let b = 0; b < blocks.length; b++) {
        let peak = 0;
        for (let i = b * size; i < (b + 1) * size; i++) peak = Math.max(peak, Math.abs(samples[i]));
        blocks[b] = peak;
    }
    const sorted = Float32Array.from(blocks).sort();
    const floor = sorted[Math.floor(sorted.length * 0.5)] ?? 0;
    const loud = sorted[Math.floor(sorted.length * 0.99)] ?? 0;
    const threshold = Math.max(0.01, floor * 6, loud * 0.3);
    const onsets = [];
    let quietUntil = -1;
    for (let b = 0; b < blocks.length; b++) {
        if (b < quietUntil || blocks[b] < threshold) continue;
        let start = b;
        while (start > 0 && blocks[start - 1] >= threshold / 2) start--;
        onsets.push((start * size) / sampleRate);
        quietUntil = b + Math.round(CLAP_REFRACTORY / BLOCK);
    }
    return onsets;
}

/**
 * The delay from claps (`onsets`, seconds) against clicks every `gap` from `first` (seconds): each clap after the
 * lock-on read as how far it came after the click before it (within CLAP_MIN_LAG to one gap past it, so a delay up to
 * 580 ms is read whole), the median plus the lead. Returns `{ ms, spreadMs, claps }`, or `{ ms: null, reason }` with
 * reason 'few' (under CLAP_NEEDED claps) or 'uneven' (their middle half wider than CLAP_SPREAD_MS).
 */
export function clapDelay(onsets, { first = 0, gap = CLAP_GAP_SECONDS, lockOn = CLAP_LOCK_ON, count = CLAP_CLICKS } = {}) {
    const from = first + lockOn * gap + CLAP_MIN_LAG;
    const to = first + (count - 1) * gap + CLAP_MIN_LAG + gap;
    const lags = onsets.filter((t) => t >= from && t < to).map((t) => ((((t - first - CLAP_MIN_LAG) % gap) + gap) % gap) + CLAP_MIN_LAG);
    if (lags.length < CLAP_NEEDED) return { ms: null, reason: 'few', claps: lags.length };
    const sorted = [...lags].sort((a, b) => a - b);
    const quartile = (q) => sorted[Math.min(sorted.length - 1, Math.floor(q * (sorted.length - 1) + 0.5))];
    const spreadMs = (quartile(0.75) - quartile(0.25)) * 1000;
    if (spreadMs > CLAP_SPREAD_MS) return { ms: null, reason: 'uneven', claps: lags.length, spreadMs };
    return { ms: median(lags) * 1000 + CLAP_LEAD_MS, spreadMs, claps: lags.length };
}

/**
 * Clap along: plays the clicks (`measure(signal, captureLength)` plays and resolves to what the mic heard from that
 * moment, as measureLatency's) and reads the claps. Resolves to `{ frames, ms, spreadMs, claps }`, or `{ frames: null,
 * reason }` ('few' or 'uneven').
 */
export async function measureClaps({ measure, sampleRate }) {
    const train = clapClicks(sampleRate);
    const heard = await measure(train.signal, train.signal.length + Math.round((CLAP_GAP_SECONDS + CLAP_MIN_LAG) * sampleRate));
    const found = clapDelay(clapOnsets(heard, sampleRate), { first: train.positions[0] / sampleRate });
    if (found.ms === null) return { frames: null, ...found };
    return { frames: Math.round((found.ms * sampleRate) / 1000), ms: found.ms, spreadMs: found.spreadMs, claps: found.claps };
}
