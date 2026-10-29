// Wait for me (docs/vocal-coaching-plan.md, "The play-along screen"): the music stops on each note
// and holds it until the singer finds it, within the strictness, for about a third of a second;
// then it moves on. A hint to go higher or lower shows only after a moment of searching. A note found
// quickly adds to the combo ("8 in a row"); a slow one starts it again, quietly. Pure: the screen
// feeds it the pitch it hears each frame and plays what it says on the audio clock.

export const HOLD_TO_LIGHT = 0.33; // seconds on the note to light it
export const HINT_AFTER = 1.5; // seconds searching before the hint
export const QUICK = 2.5; // found within this, the combo grows
const SETTLE = 0.25; // the direction is the one heard for this long, not a passing slide

/** Off the target, in cents, folded to the nearest octave (a man singing an alto line). */
export function centsOff(midi, target) {
    const semitones = ((((midi - target) % 12) + 18) % 12) - 6;
    return semitones * 100;
}

export function startWaiting() {
    return { index: 0, held: 0, searching: 0, direction: null, leaning: 0, leanFor: 0, combo: 0, best: 0, found: [], done: false };
}

/**
 * One frame: `midi` heard (or null), `dt` seconds since the last frame, `cents` the strictness.
 * Returns the next state and what happened: 'lit' (a note found: move on), 'done' (the last one),
 * or null. `state.direction` is 'higher' or 'lower' once a hint is due, else null.
 */
export function heardFrame(state, notes, midi, dt, cents) {
    if (state.done) return { state, event: null };
    const target = notes[state.index].pitch;
    const next = { ...state, searching: state.searching + dt };
    if (midi === null) {
        next.held = 0;
        return { state: hinted(next), event: null };
    }
    const off = centsOff(midi, target);
    if (Math.abs(off) <= cents) {
        next.held = state.held + dt;
        next.leanFor = 0;
        if (next.held >= HOLD_TO_LIGHT) {
            const quick = next.searching <= QUICK;
            const combo = quick ? state.combo + 1 : 0;
            const found = [...state.found, { index: state.index, seconds: next.searching, cents: off }];
            const last = state.index === notes.length - 1;
            return {
                state: { ...startWaiting(), index: last ? state.index : state.index + 1, combo, best: Math.max(state.best, combo), found, done: last },
                event: last ? 'done' : 'lit',
            };
        }
        return { state: { ...next, direction: null }, event: null };
    }
    next.held = 0;
    // Which way to go: the side the voice has stayed on for a moment.
    const lean = off < 0 ? 1 : -1; // flat: go higher
    next.leanFor = lean === state.leaning ? state.leanFor + dt : dt;
    next.leaning = lean;
    return { state: hinted(next), event: null };
}

function hinted(state) {
    const due = state.searching >= HINT_AFTER && state.leanFor >= SETTLE;
    return { ...state, direction: due ? (state.leaning > 0 ? 'higher' : 'lower') : state.searching >= HINT_AFTER ? state.direction : null };
}

/** The word for a found note: 'perfect' within 15 cents, else 'nice'. */
export function praise(cents) {
    return Math.abs(cents) <= 15 ? 'perfect' : 'nice';
}
