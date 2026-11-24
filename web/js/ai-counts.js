// AI optional's engagement counts from the device (docs/ai-optional-plan.md, "Measuring"), behind the ai_optional
// switch. Coming back on day 1 and day 7 (aic:back_d1, aic:back_d7), once in days 1 to 6 (aic:back_w1) and once in days
// 21 to 27 (aic:back_w4), and once on the first open from day 7 the lessons with stars in days 0 to 6 (aic:wk1_lessons).
// Days run from the account's making, or for a guest from the device's arrival. Each with the state (on, off, or unset
// before a choice) and the band stored at sign-up (`unknown` without one, `guest` signed out). Daily totals, never who.

import { account } from './stores/account.js';
import { local } from './storage.js';
import { count } from './usage.js';
import { aiBand, aiChosen, aiOn } from './ai-choice.js';

const KEPT = 'stackapella.aic'; // { day, w1, w4, wk1 }: what this device already sent, per account
const BANDS = new Set(['u18', '18-24', '25-34', '35-44', '45-54', '55-64', '65+', 'guest']);

const dayKey = (at) => new Date(at - new Date(at).getTimezoneOffset() * 60000).toISOString().slice(0, 10);

/** Lessons (and course lessons) with stars, last kept within the first week after `start`. */
export function weekOneLessons(progress, start) {
    const end = start + 7 * 86400000;
    const lists = [progress?.lessons ?? {}, ...Object.values(progress?.courses ?? {}).map((c) => c?.lessons ?? {})];
    return lists.flatMap((l) => Object.values(l)).filter((r) => r?.stars > 0 && r.at >= start && r.at < end).length;
}

export const lessonBucket = (n) => (n === 0 ? '0' : n <= 2 ? '1-2' : n <= 5 ? '3-5' : '6+');

/**
 * The kinds to send today, and what to keep: pure. `kept` is what was sent before (for this account), `days` the whole
 * days since the account was made (local), `lessons` the week-one count.
 */
export function aiReturnKinds({ state, band, days, lessons, kept = {}, today }) {
    const tail = `${['on', 'off'].includes(state) ? state : 'unset'}:${BANDS.has(band) ? band : 'unknown'}`;
    const kinds = [];
    const next = { ...kept };
    if (kept.day !== today) {
        next.day = today;
        if (days === 1 || days === 7) kinds.push(`aic:back_d${days}:${tail}`);
    }
    if (days >= 1 && days <= 6 && !kept.w1) {
        next.w1 = 1;
        kinds.push(`aic:back_w1:${tail}`);
    }
    if (days >= 21 && days <= 27 && !kept.w4) {
        next.w4 = 1;
        kinds.push(`aic:back_w4:${tail}`);
    }
    if (days >= 7 && !kept.wk1) {
        next.wk1 = 1;
        kinds.push(`aic:wk1_lessons:${tail}:${lessonBucket(lessons)}`);
    }
    return { kinds, kept: next };
}

/** When the counted days start: the account's making, or for a guest the device's arrival (js/funnel.js). */
function startOf(me) {
    if (me) return Date.parse(me.createdAt ?? '');
    try {
        const arrived = JSON.parse(localStorage.getItem('stackapella.funnel') || 'null')?.arrived;
        return arrived ? Date.parse(`${arrived}T12:00:00`) : NaN;
    } catch (error) {
        console.info('The arrival day was unreadable', error?.message ?? error);
        return NaN;
    }
}

/** This open's counts, once the account and the settings are known (main.js, after the first screen). */
export function countAiReturns(progress, at = Date.now()) {
    const me = account.me;
    if (account.config?.aiOptional !== true) return;
    const made = startOf(me);
    if (!Number.isFinite(made)) return;
    const days = Math.round((Date.parse(dayKey(at)) - Date.parse(dayKey(made))) / 86400000);
    if (days > 28) return; // past every window: nothing left to send, nothing kept
    let all = {};
    try {
        all = JSON.parse(local.get(KEPT) || '{}') ?? {};
    } catch (error) {
        console.info('The AI counts record was unreadable', error?.message ?? error);
    }
    const id = me ? String(me.id ?? '') : 'guest';
    const state = !aiChosen() ? 'unset' : aiOn() ? 'on' : 'off';
    const { kinds, kept } = aiReturnKinds({ state, band: aiBand(me), days, lessons: weekOneLessons(progress, made), kept: all[id] ?? {}, today: dayKey(at) });
    if (!kinds.length && kept.day === all[id]?.day) return;
    try {
        local.set(KEPT, JSON.stringify({ [id]: kept })); // only this account's record: an older account's goes
    } catch (error) {
        console.info('The AI counts record was not kept', error?.message ?? error);
        return; // without the record, counting would repeat on every open
    }
    for (const kind of kinds) count(kind);
}
