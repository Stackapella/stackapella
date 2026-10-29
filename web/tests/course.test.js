// The course's rules (js/course.js): stars at each boundary, units opening at two thirds of the stars,
// XP with the daily cap, levels, and the weekly goal across a missed week (no penalty).
import { addPractice, addXp, countDay, emptyProgress, levelFor, mergeProgress, nextLesson, recordLesson, restartedCourse, starsFor, starsToOpen, strictnessFor, unitOpen, weekRing, weeksInARow, xpForLevel } from '/js/course.js';
import { FREE_SONGS } from '/js/templates.js';
import { assert, test } from './harness.js';

const units = [
    { id: 'u1', lessons: [{ id: 'a' }, { id: 'b' }, { id: 'c' }] },
    { id: 'u2', lessons: [{ id: 'd' }, { id: 'e' }] },
    { id: 'u3', lessons: [{ id: 'f' }] },
];
const at = (day, hour = 12) => new Date(`${day}T${String(hour).padStart(2, '0')}:00:00`).getTime();

test('course: stars at each boundary, and nothing for a take that couldn\'t be heard', () => {
    assert(starsFor({ percent: 69 }) === 1 && starsFor({ percent: 70 }) === 2 && starsFor({ percent: 89 }) === 2, 'one and two');
    assert(starsFor({ percent: 90, onTime: true }) === 3 && starsFor({ percent: 95, onTime: false }) === 2, 'three needs the entries on time');
    assert(starsFor({ percent: 95, onTime: false, timing: 'estimated' }) === 3, 'with an estimated timing, accuracy is enough');
    assert(starsFor({ percent: null }) === 0, 'couldn\'t hear: no stars, no loss');
    assert(strictnessFor(0) === 'relaxed' && strictnessFor(3) === 'relaxed' && strictnessFor(4) === 'standard' && strictnessFor(1, true) === 'precise', 'strictness');
});

test('course: a unit opens at two thirds of the stars before it, or by the check', () => {
    let p = emptyProgress();
    assert(unitOpen(units, 0, p) && !unitOpen(units, 1, p), 'only the first');
    assert(starsToOpen(units[0]) === 6, `two thirds of 9: ${starsToOpen(units[0])}`);
    p = recordLesson(recordLesson(p, 'a', { stars: 3, percent: 92 }), 'b', { stars: 2, percent: 75 });
    assert(!unitOpen(units, 1, p), '5 of 9 stars');
    p = recordLesson(p, 'c', { stars: 1, percent: 40 });
    assert(unitOpen(units, 1, p) && !unitOpen(units, 2, p), '6 of 9 opens unit 2');
    assert(nextLesson(units, p).lesson.id === 'd', 'next: the first lesson of unit 2');
    p = recordLesson(p, 'a', { stars: 1, percent: 30 });
    assert(p.lessons.a.stars === 3 && p.lessons.a.bestPercent === 92, 'a replay keeps the best');
});

test('course: the next lesson is the first open one without stars, in order; Plus units only for Plus', () => {
    const course = [
        { id: 'u1', lessons: [{ id: 'home', findsHome: true }, { id: 'b' }] },
        { id: 'u2', plus: true, lessons: [{ id: 'c' }] },
    ];
    let p = emptyProgress();
    assert(nextLesson(course, p).lesson.id === 'home', 'lesson 1 first, for everyone');
    p = recordLesson(recordLesson(p, 'home', { stars: 3, percent: 95 }), 'b', { stars: 3, percent: 95 });
    assert(nextLesson(course, p).lesson.id === 'b', 'all free done: the last free lesson');
    assert(nextLesson(course, p, { plus: true }).lesson.id === 'c', 'a Plus member goes on');
    // Units an earlier skip-ahead opened stay open.
    assert(unitOpen(units, 2, { ...emptyProgress(), testedOut: ['u3'] }), 'kept open');
});

