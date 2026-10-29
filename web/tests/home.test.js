// The home's band order (js/home.js): the course first, then what's waiting on you, songs, your
// projects; features not built are left out.
import { betaWaiting, homeBands, sangThisWeek, WEEK } from '/js/home.js';
import { assert, test } from './harness.js';

const order = (state) => homeBands(state).bands.join(' ');

test('home: a first visit gets the course, Your songs (New song), then the library', () => {
    assert(order({}) === 'course projects songs', order({}));
    assert(!homeBands({}).compactCourse, 'the course band is full size');
});

test('home: what waits on you comes second, beta parts before a recent project', () => {
    assert(order({ recentProject: true }) === 'course projects songs', order({ recentProject: true }));
    assert(homeBands({ recentProject: true }).compactCourse, 'compact, so the project is above the fold');
    assert(order({ betaSinger: true, betaWaiting: 2, recentProject: true }) === 'course beta projects songs', 'beta first');
    assert(order({ betaSinger: true, betaWaiting: 0 }) === 'course projects songs beta', 'nothing waiting: the beta band goes last');
    assert(!homeBands({ betaSinger: true }).compactCourse, 'nothing waiting: full size');
});

test('home: the workout and the More chips only when switched on', () => {
    assert(order({ features: { workout: true } }) === 'course workout projects songs', 'workout second');
    assert(order({ recentProject: true, features: { workout: true } }) === 'course projects songs', 'a recent project wins over the workout');
    assert(order({ features: { more: ['sing'] } }) === 'course projects songs more', 'chips last');
    assert(order({ features: { more: [] } }) === 'course projects songs', 'no chips, no band');
});

test('home: this week, and parts waiting', () => {
    const now = Date.parse('2026-10-26T12:00:00Z');
    assert(sangThisWeek([{ recorded: true, updatedAt: now - 3600e3 }], now), 'an hour ago');
    assert(!sangThisWeek([{ recorded: true, updatedAt: now - WEEK - 1 }], now), 'over a week');
    assert(!sangThisWeek([{ recorded: false, updatedAt: now }], now), 'nothing recorded');
    assert(!sangThisWeek([{ recorded: true, course: { lessonId: 'x' }, updatedAt: now }], now), 'a lesson\'s own project');
    assert(betaWaiting([{ status: 'to_record' }, { status: 'retake' }, { status: 'submitted' }, { status: 'to_record', recordable: false }]) === 2, 'two wait');
    assert(betaWaiting(null) === 0, 'not loaded');
});
