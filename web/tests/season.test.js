// The season's date rules (js/public/season.js): the logo's Santa hat on Christmas Eve and Christmas Day only, by the
// device's own calendar.
import { assert, test } from './harness.js';
import { hatDay, showHats } from '../js/public/season.js';

test('season: the hat shows on 24 and 25 December only, by the local date', () => {
    for (const [y, m, d, on] of [[2026, 11, 24, true], [2026, 11, 25, true], [2026, 11, 23, false], [2026, 11, 26, false], [2026, 10, 10, false], [2027, 0, 1, false], [2027, 11, 24, true]]) {
        assert(hatDay(new Date(y, m, d, 12)) === on, `${y}-${m + 1}-${d}: ${on ? 'on' : 'off'}`);
    }
    // Late on the 23rd and early on the 26th, local time, stay off.
    assert(!hatDay(new Date(2026, 11, 23, 23, 59)) && !hatDay(new Date(2026, 11, 26, 0, 1)), 'only the two whole days');
});

test('season: showHats unhides only the hidden hats, and only on a hat day', () => {
    const root = document.createElement('div');
    root.innerHTML = '<svg data-hat-christmas hidden></svg><svg class="other" hidden></svg>';
    showHats(new Date(2026, 10, 10), root);
    assert(root.querySelector('[data-hat-christmas]').hasAttribute('hidden'), 'still hidden on 10 November');
    showHats(new Date(2026, 11, 24), root);
    assert(!root.querySelector('[data-hat-christmas]').hasAttribute('hidden'), 'shown on Christmas Eve');
    assert(root.querySelector('.other').hasAttribute('hidden'), 'nothing else touched');
});
