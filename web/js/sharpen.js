// Sharpen up (docs/courses-ideas.md, idea 5; Hillel chose it, 2026-10-29): a Plus course's two or
// three weakest lessons by their kept result, sung back to back from each one's scored part. Each
// lesson finishes as itself (views/learn.js), so its own best result is kept as ever: nothing new is
// stored, and the session's queue lives in this tab only. Pure but for the queue; unit-tested in
// tests/sharpen.test.js.

import { courseLessons, courseLessonsKept, lessonAccess } from './courses.js';
import { isScored } from './lesson-steps.js';
import { session } from './storage.js';

/** Lessons done before a course page offers Sharpen up. */
export const SHARPEN_FROM = 3;
const MOST = 3;
const KEY = 'stackapella.sharpen';

/**
 * Where a lesson's Sharpen up run starts: its last scored step, or an earlier step that one (or a
 * step after it) builds on (`under`, `compare`: a take sung over, a result compared). Null when the
 * lesson has nothing scored, or its scored step is a song in Sing (Use it, Record).
 */
export function sharpenStart(lesson) {
    const steps = lesson?.steps ?? [];
    let start = steps.findLastIndex((s) => isScored(s.kind));
    if (start < 0 || ['record', 'use'].includes(steps[start].kind)) return null;
    for (let i = steps.length - 1; i >= start; i--) {
        const e = steps[i].exercise ?? {};
        const needs = [e.under?.step, ...(e.under?.steps ?? []), e.compare?.step].filter(Number.isInteger);
        if (needs.length) start = Math.min(start, ...needs);
    }
    return start;
}

/**
 * The lessons to sharpen, weakest first: lessons done and open, by fewest stars kept, then the
 * lowest best percent, then the longest ago. Two once SHARPEN_FROM are done, three from one more
 * (never the whole course). Each `{ lesson, unit, kept }`; empty until SHARPEN_FROM are done.
 */
export function sharpenPicks(course, progress, access = {}) {
    const kept = courseLessonsKept(progress, course?.id);
    const done = courseLessons(course).filter((l) => kept[l.lesson.id]?.stars && !l.lesson.findsHome);
    const playable = (id) => {
        const { open, plusOnly } = lessonAccess(course, id, progress, access);
        return open && !plusOnly;
    };
    const ready = done.filter((l) => sharpenStart(l.lesson) !== null && playable(l.lesson.id));
    if (ready.length < SHARPEN_FROM) return [];
    const at = (l) => (kept[l.lesson.id].at ? new Date(kept[l.lesson.id].at).getTime() : 0);
    const ranked = [...ready].sort((a, b) => kept[a.lesson.id].stars - kept[b.lesson.id].stars || (kept[a.lesson.id].bestPercent ?? 0) - (kept[b.lesson.id].bestPercent ?? 0) || at(a) - at(b));
    return ranked.slice(0, Math.min(MOST, ready.length - 1)).map((l) => ({ lesson: l.lesson, unit: l.unit, kept: kept[l.lesson.id] }));
}

/** Begin a session in this tab: the course and its lessons, in order. */
export function startSharpen(courseId, lessonIds) {
    session.set(KEY, JSON.stringify({ course: courseId, ids: lessonIds, index: 0 }));
}

/** The session, when `lessonId` is the lesson it's on now: `{ course, ids, index, place, total }`, else null. */
export function sharpenAt(lessonId) {
    let run = null;
    try {
        run = JSON.parse(session.get(KEY) || 'null');
    } catch (error) {
        console.warn('A Sharpen up session could not be read', error);
    }
    if (!run || !Array.isArray(run.ids) || run.ids[run.index] !== lessonId) return null;
    return { ...run, place: run.index + 1, total: run.ids.length };
}

/** On from `lessonId`: the session's next lesson id (and the session moves to it), or null at its end. */
export function sharpenOn(lessonId) {
    const run = sharpenAt(lessonId);
    const next = run?.ids[run.index + 1] ?? null;
    if (next) session.set(KEY, JSON.stringify({ course: run.course, ids: run.ids, index: run.index + 1 }));
    else endSharpen();
    return next;
}

/** The session is over (its end, or the course page opened again). */
export function endSharpen() {
    session.remove(KEY);
}
