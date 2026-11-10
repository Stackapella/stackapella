// What the free tuner shows from the mic's readings (docs/tools/competitors-tuner-metronome-range.md,
// "UX spec (tuner)"), kept apart from the card so it can be tested without a mic:
// - An automatic noise gate: the room's own level is learnt from the unvoiced readings (it only rises
//   while nothing is played, so a long held note never gates itself), and a reading counts only when
//   it's clearly above it and above the sensitivity's floor. A pitch heard under the gate is "too quiet".
// - Octave jumps are held off: pitch-display.js only believes a slip of an octave or a fifth once
//   several readings in a row agree, the guard every pitch line in the app uses.
// - Voice is smoothed slowly enough to average a vibrato (a 5 to 6 Hz wave of ±50 cents shows as
//   about ±6); Instrument follows quickly. A pitch trail of the last few seconds is kept for Voice.
// - "In tune" only once the reading has stayed inside the zone for half a second.
// - When the sound stops, the last reading is held (shown dimmed, "Last note") for a few seconds.
// Pure: tests/tuner-reading.test.js.

import { hzToMidi, midiToHz } from '../dsp.js';
import { pitchDisplay, stepDisplay } from '../pitch-display.js';

/** A4's reach and presets, for every tool with a reference pitch (the tuner, the note frequency chart). */
export const REFERENCE = { low: 415, high: 466, standard: 440, presets: [415, 430, 432, 440, 442, 443, 466] };

export const TUNER = {
    tolerance: { voice: 10, instrument: 5 }, // the in-tune zone, ± cents
    inTuneSeconds: 0.5,
    smoothSeconds: { voice: 0.25, instrument: 0.05 },
    holdSeconds: 6,
    trailSeconds: 8, // the trace shows the last 8 s (Hillel's tuner v2)
    gateAboveFloor: 8, // dB over the room's level
    quietSeconds: 0.3, // a too-quiet pitch heard this long says so
    minClarity: 0.6,
};

/** A fresh reading: nothing heard yet. */
export function tunerReading() {
    return { display: pitchDisplay(), at: null, midi: null, last: null, lastAt: null, inTuneSince: null, floor: null, quietSince: null, quietAt: null, trail: [] };
}

/**
 * Steps `state` (changed in place) by one mic reading `{ at, hz, loudness, clarity }` (`at` in
 * seconds, hz 0 when unvoiced, loudness in dBFS) and returns what to show:
 * `{ status: 'live' | 'held' | 'listening', quiet, midi, note, cents, hz, inTune, settling, trail }`.
 * `midi` is the smoothed pitch (fractional, concert, against A4 = `reference`); `note` is the note
 * it's measured from (the `target` when one is set, else the nearest); `cents` how far off.
 * `quietDb` is the sensitivity: nothing below it ever counts.
 */
export function stepTuner(state, { at, hz, loudness, clarity = 1 }, { mode = 'instrument', reference = 440, target = null, tolerance = null, quietDb = -60 } = {}) {
    const dt = state.at === null ? 1 / 60 : Math.min(0.25, Math.max(0, at - state.at));
    state.at = at;
    const pitched = hz > 0 && clarity >= TUNER.minClarity && Number.isFinite(loudness);
    if (Number.isFinite(loudness) && !pitched) {
        // The room: falls to a quieter reading at once (within a third of a second), rises slowly.
        if (state.floor === null) state.floor = loudness;
        else state.floor += (loudness - state.floor) * (1 - Math.exp(-dt / (loudness < state.floor ? 0.3 : 10)));
    }
    const gate = Math.max(quietDb, (state.floor ?? -Infinity) + TUNER.gateAboveFloor);
    const voiced = pitched && loudness >= gate;
    if (pitched && !voiced) {
        state.quietSince ??= at;
        state.quietAt = at;
    } else if (voiced || (state.quietAt !== null && at - state.quietAt > 1)) {
        state.quietSince = null;
        state.quietAt = null;
    }

    state.display = stepDisplay(state.display, { midi: voiced ? hzToMidi(hz, reference) : null, dt });
    // The display bridges a short dropout (a consonant, a missed reading) at full strength: the
    // reading stays live through it rather than flicking to "Last note" and back.
    const shown = state.display.shown !== null && state.display.alpha >= 1 ? state.display.shown : null;
    if (shown === null) {
        state.midi = null;
    } else if (voiced || state.midi === null) {
        const ease = TUNER.smoothSeconds[mode] ?? TUNER.smoothSeconds.instrument;
        state.midi = state.midi === null || Math.abs(shown - state.midi) > 1.5 ? shown : state.midi + (shown - state.midi) * (1 - Math.exp(-dt / ease));
        state.last = state.midi;
        state.lastAt = at;
    }
    if (state.last !== null && at - state.lastAt > TUNER.holdSeconds) state.last = null;
    if (state.midi !== null && voiced) state.trail.push({ at, midi: state.midi });
    while (state.trail.length && at - state.trail[0].at > TUNER.trailSeconds) state.trail.shift();

    const midi = state.midi ?? state.last;
    const status = state.midi !== null ? 'live' : state.last !== null ? 'held' : 'listening';
    const quiet = state.quietSince !== null && at - state.quietSince >= TUNER.quietSeconds && status !== 'live';
    if (midi === null) {
        state.inTuneSince = null;
        return { status, quiet, midi: null, note: target, cents: null, hz: null, inTune: false, settling: false, trail: state.trail };
    }
    const note = target ?? Math.round(midi);
    const cents = (midi - note) * 100;
    const zone = tolerance ?? TUNER.tolerance[mode] ?? TUNER.tolerance.instrument;
    if (status === 'live') {
        if (Math.abs(cents) <= zone) state.inTuneSince ??= at;
        else state.inTuneSince = null;
    }
    const inTune = state.inTuneSince !== null && at - state.inTuneSince >= TUNER.inTuneSeconds;
    return { status, quiet, midi, note, cents, hz: midiToHz(midi, reference), inTune, settling: state.inTuneSince !== null && !inTune, trail: state.trail };
}
