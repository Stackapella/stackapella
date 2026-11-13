// Admin > Live's small pieces (js/admin/live.js): bar heights against the tallest with a sliver for a zero, the
// twelve weeks' line in its box, and whether the page is live, reconnecting or paused.
import { heights, linePoints, liveState, STEPS } from '/js/admin/live.js';
import { areaFor } from '/js/live-beat.js';
import { assert, test } from './harness.js';

test('admin live: bars are a share of the tallest, a zero a sliver, all zeros still a row', () => {
    assert(JSON.stringify(heights([0, 5, 10])) === '[1.5,50,100]', JSON.stringify(heights([0, 5, 10])));
    assert(JSON.stringify(heights([1, 100])) === '[4,100]', 'a small count stays visible');
    assert(JSON.stringify(heights([0, 0])) === '[1.5,1.5]', JSON.stringify(heights([0, 0])));
});

test('admin live: the weeks line spans the box from the foot, one value or none', () => {
    const points = linePoints([0, 10]).split(' ');
    assert(points[0] === '0,31' && points[1] === '100,1', points.join(' '));
    assert(linePoints([5]) === '0,1', linePoints([5]));
    assert(linePoints([]) === '', 'no weeks, no line');
});

test('admin live: live, reconnecting after a failed poll, paused while hidden', () => {
    assert(liveState({ visible: true, staleSince: null }) === 'live');
    assert(liveState({ visible: true, staleSince: 1 }) === 'stale');
    assert(liveState({ visible: false, staleSince: 1 }) === 'paused');
    assert(STEPS.join() === 'created,lesson1,wholeSong,keptPart,shared');
});

test('live beat: each screen in its area, the rest the app, staff screens never', () => {
    assert(areaFor('learn') === 'courses' && areaFor('songCourse') === 'courses', 'lessons and courses');
    assert(areaFor('studio') === 'studio' && areaFor('sing') === 'studio', 'the studio and Sing');
    assert(areaFor('projects') === 'app' && areaFor('settings') === 'app', 'Home and settings');
    assert(areaFor('admin') === null && areaFor('styleguide') === null && areaFor('styleguideGame') === null, 'staff never beat');
});
