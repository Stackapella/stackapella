// The learner's course progress (docs/vocal-coaching-plan.md, "Storage"): kept on the device in the
// `learning` store (db.js), synced with the account from M4 (account.learning). The course itself
// is data (web/course/course.json); js/course.js holds its rules.

import { reactive } from 'vue';
import * as db from '../db.js';
import { api } from '../api.js';
import { mockWanted } from '../api-mock.js';
import { addPractice, addXp, countDay, dayOf, emptyProgress, levelFor, mergeProgress, recordLesson, restartedCourse, starsFor, weekRing } from '../course.js';
import { account } from './account.js';
import { BADGES, newBadges, tally, withBadges } from '../badges.js';
import { playSound } from '../sounds.js';
import { t } from '../i18n.js';
import { sayBadges } from '../game/badge-news.js';
import { count, countFor } from '../usage.js';
import { askYes, first, goalMet } from '../funnel.js';
import { courseLessons, courseOf, recordCourseLesson, restartedCourseProgress } from '../courses.js';

export const KEY_REACH = 6; // Lower and Higher go this many semitones either way from the note that fits
// `course` is the free course; `catalogue` lists the Plus courses and `courses` holds each live one's
// data by id (js/courses.js, docs/plus-courses-build-plan.md).
export const learning = reactive({ progress: emptyProgress(), course: null, catalogue: [], courses: {}, coursesLoaded: false, cooldown: null, loaded: false, failed: false, epoch: 0, undoUntil: null });

const UNDO_DAYS = 30;

let loading = null;
// Saving is refused until the stored progress has been read: an empty start must never replace it.
let readOk = false;
// Another tab (the installed app beside a browser tab) saved: read its progress before going on.
const tabs = typeof BroadcastChannel === 'function' ? new BroadcastChannel('stackapella.learning') : null;
if (tabs) tabs.onmessage = () => readProgress();

async function readProgress() {
    try {
        const kept = await db.get('learning', 'progress');
        const { placement, ...rest } = kept ?? {};
        // Before there was one way in, the first run's answer said the course had started.
        const progress = { ...emptyProgress(), ...rest, startedAt: rest.startedAt ?? (placement ? 1 : null) };
        // Before badges: what was already earned is given quietly, once (no burst of toasts).
        learning.progress = progress.badges ? progress : withBadges({ ...progress, badges: {} }, newBadges({ ...progress, badges: {} }));
        // The account's reset count this device has taken (a save from an older one is refused), and
        // a restart's snapshot while it can be undone.
        const reset = (await db.get('learning', 'reset')) ?? {};
        learning.epoch = reset.epoch ?? 0;
        learning.undoUntil = reset.undoUntil && reset.undoUntil > Date.now() ? reset.undoUntil : null;
        readOk = true;
    } catch (error) {
        console.error('Course progress could not be read', error);
    }
}

/**
 * Loads the course and the learner's progress. A course that didn't load (offline, say) is tried
 * again on the next call, and `learning.failed` says so meanwhile.
 */
export function loadLearning() {
    // Not the Plus courses (a first lesson needs none of them, and on a slow line they'd hold up its
    // mic): the pages that show them call loadPlusCourses() themselves.
    loading ??= (async () => {
        const [course] = await Promise.all([fetch('/course/course.json').then((r) => (r.ok ? r.json() : null)).catch(() => null), readProgress()]);
        learning.course = course;
        learning.failed = !course;
        learning.loaded = true;
        if (!course) loading = null;
        return learning;
    })();
    return loading;
}

const getJson = (path) => fetch(path).then((r) => (r.ok ? r.json() : null)).catch(() => null);

let coursesLoading = null;
let coolLoading = null;

/** The cool-down's lesson id (course/cooldown.json, the courses lead's): /cooldown opens it. */
export const COOL_DOWN = 'cool-down-1';

