// The note capture's scale (components/voice-capture.js in the app, js/public/voice-capture.js on the site): pure
// and dependency-free (dsp.js only), so a public page can load it with no import map. Its span narrows only as a
// note is really held (Hillel's Comfortable Note v2: "We'll zoom in as you settle"), and its ticks sit round a
// fixed needle. Tested in tests/voice-capture.test.js.

import { midiName } from './dsp.js';

/** Held this long, the voice has settled: the scale starts to close in. */
export const SETTLE_SECONDS = 0.6;

// The scale's span in semitones (as v2: wide around the voice, closing in as it settles): this wide while the
// voice hasn't settled, narrowing to LOCKED_SPAN as the note is held. From the real hold, never a show.
export const WIDE_SPAN = 18;
export const LOCKED_SPAN = 7;
const ZOOM_SECONDS = 0.35; // the span's easing

/** The span to show for a note held `held` seconds of `holdSeconds`. */
export function targetSpan(held, holdSeconds) {
    if (held < SETTLE_SECONDS) return WIDE_SPAN;
    const k = Math.min(1, (held - SETTLE_SECONDS) / Math.max(0.1, holdSeconds - SETTLE_SECONDS));
    return WIDE_SPAN - (WIDE_SPAN - LOCKED_SPAN) * k;
}

/** The span eased toward `target` over `dt` seconds; with reduced motion, there at once. */
export function easeSpan(span, target, dt, { still = false } = {}) {
    return still ? target : span + (target - span) * (1 - Math.exp(-dt / ZOOM_SECONDS));
}

/** How zoomed the span is: 0 wide, 1 closer, 2 locked. */
export function zoomOf(span) {
    return span > 14 ? 0 : span > 9 ? 1 : 2;
}

/**
 * The scale's ticks around the needle: every semitone in the window, placed by how far it is from `centre`
 * across the window's span (0.5 is the needle). Naturals are taller; the note under the needle is marked;
 * names show once the window is close (or for the Cs while wide).
 */
export function scaleTicks(centre, span) {
    const out = [];
    const near = Math.round(centre);
    for (let midi = Math.floor(centre - span / 2) - 1; midi <= Math.ceil(centre + span / 2) + 1; midi++) {
        const at = 0.5 + (midi - centre) / span;
        if (at < -0.02 || at > 1.02) continue;
        const pc = ((midi % 12) + 12) % 12;
        const natural = [0, 2, 4, 5, 7, 9, 11].includes(pc);
        const active = midi === near;
        // Named: the note under the needle, and the naturals once close (wide, the Cs and Fs); none crowding it.
        const named = active || ((span <= 10 ? natural : pc === 0 || pc === 5) && Math.abs(midi - centre) * (340 / span) > 34);
        out.push({ midi, at, natural, active, label: named ? midiName(midi) : null });
    }
    return out;
}
