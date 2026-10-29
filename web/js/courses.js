// The Plus courses (docs/plus-courses-build-plan.md, section 2): the catalogue, who sees a course,
// which lessons are open, and each course's own progress (`progress.courses.<id>.lessons`). A Plus
// course has the free course's shape, so its rules are course.js's, run on a view of the progress
// that holds only that course's lessons. Pure, unit-tested in tests/plus-courses.test.js; the
// courses' data and the progress are kept by stores/learning.js.

import { nextLesson, recordLesson, unitOpen } from './course.js';

/** Lessons of every Plus course that are free: the first (Hillel, 2026-10-28). */
export const FREE_LESSONS = 1;

/** A course's lessons, flat, each with its unit and places: `{ unit, unitIndex, lesson, index, place }`. */
export function courseLessons(course) {
    let place = 0;
    return (course?.units ?? []).flatMap((unit, unitIndex) => unit.lessons.map((lesson, index) => ({ unit, unitIndex, lesson, index, place: place++ })));
}

/** The course holding `lessonId`, among `courses` (id to course data), or null. */
export function courseOf(courses, lessonId) {
    return Object.values(courses ?? {}).find((course) => courseLessons(course).some((l) => l.lesson.id === lessonId)) ?? null;
}

/** One course's lessons as kept: `{ lessonId: { stars, bestPercent, at } }`. */
export function courseLessonsKept(progress, courseId) {
    return progress?.courses?.[courseId]?.lessons ?? {};
}

/**
 * The progress as one course sees it: its own lessons in `lessons`, so course.js's rules (units
 * opening at two thirds of the stars, the next lesson) run on it unchanged.
 */
export function courseView(progress, courseId) {
    return { ...progress, lessons: courseLessonsKept(progress, courseId), testedOut: [] };
}

/** A course lesson's result kept, the best so far, in that course's own place. Returns a new progress. */
export function recordCourseLesson(progress, courseId, lessonId, result, at = Date.now()) {
    const kept = recordLesson({ lessons: courseLessonsKept(progress, courseId) }, lessonId, result, at).lessons;
    return { ...progress, courses: { ...progress.courses, [courseId]: { ...progress.courses?.[courseId], lessons: kept } } };
}

/** Restart this course: only its lessons go; everything else (XP, badges, other courses) stays. */
export function restartedCourseProgress(progress, courseId) {
    const { [courseId]: _gone, ...others } = progress.courses ?? {};
    return { ...progress, courses: others };
}

/** Two devices' lessons as one: the best stars and percent of each, the later time. */
export function mergeLessons(mine = {}, theirs = {}) {
    const lessons = { ...theirs };
    for (const [id, had] of Object.entries(mine)) {
        const other = lessons[id];
        if (!other) {
            lessons[id] = had;
            continue;
        }
        const bestPercent = Math.max(had.bestPercent ?? -1, other.bestPercent ?? -1);
        lessons[id] = { stars: Math.max(had.stars ?? 0, other.stars ?? 0), bestPercent: bestPercent < 0 ? null : bestPercent, at: Math.max(had.at ?? 0, other.at ?? 0) };
    }
    return lessons;
}

/**
 * Every course's progress from two devices, merged course by course (course.js mergeProgress): the
 * lessons lesson by lesson, the personal values from whichever device set them later.
 */
export function mergeCourses(mine = {}, theirs = {}) {
    const ids = [...new Set([...Object.keys(mine ?? {}), ...Object.keys(theirs ?? {})])];
    return Object.fromEntries(ids.map((id) => {
        const a = mine?.[id] ?? {};
        const b = theirs?.[id] ?? {};
        const newer = (a.personalAt ?? 0) >= (b.personalAt ?? 0) ? a : b;
        const personal = newer.personal ? { personal: newer.personal, personalAt: newer.personalAt ?? null } : {};
        return [id, { ...b, ...a, lessons: mergeLessons(a.lessons, b.lessons), ...personal }];
    }));
}

/**
 * Whether an account sees a course: a live course, for staff before launch, then for everyone the
 * `plusCourses` feature reaches (account.show, once the backend has it). Signed out sees none yet.
 */
export function courseVisible(entry, me) {
    if (!entry || entry.status !== 'live' || !me) return false;
    return me.plusCourses === true || !!me.staff;
}

/** Whether a lesson is one of the course's free ones (the first). */
export function lessonFree(course, lessonId) {
    return courseLessons(course).findIndex((l) => l.lesson.id === lessonId) < FREE_LESSONS;
}

/**
 * Whether `lessonId` can be sung now: `{ open, plusOnly }`. Its unit must be open (two thirds of
 * the stars of the unit before, as in the free course), and past the free lesson it needs Plus.
 * Staff (`staff: true`) preview every unit and lesson, to review a course before launch.
 */
export function lessonAccess(course, lessonId, progress, { plus = false, staff = false } = {}) {
    const entry = courseLessons(course).find((l) => l.lesson.id === lessonId);
    if (!entry) return { open: false, plusOnly: false };
    const plusOnly = !plus && !staff && !lessonFree(course, lessonId);
    return { open: staff || unitOpen(course.units, entry.unitIndex, courseView(progress, course.id)), plusOnly };
}

/** The lesson to do next in a course, as course.js's nextLesson picks it. */
export function nextInCourse(course, progress) {
    return course ? nextLesson(course.units, courseView(progress, course.id), { plus: true }) : null;
}

/** A course's card: lessons done of all, and the stars. */
export function courseSummary(course, progress) {
    const kept = courseLessonsKept(progress, course.id);
    const lessons = courseLessons(course);
    const done = lessons.filter((l) => kept[l.lesson.id]?.stars).length;
    const stars = lessons.reduce((sum, l) => sum + (kept[l.lesson.id]?.stars ?? 0), 0);
    return { done, total: lessons.length, stars, started: done > 0 };
}

/** The lesson after `lessonId` in its course, if it's open to this learner now. */
export function lessonAfter(course, lessonId, progress, access = {}) {
    const lessons = courseLessons(course);
    const after = lessons[lessons.findIndex((l) => l.lesson.id === lessonId) + 1];
    if (!after) return null;
    const { open, plusOnly } = lessonAccess(course, after.lesson.id, progress, access);
    return open && !plusOnly ? after.lesson : null;
}
