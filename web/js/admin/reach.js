// People using the app (Hillel, 2026-10-31: "people using the app broken down into signed in vs
// anonymous"), on Growth's Overview, following its switch (Hillel: "today/7 day/30 days all show
// the same thing"): Today, today so far beside the same weekday last week; 7 and 30 days, a bar a day
// beside the day one period earlier; 12 weeks, a bar a week beside the week 12 earlier. Stacked signed
// in and guests (admin.reach.show). From the anonymous counts only, so never unique people: the best
// measure the whole view has is shown and named plainly (devices in a week, devices a day, else app
// opens, every load). Plain bars, no library.

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { change, LoadFailed } from './common.js';

const MEASURES = {
    reach: { title: tk('admin.reach.byWeek'), note: tk('admin.reach.byWeekNote') },
    devices: { title: tk('admin.reach.byDay'), note: tk('admin.reach.byDayNote') },
    opens: { title: tk('admin.reach.opens'), note: tk('admin.reach.opensNote') },
    dayDevices: { title: tk('admin.reach.dayDevices'), note: tk('admin.reach.byDayNote') },
    dayOpens: { title: tk('admin.reach.dayOpens'), note: tk('admin.reach.opensNote') },
};

/** The best measure all of `weeks` have: 'reach', then 'devices', then 'opens'. */
export function bestMeasure(weeks = []) {
    if (!weeks.length) return 'opens';
    return ['reach', 'devices'].find((m) => weeks.every((w) => w[m])) ?? 'opens';
}

/** A week (or a day) in one measure: { account, guest, total }, or null where it isn't counted. */
export function weekIn(week, measure) {
    const v = week?.[measure];
    if (!v) return null;
    return { from: week.from, to: week.to, account: v.account ?? 0, guest: v.guest ?? 0, total: (v.account ?? 0) + (v.guest ?? 0) };
}

/**
 * The daily view: the last `count` of `days` (oldest first), each paired with the day `count` earlier,
 * in devices if every one of them has devices, else opens. `{ measure, bars: [{ key, now, before }] }`.
 */
export function dailyBars(days = [], count) {
    const shown = days.slice(-count);
    const earlier = days.slice(Math.max(0, days.length - 2 * count), Math.max(0, days.length - count));
    const offset = shown.length - earlier.length; // the earlier days missing at the start, before the counts began
    const measure = shown.length && [...shown, ...earlier].every((d) => d.devices) ? 'devices' : 'opens';
    const bars = shown.map((d, i) => ({ key: d.day, now: weekIn(d, measure), before: weekIn(earlier[i - offset] ?? null, measure) }));
    return { measure, bars };
}

/**
 * Today so far against the same weekday a week before: from `today` when the server sends it, else
 * the last whole day (said as such). `{ measure, now, before, day, soFar }`, or null with no days.
 */
export function todayPair(days = [], today = null) {
    const now = today ?? days.at(-1) ?? null;
    if (!now) return null;
    const weekBefore = new Date(Date.parse(`${now.day}T12:00:00Z`) - 7 * 86400000).toISOString().slice(0, 10);
    const before = days.find((d) => d.day === weekBefore) ?? null;
    const measure = now.devices && (!before || before.devices) ? 'devices' : 'opens';
    return { measure, now: weekIn(now, measure), before: weekIn(before, measure), day: now.day, soFar: !!today };
}

