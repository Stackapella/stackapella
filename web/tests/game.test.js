// Game mode's arcade moments (docs/game-elements-plan.md): each shows only in game mode with its own
// switch on, Game sounds off until chosen; count-ups land on the real number; pop-ups wait out a
// take's first 2 seconds, show one at a time and tell a screen reader once a phrase; and a chip cue
// never sounds while the engine plays.
import { appearance } from '/js/stores/appearance.js';
import { EFFECTS, effect, effects } from '/js/game/effects.js';
import { countAt, run, sequence } from '/js/game/motion.js';
import { POPUP_SECONDS, SAY_GAP, bonusRoom, comboMilestone, heldToEnd, popupQueue } from '/js/game/popups.js';
import { CUES, blip, chipCountIn, cue, cueAllowed, cueLength } from '/js/game/chip.js';
import { GAME_FONTS, loadGameFonts } from '/js/game/fonts.js';
import { letters } from '/js/game/record.js';
import { stageCues } from '/js/game/tally.js';
import { badgeNews, badgeWords, sayBadges } from '/js/game/badge-news.js';
import { route } from '/js/router.js';
import { toasts } from '/js/components/ui.js';
import { markTitleSeen, titleSeen } from '/js/game/title-card.js';
import { nodeAt, segment, twoStarPercent } from '/js/game/stage-map.js';
import { CELLS, cellsOf, leapWord, partStats } from '/js/game/part-select.js';
import { addXp, DAILY_XP_CAP, dayOf, emptyProgress, starsFor, xpFor } from '/js/course.js';
import { engine } from '/js/audio.js';
import { click } from '/js/dsp.js';
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

test('game sounds: a stage clear sounds each new star, then the count, then the level-up, in turn', async () => {
    const kept = effects.sounds;
    try {
        effects.sounds = true;
        await engine.unlock();
        await inSkin('game', () => {
            const end = document.createElement('div');
            end.innerHTML = '<span class="gem-pop"></span><span class="gem-pop"></span><span></span><p data-level-up></p>';
            const at = engine.ctx.currentTime + 5;
            const sounds = stageCues(end, at, 1, true);
            try {
                assert(sounds.length === 4, `two stars, the count and the level-up: ${sounds.length}`);
                assert(sounds.every((s, i) => i === 0 || s.end > sounds[i - 1].end), 'one after another');
                assert(sounds[2].end - cueLength('tallyEnd') >= at + 1 - 1e-6, "the count's end no earlier than the count");
            } finally {
                for (const sound of sounds) sound.stop();
            }
            end.innerHTML = '<span></span>';
            const quiet = stageCues(end, at, 1, false);
            assert(quiet.length === 0, 'no new stars, no XP and no level: silent');
        });
    } finally {
        effects.sounds = kept;
    }
});

test('game badges: a lesson keeps them for its end, merged; elsewhere one short toast at a time', async () => {
    const was = route.name;
    const kept = toasts.list;
    try {
        toasts.list = [];
        route.name = 'learn';
        await new Promise((r) => setTimeout(r)); // the route's watcher sees the lesson
        sayBadges(['First note']);
        sayBadges(['First lesson', 'Three stars', 'First note']);
        assert(toasts.list.length === 0, 'no toast in a lesson');
        assert(badgeNews.names.join() === 'First note,First lesson,Three stars', `kept once each, in order: ${badgeNews.names.join()}`);
        assert(badgeWords(badgeNews.names).includes('First note') && badgeWords(['One']) !== badgeWords(['One', 'Two']), 'one or many, in words');
        // Left before the end: said once on the way out.
        route.name = 'projects';
        await new Promise((r) => setTimeout(r));
        assert(badgeNews.names.length === 0 && toasts.list.filter((x) => x.tone === 'badge').length === 1, 'said on the way out');
        sayBadges(['Two']);
        assert(toasts.list.filter((x) => x.tone === 'badge').length === 1, 'one badge toast at a time');
    } finally {
        badgeNews.names.splice(0);
        toasts.list = kept;
        route.name = was;
    }
});

test('game pop-ups: "Held it!" only for a long note sung through and in tune to its end', () => {
    const note = { start: 10, end: 12, pitch: 60 };
    const line = (from, to, midi = (s) => 60.05) => Array.from({ length: Math.round((to - from) * 50) }, (_, i) => ({ seconds: from + i / 50, midi: midi(from + i / 50), alpha: 1 }));
    assert(heldToEnd(line(10.05, 12), note), 'held from its start to its end');
    assert(heldToEnd(line(10.05, 12, () => 48.1), note), 'an octave down counts, as in the score');
    assert(!heldToEnd(line(10, 12, (s) => (s > 11.85 ? 60.6 : 60)), note), 'drifting off at the end is not held');
    assert(!heldToEnd(line(11, 12), note), 'half sung is not held');
    assert(!heldToEnd(line(10, 12, (s) => (s < 11 ? 60.5 : 60)), note), 'half of it out of tune is not held');
    assert(!heldToEnd(line(10, 11), { start: 10, end: 11, pitch: 60 }), 'a short note is never "held"');
    assert(!heldToEnd(line(10, 12).map((p) => ({ ...p, alpha: 0.4 })), note), 'a fading line is no voice');
});

