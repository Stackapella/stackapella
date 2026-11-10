// Growth's "So far, against before" charts (docs/growth-compare-spec.md 4.4 and 11; admin.growth.show `chart`): Today hour
// by hour as running totals, today in ink to now, yesterday a grey dashed line, a usual day as a grey band with its mean,
// and the projection to midnight a dotted ink line in a fan, labelled "Projected" at the mark and in the legend. The server
// decides every number and whether a projection shows (`projection.hidden` gives the reason line); the page only draws.
// Blue and orange stay out of charts, so a chart never reads as a verdict. Each chart has a table view, the same numbers.
// Inline SVG, no library; the plot measures its own width so text stays its real size.

import { formatNumber, t, tk } from '../i18n.js';

const H = 220; // the plot's height in px
const M = { top: 18, right: 14, bottom: 26, left: 40 };
/** The plot's margins; the sign-up strip lines its cells up inside the same ones. */
export const HOUR_MARGINS = M;
const HOURS = 24;
const HIDDEN = { early: tk('admin.growth.chart.hidden.early'), tooFew: tk('admin.growth.chart.hidden.tooFew'), noHistory: tk('admin.growth.chart.hidden.noHistory') };

/** Running totals of per-slot counts, null where the count is null (after now). */
export function runningTotals(counts = []) {
    let sum = 0;
    return counts.map((n) => {
        if (n === null || n === undefined) return null;
        sum += n;
        return sum;
    });
}

/** Rounded as the spec says a projection is: whole under 1,000, to the nearest 10 above. */
export function roundProjected(n) {
    if (!Number.isFinite(n)) return n;
    return Math.abs(n) >= 1000 ? Math.round(n / 10) * 10 : Math.round(n);
}

/** Three or four round gridline values from 0 up to just above `max`. */
export function ticks(max) {
    if (!(max > 0)) return [0, 1];
    const raw = max / 3;
    const pow = 10 ** Math.floor(Math.log10(raw));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * pow).find((s) => s >= raw) ?? 10 * pow;
    const out = [];
    for (let v = 0; v <= max + step * 0.001; v += step) out.push(Math.round(v * 100) / 100);
    if (out.at(-1) < max) out.push(out.at(-1) + step);
    return out;
}

/** The hour chart's series at the end of each hour (x = hour boundary 1..24), from 0 at midnight. */
export function hourSeries(chart, key) {
    const m = chart?.metrics?.[key];
    if (!m) return null;
    const nowSlot = chart.now?.slot ?? 0;
    const fraction = chart.now?.fraction ?? 0;
    const today = runningTotals(m.now);
    const before = runningTotals(m.previous);
    const soFar = today[nowSlot] ?? today.filter((v) => v !== null).at(-1) ?? 0;
    // Today: midnight, the end of each finished hour, then now (the slot's fraction through it).
    const todayPts = [[0, 0], ...today.slice(0, nowSlot).map((v, i) => [i + 1, v]), [nowSlot + fraction, soFar]];
    const beforePts = [[0, 0], ...before.map((v, i) => [i + 1, v ?? null]).filter(([, v]) => v !== null)];
    const usual = m.usual ? { low: [[0, 0], ...m.usual.low.map((v, i) => [i + 1, v])], high: [[0, 0], ...m.usual.high.map((v, i) => [i + 1, v])], mean: [[0, 0], ...m.usual.mean.map((v, i) => [i + 1, v])], available: m.usual.available } : null;
    const p = m.projection;
    // The path's values run from the now slot's end to midnight: drawn from the now dot. A running total can't end below
    // what is already in, so the fan's low edge (low x the usual shape) is held at the count so far until it rises past it.
    const along = (arr) => [[nowSlot + fraction, soFar], ...(arr ?? []).map((v, j) => [nowSlot + j + 1, Math.max(v, soFar)])];
    const projection = p?.shown && p.path ? { mean: along(p.path.mean), lowPath: along(p.path.low), highPath: along(p.path.high), value: p.value, low: p.low, high: p.high, share: p.share } : null;
    return { today: todayPts, before: beforePts, usual, projection, soFar, beforeTotal: m.previousTotal ?? before.filter((v) => v !== null).at(-1) ?? 0, hidden: p && !p.shown ? p.hidden : null, showsFrom: p?.showsFrom ?? null, perHour: m.now, beforePerHour: m.previous, estimated: m.previousEstimated ?? [] };
}

