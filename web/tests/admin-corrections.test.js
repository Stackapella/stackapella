// The Corrections inbox's actions (js/admin/corrections.js): what each state offers, and that a refusal needs a reason.
import { ACTIONS, actionsFor, KINDS, STATES } from '/js/admin/corrections.js';
import { assert, test } from './harness.js';

test('admin corrections: a new one is accepted or rejected, an accepted one done or rejected, the rest nothing', () => {
    assert(actionsFor('new').join() === 'accepted,rejected');
    assert(actionsFor('accepted').join() === 'done,rejected');
    assert(!actionsFor('done').length && !actionsFor('rejected').length);
});

test('admin corrections: only a refusal requires its note; the filters start at To read and All', () => {
    assert(ACTIONS.rejected.required && !ACTIONS.accepted.required && !ACTIONS.done.required);
    assert(STATES[0] === 'new' && KINDS[0] === 'all');
});
