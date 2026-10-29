// How the app shows it's listening for a pitch (Hillel, after the first lesson: always clear
// whether it's listening, no "press then speak", no flickering note names, a bar that zooms in,
// and the note at the end). The maths of the pitch bar, pure and unit-tested: the voice's marker
// smoothed so it glides, the window that starts wide (the whole human range) and zooms in once the
// pitch settles, the ticks to draw, and what a comfortable note says about the parts that may suit.

import { midiName } from './dsp.js';
import { tk } from './i18n.js';

/** The whole human range, roughly: where the bar starts (C2 to C6). */
export const WIDE = { lo: 36, hi: 84 };
/** Zoomed: this far either side of the settled note (an octave in all). */
export const ZOOM_SEMITONES = 6;
/** Held this long, the note has settled: zoom in. */
export const SETTLE_SECONDS = 0.6;
const GLIDE_SECONDS = 0.18; // the marker's smoothing: it glides, never jitters
const ZOOM_SECONDS = 0.35; // the window's easing

/** The marker's next position: `shown` eased toward `target` (MIDI), over `dt` seconds. */
export function glide(shown, target, dt, seconds = GLIDE_SECONDS) {
    if (target === null || target === undefined) return shown;
    if (shown === null || shown === undefined) return target;
    // A jump of more than half an octave (a new note, an octave slip) moves faster, but still glides.
    const time = Math.abs(target - shown) > 6 ? seconds / 3 : seconds;
    return shown + (target - shown) * (1 - Math.exp(-dt / time));
}

/** The window to show: wide until the note has settled, then an octave around it (kept on the bar). */
export function targetWindow(note, held) {
    if (note === null || note === undefined || held < SETTLE_SECONDS) return { ...WIDE };
    const lo = Math.max(WIDE.lo - 12, Math.round(note) - ZOOM_SEMITONES);
    return { lo, hi: lo + ZOOM_SEMITONES * 2 };
}

/** The window eased toward `target` (both `{ lo, hi }`); with reduced motion, there at once. */
export function easeWindow(current, target, dt, { still = false } = {}) {
    if (still) return { ...target };
    const k = 1 - Math.exp(-dt / ZOOM_SECONDS);
    return { lo: current.lo + (target.lo - current.lo) * k, hi: current.hi + (target.hi - current.hi) * k };
}

/** Where `midi` sits across the window, 0 to 1 (clamped). */
export function across(midi, { lo, hi }) {
    return Math.max(0, Math.min(1, (midi - lo) / (hi - lo)));
}

/**
 * The ticks for a window: every semitone when zoomed (the naturals named); wide, only the Cs
 * (named) and a quiet tick halfway between. `[{ midi, at, label, major }]`.
 */
export function ticks({ lo, hi }) {
    const span = hi - lo;
    const out = [];
    for (let midi = Math.ceil(lo); midi <= Math.floor(hi); midi++) {
        const pc = ((midi % 12) + 12) % 12;
        const natural = [0, 2, 4, 5, 7, 9, 11].includes(pc);
        if (span > 24 && pc !== 0 && pc !== 6) continue; // wide: the Cs, and a quiet tick halfway
        const major = pc === 0 || (span <= 24 && natural);
        out.push({ midi, at: across(midi, { lo, hi }), label: major ? midiName(midi) : null, major });
    }
    return out;
}

/**
 * What a comfortable note says about the parts that may suit (a first guess: the range check says
 * for sure). Speaking-comfortable notes: lower voices around A2 to D3, higher around F3 to A3.
 */
export function partsFor(midi) {
    if (midi < 48) return tk('listening.suits.low'); // below C3
    if (midi < 53) return tk('listening.suits.lowMiddle'); // C3 to E3
    if (midi < 58) return tk('listening.suits.highMiddle'); // F3 to A3
    return tk('listening.suits.high');
}
