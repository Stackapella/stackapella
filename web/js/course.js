// The course's rules (docs/vocal-coaching-plan.md, "Stars, XP and levels", "The weekly goal", "The
// course map"): which units are open, stars from a score, XP for effort with a daily cap, voice
// levels, and the weekly goal. Pure functions over the learner's progress, unit-tested in
// tests/course.test.js; the progress itself is kept by js/stores/learning.js.

import { tk } from './i18n.js';
import { mergeCourses } from './courses.js';

/** A learner's progress as stored (db.js `learning`, key 'progress'). */
export function emptyProgress() {
    return {
        version: 1,
        startedAt: null, // when the learner first pressed Start (ms)
        home: null, // a comfortable note (MIDI), found in unit 1
        range: null, // { low, high } (MIDI), as found while singing
        rangeSource: null, // 'measured', 'chosen' or 'picked' (stores/learning.js setRange)
        rangeAt: null,
        lessons: {}, // lesson id -> { stars, bestPercent, at }
        testedOut: [], // unit ids an earlier skip-ahead opened (kept open; nothing adds to it now)
        xp: { total: 0, days: {} }, // days: 'YYYY-MM-DD' -> XP earned that day
        practice: {}, // 'YYYY-MM-DD' -> seconds practised that day
        goal: 3, // days a week
        weeks: { streak: 0, last: null }, // weeks in a row meeting the goal, and the last such week
    };
}

/**
 * The course started over (Hillel): every lesson's completion and stars go, so the course opens at
 * its start again. Kept: the comfortable note and the range (finding them again is its own choice),
 * badges, XP, the practice days and the weekly goal, and everything outside the course.
 */
export function restartedCourse(progress) {
    return { ...progress, lessons: {}, testedOut: [] };
}

// ---- Stars ----------------------------------------------------------------------------------------

/**
 * Stars for an exercise or a song section: 1 for finishing, 2 for 70% on pitch, 3 for 90% with the
 * entries on time (when the timing is measured; with an estimate, 90% is enough). "Couldn't hear
 * you" earns nothing: `percent` null.
 */
export function starsFor({ percent, onTime = true, timing = 'measured' }) {
    if (percent === null || percent === undefined) return 0;
    if (percent >= 90 && (onTime || timing !== 'measured')) return 3;
    if (percent >= 70) return 2;
    return 1;
}

/** The strictness for a unit: relaxed through unit 4, then standard (Precise is the learner's choice). */
export function strictnessFor(unitIndex, precise = false) {
    if (precise) return 'precise';
    return unitIndex < 4 ? 'relaxed' : 'standard';
}

// ---- Units ----------------------------------------------------------------------------------------

/** Stars a unit can give: 3 per lesson. */
export function maxStars(unit) {
    return unit.lessons.length * 3;
}

export function starsIn(unit, progress) {
    return unit.lessons.reduce((sum, lesson) => sum + (progress.lessons[lesson.id]?.stars ?? 0), 0);
}

/** Stars needed from a unit to open the next: about two thirds of what it can give. */
export function starsToOpen(unit) {
    return Math.ceil((maxStars(unit) * 2) / 3);
}

/**
 * Whether unit `index` is open: the first always; then when the unit before has two thirds of its
 * stars (or an earlier skip-ahead opened it, before there was one way in). The library and Sing
 * never lock.
 */
export function unitOpen(units, index, progress) {
    if (index <= 0) return true;
    if (progress.testedOut.includes(units[index].id)) return true;
    const before = units[index - 1];
    return unitOpen(units, index - 1, progress) && starsIn(before, progress) >= starsToOpen(before);
}

/**
 * The lesson to do next: the first open lesson without stars, in order, else the last open one.
 * Plus's units count only for `plus`. Everyone starts at lesson 1 (docs/decisions.md, "one way
 * into the course").
 */
