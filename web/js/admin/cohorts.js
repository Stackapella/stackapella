// Cohorts (docs/admin-analytics-plan.md, slice 3), under Growth: the people who signed up each month,
// and how many of them went on to finish a song, finish a second, are active in the last 30 days, and
// ever paid for Plus, with the median days to a first finished song (admin.cohorts.show; admins
// excluded). Counts under 20 are said as counts, never as a percentage of so few.

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { FEW, LoadFailed, screen, zoneName } from './common.js';

const COLUMNS = [
    { key: 'finishedSong', label: tk('admin.cohorts.col.finished') },
    { key: 'secondSong', label: tk('admin.cohorts.col.second') },
    { key: 'active', label: tk('admin.cohorts.col.active') },
    { key: 'plus', label: tk('admin.cohorts.col.plus') },
];

/** A cohort's step: "12 (40%)" from 20 signed up, else just "12" (a percentage of so few is noise). */
export function share(count, of) {
    if (!of || of < FEW) return formatNumber(count ?? 0);
    return `${formatNumber(count ?? 0)} (${formatNumber((count ?? 0) / of, { style: 'percent', maximumFractionDigits: 0 })})`;
}

export const CohortsSection = {
    components: { LoadFailed },
    props: { me: { type: Object, required: true } },
    data: () => ({ report: null, failed: false }),
    setup: () => ({ COLUMNS, formatNumber, share }),
    template: `
        <section class="space-y-6" aria-labelledby="cohorts-h" data-cohorts>
            <div>
                <h1 id="cohorts-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.cohorts.title') }}</h1>
                <p class="mt-1 max-w-prose text-sm text-muted">{{ t('admin.cohorts.intro', { zone: zoneName(report?.timezone) }) }}</p>
            </div>
            <LoadFailed v-if="failed" :message="t('admin.cohorts.failed')" @retry="load" />
            <p v-else-if="!report" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <!-- A phone: a card a month, each step under its name (the table needs a wide screen). -->
            <ul v-else-if="phone" class="space-y-3" data-cohort-cards>
                <li v-for="m in rows" :key="m.month" class="card p-4" :data-cohort="m.month">
                    <p class="flex items-baseline justify-between gap-3"><span class="font-bold text-cream">{{ monthName(m.month) }}</span><span class="text-sm text-muted">{{ t('admin.cohorts.col.signedUp') }} <span class="font-semibold tabular-nums text-cream" data-signed-up>{{ formatNumber(m.signedUp) }}</span></span></p>
                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div v-for="c in COLUMNS" :key="c.key" :data-col="c.key"><dt class="text-xs text-muted">{{ t(c.label) }}</dt><dd class="tabular-nums text-cream">{{ share(m[c.key], m.signedUp) }}</dd></div>
                        <div><dt class="text-xs text-muted">{{ t('admin.cohorts.col.days') }}</dt><dd class="tabular-nums text-cream">{{ m.medianDaysToFirstSong == null ? '-' : formatNumber(m.medianDaysToFirstSong, { maximumFractionDigits: 1 }) }}</dd></div>
                    </dl>
                </li>
                <li class="px-1 text-xs text-muted">{{ t('admin.cohorts.key') }}</li>
            </ul>
            <div v-else class="card -mx-1 overflow-x-auto p-5 md:p-6">
                <table class="w-full text-sm">
                    <thead class="text-xs font-semibold uppercase tracking-wider text-muted">
                        <tr>
                            <th scope="col" class="pb-2 text-start font-semibold">{{ t('admin.cohorts.col.month') }}</th>
                            <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.cohorts.col.signedUp') }}</th>
                            <th v-for="c in COLUMNS" :key="c.key" scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t(c.label) }}</th>
                            <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.cohorts.col.days') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in rows" :key="m.month" class="border-t border-line" :data-cohort="m.month">
                            <th scope="row" class="py-2 text-start font-semibold text-cream">{{ monthName(m.month) }}</th>
                            <td class="py-2 ps-3 text-end tabular-nums text-cream" data-signed-up>{{ formatNumber(m.signedUp) }}</td>
                            <td v-for="c in COLUMNS" :key="c.key" class="py-2 ps-3 text-end tabular-nums text-cream" :data-col="c.key">{{ share(m[c.key], m.signedUp) }}</td>
                            <td class="py-2 ps-3 text-end tabular-nums text-cream">{{ m.medianDaysToFirstSong == null ? '-' : formatNumber(m.medianDaysToFirstSong, { maximumFractionDigits: 1 }) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-4 text-xs text-muted">{{ t('admin.cohorts.key') }}</p>
            </div>
        </section>`,
    computed: {
        phone: () => screen.phone,
        /** Newest first, as a table is read. */
        rows() {
            return [...(this.report?.months ?? [])].reverse();
        },
    },
    created() {
        this.load();
    },
    methods: {
        zoneName,
        monthName(month) {
            return formatDate(`${month}-15T12:00:00Z`, { month: 'short', year: 'numeric' });
        },
        async load() {
            this.failed = false;
            try {
                this.report = (await api.admin.cohorts.show()).data;
            } catch (error) {
                console.warn('Cohorts did not load', error);
                this.failed = true;
            }
        },
    },
};
