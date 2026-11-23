// AI choirs on Growth (Hillel, 2026-11-23: "make sure to include AI choirs made on admin growth"; contract:
// admin.growth.choirs): beside the sign-ups, over the last 7 or 30 days, each total against the period before. Songs
// made from a singer's own recording by their plan (free, Plus, Plus given), friends' parts made into a song, the
// renderer's part-minutes, the failures split ours and the recording's, and recordings started to songs made. A day
// at a time as bars, and the same as a table. Anonymous daily counts only: numbers, nothing about anyone.

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { change, LoadFailed, zoneName } from './common.js';

export const CHOIR_PERIODS = ['7d', '30d'];
const PERIOD_WORDS = { '7d': tk('admin.growth.choirs.period.7d'), '30d': tk('admin.growth.choirs.period.30d') };

const COLUMNS = ['made', 'free', 'plus', 'comped', 'together', 'partMinutes', 'failedOurs', 'failedTake'];
const WORDS = {
    made: tk('admin.growth.choirs.col.made'),
    free: tk('admin.growth.choirs.col.free'),
    plus: tk('admin.growth.choirs.col.plus'),
    comped: tk('admin.growth.choirs.col.comped'),
    together: tk('admin.growth.choirs.col.together'),
    partMinutes: tk('admin.growth.choirs.col.partMinutes'),
    failed: tk('admin.growth.choirs.col.failed'),
    failedOurs: tk('admin.growth.choirs.col.failedOurs'),
    failedTake: tk('admin.growth.choirs.col.failedTake'),
};

const day = (date) => formatDate(`${date}T12:00:00Z`, { month: 'short', day: 'numeric' });
const minutes = (n) => formatNumber(Math.round(n * 10) / 10);
const pct = (rate) => `${formatNumber(Math.round(rate * 1000) / 10)}%`;

