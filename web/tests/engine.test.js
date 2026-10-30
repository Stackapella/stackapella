// The engine over loopback: what is played goes straight into the recorder, so every timing must
// come out exact to the sample. Needs a browser that may start audio without a tap (tool/test.sh).
import { Engine } from '/engine/engine.js';
import { analyzeTrain, clickTrain } from '/js/dsp.js';
import { assert, close, test } from './harness.js';
import { FADE_SECONDS, trimmedSamples } from '/js/trim.js';
import { harmonyPart } from './fixtures.js';

const engine = new Engine();
const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function untilAudioTime(seconds) {
    while (engine.ctx.currentTime < seconds) {
        await pause(20);
    }
}

function worstDifference(actual, expected) {
    let worst = 0;
    for (let i = 0; i < expected.length; i++) {
        worst = Math.max(worst, Math.abs(actual[i] - expected[i]));
    }
    return worst;
}

const mix = { volume: 1, pan: 0, audible: true, offsetSeconds: 0 };

test('engine: starts, with the recorder fed by loopback', async () => {
    await engine.unlock();
    engine.setLoopback(true);
    assert(engine.ctx.state === 'running', engine.ctx.state);
});

test('engine: calibration over loopback finds a zero round trip', async () => {
    const sr = engine.sampleRate;
    const train = clickTrain(sr);
    const recording = await engine.measure(train.signal, { captureLength: train.signal.length + sr });
    const result = analyzeTrain(recording, train, { maxLag: sr });
    assert(result.detected === 8, `found ${result.detected} of 8`);
    close(result.lagFrames, 0, 0.01, 'lag');
});

test('engine: a take recorded over two parts equals their mix, sample for sample', async () => {
    const sr = engine.sampleRate;
    const a = harmonyPart(sr, { notes: [60, 65], bpm: 120 });
    const b = harmonyPart(sr, { notes: [64, 69], bpm: 120 });
    engine.setPart('a', a, sr, mix);
    engine.setPart('b', b, sr, { ...mix, volume: 0.5, offsetSeconds: 101 / sr });
    const playing = engine.play({ fromSeconds: 0, bpm: 120, countInBeats: 4, record: true, latencyFrames: 0 });
    await untilAudioTime(playing.startTime + 1.2);
    const take = (await engine.stop()).samples;
    assert(take.length > sr, `take is ${take.length} frames`);
    // Centre pan is equal-power: each side carries the part at cos(pi/4).
    const expected = new Float32Array(take.length).map((_, i) => ((a[i] ?? 0) + 0.5 * (b[i - 101] ?? 0)) * Math.SQRT1_2);
    close(worstDifference(take, expected), 0, 1e-5, 'worst sample difference');
    engine.removePart('a');
    engine.removePart('b');
});

test('engine: recording from a later position lines up with the project timeline', async () => {
    const sr = engine.sampleRate;
    const a = harmonyPart(sr, { notes: [60, 62, 64], bpm: 120 });
    engine.setPart('a', a, sr, { ...mix, offsetSeconds: 0.25 });
    const from = 0.75; // half a second into part a
    const playing = engine.play({ fromSeconds: from, bpm: 120, countInBeats: 2, record: true, latencyFrames: 0 });
    await untilAudioTime(playing.startTime + 1);
    const take = (await engine.stop()).samples;
    const skip = Math.round((from - 0.25) * sr);
    const expected = new Float32Array(take.length).map((_, i) => (a[i + skip] ?? 0) * Math.SQRT1_2);
    close(worstDifference(take, expected), 0, 1e-5, 'worst sample difference');
    engine.removePart('a');
});

test('engine: a measured latency shifts the take by exactly that many frames', async () => {
    const sr = engine.sampleRate;
    const a = harmonyPart(sr, { notes: [60, 67], bpm: 120 });
    engine.setPart('a', a, sr, mix);
    const playing = engine.play({ fromSeconds: 0, record: true, latencyFrames: 300 });
    await untilAudioTime(playing.startTime + 1);
    const take = (await engine.stop()).samples;
    // The recorder starts 300 frames late, so the take holds the part from frame 300 on.
    const expected = new Float32Array(take.length).map((_, i) => (a[i + 300] ?? 0) * Math.SQRT1_2);
    close(worstDifference(take, expected), 0, 1e-5, 'worst sample difference');
    engine.removePart('a');
});

