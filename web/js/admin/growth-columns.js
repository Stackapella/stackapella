// Growth's column charts (docs/growth-compare-spec.md 11.2, F3b to F3d; admin.growth.show `chart` of kind days or weeks):
// 7 days and 4 weeks day by day, This month every day of the month, 12 weeks Monday-to-Sunday weeks. This period's
// slots as ink columns, the period before as a grey tick across each slot, the current slot so far lighter with a dashed
// outline, its projected rest an empty dashed outline with the 45° hatch and a whisker for its range, labelled
// "Projected". The month's days to come are empty slots with a faint baseline; its projection is one dashed line at the
// projected daily rate, never a column per future day. The server decides every number and whether a projection shows;
// a projection is never added into a number shown as fact. And the sign-up strip (F3e): a row of counts under Arrivals,
// lined up with its slots, every period.

import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { roundProjected, ticks } from './growth-chart.js';

const H = 200;
/** The plot's margins; the sign-up strip lines its cells up inside the same ones. */
export const COLUMN_MARGINS = { top: 22, right: 10, bottom: 24, left: 40 };
const M = COLUMN_MARGINS;

const HIDDEN = { early: tk('admin.growth.chart.hidden.early'), tooFew: tk('admin.growth.chart.hidden.tooFew'), noHistory: tk('admin.growth.chart.hidden.noHistory') };
const HIDDEN_FROM = tk('admin.growth.chart.hiddenFrom');
const HEADER = {
    '7d': tk('admin.growth.chart.head.7d'),
    '7dProjected': tk('admin.growth.chart.head.7dProjected'),
    '28d': tk('admin.growth.chart.head.28d'),
    '28dProjected': tk('admin.growth.chart.head.28dProjected'),
    month: tk('admin.growth.chart.head.month'),
    monthProjected: tk('admin.growth.chart.head.monthProjected'),
    '12w': tk('admin.growth.chart.head.12w'),
    '12wProjected': tk('admin.growth.chart.head.12wProjected'),
};
const CAPTION = { '7d': tk('admin.growth.chart.caption.7d'), '28d': tk('admin.growth.chart.caption.28d'), month: tk('admin.growth.chart.caption.month'), '12w': tk('admin.growth.chart.caption.12w') };
const BEFORE = { '7d': tk('admin.growth.chart.before.7d'), '28d': tk('admin.growth.chart.before.28d'), month: tk('admin.growth.chart.before.month'), '12w': tk('admin.growth.chart.before.12w') };
const SO_FAR_NOTE = { days: tk('admin.growth.chart.soFarNote.days'), weeks: tk('admin.growth.chart.soFarNote.weeks') };
const SLOT_COL = { days: tk('admin.growth.chart.col.day'), weeks: tk('admin.growth.chart.col.week') };
/** A week's part of day, from the server's `showsFrom` ("tuesday-evening"). */
const DAY_PART = { morning: tk('admin.growth.chart.dayPart.morning'), afternoon: tk('admin.growth.chart.dayPart.afternoon'), evening: tk('admin.growth.chart.dayPart.evening'), night: tk('admin.growth.chart.dayPart.night') };
const TIP_PROJECTED = { days: tk('admin.growth.chart.tipProjectedBy.days'), weeks: tk('admin.growth.chart.tipProjectedBy.weeks') };
const TIP_COUNT = { soFar: tk('admin.growth.chart.tipSoFar'), done: tk('admin.growth.chart.tipCount') };
const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

const date = (d, options) => formatDate(`${d}T12:00:00Z`, { ...options, timeZone: 'UTC' });
const sum = (list) => (list ?? []).reduce((n, v) => n + (v ?? 0), 0);
const n0 = (v) => formatNumber(Math.round(v));
const np = (v) => formatNumber(roundProjected(v));

/** The month before a date's month, as a date in it ("2026-11-01" gives "2026-10-01"). */
function monthBefore(d) {
    const [y, m] = d.split('-').map(Number);
    return m === 1 ? `${y - 1}-12-01` : `${y}-${String(m - 1).padStart(2, '0')}-01`;
}