export function nextLesson(units, progress, { plus = false } = {}) {
    let last = null;
    for (let i = 0; i < units.length && (plus || !units[i].plus) && unitOpen(units, i, progress); i++) {
        for (const lesson of units[i].lessons) {
            last = { unit: units[i], lesson };
            if (!progress.lessons[lesson.id]?.stars) return last;
        }
    }
    return last;
}

/** A lesson's result kept: the best stars and percent so far. Returns a new progress. */
export function recordLesson(progress, lessonId, { stars, percent }, at = Date.now()) {
    const had = progress.lessons[lessonId] ?? { stars: 0, bestPercent: null };
    const best = { stars: Math.max(had.stars, stars), bestPercent: Math.max(had.bestPercent ?? -1, percent ?? -1), at };
    if (best.bestPercent < 0) best.bestPercent = null;
    return { ...progress, lessons: { ...progress.lessons, [lessonId]: best } };
}

// ---- XP and levels --------------------------------------------------------------------------------

export const DAILY_XP_CAP = 300;
export const XP = { exercise: 10, star: 5, practiceRun: 20, part: 50, harmony: 200, workout: 25 };

/** The day an event counts for, in the learner's own time zone: 'YYYY-MM-DD'. */
export function dayOf(at = Date.now()) {
    const d = new Date(at);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/** XP for an event: { kind: 'exercise', stars } and so on. */
export function xpFor(event) {
    // The combo bonus: +1 for each note lit while a run of 10 or more held (the coordinator's rule, 2026-10-29).
    if (event.kind === 'exercise') return XP.exercise + XP.star * (event.stars ?? 0) + Math.max(0, Math.round(event.bonus ?? 0));
    return XP[event.kind] ?? 0;
}

/** Adds an event's XP, within the day's cap (practice, not grinding). Returns `{ progress, gained }`. */
export function addXp(progress, event, at = Date.now()) {
    const day = dayOf(at);
    const today = progress.xp.days[day] ?? 0;
    const gained = Math.max(0, Math.min(xpFor(event), DAILY_XP_CAP - today));
    return { gained, progress: { ...progress, xp: { total: progress.xp.total + gained, days: { ...progress.xp.days, [day]: today + gained } } } };
}

const LEVEL_NAMES = { 1: tk('course.level.1'), 5: tk('course.level.5'), 10: tk('course.level.10'), 15: tk('course.level.15'), 20: tk('course.level.20') };

/** XP needed to reach `level` (level 1 needs none; each level a little more than the last). */
export function xpForLevel(level) {
    return level <= 1 ? 0 : 50 * (level - 1) * level;
}

/** The voice level for `total` XP: `{ level, name, into, needed }` (XP into this level, and to the next). */
export function levelFor(total) {
    let level = 1;
    while (total >= xpForLevel(level + 1)) level++;
    const named = Object.keys(LEVEL_NAMES).map(Number).filter((n) => n <= level).at(-1);
    return { level, name: LEVEL_NAMES[named], into: total - xpForLevel(level), needed: xpForLevel(level + 1) - xpForLevel(level) };
}

// ---- The weekly goal ------------------------------------------------------------------------------

export const DAY_SECONDS = 5 * 60; // a day counts after 5 minutes of practice (or the workout)

/** Monday of the week that `day` ('YYYY-MM-DD') falls in. */
export function weekOf(day) {
    const [y, m, d] = day.split('-').map(Number);
    const date = new Date(y, m - 1, d);
    date.setDate(date.getDate() - ((date.getDay() + 6) % 7));
    return dayOf(date.getTime());
}

/** Adds practice time; a day that reaches 5 minutes counts toward the week. Returns a new progress. */
export function addPractice(progress, seconds, at = Date.now()) {
    const day = dayOf(at);
    const practice = { ...progress.practice, [day]: (progress.practice[day] ?? 0) + seconds };
    return closeWeek({ ...progress, practice }, day);
}

/** A workout counts its day whatever the minutes. */
export function countDay(progress, at = Date.now()) {
    const day = dayOf(at);
    return closeWeek({ ...progress, practice: { ...progress.practice, [day]: Math.max(progress.practice[day] ?? 0, DAY_SECONDS) } }, day);
}

/** Days this week (Monday to Sunday) that count, for the ring: `{ done, goal, met }`. */
export function weekRing(progress, at = Date.now()) {
    const week = weekOf(dayOf(at));
    const done = Object.entries(progress.practice).filter(([day, seconds]) => weekOf(day) === week && seconds >= DAY_SECONDS).length;
    return { done, goal: progress.goal, met: done >= progress.goal };
}

/**
 * This week's days, Monday first, for the home's day bars (42.png): `[{ day, share, done, today,
 * future }]`, `share` the part of a counted day sung (0 to 1), `done` once it counts.
 */
export function weekDays(progress, at = Date.now()) {
    const monday = new Date(`${weekOf(dayOf(at))}T12:00:00`);
    const today = dayOf(at);
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(monday);
        d.setDate(monday.getDate() + i);
        const day = dayOf(d.getTime());
        const seconds = progress.practice?.[day] ?? 0;
        return { day, share: Math.min(1, seconds / DAY_SECONDS), done: seconds >= DAY_SECONDS, today: day === today, future: day > today };
    });
}

