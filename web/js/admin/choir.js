// Admin > Numbers > Choir (contract: admin.choir.show; Hillel, 2026-11-22: "make sure this is all tracked so we can
// analyze over time to improve"): the choir's anonymous daily counts over 7 or 30 days, each beside the period before.
// The six numbers that say how it's going as one ruled panel; the two daily trends; the funnel from the intro to a
// first play; why clips failed; how each capture song does; which parts fail their gates; the tries a first song took;
// then every count the server keeps, grouped. One hue for every mark (the app's ink), the period before as a grey
// tick: no colour reads as a verdict. A number over a total under the report's floor reads "too few to tell".
// Numbers only, nothing about anyone.

import { api } from '../api.js';
import { formatDate, formatNumber, t, timeAgo, tk } from '../i18n.js';
import { Segmented } from '../components/controls.js';
import { change, LoadFailed, remembers } from './common.js';

export const PERIODS = ['7d', '30d'];
const STEPS = { intro: tk('admin.choir.step.intro'), take: tk('admin.choir.step.take'), sent: tk('admin.choir.step.sent'), requested: tk('admin.choir.step.requested'), ready: tk('admin.choir.step.ready'), seen: tk('admin.choir.step.seen'), played: tk('admin.choir.step.played') };
// The server's failure reasons (the same words as the singer's own, shorter).
const REASONS = { notes: tk('admin.choir.reason.notes'), quiet: tk('admin.choir.reason.quiet'), noisy: tk('admin.choir.reason.noisy'), loud: tk('admin.choir.reason.loud'), short: tk('admin.choir.reason.short'), edge: tk('admin.choir.reason.edge'), length: tk('admin.choir.reason.length'), other: tk('admin.choir.reason.other') };
// The capture songs, by the server's short names (songs' own titles: not translated).
// Live's songs by their ids (titles, not translated).
const SONGS_BY_ID = { 'voice-capture': 'Happy Birthday', 'happy-birthday': 'Happy Birthday', 'silent-night': 'Silent Night', 'amazing-grace': 'Amazing Grace', 'joy-to-the-world': 'Joy to the World' }; // i18n-ignore: song titles
const SONGS = { capture: 'Happy Birthday', silent: 'Silent Night', grace: 'Amazing Grace', joy: 'Joy to the World' }; // i18n-ignore: song titles
const PARTS = { soprano: tk('admin.choir.part.soprano'), alto: tk('admin.choir.part.alto'), tenor: tk('admin.choir.part.tenor'), bass: tk('admin.choir.part.bass') };
const VOICINGS = { auto: tk('admin.choir.voicing.auto'), low: tk('admin.choir.voicing.low'), full: tk('admin.choir.voicing.full'), written: tk('admin.choir.voicing.written') };
// Every count, grouped by its kind's start (the contract's list); anything else is "Other counts".
const GROUPS = [
    { prefix: 'own_choir_', label: tk('admin.choir.group.server') },
    { prefix: 'choir_take_level_', label: tk('admin.choir.group.level') },
    { prefix: 'choir_take_', label: tk('admin.choir.group.take') },
    { prefix: 'choir_guide_', label: tk('admin.choir.group.guide') },
    { prefix: 'choir_headphones_', label: tk('admin.choir.group.headphones') },
    { prefix: 'choir_song_', label: tk('admin.choir.group.song') },
    { prefix: 'choir_transpose_', label: tk('admin.choir.group.transpose') },
    { prefix: 'choir_octave_', label: tk('admin.choir.group.octave') },
    { prefix: 'choir_view_', label: tk('admin.choir.group.view') },
    { prefix: 'choir_settings_opened_', label: tk('admin.choir.group.settings') },
    { prefix: 'choir_rema', label: tk('admin.choir.group.remake') }, // choir_remake_* and choir_remade
    { prefix: 'choir_plus_sheet_', label: tk('admin.choir.group.plus') },
    { prefix: 'choir_part_tries_', label: tk('admin.choir.group.tries') },
];
const OTHER = tk('admin.choir.group.other');
// The two trends: takes sent and choirs made, day by day.
const TRENDS = [
    { kind: 'choir_take_sent', label: tk('admin.choir.trend.sent') },
    { kind: 'own_choir_ready', label: tk('admin.choir.trend.made') },
];

const CLIPS_SHOWN = 10;
const day = (d, options = { day: 'numeric', month: 'short' }) => formatDate(`${d}T12:00:00Z`, { ...options, timeZone: 'UTC' });
const percent = (v) => formatNumber(v, { style: 'percent', maximumFractionDigits: 0 });

/** Every day from `from` to `to` (the report's dates), oldest first. */
export function daysOf(from, to) {
    const days = [];
    for (let d = new Date(`${from}T12:00:00Z`); d <= new Date(`${to}T12:00:00Z`); d.setUTCDate(d.getUTCDate() + 1)) days.push(d.toISOString().slice(0, 10));
    return days;
}

