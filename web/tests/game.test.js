// Game mode's arcade moments (docs/game-elements-plan.md): each shows only in game mode with its own
// switch on, Game sounds off until chosen; count-ups land on the real number; pop-ups wait out a
// take's first 2 seconds, show one at a time and tell a screen reader once a phrase; and a chip cue
// never sounds while the engine plays.
import { appearance } from '/js/stores/appearance.js';
import { EFFECTS, effect, effects } from '/js/game/effects.js';
import { countAt, run, sequence } from '/js/game/motion.js';
import { POPUP_SECONDS, SAY_GAP, bonusRoom, comboMilestone, popupQueue } from '/js/game/popups.js';
import { CUES, cue, cueAllowed, cueLength } from '/js/game/chip.js';
import { GAME_FONTS, loadGameFonts } from '/js/game/fonts.js';
import { letters } from '/js/game/record.js';
import { markTitleSeen, titleSeen } from '/js/game/title-card.js';
import { nodeAt, segment, twoStarPercent } from '/js/game/stage-map.js';
import { CELLS, cellsOf, leapWord, partStats } from '/js/game/part-select.js';
import { addXp, DAILY_XP_CAP, dayOf, emptyProgress, starsFor, xpFor } from '/js/course.js';
import { engine } from '/js/audio.js';
import { assert, test } from './harness.js';

async function inSkin(skin, body) {
    const was = appearance.skin;
    appearance.skin = skin;
    try {
        await body();
    } finally {
        appearance.skin = was;
    }
}

test('game effects: game sounds off and the rest on by default; nothing in serious mode', async () => {
    assert(EFFECTS.sounds === false && Object.entries(EFFECTS).every(([name, on]) => name === 'sounds' || on === true), 'the defaults');
    const kept = { ...effects };
    try {
        Object.assign(effects, EFFECTS);
        await inSkin('game', () => {
            assert(effect('hud') && effect('popups') && !effect('sounds'), 'game mode: on, sounds off');
            effects.hud = false;
            assert(!effect('hud'), 'its switch turns it off');
        });
        await inSkin('serious', () => {
            effects.sounds = true;
            assert(Object.keys(EFFECTS).every((name) => !effect(name)), 'serious mode shows none of it');
        });
        let threw = false;
        try {
            effect('confetti');
        } catch {
            threw = true;
        }
        assert(threw, 'an unknown effect is a mistake, not a quiet false');
    } finally {
        Object.assign(effects, kept);
    }
});

test('game motion: a count-up lands on the real number and never passes it', () => {
    assert(countAt(0, 7, 0) === 0 && countAt(0, 7, 1) === 7, 'from and to');
    let last = -1;
    for (let p = 0; p <= 1.0001; p += 0.01) {
        const n = countAt(0, 82, p);
        assert(n >= last && n <= 82, `rises to 82 at ${p}: ${n}`);
        last = n;
    }
    assert(countAt(150, 170, 0.5) > 150 && countAt(150, 170, 0.5) < 170, 'from a start that isn\'t 0');
    assert(countAt(0, 3, 2) === 3 && countAt(0, 3, -1) === 0, 'clamped');
});

test('game motion: a tap snaps a run to its end; reduced motion draws only the end', async () => {
    const seen = [];
    const going = run(10000, (p) => seen.push(p));
    going.skip();
    await going.done;
    assert(seen.at(-1) === 1 && going.over, `snapped to the end: ${seen}`);
    const stillSeen = [];
    await run(10000, (p) => stillSeen.push(p), { motion: false }).done;
    assert(stillSeen.length === 1 && stillSeen[0] === 1, 'still: only the end');
    const steps = { a: [], b: [] };
    const both = sequence([{ at: 0, ms: 500, frame: (p) => steps.a.push(p) }, { at: 400, ms: 500, frame: (p) => steps.b.push(p) }]);
    both.skip();
    await both.done;
    assert(steps.a.at(-1) === 1 && steps.b.at(-1) === 1, 'every step lands on its end');
});

