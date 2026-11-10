// Endless reading practice (js/reading-lines.js; docs/sight-singing-plan.md, slice 5): only for a
// singer who has started Sight-singing and is past their first week; each line two whole bars, inside
// the singer's range, at the level reached, starting on the chord and ending on do; the same seed the
// same line, a new seed a new one.
import { readingLevel, readingLine } from '/js/reading-lines.js';
import { assert, test } from './harness.js';

const DAY = 86400000;
const progress = (lessons, startedAt = Date.now() - 30 * DAY) => ({ startedAt, courses: { 'sight-singing': { lessons } } });
const done = { stars: 2 };

test('reading lines: the level is the highest unit sung, none before the course or in the first week', () => {
    assert(readingLevel({}) === null && readingLevel(progress({})) === null, 'not started');
    assert(readingLevel(progress({ 'ss-1-do': done })) === 1 && readingLevel(progress({ 'ss-1-do': done, 'ss-3-long': done })) === 3, 'by unit');
    assert(readingLevel(progress({ 'ss-2-chord': { stars: 0 } })) === null, 'a lesson opened but not sung');
    assert(readingLevel(progress({ 'ss-1-do': done }, Date.now() - 2 * DAY)) === null, 'never in the first week');
    assert(readingLevel(progress({ 'ss-3-long': done, 'ss-5-la': done })) === 5, 'Further from do: level 5');
    assert(readingLevel(progress({ 'ss-5-la': done, 'ss-4-part': done })) === 5, 'and still 5 after Your part in a chord (ss-4), which comes after it');
});

test('reading lines: two whole bars, in range, on the chord to do, steps only at level 1, rhythm from level 3', () => {
    const range = { low: 52, high: 64 };
    for (let level = 1; level <= 4; level++) {
        if (level >= 2) for (let seed = 1; seed <= 60; seed++) {
            const steps = readingLine({ level, seed }).notes.map((n) => n.step);
            assert(steps.every((d) => ![-3, -1, 9].includes(d)), `level ${level} seed ${seed}: low la, low ti or la before Further from do (${steps})`);
        }
        const seen = new Set();
        for (let seed = 1; seed <= 60; seed++) {
            const line = readingLine({ level, seed, home: 57, range });
            const end = Math.max(...line.notes.map((n) => n.beat + n.length));
            const rests = line.notes.reduce((gap, n, i) => gap + (i ? n.beat - (line.notes[i - 1].beat + line.notes[i - 1].length) : n.beat), 0);
            assert(end <= 8 && end + (8 - end) === 8 && (end === 8 || level >= 3), `level ${level} seed ${seed}: ends at ${end}`);
            assert(line.notes.every((n) => 57 + n.step >= range.low && 57 + n.step <= range.high), `level ${level} seed ${seed}: out of range`);
            assert(line.notes.at(-1).step === 0 && [0, 4, 7].includes(line.notes[0].step), `level ${level} seed ${seed}: from the chord to do`);
            if (level === 1) assert(line.notes.every((n) => n.step >= 0 && n.step <= 7) && line.notes.every((n, i) => !i || [1, 2].includes(Math.abs(n.step - line.notes[i - 1].step)) || i === line.notes.length - 1), `level 1 seed ${seed}: steps only`);
            if (level < 3) assert(!line.staff.rhythm && rests === 0, `level ${level}: no rhythm yet`);
            else assert(line.staff.rhythm && line.staff.count === 4 && line.staff.time === '4/4', `level ${level}: reading time`);
            assert(line.score === 'read' && line.guide === false && line.hearFirst, 'a cold read');
            seen.add(JSON.stringify(line.notes));
        }
        assert(seen.size > 20, `level ${level}: ${seen.size} different lines from 60 seeds`);
    }
    assert(JSON.stringify(readingLine({ level: 2, seed: 7 })) === JSON.stringify(readingLine({ level: 2, seed: 7 })), 'the same seed, the same line');
    const narrow = readingLine({ level: 2, seed: 3, home: 57, range: { low: 56, high: 61 } });
    assert(narrow.notes.every((n) => 57 + n.step >= 56 && 57 + n.step <= 61), 'a narrow range still fits');
});

