// A course's daily cap (js/daily-cap.js): minutes from the course file, a day's count that starts again tomorrow,
// and two devices' counts merged as the later day's, or the larger on the same day.
import { addSpent, capMinutes, capReached, mergeCapDay, spentToday } from '/js/daily-cap.js';
import { dayOf } from '/js/course.js';
import { assert, test } from './harness.js';

const noon = new Date(2026, 10, 2, 12).getTime();
const tomorrow = new Date(2026, 10, 3, 9).getTime();

test('daily cap: the course says its minutes, or has none', () => {
    assert(capMinutes({ dailyCap: { minutes: 10 } }) === 10, 'ten');
    assert(capMinutes({}) === null && capMinutes({ dailyCap: { minutes: 0 } }) === null && capMinutes(null) === null, 'none');
});

test('daily cap: time adds up through the day, rests at the cap, and a new day starts again', () => {
    const course = { id: 'belting', dailyCap: { minutes: 10 } };
    let capDay = addSpent(null, 300, noon);
    capDay = addSpent(capDay, 299, noon);
    const progress = (day) => ({ courses: { belting: { capDay: day } } });
    assert(spentToday(progress(capDay), 'belting', noon) === 599 && !capReached(progress(capDay), course, noon), 'just under');
    capDay = addSpent(capDay, 1, noon);
    assert(capReached(progress(capDay), course, noon), 'ten minutes: rest');
    assert(!capReached(progress(capDay), course, tomorrow) && spentToday(progress(capDay), 'belting', tomorrow) === 0, 'tomorrow: open again');
    assert(addSpent(capDay, 60, tomorrow).seconds === 60 && addSpent(capDay, 60, tomorrow).day === dayOf(tomorrow), 'a new day counts from nothing');
    assert(!capReached(progress(capDay), { id: 'belting' }, noon), 'a course with no cap never rests');
});

test('daily cap: two devices keep the later day, or the larger count on the same day', () => {
    const a = { day: '2026-11-02', seconds: 200 };
    const b = { day: '2026-11-02', seconds: 450 };
    assert(mergeCapDay(a, b) === b && mergeCapDay(b, a) === b, 'the larger');
    assert(mergeCapDay(a, { day: '2026-11-03', seconds: 10 }).day === '2026-11-03', 'the later day');
    assert(mergeCapDay(null, a) === a && mergeCapDay(a, undefined) === a && mergeCapDay(null, null) === null, 'one side only');
});
