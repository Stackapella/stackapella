// /next-claude's own script (js/public/landing-next-claude.js): the scene a scroll position shows, each story scene's
// still moment, the sung line a beat falls in, and a note's state as the song passes it.
import { assert, test } from './harness.js';
import { noteState, phraseAt, poseOf, sceneAt } from '/js/public/landing-next-claude.js';

const eq = (actual, expected) => assert(actual === expected, `${JSON.stringify(actual)} should be ${JSON.stringify(expected)}`);

test('next-claude: a screen of scroll is a scene, the nearest one, never past the ends', () => {
    eq(sceneAt(0, 800, 9), 0);
    eq(sceneAt(399, 800, 9), 0);
    eq(sceneAt(401, 800, 9), 1);
    eq(sceneAt(800 * 4, 800, 9), 4);
    eq(sceneAt(800 * 40, 800, 9), 8);
    eq(sceneAt(-50, 800, 9), 0);
    eq(sceneAt(500, 0, 9), 0); // a window with no height yet
});

test('next-claude: only the story scenes pose the song; the rest stand at its start', () => {
    eq(poseOf(0), 0);
    assert(poseOf(1) > 0 && poseOf(2) > poseOf(1) && poseOf(3) > poseOf(2), 'the story moves on through the song');
    for (const scene of [5, 6, 7, 8, 99]) eq(poseOf(scene), 0);
});

test('next-claude: the sung line is the phrase the beat is in, and holds through a rest', () => {
    const phrases = [{ from: 0, to: 12 }, { from: 12, to: 24 }, { from: 24, to: 36 }];
    eq(phraseAt(phrases, 0), 0);
    eq(phraseAt(phrases, 11.9), 0);
    eq(phraseAt(phrases, 12), 1);
    eq(phraseAt(phrases, 40), 2);
    eq(phraseAt([], 5), 0);
});

test('next-claude: a note is lit while it sounds and sung once it has, and nothing is lit before the song starts', () => {
    const note = { b: 4, l: 2 };
    eq(noteState(note, 0), '');
    eq(noteState(note, 3.9), '');
    eq(noteState(note, 4), 'lit');
    eq(noteState(note, 5.99), 'lit');
    eq(noteState(note, 6), 'sung');
    eq(noteState({ b: 0, l: 1 }, 0), ''); // the first note isn't lit while the song waits at 0
});