/** The cool-down (voice care's 2-minute, unscored lesson), loaded once, on its own page. */
export function loadCooldown() {
    coolLoading ??= getJson('/course/cooldown.json').then((data) => {
        learning.cooldown = data;
        if (!data) coolLoading = null; // not here (offline, or not yet in the content): asked again next time
        return data;
    });
    return coolLoading;
}
/**
 * The Plus courses, loaded once, by the pages that show them (a phone on a slow line opens its
 * first lesson without them): resolves once `learning.courses` and the catalogue are in.
 */
export function loadPlusCourses() {
    coursesLoading ??= loadCourses().finally(() => {
        learning.coursesLoaded = true;
    });
    return coursesLoading;
}

/**
 * The Plus courses: the catalogue, and each live course's lessons (a missing one is left out), each
 * kept as it arrives, so a course's page shows once its own file is in (Hillel's "blank for a
 * second": on a slow line, all of them is a few megabytes).
 */
async function loadCourses() {
    const catalogue = (await getJson('/course/plus/courses.json'))?.courses ?? [];
    learning.catalogue = catalogue;
    const live = catalogue.filter((c) => c.status === 'live' && c.file);
    await Promise.all(live.map(async (c) => {
        const data = await getJson(`/course/plus/${c.file}`);
        if (data) learning.courses[c.id] = data;
    }));
}

async function save(progress) {
    if (!readOk) {
        console.error('Course progress not saved: what was kept could not be read');
        return;
    }
    // Badges earned by this change: kept once each, and said (between exercises, never mid-take).
    const earned = newBadges(progress);
    progress = withBadges(progress, earned);
    // Several at once (a first lesson often brings three): said together, never stacked over the stars.
    const names = earned.map((id) => t(BADGES.find((b) => b.id === id).name));
    // Kept in either skin; said only in game mode (docs/skins-plan.md: serious mode shows no badges).
    const said = document.documentElement.dataset.skin !== 'serious';
    if (names.length || (progress.weeks?.streak ?? 0) > (learning.progress?.weeks?.streak ?? 0)) playSound('special');
    // One celebration at a time (game/badge-news.js): in a lesson, said once at its end; else one short toast.
    if (said) sayBadges(names);
    goalMet(weekRing(progress).met); // the funnel: the weekly goal met in the first week
    learning.progress = progress;
    try {
        await db.put('learning', JSON.parse(JSON.stringify(progress)), 'progress');
        tabs?.postMessage('saved');
    } catch (error) {
        console.error('Course progress was not saved', error);
    }
    sendSoon();
}

// ---- With the account (account.learning) ----------------------------------------------------------

let sending = null;
let sendTimer = null;
const signedIn = () => !!account.me && !mockWanted();
// The same progress whatever order its keys came back in (the database keeps JSON in its own order).
const canonical = (value) => (Array.isArray(value) ? value.map(canonical) : value && typeof value === 'object' ? Object.fromEntries(Object.keys(value).sort().map((k) => [k, canonical(value[k])])) : value);
const same = (a, b) => JSON.stringify(canonical(a)) === JSON.stringify(canonical(b));

function sendSoon() {
    if (!signedIn()) return;
    clearTimeout(sendTimer);
    sendTimer = setTimeout(() => syncLearning().catch((error) => console.warn('Course progress did not reach the account', error)), 3000);
}

/**
 * Brings the account's progress and this device's together (signing in, coming back to the app,
 * after a change): merged, kept here, and sent when the account's copy lacks something. A stale
 * version (another device saved first) merges with theirs and goes again.
 */
