// Wait for me (js/waitforme.js): a note lights after a third of a second on it, never before; the
// hint waits a moment and points the right way; a quick find grows the combo, a slow one restarts it.
import { centsOff, heardFrame, HINT_AFTER, HOLD_TO_LIGHT, praise, startWaiting } from '/js/waitforme.js';
import { assert, close, test } from './harness.js';

const notes = [{ pitch: 60 }, { pitch: 64 }, { pitch: 67 }];
const DT = 1 / 60;

/** Feeds `seconds` of the same reading; returns the state and the events seen. */
function feed(state, midi, seconds, cents = 50) {
    const events = [];
    for (let t = 0; t < seconds - 1e-9; t += DT) {
        const out = heardFrame(state, notes, midi, DT, cents);
        state = out.state;
        if (out.event) events.push(out.event);
    }
    return { state, events };
}

test('wait for me: a note lights after a third of a second on it, and the music moves on', () => {
    let { state, events } = feed(startWaiting(), 60.1, HOLD_TO_LIGHT - 0.05);
    assert(!events.length && state.index === 0, 'not yet');
    ({ state, events } = feed(state, 60.1, 0.1));
    assert(events[0] === 'lit' && state.index === 1, `lit and on: ${JSON.stringify(events)}`);
    assert(state.found[0].index === 0 && Math.abs(state.found[0].cents - 10) < 1, 'remembers how close');
    ({ state, events } = feed(state, 52, 0.5)); // an octave below E4 counts (folded)
    assert(events[0] === 'lit', 'an octave down still counts');
    ({ state, events } = feed(state, 67, 0.5));
    assert(events.includes('done') && state.done, 'the last note ends it');
});

test('wait for me: a wobble resets the hold, and silence never lights a note', () => {
    let state = startWaiting();
    for (let i = 0; i < 3; i++) {
        ({ state } = feed(state, 60, 0.2));
        ({ state } = feed(state, 61.5, 0.1));
    }
    assert(state.index === 0, 'never held a third of a second');
    ({ state } = feed(state, null, 3));
    assert(state.index === 0 && state.held === 0, 'silence lights nothing');
});

test('wait for me: Higher or Lower only after a moment, the way the voice has stayed', () => {
    let { state } = feed(startWaiting(), 57, HINT_AFTER - 0.2);
    assert(state.direction === null, 'no hint at first');
    ({ state } = feed(state, 57, 0.5));
    assert(state.direction === 'higher', `flat: higher (${state.direction})`);
    ({ state } = feed(state, 63, 0.5));
    assert(state.direction === 'lower', 'sharp: lower');
    close(centsOff(59.5, 60), -50, 1e-9, 'cents off');
    assert(praise(-12) === 'perfect' && praise(30) === 'nice', 'the words');
});

test('wait for me: quick finds grow the combo; a slow one starts it again', () => {
    let { state } = feed(startWaiting(), 60, 0.4);
    ({ state } = feed(state, 64, 0.4));
    assert(state.combo === 2, `two in a row: ${state.combo}`);
    ({ state } = feed(state, 70, 3));
    ({ state } = feed(state, 67, 0.4));
    assert(state.combo === 0 && state.best === 2 && state.done, `slow: starts again (${state.combo}), best kept`);
});
