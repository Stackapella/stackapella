// The one note capture (components/voice-capture.js): its scale's honest zoom and its ticks.
import { LOCKED_SPAN, scaleTicks, targetSpan, WIDE_SPAN, zoomOf } from '/js/capture-scale.js';
import { assert, test } from './harness.js';

test('voice capture: the scale stays wide until the voice settles, then closes in only as the note is held', () => {
    assert(targetSpan(0, 2) === WIDE_SPAN && targetSpan(0.5, 2) === WIDE_SPAN, 'not settled: wide');
    assert(targetSpan(1.3, 2) < WIDE_SPAN && targetSpan(1.3, 2) > LOCKED_SPAN, 'part held: closer');
    assert(targetSpan(2, 2) === LOCKED_SPAN && targetSpan(5, 2) === LOCKED_SPAN, 'held: locked, and no closer');
    assert(zoomOf(WIDE_SPAN) === 0 && zoomOf(12) === 1 && zoomOf(LOCKED_SPAN) === 2, 'Wide, Closer, Locked');
});

test('voice capture: the ticks sit round the needle, the note under it marked and named, none named crowding it', () => {
    const wide = scaleTicks(57.2, WIDE_SPAN);
    const active = wide.filter((t) => t.active);
    assert(active.length === 1 && active[0].midi === 57 && active[0].label === 'A3', JSON.stringify(active));
    assert(wide.every((t) => t.at >= -0.02 && t.at <= 1.02), 'all on the scale');
    const named = wide.filter((t) => t.label && !t.active).map((t) => t.label);
    assert(named.every((n) => /^[CF]/.test(n)), `wide: the Cs and Fs only: ${named}`);
    const near = (t) => Math.abs(t.midi - 57.2) * (340 / WIDE_SPAN) <= 34;
    assert(!wide.some((t) => t.label && !t.active && near(t)), 'no name crowding the note');
    const locked = scaleTicks(57, LOCKED_SPAN).filter((t) => t.label).map((t) => t.label);
    assert(locked.includes('A3') && locked.includes('G3') && locked.includes('B3'), `close: the naturals beside it: ${locked}`);
});
