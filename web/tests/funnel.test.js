// The onboarding funnel (js/funnel.js): each step counted once per device, the experiment's arm
// drawn on the first open while one enrols (and sent with the counts), and the day-2 and first-week
// returns counted from the day the device arrived.
import { arrive, askShown, askYes, first, funnelRecord, goalMet, variant } from '/js/funnel.js';
import { count, send } from '/js/usage.js';
import { account } from '/js/stores/account.js';
import { local, session } from '/js/storage.js';
import { assert, test } from './harness.js';

const DAY = 86400000;

async function sending(run) {
    const sent = [];
    const beacon = navigator.sendBeacon;
    const had = { config: account.config, me: account.me };
    const kept = local.get('stackapella.funnel');
    navigator.sendBeacon = (url, blob) => {
        sent.push(blob);
        return true;
    };
    try {
        account.config = { usageCounts: true };
        account.me = null;
        send(); // anything another test left queued
        sent.length = 0;
        local.remove('stackapella.funnel');
        session.remove('stackapella.landingVariant');
        await run(async () => {
            send();
            const bodies = await Promise.all(sent.splice(0).map((blob) => blob.text()));
            return bodies.map((b) => JSON.parse(b));
        });
    } finally {
        navigator.sendBeacon = beacon;
        Object.assign(account, had);
        if (kept) local.set('stackapella.funnel', kept);
        else local.remove('stackapella.funnel');
        session.remove('stackapella.landingVariant');
    }
}

const kinds = (bodies) => Object.fromEntries(bodies.flatMap((b) => b.events).map((e) => [e.kind, e.n]));

test('funnel: a step is counted once on this device, however often it happens', async () => {
    await sending(async (sent) => {
        assert(first('f_mic') === true, 'the first time counts');
        assert(first('f_mic') === false, 'the second does not');
        askShown('goal');
        askShown('goal');
        askYes('goal');
        askShown('somebody'); // not an ask: ignored
        const n = kinds(await sent());
        assert(n.f_mic === 1 && n.ask_goal_shown === 1 && n.ask_goal_yes === 1 && Object.keys(n).length === 3, JSON.stringify(n));
        assert(funnelRecord().firsts.f_mic === true, 'the device remembers the step, and nothing about anyone');
    });
});

test('funnel: the first open draws an arm while an experiment enrols, and the counts carry it', async () => {
    await sending(async (sent) => {
        const at = Date.parse('2026-11-02T12:00:00');
        arrive({ experiment: { id: 'onb1', arms: ['a', 'b'], enrol: true }, query: new URLSearchParams('from=start'), at });
        assert(['onb1-a', 'onb1-b'].includes(variant()), `an arm: ${variant()}`);
        const drawn = variant();
        arrive({ experiment: { id: 'onb1', arms: ['a', 'b'], enrol: true }, query: new URLSearchParams(), at });
        assert(variant() === drawn, 'the arm is kept');
        count('app_open');
        const bodies = await sent();
        const n = kinds(bodies);
        assert(n.f_arrive_start === 1 && !n.f_arrive, `an arrival from the landing's Start, once: ${JSON.stringify(n)}`);
        assert(bodies.every((b) => b.variant === drawn), 'the batch carries the arm');
    });
});

test("funnel: an arm the landing's Start drew is the device's, not drawn again", async () => {
    await sending(async () => {
        arrive({ experiment: { id: 'onb1', arms: ['a', 'b'], enrol: true }, query: new URLSearchParams('from=start&v=onb1-a') });
        assert(funnelRecord().variant === 'onb1-a', `the landing's arm: ${funnelRecord().variant}`);
    });
});

test('funnel: no experiment, no arm; a landing variant holds for the visit', async () => {
    await sending(async (sent) => {
        arrive({ experiment: { id: 'onb1', arms: ['a', 'b'], enrol: false }, query: new URLSearchParams('v=land1-b') });
        assert(funnelRecord().variant === null, 'not enrolling: no arm drawn');
        assert(variant() === 'land1-b', `the landing's variant, for this visit: ${variant()}`);
        const bodies = await sent();
        assert(kinds(bodies).f_arrive === 1 && bodies[0].variant === 'land1-b', JSON.stringify(bodies));
        session.remove('stackapella.landingVariant');
        arrive({ query: new URLSearchParams('v=Not a variant!') });
        assert(variant() === null, 'a malformed variant is ignored');
    });
});

test('funnel: a return the next day counts day 2 and week 1; later opens count neither', async () => {
    await sending(async (sent) => {
        const monday = Date.parse('2026-11-02T20:00:00');
        arrive({ at: monday });
        arrive({ at: monday + 3600000 }); // the same day
        arrive({ at: monday + DAY });
        arrive({ at: monday + 3 * DAY });
        goalMet(true, monday + 5 * DAY);
        let n = kinds(await sent());
        assert(n.f_arrive === 1 && n.f_day2 === 1 && n.f_week1 === 1 && n.f_goal_met === 1, JSON.stringify(n));
        local.remove('stackapella.funnel');
        arrive({ at: monday });
        arrive({ at: monday + 8 * DAY });
        goalMet(true, monday + 8 * DAY);
        n = kinds(await sent());
        assert(n.f_arrive === 1 && !n.f_day2 && !n.f_week1 && !n.f_goal_met, `after the first week, neither: ${JSON.stringify(n)}`);
    });
});
