// Yodeling's five-minute flip cap (js/flip-rest.js): which lessons count, and when the next one waits.
import { FLIP_CAP_MINUTES, FLIP_REST_MINUTES, flipLesson, flipRestUntil } from '/js/flip-rest.js';
import { assert, test } from './harness.js';

const M = 60000;

test('flip rest: lessons that flip count; listening and plain lessons don\'t', () => {
    assert(flipLesson({ steps: [{ kind: 'wave', exercise: { score: 'flipFinder' } }] }), 'the siren that finds the flip');
    assert(flipLesson({ steps: [{ kind: 'play', exercise: { base: 'flipKey' } }] }), 'a song tag');
    assert(!flipLesson({ steps: [{ kind: 'choose' }, { kind: 'play', exercise: { score: 'pitch' } }] }), 'a listening lesson');
    assert(!flipLesson(null), 'no lesson');
});

test('flip rest: five minutes of flips in a sitting, then the next flip lesson waits ten minutes after the last ended', () => {
    assert(FLIP_CAP_MINUTES === 5 && FLIP_REST_MINUTES === 10, 'the review\'s numbers');
    assert(flipRestUntil([], 0) === null, 'nothing sung yet');
    assert(flipRestUntil([{ from: 0, to: 4 * M }], 4 * M) === null, 'four minutes: open');
    const sitting = [{ from: 0, to: 3 * M }, { from: 4 * M, to: 6 * M }];
    assert(flipRestUntil(sitting, 6 * M) === 16 * M, `five minutes over two lessons: rest until ten after the last ${flipRestUntil(sitting, 6 * M)}`);
    assert(flipRestUntil(sitting, 16 * M) === null, 'rested: open again');
    const twoSittings = [{ from: 0, to: 4 * M }, { from: 20 * M, to: 22 * M }];
    assert(flipRestUntil(twoSittings, 22 * M) === null, 'a rest of ten minutes or more starts a new sitting');
});
