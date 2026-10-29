// Beta takes on their way to Hillel (js/beta-outbox.js): a take is sent as from the song's start.
import { alignedTake } from '/js/beta-outbox.js';
import { assert, test } from './harness.js';

test('beta outbox: a take recorded from the start is sent as it is', () => {
    const take = new Float32Array([1, 2, 3]);
    assert(alignedTake(take, { offsetSeconds: 0, nudgeSeconds: 0, sampleRate: 48000 }) === take, 'unchanged');
});

test('beta outbox: a take recorded later is padded with silence, to the frame', () => {
    const out = alignedTake(new Float32Array([1, 2]), { offsetSeconds: 0.5, nudgeSeconds: 0.25, sampleRate: 8 });
    assert(out.length === 8 && out[5] === 0 && out[6] === 1 && out[7] === 2, Array.from(out).join());
});

test('beta outbox: a take nudged earlier loses what came before the start', () => {
    const out = alignedTake(new Float32Array([1, 2, 3, 4]), { offsetSeconds: 0, nudgeSeconds: -0.25, sampleRate: 8 });
    assert(out.length === 2 && out[0] === 3, Array.from(out).join());
});
