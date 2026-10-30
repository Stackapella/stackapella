// Voice care's day (js/voice-day.js): seconds sung today on this device, the notes at 20 and 40
// minutes each said once a day, a new day at local midnight, and the Settings switch.
import { assert, test } from './harness.js';
import { addSung, flushSung, markShown, noteDue, setNotes, sungToday } from '../js/voice-day.js';

const KEY = 'stackapella.voiceDay';
const NOON = new Date(2026, 9, 30, 12).getTime();
const NEXT_DAY = new Date(2026, 9, 31, 0, 5).getTime();

function fresh(seconds = 0, shown = []) {
    localStorage.setItem(KEY, JSON.stringify({ day: new Date(NOON).toDateString(), sungSeconds: seconds, shown }));
}

test('voice care: under 20 minutes sung, no note', () => {
    setNotes(true);
    fresh(19 * 60);
    assert(noteDue(NOON) === null, 'no note yet');
});

test('voice care: 20 minutes sung is the first note, said once a day', () => {
    setNotes(true);
    fresh(20 * 60 + 1);
    assert(noteDue(NOON) === 20, `20, got ${noteDue(NOON)}`);
    markShown(20, NOON);
    assert(noteDue(NOON) === null, 'not again today');
});

test('voice care: 40 minutes is the second note, and covers the 20', () => {
    setNotes(true);
    fresh(41 * 60);
    assert(noteDue(NOON) === 40, 'the 40, the highest crossed');
    markShown(40, NOON);
    assert(noteDue(NOON) === null, 'neither again today');
});

test('voice care: sung seconds add up, and a new day starts at local midnight', () => {
    setNotes(true);
    fresh(0);
    addSung(3);
    addSung(4); // over the flush threshold: kept
    flushSung(NOON);
    assert(Math.round(sungToday(NOON)) === 7, `7 s, got ${sungToday(NOON)}`);
    assert(sungToday(NEXT_DAY) === 0, 'a new day starts at nothing');
});

test('voice care: the switch off says no note; the count goes on', () => {
    fresh(30 * 60);
    setNotes(false);
    assert(noteDue(NOON) === null, 'no note with the switch off');
    setNotes(true);
    assert(noteDue(NOON) === 20, 'and the note back with it on');
    localStorage.removeItem(KEY);
});
