// Sharpen up (js/sharpen.js): which lessons it replays, where each one starts, and the tab's queue.
import { endSharpen, sharpenAt, sharpenOn, sharpenPicks, sharpenStart, startSharpen } from '/js/sharpen.js';
import { assert, test } from './harness.js';

const learn = { kind: 'learn' };
const play = (exercise = {}) => ({ kind: 'play', exercise });
const course = {
    id: 'pitch',
    units: [
        {
            id: 'u1',
            lessons: [
                { id: 'a', steps: [learn, { kind: 'try' }, play()] },
                { id: 'b', steps: [learn, { kind: 'choose' }, { kind: 'bullseye' }] },
                { id: 'c', steps: [learn, { kind: 'bullseye' }, play()] },
                { id: 'd', steps: [learn] },
                { id: 'e', steps: [learn, play()] },
            ],
        },
    ],
};
const kept = (lessons) => ({ courses: { pitch: { lessons } } });
const staff = { staff: true };

test('sharpen up: a lesson starts at its last scored step, or the step it builds on', () => {
    assert(sharpenStart(course.units[0].lessons[0]) === 2, 'the play, past learn and try');
    assert(sharpenStart(course.units[0].lessons[1]) === 2, 'a registered scored step (Bullseye), past the choose before it');
    assert(sharpenStart(course.units[0].lessons[3]) === null, 'nothing scored: never replayed');
    assert(sharpenStart({ steps: [learn, play(), play({ under: { step: 1 } })] }) === 1, 'a take sung over an earlier one starts at that one');
    assert(sharpenStart({ steps: [learn, { kind: 'breathhold' }, learn, { kind: 'breathhold', exercise: { under: { steps: [1] } } }] }) === 1, 'a hold over earlier holds');
    assert(sharpenStart({ steps: [learn, { kind: 'bullseye' }, { kind: 'bullseye', exercise: { compare: { step: 1 } } }] }) === 1, 'a round compared with an earlier one');
    assert(sharpenStart({ steps: [learn, play(), { kind: 'selfcheck' }] }) === 1, 'unscored steps after it still follow');
    assert(sharpenStart({ steps: [learn, { kind: 'record' }] }) === null, 'a recording in Sing is not replayed here');
});

test('sharpen up: the weakest lessons done, fewest stars, then lowest best, then longest ago', () => {
    assert(sharpenPicks(course, kept({ a: { stars: 1, bestPercent: 50, at: 1 }, b: { stars: 2, bestPercent: 70, at: 1 } }), staff).length === 0, 'two done: not yet');
    const three = sharpenPicks(course, kept({ a: { stars: 3, bestPercent: 95, at: 1 }, b: { stars: 2, bestPercent: 70, at: 1 }, c: { stars: 1, bestPercent: 40, at: 1 } }), staff);
    assert(three.map((p) => p.lesson.id).join() === 'c,b', `three done: the two weakest, weakest first (${three.map((p) => p.lesson.id)})`);
    const four = sharpenPicks(course, kept({ a: { stars: 2, bestPercent: 75, at: 5 }, b: { stars: 2, bestPercent: 75, at: 2 }, c: { stars: 2, bestPercent: 72, at: 9 }, e: { stars: 3, bestPercent: 99, at: 1 } }), staff);
    assert(four.map((p) => p.lesson.id).join() === 'c,b,a', `four done: three, the lower best first, then the longer ago (${four.map((p) => p.lesson.id)})`);
    assert(four[0].kept.bestPercent === 72 && four[0].unit.id === 'u1', 'each with its kept result and unit');
    const learnOnly = sharpenPicks(course, kept({ a: { stars: 3, bestPercent: 95 }, b: { stars: 2, bestPercent: 70 }, d: { stars: 3, bestPercent: null } }), staff);
    assert(learnOnly.length === 0, 'a lesson with nothing scored does not count toward it');
    const closed = sharpenPicks(course, kept({ a: { stars: 1, bestPercent: 40 }, b: { stars: 1, bestPercent: 40 }, c: { stars: 1, bestPercent: 40 } }), {});
    assert(closed.length === 0, 'lessons past the free one, without Plus: not offered');
});

test('sharpen up: the queue in this tab moves on lesson by lesson and ends', () => {
    endSharpen();
    assert(sharpenAt('c') === null, 'no session');
    startSharpen('pitch', ['c', 'b', 'a']);
    const at = sharpenAt('c');
    assert(at && at.place === 1 && at.total === 3 && at.course === 'pitch', 'on the first');
    assert(sharpenAt('b') === null, 'only the lesson it is on now');
    assert(sharpenOn('c') === 'b' && sharpenAt('b').place === 2, 'on to the second');
    assert(sharpenOn('b') === 'a' && sharpenOn('a') === null, 'the last one ends it');
    assert(sharpenAt('a') === null && sharpenAt('c') === null, 'ended');
});
