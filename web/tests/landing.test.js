// The landing's page script (js/public/landing.js): what it works out from the song's place, as pure functions. The page itself is tests/flows/landing_page.py.
import { assert, test } from './harness.js';
import { JOIN_LEAD, buildAt, captionOf, nextGlow, noteState, phraseAt, waiting } from '/js/public/landing.js';

const assertEq = (actual, expected) => assert(JSON.stringify(actual) === JSON.stringify(expected), `${JSON.stringify(actual)} should be ${JSON.stringify(expected)}`);

test('landing: the sung line is the phrase the beat is in, and holds through a rest', () => {
    const phrases = [{ from: 0, to: 6 }, { from: 8, to: 14 }, { from: 14, to: 20 }];
    assertEq([0, 5.9, 7, 8, 13.9, 14, 99].map((beat) => phraseAt(phrases, beat)), [0, 0, 0, 1, 1, 2, 2]);
    assertEq(phraseAt([], 3), 0);
});

test('landing: a note is lit while it sounds and sung once it has, and nothing is lit before the song starts', () => {
    const note = { b: 2, l: 1.5 };
    assertEq([0, 1.9, 2, 3.4, 3.5, 9].map((beat) => noteState(note, beat)), ['', '', 'lit', 'lit', 'sung', 'sung']);
    assertEq(noteState({ b: 0, l: 1 }, 0), '');
});

test('landing: the next note glows as it nears, the app\'s ramp: none until min(0.45 s, 1 beat) before it, then t squared to 0.3', () => {
    const note = { b: 4, l: 1 };
    // At 60 bpm a beat is 1 s, so the ramp is the last 0.45 beats.
    assertEq(nextGlow(note, 3.5, 1), 0);
    assert(Math.abs(nextGlow(note, 4 - 0.225, 1) - 0.3 * 0.25) < 1e-9, 'half way: a quarter of the level');
    assert(Math.abs(nextGlow(note, 3.99999, 1) - 0.3) < 1e-3, 'at its start: the level');
    assertEq(nextGlow(note, 4, 1), 0);
    // At 200 bpm a beat (0.3 s) is shorter than 0.45 s: the ramp is the last beat.
    assert(nextGlow(note, 3.1, 0.3) > 0 && nextGlow(note, 2.9, 0.3) === 0, 'one beat at a fast tempo');
    assertEq(nextGlow(note, 0, 1), 0);
});

test('landing: a play in two passes is the first time through, then the second from its own start', () => {
    assertEq([0, 12, 47.5, 48, 60, 96].map((place) => buildAt(place, 48, 2)), [
        { pass: 0, beat: 0 }, { pass: 0, beat: 12 }, { pass: 0, beat: 47.5 }, { pass: 1, beat: 0 }, { pass: 1, beat: 12 }, { pass: 1, beat: 48 },
    ]);
    assert(Math.abs(buildAt(95.9, 48, 2).beat - 47.9) < 1e-9 && buildAt(95.9, 48, 2).pass === 1, 'late in the second pass');
    assertEq(buildAt(30, 48), { pass: 0, beat: 30 });
    assertEq(buildAt(30, 0, 2), { pass: 0, beat: 0 });
    assertEq(buildAt(-1, 48, 2), { pass: 0, beat: 0 });
});

test('landing: a voice waits until just before its beat of the first pass, and never in the second', () => {
    assertEq([0, 11, 12 - JOIN_LEAD - 0.01, 12 - JOIN_LEAD, 12, 40].map((beat) => waiting(12, 0, beat)), [true, true, true, false, false, false]);
    assertEq(waiting(0, 0, 0), false);
    assertEq([0, 5, 30].map((beat) => waiting(36, 1, beat)), [false, false, false]);
});

test('landing: the stage says who sings: the first voice, each one as it joins, then all four, and a voice alone', () => {
    const joins = [0, 12, 24, 36];
    assertEq([1, 11, 12, 23, 24, 35.9, 36, 47].map((beat) => captionOf(joins, 0, beat)), [
        { say: 'first', at: 0 }, { say: 'first', at: 0 }, { say: 'join', at: 1 }, { say: 'join', at: 1 }, { say: 'join', at: 2 }, { say: 'join', at: 3 }, { say: 'join', at: 3 }, { say: 'join', at: 3 },
    ]);
    assertEq(captionOf(joins, 1, 2), { say: 'all', at: -1 });
    assertEq(captionOf([0, 0, 0, 0], 0, 2), { say: 'all', at: -1 });
    assertEq(captionOf(joins, 0, 2, true), { say: 'alone', at: -1 });
});
