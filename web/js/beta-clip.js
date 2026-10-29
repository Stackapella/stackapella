// A course clip, recorded on My parts (docs/plus-courses-build-plan.md, section 8: the clips a real
// singer records for the Plus courses' listening games; CourseClips on the server). Lighter than a
// song's part: three beats counted in, then the take runs the clip's seconds and a little more and
// stops by itself. Its check is the device's alone (not silent, not too quiet, not clipping, and sung
// for long enough): a clip has no song to match, so there's no pitch or timing bar.

import { audio, engine, leadSeconds } from './audio.js';
import { readyToSing } from './creator-audio.js';
import { trackFrame } from './pitchtrack.js';
import { levelChecks } from './quality.js';

export const COUNT_IN = 3; // beats at 60 a minute: three seconds to get ready
const MARGIN_SECONDS = 1.5; // past the clip's length before it stops: room to finish the note
const SUSTAIN_FRAMES = 6; // a pitch held this many frames (about 100 ms) is singing
const ENOUGH = 0.7; // sung for at least this much of the clip's seconds

/** Opens the mic from a tap (with the mic check the first time). False when it was closed. */
export function readyClipMic() {
    return readyToSing();
}

/**
 * Records one clip: COUNT_IN clicks, then `seconds` and a margin, then it stops. `onFrame({ seconds,
 * singing })` runs each frame, `seconds` negative during the count-in. Returns `{ stop, done }`;
 * `done` resolves `{ samples, sampleRate, sungSeconds }`, or null when stopped before it began.
 */
export function recordClip({ seconds, onFrame = () => {} }) {
    engine.play({ fromSeconds: 0, bpm: 60, beatsPerBar: COUNT_IN, pickupBeats: 0, countInBeats: COUNT_IN, metronome: false, record: true, latencyFrames: audio.latencyFrames ?? 0, leadSeconds: leadSeconds() });
    let run = 0;
    let sung = 0;
    let last = null;
    let stopping = false;
    let frame = 0;
    let finish;
    const done = new Promise((resolve) => (finish = resolve));
    const end = async () => {
        if (stopping) return;
        stopping = true;
        cancelAnimationFrame(frame);
        const take = await engine.stop();
        if (!take || take.samples.length < take.sampleRate * 0.3) return finish(null);
        finish({ samples: take.samples, sampleRate: take.sampleRate, sungSeconds: sung });
    };
    const tick = () => {
        const at = engine.positionSeconds();
        if (at === null) return end(); // stopped from elsewhere (an interruption)
        const input = engine.inputFrame();
        const reading = input ? trackFrame(input, engine.sampleRate) : null;
        run = reading?.hz > 0 ? run + 1 : 0;
        const singing = at >= 0 && run >= SUSTAIN_FRAMES;
        if (singing && last !== null) sung += Math.max(0, at - last);
        last = at >= 0 ? at : null;
        onFrame({ seconds: at, singing });
        if (at >= seconds + MARGIN_SECONDS) return end();
        frame = requestAnimationFrame(tick);
    };
    frame = requestAnimationFrame(tick);
    return { stop: end, done };
}

/**
 * The device's verdict on a clip's take: `ready` when it isn't silent or too quiet, doesn't clip,
 * and was sung for long enough; else which of those it missed.
 */
export function checkClip(take, seconds) {
    const levels = levelChecks(take.samples, take.sampleRate);
    const short = !levels.silent && take.sungSeconds < seconds * ENOUGH;
    return { ready: !levels.silent && !levels.quiet && !levels.clipping && !short, silent: levels.silent, quiet: levels.quiet, clipping: levels.clipping, short };
}
