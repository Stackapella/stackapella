// Growth's fair cards and its "How we're tracking" panel (docs/growth-compare-spec.md, 4.2 to 4.6, F1 and F2): a
// metric's number for the period, its verdict chip (blue up, orange down, always with an arrow and a word), the usual
// range as a strip with its words, and its comparison rows, each cut at the same clock time. The server decides every
// verdict and change (admin.growth.show `metrics`, `tracking`); growth-compare.js says them.

import { formatNumber, t, tk } from '../i18n.js';
import { changeText, comparisonName, expectedText, noHistoryText, periodWords, rangeText, tooFewText, trackingSentence, VERDICTS } from './growth-compare.js';

/** Each card's name, in the spec's order (section 4.1). */
export const METRICS = {
    visits: tk('admin.growth.metric.visits'),
    arrivals: tk('admin.growth.metric.arrivals'),
    lesson1: tk('admin.growth.metric.lesson1'),
    lessons: tk('admin.growth.metric.lessons'),
    signUps: tk('admin.growth.metric.signUps'),
    asks: tk('admin.growth.metric.asks'),
    day2: tk('admin.growth.metric.day2'),
    plus: tk('admin.growth.metric.plus'),
    firstLessons: tk('admin.growth.metric.firstLessons'),
};

const pct = (share) => `${formatNumber(Math.round(share * 1000) / 10)}%`;

/** The verdict's chip: a tint for up and down, an outline for maybe and the steady ones, plain muted words for none. */
export const VerdictChip = {
    props: { verdict: { type: String, default: 'none' } },
    setup: () => ({ VERDICTS }),
    template: `
        <span v-if="v" class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-md py-0.5 text-xs font-semibold" :class="frame" :data-verdict="verdict">
            <span v-if="v.arrow" aria-hidden="true" :class="v.tone === 'up' ? 'text-up' : 'text-down'">{{ v.arrow }}</span>
            <span :class="v.frame === 'tint' ? 'text-cream' : 'text-muted'">{{ t(v.words) }}</span>
        </span>`,
    computed: {
        v() {
            return VERDICTS[this.verdict] ?? null;
        },
        frame() {
            if (this.v.frame === 'tint') return this.v.tone === 'up' ? 'bg-up/15 px-2' : 'bg-down/15 px-2';
            if (this.v.frame === 'outline') return 'border border-line px-2';
            return '';
        },
    },
};

/**
 * The usual range as a strip (spec 4.2): a 6 px track, the range as a band, and the number as a 10 px dot with a ring,
 * ink inside the band and the up or down colour outside it. The scale runs to 1.25 times the larger of the number and
 * the band's top, so the band never fills the track. Words beside it carry the meaning (rangeText); the strip is a
 * picture of them (role img, its label the same words).
 */
export const RangeStrip = {
    props: { value: { type: Number, required: true }, low: { type: Number, required: true }, high: { type: Number, required: true }, verdict: { type: String, default: 'same' }, label: { type: String, default: '' } },
    template: `
        <span class="relative block h-2.5 w-full" role="img" :aria-label="label" data-range-strip :data-inside="inside ? 'yes' : 'no'">
            <span class="absolute inset-x-0 top-1/2 h-1.5 -translate-y-1/2 rounded-full bg-raised"></span>
            <span class="absolute top-1/2 h-1.5 -translate-y-1/2 rounded-full bg-line/60" :style="{ insetInlineStart: at(low) + '%', width: Math.max(1, at(high) - at(low)) + '%' }" data-range-band></span>
            <span class="absolute top-1/2 size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-panel rtl:translate-x-1/2" :class="dot" :style="{ insetInlineStart: at(value) + '%' }" data-range-dot></span>
        </span>`,
    computed: {
        top() {
            return Math.max(1, 1.25 * Math.max(this.value, this.high));
        },
        inside() {
            return this.value >= this.low && this.value <= this.high;
        },
        dot() {
            if (this.inside) return 'bg-cream';
            return this.value > this.high ? 'bg-up' : 'bg-down';
        },
    },
    methods: {
        at(n) {
            return Math.min(100, Math.max(0, (100 * n) / this.top));
        },
    },
};

