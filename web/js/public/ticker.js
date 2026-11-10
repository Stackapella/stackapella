// A repeating pattern on the audio clock (docs/tools-plan.md, "Shared modules"): the metronome's
// heart, and any tool's pulse. step(time, n, late) is called once per step, in order, a little before
// its time; it schedules that step's sounds at `time` (seconds on the AudioContext clock) and returns
// the seconds to the next step, or null to end. Because each step is asked for just before it sounds,
// a change (tempo, metre, accents) lands on the next step, with no stop.
//
// What wakes it is the audio clock itself: a silent source that ends a moment ahead, whose `ended`
// event asks for the next steps. Never a timer (the house rule), and an audible background tab keeps
// it going where timers and animation frames are slowed or stopped. If the clock was suspended (a
// phone locked) and the steps fell behind, the missed ones are asked for with `late` true, so the
// caller keeps count without sounding them all at once. Tested in tests/ticker.test.js.

import { audio } from './voices.js';

/**
 * Starts stepping at `at` (now when null). `ahead` is how far ahead steps are scheduled: long enough
 * to ride out a busy main thread, short enough that a change is heard on the next beat.
 */
export function ticker(step, { at = null, ahead = 0.3, context = null } = {}) {
    const c = context ?? audio();
    let next = at ?? c.currentTime + 0.05;
    let n = 0;
    let running = true;
    let pacer = null;

    function fill() {
        if (!running) return;
        const now = c.currentTime;
        while (running && next < now + ahead) {
            const gap = step(next, n, next < now - 0.02);
            n += 1;
            if (gap === null || gap === undefined || !(gap > 0)) {
                running = false;
                break;
            }
            next += gap;
        }
        if (running) wake(now);
    }

    function wake(now) {
        pacer = c.createConstantSource();
        pacer.offset.value = 0;
        pacer.connect(c.destination);
        pacer.onended = fill;
        pacer.start(now);
        pacer.stop(now + ahead / 3);
    }

    fill();
    return {
        get running() {
            return running;
        },
        /** When the next step not yet scheduled will be, on the audio clock. */
        get next() {
            return next;
        },
        stop() {
            running = false;
            if (pacer) {
                pacer.onended = null;
                try {
                    pacer.stop();
                } catch {
                    // already ended
                }
            }
        },
    };
}
