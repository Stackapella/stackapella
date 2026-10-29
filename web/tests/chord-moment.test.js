// The chord moment (js/components/chord-moment.js): the chord sits around your note, and the mix is
// your note at a steady level with two guide voices under it, never clipping.
import { chordAround, chordMix } from '/js/components/chord-moment.js';
import { renderPart } from '/js/synth.js';
import { assert, test } from './harness.js';

test('chord moment: a low note is the root, a high one the top, a middle one the third', () => {
    const shape = (note) => chordAround(note).map((p) => (p.yours ? 'you' : Math.round(p.pitch - note))).join(' ');
    assert(shape(50) === 'you 4 7', shape(50));
    assert(shape(72) === '-7 -3 you', shape(72));
    assert(shape(62) === '-4 you 3', shape(62));
});

test('chord moment: the mix keeps your note, adds the chord, and never clips', () => {
    const sampleRate = 16000;
    const quiet = renderPart([{ beat: 0, length: 2, pitch: 57, vibrato: { cents: 0 } }], { bpm: 60, sampleRate }).slice(0, 2 * sampleRate).map((v) => v * 0.05);
    const { samples } = chordMix({ samples: quiet, sampleRate }, 57);
    let peak = 0;
    for (const v of samples) peak = Math.max(peak, Math.abs(v));
    assert(peak <= 0.95 && peak > 0.2, `peak ${peak}`);
    assert(samples.length === quiet.length, 'two seconds, as held');
    assert(samples[0] === 0 && Math.abs(samples[samples.length - 1]) < 0.01, 'faded in and out');
});