/** One comparison row: its name with the cut time, the number it's set against ("about 31", estimated), the change. */
const CompareRow = {
    props: { metric: { type: Object, required: true }, rowKey: { type: String, required: true }, period: { type: Object, required: true }, context: { type: Object, required: true } },
    setup: () => ({ changeText, expectedText }),
    template: `
        <div class="flex items-baseline justify-between gap-3 text-sm" :data-row="rowKey" :data-row-verdict="row.verdict">
            <dt class="min-w-0 text-muted">{{ name }} <span v-if="row.estimated" class="ms-0.5 inline-block rounded border border-line px-1 text-[11px] text-muted" :title="t('admin.growth.compare.estimatedHow')" data-estimated>{{ t('admin.growth.compare.estimated') }}</span></dt>
            <dd class="shrink-0 text-end tabular-nums text-cream">
                <template v-if="row.verdict === 'noHistory'"><span class="text-muted">{{ history }}</span></template>
                <template v-else-if="row.verdict === 'tooFew' || row.verdict === 'none'"><span class="text-muted">{{ expectedText(row) || '0' }}</span></template>
                <template v-else><span class="text-muted">{{ expectedText(row) }}</span> <b class="ms-2 font-semibold" data-change>{{ changeText(row.change) }}</b></template>
            </dd>
        </div>`,
    computed: {
        row() {
            return this.metric[this.rowKey] ?? {};
        },
        name() {
            return comparisonName(this.period, this.rowKey, { ...this.context, ...(this.rowKey === 'previous' ? this.context.previous : {}), available: this.row.available ?? this.context.available });
        },
        history() {
            return noHistoryText(this.row, this.metric.since);
        },
    },
};

/** A metric's card (spec 4.2): name and verdict, the number, the usual strip and its words, then the rows. */
export const MetricCard = {
    components: { VerdictChip, RangeStrip, CompareRow },
    props: { metric: { type: Object, required: true }, period: { type: Object, required: true }, context: { type: Object, required: true } },
    setup: () => ({ METRICS, formatNumber, periodWords, pct }),
    template: `
        <li class="card flex min-w-0 flex-col gap-3 p-4 sm:p-5" :data-metric="metric.key">
            <div class="flex flex-wrap items-start justify-between gap-x-2 gap-y-1">
                <p class="text-sm text-muted">{{ METRICS[metric.key] ? t(METRICS[metric.key]) : metric.key }}</p>
                <VerdictChip :verdict="headline" />
            </div>
            <p class="flex flex-wrap items-baseline gap-x-2">
                <span class="text-3xl font-extrabold text-cream md:text-4xl" data-now>{{ formatNumber(metric.now) }}</span>
                <span class="text-sm text-muted">{{ periodWords(period) }}</span>
                <span v-if="share" class="text-sm text-muted" data-share>{{ share }}</span>
            </p>
            <div v-if="strip" class="space-y-1">
                <RangeStrip :value="metric.now" :low="metric.usual.low" :high="metric.usual.high" :verdict="metric.usual.verdict" :label="stripWords" />
                <p class="text-xs text-muted" data-usually>{{ stripWords }}</p>
            </div>
            <p v-if="few" class="text-sm text-muted" data-few>{{ few }}</p>
            <dl class="mt-auto space-y-1.5 border-t border-line pt-3">
                <CompareRow v-if="metric.previous" :metric="metric" row-key="previous" :period="period" :context="context" />
                <CompareRow v-if="metric.usual" :metric="metric" row-key="usual" :period="period" :context="context" />
            </dl>
        </li>`,
    computed: {
        /** The chip: the usual row's verdict when there is one with enough history, else the previous row's. */
        headline() {
            const usual = this.metric.usual;
            return usual && usual.verdict !== 'noHistory' && (usual.available ?? 4) >= 2 ? usual.verdict : this.metric.previous?.verdict ?? 'none';
        },
        strip() {
            const u = this.metric.usual;
            return !!u && Number.isFinite(u.low) && Number.isFinite(u.high) && !['tooFew', 'none', 'noHistory'].includes(u.verdict);
        },
        stripWords() {
            return rangeText(this.metric.usual, { cut: this.period.cut || null });
        },
        few() {
            const p = this.metric.previous;
            return p?.verdict === 'tooFew' ? tooFewText(this.period, this.metric.now, p, { cut: this.period.cut }) : '';
        },
        /** Sign-ups and lesson 1 beside their share of arrivals; the asks as "n of m said yes". */
        share() {
            const r = this.metric.rate;
            if (!r || !Number.isFinite(r.now)) return '';
            if (r.of === 'shown') return t('admin.growth.compare.saidYes', { yes: formatNumber(this.metric.now), shown: formatNumber(r.base ?? 0) });
            return t('admin.growth.compare.ofArrivals', { share: pct(r.now) });
        },
    },
};

