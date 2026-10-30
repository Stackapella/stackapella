// The Plus courses (js/courses.js): each course's own progress, the gates (staff before launch,
// lesson 1 free, units by stars), merging two devices, restarting one course, and the course files
// themselves (every lesson id unique, prefixed, and shaped like the free course's).
import { courseLessons, courseOf, courseSummary, courseView, courseVisible, lessonAccess, lessonAfter, lessonFree, mergeCourses, nextInCourse, recordCourseLesson, restartedCourseProgress } from '/js/courses.js';
import { emptyProgress, mergeProgress, recordLesson } from '/js/course.js';
import { stepOf } from '/js/lesson-steps.js';
import { assert, test } from './harness.js';

const course = {
    id: 'vibrato',
    units: [
        { id: 'v1', lessons: [{ id: 'vib-a' }, { id: 'vib-b' }, { id: 'vib-c' }] },
        { id: 'v2', lessons: [{ id: 'vib-d' }, { id: 'vib-e' }] },
    ],
};

test('plus courses: a course keeps its own lessons, apart from the free course\'s', () => {
    let p = recordLesson(emptyProgress(), 'a', { stars: 2, percent: 80 });
    p = recordCourseLesson(p, 'vibrato', 'vib-a', { stars: 3, percent: 95 }, 1000);
    assert(p.lessons.a.stars === 2 && !p.lessons['vib-a'], 'the free course\'s lessons are untouched');
    assert(p.courses.vibrato.lessons['vib-a'].stars === 3, 'kept in the course\'s own place');
    p = recordCourseLesson(p, 'vibrato', 'vib-a', { stars: 1, percent: 40 }, 2000);
    assert(p.courses.vibrato.lessons['vib-a'].stars === 3 && p.courses.vibrato.lessons['vib-a'].bestPercent === 95, 'a replay keeps the best');
    const view = courseView(p, 'vibrato');
    assert(view.lessons['vib-a'] && !view.lessons.a && view.testedOut.length === 0, 'the view holds only the course\'s lessons');
});

test('plus courses: staff see a live course before launch; the feature opens it to everyone', () => {
    const live = { id: 'vibrato', status: 'live' };
    assert(!courseVisible(live, null), 'signed out: none yet');
    assert(!courseVisible(live, null, { plusCourses: false }), 'signed out, the feature off: none');
    assert(courseVisible(live, null, { plusCourses: true }), 'signed out, the feature on: a guest can try it');
    assert(!courseVisible(live, { plan: 'plus', staff: null }), 'Plus alone, before launch: hidden');
    assert(courseVisible(live, { plan: 'free', staff: 'admin' }), 'staff, any plan');
    assert(courseVisible(live, { plan: 'free', staff: null, plusCourses: true }), 'once the feature is on');
    assert(!courseVisible({ id: 'counterpoint', status: 'soon' }, { staff: 'admin' }), 'a course coming soon stays hidden');
});

test('plus courses: lesson 1 is free, the rest need Plus; units open at two thirds of the stars', () => {
    let p = emptyProgress();
    assert(lessonFree(course, 'vib-a') && !lessonFree(course, 'vib-b'), 'only the first is free');
    assert(lessonAccess(course, 'vib-a', p).open && !lessonAccess(course, 'vib-a', p).plusOnly, 'lesson 1 for anyone');
    assert(lessonAccess(course, 'vib-b', p).plusOnly, 'lesson 2 needs Plus');
    assert(!lessonAccess(course, 'vib-b', p, { plus: true }).plusOnly && !lessonAccess(course, 'vib-b', p, { staff: true }).plusOnly, 'Plus, or staff trying it');
    assert(!lessonAccess(course, 'vib-d', p, { plus: true }).open, 'unit 2 waits for unit 1\'s stars');
    assert(lessonAccess(course, 'vib-d', p, { staff: true }).open, 'staff preview every unit');
    for (const id of ['vib-a', 'vib-b', 'vib-c']) p = recordCourseLesson(p, 'vibrato', id, { stars: 2, percent: 75 });
    assert(lessonAccess(course, 'vib-d', p, { plus: true }).open, '6 of 9 opens it');
    // The free course's stars never open a Plus course's unit.
    let q = emptyProgress();
    for (const id of ['vib-a', 'vib-b', 'vib-c']) q = recordLesson(q, id, { stars: 3, percent: 95 });
    assert(!lessonAccess(course, 'vib-d', q, { plus: true }).open, 'only the course\'s own stars count');
    assert(!lessonAccess(course, 'nope', p).open, 'a lesson not in the course');
});