/** When a hidden projection will show, in words: a time ("06:00"), a date ("Nov 4"), or a week's day and part of day. */
export function showsFromText(showsFrom) {
    if (!showsFrom) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(showsFrom)) return date(showsFrom, { month: 'short', day: 'numeric' });
    const [day, part] = showsFrom.split('-');
    const i = WEEKDAYS.indexOf(day);
    if (i >= 0) {
        // 2026-11-09 was a Monday: the weekday's name in the page's language.
        const weekday = date(`2026-11-${String(9 + i).padStart(2, '0')}`, { weekday: 'long' });
        return DAY_PART[part] ? t(DAY_PART[part], { weekday }) : weekday;
    }
    return showsFrom;
}

/** A slot's name on the axis and in the table: a weekday for 7 days, a date otherwise ("Nov 3"; a week by its Monday). */
export function slotName(chart, period, i, { long = false } = {}) {
    const d = chart.slots?.[i];
    if (!d) return '';
    if (period === '7d' && !long) return date(d, { weekday: 'short' });
    return date(d, long ? { weekday: 'short', month: 'short', day: 'numeric' } : { month: 'short', day: 'numeric' });
}

/**
 * The period's projected total and its range, for the header: the month's own (`value`, `low`, `high`), or, for the
 * others, the full slots plus the current slot's projection and its range (`periodTotal` is full slots plus `slot.value`).
 */
export function projectedTotal(period, projection) {
    if (!projection?.shown) return null;
    if (period === 'month') return { value: projection.value, low: projection.low, high: projection.high };
    const slot = projection.slot;
    if (!slot || !Number.isFinite(projection.periodTotal)) return null;
    const full = projection.periodTotal - slot.value;
    return { value: projection.periodTotal, low: full + slot.low, high: full + slot.high };
}

