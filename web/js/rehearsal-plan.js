// Ready by rehearsal (docs/rehearsal-ready-plan.md; Hillel's approved build plan, brief A): a part learned by the day the
// singer needs it. The part's phrases spread over the days left, split by sung time, the last day the whole part with the
// others and the ready check. Kept in progress.rehearsals (contract: `{ songId, partKey, date, madeAt, removedAt?, days:
// [{ date, phrases?: [first, last], whole?, doneAt? }], readyAt?, groupId? }`), synced, never sent anywhere else. Missing a
// day spreads what's left over the days left: no "behind", no red, no streak. Pure, for its tests (tests/rehearsal-plan.test.js).

import { phrases as templatePhrases } from './templates.js';
import { daysUntil, isoDate, localDate } from './rehearsal.js';
import { STEPS } from './sing.js';
import { MAX_PLANS } from './rehearsal-merge.js';
export { isReady } from './rehearsal-merge.js';

export { MAX_PLANS, mergeRehearsals } from './rehearsal-merge.js';

export const MAX_DAYS = 5; // practice days in a plan at most (and calendar entries)
export const READY_PERCENT = 70; // a phrase sung "close" or better (two stars and up) is ready; under it, one to go over

/**
 * The ready check's verdict from a whole take's phrase review (report.js phraseReview): ready when every phrase heard is
 * close or better, else the phrases to go over, in the song's order. Never a score, never "not ready". Pure.
 */
export function readyVerdict(review = []) {
    const phrases = review.filter((p) => p.percent !== null && p.percent < READY_PERCENT).map((p) => p.index).sort((a, b) => a - b);
    return { ready: review.length > 0 && phrases.length === 0, phrases };
}

const addDays = (date, n) => new Date(date.getFullYear(), date.getMonth(), date.getDate() + n);
const midnight = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());

/** The song's phrases as [{ index, beats }], the same indexes Sing's Coach counts. */
export function phraseLengths(template) {
    return templatePhrases(template).map((p, index) => ({ index, beats: Math.max(0.25, p.endBeat - p.startBeat) }));
}

/** Contiguous phrase indexes split into `groups` runs of about equal sung length: [[first, last], ...]. Never empty runs. */
export function splitPhrases(lengths, groups) {
    const count = Math.max(1, Math.min(groups, lengths.length));
    const total = lengths.reduce((sum, p) => sum + p.beats, 0);
    const runs = [];
    let start = 0;
    let sung = 0;
    for (let g = 0; g < count; g++) {
        const left = count - g; // runs still to make, this one included
        if (left === 1) {
            runs.push([lengths[start].index, lengths.at(-1).index]);
            break;
        }
        const target = (total - sung) / left;
        let end = start;
        let run = lengths[start].beats;
        // Grow the run while it's nearer the target with the next phrase, leaving one phrase for each run after it.
        while (end + 1 < lengths.length - (left - 1) && Math.abs(run + lengths[end + 1].beats - target) <= Math.abs(run - target)) {
            end += 1;
            run += lengths[end].beats;
        }
        runs.push([lengths[start].index, lengths[end].index]);
        sung += run;
        start = end + 1;
    }
    return runs;
}

/**
 * A new plan for `partKey` of the song, for a rehearsal on `date` (YYYY-MM-DD). With two days or fewer: today, the whole
 * part and the ready check. Otherwise up to MAX_DAYS days from today, the phrases spread over all but the last, which is
 * the whole part with the others. A rehearsal today or past: the whole part at once.
 */
export function makePlan({ songId, partKey, date, template, today = new Date(), now = Date.now(), groupId = null }) {
    const day0 = midnight(today);
    const left = daysUntil(localDate(date), day0); // 0: the rehearsal is today
    const plan = { songId, partKey, date, madeAt: now, days: [], ...(groupId ? { groupId } : {}) };
    const lengths = phraseLengths(template);
    if (left <= 1 || lengths.length < 2) {
        plan.days = [{ date: isoDate(day0), whole: true }];
        return plan;
    }
    const total = Math.min(left + 1, MAX_DAYS); // today to the rehearsal day, at most five
    const runs = splitPhrases(lengths, total - 1);
    plan.days = [...runs.map((phrases, i) => ({ date: isoDate(addDays(day0, i)), phrases })), { date: isoDate(addDays(day0, runs.length)), whole: true }];
    return plan;
}

/**
 * The plan from today, a missed day's phrases spread over the days left (done days and today's kept as they are). The
 * same plan when nothing was missed.
 */
export function replan(plan, template, today = new Date()) {
    const day0 = isoDate(midnight(today));
    const missed = plan.days.filter((d) => d.date < day0 && !d.doneAt && d.phrases);
    if (!missed.length) return plan;
    const kept = plan.days.filter((d) => d.date < day0 && d.doneAt);
    // What's still to learn: the missed days' phrases and every day ahead's, in order.
    const ahead = plan.days.filter((d) => d.date >= day0 && d.phrases && !d.doneAt);
    const indexes = [...missed, ...ahead].flatMap((d) => range(d.phrases));
    const lengths = phraseLengths(template).filter((p) => indexes.includes(p.index));
    const left = daysUntil(localDate(plan.date), midnight(today));
    const done = plan.days.filter((d) => d.date >= day0 && d.doneAt && d.phrases);
    if (left <= 1 || !lengths.length) return { ...plan, days: [...kept, ...done, { date: day0, whole: true }] };
    const total = Math.min(left + 1, MAX_DAYS);
    const runs = splitPhrases(lengths, Math.max(1, total - 1));
    const base = midnight(today);
    return { ...plan, days: [...kept, ...done, ...runs.map((phrases, i) => ({ date: isoDate(addDays(base, i)), phrases })), { date: isoDate(addDays(base, runs.length)), whole: true }] };
}

/** [first, last] as every index from first to last. */
export function range([first, last]) {
    return Array.from({ length: last - first + 1 }, (_, i) => first + i);
}

/** The day to do next: today's if not done, else the first day not done (the whole part's when only it is left). */
export function dayNow(plan, today = new Date()) {
    const day0 = isoDate(midnight(today));
    return plan.days.find((d) => d.date === day0 && !d.doneAt) ?? plan.days.find((d) => !d.doneAt) ?? null;
}

/**
 * A phrase day done by Coach's own mark: the part's progress past "On your own", or in it with each of the day's phrases
 * sung at least once. `progress` is Sing's progressOf(practice, partKey).
 */
export function phrasesDone(day, progress) {
    if (!day?.phrases || !progress) return false;
    const at = STEPS.indexOf(progress.step);
    if (at > STEPS.indexOf('own')) return true;
    return progress.step === 'own' && range(day.phrases).every((i) => (progress.phrases?.[i] ?? 0) > 0);
}

/** Days from today to the rehearsal (0 today), for "Thursday, 4 days". */
export const daysLeft = (plan, today = new Date()) => daysUntil(localDate(plan.date), midnight(today));

/** A plan put in the list: replacing the same part's, newest first, at most MAX_PLANS. */
export function upsertPlan(list = [], plan) {
    return [plan, ...list.filter((p) => !(p.songId === plan.songId && p.partKey === plan.partKey))].slice(0, MAX_PLANS);
}

/** The plans still on (not removed, rehearsal today or ahead). */
export function livePlans(list = [], today = new Date()) {
    return list.filter((p) => !p.removedAt && daysLeft(p, today) >= 0);
}

