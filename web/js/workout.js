// Today's workout (docs/vocal-coaching-plan.md, "Today's workout"): five to eight minutes, made for
// you. A warm-up in your range, one weak spot from your recent singing, one lesson whose stars are
// low, and a phrase from the song you're learning. Free: one a day; Plus: as many as you like.
// Pure: the exercises are the course's own shape (`{ tempo, notes: [{ beat, length, step }], turn }`,
// steps in semitones from your comfortable note), and the runner (js/views/workout.js) plays them.

import { dayOf } from './course.js';

/** The weak spot each insight points to, and the exercise that works on it. */
const DRILLS = {
    flatHigh: { tempo: 66, turn: true, notes: [{ beat: 0, length: 2, step: 4 }, { beat: 2, length: 2, step: 7 }, { beat: 4, length: 3, step: 9 }] },
    sharpHigh: { tempo: 66, turn: true, notes: [{ beat: 0, length: 2, step: 7 }, { beat: 2, length: 2, step: 9 }, { beat: 4, length: 3, step: 7 }] },
    flatOverall: { tempo: 60, turn: false, drone: { step: 0 }, notes: [{ beat: 0, length: 4, step: 0 }, { beat: 4, length: 4, step: 4 }, { beat: 8, length: 4, step: 7 }] },
    sharpOverall: { tempo: 60, turn: false, drone: { step: 0 }, notes: [{ beat: 0, length: 4, step: 7 }, { beat: 4, length: 4, step: 4 }, { beat: 8, length: 4, step: 0 }] },
    scoops: { tempo: 80, turn: true, notes: [{ beat: 0, length: 1, step: 0 }, { beat: 2, length: 1, step: 4 }, { beat: 4, length: 1, step: 7 }, { beat: 6, length: 1, step: 4 }] },
    late: { tempo: 84, turn: true, notes: [{ beat: 0, length: 1, step: 0 }, { beat: 1, length: 1, step: 2 }, { beat: 3, length: 1, step: 4 }, { beat: 4, length: 2, step: 0 }] },
    early: { tempo: 84, turn: true, notes: [{ beat: 0, length: 1, step: 0 }, { beat: 2, length: 1, step: 2 }, { beat: 4, length: 1, step: 4 }, { beat: 6, length: 2, step: 0 }] },
    wavering: { tempo: 54, turn: false, notes: [{ beat: 0, length: 6, step: 0 }, { beat: 6, length: 6, step: 4 }] },
};
const STEADY = { tempo: 60, turn: false, notes: [{ beat: 0, length: 6, step: 0 }, { beat: 6, length: 6, step: 2 }] };

/** The warm-up's rounds, each a half step higher (Hillel's 85.png). */
export const WARM_UP_ROUNDS = 3;

/**
 * The warm-up: five notes up and down (three, when the top of your range is close), then a held
 * note, sung in rounds a half step apart. `climb`: how many notes it climbs, for its headline.
 */
function warmUp(range, home) {
    // Room for the top note in the last round too, a whole step up.
    const room = range && home !== null ? Math.max(4, Math.min(7, range.high - home - 2 - (WARM_UP_ROUNDS - 1))) : 7;
    const top = room >= 7 ? 7 : 4;
    const steps = top === 7 ? [0, 2, 4, 5, 7, 5, 4, 2, 0] : [0, 2, 4, 2, 0];
    const exercise = { tempo: 92, turn: true, notes: [...steps.map((step, i) => ({ beat: i, length: 1, step, lyric: 'ah' })), { beat: steps.length + 1, length: 4, step: 0, lyric: 'oo' }] };
    return { exercise, climb: top === 7 ? 5 : 3 };
}

/** The insight that came up most in the recent takes (newest first breaks ties), or null. */
export function weakSpot(recent = []) {
    const counts = new Map();
    for (const [i, take] of recent.entries()) {
        for (const code of take.insights ?? []) {
            if (!DRILLS[code]) continue;
            const had = counts.get(code);
            counts.set(code, { n: (had?.n ?? 0) + 1, first: had ? had.first : i });
        }
    }
    const sorted = [...counts].sort((a, b) => b[1].n - a[1].n || a[1].first - b[1].first);
    return sorted[0]?.[0] ?? null;
}

/**
 * Today's workout: `{ steps: [{ kind, exercise, rounds?, climb?, lessonId?, insight? }], song }`
 * (`rounds`: the warm-up's, each a half step higher; one round otherwise), where `song` is
 * `{ projectId, title }` (the song you're learning, to sing a phrase of) or null.
 * - `progress`: the course progress (range, home, lessons and their stars, recent takes);
 * - `lessons`: the course's lessons as `{ id, exercise }` for those with a scored exercise;
 * - `learning`: `{ projectId, title }` of the song most recently sung, or null.
 */
export function pickWorkout(progress, lessons, learning = null) {
    const warm = warmUp(progress.range, progress.home);
    const steps = [{ kind: 'warmUp', exercise: warm.exercise, climb: warm.climb, rounds: WARM_UP_ROUNDS }];
    const insight = weakSpot(progress.recent);
    steps.push({ kind: 'weakSpot', insight, exercise: insight ? DRILLS[insight] : STEADY });
    // The lesson with the fewest stars that has some (a lesson not started is the course's to teach).
    const review = lessons
        .map((l) => ({ ...l, stars: progress.lessons?.[l.id]?.stars ?? 0 }))
        .filter((l) => l.stars > 0 && l.stars < 3)
        .sort((a, b) => a.stars - b.stars)[0];
    if (review) steps.push({ kind: 'review', lessonId: review.id, exercise: review.exercise });
    return { steps, song: learning };
}

/** Whether a free account has had today's workout (Plus has as many as it likes). */
export function workoutUsed(progress, at = Date.now()) {
    return progress.lastWorkout === dayOf(at);
}

/** An exercise moved by `shift` semitones (Lower, Higher: kept for next time; and each round up). */
export function shifted(exercise, shift) {
    return shift ? { ...exercise, notes: exercise.notes.map((n) => ({ ...n, step: n.step + shift })), drone: exercise.drone ? { step: exercise.drone.step + shift } : undefined } : exercise;
}

/** How long one run of an exercise takes, in seconds: as exercise.js lays it out (its turn after a beat's gap). */
export function exerciseSeconds(exercise) {
    const span = Math.max(...exercise.notes.map((n) => n.beat + n.length));
    const beats = exercise.turn === false ? span + 0.5 : span * 2 + 1.5;
    return (beats * 60) / (exercise.tempo ?? 72);
}

/** A step's length, all its rounds, in seconds. */
export function stepSeconds(step) {
    return exerciseSeconds(step.exercise) * (step.rounds ?? 1);
}
