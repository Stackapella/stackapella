// A voice's breaths softened off the main thread (js/soften-breaths.js asks; js/breaths.js does the work): a part that
// comes in late has one long gap before its first note, half a second of work on a slow phone, which would hold the
// page still if it ran there. The samples come and go as their own buffer, never copied twice.
import { duckBreaths } from './breaths.js';

self.onmessage = ({ data }) => {
    const { id, samples, rate, windows } = data;
    duckBreaths(samples, rate, windows);
    self.postMessage({ id, samples }, [samples.buffer]);
};
