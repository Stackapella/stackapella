// Growth's Overview (docs/admin-growth-plan.md, slice 2, as Hillel redrew it on 2026-10-30; contract:
// admin.growth.show, admin.sources.show, admin.funnel.show): the page Hillel opens each morning. Four
// cards of one layout (In the app, Lesson 1 sung, Signed up, Plus started): the number, its share of
// arrivals, then against yesterday and the same weekday last week in labelled rows, up or down said
// with an arrow and a sign as well as colour, and a Low sample tag under 20. How far they got: a row a
// step with its devices and share, and between steps how many didn't continue (or, where the next step
// isn't a part of the one before, how many fewer), the biggest real drop in red and said so. Where they
// came from, always over the last 7 days so day 2 can count, small sources folded into one row so a
// tiny one never stands for a person. And the running experiment. Today so far (UTC), refreshed every
// 30 seconds while in view, like Usage; or 7 or 30 days. Anonymous daily totals only.

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { local } from '../storage.js';
import { change, FEW, LoadFailed, remembers, zoneName } from './common.js';

export { change };
import { ReachChart } from './reach.js';
import { updatedAgo } from './usage.js';
import { ARM_COLORS, arrivalsByArm, lostBetween } from './funnel.js';
import { SiteSectionsCard } from './site-sections.js';
import { FROM_DAYS, PERIODS } from './growth-compare.js';
import { METRICS as METRIC_NAMES, MetricCard, TrackingPanel } from './growth-cards.js';
import { HOUR_MARGINS, HourChart } from './growth-chart.js';
import { COLUMN_MARGINS, ColumnChart, SignUpStrip } from './growth-columns.js';

// Each period's days, for what still reads a day count (the reach chart, and the cards from an older server); 4 weeks and
// This month read as 30 there, 12 weeks as 84 (the people chart a week at a time).
const DAYS = { today: 1, '7d': 7, '28d': 30, month: 30, '12w': 84 };
const PERIOD_NAMES = { today: tk('admin.growth.periods.today'), '7d': tk('admin.growth.periods.7d'), '28d': tk('admin.growth.periods.28d'), month: tk('admin.growth.periods.month'), '12w': tk('admin.growth.periods.12w') };
const BREAK_NOTES = { crawlers: tk('admin.growth.breakNotes.crawlers') };
const REFRESH_MS = 30000; // while the page is in view (a timer for the page, never for audio)
const TICK_MS = 1000;
const TILES = { arrivals: tk('admin.growth.tile.arrivals'), lesson1: tk('admin.growth.tile.lesson1'), signedUp: tk('admin.growth.tile.signedUp'), plusStarted: tk('admin.growth.tile.plusStarted') };
const SHARE_OF_ARRIVALS = ['lesson1', 'signedUp', 'plusStarted'];
const STEPS = { visits: tk('admin.growth.step.visits'), start: tk('admin.growth.step.start'), arrivals: tk('admin.growth.step.arrivals'), lesson1: tk('admin.growth.step.lesson1'), unit1: tk('admin.growth.step.unit1'), signedUp: tk('admin.growth.step.signedUp'), plusStarted: tk('admin.growth.step.plusStarted') };
// Where the next step is a part of the one before, so a loss between them is real: arrivals also come
// straight to the app, and signing up can come before Unit 1.
const FOLLOWS = new Set(['visits>start', 'arrivals>lesson1', 'lesson1>unit1', 'signedUp>plusStarted']);
const SOURCES = { none: tk('admin.growth.source.none'), harmony: tk('admin.growth.source.harmony'), profile: tk('admin.growth.source.profile'), coach: tk('admin.growth.source.coach'), other: tk('admin.growth.source.other') };
const COLUMNS = { arrivals: tk('admin.growth.col.arrivals'), lesson1: tk('admin.growth.col.lesson1'), day2: tk('admin.growth.col.day2Return') };
const DROPS = { real: tk('admin.growth.didntContinue'), fewer: tk('admin.growth.fewer') };
const SOURCE_DAYS = 7; // the Overview's sources: always a week, so day-2 returns have had time to come in

const pct = (share) => `${formatNumber(Math.round(share * 1000) / 10)}%`;
const day = (date) => (date ? formatDate(`${date}T12:00:00Z`, { month: 'short', day: 'numeric' }) : '');


