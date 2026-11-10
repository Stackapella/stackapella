// Growth's fair comparisons in words (js/admin/growth-compare.js; docs/growth-compare-spec.md 3 and 4.6): the change as
// "+3", "+24%" or "3 times as many", an estimated number as "about n", each comparison's name with its cut, the usual
// range, too few said with both counts, and the "How we're tracking" sentence from the four verdicts.
import { changeText, comparisonName, expectedText, FROM_DAYS, rangeText, tooFewText, trackingSentence } from '/js/admin/growth-compare.js';
import { assert, test } from './harness.js';

const today = { key: 'today', from: '2026-11-09', to: '2026-11-09', cut: '10:23' };
const week = { key: '7d', from: '2026-11-03', to: '2026-11-09', cut: '10:23' };
const month = { key: 'month', from: '2026-11-01', to: '2026-11-09', cut: '10:23' };

test('growth compare: a change is a count under 20, a percentage from 20, and "times as many" past +200%', () => {
    assert(changeText({ kind: 'count', value: 3 }) === '+3', changeText({ kind: 'count', value: 3 }));
    assert(changeText({ kind: 'count', value: -4 }) === '-4', changeText({ kind: 'count', value: -4 }));
    assert(changeText({ kind: 'percent', value: 24 }) === '+24%', changeText({ kind: 'percent', value: 24 }));
    assert(changeText({ kind: 'times', value: 3 }) === '3 times as many', changeText({ kind: 'times', value: 3 }));
    assert(changeText(null) === '' && changeText({ kind: 'count', value: null }) === '', 'no change, no words');
});

test('growth compare: an estimated number says "about", an exact one doesn\'t', () => {
    assert(expectedText({ expected: 12.3, estimated: false }) === '12', expectedText({ expected: 12.3, estimated: false }));
    assert(expectedText({ expected: 8.7, estimated: true }) === 'about 9', expectedText({ expected: 8.7, estimated: true }));
    assert(expectedText({ expected: null }) === '', 'nothing to say');
});

test('growth compare: each comparison is named with its cut time', () => {
    const at = { cut: '10:23', weekday: 'Monday' };
    assert(comparisonName(today, 'previous', at) === 'Yesterday by 10:23', comparisonName(today, 'previous', at));
    assert(comparisonName(today, 'usual', at) === 'A usual Monday by 10:23', comparisonName(today, 'usual', at));
    assert(comparisonName(today, 'usual', { ...at, available: 2 }) === 'A usual Monday by 10:23 (the 2 before)', comparisonName(today, 'usual', { ...at, available: 2 }));
    assert(comparisonName(today, 'usual', { ...at, available: 0 }) === 'A usual Monday by 10:23', 'none there: the plain name, beside "Compared from"');
    assert(comparisonName(week, 'previous', at) === 'The 7 days before, to 10:23', comparisonName(week, 'previous', at));
    assert(comparisonName(week, 'usual', at) === 'A usual week (the 4 before)', comparisonName(week, 'usual', at));
    assert(comparisonName({ key: '28d' }, 'previous', at) === 'The 4 weeks before, to 10:23', comparisonName({ key: '28d' }, 'previous', at));
    assert(comparisonName({ key: '12w' }, 'previous', at) === 'The 12 weeks before', comparisonName({ key: '12w' }, 'previous', at));
    assert(comparisonName(month, 'previous', { ...at, from: '2026-10-01', to: '2026-10-09' }) === 'Oct 1 to 9, to 10:23', comparisonName(month, 'previous', { ...at, from: '2026-10-01', to: '2026-10-09' }));
    assert(comparisonName(month, 'previous', { ...at, from: '2027-02-01', to: '2027-02-28', scaledDays: 29.4 }) === 'All of February, scaled to 29.4 days', comparisonName(month, 'previous', { ...at, from: '2027-02-01', to: '2027-02-28', scaledDays: 29.4 }));
});

test('growth compare: the usual range in words, and too few said with both counts', () => {
    assert(rangeText({ low: 20, high: 42 }, { cut: '10:23' }) === 'usually 20 to 42 by 10:23', rangeText({ low: 20, high: 42 }, { cut: '10:23' }));
    assert(rangeText({ low: 31, high: 52 }) === 'Usually 31 to 52 by now', rangeText({ low: 31, high: 52 }));
    assert(rangeText({ low: null, high: null }) === '', 'no range, no words');
    // Spec 4.6's first worked example: 1 against 2 is too few, never "-50%".
    assert(tooFewText(today, 1, { expected: 2, estimated: false }, { cut: '10:23' }) === '1 so far, 2 by 10:23 yesterday', tooFewText(today, 1, { expected: 2 }, { cut: '10:23' }));
});

