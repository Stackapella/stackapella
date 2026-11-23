// Admin > People's activity cell (Hillel, 2026-11-23: "easy to glance/parse"): the three biggest of what someone used,
// said in the leading order (AI choir, lessons, courses, songs, takes, shares), none at zero.
import { activityOf } from '/js/admin/people.js';
import { assert, test } from './harness.js';

test('people activity: the three biggest, in the leading order', () => {
    const said = activityOf({ stats: { choirs: 3, lessonsDone: 5, coursesStarted: 1, songs: 3, takes: 12, shares: 1 } });
    assert(said === '3 AI choirs · 5 lessons · 12 takes', said);
});

test('people activity: zeros left out, one alone, nothing yet', () => {
    assert(activityOf({ stats: { choirs: 0, lessonsDone: 1, songs: 0 } }) === '1 lesson', activityOf({ stats: { lessonsDone: 1 } }));
    assert(activityOf({ stats: { songs: 0, lessonsDone: 0 } }) === 'Nothing yet', 'all zero');
    assert(activityOf({}) === '', 'no stats');
});
