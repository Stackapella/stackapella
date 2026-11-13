import { detectPitch, hzToMidi, midiName, rankParts, suggestTranspose } from '/js/dsp.js';
import { beatToSeconds, renderPart } from '/js/synth.js';
import { trackTake } from '/js/pitchtrack.js';
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

// The human voice (Hillel: "too perfect"): seeded, so the same every time; the guide tier keeps the
// held part of every note within about 5 cents (it teaches pitch), the full tier within a quarter of
// a semitone (never a wrong note), and neither is machine-steady.
const LINE = [55, 57, 59, 60, 62, 60, 59, 57].map((pitch, i) => ({ beat: i * 2, length: 1.8, pitch }));

/** Each note's held part (after its first 0.15 s, before its last 0.12 s), smoothed over a vibrato cycle: the worst, and how much the pitch moves. */
function heldPitch(samples, notes, bpm) {
    const track = trackTake(samples, sr);
    const beat = 60 / bpm;
    let worst = 0;
    let moves = 0;
    for (const n of notes) {
        const cents = [];
        for (let i = Math.ceil((n.beat * beat + 0.15) * track.rate); i < ((n.beat + n.length) * beat - 0.12) * track.rate; i++) {
            if (track.hz[i] <= 0) continue;
            const c = 1200 * Math.log2(track.hz[i] / (440 * 2 ** ((n.pitch - 69) / 12)));
            cents.push(c - 1200 * Math.round(c / 1200)); // an octave misread is the tracker's, not the voice's
        }
        const w = Math.round(0.2 * track.rate);
        for (let i = 0; i + w <= cents.length; i++) worst = Math.max(worst, Math.abs(cents.slice(i, i + w).reduce((a, b) => a + b, 0) / w));
        moves = Math.max(moves, Math.max(...cents) - Math.min(...cents));
    }
    return { worst, moves };
}

test('human voice: the guide tier stays within 5 cents on each held note, and still moves', () => {
    const samples = renderPart(LINE, { bpm: 90, sampleRate: sr, human: 'guide', seed: 3 });
    const { worst, moves } = heldPitch(samples, LINE, 90);
    assert(worst <= 5, `the guide drifts ${worst.toFixed(1)} cents on a held note`);
    assert(moves >= 8, `the guide is machine-steady: it moves ${moves.toFixed(1)} cents`);
});

test('human voice: the full tier varies more, but never by a wrong note (within 30 cents held)', () => {
    const samples = renderPart(LINE, { bpm: 90, sampleRate: sr, human: 'full', seed: 3 });
    const { worst, moves } = heldPitch(samples, LINE, 90);
    assert(worst <= 30, `the demo voice drifts ${worst.toFixed(1)} cents on a held note`);
    assert(moves >= 25, `the demo voice moves only ${moves.toFixed(1)} cents: too perfect`);
});

test('human voice: the same seed sings the same, another seed differently; a course note keeps its own vibrato', () => {
    const a = renderPart(LINE, { bpm: 90, sampleRate: sr, human: 'full', seed: 5 });
    const b = renderPart(LINE, { bpm: 90, sampleRate: sr, human: 'full', seed: 5 });
    const c = renderPart(LINE, { bpm: 90, sampleRate: sr, human: 'full', seed: 6 });
    assert(a.length === b.length && a.every((v, i) => v === b[i]), 'repeatable');
    assert(a.some((v, i) => Math.abs(v - c[i]) > 1e-3), 'a different seed, a different take');
    const straight = [{ beat: 0, length: 4, pitch: 60, vibrato: { cents: 0 } }];
    const { moves } = heldPitch(renderPart(straight, { bpm: 60, sampleRate: sr, human: 'full', seed: 5 }), straight, 60);
    assert(moves <= 6, `a straight tone asked for by a course wobbles ${moves.toFixed(1)} cents`);
});

/** A harmonic's power (dB) in `x` at `hz`: one Goertzel bin. */
function harmonicDb(x, hz, sampleRate) {
    const c = 2 * Math.cos((2 * Math.PI * hz) / sampleRate);
    let s1 = 0;
    let s2 = 0;
    for (const v of x) {
        const s = v + c * s1 - s2;
        s2 = s1;
        s1 = s;
    }
    return 10 * Math.log10(s1 * s1 + s2 * s2 - c * s1 * s2 + 1e-20);
}

test('synth: the note itself leads its harmonics at every pitch, so no part is heard an octave up', () => {
    // frontend2's measure (2026-11-13): one 2 s note at 60 bpm, its held middle. From C#3 to F3 the 2nd harmonic was
    // 6 to 16 dB over the fundamental; basses and tenors heard (and the tracker read) their note an octave up.
    const rate = 48000;
    for (const human of [null, 'guide']) {
        for (let midi = 36; midi <= 84; midi += 1) {
            const audio = renderPart([{ beat: 0, length: 2, pitch: midi }], { bpm: 60, sampleRate: rate, human, seed: 1 });
            const mid = audio.subarray(Math.round(0.6 * rate), Math.round(1.6 * rate));
            const hz = 440 * 2 ** ((midi - 69) / 12);
            const h = [1, 2, 3, 4, 5].filter((k) => k * hz < rate / 2).map((k) => harmonicDb(mid, k * hz, rate));
            assert(h[0] >= Math.max(...h.slice(1)), `${human ?? 'plain'} ${midiName(midi)}: the fundamental ${(h[0] - Math.max(...h.slice(1))).toFixed(1)} dB under a harmonic`);
        }
    }
});

test('synth: a low guide note reads at its own octave, D3 included', () => {
    const rate = 48000;
    for (const midi of [49, 50, 51, 52, 53]) {
        const audio = renderPart([{ beat: 0, length: 2, pitch: midi }], { bpm: 60, sampleRate: rate, human: 'guide', seed: 2 });
        const found = detectPitch(audio.subarray(Math.round(0.8 * rate), Math.round(0.8 * rate) + 4096), rate);
        assert(found && Math.abs(cents(found.hz, midi)) < 60, `${midiName(midi)} read as ${found ? midiName(Math.round(69 + 12 * Math.log2(found.hz / 440))) : 'nothing'}`);
    }
});

test('synth: the same pitch sung again is a new syllable, not one long note', () => {
    // frontend2's stress pass: joined repeats dipped only 1 to 3 dB. Each join now dips enough to be heard as a new start.
    const rate = 48000;
    const bpm = 90;
    const audio = renderPart(Array.from({ length: 8 }, (_, i) => ({ beat: i, length: 1, pitch: 61 })), { bpm, sampleRate: rate, human: 'guide', seed: 3 });
    const win = Math.round(0.005 * rate);
    const env = (t) => {
        const a = Math.round(t * rate);
        let s = 0;
        for (let i = a; i < a + win; i++) s += audio[i] ** 2;
        return Math.sqrt(s / win);
    };
    for (let j = 1; j < 8; j++) {
        const join = (j * 60) / bpm;
        let low = Infinity;
        for (let t = join - 0.12; t < join + 0.08; t += 0.0025) low = Math.min(low, env(t));
        const dip = 20 * Math.log10(env(join - 0.2) / low);
        assert(dip >= 4, `join ${j}: a ${dip.toFixed(1)} dB dip`);
    }
});
