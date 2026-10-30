// An idea's voice lined up from the tune's beat zero (components/idea-voice.js, ideaTake): the
// take's offset and nudge become offsetFrames; one that starts before beat zero is cut there.
import { assert, test } from './harness.js';
import { ideaTake } from '../js/components/idea-voice.js';

test('ideas: a voice keeps its offset and nudge as frames from beat zero', () => {
    const samples = new Float32Array(1000);
    const { samples: kept, offsetFrames } = ideaTake(samples, { offsetSeconds: 0.5, nudgeSeconds: 0.01, sampleRate: 1000 });
    assert(offsetFrames === 510, `510 frames in, got ${offsetFrames}`);
    assert(kept === samples, 'the take as it is');
});

test('ideas: a voice that starts before beat zero is cut there', () => {
    const samples = new Float32Array(1000).map((_, i) => i);
    const { samples: kept, offsetFrames } = ideaTake(samples, { offsetSeconds: -0.6, nudgeSeconds: 0.1, sampleRate: 1000 });
    assert(offsetFrames === 0, 'from beat zero');
    assert(kept.length === 500 && kept[0] === 500, `the first 500 frames cut, got ${kept.length} from ${kept[0]}`);
});