test('engine: parts stay silent during the count-in', async () => {
    const sr = engine.sampleRate;
    engine.setPart('a', new Float32Array(sr * 3).fill(0.25), sr, mix);
    engine.clicks.accent = engine.toBuffer(new Float32Array(1), sr); // silent clicks, so only parts could sound
    engine.clicks.normal = engine.clicks.accent;
    const playing = engine.play({ fromSeconds: 1, bpm: 120, countInBeats: 4 });
    const countIn = await engine.startCapture(Math.round(playing.countInStart * sr), Math.round(playing.startTime * sr), null);
    await engine.stop();
    assert(countIn.samples.every((v) => v === 0), 'a part sounded during the count-in');
    engine.removePart('a');
});

test('engine: a loop plays its stretch end to end, every part in step, sample for sample', async () => {
    const sr = engine.sampleRate;
    const a = harmonyPart(sr, { notes: [60, 64, 67], bpm: 120 });
    const b = harmonyPart(sr, { notes: [55, 59], bpm: 120 });
    engine.setPart('a', a, sr, mix);
    engine.setPart('b', b, sr, { ...mix, offsetSeconds: 0.5 }); // starts partway into the loop
    const loop = { startSeconds: 0.25, endSeconds: 0.875 };
    const playing = engine.play({ loop });
    const loopFrames = Math.round(loop.endSeconds * sr) - Math.round(loop.startSeconds * sr);
    const start = Math.round(playing.startTime * sr);
    const captured = await engine.startCapture(start, start + 2 * loopFrames, null);
    await engine.stop();
    const from = Math.round(loop.startSeconds * sr);
    const once = Float32Array.from({ length: loopFrames }, (_, i) => ((a[from + i] ?? 0) + (b[from + i - Math.round(0.5 * sr)] ?? 0)) * Math.SQRT1_2);
    close(worstDifference(captured.samples.subarray(0, loopFrames), once), 0, 1e-5, 'first time round');
    close(worstDifference(captured.samples.subarray(loopFrames), once), 0, 1e-5, 'second time round');
    engine.removePart('a');
    engine.removePart('b');
});

test('engine: a run started at a loop\'s end takes over there, sample for sample, the pass before it played out (the tempo ladder)', async () => {
    const sr = engine.sampleRate;
    const slow = harmonyPart(sr, { notes: [60, 64], bpm: 100 });
    const fast = harmonyPart(sr, { notes: [62, 65], bpm: 120 });
    engine.setPart('a', slow, sr, mix);
    const before = { startSeconds: 0.25, endSeconds: 1.0 };
    const after = { startSeconds: 0.2, endSeconds: 0.8 }; // the same phrase, a faster speed: shorter
    try {
        const playing = engine.play({ loop: before });
        const start = Math.round(playing.startTime * sr);
        const passFrames = Math.round(before.endSeconds * sr) - Math.round(before.startSeconds * sr);
        const nextFrames = Math.round(after.endSeconds * sr) - Math.round(after.startSeconds * sr);
        const capturing = engine.startCapture(start, start + passFrames + nextFrames, null);
        await untilAudioTime(playing.startTime + 0.1);
        const end = engine.passEnd();
        assert(end === start + passFrames, `the first pass ends at ${start + passFrames}, not ${end}`);
        // The faster guide in place of the slow one; the pass heard now keeps its own (already scheduled).
        assert(engine.replaceBuffer('a', fast, sr), 'the part is there to replace');
        engine.play({ loop: after, startFrame: end });
        const captured = (await capturing).samples;
        const was = Math.round(before.startSeconds * sr);
        const now = Math.round(after.startSeconds * sr);
        const first = Float32Array.from({ length: passFrames }, (_, i) => slow[was + i] * Math.SQRT1_2);
        const second = Float32Array.from({ length: nextFrames }, (_, i) => fast[now + i] * Math.SQRT1_2);
        close(worstDifference(captured.subarray(0, passFrames), first), 0, 1e-5, 'the slow pass, to its end');
        close(worstDifference(captured.subarray(passFrames, passFrames + nextFrames), second), 0, 1e-5, 'the fast pass from the next frame, nothing doubled');
    } finally {
        await engine.stop();
        engine.removePart('a');
    }
});

