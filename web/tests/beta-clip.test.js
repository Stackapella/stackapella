// A course clip's check on My parts (js/beta-clip.js), on synthesized takes: a clear, long enough
// take is ready; silent, quiet, clipped and short ones say which.
import { checkClip } from '/js/beta-clip.js';
import { assert, test } from './harness.js';

const rate = 48000;
const tone = (seconds, amplitude) => {
    const out = new Float32Array(Math.round(seconds * rate));
    for (let i = 0; i < out.length; i++) out[i] = amplitude * Math.sin((2 * Math.PI * 220 * i) / rate);
    return out;
};

test('a clear clip sung for its length is ready to send', () => {
    const check = checkClip({ samples: tone(4, 0.3), sampleRate: rate, sungSeconds: 3 }, 3);
    assert(check.ready, `not ready: ${JSON.stringify(check)}`);
});

test('a silent clip is caught, and not called short', () => {
    const check = checkClip({ samples: new Float32Array(rate * 4), sampleRate: rate, sungSeconds: 0 }, 3);
    assert(!check.ready && check.silent && !check.short, JSON.stringify(check));
});

test('a very quiet clip is caught', () => {
    const check = checkClip({ samples: tone(4, 0.002), sampleRate: rate, sungSeconds: 3 }, 3);
    assert(!check.ready && (check.quiet || check.silent), JSON.stringify(check));
});

test('a clip that clips is caught', () => {
    const samples = tone(4, 1.4).map((x) => Math.max(-1, Math.min(1, x)));
    const check = checkClip({ samples, sampleRate: rate, sungSeconds: 3 }, 3);
    assert(!check.ready && check.clipping, JSON.stringify(check));
});

test('a clip sung for under most of its length is short', () => {
    const check = checkClip({ samples: tone(4, 0.3), sampleRate: rate, sungSeconds: 1 }, 3);
    assert(!check.ready && check.short, JSON.stringify(check));
});
