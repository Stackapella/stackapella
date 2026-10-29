// The shown pitch (js/pitch-display.js, after the pitch display research): short gaps bridged,
// long ones faded where they were (never dropped), fifth and octave slips held off, real leaps
// snapped quickly, single spikes taken out, and vibrato kept.
import { DISPLAY, pitchDisplay, stepDisplay } from '/js/pitch-display.js';
import { assert, test } from './harness.js';

const DT = 1 / 50;
const run = (readings, { dt = DT } = {}) => readings.reduce((states, midi) => [...states, stepDisplay(states.at(-1) ?? pitchDisplay(), { midi, dt })], []);

test('pitch display: a short gap is bridged; a long one fades where it was, never drops', () => {
    const short = run([60, 60, 60, null, null, null, 60]);
    assert(short.every((s) => s.alpha === 1 && Math.abs(s.shown - 60) < 1e-9), 'a 60 ms gap: one line, full strength');
    const long = run([60, 60, 60, ...Array(30).fill(null)]);
    const at = (seconds) => long[2 + Math.round(seconds / DT)];
    assert(at(0.1).alpha === 1 && at(0.1).shown === 60, 'held at first');
    const fading = at(DISPLAY.bridgeSeconds + DISPLAY.fadeSeconds / 2);
    assert(fading.alpha > 0 && fading.alpha < 1 && fading.shown === 60, `fading where it was: ${JSON.stringify(fading)}`);
    assert(long.at(-1).shown === null, 'then gone, not dropped');
});

test('pitch display: fifth and octave slips are held off; a real leap snaps in three readings', () => {
    for (const slip of [-12, 7, -19]) {
        const states = run([60, 60, 60, ...Array(DISPLAY.slipReadings - 1).fill(60 + slip), 60, 60]);
        assert(states.every((s) => Math.abs(s.shown - 60) < 0.3), `a ${slip} slip of ${DISPLAY.slipReadings - 1} readings shows: ${states.map((s) => s.shown.toFixed(1))}`);
    }
    const leap = run([60, 60, 64, 64, 64, 64]);
    assert(leap[3].shown < 61 && leap[4].shown === 64, `a third, snapped after 3: ${leap.map((s) => s.shown.toFixed(1))}`);
    const octave = run([60, 60, ...Array(DISPLAY.slipReadings).fill(72)]);
    assert(octave.at(-1).shown === 72, 'an octave that lasts is believed');
});

test('pitch display: a single spike goes; vibrato (4 to 7 Hz) keeps most of its swing', () => {
    const spike = run([60, 60, 60, 61.5, 60, 60]);
    assert(spike.every((s) => s.shown < 60.3), `the spike is filtered: ${spike.map((s) => s.shown.toFixed(2))}`);
    for (const hz of [4, 5.5, 7]) {
        const readings = Array.from({ length: 150 }, (_, i) => 60 + 0.5 * Math.sin(2 * Math.PI * hz * i * DT));
        const shown = run(readings).slice(50).map((s) => s.shown);
        const swing = (Math.max(...shown) - Math.min(...shown)) / 2;
        assert(swing > 0.25 && swing <= 0.5, `${hz} Hz: shown at ±${swing.toFixed(2)} of ±0.5`);
    }
});
