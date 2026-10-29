// The pitch camera (js/camera.js): a narrow phrase framed tight, a leap framed before it comes, the
// span kept between its limits, a critically damped spring (no overshoot, settled in time), a quick
// note that moves nothing, the push-in and pull-back, and reduced motion's frame a phrase.
import { CAMERA, camera, frameFor, moodFor, phraseFrame, stepCamera, viewOf } from '/js/camera.js';
import { assert, test } from './harness.js';

const notes = (list) => list.map(([start, end, pitch]) => ({ start, end, pitch }));

test('camera: a narrow phrase is framed tight, never under the minimum span', () => {
    const frame = frameFor(notes([[0, 1, 60], [1, 2, 62], [2, 3, 60]]), 0.5);
    assert(frame.span === CAMERA.minSpan, `span ${frame.span}`);
    assert(Math.abs(frame.centre - 61) < 1e-9, `centred on the notes: ${frame.centre}`);
});

test('camera: a leap is framed before it arrives, and the part\'s range caps the span', () => {
    const song = notes([[0, 1, 60], [1, 2, 62], [2.5, 3.5, 74]]);
    const before = frameFor(song, 0.5);
    assert(before.span >= 74 - 60 + 2 * CAMERA.pad - 1e-9, `the leap 2 s ahead is in the frame already: ${before.span}`);
    assert(frameFor(song, 0.5, { maxSpan: 10 }).span === 10, 'never wider than the part');
    assert(frameFor([], 0) === null, 'nothing to frame');
    assert(frameFor([], 0, { voice: 57 }).centre === 57, 'the voice alone');
});

test('camera: a voice far off does not drag the frame further than the part allows', () => {
    const frame = frameFor(notes([[0, 2, 60]]), 0.5, { voice: 84, maxSpan: 14 });
    assert(frame.centre + frame.span / 2 <= 60 + 7 + CAMERA.pad + 1e-9, `top ${frame.centre + frame.span / 2}`);
});

test('camera: the spring settles in about its time, without overshooting', () => {
    let cam = stepCamera(camera(), { centre: 60, span: 8 }, 0.016);
    assert(cam.centre === 60 && cam.span === 8, 'the first frame is taken at once');
    const target = { centre: 67, span: 14 };
    let over = false;
    let t = 0;
    for (; t < CAMERA.settle; t += 1 / 60) {
        cam = stepCamera(cam, target, 1 / 60);
        if (cam.centre > 67 + 1e-6 || cam.span > 14 + 1e-6) over = true;
    }
    assert(!over, 'no overshoot');
    assert(Math.abs(cam.centre - 67) < 7 * 0.03 && Math.abs(cam.span - 14) < 6 * 0.03, `settled after ${CAMERA.settle} s: ${cam.centre}, ${cam.span}`);
    const half = stepCamera(stepCamera(camera(), { centre: 60, span: 8 }, 0.016), target, 0.2);
    assert(half.centre > 60.5 && half.centre < 66, `moving, smoothly: ${half.centre}`);
    const still = stepCamera(stepCamera(camera(), { centre: 60, span: 8 }, 0.016), target, 0.016, { still: true });
    assert(still.centre === 67, 'reduced motion: at once');
});

test('camera: a frame inside the deadband changes nothing; a frame rate change does not change the path', () => {
    let cam = stepCamera(camera(), { centre: 60, span: 8 }, 0.016);
    cam = stepCamera(cam, { centre: 60.4, span: 8.5 }, 0.5 - 1e-3);
    assert(cam.centre === 60 && cam.span === 8, 'a quick note inside the frame moves nothing');
    let fast = stepCamera(camera(), { centre: 60, span: 8 }, 0.016);
    let slow = fast;
    for (let i = 0; i < 60; i++) fast = stepCamera(fast, { centre: 66, span: 12 }, 1 / 120);
    for (let i = 0; i < 15; i++) slow = stepCamera(slow, { centre: 66, span: 12 }, 1 / 30);
    assert(Math.abs(fast.centre - slow.centre) < 0.01, `120 fps and 30 fps agree: ${fast.centre} ${slow.centre}`);
    const { low, high } = viewOf(fast);
    assert(Math.abs(high - low - fast.span) < 1e-9, 'the view is the span');
});

test('camera: a long note held in tune pushes in; a phrase\'s end pulls back', () => {
    const song = notes([[0, 3, 60], [6, 7, 62]]);
    assert(moodFor(song, 1, { inTuneFor: 0.2 }) === 1, 'not yet');
    assert(moodFor(song, 1, { inTuneFor: 0.8 }) === CAMERA.lockIn, 'held in tune: in');
    assert(moodFor(song, 3.5) === CAMERA.phraseOut, 'a rest ahead: out');
    assert(moodFor(song, 5.5) === 1, 'the next phrase coming: back');
});

test('camera: reduced motion frames the whole phrase, so it only changes at a phrase\'s start', () => {
    const song = notes([[0, 1, 60], [1, 2, 72], [4, 5, 50], [5, 6, 52]]);
    const phrases = [[0, 2], [4, 6]];
    const first = phraseFrame(song, 0.2, phrases);
    assert(first.span >= 12 + 2 * CAMERA.pad - 1e-9, 'the first phrase whole');
    assert(JSON.stringify(phraseFrame(song, 1.5, phrases)) === JSON.stringify(first), 'the same frame through the phrase');
    assert(phraseFrame(song, 3, phrases).centre === 51, 'the next phrase framed in the rest before it');
});
