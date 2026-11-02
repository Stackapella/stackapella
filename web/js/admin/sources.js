// Growth's Sources (docs/admin-growth-plan.md, slice 4; contract: admin.sources.show): every source
// with how far its arrivals got, sortable on each column, over 7, 30 or 90 days. A source is the
// ?ref= tag on our own links, grouped by the server (campaigns as written, shared harmonies and
// coaches' links as one row each, no tag); sources under 5 arrivals are folded into one last row, so a
// tiny source never stands for a person. Anonymous daily totals only: nothing follows a device.

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { LoadFailed, zoneName } from './common.js';
import { isTag, sortSources, sourceName } from './growth.js';

const RANGES = [7, 30, 90];
// Arrivals as a count; every later step as its share of the source's arrivals (sorted by it too), so
// a big source and a small one compare fairly.
const COLUMNS = [
    { key: 'arrivals', label: tk('admin.growth.col.arrivals'), rate: false },
    { key: 'lesson1', label: tk('admin.growth.col.lesson1'), rate: true },
    { key: 'signedUp', label: tk('admin.sources.col.signedUp'), rate: true },
    { key: 'day2', label: tk('admin.growth.col.day2'), rate: true },
    { key: 'week1', label: tk('admin.sources.col.week1'), rate: true },
];

const pct = (share) => `${formatNumber(Math.round(share * 1000) / 10)}%`;
const day = (date) => (date ? formatDate(`${date}T12:00:00Z`, { month: 'short', day: 'numeric' }) : '');

export const SourcesSection = {
    components: { LoadFailed },
    props: { me: { type: Object, required: true } },
    data: () => ({ days: 7, report: null, failed: false, sortBy: 'arrivals' }),
    setup: () => ({ zoneName, RANGES, COLUMNS, formatNumber, isTag, sourceName }),
    template: `
        <section class="space-y-6" aria-labelledby="sources-h" data-sources>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 id="sources-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.sources.title') }}</h1>
                    <p class="mt-1 text-sm text-muted">{{ report ? t('admin.sources.intro', { from: day(report.from), to: day(report.to), zone: zoneName(report.timezone) }) : t('admin.sources.introLoading') }}</p>
                </div>
                <div class="seg flex" role="radiogroup" :aria-label="t('admin.usage.range')">
                    <button v-for="d in RANGES" :key="d" type="button" role="radio" :aria-checked="days === d" class="seg-option" @click="setDays(d)">{{ t('admin.usage.days', { count: d }) }}</button>
                </div>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!report" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <p v-else-if="!rows.length" class="card p-5 text-muted" data-sources-none>{{ t('admin.sources.none') }}</p>
            <template v-else>
                <!-- Wider screens: one table, each column sortable. -->
                <div class="card hidden p-4 sm:block md:p-5">
                    <table class="w-full text-sm" data-sources-table>
                        <thead class="text-xs text-muted">
                            <tr class="border-b border-line">
                                <th scope="col" class="py-2 pe-2 text-start font-semibold">{{ t('admin.growth.col.source') }}</th>
                                <th v-for="c in COLUMNS" :key="c.key" scope="col" class="px-2 py-2 text-end font-semibold" :aria-sort="sortBy === c.key ? 'descending' : 'none'">
                                    <button type="button" class="tap-44 inline-flex min-h-6 items-center gap-1" :data-sort="c.key" @click="sortBy = c.key">{{ t(c.label) }}<span v-if="sortBy === c.key" aria-hidden="true">↓</span></button>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="row in sorted" :key="row.source" :data-source="row.source">
                                <th scope="row" class="py-2.5 pe-2 text-start font-normal text-cream" :translate="isTag(row.source) ? 'no' : null">{{ sourceName(row) }}</th>
                                <td v-for="c in COLUMNS" :key="c.key" class="px-2 py-2.5 text-end tabular-nums" :data-col="c.key">
                                    <template v-if="!c.rate"><b class="text-cream">{{ formatNumber(row[c.key]) }}</b></template>
                                    <template v-else><b class="text-cream">{{ rate(row, c.key) }}</b><span class="block text-xs text-muted">{{ formatNumber(row[c.key]) }}</span></template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Phones: a card a source, sorted the same way. -->
                <div class="space-y-3 sm:hidden">
                    <label class="flex items-center justify-between gap-3 text-sm text-muted">{{ t('admin.sources.sortBy') }}
                        <select v-model="sortBy" class="input w-auto" data-sources-sort>
                            <option v-for="c in COLUMNS" :key="c.key" :value="c.key">{{ t(c.label) }}</option>
                        </select>
                    </label>
                    <ul class="space-y-3" data-sources-cards>
                        <li v-for="row in sorted" :key="row.source" class="card p-4" :data-source="row.source">
                            <p class="font-semibold text-cream" :translate="isTag(row.source) ? 'no' : null">{{ sourceName(row) }}</p>
                            <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                                <div v-for="c in COLUMNS" :key="c.key" class="flex items-baseline justify-between gap-2">
                                    <dt class="text-muted">{{ t(c.label) }}</dt>
                                    <dd class="tabular-nums text-cream"><b>{{ c.rate ? rate(row, c.key) : formatNumber(row[c.key]) }}</b></dd>
                                </div>
                            </dl>
                        </li>
                    </ul>
                </div>
                <div class="space-y-1 text-xs text-muted">
                    <p>{{ t('admin.sources.note') }}</p>
                    <p>{{ t('admin.sources.returnsNote') }}</p>
                </div>
            </template>
        </section>`,
    computed: {
        rows() {
            return this.report?.sources ?? [];
        },
        sorted() {
            return sortSources(this.rows, this.sortBy, COLUMNS.find((c) => c.key === this.sortBy)?.rate);
        },
    },
    created() {
        this.load();
    },
    methods: {
        day,
        rate(row, key) {
            return row.arrivals ? pct(row[key] / row.arrivals) : '-';
        },
        setDays(days) {
            this.days = days;
            this.load();
        },
        async load() {
            const days = this.days;
            this.failed = false;
            this.report = null;
            try {
                const { data } = await api.admin.sources.show({ days });
                if (days === this.days) this.report = data;
            } catch (error) {
                console.warn('Sources did not load', error);
                if (days === this.days) this.failed = t('admin.sources.failed');
            }
        },
    },
};
