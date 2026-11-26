// AI optional (docs/ai-optional-plan.md; its default changed by Hillel, 2026-11-26: "I'd like instead most new users to see
// it but have the option to hide forever, this way we learn more faster"): every AI feature behind one choice. The one
// source of truth every hide reads: an account's own choice (account.show `aiFeatures`: true or false; null, not chosen,
// acts as on, as does `existing`, the on every older account was given), else this device's key (`on` after an arrival
// through /ai-harmony-singing, `off` after Hide AI features, absent as on). An account under 18 is always off. Off:
// AI guides give way to the practice voice (ai-voices.js allowed), Hear yourself as a choir and Lend your voice are hidden
// (own-choir-state.js, views/voices.js). Real voices are never touched. The way out is a quiet Hide AI features on each AI
// entry point an undecided singer sees (hideAi below; components/ai-hide.js). Small and first-screen safe.

import { reactive } from 'vue';
import { account } from './stores/account.js';
import { local } from './storage.js';
import { count } from './usage.js';

export const AI_KEY = 'stackapella.ai';
const FROM = 'stackapella.aiFrom'; // where the device's choice came from: 'hide', 'card' (before 2026-11-26) or 'ai-singing' (the account's source names)

/** Whether the choice exists yet: the ai_optional release switch (auth.config `aiOptional`), known to be true. */
export function aiOptional() {
    return account.config?.aiOptional === true;
}

// A guest's choice is kept in storage, which Vue can't see: this ticks when it changes, so what reads aiOn() follows.
const device = reactive({ changed: 0 });

/** This device's key: 'on', 'off' or null (not chosen). */
export function deviceAi() {
    void device.changed;
    return local.get(AI_KEY);
}

/** An account under 18 by its stored band: AI stays off for them, whatever is set (the server's same rule, AiChoice::aiOn). */
const minor = (me) => me?.ageBand === 'u18'; // the band alone, as the server reads it (no birth date: not known to be under 18)

/**
 * Whether AI features are on here. Before the ai_optional switch, on for everyone, exactly as today. After it: the
 * account's choice (undecided: on; under 18: off), else the device's key (none yet: on).
 */
export function aiOn() {
    if (!aiOptional()) return true;
    const me = account.me;
    if (me) return !minor(me) && me.aiFeatures !== false;
    return deviceAi() !== 'off';
}

/** Whether a choice has been made here: the account's, else this device's. An `existing` account's on wasn't a choice. */
export function aiChosen() {
    const me = account.me;
    if (me) return typeof me.aiFeatures === 'boolean' && me.aiFeaturesSource !== 'existing';
    return deviceAi() === 'on' || deviceAi() === 'off';
}

/**
 * An arrival through /ai-harmony-singing (the AI page; /es/armonias-con-ia): this device's key on, for a guest and for the account they make. Its links carry
 * ?ref=ai-harmony-singing (or &for=ai beside a visitor's own ref), and the site sets `sa_for=ai` (a first-party cookie on the
 * domain the site and the app share, no ID) on any visit there.
 */
export function noteAiArrival(search = location.search, cookie = document.cookie) {
    const query = new URLSearchParams(search);
    if (query.get('ref') === 'ai-harmony-singing' || query.get('for') === 'ai' || cookie.split('; ').includes('sa_for=ai')) {
        if (deviceAi() === 'on' && local.get(FROM) === 'card') return; // already on from the card: its source stays
        if (deviceAi() === 'off' && local.get(FROM) === 'hide') return; // hidden for good (Hillel): Settings brings it back, not a link
        local.set(AI_KEY, 'on');
        local.set(FROM, 'ai-singing');
    }
}

/** The device's choice as the account takes it: { aiFeatures, aiFeaturesSource }, or {} with none. */
function deviceChoice() {
    const key = deviceAi();
    if (key !== 'on' && key !== 'off') return {};
    const from = local.get(FROM);
    return { aiFeatures: key === 'on', aiFeaturesSource: ['hide', 'card', 'ai-singing'].includes(from) ? from : key === 'on' ? 'ai-singing' : 'hide' };
}

