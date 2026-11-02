// Home's Today line (js/home-today.js; docs/home-returning-plan.md): the first case that fits, the
// stuck watch, and the slower tempo.
import { isStuck, slowed, todayCase, watchNext } from '/js/home-today.js';
import { assert, test } from './harness.js';

test('home today: a gap of four days or more offers the warm-up, while a workout can be had', () => {
    assert(todayCase({ away: 4, workoutOpen: true }) === 'gap', 'four days away');
    assert(todayCase({ away: 3, workoutOpen: true }) === null, 'three days is no gap');
    assert(todayCase({ away: 9, workoutOpen: false }) === null, "today's workout already had: nothing to offer");
    assert(todayCase({ away: 5, workoutOpen: true, stuck: true, finished: true, daily: true }) === 'gap', 'the gap comes first');
});

test('home today: then stuck, then the course finished, then the challenge, else nothing', () => {
    assert(todayCase({ stuck: true }) === 'stuck', 'stuck');
    assert(todayCase({ stuck: true, finished: true }) === 'done', 'finished is never stuck');
    assert(todayCase({ finished: true, daily: true }) === 'done', 'finished before the challenge');
    assert(todayCase({ daily: true }) === 'daily', "game mode's challenge on a day with nothing else");
    assert(todayCase({}) === null, 'nothing new');
});

test('home today: the next lesson is watched from the day it became next', () => {
    assert(watchNext(null, 'a1', '2026-11-01').day === '2026-11-01', 'a new watch starts today');
    const kept = { id: 'a1', day: '2026-10-28' };
    assert(watchNext(kept, 'a1', '2026-11-01') === kept, 'the same lesson keeps its day');
    assert(watchNext(kept, 'a2', '2026-11-01').id === 'a2', 'a new next lesson starts again');
});

test('home today: stuck is three days on one lesson with two of them sung, said once a lesson', () => {
    const watch = { id: 'a1', day: '2026-10-29' };
    const sung = { '2026-10-30': 120, '2026-11-01': 60, '2026-10-01': 300 };
    assert(isStuck(watch, sung, '2026-11-01'), 'three days, two sung');
    assert(!isStuck(watch, sung, '2026-10-31'), 'two days is not yet stuck');
    assert(!isStuck(watch, { '2026-11-01': 60, '2026-10-01': 300 }, '2026-11-01'), 'one day sung: a break, not a struggle');
    assert(!isStuck(watch, { '2026-10-30': 0, '2026-11-01': 60 }, '2026-11-01'), 'a day with no seconds is no day sung');
    assert(isStuck(watch, sung, '2026-11-01', { id: 'a1', day: '2026-11-01' }), 'said today: still shown today');
    assert(!isStuck(watch, sung, '2026-11-02', { id: 'a1', day: '2026-11-01' }), 'said on an earlier day: never again for this lesson');
    assert(isStuck(watch, sung, '2026-11-01', { id: 'a0', day: '2026-10-20' }), 'said for another lesson: this one can be said');
});

test('home today: try it slower plays a lesson at 85% of its tempo', () => {
    assert(slowed({ tempo: 80, notes: [] }, true).tempo === 68, '80 to 68');
    assert(slowed({ notes: [] }, true).tempo === 61, 'the default 72 to 61');
    const ex = { tempo: 80 };
    assert(slowed(ex, false) === ex, 'not slower: as written');
    assert(slowed(null, true) === null, 'no exercise: nothing');
});