test('course: the free course uses only the free songs', async () => {
    const course = await (await fetch('/course/course.json')).json();
    for (const unit of course.units.filter((u) => !u.plus)) {
        for (const lesson of unit.lessons) {
            for (const step of lesson.steps) {
                if (step.template) assert(FREE_SONGS.includes(step.template), `${lesson.id} uses ${step.template}`);
            }
        }
    }
});

test('course: XP for effort, within a daily cap', () => {
    let p = emptyProgress();
    let gained;
    ({ progress: p, gained } = addXp(p, { kind: 'exercise', stars: 3 }, at('2026-10-26')));
    assert(gained === 25 && p.xp.total === 25, `an exercise with 3 stars: ${gained}`);
    for (let i = 0; i < 5; i++) ({ progress: p } = addXp(p, { kind: 'harmony' }, at('2026-10-26')));
    assert(p.xp.days['2026-10-26'] === 300 && p.xp.total === 300, `capped at 300: ${p.xp.total}`);
    ({ progress: p, gained } = addXp(p, { kind: 'part' }, at('2026-10-27')));
    assert(gained === 50, 'a new day');
});

test('course: voice levels climb, each a little further', () => {
    assert(levelFor(0).level === 1 && levelFor(99).level === 1 && levelFor(100).level === 2, 'level 2 at 100');
    assert(xpForLevel(5) === 1000 && levelFor(1000).level === 5, 'level 5 at 1000');
    assert(levelFor(1000).name === 'course.level.5' && levelFor(150).name === 'course.level.1', 'named levels');
    const l = levelFor(130);
    assert(l.into === 30 && l.needed === 200, JSON.stringify(l));
});

test('course: the weekly goal fills, and a missed week starts afresh without a penalty', () => {
    let p = emptyProgress();
    p = addPractice(p, 200, at('2026-10-26')); // a Monday: under 5 minutes
    assert(weekRing(p, at('2026-10-26')).done === 0, 'not yet a day');
    p = addPractice(p, 120, at('2026-10-26'));
    p = countDay(p, at('2026-10-27'));
    assert(weekRing(p, at('2026-10-27')).done === 2 && !weekRing(p, at('2026-10-27')).met, '2 of 3');
    p = addPractice(p, 600, at('2026-11-01')); // Sunday, the same week
    assert(weekRing(p, at('2026-11-01')).met && p.weeks.streak === 1, 'met: 1 week');
    // The next week is missed; the week after starts a fresh streak.
    assert(weeksInARow(p, at('2026-11-12')) === 0, 'a missed week shows none, quietly');
    for (const day of ['2026-11-16', '2026-11-17', '2026-11-18']) p = countDay(p, at(day));
    assert(p.weeks.streak === 1, `fresh start: ${p.weeks.streak}`);
    for (const day of ['2026-11-23', '2026-11-24', '2026-11-25']) p = countDay(p, at(day));
    assert(p.weeks.streak === 2 && weeksInARow(p, at('2026-11-26')) === 2, 'two in a row');
});

test('course: two devices\' progress merges to the best of each, never less', () => {
    let phone = recordLesson(emptyProgress(), 'a', { stars: 3, percent: 92 }, at('2026-10-26'));
    phone = addXp(phone, { kind: 'exercise', stars: 3 }, at('2026-10-26')).progress;
    phone = { ...phone, startedAt: at('2026-10-26'), range: { low: 55, high: 67 }, goal: 4 };
    let mac = recordLesson(emptyProgress(), 'a', { stars: 2, percent: 95 }, at('2026-10-27'));
    mac = recordLesson(mac, 'b', { stars: 1, percent: 40 }, at('2026-10-27'));
    mac = addXp(mac, { kind: 'part' }, at('2026-10-27')).progress;
    mac = { ...mac, startedAt: at('2026-10-27'), range: { low: 52, high: 64 } };
    const both = mergeProgress(phone, mac);
    assert(both.lessons.a.stars === 3 && both.lessons.a.bestPercent === 95 && both.lessons.b.stars === 1, JSON.stringify(both.lessons));
    assert(both.xp.total === phone.xp.total + mac.xp.total, `XP from both days: ${both.xp.total}`);
    assert(both.range.low === 52 && both.range.high === 67 && both.startedAt === at('2026-10-26') && both.goal === 4, 'the wider range, the first start, this device\'s goal');
    assert(JSON.stringify(mergeProgress(both, both)) === JSON.stringify(both), 'merging again changes nothing');
});

