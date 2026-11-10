// Sharing in lessons (js/share-moment.js; docs/design-rules.md, "Sharing in lessons"): at most one invitation a session,
// the first earned moment's, which keeps it if its screen is drawn again; and a streak is worth a line on the card only at
// its milestones.
import { claimShare, forgetShare, streakMilestone } from '/js/share-moment.js';
import { assert, test } from './harness.js';

test('share moments: the session\'s first earned moment offers Share, the same one again keeps it, any other gets none', () => {
    forgetShare();
    assert(claimShare('lesson:a') === true, 'the first moment');
    assert(claimShare('lesson:a') === true, 'the same moment, drawn again');
    assert(claimShare('unit:u1') === false, 'a unit after it, the same session: none');
    assert(claimShare('then:b') === false, 'a Then and now after it: none');
    forgetShare();
    assert(claimShare('unit:u1') === true, 'a new session: the unit claims it');
    forgetShare();
});

test('share moments: a streak earns a line at 7, 30 and every 100 days, and nowhere else', () => {
    assert(streakMilestone(7) === 7 && streakMilestone(30) === 30 && streakMilestone(100) === 100 && streakMilestone(300) === 300, 'the milestones');
    assert([0, 1, 6, 8, 14, 29, 31, 99, 150, null, undefined, 7.5].every((d) => streakMilestone(d) === null), 'none between');
});
