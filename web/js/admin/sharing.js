// Admin > Numbers > Sharing (docs/retention-plan.md, "Sharing"; contract: admin.sharing.show): how often singers pass
// the app on and what comes of it, over the seven days to today against the seven before. The four measures as one
// ruled panel (shares per 100 active singers, arrivals per share, arrivals that sing, and each 100 singers bring N new
// ones), each with its move on last week; then a row a surface: shares (by how), opens of a shared page, arrivals by its
// tag, first lessons and sign-ups. A number over a total under the report's floor reads "too few to tell".
// Anonymous weekly totals only. Home's What needs you now has the four with the weakest surface (SharingCard).

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { LoadFailed } from './common.js';

// Each surface a share starts from, in words (config/counts.php share_surfaces); a free tool's is `tool-<slug>`.
const SURFACES = {
    song: tk('admin.sharing.surface.song'), lesson: tk('admin.sharing.surface.lesson'), note: tk('admin.sharing.surface.note'), daily: tk('admin.sharing.surface.daily'),
    invite: tk('admin.sharing.surface.invite'), challenge: tk('admin.sharing.surface.challenge'), group: tk('admin.sharing.surface.group'), class: tk('admin.sharing.surface.class'),
    arrangement: tk('admin.sharing.surface.arrangement'), warmup: tk('admin.sharing.surface.warmup'), app: tk('admin.sharing.surface.app'), embed: tk('admin.sharing.surface.embed'),
    'page-song': tk('admin.sharing.surface.pageSong'), 'page-part': tk('admin.sharing.surface.pagePart'), 'page-course': tk('admin.sharing.surface.pageCourse'),
    'page-lesson': tk('admin.sharing.surface.pageLesson'), 'page-carols': tk('admin.sharing.surface.pageCarols'),
};
const METHODS = { sheet: tk('admin.sharing.method.sheet'), copy: tk('admin.sharing.method.copy'), video: tk('admin.sharing.method.video'), image: tk('admin.sharing.method.image'), embed: tk('admin.sharing.method.embed') };
// The four measures, each read over its own total: under the floor, too few to tell.
export const MEASURES = [
    { key: 'sharesPer100', label: tk('admin.sharing.measure.sharesPer100'), over: 'active', kind: 'number' },
    { key: 'arrivalsPerShare', label: tk('admin.sharing.measure.arrivalsPerShare'), over: 'shares', kind: 'ratio' },
    { key: 'arrivalsThatSing', label: tk('admin.sharing.measure.arrivalsThatSing'), over: 'arrivals', kind: 'percent' },
    { key: 'eachHundredBring', label: tk('admin.sharing.measure.eachHundredBring'), over: 'active', kind: 'number' },
];

/** A surface in words: its name, or a free tool's slug as it's written. */
export function surfaceName(surface) {
    if (SURFACES[surface]) return t(SURFACES[surface]);
    if (surface.startsWith('tool-')) return t('admin.sharing.surface.tool', { slug: surface.slice(5) });
    return surface;
}

/** A measure as people read it (15, 0.8, 47%), or null under the floor or when the server has none. */
export function measureText(m, week, floor) {
    const value = week?.[m.key];
    if (typeof value !== 'number' || (week?.[m.over] ?? 0) < floor) return null;
    if (m.kind === 'percent') return formatNumber(value, { style: 'percent', maximumFractionDigits: 0 });
    return formatNumber(value, { maximumFractionDigits: m.kind === 'ratio' ? 2 : 1 });
}

/** Its move on the week before: { dir: up, down or same, text }, or null when either week can't be read. */
export function measureMove(m, now, before, floor) {
    if (measureText(m, now, floor) === null || measureText(m, before, floor) === null) return null;
    const d = now[m.key] - before[m.key];
    const dir = Math.abs(d) < 1e-9 ? 'same' : d > 0 ? 'up' : 'down';
    const size = m.kind === 'percent' ? formatNumber(Math.abs(d) * 100, { maximumFractionDigits: 0 }) + ' ' + t('admin.sharing.points') : formatNumber(Math.abs(d), { maximumFractionDigits: m.kind === 'ratio' ? 2 : 1 });
    return { dir, text: dir === 'same' ? t('admin.sharing.same') : size };
}

