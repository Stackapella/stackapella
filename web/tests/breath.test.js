// Breath and stamina (course/plus/breath/course.json, docs/courses/breath.md): the rules its outline
// promises, checked on the file. Every note sits a fourth below to a fifth above your note; holds
// use sounds the pitch tracker can hear (never a hiss); goals grow from your own first hold, never
// past 15 seconds; no more than three long holds in a row; every lesson starts with why, says what
// its stars measured and fits in 5 minutes.
import { lessonMinutes } from '/js/results.js';
import { holdGoal } from '/js/pitch-skills.js';
import { assert, test } from './harness.js';

const lessons = async () => (await (await fetch('/course/plus/breath/course.json')).json()).units.flatMap((u) => u.lessons);
const VOICED = ['oo', 'ah', 'vv', 'zz', 'mm', 'brr'];

test('breath course: every note sits a fourth below to a fifth above your note', async () => {
    for (const lesson of await lessons()) {
        for (const step of lesson.steps) {
            for (const e of [step.exercise, step.listen].filter(Boolean)) {
                const steps = e.notes ? e.notes.map((n) => n.step) : [e.step ?? 0];
                for (const s of steps) assert(s >= -5 && s <= 7, `${lesson.id}: step ${s} outside the span`);
            }
        }
    }
});

test('breath course: a hold is a voiced sound, its goal grows from your own first hold, never past 15 seconds', async () => {
    let baselines = 0;
    for (const lesson of await lessons()) {
        for (const step of lesson.steps.filter((s) => s.kind === 'breathhold')) {
            const e = step.exercise;
            assert(VOICED.includes(e.lyric), `${lesson.id}: "${e.lyric}" is a sound the tracker can hear`);
            if (e.baseline) baselines++;
            if (e.goal) assert(holdGoal(30, e.goal.plus) === 15 && holdGoal(null, e.goal.plus) === null && e.goal.plus <= 4, `${lesson.id}: its goal grows a little from your own hold, never past 15 seconds`);
            assert(!e.max || e.max <= 20, `${lesson.id}: no hold runs past 20 seconds`);
        }
    }
    assert(baselines >= 1, 'the first lesson sets your baseline');
});

test('breath course: a rest between long holds, and every lesson says why first and what it measured last', async () => {
    for (const lesson of await lessons()) {
        let run = 0;
        let holds = 0;
        for (const step of lesson.steps) {
            run = step.kind === 'breathhold' ? run + 1 : 0;
            assert(run <= 3, `${lesson.id}: four long holds in a row`);
            // A long note of four beats or more in a sung step is a hold too: never two without a rest between.
            const long = step.kind === 'breathhold' || (step.kind === 'play' && step.exercise.notes.some((n) => n.length >= 4));
            holds = long ? holds + 1 : step.kind === 'learn' ? 0 : holds;
            assert(holds <= 1, `${lesson.id}: two long holds with no rest between`);
        }
        assert(lesson.steps[0].kind === 'learn', `${lesson.id}: starts with why`);
        const scored = lesson.steps.filter((s) => ['breathhold', 'play', 'bullseye'].includes(s.kind));
        assert(scored.at(-1)?.measures?.en, `${lesson.id}: its stars say what they measured`);
        assert(lessonMinutes(lesson) <= 5, `${lesson.id}: ${lessonMinutes(lesson)} minutes`);
    }
});
