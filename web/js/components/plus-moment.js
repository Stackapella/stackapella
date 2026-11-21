// An upgrade moment (docs/plus-plan.md; Hillel's 99.png): when the free plan stops something, a short sheet says what
// happened and the ways on. Teaming up is Plus (docs/pricing-plan.md, top; Hillel, 2026-11-12: "you can learn alone for
// free but to team up becomes more serious"): starting a choir group, inviting a friend onto your song. The primary
// is Get Plus, never a free trial (Hillel, 2026-11-21: "better to charge people from the start"); the Plus page brings
// you back to `next` once Plus is on. Never a
// toast that leaves the person guessing; never in front of a course, song or workout; never asked of someone joining.
// With `price`, it also says what Plus costs (Invite singers, Hillel 2026-11-15).

import { navigate } from '../router.js';
import { formatNumber, t } from '../i18n.js';
import { guessCurrency, loadBilling, loadPrices, plan } from '../stores/plan.js';
import { account, selfHosted } from '../stores/account.js';
import { session } from '../storage.js';
import { Icon } from './icon.js';
import { Modal } from './ui.js';

/** Where the Plus page sends you back to once Plus is on: a path in this app, kept for the tab (Checkout leaves it). */
export const NEXT_KEY = 'stackapella.plus.next';

/** Keeps the way back for the Plus page, only a path in this app ("/groups?start=1"), with the moment it came from. */
export function keepNext(path, from) {
    if (typeof path === 'string' && path.startsWith('/') && !path.startsWith('//')) session.set(NEXT_KEY, JSON.stringify({ path, from }));
}

/** The kept way back, or null. */
export function keptNext() {
    try {
        const kept = JSON.parse(session.get(NEXT_KEY) ?? 'null');
        return kept && typeof kept.path === 'string' && kept.path.startsWith('/') && !kept.path.startsWith('//') ? kept : null;
    } catch (error) {
        console.info('The way back from Plus was unreadable', error?.message ?? error);
        return null;
    }
}

export const PlusMoment = {
    components: { Icon, Modal },
    props: {
        title: { type: String, required: true },
        text: { type: String, required: true },
        from: { type: String, required: true }, // the moment (billing.checkout's `moment`, and the Plus page's ?from=)
        next: { type: String, default: '' }, // where to come back to once Plus is on
        other: { type: String, default: '' }, // a second way out (Manage songs), if there is one
        price: { type: Boolean, default: false }, // say what Plus costs, a year and a month
    },
    emits: ['close', 'other'],
    template: `
        <Modal :title="title" @close="$emit('close')">
            <div class="space-y-5" data-plus-moment :data-moment="from">
                <p class="text-cream/85">{{ text }}</p>
                <p v-if="priceLine" class="font-semibold text-cream" data-plus-price>{{ priceLine }}</p>
                <!-- On a phone the primary takes the width, stacked first; from sm up, side by side at the end (design rules). -->
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
                    <button v-if="other" type="button" class="btn-secondary w-full sm:w-auto" @click="$emit('other')">{{ other }}</button>
                    <button v-else type="button" class="btn-secondary w-full sm:w-auto" @click="$emit('close')">{{ t('common.notNow') }}</button>
                    <button v-if="!selfHosted()" type="button" class="btn-primary w-full sm:w-auto" data-plus-go @click="go">{{ t('plus.subscribe') }}</button>
                </div>
            </div>
        </Modal>`,
    setup: () => ({ selfHosted }),
    computed: {
        /** "Plus is $39.99 a year or $4.99 a month", in the account's currency (else the region's); empty until the prices load. */
        priceLine() {
            if (!this.price || selfHosted()) return '';
            const currency = plan.billing?.subscription?.currency ?? guessCurrency();
            const of = (interval) => plan.prices?.prices?.find((p) => p.interval === interval && p.currency === currency)?.amountCents;
            const [year, month] = [of('year'), of('month')];
            if (!year || !month) return '';
            const money = (cents) => formatNumber(cents / 100, { style: 'currency', currency: currency.toUpperCase() });
            return t('plus.momentPrice', { year: money(year), month: money(month) });
        },
    },
    created() {
        if (selfHosted()) return;
        // The price line's prices, and the account's own currency where it pays already.
        const unread = (error) => console.info('The prices were not read', error?.message ?? error);
        if (this.price) loadPrices().catch(unread);
        if (this.price && account.me && !plan.billing) loadBilling().catch(unread);
    },
    methods: {
        go() {
            if (this.next) keepNext(this.next, this.from);
            navigate('/plus?from=' + encodeURIComponent(this.from));
        },
    },
};
