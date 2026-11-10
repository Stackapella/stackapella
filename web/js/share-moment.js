// When a lesson offers its Share (docs/design-rules.md, "Sharing in lessons"; Hillel: "provide options to share while
// doing lessons to help with organic growth, don't make it annoying"): only at an earned, proud moment, and at most once a
// session (this tab's visit). The first moment that asks claims the session's one invitation; the same moment asking again
// (its screen redrawn) keeps it; any other moment that session gets none. Nothing here sends or keeps anything about who.

import { session } from './storage.js';

const KEY = 'stackapella.shareMoment';

/** Whether moment `id` (a lesson's end, a unit's, a Then and now) may offer Share: the session's first to ask, or the one that did. */
export function claimShare(id) {
    const held = session.get(KEY);
    if (held === null || held === undefined) {
        session.set(KEY, id);
        return true;
    }
    return held === id;
}

/** For the tests: the session as new. */
export function forgetShare() {
    session.remove(KEY);
}

/** Today's streak, when it's a milestone worth a line on the card (7, 30 or 100 days, then each 100), else null. */
export function streakMilestone(days) {
    if (!Number.isInteger(days) || days < 7) return null;
    return days === 7 || days === 30 || days % 100 === 0 ? days : null;
}
