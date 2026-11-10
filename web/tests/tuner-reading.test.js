// The free tuner's reading (js/public/tuner-reading.js): the noise gate, the octave guard, Voice's
// smoothing, the in-tune hold and the last note held.
import { stepTuner, TUNER, tunerReading } from '/js/public/tuner-reading.js';
import { midiToHz } from '/js/dsp.js';
import { assert, close, test } from './harness.js';

/** Feeds readings at 60 a second from `from` for `seconds`; `pitch(t)` is MIDI (null: unvoiced). Returns the last view. */
function feed(state, from, seconds, pitch, { loudness = -30, ...options } = {}) {
    let view = null;
    for (let i = 0; i < Math.round(seconds * 60); i++) {
        const t = from + i / 60;
        const midi = pitch(t);
        const level = typeof loudness === 'function' ? loudness(t) : loudness;
        view = stepTuner(state, { at: t, hz: midi === null ? 0 : midiToHz(midi), loudness: level, clarity: midi === null ? 0.2 : 0.95 }, options);
    }
    return view;
}

test('tuner reading: a held A4 three cents sharp reads A, +3, and is in tune only after half a second', () => {
    const s = tunerReading();
    let view = feed(s, 0, 0.3, () => 69.03);
    assert(view.status === 'live' && view.note === 69 && !view.inTune && view.settling, `early: ${JSON.stringify({ ...view, trail: 0 })}`);
    view = feed(s, 0.3, 0.5, () => 69.03);
    assert(view.inTune, 'in tune after half a second');
    close(view.cents, 3, 0.3, 'cents');
    close(view.hz, 440 * 2 ** (0.03 / 12), 0.2, 'Hz');
});

test('tuner reading: Instrument wants ±5 cents, Voice ±10, and a target measures from it', () => {
    const s = tunerReading();
    assert(!feed(s, 0, 1, () => 57.08, { mode: 'instrument' }).inTune, '8 cents is out for an instrument');
    const v = tunerReading();
    assert(feed(v, 0, 1, () => 57.08, { mode: 'voice' }).inTune, '8 cents is in for a voice');
    const t = tunerReading();
    const view = feed(t, 0, 1, () => 40.3, { target: 40 });
    assert(view.note === 40 && Math.round(view.cents) === 30, `against the string: ${view.cents}`);
    const far = feed(tunerReading(), 0, 1, () => 41, { target: 40 });
    assert(Math.round(far.cents) === 100, 'a whole tone away still measures from the string');
});

test('tuner reading: Voice averages a vibrato, Instrument follows it', () => {
    const vibrato = (t) => 60 + 0.5 * Math.sin(2 * Math.PI * 5.5 * t);
    const swing = (mode) => {
        const s = tunerReading();
        feed(s, 0, 1, vibrato, { mode });
        let low = Infinity;
        let high = -Infinity;
        for (let i = 0; i < 60; i++) {
            const view = feed(s, 1 + i / 60, 1 / 60, vibrato, { mode });
            low = Math.min(low, view.cents);
            high = Math.max(high, view.cents);
        }
        return high - low;
    };
    const voice = swing('voice');
    const instrument = swing('instrument');
    assert(voice < 25 && instrument > 2 * voice, `voice swings ${voice.toFixed(1)}, instrument ${instrument.toFixed(1)}`);
});

test('tuner reading: an octave slip is held off, a real change of note is believed', () => {
    const s = tunerReading();
    feed(s, 0, 0.5, () => 28); // E1
    // A few readings an octave up (a harmonic) don't move it.
    const slip = feed(s, 0.5, 4 / 60, () => 40);
    assert(slip.note === 28, `held off the octave: ${slip.note}`);
    feed(s, 0.6, 0.3, () => 28);
    const change = feed(s, 0.9, 0.3, () => 33); // A1: a different string
    assert(change.note === 33, `a new note: ${change.note}`);
});

test('tuner reading: the last note is held, dimmed, then let go; the room\'s noise never reads', () => {
    const s = tunerReading();
    feed(s, 0, 1, () => 64);
    let view = feed(s, 1, 0.5, () => null);
    assert(view.status === 'held' && view.note === 64, `held: ${view.status}`);
    view = feed(s, 1.5, TUNER.holdSeconds, () => null);
    assert(view.status === 'listening' && view.midi === null, 'let go');
    // A fan at -40 dB with a pitch-like hum: the floor rises to it, and a quiet hum under the gate is "too quiet".
    const room = tunerReading();
    feed(room, 0, 3, () => null, { loudness: -40 });
    view = feed(room, 3, 1, () => 57, { loudness: -36 });
    assert(view.status === 'listening' && view.quiet, `too quiet over the room: ${view.status} ${view.quiet}`);
    view = feed(room, 4, 1, () => 57, { loudness: -20 });
    assert(view.status === 'live' && !view.quiet, 'clearly above it reads');
    // Held for a long time, a note never gates itself.
    view = feed(room, 5, 30, () => 57, { loudness: -20 });
    assert(view.status === 'live', 'still live after 30 s');
});
