// The metronome's sounds (docs/tools/competitors-tuner-metronome-range.md, "More": woodblock,
// click, beep, soft wood, cowbell), made here as samples so they're the same on every device and
// cost nothing to download. Each has a plain beat and a higher accent; the subdivision is the beat
// played quieter. Pure, no browser APIs: tested in tests/click-sounds.test.js.

import { click } from '../dsp.js';

export const CLICK_SOUNDS = ['woodblock', 'click', 'beep', 'soft', 'cowbell'];

/** Decaying sines added together: [{ hz, level, decay }], `seconds` long, with a 1 ms fade in. */
function partials(sampleRate, seconds, list) {
    const out = new Float32Array(Math.round(seconds * sampleRate));
    const fadeIn = Math.max(1, Math.round(0.001 * sampleRate));
    for (const p of list) {
        const one = click(sampleRate, { hz: p.hz, seconds, level: p.level, decay: p.decay });
        for (let i = 0; i < out.length; i++) out[i] += one[i] * Math.min(1, i / fadeIn);
    }
    return out;
}

/** A pure tone with 5 ms ramps at both ends: a beep that doesn't click. */
function beep(sampleRate, hz, seconds, level) {
    const out = new Float32Array(Math.round(seconds * sampleRate));
    const ramp = Math.round(0.005 * sampleRate);
    for (let i = 0; i < out.length; i++) {
        const edge = Math.min(1, i / ramp, (out.length - 1 - i) / ramp);
        out[i] = level * edge * Math.sin((2 * Math.PI * hz * i) / sampleRate);
    }
    return out;
}

/** One metronome sound at `sampleRate`: `kind` from CLICK_SOUNDS, the accent a little higher. Peaks below 0.9. */
export function clickSound(kind, sampleRate, { accent = false } = {}) {
    const up = accent ? 1.5 : 1;
    switch (kind) {
        case 'click':
            // The app's own count-in clicks (engine.js): 2500 Hz on the one, 1500 Hz after.
            return click(sampleRate, { hz: accent ? 2500 : 1500, seconds: 0.03, level: 0.7 });
        case 'beep':
            return beep(sampleRate, accent ? 1320 : 880, 0.07, 0.5);
        case 'soft':
            return partials(sampleRate, 0.09, [{ hz: 520 * up, level: 0.55, decay: 45 }, { hz: 1180 * up, level: 0.12, decay: 90 }]);
        case 'cowbell':
            // Two inharmonic partials a fifth-and-a-bit apart, the way a struck bell of metal sounds.
            return partials(sampleRate, 0.3, [{ hz: 560 * up, level: 0.38, decay: 12 }, { hz: 845 * up, level: 0.32, decay: 14 }, { hz: 1680 * up, level: 0.1, decay: 30 }]);
        default:
            // A woodblock: a hollow knock, one strong resonance and a quick higher one.
            return partials(sampleRate, 0.06, [{ hz: 880 * up, level: 0.6, decay: 70 }, { hz: 2420 * up, level: 0.22, decay: 150 }]);
    }
}
