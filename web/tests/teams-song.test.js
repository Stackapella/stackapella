// Teaming up is Plus, the song side (docs/pricing-plan.md, top; behind plus_teams, js/teams.js): a friend's song whose
// starter's Plus ended (readOnly 'plus_ended') is listened to and kept, records nothing new, and says so calmly; with the
// switch off nothing changes. Record's own check (record-setup.js) is the one place a recording starts.
import { RecordSetup } from '/js/record-setup.js';
import { session } from '/js/stores/session.js';
import { lockReason } from '/js/stores/plan.js';
import { teams } from '/js/teams.js';
import { assert, test } from './harness.js';

const friendsPaused = { id: 'p-paused', title: 'Shared', readOnly: 'plus_ended', shared: { role: 'singer', owner: { id: 'u1', name: 'Ana Ruiz' } } };

/** Taps Record on `project` with the switch `on`: whether the recording went on to open the mic. */
async function recordOn(project, on) {
    const before = { on: teams.on, project: session.project };
    teams.on = on;
    session.project = project;
    let micAsked = false;
    const screen = { busy: false, recording: false, rerecordId: 'part-1', project, dialog: null, prepareMic: async () => (micAsked = true), finishRecording: async () => {} };
    try {
        // Anything past the lock check opens the mic (or asks about headphones first); stop it there.
        screen.dialog = null;
        await Promise.race([RecordSetup.methods.recordOrStop.call(screen), new Promise((resolve) => setTimeout(resolve, 300))]);
        return { went: micAsked || screen.busy || screen.dialog !== null, rerecordId: screen.rerecordId };
    } finally {
        teams.on = before.on;
        session.project = before.project;
    }
}

test("teams: a friend's song paused by its starter's Plus is read-only only while plus_teams is on", () => {
    const before = teams.on;
    try {
        teams.on = true;
        assert(lockReason(friendsPaused) === 'plus_ended', `on: ${lockReason(friendsPaused)}`);
        assert(lockReason({ ...friendsPaused, shared: null }) === null, "your own song is never paused for you");
        assert(lockReason({ ...friendsPaused, readOnly: 'limit' }) === 'limit', 'the plan limit is as it was');
        teams.on = false;
        assert(lockReason(friendsPaused) === null, `off: ${lockReason(friendsPaused)}`);
    } finally {
        teams.on = before;
    }
});

test("teams: Record on a friend's paused song records nothing, and does with the switch off", async () => {
    const paused = await recordOn(friendsPaused, true);
    assert(!paused.went && paused.rerecordId === null, `switch on: ${JSON.stringify(paused)}`);
    const off = await recordOn(friendsPaused, false);
    assert(off.went, `switch off: ${JSON.stringify(off)}`);
});
