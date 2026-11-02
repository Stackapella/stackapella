// A song's shape for its cover (js/song-shape.js; Hillel: "make the bars match the actual structure
// of the song parts"): a dash per phrase where each part sings, a late entry set in, the same rules
// the catalogue's `parts[].shape` follows.
import { coverShape, partShape, songShape } from '/js/song-shape.js';
import { assert, test } from './harness.js';

const bundled = (id) => fetch(`/templates/${id}.json`).then((r) => r.json());

test('song shape: a round\'s parts come in a phrase apart, each set in further', async () => {
    const shape = songShape(await bundled('are-you-sleeping'));
    assert(shape.map((p) => p.key).join() === 'soprano,alto,tenor,bass', 'a row a part, in order');
    const firsts = shape.map((p) => p.shape[0][0]);
    assert(firsts[0] === 0 && firsts.every((f, i) => i === 0 || f > firsts[i - 1]), `staggered entries: ${firsts}`);
    // The alto comes in two bars on (beat 8 of 88); the soprano breathes after its first held "John?"
    // (the singability pass), so its first phrase runs on past the alto's entry.
    assert(shape[1].shape[0][0] === Math.round((8 / 88) * 1000), `the alto comes in at beat 8: ${shape[1].shape[0][0]}`);
    assert(JSON.stringify(shape[0].shape.slice(0, 2)) === '[[0,125,0],[136,170,3]]', JSON.stringify(shape[0].shape.slice(0, 2)));
});

test('song shape: phrases from rests and lines of words, placed on the song\'s length', () => {
    const notes = [
        { beat: 0, length: 1, pitch: 60, br: true }, { beat: 1, length: 1, pitch: 62 }, // a phrase
        { beat: 4, length: 2, pitch: 67 }, // after a rest of two beats: the next
        { beat: 6, length: 2, pitch: 65, br: true }, // a new line of words, with no rest: the next
    ];
    const shape = partShape(notes, { from: 0, to: 8 });
    assert(JSON.stringify(shape.map(([s, e]) => [s, e])) === '[[0,250],[500,750],[750,1000]]', JSON.stringify(shape));
    assert(shape[0][2] < 0 && shape[1][2] > 0, `low then high: ${JSON.stringify(shape)}`);
    assert(partShape([], { from: 0, to: 8 }).length === 0, 'a part with no notes has no dashes');
});

test('song shape: never more than 24 dashes a part, the closest joined', () => {
    const notes = Array.from({ length: 60 }, (_, i) => ({ beat: i * 2 + (i % 3 === 0 ? 1 : 0), length: 1, pitch: 60 + (i % 5) }));
    const shape = partShape(notes, { from: 0, to: 125 });
    assert(shape.length === 24, `24 dashes: ${shape.length}`);
    assert(shape.every(([s, e], i) => s < e && (i === 0 || s >= shape[i - 1][1])), 'in order, never overlapping');
    assert(shape.every(([, , lean]) => Math.abs(lean) <= 6), 'the lean within six semitones');
});

test('song shape: the catalogue\'s shape when it sends one, else the notes, else nothing', async () => {
    const sent = { id: 'x', parts: [{ key: 'soprano', shape: [[0, 500, 1]] }, { key: 'alto', shape: [] }] };
    assert(JSON.stringify(coverShape(sent)) === '[{"key":"soprano","shape":[[0,500,1]]},{"key":"alto","shape":[]}]', 'the sent shape');
    const song = await bundled('amazing-grace');
    assert(coverShape(song) === coverShape(song), 'worked out once a song');
    assert(coverShape({ id: 'y', parts: [{ key: 'soprano', guide: 'Gd7Kp2' }] }) === null, 'no notes and no shape: plain dashes');
});