/** A kind's count on each day of the report (0 where the server has none). */
export function dailyCounts(report, kind) {
    const byDay = Object.fromEntries((report.daily ?? []).map((row) => [row.date, row.kinds.find((k) => k.kind === kind)?.n ?? 0]));
    return daysOf(report.from, report.to).map((date) => ({ date, n: byDay[date] ?? 0 }));
}

/** A share (part over whole), or null when the whole is under the floor (too few to tell). */
export function shareOf(part, whole, floor) {
    return whole >= floor && whole > 0 ? part / whole : null;
}

/** The headline: six numbers, each with its move on the period before when both can be read. */
export function headline(report) {
    const step = (key) => report.funnel?.find((s) => s.step === key) ?? { n: 0, before: 0 };
    const sent = step('sent');
    const ready = step('ready');
    const tries = report.firstSuccess ?? [];
    const firsts = tries.reduce((n, row) => n + row.n, 0);
    const first = tries.find((row) => row.tries === '1')?.n ?? 0;
    const share = (now, before) => {
        if (now === null) return { value: null };
        return { value: percent(now), move: before === null ? null : pointsMove(now, before) };
    };
    const count = (now, before, text = formatNumber(now)) => ({ value: text, move: change(now, before) });
    const hours = (s) => formatNumber((s ?? 0) / 3600, { maximumFractionDigits: 1 });
    return [
        { key: 'made', label: tk('admin.choir.tile.made'), ...count(ready.n, ready.before) },
        { key: 'sent', label: tk('admin.choir.tile.sent'), ...count(sent.n, sent.before) },
        { key: 'madeShare', label: tk('admin.choir.tile.madeShare'), ...share(shareOf(ready.n, sent.n, report.floor), shareOf(ready.before, sent.before, report.floor)) },
        { key: 'firstTry', label: tk('admin.choir.tile.firstTry'), ...share(shareOf(first, firsts, report.floor), null) },
        { key: 'gpu', label: tk('admin.choir.tile.gpu'), ...count(report.renderSeconds?.n ?? 0, report.renderSeconds?.before ?? 0, hours(report.renderSeconds?.n)) },
        { key: 'plus', label: tk('admin.choir.tile.plus'), ...count(report.plusFromChoir?.n ?? 0, report.plusFromChoir?.before ?? 0) },
    ];
}

/** A share's move in points ("4 pts"), or the same. */
function pointsMove(now, before) {
    const d = Math.round((now - before) * 100);
    if (d === 0) return { dir: 'same', text: t('admin.growth.same') };
    return { dir: d > 0 ? 'up' : 'down', text: t('admin.choir.points', { n: formatNumber(Math.abs(d)) }) };
}

/**
 * Rows of horizontal bars (magnitude, one hue), each with its number at the end and, when there is one, the period
 * before as a grey tick on the same scale. The numbers are the rows' own, so the rows are their table too.
 */
export const BarRows = {
    props: { rows: { type: Array, required: true }, label: { type: String, required: true }, before: Boolean },
    setup: () => ({ formatNumber }),
    computed: {
        max() {
            return Math.max(1, ...this.rows.map((r) => Math.max(r.n, this.before ? r.before ?? 0 : 0)));
        },
    },
    template: `
        <ul class="space-y-2.5" :aria-label="label">
            <li v-for="row in rows" :key="row.key" class="grid grid-cols-[minmax(0,10rem)_minmax(2rem,1fr)_auto] items-center gap-x-3 text-sm" :data-bar-row="row.key" :title="row.tip || null">
                <span class="min-w-0 truncate" :class="row.n ? 'text-cream' : 'text-muted'" :title="row.label">{{ row.label }}</span>
                <span class="relative h-3" aria-hidden="true">
                    <span v-if="row.n" class="absolute inset-y-0 start-0 rounded-e-[4px] bg-cream/85" :style="{ width: Math.max(0.5, (row.n / max) * 100) + '%' }"></span>
                    <span v-if="before && row.before" class="absolute -inset-y-1 w-0.5 rounded-full bg-muted" :style="{ insetInlineStart: 'calc(' + (row.before / max) * 100 + '% - 1px)' }"></span>
                </span>
                <span class="min-w-[3rem] text-end tabular-nums" :class="row.n ? 'text-cream' : 'text-muted'">{{ formatNumber(row.n) }}<span v-if="row.note" class="ms-1.5 text-xs text-muted">{{ row.note }}</span><span v-if="before" class="sr-only"> {{ t('admin.choir.beforeWas', { n: formatNumber(row.before ?? 0) }) }}</span></span>
            </li>
        </ul>`,
};