test('reading lines: level 5, Further from do: la to la, a half step in every major line, minor lines home on la', () => {
    const range = { low: 45, high: 69 };
    let minors = 0;
    let toFa = 0;
    const seen = new Set();
    for (let seed = 1; seed <= 200; seed++) {
        const line = readingLine({ level: 5, seed, home: 57, range });
        const steps = line.notes.map((n) => n.step);
        const minor = line.drone.step === -3;
        assert(line.tempo === 72 && line.staff.count === 4 && line.staff.rhythm && line.staff.names === 'off', `seed ${seed}: 72 with the count-in, names off`);
        assert(line.notes.every((n) => 57 + n.step >= range.low && 57 + n.step <= range.high), `seed ${seed}: out of range`);
        if (minor) {
            minors++;
            if (steps.includes(5)) toFa++;
            assert(steps.every((d) => d >= -3 && d <= 5), `seed ${seed}: a minor line from la to fa at most (${steps})`);
            assert(steps.at(-1) === -3 && [-3, 0, 4].includes(steps[0]), `seed ${seed}: from the la chord home to la (${steps})`);
        } else {
            assert(line.drone.step === 0 && steps.every((d) => d >= -5 && d <= 9), `seed ${seed}: a major line from the so below to la (${steps})`);
            assert(steps.at(-1) === 0 && [0, 4, 7].includes(steps[0]), `seed ${seed}: from the do chord home to do (${steps})`);
            const half = steps.some((p, i) => i && ((p === 4 && steps[i - 1] === 5) || (p === 5 && steps[i - 1] === 4) || (p === -1 && steps[i - 1] === 0) || (p === 0 && steps[i - 1] === -1)));
            assert(half, `seed ${seed}: a major line crosses a half step (${steps})`);
        }
        seen.add(JSON.stringify(line.notes));
    }
    assert(minors > 70 && minors < 130, `about half minor: ${minors} of 200`);
    assert(toFa > 0 && toFa < minors / 2, `minor lines now and then up to fa, most within la to mi: ${toFa} of ${minors}`);
    assert(seen.size > 100, `${seen.size} different lines from 200 seeds`);
    assert(JSON.stringify(readingLine({ level: 5, seed: 9 })) === JSON.stringify(readingLine({ level: 5, seed: 9 })), 'the same seed, the same line');
    for (let seed = 1; seed <= 40; seed++) {
        const narrow = readingLine({ level: 5, seed, home: 57, range: { low: 55, high: 62 } });
        assert(narrow.notes.every((n) => 57 + n.step >= 55 && 57 + n.step <= 62), `seed ${seed}: a narrow range (no low la) still fits`);
        assert(narrow.drone.step === 0 && narrow.notes.at(-1).step === 0, `seed ${seed}: with no room for la, a major line`);
    }
});

test('reading lines: the workout takes one in the review\'s place every other day, keeping its length', async () => {
    const { pickWorkout } = await import('/js/workout.js');
    const lessons = [{ id: 'a', exercise: { tempo: 72, notes: [{ beat: 0, length: 1, step: 0 }] } }];
    const progress = { home: 57, range: { low: 50, high: 66 }, lessons: { a: { stars: 1 } }, recent: [] };
    const plain = pickWorkout(progress, lessons);
    const even = pickWorkout(progress, lessons, null, { level: 2, day: 100 });
    const odd = pickWorkout(progress, lessons, null, { level: 2, day: 101 });
    assert(plain.steps.map((s) => s.kind).join() === 'warmUp,weakSpot,review', 'as it was without Sight-singing');
    assert(even.steps.map((s) => s.kind).join() === 'warmUp,weakSpot,read' && even.steps[2].exercise.score === 'read', 'a line to read on an even day');
    assert(odd.steps.map((s) => s.kind).join() === 'warmUp,weakSpot,review', 'the review on an odd day');
    const noReview = pickWorkout({ ...progress, lessons: {} }, lessons, null, { level: 1, day: 100 });
    assert(noReview.steps.map((s) => s.kind).join() === 'warmUp,weakSpot', 'never an extra step: with nothing to review, no line either');
});