test('plus courses: the next lesson, the lesson after, and the card\'s summary', () => {
    let p = emptyProgress();
    assert(nextInCourse(course, p).lesson.id === 'vib-a', 'starts at lesson 1');
    p = recordCourseLesson(p, 'vibrato', 'vib-a', { stars: 1, percent: 50 });
    assert(nextInCourse(course, p).lesson.id === 'vib-b', 'then lesson 2');
    assert(lessonAfter(course, 'vib-a', p) === null, 'free plan: nothing after the free lesson');
    assert(lessonAfter(course, 'vib-a', p, { plus: true }).id === 'vib-b', 'Plus: lesson 2');
    assert(lessonAfter(course, 'vib-c', p, { plus: true }) === null, 'unit 2 still closed');
    const s = courseSummary(course, p);
    assert(s.done === 1 && s.total === 5 && s.stars === 1 && s.started, `summary: ${JSON.stringify(s)}`);
    assert(courseOf({ vibrato: course }, 'vib-e') === course && courseOf({ vibrato: course }, 'a') === null, 'the course of a lesson');
    assert(courseLessons(course)[3].unitIndex === 1 && courseLessons(course)[3].place === 3, 'flat, with unit and place');
});

test('plus courses: two devices merge each course lesson by lesson; restarting one keeps the rest', () => {
    const mine = { vibrato: { lessons: { 'vib-a': { stars: 3, bestPercent: 92, at: 5 } } } };
    const theirs = { vibrato: { lessons: { 'vib-a': { stars: 2, bestPercent: 95, at: 9 }, 'vib-b': { stars: 1, bestPercent: 40, at: 3 } } }, counterpoint: { lessons: { 'cp-a': { stars: 2, bestPercent: 70, at: 1 } } } };
    const merged = mergeCourses(mine, theirs);
    const a = merged.vibrato.lessons['vib-a'];
    assert(a.stars === 3 && a.bestPercent === 95 && a.at === 9, `best of each: ${JSON.stringify(a)}`);
    assert(merged.vibrato.lessons['vib-b'] && merged.counterpoint.lessons['cp-a'], 'nothing lost from either');
    assert(Object.keys(mergeCourses(undefined, undefined)).length === 0, 'none on either');
    let p = { ...emptyProgress(), xp: { total: 120, days: {} }, courses: merged };
    p = restartedCourseProgress(p, 'vibrato');
    assert(!p.courses.vibrato && p.courses.counterpoint && p.xp.total === 120, 'only that course goes');
});

test('plus courses: the course files are well formed, every lesson id unique and prefixed', async () => {
    const catalogue = await (await fetch('/course/plus/courses.json')).json();
    const free = await (await fetch('/course/course.json')).json();
    const seen = new Set(free.units.flatMap((u) => u.lessons.map((l) => l.id)));
    assert(Array.isArray(catalogue.courses) && catalogue.courses.length > 0, 'a list of courses');
    for (const entry of catalogue.courses) {
        assert(entry.id && entry.title?.en && entry.line?.en && ['live', 'soon'].includes(entry.status), `${entry.id}: id, title, line and status`);
        if (!entry.file) continue;
        const data = await (await fetch(`/course/plus/${entry.file}`)).json();
        assert(data.id === entry.id && data.units.length > 0, `${entry.id}: its file`);
        for (const unit of data.units) {
            assert(unit.id && unit.title?.en && unit.lessons.length > 0, `${entry.id}/${unit.id}: a unit`);
            for (const lesson of unit.lessons) {
                assert(lesson.id.startsWith(`${entry.prefix}-`), `${lesson.id}: starts with ${entry.prefix}-`);
                assert(!seen.has(lesson.id), `${lesson.id}: unique across every course`);
                seen.add(lesson.id);
                assert(lesson.title?.en && lesson.steps.length > 0, `${lesson.id}: a title and steps`);
                for (const step of lesson.steps) assert((['learn', 'try', 'play', 'home'].includes(step.kind) || !!stepOf(step.kind)), `${lesson.id}: a known step (${step.kind})`);
            }
        }
    }
});