test('badges: each is earned once, from what was done; two devices keep the earlier', async () => {
    const { newBadges, tally, withBadges, BADGES } = await import('/js/badges.js');
    let p = emptyProgress();
    assert(!newBadges(p).length, 'none at first');
    p = recordLesson({ ...p, home: 57 }, 'a', { stars: 3, percent: 95 });
    p = tally(p, 'parts', { add: 1 });
    p = tally(p, 'bestCombo', { best: 12 });
    p = tally(p, 'bestCombo', { best: 4 });
    const first = newBadges(p);
    assert(['firstNote', 'firstLesson', 'firstPart', 'threeStars', 'combo10'].every((id) => first.includes(id)) && !first.includes('combo20'), JSON.stringify(first));
    p = withBadges(p, first, 100);
    assert(!newBadges(p).length, 'once each');
    p = { ...p, range: { low: 41, high: 70 } };
    assert(newBadges(p).join() === 'octave,octaveAndHalf,twoOctaves,lowAndSteady', newBadges(p).join());
    const both = mergeProgress(withBadges(p, ['octave'], 300), withBadges(emptyProgress(), ['octave', 'firstShare'], 200));
    assert(both.badges.octave === 200 && both.badges.firstShare === 200 && both.badges.firstNote === 100 && both.counts.bestCombo === 12, 'merged: the earlier, and the best counts');
    assert(BADGES.length >= 25 && BADGES.every((b) => b.name && b.line && b.icon), 'about 25, each with words and a picture');
});

test('your voice: the voice a range suits best, and none from too little', async () => {
    const { voiceType } = await import('/js/course.js');
    assert(voiceType({ low: 62, high: 79 }) === 'soprano', 'soprano');
    assert(voiceType({ low: 55, high: 72 }) === 'alto', 'alto');
    assert(voiceType({ low: 49, high: 66 }) === 'tenor', 'tenor');
    assert(voiceType({ low: 41, high: 60 }) === 'bass', 'bass');
    assert(voiceType({ low: 60, high: 64 }) === null && voiceType(null) === null, 'too little to go on');
});

test('course: this week\'s days for the home, Monday first (42.png)', async () => {
    const { weekDays, DAY_SECONDS } = await import('/js/course.js');
    const at = new Date(2026, 9, 29, 12).getTime(); // a Thursday
    const days = weekDays({ practice: { '2026-10-26': DAY_SECONDS, '2026-10-28': DAY_SECONDS / 2, '2026-10-29': 10 } }, at);
    assert(days.length === 7 && days[0].day === '2026-10-26', `Monday first: ${days[0].day}`);
    assert(days[0].done && !days[2].done && days[2].share === 0.5, 'a day counts at five minutes; part of one shows');
    assert(days[3].today && !days[3].future && days[4].future, 'today, then the days to come');
});

test('course: starting over clears the lessons and the early opens, and keeps the note, range, XP and badges', () => {
    const before = { ...emptyProgress(), home: 57, range: { low: 50, high: 64 }, lessons: { '1-find': { stars: 3, bestPercent: 90, at: 1 } }, testedOut: ['u2'], xp: { total: 120, days: { '2026-10-28': 120 } }, badges: { 'first-note': 1 } };
    const after = restartedCourse(before);
    assert(Object.keys(after.lessons).length === 0 && after.testedOut.length === 0, JSON.stringify(after));
    assert(after.home === 57 && after.range.low === 50 && after.xp.total === 120 && after.badges['first-note'] === 1, 'kept what it should');
    assert(Object.keys(before.lessons).length === 1, 'the progress it was given is unchanged');
});

