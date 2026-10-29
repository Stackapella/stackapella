// The staff (js/sing.js staffLayout): notes on the right lines, with the key's accidentals.
import { clefFor, keySignature, spell, staffLayout, staffPitch } from '/js/sing.js';
import { assert, test } from './harness.js';

const one = (pitch, options) => staffLayout([{ beat: 0, length: 1, pitch }], options).notes[0];

test('staff: E4 sits on the treble clef bottom line, and B3 needs a ledger line', () => {
    assert(one(64).position === 0 && one(64).ledgers.length === 0, JSON.stringify(one(64)));
    const b3 = one(59);
    assert(b3.position === -3 && b3.ledgers.join() === '-2', JSON.stringify(b3));
    assert(one(60).position === -2 && one(60).ledgers.join() === '-2', 'middle C on its ledger line');
    assert(one(81).position === 10 && one(81).ledgers.join() === '10', 'A5 above the top line');
});

test('staff: F# in G major needs no sign, F natural does, and Bb in C has a flat', () => {
    assert(one(66, { key: 'G' }).accidental === null && one(66, { key: 'G' }).name === 'F#4', 'F# in G');
    assert(one(65, { key: 'G' }).accidental === 'natural', 'F natural in G');
    assert(one(70, { key: 'C' }).accidental === 'flat' && one(70, { key: 'C' }).name === 'Bb4', 'Bb in C');
    assert(one(66, { key: 'C' }).name === 'F#4' && one(63, { key: 'C' }).name === 'Eb4', 'C spells F# and Eb');
    assert(one(70, { key: 'F' }).accidental === null && one(70, { key: 'F' }).name === 'Bb4', 'Bb in F');
    assert(keySignature('Em').type === 'sharp' && keySignature('Gm').count === 2, 'minor keys');
});

test('staff: an accidental lasts to the end of its bar', () => {
    const notes = [{ beat: 0, length: 1, pitch: 65 }, { beat: 1, length: 1, pitch: 65 }, { beat: 4, length: 1, pitch: 65 }];
    const laid = staffLayout(notes, { key: 'G', beatsPerBar: 4 }).notes;
    assert(laid.map((n) => n.accidental).join() === 'natural,,natural', laid.map((n) => n.accidental).join());
});

test('staff: G2 sits on the bass clef bottom line, and the tenor reads an octave up', () => {
    assert(clefFor('bass') === 'bass' && clefFor('tenor') === 'treble8' && clefFor('alto') === 'treble', 'clefs');
    assert(one(43, { clef: 'bass' }).position === 0, 'G2');
    assert(one(52, { clef: 'treble8' }).position === 0, 'the tenor E3 is written on the bottom line');
});

test('staff: long notes are hollow, stems turn at the middle line, bar lines follow the pickup', () => {
    const layout = staffLayout([{ beat: 0, length: 1, pitch: 64 }, { beat: 1, length: 2, pitch: 77 }, { beat: 4, length: 1, pitch: 71 }], { beatsPerBar: 3, pickupBeats: 1 });
    assert(!layout.notes[0].hollow && layout.notes[1].hollow, 'heads');
    assert(layout.notes[0].stem === 'up' && layout.notes[1].stem === 'down' && layout.notes[2].stem === 'down', 'stems');
    assert(layout.bars.join() === '1,4', layout.bars.join());
    assert(spell(61, 'D').alter === 1 && spell(61, 'Eb').alter === -1, 'C# in D, Db in Eb');
});

test('staff: every key spells its own scale, and a minor key its raised 6th and 7th', () => {
    assert(one(77, { key: 'F#' }).name === 'E#5' && one(77, { key: 'F#' }).accidental === null, `E# in F# major: ${one(77, { key: 'F#' }).name}`);
    assert(one(72, { key: 'C#' }).name === 'B#4' && one(72, { key: 'C#' }).accidental === null, `B# in C# major: ${one(72, { key: 'C#' }).name}`);
    assert(one(71, { key: 'Gb' }).name === 'Cb5' && one(71, { key: 'Gb' }).accidental === null, `Cb in Gb major: ${one(71, { key: 'Gb' }).name}`);
    assert(one(61, { key: 'Dm' }).name === 'C#4' && one(61, { key: 'Dm' }).accidental === 'sharp', `C# in D minor: ${one(61, { key: 'Dm' }).name}`);
    assert(one(66, { key: 'Gm' }).name === 'F#4', `F# in G minor: ${one(66, { key: 'Gm' }).name}`);
    assert(one(70, { key: 'D' }).name === 'Bb4', `the lowered 6th in D major: ${one(70, { key: 'D' }).name}`);
    assert(one(68, { key: 'D' }).name === 'G#4', `the raised 4th in D major: ${one(68, { key: 'D' }).name}`);
    assert(one(68, { key: 'C' }).name === 'Ab4' && one(61, { key: 'C' }).name === 'C#4', 'C major: Ab and C#');
});

test('staff: dotted notes have their dot, and short ones their flags', () => {
    const layout = staffLayout([{ beat: 0, length: 3, pitch: 67 }, { beat: 3, length: 0.5, pitch: 67 }, { beat: 3.5, length: 0.25, pitch: 67 }]);
    assert(layout.notes[0].dotted && layout.notes[0].hollow && layout.notes[1].flags === 1 && layout.notes[2].flags === 2, JSON.stringify(layout.notes.map((n) => [n.dotted, n.flags])));
});

test('staff: a position on the staff is the pitch staffLayout puts there, in the key (the note editor draws with it)', () => {
    const scale = { C: [0, 2, 4, 5, 7, 9, 11], G: [0, 2, 4, 6, 7, 9, 11], F: [0, 2, 4, 5, 7, 9, 10], Bb: [0, 2, 3, 5, 7, 9, 10], E: [1, 3, 4, 6, 8, 9, 11] };
    for (const [key, pcs] of Object.entries(scale)) {
        for (const clef of ['treble', 'treble8', 'bass']) {
            for (let pitch = 36; pitch <= 84; pitch++) {
                if (!pcs.includes(pitch % 12)) continue;
                const position = one(pitch, { key, clef }).position;
                assert(staffPitch(position, { key, clef }) === pitch, `${key} ${clef}: ${pitch} sits at ${position}, which reads back as ${staffPitch(position, { key, clef })}`);
            }
        }
    }
});
