// Growth's whole-site visits (the coordinator, 2026-11-08: "so Hillel sees whole-site visits without a pull"): every
// public page's loads by its section (the sv_* kinds, App\Support\Site\SiteVisit: the footer carries the section,
// site.js sends it), over the last 7 days, a row a section with a bar a day and the week's total. From the anonymous
// daily counts (admin.counts.show), so nothing here follows anyone. The Steps' first number is the home page's own
// loads (site_visit), one of these sections.

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { changeText, VERDICTS } from './growth-compare.js';

const DAYS = 7;
export const SECTIONS = {
    sv_home: tk('admin.growth.sections.home'),
    sv_courses: tk('admin.growth.sections.courses'),
    sv_course: tk('admin.growth.sections.course'),
    sv_lesson: tk('admin.growth.sections.lesson'),
    sv_tools: tk('admin.growth.sections.tools'),
    sv_songs: tk('admin.growth.sections.songs'),
    sv_part: tk('admin.growth.sections.part'),
    sv_guides: tk('admin.growth.sections.guides'),
    sv_other: tk('admin.growth.sections.other'),
};
const day = (date) => formatDate(`${date}T12:00:00Z`, { weekday: 'short', month: 'short', day: 'numeric', timeZone: 'UTC' });

/** The last `count` dates up to `last` (YYYY-MM-DD), oldest first. */
export function datesTo(last, count = DAYS) {
    const end = Date.parse(`${last}T12:00:00Z`);
    return Array.from({ length: count }, (_, i) => new Date(end - (count - 1 - i) * 864e5).toISOString().slice(0, 10));
}

/** The counts' days as a row a section: its loads each day (guest and account together) and the week's total. */
export function sectionRows(days, dates) {
    const by = new Map();
    for (const d of days ?? []) {
        if (!(d.kind in SECTIONS)) continue;
        by.set(`${d.kind}|${d.date}`, (by.get(`${d.kind}|${d.date}`) ?? 0) + (d.guest ?? 0) + (d.account ?? 0));
    }
    return Object.keys(SECTIONS).map((kind) => {
        const daily = dates.map((date) => ({ date, n: by.get(`${kind}|${date}`) ?? 0 }));
        return { kind, daily, total: daily.reduce((n, d) => n + d.n, 0) };
    });
}

