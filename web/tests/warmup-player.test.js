// The warm-up player (js/public/tools/warmup-player.js): a warm-up from the courses lead's list
// (course/warmups.json), its notes read from its course step, climbing through keys as the app's workout
// does (js/warmup.js) inside both the voice and the entry's bounds, heard then sung or sung along; the screen.
import { loadWarmup, mountWarmupPlayer, ownVoice, reachOf, timelineOf, VOICES, warmupOf, WORDS } from '/js/public/tools/warmup-player.js';
import { assert, test } from './harness.js';

const scale = { tempo: 84, notes: [0, 2, 4, 5, 7, 5, 4, 2, 0].map((step, beat) => ({ beat, length: 1, step, lyric: 'brr' })) };
const entry = {
    id: 'test-trills', name: { en: 'Lip trills' }, from: { course: 'breath', lesson: 'br-2-hill', step: 2 }, pattern: [0, 2, 4, 5, 7, 5, 4, 2, 0], sound: 'trill',
    instruction: { en: 'Trill up five notes and back.' }, stop: { en: 'Stop if you feel light-headed.' }, bounds: { low: -5, high: 11 },
    tempo: { min: 60, max: 96, default: 72 }, climb: { default: 4, max: 4 },
};

test('warm-up player: a warm-up loads from the list and its course step, never a copy', async () => {
    const files = {
        '/course/warmups.json': { warmups: [entry] },
        '/course/plus/courses.json': { courses: [{ id: 'breath', file: 'breath/course.json' }] },
        '/course/plus/breath/course.json': { units: [{ lessons: [{ id: 'br-2-hill', steps: [{}, {}, { exercise: scale }] }] }] },
    };
    const got = await loadWarmup('test-trills', { get: async (url) => files[url] });
    assert(got.entry.id === 'test-trills' && got.exercise === scale, 'the entry and its step');
    let missing = false;
    await loadWarmup('nope', { get: async (url) => files[url] }).catch(() => (missing = true));
    assert(missing, 'an unknown warm-up rejects');
    const w = warmupOf(got);
    assert(w.sound === 'trill' && w.tempo === 72 && w.climb === 4 && w.steps.join() === entry.pattern.join(), JSON.stringify(w));
    assert(warmupOf(got, { climb: 9 }).climb === 4, 'never over the entry\'s climb');
});

test('warm-up player: the rounds climb a half step and back, inside the voice and the entry\'s bounds, heard then sung', () => {
    const warmup = { ...warmupOf({ entry, exercise: scale }), climb: 2 };
    const along = timelineOf(warmup, { voice: VOICES.medium, way: 'along', bounds: entry.bounds });
    assert(JSON.stringify(along.shifts) === '[0,1,2,1,0]' && along.rounds === 5 && along.notes.length === 45, JSON.stringify(along.shifts));
    assert(along.notes[0].midi === VOICES.medium.home && along.notes[9].midi === VOICES.medium.home + 1 && along.notes[8].length === 3, 'a half step up each round, the last note held');
    const echo = timelineOf(warmup, { voice: VOICES.medium, way: 'echo' });
    assert(echo.notes.length === 90 && echo.notes.filter((n) => n.guide).length === 45 && echo.notes[9].guide === false && echo.notes[9].midi === echo.notes[0].midi, 'heard, then sung');
    assert(timelineOf(warmup, { voice: VOICES.medium, times: 2 }).rounds === 10, 'twice through');
    // The tighter of the voice and the bounds: a high voice's top stays under home + 11.
    assert(JSON.stringify(reachOf(VOICES.high, entry.bounds)) === '{"low":57,"high":73}', JSON.stringify(reachOf(VOICES.high, entry.bounds)));
    const tight = timelineOf({ ...warmup, climb: 4 }, { voice: { low: 50, high: 64, home: 55 }, bounds: { low: -5, high: 9 } });
    assert(tight.notes.every((n) => n.midi >= 50 && n.midi <= 64), 'inside both');
    assert(JSON.stringify(ownVoice({ low: 48, high: 69 })) === '{"low":48,"high":69,"home":55}' && ownVoice(null) === null, 'your own range');
});

test('warm-up player: the screen shows the round, the key, the instruction and the stop line, and remembers its settings', () => {
    try {
        localStorage.removeItem('stackapella.tools.warmups-test-trills');
    } catch (error) {
        console.warn('storage', error);
    }
    const words = { ...Object.fromEntries(WORDS.map((k) => [k, `[${k}]`])), round: ':n of :total', inKey: 'in :key', span: ':low to :high', timesValue: 'x:n', climbValue: ':n' };
    const el = document.createElement('div');
    const slot = document.createElement('div');
    document.body.append(slot, el);
    const player = mountWarmupPlayer(el, { words, keySlot: slot, warmup: { entry, exercise: scale } });
    const card = el.querySelector('[data-ui=tool]');
    const $ = (s) => card.querySelector(s);
    assert($('[data-round]').textContent === '1 of 9' && $('[data-key]').textContent === 'in G', `${$('[data-round]').textContent} ${$('[data-key]').textContent}`);
    assert($('[data-how-to]').textContent === 'Trill up five notes and back.' && $('[data-stop-if]').textContent === 'Stop if you feel light-headed.' && $('[data-stop-line]').textContent === 'Stop if you feel light-headed.' && !$('[data-stop-line]').hidden, 'the instruction, and the stop line beside Start and in More');
    assert(card.querySelectorAll('[data-keyboard] [data-midi]').length > 20 && $('[data-ui=tool-stat]').textContent === 'C3 to A4', $('[data-ui=tool-stat]').textContent);
    $('[data-segment=low]').click();
    assert($('[data-key]').textContent === 'in C' && JSON.parse(localStorage.getItem('stackapella.tools.warmups-test-trills')).settings.voice === 'low', 'a low voice, remembered');
    slot.querySelector('[data-step="1"]').click();
    assert(JSON.parse(localStorage.getItem('stackapella.tools.warmups-test-trills')).settings.tempo === 76, 'the tempo, remembered');
    player.stop();
    assert(slot.querySelector('[data-step]'), 'stopping keeps the brand row\'s tempo');
    player.destroy();
    assert(!slot.querySelector('[data-step]'), 'destroying takes it away');
    el.remove();
    slot.remove();
    localStorage.removeItem('stackapella.tools.warmups-test-trills');
});
