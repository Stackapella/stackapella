// What's next (js/whatsnext.js): a song that fits your range and one a step harder (the song of the
// month ended with every song free, 2026-11-07); never the one just sung or one finished.
import { whatsNext } from '/js/whatsnext.js';
import { assert, test } from './harness.js';

const song = (id, difficulty, ranges) => ({ id, difficulty, parts: ranges.map(([low, high], i) => ({ key: `p${i}`, range: { low, high } })) });
const songs = [
    song('grace', 'beginner', [[60, 72], [55, 67]]),
    song('round', 'beginner', [[62, 74]]),
    song('low', 'beginner', [[40, 52]]),
    song('carol', 'intermediate', [[60, 76]]),
    song('deep', 'advanced', [[48, 60]]),
];

test("what's next: a song that fits and one harder, no song of the month", () => {
    const next = whatsNext({ songs, current: { id: 'grace', difficulty: 'beginner' }, range: { low: 58, high: 76 } });
    assert(next.map((n) => `${n.kind}:${n.song.id}`).join() === 'fits:round,harder:carol', next.map((n) => `${n.kind}:${n.song.id}`).join());
    assert(next[0].part.key === 'p0', 'with the part that fits');
});

test("what's next: never the song just sung or one finished; nothing to fit without a range", () => {
    const next = whatsNext({ songs, current: { id: 'grace', difficulty: 'beginner' }, finished: ['carol', 'round'], range: null });
    assert(next.every((n) => !['grace', 'carol', 'round'].includes(n.song.id)), 'skipped');
    assert(!next.some((n) => n.kind === 'fits'), 'no fit without a range');
});

test("what's next: songs that can be started come first", () => {
    const next = whatsNext({ songs, current: { id: 'grace', difficulty: 'beginner' }, range: { low: 58, high: 76 }, startable: (s) => s.id !== 'round' });
    assert(next[0].kind === 'fits' && next[0].song.id !== 'round', `a startable song fits first: ${next[0].song.id}`);
});