test('engine: listen, then repeat plays the phrase, a cue beat, then your turn without your guide', async () => {
    const sr = engine.sampleRate;
    const guide = harmonyPart(sr, { notes: [64, 65], bpm: 120 });
    const other = harmonyPart(sr, { notes: [57, 59], bpm: 120 });
    engine.setPart('g', guide, sr, mix);
    engine.setPart('o', other, sr, mix);
    const phrase = { startSeconds: 0.5, endSeconds: 1.25 };
    const playing = engine.play({ bpm: 120, repeat: { phrases: [phrase], mute: ['g'], cueBeats: 1 } });
    const len = Math.round(phrase.endSeconds * sr) - Math.round(phrase.startSeconds * sr);
    const cue = Math.round(sr / 2); // one beat at 120 bpm
    const start = Math.round(playing.startTime * sr);
    const captured = (await engine.startCapture(start, start + 2 * len + cue, null)).samples;
    // Wait for the run to finish, then check the engine says so.
    while (!engine.positionInfo()?.done) await pause(50);
    await engine.stop();
    const from = Math.round(phrase.startSeconds * sr);
    const both = Float32Array.from({ length: len }, (_, i) => (guide[from + i] + other[from + i]) * Math.SQRT1_2);
    const otherOnly = Float32Array.from({ length: len }, (_, i) => other[from + i] * Math.SQRT1_2);
    close(worstDifference(captured.subarray(0, len), both), 0, 1e-5, 'the phrase, everyone');
    close(worstDifference(captured.subarray(len, len + cue), new Float32Array(cue)), 0, 1e-6, 'the cue beat (silent clicks in this test)');
    close(worstDifference(captured.subarray(len + cue), otherOnly), 0, 1e-5, 'your turn, without your guide');
    engine.removePart('g');
    engine.removePart('o');
});

test('engine: the position follows the segments, with the turn phase reported', async () => {
    const sr = engine.sampleRate;
    engine.setPart('g', new Float32Array(sr), sr, mix);
    const playing = engine.play({ bpm: 120, repeat: { phrases: [{ startSeconds: 0.2, endSeconds: 0.6 }], mute: ['g'], cueBeats: 1, cycle: true } });
    const phases = new Set();
    while (engine.ctx.currentTime < playing.startTime + 1.8) {
        const info = engine.positionInfo();
        if (info.phase !== 'count-in') {
            phases.add(info.phase);
            assert(info.position >= 0.2 - 1e-6 && info.position <= 0.6 + 1e-6, `position ${info.position} outside the phrase`);
        }
        await pause(15);
    }
    await engine.stop();
    assert(phases.has('listen') && phases.has('cue') && phases.has('turn'), [...phases].join());
    engine.removePart('g');
});

test('engine: the export renders the audible parts in stereo, panned', async () => {
    const sr = engine.sampleRate;
    const a = harmonyPart(sr, { notes: [60], bpm: 120 });
    engine.setPart('a', a, sr, mix);
    engine.setPart('b', a, sr, mix);
    const rendered = await engine.render([{ id: 'a', volume: 1, pan: -1, audible: true }, { id: 'b', volume: 1, pan: 0, audible: false }], a.length / sr);
    const [left, right] = rendered.channels;
    close(worstDifference(left, a), 0, 1e-5, 'left is part a');
    close(worstDifference(right, new Float32Array(a.length)), 0, 1e-6, 'right is silent');
    engine.removePart('a');
    engine.removePart('b');
});

