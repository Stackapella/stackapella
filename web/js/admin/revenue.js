// Revenue (docs/admin-analytics-plan.md, slice 2), under Growth: MRR and ARR with the change on 30
// days ago, subscribers by state, store and plan, 12 months of MRR with new and lost subscribers, and
// one-off income (coaching, packs). From our own tables (admin.revenue.show). Every amount stays in
// its own currency, never converted; store subscriptions without a known price are a count, never
// money. Small counts are said as counts, never as percentages. Plain bars, no charts library.

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { LoadFailed } from './common.js';

const STATES = { paying: tk('admin.revenue.state.paying'), trialing: tk('admin.revenue.state.trialing'), pastDue: tk('admin.revenue.state.pastDue'), cancelling: tk('admin.revenue.state.cancelling') };
const STORES = { web: tk('admin.revenue.store.web'), apple: tk('admin.revenue.store.apple'), google: tk('admin.revenue.store.google') };
const PLANS = { month: tk('admin.revenue.plan.month'), year: tk('admin.revenue.plan.year'), unknown: tk('admin.revenue.plan.unknown') };
const KINDS = { coaching: tk('admin.revenue.kind.coaching'), packs: tk('admin.revenue.kind.packs') };

/** Cents in a currency ('usd'), as money: "$46.58". */
export const money = (cents, currency) => formatNumber((cents ?? 0) / 100, { style: 'currency', currency: currency.toUpperCase() });

/** One currency's amount in a list, 0 when it isn't there. */
const centsIn = (list, currency) => list?.find((m) => m.currency === currency)?.cents ?? 0;

/** Each currency's MRR against 30 days ago: [{ currency, now, before, diff }], the currencies of either. */
export function mrrChanges(mrr = [], monthAgo = []) {
    const currencies = [...new Set([...mrr, ...monthAgo].map((m) => m.currency))].sort();
    return currencies.map((currency) => {
        const now = centsIn(mrr, currency);
        const before = centsIn(monthAgo, currency);
        return { currency, now, before, diff: now - before };
    });
}

/** Subscriber rows summed by one field ('state', 'store' or 'plan'), in `order`'s order, empty ones left out. */
export function countsBy(rows = [], field, order) {
    const totals = {};
    for (const row of rows) totals[row[field]] = (totals[row[field]] ?? 0) + row.count;
    return Object.keys(order).filter((key) => totals[key]).map((key) => ({ key, count: totals[key] }));
}

