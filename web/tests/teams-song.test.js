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

// Hillel, 2026-11-15: "in the studio if you click invite singers you should need to start a trial to be able to add
// someone to a song".
test('teams: Invite singers is the Plus sheet first for a starter without Plus, only while plus_teams is on', async () => {
    const { TogetherSheet } = await import('/js/components/together.js');
    const { account } = await import('/js/stores/account.js');
    const plusFirst = (screen) => TogetherSheet.computed.plusFirst.call(screen);
    const before = { on: teams.on, me: account.me };
    const own = { singer: false, members: null };
    try {
        teams.on = true;
        account.me = { id: 'u-me', name: 'Kim Ode', plan: 'free' };
        assert(plusFirst(own), 'a free starter gets the Plus sheet first');
        assert(plusFirst({ ...own, members: [{ id: 'u-me', role: 'owner' }] }), 'nobody singing in it yet: still the Plus sheet');
        assert(!plusFirst({ ...own, members: [{ id: 'u-me', role: 'owner' }, { id: 'u-sam', role: 'singer' }] }), 'friends already in it: who is in it, and the paused note');
        assert(!plusFirst({ ...own, singer: true }), 'a friend on the song is never asked for Plus');
        account.me = { ...account.me, plan: 'plus' }; // a trial counts: the account says plus
        assert(!plusFirst(own), 'Plus (or the trial) invites as before');
        account.me = null;
        assert(!plusFirst(own), 'a guest is asked for an account first');
        account.me = { id: 'u-me', name: 'Kim Ode', plan: 'free' };
        teams.on = false;
        assert(!plusFirst(own), 'switch off: inviting is free');
    } finally {
        teams.on = before.on;
        account.me = before.me;
    }
});

test("teams: the invite sheet says Plus's price, a year and a month, in the account's currency", async () => {
    const { PlusMoment } = await import('/js/components/plus-moment.js');
    const { plan } = await import('/js/stores/plan.js');
    const before = { prices: plan.prices, billing: plan.billing };
    try {
        plan.prices = { prices: [{ interval: 'year', currency: 'usd', amountCents: 3999 }, { interval: 'month', currency: 'usd', amountCents: 499 }, { interval: 'year', currency: 'eur', amountCents: 3999 }, { interval: 'month', currency: 'eur', amountCents: 499 }] };
        plan.billing = { plan: 'free', subscription: { currency: 'usd' } };
        const line = PlusMoment.computed.priceLine.call({ price: true });
        assert(line.includes('$39.99') && line.includes('$4.99'), line);
        assert(PlusMoment.computed.priceLine.call({ price: false }) === '', 'no price unless asked');
        plan.prices = null;
        assert(PlusMoment.computed.priceLine.call({ price: true }) === '', 'nothing before the prices load');
    } finally {
        plan.prices = before.prices;
        plan.billing = before.billing;
    }
});