/**
 * "How we're tracking" (spec 3, F2): always the last 7 days against a usual week, cut at the same time, whatever the
 * period below. The sentence from the four verdicts, then the four headline numbers, each with its usual range.
 */
export const TrackingPanel = {
    components: { VerdictChip, RangeStrip },
    props: { tracking: { type: Object, required: true }, cut: { type: String, default: '' } },
    setup: () => ({ METRICS, formatNumber, rangeText, trackingSentence }),
    template: `
        <section class="card space-y-4 p-5 md:p-6" aria-labelledby="growth-track-h" data-growth-tracking>
            <div>
                <h2 id="growth-track-h" class="text-lg font-bold text-cream">{{ t('admin.growth.track.title') }}</h2>
                <p class="text-sm text-muted">{{ t('admin.growth.track.caption', { cut, count: available }) }}</p>
            </div>
            <p class="text-base text-cream" data-track-sentence>{{ trackingSentence(tracking.metrics) }}</p>
            <ul class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <li v-for="m in tracking.metrics" :key="m.key" class="min-w-0 rounded-xl bg-raised/40 p-3 sm:p-4" :data-track="m.key">
                    <div class="flex flex-wrap items-start justify-between gap-x-2 gap-y-1">
                        <p class="text-sm text-muted">{{ label(m) }}</p>
                        <VerdictChip :verdict="m.usual?.verdict ?? 'none'" />
                    </div>
                    <p class="mt-1 text-3xl font-extrabold text-cream" data-now>{{ formatNumber(m.now) }}</p>
                    <p v-if="m.rate && Number.isFinite(m.rate.now)" class="text-xs text-muted">{{ t('admin.growth.compare.ofArrivals', { share: (Math.round(m.rate.now * 1000) / 10) + '%' }) }}</p>
                    <div v-if="m.usual && Number.isFinite(m.usual.low) && Number.isFinite(m.usual.high)" class="mt-2 space-y-1">
                        <RangeStrip :value="m.now" :low="m.usual.low" :high="m.usual.high" :verdict="m.usual.verdict" :label="rangeText(m.usual)" />
                        <p class="text-xs text-muted" data-usually>{{ rangeText(m.usual) }}</p>
                    </div>
                </li>
            </ul>
        </section>`,
    computed: {
        available() {
            return this.tracking.period?.available ?? this.tracking.metrics?.[0]?.usual?.available ?? 4;
        },
    },
    methods: {
        label(m) {
            return t(m.key === 'firstLessons' && m.measure === 'lesson1' ? METRICS.lesson1 : METRICS[m.key] ?? m.key);
        },
    },
};
