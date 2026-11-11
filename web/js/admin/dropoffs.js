// Admin > Numbers > Drop-offs (docs/retention-plan.md, "Detect"; contract: admin.dropoffs.show): the weekly report
// of where singers stop. The three worst drops first, in plain words, then every path step by step: how many reached the
// step, how many went on, this week's pass-through against its neighbours and its own four weeks before, last week's
// beside it, and the last 28 days (the long window that lets a deep step be read at all). A step with too few devices
// says so and is never a drop. Anonymous weekly totals only; worked out each Friday night (app:dropoffs).
// Home's What needs you now shows the same three worst (DropoffLines, admin/needs.js).

import { api } from '../api.js';
import { formatDate, formatNumber, t } from '../i18n.js';
import { navigate } from '../router.js';
import { StatusChip } from '../components/form.js';
import { LoadFailed } from './common.js';

/** A pass-through as people read it: 36%. */
export const pct = (x) => (typeof x === 'number' ? formatNumber(x, { style: 'percent', maximumFractionDigits: 0 }) : '');

/** Where a drop is looked at: the funnel's main line on Funnel, a lesson's steps here. */
export const anchor = (path) => `path-${path.replace(/[^a-z0-9-]/gi, '-')}`;
export const dropPath = (path) => (path === 'funnel' ? '/admin/funnel' : `/admin/dropoffs#${anchor(path)}`);

/** One worst drop as a line, the step's pass-through against its neighbours and, when there's one, last week's. */
export const DropoffLine = {
    props: { drop: { type: Object, required: true }, lastWeek: { type: Number, default: null } },
    setup: () => ({ pct, formatNumber }),
    template: `
        <span class="min-w-0 flex-1">
            <span class="block text-cream" translate="no">{{ drop.pathLabel }}: {{ drop.label }}</span>
            <span class="block text-sm text-muted">{{ t('admin.dropoffs.line', { pass: pct(drop.passThrough), neighbours: pct(drop.neighbours), lost: formatNumber(drop.lost) }) }}<template v-if="lastWeek !== null"> · {{ t('admin.dropoffs.lastWeekWas', { pass: pct(lastWeek) }) }}</template></span>
        </span>`,
};

/** A step's pass-through in last week's report, or null. */
export function lastWeekOf(previous, path, step) {
    const p = previous?.paths?.find((x) => x.path === path)?.steps.find((s) => s.step === step);
    return p && !p.tooFew && typeof p.passThrough === 'number' ? p.passThrough : null;
}

