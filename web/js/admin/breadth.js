// Admin > Numbers > Breadth (docs/retention-plan.md, "Breadth"; contract: admin.breadth.show): how much of the app
// singers have used, from two anonymous counts only (br:<n>, how many of the eleven core things a device has tried, once
// a day; ft:<feature>, a first use). The headline: the share of this week's active days at three or more, with its move
// on last week and four weeks ago. Then one stacked bar a week, guests and accounts apart (the levels grouped into five
// bands of one hue, light to dark, with a legend and the same as a table), and the eleven as a ladder: first uses this
// week beside new devices and the step before. "Too few to tell" under the report's floor. Nothing about anyone.

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { LoadFailed } from './common.js';

const FEATURES = {
    lesson: tk('admin.breadth.feature.lesson'), warmup: tk('admin.breadth.feature.warmup'), singalong: tk('admin.breadth.feature.singalong'),
    record: tk('admin.breadth.feature.record'), stack: tk('admin.breadth.feature.stack'), whole: tk('admin.breadth.feature.whole'),
    course: tk('admin.breadth.feature.course'), create: tk('admin.breadth.feature.create'), share: tk('admin.breadth.feature.share'),
    together: tk('admin.breadth.feature.together'), tool: tk('admin.breadth.feature.tool'),
};
// The levels in five bands, one hue light to dark (sequential: more tried, darker).
export const BANDS = [
    { key: 'none', label: tk('admin.breadth.band.none'), from: 0, to: 0, mix: 14 },
    { key: 'one', label: tk('admin.breadth.band.one'), from: 1, to: 1, mix: 32 },
    { key: 'two', label: tk('admin.breadth.band.two'), from: 2, to: 2, mix: 52 },
    { key: 'threeFour', label: tk('admin.breadth.band.threeFour'), from: 3, to: 4, mix: 74 },
    { key: 'fivePlus', label: tk('admin.breadth.band.fivePlus'), from: 5, to: 11, mix: 100 },
];
const WHO = { guest: tk('admin.breadth.guests'), account: tk('admin.breadth.accounts') };

const pct = (x) => (typeof x === 'number' ? formatNumber(x, { style: 'percent', maximumFractionDigits: 0 }) : '');

/** A week's device-days in the five bands, each as a share of its days. */
export function bandsOf(levels = [], days = 0) {
    return BANDS.map((b) => {
        const n = levels.slice(b.from, b.to + 1).reduce((s, x) => s + (x ?? 0), 0);
        return { ...b, days: n, share: days ? n / days : 0 };
    });
}

/** The headline's move: points between two shares, or null when either is missing. */
export function points(now, before) {
    if (typeof now !== 'number' || typeof before !== 'number') return null;
    const d = Math.round((now - before) * 100);
    return { dir: d > 0 ? 'up' : d < 0 ? 'down' : 'same', points: Math.abs(d) };
}

/** Home's one line: three or more this week, against last week; null under the floor or before the first week. */
export function breadthLine(report) {
    const h = report?.headline;
    if (!h || h.tooFew || typeof h.threePlus !== 'number') return null;
    return typeof h.lastWeek === 'number' ? t('admin.breadth.homeLine', { share: pct(h.threePlus), last: pct(h.lastWeek) }) : t('admin.breadth.homeLineFirst', { share: pct(h.threePlus) });
}

