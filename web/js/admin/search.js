// Search (Hillel: Search Console in Admin), under Growth: Google Search's last 28 days for the public
// site (admin.search.show, synced by the server from Search Console): the pages to improve first (seen
// often but rarely clicked, or close to the top), then clicks, impressions, click rate and position by
// page and by search, and the sitemaps. Until the credentials are set, an honest "Not connected yet"
// (docs/search-console.md). Serious skin, as all of Admin.

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { LoadFailed, remembers } from './common.js';
import { Notice } from '../components/form.js';

const REASONS = { lowCtr: tk('admin.search.reason.lowCtr'), nearTop: tk('admin.search.reason.nearTop') };
const DOC = 'docs/search-console.md'; // i18n-ignore: a path in the repo
const VIEWS = { pages: tk('admin.search.byPage'), queries: tk('admin.search.byQuery') };

/** A page's address without the site's origin ("/courses/perfect-your-pitch"); a search as typed. */
export function shortKey(key) {
    try {
        const url = new URL(key);
        return url.pathname + url.search || '/';
    } catch {
        return key;
    }
}

/** A click rate (0 to 1) as a percentage, one decimal. */
export const rate = (ctr) => formatNumber(ctr ?? 0, { style: 'percent', maximumFractionDigits: 1 });

export const SearchSection = {
    mixins: [remembers('search', { view: Object.keys(VIEWS) })],
    components: { LoadFailed, Notice },
    props: { me: { type: Object, required: true } },
    data: () => ({ report: null, failed: false, view: 'pages' }),
    setup: () => ({ DOC, REASONS, VIEWS, formatNumber, rate, shortKey }),
    template: `
        <section class="space-y-6" aria-labelledby="search-h" data-search>
            <div>
                <h1 id="search-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.search.title') }}</h1>
                <p class="mt-1 text-sm text-muted">{{ report?.from ? t('admin.search.intro', { from: day(report.from), to: day(report.to) }) : t('admin.search.introPlain') }}</p>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!report" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <section v-else-if="!report.connected" class="card space-y-2 p-5 md:p-6" data-search-off>
                <h2 class="text-lg font-bold text-cream">{{ t('admin.search.offTitle') }}</h2>
                <p class="text-sm text-muted">{{ t('admin.search.offBody', { doc: DOC }) }}</p>
            </section>
            <template v-else>
                <Notice v-if="report.error" tone="warn" data-search-error>{{ t('admin.search.lastFailed', { error: report.error }) }}</Notice>
                <p v-if="report.syncedAt" class="text-xs text-muted">{{ t('admin.search.synced', { when: formatDate(report.syncedAt, { dateStyle: 'medium', timeStyle: 'short' }) }) }}</p>
                <ul class="grid gap-3 sm:grid-cols-3">
                    <li v-for="tile in tiles" :key="tile.key" class="card p-5" :data-tile="tile.key">
                        <p class="text-sm text-muted">{{ t(tile.label) }}</p>
                        <p class="mt-2 text-4xl font-extrabold tabular-nums text-cream">{{ tile.value }}</p>
                    </li>
                </ul>

                <!-- The list marketing works through, first. -->
                <section class="card min-w-0 p-5 md:p-6" aria-labelledby="search-improve-h" data-to-improve>
                    <h2 id="search-improve-h" class="text-lg font-bold text-cream">{{ t('admin.search.improve') }}</h2>
                    <p class="text-sm text-muted">{{ t('admin.search.improveWhy', { min: formatNumber(report.rules.minImpressions), low: rate(report.rules.lowCtr), from: report.rules.nearTop[0], to: report.rules.nearTop[1] }) }}</p>
                    <p v-if="!report.toImprove.length" class="mt-3 text-sm text-muted">{{ t('admin.search.improveNone') }}</p>
                    <div v-else class="-mx-1 mt-4 overflow-x-auto px-1">
                        <table class="w-full text-sm">
                            <thead class="text-xs font-semibold uppercase tracking-wider text-muted">
                                <tr>
                                    <th scope="col" class="pb-2 text-start font-semibold">{{ t('admin.search.col.page') }}</th>
                                    <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.search.col.impressions') }}</th>
                                    <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.search.col.clicks') }}</th>
                                    <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.search.col.ctr') }}</th>
                                    <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.search.col.position') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in report.toImprove" :key="row.key" class="border-t border-line align-top" :data-improve="shortKey(row.key)">
                                    <td class="py-2">
                                        <a :href="row.key" target="_blank" rel="noopener" class="link break-all text-cream" translate="no">{{ shortKey(row.key) }}</a>
                                        <span class="mt-1 flex flex-wrap gap-1.5"><span v-for="r in row.reasons" :key="r" class="rounded-md border border-line px-1.5 py-0.5 text-xs text-muted" :data-reason="r">{{ t(REASONS[r]) }}</span></span>
                                    </td>
                                    <td class="py-2 ps-3 text-end tabular-nums text-cream">{{ formatNumber(row.impressions) }}</td>
                                    <td class="py-2 ps-3 text-end tabular-nums text-cream">{{ formatNumber(row.clicks) }}</td>
                                    <td class="py-2 ps-3 text-end tabular-nums text-cream">{{ rate(row.ctr) }}</td>
                                    <td class="py-2 ps-3 text-end tabular-nums text-cream">{{ position(row.position) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="card min-w-0 p-5 md:p-6" aria-labelledby="search-top-h" data-search-top>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <h2 id="search-top-h" class="text-lg font-bold text-cream">{{ t('admin.search.top') }}</h2>
                        <div class="seg flex" role="radiogroup" :aria-label="t('admin.search.top')">
                            <button v-for="(words, key) in VIEWS" :key="key" type="button" role="radio" :aria-checked="view === key" class="seg-option" @click="view = key">{{ t(words) }}</button>
                        </div>
                    </div>
                    <p v-if="!rows.length" class="mt-3 text-sm text-muted">{{ t('admin.search.none') }}</p>
                    <div v-else class="-mx-1 mt-4 overflow-x-auto px-1">
                        <table class="w-full text-sm">
                            <thead class="text-xs font-semibold uppercase tracking-wider text-muted">
                                <tr>
                                    <th scope="col" class="pb-2 text-start font-semibold">{{ view === 'pages' ? t('admin.search.col.page') : t('admin.search.col.query') }}</th>
                                    <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.search.col.clicks') }}</th>
                                    <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.search.col.impressions') }}</th>
                                    <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.search.col.ctr') }}</th>
                                    <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.search.col.position') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in rows" :key="row.key" class="border-t border-line" :data-row="view">
                                    <td class="py-2 break-all text-cream" translate="no">{{ view === 'pages' ? shortKey(row.key) : row.key }}</td>
                                    <td class="py-2 ps-3 text-end tabular-nums text-cream">{{ formatNumber(row.clicks) }}</td>
                                    <td class="py-2 ps-3 text-end tabular-nums text-cream">{{ formatNumber(row.impressions) }}</td>
                                    <td class="py-2 ps-3 text-end tabular-nums text-cream">{{ rate(row.ctr) }}</td>
                                    <td class="py-2 ps-3 text-end tabular-nums text-cream">{{ position(row.position) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section v-if="report.sitemaps.length" class="card p-5 md:p-6" aria-labelledby="search-maps-h" data-sitemaps>
                    <h2 id="search-maps-h" class="text-lg font-bold text-cream">{{ t('admin.search.sitemaps') }}</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="m in report.sitemaps" :key="m.path" class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <span class="break-all text-cream" translate="no">{{ shortKey(m.path) }}</span>
                            <span class="text-muted">{{ t('admin.search.sitemapLine', { errors: m.errors, warnings: m.warnings }) }} · {{ m.lastDownloaded ? t('admin.search.read', { when: formatDate(m.lastDownloaded) }) : t('admin.search.notRead') }}</span>
                        </li>
                    </ul>
                </section>
            </template>
        </section>`,
    computed: {
        tiles() {
            const totals = this.report?.totals ?? {};
            return [
                { key: 'clicks', label: tk('admin.search.clicks'), value: formatNumber(totals.clicks ?? 0) },
                { key: 'impressions', label: tk('admin.search.impressions'), value: formatNumber(totals.impressions ?? 0) },
                { key: 'ctr', label: tk('admin.search.ctr'), value: rate(totals.ctr) },
            ];
        },
        rows() {
            return (this.view === 'pages' ? this.report?.pages : this.report?.queries) ?? [];
        },
    },
    created() {
        this.load();
    },
    methods: {
        formatDate,
        day(value) {
            return formatDate(`${value}T12:00:00Z`, { day: 'numeric', month: 'short' });
        },
        /** The average position, 1 being the top result: one decimal. */
        position(value) {
            return value == null ? '-' : formatNumber(value, { minimumFractionDigits: 1, maximumFractionDigits: 1 });
        },
        async load() {
            this.failed = false;
            try {
                this.report = (await api.admin.search.show()).data;
            } catch (error) {
                console.warn('Search did not load', error);
                this.failed = t('admin.search.failed');
            }
        },
    },
};