test('game daily: a scored take started from today\'s challenge stamps today, kept on the device; another song doesn\'t', async () => {
    const d = await import('/js/game/daily.js');
    const keptTab = sessionStorage.getItem('stackapella.daily');
    const keptDays = localStorage.getItem('stackapella.dailyDays');
    const before = [...d.dailyDays];
    try {
        await inSkin('game', () => {
            d.dailyDays.clear();
            const today = d.utcDay();
            sessionStorage.setItem('stackapella.daily', JSON.stringify({ date: today, templateId: 'michael-row', partKey: 'tenor' }));
            d.stampDaily('amazing-grace', 'alto');
            assert(!d.dailyDays.size, 'another song: no stamp');
            d.stampDaily('michael-row', 'tenor');
            assert(d.dailyDays.has(today) && d.stamped(today), 'today stamped');
            assert(JSON.parse(localStorage.getItem('stackapella.dailyDays')).includes(today), 'kept on the device');
        });
        await inSkin('serious', () => assert(!d.stamped(d.utcDay()), 'serious mode shows no stamp'));
    } finally {
        d.dailyDays.clear();
        for (const day of before) d.dailyDays.add(day);
        if (keptTab === null) sessionStorage.removeItem('stackapella.daily');
        else sessionStorage.setItem('stackapella.daily', keptTab);
        if (keptDays === null) localStorage.removeItem('stackapella.dailyDays');
        else localStorage.setItem('stackapella.dailyDays', keptDays);
    }
});

test('game sounds: the count-in as chip blips, no louder than the click, only with Game sounds, and only for the count-in', async () => {
    const rate = 48000;
    const energy = (samples) => samples.reduce((sum, x) => sum + x * x, 0) / rate;
    const high = blip(rate, 84, 0.07);
    assert(high.length === Math.round(0.07 * rate) && Math.max(...high.map(Math.abs)) <= 0.22 + 1e-6, 'short and level');
    assert(Math.abs(high.at(-1)) < 1e-3, 'no click at its end');
    assert(energy(high) <= energy(click(rate, { hz: 2500 })) * 1.1, `no louder than the click: ${energy(high)} against ${energy(click(rate, { hz: 2500 }))}`);
    const kept = effects.sounds;
    await engine.unlock();
    const plain = engine.scheduleBuffer;
    const seen = [];
    try {
        effects.sounds = false;
        await inSkin('game', () => assert(chipCountIn() === null, 'Game sounds off: the plain clicks'));
        await inSkin('serious', () => assert(chipCountIn() === null, 'never in serious mode'));
        effects.sounds = true;
        let blips = null;
        await inSkin('game', () => (blips = chipCountIn()));
        assert(blips?.accent && blips.normal && blips.accent !== engine.clicks.accent, 'two chip buffers');
        engine.scheduleBuffer = function (buffer, ...rest) {
            seen.push(buffer);
            return plain.call(this, buffer, ...rest);
        };
        const playing = engine.play({ fromSeconds: 0, bpm: 240, countInBeats: 4, countInSounds: blips, metronome: true });
        while (engine.ctx.currentTime < playing.startTime + 1.2) await new Promise((r) => requestAnimationFrame(r));
        await engine.stop();
        const mine = (b) => b === blips.accent || b === blips.normal;
        assert(seen.slice(0, 4).every(mine) && seen[0] === blips.accent, 'the count-in: blips, high on the bar line');
        assert(seen.length > 4 && !seen.slice(4).some(mine), 'once the take starts: the plain clicks only');
    } finally {
        engine.scheduleBuffer = plain;
        effects.sounds = kept;
    }
});

