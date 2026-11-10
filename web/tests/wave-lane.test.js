// The wave lane's course measure (js/components/wave-lane.js courseScore): it hands the course what it keeps, so
// Yodeling's flip finder settles a flip from two sirens, as the courses audit simulated (voice.md F2: without it, every
// siren's reading replaced the last and no flip was ever kept).
import { courseScore } from '/js/components/wave-lane.js';
import { PLAY_SCORERS } from '/js/lesson-scores.js';
import { assert, test } from './harness.js';

/** A track from [seconds, from, to] parts (MIDI; 0 is silence), at 100 readings a second. */
function track(parts) {
    const hz = [];
    for (const [seconds, from, to = from] of parts) {
        const n = Math.round(seconds * 100);
        for (let i = 0; i < n; i++) {
            const midi = from + ((to - from) * i) / Math.max(1, n - 1);
            hz.push(from === 0 ? 0 : 440 * 2 ** ((midi - 69) / 12));
        }
    }
    return { rate: 100, hz: Float32Array.from(hz) };
}

test('wave lane: a siren step hands the flip finder what the course kept, so a second siren that flips in the same place keeps the flip', () => {
    const home = 57;
    const plan = { beatSeconds: 1, segments: [{ from: 1, to: 13, seconds: 12 }] };
    // One siren up and down, its voice jumping from about 5 to 9 above home going up, and back coming down.
    const siren = track([[1, 0], [1, home - 3, home + 5], [1, home + 9, home + 12], [1, home + 12, home + 9], [1, home + 5, home - 3], [8, home - 3]]);
    const ask = (personal) => courseScore(PLAY_SCORERS.flipFinder, { track: siren, plan, pitch: home, exercise: { step: 0 }, strictness: 'standard', personal });
    const first = ask({});
    assert(first.percent === 100 && first.personal.flipSeen.length === 1 && first.personal.flip === undefined, `one siren: seen once, not kept: ${JSON.stringify(first)}`);
    const second = ask(first.personal);
    assert(second.percent === 100 && second.personal.flip === 7, `the second siren, with the first's reading, keeps the flip: ${JSON.stringify(second)}`);
    const forgetful = ask({});
    assert(forgetful.personal.flip === undefined, 'without what was kept, a siren is only ever the first');
});