export const SiteSectionsCard = {
    // `sections`: admin.growth.show's fair 7-day comparison per section (docs/growth-compare-spec.md 4.5), when the server sends it.
    props: { today: { type: String, default: '' }, sections: { type: Array, default: null } },
    data: () => ({ report: null, failed: false }),
    setup: () => ({ SECTIONS, formatNumber, day }),
    template: `
        <section class="card scroll-mt-36 p-5 md:p-6" aria-labelledby="growth-site-h" data-growth-site>
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 id="growth-site-h" class="text-lg font-bold text-cream">{{ t('admin.growth.sections.title') }}</h2>
                    <p class="text-sm text-muted">{{ t('admin.growth.sections.sub') }}</p>
                </div>
                <span class="shrink-0 rounded-md border border-warn/50 px-2 py-0.5 text-xs font-semibold text-warn">{{ t('admin.growth.last7') }}</span>
            </div>
            <p v-if="failed" class="mt-4 text-sm text-muted">{{ t('admin.growth.sections.failed') }} <button type="button" class="link" @click="load">{{ t('common.tryAgain') }}</button></p>
            <p v-else-if="!report" class="mt-4 text-sm text-muted" role="status">{{ t('admin.loading') }}</p>
            <table v-else class="mt-4 w-full text-sm" data-growth-site-table>
                <thead class="text-xs font-semibold uppercase tracking-wider text-muted">
                    <tr class="border-b border-line">
                        <th scope="col" class="pb-2 pe-2 text-start font-semibold">{{ t('admin.growth.sections.section') }}</th>
                        <th scope="col" class="pb-2 px-2 text-start font-semibold">{{ t('admin.growth.sections.byDay') }}</th>
                        <th scope="col" class="pb-2 ps-2 text-end font-semibold">{{ t('admin.growth.sections.week') }}</th>
                        <th v-if="sections" scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.growth.sections.vsBefore') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="row in rows" :key="row.kind" :data-section="row.kind">
                        <th scope="row" class="py-2 pe-2 text-start font-normal text-cream">{{ t(SECTIONS[row.kind]) }}</th>
                        <td class="px-2 py-2">
                            <span class="inline-flex h-6 items-end gap-1" role="img" :aria-label="t('admin.growth.sections.daysLabel', { counts: row.daily.map((d) => d.n).join(', ') })">
                                <span v-for="d in row.daily" :key="d.date" class="w-2.5 rounded-sm" :class="d.n ? 'bg-accent' : 'bg-raised'" :style="{ height: height(d.n) }" :title="t('admin.growth.sections.dayTitle', { day: day(d.date), count: formatNumber(d.n) })" :data-day="d.date" :data-n="d.n"></span>
                            </span>
                        </td>
                        <td class="ps-2 py-2 text-end tabular-nums text-cream" data-col="week">{{ formatNumber(row.total) }}</td>
                        <!-- Against the 7 days before, cut at the same time: "+n" or a percentage in ink, the arrow coloured
                             only when it's up or down past chance; too few said so; no chips (spec 4.5). -->
                        <td v-if="sections" class="ps-3 py-2 text-end text-sm tabular-nums whitespace-nowrap" data-col="vs" :data-verdict="vs(row.kind).verdict">
                            <template v-if="vs(row.kind).words"><span class="text-muted">{{ vs(row.kind).words }}</span></template>
                            <template v-else><span v-if="vs(row.kind).arrow" aria-hidden="true" class="me-1" :class="vs(row.kind).arrow === '↑' ? 'text-up' : 'text-down'">{{ vs(row.kind).arrow }}</span><span class="text-cream">{{ vs(row.kind).change }}</span><span v-if="vs(row.kind).arrow" class="sr-only">{{ vs(row.kind).said }}</span></template>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t border-line">
                        <th scope="row" class="pt-2 pe-2 text-start font-semibold text-cream">{{ t('admin.growth.sections.all') }}</th>
                        <td></td>
                        <td class="ps-2 pt-2 text-end tabular-nums font-bold text-cream" data-growth-site-total>{{ formatNumber(total) }}</td>
                        <td v-if="sections"></td>
                    </tr>
                </tfoot>
            </table>
            <!-- A part played on a song part page (part_play), read against those pages' visits: guests pressing play. -->
            <p v-if="report" class="mt-3 text-sm text-muted" data-growth-part-plays>{{ t('admin.growth.sections.partPlays', { plays: formatNumber(partPlays), visits: formatNumber(partVisits) }) }}</p>
        </section>`,
    computed: {
        dates() {
            const days = this.report?.days ?? [];
            const last = this.today || days.reduce((max, d) => (d.date > max ? d.date : max), '') || new Date().toISOString().slice(0, 10);
            return datesTo(last);
        },
        rows() {
            return sectionRows(this.report?.days, this.dates);
        },
        total() {
            return this.rows.reduce((n, r) => n + r.total, 0);
        },
        partPlays() {
            return (this.report?.days ?? []).filter((d) => d.kind === 'part_play' && this.dates.includes(d.date)).reduce((n, d) => n + (d.guest ?? 0) + (d.account ?? 0), 0);
        },
        partVisits() {
            return this.rows.find((r) => r.kind === 'sv_part')?.total ?? 0;
        },
        peak() {
            return Math.max(1, ...this.rows.flatMap((r) => r.daily.map((d) => d.n)));
        },
    },
    created() {
        this.load();
    },
    methods: {
        /** A section's row against the 7 days before: the change in ink, an arrow only for up or down, or words. */
        vs(kind) {
            const row = (this.sections ?? []).find((s) => s.key === kind)?.previous;
            const verdict = row?.verdict ?? 'noHistory';
            if (verdict === 'tooFew') return { verdict, words: t('admin.growth.sections.tooFew') };
            if (verdict === 'noHistory' || verdict === 'none') return { verdict, words: '-' };
            if (verdict === 'new') return { verdict, words: t(VERDICTS.new.words) };
            const arrow = verdict === 'up' ? '↑' : verdict === 'down' ? '↓' : '';
            return { verdict, arrow, change: changeText(row.change), said: arrow ? t(VERDICTS[verdict].words) : '' };
        },
        // One scale for every row, so the sections compare; a day with none is a sliver.
        height(n) {
            return n ? `${Math.max(15, Math.round((100 * n) / this.peak))}%` : '3px';
        },
        async load() {
            this.failed = false;
            try {
                this.report = (await api.admin.counts.show({ days: DAYS })).data;
            } catch (error) {
                console.warn('Site visits did not load', error);
                this.failed = true;
            }
        },
    },
};
