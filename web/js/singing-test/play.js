// The singing test's turns (docs/singing-test-v1.md): a prompt in the practice synth (js/synth.js, the same voice and
// tempo every time in this test version), then the singer's answer, captured from the prompt's end with nothing else
// sounding, whatever they listen through. Everything that must line up is scheduled on the AudioContext clock: the
// prompt starts at a context time, and the capture is given in context frames (the recorder worklet's own), shifted
// by the measured round trip (audio.latencyFrames) so the answer is read when the singer actually sang it.

import { audio, engine, unlock } from '../audio.js';
import { trackInWorker } from '../pitchtrack.js';
import { renderPart } from '../synth.js';

/** Captured before the prompt's end too, so an answer that starts early shows as early (the boards' check). */
export const LEAD_SECONDS = 0.3;
/** The prompt starts this long after it's asked for (time to schedule it on the clock). */
const START_AFTER = 0.08;

/** A prompt's sound: its notes (MIDI) one after another, each `noteSeconds` long, in the practice synth. */
export function cueSamples(notes, noteSeconds, sampleRate) {
    return renderPart(notes.map((pitch, i) => ({ beat: i, length: 1, pitch })), { bpm: 60 / noteSeconds, sampleRate });
}

/**
 * One turn: the cue (its `notes`, possibly none), then `answerSeconds` of the singer's answer. Resolves with
 * `{ track, promptEnd }`: the answer's pitch track (js/pitchtrack.js) and where in it the prompt ended (seconds), for
 * js/singing-test/measure.js. `onAnswer(contextTime)` is told when the answer's time begins (the screen's "your turn").
 * Nothing else plays during a turn: whatever was playing stops first.
 */
export async function turn(notes, { noteSeconds = 1, answerSeconds, onAnswer = () => {} } = {}) {
    await unlock();
    await engine.stop();
    const ctx = engine.ctx;
    const rate = ctx.sampleRate;
    const start = ctx.currentTime + START_AFTER;
    let source = null;
    let promptEnd = start;
    if (notes.length) {
        const samples = cueSamples(notes, noteSeconds, rate);
        source = ctx.createBufferSource();
        source.buffer = engine.toBuffer(samples, rate);
        source.connect(engine.playBus);
        source.start(start);
        promptEnd = start + samples.length / rate;
    }
    onAnswer(promptEnd);
    const lead = Math.round(LEAD_SECONDS * rate);
    const latency = audio.latencyFrames ?? engine.estimatedLatencyFrames() ?? 0;
    const fromFrame = Math.round(promptEnd * rate) + latency - lead;
    const take = await engine.startCapture(fromFrame, fromFrame + lead + Math.round(answerSeconds * rate));
    const track = await trackInWorker(take.samples, take.sampleRate);
    return { track, promptEnd: LEAD_SECONDS, interrupted: take.interrupted };
}

/** Stops a turn under way (leaving the test, or a sound interrupted): nothing is kept. */
export function cancelTurn() {
    engine.cancelCapture();
    engine.stopSources();
}