export const ReachChart = {
    components: { LoadFailed },
    // days: the Overview's switch: 1, 7 or 30, or 84 for the 12 weeks, week by week.
    props: { days: { type: Number, default: 84 } },
    data: () => ({ reach: null, failed: false }),
    setup: () => ({ MEASURES, formatNumber }),
    template: `
        <section class="card min-w-0 p-5 md:p-6" aria-labelledby="reach-h" data-reach-chart :data-view="view" :data-measure="measure">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 id="reach-h" class="text-lg font-bold text-cream">{{ t('admin.reach.title') }}</h2>
                    <p class="text-sm text-muted">{{ t(MEASURES[measure].title) }}</p>
                </div>
                <dl v-if="latest" class="flex gap-6 text-end">
                    <div><dt class="text-xs text-muted">{{ t('admin.reach.account') }}</dt><dd class="text-2xl font-extrabold tabular-nums text-cream" data-latest-account>{{ number(latest.account) }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ t('admin.reach.guest') }}</dt><dd class="text-2xl font-extrabold tabular-nums text-cream" data-latest-guest>{{ number(latest.guest) }}</dd></div>
                </dl>
            </div>
            <LoadFailed v-if="failed" class="mt-4" :message="failed" @retry="load" />
            <p v-else-if="!reach" class="mt-4 text-muted" role="status">{{ t('admin.loading') }}</p>
            <!-- Today: two stacked rows, today so far and the same weekday a week before. -->
            <dl v-else-if="view === 'today'" class="mt-5 space-y-3" data-today>
                <div v-for="row in todayRows" :key="row.key" class="grid grid-cols-[7rem_minmax(0,1fr)_4rem] items-center gap-3" :data-row="row.key">
                    <dt class="text-sm text-muted">{{ row.label }}</dt>
                    <dd class="flex h-6 overflow-hidden rounded-md bg-raised" :class="row.key === 'before' ? 'opacity-50' : ''" :aria-label="row.words">
                        <i v-if="row.value" class="block bg-accent" :style="{ width: share(row.value.account) + '%' }"></i>
                        <i v-if="row.value" class="block bg-part-4" :style="{ width: share(row.value.guest) + '%' }"></i>
                    </dd>
                    <dd class="text-end text-sm font-semibold tabular-nums text-cream">{{ row.value ? number(row.value.total) : '-' }}</dd>
                </div>
            </dl>
            <template v-else>
                <ol class="mt-5 grid h-40 items-end" :class="bars.length > 12 ? 'gap-0.5' : 'gap-1.5 sm:gap-2'" :style="{ gridTemplateColumns: 'repeat(' + Math.max(1, bars.length) + ', minmax(0, 1fr))' }" :aria-label="t('admin.reach.chart')">
                    <li v-for="b in bars" :key="b.key" class="flex h-full items-end gap-px" :data-bar="b.key" :title="label(b)">
                        <span class="sr-only">{{ label(b) }}</span>
                        <i class="block w-2/5 rounded-t-sm bg-cream/20" :style="{ height: height(b.before?.total) + '%' }" aria-hidden="true"></i>
                        <span v-if="b.now" class="flex w-3/5 flex-col justify-end" :style="{ height: height(b.now.total) + '%' }" aria-hidden="true">
                            <i class="block rounded-t-sm bg-part-4" :style="{ flexGrow: b.now.guest }"></i>
                            <i class="block bg-accent" :style="{ flexGrow: b.now.account }"></i>
                        </span>
                        <i v-else class="block h-1 w-3/5 rounded-full bg-line" aria-hidden="true"></i>
                    </li>
                </ol>
                <div class="mt-2 flex justify-between text-xs text-muted" aria-hidden="true"><span>{{ day(bars[0]?.key) }}</span><span>{{ lastLabel }}</span></div>
            </template>
            <template v-if="reach">
                <p class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted">
                    <span class="inline-flex items-center gap-1.5"><i class="inline-block size-2.5 rounded-sm bg-accent" aria-hidden="true"></i>{{ t('admin.reach.account') }}</span>
                    <span class="inline-flex items-center gap-1.5"><i class="inline-block size-2.5 rounded-sm bg-part-4" aria-hidden="true"></i>{{ t('admin.reach.guest') }}</span>
                    <span v-if="view !== 'today'" class="inline-flex items-center gap-1.5"><i class="inline-block size-2.5 rounded-sm bg-cream/20" aria-hidden="true"></i>{{ beforeKey }}</span>
                </p>
                <p class="mt-2 text-xs text-muted" data-reach-note>{{ t(MEASURES[measure].note) }}</p>
            </template>
        </section>`,
    computed: {
        view() {
            return this.days === 1 ? 'today' : this.days === 7 || this.days === 30 ? 'days' : 'weeks';
        },
        daily() {
            return dailyBars(this.reach?.days, this.days);
        },
        pair() {
            return todayPair(this.reach?.days, this.reach?.today ?? null);
        },
        weekMeasure() {
            return bestMeasure(this.reach?.weeks);
        },
        measure() {
            if (this.view === 'today') return this.pair?.measure === 'devices' ? 'dayDevices' : 'dayOpens';
            if (this.view === 'days') return this.daily.measure === 'devices' ? 'dayDevices' : 'dayOpens';
            return this.weekMeasure;
        },
        bars() {
            if (this.view === 'days') return this.daily.bars;
            return (this.reach?.weeks ?? []).map((w, i) => ({ key: w.to, soFar: (w.days ?? 7) < 7, now: weekIn(w, this.weekMeasure), before: weekIn(this.reach.previous?.[i], this.weekMeasure) }));
        },
        todayRows() {
            const p = this.pair;
            if (!p) return [];
            const weekday = formatDate(`${p.day}T12:00:00Z`, { weekday: 'short' });
            return [
                { key: 'now', label: p.soFar ? t('admin.reach.todaySoFar') : t('admin.reach.lastDay', { day: this.day(p.day) }), value: p.now, words: this.rowWords(p.now) },
                { key: 'before', label: t('admin.reach.weekdayBefore', { weekday }), value: p.before, words: this.rowWords(p.before) },
            ];
        },
        latest() {
            if (this.view === 'today') return this.pair?.now ?? null;
            return this.bars.at(-1)?.now ?? null;
        },
        most() {
            if (this.view === 'today') return Math.max(1, this.pair?.now?.total ?? 0, this.pair?.before?.total ?? 0);
            return Math.max(1, ...this.bars.flatMap((b) => [b.now?.total ?? 0, b.before?.total ?? 0]));
        },
        beforeKey() {
            return this.view === 'days' ? t('admin.reach.daysBefore', { count: this.days }) : t('admin.reach.before');
        },
        lastLabel() {
            const last = this.bars.at(-1);
            return last?.soFar ? t('admin.reach.soFar', { to: this.day(last.key) }) : this.day(last?.key);
        },
    },
    created() {
        this.load();
    },
    methods: {
        height(total) {
            return total ? Math.max(2, Math.round((total / this.most) * 100)) : 0;
        },
        share(value) {
            return value ? Math.round((value / this.most) * 1000) / 10 : 0;
        },
        /** Opens and devices a day in a week are averages (one decimal); a day's are whole. */
        number(value) {
            return formatNumber(value, { maximumFractionDigits: 1 });
        },
        day(value) {
            return value ? formatDate(`${value}T12:00:00Z`, { day: 'numeric', month: 'short' }) : '';
        },
        rowWords(value) {
            return value ? t('admin.reach.rowWords', { account: this.number(value.account), guest: this.number(value.guest) }) : t('admin.reach.notCounted');
        },
        /** "25 Oct: 18 signed in, 57 guests. 7 days earlier 65 (+16%)." */
        label(b) {
            if (!b.now) return t('admin.reach.weekPending', { to: this.day(b.key) });
            const key = b.soFar ? tk('admin.reach.weekSoFarLabel') : this.view === 'days' ? tk('admin.reach.dayLabel') : tk('admin.reach.weekLabel');
            const line = t(key, { to: this.day(b.key), account: this.number(b.now.account), guest: this.number(b.now.guest) });
            if (!b.before) return line;
            const moved = change(Math.round(b.now.total), Math.round(b.before.total)).text;
            const earlier = this.view === 'days' ? t('admin.reach.dayBefore', { count: this.days, total: this.number(b.before.total), change: moved }) : t('admin.reach.weekBefore', { total: this.number(b.before.total), change: moved });
            return `${line} ${earlier}`;
        },
        async load() {
            this.failed = false;
            try {
                this.reach = (await api.admin.reach.show()).data;
            } catch (error) {
                console.warn('People using the app did not load', error);
                this.failed = t('admin.reach.failed');
            }
        },
    },
};