/** A kind's daily columns: one hue, 4 px rounded tops on the baseline, a hover tip, and a table of the same numbers. */
export const DayColumns = {
    props: { days: { type: Array, required: true }, label: { type: String, required: true }, kind: { type: String, required: true } },
    data: () => ({ width: 320, hover: null, table: false }),
    setup: () => ({ formatNumber, day, H: 120, M: { top: 10, bottom: 20, left: 28, right: 4 } }),
    computed: {
        max() {
            return Math.max(1, ...this.days.map((d) => d.n));
        },
        top() {
            // A round top for the scale: 1, 2 or 5 times a power of ten.
            const pow = 10 ** Math.floor(Math.log10(this.max));
            return [1, 2, 5, 10].map((m) => m * pow).find((v) => v >= this.max);
        },
        step() {
            return (this.width - this.M.left - this.M.right) / Math.max(1, this.days.length);
        },
        total() {
            return this.days.reduce((n, d) => n + d.n, 0);
        },
        summary() {
            return t('admin.choir.trendSummary', { label: this.label, total: formatNumber(this.total), from: day(this.days[0]?.date ?? ''), to: day(this.days.at(-1)?.date ?? '') });
        },
    },
    mounted() {
        this.observer = new ResizeObserver(([entry]) => (this.width = Math.max(200, Math.floor(entry.contentRect.width))));
        this.observer.observe(this.$refs.box);
    },
    unmounted() {
        this.observer?.disconnect();
    },
    methods: {
        y(v) {
            return this.M.top + (1 - v / this.top) * (this.H - this.M.top - this.M.bottom);
        },
        x(i) {
            return this.M.left + i * this.step + Math.max(1, this.step * 0.15);
        },
        barWidth() {
            return Math.max(2, this.step * 0.7);
        },
        /** A column: its top rounded 4 px (less on a thin one), square on the baseline. */
        bar(i, n) {
            const w = this.barWidth();
            const x = this.x(i);
            const base = this.y(0);
            const top = this.y(n);
            const r = Math.min(4, w / 2, base - top);
            return `M${x},${base}V${top + r}Q${x},${top} ${x + r},${top}H${x + w - r}Q${x + w},${top} ${x + w},${top + r}V${base}Z`;
        },
    },
    template: `
        <figure class="min-w-0 space-y-2" :data-choir-trend="kind">
            <figcaption class="flex flex-wrap items-baseline justify-between gap-2">
                <span class="font-semibold text-cream">{{ label }} <span class="ms-1 font-normal text-muted tabular-nums">{{ formatNumber(total) }}</span></span>
                <button type="button" class="link tap-44 text-xs" :aria-pressed="table ? 'true' : 'false'" @click="table = !table">{{ table ? t('admin.choir.asChart') : t('admin.choir.asTable') }}</button>
            </figcaption>
            <div v-show="!table" ref="box" class="relative" @pointerleave="hover = null">
                <svg :width="width" :height="H" class="block max-w-full select-none overflow-visible" role="img" :aria-label="summary">
                    <g class="text-[11px]" fill="var(--color-muted)">
                        <g v-for="v in [0, top / 2, top].filter(Number.isInteger)" :key="v">
                            <line :x1="M.left" :x2="width - M.right" :y1="y(v)" :y2="y(v)" stroke="var(--color-line)" :opacity="v === 0 ? 1 : 0.5" />
                            <text :x="M.left - 6" :y="y(v) + 3.5" text-anchor="end" class="tabular-nums">{{ formatNumber(v) }}</text>
                        </g>
                        <text :x="M.left" :y="H - 4">{{ day(days[0]?.date ?? '') }}</text>
                        <text :x="width - M.right" :y="H - 4" text-anchor="end">{{ day(days.at(-1)?.date ?? '') }}</text>
                    </g>
                    <g v-for="(d, i) in days" :key="d.date" :data-day="d.date" @pointerenter="hover = i" @pointerdown="hover = hover === i ? null : i">
                        <rect :x="M.left + i * step" :y="M.top" :width="step" :height="H - M.top - M.bottom" fill="transparent" />
                        <path v-if="d.n" :d="bar(i, d.n)" fill="var(--color-cream)" :opacity="hover === null || hover === i ? 0.85 : 0.45" />
                    </g>
                </svg>
                <div v-if="hover !== null" class="pointer-events-none absolute top-0 z-10 -translate-x-1/2 whitespace-nowrap rounded-lg border border-line bg-panel px-2.5 py-1.5 text-xs shadow-lg" :style="{ left: Math.min(width - 50, Math.max(50, x(hover) + barWidth() / 2)) + 'px' }" role="status">
                    <span class="text-muted">{{ day(days[hover].date, { weekday: 'short', day: 'numeric', month: 'short' }) }}</span>
                    <span class="ms-2 font-semibold tabular-nums text-cream">{{ formatNumber(days[hover].n) }}</span>
                </div>
            </div>
            <div v-if="table" class="card max-h-72 overflow-auto">
                <table class="data-table">
                    <thead><tr><th>{{ t('admin.choir.col.day') }}</th><th class="text-end">{{ label }}</th></tr></thead>
                    <tbody><tr v-for="d in days" :key="d.date"><td>{{ day(d.date, { weekday: 'short', day: 'numeric', month: 'short' }) }}</td><td class="text-end tabular-nums">{{ formatNumber(d.n) }}</td></tr></tbody>
                </table>
            </div>
        </figure>`,
};

