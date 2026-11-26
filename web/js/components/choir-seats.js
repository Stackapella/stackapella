// Choir seats, the app's side (docs/choir-seats-plan.md; contract/api.md "Choir seats"; behind `choir_seats`,
// auth.config's `choirSeats`). Every singer in a choir group needs a seat to practise its songs; the director buys
// them, yearly, by the seat, from `min` (10). Here: the director's Seats card on the group page (buy, more or fewer,
// what's used, the payment's state), a member's seat tag and Give a seat for the director, and the price line
// before Start a group. Prices only ever come from billing.prices' `seats`, yearly only: no trial, founding rate or
// monthly price is said (Hillel's open questions), and a trial's date shows only if the server sends one.

import { api } from '../api.js';
import { count } from '../usage.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { account } from '../stores/account.js';
import { guessCurrency, loadPrices, plan, portal, stripe } from '../stores/plan.js';
import { Icon } from './icon.js';
import { Modal, toast } from './ui.js';

/** The most seats a group may have (its member cap). */
export const MOST_SEATS = 60;
const PAID = ['trialing', 'active', 'past_due', 'canceled'];
const REFUSED = {
    email_unverified: tk('groups.seats.refused.emailUnverified'),
    paused: tk('groups.seats.refused.paused'),
    rate_limited: tk('groups.seats.refused.rateLimited'),
    director_only: tk('groups.refused.directorOnly'),
    not_found: tk('groups.refused.notFound'),
    offline: tk('common.offline'),
};
const refusal = (error) => t(REFUSED[error?.code] ?? tk('groups.refused.other'));

/** Whether choir seats are on here. */
export const seatsOn = () => !!account.config?.choirSeats;

/** The seat price in the person's currency (a subscriber's own, else the region's): `{ currency, cents }`, or null before the prices load. */
export function seatPrice(prices = plan.prices) {
    const seats = prices?.seats;
    if (!seats?.prices?.length) return null;
    const currency = plan.billing?.subscription?.currency ?? guessCurrency();
    const price = seats.prices.find((p) => p.currency === currency) ?? seats.prices.find((p) => p.currency === 'usd') ?? seats.prices[0];
    return { currency: price.currency, cents: price.amountCents, min: seats.min ?? 10 };
}

/** "$15", or "$13.50": whole amounts without the cents. */
const money = (cents, currency) => formatNumber(cents / 100, { style: 'currency', currency: currency.toUpperCase(), ...(cents % 100 ? {} : { minimumFractionDigits: 0, maximumFractionDigits: 0 }) });

/** "$15 a singer a year, from 10 seats", or '' before the prices load. */
export function seatPriceLine(prices = plan.prices) {
    const price = seatPrice(prices);
    return price ? t('groups.seats.price', { price: money(price.cents, price.currency), min: price.min }) : '';
}

/** A seats number kept within the pack's bounds. */
export const clampSeats = (n, min = 10) => Math.min(MOST_SEATS, Math.max(min, Math.round(Number(n) || min)));

/** Seats to suggest: one for each singer who'd need one (not the director, not those on their own Plus), at least the minimum. */
export const seatsToSuggest = (members, min = 10) => clampSeats((members ?? []).filter((m) => m.role !== 'director' && m.seat !== 'own').length, min);

/** A seat count with − and + beside it, 44 px each, the number typed too. */
const SeatCount = {
    components: { Icon },
    props: { modelValue: { type: Number, required: true }, min: { type: Number, default: 10 }, label: { type: String, required: true } },
    emits: ['update:modelValue'],
    data: () => ({ uid: 'seats-' + Math.random().toString(36).slice(2, 8) }),
    template: `
        <div class="space-y-1.5">
            <label :for="uid" class="label">{{ label }}</label>
            <div class="flex items-center gap-2">
                <button type="button" class="btn-secondary grid size-11 shrink-0 place-items-center p-0" :disabled="modelValue <= min" :aria-label="t('groups.seats.fewer')" data-seats-fewer @click="set(modelValue - 1)"><Icon name="minus" :size="18" /></button>
                <input :id="uid" type="number" inputmode="numeric" class="input w-20 text-center tabular-nums" :min="min" :max="MOST_SEATS" :value="modelValue" data-seats-count @change="set($event.target.value)" />
                <button type="button" class="btn-secondary grid size-11 shrink-0 place-items-center p-0" :disabled="modelValue >= MOST_SEATS" :aria-label="t('groups.seats.more')" data-seats-more @click="set(modelValue + 1)"><Icon name="plus" :size="18" /></button>
            </div>
        </div>`,
    setup: () => ({ MOST_SEATS }),
    methods: {
        set(n) {
            const next = clampSeats(n, this.min);
            this.$emit('update:modelValue', next);
            this.$el.querySelector('input').value = next; // a typed number out of bounds shows the kept one
        },
    },
};

