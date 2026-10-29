// Ear training (course/plus/ear/course.json, docs/courses/ear.md): the rules its outline promises,
// checked on the file. Every note sits a fourth below to a fifth above your note; no note is held
// over 4 seconds; every lesson ends in singing (a listening round never gives the stars); a leap is
// sung up only from the starting notes its size allows; every lesson fits in 5 minutes.
import { lessonMinutes } from '/js/results.js';
import { echoPlan } from '/js/components/bullseye.js';
import { assert, test } from './harness.js';

const course = async () => (await fetch('/course/plus/ear/course.json')).json();
const lessons = async () => (await course()).units.flatMap((u) => u.lessons);

/** Every note a lesson plays or asks for, as `{ step, seconds }`. */
const notesOf = (lesson) => lesson.steps.flatMap((s) => [s.exercise, s.listen].filter(Boolean)).flatMap((e) => {
    const beat = 60 / (e.tempo ?? 72);
    const notes = e.echoes ? [...echoPlan(e).calls, ...echoPlan(e).answers] : e.rounds ? e.rounds.flatMap((r) => r.clips.flatMap((c) => c.notes)) : e.notes;
    return notes.map((n) => ({ step: n.step, seconds: n.length * beat }));
});

// The lowest and highest starting note for a leap up of each size (docs/courses/ear.md, section 1).
const STARTS = { 1: [-5, 4], 2: [-5, 4], 3: [-5, 2], 4: [-5, 2], 5: [-5, 2], 7: [-5, 0], 8: [-5, -3], 9: [-5, -3], 12: [-5, -5] };

test('ear course: every note sits a fourth below to a fifth above your note, none held over 4 seconds', async () => {
    for (const lesson of await lessons()) {
        for (const n of notesOf(lesson)) {
            assert(n.step >= -5 && n.step <= 7, `${lesson.id}: step ${n.step} outside the span`);
            assert(n.seconds <= 4, `${lesson.id}: a ${n.seconds}-second note`);
        }
    }
});

test('ear course: every lesson starts with why, ends in singing that says what it measured, and fits 5 minutes', async () => {
    for (const lesson of await lessons()) {
        assert(lesson.steps[0].kind === 'learn', `${lesson.id}: starts with why`);
        const scored = lesson.steps.filter((s) => ['bullseye', 'play', 'choose'].includes(s.kind));
        assert(['bullseye', 'play'].includes(scored.at(-1)?.kind), `${lesson.id}: its stars come from singing, never a listening round`);
        assert(scored.at(-1).measures?.en, `${lesson.id}: its stars say what they measured`);
        assert(lessonMinutes(lesson) <= 5, `${lesson.id}: ${lessonMinutes(lesson)} minutes`);
    }
});

test('ear course: a leap is only asked from the starting notes its size allows', async () => {
    for (const lesson of await lessons()) {
        for (const step of lesson.steps.filter((s) => s.kind === 'bullseye')) {
            for (const echo of step.exercise.echoes.filter((e) => e.sing?.length === 1 && e.call?.length === 1 && e.call[0] !== e.sing[0])) {
                const [from, to] = [echo.call[0], echo.sing[0]];
                const size = Math.abs(to - from);
                const allowed = STARTS[size];
                assert(allowed, `${lesson.id}: a leap of ${size} semitones has no allowed starts`);
                // A leap up starts only where the table allows; a leap down only has to stay in the span.
                if (to > from) assert(from >= allowed[0] && from <= allowed[1], `${lesson.id}: a leap of ${size} up from ${from}`);
                else assert(from <= 7 && to >= -5, `${lesson.id}: a leap of ${size} down from ${from}`);
            }
        }
    }
});

test('ear course: a leap is sung on its own, from the guide\'s note, never echoing it', async () => {
    for (const lesson of await lessons()) {
        for (const step of lesson.steps.filter((s) => s.kind === 'bullseye' && s.exercise.named)) {
            for (const echo of step.exercise.echoes) assert(!(echo.call?.length === 1 && echo.sing?.length > 1 && echo.sing[0] === echo.call[0]), `${lesson.id}: a named leap echoes the guide's note`);
        }
    }
});

test('ear course: a listening round\'s answer is one of its choices, and every round says why', async () => {
    for (const lesson of await lessons()) {
        for (const step of lesson.steps.filter((s) => s.kind === 'choose')) {
            for (const round of step.exercise.rounds) {
                const choices = round.options?.length ?? round.clips.length;
                assert(round.answer >= 0 && round.answer < choices, `${lesson.id}: "${round.prompt.en}" answers a choice that exists`);
                assert(round.why?.en, `${lesson.id}: "${round.prompt.en}" says why`);
            }
        }
    }
});
