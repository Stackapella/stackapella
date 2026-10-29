// Picking the best singers (js/admin/singers.js): the list's orders.
import { orderSingers } from '/js/admin/singers.js';
import { assert, test } from './harness.js';

const singer = (id, pitchPercent, accepted, defaults = 0) => ({ id, stats: { takes: 3, accepted, retakes: 0, published: accepted, defaults, pitchPercent } });

test('admin singers: newest as sent, best on pitch with the unscored last, or most accepted', () => {
    const list = [singer('a', 80, 1), singer('b', null, 4), singer('c', 93, 2, 1), singer('d', 70, 2, 2)];
    const ids = (order) => orderSingers(list, order).map((s) => s.id).join('');
    assert(ids('newest') === 'abcd', ids('newest'));
    assert(ids('pitch') === 'cadb', ids('pitch'));
    assert(ids('accepted') === 'bdca', `ties go to who leads more parts: ${ids('accepted')}`);
    assert(list[0].id === 'a', 'the list itself is untouched');
});