export async function syncLearning() {
    if (!signedIn()) return;
    if (sending) return sending;
    sending = (async () => {
        await loadLearning();
        if (!readOk) return;
        let theirs = (await api.account.learning.show()).data;
        // The course was started over (here or on another device) since this device last heard:
        // the account's progress replaces this one, never merged (a merge would bring it all back).
        if ((theirs.epoch ?? 0) !== learning.epoch) await takeTheirs(theirs);
        for (let attempt = 0; attempt < 3; attempt++) {
            const merged = mergeProgress(learning.progress, Object.keys(theirs.progress ?? {}).length ? { ...emptyProgress(), ...theirs.progress } : null);
            if (!same(merged, learning.progress)) {
                learning.progress = merged;
                await db.put('learning', JSON.parse(JSON.stringify(merged)), 'progress');
                tabs?.postMessage('saved');
            }
            if (same(merged, theirs.progress)) return;
            try {
                await api.account.learning.update({ changeId: crypto.randomUUID(), version: theirs.version, epoch: learning.epoch, progress: JSON.parse(JSON.stringify(merged)) });
                return;
            } catch (error) {
                if (error?.code === 'learning_reset' && error.current) {
                    await takeTheirs(error.current);
                    return;
                }
                if (error?.code !== 'version_conflict' || !error.current) throw error;
                theirs = error.current;
            }
        }
    })().finally(() => (sending = null));
    return sending;
}

/** The account's progress as this device's, after a start-over (theirs is newer by its epoch). */
async function takeTheirs(theirs) {
    learning.progress = { ...emptyProgress(), ...(theirs.progress ?? {}) };
    learning.epoch = theirs.epoch ?? 0;
    learning.undoUntil = theirs.undoUntil ? Date.parse(theirs.undoUntil) : null;
    await db.put('learning', JSON.parse(JSON.stringify(learning.progress)), 'progress');
    await db.put('learning', { epoch: learning.epoch, undoUntil: learning.undoUntil, snapshot: null }, 'reset');
    tabs?.postMessage('saved');
}

/**
 * Starts the course over (Hillel): lessons and stars go; the note, range, badges, XP and songs
 * stay. Undoable for 30 days: the old progress is kept (here, and on the account by its reset).
 */
export async function restartCourse() {
    await loadLearning();
    const before = JSON.parse(JSON.stringify(learning.progress));
    const until = Date.now() + UNDO_DAYS * 86400 * 1000;
    if (signedIn()) {
        const { data } = await api.account.learning.reset({ body: { changeId: crypto.randomUUID() } });
        await takeTheirs({ ...data, progress: data.progress && Object.keys(data.progress).length ? data.progress : restartedCourse(before) });
        await db.put('learning', { epoch: learning.epoch, undoUntil: learning.undoUntil ?? until, snapshot: before }, 'reset');
        learning.undoUntil ??= until;
        return;
    }
    learning.progress = restartedCourse(before);
    learning.undoUntil = until;
    await db.put('learning', JSON.parse(JSON.stringify(learning.progress)), 'progress');
    await db.put('learning', { epoch: learning.epoch, undoUntil: until, snapshot: before }, 'reset');
    tabs?.postMessage('saved');
}

/** Brings back the course as it was before starting over (within 30 days). */
export async function undoRestart() {
    if (signedIn()) {
        const { data } = await api.account.learning.undoReset({ body: { changeId: crypto.randomUUID() } });
        await takeTheirs({ ...data, undoUntil: null });
        return;
    }
    const kept = await db.get('learning', 'reset');
    if (!kept?.snapshot) return;
    learning.progress = { ...emptyProgress(), ...kept.snapshot };
    learning.undoUntil = null;
    await db.put('learning', JSON.parse(JSON.stringify(learning.progress)), 'progress');
    await db.put('learning', { epoch: learning.epoch, undoUntil: null, snapshot: null }, 'reset');
    tabs?.postMessage('saved');
}

/** Units and lessons, flat, with each lesson's unit and its place. */
export function allLessons() {
    return (learning.course?.units ?? []).flatMap((unit, unitIndex) => unit.lessons.map((lesson, index) => ({ unit, unitIndex, lesson, index })));
}

/**
 * A lesson by id, in the free course or a Plus course. A Plus course's entry carries `course` (its
 * data) and its places within that course; the free course's has `course: null`.
 */
export function findLesson(id) {
    const free = allLessons().find((entry) => entry.lesson.id === id);
    if (free) return { ...free, course: null };
    // The cool-down (docs/voice-care-plan.md): one standalone, unscored lesson, outside every course.
    const cool = learning.cooldown?.units?.[0];
    if (cool?.lessons?.[0]?.id === id) return { unit: cool, unitIndex: 0, lesson: cool.lessons[0], index: 0, course: null, cooldown: true };
    const course = courseOf(learning.courses, id);
    const entry = course ? courseLessons(course).find((l) => l.lesson.id === id) : null;
    return entry ? { ...entry, course } : null;
}

