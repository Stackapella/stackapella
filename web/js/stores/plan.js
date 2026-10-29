// Plus (docs/pricing-plan.md; contract/api.md "Plus and billing"): the account's plan and
// subscription, the prices, Checkout and the billing portal. The app never sees a card: it opens
// Stripe's page, and reads the plan again when it comes back to the foreground.

import { reactive } from 'vue';
import { api } from '../api.js';
import { local } from '../storage.js';
import { account, loadAccount } from './account.js';
import { FREE_SONGS } from '../templates.js';

export const plan = reactive({ billing: null, prices: null, loaded: false });

const OFFLINE_UNTIL = 'stackapella.plusOfflineUntil';

/** On Plus now: the account says so (a trial counts). */
export function isPlus() {
    return account.me?.plan === 'plus';
}

/** Offline, a Plus song stays usable until this (ms), from the last check; null when unknown. */
export function plusOfflineUntil() {
    const kept = Number(local.get(OFFLINE_UNTIL) || 0);
    return kept || null;
}

/** The account's plan and subscription (billing.show), kept for the offline grace. */
export async function loadBilling() {
    if (!account.me) {
        plan.billing = null;
        plan.loaded = true;
        return null;
    }
    try {
        const { data } = await api.billing.show();
        plan.billing = data;
        if (data.plusOfflineUntil) local.set(OFFLINE_UNTIL, String(Date.parse(data.plusOfflineUntil)));
        // The account's own plan follows (a Checkout just finished, a plan that ended).
        if (account.me && data.plan !== account.me.plan) await loadAccount({ fresh: true });
    } catch (error) {
        if (error?.code !== 'offline') console.warn('The plan was not read', error);
    } finally {
        plan.loaded = true;
    }
    return plan.billing;
}

export async function loadPrices() {
    if (plan.prices) return plan.prices;
    plan.prices = (await api.billing.prices()).data;
    return plan.prices;
}

/** The currency to show first: the person's region's, else dollars (a returning subscriber's is theirs). */
export function guessCurrency(language = navigator.language, zone = Intl.DateTimeFormat().resolvedOptions().timeZone ?? '') {
    const region = (language.split('-')[1] ?? '').toUpperCase();
    if (region === 'GB' || zone === 'Europe/London') return 'gbp';
    const euro = ['AT', 'BE', 'CY', 'DE', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PT', 'SI', 'SK'];
    if (euro.includes(region) || (zone.startsWith('Europe/') && !['Europe/London', 'Europe/Zurich', 'Europe/Oslo', 'Europe/Stockholm', 'Europe/Copenhagen', 'Europe/Warsaw', 'Europe/Prague', 'Europe/Budapest', 'Europe/Bucharest', 'Europe/Sofia', 'Europe/Istanbul', 'Europe/Moscow', 'Europe/Kiev', 'Europe/Kyiv'].includes(zone))) return 'eur';
    return 'usd';
}

/**
 * Starts Checkout from an upgrade `moment` (counted on the server, never in the browser) and opens
 * Stripe's page. Resolves with nothing (the page is left), or throws the refusal.
 */
export async function checkout({ interval, currency, trial, moment }) {
    const { data } = await api.billing.checkout({ interval, currency, trial: !!trial, moment });
    openStripe(data.url);
}

/** Stripe's billing portal: change plan or card, see invoices, cancel. */
export async function portal() {
    const { data } = await api.billing.portal();
    openStripe(data.url);
}

/** In the installed app, Stripe opens in the system browser; in a tab, the tab goes there. (A flow replaces `open`.) */
export const stripe = {
    open(url) {
        if (window.matchMedia?.('(display-mode: standalone)').matches) window.open(url, '_blank', 'noopener');
        else location.assign(url);
    },
};
const openStripe = (url) => stripe.open(url);

let watching = false;
/** Back in the app (after Checkout in another window, say): the plan is read again. */
export function watchPlan() {
    if (watching) return;
    watching = true;
    document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && account.me && loadBilling());
}

// ---- Read-only projects (contract/api.md, "Plus and billing") -------------------------------------

/**
 * Why a project can be played and exported but not changed, or null: past the free plan's limit
 * after a downgrade ('limit'), made from a Plus song without Plus ('plusSong'), or a Plus song
 * offline for longer than the 14 days since the plan was last checked ('offline').
 */
export function lockReason(project, { online = navigator.onLine, now = Date.now(), free = FREE_SONGS } = {}) {
    if (!project || project.beta || project.course) return null;
    if (project.readOnly === 'limit') return 'limit';
    if (project.readOnly === 'plus_song') return 'plusSong';
    const until = plusOfflineUntil();
    if (!online && isPlus() && until && now > until && project.template && !free.includes(project.template.id)) return 'offline';
    return null;
}

// ---- The app-only gates (docs/features.md, "In the app only") -------------------------------------
// The course past unit 7 is the course's data (a unit's `plus`); the workout's count is js/workout.js.

/** MP3 and stems are Plus. */
export function exportNeedsPlus(format) {
    return (format === 'mp3' || format === 'stems') && !isPlus();
}
