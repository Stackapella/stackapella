// Today's workout (js/workout.js): the warm-up fits your range, the weak spot is the one that came
// up most in your recent singing, the review is your lowest-starred lesson, and free means once a day.
import { exerciseSeconds, pickWorkout, shifted, stepSeconds, WARM_UP_ROUNDS, weakSpot, workoutUsed } from '/js/workout.js';
import { dayOf } from '/js/course.js';
import { assert, test } from './harness.js';

const lessons = [{ id: 'a', exercise: { notes: [{ beat: 0, length: 1, step: 0 }] } }, { id: 'b', exercise: { notes: [] } }, { id: 'c', exercise: { notes: [] } }];

test('workout: the weakest spot from your recent singing, the newest breaking a tie', () => {
    assert(weakSpot([{ insights: ['scoops'] }, { insights: ['flatHigh', 'late'] }, { insights: ['flatHigh'] }]) === 'flatHigh', 'the most common');
    assert(weakSpot([{ insights: ['late'] }, { insights: ['scoops'] }]) === 'late', 'a tie: the newest');
    assert(weakSpot([]) === null && weakSpot([{ insights: ['unknown'] }]) === null, 'nothing to go on');
});

test('workout: a warm-up, a weak spot, then the lesson with the fewest stars (started, not perfect)', () => {
    const progress = { range: { low: 55, high: 72 }, home: 60, lessons: { a: { stars: 3 }, b: { stars: 1 }, c: { stars: 2 } }, recent: [{ insights: ['wavering'] }] };
    const { steps, song } = pickWorkout(progress, lessons, { projectId: 'p1', title: 'Amazing Grace' });
    assert(steps.map((s) => s.kind).join() === 'warmUp,weakSpot,review', steps.map((s) => s.kind).join());
    assert(steps[1].insight === 'wavering' && steps[2].lessonId === 'b' && song.projectId === 'p1', 'the right ones');
    assert(Math.max(...steps[0].exercise.notes.map((n) => n.step)) + WARM_UP_ROUNDS - 1 <= 72 - 60, 'the warm-up stays in range, its last round too');
    const fresh = pickWorkout({ range: null, home: null, lessons: {}, recent: [] }, lessons);
    assert(fresh.steps.length === 2 && fresh.steps[1].insight === null, 'a first workout: a warm-up and a steady hold');
});

test('workout: free is one a day; Too high and Too low move it a semitone', () => {
    assert(workoutUsed({ lastWorkout: dayOf() }) && !workoutUsed({ lastWorkout: '2020-01-01' }) && !workoutUsed({}), 'once a day');
    const moved = shifted({ notes: [{ step: 2 }], drone: { step: 0 } }, -1);
    assert(moved.notes[0].step === 1 && moved.drone.step === -1, 'down a semitone');
});

test('workout: the warm-up climbs five notes in three rounds, or three when the top is close', () => {
    const roomy = pickWorkout({ range: { low: 48, high: 72 }, home: 55, lessons: {}, recent: [] }, lessons).steps[0];
    assert(roomy.rounds === 3 && roomy.climb === 5 && Math.max(...roomy.exercise.notes.map((n) => n.step)) === 7, 'five notes up');
    const close = pickWorkout({ range: { low: 55, high: 66 }, home: 57, lessons: {}, recent: [] }, lessons).steps[0];
    assert(close.climb === 3 && Math.max(...close.exercise.notes.map((n) => n.step)) === 4, 'three notes up near the top');
    assert(pickWorkout({ range: null, home: null, lessons: {}, recent: [] }, lessons).steps[1].rounds === undefined, 'other steps are one round');
});

test('workout: how long a run takes, and a step of rounds', () => {
    const turn = { tempo: 60, turn: true, notes: [{ beat: 0, length: 2, step: 0 }] };
    assert(exerciseSeconds(turn) === 5.5, `listen, a beat, then sing: ${exerciseSeconds(turn)}`);
    assert(exerciseSeconds({ ...turn, turn: false }) === 2.5, 'sung along: once');
    assert(stepSeconds({ exercise: turn, rounds: 3 }) === 16.5 && stepSeconds({ exercise: turn }) === 5.5, 'rounds add up');
});