/** A campaign tag as written ("hn"), never translated; our own groupings and the fold are words. */
// A free tool's try-the-app link (?ref=tool-tuner) reads as words, "Tools: tuner" (docs/tools-plan.md).
// Named sources that read as words: a tool's link (tool-tuner: "Tools: tuner"), an embed on another site
// (embed-x.org: "Embed: x.org") and the song part pages (part-page).
const TOOL = 'tool-';
const EMBED = 'embed-';
const PART_PAGE = 'part-page';
export const isTag = (source) => !SOURCES[source] && source !== 'small' && source !== PART_PAGE && !String(source).startsWith(TOOL) && !String(source).startsWith(EMBED);

export function sourceName(row) {
    if (row.source === 'small') return t('admin.growth.source.small', { count: row.sources ?? 0 });
    if (String(row.source).startsWith(TOOL)) return t('admin.growth.source.tool', { name: row.source.slice(TOOL.length) });
    if (String(row.source).startsWith(EMBED)) return t('admin.growth.source.embed', { name: row.source.slice(EMBED.length) });
    if (row.source === PART_PAGE) return t('admin.growth.source.partPage');
    return SOURCES[row.source] ? t(SOURCES[row.source]) : row.source;
}

/** The Overview's shorter name for no tag, with what it means beneath ("No tag", "Search and direct"). */
const SHORT = { none: [tk('admin.growth.source.noneName'), tk('admin.growth.source.noneHint')] };

/** Sources sorted by a column, the folded row last whatever the sort; `rate` sorts by share of arrivals. */
export function sortSources(rows, by, rate = false) {
    const value = (row) => (rate ? (row.arrivals ? row[by] / row.arrivals : -1) : row[by] ?? 0);
    return [...rows].sort((a, b) => Number(a.source === 'small') - Number(b.source === 'small') || value(b) - value(a));
}