/** The surface that brings least per share, among those with enough shares to tell; null if none has. */
export function weakest(surfaces, floor) {
    const readable = (surfaces ?? []).filter((s) => s.shares >= floor && typeof s.arrivalsPerShare === 'number');
    return readable.length ? readable.reduce((a, b) => (b.arrivalsPerShare < a.arrivalsPerShare ? b : a)) : null;
}

/** The four measures as one ruled panel (Hillel's Home: a label over a big value, its move small beside it). */
export const SharingPanel = {
    props: { report: { type: Object, required: true } },
    setup: () => ({ MEASURES, measureText, measureMove }),
    template: `
        <dl class="card grid grid-cols-2 overflow-hidden min-[900px]:grid-cols-4" data-sharing-panel>
            <div v-for="m in MEASURES" :key="m.key" class="-mb-px -me-px min-w-0 border-b border-e border-line p-4 sm:p-5" :data-measure="m.key">
                <dt class="text-sm text-muted">{{ t(m.label) }}</dt>
                <dd class="mt-2 flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
                    <template v-if="measureText(m, report.headline, report.floor) !== null">
                        <span class="text-3xl font-extrabold tabular-nums tracking-tight text-cream" data-value>{{ measureText(m, report.headline, report.floor) }}</span>
                        <span v-if="measureMove(m, report.headline, report.before, report.floor)" class="text-sm font-semibold tabular-nums" :class="moveClass(m)" data-move :data-dir="measureMove(m, report.headline, report.before, report.floor).dir"><span v-if="measureMove(m, report.headline, report.before, report.floor).dir !== 'same'" class="me-0.5" aria-hidden="true">{{ measureMove(m, report.headline, report.before, report.floor).dir === 'up' ? '↑' : '↓' }}</span>{{ measureMove(m, report.headline, report.before, report.floor).text }}<span class="sr-only"> {{ t('admin.sharing.onLastWeek') }}</span></span>
                    </template>
                    <span v-else class="text-sm text-muted" data-too-few>{{ t('admin.sharing.tooFew') }}</span>
                </dd>
            </div>
        </dl>`,
    methods: {
        moveClass(m) {
            const dir = measureMove(m, this.report.headline, this.report.before, this.report.floor)?.dir;
            return dir === 'up' ? 'text-up' : dir === 'down' ? 'text-down' : 'text-muted';
        },
    },
};

