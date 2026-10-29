// The pitch bar (js/listening.js): the marker glides, the window starts wide and zooms in once
// the note settles, the ticks fit the window, and a comfortable note suggests parts.
import { across, easeWindow, glide, partsFor, SETTLE_SECONDS, targetWindow, ticks, WIDE } from '/js/listening.js';
import { assert, test } from './harness.js';

test('listening: the marker glides toward the voice, never jumping there in one frame', () => {
    assert(glide(null, 57, 0.016) === 57, 'the first reading places it');
    const next = glide(57, 59, 0.016);
    assert(next > 57 && next < 58, `one frame moves it a little: ${next}`);
    let shown = 57;
    for (let i = 0; i < 60; i++) shown = glide(shown, 59, 0.016);
    assert(Math.abs(shown - 59) < 0.05, `a second later it's there: ${shown}`);
    assert(glide(57, null, 0.016) === 57, 'no reading: it stays');
});

test('listening: wide until the note settles, then an octave around it, eased unless motion is reduced', () => {
    assert(JSON.stringify(targetWindow(57, 0.2)) === JSON.stringify(WIDE), 'not settled: wide');
    const zoomed = targetWindow(57.3, SETTLE_SECONDS);
    assert(zoomed.lo === 51 && zoomed.hi === 63, JSON.stringify(zoomed));
    const eased = easeWindow(WIDE, zoomed, 0.016);
    assert(eased.lo > WIDE.lo && eased.lo < zoomed.lo, `eased: ${JSON.stringify(eased)}`);
    assert(JSON.stringify(easeWindow(WIDE, zoomed, 0.016, { still: true })) === JSON.stringify(zoomed), 'reduced motion: at once');
    assert(across(57, zoomed) === 0.5 && across(20, zoomed) === 0 && across(99, zoomed) === 1, 'positions, clamped');
});

test('listening: wide shows the Cs named, zoomed names every natural note', () => {
    const wide = ticks(WIDE);
    assert(wide.filter((t) => t.label).map((t) => t.label).join() === 'C2,C3,C4,C5,C6', wide.filter((t) => t.label).map((t) => t.label).join());
    const zoomed = ticks({ lo: 51, hi: 63 });
    assert(zoomed.length === 13 && zoomed.filter((t) => t.label).length === 7, `${zoomed.length} ticks, ${zoomed.filter((t) => t.label).length} named`);
});

test('listening: a comfortable note suggests the parts that may suit', () => {
    assert(partsFor(45) === 'listening.suits.low' && partsFor(50) === 'listening.suits.lowMiddle' && partsFor(55) === 'listening.suits.highMiddle' && partsFor(60) === 'listening.suits.high', 'by register');
});
