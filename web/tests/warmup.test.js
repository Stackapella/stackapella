// A teacher's warm-up by link (js/warmup.js; docs/warmup-builder-plan.md): the code round-trips,
// stays short, keeps every limit whatever a link says, and the rounds climb and come back down.
import { cleanWarmup, codeFrom, decodeWarmup, encodeWarmup, LIMITS, warmupExercise, warmupRounds, warmupStep } from '/js/warmup.js';
import { chooseWarmup, chosenWarmup, keptWarmups } from '/js/stores/warmups.js';
import { assert, test } from './harness.js';

const FIVE = { steps: [0, 2, 4, 5, 7, 5, 4, 2, 0], sound: 'ah', tempo: 84, start: -3, climb: 5, name: 'Five-note scale' };

test('warm-up: a code round-trips, short enough for a message', () => {
    const code = encodeWarmup(FIVE);
    assert(/^[A-Za-z0-9_-]+$/.test(code), `url-safe: ${code}`);
    assert(code.length < 140, `short: ${code.length} characters`);
    const back = decodeWarmup(code);
    assert(JSON.stringify(back) === JSON.stringify(cleanWarmup(FIVE)), JSON.stringify(back));
    assert(codeFrom('#' + code) === code && codeFrom('') === null, 'the code from the fragment');
});

test('warm-up: every limit holds, whatever a link says', () => {
    const wild = cleanWarmup({ steps: Array.from({ length: 40 }, (_, i) => i % 5), sound: 'shout', tempo: 400, start: -20, climb: 30, name: 'x'.repeat(90) + '\n<b>' });
    assert(wild.steps.length === LIMITS.notes, `at most ${LIMITS.notes} notes`);
    assert(wild.sound === 'ah' && wild.tempo === LIMITS.tempo[1] && wild.start === LIMITS.start[0] && wild.climb === LIMITS.climb[1], JSON.stringify(wild));
    assert(wild.name.length === LIMITS.name && !/[\n\r]/.test(wild.name), 'the name: 40 characters, one line');
    assert(cleanWarmup({ steps: [0, 30] }) === null, 'a span past an octave and a half');
    assert(cleanWarmup({ steps: [] }) === null && cleanWarmup(null) === null, 'nothing to sing');
});

test('warm-up: a broken, edited or foreign code is refused, never a crash', () => {
    const code = encodeWarmup(FIVE);
    assert(decodeWarmup(code.slice(0, 10)) === null, 'cut short');
    assert(decodeWarmup('not a code!') === null, 'not base64url');
    assert(decodeWarmup(btoa(JSON.stringify({ v: 9, p: [0] })).replace(/=+$/, '')) === null, 'another version');
});

test('warm-up: rounds go up a half step each, then back down, within your range', () => {
    assert(JSON.stringify(warmupRounds({ ...FIVE, climb: 2 })) === '[0,1,2,1,0]', 'up and back');
    // Home G3 (55), the warm-up from three below to four above: a range to 62 leaves out the rounds that would pass it.
    const within = warmupRounds(FIVE, { home: 55, range: { low: 48, high: 62 } });
    assert(within.every((s) => 55 - 3 + 7 + s <= 62) && within.length < 11, JSON.stringify(within));
    const ex = warmupExercise(FIVE);
    assert(ex.notes[0].step === -3 && ex.notes.at(-1).length > 1 && ex.notes.every((n) => n.lyric === 'ah'), JSON.stringify(ex.notes[0]));
});

test('warm-up: a kept one as the workout\'s warm-up step, its rounds up and back', () => {
    const step = warmupStep({ ...FIVE, climb: 2 });
    assert(step.kind === 'warmUp' && step.rounds === 5 && JSON.stringify(step.roundShifts) === '[0,1,2,1,0]', JSON.stringify(step));
    assert(step.exercise.notes.length === FIVE.steps.length && step.name === FIVE.name, 'its notes and name');
});