export const SharingSection = {
    components: { LoadFailed, SharingPanel },
    props: { me: { type: Object, required: true } },
    data: () => ({ report: null, failed: false }),
    setup: () => ({ formatNumber, surfaceName, METHODS }),
    template: `
        <section class="space-y-6" aria-labelledby="sharing-h" data-sharing>
            <div>
                <h1 id="sharing-h" class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.sharing') }}</h1>
                <p class="mt-1 max-w-prose text-sm text-muted">{{ report ? t('admin.sharing.intro', { from: day(report.from), to: day(report.to), floor: report.floor }) : t('admin.sharing.introShort') }}</p>
            </div>
            <LoadFailed v-if="failed" :message="t('admin.sharing.failed')" @retry="load" />
            <p v-else-if="!report" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <SharingPanel :report="report" />
                <p class="text-sm text-muted" data-sharing-totals>{{ t('admin.sharing.totals', { active: formatNumber(report.headline?.active ?? 0), shares: formatNumber(report.headline?.shares ?? 0), arrivals: formatNumber(report.headline?.arrivals ?? 0) }) }}</p>
                <section class="space-y-3" aria-labelledby="sharing-surfaces-h">
                    <h2 id="sharing-surfaces-h" class="text-lg font-bold text-cream">{{ t('admin.sharing.bySurface') }}</h2>
                    <p v-if="!surfaces.length" class="text-sm text-muted">{{ t('admin.sharing.noShares') }}</p>
                    <!-- A phone: a short block a surface, never a table to scroll sideways. -->
                    <ul v-if="surfaces.length" class="card divide-y divide-line sm:hidden">
                        <li v-for="s in surfaces" :key="s.surface" class="space-y-1 p-4 text-sm" :data-surface-phone="s.surface">
                            <p class="flex flex-wrap items-baseline justify-between gap-2"><span class="font-semibold text-cream">{{ surfaceName(s.surface) }}</span><span class="font-semibold tabular-nums text-cream">{{ perShare(s) }} <span class="font-normal text-muted">{{ t('admin.sharing.col.perShare') }}</span></span></p>
                            <p class="text-muted">{{ t('admin.sharing.col.shares') }} {{ formatNumber(s.shares) }}<template v-if="methodsText(s)">: {{ methodsText(s) }}</template></p>
                            <p class="text-muted"><template v-if="s.opened !== null">{{ t('admin.sharing.col.opened') }} {{ formatNumber(s.opened) }} · </template>{{ t('admin.sharing.col.arrivals') }} {{ formatNumber(s.arrivals) }} · {{ t('admin.sharing.col.lesson1') }} {{ formatNumber(s.lesson1) }} · {{ t('admin.sharing.col.signedUp') }} {{ formatNumber(s.signedUp) }}</p>
                        </li>
                    </ul>
                    <div v-if="surfaces.length" class="card hidden overflow-x-auto sm:block">
                        <table class="data-table">
                            <thead><tr>
                                <th>{{ t('admin.sharing.col.surface') }}</th>
                                <th class="text-end">{{ t('admin.sharing.col.shares') }}</th>
                                <th>{{ t('admin.sharing.col.how') }}</th>
                                <th class="text-end">{{ t('admin.sharing.col.opened') }}</th>
                                <th class="text-end">{{ t('admin.sharing.col.arrivals') }}</th>
                                <th class="text-end">{{ t('admin.sharing.col.lesson1') }}</th>
                                <th class="text-end">{{ t('admin.sharing.col.signedUp') }}</th>
                                <th class="text-end">{{ t('admin.sharing.col.perShare') }}</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="s in surfaces" :key="s.surface" :data-surface="s.surface">
                                    <td class="text-cream">{{ surfaceName(s.surface) }}</td>
                                    <td class="text-end tabular-nums">{{ formatNumber(s.shares) }}</td>
                                    <td class="text-xs text-muted">{{ methodsText(s) }}</td>
                                    <td class="text-end tabular-nums">{{ s.opened === null ? '' : formatNumber(s.opened) }}</td>
                                    <td class="text-end tabular-nums">{{ formatNumber(s.arrivals) }}</td>
                                    <td class="text-end tabular-nums">{{ formatNumber(s.lesson1) }}</td>
                                    <td class="text-end tabular-nums">{{ formatNumber(s.signedUp) }}</td>
                                    <td class="text-end tabular-nums font-semibold text-cream">{{ perShare(s) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </section>`,
    computed: {
        /** Most shared first. */
        surfaces: (vm) => [...(vm.report?.surfaces ?? [])].sort((a, b) => b.shares - a.shares || b.arrivals - a.arrivals),
    },
    created() {
        this.load();
    },
    methods: {
        async load() {
            this.failed = false;
            try {
                this.report = (await api.admin.sharing()).data;
            } catch (error) {
                console.warn('Sharing did not load', error);
                this.failed = true;
            }
        },
        /** Arrivals a share, or too few to tell under the floor. */
        perShare(s) {
            return s.shares < this.report.floor || typeof s.arrivalsPerShare !== 'number' ? t('admin.sharing.tooFew') : formatNumber(s.arrivalsPerShare, { maximumFractionDigits: 2 });
        },
        /** How it was shared: "7 copied, 2 from the share sheet". */
        methodsText(s) {
            return Object.entries(s.methods ?? {}).filter(([, n]) => n > 0).sort((a, b) => b[1] - a[1]).map(([key, n]) => t(METHODS[key] ?? METHODS.sheet, { count: n })).join(', ');
        },
        day: (d) => formatDate(`${d}T12:00:00`, { day: 'numeric', month: 'short' }),
        go: (path) => navigate(path),
    },
};
