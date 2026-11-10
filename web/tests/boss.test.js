// The boss round (js/boss.js, docs/boss-round-plan.md): when it opens (the challenge's stars, never in the first week),
// its one honest percent (notes-weighted, null if any step wasn't heard), 80% beats it, the XP paid once outside the
// day's cap, the Every boss count, two devices merged, and the course started over clearing it.
import { BOSS_PASS, bossBeaten, bossOpen, bossPercent, bossShown, challengeOf, pastFirstWeek, recordBoss, unitOfBoss } from '/js/boss.js';
import { DAILY_XP_CAP, emptyProgress, maxStars, mergeProgress, nextLesson, restartedCourse, starsToOpen, unitOpen } from '/js/course.js';
import { newBadges } from '/js/badges.js';
import { assert, test } from './harness.js';

const DAY = 86400000;
const units = [
    { id: 'u1', lessons: [{ id: 'a' }, { id: 'b' }, { id: 'u1-ch', challenge: true }], boss: { id: '1-boss', title: { en: 'Boss 1' }, steps: [] } },
    { id: 'u2', lessons: [{ id: 'd' }, { id: 'u2-ch' }], boss: { id: '2-boss', title: { en: 'Boss 2' }, steps: [] } },
    { id: 'u3', plus: true, lessons: [{ id: 'f' }], boss: { id: '3-boss', title: { en: 'Boss 3' }, steps: [] } },
];
const NOW = Date.parse('2026-11-20T12:00:00');
const started = (days) => ({ ...emptyProgress(), startedAt: NOW - days * DAY });
const withStars = (p, ids) => ({ ...p, lessons: { ...p.lessons, ...Object.fromEntries(ids.map((id) => [id, { stars: 2, bestPercent: 75, at: 0 }])) } });

test('boss: it opens on the challenge\'s stars, and never in the first week', () => {
    assert(challengeOf(units[0]).id === 'u1-ch' && challengeOf(units[1]).id === 'u2-ch', 'the challenge, else the last lesson');
    assert(!bossOpen(units[0], started(10), NOW), 'no stars on the challenge: locked');
    assert(bossOpen(units[0], withStars(started(10), ['u1-ch']), NOW), 'the challenge has stars, past week one: open');
    assert(!bossOpen(units[0], withStars(started(3), ['u1-ch']), NOW), 'in the first week: not open');
    assert(!bossShown(units[0], withStars(started(3), ['u1-ch']), NOW) && bossShown(units[0], started(7), NOW), 'not even shown in week one; shown (locked) after');
    assert(!pastFirstWeek(emptyProgress(), NOW), 'a course not started has no first week behind it');
    assert(!bossOpen({ id: 'x', lessons: [{ id: 'y' }] }, withStars(started(10), ['y']), NOW), 'a unit without a boss');
    assert(unitOfBoss(units, '2-boss').unitIndex === 1 && unitOfBoss(units, 'nope') === null, 'its unit by id');
});

test('boss: it changes nothing about the course\'s stars or opening', () => {
    const p = withStars(started(10), ['a', 'b', 'u1-ch']);
    assert(maxStars(units[0]) === 9 && starsToOpen(units[0]) === 6, 'the boss isn\'t a lesson');
    const beaten = recordBoss(p, units, 'u1', 95, NOW).progress;
    assert(unitOpen(units, 1, p) === unitOpen(units, 1, beaten), 'beating it opens nothing new');
    assert(nextLesson(units, beaten).lesson.id === nextLesson(units, p).lesson.id, 'and the next lesson is the same');
});

test('boss: one notes-weighted percent, and none when a step wasn\'t heard', () => {
    assert(bossPercent([{ percent: 100, notes: 5 }, { percent: 70, notes: 15 }]) === 78, 'a long verse weighs more than a short call');
    assert(bossPercent([{ percent: 90, notes: 4 }]) === 90, 'one step');
    assert(bossPercent([{ percent: 90, notes: 4 }, { percent: null, notes: 8 }]) === null, 'couldn\'t hear one step: the round doesn\'t count');
    assert(bossPercent([]) === null, 'nothing sung');
    assert(bossPercent([{ percent: 80 }, { percent: 60, notes: 0 }]) === 70, 'a step without a count weighs one note');
});