test('engine: a trimmed part plays and renders only its kept stretch, at its own time, to the frame', async () => {
    const sr = engine.sampleRate;
    const a = harmonyPart(sr, { notes: [60, 64, 67], bpm: 120 });
    const offset = 0.25;
    const trims = { start: 4801, end: 9599 };
    // As the session places it: the kept stretch, from where it always sat (js/stores/session.js voiceMix).
    engine.setPart('a', trimmedSamples(a, sr, trims), sr, { ...mix, offsetSeconds: offset + trims.start / sr });
    const lengthSeconds = offset + a.length / sr + 0.1;
    const [left] = (await engine.render([{ id: 'a', volume: 1, pan: -1, audible: true }], lengthSeconds)).channels;
    const at = Math.round(offset * sr);
    const fade = Math.round(FADE_SECONDS * sr);
    const first = at + trims.start;
    const last = at + a.length - trims.end; // one past the last kept frame
    assert(left.subarray(0, first).every((v) => v === 0), 'silent before the start trim');
    assert(left.subarray(last).every((v) => Math.abs(v) < 1e-7), 'silent after the end trim');
    const middle = left.subarray(first + fade, last - fade);
    const expected = a.subarray(trims.start + fade, a.length - trims.end - fade);
    close(worstDifference(middle, expected), 0, 1e-5, 'the kept stretch is the take, where it was');
    // Playback over loopback: the same frames, from a later position.
    const playing = engine.play({ fromSeconds: 0, bpm: 120, countInBeats: 2, record: true, latencyFrames: 0 });
    await untilAudioTime(playing.startTime + lengthSeconds + 0.1);
    const take = (await engine.stop()).samples;
    close(worstDifference(take.subarray(first + fade, last - fade), expected.map((v) => v * Math.SQRT1_2)), 0, 1e-5, 'played the same, sample for sample');
    assert(take.subarray(0, first).every((v) => Math.abs(v) < 1e-7), 'played nothing before the trim');
    engine.removePart('a');
});

// ---- M0: real devices (docs/roadmap.md): the mic's settle, interruptions, a new sample rate ----

test('engine: a longer lead places the first click that far ahead on the audio clock', async () => {
    const before = engine.ctx.currentTime;
    const playing = engine.play({ bpm: 120, countInBeats: 2, leadSeconds: 0.8 });
    await engine.stop();
    assert(playing.countInStart - before >= 0.8 - 1e-3, `first click ${playing.countInStart - before} s ahead`);
});

test('engine: an interruption mid-take keeps the take up to it, still exact to the sample', async () => {
    const sr = engine.sampleRate;
    const a = harmonyPart(sr, { notes: [60, 64, 67, 72], bpm: 120 });
    engine.setPart('a', a, sr, mix);
    const reasons = [];
    engine.onInterrupt = (reason) => reasons.push(reason);
    const playing = engine.play({ fromSeconds: 0, record: true, latencyFrames: 0 });
    await untilAudioTime(playing.startTime + 0.8);
    await engine.ctx.suspend(); // what a call or a locked screen does to the context
    await pause(50);
    const take = await engine.stop();
    await engine.ctx.resume();
    engine.onInterrupt = null;
    assert(reasons.includes('audio'), `told the app: ${reasons}`);
    assert(take && take.interrupted, 'the take is marked interrupted');
    assert(take.samples.length >= 0.5 * sr && take.samples.length <= 1.2 * sr, `kept ${take.samples.length / sr} s`);
    const expected = new Float32Array(take.samples.length).map((_, i) => (a[i] ?? 0) * Math.SQRT1_2);
    close(worstDifference(take.samples, expected), 0, 1e-5, 'worst sample difference');
    engine.removePart('a');
});

test('engine: a stop already waiting when the context stops still returns the take', async () => {
    const sr = engine.sampleRate;
    engine.setPart('a', harmonyPart(sr, { notes: [62, 65], bpm: 120 }), sr, mix);
    const playing = engine.play({ fromSeconds: 0, record: true, latencyFrames: 0 });
    await untilAudioTime(playing.startTime + 0.5);
    const stopping = engine.stop();
    await engine.ctx.suspend();
    const take = await Promise.race([stopping, pause(3000).then(() => 'hung')]);
    await engine.ctx.resume();
    assert(take !== 'hung', 'stop() never returned');
    assert(take.samples.length > 0, 'the take is empty');
    engine.removePart('a');
});

