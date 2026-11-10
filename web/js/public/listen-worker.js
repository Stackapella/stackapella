// The site's live pitch reading off the main thread (live-mic.js): one window of mic samples in, the
// app's own tracker's reading out. A tool can widen the tracker's reach: an instrument's low strings
// (minHz, and a longer window) and high notes (maxHz), and its own quiet level (quietDb).
import { LISTEN_CLARITY } from '../listen.js';
import { trackFrame } from '../pitchtrack.js';

self.onmessage = (event) => {
    const { samples, sampleRate, at, minHz, maxHz, quietDb } = event.data;
    const reach = {
        ...(minHz ? { minHz } : {}),
        ...(maxHz ? { maxHz } : {}),
        ...(Number.isFinite(quietDb) ? { quietDb } : {}),
        ...(samples.length > 2048 ? { windowSeconds: samples.length / sampleRate } : {}),
    };
    self.postMessage({ at, ...trackFrame(samples, sampleRate, { minClarity: LISTEN_CLARITY, ...reach }) });
};