export const RevenueSection = {
    components: { LoadFailed },
    props: { me: { type: Object, required: true } },
    data: () => ({ report: null, failed: false, currency: null, churn: null }),
    setup: () => ({ STATES, STORES, PLANS, KINDS, formatNumber, money }),
    template: `
        <section class="space-y-6" aria-labelledby="revenue-h" data-revenue>
            <div>
                <h1 id="revenue-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.revenue.title') }}</h1>
                <p class="mt-1 text-sm text-muted">{{ t('admin.revenue.intro') }}</p>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!report" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <ul class="grid gap-3 sm:grid-cols-2">
                    <li class="card flex flex-col p-5" data-tile="mrr">
                        <p class="text-sm text-muted">{{ t('admin.revenue.mrr') }}</p>
                        <p v-if="!changes.length" class="mt-2 text-4xl font-extrabold tabular-nums text-cream">{{ money(0, 'usd') }}</p>
                        <p v-for="c in changes" :key="c.currency" class="mt-2 text-4xl font-extrabold tabular-nums text-cream" :data-currency="c.currency">{{ money(c.now, c.currency) }}</p>
                        <p v-if="report.unpriced" class="mt-1 text-sm text-muted" data-unpriced>{{ t('admin.revenue.unpriced', { count: report.unpriced }) }}</p>
                        <div v-if="changes.length" class="mt-auto pt-4"><dl class="space-y-1 border-t border-line pt-3 text-sm">
                            <div v-for="c in changes" :key="c.currency" class="flex items-center justify-between gap-3" data-change :data-dir="dir(c.diff)">
                                <dt class="text-muted">{{ t('admin.revenue.vsMonthAgo', { currency: c.currency.toUpperCase() }) }}</dt>
                                <dd class="font-semibold tabular-nums" :class="{ up: 'text-good', down: 'text-bad', same: 'text-muted' }[dir(c.diff)]"><span v-if="c.diff" class="me-1" aria-hidden="true">{{ dir(c.diff) === 'up' ? '↑' : '↓' }}</span>{{ moneyChange(c) }}</dd>
                            </div>
                        </dl></div>
                    </li>
                    <li class="card flex flex-col p-5" data-tile="arr">
                        <p class="text-sm text-muted">{{ t('admin.revenue.arr') }}</p>
                        <p v-if="!report.arr.length" class="mt-2 text-4xl font-extrabold tabular-nums text-cream">{{ money(0, 'usd') }}</p>
                        <p v-for="a in report.arr" :key="a.currency" class="mt-2 text-4xl font-extrabold tabular-nums text-cream" :data-currency="a.currency">{{ money(a.cents, a.currency) }}</p>
                        <p class="mt-auto pt-4 text-sm text-muted">{{ t('admin.revenue.arrHow') }}</p>
                    </li>
                </ul>

                <section class="card p-5 md:p-6" aria-labelledby="revenue-subs-h" data-subscribers>
                    <h2 id="revenue-subs-h" class="text-lg font-bold text-cream">{{ t('admin.revenue.subscribers', { count: subscriberTotal }) }}</h2>
                    <div class="mt-4 grid gap-6 sm:grid-cols-3">
                        <div v-for="g in groups" :key="g.field">
                            <h3 class="eyebrow text-muted">{{ t(g.title) }}</h3>
                            <ul class="mt-2 space-y-1.5 text-sm">
                                <li v-for="row in g.rows" :key="row.key" class="flex items-center justify-between gap-3" :data-by="g.field + ':' + row.key">
                                    <span class="text-cream">{{ t(g.words[row.key]) }}</span><span class="font-semibold tabular-nums text-cream">{{ formatNumber(row.count) }}</span>
                                </li>
                                <li v-if="!g.rows.length" class="text-muted">{{ t('admin.revenue.none') }}</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <section class="card min-w-0 p-5 md:p-6" aria-labelledby="revenue-months-h" data-months>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 id="revenue-months-h" class="text-lg font-bold text-cream">{{ t('admin.revenue.months') }}</h2>
                            <p class="text-sm text-muted">{{ t('admin.revenue.monthsSub') }}</p>
                        </div>
                        <div v-if="currencies.length > 1" class="seg flex" role="radiogroup" :aria-label="t('admin.revenue.currency')">
                            <button v-for="c in currencies" :key="c" type="button" role="radio" :aria-checked="shownCurrency === c" class="seg-option" @click="currency = c">{{ c.toUpperCase() }}</button>
                        </div>
                    </div>
                    <ol class="mt-5 grid h-36 grid-cols-12 items-end gap-1.5 sm:gap-2" :aria-label="t('admin.revenue.months')">
                        <li v-for="m in report.months" :key="m.month" class="flex h-full items-end" :data-month="m.month" :title="monthLabel(m)">
                            <span class="sr-only">{{ monthLabel(m) }}</span>
                            <i class="block w-full rounded-t-sm bg-accent" :style="{ height: bar(m) + '%' }" aria-hidden="true"></i>
                        </li>
                    </ol>
                    <ol class="mt-1 grid grid-cols-12 gap-1.5 text-center text-xs tabular-nums sm:gap-2" aria-hidden="true">
                        <li v-for="m in report.months" :key="m.month"><span class="block text-good">{{ m.new ? '+' + formatNumber(m.new) : '' }}</span><span class="block text-bad">{{ m.lost ? '-' + formatNumber(m.lost) : '' }}</span></li>
                    </ol>
                    <div class="mt-2 flex justify-between text-xs text-muted" aria-hidden="true"><span>{{ monthName(report.months[0]?.month) }}</span><span>{{ monthName(report.months.at(-1)?.month) }}</span></div>
                    <p class="mt-3 text-xs text-muted">{{ t('admin.revenue.monthsKey') }}</p>
                </section>

                <!-- Churn and trials (slice 4): who left each month, by store, and trials that ended or converted. -->
                <section v-if="churn" class="card min-w-0 p-5 md:p-6" aria-labelledby="revenue-churn-h" data-churn>
                    <h2 id="revenue-churn-h" class="text-lg font-bold text-cream">{{ t('admin.revenue.churn') }}</h2>
                    <p class="text-sm text-muted">{{ t('admin.revenue.churnSub') }}</p>
                    <div class="-mx-1 mt-4 overflow-x-auto px-1">
                        <table class="w-full text-sm">
                            <thead class="text-xs font-semibold uppercase tracking-wider text-muted">
                                <tr>
                                    <th scope="col" class="pb-2 text-start font-semibold">{{ t('admin.revenue.col.month') }}</th>
                                    <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.revenue.col.payingAtStart') }}</th>
                                    <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.revenue.col.lost') }}</th>
                                    <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.revenue.col.trials') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in churnRows" :key="m.month" class="border-t border-line" :data-churn-month="m.month">
                                    <th scope="row" class="py-2 text-start font-semibold text-cream">{{ monthName(m.month) }}</th>
                                    <td class="py-2 ps-3 text-end tabular-nums text-cream">{{ formatNumber(m.payingAtStart) }}</td>
                                    <td class="py-2 ps-3 text-end tabular-nums text-cream" :title="lostWords(m)">{{ formatNumber(lostOf(m)) }}<span v-if="lostOf(m)" class="block text-xs text-muted">{{ lostWords(m) }}</span></td>
                                    <td class="py-2 ps-3 text-end tabular-nums text-cream">{{ t('admin.revenue.trialsLine', { converted: formatNumber(m.trialsConverted), ended: formatNumber(m.trialsEnded) }) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-xs text-muted">{{ t('admin.revenue.churnKey') }}</p>
                </section>

                <section class="card min-w-0 p-5 md:p-6" aria-labelledby="revenue-oneoff-h" data-one-off>
                    <h2 id="revenue-oneoff-h" class="text-lg font-bold text-cream">{{ t('admin.revenue.oneOff') }}</h2>
                    <p v-if="!report.oneOff.length" class="mt-3 text-sm text-muted">{{ t('admin.revenue.noOneOff') }}</p>
                    <div v-else class="-mx-1 mt-4 overflow-x-auto px-1">
                        <table class="w-full text-sm">
                            <thead class="text-xs font-semibold uppercase tracking-wider text-muted">
                                <tr>
                                    <th scope="col" class="pb-2 text-start font-semibold">{{ t('admin.revenue.col.month') }}</th>
                                    <th scope="col" class="pb-2 ps-3 text-start font-semibold">{{ t('admin.revenue.col.kind') }}</th>
                                    <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.revenue.col.payments') }}</th>
                                    <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.revenue.col.gross') }}</th>
                                    <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.revenue.col.ours') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in report.oneOff" :key="row.month + row.kind + row.currency" class="border-t border-line" :data-one-off-row="row.kind">
                                    <td class="py-2 text-cream">{{ monthName(row.month) }}</td>
                                    <td class="py-2 ps-3 text-cream">{{ t(KINDS[row.kind]) }}</td>
                                    <td class="py-2 ps-3 text-end tabular-nums text-cream">{{ formatNumber(row.payments) }}</td>
                                    <td class="py-2 ps-3 text-end tabular-nums text-cream">{{ money(row.gross, row.currency) }}</td>
                                    <td class="py-2 ps-3 text-end tabular-nums text-cream">{{ money(row.ours, row.currency) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </section>`,
    computed: {
        /** Newest first. */
        churnRows() {
            return [...(this.churn?.months ?? [])].reverse();
        },
        changes() {
            return mrrChanges(this.report?.mrr, this.report?.monthAgo);
        },
        subscriberTotal() {
            return (this.report?.subscribers ?? []).reduce((sum, row) => sum + row.count, 0);
        },
        groups() {
            const rows = this.report?.subscribers ?? [];
            return [
                { field: 'state', title: tk('admin.revenue.byState'), words: STATES, rows: countsBy(rows, 'state', STATES) },
                { field: 'store', title: tk('admin.revenue.byStore'), words: STORES, rows: countsBy(rows, 'store', STORES) },
                { field: 'plan', title: tk('admin.revenue.byPlan'), words: PLANS, rows: countsBy(rows, 'plan', PLANS) },
            ];
        },
        currencies() {
            return [...new Set((this.report?.months ?? []).flatMap((m) => m.mrr.map((x) => x.currency)))].sort();
        },
        shownCurrency() {
            return this.currencies.includes(this.currency) ? this.currency : this.currencies[0] ?? null;
        },
        most() {
            return Math.max(1, ...(this.report?.months ?? []).map((m) => centsIn(m.mrr, this.shownCurrency)));
        },
    },
    created() {
        this.load();
    },
    methods: {
        /** "+$23.29", "-£4.49", or the same. */
        moneyChange(c) {
            if (!c.diff) return t('admin.growth.same');
            return (c.diff > 0 ? '+' : '-') + money(Math.abs(c.diff), c.currency);
        },
        lostOf: (m) => (m.lost?.web ?? 0) + (m.lost?.apple ?? 0) + (m.lost?.google ?? 0),
        /** "2 web, 1 App Store" (the stores that lost someone). */
        lostWords(m) {
            return Object.entries(m.lost ?? {}).filter(([, n]) => n).map(([store, n]) => `${formatNumber(n)} ${t(STORES[store] ?? STORES.web)}`).join(', ');
        },
        dir(diff) {
            return diff > 0 ? 'up' : diff < 0 ? 'down' : 'same';
        },
        bar(m) {
            const cents = centsIn(m.mrr, this.shownCurrency);
            return cents ? Math.max(2, Math.round((cents / this.most) * 100)) : 0;
        },
        monthName(month) {
            return month ? formatDate(`${month}-15T12:00:00Z`, { month: 'short', year: 'numeric' }) : '';
        },
        /** "Oct 2026: MRR $46.58, 10 new, 1 lost (+ 2 store subscriptions, price not known)". */
        monthLabel(m) {
            const mrr = this.shownCurrency ? money(centsIn(m.mrr, this.shownCurrency), this.shownCurrency) : '-';
            const line = t('admin.revenue.monthLabel', { month: this.monthName(m.month), mrr, new: formatNumber(m.new), lost: formatNumber(m.lost) });
            return m.unpriced ? `${line} ${t('admin.revenue.unpriced', { count: m.unpriced })}` : line;
        },
        async load() {
            this.failed = false;
            try {
                this.report = (await api.admin.revenue.show()).data;
                // Churn and trials, beside it: a page without it still shows the money.
                api.admin.churn.show().then(({ data }) => (this.churn = data), (error) => console.warn('Churn did not load', error));
            } catch (error) {
                console.warn('Revenue did not load', error);
                this.failed = t('admin.revenue.failed');
            }
        },
    },
};
