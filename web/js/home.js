// The home's order (docs/decisions.md, "the home page", direction C): a stack of bands, the course
// always first. Then beta parts waiting on you, else today's workout (unless you sang a project this
// week), then Your songs (everything of yours, with New song: docs/home-create-plan.md), then the
// song library, then the "More" chips.
// A band for a feature that isn't built or is switched off is left out. Pure, unit-tested in
// tests/home.test.js.

export const WEEK = 7 * 24 * 3600 * 1000;

/**
 * The bands in order, and whether the course's band is compact (it is when your own work is
 * waiting, so that work is still above the fold on a phone).
 *
 * - `betaSinger`, `betaWaiting`: a beta singer, and how many parts wait on them (to record, or
 *   asked for another take);
 * - `recentProject`: a project of theirs with a take from this week;
 * - `features`: `{ workout, more }`, what's switched on (`more` lists the chips).
 */
export function homeBands({ betaSinger = false, betaWaiting = 0, recentProject = false, features = {} } = {}) {
    const bands = ['course'];
    const waiting = betaSinger && betaWaiting > 0;
    if (waiting) bands.push('beta');
    else if (!recentProject && features.workout) bands.push('workout');
    bands.push('projects', 'songs');
    if (betaSinger && !waiting) bands.push('beta');
    if (features.more?.length) bands.push('more');
    return { bands, compactCourse: waiting || recentProject };
}

/** A project they sang in this week (course lessons' own projects don't count: the course band has them). */
export function sangThisWeek(projects, now = Date.now()) {
    return projects.some((p) => !p.course && !p.beta && p.recorded && now - p.updatedAt < WEEK);
}

/** Parts that wait on a beta singer: still to record, or asked for another take. */
export function betaWaiting(parts) {
    return (parts ?? []).filter((part) => part.recordable !== false && ['to_record', 'retake'].includes(part.status)).length;
}