/** A new record: `percent` beat a lesson's best kept from an earlier try (a first try is never a record). */
export const beatsBest = (had, percent) => had?.bestPercent !== null && had?.bestPercent !== undefined && percent !== null && percent !== undefined && percent > had.bestPercent;

/**
 * A lesson finished with its result: the best stars kept, XP added (with the day's cap), and the
 * practice time counted toward the weekly goal. Returns what to celebrate (`newBest`: it beat the
 * lesson's best percent from an earlier try).
 */
export async function finishLesson(lessonId, { percent, onTime = true, timing = 'estimated', seconds = 0, stars = null, bonus = 0 }) {
    const level = levelFor(learning.progress.xp.total).level;
    const entry = findLesson(lessonId);
    // A Plus course's lessons are kept in its own place (progress.courses.<id>.lessons).
    const kept = (progress) => (entry?.course ? progress.courses?.[entry.course.id]?.lessons ?? {} : progress.lessons);
    const unitDone = (progress) => !!entry && entry.unit.lessons.every((l) => kept(progress)[l.id]);
    const unitWasDone = unitDone(learning.progress);
    const earned = stars ?? starsFor({ percent, onTime, timing });
    const newBest = beatsBest(kept(learning.progress)[lessonId], percent);
    let progress = entry?.course ? recordCourseLesson(learning.progress, entry.course.id, lessonId, { stars: earned, percent }) : recordLesson(learning.progress, lessonId, { stars: earned, percent });
    const xp = addXp(progress, { kind: 'exercise', stars: earned, bonus });
    progress = addPractice(xp.progress, seconds);
    await save(progress);
    count('lesson_done'); // once a finish: here, where it's recorded, never as the end screen shows again
    countFor(entry?.course ? 'pl_d' : 'fl_d', entry?.course ? entry.course.id : lessonId);
    if (!unitWasDone && unitDone(progress)) count('unit_done');
    if (!entry?.course) {
        // The funnel's first lessons and first unit (the free course only), each counted once on this device.
        const sung = Object.values(progress.lessons).filter((l) => l?.stars).length;
        if (sung >= 1) first('f_lesson1');
        if (sung >= 2) first('f_lesson2');
        if (sung >= 3) first('f_lesson3');
        if (entry && learning.course?.units?.[0] === entry.unit && unitDone(progress)) first('f_unit1');
    }
    const now = levelFor(progress.xp.total);
    return { stars: earned, xp: xp.gained, level: now, levelledUp: now.level > level, newBest };
}

const COUNTED = { part: 'parts', harmony: 'harmonies' };

/** XP for practising in Sing, recording a part, or finishing a harmony (feeds the weekly goal too). */
export async function earn(kind, { seconds = 0 } = {}) {
    await loadLearning();
    let { progress, gained } = addXp(learning.progress, { kind });
    if (COUNTED[kind]) progress = tally(progress, COUNTED[kind], { add: 1 });
    await save(seconds ? addPractice(progress, seconds) : progress);
    return gained;
}

/**
 * Something done that a badge counts: `add` to a count (notesLit, excellentTakes, shares, choirs,
 * openParts), or `best` for the highest (bestCombo).
 */
export async function counted(name, amount = {}) {
    await loadLearning();
    await save(tally(learning.progress, name, amount));
}

export { BADGES };

/** Time spent singing, toward the weekly goal (a day counts at 5 minutes). */
export async function practised(seconds) {
    if (!(seconds > 0)) return;
    await loadLearning();
    await save(addPractice(learning.progress, seconds));
}

/** Restart this course (a Plus course): only its lessons and stars go; the rest stays. */
export async function restartPlusCourse(courseId) {
    await loadLearning();
    await save(restartedCourseProgress(learning.progress, courseId));
}

