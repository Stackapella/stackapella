// Activity (docs/admin-analytics-plan.md, slice 5), under Growth: what happened lately from our own
// records (admin.activity.show; admins excluded): the latest sign-ups, trials, Plus starts and ends,
// coaching sales and school years, newest first; the last 24 hours of takes, scores and sign-ups as
// bars; what was used in 30 days by guests and accounts; and free accounts at or near their song
// limit. Plain bars, no library.

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { LoadFailed, remembers, zoneName } from './common.js';
import { KINDS as KIND_WORDS } from './usage.js';

const EVENTS = {
    sign_up: tk('admin.activity.kind.signUp'),
    trial_started: tk('admin.activity.kind.trial'),
    plus_started: tk('admin.activity.kind.plus'),
    plus_ended: tk('admin.activity.kind.plusEnded'),
    coaching_sale: tk('admin.activity.kind.coaching'),
    school_year: tk('admin.activity.kind.school'),
};
const OTHER = tk('admin.activity.kind.other');
// A store, as Revenue names it (the feed's detail for Plus events).
const STORES = { web: tk('admin.revenue.store.web'), apple: tk('admin.revenue.store.apple'), google: tk('admin.revenue.store.google') };
const SERIES = [
    { key: 'takes', label: tk('admin.activity.takes'), color: 'bg-accent' },
    { key: 'scores', label: tk('admin.activity.scores'), color: 'bg-part-4' },
    { key: 'signUps', label: tk('admin.activity.signUps'), color: 'bg-good' },
];

