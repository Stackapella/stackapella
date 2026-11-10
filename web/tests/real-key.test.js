// "Prefer the real key" (js/real-key.js): a part with a real voice keeps the song's own key when the singer's range
// covers it within two semitones.
import { coversWithin, REAL_KEY_SLACK } from '/js/real-key.js';
import { assert, test } from './harness.js';

test('real key: a range covering the part within two semitones keeps the song\'s key', () => {
    const part = { range: { low: 59, high: 71 } };
    assert(REAL_KEY_SLACK === 2, 'two semitones');
    assert(coversWithin({ low: 59, high: 71 }, part), 'exactly');
    assert(coversWithin({ low: 61, high: 73 }, part) && coversWithin({ low: 57, high: 69 }, part), 'two either way');
    assert(!coversWithin({ low: 62, high: 74 }, part) && !coversWithin({ low: 56, high: 68 }, part), 'three is too far');
    assert(coversWithin({ low: 61, high: 69 }, part), 'two short at each end: still covered');
    assert(!coversWithin(null, part) && !coversWithin({ low: 59, high: 71 }, {}), 'nothing known: no');
});