/**
 * A Plus course's own values (a learner's baseline: courses-skills' Breath and stamina), merged into
 * `progress.courses.<id>.personal` and dated, so two devices keep the newer (js/courses.js).
 */
export async function setCoursePersonal(courseId, patch, at = Date.now()) {
    await loadLearning();
    const had = learning.progress.courses?.[courseId] ?? {};
    await save({ ...learning.progress, courses: { ...learning.progress.courses, [courseId]: { ...had, personal: { ...had.personal, ...patch }, personalAt: at } } });
}

/** The first Start: everyone begins at lesson 1. Returns its id. */
export async function startCourse() {
    if (!learning.progress.startedAt) await save({ ...learning.progress, startedAt: Date.now() });
    return learning.course.units[0].lessons[0].id;
}

/** The learner's comfortable note (unit 1); the range so far takes it in (never a guess around it). */
export async function setHome(midi) {
    first('f_note'); // the funnel: the first note found on this device
    const home = Math.round(midi);
    const range = learning.progress.range ?? { low: home, high: home };
    await save({ ...learning.progress, home, range: { low: Math.min(range.low, home), high: Math.max(range.high, home) } });
}

/** Widens the range with notes sung steadily. */
export async function noteSung(low, high) {
    const range = learning.progress.range ?? { low, high };
    if (low >= range.low && high <= range.high) return;
    await save({ ...learning.progress, range: { low: Math.min(range.low, low), high: Math.max(range.high, high) } });
}

/**
 * Every singer has a range once it's known (Hillel, 2026-10-28), kept with the course's progress
 * (and the account's): `rangeSource` says how. 'measured' (sung: Find your voice, Find your range,
 * a range check), 'chosen' (set on Your voice), or 'picked' (a part picked on a song while no range
 * was known, so nothing asks again). `rangeAt` is when. A known range (a fifth or more) means no
 * part or range picker asks again.
 */
export const FIT_SPAN = 7;

/** The stored range when there's enough of it to fit parts to, else null. */
export function knownRange(progress = learning.progress) {
    const range = progress?.range;
    return range && range.high - range.low >= FIT_SPAN ? range : null;
}

/** A range check's answer, or Your voice's Change it ('chosen'): the singer's range from now on. */
export async function setRange(low, high, { source = 'measured' } = {}) {
    await loadLearning();
    await save({ ...learning.progress, range: { low: Math.round(low), high: Math.round(high) }, rangeSource: source, rangeAt: Date.now() });
}

/** A part picked while no range is known: its range is kept as the singer's ('picked'). Never over a known one. */
export async function pickRange(low, high) {
    await loadLearning();
    if (knownRange()) return false;
    await setRange(low, high, { source: 'picked' });
    return true;
}

export async function setGoal(days) {
    first('f_goal');
    askYes('goal');
    await save({ ...learning.progress, goal: days });
}

export async function workoutDone() {
    const { progress } = addXp(learning.progress, { kind: 'workout' });
    await save({ ...tally(countDay(progress), 'workouts', { add: 1 }), lastWorkout: dayOf() });
}

/** Lower or Higher on a lesson: its exercises move by this many semitones there from now on. */
export async function setLessonKey(lessonId, shift) {
    await save({ ...learning.progress, keys: { ...learning.progress.keys, [lessonId]: Math.max(-KEY_REACH, Math.min(KEY_REACH, shift)) } });
}

/** Too high or Too low in the workout: its exercises move by this many semitones from now on. */
export async function setWorkoutShift(shift) {
    await save({ ...learning.progress, workoutShift: Math.max(-7, Math.min(7, shift)) });
}

const RECENT = 10;
/** A scored take or exercise, for the workout's weak spot: what stood out, newest first. */
export async function remember({ percent, insights = [], tendency = null }) {
    if (percent === null || percent === undefined) return;
    await loadLearning();
    const recent = [{ at: Date.now(), percent, insights, tendency }, ...(learning.progress.recent ?? [])].slice(0, RECENT);
    await save({ ...learning.progress, recent });
}
