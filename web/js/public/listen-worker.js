// The site's live pitch reading off the main thread (listen.js): one window of mic samples in, the
// app's own tracker's reading out.
import { LISTEN_CLARITY } from '../listen.js';
import { trackFrame } from '../pitchtrack.js';

self.onmessage = (event) => {
    const { samples, sampleRate, at } = event.data;
    self.postMessage({ at, ...trackFrame(samples, sampleRate, { minClarity: LISTEN_CLARITY }) }); // a laptop mic, as Find your note hears it
};
