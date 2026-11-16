// The landing's page script (js/public/landing.js): what it works out from the scroll and from the song's
// place, as pure functions. The page itself is tests/flows/landing_page.py.
import { assert, test } from './harness.js';
import { JOIN_LEAD, PINNED, buildAt, captionOf, inTail, noteState, phraseAt, poseOf, sceneAt, waiting } from '/js/public/landing.js';

const assertEq = (actual, expected) => assert(JSON.stringify(actual) === JSON.stringify(expected), `${JSON.stringify(actual)} should be ${JSON.stringify(expected)}`);

test('landing: the stage holds five scenes, a screen of scroll each, and the last one from there on', () => {
    assertEq(PINNED, 5);
    assertEq([0, 399, 401, 800, 1200, 3200, 3600, 4400, 9000].map((y) => sceneAt(y, 800)), [0, 0, 1, 1, 2, 4, 4, 4, 4]);
    assertEq(sceneAt(-50, 800), 0);
    assertEq(sceneAt(500, 0), 0);
});

test('landing: past the stage is the page that scrolls, once the stage is more than half gone', () => {
    assertEq([0, 3200, 3600, 3601, 4000, 9000].map((y) => inTail(y, 800)), [false, false, false, true, true, true]);
    assertEq(inTail(5000, 0), false);
});

test('landing: only the story scenes pose the song; the first stands at its start', () => {
    assertEq(poseOf(0), 0);
    assertEq([1, 2, 3, 4].every((scene) => poseOf(scene) > 0), true);
    assertEq(poseOf(7), 0);
});

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
