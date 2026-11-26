// AI choice on Growth (docs/ai-optional-plan.md, "Measuring"; contract: admin.growth.ai): how the card after a first
// recorded part is answered, AI used, and who comes back, by the age band stored at sign-up (guests as their own band),
// over the last 28 or 90 days. The totals first (cards shown, Try it and Not for me rates, switches), then one table by
// band, then coming back by state (on, off, not chosen) with week-one lessons. Counts under the floor say "too few".
// Anonymous daily counts only: numbers, nothing about anyone.

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { CsvLink, LoadFailed, zoneName } from './common.js';

export const AI_PERIODS = ['28d', '90d'];
const PERIOD_WORDS = { '28d': tk('admin.growth.ai.period.28d'), '90d': tk('admin.growth.ai.period.90d') };
const BAND_WORDS = { u18: tk('admin.growth.ai.band.u18'), guest: tk('admin.growth.ai.band.guest'), total: tk('admin.growth.ai.band.total') };
const STATE_WORDS = { on: tk('admin.growth.ai.state.on'), off: tk('admin.growth.ai.state.off'), unset: tk('admin.growth.ai.state.unset') };
const CARD_COLUMNS = [
    ['cardShown', tk('admin.growth.ai.col.shown')], ['tryRate', tk('admin.growth.ai.col.try')], ['notRate', tk('admin.growth.ai.col.not')],
    ['cardClosed', tk('admin.growth.ai.col.closed')], ['choirStart', tk('admin.growth.ai.col.choirStart')], ['choirHeard', tk('admin.growth.ai.col.choirHeard')],
    ['guideHeard', tk('admin.growth.ai.col.guideHeard')], ['hideHomeChoir', tk('admin.growth.ai.col.hideHome')], ['hideSongChoir', tk('admin.growth.ai.col.hideSong')],
    ['switchOnOff', tk('admin.growth.ai.col.onOff')], ['switchOffOn', tk('admin.growth.ai.col.offOn')],
];
const BACK_COLUMNS = [
    ['backD1', tk('admin.growth.ai.col.backD1')], ['backW1', tk('admin.growth.ai.col.backW1')], ['backD7', tk('admin.growth.ai.col.backD7')], ['backW4', tk('admin.growth.ai.col.backW4')],
    ['lessons0', tk('admin.growth.ai.col.lessons0')], ['lessons1to2', tk('admin.growth.ai.col.lessons1to2')], ['lessons3to5', tk('admin.growth.ai.col.lessons3to5')], ['lessons6plus', tk('admin.growth.ai.col.lessons6plus')],
];

const RATES = new Set(['tryRate', 'notRate']);
const day = (date) => formatDate(`${date}T12:00:00Z`, { month: 'short', day: 'numeric' });
const pct = (rate) => `${formatNumber(Math.round(rate * 1000) / 10)}%`;

