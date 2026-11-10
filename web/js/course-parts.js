// Your part (docs/courses/choir-part-sections-plan.md, "Engine"): a course whose file carries `parts`
// (`{ template, choose: [score order], default }`) lets each singer pick the part they sing, and the
// course's lessons are read for that part: `part: "yours"` in a song spec or a record step becomes the
// pick, a learn step's `byPart` and a sung step's `textByPart` replace its words for that part (the
// step's own words are the default part's, so a reader that doesn't know them still reads a whole
// lesson), and a kept first take (Then and now) is kept per part. A course without `parts` is untouched.

import { voiceType } from './course.js';

/** The course's parts (`{ template, choose, default }`), or null for a course without them. */
export function partsOf(course) {
    const parts = course?.parts;
    return parts && Array.isArray(parts.choose) && parts.choose.length && parts.choose.includes(parts.default) ? parts : null;
}

/**
 * The singer's part for `course`: their pick (kept in progress.courses.<id>.personal.part), else the
 * voice their range suits (course.js voiceType: a suggestion, never a label), else the course's default.
 * Null for a course without parts.
 */
export function coursePart(course, progress, range = null) {
    const parts = partsOf(course);
    if (!parts) return null;
    const picked = progress?.courses?.[course.id]?.personal?.part;
    if (parts.choose.includes(picked)) return picked;
    const suited = voiceType(range);
    return parts.choose.includes(suited) ? suited : parts.default;
}

/** Whether the singer has picked (rather than the suggestion standing in). */
export const partPicked = (course, progress) => !!partsOf(course) && partsOf(course).choose.includes(progress?.courses?.[course.id]?.personal?.part);

/** A kept take's id for a part: the default part keeps the plain id (takes kept before parts stay theirs). */
export const keepIdFor = (id, part, parts) => (!id || !parts || part === parts.default ? id : `${id}:${part}`);

const yours = (spec, part) => (spec?.part === 'yours' ? { ...spec, part } : spec);

/** One step read for `part`. */
function stepFor(step, part, parts) {
    let out = step;
    const words = step.byPart?.[part];
    if (words && part !== parts.default) out = { ...out, headline: words.headline ?? out.headline, steps: words.steps ?? out.steps };
    if (step.textByPart?.[part]) out = { ...out, text: step.textByPart[part] };
    if (step.part === 'yours') out = { ...out, part };
    if (step.listen?.song?.part === 'yours') out = { ...out, listen: { ...step.listen, song: yours(step.listen.song, part) } };
    const exercise = step.exercise;
    if (exercise && (exercise.song?.part === 'yours' || exercise.keep || exercise.thenAndNow)) {
        const next = { ...exercise, song: yours(exercise.song, part) };
        if (!exercise.song) delete next.song;
        if (exercise.keep) next.keep = keepIdFor(exercise.keep, part, parts);
        if (exercise.thenAndNow) {
            next.thenAndNow = keepIdFor(exercise.thenAndNow, part, parts);
            // The other parts' takes, so a finale with only another part's says so ("Lesson 1 was the alto").
            next.thenAndNowOthers = parts.choose.filter((p) => p !== part).map((p) => ({ part: p, id: keepIdFor(exercise.thenAndNow, p, parts) }));
        }
        out = { ...out, exercise: next };
    }
    return out;
}

const resolved = new WeakMap(); // lesson → { part, lesson }: one object per lesson and part, so Vue's caches hold

/** `lesson` read for `part` in `course` (the same lesson when the course has no parts). */
export function lessonForPart(lesson, course, part) {
    const parts = partsOf(course);
    if (!parts || !lesson?.steps || !parts.choose.includes(part)) return lesson;
    const had = resolved.get(lesson);
    if (had?.part === part) return had.lesson;
    const out = { ...lesson, steps: lesson.steps.map((step) => stepFor(step, part, parts)) };
    resolved.set(lesson, { part, lesson: out });
    return out;
}
