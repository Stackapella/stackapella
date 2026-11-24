// Ready by rehearsal's plan (js/rehearsal-plan.js): the phrases spread by sung time over the days left, the last day the
// whole part; short on time, the whole part today; a missed day spread over what's left; a day done by Coach's own mark;
// two devices' plans merged as the contract says.
import { dayNow, makePlan, mergeRehearsals, phrasesDone, replan, splitPhrases, upsertPlan } from '/js/rehearsal-plan.js';
import { assert, test } from './harness.js';

// A song of eight phrases of four beats, one of them twice as long.
const song = { sections: [{ startBeat: 0, endBeat: 36, phrases: [[0, 4], [4, 8], [8, 12], [12, 20], [20, 24], [24, 28], [28, 32], [32, 36]] }], parts: [] };
const monday = new Date(2026, 10, 23);

test('rehearsal plan: phrases over the days, the last the whole part', () => {
    const plan = makePlan({ songId: 's', partKey: 'alto', date: '2026-11-26', template: song, today: monday, now: 1 });
    const shape = plan.days.map((d) => `${d.date.slice(8)} ${d.whole ? 'whole' : d.phrases.join('-')}`).join(', ');
    assert(shape === '23 0-2, 24 3-4, 25 5-7, 26 whole', shape);
});

test('rehearsal plan: at most five days, and two days or fewer is the whole part today', () => {
    const far = makePlan({ songId: 's', partKey: 'alto', date: '2026-12-10', template: song, today: monday });
    assert(far.days.length === 5 && far.days.at(-1).whole, `${far.days.length} days`);
    const soon = makePlan({ songId: 's', partKey: 'alto', date: '2026-11-24', template: song, today: monday });
    assert(soon.days.length === 1 && soon.days[0].whole && soon.days[0].date === '2026-11-23', JSON.stringify(soon.days));
});

test('rehearsal plan: runs follow sung time and never leave one empty', () => {
    const lengths = [8, 1, 1, 1, 1].map((beats, index) => ({ index, beats }));
    const runs = splitPhrases(lengths, 2);
    assert(JSON.stringify(runs) === '[[0,0],[1,4]]', JSON.stringify(runs));
    assert(splitPhrases(lengths, 9).length === 5, 'more days than phrases');
});

test('rehearsal plan: a missed day spreads over the days left, done days kept', () => {
    const plan = makePlan({ songId: 's', partKey: 'alto', date: '2026-11-27', template: song, today: monday });
    plan.days[0].doneAt = 5; // Monday done; Tuesday missed
    const wednesday = new Date(2026, 10, 25);
    const again = replan(plan, song, wednesday);
    assert(again.days[0].date === '2026-11-23' && again.days[0].doneAt === 5, 'the done day went');
    const ahead = again.days.filter((d) => d.date >= '2026-11-25');
    assert(ahead[0].date === '2026-11-25' && ahead.at(-1).whole && ahead.at(-1).date === '2026-11-27', JSON.stringify(ahead));
    const sung = ahead.filter((d) => d.phrases).flatMap((d) => d.phrases);
    assert(sung[0] === plan.days[1].phrases[0], `starts again at ${sung[0]}`);
    assert(dayNow(again, wednesday).date === '2026-11-25', 'today is the day to do');
});

test('rehearsal plan: a day is done by Coach on your own, or past it', () => {
    const day = { date: '2026-11-23', phrases: [2, 3] };
    assert(!phrasesDone(day, { step: 'singAlong', phrases: { 2: 1, 3: 1 } }), 'sing along counted as done');
    assert(!phrasesDone(day, { step: 'own', phrases: { 2: 1 } }), 'half the phrases');
    assert(phrasesDone(day, { step: 'own', phrases: { 2: 1, 3: 2 } }), 'both on your own');
    assert(phrasesDone(day, { step: 'record', phrases: {} }), 'past it');
});

test('rehearsal plan: two devices merge by the newer plan, done days and ready kept', () => {
    const a = { songId: 's', partKey: 'alto', date: '2026-11-27', madeAt: 10, days: [{ date: '2026-11-23', phrases: [0, 2], doneAt: 11 }, { date: '2026-11-24', phrases: [3, 7] }] };
    const b = { ...a, madeAt: 20, days: [{ date: '2026-11-23', phrases: [0, 2] }, { date: '2026-11-24', phrases: [3, 7], doneAt: 21 }], readyAt: 30 };
    const [merged] = mergeRehearsals([a], [b]);
    assert(merged.madeAt === 20 && merged.days[0].doneAt === 11 && merged.days[1].doneAt === 21 && merged.readyAt === 30, JSON.stringify(merged));
    const removed = mergeRehearsals([{ ...a, removedAt: 40 }], [b]);
    assert(removed[0].removedAt === 40, 'a later removal lost');
    assert(upsertPlan([a, { ...a, partKey: 'bass' }], { ...a, madeAt: 50 }).length === 2, 'the same part twice');
});
