// Plus (docs/pricing-plan.md; contract/api.md "Plus and billing"): the account's plan and
// subscription, the prices, Checkout and the billing portal. The app never sees a card: it opens
// Stripe's page, and reads the plan again when it comes back to the foreground.

import { reactive } from 'vue';
import { api } from '../api.js';
import { account, loadAccount } from './account.js';
import { teams } from '../teams.js';

export const plan = reactive({ billing: null, prices: null, loaded: false });

/** On Plus now: the account says so (a trial counts). */
export function isPlus() {
    return account.me?.plan === 'plus';
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
 * after a downgrade ('limit'). Every library song is free since 2026-11-07 (Plus is for singing with
 * others), so a song never locks a project any more, online or off.
 */
export function lockReason(project) {
    if (!project || project.beta || project.course) return null;
    // A friend's song paused because its starter's Plus ended (teaming up is Plus, behind plus_teams): listened to and kept,
    // nothing new recorded until it opens again.
    if (project.readOnly === 'plus_ended' && project.shared && teams.on) return 'plus_ended';
    return project.readOnly === 'limit' ? 'limit' : null;
}

// ---- The app-only gates (docs/features.md, "In the app only") -------------------------------------
// Courses, songs and workouts are free (2026-11-06 and 11-07); Plus is singing with others, and the solo extras.

/** MP3 and stems are Plus. */
export function exportNeedsPlus(format) {
    return (format === 'mp3' || format === 'stems') && !isPlus();
}
