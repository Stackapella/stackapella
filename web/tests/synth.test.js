import { detectPitch, hzToMidi, midiName, rankParts, suggestTranspose } from '/js/dsp.js';
import { beatToSeconds, renderPart } from '/js/synth.js';
import { assert, close, test } from './harness.js';

const sr = 48000;

function tone(hz, seconds = 0.1, harmonics = [1]) {
    return Float32Array.from({ length: Math.round(seconds * sr) }, (_, i) =>
        harmonics.reduce((sum, weight, k) => sum + weight * Math.sin((2 * Math.PI * hz * (k + 1) * i) / sr), 0));
}

function cents(hz, midi) {
    return (hzToMidi(hz) - midi) * 100;
}

/** The pitch heard `seconds` into `samples`. */
function pitchAt(samples, seconds) {
    const at = Math.round(seconds * sr);
    return detectPitch(samples.subarray(at, at + 2048), sr);
}

test('pitch: a pure 220 Hz tone reads as A3, within a cent', () => {
    const found = detectPitch(tone(220), sr);
    close(cents(found.hz, 57), 0, 1, 'cents off A3');
    assert(found.clarity > 0.9, `clarity ${found.clarity}`);
});

test('pitch: a voice-like tone with strong harmonics reads at its fundamental', () => {
    const found = detectPitch(tone(146.83, 0.1, [0.6, 1, 0.8, 0.4, 0.3]), sr);
    close(cents(found.hz, 50), 0, 5, 'cents off D3');
});

test('pitch: noise has no pitch', () => {
    let seed = 7;
    const noise = Float32Array.from({ length: 4096 }, () => ((seed = (seed * 16807) % 2147483647) / 2147483647) * 2 - 1);
    const found = detectPitch(noise, sr);
    assert(found === null || found.clarity < 0.5, `noise read as ${found?.hz} Hz`);
});

test('pitch: note names', () => {
    assert(midiName(60) === 'C4' && midiName(69) === 'A4' && midiName(70) === 'Bb4' && midiName(43) === 'G2', 'names');
});

test('synth: every note sounds at its pitch, on its beat', () => {
    const notes = [{ beat: 0, length: 2, pitch: 60 }, { beat: 2, length: 1, pitch: 67 }, { beat: 3, length: 1, pitch: 55 }];
    const audio = renderPart(notes, { bpm: 120, sampleRate: sr });
    for (const note of notes) {
        const found = pitchAt(audio, beatToSeconds(note.beat, 120) + 0.1);
        close(cents(found.hz, note.pitch), 0, 5, `cents off note ${note.pitch}`);
    }
});

test('synth: silence before the first note, sound from its beat', () => {
    const audio = renderPart([{ beat: 1, length: 1, pitch: 64 }], { bpm: 120, sampleRate: sr });
    const start = Math.round(0.5 * sr);
    assert(audio.subarray(0, start).every((v) => v === 0), 'sound before the note');
    assert(Math.abs(audio[start + 200]) > 0, 'no sound at the note');
});

test('synth: transposing moves every note by the same number of semitones', () => {
    const notes = [{ beat: 0, length: 1, pitch: 62 }, { beat: 1, length: 1, pitch: 66 }];
    const audio = renderPart(notes, { bpm: 120, sampleRate: sr, transpose: -3 });
    close(cents(pitchAt(audio, 0.1).hz, 59), 0, 5, 'first note');
    close(cents(pitchAt(audio, 0.6).hz, 63), 0, 5, 'second note');
});

test('synth: slow practice keeps the pitch and stretches the beats', () => {
    const notes = [{ beat: 0, length: 1, pitch: 60 }, { beat: 1, length: 1, pitch: 64 }];
    const audio = renderPart(notes, { bpm: 120, sampleRate: sr, speed: 0.5 });
    // Vibrato (about 9 cents) has started by 0.6 s; the next note would be 400 cents away.
    close(cents(pitchAt(audio, 0.6).hz, 60), 0, 12, 'still the first note at 0.6 s');
    close(cents(pitchAt(audio, 1.1).hz, 64), 0, 5, 'second note at 1 s');
});

test('synth: guides come out at a take-like loudness, the same every time', () => {
    const notes = [{ beat: 0, length: 4, pitch: 57 }];
    const a = renderPart(notes, { bpm: 90, sampleRate: sr });
    const b = renderPart(notes, { bpm: 90, sampleRate: sr });
    let sum = 0;
    let voiced = 0;
    a.forEach((v) => { if (Math.abs(v) > 1e-4) { sum += v * v; voiced++; } });
    close(Math.sqrt(sum / voiced), 0.1, 0.005, 'RMS');
    assert(a.every((v, i) => v === b[i]), 'two renders differ');
});

test('range: the smallest shift that fits, ties toward no shift', () => {
    const singer = { low: 48, high: 64 };
    assert(suggestTranspose(singer, { low: 50, high: 62 }).transpose === 0, 'fits as is');
    const up = suggestTranspose(singer, { low: 45, high: 57 });
    assert(up.transpose === 3 && up.fits, `got ${up.transpose}`);
    const down = suggestTranspose(singer, { low: 55, high: 67 });
    assert(down.transpose === -3 && down.fits, `got ${down.transpose}`);
    assert(!suggestTranspose(singer, { low: 40, high: 70 }).fits, 'too wide fits');
});

