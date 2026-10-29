// The pitch tracker (js/pitchtrack.js) on synthesized singing.
import { hzToCents, medianFilter, repairOctaves, trackFrame, trackInWorker, trackTake } from '/js/pitchtrack.js';
import { assert, close, test } from './harness.js';
import { sing } from './fixtures.js';

const median = (values) => [...values].sort((a, b) => a - b)[values.length >> 1];

/** The median pitch error (cents) in the middle half of each note. */
function errors(track, notes, bpm) {
    const beat = 60 / bpm;
    return notes.map((note) => {
        const from = Math.ceil((note.beat + note.length * 0.25) * beat * track.rate);
        const to = Math.floor((note.beat + note.length * 0.75) * beat * track.rate);
        const cents = [];
        for (let i = from; i <= to; i++) if (track.hz[i]) cents.push(hzToCents(track.hz[i], note.pitch));
        return cents.length ? median(cents) : NaN;
    });
}

test('pitchtrack: every sung pitch from a low E2 to a high C6 is found within 3 cents, at 48 and 44.1 kHz', () => {
    const notes = [40, 45, 50, 55, 60, 65, 70, 75, 80, 84].map((pitch, i) => ({ beat: i, length: 1, pitch }));
    for (const rate of [48000, 44100]) {
        const track = trackTake(sing(rate, { notes, bpm: 100 }), rate);
        assert(track.rate === 100 && Math.abs(track.hz.length - (notes.length * 0.6 + 0.5) * 100) <= 2, `${track.hz.length} frames`);
        errors(track, notes, 100).forEach((cents, i) => close(cents, 0, 3, `${rate} Hz, note ${notes[i].pitch}`));
    }
});

test('pitchtrack: silence and noise are unvoiced, and loudness is in dBFS', () => {
    const rate = 48000;
    const silent = trackTake(new Float32Array(rate), rate);
    assert(silent.hz.every((hz) => hz === 0), 'silence has no pitch');
    const noise = trackTake(sing(rate, { notes: [], bpm: 100, noise: 0.2 }), rate);
    const voiced = noise.hz.filter((hz) => hz > 0).length / noise.hz.length;
    assert(voiced < 0.05, `${Math.round(voiced * 100)}% of noise read as voiced`);
    const tone = trackTake(Float32Array.from({ length: rate }, (_, i) => 0.5 * Math.sin((2 * Math.PI * 220 * i) / rate)), rate);
    close(tone.loudness[50], 20 * Math.log10(0.5 / Math.SQRT2), 0.3, 'a 0.5 sine is about -9 dBFS');
});

test('pitchtrack: a slip of an octave is put back, and a median smooths single bad frames', () => {
    const hz = Float32Array.from([220, 220, 221, 220, 440, 220, 219, 220, 220, 0, 0]);
    repairOctaves(hz);
    close(hz[4], 220, 1e-3, 'the octave slip');
    const smoothed = medianFilter(Float32Array.from([220, 220, 250, 220, 220, 0, 0]), 3);
    assert(smoothed[2] === 220 && smoothed[5] === 0, Array.from(smoothed).join());
});

test('pitchtrack: a live reading from the mic (2048 samples) finds the note', () => {
    const rate = 48000;
    const voice = sing(rate, { notes: [{ beat: 0, length: 2, pitch: 64 }], bpm: 60 });
    const reading = trackFrame(voice.subarray(rate - 2048, rate), rate);
    close(hzToCents(reading.hz, 64), 0, 5, 'E4 live');
    assert(trackFrame(new Float32Array(2048), rate).hz === 0, 'silence live');
});

test('pitchtrack: a minute of singing is tracked in a worker in a few seconds', async () => {
    const rate = 48000;
    const notes = Array.from({ length: 60 }, (_, i) => ({ beat: i, length: 0.9, pitch: 55 + (i % 12) }));
    const voice = sing(rate, { notes, bpm: 60 });
    const started = performance.now();
    const track = await trackInWorker(voice, rate);
    const seconds = (performance.now() - started) / 1000;
    assert(seconds < 6, `took ${seconds.toFixed(1)} s`);
    assert(track.hz.length >= 5900 && voice.length > 0 && voice.buffer.byteLength > 0, 'the samples were copied, not taken');
    errors(track, notes.slice(0, 12), 60).forEach((cents, i) => close(cents, 0, 3, `note ${i}`));
});

test('pitchtrack: live at 96 kHz still hears a bass note (the mic window scales with the rate)', () => {
    const rate = 96000;
    const voice = sing(rate, { notes: [{ beat: 0, length: 2, pitch: 40 }], bpm: 60 });
    const reading = trackFrame(voice.subarray(rate - 4096, rate), rate);
    close(hzToCents(reading.hz, 40), 0, 5, 'E2 live at 96 kHz');
});