// The Live panel (contract: admin.choir.live; the coordinator, 2026-11-23): the queue now, today's spend against the
// daily meter, the month's GPU cost, the last day's failures, the latest requests and the worker's last call for work.
// Read fresh every 30 seconds while it's open and the tab is in view (a data refresh, never audio). Nothing per person.
const FAILURE_WORDS = { clip_unusable: tk('admin.choir.live.failure.clip_unusable'), bad_clip: tk('admin.choir.live.failure.bad_clip'), gates_failed: tk('admin.choir.live.failure.gates_failed'), render_failed: tk('admin.choir.live.failure.render_failed'), expired: tk('admin.choir.live.failure.expired'), reference_gone: tk('admin.choir.live.failure.reference_gone') };
const KIND_WORDS = { own: tk('admin.choir.live.kind.own'), friend: tk('admin.choir.live.kind.friend'), remake: tk('admin.choir.live.kind.remake') };
const STATUS_WORDS = { queued: tk('admin.choir.live.status.queued'), rendering: tk('admin.choir.live.status.rendering'), ready: tk('admin.choir.live.status.ready'), failed: tk('admin.choir.live.status.failed') };
const QUIET_MINUTES = 10;
const REFRESH_MS = 30000;

/** Seconds as people say a wait: "45 s", "4 min", "1 h 20 min". */
export function waitWords(seconds) {
    if (seconds == null) return '';
    if (seconds < 60) return t('admin.choir.live.seconds', { n: formatNumber(Math.round(seconds)) });
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) return t('admin.choir.live.minutes', { n: formatNumber(minutes) });
    return t('admin.choir.live.hours', { h: formatNumber(Math.floor(minutes / 60)), m: formatNumber(minutes % 60) });
}

/** The worker's state: quiet when it hasn't asked for work in 10 minutes while something waits. */
export function workerState(live, now = Date.now()) {
    const polled = live?.worker?.lastPollAt ? Date.parse(live.worker.lastPollAt) : null;
    const busy = (live?.queue?.queued ?? 0) + (live?.queue?.rendering ?? 0) > 0;
    const since = polled == null ? null : Math.max(0, (now - polled) / 1000);
    return { since, quiet: busy && (since == null || since > QUIET_MINUTES * 60) };
}

