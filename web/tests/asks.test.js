// The ask ledger (js/asks.js, docs/account-nudges-plan.md section 3): one ask a day, an account ask
// at most weekly, Not now puts them all away for 14 days, three stop them, the loss card monthly.
import { askAllowed, dayGap, fromOldKeys, withNotNow, withSeen } from '/js/asks.js';
import { assert, test } from './harness.js';

const DAY = 86400000;
const NOON = new Date(2026, 9, 31, 12).getTime();
const fresh = () => fromOldKeys(() => null);

test('asks: one a day across every kind, and the one shown stays shown that day', () => {
    const seen = withSeen('install', fresh(), NOON);
    assert(askAllowed('install', seen, NOON + 3600000), 'the install card again later today');
    assert(!askAllowed('remind', seen, NOON + 3600000), 'no reminders ask the same day');
    assert(!askAllowed('account', seen, NOON + 3600000), 'no account ask the same day');
    assert(askAllowed('remind', seen, NOON + DAY), 'the next day another may ask');
});

test('asks: an account ask at most once a week, install and reminders unaffected', () => {
    const seen = withSeen('account', fresh(), NOON);
    assert(askAllowed('account', seen, NOON + 1000), 'the same ask again today');
    assert(!askAllowed('acct_week', seen, NOON + 6 * DAY), 'another account ask six days on');
    assert(askAllowed('acct_week', seen, NOON + 7 * DAY), 'a week on');
    assert(askAllowed('install', seen, NOON + DAY) && askAllowed('remind', seen, NOON + DAY), 'install and reminders the next day');
});

test('asks: an ask seen and left unanswered waits a week, the others go on', () => {
    const seen = withSeen('install', fresh(), NOON);
    assert(askAllowed('install', seen, NOON + 3600000), 'again later the same day');
    assert(!askAllowed('install', seen, NOON + DAY), 'not the next day');
    assert(!askAllowed('install', seen, NOON + 6 * DAY), 'not six days on');
    assert(askAllowed('install', seen, NOON + 7 * DAY), 'a week on');
    assert(askAllowed('remind', seen, NOON + DAY), 'another ask the next day');
});

test('asks: a Not now on any puts all away for 14 days, and the third stops them for good', () => {
    let ledger = withNotNow('remind', withSeen('remind', fresh(), NOON), NOON);
    assert(!askAllowed('remind', ledger, NOON + 1000), 'not again today, after its own Not now');
    for (const name of ['install', 'account', 'acct_songs']) assert(!askAllowed(name, ledger, NOON + 13 * DAY), `${name} waits 14 days`);
    assert(askAllowed('install', ledger, NOON + 14 * DAY), 'after 14 days');
    ledger = withNotNow('install', ledger, NOON + 14 * DAY);
    ledger = withNotNow('account', ledger, NOON + 28 * DAY);
    for (const name of ['install', 'remind', 'account', 'acct_week']) assert(!askAllowed(name, ledger, NOON + 400 * DAY), `${name} never after three`);
});

test('asks: the Safari loss card comes at most monthly, even after three Not nows', () => {
    let ledger = fresh();
    for (let i = 0; i < 3; i++) ledger = withNotNow('account', ledger, NOON - 60 * DAY + i);
    assert(askAllowed('acct_risk', ledger, NOON), 'after three Not nows');
    ledger = withSeen('acct_risk', ledger, NOON);
    assert(ledger.accountAt === fresh().accountAt, 'it starts no weekly account clock');
    assert(!askAllowed('acct_risk', ledger, NOON + 29 * DAY), 'not within 30 days');
    assert(askAllowed('acct_risk', ledger, NOON + 30 * DAY), 'a month on');
    assert(!askAllowed('acct_risk', withSeen('install', fresh(), NOON), NOON), 'still one ask a day');
});

test('asks: an ask put away for good stays away, the others go on', () => {
    const ledger = withNotNow('install', fresh(), NOON, { forGood: true });
    assert(!askAllowed('install', ledger, NOON + 100 * DAY), 'the install offer, gone');
    assert(askAllowed('remind', ledger, NOON + 15 * DAY), 'reminders after the snooze');
});

test('asks: the old keys are read into the ledger once', () => {
    const old = {
        'stackapella.keepAsk': JSON.stringify({ nots: 1, at: NOON - 2 * DAY }),
        'stackapella.remindAsk': '{broken',
        'stackapella.installDismissed': '1',
        'stackapella.installAskedOn': new Date(NOON).toDateString(),
    };
    const ledger = fromOldKeys((key) => old[key] ?? null);
    assert(ledger.nots === 2 && ledger.notAt === NOON - 2 * DAY, `two Not nows, the last two days ago (${ledger.nots}, ${ledger.notAt})`);
    assert(ledger.stopped.includes('install'), 'the install offer stays put away');
    assert(!askAllowed('remind', ledger, NOON), 'the wrap offered install today: nothing else today');
    const safe = fromOldKeys((key) => (key === 'stackapella.safeCardDismissed' ? String(NOON) : null));
    assert(safe.nots === 1 && !askAllowed('account', safe, NOON + 5 * DAY), 'the songs card put off five days ago: waits');
});

test('asks: the days away since the last visit, by local day', () => {
    assert(dayGap(null, '2026-10-31') === 0, 'a first visit');
    assert(dayGap('2026-10-31', '2026-10-31') === 0, 'again today');
    assert(dayGap('2026-10-26', '2026-10-31') === 5, 'five days away');
    assert(dayGap('2026-03-27', '2026-04-01') === 5, 'across a clock change');
});
