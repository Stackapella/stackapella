// The onboarding funnel, counted anonymously (docs/onboarding-review.md, section 4; Hillel: "Yes" to
// the device keeping its firsts and a variant). Each step is a "first": counted the first time it
// happens on this device, so a step's total is devices, not events. The device remembers only which
// firsts it has counted, its experiment variant ("onb1-b": half of all devices share it, so it names
// no one) and the day it arrived; none of it leaves the device except as part of a daily total
// (js/usage.js, counts.create). No ID, no path, nothing about anyone.

import { watch } from 'vue';
import { local, session as tabStorage } from './storage.js';
import { count } from './usage.js';
import { account } from './stores/account.js';

const KEY = 'stackapella.funnel';
const LANDING_VARIANT = 'stackapella.landingVariant'; // ?v= from the landing's Start, for the visit
// The account nudges' asks (docs/account-nudges-plan.md, section 6) are acct_*: N1 to N4, Settings and P4.
// acct_course_<place>: the course wall again, by where it was met (the home's lesson card, a lesson, the course map, a
// course's page), beside acct_course's total, so the growth pull tells them apart (anonymous counts, no ids).
export const ASKS = ['install', 'account', 'name', 'share', 'goal', 'remind', 'nudge', 'acct_week', 'acct_songs', 'acct_unit', 'acct_risk', 'acct_settings', 'acct_course', 'acct_course_home', 'acct_course_lesson', 'acct_course_map', 'acct_course_page'];
const DAY = 86400000;

/** What this device has kept: { firsts: { kind: true }, variant: 'onb1-b' | null, arrived: 'YYYY-MM-DD' | null, free: bool }. */
function kept() {
    try {
        const k = JSON.parse(local.get(KEY) || 'null');
        if (k && typeof k === 'object') return { firsts: k.firsts ?? {}, variant: k.variant ?? null, arrived: k.arrived ?? null, free: !!k.free };
    } catch (error) {
        console.info('The funnel record was unreadable; starting again', error?.message ?? error);
    }
    return { firsts: {}, variant: null, arrived: null, free: false };
}

function keep(record) {
    local.set(KEY, JSON.stringify(record));
}

const today = (at = Date.now()) => new Date(at - new Date(at).getTimezoneOffset() * 60000).toISOString().slice(0, 10);

/** The variant counts carry ("onb1-b"): this device's experiment arm, else the landing's for this visit, else none. */
export function variant() {
    return kept().variant ?? tabStorage.get(LANDING_VARIANT) ?? null;
}

/** Counts `kind` once on this device, ever (a funnel step, or an ask shown or said yes to). */
export function first(kind) {
    const record = kept();
    if (record.firsts[kind]) return false;
    record.firsts[kind] = true;
    keep(record);
    count(kind);
    return true;
}

/** An ask (install, account, name, share, goal, remind) was shown: counted the first time. */
export function askShown(name) {
    if (ASKS.includes(name)) first(`ask_${name}_shown`);
}

/** An ask was put off: a plain daily total each time (the annoyance readout), for the account nudges. */
export function askNot(name) {
    if (ASKS.includes(name) && (name.startsWith('acct_') || name === 'nudge')) count(`ask_${name}_not`);
}

/** An ask was said yes to: counted the first time. */
export function askYes(name) {
    if (ASKS.includes(name)) first(`ask_${name}_yes`);
}

/**
 * The app opened: on this device's first open, its arrival (f_arrive, or f_arrive_start when it came
 * from the landing's Start) and, while an experiment enrols, its arm, drawn at random; on later
 * days, the day-2 and first-week returns. `experiment` is auth.config's ({ id, arms, enrol }) or null;
 * `query` the address's search params.
 */
export function arrive({ experiment = null, query = new URLSearchParams(location.search), at = Date.now() } = {}) {
    // The landing's variant (?v=land1-b) holds for this visit, so its starts compare with its first takes.
    const fromLanding = query.get('v');
    if (fromLanding && /^[a-z0-9]+-[a-z0-9]+$/.test(fromLanding)) tabStorage.set(LANDING_VARIANT, fromLanding);
    const record = kept();
    if (!record.arrived) {
        record.arrived = today(at);
        if (experiment?.enrol && Array.isArray(experiment.arms) && experiment.arms.length) {
            // The landing's Start already drew this visitor's arm (and promised what it shows): kept.
            const drawn = experiment.arms.map((arm) => `${experiment.id}-${arm}`);
            if (drawn.includes(fromLanding)) record.variant = fromLanding;
            else {
                const pick = new Uint32Array(1);
                crypto.getRandomValues(pick);
                record.variant = drawn[pick[0] % drawn.length];
            }
        }
        keep(record);
        first(query.get('from') === 'start' ? 'f_arrive_start' : 'f_arrive');
        return;
    }
    const days = Math.round((Date.parse(today(at)) - Date.parse(record.arrived)) / DAY);
    if (days === 1) first('f_day2');
    if (days >= 1 && days <= 6) first('f_week1');
    if (days === 6) first('f_day7'); // back on day 7: the experiments' longer return
    if (days >= 14 && days <= 20) first('f_day14'); // back in the third week: keep1's guardrail
}

/**
 * The account's plan as this device sees it: Plus started here (f_plus, an experiment's guardrail, as
 * the server's own count can't know an arm) once an account this device saw on the free plan turns
 * Plus; signing in to one already on Plus counts nothing.
 */
export function planSeen(plan) {
    const record = kept();
    if (plan === 'plus') {
        if (record.free) first('f_plus');
    } else if (plan && !record.free) {
        record.free = true;
        keep(record);
    }
}

/** The weekly goal met: a funnel step only in the device's first week (days 1 to 7). */
export function goalMet(met, at = Date.now()) {
    const arrived = kept().arrived;
    if (!met || !arrived) return;
    if (Math.round((Date.parse(today(at)) - Date.parse(arrived)) / DAY) <= 6) first('f_goal_met');
}

/** Whole days since this device arrived (0 on its first day), or null before it has. */
export function daysSinceArrival(at = Date.now()) {
    const arrived = kept().arrived;
    return arrived ? Math.round((Date.parse(today(at)) - Date.parse(arrived)) / DAY) : null;
}

/** Whether this is the day the device arrived (its first session, for the session wrap). */
export function arrivedToday(at = Date.now()) {
    return kept().arrived === today(at);
}

/** For tests: what this device has kept. */
export function funnelRecord() {
    return kept();
}

// The account's plan as it's read (signed in, a refresh after checkout): f_plus when it turns Plus here.
watch(() => account.me?.plan ?? null, planSeen);