export const GrowthChoirs = {
    components: { LoadFailed },
    // `period`: the page's (today, 7d, 28d, month, 12w): 7 days for today or a week, else 30, until the singer picks.
    props: { period: { type: String, default: '30d' } },
    data() {
        return { shown: ['today', '7d'].includes(this.period) ? '7d' : '30d', report: null, failed: false, loading: false, tableOpen: false };
    },
    template: `
        <section class="card space-y-5 p-5 md:p-6" aria-labelledby="growth-choirs-h" data-growth-choirs>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <h2 id="growth-choirs-h" class="text-lg font-semibold text-cream">{{ t('admin.growth.choirs.title') }}</h2>
                    <p class="t-hint">{{ report ? t('admin.growth.choirs.sub', { from: day(report.from), to: day(report.to), zone: zoneName(report.timezone) }) : t('admin.growth.choirs.subLoading') }}</p>
                </div>
                <div class="seg seg-inset" role="radiogroup" :aria-label="t('admin.growth.choirs.period.label')">
                    <button v-for="p in CHOIR_PERIODS" :key="p" type="button" role="radio" class="seg-option" :aria-checked="shown === p ? 'true' : 'false'" :data-choirs-period="p" @click="pick(p)">{{ t(PERIOD_WORDS[p]) }}</button>
                </div>
            </div>
            <LoadFailed v-if="failed" :message="t('admin.growth.choirs.failed')" @retry="load" />
            <template v-else-if="report">
                <dl class="hairline-grid grid-cols-2 overflow-hidden rounded-2xl border border-edge lg:grid-cols-4" data-choirs-totals :aria-busy="loading ? 'true' : null">
                    <div v-for="tile in tiles" :key="tile.key" class="flex min-w-0 flex-col gap-1 p-4" :data-choirs-total="tile.key">
                        <dt class="t-section">{{ t(tile.label) }}</dt>
                        <dd class="text-3xl font-bold tabular-nums text-cream" data-n>{{ tile.n }}</dd>
                        <dd v-if="tile.split" class="t-hint" data-split>{{ tile.split }}</dd>
                        <dd class="mt-auto pt-1 text-sm tabular-nums" data-change :data-dir="tile.change.dir" :class="tile.tone">
                            <span v-if="tile.change.dir !== 'same'" class="me-1" aria-hidden="true">{{ tile.change.dir === 'up' ? '↑' : '↓' }}</span>{{ t('admin.growth.choirs.vsBefore', { change: tile.change.text, before: tile.before }) }}
                        </dd>
                    </div>
                </dl>

                <!-- Recordings started in the app to songs made: the rate, and the one before; too few to tell under the floor. -->
                <div class="space-y-1" data-choirs-funnel>
                    <p class="text-cream">{{ t('admin.growth.choirs.funnel', { started: formatNumber(report.funnel.captureStarted.n), made: formatNumber(report.funnel.made.n) }) }}
                        <span v-if="report.funnel.rate !== null" data-rate> · <b class="font-semibold">{{ t('admin.growth.choirs.rate', { rate: pct(report.funnel.rate) }) }}</b></span>
                        <span v-if="fewToTell" class="chip-tag ms-1" data-few>{{ t('admin.growth.choirs.few') }}</span>
                    </p>
                    <p class="t-hint" data-rate-before>{{ report.funnel.rateBefore === null ? t('admin.growth.choirs.rateBeforeNone') : t('admin.growth.choirs.rateBefore', { rate: pct(report.funnel.rateBefore), started: formatNumber(report.funnel.captureStarted.before) }) }}</p>
                </div>

                <!-- Songs made a day, the plan said in each bar's label; the table under it says every number. -->
                <figure class="space-y-2" data-choirs-days>
                    <figcaption class="t-section">{{ t('admin.growth.choirs.byDay') }}</figcaption>
                    <div class="flex h-28 items-end gap-[2px]" aria-hidden="true">
                        <div v-for="d in report.days" :key="d.date" class="flex h-full min-w-0 flex-1 flex-col justify-end" :title="barWords(d)" data-choirs-bar :data-date="d.date">
                            <div v-if="d.made" class="rounded-t-[4px] bg-cream/80" :style="{ height: Math.max(4, (d.made / most) * 100) + '%' }"></div>
                            <div v-else class="h-[2px] bg-line"></div>
                        </div>
                    </div>
                    <div class="flex justify-between t-hint" aria-hidden="true"><span>{{ day(report.from) }}</span><span>{{ day(report.to) }}</span></div>
                </figure>

                <div>
                    <button type="button" class="link tap-44 text-sm font-semibold" :aria-expanded="tableOpen ? 'true' : 'false'" aria-controls="growth-choirs-table" data-choirs-table-toggle @click="tableOpen = !tableOpen">{{ tableOpen ? t('admin.growth.choirs.tableHide') : t('admin.growth.choirs.tableShow') }}</button>
                    <div v-show="tableOpen" id="growth-choirs-table" class="mt-2 overflow-x-auto [scrollbar-width:none]" data-scroll-row>
                        <table class="w-full min-w-[36rem] text-sm tabular-nums" data-choirs-table>
                            <thead><tr class="border-b border-edge text-start">
                                <th scope="col" class="py-2 pe-3 text-start t-section">{{ t('admin.growth.choirs.col.date') }}</th>
                                <th v-for="c in COLUMNS" :key="c" scope="col" class="px-2 py-2 text-end t-section">{{ t(WORDS[c]) }}</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="d in [...report.days].reverse()" :key="d.date" class="border-b border-edge last:border-0">
                                    <th scope="row" class="py-2 pe-3 text-start font-medium text-cream">{{ day(d.date) }}</th>
                                    <td v-for="c in COLUMNS" :key="c" class="px-2 py-2 text-end" :class="d[c] ? 'text-cream' : 'text-subtle'">{{ c === 'partMinutes' ? minutes(d[c]) : formatNumber(d[c]) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
            <p v-else class="t-hint" role="status">{{ t('admin.growth.choirs.loading') }}</p>
        </section>`,
    setup: () => ({ CHOIR_PERIODS, PERIOD_WORDS, COLUMNS, WORDS, day, formatNumber, minutes, pct, zoneName }),
    computed: {
        most() {
            return Math.max(1, ...(this.report?.days ?? []).map((d) => d.made));
        },
        /** Under the floor, the rate reads as too few to tell. */
        fewToTell() {
            return this.report.funnel.captureStarted.n < (this.report.floor ?? 0);
        },
        tiles() {
            const totals = this.report.totals;
            const failed = { n: totals.failedOurs.n + totals.failedTake.n, before: totals.failedOurs.before + totals.failedTake.before };
            const tile = (key, label, total, { split = '', format = formatNumber, worse = false } = {}) => {
                const moved = change(total.n, total.before);
                // More made is good; more failures isn't: those say their direction without the colour.
                const tone = worse || moved.dir === 'same' ? 'text-muted' : moved.dir === 'up' ? 'text-up' : 'text-down';
                return { key, label, n: format(total.n), before: format(total.before), split, change: moved, tone };
            };
            return [
                tile('made', WORDS.made, totals.made, { split: t('admin.growth.choirs.split', { free: formatNumber(totals.free.n), plus: formatNumber(totals.plus.n), comped: formatNumber(totals.comped.n) }) }),
                tile('together', WORDS.together, totals.together, { split: t('admin.growth.choirs.togetherSub') }),
                tile('partMinutes', WORDS.partMinutes, totals.partMinutes, { format: minutes }),
                tile('failed', WORDS.failed, failed, { worse: true, split: t('admin.growth.choirs.failedSplit', { ours: formatNumber(totals.failedOurs.n), take: formatNumber(totals.failedTake.n) }) }),
            ];
        },
    },
    created() {
        this.load();
    },
    methods: {
        pick(period) {
            if (period === this.shown) return;
            this.shown = period;
            this.load();
        },
        async load() {
            const asked = this.shown;
            this.loading = true;
            this.failed = false;
            try {
                const answer = await api.admin.growth.choirs({ period: asked });
                if (asked === this.shown) this.report = answer.data;
            } catch (error) {
                console.warn('admin.growth.choirs', error);
                if (asked === this.shown) this.failed = true;
            } finally {
                if (asked === this.shown) this.loading = false;
            }
        },
        barWords(d) {
            return t('admin.growth.choirs.bar', { date: day(d.date), made: formatNumber(d.made), free: formatNumber(d.free), plus: formatNumber(d.plus), comped: formatNumber(d.comped) });
        },
    },
};