test('plus courses: two devices\' progress keeps each Plus course, and adds nothing for a learner with none', () => {
    const a = recordCourseLesson(emptyProgress(), 'vibrato', 'vib-a', { stars: 2, percent: 80 }, 1);
    const b = recordCourseLesson(emptyProgress(), 'vibrato', 'vib-b', { stars: 3, percent: 95 }, 2);
    const merged = mergeProgress(a, b);
    assert(merged.courses.vibrato.lessons['vib-a'].stars === 2 && merged.courses.vibrato.lessons['vib-b'].stars === 3, 'both devices\' lessons');
    assert(!('courses' in mergeProgress(emptyProgress(), emptyProgress())), 'no courses key appears from nowhere (a sync would differ every time)');
});

test('plus courses: listen and choose counts each round\'s first answer, and a clip is its notes over its drone', async () => {
    const { choosePercent, playableRounds, renderClip } = await import('/js/components/choose.js');
    const waiting = { clips: [{ clip: 'breath.onset-hard' }, { notes: [{ beat: 0, length: 1, step: 0 }] }], answer: 0 };
    assert(playableRounds([waiting, { clips: [{ notes: [{ beat: 0, length: 1, step: 0 }] }], answer: 0 }]).length === 1, 'a round waiting for a recording is left out');
    const rounds = [{ answer: 0 }, { answer: 1 }, { answer: 2 }, { answer: 0 }];
    assert(choosePercent(rounds, [0, 1, 2, 0]) === 100 && choosePercent(rounds, [0, 0, 2, 1]) === 50, 'right of all');
    assert(choosePercent([], []) === null, 'no rounds, no percent');
    const clip = { notes: [{ beat: 0, length: 2, step: 0 }], drone: { step: 0 } };
    const plain = renderClip({ notes: clip.notes }, 57, { tempo: 60, sampleRate: 8000 });
    const under = renderClip(clip, 57, { tempo: 60, sampleRate: 8000 });
    assert(under.length >= plain.length && under.some((v, i) => v !== plain[i]), 'the drone is mixed in');
    const scooped = renderClip({ notes: [{ beat: 0, length: 2, step: 0, style: 'scoop' }] }, 57, { tempo: 60, sampleRate: 8000 });
    assert(scooped.some((v, i) => v !== plain[i]), 'a styled note is sung with its shape (a scoop), not plain');
});

test('plus courses: a self-check is done once recorded and every question answered, never scored', async () => {
    const { modelBeats, selfCheckDone } = await import('/js/components/selfcheck.js');
    const { isScored, stepOf } = await import('/js/lesson-steps.js');
    const qs = [{ en: 'a' }, { en: 'b' }];
    assert(!selfCheckDone(false, qs, { 0: 'yes', 1: 'yes' }), 'not before recording');
    assert(!selfCheckDone(true, qs, { 0: 'yes' }), 'not with a question left');
    assert(selfCheckDone(true, qs, { 0: 'yes', 1: 'notYet' }), 'not yet is an answer');
    assert(modelBeats({ notes: [{ beat: 0, length: 2 }, { beat: 3, length: 1.5 }] }) === 4.5, 'the model\'s length');
    assert(!isScored('selfcheck') && stepOf('selfcheck') && isScored('choose') && isScored('wave') && isScored('play') && !isScored('learn'), 'only real measures give stars');
});

test('plus courses: a course\'s personal values come from whichever device set them later', () => {
    const older = { vibrato: { lessons: {}, personal: { baseline: 6 }, personalAt: 100 } };
    const newer = { vibrato: { lessons: {}, personal: { baseline: 9 }, personalAt: 200 } };
    assert(mergeCourses(older, newer).vibrato.personal.baseline === 9 && mergeCourses(newer, older).vibrato.personal.baseline === 9, 'the newer wins, either way round');
    assert(mergeCourses({ vibrato: { lessons: {} } }, older).vibrato.personal.baseline === 6, 'kept when only one device has them');
    assert(!('personal' in mergeCourses({ vibrato: { lessons: {} } }, { vibrato: { lessons: {} } }).vibrato), 'none appear from nowhere');
});