export const ColumnChart = {
    props: {
        chart: { type: Object, required: true },
        metric: { type: String, required: true },
        label: { type: String, required: true },
        period: { type: String, required: true },
        cut: { type: String, default: '' },
    },
    data: () => ({ width: 560, hover: null, table: false }),
    setup: () => ({ formatNumber, M, H, n0, np, CAPTION, SLOT_COL, SO_FAR_NOTE }),
    template: `
        <figure class="min-w-0 space-y-2" :data-chart="metric" :data-chart-kind="chart.kind">
            <figcaption class="space-y-0.5">
                <span class="block font-semibold text-cream">{{ label }}</span>
                <span class="block text-sm text-muted" data-chart-header>{{ header }}</span>
            </figcaption>
            <!-- The legend: names only, each with its mark's sample. -->
            <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" data-chart-legend>
                <li class="inline-flex items-center gap-1.5"><svg width="10" height="10" aria-hidden="true"><rect x="0" y="0" width="10" height="10" rx="2" fill="var(--color-cream)" opacity="0.85" /></svg>{{ t('admin.growth.chart.col.thisPeriod') }}</li>
                <li class="inline-flex items-center gap-1.5"><svg width="14" height="6" aria-hidden="true"><line x1="0" y1="3" x2="14" y2="3" stroke="var(--color-muted)" stroke-width="2" /></svg>{{ t(beforeWords, { month: lastMonthName }) }}</li>
                <li v-if="projection" class="inline-flex items-center gap-1.5" data-legend-projected>
                    <svg v-if="period === 'month'" width="18" height="6" aria-hidden="true"><line x1="0" y1="3" x2="18" y2="3" stroke="var(--color-cream)" stroke-width="1.5" stroke-dasharray="5 4" /></svg>
                    <svg v-else width="12" height="12" aria-hidden="true"><rect x="0.5" y="0.5" width="11" height="11" :fill="'url(#' + hatchId + ')'" stroke="var(--color-cream)" stroke-dasharray="2 2" /></svg>{{ t('admin.growth.chart.projectedLegend') }}
                </li>
            </ul>
            <div v-show="!table" ref="box" class="relative" @pointerleave="hover = null">
                <svg :width="width" :height="H" class="block max-w-full select-none overflow-visible" role="img" :aria-label="summary">
                    <defs>
                        <pattern :id="hatchId" width="5" height="5" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><line x1="0" y1="0" x2="0" y2="5" stroke="var(--color-cream)" stroke-width="1.2" opacity="0.6" /></pattern>
                    </defs>
                    <!-- Gridlines and the axes' words: recessive. -->
                    <g class="text-[11px]" fill="var(--color-muted)">
                        <g v-for="v in yTicks" :key="'y' + v">
                            <line :x1="M.left" :x2="width - M.right" :y1="sy(v)" :y2="sy(v)" stroke="var(--color-line)" stroke-width="1" :opacity="v === 0 ? 1 : 0.5" />
                            <text :x="M.left - 6" :y="sy(v) + 3.5" text-anchor="end" class="tabular-nums">{{ formatNumber(v) }}</text>
                        </g>
                        <text v-for="i in xLabels" :key="'x' + i" :x="step < 30 ? x0(i) : cx(i)" :y="H - 6" :text-anchor="step < 30 ? 'start' : 'middle'" data-x-label>{{ slotName(chart, period, i) }}</text>
                    </g>
                    <g v-for="(n, i) in counts" :key="i" :data-slot="i" :data-future="n === null || n === undefined ? '' : null" @pointerenter="hover = i" @pointerdown="hover = hover === i ? null : i">
                        <!-- The slot's hit area: the whole column, taller than the mark. A day still to come in the month
                             draws nothing of its own: empty space, with only last month's tick and the rate line over it. -->
                        <rect :x="x0(i)" :y="M.top" :width="step" :height="H - M.top - M.bottom" fill="transparent" />
                        <template v-if="hasCount(i)">
                            <!-- A finished slot: ink. -->
                            <path v-if="i !== nowSlot" :d="column(i, 0, n)" fill="var(--color-cream)" opacity="0.85" data-column />
                            <!-- The current slot: so far, lighter, its outline dashed. -->
                            <path v-else :d="column(i, 0, n)" fill="var(--color-cream)" fill-opacity="0.5" stroke="var(--color-cream)" stroke-width="1" stroke-dasharray="3 2" data-so-far />
                        </template>
                        <!-- The period before at the same position: a grey tick across the whole slot, wider than the column,
                             on a halo of the panel's colour so it shows on the column's top as well as above it. -->
                        <template v-if="hasBefore(i)">
                            <line :x1="x0(i) + 0.5" :x2="x0(i) + step - 0.5" :y1="sy(before[i])" :y2="sy(before[i])" stroke="var(--color-panel)" stroke-width="6" data-before-halo />
                            <line :x1="x0(i) + 0.5" :x2="x0(i) + step - 0.5" :y1="sy(before[i])" :y2="sy(before[i])" stroke="var(--color-muted)" stroke-width="2.5" data-before-tick />
                        </template>
                    </g>
                    <!-- The current slot's projected rest: hatched, a dashed outline, a whisker for its range, labelled. -->
                    <g v-if="slotProjection" data-projection>
                        <path :d="column(nowSlot, counts[nowSlot] ?? 0, slotProjection.value)" :fill="'url(#' + hatchId + ')'" stroke="var(--color-cream)" stroke-width="1" stroke-dasharray="3 2" data-projected-rest />
                        <g stroke="var(--color-cream)" stroke-width="1.5" data-whisker>
                            <line :x1="cx(nowSlot)" :x2="cx(nowSlot)" :y1="sy(whiskerLow)" :y2="sy(slotProjection.high)" />
                            <line :x1="cx(nowSlot) - whisker" :x2="cx(nowSlot) + whisker" :y1="sy(whiskerLow)" :y2="sy(whiskerLow)" />
                            <line :x1="cx(nowSlot) - whisker" :x2="cx(nowSlot) + whisker" :y1="sy(slotProjection.high)" :y2="sy(slotProjection.high)" />
                        </g>
                        <text :x="cx(nowSlot)" :y="sy(slotProjection.high) - 6" :text-anchor="labelAnchor" class="text-[11px]" fill="var(--color-cream)" stroke="var(--color-panel)" stroke-width="3" paint-order="stroke" data-projected-label>{{ t('admin.growth.chart.projectedShort', { n: np(slotProjection.value) }) }}</text>
                    </g>
                    <!-- The month's days to come: one dashed line at the projected daily rate, labelled once. -->
                    <g v-if="rateLine" data-projection-rate>
                        <line :x1="x0(rateLine.from)" :x2="width - M.right" :y1="sy(rateLine.rate)" :y2="sy(rateLine.rate)" stroke="var(--color-cream)" stroke-width="1.5" stroke-dasharray="5 4" data-line-projected />
                        <text :x="width - M.right" :y="rateLabelY" text-anchor="end" class="text-[11px]" fill="var(--color-cream)" stroke="var(--color-panel)" stroke-width="3" paint-order="stroke" data-projected-label>{{ t('admin.growth.chart.projectedRate', { n: np(rateLine.rate) }) }}</text>
                    </g>
                </svg>
                <div v-if="hover !== null" class="pointer-events-none absolute top-1 max-w-[70%] rounded-lg border border-line bg-panel px-2.5 py-1.5 text-xs text-cream shadow" :style="tipStyle" role="status" data-chart-tip>{{ tip(hover) }}</div>
            </div>
            <slot name="under" />
            <p v-if="nowSlot !== null && !table" class="text-xs text-muted" data-so-far-note>{{ t(SO_FAR_NOTE[chart.kind] ?? SO_FAR_NOTE.days, { cut }) }}</p>
            <p v-if="hiddenLine" class="text-xs text-muted" data-projection-hidden>{{ hiddenLine }}</p>
            <button type="button" class="link inline-flex min-h-6 items-center text-xs" :aria-expanded="table ? 'true' : 'false'" data-chart-table-toggle @click="table = !table">{{ table ? t('admin.growth.chart.showChart') : t('admin.growth.chart.showTable') }}</button>
            <div v-if="table" class="max-h-96 overflow-auto" data-chart-table>
                <table class="w-full text-sm">
                    <caption class="pb-1 text-start text-xs text-muted">{{ t(CAPTION[period] ?? CAPTION['7d'], { what: label, month: monthName }) }}</caption>
                    <thead class="text-xs text-muted"><tr class="border-b border-line">
                        <th scope="col" class="py-1 pe-2 text-start font-semibold">{{ t(SLOT_COL[chart.kind] ?? SLOT_COL.days) }}</th>
                        <th scope="col" class="px-2 py-1 text-end font-semibold">{{ t('admin.growth.chart.col.thisPeriod') }}</th>
                        <th scope="col" class="px-2 py-1 text-end font-semibold">{{ t(beforeWords, { month: lastMonthName }) }}</th>
                        <th v-if="slotProjection" scope="col" class="ps-2 py-1 text-end font-semibold">{{ t('admin.growth.chart.col.projected') }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-line tabular-nums">
                        <tr v-for="(n, i) in counts" :key="i" :data-slot="i">
                            <th scope="row" class="py-1 pe-2 text-start font-normal text-muted">{{ slotName(chart, period, i, { long: true }) }}<span v-if="i === nowSlot"> {{ t('admin.growth.chart.soFar') }}</span></th>
                            <td class="px-2 py-1 text-end text-cream" data-col="now">{{ n === null || n === undefined ? '' : n0(n) }}</td>
                            <td class="px-2 py-1 text-end text-cream" data-col="before">{{ beforeCell(i) }}</td>
                            <td v-if="slotProjection" class="ps-2 py-1 text-end text-muted" data-col="projected">{{ projectedCell(i) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </figure>`,
    computed: {
        m() {
            return this.chart.metrics?.[this.metric] ?? {};
        },
        counts() {
            return this.m.now ?? [];
        },
        before() {
            return this.m.previous ?? [];
        },
        nowSlot() {
            return this.chart.now?.slot ?? null;
        },
        projection() {
            const p = this.m.projection;
            return p?.shown ? p : null;
        },
        slotProjection() {
            const slot = this.projection?.slot;
            return slot && this.nowSlot !== null && slot.index === this.nowSlot ? slot : null;
        },
        /** The whisker's foot: the range's low, held at the count so far (a day can't end below what is already in). */
        whiskerLow() {
            return Math.max(this.slotProjection?.low ?? 0, this.counts[this.nowSlot] ?? 0);
        },
        rateLine() {
            const p = this.projection;
            if (!p || this.period !== 'month' || !Number.isFinite(p.rate)) return null;
            const from = this.counts.findIndex((n) => n === null || n === undefined);
            return from >= 0 ? { from, rate: p.rate } : null;
        },
        /** The rate's label: over its line, or over last month's ticks above it on the days to come, so they don't cross. */
        rateLabelY() {
            const r = this.rateLine;
            if (!r) return 0;
            const ticksOver = this.before.slice(r.from).filter((v) => Number.isFinite(v) && v > 0);
            return this.sy(Math.max(r.rate, ...ticksOver)) - 6;
        },
        hatchId() {
            return `hatch-${this.metric}-${this.period}`;
        },
        beforeWords() {
            return BEFORE[this.period] ?? BEFORE['7d'];
        },
        monthName() {
            return this.chart.slots?.[0] ? date(this.chart.slots[0], { month: 'long' }) : '';
        },
        lastMonthName() {
            return this.chart.slots?.[0] ? date(monthBefore(this.chart.slots[0]), { month: 'long' }) : '';
        },
        step() {
            return (this.width - M.left - M.right) / Math.max(1, this.counts.length);
        },
        /** The space each side of a column: wide enough that the period before's tick reads as wider than the column. */
        gap() {
            return this.step < 12 ? 1 : Math.max(2, Math.round(this.step * 0.12));
        },
        whisker() {
            return Math.max(2, Math.min(5, this.step / 2 - this.gap));
        },
        yMax() {
            const values = [...this.counts, ...this.before, this.slotProjection?.high, this.rateLine?.rate].filter(Number.isFinite);
            return Math.max(1, ...values);
        },
        yTicks() {
            return ticks(this.yMax);
        },
        /** Axis labels that fit: every slot when there's room, else every second; weekly for 4 weeks and the month. */
        xLabels() {
            const n = this.counts.length;
            const every = n > 12 ? 7 : this.step < 40 ? 2 : 1;
            return [...Array(n).keys()].filter((i) => i % every === 0);
        },
        labelAnchor() {
            const x = this.cx(this.nowSlot);
            return x > this.width - 60 ? 'end' : x < M.left + 60 ? 'start' : 'middle';
        },
        soFar() {
            return sum(this.counts);
        },
        beforeTotal() {
            return Number.isFinite(this.m.previousTotal) ? this.m.previousTotal : sum(this.before);
        },
        header() {
            const values = { now: n0(this.soFar), before: n0(this.beforeTotal), month: this.monthName, lastMonth: this.lastMonthName };
            const total = projectedTotal(this.period, this.projection);
            if (total) return t(HEADER[`${this.period}Projected`] ?? HEADER['7dProjected'], { ...values, projected: np(total.value), low: np(total.low), high: np(total.high) });
            return t(HEADER[this.period] ?? HEADER['7d'], values);
        },
        hiddenLine() {
            const p = this.m.projection;
            if (!p || p.shown || !HIDDEN[p.hidden]) return '';
            const from = showsFromText(p.showsFrom);
            // Today's column (a time): the day's own words; the month and the week: when, plainly.
            if (p.hidden === 'early' && p.showsFrom && !/^\d{2}:\d{2}$/.test(p.showsFrom)) return t(HIDDEN_FROM, { from });
            return t(HIDDEN[p.hidden], { from });
        },
        summary() {
            return `${this.label}: ${this.header}`;
        },
        tipStyle() {
            const x = this.cx(this.hover ?? 0);
            return x > this.width / 2 ? { insetInlineEnd: `${this.width - x + 8}px` } : { insetInlineStart: `${x + 8}px` };
        },
    },
    mounted() {
        this.fit();
        this.observer = new ResizeObserver(() => this.fit());
        if (this.$refs.box) this.observer.observe(this.$refs.box);
    },
    beforeUnmount() {
        this.observer?.disconnect();
    },
    methods: {
        slotName,
        fit() {
            const w = this.$refs.box?.clientWidth;
            if (w) this.width = Math.max(260, Math.round(w));
        },
        x0(i) {
            return M.left + i * this.step;
        },
        cx(i) {
            return this.x0(i) + this.step / 2;
        },
        sy(v) {
            const top = this.yTicks.at(-1) || 1;
            return M.top + (1 - v / top) * (H - M.top - M.bottom);
        },
        /** A column from `from` to `to`, its top corners rounded (4 px, 2 when narrow), square on the axis. */
        column(i, from, to) {
            const f = (v) => v.toFixed(1);
            const x = this.x0(i) + this.gap;
            const w = Math.max(1, this.step - 2 * this.gap);
            const y1 = this.sy(from);
            const y0 = this.sy(Math.max(from, to));
            const r = Math.min(this.step < 12 ? 2 : 4, w / 2, Math.max(0, y1 - y0));
            return `M${f(x)} ${f(y1)} L${f(x)} ${f(y0 + r)} Q${f(x)} ${f(y0)} ${f(x + r)} ${f(y0)} L${f(x + w - r)} ${f(y0)} Q${f(x + w)} ${f(y0)} ${f(x + w)} ${f(y0 + r)} L${f(x + w)} ${f(y1)} Z`;
        },
        /** A count for the slot (none for a day still to come). */
        hasCount(i) {
            return Number.isFinite(this.counts[i]);
        },
        /** A tick for the period before: none at 0, where it would only thicken the axis (the table still says 0). */
        hasBefore(i) {
            return Number.isFinite(this.before[i]) && this.before[i] > 0;
        },
        beforeCell(i) {
            const v = this.before[i];
            if (!Number.isFinite(v)) return '';
            return this.m.previousEstimated?.[i] ? t('admin.growth.compare.about', { n: n0(v) }) : n0(v);
        },
        projectedCell(i) {
            if (i !== this.nowSlot || !this.slotProjection) return '';
            const s = this.slotProjection;
            return t('admin.growth.chart.projectedCell', { n: np(s.value), low: np(s.low), high: np(s.high) });
        },
        tip(i) {
            const n = this.counts[i];
            const parts = [slotName(this.chart, this.period, i, { long: true })];
            if (Number.isFinite(n)) parts.push(t(i === this.nowSlot ? TIP_COUNT.soFar : TIP_COUNT.done, { n: n0(n) }));
            if (Number.isFinite(this.before[i])) parts.push(t('admin.growth.chart.tipBefore', { what: t(this.beforeWords, { month: this.lastMonthName }), n: this.beforeCell(i) }));
            if (i === this.nowSlot && this.slotProjection) parts.push(t(TIP_PROJECTED[this.chart.kind] ?? TIP_PROJECTED.days, { n: np(this.slotProjection.value), low: np(this.slotProjection.low), high: np(this.slotProjection.high) }));
            if (!Number.isFinite(n) && this.rateLine && i >= this.rateLine.from) parts.push(t('admin.growth.chart.tipRate', { n: np(this.rateLine.rate) }));
            return parts.join('; ');
        },
    },
};

