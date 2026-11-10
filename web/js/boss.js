// The boss round (docs/boss-round-plan.md; Hillel approved it 2026-11-02): each unit's `boss`, written by the courses
// lead beside the unit's lessons, its skills combined in one optional, harder go. It opens once the unit's challenge
// has stars and never in a singer's first week; it gates nothing and gives no stars, so the course opens as before.
// Scored only by the lessons' own scorers: each sung step's percent, weighted by its notes; 80% beats it. Beating it
// marks the unit mastered, pays XP once and counts toward two badges. Pure: the lesson view runs it, stores keep it.

import { addXp } from './course.js';
import { tally } from './badges.js';

/** The percent that beats a boss. */
export const BOSS_PASS = 80;

/** A new singer's first week, from their course start: no boss shows in it (reading-lines.js keeps the same rule). */
export const FIRST_WEEK = 7 * 86400000;

/** The unit's challenge (its lesson marked `challenge`, else its last lesson): its stars open the boss. */
export const challengeOf = (unit) => unit.lessons.find((lesson) => lesson.challenge) ?? unit.lessons.at(-1) ?? null;

/** Past the first week of the course (a course not started has none). */
export const pastFirstWeek = (progress, now = Date.now()) => !!progress.startedAt && now - progress.startedAt >= FIRST_WEEK;

/**
 * Whether a unit's boss can be taken: the unit has one, its challenge has stars, and the first week is over. Plus and
 * the unit being open are the caller's (a challenge with stars is only reachable in an open unit).
 */
export function bossOpen(unit, progress, now = Date.now()) {
    if (!unit?.boss) return false;
    const challenge = challengeOf(unit);
    return !!challenge && (progress.lessons?.[challenge.id]?.stars ?? 0) > 0 && pastFirstWeek(progress, now);
}

/** Whether a boss shows at all yet (locked or open): the unit has one and the first week is over. */
export const bossShown = (unit, progress, now = Date.now()) => !!unit?.boss && pastFirstWeek(progress, now);

/** The unit whose boss this id is (`{ unit, unitIndex }`), or null. */
export function unitOfBoss(units, id) {
    const unitIndex = units.findIndex((unit) => unit.boss?.id === id);
    return unitIndex < 0 ? null : { unit: units[unitIndex], unitIndex };
}

/**
 * The round's one percent: each sung step's `{ percent, notes }`, weighted by its notes (a long verse weighs more
 * than a short call). Null when any step couldn't be heard or none was sung: the round doesn't count, either way.
 */
export function bossPercent(results) {
    if (!results.length || results.some((r) => r.percent === null || r.percent === undefined)) return null;
    const notes = results.reduce((sum, r) => sum + Math.max(1, r.notes ?? 1), 0);
    return Math.round(results.reduce((sum, r) => sum + r.percent * Math.max(1, r.notes ?? 1), 0) / notes);
}

/** A unit's kept result, `{ best, beatAt }`, or null. */
export const bossResult = (progress, unitId) => progress.bosses?.[unitId] ?? null;

/** Whether a unit's boss is beaten (mastered). */
export const bossBeaten = (progress, unitId) => !!progress.bosses?.[unitId]?.beatAt;

/**
 * A round finished at `percent`: the best kept, and on the first win the date, the XP (once; outside the day's cap,
 * as it can't be ground) and, when it's the last free unit's boss to fall, the count the Every boss badge reads.
 * Returns `{ progress, beaten, first, newBest, gained }`.
 */
export function recordBoss(progress, units, unitId, percent, at = Date.now()) {
    const had = bossResult(progress, unitId);
    const beaten = percent >= BOSS_PASS;
    const first = beaten && !had?.beatAt;
    const newBest = !!had && percent > (had.best ?? -1);
    let next = { ...progress, bosses: { ...(progress.bosses ?? {}), [unitId]: { best: Math.max(percent, had?.best ?? -1), beatAt: had?.beatAt ?? (beaten ? at : null) } } };
    let gained = 0;
    if (first) {
        ({ progress: next, gained } = addXp(next, { kind: 'boss' }, at));
        const all = units.filter((unit) => unit.boss);
        if (all.length && all.every((unit) => bossBeaten(next, unit.id))) next = tally(next, 'everyBoss', { best: 1 });
    }
    return { progress: next, beaten, first, newBest, gained };
}
