// The hanukkiah's night (js/public/hanukkiah.js): the shamash and that night's candles, counted in the visitor's own
// day, a night beginning at sundown (16:30) and holding until the next evening; candle 1 the far right.
import { assert, test } from './harness.js';
import { light, night } from '/js/public/hanukkiah.js';

const FIRSTS = ['2026-12-04', '2027-12-24'];
const at = (y, m, d, h, min = 0) => night(new Date(y, m - 1, d, h, min), FIRSTS);

test('hanukkiah: the night from sundown on the first evening to nightfall after the eighth day, any year', () => {
    assert(at(2026, 12, 4, 16, 29) === null, 'before the first evening: not yet');
    assert(at(2026, 12, 4, 16, 30) === 1 && at(2026, 12, 4, 20) === 1, 'the first evening: night 1');
    assert(at(2026, 12, 5, 12) === 1, 'day 1 still has night 1\'s lights');
    assert(at(2026, 12, 5, 17) === 2, 'the second evening: night 2');
    assert(at(2026, 12, 7, 20) === 4, '7 December at 20:00: night 4');
    assert(at(2026, 12, 11, 18) === 8 && at(2026, 12, 12, 16, 29) === 8, 'night 8, through day 8 until sundown');
    assert(at(2026, 12, 12, 16, 30) === null && at(2026, 12, 20, 12) === null, 'after the eighth day: over');
    assert(at(2027, 12, 24, 17) === 1 && at(2028, 1, 1, 9) === 8 && at(2028, 1, 1, 17) === null, 'the next year, across New Year');
    assert(night(new Date(2026, 11, 7, 20), []) === null && night(new Date(2026, 11, 7, 20), ['nonsense']) === null, 'no dates: no count');
});

test('hanukkiah: a night lights its candles from the right, and none leaves every one lit', () => {
    const lamp = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    for (let n = 1; n <= 8; n++) {
        const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        g.dataset.hk = String(n);
        lamp.append(g);
    }
    const unlit = () => [...lamp.querySelectorAll('[data-unlit]')].map((g) => Number(g.dataset.hk));
    light(lamp, 3);
    assert(JSON.stringify(unlit()) === '[4,5,6,7,8]' && lamp.dataset.night === '3', `night 3: ${unlit()}`);
    light(lamp, null);
    assert(unlit().length === 0 && lamp.dataset.night === undefined, 'outside Hanukkah: all lit');
});