export const ChoirLive = {
    data: () => ({ live: null, failed: false, now: Date.now() }),
    setup: () => ({ formatNumber, waitWords, KIND_WORDS, STATUS_WORDS, REASONS_OF: REASONS, FAILURE_OF: FAILURE_WORDS }),
    computed: {
        worker() {
            return workerState(this.live, this.now);
        },
        meterNow() {
            return Math.round(this.meter);
        },
        meter() {
            const today = this.live?.today;
            return today?.dailyPartMinutes ? Math.min(100, (today.partMinutes / today.dailyPartMinutes) * 100) : 0;
        },
        failures() {
            return (this.live?.failures ?? []).map((f) => ({ key: `${f.failure}:${f.failureDetail ?? ''}`, label: f.failureDetail && REASONS[f.failureDetail] ? t(REASONS[f.failureDetail]) : t(FAILURE_WORDS[f.failure] ?? REASONS.other), n: f.n }));
        },
        money() {
            const month = this.live?.month;
            if (!month) return '';
            try {
                return formatNumber(month.cost, { style: 'currency', currency: (month.currency || 'usd').toUpperCase() });
            } catch {
                return formatNumber(month.cost);
            }
        },
    },
    created() {
        this.load();
        this.timer = setInterval(() => {
            if (!document.hidden) this.load();
        }, REFRESH_MS);
    },
    unmounted() {
        clearInterval(this.timer);
    },
    methods: {
        async load() {
            try {
                this.live = (await api.admin.choir.live()).data;
                this.failed = false;
                this.now = Date.now();
            } catch (error) {
                console.warn('The choir live numbers did not load', error);
                this.failed = true;
            }
        },
        hours: (seconds) => formatNumber((seconds ?? 0) / 3600, { maximumFractionDigits: 1 }),
        title: (id) => SONGS_BY_ID[id] ?? id,
        statusDot: (status) => (status === 'ready' ? 'bg-good' : status === 'failed' ? 'bg-bad' : status === 'rendering' ? 'bg-accent' : 'bg-line'),
        ago(time) {
            return time ? timeAgo(time) : '';
        },
    },
    template: `
        <section class="card min-w-0 space-y-5 p-4 sm:p-5" aria-labelledby="choir-live-h" data-choir-live>
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="choir-live-h" class="text-lg font-bold text-cream">{{ t('admin.choir.live.title') }}</h2>
                <p v-if="live" class="text-xs text-muted" role="status" data-live-as-of>{{ t('admin.choir.live.asOf', { when: ago(live.asOf) }) }}</p>
            </div>
            <p v-if="failed && !live" class="text-sm text-bad" role="alert">{{ t('admin.choir.live.failed') }} <button type="button" class="link tap-44" @click="load">{{ t('common.tryAgain') }}</button></p>
            <p v-else-if="!live" class="text-sm text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <!-- The worker: polling, or quiet while something waits (the warn colour, with its words). -->
                <p class="flex items-center gap-2 text-sm" :class="worker.quiet ? 'text-warn' : 'text-muted'" data-live-worker :data-quiet="worker.quiet ? 'yes' : 'no'">
                    <span class="size-2.5 shrink-0 rounded-full" :class="worker.quiet ? 'bg-warn' : 'bg-good'" aria-hidden="true"></span>
                    {{ worker.since == null ? t('admin.choir.live.workerNever') : worker.quiet ? t('admin.choir.live.workerQuiet', { wait: waitWords(worker.since) }) : t('admin.choir.live.workerPolled', { wait: waitWords(worker.since) }) }}
                </p>
                <dl class="grid grid-cols-2 gap-3 min-[900px]:grid-cols-4" data-live-tiles>
                    <div class="rounded-2xl border border-line p-3" data-live-tile="queue">
                        <dt class="text-sm text-muted">{{ t('admin.choir.live.queued') }}</dt>
                        <dd class="mt-1 text-2xl font-bold tabular-nums text-cream">{{ formatNumber(live.queue.queued) }}</dd>
                        <dd class="text-xs text-muted">{{ live.queue.queued ? t('admin.choir.live.oldest', { wait: waitWords(live.queue.oldestWaitSeconds) }) : t('admin.choir.live.nothingWaiting') }}</dd>
                        <dd v-if="live.queue.remakes || live.queue.friends || live.queue.waitingForTomorrow" class="text-xs text-muted">{{ t('admin.choir.live.among', { remakes: live.queue.remakes, friends: live.queue.friends, tomorrow: live.queue.waitingForTomorrow }) }}</dd>
                    </div>
                    <div class="rounded-2xl border border-line p-3" data-live-tile="rendering">
                        <dt class="text-sm text-muted">{{ t('admin.choir.live.rendering') }}</dt>
                        <dd class="mt-1 text-2xl font-bold tabular-nums text-cream">{{ formatNumber(live.queue.rendering) }}</dd>
                        <dd class="text-xs text-muted">{{ t('admin.choir.live.madeFailed', { made: live.today.made, failed: live.today.failed }) }}</dd>
                    </div>
                    <div class="rounded-2xl border border-line p-3" data-live-tile="today">
                        <dt class="text-sm text-muted">{{ t('admin.choir.live.today') }}</dt>
                        <dd class="mt-1 text-2xl font-bold tabular-nums text-cream">{{ formatNumber(live.today.partMinutes, { maximumFractionDigits: 1 }) }}</dd>
                        <dd class="text-xs text-muted">{{ t('admin.choir.live.ofDaily', { daily: formatNumber(live.today.dailyPartMinutes) }) }}</dd>
                        <dd class="mt-2 h-1.5 overflow-hidden rounded-full bg-raised" role="meter" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="meterNow" :aria-label="t('admin.choir.live.meter')"><span class="block h-full rounded-full" :class="meter >= 80 ? 'bg-warn' : 'bg-cream/85'" :style="{ width: Math.max(2, meter) + '%' }"></span></dd>
                    </div>
                    <div class="rounded-2xl border border-line p-3" data-live-tile="month">
                        <dt class="text-sm text-muted">{{ t('admin.choir.live.month') }}</dt>
                        <dd class="mt-1 text-2xl font-bold tabular-nums text-cream">{{ money }}</dd>
                        <dd class="text-xs text-muted">{{ t('admin.choir.live.gpu', { today: hours(live.today.gpuSeconds), month: hours(live.month.gpuSeconds) }) }}</dd>
                    </div>
                </dl>
                <div v-if="failures.length" class="space-y-2" data-live-failures>
                    <h3 class="text-sm font-semibold text-cream">{{ t('admin.choir.live.failures') }}</h3>
                    <ul class="divide-y divide-line text-sm">
                        <li v-for="f in failures" :key="f.key" class="flex items-baseline justify-between gap-3 py-1.5"><span class="min-w-0 text-cream">{{ f.label }}</span><span class="tabular-nums text-muted">{{ formatNumber(f.n) }}</span></li>
                    </ul>
                </div>
                <div class="space-y-2" data-live-recent>
                    <h3 class="text-sm font-semibold text-cream">{{ t('admin.choir.live.recent') }}</h3>
                    <p v-if="!live.recent.length" class="text-sm text-muted">{{ t('admin.choir.live.noneRecent') }}</p>
                    <!-- A row a request, its song and facts (never its singer); no table to scroll sideways. -->
                    <ul v-else class="divide-y divide-line text-sm">
                        <li v-for="(r, i) in live.recent" :key="i" class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 py-2" :data-live-request="r.status">
                            <span class="inline-flex items-center gap-1.5"><span class="size-2 shrink-0 rounded-full" :class="statusDot(r.status)" aria-hidden="true"></span><span class="text-cream" translate="no">{{ title(r.songId) }}</span></span>
                            <span class="text-muted">{{ t(STATUS_WORDS[r.status] ?? STATUS_WORDS.queued) }} · {{ t(KIND_WORDS[r.kind] ?? KIND_WORDS.own) }}<template v-if="r.paid"> · {{ t('admin.choir.live.paid') }}</template></span>
                            <span class="ms-auto tabular-nums text-xs text-muted">{{ t('admin.choir.live.facts', { minutes: formatNumber(r.minutes ?? 0, { maximumFractionDigits: 1 }), gpu: formatNumber(Math.round(r.gpuSeconds ?? 0)) }) }}<template v-if="r.seconds != null"> · {{ t('admin.choir.live.took', { wait: waitWords(r.seconds) }) }}</template> · {{ ago(r.createdAt) }}</span>
                            <span v-if="r.status === 'failed'" class="basis-full text-xs text-warn">{{ r.failureDetail && REASONS_OF[r.failureDetail] ? t(REASONS_OF[r.failureDetail]) : t(FAILURE_OF[r.failure] ?? REASONS_OF.other) }}</span>
                        </li>
                    </ul>
                </div>
            </template>
        </section>`,
};