/**
 * Weeks in a row: meeting the goal this week adds one (once); a week missed starts afresh, with no
 * message about it ("New week, fresh start").
 */
function closeWeek(progress, day) {
    const week = weekOf(day);
    const ring = weekRing(progress, new Date(`${day}T12:00:00`).getTime());
    if (!ring.met || progress.weeks.last === week) return progress;
    const previous = new Date(`${week}T12:00:00`);
    previous.setDate(previous.getDate() - 7);
    const continued = progress.weeks.last === dayOf(previous.getTime());
    return { ...progress, weeks: { streak: continued ? progress.weeks.streak + 1 : 1, last: week } };
}

/** Weeks in a row as shown now: a streak that ended before last week shows as none. */
export function weeksInARow(progress, at = Date.now()) {
    const week = weekOf(dayOf(at));
    const last = new Date(`${week}T12:00:00`);
    last.setDate(last.getDate() - 7);
    return progress.weeks.last === week || progress.weeks.last === dayOf(last.getTime()) ? progress.weeks.streak : 0;
}

// ---- Two devices ----------------------------------------------------------------------------------

const maxBy = (a = {}, b = {}) => Object.fromEntries([...new Set([...Object.keys(a), ...Object.keys(b)])].map((k) => [k, Math.max(a[k] ?? 0, b[k] ?? 0)]));

/**
 * Progress from two devices as one (account.learning's 409, and signing in on a second device):
 * the best stars and percent of each lesson, the most XP and practice of each day, the wider range,
 * the earlier start. This device's own choices (the goal, the home note) win when both have one.
 */
