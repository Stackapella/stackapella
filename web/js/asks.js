// The ask ledger (docs/account-nudges-plan.md, section 3): one record on this device,
// `stackapella.asks`, that every proactive ask (install, an account, reminders) checks before it
// shows, so together they stay within the caps:
//   - one ask a day (a session), across every kind: the first to show keeps the day;
//   - an account ask at most once a week;
//   - a Not now on any of them puts all of them away for 14 days;
//   - three Not nows in all, and they stop for good (walls, Settings and the header link stay);
//   - an ask seen and left unanswered waits a week before it shows again (Hillel, 2026-11-02: "ask
//     once"; the install card had shown on every Home through a singer's first week);
//   - the Safari loss card (acct_risk) is the one exception: at most once in 30 days, even after
//     three Not nows (Hillel, 2026-10-30), still one ask a day.
// Funnel counts stay in funnel.js (askShown, askYes); this only decides whether an ask may show.

import { local, session } from './storage.js';

const KEY = 'stackapella.asks';
const DAY = 24 * 3600 * 1000;
export const SNOOZE = 14 * DAY;
export const ACCOUNT_EVERY = 7 * DAY;
export const RISK_EVERY = 30 * DAY;
export const MOST_NOT_NOWS = 3;
export const UNANSWERED_WAIT = 7 * DAY;

/** Asks for an account, which share the weekly cap: the wrap's and Home's ("account"), and acct_*. */
export const isAccountAsk = (name) => name === 'account' || name.startsWith('acct_');

const empty = () => ({ day: null, today: null, nots: 0, notAt: 0, accountAt: 0, riskAt: 0, stopped: [], seenAt: {} });

function parse(raw, fallback) {
    try {
        return JSON.parse(raw ?? 'null') ?? fallback;
    } catch (error) {
        console.info('An old ask answer was unreadable', error?.message ?? error);
        return fallback;
    }
}

/**
 * The ledger from the keys each ask kept before it (read once, then removed): the wrap's and the
 * reminders' { nots, at }, the install offer put away for good, the songs card's last Later, and the
 * day the wrap offered install.
 */
export function fromOldKeys(get = (key) => local.get(key)) {
    const ledger = empty();
    for (const key of ['stackapella.keepAsk', 'stackapella.remindAsk']) {
        const kept = parse(get(key), null);
        if (kept && typeof kept === 'object' && Number(kept.nots) > 0) {
            ledger.nots += Number(kept.nots);
            ledger.notAt = Math.max(ledger.notAt, Number(kept.at) || 0);
        }
    }
    if (get('stackapella.installDismissed') === '1') {
        ledger.nots += 1;
        ledger.stopped.push('install');
    }
    const safe = Number(get('stackapella.safeCardDismissed') || 0);
    if (safe > 0) {
        ledger.nots += 1;
        ledger.notAt = Math.max(ledger.notAt, safe);
    }
    const asked = get('stackapella.installAskedOn');
    if (asked) Object.assign(ledger, { day: asked, today: 'install' });
    return ledger;
}

const OLD_KEYS = ['stackapella.keepAsk', 'stackapella.remindAsk', 'stackapella.installDismissed', 'stackapella.safeCardDismissed', 'stackapella.installAskedOn'];

/** The ledger as kept, or made from the old keys the first time. */
export function readLedger() {
    const raw = local.get(KEY);
    if (raw === null) {
        const ledger = fromOldKeys();
        writeLedger(ledger);
        for (const key of OLD_KEYS) local.remove(key);
        return ledger;
    }
    const kept = parse(raw, null);
    return kept && typeof kept === 'object' ? { ...empty(), ...kept, stopped: Array.isArray(kept.stopped) ? kept.stopped : [], seenAt: kept.seenAt && typeof kept.seenAt === 'object' ? kept.seenAt : {} } : empty();
}

function writeLedger(ledger) {
    local.set(KEY, JSON.stringify(ledger));
}

const dayOf = (now) => new Date(now).toDateString();

/** Whether the ask `name` may show now, by the caps above. Pure, for the tests. */
export function askAllowed(name, ledger, now = Date.now()) {
    if (ledger.stopped.includes(name)) return false;
    const mine = ledger.day === dayOf(now) && ledger.today === name;
    if (ledger.day === dayOf(now) && ledger.today && !mine) return false; // one a day
    if (ledger.notAt && dayOf(ledger.notAt) === dayOf(now)) return false; // put off today, by any ask
    if (mine) return true; // already shown today and not put off: it stays (a reload, another screen)
    if (name === 'acct_risk') return now - ledger.riskAt >= RISK_EVERY;
    if (ledger.nots >= MOST_NOT_NOWS) return false;
    if (ledger.nots && now - ledger.notAt < SNOOZE) return false;
    if (isAccountAsk(name) && ledger.accountAt && now - ledger.accountAt < ACCOUNT_EVERY) return false;
    const seen = ledger.seenAt?.[name] ?? 0;
    if (seen && dayOf(seen) !== dayOf(now) && now - seen < UNANSWERED_WAIT) return false; // seen and left: a week
    return true;
}

/** Whether the ask `name` may show now on this device. */
export const askDue = (name, now = Date.now()) => askAllowed(name, readLedger(), now);

/** Pure: the ledger once `name` has shown. */
export function withSeen(name, ledger, now = Date.now()) {
    const next = { ...ledger, day: dayOf(now), today: name };
    if (ledger.day === dayOf(now) && ledger.today === name) return next; // the same ask again today
    next.seenAt = { ...(ledger.seenAt ?? {}), [name]: now };
    if (isAccountAsk(name) && name !== 'acct_risk') next.accountAt = now;
    if (name === 'acct_risk') next.riskAt = now;
    return next;
}

/** Pure: the ledger after a Not now on `name`; `forGood` stops that one ask on this device. */
export function withNotNow(name, ledger, now = Date.now(), { forGood = false } = {}) {
    return { ...ledger, nots: ledger.nots + 1, notAt: now, stopped: forGood && !ledger.stopped.includes(name) ? [...ledger.stopped, name] : ledger.stopped };
}

/** The ask `name` is on screen: it keeps today's slot (and the account or risk clock starts). */
export function askSeen(name, now = Date.now()) {
    writeLedger(withSeen(name, readLedger(), now));
}

/** Not now on `name`: every ask waits 14 days, and the third ends them. */
export function askNotNow(name, { forGood = false, now = Date.now() } = {}) {
    writeLedger(withNotNow(name, readLedger(), now, { forGood }));
}

const LAST_VISIT = 'stackapella.lastVisit'; // the last day the app was opened here (YYYY-MM-DD, local), nothing else
const AWAY = 'stackapella.daysAway'; // this tab's visit: the gap read when it opened

const localDay = (at) => new Date(at - new Date(at).getTimezoneOffset() * 60000).toISOString().slice(0, 10);

/** Pure: whole days between two local days ('YYYY-MM-DD'), 0 without a last one. */
export const dayGap = (last, today) => (last ? Math.max(0, Math.round((Date.parse(today) - Date.parse(last)) / DAY)) : 0);

/**
 * Days since the app was last opened on this device, for this visit (the Safari loss card asks after
 * 4 or more): read once a tab, when the app opens, and today kept as the last visit.
 */
export function daysAway(now = Date.now()) {
    const kept = session.get(AWAY);
    if (kept !== null) return Number(kept) || 0;
    const today = localDay(now);
    const gap = dayGap(local.get(LAST_VISIT), today);
    local.set(LAST_VISIT, today);
    session.set(AWAY, String(gap));
    return gap;
}
