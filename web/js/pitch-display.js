// Your sung pitch as it's shown (Hillel: the line shouldn't suddenly drop): one smoother for every
// place the app draws your voice (Sing's line, Find your note's marker, the range check, the
// creator's hold-to-sing), the same numbers in the apps (ux-spec 5.7, after the pitch display
// research's section 6). For display only: the scoring and the lit notes read the raw readings.
// - A short gap (a consonant, a dropped reading, up to BRIDGE) is bridged: the line holds its last
//   pitch at full strength. A longer one ends the line: it fades out over FADE where it was,
//   never drawn toward the floor or toward the next reading.
// - A slip of a fifth or an octave (7, 12, 19 or 24 semitones, within 0.6) is held off until
//   SLIP_READINGS readings in a row agree; any other jump over LEAP semitones waits LEAP_READINGS,
//   then the line snaps there (a real leap shows in about 60 ms, with no easing lag).
// - A median of the last MEDIAN readings takes out single spikes; then a quick ease (30 ms, 12 ms
//   on a leap), so vibrato keeps about two thirds of its swing.
// The octave a person sings in (a man on a treble part) is the caller's to take off first
// (js/livevoice.js learns it once and applies it everywhere). Pure, tested in
// tests/pitch-display.test.js.

export const DISPLAY = {
    bridgeSeconds: 0.15, // Hillel: the dot flashed; it holds through a dropout,
    fadeSeconds: 0.2, // then fades where it was
    median: 3,
    slips: [7, 12, 19, 24],
    slipTolerance: 0.6,
    slipReadings: 8,
    leapSemitones: 2.5,
    leapReadings: 3,
    easeSeconds: 0.03,
    leapEaseSeconds: 0.012,
};

/** A fresh display: nothing shown yet. */
export function pitchDisplay() {
    return { shown: null, alpha: 0, recent: [], since: Infinity, pending: null, pendingCount: 0 };
}

const median = (values) => {
    const sorted = [...values].sort((a, b) => a - b);
    const mid = sorted.length >> 1;
    return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
};

/** A gap: bridged at full strength at first, then fading where it was, then gone. */
function holding(state, dt) {
    const since = state.since + dt;
    if (state.shown === null) return { ...state, since };
    const alpha = since <= DISPLAY.bridgeSeconds ? 1 : Math.max(0, 1 - (since - DISPLAY.bridgeSeconds) / DISPLAY.fadeSeconds);
    return alpha > 0 ? { ...state, since, alpha } : { ...pitchDisplay(), since };
}

/**
 * The next state after a reading: `midi` (null when there's no clear pitch) `dt` seconds after the
 * last. `shown` is the pitch to draw (null: nothing), `alpha` how strongly (1, or fading).
 * (`mode` is accepted for older callers; the ease is the same singing or holding.)
 */
export function stepDisplay(state, { midi, dt }) {
    if (midi === null || midi === undefined || !Number.isFinite(midi)) return holding({ ...state, pending: null, pendingCount: 0 }, dt);
    if (state.shown === null) return { shown: midi, alpha: 1, recent: [midi], since: 0, pending: null, pendingCount: 0 };
    const jump = Math.abs(midi - state.shown);
    if (jump > DISPLAY.leapSemitones) {
        // A slip (a fifth, an octave) needs longer to be believed than a real leap.
        const slip = DISPLAY.slips.some((s) => Math.abs(jump - s) <= DISPLAY.slipTolerance);
        const need = slip ? DISPLAY.slipReadings : DISPLAY.leapReadings;
        const same = state.pending !== null && Math.abs(midi - state.pending) <= DISPLAY.slipTolerance;
        const count = same ? state.pendingCount + 1 : 1;
        if (count < need) return { ...state, since: 0, alpha: 1, pending: same ? state.pending : midi, pendingCount: count };
        return { shown: midi, alpha: 1, recent: [midi], since: 0, pending: null, pendingCount: 0 };
    }
    const recent = [...state.recent.slice(1 - DISPLAY.median), midi];
    const target = median(recent);
    const ease = Math.abs(target - state.shown) > 1.5 ? DISPLAY.leapEaseSeconds : DISPLAY.easeSeconds;
    const shown = state.shown + (target - state.shown) * (1 - Math.exp(-dt / ease));
    return { shown, alpha: 1, recent, since: 0, pending: null, pendingCount: 0 };
}