/** "M x y L x y ..." for points in data space through the scales. */
const path = (pts, sx, sy) => pts.map(([x, y], i) => `${i ? 'L' : 'M'}${sx(x).toFixed(1)} ${sy(y).toFixed(1)}`).join(' ');
const band = (low, high, sx, sy) => `${path(low, sx, sy)} ${[...high].reverse().map(([x, y]) => `L${sx(x).toFixed(1)} ${sy(y).toFixed(1)}`).join(' ')} Z`;

/** Today's chart, hour by hour (spec 4.4 and 11.2). */
export const HourChart = {
    props: {
        chart: { type: Object, required: true },
        metric: { type: String, required: true },
        label: { type: String, required: true },
        cut: { type: String, default: '' },
        weekday: { type: String, default: '' },
        tableKey: { type: String, default: '' },
    },
    data: () => ({ width: 560, hover: null, pinned: null, table: false }),
    setup: () => ({ formatNumber, M, H, roundProjected }),
    template: `
        <figure class="min-w-0 space-y-2" :data-chart="metric">
            <figcaption class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                <span class="font-semibold text-cream">{{ label }}</span>
                <span class="text-sm text-muted" data-chart-header>{{ header }}</span>
            </figcaption>
            <!-- The legend: names only, each with its mark's sample. -->
            <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" data-chart-legend>
                <li class="inline-flex items-center gap-1.5"><svg width="18" height="6" aria-hidden="true"><line x1="0" y1="3" x2="18" y2="3" stroke="var(--color-cream)" stroke-width="2" /></svg>{{ t('admin.growth.chart.today') }}</li>
                <li class="inline-flex items-center gap-1.5"><svg width="18" height="6" aria-hidden="true"><line x1="0" y1="3" x2="18" y2="3" stroke="var(--color-muted)" stroke-width="1.5" stroke-dasharray="4 3" /></svg>{{ t('admin.growth.chart.yesterday') }}<span v-if="anyEstimated"> {{ t('admin.growth.chart.estimatedByHour') }}</span></li>
                <li v-if="s.usual" class="inline-flex items-center gap-1.5"><svg width="18" height="8" aria-hidden="true"><rect x="0" y="0" width="18" height="8" rx="2" fill="var(--color-line)" opacity="0.5" /></svg>{{ t('admin.growth.chart.usual', { weekday }) }}</li>
                <li v-if="s.projection" class="inline-flex items-center gap-1.5" data-legend-projected><svg width="18" height="6" aria-hidden="true"><line x1="0" y1="3" x2="18" y2="3" stroke="var(--color-cream)" stroke-width="2" stroke-dasharray="1.5 3" stroke-linecap="round" /></svg>{{ t('admin.growth.chart.projectedLegend') }}</li>
            </ul>
            <div v-show="!table" ref="box" class="relative" @pointerleave="hover = pinned">
                <svg :width="width" :height="H" class="block max-w-full touch-none select-none overflow-visible" role="img" :aria-label="summary" @pointermove="point" @pointerdown="pin">
                    <!-- Gridlines and the y-axis's numbers: recessive. -->
                    <g class="text-[11px]" fill="var(--color-muted)">
                        <g v-for="v in yTicks" :key="'y' + v">
                            <line :x1="M.left" :x2="width - M.right" :y1="sy(v)" :y2="sy(v)" stroke="var(--color-line)" stroke-width="1" :opacity="v === 0 ? 1 : 0.5" />
                            <text :x="M.left - 6" :y="sy(v) + 3.5" text-anchor="end" class="tabular-nums">{{ formatNumber(v) }}</text>
                        </g>
                        <text v-for="h in [0, 6, 12, 18, 24]" :key="'x' + h" :x="sx(h)" :y="H - 6" text-anchor="middle" class="tabular-nums">{{ String(h).padStart(2, '0') }}:00</text>
                    </g>
                    <!-- A usual day: its range a grey band, its mean a thin grey line. -->
                    <g v-if="s.usual" data-usual-band>
                        <path :d="band(s.usual.low, s.usual.high)" fill="var(--color-line)" opacity="0.25" />
                        <path :d="line(s.usual.mean)" fill="none" stroke="var(--color-muted)" stroke-width="1" opacity="0.7" />
                    </g>
                    <!-- Yesterday, the whole day: grey, dashed. -->
                    <path :d="line(s.before)" fill="none" stroke="var(--color-muted)" stroke-width="1.5" stroke-dasharray="5 4" data-line-before />
                    <!-- The projection: a fan (its range) and a dotted ink line from now to midnight, ending in a ring, labelled. -->
                    <g v-if="s.projection" data-projection>
                        <path :d="band(s.projection.lowPath, s.projection.highPath)" fill="var(--color-cream)" opacity="0.1" data-projection-fan />
                        <path :d="line(s.projection.mean)" fill="none" stroke="var(--color-cream)" stroke-width="2" stroke-dasharray="1.5 4" stroke-linecap="round" data-line-projected />
                        <circle :cx="sx(24)" :cy="sy(s.projection.value)" r="4.5" fill="var(--color-panel)" stroke="var(--color-cream)" stroke-width="1.5" />
                        <text :x="sx(24) - 8" :y="sy(s.projection.value) - 9" text-anchor="end" class="text-[11px]" fill="var(--color-cream)" stroke="var(--color-panel)" stroke-width="3" paint-order="stroke" data-projected-label>{{ projectedLabel }}</text>
                    </g>
                    <!-- Now: a thin rule, labelled. -->
                    <line :x1="sx(nowX)" :x2="sx(nowX)" :y1="M.top - 6" :y2="H - M.bottom" stroke="var(--color-muted)" stroke-width="1" opacity="0.6" data-now-rule />
                    <text :x="sx(nowX)" :y="M.top - 8" text-anchor="middle" class="text-[11px]" fill="var(--color-muted)">{{ t('admin.growth.chart.now') }}</text>
                    <!-- Today: 2 px ink to now, a dot at now with its number. -->
                    <path :d="line(s.today)" fill="none" stroke="var(--color-cream)" stroke-width="2" stroke-linejoin="round" data-line-today />
                    <circle :cx="sx(nowX)" :cy="sy(s.soFar)" r="4" fill="var(--color-cream)" stroke="var(--color-panel)" stroke-width="2" data-now-dot />
                    <!-- Its label beside the dot, or above and left of it when the projection runs on from the dot. -->
                    <text :x="sx(nowX) + (s.projection ? -8 : 8)" :y="sy(s.soFar) + (s.projection ? -9 : 4)" :text-anchor="s.projection ? 'end' : 'start'" class="text-[11px] font-semibold" fill="var(--color-cream)" stroke="var(--color-panel)" stroke-width="3" paint-order="stroke" data-now-label>{{ t('admin.growth.chart.atNow', { n: formatNumber(Math.round(s.soFar)), cut }) }}</text>
                    <!-- The crosshair, snapped to the hour under the pointer. -->
                    <line v-if="hover !== null" :x1="sx(hover)" :x2="sx(hover)" :y1="M.top" :y2="H - M.bottom" stroke="var(--color-cream)" stroke-width="1" opacity="0.35" />
                </svg>
                <div v-if="hover !== null" class="pointer-events-none absolute top-1 rounded-lg border border-line bg-panel px-2.5 py-1.5 text-xs text-cream shadow" :style="tipStyle" role="status" data-chart-tip>{{ tip }}</div>
            </div>
            <slot name="under" />
            <p v-if="hiddenLine" class="text-xs text-muted" data-projection-hidden>{{ hiddenLine }}</p>
            <button type="button" class="link inline-flex min-h-6 items-center text-xs" :aria-expanded="table ? 'true' : 'false'" data-chart-table-toggle @click="table = !table">{{ table ? t('admin.growth.chart.showChart') : t('admin.growth.chart.showTable') }}</button>
            <div v-if="table" class="max-h-96 overflow-auto" data-chart-table>
                <table class="w-full text-sm">
                    <caption class="pb-1 text-start text-xs text-muted">{{ t('admin.growth.chart.tableCaption', { what: label }) }}</caption>
                    <thead class="text-xs text-muted"><tr class="border-b border-line">
                        <th scope="col" class="py-1 pe-2 text-start font-semibold">{{ t('admin.growth.chart.col.hour') }}</th>
                        <th scope="col" class="px-2 py-1 text-end font-semibold">{{ t('admin.growth.chart.col.today') }}</th>
                        <th scope="col" class="px-2 py-1 text-end font-semibold">{{ t('admin.growth.chart.col.yesterday') }}</th>
                        <th v-if="s.usual" scope="col" class="px-2 py-1 text-end font-semibold">{{ t('admin.growth.chart.col.usual') }}</th>
                        <th v-if="s.projection" scope="col" class="ps-2 py-1 text-end font-semibold">{{ t('admin.growth.chart.col.projected') }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-line tabular-nums">
                        <tr v-for="r in rows" :key="r.hour" :data-hour="r.hour">
                            <th scope="row" class="py-1 pe-2 text-start font-normal text-muted">{{ r.slot }}</th>
                            <td class="px-2 py-1 text-end text-cream" data-col="today">{{ r.today }}</td>
                            <td class="px-2 py-1 text-end text-cream" data-col="before">{{ r.before }}</td>
                            <td v-if="s.usual" class="px-2 py-1 text-end text-muted" data-col="usual">{{ r.usual }}</td>
                            <td v-if="s.projection" class="ps-2 py-1 text-end text-muted" data-col="projected">{{ r.projected }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </figure>`,
    computed: {
        s() {
            return hourSeries(this.chart, this.metric) ?? { today: [[0, 0]], before: [[0, 0]], usual: null, projection: null, soFar: 0, beforeTotal: 0, perHour: [], beforePerHour: [], estimated: [] };
        },
        nowX() {
            return (this.chart.now?.slot ?? 0) + (this.chart.now?.fraction ?? 0);
        },
        yMax() {
            const values = [...this.s.today, ...this.s.before, ...(this.s.usual?.high ?? []), ...(this.s.projection?.highPath ?? [])].map(([, y]) => y).filter(Number.isFinite);
            // Headroom over the now dot, so it and its label never sit on the "Now" label at the top.
            return Math.max(1, this.s.soFar * 1.15, ...values);
        },
        yTicks() {
            return ticks(this.yMax);
        },
        anyEstimated() {
            return this.s.estimated.some(Boolean);
        },
        projectedLabel() {
            const p = this.s.projection;
            return p ? t('admin.growth.chart.projected', { n: formatNumber(roundProjected(p.value)), low: formatNumber(roundProjected(p.low)), high: formatNumber(roundProjected(p.high)) }) : '';
        },
        header() {
            return t('admin.growth.chart.beforeHad', { n: formatNumber(this.s.beforeTotal) });
        },
        hiddenLine() {
            const h = this.s.hidden;
            if (!h || !HIDDEN[h]) return '';
            return t(HIDDEN[h], { from: this.s.showsFrom ?? '' });
        },
        /** One sentence for the plot as a whole (its role img's name). */
        summary() {
            const before = this.beforeAt(this.nowX);
            const base = t('admin.growth.chart.summary', { what: this.label, n: formatNumber(Math.round(this.s.soFar)), cut: this.cut, before: formatNumber(Math.round(before ?? 0)), total: formatNumber(this.s.beforeTotal) });
            return this.s.projection ? `${base} ${t('admin.growth.chart.summaryProjected', { n: formatNumber(roundProjected(this.s.projection.value)) })}` : base;
        },
        rows() {
            const nowSlot = this.chart.now?.slot ?? 0;
            const today = runningTotals(this.s.perHour);
            const before = runningTotals(this.s.beforePerHour);
            return (this.chart.slots ?? []).map((slot, i) => {
                const next = String((i + 1) % 24).padStart(2, '0') + ':00';
                const own = this.s.perHour[i];
                const proj = this.s.projection && i >= nowSlot ? this.s.projection.mean[i - nowSlot + 1] : null;
                return {
                    hour: i,
                    slot: t('admin.growth.chart.hourRange', { from: slot, to: i === 23 ? '24:00' : next }),
                    today: own === null || own === undefined ? '' : t('admin.growth.chart.hourCell', { n: formatNumber(Math.round(own)), total: formatNumber(Math.round(today[i])) }),
                    before: this.beforeCell(this.s.beforePerHour[i], before[i], this.s.estimated[i]),
                    usual: this.s.usual ? t('admin.growth.chart.range', { low: formatNumber(Math.round(this.s.usual.low[i + 1][1])), high: formatNumber(Math.round(this.s.usual.high[i + 1][1])) }) : '',
                    projected: proj ? t('admin.growth.chart.projectedCell', { n: formatNumber(roundProjected(proj[1])), low: formatNumber(roundProjected(this.s.projection.lowPath[i - nowSlot + 1][1])), high: formatNumber(roundProjected(this.s.projection.highPath[i - nowSlot + 1][1])) }) : '',
                };
            });
        },
        tip() {
            const x = this.hover;
            if (x === null) return '';
            const at = String(x).padStart(2, '0') + ':00';
            const today = x <= this.nowX ? this.valueAt(this.s.today, x) : null;
            const before = this.beforeAt(x);
            const parts = [t('admin.growth.chart.tipBy', { at })];
            if (today !== null) parts.push(t('admin.growth.chart.tipToday', { n: formatNumber(Math.round(today)) }));
            if (before !== null) parts.push(t('admin.growth.chart.tipYesterday', { n: formatNumber(Math.round(before)) }));
            if (this.s.usual) parts.push(t('admin.growth.chart.tipUsual', { low: formatNumber(Math.round(this.valueAt(this.s.usual.low, x))), high: formatNumber(Math.round(this.valueAt(this.s.usual.high, x))) }));
            if (this.s.projection && x > this.nowX) parts.push(t('admin.growth.chart.tipProjected', { n: formatNumber(roundProjected(this.valueAt(this.s.projection.mean, x))) }));
            return parts.join(', ');
        },
        tipStyle() {
            const x = this.sx(this.hover ?? 0);
            return x > this.width / 2 ? { insetInlineEnd: `${this.width - x + 8}px` } : { insetInlineStart: `${x + 8}px` };
        },
    },
    mounted() {
        this.fit();
        this.observer = new ResizeObserver(() => this.fit());
        this.observer.observe(this.$refs.box);
    },
    beforeUnmount() {
        this.observer?.disconnect();
    },
    methods: {
        fit() {
            const w = this.$refs.box?.clientWidth;
            if (w) this.width = Math.max(260, Math.round(w));
        },
        sx(hour) {
            return M.left + (hour / HOURS) * (this.width - M.left - M.right);
        },
        sy(v) {
            const top = this.yTicks.at(-1) || 1;
            return M.top + (1 - v / top) * (H - M.top - M.bottom);
        },
        line(pts) {
            return pts?.length ? path(pts, this.sx, this.sy) : '';
        },
        band(low, high) {
            return low?.length && high?.length ? band(low, high, this.sx, this.sy) : '';
        },
        /** A series' value at hour x, straight between its points. */
        valueAt(pts, x) {
            if (!pts?.length) return null;
            for (let i = 1; i < pts.length; i++) {
                const [x0, y0] = pts[i - 1];
                const [x1, y1] = pts[i];
                if (x >= x0 && x <= x1) return x1 === x0 ? y1 : y0 + ((y1 - y0) * (x - x0)) / (x1 - x0);
            }
            return pts.at(-1)[0] <= x ? pts.at(-1)[1] : null;
        },
        beforeAt(x) {
            return this.valueAt(this.s.before, x);
        },
        beforeCell(own, total, estimated) {
            if (own === null || own === undefined) return '';
            const words = t('admin.growth.chart.hourCell', { n: formatNumber(Math.round(own)), total: formatNumber(Math.round(total)) });
            return estimated ? t('admin.growth.compare.about', { n: words }) : words;
        },
        point(event) {
            if (this.pinned !== null) return;
            this.hover = this.hourAt(event);
        },
        pin(event) {
            const h = this.hourAt(event);
            this.pinned = this.pinned === h ? null : h;
            this.hover = this.pinned ?? h;
        },
        hourAt(event) {
            const box = event.currentTarget.getBoundingClientRect();
            const x = ((event.clientX - box.left - M.left) / (this.width - M.left - M.right)) * HOURS;
            return Math.min(HOURS, Math.max(0, Math.round(x)));
        },
    },
};
