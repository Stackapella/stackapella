// Hear yourself as a choir's page script (js/public/your-choir.js): what it works out from the scroll and from the one
// player's clock, as pure functions. The page's stage and gating are server/tests/Feature/Site/YourChoirPageTest.php.
import { assert, test } from './harness.js';
import { JOIN_FADE, LEAD, beatsAt, clock, heard, inTail, noteState, poseOf, sayAt, sceneAt } from '/js/public/your-choir.js';

const assertEq = (actual, expected) => assert(JSON.stringify(actual) === JSON.stringify(expected), `${JSON.stringify(actual)} should be ${JSON.stringify(expected)}`);

// Her real song (80 bpm, 0.75 s a beat, 8 beats), then the AI one 7.6 s in (66 bpm), its tenor and bass joining on beat 12.
const note = (beat, length) => ({ beat, length, pitch: 60 });
const play = {
    seconds: 30,
    segments: [
        { key: 'real', start: 0, spb: 0.75, voices: [{ part: 'soprano', join: 0, notes: [note(0, 4), note(4, 4)] }] },
        { key: 'ai', start: 7.6, spb: 60 / 66, voices: [
            { part: 'soprano', join: 0, notes: [note(0, 12), note(12, 12)] },
            { part: 'alto', join: 0, notes: [note(0, 24)] },
            { part: 'tenor', join: 12, notes: [note(0, 24)] },
            { part: 'bass', join: 12, notes: [note(0, 24)] },
        ] },
    ],
};

test('your-choir: a scene a screen of scroll, the last one from there on; past the stage once it is half gone', () => {
    assertEq([0, 399, 401, 800, 3200, 9000].map((y) => sceneAt(y, 800, 5)), [0, 0, 1, 1, 4, 4]);
    assertEq(sceneAt(500, 0, 5), 0);
    assertEq(sceneAt(500, 800, 0), 0);
    assertEq([0, 3200, 3601, 9000].map((y) => inTail(y, 800, 5)), [false, false, true, true]);
    assertEq(inTail(5000, 0, 5), false);
});

test('your-choir: each view poses its lanes at a moment of its own; four waits past the join', () => {
    assertEq(poseOf('hero').rb < poseOf('real').rb, true);
    assertEq(poseOf('split').ab > 0, true);
    assertEq(poseOf('four', 12).ab > 12, true);
    assertEq(poseOf('nowhere'), { rb: 0, ab: 0 });
});

test('your-choir: the time reads as minutes and seconds', () => {
    assertEq([0, 5.4, 59.6, 62, 102].map(clock), ['0:00', '0:05', '1:00', '1:02', '1:42']);
    assertEq(clock(-3), '0:00');
});

test('your-choir: each song keeps its own beats on the one clock', () => {
    const at = beatsAt(play, 7.6 + 60 / 66 * 2);
    assertEq(Math.round(at.rb * 1000) / 1000, Math.round((7.6 + 60 / 66 * 2) / 0.75 * 1000) / 1000);
    assertEq(Math.round(at.ab * 1000) / 1000, 2);
    assertEq(beatsAt(play, 1).ab < 0, true);
});

test('your-choir: the picture is the sound: a voice is heard from its join to its last note, and only then', () => {
    const [real, ai] = play.segments;
    const [s, , t] = ai.voices;
    assertEq([0, 3, 6.2, 7].map((x) => heard(real, real.voices[0], x)), [true, true, false, false]);
    assertEq([3, 7.6, 10].map((x) => heard(ai, s, x)), [false, true, true]);
    const joinAt = ai.start + 12 * ai.spb;
    assertEq([joinAt - 1, joinAt + 0.1].map((x) => heard(ai, t, x)), [false, true]);
});

test('your-choir: the line says who is heard: her, then the two AI parts, then all four', () => {
    const ai = play.segments[1];
    assertEq(sayAt(play, 1), 'real');
    assertEq(sayAt(play, ai.start + 1), 'two');
    assertEq(sayAt(play, ai.start + 12 * ai.spb + 0.2), 'four');
    const two = { ...play, segments: [play.segments[0], { ...ai, voices: ai.voices.slice(0, 2) }] };
    assertEq(sayAt(two, ai.start + 20 * ai.spb), 'two');
});

test('your-choir: a note is lit while it sounds and settled once it has', () => {
    const n = { b: 2, l: 1 };
    assertEq([0, 1.9, 2, 2.9, 3].map((beat) => noteState(n, beat)), ['', '', 'lit', 'lit', 'sung']);
});

test('your-choir: everything is set on the clock before the first note, and a join ramps in by its beat', () => {
    assertEq(LEAD > 0 && LEAD < 0.5, true);
    assertEq(JOIN_FADE > 0 && JOIN_FADE < 0.2, true);
});
