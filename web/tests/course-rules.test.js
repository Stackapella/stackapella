// A course's own game rules (game/effects.js courseRules, course.js paysLessonXp; First steps to belting's "no game
// pressure"): course.json's "game" and a lesson's "quiet" turn moments off for that course only, every field absent means
// game mode as everywhere, and a replay pays no XP only where the course says so.
import { courseRules, EFFECTS, offList, setCourseRules } from '/js/game/effects.js';
import { paysLessonXp } from '/js/course.js';
import { assert, test } from './harness.js';

const ALL_ON = Object.fromEntries(Object.keys(EFFECTS).map((name) => [name, true]));

test('course rules: absent fields keep everything; the course file and a quiet lesson turn theirs off', () => {
    try {
        setCourseRules();
        assert(courseRules.combo && courseRules.streak && courseRules.meter && !courseRules.quiet, 'no course: everything');
        setCourseRules({ id: 'other' }, { id: 'x' });
        assert(courseRules.combo && courseRules.streak && courseRules.meter && !courseRules.quiet, 'a course without "game": everything');
        setCourseRules({ game: { replayXp: false, combo: false, streak: false, meter: 'plain' } }, { id: 'y' });
        assert(!courseRules.combo && !courseRules.streak && !courseRules.meter && !courseRules.quiet, 'Belting: no combo, streak or power look');
        setCourseRules({ game: {} }, { quiet: true });
        assert(courseRules.quiet && courseRules.combo, 'a quiet lesson in an ordinary course');
    } finally {
        setCourseRules();
    }
});

test('course rules: the moments a course turns off reach <html data-game-off>, the looks stay', () => {
    assert(offList(ALL_ON, { combo: true, streak: true, meter: true, quiet: false }) === '', 'nothing off');
    const quiet = offList(ALL_ON, { combo: true, streak: true, meter: true, quiet: true }).split(' ');
    assert(quiet.includes('popups') && quiet.includes('celebrations') && quiet.includes('titles') && quiet.includes('sounds'), `a quiet lesson's moments: ${quiet}`);
    assert(!quiet.includes('arcade') && !quiet.includes('scanlines') && !quiet.includes('all'), 'its looks and the master switch stay');
    const plain = offList(ALL_ON, { combo: false, streak: true, meter: false, quiet: false }).split(' ');
    assert(plain.includes('popups') && plain.includes('meter') && !plain.includes('hud'), `no combos: no pop-ups; plain meter: "meter" (${plain})`);
});

test('course rules: a replay pays XP unless the course says "replayXp": false', () => {
    assert(paysLessonXp(undefined, 0) && paysLessonXp(undefined, 3), 'everywhere else: always');
    assert(paysLessonXp({ replayXp: false }, 0), 'Belting: the first finish pays');
    assert(!paysLessonXp({ replayXp: false }, 2), 'Belting: a replay pays nothing');
    assert(paysLessonXp({ combo: false }, 2), 'other rules leave XP alone');
});