export const GrowthAi = {
    components: { CsvLink, LoadFailed },
    data: () => ({ shown: '28d', report: null, failed: false, loading: false }),
    template: `
        <section class="card space-y-5 p-5 md:p-6" aria-labelledby="growth-ai-h" data-growth-ai>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <h2 id="growth-ai-h" class="text-lg font-semibold text-cream">{{ t('admin.growth.ai.title') }}</h2>
                    <p class="t-hint">{{ report ? t('admin.growth.ai.sub', { from: day(report.from), to: day(report.to), zone: zoneName(report.timezone), floor: report.floor }) : t('admin.growth.ai.subLoading') }}</p>
                </div>
                <!-- The same table as CSV (admin.growth.export page=ai), for the period shown. -->
                <CsvLink page="ai" :params="{ period: shown }" class="ms-auto" />
                <div class="seg seg-inset" role="radiogroup" :aria-label="t('admin.growth.ai.period.label')">
                    <button v-for="p in AI_PERIODS" :key="p" type="button" role="radio" class="seg-option" :aria-checked="shown === p ? 'true' : 'false'" :data-ai-period="p" @click="pick(p)">{{ t(PERIOD_WORDS[p]) }}</button>
                </div>
            </div>
            <LoadFailed v-if="failed" :message="t('admin.growth.ai.failed')" @retry="load" />
            <template v-else-if="report">
                <dl class="hairline-grid grid-cols-2 overflow-hidden rounded-2xl border border-edge lg:grid-cols-4" data-ai-totals :aria-busy="loading ? 'true' : null">
                    <div v-for="tile in tiles" :key="tile.key" class="flex min-w-0 flex-col gap-1 p-4" :data-ai-total="tile.key">
                        <dt class="t-section">{{ t(tile.label) }}</dt>
                        <dd class="text-3xl font-bold tabular-nums text-cream" data-n>{{ tile.n }}</dd>
                        <dd v-if="tile.hint" class="t-hint">{{ tile.hint }}</dd>
                    </div>
                </dl>

                <div class="space-y-2">
                    <h3 class="t-section">{{ t('admin.growth.ai.byBand') }}</h3>
                    <div class="overflow-x-auto rounded-2xl border border-edge [scrollbar-width:none]" data-scroll-row>
                        <table class="w-full min-w-[46rem] text-sm tabular-nums" data-ai-bands>
                            <thead class="text-subtle">
                                <tr><th scope="col" class="px-3 py-2 text-start font-semibold">{{ t('admin.growth.ai.col.band') }}</th><th v-for="[key, label] in CARD_COLUMNS" :key="key" scope="col" class="px-3 py-2 text-end font-semibold">{{ t(label) }}</th></tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <tr v-for="row in rows" :key="row.band" :data-ai-band="row.band" :class="row.band === 'total' ? 'font-semibold text-cream' : 'text-cream'">
                                    <th scope="row" class="px-3 py-2 text-start font-semibold">{{ bandName(row.band) }}</th>
                                    <td v-for="[key] in CARD_COLUMNS" :key="key" class="px-3 py-2 text-end" :class="row[key] === null ? 'text-subtle' : ''">{{ cell(row, key) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-2">
                    <h3 class="t-section">{{ t('admin.growth.ai.back') }}</h3>
                    <p class="t-hint">{{ t('admin.growth.ai.backHint') }}</p>
                    <div class="overflow-x-auto rounded-2xl border border-edge [scrollbar-width:none]" data-scroll-row>
                        <table class="w-full min-w-[46rem] text-sm tabular-nums" data-ai-back>
                            <thead class="text-subtle">
                                <tr><th scope="col" class="px-3 py-2 text-start font-semibold">{{ t('admin.growth.ai.col.band') }}</th><th scope="col" class="px-3 py-2 text-start font-semibold">{{ t('admin.growth.ai.col.state') }}</th><th v-for="[key, label] in BACK_COLUMNS" :key="key" scope="col" class="px-3 py-2 text-end font-semibold">{{ t(label) }}</th></tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <tr v-for="row in backRows" :key="row.band + row.state" :data-ai-back-row="row.band + ':' + row.state" class="text-cream">
                                    <th scope="row" class="px-3 py-2 text-start font-semibold">{{ row.first ? bandName(row.band) : '' }}</th>
                                    <td class="px-3 py-2">{{ t(STATE_WORDS[row.state]) }}</td>
                                    <td v-for="[key] in BACK_COLUMNS" :key="key" class="px-3 py-2 text-end" :class="row[key] === null ? 'text-subtle' : ''">{{ row[key] === null ? t('admin.growth.ai.few') : formatNumber(row[key]) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="t-hint">{{ t('admin.growth.ai.caveat') }}</p>
                </div>
            </template>
            <p v-else class="text-muted" role="status">{{ t('admin.loading') }}</p>
        </section>`,
    setup: () => ({ AI_PERIODS, PERIOD_WORDS, CARD_COLUMNS, BACK_COLUMNS, STATE_WORDS, day, zoneName, formatNumber }),
    computed: {
        rows() {
            return [...this.report.bands, { band: 'total', ...this.report.total }];
        },
        backRows() {
            return this.rows.flatMap((row) => (row.engagement ?? []).map((e, i) => ({ band: row.band, first: i === 0, ...e })));
        },
        tiles() {
            const total = this.report.total;
            const n = (v) => (v === null ? t('admin.growth.ai.few') : formatNumber(v));
            const r = (v) => (v === null ? t('admin.growth.ai.few') : pct(v));
            return [
                { key: 'shown', label: tk('admin.growth.ai.col.shown'), n: n(total.cardShown) },
                { key: 'try', label: tk('admin.growth.ai.col.try'), n: r(total.tryRate), hint: total.cardTry === null ? '' : t('admin.growth.ai.ofShown', { n: formatNumber(total.cardTry) }) },
                { key: 'not', label: tk('admin.growth.ai.col.not'), n: r(total.notRate), hint: total.cardNot === null ? '' : t('admin.growth.ai.ofShown', { n: formatNumber(total.cardNot) }) },
                // Hide AI features (since 2026-11-26, when undecided became on): from Home's choir panel and the song sheet.
                { key: 'hides', label: tk('admin.growth.ai.hides'), n: total.hideHomeChoir === null && total.hideSongChoir === null ? t('admin.growth.ai.few') : formatNumber((total.hideHomeChoir ?? 0) + (total.hideSongChoir ?? 0)), hint: t('admin.growth.ai.hidesHint') },
                { key: 'switches', label: tk('admin.growth.ai.switches'), n: `${n(total.switchOffOn)} / ${n(total.switchOnOff)}`, hint: t('admin.growth.ai.switchesHint') },
            ];
        },
    },
    created() {
        this.load();
    },
    methods: {
        bandName(band) {
            return BAND_WORDS[band] ? t(BAND_WORDS[band]) : band;
        },
        cell(row, key) {
            const v = row[key];
            if (v === null || v === undefined) return t('admin.growth.ai.few');
            return RATES.has(key) ? pct(v) : formatNumber(v);
        },
        pick(period) {
            if (this.shown === period) return;
            this.shown = period;
            this.load();
        },
        async load() {
            this.loading = true;
            this.failed = false;
            try {
                this.report = (await api.admin.growth.ai({ period: this.shown })).data;
            } catch (error) {
                console.warn('The AI choice card did not load', error);
                this.failed = true;
            } finally {
                this.loading = false;
            }
        },
    },
};
