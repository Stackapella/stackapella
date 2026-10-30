// Growth's Overview (docs/admin-growth-plan.md, slice 2; contract: admin.growth.show,
// admin.sources.show, admin.funnel.show): the page Hillel opens each morning. Four tiles (in the
// app, lesson 1 sung, signed up, Plus started), each against yesterday and the same day last week,
// said with an arrow, a sign and words (never colour alone), and "few so far" under 20; how far
// they got, as one bar a step with what didn't go on between; where they came from, small sources
// folded into one row so a tiny one never stands for a person; and the running experiment in one
// card. Today so far (UTC), refreshed every 30 seconds while in view, like Usage; or 7 or 30 days.
// Anonymous daily totals only: nothing follows a device.

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { LoadFailed } from './common.js';
import { updatedAgo } from './usage.js';
import { ARM_COLORS, arrivalsByArm, lostBetween } from './funnel.js';

const RANGES = [1, 7, 30];
const REFRESH_MS = 30000; // while the page is in view (a timer for the page, never for audio)
const TICK_MS = 1000;
const FEW = 20; // under this, a change is said as a number, never a percentage (it'd be noise)
const TILES = { arrivals: tk('admin.growth.tile.arrivals'), lesson1: tk('admin.growth.tile.lesson1'), signedUp: tk('admin.growth.tile.signedUp'), plusStarted: tk('admin.growth.tile.plusStarted') };
const SHARE_OF_ARRIVALS = ['lesson1', 'signedUp'];
const STEPS = { visits: tk('admin.growth.step.visits'), start: tk('admin.growth.step.start'), arrivals: tk('admin.growth.step.arrivals'), lesson1: tk('admin.growth.step.lesson1'), unit1: tk('admin.growth.step.unit1'), signedUp: tk('admin.growth.step.signedUp'), plusStarted: tk('admin.growth.step.plusStarted') };
// Where the next step is a part of the one before, so a loss between them is real: arrivals also come
// straight to the app, and signing up can come before Unit 1.
const FOLLOWS = new Set(['visits>start', 'arrivals>lesson1', 'lesson1>unit1', 'signedUp>plusStarted']);
const SOURCES = { none: tk('admin.growth.source.none'), harmony: tk('admin.growth.source.harmony'), coach: tk('admin.growth.source.coach'), other: tk('admin.growth.source.other') };
const COLUMNS = { arrivals: tk('admin.growth.col.arrivals'), lesson1: tk('admin.growth.col.lesson1'), day2: tk('admin.growth.col.day2') };

const pct = (share) => `${formatNumber(Math.round(share * 1000) / 10)}%`;
const day = (date) => (date ? formatDate(`${date}T12:00:00Z`, { month: 'short', day: 'numeric' }) : '');

/** "↑ +18% vs yesterday", "↓ -2 vs last Thu" (under 20, the number), "same as yesterday": words, a sign and an arrow. */
export function change(now, before, against) {
    if (now === before) return { arrow: '', text: t('admin.growth.same', { against }) };
    const arrow = now > before ? '↑' : '↓';
    const diff = before >= FEW ? `${now > before ? '+' : ''}${formatNumber(Math.round(((now - before) / before) * 100))}%` : `${now > before ? '+' : ''}${formatNumber(now - before)}`;
    return { arrow, text: t('admin.growth.versus', { diff, against }) };
}

