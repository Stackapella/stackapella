// /songs's "Coming up" (js/public/coming-up.js): the same rules as the server's calendar (App\Support\Pages\Festivals),
// on the visitor's own date: a window opens `ahead` days before a festival and closes after its last day; the one on
// now leads, then the nearest; a festival from sundown says "Tonight" on its first day.
import { assert, test } from './harness.js';
import { midnight, pick, state, when } from '/js/public/coming-up.js';

const HANUKKAH = { from: '2026-12-04', until: '2026-12-12', ahead: 21, evening: true };
const CHRISTMAS = { from: '2026-12-25', until: '2026-12-25', ahead: 35, evening: false };
const NEW_YEAR = { from: '2026-12-31', until: '2027-01-01', ahead: 35, evening: false };
const TU_BISHVAT = { from: '2027-01-22', until: '2027-01-23', ahead: 0, evening: true };
const on = (y, m, d) => new Date(y, m - 1, d);
const T = { one: 'Tomorrow', other: 'In # days', tonight: 'Tonight', today: 'Today' };

test('coming up: a window opens ahead and closes after the last day, an evening festival tonight on its first day', () => {
    assert(!state(on(2026, 11, 12), HANUKKAH).open && state(on(2026, 11, 13), HANUKKAH).open, 'opens 21 days ahead');
    const first = state(on(2026, 12, 4), HANUKKAH);
    assert(first.days === 0 && !first.on && when(HANUKKAH, first, T) === 'Tonight', `first evening: ${JSON.stringify(first)}`);
    assert(state(on(2026, 12, 5), HANUKKAH).on && state(on(2026, 12, 12), HANUKKAH).on, 'on through its last day');
    assert(!state(on(2026, 12, 13), HANUKKAH).open, 'closed after');
    assert(state(on(2026, 12, 25), CHRISTMAS).on && when(CHRISTMAS, state(on(2026, 12, 25), CHRISTMAS), T) === '', 'a day festival is on on its day');
    assert(when(CHRISTMAS, state(on(2026, 12, 24), CHRISTMAS), T) === 'Tomorrow' && when(CHRISTMAS, state(on(2026, 11, 20), CHRISTMAS), T) === 'In 35 days', 'how soon');
    assert(!state(on(2027, 1, 20), TU_BISHVAT).open, 'ahead 0 never leads');
    assert(midnight(new Date(2026, 11, 4, 23, 59)).getHours() === 0, 'the visitor\'s day');
});

test('coming up: the one on now first, then the nearest; none when nothing is near', () => {
    const all = [HANUKKAH, CHRISTMAS, NEW_YEAR];
    assert(JSON.stringify(pick(on(2026, 11, 20), all)) === '[0,1]', `20 Nov: ${pick(on(2026, 11, 20), all)}`);
    assert(JSON.stringify(pick(on(2026, 12, 10), [CHRISTMAS, HANUKKAH])) === '[1,0]', 'Hanukkah on leads Christmas');
    assert(JSON.stringify(pick(on(2026, 12, 28), all)) === '[2]', '28 Dec: New Year');
    assert(JSON.stringify(pick(on(2027, 7, 15), all)) === '[]', 'July: nothing');
});
