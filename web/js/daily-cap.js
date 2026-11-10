// A course's daily cap (course.json "dailyCap": { "minutes": 10 }; First steps to belting, from the voice teacher's
// review): once that many minutes of the course's lessons are spent in a day, its lessons rest until tomorrow with
// "That's today's belting. Your voice recovers overnight." Kept per local day in the course's progress
// (progress.courses.<id>.capDay, so it follows the account across devices), counted only while one of its lessons is
// open and on screen, and checked only as a lesson opens: never mid-lesson, and no countdown. Other courses stay open.
// Unlike Yodeling's flip rest (flip-rest.js, a sitting kept in memory), this one lasts the day.

import { dayOf } from './course.js';

/** The course's cap in minutes, or null when it has none. */
export function capMinutes(course) {
    const minutes = course?.dailyCap?.minutes;
    return Number.isFinite(minutes) && minutes > 0 ? minutes : null;
}

/** Seconds of `courseId`'s lessons spent on the day of `now`. */
export function spentToday(progress, courseId, now = Date.now()) {
    const kept = progress?.courses?.[courseId]?.capDay;
    return kept && kept.day === dayOf(now) ? kept.seconds : 0;
}

/** Whether `course`'s lessons rest for the rest of the day. */
export function capReached(progress, course, now = Date.now()) {
    const minutes = capMinutes(course);
    return minutes !== null && spentToday(progress, course.id, now) >= minutes * 60;
}

/** The day's count after `seconds` more (a new day starts from nothing). */
export function addSpent(capDay, seconds, now = Date.now()) {
    const day = dayOf(now);
    return { day, seconds: Math.round((capDay?.day === day ? capDay.seconds : 0) + Math.max(0, seconds)) };
}

/** Two devices' counts: the later day's, and on the same day the larger (time spent on either counts). */
export function mergeCapDay(a, b) {
    if (!a) return b ?? null;
    if (!b) return a;
    if (a.day !== b.day) return a.day > b.day ? a : b;
    return a.seconds >= b.seconds ? a : b;
}
