// The school offer's screens (js/views/school.js, docs/school-offer-plan.md): a typed code made clean,
// and a class code's state.
import { classState, cleanCode } from '/js/views/school.js';
import { assert, test } from './harness.js';

test('school: a code as typed becomes the code (upper case, letters and digits, the dash after four)', () => {
    assert(cleanCode('kq7m3xpa') === 'KQ7M-3XPA', cleanCode('kq7m3xpa'));
    assert(cleanCode(' KQ7M - 3XPA ') === 'KQ7M-3XPA', 'spaces and a dash typed');
    assert(cleanCode('kq7') === 'KQ7' && cleanCode('') === '', 'part of one');
    assert(cleanCode('KQ7M-3XPA-EXTRA') === 'KQ7M-3XPA', 'never longer than a code');
});

test('school: a class code takes students until it is closed, expired or full', () => {
    const now = Date.parse('2026-11-01T12:00:00Z');
    const open = { seats: 30, used: 4, expiresAt: '2027-03-01T00:00:00Z', closedAt: null };
    assert(classState(open, now) === 'open', 'open');
    assert(classState({ ...open, used: 30 }, now) === 'full', 'full');
    assert(classState({ ...open, expiresAt: '2026-10-01T00:00:00Z' }, now) === 'expired', 'expired');
    assert(classState({ ...open, used: 30, closedAt: '2026-10-20T00:00:00Z' }, now) === 'closed', 'closed wins');
});

test('school: the status the server sends wins', () => {
    assert(classState({ status: 'full', seats: 30, used: 4, closedAt: null }) === 'full', 'its word');
});
