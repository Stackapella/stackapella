// Renders guide parts off the main thread, so phones stay responsive while a song is prepared.
import { renderBacking, renderPart } from './synth.js';

self.onmessage = (event) => {
    const { id, notes, chords, options } = event.data;
    const samples = chords ? renderBacking(chords, options) : renderPart(notes, options);
    self.postMessage({ id, samples }, [samples.buffer]);
};