/**
 * The director's Seats card (the group page, above Singers): one state at a time and one way on. `group` is
 * groups.show's; `updated` gives back a group the server answered with (a change or a move).
 */
export const SeatsPanel = {
    components: { Icon, Modal, SeatCount },
    props: { group: { type: Object, required: true } },
    emits: ['updated', 'reload'],
    data: () => ({ count: 10, buying: false, error: null, consent: false, settingUp: false, changing: false, changeTo: 10, saving: false, changeError: null }),
    template: `
        <section class="space-y-3" aria-labelledby="group-seats" data-seats :data-seats-status="status">
            <h2 id="group-seats" class="t-section">{{ t('groups.seats.title') }}</h2>
            <div class="card space-y-4 p-5">
                <p v-if="settingUp" class="text-cream" role="status" data-seats-setting-up>{{ t('groups.seats.settingUp') }}</p>
                <!-- None bought yet, or lapsed: what seats are for, the price, how many, and Buy. -->
                <template v-else-if="status === 'none' || status === 'lapsed'">
                    <p class="text-cream" data-seats-why>{{ status === 'lapsed' ? t('groups.seats.lapsed') : t('groups.seats.none') }}</p>
                    <p v-if="priceLine" class="text-sm text-muted" data-seats-price>{{ priceLine }}</p>
                    <form class="space-y-4" @submit.prevent="buy">
                        <SeatCount v-model="count" :min="seats.min ?? 10" :label="t('groups.seats.howMany')" />
                        <p v-if="total" class="font-semibold text-cream tabular-nums" data-seats-total>{{ total }}</p>
                        <!-- In the EU, EEA and UK, consent to start at once (the server's versioned words, billing.prices). -->
                        <label v-if="consentNeeded" class="check-row" data-seats-consent><input v-model="consent" type="checkbox" class="checkbox" /><span class="text-sm">{{ consentWords }}</span></label>
                        <p v-if="error" class="field-error" role="alert">{{ error }}</p>
                        <button type="submit" class="btn-secondary w-full justify-center sm:w-auto" :disabled="buying || !price || (consentNeeded && !consent)" data-seats-buy>{{ status === 'lapsed' ? t('groups.seats.buyAgain') : t('groups.seats.buy') }}</button>
                    </form>
                </template>
                <!-- Paid for: how many are used, and the way to more or fewer. -->
                <template v-else>
                    <div class="space-y-2">
                        <p class="flex flex-wrap items-baseline justify-between gap-x-3 text-cream"><span class="font-semibold tabular-nums" data-seats-used>{{ t('groups.seats.used', { used: seats.used, bought: seats.bought }) }}</span><span v-if="seats.trialEndsAt" class="text-sm text-muted">{{ t('groups.seats.trialEnds', { date: day(seats.trialEndsAt) }) }}</span></p>
                        <div class="h-2 overflow-hidden rounded-full bg-raised" aria-hidden="true"><div class="h-full rounded-full bg-good" :style="{ width: Math.min(100, (seats.used / Math.max(1, seats.bought)) * 100) + '%' }"></div></div>
                        <p v-if="waiting" class="text-sm text-warn" data-seats-waiting>{{ t('groups.seats.waiting', { count: waiting }) }}</p>
                        <p v-else-if="seats.bought > seats.used" class="text-sm text-muted" data-seats-free>{{ t('groups.seats.free', { count: seats.bought - seats.used }) }}</p>
                    </div>
                    <p v-if="status === 'past_due'" class="alert-inline text-sm" style="--alert-c: var(--color-warn)" role="status" data-seats-past-due><span>{{ t('groups.seats.pastDue') }}</span></p>
                    <p v-else-if="status === 'canceled'" class="text-sm text-cream" data-seats-canceled>{{ t('groups.seats.canceled', { date: day(seats.endsAt) }) }}</p>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                        <button v-if="status === 'past_due'" type="button" class="btn-secondary btn-sm" data-seats-portal @click="openPortal">{{ t('groups.seats.updatePayment') }}</button>
                        <button v-else-if="status === 'canceled'" type="button" class="btn-secondary btn-sm" data-seats-portal @click="openPortal">{{ t('groups.seats.keep') }}</button>
                        <button v-else type="button" class="btn-secondary btn-sm" data-seats-change @click="openChange">{{ t('groups.seats.change') }}</button>
                        <button v-if="status !== 'past_due' && status !== 'canceled'" type="button" class="link inline-flex min-h-11 items-center text-sm" data-seats-billing @click="openPortal">{{ t('groups.seats.billing') }}</button>
                    </div>
                </template>
            </div>
            <Modal v-if="changing" :title="t('groups.seats.change')" @close="changing = false">
                <form class="space-y-4" data-seats-change-form @submit.prevent="saveChange">
                    <p class="text-muted">{{ t('groups.seats.changeHint') }}</p>
                    <SeatCount v-model="changeTo" :min="seats.min ?? 10" :label="t('groups.seats.howMany')" />
                    <p v-if="changeTotal" class="font-semibold text-cream tabular-nums">{{ changeTotal }}</p>
                    <p v-if="changeTo < seats.used" class="text-sm text-warn" data-seats-drop>{{ t('groups.seats.drop', { count: seats.used - changeTo }) }}</p>
                    <p v-if="changeError" class="field-error" role="alert">{{ changeError }}</p>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="btn-secondary" @click="changing = false">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary" :disabled="saving || changeTo === seats.bought" data-seats-save>{{ t('common.save') }}</button>
                    </div>
                </form>
            </Modal>
        </section>`,
    computed: {
        seats() {
            return this.group.seats ?? { status: 'none', bought: 0, used: 0, min: 10 };
        },
        status() {
            return this.seats.status;
        },
        price: () => seatPrice(),
        priceLine: () => seatPriceLine(),
        /** Singers who'd sing with a seat and haven't one (the seats are all taken). */
        waiting() {
            return (this.group.members ?? []).filter((m) => m.role !== 'director' && m.seat === 'needed').length;
        },
        total() {
            return this.price ? t('groups.seats.total', { count: this.count, total: money(this.count * this.price.cents, this.price.currency) }) : '';
        },
        changeTotal() {
            return this.price ? t('groups.seats.total', { count: this.changeTo, total: money(this.changeTo * this.price.cents, this.price.currency) }) : '';
        },
        consentNeeded: () => !!plan.prices?.startNowConsent,
        /** The words for starting at once: the server's (versioned, billing.prices), else the same approved words here, as Plus's page. */
        consentWords: () => plan.prices?.startNowWords?.text || t('plus.startNow'),
    },
    async created() {
        this.count = seatsToSuggest(this.group.members, this.seats.min ?? 10);
        loadPrices().catch((error) => console.warn('The seat price did not load', error));
        const asked = new URLSearchParams(location.search);
        const back = asked.get('seats');
        if (back) history.replaceState(history.state, '', location.pathname);
        if (back === 'bought' && !PAID.includes(this.status)) await this.waitForSeats();
    },
    watch: {
        // The offer, seen: counted once a page (anonymous, daily).
        status: {
            immediate: true,
            handler(status) {
                if (status === 'none' || status === 'lapsed') count('seats_offer_shown');
            },
        },
    },
    methods: {
        day: (iso) => (iso ? formatDate(iso, { day: 'numeric', month: 'long', year: 'numeric' }) : ''),
        async buy() {
            count('seats_offer_tap');
            this.buying = true;
            this.error = null;
            try {
                const { data } = await api.groups.seats.checkout(this.group.id, { seats: this.count, currency: this.price.currency, startNowConsent: this.consentNeeded ? this.consent : false });
                stripe.open(data.url);
            } catch (error) {
                if (error?.code === 'seats_active') this.$emit('reload');
                else this.error = error?.code === 'validation_failed' ? (error.errors?.seats?.[0] ?? error.errors?.startNowConsent?.[0] ?? refusal(error)) : refusal(error);
            } finally {
                this.buying = false;
            }
        },
        /** Back from Checkout: the seats appear once Stripe's webhook lands, so the group is read again a few times. */
        async waitForSeats() {
            this.settingUp = true;
            try {
                for (let i = 0; i < 6; i++) {
                    await new Promise((r) => setTimeout(r, i ? 2000 : 800));
                    const { data } = await api.groups.show(this.group.id);
                    this.$emit('updated', data);
                    if (PAID.includes(data.seats?.status)) {
                        toast(t('groups.seats.ready'));
                        return;
                    }
                }
            } catch (error) {
                console.warn('The seats did not load after Checkout', error);
            } finally {
                this.settingUp = false;
            }
        },
        openChange() {
            this.changeTo = this.seats.bought;
            this.changeError = null;
            this.changing = true;
        },
        async saveChange() {
            this.saving = true;
            this.changeError = null;
            try {
                const { data } = await api.groups.seats.update(this.group.id, { seats: this.changeTo });
                this.$emit('updated', data);
                this.changing = false;
                toast(t('groups.seats.changed', { count: this.changeTo }));
            } catch (error) {
                if (error?.code === 'no_subscription') this.$emit('reload');
                this.changeError = error?.code === 'validation_failed' ? (error.errors?.seats?.[0] ?? refusal(error)) : refusal(error);
            } finally {
                this.saving = false;
            }
        },
        async openPortal() {
            try {
                await portal();
            } catch (error) {
                toast(refusal(error), { tone: 'error' });
            }
        },
    },
};