test('boss: 80% beats it; the XP once, outside the day\'s cap; the best kept', () => {
    assert(BOSS_PASS === 80, 'the line');
    let p = withStars(started(10), ['u1-ch']);
    p = { ...p, xp: { total: DAILY_XP_CAP, days: { '2026-11-20': DAILY_XP_CAP } } }; // today's cap already reached
    const lost = recordBoss(p, units, 'u1', 79, NOW);
    assert(!lost.beaten && !lost.first && lost.gained === 0 && !bossBeaten(lost.progress, 'u1'), '79%: not beaten, nothing paid');
    assert(lost.progress.bosses.u1.best === 79 && lost.progress.bosses.u1.beatAt === null, 'the try is kept as a best');
    const won = recordBoss(lost.progress, units, 'u1', 80, NOW);
    assert(won.beaten && won.first && won.newBest && won.gained === 100, `80%: beaten, 100 XP even past the cap (${won.gained})`);
    assert(won.progress.xp.total === DAILY_XP_CAP + 100 && won.progress.bosses.u1.beatAt === NOW, 'paid and dated');
    const again = recordBoss(won.progress, units, 'u1', 92, NOW + DAY);
    assert(again.beaten && !again.first && again.gained === 0 && again.progress.bosses.u1.best === 92 && again.progress.bosses.u1.beatAt === NOW, 'a replay: a new best, no XP, the first win kept');
    const worse = recordBoss(again.progress, units, 'u1', 50, NOW + 2 * DAY);
    assert(worse.progress.bosses.u1.best === 92 && !worse.newBest, 'a worse try never lowers the best');
});

test('boss: the badges, the first and every unit\'s (every unit is free since 2026-11-06)', () => {
    let p = withStars(started(10), ['u1-ch', 'u2-ch']);
    p = recordBoss(p, units, 'u1', 85, NOW).progress;
    assert(newBadges(p, NOW).includes('bossBeaten') && !newBadges(p, NOW).includes('everyBoss'), 'the first boss');
    p = recordBoss(p, units, 'u2', 81, NOW).progress;
    assert(!newBadges(p, NOW).includes('everyBoss'), 'a unit once marked Plus counts like the rest');
    p = recordBoss(p, units, 'u3', 81, NOW).progress;
    assert(newBadges(p, NOW).includes('everyBoss'), 'every unit\'s boss beaten');
});

test('boss: two devices merge to the higher best and the earlier win; starting over clears it', () => {
    const mine = { ...emptyProgress(), bosses: { u1: { best: 70, beatAt: null }, u2: { best: 90, beatAt: 500 } } };
    const theirs = { ...emptyProgress(), bosses: { u1: { best: 84, beatAt: 300 }, u2: { best: 81, beatAt: 200 } } };
    const both = mergeProgress(mine, theirs).bosses;
    assert(both.u1.best === 84 && both.u1.beatAt === 300, 'their win on u1 stays');
    assert(both.u2.best === 90 && both.u2.beatAt === 200, 'the higher best and the earlier win');
    assert(!('bosses' in mergeProgress(emptyProgress(), emptyProgress())), 'nothing added while neither has one');
    assert(Object.keys(restartedCourse(mine).bosses).length === 0, 'Start over clears the bosses');
    // The server sends an emptied map back as a JSON [] (SaveLearning::RESET_CLEARS): read as no bosses.
    const reset = { ...withStars(started(10), ['u1-ch']), bosses: [] };
    assert(!bossBeaten(reset, 'u1') && recordBoss(reset, units, 'u1', 90, NOW).progress.bosses.u1.best === 90, 'an emptied [] reads as an empty map');
    assert(mergeProgress(reset, mine).bosses.u2.best === 90, 'and merges as one');
});
