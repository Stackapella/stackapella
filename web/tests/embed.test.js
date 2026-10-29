// The embedded lane's take (js/embed/take.js): a voice made from a part's notes, sung like a person,
// with a note sung flat that misses; and its sound.
import { expressiveTrack, noteResults, renderTrack } from '/js/embed/take.js';
import { assert, test } from './harness.js';

const NOTES = [
    { beat: 1, length: 2, pitch: 59, br: true },
    { beat: 3, length: 1, pitch: 62 },
    { beat: 4, length: 2, pitch: 67 },
    { beat: 6, length: 1, pitch: 66 },
    { beat: 7, length: 2, pitch: 64 },
    { beat: 9, length: 1, pitch: 60 },
    { beat: 10, length: 2, pitch: 59 },
];

test('embed: the made-up voice sings every note, misses only the one sung flat, and swells on long notes', () => {
    const track = expressiveTrack(NOTES, { tempo: 80, flat: [9] });
    const results = noteResults(NOTES, track, { tempo: 80 });
    assert(results.every((r) => r.hit === (r.beat !== 9)), `hits: ${JSON.stringify(results.map((r) => [r.beat, r.hit]))}`);
    const long = track.filter((p) => p.seconds >= 4 * 0.75 && p.seconds < 6 * 0.75).map((p) => p.level);
    assert(Math.max(...long) > long[2] + 0.2, 'no swell on the long note');
    const wobble = track.filter((p) => p.seconds > 3.6 && p.seconds < 4.4).map((p) => p.midi);
    assert(Math.max(...wobble) - Math.min(...wobble) > 0.3, 'no vibrato on the long note');
    assert(!track.some((p) => p.seconds < 0.74), 'a voice in the rest before the first note');
});

test('embed: the voice sounds for as long as its track, and is silent in its rests', () => {
    const track = expressiveTrack(NOTES, { tempo: 80 });
    const samples = renderTrack(track, 8000);
    assert(Math.abs(samples.length / 8000 - (track.at(-1).seconds + 0.3)) < 0.01, 'the length');
    const rest = samples.subarray(0, 0.5 * 8000);
    assert(rest.every((s) => Math.abs(s) < 1e-3), 'sound before the first note');
    const sung = samples.subarray(2 * 8000, 3 * 8000);
    assert(Math.max(...sung.map(Math.abs)) > 0.05, 'silent while singing');
});