test('engine: the mic ending mid-take (unplugged, AirPods away) tells the app', async () => {
    await engine.openMic();
    const reasons = [];
    engine.onInterrupt = (reason) => reasons.push(reason);
    const playing = engine.play({ fromSeconds: 0, record: true, latencyFrames: 0 });
    await untilAudioTime(playing.startTime + 0.2);
    engine.mic.stream.getAudioTracks()[0].dispatchEvent(new Event('ended'));
    const take = await engine.stop();
    engine.onInterrupt = null;
    engine.closeMic();
    assert(reasons.includes('mic'), `told the app: ${reasons}`);
    assert(take.samples.length > 0, 'the take is empty');
});

test('engine: an interruption during the timing check stops it, instead of measuring half a run', async () => {
    const sr = engine.sampleRate;
    const train = clickTrain(sr);
    const measuring = engine.measure(train.signal, { captureLength: train.signal.length + sr });
    await pause(400);
    await engine.ctx.suspend();
    let error = null;
    try {
        await Promise.race([measuring, pause(3000).then(() => { throw new Error('hung'); })]);
    } catch (thrown) {
        error = thrown;
    }
    await engine.ctx.resume();
    assert(error?.name === 'AbortError', `got ${error}`);
});

test('engine: two mic opens at once share one mic, and closing while the browser asks closes it', async () => {
    const [a, b] = await Promise.all([engine.openMic(), engine.openMic()]);
    assert(a === b && engine.mic === a, 'one mic');
    engine.closeMic();
    const opening = engine.openMic();
    engine.closeMic(); // the person left while the permission prompt was up
    let error = null;
    try {
        await opening;
    } catch (thrown) {
        error = thrown;
    }
    assert(error?.name === 'AbortError', `got ${error}`);
    assert(engine.mic === null, 'no mic left open');
});

test('engine: a rebuild that fails keeps the old context and every part', async () => {
    const sr = engine.sampleRate;
    const ctx = engine.ctx;
    engine.setPart('a', harmonyPart(sr, { notes: [60], bpm: 120 }), sr, mix);
    let failed = false;
    try {
        await engine.rebuild(1); // no browser makes a context at 1 Hz
    } catch {
        failed = true;
    }
    assert(failed, 'the rebuild should fail');
    assert(engine.ctx === ctx && engine.ctx.state === 'running', 'the old context is still in use');
    assert(engine.parts.has('a'), 'the part is still there');
    const playing = engine.play({ fromSeconds: 0, record: true, latencyFrames: 0 });
    await untilAudioTime(playing.startTime + 0.3);
    const take = await engine.stop();
    assert(take.samples.length > 0, 'it still records');
    engine.removePart('a');
});

test('engine: rebuilding at another sample rate keeps the parts, the mic and loopback, and timing stays exact', async () => {
    const from = engine.sampleRate;
    const to = from === 48000 ? 44100 : 48000;
    engine.setPart('a', harmonyPart(from, { notes: [60], bpm: 120 }), from, { ...mix, volume: 0.7, pan: -0.5, offsetSeconds: 0.25 });
    await engine.openMic();
    await engine.rebuild(to);
    assert(engine.sampleRate === to, `rate ${engine.sampleRate}`);
    const part = engine.parts.get('a');
    assert(part && part.offsetSeconds === 0.25 && part.mix.volume === 0.7 && part.mix.pan === -0.5, JSON.stringify(part?.mix));
    assert(engine.mic.source.context === engine.ctx, 'the mic was not moved to the new context');
    engine.removePart('a');
    engine.closeMic();
    const train = clickTrain(to);
    const recording = await engine.measure(train.signal, { captureLength: train.signal.length + to });
    const result = analyzeTrain(recording, train, { maxLag: to });
    assert(result.detected === 8, `found ${result.detected} of 8`);
    close(result.lagFrames, 0, 0.01, 'lag at the new rate');
    await engine.rebuild(from);
});