export const DropoffsSection = {
    components: { DropoffLine, LoadFailed, StatusChip },
    props: { me: { type: Object, required: true } },
    data: () => ({ report: null, failed: false }),
    setup: () => ({ pct, formatNumber, dropPath, lastWeekOf, anchor }),
    template: `
        <section class="space-y-6" aria-labelledby="dropoffs-h" data-dropoffs>
            <div>
                <h1 id="dropoffs-h" class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.dropoffs') }}</h1>
                <p class="mt-1 max-w-prose text-sm text-muted">{{ latest ? t('admin.dropoffs.intro', { from: day(latest.weekStart), to: day(latest.weekEnd), floor: latest.floor }) : t('admin.dropoffs.introNone') }}</p>
            </div>
            <LoadFailed v-if="failed" :message="t('admin.dropoffs.failed')" @retry="load" />
            <p v-else-if="!report" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <p v-else-if="!latest" class="card p-5 text-sm text-muted" data-dropoffs-none>{{ t('admin.dropoffs.none') }}</p>
            <template v-else>
                <section class="space-y-3" aria-labelledby="dropoffs-worst-h">
                    <h2 id="dropoffs-worst-h" class="text-lg font-bold text-cream">{{ t('admin.dropoffs.worst') }}</h2>
                    <p v-if="!latest.worst.length" class="text-sm text-muted">{{ t('admin.dropoffs.noDrops') }}</p>
                    <ol v-else class="card divide-y divide-line overflow-hidden">
                        <li v-for="(d, i) in latest.worst" :key="d.path + d.step">
                            <a :href="dropPath(d.path)" class="flex items-center gap-3.5 px-5 py-3 transition hover:bg-(--fill)" :data-dropoff-worst="d.step" @click.prevent="go(dropPath(d.path))">
                                <span class="grid h-7 min-w-7 shrink-0 place-items-center rounded-lg bg-warn/15 px-1.5 text-sm font-bold tabular-nums text-warn">{{ i + 1 }}</span>
                                <DropoffLine :drop="d" :last-week="lastWeekOf(previous, d.path, d.step)" />
                            </a>
                        </li>
                    </ol>
                </section>
                <section v-for="p in latest.paths" :key="p.path" :id="anchor(p.path)" class="scroll-mt-6 space-y-3" :aria-labelledby="'dh-' + p.path" :data-dropoff-path="p.path">
                    <h2 :id="'dh-' + p.path" class="text-lg font-bold text-cream" translate="no">{{ p.label }}</h2>
                    <!-- A phone: each step as a short block, never a table to scroll sideways. -->
                    <ul class="card divide-y divide-line sm:hidden">
                        <li v-for="s in p.steps" :key="s.step" class="space-y-1 p-4 text-sm" :data-dropoff-step-phone="s.step">
                            <p class="flex flex-wrap items-center justify-between gap-2"><span class="font-semibold text-cream" translate="no">{{ s.label }}</span><StatusChip v-if="s.drop" tone="warn" :label="t('admin.dropoffs.lost', { count: s.lost })" /></p>
                            <p class="text-muted">{{ t('admin.dropoffs.col.entered') }} {{ formatNumber(s.entered) }} · {{ t('admin.dropoffs.col.passed') }} {{ formatNumber(s.passed) }}</p>
                            <p><span class="font-semibold" :class="s.drop ? 'text-warn' : 'text-cream'">{{ t('admin.dropoffs.col.week') }} {{ s.tooFew ? t('admin.dropoffs.tooFew') : pct(s.passThrough) }}</span><span class="text-muted"> · {{ t('admin.dropoffs.col.neighbours') }} {{ pct(s.neighbours) }}</span></p>
                            <p class="text-muted">{{ t('admin.dropoffs.col.lastWeek') }} {{ pct(lastWeekOf(previous, p.path, s.step)) || '-' }} · {{ t('admin.dropoffs.col.before') }} {{ pct(s.before) || '-' }} · {{ t('admin.dropoffs.col.month') }} {{ monthText(s) || '-' }}</p>
                        </li>
                    </ul>
                    <div class="card hidden overflow-x-auto sm:block">
                        <table class="data-table">
                            <thead><tr>
                                <th>{{ t('admin.dropoffs.col.step') }}</th>
                                <th class="text-end">{{ t('admin.dropoffs.col.entered') }}</th>
                                <th class="text-end">{{ t('admin.dropoffs.col.passed') }}</th>
                                <th class="text-end">{{ t('admin.dropoffs.col.week') }}</th>
                                <th class="text-end">{{ t('admin.dropoffs.col.lastWeek') }}</th>
                                <th class="text-end">{{ t('admin.dropoffs.col.before') }}</th>
                                <th class="text-end">{{ t('admin.dropoffs.col.neighbours') }}</th>
                                <th class="text-end">{{ t('admin.dropoffs.col.month') }}</th>
                                <th><span class="sr-only">{{ t('admin.dropoffs.col.verdict') }}</span></th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="s in p.steps" :key="s.step" :data-dropoff-step="s.step">
                                    <td class="text-cream" translate="no">{{ s.label }}</td>
                                    <td class="text-end tabular-nums">{{ formatNumber(s.entered) }}</td>
                                    <td class="text-end tabular-nums">{{ formatNumber(s.passed) }}</td>
                                    <td class="text-end tabular-nums font-semibold" :class="s.drop ? 'text-warn' : 'text-cream'">{{ s.tooFew ? t('admin.dropoffs.tooFew') : pct(s.passThrough) }}</td>
                                    <td class="text-end tabular-nums text-muted">{{ pct(lastWeekOf(previous, p.path, s.step)) }}</td>
                                    <td class="text-end tabular-nums text-muted">{{ pct(s.before) }}</td>
                                    <td class="text-end tabular-nums text-muted">{{ pct(s.neighbours) }}</td>
                                    <td class="text-end tabular-nums text-muted">{{ monthText(s) }}</td>
                                    <td><StatusChip v-if="s.drop" tone="warn" :label="t('admin.dropoffs.lost', { count: s.lost })" data-dropoff-drop /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </section>`,
    computed: {
        latest: (vm) => vm.report?.latest ?? null,
        previous: (vm) => vm.report?.previous ?? null,
    },
    created() {
        this.load();
    },
    methods: {
        async load() {
            this.failed = false;
            try {
                this.report = (await api.admin.dropoffs()).data;
                if (location.hash) this.$nextTick(() => document.getElementById(location.hash.slice(1))?.scrollIntoView({ block: 'start' }));
            } catch (error) {
                console.warn('The drop-offs did not load', error);
                this.failed = true;
            }
        },
        /** The last 28 days' pass-through, or too few to tell. */
        monthText(s) {
            return s.month ? (s.month.tooFew ? t('admin.dropoffs.tooFew') : pct(s.month.passThrough)) : '';
        },
        day: (d) => formatDate(`${d}T12:00:00`, { day: 'numeric', month: 'short' }),
        go: (path) => navigate(path),
    },
};