test('game share card: NEW RECORD only when a take beats a best from an earlier try', async () => {
    const { beatsBest } = await import('/js/stores/learning.js');
    assert(!beatsBest(undefined, 90), 'a first try is never a record');
    assert(!beatsBest({ stars: 1, bestPercent: null }, 90), 'no best kept yet: not a record');
    assert(beatsBest({ stars: 2, bestPercent: 80 }, 91), 'beat it: a record');
    assert(!beatsBest({ stars: 3, bestPercent: 91 }, 91), 'equal is not a record');
    assert(!beatsBest({ stars: 3, bestPercent: 95 }, null), 'no percent: not a record');
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

test('game ghost: a take\'s pitch line packs to 20 frames a second and back, placed on the part', async () => {
    const { decodeGhost, encodeGhost, placeGhost, GHOST_RATE } = await import('/js/game/ghost.js');
    const { FRAME_RATE } = await import('/js/pitchtrack.js');
    // One second of A4 (440 Hz), half a second of nothing, one second of A3.
    const frames = FRAME_RATE * 2.5;
    const hz = new Float32Array(frames).map((_, i) => (i < FRAME_RATE ? 440 : i < FRAME_RATE * 1.5 ? 0 : 220));
    const ghost = encodeGhost({ hz, clarity: new Float32Array(frames).fill(0.9) }, { startSeconds: 3 });
    const points = decodeGhost(ghost);
    assert(ghost.rate === GHOST_RATE && points.length === GHOST_RATE * 2.5, `${points.length} frames`);
    assert(points[0].seconds === 3 && Math.abs(points[0].midi - 69) < 0.01, 'starts where the take did, on A4');
    assert(points[GHOST_RATE * 1.2].midi === null, 'a gap where nothing was sung');
    assert(Math.abs(points.at(-1).midi - 57) < 0.01, 'then A3');
    // Sung an octave below a part around A4: placed back on it; a key change moves it too.
    const notes = [{ pitch: 69 }, { pitch: 71 }];
    const low = points.map((p) => (p.midi === null ? p : { ...p, midi: p.midi - 12 }));
    assert(Math.abs(placeGhost(low, notes)[0].midi - 69) < 0.01, 'an octave off is raced on the part');
    assert(Math.abs(placeGhost(points, notes, { keyOffset: 0, transpose: 2 })[0].midi - 71) < 0.01, 'a key up two since: the line moves with it');
});

test('game ghost: signed in with none kept yet (data: null) is no ghost, quietly; one kept loads', async () => {
    const { loadGhost } = await import('/js/game/ghost.js');
    const { account } = await import('/js/stores/account.js');
    const { useTransport } = await import('/js/api.js');
    const { mockFetch } = await import('/js/api-mock.js');
    const where = { templateId: 'amazing-grace', templateVersion: 3, partKey: 'alto', scoringVersion: 2 };
    const me = account.me;
    let answer = { data: null };
    try {
        account.me = { id: 1 };
        useTransport(async () => new Response(JSON.stringify(answer), { status: 200, headers: { 'Content-Type': 'application/json' } }));
        assert((await loadGhost(where)) === null, 'none kept: null');
        answer = { data: { templateVersion: 3, scoringVersion: 2, score: 80, rate: 20, startSeconds: 0, keyOffset: 0, pitches: '' } };
        assert((await loadGhost(where))?.score === 80, 'one kept: loaded');
        answer = { data: { ...answer.data, templateVersion: 2 } };
        assert((await loadGhost(where)) === null, "another version's: none");
    } finally {
        account.me = me;
        useTransport(mockFetch);
    }
});

test('game ghost: a guest keeps only their best on the device, a few at most', async () => {
    const { deviceGhost, keepDeviceGhost } = await import('/js/game/ghost.js');
    const kept = localStorage.getItem('stackapella.ghosts');
    try {
        localStorage.removeItem('stackapella.ghosts');
        const where = { templateId: 'song', templateVersion: 1, partKey: 'alto', scoringVersion: 1 };
        assert(keepDeviceGhost(where, { score: 70, rate: 20, startSeconds: 0, pitches: 'AAA=' }), 'the first is kept');
        assert(!keepDeviceGhost(where, { score: 60, rate: 20, startSeconds: 0, pitches: 'AAA=' }) && deviceGhost(where).score === 70, 'a lower score never replaces it');
        assert(keepDeviceGhost(where, { score: 82, rate: 20, startSeconds: 0, pitches: 'AAA=' }) && deviceGhost(where).score === 82, 'a better one does');
        assert(deviceGhost({ ...where, templateVersion: 2 }) === null, 'a new version of the song starts over');
        for (let i = 0; i < 25; i++) keepDeviceGhost({ ...where, partKey: `p${i}` }, { score: 50, rate: 20, startSeconds: 0, pitches: 'AAA=' });
        assert(Object.keys(JSON.parse(localStorage.getItem('stackapella.ghosts'))).length === 20, 'twenty at most');
    } finally {
        if (kept === null) localStorage.removeItem('stackapella.ghosts');
        else localStorage.setItem('stackapella.ghosts', kept);
    }
});
