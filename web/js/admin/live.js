// Live (Numbers > Live; Hillel, 2026-11-12: "i really like the dashboard from code/eventschedule, lets copy the best
// parts and combine it into admin growth/numbers"; docs/realtime-dashboard-plan.md). Who's here right now and the last
// 24 hours at a glance, from anonymous counts only: page views per minute and ID-less heartbeats held briefly in cache,
// never stored ("Allow, cache only"), and sign-ups' sources as totals ("Totals only"). The one list of people is the
// day's new accounts, which admin already shows. admin.live.show every 15 seconds and admin.pulse.show (with Revenue)
// every 60, only while the page is in view; caught up at once on coming back. A timer for the page, never for audio.

import { api } from '../api.js';
import { formatNumber, t, timeAgo, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { Switch } from '../components/controls.js';
import { change, LoadFailed, remembers } from './common.js';
import { sourceName } from './growth.js';
import { money } from './revenue.js';
import { KINDS } from './usage.js';
import { updatedAgo } from './usage.js';

const LIVE_MS = 15000;
const PULSE_MS = 60000;
const TICK_MS = 1000;
// Where people are, by the area their page belongs to; an area the server adds later shows by its key until named here.
const AREAS = { courses: tk('admin.live.area.courses'), tools: tk('admin.live.area.tools'), site: tk('admin.live.area.site'), studio: tk('admin.live.area.studio'), app: tk('admin.live.area.app') };
// A new account's steps in the order a person meets them (signUps24h.funnel and each person's `steps`).
export const STEPS = ['created', 'lesson1', 'wholeSong', 'keptPart', 'shared'];
const STEP_NAMES = { created: tk('admin.live.step.created'), lesson1: tk('admin.live.step.lesson1'), wholeSong: tk('admin.live.step.wholeSong'), keptPart: tk('admin.live.step.keptPart'), shared: tk('admin.live.step.shared') };

/** Bar heights as a share of the tallest (a zero still shows as a sliver, so the row reads as time). */
export function heights(values = []) {
    const top = Math.max(1, ...values);
    return values.map((v) => Math.max(v ? 4 : 1.5, Math.round((v / top) * 100)));
}

/** A small line's points in a 100 x 32 box, from 0 at the foot; one value is a flat line. */
export function linePoints(values = [], width = 100, height = 32) {
    if (!values.length) return '';
    const top = Math.max(1, ...values);
    const step = values.length > 1 ? width / (values.length - 1) : 0;
    return values.map((v, i) => `${Math.round(i * step * 10) / 10},${Math.round((height - (v / top) * (height - 2) - 1) * 10) / 10}`).join(' ');
}

/** Whether the last poll is fresh: 'live', 'stale' (a poll failed; the numbers kept) or 'paused' (the tab is hidden). */
export function liveState({ visible, staleSince }) {
    if (!visible) return 'paused';
    return staleSince ? 'stale' : 'live';
}

const STATE_WORDS = { live: tk('admin.live.state.live'), stale: tk('admin.live.state.stale'), paused: tk('admin.live.state.paused') };
const STATE_DOT = { live: 'bg-good', stale: 'bg-warn', paused: 'bg-muted' };

/** A row of bars over time, the last one "so far" (lighter, dashed), its ends labelled; named for screen readers. */
const Bars = {
    props: { values: { type: Array, required: true }, label: { type: String, required: true }, start: { type: String, required: true }, end: { type: String, required: true }, soFar: { type: String, required: true }, tall: Boolean },
    setup: () => ({ heights }),
    template: `
        <figure class="min-w-0">
            <div class="flex items-end gap-[2px]" :class="tall ? 'h-24' : 'h-14'" role="img" :aria-label="label" data-bars>
                <span v-for="(h, i) in heights(values)" :key="i" class="min-w-0 flex-1 rounded-t-[2px]" :class="i === values.length - 1 ? 'border border-dashed border-accent bg-accent/30' : 'bg-accent'" :style="{ height: h + '%' }" :title="String(values[i])" :data-so-far="i === values.length - 1 ? '' : null"></span>
            </div>
            <figcaption class="mt-1.5 flex justify-between gap-2 text-xs text-muted"><span>{{ start }}</span><span class="inline-flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-[2px] border border-dashed border-accent bg-accent/30" aria-hidden="true"></span>{{ soFar }}</span><span>{{ end }}</span></figcaption>
        </figure>`,
};

export const LiveSection = {
    mixins: [remembers('live', { showAdmins: [false, true] })],
    components: { Bars, LoadFailed, Switch },
    props: { me: { type: Object, required: true } },
    data: () => ({ showAdmins: false, live: null, pulse: null, revenue: null, failed: false, updatedAt: null, staleSince: null, now: Date.now(), visible: document.visibilityState === 'visible' }),
    setup: () => ({ AREAS, STEPS, STEP_NAMES, KINDS, formatNumber, money, sourceName, navigate }),
    template: `
        <section class="space-y-6" aria-labelledby="live-h" data-live>
            <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
                <div class="min-w-0">
                    <h1 id="live-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.live.title') }}</h1>
                    <p class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted">
                        <span class="inline-flex items-center gap-1.5 font-semibold" :class="state === 'live' ? 'text-cream' : ''" role="status" :data-live-state="state"><span class="size-2 rounded-full" :class="STATE_DOT[state]" aria-hidden="true"></span>{{ t(STATE_WORDS[state]) }}</span>
                        <template v-if="updatedAt"><span aria-hidden="true">·</span><span data-live-updated>{{ t('admin.usage.updated', { when: updated }) }}</span></template>
                    </p>
                    <p class="mt-1 max-w-prose text-sm text-muted">{{ t('admin.live.intro') }}</p>
                </div>
                <div class="w-fit min-w-52" data-live-admins><Switch :on="showAdmins" :label="t('admin.live.showAdmins')" :hint="t('admin.live.showAdminsHint')" @change="setAdmins" /></div>
            </div>

            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!live" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <!-- The numbers as one ruled panel (design rules: a dashboard's numbers): right now with its minutes, the
                     week's signed-in singers with twelve weeks behind them, and revenue. -->
                <div class="card grid overflow-hidden min-[900px]:grid-cols-[1.35fr_1fr_1fr]" data-live-panel>
                    <section class="-mb-px -me-px min-w-0 border-b border-e border-line p-4 sm:p-5" aria-labelledby="live-now-h" data-live-now>
                        <h2 id="live-now-h" class="text-sm text-muted">{{ t('admin.live.rightNow') }}</h2>
                        <p class="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <span class="text-4xl font-extrabold tabular-nums tracking-tight text-cream" data-live-total>{{ formatNumber(live.now.total) }}</span>
                            <span class="text-sm text-muted" data-live-split>{{ t('admin.live.split', { signedIn: formatNumber(live.now.signedIn), guests: formatNumber(live.now.guests) }) }}</span>
                        </p>
                        <p class="mt-4 flex flex-wrap items-baseline justify-between gap-x-3 text-sm"><span class="font-semibold text-cream">{{ t('admin.live.viewsPerMinute') }}</span><span class="tabular-nums text-muted" data-live-views30>{{ t('admin.live.views30', { count: live.views.last30 }) }}</span></p>
                        <Bars class="mt-2" :values="live.views.perMinute" :label="t('admin.live.viewsChart', { count: live.views.last30 })" :start="t('admin.live.minutesAgo')" :end="t('admin.live.now')" :so-far="t('admin.live.thisMinute')" />
                    </section>
                    <section class="-mb-px -me-px min-w-0 border-b border-e border-line p-4 sm:p-5" aria-labelledby="live-active-h" data-live-active>
                        <h2 id="live-active-h" class="text-sm text-muted">{{ t('admin.live.active7d') }}</h2>
                        <template v-if="pulse">
                            <p class="mt-2 flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
                                <span class="text-4xl font-extrabold tabular-nums tracking-tight text-cream" data-live-active-n>{{ formatNumber(pulse.active.signedIn7d) }}</span>
                                <span class="text-sm font-semibold tabular-nums" :class="activeChange.dir === 'up' ? 'text-up' : activeChange.dir === 'down' ? 'text-down' : 'text-muted'" data-change :data-dir="activeChange.dir"><span v-if="activeChange.dir === 'up' || activeChange.dir === 'down'" class="me-0.5" aria-hidden="true">{{ activeChange.dir === 'up' ? '↑' : '↓' }}</span>{{ activeChange.text }}<span class="sr-only"> {{ t('admin.live.vsWeekBefore') }}</span></span>
                            </p>
                            <p class="mt-1 text-sm text-muted" data-live-active30>{{ t('admin.live.active30d', { count: pulse.active.active30d }) }}</p>
                            <figure class="mt-4">
                                <svg viewBox="0 0 100 32" preserveAspectRatio="none" class="h-14 w-full overflow-visible" role="img" :aria-label="t('admin.live.weeksChart', { first: formatNumber(pulse.active.weeks[0] ?? 0), last: formatNumber(pulse.active.weeks.at(-1) ?? 0) })" data-live-weeks>
                                    <polyline :points="weekLine" fill="none" stroke="currentColor" stroke-width="1.75" vector-effect="non-scaling-stroke" stroke-linejoin="round" class="text-accent" />
                                </svg>
                                <figcaption class="mt-1.5 flex justify-between gap-2 text-xs text-muted"><span>{{ t('admin.live.weeksAgo') }}</span><span>{{ t('admin.live.thisWeek') }}</span></figcaption>
                            </figure>
                        </template>
                        <p v-else class="mt-2 text-sm text-muted">{{ t('admin.loading') }}</p>
                    </section>
                    <section class="-mb-px -me-px min-w-0 border-b border-e border-line p-4 sm:p-5" aria-labelledby="live-revenue-h" data-live-revenue>
                        <h2 id="live-revenue-h" class="flex items-baseline justify-between gap-2 text-sm text-muted">{{ t('admin.live.mrr') }}<a href="/admin/revenue" class="link tap-44 inline-flex min-h-6 items-center gap-1" @click.prevent="navigate('/admin/revenue')">{{ t('admin.live.openRevenue') }}<span aria-hidden="true" class="rtl:-scale-x-100">→</span></a></h2>
                        <template v-if="revenue">
                            <p v-for="m in mrr" :key="m.currency" class="mt-2 text-4xl font-extrabold tabular-nums tracking-tight text-cream" :data-currency="m.currency">{{ money(m.cents, m.currency) }}</p>
                            <dl class="mt-3 space-y-1.5 text-sm">
                                <div class="flex flex-wrap justify-between gap-x-3"><dt class="text-muted">{{ t('admin.live.arr') }}</dt><dd class="tabular-nums text-cream" data-live-arr>{{ moneyList(revenue.arr) }}</dd></div>
                                <div v-if="revenue.ifAllConvert" class="flex flex-wrap justify-between gap-x-3"><dt class="text-muted">{{ t('admin.live.ifAllConvert') }}</dt><dd class="tabular-nums text-cream" data-live-convert>{{ t('admin.live.perMonth', { money: moneyList(revenue.ifAllConvert.mrr) }) }}</dd></div>
                            </dl>
                        </template>
                        <p v-else class="mt-2 text-sm text-muted">{{ t('admin.loading') }}</p>
                    </section>
                </div>

                <!-- Where they are now: an area a card, busiest first, its three most viewed pages in the last 30 minutes. -->
                <section class="space-y-3" aria-labelledby="live-areas-h" data-live-areas>
                    <h2 id="live-areas-h" class="text-xl font-bold text-cream">{{ t('admin.live.whereTitle') }} <span class="ms-1 text-sm font-normal text-muted">{{ t('admin.live.whereHint') }}</span></h2>
                    <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <li v-for="area in areas" :key="area.key" class="card min-w-0 p-4" :class="area.now ? '' : 'opacity-80'" :data-area="area.key">
                            <div class="flex items-baseline justify-between gap-3">
                                <h3 class="font-semibold text-cream">{{ areaName(area.key) }}</h3>
                                <p class="text-sm tabular-nums text-muted"><span class="text-2xl font-extrabold text-cream" data-area-now>{{ formatNumber(area.now) }}</span> {{ t('admin.live.here') }}</p>
                            </div>
                            <p class="mt-0.5 text-sm text-muted">{{ t('admin.live.areaViews', { count: area.views30 }) }}</p>
                            <ol v-if="area.top?.length" class="mt-3 space-y-1 border-t border-line pt-3 text-sm">
                                <li v-for="page in area.top" :key="page.page" class="flex justify-between gap-3"><span class="min-w-0 truncate text-cream" translate="no" :title="page.page">{{ page.page }}</span><span class="shrink-0 tabular-nums text-muted">{{ formatNumber(page.views) }}</span></li>
                            </ol>
                        </li>
                    </ul>
                </section>

                <!-- The last 24 hours' sign-ups: how far they've got, by the hour, and the new accounts themselves; where
                     they came from is the day's totals, never per account (Hillel's answer, docs/decisions.md). -->
                <section v-if="pulse" class="card grid overflow-hidden min-[900px]:grid-cols-2" aria-labelledby="live-signups-h" data-live-signups>
                    <div class="-mb-px -me-px min-w-0 space-y-4 border-b border-e border-line p-4 sm:p-5">
                        <h2 id="live-signups-h" class="flex flex-wrap items-baseline gap-x-2 text-xl font-bold text-cream">{{ t('admin.live.signUps') }} <span class="text-sm font-normal text-muted">{{ t('admin.live.last24h') }}</span></h2>
                        <ol class="space-y-2" data-live-funnel>
                            <li v-for="step in STEPS" :key="step" class="grid grid-cols-[minmax(0,9rem)_1fr_auto] items-center gap-3 text-sm" :data-step="step">
                                <span class="truncate text-muted">{{ t(STEP_NAMES[step]) }}</span>
                                <span class="h-2.5 overflow-hidden rounded-full bg-raised" aria-hidden="true"><span class="block h-full rounded-full bg-accent" :style="{ width: stepWidth(step) + '%' }"></span></span>
                                <span class="w-10 text-end font-semibold tabular-nums text-cream" data-step-n>{{ formatNumber(signUps.funnel[step] ?? 0) }}</span>
                            </li>
                        </ol>
                        <Bars :values="signUps.hourly" :label="t('admin.live.hourlyChart', { count: signUps.funnel.created ?? 0 })" :start="t('admin.live.hoursAgo')" :end="t('admin.live.now')" :so-far="t('admin.live.thisHour')" />
                        <div v-if="signUps.sources?.length" data-live-sources>
                            <p class="text-sm font-semibold text-cream">{{ t('admin.live.cameFrom') }}</p>
                            <ul class="mt-2 flex flex-wrap gap-2">
                                <li v-for="s in signUps.sources" :key="s.source" class="chip gap-1.5" :data-source="s.source"><span>{{ sourceName(s) }}</span><span class="font-semibold tabular-nums">{{ formatNumber(s.n) }}</span></li>
                            </ul>
                        </div>
                    </div>
                    <div class="-mb-px -me-px min-w-0 border-b border-e border-line p-4 sm:p-5">
                        <h3 class="text-sm font-semibold text-cream">{{ t('admin.live.newAccounts') }}</h3>
                        <p v-if="!signUps.people?.length" class="mt-2 text-sm text-muted" data-live-nobody>{{ t('admin.live.noSignUps') }}</p>
                        <ul v-else class="mt-2 divide-y divide-line" data-live-people>
                            <li v-for="person in signUps.people" :key="person.id">
                                <a :href="'/admin/people/' + person.id" class="flex min-h-11 flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2 text-sm hover:bg-raised" :data-person="person.id" @click.prevent="navigate('/admin/people/' + person.id)">
                                    <span class="min-w-0 truncate font-semibold text-cream">{{ person.name }}</span>
                                    <span class="flex items-center gap-3">
                                        <span class="flex items-center gap-1" role="img" :aria-label="stepsLabel(person)" data-person-steps>
                                            <span v-for="step in STEPS" :key="step" class="size-2.5 rounded-full" :class="person.steps?.includes(step) ? 'bg-accent' : 'border border-line'" :title="t(STEP_NAMES[step])"></span>
                                        </span>
                                        <span class="min-w-16 whitespace-nowrap text-end text-muted" data-person-at>{{ ago(person.at) }}</span>
                                    </span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- What people did in the last 24 hours, as counts (the Usage page's moments). -->
                <section v-if="activity.length" class="space-y-3" aria-labelledby="live-activity-h" data-live-activity>
                    <h2 id="live-activity-h" class="text-xl font-bold text-cream">{{ t('admin.live.activity') }} <span class="ms-1 text-sm font-normal text-muted">{{ t('admin.live.last24h') }}</span></h2>
                    <ul class="flex flex-wrap gap-2">
                        <li v-for="a in activity" :key="a.kind" class="chip gap-1.5" :data-kind="a.kind"><span>{{ kindName(a.kind) }}</span><span class="font-semibold tabular-nums">{{ formatNumber(a.n) }}</span></li>
                    </ul>
                </section>
            </template>
        </section>`,
    computed: {
        state() {
            return liveState({ visible: this.visible, staleSince: this.staleSince });
        },
        STATE_WORDS: () => STATE_WORDS,
        STATE_DOT: () => STATE_DOT,
        updated() {
            return updatedAgo(this.updatedAt, this.now);
        },
        areas() {
            return [...(this.live?.areas ?? [])].sort((a, b) => b.now - a.now || b.views30 - a.views30);
        },
        /** This week's signed-in singers against the week before (`change` is the difference). */
        activeChange() {
            const a = this.pulse.active;
            return change(a.signedIn7d, a.signedIn7d - (a.change ?? 0));
        },
        weekLine() {
            return linePoints(this.pulse?.active.weeks ?? []);
        },
        mrr() {
            return this.revenue?.mrr?.length ? this.revenue.mrr : [{ currency: 'usd', cents: 0 }];
        },
        signUps() {
            return this.pulse?.signUps24h ?? { funnel: {}, hourly: [], people: [], sources: [] };
        },
        activity() {
            return [...(this.pulse?.activity24h ?? [])].sort((a, b) => b.n - a.n);
        },
    },
    created() {
        this.load();
    },
    mounted() {
        // Each poll only while the page is in view; on coming back, whatever is due runs at once.
        this.ticker = setInterval(() => this.visible && (this.now = Date.now()), TICK_MS);
        this.livePoller = setInterval(() => this.visible && this.refreshLive(), (this.live?.refreshSeconds ?? LIVE_MS / 1000) * 1000);
        this.pulsePoller = setInterval(() => this.visible && this.refreshPulse(), PULSE_MS);
        this.onVisible = () => {
            this.visible = document.visibilityState === 'visible';
            if (!this.visible) return;
            this.now = Date.now();
            if (this.updatedAt && this.now - this.updatedAt >= LIVE_MS) this.refreshLive();
            if (this.pulseAt && this.now - this.pulseAt >= PULSE_MS) this.refreshPulse();
        };
        document.addEventListener('visibilitychange', this.onVisible);
    },
    unmounted() {
        clearInterval(this.ticker);
        clearInterval(this.livePoller);
        clearInterval(this.pulsePoller);
        document.removeEventListener('visibilitychange', this.onVisible);
    },
    methods: {
        areaName(key) {
            return AREAS[key] ? t(AREAS[key]) : key;
        },
        kindName(kind) {
            return KINDS[kind] ? t(KINDS[kind]) : kind;
        },
        moneyList(list = []) {
            return list.length ? list.map((m) => money(m.cents, m.currency)).join(' + ') : money(0, 'usd');
        },
        stepWidth(step) {
            const top = this.signUps.funnel.created ?? 0;
            const n = this.signUps.funnel[step] ?? 0;
            return top ? Math.max(n ? 2 : 0, Math.min(100, (n / top) * 100)) : 0;
        },
        stepsLabel(person) {
            const done = STEPS.filter((s) => person.steps?.includes(s)).map((s) => t(STEP_NAMES[s]));
            return t('admin.live.stepsDone', { steps: done.join(', ') });
        },
        ago(at) {
            return timeAgo(at, this.now);
        },
        setAdmins(on) {
            this.showAdmins = on;
            this.load();
        },
        async load() {
            this.failed = false;
            this.live = null;
            this.pulse = null;
            try {
                await Promise.all([this.refreshLive({ first: true }), this.refreshPulse()]);
            } catch (error) {
                console.warn('Live did not load', error);
                this.failed = t('admin.live.failed');
            }
        },
        /** Right now and the areas; a failed refresh says so (Reconnecting) and keeps the numbers. */
        async refreshLive({ first = false } = {}) {
            const admins = this.showAdmins;
            try {
                const answer = (await api.admin.live.show({ admins })).data;
                if (admins !== this.showAdmins) return;
                this.live = answer;
                this.updatedAt = this.now = Date.now();
                this.staleSince = null;
            } catch (error) {
                if (first) throw error;
                console.info('Live did not refresh', error?.message ?? error);
                this.staleSince ??= Date.now();
            }
        },
        /** The week, the day's sign-ups and activity, and revenue; revenue missing never hides the rest. */
        async refreshPulse() {
            const admins = this.showAdmins;
            const [pulse, revenue] = await Promise.allSettled([api.admin.pulse.show({ admins }), api.admin.revenue.show()]);
            if (admins !== this.showAdmins) return;
            if (pulse.status === 'fulfilled') {
                this.pulse = pulse.value.data;
                this.pulseAt = Date.now();
            } else console.info('Pulse did not refresh', pulse.reason?.message ?? pulse.reason);
            if (revenue.status === 'fulfilled') this.revenue = revenue.value.data;
        },
    },
};
