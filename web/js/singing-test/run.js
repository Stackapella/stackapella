// A singing test, run (docs/singing-test-v1.md): its items in order (Match a note, 1 practice + 12; Sing it back, 1 +
// 6; Hold it, 2), each a turn (js/singing-test/play.js) measured into the contract's numbers
// (js/singing-test/measure.js). An item lost to noise is offered again, at most RETRIES times a test, never scored
// wrong; practice items are never kept or sent. The screen drives it item by item and shows only a neutral "heard"
// tick between: never a running score. `turn` is passed in for the tests (a synthetic voice); the app's is play.js's.

import { buildTest, HOLD_SECONDS, SCORING_VERSION, TEST_VERSION } from './items.js';
import { backItem, holdItem, matchItem } from './measure.js';
import { PATTERN_HEARD, result } from './score.js';

/** Unheard items offered again, at most this many times in one test. */
export const RETRIES = 3;
/** Each prompt note's length, and the time given to answer, in seconds (the same every time in this test version). */
export const TIMING = {
    match: { note: 1.2, answer: 2.4 },
    back: { note: 0.55, answer: 4 * 0.55 + 1.6 },
    hold: { note: 1.2, answer: HOLD_SECONDS + 1 },
};

/** The events in their order, with the contract's names. */
export const EVENTS = ['match', 'back', 'hold'];

/** Whether an item's answer was heard enough to score (else it's offered again). */
export function wasHeard(event, measures) {
    if (event === 'match') return measures.centsOff !== null;
    if (event === 'back') return measures.notes.filter((n) => n.centsOff !== null).length >= PATTERN_HEARD;
    return measures.spreadCents !== null;
}

/**
 * A test for `anchor` (its MIDI note). `{ items, sing(step), request(meta), retriesLeft() }`:
 * - `items`: every step in order, `{ event, index, practice, target | pattern }`;
 * - `sing(step, { onAnswer })`: its turn, measured, offered again while unheard (retries permitting); resolves with
 *   `{ heard, tries, measures }`;
 * - `request({ id, takenAt, device })`: the contract's singingTests.create body, the device's own score included.
 */
export function createRun(anchor, { turn }) {
    const test = buildTest(anchor);
    const items = [
        ...test.match.map((it, index) => ({ event: 'match', index, ...it })),
        ...test.back.map((it, index) => ({ event: 'back', index, ...it })),
        ...test.hold.map((it, index) => ({ event: 'hold', index, practice: false, ...it })),
    ];
    const kept = { match: [], back: [], hold: [] };
    let retries = RETRIES;

    async function once(step, onAnswer) {
        const timing = TIMING[step.event];
        const notes = step.event === 'back' ? step.pattern : [step.target];
        const { track, promptEnd } = await turn(notes, { noteSeconds: timing.note, answerSeconds: timing.answer, onAnswer });
        if (step.event === 'match') return matchItem(track, step.target, { promptEnd });
        if (step.event === 'back') return backItem(track, step.pattern, { promptEnd });
        return holdItem(track, step.target, { promptEnd });
    }

    async function sing(step, { onAnswer = () => {}, onRetry = () => {} } = {}) {
        let measures = await once(step, onAnswer);
        let tries = 1;
        while (!wasHeard(step.event, measures) && !step.practice && retries > 0) {
            retries -= 1;
            tries += 1;
            onRetry(step);
            measures = await once(step, onAnswer);
        }
        if (!step.practice) kept[step.event].push(measures);
        return { heard: wasHeard(step.event, measures), tries, measures };
    }

    function request({ id, takenAt, device, warmUp = false }) {
        const body = { id, testVersion: TEST_VERSION, scoringVersion: SCORING_VERSION, takenAt, anchor: Math.round(anchor * 10) / 10, device, warmUp, match: kept.match, back: kept.back, hold: kept.hold };
        const r = result(body);
        return { ...body, score: { total: r.total, match: r.scores.match, back: r.scores.back, hold: r.scores.hold, band: r.band } };
    }

    return { items, sing, request, retriesLeft: () => retries };
}