test('warm-up: kept ones newest first, removed ones hidden; the workout\'s choice only while it\'s kept', async () => {
    const { learning } = await import('/js/stores/learning.js');
    const keys = ['stackapella.warmups', 'stackapella.warmups.chosen'];
    const had = keys.map((k) => localStorage.getItem(k));
    const before = learning.progress;
    try {
        keys.forEach((k) => localStorage.removeItem(k));
        const a = encodeWarmup(FIVE);
        const b = encodeWarmup({ ...FIVE, name: 'Other' });
        learning.progress = { ...before, warmups: [{ code: a, name: FIVE.name, keptAt: 10 }, { code: b, name: 'Other', keptAt: 20 }] };
        assert(keptWarmups().map((w) => w.code).join() === [b, a].join(), 'newest first');
        assert(chosenWarmup() === null, 'ours until one is chosen');
        chooseWarmup(a);
        assert(chosenWarmup() === a, 'chosen');
        learning.progress = { ...before, warmups: [{ code: a, name: FIVE.name, keptAt: 10, removedAt: 30 }, { code: b, name: 'Other', keptAt: 20 }] };
        assert(keptWarmups().map((w) => w.code).join() === b && chosenWarmup() === null, 'a removed one is gone, and no longer chosen');
        // An older version's list, on this device, still shows until it's read in.
        localStorage.setItem('stackapella.warmups', JSON.stringify([{ code: a, name: FIVE.name, keptAt: 40 }]));
        assert(keptWarmups().map((w) => w.code).join() === [a, b].join(), 'the older list, merged in');
        chooseWarmup(null);
        assert(localStorage.getItem('stackapella.warmups.chosen') === null, 'back to ours');
    } finally {
        learning.progress = before;
        keys.forEach((k, i) => (had[i] === null ? localStorage.removeItem(k) : localStorage.setItem(k, had[i])));
    }
});

test('warm-up QR: dark squares become one path, a square each, nothing else', async () => {
    const { qrPath } = await import('/js/qr.js');
    assert(qrPath(2, (r, c) => r === c) === 'M0 0h1v1h-1zM1 1h1v1h-1z', qrPath(2, (r, c) => r === c));
    assert(qrPath(3, () => false) === '', 'all light: no path');
});

test('kept warm-ups: two devices as one list, a removal never undone by the other copy', async () => {
    const { mergeWarmups } = await import('/js/warmup.js');
    const here = [{ code: 'a', name: 'Sirens', keptAt: 10 }, { code: 'b', name: 'Lip trill', keptAt: 20, removedAt: 50 }];
    const there = [{ code: 'b', name: 'Lip trill', keptAt: 20 }, { code: 'c', name: '', keptAt: 30 }];
    const merged = mergeWarmups(here, there);
    assert(merged.map((w) => w.code).join() === 'b,c,a', merged.map((w) => w.code).join());
    assert(merged.find((w) => w.code === 'b').removedAt === 50, 'the removal stands');
    const again = mergeWarmups([{ code: 'b', name: 'Lip trill', keptAt: 60 }], merged);
    assert(!again.find((w) => w.code === 'b').removedAt, 'kept again later: it comes back');
    assert(mergeWarmups(Array.from({ length: 50 }, (_, i) => ({ code: `w${i}`, keptAt: i }))).length === 40, 'at most 40');
});

test('warm-up from a lesson: its notes in order, moved to start on do, the lyric its sound; else none', async () => {
    const { warmupFromExercise } = await import('/js/warmup.js');
    const w = warmupFromExercise({ tempo: 72, notes: [{ beat: 3, step: 4, lyric: 'oo' }, { beat: 0, step: 2, lyric: 'oo' }, { beat: 6, step: 7, lyric: 'oo' }] });
    assert(w.steps.join() === '0,2,5' && w.start === 2 && w.sound === 'oo' && w.tempo === 72, JSON.stringify(w));
    assert(warmupFromExercise({ tempo: 72, notes: [{ beat: 0, step: 0 }, { beat: 1, step: 1 }, { beat: 2, step: 2 }] }) === null, 'a half step off the scale: none');
    assert(warmupFromExercise({ tempo: 72, notes: [{ beat: 0, step: 0 }, { beat: 1, step: 2 }] }) === null, 'two notes: none');
});
