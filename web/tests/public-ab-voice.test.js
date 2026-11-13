// The Real voices A/B (js/public/ab-voice.js): one transport, the synth and the real voice crossfading
// in about 30 ms without a restart, and no real voice meaning the synth stays.
import { abVoice } from '/js/public/ab-voice.js';
import { audio } from '/js/public/voices.js';
import { assert, test } from './harness.js';

const SONG = { song: 'test-ab', version: 1, tempo: 120, beats: 4, parts: [{ key: 'soprano', notes: [{ beat: 0, length: 4, pitch: 72 }] }, { key: 'alto', notes: [{ beat: 0, length: 4, pitch: 67 }] }] };
const wait = (ms) => new Promise((r) => setTimeout(r, ms));
const near = (a, b) => a !== null && Math.abs(a - b) < 0.05;

/** The song's own answer and the catalogue (real voices) are the server's: here the song isn't one, and none has a voice. */
async function noCatalogue(body) {
    const fetched = window.fetch;
    window.fetch = (url, ...rest) => (String(url).includes('/api/templates') ? Promise.resolve(new Response(JSON.stringify({ data: [] }), { status: String(url).endsWith('/api/templates') ? 200 : 404, headers: { 'Content-Type': 'application/json' } })) : fetched(url, ...rest));
    try {
        return await body();
    } finally {
        window.fetch = fetched;
    }
}

test('real voices A/B: the synth first, then the real voice crossfaded in on the same transport', async () => {
    const rate = audio().sampleRate;
    const recording = new Float32Array(rate * 2).map((_, i) => 0.1 * Math.sin((2 * Math.PI * 392 * i) / rate));
    const ab = await abVoice({ song: SONG, part: 'alto', recording });
    assert(ab.hasReal && ab.kind === 'synth', 'ready, the synth first');
    const run = ab.start();
    assert(run.voices.length === 3 && run.length >= 1.9, `the synth, the real voice and the soprano: ${run.voices.length}, ${run.length} s`);
    await wait(200);
    let levels = ab.levels();
    assert(near(levels.synth, 1) && near(levels.real, 0), `synth heard: ${JSON.stringify(levels)}`);
    ab.use('real');
    await wait(150);
    levels = ab.levels();
    assert(ab.kind === 'real' && near(levels.synth, 0) && near(levels.real, 1), `real heard: ${JSON.stringify(levels)}`);
    run.voices.forEach((v) => v.stop());
});

test('real voices A/B: no real voice, so the synth sings alone and use(real) changes nothing', async () => {
    await noCatalogue(async () => {
        const ab = await abVoice({ song: SONG, part: 'soprano', with: 'none' });
        assert(!ab.hasReal, 'no real voice');
        ab.use('real');
        assert(ab.kind === 'synth', 'still the synth');
        const run = ab.start();
        assert(run.voices.length === 1, `the part alone: ${run.voices.length}`);
        run.voices.forEach((v) => v.stop());
    });
});
