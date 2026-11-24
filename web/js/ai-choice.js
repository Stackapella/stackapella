// AI optional (docs/ai-optional-plan.md, revised and approved 2026-11-23): every AI feature behind one choice. The one
// source of truth every hide reads: an account's own choice (account.show `aiFeatures`: true or false; null, not chosen,
// acts as off, since the server set every account made before this to true), else this device's key (`on` after an
// arrival through /ai-harmony-singing, `off` after Not for me, absent as off). Off: AI guides give way to the practice voice
// (ai-voices.js allowed), Hear yourself as a choir and Lend your voice are hidden (own-choir-state.js, views/voices.js).
// Real voices are never touched. The way in is one card after a recorded part (cardAllowed below; components/ai-card.js).
// Small and first-screen safe.

import { account } from './stores/account.js';
import { local } from './storage.js';
import { count } from './usage.js';

export const AI_KEY = 'stackapella.ai';
const FROM = 'stackapella.aiFrom'; // where the device's choice came from: 'card' or 'ai-singing' (the account's source names; the page is /ai-harmony-singing)
const CARD = 'stackapella.aiCard'; // how many times the card has shown on this device (closing counts; twice, then never)
export const CARD_MOST = 2;

/** Whether the choice exists yet: the ai_optional release switch (auth.config `aiOptional`), known to be true. */
export function aiOptional() {
    return account.config?.aiOptional === true;
}

/** This device's key: 'on', 'off' or null (not chosen). */
export const deviceAi = () => local.get(AI_KEY);

/**
 * Whether AI features are on here. Before the ai_optional switch, on for everyone, exactly as today. After it: the
 * account's choice (not chosen: off), else the device's key.
 */
export function aiOn() {
    if (!aiOptional()) return true;
    const me = account.me;
    if (me) return me.aiFeatures === true;
    return deviceAi() === 'on';
}

/**
 * A real yes to AI (Hillel, 2026-11-24: "the 'Hear yourself as a choir' should be hidden from top of home page until we
 * know if they use AI or not"): the account's AI features on by its own choice (not `existing`, set on for every account
 * made before AI was optional), else a guest's device on. Whatever the release switch says.
 */
export function aiYes() {
    const me = account.me;
    if (me) return me.aiFeatures === true && me.aiFeaturesSource !== 'existing';
    return deviceAi() === 'on';
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
        local.set(AI_KEY, 'on');
        local.set(FROM, 'ai-singing');
    }
}

/** A guest's choice on this device (the card): 'on' or 'off'. */
export function setDeviceAi(on) {
    local.set(AI_KEY, on ? 'on' : 'off');
    local.set(FROM, 'card');
}

/** The device's choice as the account takes it: { aiFeatures, aiFeaturesSource }, or {} with none. */
function deviceChoice() {
    const key = deviceAi();
    if (key !== 'on' && key !== 'off') return {};
    const from = local.get(FROM);
    return { aiFeatures: key === 'on', aiFeaturesSource: from === 'card' || from === 'ai-singing' ? from : key === 'on' ? 'ai-singing' : 'card' };
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

/** Whether today is this device's first day (the funnel's arrival day, js/funnel.js); unknown counts as the first. */
export function firstDay(at = Date.now()) {
    let arrived = null;
    try {
        arrived = JSON.parse(localStorage.getItem('stackapella.funnel') || 'null')?.arrived ?? null;
    } catch (error) {
        console.info('The arrival day was unreadable', error?.message ?? error);
    }
    const today = new Date(at - new Date(at).getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    return !arrived || arrived >= today;
}

/** The choice made on the card: the account's (source `card`, never counted as a switch), or this device's for a guest. */
export async function chooseAi(on) {
    if (account.me) {
        const { api } = await import('./api.js');
        await api.account.update({ aiFeatures: on, aiFeaturesSource: 'card' });
        account.me = { ...account.me, aiFeatures: on, aiFeaturesSource: 'card' };
    } else {
        setDeviceAi(on);
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

export const cardShows = () => Number(local.get(CARD) || 0);
export const noteCardShown = () => local.set(CARD, String(cardShows() + 1));

/**
 * Whether the card may show under a take's review. Pure, for the tests. All must hold: the switch on; the choir's own
 * switch on; no choice yet; not under 18 (a guest is shown it); a song the choir can make; the take the song's first
 * recorded part, and heard (scored: never under "Couldn't hear you clearly"); not on the device's first day; shown fewer than twice; and the ask ledger's slot free (`askFree`).
 */
export function cardAllowed({ optional, choir, chosen, adult, songOk, firstPart, heard, firstDay, shows, askFree }) {
    return !!(optional && choir && !chosen && adult !== false && songOk && firstPart && heard && !firstDay && shows < CARD_MOST && askFree);
}