export const ActivitySection = {
    mixins: [remembers('activity', { series: SERIES.map((s) => s.key) })],
    components: { LoadFailed },
    props: { me: { type: Object, required: true } },
    data: () => ({ report: null, failed: false, series: 'takes' }),
    setup: () => ({ EVENTS, OTHER, SERIES, STORES, formatNumber }),
    template: `
        <section class="space-y-6" aria-labelledby="activity-h" data-activity>
            <div>
                <h1 id="activity-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.activity.title') }}</h1>
                <p class="mt-1 max-w-prose text-sm text-muted">{{ t('admin.activity.intro', { zone: zoneName(report?.timezone) }) }}</p>
            </div>
            <LoadFailed v-if="failed" :message="t('admin.activity.failed')" @retry="load" />
            <p v-else-if="!report" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <!-- The last 24 hours, one series at a time. -->
                    <section class="card min-w-0 p-5 md:p-6" aria-labelledby="activity-hours-h" data-activity-hours>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <h2 id="activity-hours-h" class="text-lg font-bold text-cream">{{ t('admin.activity.hours') }}</h2>
                            <div class="seg flex" role="radiogroup" :aria-label="t('admin.activity.hours')">
                                <button v-for="s in SERIES" :key="s.key" type="button" role="radio" :aria-checked="series === s.key" class="seg-option" @click="series = s.key">{{ t(s.label) }}</button>
                            </div>
                        </div>
                        <ol class="mt-5 grid h-32 grid-cols-[repeat(24,minmax(0,1fr))] items-end gap-0.5" :aria-label="t('admin.activity.hours')">
                            <li v-for="h in report.hours" :key="h.hour" class="flex h-full items-end" :title="hourLabel(h)" :data-hour="h.hour">
                                <span class="sr-only">{{ hourLabel(h) }}</span>
                                <i class="block w-full rounded-t-sm" :class="seriesColor" :style="{ height: height(h[series]) + '%' }" aria-hidden="true"></i>
                            </li>
                        </ol>
                        <div class="mt-2 flex justify-between text-xs text-muted" aria-hidden="true"><span>{{ hourName(report.hours[0]?.hour) }}</span><span>{{ hourName(report.hours.at(-1)?.hour) }}</span></div>
                        <p class="mt-2 text-sm text-muted">{{ t('admin.activity.total', { count: formatNumber(total) }) }}</p>
                    </section>

                    <!-- Free accounts against their song limit. -->
                    <section class="card p-5 md:p-6" aria-labelledby="activity-limits-h" data-activity-limits>
                        <h2 id="activity-limits-h" class="text-lg font-bold text-cream">{{ t('admin.activity.limits') }}</h2>
                        <dl class="mt-4 grid grid-cols-3 gap-4">
                            <div><dt class="text-xs text-muted">{{ t('admin.activity.freeAccounts') }}</dt><dd class="text-2xl font-extrabold tabular-nums text-cream">{{ formatNumber(report.limits.freeAccounts) }}</dd></div>
                            <div><dt class="text-xs text-muted">{{ t('admin.activity.atLimit', { limit: report.limits.projectLimit }) }}</dt><dd class="text-2xl font-extrabold tabular-nums text-cream" data-at-limit>{{ formatNumber(report.limits.projectsAtLimit) }}</dd></div>
                            <div><dt class="text-xs text-muted">{{ t('admin.activity.nearLimit') }}</dt><dd class="text-2xl font-extrabold tabular-nums text-cream">{{ formatNumber(report.limits.projectsNearLimit) }}</dd></div>
                        </dl>
                        <p class="mt-3 text-sm text-muted">{{ t('admin.activity.limitsHint') }}</p>
                    </section>
                </div>

                <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <!-- The latest events, newest first. -->
                    <section class="card min-w-0 p-5 md:p-6" aria-labelledby="activity-feed-h" data-activity-feed>
                        <h2 id="activity-feed-h" class="text-lg font-bold text-cream">{{ t('admin.activity.feed') }}</h2>
                        <p v-if="!report.feed.length" class="mt-3 text-sm text-muted">{{ t('admin.activity.none') }}</p>
                        <ol v-else class="mt-3 divide-y divide-line text-sm">
                            <li v-for="(e, i) in report.feed" :key="i" class="flex items-baseline justify-between gap-3 py-2" :data-event="e.kind">
                                <span class="min-w-0"><span class="font-semibold text-cream">{{ t(EVENTS[e.kind] ?? OTHER) }}</span><span v-if="e.person" class="text-cream" translate="no"> · {{ e.person.name }}</span><span v-if="e.detail" class="text-muted" translate="no"> · {{ STORES[e.detail] ? t(STORES[e.detail]) : e.detail }}</span></span>
                                <time class="shrink-0 text-xs text-muted" :datetime="e.at">{{ when(e.at) }}</time>
                            </li>
                        </ol>
                    </section>

                    <!-- What was used in 30 days, by guests and by accounts. -->
                    <section class="card min-w-0 p-5 md:p-6" aria-labelledby="activity-features-h" data-activity-features>
                        <h2 id="activity-features-h" class="text-lg font-bold text-cream">{{ t('admin.activity.features') }}</h2>
                        <ul class="mt-3 space-y-2.5 text-sm">
                            <li v-for="f in features" :key="f.kind" class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1" :data-feature="f.kind">
                                <span class="truncate text-cream">{{ kindName(f.kind) }}</span>
                                <span class="tabular-nums text-muted">{{ t('admin.activity.split', { account: formatNumber(f.account), guest: formatNumber(f.guest) }) }}</span>
                                <span class="col-span-2 flex h-2 overflow-hidden rounded-full bg-raised" aria-hidden="true"><i class="block bg-accent" :style="{ width: width(f.account) + '%' }"></i><i class="block bg-cream/40" :style="{ width: width(f.guest) + '%' }"></i></span>
                            </li>
                        </ul>
                        <p class="mt-3 flex flex-wrap gap-x-5 text-xs text-muted">
                            <span class="inline-flex items-center gap-1.5"><i class="inline-block size-2.5 rounded-sm bg-accent" aria-hidden="true"></i>{{ t('admin.activity.accounts') }}</span>
                            <span class="inline-flex items-center gap-1.5"><i class="inline-block size-2.5 rounded-sm bg-cream/40" aria-hidden="true"></i>{{ t('admin.activity.guests') }}</span>
                        </p>
                    </section>
                </div>
            </template>
        </section>`,
    computed: {
        /** The report's time zone, when the browser knows it (else UTC); never one written here. */
        zone() {
            const zone = this.report?.timezone;
            try {
                if (zone) new Intl.DateTimeFormat('en', { timeZone: zone });
                return zone || 'UTC';
            } catch {
                return 'UTC';
            }
        },
        most() {
            return Math.max(1, ...(this.report?.hours ?? []).map((h) => h[this.series] ?? 0));
        },
        total() {
            return (this.report?.hours ?? []).reduce((sum, h) => sum + (h[this.series] ?? 0), 0);
        },
        seriesColor() {
            return SERIES.find((s) => s.key === this.series)?.color ?? 'bg-accent';
        },
        /** Most used first. */
        features() {
            return [...(this.report?.features ?? [])].sort((a, b) => b.guest + b.account - (a.guest + a.account));
        },
        mostFeature() {
            return Math.max(1, ...this.features.map((f) => f.guest + f.account));
        },
    },
    created() {
        this.load();
    },
    methods: {
        zoneName,
        height(value) {
            return value ? Math.max(3, Math.round((value / this.most) * 100)) : 0;
        },
        width(value) {
            return value ? Math.max(1, (value / this.mostFeature) * 100) : 0;
        },
        kindName(kind) {
            return KIND_WORDS[kind] ? t(KIND_WORDS[kind]) : kind;
        },
        hourName(hour) {
            return hour ? formatDate(hour, { hour: 'numeric', timeZone: this.zone }) : '';
        },
        hourLabel(h) {
            return t('admin.activity.hourLabel', { hour: this.hourName(h.hour), takes: formatNumber(h.takes), scores: formatNumber(h.scores), signUps: formatNumber(h.signUps) });
        },
        when(at) {
            return formatDate(at, { dateStyle: 'medium', timeStyle: 'short', timeZone: this.zone });
        },
        async load() {
            this.failed = false;
            try {
                this.report = (await api.admin.activity.show()).data;
            } catch (error) {
                console.warn('Activity did not load', error);
                this.failed = true;
            }
        },
    },
};
