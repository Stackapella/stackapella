// A choir group's next rehearsal (js/rehearsal.js): the server's dates as local days, a weekly one rolled on by weeks once
// it has passed here, a one-off past here gone, and the days counted to it.
import { daysUntil, isoDate, localDate, rehearsalDates } from '/js/rehearsal.js';
import { assert, test } from './harness.js';

const day = (text) => localDate(text);

test('rehearsal: the next one and the one before, as the server says, rolled on by weeks when it has passed here', () => {
    const today = day('2026-11-08');
    const ahead = rehearsalDates({ date: '2026-11-12', weekly: true, next: '2026-11-12', previous: '2026-11-05' }, today);
    assert(isoDate(ahead.next) === '2026-11-12' && isoDate(ahead.previous) === '2026-11-05', JSON.stringify(ahead));
    // The example's October date, weekly: on to the Thursday after today, the previous a week before it.
    const rolled = rehearsalDates({ date: '2026-10-22', weekly: true, next: '2026-10-29', previous: '2026-10-22' }, today);
    assert(isoDate(rolled.next) === '2026-11-12' && isoDate(rolled.previous) === '2026-11-05', `rolled on: ${isoDate(rolled.next)} after ${isoDate(rolled.previous)}`);
    // Today is still the next one.
    assert(isoDate(rehearsalDates({ date: '2026-11-08', weekly: false, next: '2026-11-08', previous: null }, today).next) === '2026-11-08', 'today counts');
    // A one-off past here: no next, its date the previous.
    const gone = rehearsalDates({ date: '2026-11-01', weekly: false, next: '2026-11-01', previous: null }, today);
    assert(gone.next === null && isoDate(gone.previous) === '2026-11-01', JSON.stringify(gone));
    const none = rehearsalDates(null, today);
    assert(none.next === null && none.previous === null, 'none set');
});

test('rehearsal: days to it, from today (0), tomorrow (1), across a month', () => {
    const today = day('2026-11-29');
    assert(daysUntil(day('2026-11-29'), today) === 0 && daysUntil(day('2026-11-30'), today) === 1 && daysUntil(day('2026-12-02'), today) === 3, 'days');
    assert(localDate('2026-13-01') === null || localDate('nope') === null, 'a bad date is none');
});
