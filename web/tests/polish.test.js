// Make it sound good (js/polish.js, feature 1): loudness measured to BS.1770, parts brought level, the
// mix at -14 LUFS with nothing above -1 dBFS, nothing moved in time, and the same render every time.
import { CEILING_DB, compress, impulseResponse, levelGains, limit, loudness, polishRender, TARGET_LUFS } from '/js/polish.js';
import { assert, close, test } from './harness.js';

const SR = 48000;
const tone = (seconds, hz, gain, from = 0) => new Float32Array(Math.round(seconds * SR)).map((_, i) => (i / SR >= from ? gain * Math.sin((2 * Math.PI * hz * i) / SR) : 0));
const peakDb = (channels) => 20 * Math.log10(Math.max(...channels.map((c) => c.reduce((m, v) => Math.max(m, Math.abs(v)), 0))));

test('polish: a 997 Hz tone at -23 dBFS in both channels measures -23 LUFS', () => {
    const level = 10 ** (-23 / 20);
    const c = tone(5, 997, level);
    close(loudness([c, c], SR), -23, 0.1, 'LUFS');
    assert(loudness([new Float32Array(SR * 2)], SR) === -Infinity, 'silence has no loudness');
});

test('polish: two parts at very different levels come out level, the melody a little forward', () => {
    const loud = { samples: tone(4, 330, 0.6), sampleRate: SR };
    const quiet = { samples: tone(4, 262, 0.03), sampleRate: SR };
    const [a, b] = levelGains([loud, quiet]);
    const after = (v, g) => loudness([v.samples.map((x) => x * g)], SR);
    close(after(loud, a) - after(quiet, b), 0, 0.5, 'level');
    const [m, o] = levelGains([{ ...loud, melody: true }, quiet]);
    close(after(loud, m) - after(quiet, o), 1.5, 0.3, 'the melody 1.5 dB forward');
});

test('polish: a mix comes out at -14 LUFS with no sample above -1 dBFS, the same every time', async () => {
    const voices = [
        { samples: tone(4, 392, 0.5, 0.5), sampleRate: SR, offsetSeconds: 0, volume: 1, pan: 0, audible: true, melody: true },
        { samples: tone(4, 330, 0.08, 0.5), sampleRate: SR, offsetSeconds: 0.25, volume: 1, pan: 0, audible: true },
        { samples: tone(4, 131, 0.9, 0.5), sampleRate: SR, offsetSeconds: 0, volume: 1, pan: 0, audible: true },
    ];
    for (const room of ['dry', 'small', 'chapel']) {
        const { channels } = await polishRender({ voices, lengthSeconds: 4.5, sampleRate: SR, room });
        close(loudness(channels, SR), TARGET_LUFS, 0.5, `${room}: LUFS`);
        assert(peakDb(channels) <= CEILING_DB + 1e-6, `${room}: peak ${peakDb(channels).toFixed(2)} dBFS`);
    }
    const one = await polishRender({ voices, lengthSeconds: 4.5, sampleRate: SR, room: 'hall' });
    const two = await polishRender({ voices, lengthSeconds: 4.5, sampleRate: SR, room: 'hall' });
    assert(one.channels.every((c, k) => c.every((v, i) => v === two.channels[k][i])), 'the same render, sample for sample');
});

test('polish: nothing moves in time: a part starts on its frame, and the room comes after', async () => {
    const click = new Float32Array(SR);
    click.fill(0.5, 4800, 4800 + 480); // a burst 0.1 s into the part
    const voices = [{ samples: click, sampleRate: SR, offsetSeconds: 0.5, volume: 1, pan: 0, audible: true }];
    for (const room of ['dry', 'hall']) {
        const { channels } = await polishRender({ voices, lengthSeconds: 1.5, sampleRate: SR, room });
        const first = channels[0].findIndex((v) => Math.abs(v) > 1e-4);
        assert(first === Math.round(0.6 * SR), `${room}: first sound at frame ${first}, not ${Math.round(0.6 * SR)}`);
    }
    const ir = impulseResponse('hall', SR);
    assert(ir[0].findIndex((v) => v !== 0) > 0.02 * SR, 'the room starts after a short gap');
});

test('polish: the limiter holds the ceiling and the compressor adds no delay', () => {
    const c = [tone(1, 220, 1.4), tone(1, 220, 1.4)];
    limit(c, SR, -1);
    assert(peakDb(c) <= -1 + 1e-6, `peak ${peakDb(c)}`);
    const squeezed = compress(tone(1, 220, 0.9), SR);
    const plain = tone(1, 220, 0.9);
    assert(Math.sign(squeezed[100]) === Math.sign(plain[100]) && squeezed.findIndex((v) => v !== 0) === plain.findIndex((v) => v !== 0), 'in step with the input');
    const held = (c) => 10 * Math.log10(c.subarray(SR / 2).reduce((sum, v) => sum + v * v, 0) / (SR / 2));
    assert(held(squeezed) < held(plain) - 1, `a loud held note is brought down: ${(held(squeezed) - held(plain)).toFixed(1)} dB`);
    const soft = tone(1, 220, 0.01);
    close(held(compress(soft, SR)), held(soft), 0.05, 'soft singing is left alone');
});