test('game pop-ups: none in the first 2 s, one at a time, the newest waiting, told once a phrase', () => {
    assert(comboMilestone(10) && comboMilestone(20) && !comboMilestone(9) && !comboMilestone(0), 'every tenth in a row');
    const q = popupQueue();
    assert(!q.offer({ kind: 'combo', count: 10 }, 1.5) && q.now === null, 'quiet at the start');
    assert(q.offer({ kind: 'combo', count: 10, phrase: 0 }, 3) && q.now.kind === 'combo', 'shown');
    q.offer({ kind: 'phrase', phrase: 0 }, 3.2);
    q.offer({ kind: 'combo', count: 20, phrase: 0 }, 3.4);
    assert(q.now.count === 10 && q.next.count === 20, 'the newest waits for the one showing');
    q.tick(3 + POPUP_SECONDS / 2);
    assert(q.now.count === 10, 'held for its time');
    q.tick(3 + POPUP_SECONDS);
    assert(q.now.count === 20 && q.next === null, 'then the next');
    assert(q.say.count === 10, 'a screen reader heard only the first of the phrase');
    q.tick(3 + POPUP_SECONDS * 2);
    q.offer({ kind: 'phrase', phrase: 1 }, 6);
    assert(q.say.kind === 'phrase', 'a new phrase is told');
    q.clear();
    assert(q.now === null && q.next === null && q.say === null, 'cleared');
    q.clear(30);
    assert(!q.offer({ kind: 'combo', count: 10 }, 31) && q.offer({ kind: 'combo', count: 10 }, 32.5), 'quiet for the first 2 s of a take started mid-song');
    // A lesson's pop-ups know no phrase: a screen reader hears at most one every SAY_GAP seconds, and a
    // quiet one (the bonus after its combo) never.
    const lesson = popupQueue();
    lesson.offer({ kind: 'combo', count: 10 }, 3);
    const first = lesson.say;
    lesson.offer({ kind: 'bonus', count: 1, quiet: true }, 3.1);
    lesson.tick(3 + POPUP_SECONDS);
    assert(lesson.now.kind === 'bonus' && lesson.say === first, 'the bonus is seen, not said');
    lesson.tick(3 + POPUP_SECONDS * 2);
    lesson.offer({ kind: 'combo', count: 20 }, 3 + POPUP_SECONDS * 2);
    assert(lesson.say === first, `not again within ${SAY_GAP} s`);
    lesson.tick(3 + SAY_GAP + 2);
    lesson.offer({ kind: 'combo', count: 30 }, 3 + SAY_GAP + 2);
    assert(lesson.say.count === 30, 'told again after the gap');
});

test('game combo bonus: the pop-up never promises more than today\'s cap still pays', () => {
    const progress = emptyProgress();
    const day = dayOf();
    assert(bonusRoom(progress) === DAILY_XP_CAP - 15, 'room once the award (10 and a star) is in');
    progress.xp.days[day] = DAILY_XP_CAP - 20;
    assert(bonusRoom(progress) === 5, 'a little room left');
    progress.xp.days[day] = DAILY_XP_CAP;
    assert(bonusRoom(progress) === 0, 'none at the cap');
});

test('game sounds: cues are short, and none sounds while off, in serious mode, or while the engine plays', async () => {
    assert(Object.keys(CUES).every((name) => cueLength(name) <= 0.85), 'every cue under a second');
    const kept = effects.sounds;
    try {
        effects.sounds = false;
        await inSkin('game', () => assert(!cueAllowed() && cue('select') === null, 'off by default'));
        effects.sounds = true;
        await inSkin('serious', () => assert(!cueAllowed(), 'never in serious mode'));
        await inSkin('game', () => {
            const playing = engine.playing;
            engine.playing = { recording: true };
            try {
                assert(!cueAllowed() && cue('record') === null, 'silent while a take runs');
            } finally {
                engine.playing = playing;
            }
        });
    } finally {
        effects.sounds = kept;
    }
});

