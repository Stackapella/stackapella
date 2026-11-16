// /next-final's page script (js/public/landing-next-final.js): what it works out from the scroll and from the song's
// place, as pure functions. The page itself is tests/flows/next_final.py.
import { assert, test } from './harness.js';
import { PINNED, inTail, noteState, phraseAt, poseOf, sceneAt } from '/js/public/landing-next-final.js';

const assertEq = (actual, expected) => assert(JSON.stringify(actual) === JSON.stringify(expected), `${JSON.stringify(actual)} should be ${JSON.stringify(expected)}`);

test('next-final: the stage holds five scenes, a screen of scroll each, and the last one from there on', () => {
    assertEq(PINNED, 5);
    assertEq([0, 399, 401, 800, 1200, 3200, 3600, 4400, 9000].map((y) => sceneAt(y, 800)), [0, 0, 1, 1, 2, 4, 4, 4, 4]);
    assertEq(sceneAt(-50, 800), 0);
    assertEq(sceneAt(500, 0), 0);
});

test('next-final: past the stage is the page that scrolls, once the stage is more than half gone', () => {
    assertEq([0, 3200, 3600, 3601, 4000, 9000].map((y) => inTail(y, 800)), [false, false, false, true, true, true]);
    assertEq(inTail(5000, 0), false);
});

test('next-final: only the story scenes pose the song; the first stands at its start', () => {
    assertEq(poseOf(0), 0);
    assertEq([1, 2, 3, 4].every((scene) => poseOf(scene) > 0), true);
    assertEq(poseOf(7), 0);
});

test('next-final: the sung line is the phrase the beat is in, and holds through a rest', () => {
    const phrases = [{ from: 0, to: 6 }, { from: 8, to: 14 }, { from: 14, to: 20 }];
    assertEq([0, 5.9, 7, 8, 13.9, 14, 99].map((beat) => phraseAt(phrases, beat)), [0, 0, 0, 1, 1, 2, 2]);
    assertEq(phraseAt([], 3), 0);
});

test('next-final: a note is lit while it sounds and sung once it has, and nothing is lit before the song starts', () => {
    const note = { b: 2, l: 1.5 };
    assertEq([0, 1.9, 2, 3.4, 3.5, 9].map((beat) => noteState(note, beat)), ['', '', 'lit', 'lit', 'sung', 'sung']);
    assertEq(noteState({ b: 0, l: 1 }, 0), '');
});
