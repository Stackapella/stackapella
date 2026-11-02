// Drones (js/drone.js) and pure tuning (js/ring.js's pureOffset; docs/tune-chord-plan.md): a key
// drone is the tonic and its pure fifth under the singer, straight, the whole song; a chord drone is
// each chord tuned pure around its root, from its beat to the next; pure targets are the ratios'.
import { droneNotes, tonicOf } from '/js/drone.js';
import { chordTones, pureOffset } from '/js/ring.js';
import { lengthBeats } from '/js/templates.js';
import { renderPart } from '/js/synth.js';
import { trackTake } from '/js/pitchtrack.js';
import { assert, test } from './harness.js';

const near = (a, b, within = 0.05) => Math.abs(a - b) <= within;

test('pure tuning: a major third 13.7 cents low, a minor third 15.6 high, a fifth 2 high, a barbershop seventh 31.2 low, a passing note none', () => {
    const C = { root: 0, quality: 'maj' };
    assert(pureOffset(60, C) === 0 && near(pureOffset(64, C), -13.69) && near(pureOffset(67, C), 1.96), 'C major');
    assert(near(pureOffset(63, { root: 0, quality: 'min' }), 15.64), 'the minor third');
    assert(near(pureOffset(70, { root: 0, quality: 'dom7' }), -31.17), 'the barbershop seventh');
    assert(near(pureOffset(71, { root: 0, quality: 'maj7' }), -11.73) && near(pureOffset(74, { root: 0, quality: 'add9' }), 3.91), 'the major seventh and ninth');
    assert(pureOffset(62, C) === null && pureOffset(66, { root: 0, quality: 'dom7' }) === null, 'a passing note has no target');
    assert(near(pureOffset(59 + 12, { root: 7, quality: 'maj' }), -13.69), 'B is the third of G in any octave');
    assert(chordTones('dom7').join() === '0,4,7,10' && chordTones('nonsense').join() === '0,4,7', 'the tones of a chord');
});

test('drone: the key note is the tonic and its pure fifth, at or under the singer, straight, the whole song', async () => {
    const song = await (await fetch('/templates/amazing-grace.json')).json();
    const notes = droneNotes(song, 'key', { around: 55 });
    assert(notes.length === 2 && notes.every((n) => n.beat === 0 && n.length === lengthBeats(song) && n.vibrato.cents === 0), 'two straight notes, the song long');
    assert(notes[0].pitch === 55 && near((notes[1].pitch - 62) * 100, 1.96), `G3 and a pure D4: ${notes.map((n) => n.pitch)}`);
    assert(droneNotes(song, 'key', { around: 80 })[0].pitch <= 60 && droneNotes(song, 'key', { around: 30 })[0].pitch >= 36, 'kept between C2 and C4');
    assert(tonicOf('Bb') === 10 && tonicOf('F#m') === 6 && tonicOf('Ebm') === 3 && tonicOf(undefined) === 0, 'tonics');
    assert(droneNotes(song, 'off').length === 0, 'off is nothing');
});

test('drone: the chord is every chord of the song, tuned pure around its root, from its beat to the next, a slash chord with its bass below', async () => {
    const song = await (await fetch('/templates/amazing-grace.json')).json();
    const notes = droneNotes(song, 'chord', { around: 55 });
    const chords = [...song.chords].sort((a, b) => a.beat - b.beat);
    for (const [i, chord] of chords.entries()) {
        const here = notes.filter((n) => n.beat === chord.beat);
        const to = chords[i + 1]?.beat ?? lengthBeats(song);
        assert(here.length >= 3 && here.every((n) => near(n.length, to - chord.beat, 1e-9)), `chord ${i} at ${chord.beat}: ${here.length} notes`);
        const root = here.find((n) => Number.isInteger(n.pitch) && n.pitch % 12 === chord.root);
        assert(root, `chord ${i}: its root, untouched`);
        for (const n of here) {
            const semis = Math.round(n.pitch) - root.pitch;
            const cents = (n.pitch - Math.round(n.pitch)) * 100;
            const want = pureOffset(Math.round(n.pitch), chord);
            assert(want === null || near(cents, want), `chord ${i}: ${semis} semitones tuned ${cents}, want ${want}`);
        }
        if (chord.bass !== null && chord.bass !== undefined && chord.bass !== chord.root) assert(Math.round(here[0].pitch) % 12 === chord.bass && here[0].pitch < root.pitch, `chord ${i}: the bass below`);
    }
    assert(droneNotes({ ...song, chords: [] }, 'chord').length === 0, 'a song without chords has no chord drone');
});

test('drone: each note sounds at its pure pitch, steady, within a cent, by our own pitch tracker', async () => {
    const song = await (await fetch('/templates/amazing-grace.json')).json();
    const rate = 48000;
    const chordNotes = droneNotes(song, 'chord', { around: 55 }).filter((n) => n.beat < 8);
    for (const note of [...droneNotes(song, 'key', { around: 55 }), ...chordNotes]) {
        const samples = renderPart([{ ...note, beat: 0, length: 2 }], { bpm: 60, sampleRate: rate });
        const track = trackTake(samples, rate);
        const hz = track.hz.slice(50, 150).filter((h) => h > 0).sort((a, b) => a - b);
        const cents = 1200 * Math.log2(hz[hz.length >> 1] / (440 * 2 ** ((note.pitch - 69) / 12)));
        const spread = 1200 * Math.log2(hz.at(-1) / hz[0]);
        assert(Math.abs(cents) < 1 && spread < 2, `${note.pitch}: ${cents.toFixed(2)} cents off, ${spread.toFixed(2)} spread`);
    }
});
