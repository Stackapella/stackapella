// Tune the chord's meter (js/tuning.js; docs/tune-chord-plan.md, slice 2): the chord a held note sits
// in (the song's, or the other parts'), and your voice against that note's pure target, from its
// centre over the last half second: ringing, close, off, or too wide a vibrato to judge.
import { chordAt, ringReading } from '/js/tuning.js';
import { assert, test } from './harness.js';

/** Your voice at `cents` from `pitch`, 50 readings a second from `from` to `to`, with a vibrato of `swing` cents. */
const voice = (pitch, cents, { from = 0, to = 2, swing = 0 } = {}) => {
    const out = [];
    for (let t = from; t <= to + 1e-9; t += 0.02) out.push({ seconds: t, midi: pitch + (cents + swing * Math.sin(2 * Math.PI * 5.5 * t)) / 100, alpha: 1 });
    return out;
};
const C = { root: 0, quality: 'maj', bass: null };

test('tuning: a held third sung pure rings; on the piano it is 14 cents off, close or off; a wide vibrato is waved', () => {
    const note = { start: 0, end: 2, pitch: 64 };
    const at = (cents, swing = 0) => ringReading(voice(64, cents, { swing }), { now: 1.5, note, chord: C });
    const pure = at(-13.7);
    assert(pure.state === 'ringing' && pure.degree === 'third' && Math.abs(pure.pure + 13.69) < 0.01 && Math.abs(pure.off) < 0.1, JSON.stringify(pure));
    assert(at(0).state === 'off' && Math.abs(at(0).off - 13.69) < 0.1, 'the piano\'s third, 14 cents from pure');
    assert(at(-5).state === 'close', 'close within 10');
    assert(at(-13.7, 30).state === 'waved', 'too wide a vibrato to tune');
    assert(at(-13.7 + 1200).state === 'ringing', 'an octave away is the same note');
});

test('tuning: nothing in the first 300 ms, before enough voice, after it stops, or on a passing note', () => {
    const note = { start: 0, end: 2, pitch: 64 };
    assert(ringReading(voice(64, -13.7), { now: 0.2, note, chord: C }) === null, 'the first 300 ms');
    assert(ringReading(voice(64, -13.7, { to: 0.45 }), { now: 0.45, note, chord: C }) === null, 'not enough yet');
    assert(ringReading(voice(64, -13.7, { to: 1 }), { now: 1.5, note, chord: C }) === null, 'the voice stopped');
    assert(ringReading(voice(62, 0), { now: 1.5, note: { ...note, pitch: 62 }, chord: C }) === null, 'D over C major has no target');
    assert(ringReading(voice(64, -13.7), { now: 1.5, note, chord: null }) === null, 'no chord, no meter');
});

test('tuning: the barbershop seventh, a transposed song, and the root that is the piano\'s own pitch', () => {
    const seventh = ringReading(voice(70, -31.2), { now: 1.5, note: { start: 0, end: 2, pitch: 70 }, chord: { root: 0, quality: 'dom7' } });
    assert(seventh.state === 'ringing' && seventh.degree === 'seventh', JSON.stringify(seventh));
    // The song moved up 2: its C chord sounds as D; F# is D's third.
    const up = ringReading(voice(66, -13.7), { now: 1.5, note: { start: 0, end: 2, pitch: 66 }, chord: C, transpose: 2 });
    assert(up.degree === 'third' && up.state === 'ringing', JSON.stringify(up));
    const root = ringReading(voice(60, 0), { now: 1.5, note: { start: 0, end: 2, pitch: 60 }, chord: C });
    assert(root.degree === 'root' && root.pure === 0 && root.state === 'ringing', 'the root is the reference');
});

test('tuning: the chord at a beat is the song\'s own, else what the other parts sing', () => {
    const notes = (pitches) => pitches.map((pitch) => ({ beat: 0, length: 4, pitch }));
    const song = { chords: [{ beat: 0, root: 7, quality: 'maj', bass: null }, { beat: 4, root: 0, quality: 'maj', bass: 4 }], parts: [] };
    assert(chordAt(song, 2).root === 7 && chordAt(song, 4).bass === 4 && chordAt({ ...song, chords: [{ beat: 2, root: 0, quality: 'maj' }] }, 1) === null, 'the song\'s chords');
    const parts = (lead, others) => ({ parts: [{ key: 'lead', guide: { notes: notes([lead]) } }, ...others.map((p, i) => ({ key: `p${i}`, guide: { notes: notes([p]) } }))] });
    assert(JSON.stringify(chordAt(parts(64, [48, 55, 58]), 1, { focusKey: 'lead' })) === JSON.stringify({ root: 0, quality: 'dom7', bass: null }), 'C, G, Bb: a C seventh');
    assert(chordAt(parts(64, [45, 60, 64]), 1, { focusKey: 'lead' }).quality === 'min' && chordAt(parts(64, [45, 60, 64]), 1, { focusKey: 'lead' }).root === 9, 'A, C, E: A minor');
    assert(chordAt(parts(64, [52, 60, 67]), 1, { focusKey: 'lead' }).bass === 4, 'E under C and G: C over E');
    assert(chordAt(parts(64, [48]), 1, { focusKey: 'lead' }) === null, 'one other note is no chord');
});

test('tuning: in a lesson, a note\'s own cents is its pure target, judged again', async () => {
    const { judged } = await import('/js/components/exercise.js');
    const reading = { degree: 'seventh', pure: -31, cents: -24, off: 7, state: 'close' };
    const own = judged({ ...reading, pure: -25 });
    assert(own.state === 'ringing' && Math.abs(own.off - 1) < 1e-9, `within 6 of the note's own -25: ${JSON.stringify(own)}`);
    assert(judged({ ...reading, pure: -10 }).state === 'off', '14 away is off');
    assert(judged({ ...reading, state: 'waved', pure: -24 }).state === 'waved', 'a wide vibrato stays too wide to judge');
});