test('range: parts are ranked by how well they fit the singer', () => {
    const ranked = rankParts({ low: 43, high: 60 }, [
        { key: 'melody', range: { low: 62, high: 74 } },
        { key: 'bass', range: { low: 43, high: 55 } },
        { key: 'low', range: { low: 50, high: 62 } },
    ]);
    assert(ranked.map((r) => r.part.key).join() === 'bass,low,melody', ranked.map((r) => r.part.key).join());
});

test('synth: the chords underneath sound their notes, the bass below, softer than a guide', async () => {
    const { renderBacking, voicing } = await import('/js/synth.js');
    assert(voicing({ root: 7, quality: 'maj', bass: null }).join() === '43,50,55,59', `G major: ${voicing({ root: 7, quality: 'maj', bass: null })}`);
    assert(voicing({ root: 7, quality: 'maj', bass: 11 })[0] === 47, 'G/B has B in the bass');
    assert(voicing({ root: 7, quality: 'maj', bass: null }, 2).join() === '45,49,52,57', 'transposed up a tone');
    const sampleRate = 8000;
    const audio = renderBacking([{ beat: 0, root: 0, quality: 'maj', bass: null }, { beat: 4, root: 7, quality: 'dom7', bass: null }], { bpm: 120, sampleRate, endBeat: 8 });
    assert(audio.length >= 4 * sampleRate, `${audio.length} frames for 8 beats at 120`);
    let sum = 0;
    let n = 0;
    for (const v of audio) if (Math.abs(v) > 1e-4) { sum += v * v; n++; }
    const rms = Math.sqrt(sum / n);
    assert(rms > 0.03 && rms < 0.08, `backing level ${rms.toFixed(3)}`);
});

test('synth: a note\'s own vibrato is sung as asked (rate, width, straight, a late bloom), the same every time', async () => {
    const { trackTake } = await import('/js/pitchtrack.js');
    const { centsOf, measureHold } = await import('/js/vibrato.js');
    const measure = (vibrato, beats = 6) => {
        const audio = renderPart([{ beat: 0, length: beats, pitch: 57, vibrato }], { bpm: 60, sampleRate: sr });
        const track = trackTake(audio, sr);
        return measureHold(centsOf(track, { from: 0, to: beats, midi: 57 }), track.rate);
    };
    const wave = measure({ hz: 5.5, cents: 40, after: 0 });
    assert(wave.kind === 'vibrato', `a vibrato: ${wave.kind}`);
    close(wave.hz, 5.5, 0.3, 'its rate');
    close(wave.width, 40, 8, 'its width');
    assert(measure({ cents: 0 }).kind === 'straight', 'cents 0 is straight');
    const bloom = measure({ hz: 5.5, cents: 35, after: 2 }, 7);
    close(bloom.onset, 2.1, 0.4, 'the wave starts late');
    const wobble = measure({ hz: 3, cents: 70, after: 0 });
    assert(wobble.kind === 'wobble', `slow and wide is a wobble: ${wobble.kind}`);
    const a = renderPart([{ beat: 0, length: 3, pitch: 57, vibrato: { hz: 5.5, cents: 40, jitter: 1 } }], { bpm: 60, sampleRate: sr });
    const b = renderPart([{ beat: 0, length: 3, pitch: 57, vibrato: { hz: 5.5, cents: 40, jitter: 1 } }], { bpm: 60, sampleRate: sr });
    assert(a.every((v, i) => v === b[i]), 'jitter is the same every render');
    const plain = renderPart([{ beat: 0, length: 3, pitch: 57 }], { bpm: 60, sampleRate: sr });
    const none = renderPart([{ beat: 0, length: 3, pitch: 57, vibrato: null }], { bpm: 60, sampleRate: sr });
    assert(plain.every((v, i) => v === none[i]), 'no vibrato of its own: the guide as it always was');
});

test('synth: a note\'s bend glides as asked and jumps where two points share a time; a head-voice note is purer', async () => {
    const { trackTake, hzToCents } = await import('/js/pitchtrack.js');
    const { bendAt } = await import('/js/synth.js');
    const audio = renderPart([{ beat: 0, length: 3, pitch: 57, vibrato: { cents: 0 }, bend: [[0, 0], [1, 0], [1.5, 300], [2.2, 300], [2.2, -200], [3, -200]] }], { bpm: 60, sampleRate: sr });
    const track = trackTake(audio, sr);
    const at = (s) => hzToCents(track.hz[Math.round(s * track.rate)], 57);
    close(at(0.6), 0, 15, 'on the note before the glide');
    close(at(1.9), 300, 20, 'a minor third up after it');
    close(at(2.6), -200, 20, 'a whole tone down after the jump');
    assert(bendAt([[0, 0], [1, 9000]], 2) === 2400 && bendAt([[0, 0], [1, -9000]], 2) === -2400, 'kept within two octaves');
    // Purer: less of the sound in its fast wiggles (the differences between samples) than the "oo".
    const edge = (x) => Math.sqrt(x.slice(1).reduce((s, v, i) => s + (v - x[i]) ** 2, 0) / x.length) / Math.sqrt(x.reduce((s, v) => s + v * v, 0) / x.length);
    const oo = renderPart([{ beat: 0, length: 1, pitch: 69, vibrato: { cents: 0 } }], { bpm: 60, sampleRate: sr });
    const head = renderPart([{ beat: 0, length: 1, pitch: 69, vibrato: { cents: 0 }, voice: 'head' }], { bpm: 60, sampleRate: sr });
    assert(edge(head) < edge(oo), `head voice purer: ${edge(head).toFixed(3)} vs ${edge(oo).toFixed(3)}`);
    close(hzToCents(trackTake(head, sr).hz[50], 69), 0, 10, 'and on its pitch');
});