/** What a new account is made with: the device's choice, or nothing (the field left out: not chosen). */
export function registerAi() {
    return aiOptional() ? deviceChoice() : {};
}

/**
 * After a sign-up that couldn't carry it (Apple, Google, the emailed code): an account with no choice yet takes this
 * device's, once (`on` came from the AI page or the card's Try it, `off` from the card's Not for me).
 */
export async function syncDeviceAi() {
    const me = account.me;
    const choice = deviceChoice();
    if (!aiOptional() || !me || me.aiFeatures !== null || !('aiFeatures' in choice)) return;
    const { api } = await import('./api.js');
    await api.account.update(choice);
    account.me = { ...account.me, aiFeatures: choice.aiFeatures, aiFeaturesSource: choice.aiFeaturesSource ?? 'settings' };
}

/** The band the counts carry: the account's stored one, `unknown` without one, `guest` signed out. */
export const aiBand = (me = account.me) => (me ? me.ageBand || 'unknown' : 'guest');

const FIRST = 'stackapella.aiFirstShown'; // who (an account's id, or `guest`) has had AI's first showing on this device

const firstKey = () => (account.me ? String(account.me.id ?? 'account') : 'guest');

/** Whether AI's first showing has already been seen here, by this account (or this guest). */
function firstShown() {
    void device.changed;
    try {
        return (JSON.parse(local.get(FIRST) || '[]') ?? []).includes(firstKey());
    } catch (error) {
        console.info('The first AI showing record was unreadable', error?.message ?? error);
        return false;
    }
}

/** AI's first showing has been seen (an entry point offered its dismiss): no entry point offers it again; Settings does. */
export function noteFirstShown() {
    let seen = [];
    try {
        seen = JSON.parse(local.get(FIRST) || '[]') ?? [];
    } catch (error) {
        console.info('The first AI showing record was unreadable', error?.message ?? error);
    }
    if (seen.includes(firstKey())) return;
    local.set(FIRST, JSON.stringify([...seen, firstKey()].slice(-20)));
    device.changed++;
}

/**
 * Whether an AI entry point offers its quiet dismiss (Hillel, 2026-11-26: "When we first show a user the AI they can
 * dismiss it. After that the option should only be in profile settings."): the switch on, AI on here, no choice made
 * yet, and AI's first showing not yet seen here. The entry point showing it keeps it for that showing (components/ai-hide.js).
 */
export const aiHideOffered = () => aiOptional() && aiOn() && !aiChosen() && !firstShown();

/**
 * Hide AI features, for good (Hillel, 2026-11-26): the account's choice (source `hide`) or, for a guest, this device's,
 * carried into an account they make. `where` names the entry point for the count (`ai_hide:<where>:<band>`).
 */
export async function hideAi(where) {
    count(`ai_hide:${where}:${aiBand()}`);
    if (account.me) {
        const { api } = await import('./api.js');
        await api.account.update({ aiFeatures: false, aiFeaturesSource: 'hide' });
        account.me = { ...account.me, aiFeatures: false, aiFeaturesSource: 'hide' };
    } else {
        local.set(AI_KEY, 'off');
        local.set(FROM, 'hide');
        device.changed++;
    }
}

const USE = 'stackapella.aiUse'; // { kind: 'YYYY-MM-DD' }: the day each AI use was last counted on this device

/**
 * AI used (docs/ai-optional-plan.md, "Measuring"): `ai_use:<kind>:<band>`, once a day each on this device, behind the
 * ai_optional switch. Kinds: `choir_start` (a choir song asked for), `choir_heard` (one played to its end), `guide_heard`
 * (an AI guide heard in Sing or a lesson). A daily total, never who.
 */
export function aiUse(kind, at = Date.now()) {
    if (!aiOptional()) return;
    const today = new Date(at - new Date(at).getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    let kept = {};
    try {
        kept = JSON.parse(local.get(USE) || '{}') ?? {};
    } catch (error) {
        console.info('The AI use record was unreadable', error?.message ?? error);
    }
    if (kept[kind] === today) return;
    local.set(USE, JSON.stringify({ ...kept, [kind]: today }));
    count(`ai_use:${kind}:${aiBand()}`);
}
