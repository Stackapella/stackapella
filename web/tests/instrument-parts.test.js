// Instrument parts (js/instrument-parts.js): under a harmonised tune each instrument plays the
// chords as a guide the template format takes (one note at a time, whole pitches, in its range);
// the drums are General MIDI notes for the contract's pieces; a song's parts include its
// instruments whoever sings, and without samples the drums play the synthesized kit.
import { harmonize } from '/js/arrange.js';
import { finishParts } from '/js/creator.js';
import { INSTRUMENTS, instrumentPart, PIECES, synthGroove, withInstruments } from '/js/instrument-parts.js';
import { partsFor, validateTemplate } from '/js/templates.js';
import { parseKey } from '/js/notes.js';
import { renderGuide } from '/js/guides.js';
import { assert, test } from './harness.js';

async function harmonised() {
    const song = await (await fetch('/templates/amazing-grace.json')).json();
    const melody = song.parts.find((p) => p.melody);
    const { tonic, minor } = parseKey(song.key);
    return finishParts(harmonize({ ...song, parts: [melody] }, { key: { tonic, mode: minor ? 'minor' : 'major' } }).template);
}

test('instrument parts: each plays the chords as a guide the template format takes', async () => {
    const song = await harmonised();
    for (const key of Object.keys(INSTRUMENTS)) {
        const part = instrumentPart(song, key);
        assert(part && part.kind === 'instrument' && part.guide.notes.length > 8, `${key} plays`);
        let end = 0;
        for (const n of part.guide.notes) {
            assert(Number.isInteger(n.pitch) && n.length > 0 && n.beat >= end - 1e-9, `${key}: one whole note at a time, at ${n.beat}`);
            end = n.beat + n.length;
        }
    }
    for (const pair of [['piano', 'drums'], ['guitar', 'bass-guitar']]) {
        validateTemplate(withInstruments(song, pair)); // throws on a problem
    }
});

test('instrument parts: the bass plays each chord\'s root, the drums only known pieces', async () => {
    const song = await harmonised();
    const bass = instrumentPart(song, 'bass-guitar');
    for (const chord of song.chords) {
        const n = bass.guide.notes.find((x) => Math.abs(x.beat - chord.beat) < 1e-6);
        assert(n && ((n.pitch % 12) + 12) % 12 === chord.root, `the root at beat ${chord.beat}`);
    }
    const drums = instrumentPart(song, 'drums');
    assert(drums.guide.notes.every((n) => PIECES[n.pitch]), 'every drum note is a piece');
    assert(drums.guide.notes.some((n) => PIECES[n.pitch] === 'kick') && drums.guide.notes.some((n) => PIECES[n.pitch] === 'snare'), 'a kick and a snare');
});

test('instrument parts: a song plays its instruments whoever sings, and no chords means none', async () => {
    const song = withInstruments(await harmonised(), ['piano']);
    assert(partsFor(song, 2).some((p) => p.key === 'piano') && partsFor(song, 2).filter((p) => p.kind !== 'instrument').length === 2, 'two singers and the piano');
    assert(instrumentPart({ ...song, chords: undefined }, 'piano') === null, 'no chords, no part');
});

test('instrument parts: with no samples the drums play the synthesized kit, on the beat', async () => {
    const notes = [{ beat: 0, length: 0.5, pitch: 36 }, { beat: 1, length: 0.5, pitch: 38 }];
    const out = synthGroove(notes, { bpm: 120, sampleRate: 8000 });
    const peak = (from, to) => Math.max(...out.slice(from, to).map(Math.abs));
    assert(peak(0, 400) > 0.1 && peak(4000, 4400) > 0.05, 'a kick at 0 s and a snare at 0.5 s');
    assert(peak(2400, 3800) < 1e-3, 'quiet between them');
    const song = withInstruments(await harmonised(), ['drums']);
    const guide = await renderGuide({ templateId: 'drums-test', templateVersion: 1, part: song.parts.find((p) => p.key === 'drums'), tempo: 90, sampleRate: 8000 });
    assert(guide instanceof Float32Array && guide.some((v) => Math.abs(v) > 0.05), 'the drum guide renders, on the synth');
});
