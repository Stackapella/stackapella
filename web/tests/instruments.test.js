// Real instruments (js/instruments.js): the contract's playing rules, and a set played on the clock.
import { chooseZone, instrumentNames, noteGain, renderNotes, soundStart } from '/js/instruments.js';
import { account } from '/js/stores/account.js';
import { assert, test } from './harness.js';

const PIANO = { name: 'p', kind: 'pitched', gain: 0.8, zones: [
    { file: 'a', root: 60, lo: 0, hi: 62, velLo: 1, velHi: 80, frames: 1000, encoderDelay: 1105 },
    { file: 'b', root: 60, lo: 0, hi: 62, velLo: 81, velHi: 127, frames: 1000, encoderDelay: 1105 },
    { file: 'c', root: 64, lo: 63, hi: 127, velLo: 1, velHi: 127, frames: 1000, encoderDelay: 1105 },
] };
const KIT = { name: 'k', kind: 'drums', zones: [
    { file: 's1', piece: 'snare', rr: 1, velLo: 1, velHi: 127 }, { file: 's2', piece: 'snare', rr: 2, velLo: 1, velHi: 127 },
] };

test('instruments: a zone by key and velocity, drums in turn, and none for what a kit lacks', () => {
    assert(chooseZone(PIANO, { note: 60, velocity: 50 }).file === 'a' && chooseZone(PIANO, { note: 60, velocity: 100 }).file === 'b', 'velocity layers');
    assert(chooseZone(PIANO, { note: 70, velocity: 100 }).file === 'c', 'by key');
    const turns = [1, 2, 3].map(() => chooseZone(KIT, { piece: 'snare' }).file);
    assert(turns.join() === 's1,s2,s1', turns.join());
    assert(chooseZone(KIT, { piece: 'cowbell' }) === null, 'a missing piece plays nothing');
});

test('instruments: the priming is skipped only when the decoder kept it, at any rate', () => {
    const zone = PIANO.zones[0];
    assert(soundStart(1000 + 1105, zone, 44100) === 1105 / 44100, 'kept');
    assert(soundStart(1000, zone, 44100) === 0, 'trimmed');
    assert(soundStart(Math.round((1000 + 1105) * 48000 / 44100), zone, 44100, 48000) === 1105 / 44100, 'kept, resampled');
    assert(Math.abs(noteGain(PIANO, PIANO.zones[0], 80) - 0.8) < 1e-9 && noteGain(PIANO, PIANO.zones[0], 40) < 0.8, 'level by velocity');
});

test('instruments: with none set up the answer is null (the synth plays); a set renders notes on the beat', async () => {
    const before = account.config;
    account.config = { ...(before ?? {}), instrumentsIndexUrl: null };
    assert((await instrumentNames()).length === 0, 'none');
    assert((await renderNotes('piano', [{ beat: 0, length: 1, pitch: 60 }], { bpm: 120, sampleRate: 48000 })) === null, 'null without an index');
    account.config = { ...(before ?? {}), instrumentsIndexUrl: '/tests/fixtures/instruments/index.json' };
    try {
        assert((await instrumentNames()).includes('sine'), 'the fixture set listed');
        const out = await renderNotes('sine', [{ beat: 1, length: 0.25, pitch: 69 }], { bpm: 120, sampleRate: 48000 });
        assert(out instanceof Float32Array, 'rendered');
        const peak = (from, to) => out.subarray(Math.round(from * 48000), Math.round(to * 48000)).reduce((m, v) => Math.max(m, Math.abs(v)), 0);
        assert(peak(0, 0.45) < 0.001, `silent before the beat: ${peak(0, 0.45)}`);
        assert(peak(0.5, 0.6) > 0.1, `sounding on beat 1 (0.5 s): ${peak(0.5, 0.6)}`);
        assert(peak(0.75 + 0.15, 1.2) < 0.01, `released after its length: ${peak(0.9, 1.2)}`);
    } finally {
        account.config = before;
    }
});