export function mergeProgress(mine, theirs) {
    if (!theirs) return mine;
    const lessons = { ...theirs.lessons };
    for (const [id, had] of Object.entries(mine.lessons ?? {})) {
        const other = lessons[id];
        if (!other) {
            lessons[id] = had;
            continue;
        }
        const bestPercent = Math.max(had.bestPercent ?? -1, other.bestPercent ?? -1);
        lessons[id] = { stars: Math.max(had.stars, other.stars), bestPercent: bestPercent < 0 ? null : bestPercent, at: Math.max(had.at ?? 0, other.at ?? 0) };
    }
    const days = maxBy(mine.xp?.days, theirs.xp?.days);
    const sum = Object.values(days).reduce((total, xp) => total + xp, 0);
    const starts = [mine.startedAt, theirs.startedAt].filter(Boolean);
    return {
        ...theirs,
        ...mine,
        startedAt: starts.length ? Math.min(...starts) : null,
        home: mine.home ?? theirs.home ?? null,
        ...mergeRange(mine, theirs),
        lessons,
        // The Plus courses', lesson by lesson (only once either side has one, so nothing else changes).
        ...(mine.courses || theirs.courses ? { courses: mergeCourses(mine.courses, theirs.courses) } : {}),
        keys: { ...theirs.keys, ...mine.keys },
        testedOut: [...new Set([...(mine.testedOut ?? []), ...(theirs.testedOut ?? [])])],
        xp: { total: Math.max(mine.xp?.total ?? 0, theirs.xp?.total ?? 0, sum), days },
        practice: maxBy(mine.practice, theirs.practice),
        weeks: { streak: Math.max(mine.weeks?.streak ?? 0, theirs.weeks?.streak ?? 0), last: [mine.weeks?.last, theirs.weeks?.last].filter(Boolean).sort().at(-1) ?? null },
        counts: maxBy(mine.counts, theirs.counts),
        recent: [...(mine.recent ?? []), ...(theirs.recent ?? [])].filter((r, i, all) => all.findIndex((o) => o.at === r.at) === i).sort((a, b) => b.at - a.at).slice(0, 10),
        lastWorkout: [mine.lastWorkout, theirs.lastWorkout].filter(Boolean).sort().at(-1) ?? null,
        // A badge is earned once: the earlier of the two.
        badges: Object.fromEntries([...new Set([...Object.keys(mine.badges ?? {}), ...Object.keys(theirs.badges ?? {})])].map((id) => [id, Math.min(mine.badges?.[id] ?? Infinity, theirs.badges?.[id] ?? Infinity)])),
    };
}

/**
 * The range two devices agree on: a range picked from a song never beats one sung or set on Your
 * voice; otherwise the newer. Two from before ranges were dated (no `rangeAt`) widen to both, as before.
 */
export function mergeRange(mine, theirs) {
    const rank = (p) => (!p?.range ? -1 : p.rangeSource === 'picked' ? 0 : 1);
    const a = rank(mine);
    const b = rank(theirs);
    const keep = (p) => ({ range: p.range, rangeSource: p.rangeSource ?? null, rangeAt: p.rangeAt ?? null });
    if (a < 0 && b < 0) return { range: null, rangeSource: null, rangeAt: null };
    if (a !== b) return keep(a > b ? mine : theirs);
    if (!mine.rangeAt && !theirs.rangeAt) {
        return { range: { low: Math.min(mine.range.low, theirs.range.low), high: Math.max(mine.range.high, theirs.range.high) }, rangeSource: mine.rangeSource ?? theirs.rangeSource ?? null, rangeAt: null };
    }
    return keep((mine.rangeAt ?? 0) >= (theirs.rangeAt ?? 0) ? mine : theirs);
}

// ---- Your voice ------------------------------------------------------------------------------------

/** The usual ranges of the four voices (MIDI), for a suggestion: never a label you're held to. */
export const VOICE_TYPES = {
    soprano: { low: 60, high: 81 }, // C4 to A5
    alto: { low: 53, high: 74 }, // F3 to D5
    tenor: { low: 48, high: 69 }, // C3 to A4
    bass: { low: 40, high: 64 }, // E2 to E4
};

/**
 * The voice whose usual range a learner's range suits best: the most of it covered, then the
 * nearest middle. Null without a range of at least a fifth (too little to go on).
 */
export function voiceType(range) {
    if (!range || range.high - range.low < 7) return null;
    const middle = (range.low + range.high) / 2;
    const fit = ([, type]) => {
        const overlap = Math.max(0, Math.min(range.high, type.high) - Math.max(range.low, type.low));
        return overlap / (type.high - type.low) - Math.abs(middle - (type.low + type.high) / 2) / 48;
    };
    return Object.entries(VOICE_TYPES).sort((a, b) => fit(b) - fit(a))[0][0];
}
