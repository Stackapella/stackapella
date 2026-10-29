// Tracks a take's pitch off the main thread (pitchtrack.js, trackInWorker).
import { trackTake } from './pitchtrack.js';

self.onmessage = (event) => {
    const { samples, sampleRate } = event.data;
    const track = trackTake(samples, sampleRate);
    self.postMessage(track, [track.hz.buffer, track.clarity.buffer, track.loudness.buffer]);
};