/** A member's seat, for the director's list: their own Plus, a seat, or needs one. */
export const SEAT_TAGS = {
    own: { label: tk('groups.seats.tag.own'), tone: 'text-muted' },
    seated: { label: tk('groups.seats.tag.seated'), tone: 'text-good' },
    needed: { label: tk('groups.seats.tag.needed'), tone: 'text-warn' },
};

/**
 * Give a seat: the director picks whose seat goes to `member` (a singer who needs one). Seats move at most 3 times in 30
 * days (the server's 429 says until when); never an error for that, a plain date.
 */
export const GiveSeat = {
    components: { Modal },
    props: { group: { type: Object, required: true }, member: { type: Object, required: true } },
    emits: ['close', 'updated'],
    data: () => ({ from: '', busy: false, error: null, waitUntil: null }),
    template: `
        <Modal :title="t('groups.seats.giveTitle', { name: member.name })" @close="$emit('close')">
            <p v-if="waitUntil" class="text-cream" role="status" data-seats-move-wait>{{ t('groups.seats.moveWait', { date: waitUntil }) }}</p>
            <form v-else class="space-y-4" data-seats-give-form @submit.prevent="move">
                <fieldset class="space-y-2">
                    <legend class="label">{{ t('groups.seats.giveFrom') }}</legend>
                    <label v-for="m in holders" :key="m.id" class="check-row" :data-seats-holder="m.id"><input v-model="from" type="radio" name="seat-from" class="radio" :value="m.id" /><span translate="no">{{ m.name }}</span></label>
                </fieldset>
                <p class="text-sm text-muted">{{ t('groups.seats.giveHint') }}</p>
                <p v-if="error" class="field-error" role="alert">{{ error }}</p>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn-primary" :disabled="busy || !from" data-seats-move>{{ t('groups.seats.giveGo') }}</button>
                </div>
            </form>
            <div v-if="waitUntil" class="mt-5 flex justify-end"><button type="button" class="btn-secondary" @click="$emit('close')">{{ t('common.close') }}</button></div>
        </Modal>`,
    computed: {
        holders() {
            return (this.group.members ?? []).filter((m) => m.role !== 'director' && m.seat === 'seated');
        },
    },
    methods: {
        async move() {
            this.busy = true;
            this.error = null;
            try {
                const { data } = await api.groups.seats.move(this.group.id, { from: this.from, to: this.member.id });
                this.$emit('updated', data);
                toast(t('groups.seats.moved', { name: this.member.name }));
                this.$emit('close');
            } catch (error) {
                if (error?.code === 'rate_limited' && error.retryAfter) this.waitUntil = formatDate(new Date(Date.now() + error.retryAfter * 1000).toISOString(), { day: 'numeric', month: 'long' });
                else this.error = error?.code === 'validation_failed' ? (error.errors?.to?.[0] ?? error.errors?.from?.[0] ?? refusal(error)) : refusal(error);
            } finally {
                this.busy = false;
            }
        },
    },
};
