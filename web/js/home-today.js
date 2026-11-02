// Home's "Today" line (docs/home-returning-plan.md, approved by Hillel 2026-11-01; experiment ret1):
// one suggestion for a singer past their first week, inside the course card, the first that fits:
// back after a gap (a warm-up), stuck on one lesson (try it slower), the free course finished (a song
// in your range, then a course), or in game mode on a day with nothing else, today's challenge.
// Never a count of missed days, never a lost streak. Pure, unit-tested in tests/home-today.test.js;
// what it reads is this device's own (nothing sent but the anonymous counts).

export const AWAY_DAYS = 4; // days away that make a gap
export const STUCK_DAYS = 3; // days on the same next lesson
export const STUCK_PRACTICE = 2; // days sung in that time (so it's a struggle, not a break)
export const SLOWER = 0.85; // the slower try: a lesson's tempo, as Sing's slower speed

const DAY = 86400000;
const gap = (from, to) => Math.max(0, Math.round((Date.parse(to) - Date.parse(from)) / DAY));

/**
 * Where the next lesson's watch stands, from the one kept (`{ id, day }` or null): kept as it is
 * while the next lesson is the same, else started again today. Returns what to keep.
 */
export function watchNext(kept, lessonId, today) {
    return kept?.id === lessonId && kept.day ? kept : { id: lessonId, day: today };
}

/**
 * Whether the singer is stuck on `watch.id`: it has been the next lesson for STUCK_DAYS or more, and
 * they sang on STUCK_PRACTICE or more of those days (`practice`: 'YYYY-MM-DD' -> seconds), today
 * included. `said`: the lesson it was said for ({ id, day }), said once a lesson (all that day).
 */
export function isStuck(watch, practice, today, said = null) {
    if (!watch?.id || gap(watch.day, today) < STUCK_DAYS) return false;
    if (said?.id === watch.id && said.day !== today) return false;
    const sung = Object.entries(practice ?? {}).filter(([day, seconds]) => day >= watch.day && day <= today && seconds > 0).length;
    return sung >= STUCK_PRACTICE;
}

/**
 * The case for today, or null: 'gap', 'stuck', 'done' or 'daily', the first that fits.
 * - `away`: whole days since the app was last opened here (js/asks.js daysAway);
 * - `workoutOpen`: today's workout can still be had (Plus, or a free one not yet used today);
 * - `stuck`: from isStuck; `finished`: every lesson open to them has stars;
 * - `daily`: game mode's challenge is on and there is one today.
 */
export function todayCase({ away = 0, workoutOpen = false, stuck = false, finished = false, daily = false } = {}) {
    if (away >= AWAY_DAYS && workoutOpen) return 'gap';
    if (stuck && !finished) return 'stuck';
    if (finished) return 'done';
    if (daily) return 'daily';
    return null;
}

/** A lesson's exercise at the slower tempo (the stuck case's slower try), else as written. */
export function slowed(exercise, slower) {
    return slower && exercise ? { ...exercise, tempo: Math.round((exercise.tempo ?? 72) * SLOWER) } : exercise;
}