/**
 * The sign-up strip (spec 11.1, F3e): under Arrivals, a cell per slot lined up with the chart's slots, the count as a
 * digit ("0" muted) and the period before's small beneath it. No bars, no line and no projection: at single digits a shape
 * would be noise. Today's strip is six 4-hour cells. Past 14 slots (4 weeks, This month) a digit a day is too small to read,
 * so each cell sums 7 days from the period's first, spanning their columns (the axis names each block's first day); the
 * block with today in it is so far. A real row of text, so it reads without the table.
 */
export const SignUpStrip = {
    props: {
        chart: { type: Object, required: true },
        period: { type: String, required: true },
        margins: { type: Object, required: true },
    },
    setup: () => ({ n0 }),
    template: `
        <div v-if="cells.length" class="space-y-1" data-signup-strip>
            <p class="flex flex-wrap items-baseline justify-between gap-x-3 text-xs text-muted" :style="inset">
                <span class="font-semibold text-cream">{{ t('admin.growth.chart.strip.title') }}</span>
                <span>{{ t('admin.growth.chart.strip.key') }}</span>
            </p>
            <ol class="grid tabular-nums" :style="{ ...inset, gridTemplateColumns: 'repeat(' + slots + ', minmax(0, 1fr))' }">
                <li v-for="(c, i) in cells" :key="i" class="flex min-w-0 flex-col items-center border-s border-line/60 py-1 text-sm leading-none first:border-s-0" :style="{ gridColumn: 'span ' + c.span }" :data-strip-cell="i" :data-span="c.span" :title="c.words">
                    <span class="sr-only">{{ c.words }}</span>
                    <span aria-hidden="true" :class="c.now === null ? 'text-muted/40' : c.now === 0 ? 'text-muted' : 'font-semibold text-cream'" data-strip-now>{{ c.now === null ? '·' : n0(c.now) }}</span>
                    <span aria-hidden="true" class="mt-1 text-[10px] text-muted" data-strip-before>{{ c.before === null ? '' : n0(c.before) }}</span>
                </li>
            </ol>
        </div>`,
    computed: {
        inset() {
            return { paddingInlineStart: `${this.margins.left}px`, paddingInlineEnd: `${this.margins.right}px` };
        },
        slots() {
            return this.chart.metrics?.signUps?.now?.length ?? 0;
        },
        /** A cell a slot, or past 14 slots a cell a 7 days: the counts summed (null when none of its days has come). */
        cells() {
            const s = this.chart.metrics?.signUps;
            if (!s?.now) return [];
            const before = s.previous ?? [];
            const size = this.slots > 14 ? 7 : 1;
            const total = (list) => (list.some(Number.isFinite) ? list.reduce((n, v) => n + (Number.isFinite(v) ? v : 0), 0) : null);
            const cells = [];
            for (let from = 0; from < this.slots; from += size) {
                const to = Math.min(this.slots, from + size) - 1;
                const now = total(s.now.slice(from, to + 1));
                const b = total(before.slice(from, to + 1));
                cells.push({ span: to - from + 1, now, before: b, words: this.words(from, to, now, b) });
            }
            return cells;
        },
    },
    methods: {
        name(from, to) {
            if (this.chart.kind === 'hours') return t('admin.growth.chart.hourRange', { from: `${String(from * 4).padStart(2, '0')}:00`, to: `${String(from * 4 + 4).padStart(2, '0')}:00` });
            if (from === to) return slotName(this.chart, this.period, from, { long: true });
            return t('admin.growth.chart.hourRange', { from: slotName(this.chart, this.period, from), to: slotName(this.chart, this.period, to) });
        },
        words(from, to, now, before) {
            const parts = [];
            if (now !== null) parts.push(t('admin.growth.chart.strip.count', { n: n0(now) }));
            if (before !== null) parts.push(t('admin.growth.chart.strip.before', { n: n0(before) }));
            return `${this.name(from, to)}: ${parts.join(', ')}`;
        },
    },
};