export const OverviewSection = {
    mixins: [remembers('growth', { period: PERIODS, sortBy: Object.keys(COLUMNS) })],
    components: { LoadFailed, ReachChart, SiteSectionsCard, MetricCard, TrackingPanel, HourChart, ColumnChart, SignUpStrip },
    props: { me: { type: Object, required: true } },
    data: () => ({ period: 'today', showHow: false, growth: null, sources: null, funnel: null, failed: false, sortBy: 'day2', updatedAt: null, now: Date.now(), refreshing: false, staleSince: null }),
    setup: () => ({ zoneName, PERIODS, PERIOD_NAMES, METRIC_NAMES, FEW, TILES, STEPS, COLUMNS, SHORT, ARM_COLORS, formatNumber, pct, day, navigate, HOUR_MARGINS, COLUMN_MARGINS }),
    template: `
        <section class="space-y-6" aria-labelledby="growth-h" data-growth>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <h1 id="growth-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.growth.title') }}</h1>
                    <p class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted">
                        <span v-if="growth?.period" data-growth-clock>{{ t('admin.growth.clock', { zone: zoneName(growth.timezone), cut: growth.period.cut }) }}</span>
                        <span v-else>{{ days === 1 ? t('admin.growth.introToday', { zone: zoneName(growth?.timezone) }) : t('admin.growth.introDays', { from: day(growth?.from), to: day(growth?.to), zone: zoneName(growth?.timezone) }) }}</span>
                        <span v-if="updatedAt" class="inline-flex flex-wrap items-center gap-x-2" data-growth-updated>
                            <span class="hidden sm:inline" aria-hidden="true">·</span>
                            <span class="inline-flex items-center gap-1.5"><span class="size-1.5 rounded-full" :class="staleSince ? 'bg-warn' : 'bg-good'" aria-hidden="true"></span>{{ staleSince ? t('admin.usage.notUpdated', { when: updated }) : t('admin.usage.updated', { when: updated }) }}</span>
                            <button type="button" class="btn-secondary btn-sm" :aria-busy="refreshing ? 'true' : null" :disabled="refreshing" @click="refresh"><span v-if="refreshing" class="spinner"></span>{{ t('admin.usage.refresh') }}</button>
                        </span>
                    </p>
                </div>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!growth" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <!-- The period first (spec 4.1): Today, 7 days, 4 weeks, This month, 12 weeks. It scopes the charts and the cards.
                     On a phone, a compact menu (Hillel, 2026-11-09: five options stacked pushed the charts down); from sm up,
                     the segmented control. The seg sits in its own wrapper: its stacked form is display grid, unlayered. -->
                <div class="sm:hidden">
                    <select class="input w-auto min-w-40 font-semibold" :aria-label="t('admin.growth.periods.label')" :value="shownPeriod" data-growth-period-select @change="setPeriod($event.target.value)">
                        <option v-for="p in PERIODS" :key="p" :value="p">{{ t(PERIOD_NAMES[p]) }}</option>
                    </select>
                </div>
                <div class="hidden sm:block">
                    <div class="seg flex w-fit max-w-full overflow-x-auto" role="radiogroup" :aria-label="t('admin.growth.periods.label')" data-growth-periods>
                        <button v-for="p in PERIODS" :key="p" type="button" role="radio" :aria-checked="shownPeriod === p" class="seg-option whitespace-nowrap" :data-period="p" @click="setPeriod(p)">{{ t(PERIOD_NAMES[p]) }}</button>
                    </div>
                </div>

                <!-- The charts at the top, under the period they follow (Hillel, 2026-11-09: "move the new awesome charts to
                     the top"), with the lines that qualify the period: early in the day, a break in the counts. -->
                <template v-if="growth.metrics">
                    <p v-if="growth.period?.early" class="text-sm text-muted" data-growth-early>{{ t('admin.growth.early') }}</p>
                    <p v-if="breakLine" class="text-sm text-muted" data-growth-break>{{ breakLine }}</p>
                    <!-- So far, against before (spec 4.4 and 11): Visits and Arrivals as two small multiples, each its own
                         y-axis from 0 (never one chart with two scales). Today hour by hour, with the projection; the other
                         periods day by day or week by week as columns. Sign-ups as a strip of counts under Arrivals. -->
                    <section v-if="['hours', 'days', 'weeks'].includes(growth.chart?.kind)" class="card space-y-4 p-5 md:p-6" aria-labelledby="growth-chart-h" data-growth-charts :data-chart-period="shownPeriod">
                        <h2 id="growth-chart-h" class="text-lg font-bold text-cream">{{ t('admin.growth.chart.title') }}</h2>
                        <div v-if="growth.chart.kind === 'hours'" class="grid gap-6 lg:grid-cols-2">
                            <HourChart :chart="growth.chart" metric="visits" :label="t(METRIC_NAMES.visits)" :cut="growth.period?.cut ?? ''" :weekday="compareContext.weekday" />
                            <HourChart :chart="growth.chart" metric="arrivals" :label="t(METRIC_NAMES.arrivals)" :cut="growth.period?.cut ?? ''" :weekday="compareContext.weekday">
                                <template #under><SignUpStrip :chart="growth.chart" :period="shownPeriod" :margins="HOUR_MARGINS" /></template>
                            </HourChart>
                        </div>
                        <div v-else :key="shownPeriod" class="grid gap-6 lg:grid-cols-2">
                            <ColumnChart :chart="growth.chart" metric="visits" :label="t(METRIC_NAMES.visits)" :period="shownPeriod" :cut="growth.period?.cut ?? ''" />
                            <ColumnChart :chart="growth.chart" metric="arrivals" :label="t(METRIC_NAMES.arrivals)" :period="shownPeriod" :cut="growth.period?.cut ?? ''">
                                <template #under><SignUpStrip :chart="growth.chart" :period="shownPeriod" :margins="COLUMN_MARGINS" /></template>
                            </ColumnChart>
                        </div>
                    </section>
                </template>

                <!-- How we're tracking (docs/growth-compare-spec.md, 3): the last 7 days against a usual week, whatever the period. -->
                <TrackingPanel v-if="growth.tracking" :tracking="growth.tracking" :cut="growth.period?.cut ?? ''" />

                <!-- The fair cards (spec 4.2): each cut at the same clock time as what it's set against, and said in words
                     before any percentage. From a server without them, the cards of before. -->
                <template v-if="growth.metrics">
                    <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" data-growth-metrics>
                        <MetricCard v-for="m in growth.metrics" :key="m.key" :metric="m" :period="growth.period" :context="compareContext" />
                    </ul>
                    <div>
                        <button type="button" class="link inline-flex min-h-6 items-center text-sm" :aria-expanded="showHow ? 'true' : 'false'" aria-controls="growth-how" data-growth-how-link @click="showHow = !showHow">{{ t('admin.growth.howLink') }}</button>
                        <p v-show="showHow" id="growth-how" class="card mt-2 max-w-prose p-4 text-sm text-muted" data-growth-how>{{ t('admin.growth.howText', { cut: growth.period?.cut ?? '' }) }}</p>
                    </div>
                </template>
                <ul v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" data-growth-tiles>
                    <li v-for="tile in growth.tiles" :key="tile.key" class="card flex flex-col p-4 sm:p-5" :data-tile="tile.key">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm text-muted">{{ TILES[tile.key] ? t(TILES[tile.key]) : tile.key }}</p>
                            <span v-if="lowSample(tile)" class="shrink-0 rounded-md border border-line px-1.5 py-0.5 text-xs text-muted" data-few>{{ t('admin.growth.lowSample') }}</span>
                        </div>
                        <p class="mt-2 flex flex-wrap items-baseline gap-x-2.5">
                            <span class="text-3xl font-extrabold tabular-nums text-cream sm:text-4xl">{{ formatNumber(count(tile)) }}</span>
                            <span v-if="shareOfArrivals(tile)" class="text-sm text-muted" data-share><b class="font-semibold text-cream">{{ shareOfArrivals(tile) }}</b> {{ t('admin.growth.ofArrivals') }}</span>
                        </p>
                        <div class="mt-auto pt-4"><dl class="space-y-1 border-t border-line pt-3 text-sm">
                            <div v-for="row in tileRows(tile)" :key="row.label" class="flex items-center justify-between gap-3" data-change :data-dir="row.dir">
                                <dt class="text-muted">{{ row.label }}</dt>
                                <dd class="font-semibold tabular-nums" :class="row.dir === 'up' ? 'text-up' : row.dir === 'down' ? 'text-down' : 'text-muted'"><span v-if="row.dir !== 'same'" class="me-1" aria-hidden="true">{{ row.dir === 'up' ? '↑' : '↓' }}</span>{{ row.text }}</dd>
                            </div>
                        </dl></div>
                    </li>
                </ul>

                <!-- People using the app (Hillel: signed in against guests), week by week against the 12 weeks before. -->
                <ReachChart :days="days" />

                <div class="grid items-start gap-6 lg:grid-cols-[1.05fr_1fr]">
                    <!-- How far they got: a row a step, and between steps how many didn't continue. -->
                    <section class="card min-w-0 scroll-mt-36 p-5 md:p-6" aria-labelledby="growth-steps-h" data-growth-steps>
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h2 id="growth-steps-h" class="text-lg font-bold text-cream">{{ days === 1 ? t('admin.growth.stepsToday') : t('admin.growth.steps') }}</h2>
                                <p class="text-sm text-muted">{{ t('admin.growth.stepsSub') }}</p>
                            </div>
                            <a href="/admin/funnel" class="link tap-44 inline-flex min-h-6 items-center gap-1 text-sm" @click.prevent="navigate('/admin/funnel')">{{ t('admin.growth.openFunnel') }}<span aria-hidden="true" class="rtl:-scale-x-100">→</span></a>
                        </div>
                        <div class="-mx-1 mt-4 overflow-x-auto px-1">
                            <table class="w-full text-sm">
                                <thead class="text-xs font-semibold uppercase tracking-wider text-muted">
                                    <tr>
                                        <th scope="col" class="pb-2 text-start font-semibold">{{ t('admin.growth.col.step') }}</th>
                                        <th scope="col" class="pb-2"><span class="sr-only">{{ t('admin.growth.col.bar') }}</span></th>
                                        <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.growth.col.devices') }}</th>
                                        <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.growth.col.share') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="(s, i) in growth.steps" :key="s.key">
                                        <tr :data-step="s.key">
                                            <th scope="row" class="h-9 pe-3 text-start font-normal text-cream sm:whitespace-nowrap">{{ STEPS[s.key] ? t(STEPS[s.key]) : s.key }}</th>
                                            <td class="w-full"><div class="h-2.5 overflow-hidden rounded-full bg-raised" aria-hidden="true"><div class="h-full rounded-full bg-accent" :style="{ width: barWidth(s.total) + '%' }"></div></div></td>
                                            <td class="ps-3 text-end font-bold tabular-nums text-cream">{{ formatNumber(s.total) }}</td>
                                            <td class="ps-3 text-end tabular-nums text-muted">{{ firstStep ? pct(s.total / firstStep) : '-' }}</td>
                                        </tr>
                                        <tr v-if="drops[i]" class="text-xs" data-growth-lost :data-biggest="drops[i].biggest ? '' : null">
                                            <td></td>
                                            <td colspan="3" class="pb-1.5" :class="drops[i].biggest ? 'font-semibold text-bad' : 'text-muted'"><span aria-hidden="true">↓ </span>{{ drops[i].words }}<span class="text-muted"> · {{ t('admin.growth.devicesCount', { count: drops[i].devices }) }}</span><span v-if="drops[i].biggest" class="sr-only"> {{ t('admin.growth.biggestDrop') }}</span></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <div class="min-w-0 space-y-6">
                        <!-- Where they came from: always the last 7 days; small sources folded, so a tiny one never stands for a person. -->
                        <section class="card scroll-mt-36 p-5 md:p-6" aria-labelledby="growth-sources-h" data-growth-sources>
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <h2 id="growth-sources-h" class="text-lg font-bold text-cream">{{ t('admin.growth.sources') }}</h2>
                                    <p class="text-sm text-muted">{{ t('admin.growth.sourcesSub') }}</p>
                                </div>
                                <span class="shrink-0 rounded-md border border-warn/50 px-2 py-0.5 text-xs font-semibold text-warn" data-growth-last7>{{ t('admin.growth.last7') }}</span>
                            </div>
                            <p v-if="!sources" class="mt-4 text-sm text-muted" role="status">{{ t('admin.loading') }}</p>
                            <div v-else class="-mx-1 mt-4 overflow-x-auto px-1">
                                <table class="w-full text-sm">
                                    <thead class="text-xs font-semibold uppercase tracking-wider text-muted">
                                        <tr class="border-b border-line">
                                            <th scope="col" class="pb-2 pe-2 text-start font-semibold">{{ t('admin.growth.col.source') }}</th>
                                            <th v-for="(label, key) in COLUMNS" :key="key" scope="col" class="pb-2 ps-3 text-end font-semibold" :aria-sort="sortBy === key ? 'descending' : 'none'">
                                                <button type="button" class="tap-44 inline-flex min-h-6 items-center gap-1 uppercase tracking-wider" :data-sort="key" @click="sortBy = key">{{ t(label) }}<span v-if="sortBy === key" aria-hidden="true">↓</span></button>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line">
                                        <tr v-for="row in sortedSources" :key="row.source" :data-source="row.source">
                                            <th scope="row" class="py-2.5 pe-2 text-start font-normal text-cream">
                                                <template v-if="SHORT[row.source]">{{ t(SHORT[row.source][0]) }}<span class="block text-xs text-muted">{{ t(SHORT[row.source][1]) }}</span></template>
                                                <span v-else :translate="isTag(row.source) ? 'no' : null">{{ sourceName(row) }}</span>
                                            </th>
                                            <td class="ps-3 text-end tabular-nums text-cream">{{ formatNumber(row.arrivals) }}</td>
                                            <td class="ps-3 text-end tabular-nums text-cream">{{ formatNumber(row.lesson1) }}</td>
                                            <td class="ps-3">
                                                <span class="flex items-center justify-end gap-2.5">
                                                    <span class="hidden h-1.5 w-12 shrink-0 overflow-hidden rounded-full bg-raised sm:block" aria-hidden="true"><span class="block h-full rounded-full bg-accent" :style="{ width: (row.arrivals ? Math.min(100, (row.day2 / row.arrivals) * 100) : 0) + '%' }"></span></span>
                                                    <b class="w-12 text-end tabular-nums text-cream">{{ row.arrivals ? pct(row.day2 / row.arrivals) : '-' }}</b>
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4 flex flex-wrap items-end justify-between gap-2">
                                <p class="max-w-prose text-xs text-muted">{{ t('admin.growth.sourcesFoot') }}</p>
                                <a href="/admin/sources" class="link tap-44 inline-flex min-h-6 items-center gap-1 text-sm" data-growth-all-sources @click.prevent="navigate('/admin/sources')">{{ t('admin.growth.allSources') }}<span aria-hidden="true" class="rtl:-scale-x-100">→</span></a>
                            </div>
                        </section>

                        <!-- The whole public site's visits by section, the last 7 days (admin/site-sections.js). -->
                        <SiteSectionsCard :today="growth?.to ?? ''" :sections="growth?.sections ?? null" />

                        <!-- The running experiment, in one card; the Funnel has the rest. -->
                        <section v-if="experiment" class="card space-y-3 p-5 md:p-6" aria-labelledby="growth-exp-h" data-growth-experiment>
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 id="growth-exp-h" class="text-lg font-bold text-cream">{{ t('admin.growth.experiment', { id: experiment.id }) }}</h2>
                                    <p class="text-sm text-muted">{{ experimentLine }}</p>
                                </div>
                                <span class="shrink-0 rounded-md border border-line px-2 py-0.5 text-xs text-muted" data-growth-readable>{{ !started ? t('admin.funnel.notStarted') : readable ? t('admin.growth.ready') : t('admin.growth.notReady') }}</span>
                            </div>
                            <p class="text-sm text-cream">{{ t('admin.growth.primaryStep', { step: primaryName }) }}</p>
                            <div v-if="started" class="space-y-1.5">
                                <div v-for="a in primaryArms" :key="a.arm" class="flex items-center gap-2">
                                    <span class="w-3 text-xs font-bold text-cream" translate="no">{{ a.arm.toUpperCase() }}</span>
                                    <div class="h-2.5 min-w-0 flex-1 overflow-hidden rounded-full bg-raised" aria-hidden="true"><div class="h-full rounded-full" :style="{ width: Math.max(1, (a.rate ?? 0) * 100) + '%', background: ARM_COLORS[a.arm] ?? ARM_COLORS.all }"></div></div>
                                    <span class="w-14 text-end text-sm tabular-nums text-cream">{{ pct(a.rate ?? 0) }}</span>
                                </div>
                            </div>
                            <a href="/admin/funnel" class="link tap-44 inline-flex min-h-6 items-center gap-1 text-sm" @click.prevent="navigate('/admin/funnel')">{{ t('admin.growth.openFunnel') }}<span aria-hidden="true" class="rtl:-scale-x-100">→</span></a>
                        </section>
                    </div>
                </div>
            </template>
        </section>`,
    computed: {
        /** The pill that's on: the period the cards answer for (the server's), so the two always agree; the asked one while it loads. */
        shownPeriod() {
            return this.growth?.period?.key ?? this.period;
        },
        /** The period's days, for what reads a day count. */
        days() {
            return DAYS[this.period] ?? 1;
        },
        /** What the comparison rows' names need: the cut, today's weekday (the report zone's), the window before. */
        compareContext() {
            const g = this.growth;
            const to = g?.period?.to;
            const usual = g?.comparisons?.find((c) => c.key === 'usual');
            return {
                cut: g?.period?.cut ?? '',
                weekday: to ? formatDate(`${to}T12:00:00Z`, { weekday: 'long', timeZone: 'UTC' }) : '',
                previous: g?.comparisons?.find((c) => c.key === 'previous') ?? {},
                available: usual?.available ?? 4,
            };
        },
        /** A change in counting between the window before and now (spec 6): said once, above the cards. */
        breakLine() {
            const g = this.growth;
            const from = g?.comparisons?.find((c) => c.key === 'previous')?.from;
            const crossing = (g?.breaks ?? []).find((b) => from && b.date > from && b.date <= (g.period?.to ?? b.date));
            if (!crossing) return '';
            return t('admin.growth.breakLine', { date: day(crossing.date), note: BREAK_NOTES[crossing.note] ? t(BREAK_NOTES[crossing.note]) : crossing.note });
        },
        updated() {
            return this.updatedAt ? updatedAgo(this.updatedAt, this.now) : '';
        },
        /** The same weekday a week ago (UTC), for the cards' second row. */
        lastWeekday() {
            return formatDate(new Date(Date.now() - 7 * 86400000), { weekday: 'short', timeZone: 'UTC' });
        },
        /**
         * Between each step and the next: how many didn't continue (FOLLOWS: the next is a part of the
         * one before), or how many fewer (it isn't, so it's not a loss); the biggest real drop marked.
         */
        drops() {
            const steps = this.growth?.steps ?? [];
            const drops = steps.map((here, i) => {
                const next = steps[i + 1];
                const lost = next ? lostBetween(here.total, next.total) : null;
                if (lost === null) return null;
                const real = FOLLOWS.has(`${here.key}>${next.key}`);
                return { lost, real, devices: here.total - next.total, words: t(real ? DROPS.real : DROPS.fewer, { share: pct(lost) }) };
            });
            const biggest = Math.max(0, ...drops.filter((d) => d?.real).map((d) => d.lost));
            return drops.map((d) => d && { ...d, biggest: d.real && biggest > 0 && d.lost === biggest });
        },
        firstStep() {
            return this.growth?.steps?.[0]?.total ?? 0;
        },
        arrivals() {
            const tile = this.growth?.tiles?.find((t) => t.key === 'arrivals');
            return tile ? this.count(tile) : 0;
        },
        sortedSources() {
            return sortSources(this.sources?.sources ?? [], this.sortBy, this.sortBy === 'day2');
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
        // A day count remembered from before the periods (1, 7, 30, 84 days): 30 days is 4 weeks now (spec section 1).
        if (!local.get('stackapella.admin.growth.period')) {
            const before = FROM_DAYS[local.get('stackapella.admin.growth.days')];
            if (before) this.period = before;
        }
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
        isTag,
        sourceName,
        /** A card's number: the period's total (admin.growth.show's `current`), or today's from an older server. */
        count: (tile) => tile.current ?? tile.today,
        lowSample: (tile) => tile.lowSample ?? (tile.current ?? tile.today) < FEW,
        /**
         * A card's rows. Today: against yesterday and the same weekday last week. Seven days: against
         * the seven before (a week back is the same days). Thirty: against the thirty before, and the
         * thirty ending a week ago.
         */
        tileRows(tile) {
            const now = this.count(tile);
            const before = tile.previous ?? tile.yesterday;
            const weekAgo = tile.weekAgo ?? tile.lastWeek;
            if (this.days === 1) return [{ label: t('admin.growth.vsYesterday'), ...change(now, before) }, { label: t('admin.growth.vsLastWeekday', { weekday: this.lastWeekday }), ...change(now, weekAgo) }];
            const rows = [{ label: this.days === 84 ? t('admin.growth.vsWeeksBefore') : t('admin.growth.vsDaysBefore', { count: this.days }), ...change(now, before) }];
            if (this.days !== 7) rows.push({ label: t('admin.growth.vsWeekEarlier'), ...change(now, weekAgo) });
            return rows;
        },
        shareOfArrivals(tile) {
            return SHARE_OF_ARRIVALS.includes(tile.key) && this.arrivals ? pct(this.count(tile) / this.arrivals) : '';
        },
        barWidth(total) {
            return this.firstStep ? Math.max(total ? 1 : 0, Math.min(100, (total / this.firstStep) * 100)) : 0;
        },
        setPeriod(period) {
            this.period = period;
            this.load();
        },
        async fetchAll() {
            const days = this.days;
            const period = this.period;
            // Sources always over the last week: today's arrivals can't have come back on day 2 yet.
            const [growth, sources, list] = await Promise.all([api.admin.growth.show({ period, days }), api.admin.sources.show({ days: SOURCE_DAYS }), api.admin.funnel.show({ days: 7 })]);
            const running = list.data.experiments?.find((e) => e.enrolling) ?? list.data.experiments?.at(-1) ?? null;
            const funnel = !running ? null : list.data.experiment === running.id ? list.data : (await api.admin.funnel.show({ experiment: running.id })).data;
            return { period, growth: growth.data, sources: sources.data, funnel };
        },
        async load() {
            this.failed = false;
            this.growth = null;
            try {
                const answer = await this.fetchAll();
                if (answer.period !== this.period) return;
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
                if (answer.period !== this.period) return;
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
