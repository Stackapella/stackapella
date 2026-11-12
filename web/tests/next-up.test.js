// The thread back (js/next-up.js; docs/retention-plan.md, idea 2): tomorrow's calendar entry, made on the device.
import { calendarEntry } from '/js/next-up.js';
import { assert, test } from './harness.js';

test('next-up: tomorrow\'s calendar entry is one event at the hour chosen, holding the lesson\'s tagged link', () => {
    const now = new Date(2026, 10, 11, 21, 30); // 11 November, 21:30 on the singer's clock
    const morning = calendarEntry('2-match', 'morning', now);
    const evening = calendarEntry('2-match', 'evening', now);
    assert(morning.startsWith('BEGIN:VCALENDAR\r\n') && morning.trimEnd().endsWith('END:VCALENDAR'), 'a calendar file');
    assert((morning.match(/BEGIN:VEVENT/g) ?? []).length === 1, 'one entry');
    assert(morning.includes('DTSTART:20261112T090000') && morning.includes('DTEND:20261112T091500'), 'tomorrow morning, a quarter of an hour, floating local time');
    assert(evening.includes('DTSTART:20261112T190000'), 'tomorrow evening');
    assert(morning.includes(`URL:${location.origin}/learn/2-match?ref=back-calendar`), 'the lesson\'s link, tagged as the calendar\'s');
    assert(!/@example|email|ATTENDEE|ORGANIZER/i.test(morning), 'nobody named in it');
});