export const ChoirSection = {
    components: { BarRows, ChoirLive, DayColumns, LoadFailed, Segmented },
    mixins: [remembers('choir', { period: PERIODS })],
    props: { me: { type: Object, required: true } },
    data: () => ({ report: null, failed: false, period: '30d', ticket: 0, clipsAll: false }),
    setup: () => ({ formatNumber, TRENDS }),
    computed: {
        periods: () => [
            { value: '7d', label: t('admin.choir.period.7d') },
            { value: '30d', label: t('admin.choir.period.30d') },
        ],
        tiles() {
            return this.report ? headline(this.report) : [];
        },
        trends() {
            return this.report ? TRENDS.map((trend) => ({ ...trend, days: dailyCounts(this.report, trend.kind) })) : [];
        },
        funnel() {
            const steps = this.report?.funnel ?? [];
            const first = steps[0]?.n ?? 0;
            return steps.map((s, i) => {
                const prev = i ? steps[i - 1].n : null;
                const pass = i ? shareOf(s.n, prev, this.report.floor) : null;
                const ofIntro = shareOf(s.n, first, this.report.floor);
                return { key: s.step, label: t(STEPS[s.step] ?? STEPS.intro), n: s.n, before: s.before, note: i && ofIntro !== null ? percent(ofIntro) : '', tip: pass === null ? '' : t('admin.choir.passTip', { share: percent(pass) }) };
            });
        },
        reasons() {
            return [...(this.report?.reasons ?? [])].sort((a, b) => b.n - a.n || b.before - a.before).map((r) => ({ key: r.reason, label: t(REASONS[r.reason] ?? REASONS.other), n: r.n, before: r.before }));
        },
        failedTotal() {
            return this.reasons.reduce((n, r) => n + r.n, 0);
        },
        songs() {
            return (this.report?.songs ?? []).map((s) => {
                const tried = s.ok + s.failed;
                const ok = shareOf(s.ok, tried, this.report.floor);
                return { key: s.song, name: SONGS[s.song] ?? s.song, ok: s.ok, failed: s.failed, tried, share: ok === null ? t('admin.choir.tooFew') : percent(ok) };
            });
        },
        /**
         * The latest clips that couldn't be used, with the worker's measures (clipNumbers, newest first; never whose): ten,
         * then all on asking. Each row its day, its reason in words, and each measure as name and value.
         */
        clips() {
            const rows = (this.report?.clipNumbers ?? []).map((c, i) => ({ key: i, date: day(c.date), reason: t(REASONS[c.detail] ?? REASONS.other), numbers: (c.numbers ?? []).map((m) => ({ name: m.name, value: formatNumber(m.value, { maximumFractionDigits: 2 }) })) }));
            return this.clipsAll ? rows : rows.slice(0, CLIPS_SHOWN);
        },
        clipsMore() {
            return (this.report?.clipNumbers ?? []).length - CLIPS_SHOWN;
        },
        gates() {
            return (this.report?.gates ?? []).map((g) => ({ key: `${g.part}-${g.voicing}`, label: t('admin.choir.gate', { part: PARTS[g.part] ? t(PARTS[g.part]) : g.part, voicing: VOICINGS[g.voicing] ? t(VOICINGS[g.voicing]) : g.voicing }), n: g.n }));
        },
        tries() {
            return (this.report?.firstSuccess ?? []).map((row) => ({ key: row.tries, label: row.tries === '4+' ? t('admin.choir.triesMore') : t('admin.choir.tries', { count: Number(row.tries) }), n: row.n }));
        },
        /** Every count, in its group, biggest first; groups in the contract's order. */
        groups() {
            const kinds = [...(this.report?.totals ?? [])].sort((a, b) => b.n - a.n || a.kind.localeCompare(b.kind));
            const out = GROUPS.map((g) => ({ ...g, rows: [] }));
            const other = { prefix: '', label: OTHER, rows: [] };
            for (const row of kinds) (out.find((g) => row.kind.startsWith(g.prefix)) ?? other).rows.push(row);
            return [...out, other].filter((g) => g.rows.length);
        },
    },
    watch: {
        period() {
            this.load();
        },
    },
    created() {
        this.load();
    },
    methods: {
        async load() {
            const ticket = ++this.ticket;
            this.failed = false;
            try {
                const { data } = await api.admin.choir.show({ period: this.period });
                if (ticket === this.ticket) this.report = data;
            } catch (error) {
                if (ticket !== this.ticket) return;
                console.warn('Choir numbers did not load', error);
                this.failed = true;
            }
        },
        move: (row) => change(row.n, row.before ?? 0),
        moveClass: (dir) => (dir === 'up' ? 'text-up' : dir === 'down' ? 'text-down' : 'text-muted'),
        day,
    },
    template: `
        <section class="space-y-8" aria-labelledby="choir-h" data-admin-choir>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 id="choir-h" class="text-2xl font-bold text-cream">{{ t('admin.nav.choir') }}</h1>
                    <p class="mt-1 max-w-prose text-sm text-muted" data-choir-range>{{ report ? t('admin.choir.intro', { from: day(report.from), to: day(report.to), floor: formatNumber(report.floor) }) : t('admin.choir.introShort') }}</p>
                </div>
                <Segmented :model-value="period" :options="periods" :label="t('admin.choir.period.label')" data-choir-period @update:model-value="period = $event" />
            </div>
            <!-- Live first: what's happening now, whatever the period. -->
            <ChoirLive />
            <LoadFailed v-if="failed" :message="t('admin.choir.failed')" @retry="load" />
            <p v-else-if="!report" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <!-- The six numbers: a label over a big value, its move on the period before small beside it. -->
                <dl class="card grid grid-cols-2 overflow-hidden min-[900px]:grid-cols-3 xl:grid-cols-6" data-choir-panel>
                    <div v-for="tile in tiles" :key="tile.key" class="-mb-px -me-px min-w-0 border-b border-e border-line p-4 sm:p-5" :data-tile="tile.key">
                        <dt class="text-sm text-muted">{{ t(tile.label) }}</dt>
                        <dd class="mt-2 flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
                            <template v-if="tile.value !== null">
                                <span class="text-3xl font-bold tabular-nums tracking-tight text-cream" data-value>{{ tile.value }}</span>
                                <span v-if="tile.move" class="text-sm font-semibold tabular-nums" :class="moveClass(tile.move.dir)" data-move :data-dir="tile.move.dir"><span v-if="tile.move.dir !== 'same'" class="me-0.5" aria-hidden="true">{{ tile.move.dir === 'up' ? '↑' : '↓' }}</span>{{ tile.move.text }}<span class="sr-only"> {{ t('admin.choir.onBefore') }}</span></span>
                            </template>
                            <span v-else class="text-sm text-muted" data-too-few>{{ t('admin.choir.tooFew') }}</span>
                        </dd>
                    </div>
                </dl>

                <div class="grid gap-6 md:grid-cols-2" data-choir-trends>
                    <div v-for="trend in trends" :key="trend.kind" class="card min-w-0 p-4 sm:p-5"><DayColumns :days="trend.days" :label="t(trend.label)" :kind="trend.kind" /></div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <section class="card min-w-0 space-y-4 p-4 sm:p-5" aria-labelledby="choir-funnel-h" data-choir-funnel>
                        <div>
                            <h2 id="choir-funnel-h" class="text-lg font-bold text-cream">{{ t('admin.choir.funnel') }}</h2>
                            <p class="mt-0.5 text-sm text-muted">{{ t('admin.choir.funnelHint') }}</p>
                        </div>
                        <BarRows :rows="funnel" :label="t('admin.choir.funnel')" before />
                        <p class="flex items-center gap-2 text-xs text-muted"><span class="inline-block h-3 w-0.5 rounded-full bg-muted" aria-hidden="true"></span>{{ t('admin.choir.beforeLegend') }}</p>
                    </section>
                    <section class="card min-w-0 space-y-4 p-4 sm:p-5" aria-labelledby="choir-reasons-h" data-choir-reasons>
                        <div>
                            <h2 id="choir-reasons-h" class="text-lg font-bold text-cream">{{ t('admin.choir.reasons') }}</h2>
                            <p class="mt-0.5 text-sm text-muted">{{ t('admin.choir.reasonsHint', { count: failedTotal }) }}</p>
                        </div>
                        <BarRows :rows="reasons" :label="t('admin.choir.reasons')" before />
                        <p class="flex items-center gap-2 text-xs text-muted"><span class="inline-block h-3 w-0.5 rounded-full bg-muted" aria-hidden="true"></span>{{ t('admin.choir.beforeLegend') }}</p>
                    </section>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- A row a song (no table to scroll sideways): its name, made and failed, and the share that made a voice. -->
                    <section class="card min-w-0 space-y-3 p-4 sm:p-5 lg:col-span-2" aria-labelledby="choir-songs-h" data-choir-songs>
                        <h2 id="choir-songs-h" class="text-lg font-bold text-cream">{{ t('admin.choir.songs') }}</h2>
                        <ul class="divide-y divide-line">
                            <li v-for="s in songs" :key="s.key" class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2.5 text-sm" :data-choir-song="s.key">
                                <span class="text-cream" translate="no">{{ s.name }}</span>
                                <span class="text-muted tabular-nums">{{ t('admin.choir.songLine', { tried: formatNumber(s.tried), ok: formatNumber(s.ok), failed: formatNumber(s.failed) }) }}</span>
                                <span class="ms-auto min-w-[6rem] text-end font-semibold tabular-nums text-cream" data-share>{{ s.share }}</span>
                            </li>
                        </ul>
                    </section>
                    <section class="card min-w-0 space-y-3 p-4 sm:p-5" aria-labelledby="choir-tries-h" data-choir-tries>
                        <div>
                            <h2 id="choir-tries-h" class="text-lg font-bold text-cream">{{ t('admin.choir.firstSuccess') }}</h2>
                            <p class="mt-0.5 text-sm text-muted">{{ t('admin.choir.firstSuccessHint') }}</p>
                        </div>
                        <BarRows :rows="tries" :label="t('admin.choir.firstSuccess')" />
                    </section>
                </div>

                <section class="card min-w-0 space-y-3 p-4 sm:p-5" aria-labelledby="choir-gates-h" data-choir-gates>
                    <div>
                        <h2 id="choir-gates-h" class="text-lg font-bold text-cream">{{ t('admin.choir.gates') }}</h2>
                        <p class="mt-0.5 text-sm text-muted">{{ t('admin.choir.gatesHint') }}</p>
                    </div>
                    <p v-if="!gates.length" class="text-sm text-muted">{{ t('admin.choir.noGates') }}</p>
                    <BarRows v-else :rows="gates" :label="t('admin.choir.gates')" />
                </section>

                <!-- The latest unusable clips' measures (the worker's detailNumbers), to tune the checks: numbers only. -->
                <section v-if="report.clipNumbers?.length" class="card min-w-0 space-y-3 p-4 sm:p-5" aria-labelledby="choir-clips-h" data-choir-clips>
                    <div>
                        <h2 id="choir-clips-h" class="text-lg font-bold text-cream">{{ t('admin.choir.clips') }}</h2>
                        <p class="mt-0.5 text-sm text-muted">{{ t('admin.choir.clipsHint') }}</p>
                    </div>
                    <ul class="divide-y divide-line">
                        <li v-for="c in clips" :key="c.key" class="flex flex-wrap items-baseline gap-x-4 gap-y-1 py-2 text-sm" data-choir-clip>
                            <span class="w-16 shrink-0 tabular-nums text-muted">{{ c.date }}</span>
                            <span class="w-32 shrink-0 text-cream">{{ c.reason }}</span>
                            <span class="flex min-w-0 flex-wrap gap-x-4 gap-y-1 tabular-nums"><span v-for="m in c.numbers" :key="m.name"><span class="font-mono text-xs text-muted" translate="no">{{ m.name }}</span> <span class="text-cream">{{ m.value }}</span></span></span>
                        </li>
                    </ul>
                    <button v-if="clipsMore > 0" type="button" class="link tap-44 text-sm" :aria-expanded="clipsAll ? 'true' : 'false'" data-choir-clips-more @click="clipsAll = !clipsAll">{{ clipsAll ? t('admin.choir.clipsFewer') : t('admin.choir.clipsAll', { count: clipsMore }) }}</button>
                </section>

                <section class="space-y-3" aria-labelledby="choir-all-h" data-choir-all>
                    <h2 id="choir-all-h" class="text-lg font-bold text-cream">{{ t('admin.choir.all') }}</h2>
                    <p v-if="!groups.length" class="text-sm text-muted">{{ t('admin.choir.noCounts') }}</p>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="g in groups" :key="g.label" class="card p-4" :data-choir-group="g.prefix || 'other'">
                            <h3 class="mb-2 text-sm font-semibold text-cream">{{ t(g.label) }}</h3>
                            <table class="w-full text-sm">
                                <thead class="sr-only"><tr><th>{{ t('admin.choir.col.kind') }}</th><th>{{ t('admin.choir.col.count') }}</th><th>{{ t('admin.choir.col.move') }}</th></tr></thead>
                                <tbody>
                                    <tr v-for="row in g.rows" :key="row.kind" class="border-t border-line first:border-0" :data-kind="row.kind">
                                        <td class="py-1.5 pe-2 font-mono text-xs text-muted" translate="no">{{ row.kind }}</td>
                                        <td class="py-1.5 text-end tabular-nums text-cream">{{ formatNumber(row.n) }}</td>
                                        <td class="w-16 py-1.5 text-end text-xs tabular-nums" :class="moveClass(move(row).dir)">{{ move(row).text }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </template>
        </section>`,
};