test('game fonts: added once, from Google Fonts', () => {
    const doc = document.implementation.createHTMLDocument('fonts');
    loadGameFonts(doc);
    loadGameFonts(doc);
    const links = doc.querySelectorAll('link[data-game-fonts]');
    assert(links.length === 1 && links[0].href === GAME_FONTS && GAME_FONTS.startsWith('https://fonts.googleapis.com/'), 'one stylesheet');
});

test('game NEW RECORD: the letters take the part colours in turn', () => {
    const word = letters('New record');
    assert(word.map((l) => l.char).join('') === 'New record', 'every letter');
    assert(word.filter((l) => !l.space).map((l) => l.colour).join('') === '123412341', 'the four part colours in turn, spaces skipped');
});

test('game title cards: once a lesson on this device', () => {
    const kept = localStorage.getItem('stackapella.titlesSeen');
    try {
        localStorage.removeItem('stackapella.titlesSeen');
        assert(!titleSeen('2-vowels'), 'not seen yet');
        markTitleSeen('2-vowels');
        markTitleSeen('2-vowels');
        assert(titleSeen('2-vowels') && !titleSeen('2-hold'), 'seen, that lesson only');
        assert(JSON.parse(localStorage.getItem('stackapella.titlesSeen')).length === 1, 'kept once');
    } finally {
        if (kept === null) localStorage.removeItem('stackapella.titlesSeen');
        else localStorage.setItem('stackapella.titlesSeen', kept);
    }
});

test('game stage map: the special stage\'s target is the course\'s own two-star line, and the path snakes down', () => {
    const line = twoStarPercent();
    assert(starsFor({ percent: line }) === 2 && starsFor({ percent: line - 1 }) < 2, `two stars from ${line}%`);
    const stops = [0, 1, 2, 3, 4].map(nodeAt);
    assert(stops.every((s, i) => i === 0 || s.y > stops[i - 1].y), 'each stop below the last');
    assert(stops.some((s) => s.x < 50) && stops.some((s) => s.x > 50), 'from side to side');
    assert(segment(stops[0], stops[1]).startsWith(`M ${stops[0].x} ${stops[0].y} C`), 'a curve from stop to stop');
});

test('game character select: range, notes and leaps come from the part\'s own notes', () => {
    const notes = [{ beat: 2, pitch: 67 }, { beat: 0, pitch: 60 }, { beat: 1, pitch: 62 }, { beat: 3, pitch: 72 }, { beat: 4, pitch: null }];
    const stats = partStats(notes);
    assert(stats.low === 60 && stats.high === 72 && stats.notes === 4, `range and count, rests left out: ${JSON.stringify(stats)}`);
    assert(stats.leaps === 2 && Math.abs(stats.leapShare - 2 / 3) < 1e-9, 'a fourth or more, in order of the beats (62 to 67, 67 to 72)');
    assert(partStats([]).low === null && partStats([]).notes === 0, 'no notes');
    assert(leapWord(0.05) === 'few' && leapWord(0.2) === 'some' && leapWord(0.4) === 'many', 'few, some, many');
    assert(cellsOf(0, 10) === 0 && cellsOf(1, 100) === 1 && cellsOf(10, 10) === CELLS, 'cells never invent a value');
});

test('game combo bonus: +1 XP a note lit in a run of 10 or more, on top of the award, within the day\'s cap', () => {
    assert(xpFor({ kind: 'exercise', stars: 2 }) === 20, 'the award as before');
    assert(xpFor({ kind: 'exercise', stars: 2, bonus: 11 }) === 31, 'plus the bonus');
    assert(xpFor({ kind: 'exercise', stars: 2, bonus: -3 }) === 20 && xpFor({ kind: 'workout', bonus: 5 }) === xpFor({ kind: 'workout' }), 'never negative, and only an exercise\'s');
    const progress = emptyProgress();
    progress.xp.days[dayOf()] = DAILY_XP_CAP - 5; // the app's own (local) day
    assert(addXp(progress, { kind: 'exercise', stars: 3, bonus: 40 }).gained === 5, 'the daily cap still holds');
});
