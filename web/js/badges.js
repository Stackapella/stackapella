// Badges (docs/vocal-coaching-plan.md, "Badges"): about 25, each with a picture and a line, for
// firsts, range, skill, habit and community. Never paid for. Each is a rule over the course
// progress (and its `counts`: what the learner has done, kept with it); `newBadges` says which have
// just been earned, once each. Pure: js/stores/learning.js keeps them, Your voice shows them.

import { levelFor, weeksInARow } from './course.js';
import { tk } from './i18n.js';

const span = (progress) => (progress.range ? progress.range.high - progress.range.low : 0);
const count = (progress, name) => progress.counts?.[name] ?? 0;
const lessonsWith = (progress, stars) => Object.values(progress.lessons ?? {}).filter((l) => l.stars >= stars).length;
const practiceDays = (progress) => Object.values(progress.practice ?? {}).filter((seconds) => seconds >= 300).length;

/**
 * Every badge: its id, its words, its group, its picture (an icon name) and when it's earned; a badge
 * that counts something has `goal`, `[have, need]`, for how far there is to go.
 */
export const BADGES = [
    // Firsts
    { id: 'firstNote', name: tk('badges.firstNote.name'), line: tk('badges.firstNote.line'), group: 'firsts', icon: 'note', earned: (p) => p.home !== null || count(p, 'notesLit') > 0 },
    { id: 'firstLesson', name: tk('badges.firstLesson.name'), line: tk('badges.firstLesson.line'), group: 'firsts', icon: 'star', earned: (p) => lessonsWith(p, 1) >= 1 },
    { id: 'firstPart', name: tk('badges.firstPart.name'), line: tk('badges.firstPart.line'), group: 'firsts', icon: 'mic', earned: (p) => count(p, 'parts') >= 1 },
    { id: 'firstHarmony', name: tk('badges.firstHarmony.name'), line: tk('badges.firstHarmony.line'), group: 'firsts', icon: 'headphones', earned: (p) => count(p, 'harmonies') >= 1, goal: (p) => [count(p, 'harmonies'), 1] },
    { id: 'firstWorkout', name: tk('badges.firstWorkout.name'), line: tk('badges.firstWorkout.line'), group: 'firsts', icon: 'timing', earned: (p) => count(p, 'workouts') >= 1, goal: (p) => [count(p, 'workouts'), 1] },
    // Range
    { id: 'octave', name: tk('badges.octave.name'), line: tk('badges.octave.line'), group: 'range', icon: 'sliders', earned: (p) => span(p) >= 12, goal: (p) => [span(p), 12] },
    { id: 'octaveAndHalf', name: tk('badges.octaveAndHalf.name'), line: tk('badges.octaveAndHalf.line'), group: 'range', icon: 'sliders', earned: (p) => span(p) >= 18, goal: (p) => [span(p), 18] },
    { id: 'twoOctaves', name: tk('badges.twoOctaves.name'), line: tk('badges.twoOctaves.line'), group: 'range', icon: 'sliders', earned: (p) => span(p) >= 24, goal: (p) => [span(p), 24] },
    { id: 'highG', name: tk('badges.highG.name'), line: tk('badges.highG.line'), group: 'range', icon: 'arrow', earned: (p) => (p.range?.high ?? 0) >= 79 }, // G5
    { id: 'lowAndSteady', name: tk('badges.lowAndSteady.name'), line: tk('badges.lowAndSteady.line'), group: 'range', icon: 'arrow', earned: (p) => (p.range?.low ?? 999) <= 43 }, // G2
    // Skill
    { id: 'threeStars', name: tk('badges.threeStars.name'), line: tk('badges.threeStars.line'), group: 'skill', icon: 'star', earned: (p) => lessonsWith(p, 3) >= 1 },
    { id: 'tenThreeStars', name: tk('badges.tenThreeStars.name'), line: tk('badges.tenThreeStars.line'), group: 'skill', icon: 'star', earned: (p) => lessonsWith(p, 3) >= 10, goal: (p) => [lessonsWith(p, 3), 10] },
    { id: 'combo10', name: tk('badges.combo10.name'), line: tk('badges.combo10.line'), group: 'skill', icon: 'repeat', earned: (p) => count(p, 'bestCombo') >= 10, goal: (p) => [count(p, 'bestCombo'), 10] },
    { id: 'combo20', name: tk('badges.combo20.name'), line: tk('badges.combo20.line'), group: 'skill', icon: 'repeat', earned: (p) => count(p, 'bestCombo') >= 20, goal: (p) => [count(p, 'bestCombo'), 20] },
    { id: 'excellentTake', name: tk('badges.excellentTake.name'), line: tk('badges.excellentTake.line'), group: 'skill', icon: 'check', earned: (p) => count(p, 'excellentTakes') >= 1 },
    { id: 'everyPart', name: tk('badges.everyPart.name'), line: tk('badges.everyPart.line'), group: 'skill', icon: 'list', earned: (p) => count(p, 'harmonies') >= 3, goal: (p) => [count(p, 'harmonies'), 3] },
    // Habit
    { id: 'weekGoal', name: tk('badges.weekGoal.name'), line: tk('badges.weekGoal.line'), group: 'habit', icon: 'check', earned: (p, at) => weeksInARow(p, at) >= 1 || (p.weeks?.streak ?? 0) >= 1 },
    { id: 'fourWeeks', name: tk('badges.fourWeeks.name'), line: tk('badges.fourWeeks.line'), group: 'habit', icon: 'check', earned: (p) => (p.weeks?.streak ?? 0) >= 4, goal: (p) => [p.weeks?.streak ?? 0, 4] },
    { id: 'twelveWeeks', name: tk('badges.twelveWeeks.name'), line: tk('badges.twelveWeeks.line'), group: 'habit', icon: 'check', earned: (p) => (p.weeks?.streak ?? 0) >= 12, goal: (p) => [p.weeks?.streak ?? 0, 12] },
    { id: 'tenWorkouts', name: tk('badges.tenWorkouts.name'), line: tk('badges.tenWorkouts.line'), group: 'habit', icon: 'timing', earned: (p) => count(p, 'workouts') >= 10, goal: (p) => [count(p, 'workouts'), 10] },
    { id: 'thirtyDays', name: tk('badges.thirtyDays.name'), line: tk('badges.thirtyDays.line'), group: 'habit', icon: 'home', earned: (p) => practiceDays(p) >= 30, goal: (p) => [practiceDays(p), 30] },
    { id: 'level5', name: tk('badges.level5.name'), line: tk('badges.level5.line'), group: 'habit', icon: 'star', earned: (p) => levelFor(p.xp?.total ?? 0).level >= 5, goal: (p) => [levelFor(p.xp?.total ?? 0).level, 5] },
    { id: 'level10', name: tk('badges.level10.name'), line: tk('badges.level10.line'), group: 'habit', icon: 'star', earned: (p) => levelFor(p.xp?.total ?? 0).level >= 10, goal: (p) => [levelFor(p.xp?.total ?? 0).level, 10] },
    // Community (with sharing and choirs)
    { id: 'firstShare', name: tk('badges.firstShare.name'), line: tk('badges.firstShare.line'), group: 'community', icon: 'download', earned: (p) => count(p, 'shares') >= 1 },
    { id: 'joinedChoir', name: tk('badges.joinedChoir.name'), line: tk('badges.joinedChoir.line'), group: 'community', icon: 'home', earned: (p) => count(p, 'choirs') >= 1 },
    { id: 'openPart', name: tk('badges.openPart.name'), line: tk('badges.openPart.line'), group: 'community', icon: 'plus', earned: (p) => count(p, 'openParts') >= 1 },
];

export const GROUPS = ['firsts', 'range', 'skill', 'habit', 'community'];

/** The badges `progress` has earned that it doesn't have yet (their ids, in the list's order). */
export function newBadges(progress, at = Date.now()) {
    const had = progress.badges ?? {};
    return BADGES.filter((b) => !had[b.id] && b.earned(progress, at)).map((b) => b.id);
}

/** `progress` with `ids` earned at `at`. */
export function withBadges(progress, ids, at = Date.now()) {
    if (!ids.length) return progress;
    return { ...progress, badges: { ...(progress.badges ?? {}), ...Object.fromEntries(ids.map((id) => [id, at])) } };
}

/**
 * A count kept with the progress: `add` adds to it, `best` keeps the highest (a combo).
 * Returns the new progress.
 */
export function tally(progress, name, { add = 0, best = null } = {}) {
    const counts = { ...(progress.counts ?? {}) };
    counts[name] = best !== null ? Math.max(counts[name] ?? 0, best) : (counts[name] ?? 0) + add;
    return { ...progress, counts };
}