export const BreadthSection = {
    components: { LoadFailed },
    props: { me: { type: Object, required: true } },
    data: () => ({ report: null, failed: false, asTable: false }),
    setup: () => ({ BANDS, WHO, FEATURES, pct, formatNumber, bandsOf }),
    template: `
        <section class="space-y-6" aria-labelledby="breadth-h" data-breadth>
            <div>
                <h1 id="breadth-h" class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.breadth') }}</h1>
                <p class="mt-1 max-w-prose text-sm text-muted">{{ report ? t('admin.breadth.intro', { from: day(report.from), to: day(report.to), floor: report.floor }) : t('admin.breadth.introShort') }}</p>
            </div>
            <LoadFailed v-if="failed" :message="t('admin.breadth.failed')" @retry="load" />
            <p v-else-if="!report" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <!-- The headline as one ruled panel (Hillel's Home): three or more, its moves, the mean, the days read. -->
                <dl class="card grid grid-cols-2 overflow-hidden min-[900px]:grid-cols-4" data-breadth-headline>
                    <div class="-mb-px -me-px min-w-0 border-b border-e border-line p-4 sm:p-5" data-measure="threePlus">
                        <dt class="text-sm text-muted">{{ t('admin.breadth.threePlus') }}</dt>
                        <dd class="mt-2 text-3xl font-extrabold tabular-nums tracking-tight text-cream"><span v-if="readable" data-value>{{ pct(report.headline.threePlus) }}</span><span v-else class="text-sm font-normal text-muted">{{ t('admin.breadth.tooFew') }}</span></dd>
                    </div>
                    <div v-for="m in moves" :key="m.key" class="-mb-px -me-px min-w-0 border-b border-e border-line p-4 sm:p-5" :data-measure="m.key">
                        <dt class="text-sm text-muted">{{ m.label }}</dt>
                        <dd class="mt-2 text-xl font-bold tabular-nums" :class="m.move ? (m.move.dir === 'up' ? 'text-up' : m.move.dir === 'down' ? 'text-down' : 'text-muted') : 'text-muted'" :data-dir="m.move?.dir">
                            <template v-if="m.move"><span v-if="m.move.dir !== 'same'" class="me-0.5" aria-hidden="true">{{ m.move.dir === 'up' ? '↑' : '↓' }}</span>{{ m.move.dir === 'same' ? t('admin.breadth.same') : t('admin.breadth.points', { count: m.move.points }) }}<span class="block text-xs font-normal text-muted">{{ t('admin.breadth.was', { share: pct(m.was) }) }}</span></template>
                            <span v-else class="text-sm font-normal">{{ t('admin.breadth.notYet') }}</span>
                        </dd>
                    </div>
                    <div class="-mb-px -me-px min-w-0 border-b border-e border-line p-4 sm:p-5" data-measure="mean">
                        <dt class="text-sm text-muted">{{ t('admin.breadth.mean') }}</dt>
                        <dd class="mt-2 text-xl font-bold tabular-nums text-cream">{{ meanText }}<span class="block text-xs font-normal text-muted">{{ t('admin.breadth.days', { count: report.headline.deviceDays }) }}</span></dd>
                    </div>
                </dl>

                <section class="space-y-3" aria-labelledby="breadth-weeks-h">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <h2 id="breadth-weeks-h" class="text-lg font-bold text-cream">{{ t('admin.breadth.weeksTitle') }}</h2>
                        <button type="button" class="link tap-44 text-sm" :aria-pressed="asTable ? 'true' : 'false'" data-breadth-table-toggle @click="asTable = !asTable">{{ asTable ? t('admin.breadth.asBars') : t('admin.breadth.asTable') }}</button>
                    </div>
                    <!-- The legend: one hue, light to dark as more is tried. -->
                    <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" data-breadth-legend>
                        <li v-for="b in BANDS" :key="b.key" class="flex items-center gap-1.5"><span class="size-3 rounded-sm" :style="swatch(b)" aria-hidden="true"></span>{{ t(b.label) }}</li>
                    </ul>
                    <div v-if="!asTable" class="card space-y-4 p-4 sm:p-5" data-breadth-bars>
                        <div v-for="w in report.weeks" :key="w.from" class="space-y-1.5" :data-week="w.from">
                            <p class="text-xs font-semibold text-muted">{{ t('admin.breadth.weekOf', { from: day(w.from) }) }}</p>
                            <div v-for="who in ['guest', 'account']" :key="who" class="grid grid-cols-[5.5rem_minmax(0,1fr)] items-center gap-3 text-xs" :data-who="who">
                                <span class="text-muted">{{ t(WHO[who]) }}</span>
                                <span v-if="w[who].tooFew" class="text-muted">{{ t('admin.breadth.tooFewDays', { count: w[who].deviceDays }) }}</span>
                                <span v-else class="flex h-4 overflow-hidden rounded-sm" role="img" :aria-label="barLabel(w[who])">
                                    <span v-for="b in bandsOf(w[who].levels, w[who].deviceDays).filter((x) => x.days)" :key="b.key" class="h-full border-e-2 border-panel last:border-e-0" :style="{ ...swatch(b), width: (b.share * 100) + '%' }" :title="t(b.label) + ': ' + pct(b.share)"></span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="card overflow-x-auto" data-breadth-weeks-table>
                        <table class="data-table">
                            <thead><tr><th>{{ t('admin.breadth.col.week') }}</th><th>{{ t('admin.breadth.col.who') }}</th><th class="text-end">{{ t('admin.breadth.col.days') }}</th><th v-for="b in BANDS" :key="b.key" class="text-end">{{ t(b.label) }}</th></tr></thead>
                            <tbody>
                                <template v-for="w in report.weeks" :key="w.from">
                                    <tr v-for="who in ['guest', 'account']" :key="who">
                                        <td class="whitespace-nowrap text-muted">{{ day(w.from) }}</td>
                                        <td>{{ t(WHO[who]) }}</td>
                                        <td class="text-end tabular-nums">{{ formatNumber(w[who].deviceDays) }}</td>
                                        <td v-for="b in bandsOf(w[who].levels, w[who].deviceDays)" :key="b.key" class="text-end tabular-nums">{{ w[who].tooFew ? '' : pct(b.share) }}</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="space-y-3" aria-labelledby="breadth-ladder-h">
                    <h2 id="breadth-ladder-h" class="text-lg font-bold text-cream">{{ t('admin.breadth.ladderTitle') }}</h2>
                    <p class="text-sm text-muted">{{ t('admin.breadth.ladderIntro', { count: report.newDevices }) }}</p>
                    <ol class="card divide-y divide-line sm:hidden">
                        <li v-for="(f, i) in report.features" :key="f.feature" class="space-y-0.5 p-4 text-sm" :data-feature-phone="f.feature">
                            <p class="font-semibold text-cream">{{ i + 1 }}. {{ t(FEATURES[f.feature] ?? FEATURES.lesson) }}</p>
                            <p class="text-muted">{{ t('admin.breadth.col.firstUses') }} {{ formatNumber(f.firstUses) }} · {{ t('admin.breadth.col.lastWeek') }} {{ formatNumber(f.lastWeek) }}</p>
                            <p class="text-muted">{{ t('admin.breadth.col.perNew') }} {{ ratio(f, f.perNew) }} · {{ t('admin.breadth.col.fromPrevious') }} {{ i === 0 ? '-' : ratio(f, f.fromPrevious) }}</p>
                        </li>
                    </ol>
                    <div class="card hidden overflow-x-auto sm:block">
                        <table class="data-table">
                            <thead><tr><th>{{ t('admin.breadth.col.feature') }}</th><th class="text-end">{{ t('admin.breadth.col.firstUses') }}</th><th class="text-end">{{ t('admin.breadth.col.lastWeek') }}</th><th class="text-end">{{ t('admin.breadth.col.perNew') }}</th><th class="text-end">{{ t('admin.breadth.col.fromPrevious') }}</th></tr></thead>
                            <tbody>
                                <tr v-for="(f, i) in report.features" :key="f.feature" :data-feature="f.feature">
                                    <td class="text-cream"><span class="me-1.5 tabular-nums text-muted">{{ i + 1 }}</span>{{ t(FEATURES[f.feature] ?? FEATURES.lesson) }}</td>
                                    <td class="text-end tabular-nums">{{ formatNumber(f.firstUses) }}</td>
                                    <td class="text-end tabular-nums text-muted">{{ formatNumber(f.lastWeek) }}</td>
                                    <td class="text-end tabular-nums">{{ ratio(f, f.perNew) }}</td>
                                    <td class="text-end tabular-nums">{{ i === 0 ? '' : ratio(f, f.fromPrevious) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </section>`,
    computed: {
        readable: (vm) => !!vm.report && !vm.report.headline.tooFew && typeof vm.report.headline.threePlus === 'number',
        /** How many of the eleven an active day has tried, on average. */
        meanText() {
            const mean = this.report.headline.mean;
            return this.readable && typeof mean === 'number' ? formatNumber(mean, { maximumFractionDigits: 1 }) : t('admin.breadth.tooFew');
        },
        moves() {
            const h = this.report.headline;
            return [
                { key: 'lastWeek', label: t('admin.breadth.onLastWeek'), move: this.readable ? points(h.threePlus, h.lastWeek) : null, was: h.lastWeek },
                { key: 'fourWeeks', label: t('admin.breadth.onFourWeeks'), move: this.readable ? points(h.threePlus, h.fourWeeksAgo) : null, was: h.fourWeeksAgo },
            ];
        },
    },
    created() {
        this.load();
    },
    methods: {
        async load() {
            this.failed = false;
            try {
                this.report = (await api.admin.breadth()).data;
            } catch (error) {
                console.warn('Breadth did not load', error);
                this.failed = true;
            }
        },
        /** A band's colour: the accent mixed into the panel, lighter for fewer tried. */
        swatch: (b) => ({ backgroundColor: `color-mix(in oklab, var(--color-accent) ${b.mix}%, var(--color-panel))` }),
        /** A share read against what's under it: too few to tell under the floor. */
        ratio: (f, x) => (f.tooFew || typeof x !== 'number' ? t('admin.breadth.tooFew') : pct(x)),
        barLabel(week) {
            return bandsOf(week.levels, week.deviceDays).map((b) => `${t(b.label)} ${pct(b.share)}`).join(', ');
        },
        day: (d) => formatDate(`${d}T12:00:00`, { day: 'numeric', month: 'short' }),
    },
};