test('growth compare: the tracking sentence from the four verdicts, never more than two sentences', () => {
    const m = (key, verdict) => ({ key, usual: { verdict } });
    const mixed = trackingSentence([m('visits', 'up'), m('arrivals', 'up'), m('firstLessons', 'tooFew'), m('signUps', 'same')]);
    assert(mixed === 'Visits and arrivals are up on a usual week; sign-ups are about the same. First lessons: too few to tell yet.', mixed);
    const down = trackingSentence([m('visits', 'down'), m('arrivals', 'same'), m('firstLessons', 'same'), m('signUps', 'maybeUp')]);
    assert(down === 'Visits are down on a usual week; arrivals, first lessons and sign-ups are about the same.', down);
    assert(trackingSentence([m('visits', 'same'), m('arrivals', 'maybeDown'), m('firstLessons', 'same'), m('signUps', 'same')]) === 'A usual week so far: nothing has moved more than chance would.', 'all steady');
    assert(trackingSentence([m('visits', 'tooFew'), m('arrivals', 'none'), m('firstLessons', 'noHistory'), m('signUps', 'tooFew')]) === "Too few numbers this week to say how we're tracking. The counts are below.", 'all too few');
    const lesson1 = trackingSentence([m('arrivals', 'up'), { key: 'firstLessons', measure: 'lesson1', usual: { verdict: 'noHistory' } }]);
    assert(lesson1 === 'Arrivals are up on a usual week. Lesson 1: not enough history to compare yet.', lesson1);
});

test('growth compare: a remembered day count becomes its period, 30 days as 4 weeks', () => {
    assert(FROM_DAYS[1] === 'today' && FROM_DAYS[7] === '7d' && FROM_DAYS[30] === '28d' && FROM_DAYS[84] === '12w', JSON.stringify(FROM_DAYS));
});

import { hourSeries, roundProjected, runningTotals, ticks } from '/js/admin/growth-chart.js';

test('growth charts: running totals stop at now; a projection is rounded as the spec says', () => {
    assert(JSON.stringify(runningTotals([1, 2, 0, 3, null, null])) === '[1,3,3,6,null,null]', JSON.stringify(runningTotals([1, 2, 0, 3, null, null])));
    assert(roundProjected(160.4) === 160 && roundProjected(1084) === 1080 && roundProjected(1225) === 1230, 'whole under 1,000, to the nearest 10 above');
});

test('growth charts: three or four round gridlines from 0 past the top', () => {
    const t1 = ticks(98);
    assert(t1[0] === 0 && t1.at(-1) >= 98 && t1.length <= 5, JSON.stringify(t1));
    assert(JSON.stringify(ticks(0)) === '[0,1]', 'an empty chart still has an axis');
    const t2 = ticks(6);
    assert(t2.at(-1) >= 6 && t2.every((v, i) => !i || v > t2[i - 1]), JSON.stringify(t2));
});

test('growth charts: the projection starts at the now dot and its fan never dips below the count so far', () => {
    const chart = { now: { slot: 1, fraction: 0.5 }, metrics: { arrivals: { now: [10, 20, null], previous: [1, 1, 1], projection: { shown: true, value: 60, low: 40, high: 80, path: { mean: [35, 60], low: [25, 40], high: [45, 80] } } } } };
    const s = hourSeries(chart, 'arrivals');
    assert(s.soFar === 30 && JSON.stringify(s.today.at(-1)) === '[1.5,30]', JSON.stringify(s.today));
    assert(JSON.stringify(s.projection.lowPath) === '[[1.5,30],[2,30],[3,40]]', JSON.stringify(s.projection.lowPath));
    assert(JSON.stringify(s.projection.mean[0]) === '[1.5,30]' && s.projection.highPath[1][1] === 45, JSON.stringify(s.projection));
});

import { projectedTotal, showsFromText } from '/js/admin/growth-columns.js';

test('growth columns: when a hidden projection will show, said as a time, a date or a week\'s day and part of day', () => {
    assert(showsFromText('06:00') === '06:00', showsFromText('06:00'));
    assert(showsFromText('2026-11-04') === 'Nov 4', showsFromText('2026-11-04'));
    assert(showsFromText('tuesday-evening') === 'Tuesday evening', showsFromText('tuesday-evening'));
    assert(showsFromText('sunday') === 'Sunday' && showsFromText(null) === '', showsFromText('sunday'));
});

test('growth columns: a period\'s projected total is its full slots plus the current slot\'s projection and range; the month\'s is its own', () => {
    const week = projectedTotal('7d', { shown: true, value: 69, low: 41, high: 98, slot: { index: 6, value: 69, low: 41, high: 98 }, periodTotal: 205 });
    assert(JSON.stringify(week) === '{"value":205,"low":177,"high":234}', JSON.stringify(week));
    const month = projectedTotal('month', { shown: true, value: 761, low: 645, high: 878, slot: { index: 8, value: 69, low: 41, high: 98 }, periodTotal: 761 });
    assert(JSON.stringify(month) === '{"value":761,"low":645,"high":878}', JSON.stringify(month));
    assert(projectedTotal('7d', { shown: false, hidden: 'tooFew' }) === null, 'hidden: nothing to say');
});