test('plus courses: every live course\'s lessons are short (5 minutes or less) and end in something sung or answered', async () => {
    const { lessonMinutes } = await import('/js/results.js');
    const { isScored } = await import('/js/lesson-steps.js');
    const catalogue = (await (await fetch('/course/plus/courses.json')).json()).courses.filter((c) => c.status === 'live' && c.file);
    for (const entry of catalogue) {
        const data = await (await fetch(`/course/plus/${entry.file}`)).json();
        for (const lesson of data.units.flatMap((u) => u.lessons)) {
            assert(lessonMinutes(lesson) <= 5, `${lesson.id}: about ${lessonMinutes(lesson)} minutes`);
            assert(lesson.steps.some((s) => isScored(s.kind) || s.kind === 'selfcheck'), `${lesson.id}: something to sing or answer`);
        }
    }
});

test('plus courses: the catalogue lists all 27 in Home\'s and the site\'s three groups, in order, launch courses first, one featured', async () => {
    const { courses } = await (await fetch('/course/plus/courses.json')).json();
    assert(courses.length === 27, `27 courses: ${courses.length}`);
    assert(new Set(courses.map((c) => c.id)).size === 27 && new Set(courses.map((c) => c.slug)).size === 27, 'ids and slugs unique');
    for (const [group, count] of [['skills', 10], ['harmony', 8], ['styles', 9]]) {
        const inGroup = courses.filter((c) => c.group === group).sort((a, b) => a.order - b.order);
        assert(inGroup.length === count && inGroup.every((c, i) => c.order === i + 1), `${group}: ${count}, ordered from 1 (${inGroup.map((c) => c.order)})`);
        assert(inGroup.slice(0, 3).every((c) => c.launch), `${group}: its three launch courses first`);
    }
    assert(courses.filter((c) => c.launch).length === 10, 'ten launch courses');
    assert(courses.filter((c) => c.featured).length === 1, 'one featured course');
    assert(courses.every((c) => c.status === 'soon' || c.file), 'a live course has its file');
});

test('plus courses: every clip a lesson names is a slot in clips.json, and each slot lists where it\'s used', async () => {
    const { clips } = await (await fetch('/course/plus/clips.json')).json();
    const slots = new Map(clips.map((c) => [c.id, c]));
    assert(slots.size === clips.length, 'slot ids unique');
    for (const c of clips) {
        assert(c.sing?.en && c.seconds > 0 && (Number.isFinite(c.pitch?.step) || (Number.isFinite(c.pitch?.low) && Number.isFinite(c.pitch?.high))), `${c.id}: what to sing, how long, what pitch`);
        assert(c.fallback?.guide || c.fallback?.description || c.fallback?.skip === true, `${c.id}: a fallback until it's recorded (or the round is left out)`);
    }
    const { courses } = await (await fetch('/course/plus/courses.json')).json();
    const named = new Map();
    for (const entry of courses.filter((c) => c.file)) {
        const data = await (await fetch(`/course/plus/${entry.file}`)).json();
        for (const lesson of data.units.flatMap((u) => u.lessons)) {
            for (const id of JSON.stringify(lesson).match(/"clip":"([^"]+)"/g) ?? []) {
                const slot = id.slice(8, -1);
                assert(slots.has(slot), `${lesson.id}: clip ${slot} is in clips.json`);
                named.set(slot, [...(named.get(slot) ?? []), lesson.id]);
            }
        }
    }
    for (const [slot, lessons] of named) assert(lessons.every((l) => slots.get(slot).used.includes(l)), `${slot}: used lists ${lessons}`);
});

test('plus courses: a real singer\'s clip makes its round playable, and a recorded-only model takes its length from seconds', async () => {
    const { playableRounds } = await import('/js/components/choose.js');
    const { modelBeats } = await import('/js/components/selfcheck.js');
    const { courseOfSlot } = await import('/js/course-clips.js');
    const waiting = { clips: [{ clip: 'pop.breathy' }, { notes: [{ beat: 0, length: 1, step: 0 }] }], answer: 0 };
    assert(playableRounds([waiting]).length === 0, 'not recorded yet: left out');
    assert(playableRounds([waiting], { 'pop.breathy': { url: 'https://example.com/a.m4a' } }).length === 1, 'recorded: played');
    assert(modelBeats({ notes: [], seconds: 5 }, 60) === 5 && modelBeats({ seconds: 3 }, 120) === 6, 'seconds at the tempo');
    assert(courseOfSlot('pop.breathy') === 'pop' && courseOfSlot('vibrato.natural') === 'vibrato', 'a slot names its course');
});
