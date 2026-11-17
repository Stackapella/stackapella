// Shared private arrangements need everyone's own Plus (Hillel, 2026-11-17; docs/pricing-plan.md, top; behind
// plus_private, js/teams.js's teams.private): a song read `plus_needed` is kept and records nothing, Record opens the Plus
// sheet instead (never an error or a toast), and only this rule's refusals (reason private_shared) get its words. With the
// switch off nothing changes.
import { ApiError } from '/js/api.js';
import { privateRefusal } from '/js/components/private-plus.js';
import { RecordSetup } from '/js/record-setup.js';
import { session } from '/js/stores/session.js';
import { lockReason } from '/js/stores/plan.js';
import { teams } from '/js/teams.js';
import { assert, test } from './harness.js';

const friends = { id: 'p-private', title: 'Shared', readOnly: 'plus_needed', shared: { role: 'singer', owner: { id: 'u1', name: 'Ana Ruiz' } } };
const yours = { ...friends, shared: null };

test('private songs: plus_needed is read-only for a friend and the creator alike, only while plus_private is on', () => {
    const before = teams.private;
    try {
        teams.private = true;
        assert(lockReason(friends) === 'plus_needed', `a friend's: ${lockReason(friends)}`);
        assert(lockReason(yours) === 'plus_needed', `your own: ${lockReason(yours)}`);
        assert(lockReason({ ...friends, readOnly: null }) === null, 'with Plus, open');
        teams.private = false;
        assert(lockReason(friends) === null && lockReason(yours) === null, 'off: as before');
    } finally {
        teams.private = before;
    }
});

test('private songs: Record on a plus_needed song opens the Plus sheet and records nothing', async () => {
    const before = { private: teams.private, project: session.project };
    teams.private = true;
    session.project = friends;
    let micAsked = false;
    const screen = { busy: false, recording: false, rerecordId: 'part-1', project: friends, dialog: null, plusSheet: false, prepareMic: async () => (micAsked = true), finishRecording: async () => {} };
    try {
        await Promise.race([RecordSetup.methods.recordOrStop.call(screen), new Promise((resolve) => setTimeout(resolve, 300))]);
        assert(screen.plusSheet === true, 'the Plus sheet');
        assert(!micAsked && !screen.busy && screen.rerecordId === null, `nothing recorded: ${JSON.stringify({ micAsked, busy: screen.busy })}`);
    } finally {
        teams.private = before.private;
        session.project = before.project;
    }
});

test("private songs: only this rule's refusal gets its words", () => {
    const refusal = (body) => new ApiError({ status: 403, code: 'plus_required', operation: 'templates.show', body });
    assert(privateRefusal(refusal({ code: 'plus_required', reason: 'private_shared' })), 'reason private_shared');
    assert(!privateRefusal(refusal({ code: 'plus_required' })), 'teaming up keeps its own words');
    assert(!privateRefusal(new ApiError({ status: 404, code: 'not_found', operation: 'templates.show' })), 'not found is not');
});