export const OverviewSection = {
    components: { LoadFailed },
    props: { me: { type: Object, required: true } },
    data: () => ({ days: 1, growth: null, sources: null, funnel: null, failed: false, sortBy: 'arrivals', updatedAt: null, now: Date.now(), refreshing: false, staleSince: null }),
    setup: () => ({ RANGES, FEW, TILES, STEPS, COLUMNS, ARM_COLORS, formatNumber, pct, day, navigate }),
    template: `
        <section class="space-y-6" aria-labelledby="growth-h" data-growth>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 id="growth-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.growth.title') }}</h1>
                    <p class="mt-1 flex flex-wrap items-center gap-x-2 text-sm text-muted">
                        <span>{{ days === 1 ? t('admin.growth.introToday') : t('admin.growth.introDays', { from: day(growth?.from), to: day(growth?.to) }) }}</span>
                        <span v-if="days === 1 && updatedAt" class="inline-flex flex-wrap items-center gap-x-2" data-growth-updated><span>· {{ staleSince ? t('admin.usage.notUpdated', { when: updated }) : t('admin.usage.updated', { when: updated }) }}</span>
                            <button type="button" class="btn-ghost btn-sm" :aria-busy="refreshing ? 'true' : null" :disabled="refreshing" @click="refresh">{{ t('admin.usage.refresh') }}</button></span>
                    </p>
                </div>
                <div class="seg flex" role="radiogroup" :aria-label="t('admin.usage.range')">
                    <button v-for="d in RANGES" :key="d" type="button" role="radio" :aria-checked="days === d" class="seg-option" @click="setDays(d)">{{ d === 1 ? t('admin.growth.today') : t('admin.usage.days', { count: d }) }}</button>
                </div>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!growth" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <!-- Four tiles: today, against yesterday and the same day last week, whatever the range. -->
                <p v-if="days !== 1" class="-mb-3 text-sm text-muted" data-growth-tiles-today>{{ t('admin.growth.tilesToday') }}</p>
                <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-growth-tiles>
                    <li v-for="tile in growth.tiles" :key="tile.key" class="card space-y-1.5 p-4" :data-tile="tile.key">
                        <p class="text-sm text-muted">{{ TILES[tile.key] ? t(TILES[tile.key]) : tile.key }}</p>
                        <p class="text-3xl font-extrabold tabular-nums text-cream">{{ formatNumber(tile.today) }}<span v-if="tile.today < FEW" class="ms-2 text-sm font-normal text-muted" data-few>{{ t('admin.growth.few') }}</span></p>
                        <p class="text-sm text-muted"><template v-for="c in [change(tile.today, tile.yesterday, t('admin.growth.yesterday')), change(tile.today, tile.lastWeek, lastWeekday)]" :key="c.text"><span class="me-3 inline-block" data-change><span v-if="c.arrow" class="me-1" aria-hidden="true">{{ c.arrow }}</span>{{ c.text }}</span></template></p>
                        <p v-if="shareOfArrivals(tile)" class="text-sm text-muted"><b class="text-cream">{{ shareOfArrivals(tile) }}</b> {{ t('admin.growth.ofArrivals') }}</p>
                    </li>
                </ul>

                <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                    <!-- How far they got: a bar a step, and what didn't go on between. -->
                    <section class="card space-y-3 p-4 md:p-5" aria-labelledby="growth-steps-h" data-growth-steps>
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 id="growth-steps-h" class="text-lg font-bold text-cream">{{ days === 1 ? t('admin.growth.stepsToday') : t('admin.growth.steps') }}</h2>
                            <a href="/admin/funnel" class="link text-sm" @click.prevent="navigate('/admin/funnel')">{{ t('admin.growth.openFunnel') }}</a>
                        </div>
                        <ol class="space-y-1">
                            <li v-for="(s, i) in growth.steps" :key="s.key" :data-step="s.key">
                                <div class="grid grid-cols-[8rem_1fr_4.5rem] items-center gap-3">
                                    <p class="text-sm text-cream">{{ STEPS[s.key] ? t(STEPS[s.key]) : s.key }}</p>
                                    <div class="h-3 overflow-hidden rounded-full bg-raised" aria-hidden="true"><div class="h-full rounded-full bg-accent" :style="{ width: barWidth(s.total) + '%' }"></div></div>
                                    <p class="text-end text-sm tabular-nums"><b class="text-cream">{{ formatNumber(s.total) }}</b><span v-if="i > 0 && firstStep" class="block text-xs text-muted">{{ pct(s.total / firstStep) }}</span></p>
                                </div>
                                <p v-if="didntGoOn(i)" class="ms-[8.75rem] text-xs text-muted" data-growth-lost><span aria-hidden="true">↓ </span>{{ didntGoOn(i) }}</p>
                            </li>
                        </ol>
                        <p class="text-xs text-muted">{{ t('admin.growth.stepsNote') }}</p>
                    </section>

                    <!-- Where they came from: small sources folded, so a tiny one never stands for a person. -->
                    <section class="card space-y-3 p-4 md:p-5" aria-labelledby="growth-sources-h" data-growth-sources>
                        <h2 id="growth-sources-h" class="text-lg font-bold text-cream">{{ t('admin.growth.sources') }}</h2>
                        <p v-if="!sources" class="text-sm text-muted" role="status">{{ t('admin.loading') }}</p>
                        <table v-else class="w-full text-sm">
                            <thead class="text-xs text-muted">
                                <tr class="border-b border-line">
                                    <th scope="col" class="py-2 pe-2 text-start font-semibold">{{ t('admin.growth.col.source') }}</th>
                                    <th v-for="(label, key) in COLUMNS" :key="key" scope="col" class="px-1 py-2 text-end font-semibold" :aria-sort="sortBy === key ? 'descending' : 'none'">
                                        <button type="button" class="inline-flex min-h-6 items-center gap-1" :data-sort="key" @click="sortBy = key">{{ t(label) }}<span v-if="sortBy === key" aria-hidden="true">↓</span></button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <tr v-for="row in sortedSources" :key="row.source" :data-source="row.source">
                                    <th scope="row" class="py-2 pe-2 text-start font-normal text-cream" :translate="isTag(row.source) ? 'no' : null">{{ sourceName(row) }}</th>
                                    <td class="px-1 py-2 text-end tabular-nums text-cream">{{ formatNumber(row.arrivals) }}</td>
                                    <td class="px-1 py-2 text-end tabular-nums text-cream">{{ formatNumber(row.lesson1) }}</td>
                                    <td class="px-1 py-2 text-end tabular-nums text-cream">{{ row.arrivals ? pct(row.day2 / row.arrivals) : '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="text-xs text-muted">{{ days === 1 ? t('admin.growth.sourcesNoteToday') : t('admin.growth.sourcesNote') }}</p>
                    </section>
                </div>

                <!-- The running experiment, in one card; the Funnel has the rest. -->
                <section v-if="experiment" class="card space-y-3 p-4 md:p-5" aria-labelledby="growth-exp-h" data-growth-experiment>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 id="growth-exp-h" class="text-lg font-bold text-cream">{{ t('admin.growth.experiment', { id: experiment.id }) }}</h2>
                            <p class="text-sm text-muted">{{ experimentLine }}</p>
                        </div>
                        <span class="chip text-xs" data-growth-readable>{{ !started ? t('admin.funnel.notStarted') : readable ? t('admin.growth.ready') : t('admin.growth.notReady') }}</span>
                    </div>
                    <p class="text-sm text-cream">{{ t('admin.growth.primaryStep', { step: primaryName }) }}</p>
                    <div v-if="started" class="space-y-1.5">
                        <div v-for="a in primaryArms" :key="a.arm" class="flex items-center gap-2">
                            <span class="w-3 text-xs font-bold text-cream" translate="no">{{ a.arm.toUpperCase() }}</span>
                            <div class="h-2.5 min-w-0 flex-1 overflow-hidden rounded-full bg-raised" aria-hidden="true"><div class="h-full rounded-full" :style="{ width: Math.max(1, (a.rate ?? 0) * 100) + '%', background: ARM_COLORS[a.arm] ?? ARM_COLORS.all }"></div></div>
                            <span class="w-14 text-end text-sm tabular-nums text-cream">{{ pct(a.rate ?? 0) }}</span>
                        </div>
                    </div>
                    <a href="/admin/funnel" class="link text-sm" @click.prevent="navigate('/admin/funnel')">{{ t('admin.growth.openFunnel') }}</a>
                </section>
            </template>
        </section>`,
    computed: {
        updated() {
            return this.updatedAt ? updatedAgo(this.updatedAt, this.now) : '';
        },
        /** "last Thu": the same weekday a week ago (UTC). */
        lastWeekday() {
            const weekday = formatDate(new Date(Date.now() - 7 * 86400000), { weekday: 'short', timeZone: 'UTC' });
            return t('admin.growth.lastWeekday', { weekday });
        },
        firstStep() {
            return this.growth?.steps?.[0]?.total ?? 0;
        },
        arrivalsToday() {
            return this.growth?.tiles?.find((tile) => tile.key === 'arrivals')?.today ?? 0;
        },
        sortedSources() {
            const rows = this.sources?.sources ?? [];
            const value = (row) => (this.sortBy === 'day2' ? (row.arrivals ? row.day2 / row.arrivals : -1) : row[this.sortBy] ?? 0);
            // The folded row stays last, whatever the sort.
            return [...rows].sort((a, b) => Number(a.source === 'small') - Number(b.source === 'small') || value(b) - value(a));
        },
        experiment() {
            const report = this.funnel;
            return report?.experiments?.find((e) => e.id === report.experiment) ?? null;
        },
        primaryName() {
            const step = this.funnel?.steps?.find((s) => s.kind === this.experiment?.primary);
            return step ? t(`admin.funnel.step.${step.kind}`) : this.experiment?.primary ?? '';
        },
        primaryArms() {
            return this.funnel?.steps?.find((s) => s.kind === this.experiment?.primary)?.arms ?? [];
        },
        readable() {
            return this.started && !!this.funnel?.enough && !!this.experiment?.readFrom && new Date().toISOString().slice(0, 10) >= this.experiment.readFrom;
        },
        started() {
            const start = this.experiment?.start;
            return !!start && new Date().toISOString().slice(0, 10) >= start;
        },
        /** "Enrolling, day 12 of 28 · 1,480 of 1,500 arrivals per arm · read from Dec 15". */
        experimentLine() {
            const e = this.experiment;
            const today = new Date().toISOString().slice(0, 10);
            const dayOf = (from, to) => Math.round((Date.parse(to) - Date.parse(from)) / 86400000) + 1;
            const arrivals = Object.values(arrivalsByArm(this.funnel));
            const when = !this.started
                ? e.start ? t('admin.growth.startsOn', { date: day(e.start) }) : t('admin.funnel.notStarted')
                : e.enrolling && e.enrolUntil ? t('admin.growth.enrollingDay', { day: dayOf(e.start, today), of: dayOf(e.start, e.enrolUntil) })
                : e.enrolling ? t('admin.funnel.enrollingSince', { date: day(e.start) })
                : t('admin.funnel.enrolledFromTo', { from: day(e.start), to: day(e.enrolUntil) });
            return [
                when,
                this.started && arrivals.length ? t('admin.growth.arrivalsOfPlanned', { arrivals: formatNumber(Math.min(...arrivals)), planned: formatNumber(e.plannedPerArm) }) : null,
                e.readFrom ? t('admin.growth.readFrom', { date: day(e.readFrom) }) : null,
            ]
                .filter(Boolean)
                .join(' · ');
        },
    },
    created() {
        this.load();
    },
    mounted() {
        // Near live on Today: every 30 seconds while the page is in view; caught up on coming back.
        this.ticker = setInterval(() => document.visibilityState === 'visible' && (this.now = Date.now()), TICK_MS);
        this.poller = setInterval(() => document.visibilityState === 'visible' && this.days === 1 && this.refresh(), REFRESH_MS);
        this.onVisible = () => {
            if (document.visibilityState !== 'visible') return;
            this.now = Date.now();
            if (this.days === 1 && this.updatedAt && this.now - this.updatedAt >= REFRESH_MS) this.refresh();
        };
        document.addEventListener('visibilitychange', this.onVisible);
    },
    unmounted() {
        clearInterval(this.ticker);
        clearInterval(this.poller);
        document.removeEventListener('visibilitychange', this.onVisible);
    },
    methods: {
        change,
        isTag: (source) => !SOURCES[source] && source !== 'small',
        sourceName(row) {
            if (row.source === 'small') return t('admin.growth.source.small', { count: row.sources ?? 0 });
            return SOURCES[row.source] ? t(SOURCES[row.source]) : row.source;
        },
        shareOfArrivals(tile) {
            return SHARE_OF_ARRIVALS.includes(tile.key) && this.arrivalsToday ? pct(tile.today / this.arrivalsToday) : '';
        },
        barWidth(total) {
            return this.firstStep ? Math.max(total ? 1 : 0, Math.min(100, (total / this.firstStep) * 100)) : 0;
        },
        /** "75% didn't go on", between steps where the next is a part of the one before (FOLLOWS). */
        didntGoOn(i) {
            const here = this.growth.steps[i];
            const next = this.growth.steps[i + 1];
            const lost = next && FOLLOWS.has(`${here.key}>${next.key}`) ? lostBetween(here.total, next.total) : null;
            return lost === null ? '' : t('admin.growth.didntGoOn', { share: pct(lost) });
        },
        setDays(days) {
            this.days = days;
            this.load();
        },
        async fetchAll() {
            const days = this.days;
            // Sources over a week at least: today's arrivals can't have come back on day 2 yet.
            const [growth, sources, list] = await Promise.all([api.admin.growth.show({ days }), api.admin.sources.show({ days: days === 1 ? 7 : days }), api.admin.funnel.show({ days: 7 })]);
            const running = list.data.experiments?.find((e) => e.enrolling) ?? list.data.experiments?.at(-1) ?? null;
            const funnel = !running ? null : list.data.experiment === running.id ? list.data : (await api.admin.funnel.show({ experiment: running.id })).data;
            return { days, growth: growth.data, sources: sources.data, funnel };
        },
        async load() {
            this.failed = false;
            this.growth = null;
            try {
                const answer = await this.fetchAll();
                if (answer.days !== this.days) return;
                Object.assign(this, { growth: answer.growth, sources: answer.sources, funnel: answer.funnel });
                this.fresh();
            } catch (error) {
                console.warn('Growth did not load', error);
                this.failed = t('admin.growth.failed');
            }
        },
        /** The same numbers again, quietly; a failed refresh says so and keeps them. */
        async refresh() {
            if (this.refreshing || this.failed || !this.growth) return;
            this.refreshing = true;
            try {
                const answer = await this.fetchAll();
                if (answer.days !== this.days) return;
                Object.assign(this, { growth: answer.growth, sources: answer.sources, funnel: answer.funnel });
                this.fresh();
            } catch (error) {
                console.info('Growth did not refresh', error?.message ?? error);
                this.staleSince ??= Date.now();
            } finally {
                this.refreshing = false;
            }
        },
        fresh() {
            this.updatedAt = this.now = Date.now();
            this.staleSince = null;
        },
    },
};
