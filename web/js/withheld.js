// Songs withheld where the singer is (docs/geo-rights-plan.md): a song whose copyright still runs in the visitor's country
// is left out of the courses there. auth.config's `withheldSongs` lists their slugs (never the country). A course object
// that names such a song (a step's `template`, an inline step's `from`, a boss exercise's `song.template`, a unit's
// `song`) sings its `altSong` instead, a whole replacement in the same shape (the courses lead's format, 2026-11-13);
// without one it's left out. A lesson left with no steps is left out, and a lesson whose steps fell back takes its own
// `altSong` (a partial: the title, fact or intro that named the song). Stars, progress, the next lesson and a unit's
// end read the course as it comes out of here, so a hidden step, lesson or boss never counts or blocks.
// With nothing withheld (the switch off, or a country where all is free), every course is returned as it came.

import { t } from './i18n.js';
import { local } from './storage.js';

const KEPT = 'stackapella.withheldSongs'; // the last auth.config's list, so a returning visit is right from its first frame

/** The song a course object sings: its template, or the song an inline step's notes come from. */
const songOf = (item) => item?.template ?? item?.from ?? null;

/** `item` itself, its altSong when its song is withheld, or null when it has none. */
function swapped(item, withheld) {
    if (!item || !withheld.has(songOf(item))) return { item, fell: false };
    return { item: item.altSong ?? null, fell: true };
}

/** A step: the step, its altSong, or null; and whether it fell back. A boss's song swaps inside its exercise. */
function stepFor(step, withheld) {
    const own = swapped(step, withheld);
    if (!own.item) return { step: null, fell: true };
    let out = own.item;
    let fell = own.fell;
    const song = out.exercise?.song;
    if (song && typeof song === 'object') {
        const inner = swapped(song, withheld);
        if (!inner.item) return { step: null, fell: true };
        if (inner.fell) {
            fell = true;
            // An exercise song's altSong may carry the step's own words (8-boss names Danny Boy in its text).
            const { text, ...alt } = inner.item;
            out = { ...out, exercise: { ...out.exercise, song: alt }, ...(text ? { text } : {}) };
        }
    }
    return { step: out, fell };
}

/** A lesson (or a boss): its steps swapped, the lesson's own altSong applied when one fell back, or null when none is left. */
function lessonFor(lesson, withheld) {
    let fell = false;
    const steps = [];
    for (const step of lesson.steps ?? []) {
        const out = stepFor(step, withheld);
        fell ||= out.fell;
        if (out.step) steps.push(out.step);
    }
    if (!fell) return lesson;
    if (!steps.length) return null;
    return { ...lesson, ...(lesson.altSong ?? {}), steps };
}

/**
 * The course as it's sung where the singer is: `withheld` is a Set (or list) of song slugs. Returns the same object when
 * nothing in it is withheld, so a course with nothing to change is never copied.
 */
export function withholdCourse(course, withheld) {
    const set = withheld instanceof Set ? withheld : new Set(withheld ?? []);
    if (!course?.units || !set.size) return course;
    let changed = false;
    const units = [];
    for (const unit of course.units) {
        const lessons = [];
        for (const lesson of unit.lessons ?? []) {
            const out = lessonFor(lesson, set);
            if (out !== lesson) changed = true;
            if (out) lessons.push(out);
        }
        let next = lessons.length === (unit.lessons ?? []).length && lessons.every((l, i) => l === unit.lessons[i]) ? unit : { ...unit, lessons };
        if (unit.boss) {
            const boss = lessonFor(unit.boss, set);
            if (boss !== unit.boss) {
                changed = true;
                next = { ...next, boss };
                if (!boss) delete next.boss; // a hidden boss never blocks its unit
            }
        }
        if (unit.song) {
            const song = swapped(unit.song, set);
            if (song.fell) {
                changed = true;
                next = { ...next, song: song.item };
                if (!song.item) delete next.song;
            }
        }
        if (next.lessons.length) units.push(next);
        else changed = true;
    }
    return changed ? { ...course, units } : course;
}

/** The songs withheld here: auth.config's, else the last list it sent (empty when neither is known). */
export function withheldSongs(config) {
    const list = config ? config.withheldSongs : readKept();
    return new Set(Array.isArray(list) ? list.filter((slug) => typeof slug === 'string') : []);
}

/** Keeps auth.config's list for the next visit's first frame. */
export function keepWithheld(config) {
    if (!config) return;
    try {
        local.set(KEPT, JSON.stringify(Array.isArray(config.withheldSongs) ? config.withheldSongs : []));
    } catch (error) {
        console.info('The withheld songs were not kept', error?.message ?? error);
    }
}

function readKept() {
    try {
        return JSON.parse(local.get(KEPT) ?? '[]');
    } catch (error) {
        console.info('The kept withheld songs were unreadable', error?.message ?? error);
        return [];
    }
}

/**
 * A song refused where the singer is (templates.show's 451 not_available_here, the backstop when a list was late): a calm
 * line, with the year it opens here when known, never an error and never anything to buy. Null for any other failure.
 */
export function notHereLine(error) {
    if (error?.code !== 'not_available_here') return null;
    const year = Number.isInteger(error.body?.freeFrom) ? error.body.freeFrom : null;
    return year ? t('templates.notHereUntil', { year: String(year) }) : t('templates.notHere'); // a year, never "2,031"
}
