// The Plus courses (docs/plus-courses-build-plan.md, section 2): the catalogue, who sees a course,
// which lessons are open, and each course's own progress (`progress.courses.<id>.lessons`). A Plus
// course has the free course's shape, so its rules are course.js's, run on a view of the progress
// that holds only that course's lessons. Pure, unit-tested in tests/plus-courses.test.js; the
// courses' data and the progress are kept by stores/learning.js.

import { mergeCapDay } from './daily-cap.js';
import { nextLesson, recordLesson, unitOpen } from './course.js';
import { pageGrants, pageLesson } from './page-lesson.js';

/**
 * Lessons of any course a guest (signed out) may sing before making a free account: the first
 * (Hillel, 2026-11-06: "lets just ask people create an account to continue past the 1 course",
 * then "after lesson 1 of any course"). Signed in, every lesson of every course is free.
 */
export const GUEST_LESSONS = 1;

/** The free course's id (web/course/course.json). */
export const FREE_COURSE = 'stackapella-course';

// How many of the free course's lessons a guest may sing: GUEST_LESSONS, unless this device is in wall2's arm b (lessons
// 1.1 to 1.3, the wall before 1.4; docs/improvement-log.md row 12). Set by js/experiments.js, which knows the arm; kept
// here as a function so this module stays pure.
let freeGuestLessons = () => GUEST_LESSONS;
export function setFreeGuestLessons(howMany) {
    freeGuestLessons = howMany;
}

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

/**
 * A song course (docs/song-courses-plan.md): one song, a unit a part. Every unit is open from the
 * start (learning the bass needn't wait on the melody), and it's open to anyone who may start the
 * song, so it has no Plus-only lessons: the song itself is the gate.
 */
export const songCourse = (course) => !!course?.id?.startsWith('song-');

/** courseView, with every unit of a song course open (counted as tested out). */
export function openView(progress, course) {
    const view = courseView(progress, course.id);
    return songCourse(course) ? { ...view, testedOut: course.units.map((u) => u.id) } : view;
}

/** A song course's catalogue entry is seen when it's live, or by staff while it's reviewed. */
export function songCourseVisible(entry, me) {
    return entry?.status === 'live' || (entry?.status === 'staff' && !!me?.staff);
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
        const capDay = mergeCapDay(a.capDay, b.capDay); // a capped course's day (daily-cap.js)
        return [id, { ...b, ...a, lessons: mergeLessons(a.lessons, b.lessons), ...personal, ...(capDay ? { capDay } : {}) }];
    }));
}

/**
 * Whether an account sees a course: a live course, for staff before launch, then for everyone the
 * `plusCourses` feature reaches (account.show). Signed out, a guest sees it once the feature is on
 * for everyone (auth.config's `plusCourses`, passed as `config`), to try its free lesson.
 */
export function courseVisible(entry, me, config = null, known = true) {
    if (!entry || entry.status !== 'live') return false;
    // Signed out, a guest can try a live course's free lesson once the feature is on for everyone
    // (Hillel, 2026-10-30: "Guests can try it"), as auth.config says it. Only once the account is `known`: a member whose
    // account hadn't come yet was read as a guest, so a list showed the courses and then hid them (2026-11-08).
    if (!me) return known && config?.plusCourses === true;
    return me.plusCourses === true || !!me.staff;
}

/**
 * Whether a guest may sing this lesson of `course`: one of its first GUEST_LESSONS, or (in place of those) the lesson
 * its own public page opened on this device (js/page-lesson.js: Hillel, 2026-11-08, "Yes, once").
 */
export function guestOpen(course, lessonId) {
    return course?.id && pageGrants[course.id] ? pageGrants[course.id] === lessonId : guestLesson(course, lessonId);
}

/**
 * Whether a guest may sing this lesson of `course` (any course, the free one too): one of its first GUEST_LESSONS (the free
 * course's, wall2's arm b: three).
 */
export function guestLesson(course, lessonId) {
    const at = courseLessons(course).findIndex((l) => l.lesson.id === lessonId);
    return at >= 0 && at < (course?.id === FREE_COURSE ? freeGuestLessons() : GUEST_LESSONS);
}

/**
 * Whether `lessonId` can be sung now: `{ open, needsAccount }`. Its unit must be open (two thirds of
 * the stars of the unit before, as in the free course). Every lesson of every course is on the free
 * plan (Hillel, 2026-11-06: "lets make all courses included with the free plan"); signed out, past
 * the first lesson it needs a free account (`needsAccount`). Staff (`staff: true`) preview every unit
 * and lesson, to review a course before launch; a song course's parts are open to anyone.
 */
export function lessonAccess(course, lessonId, progress, { signedIn = false, staff = false } = {}) {
    const entry = courseLessons(course).find((l) => l.lesson.id === lessonId);
    if (!entry) return { open: false, needsAccount: false };
    const needsAccount = !signedIn && !staff && !songCourse(course) && !guestOpen(course, lessonId);
    // The lesson its page opened sings, its unit's lock waived for it alone (js/page-lesson.js).
    return { open: staff || pageLesson(course.id, lessonId) || unitOpen(course.units, entry.unitIndex, openView(progress, course)), needsAccount };
}

/**
 * The lesson to do next in a course, as course.js's nextLesson picks it; with `unitId` (a song
 * course's part), the next in that unit.
 */
export function nextInCourse(course, progress, unitId = null) {
    if (!course) return null;
    const units = unitId ? course.units.filter((u) => u.id === unitId) : [];
    return nextLesson(units.length ? units : course.units, openView(progress, course));
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
    return lessonAccess(course, after.lesson.id, progress, access).open ? after.lesson : null;
}
