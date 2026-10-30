// Usage (decision 2026-10-28, "anonymous usage counts"; contract: admin.counts.show): how much the
// app is used, guests included, from the anonymous daily totals and nothing else (no one's path, no
// unique visitors). Today so far (UTC, near live: Hillel's "on admin usage support seeing closer to
// realtime data", refreshed every 30 seconds while the page is in view), or over 7, 30 or 90 days:
// each moment's total split by guest and account,
// laid out by area (Hillel: "update the admin usage page with all of the new stuff, ie courses,
// etc"), the courses by course and lesson, one moment day by day and week by week, and the top
// countries. Plain bars, no charts library.

import { api } from '../api.js';
import { formatDate, formatNumber, locale, localized, t, timeAgo, tk } from '../i18n.js';
import { EmptyState } from '../components/form.js';
import { LoadFailed, loadInto } from './common.js';

// 1 is Today: today so far, UTC.
const WINDOWS = [1, 7, 30, 90];
const REFRESH_MS = 30000; // while the page is in view (a timer for the page, never for audio)
const TICK_MS = 1000; // how long ago it was updated, counting up
// The moments counted (js/usage.js; sign-ups the server counts itself), by area, each in the order a person meets them.
const AREAS = [
    { key: 'everyday', title: tk('admin.usage.area.everyday'), kinds: ['app_open', 'sign_up', 'lesson_start', 'lesson_done', 'unit_done', 'song_created', 'take_recorded', 'harmonise_used', 'export_done', 'share_created', 'result_shared'] },
    { key: 'skins', title: tk('admin.usage.area.skins'), kinds: ['open_game', 'open_serious', 'game_on', 'game_off', 'daily_viewed', 'daily_sung'] },
    { key: 'plus', title: tk('admin.usage.area.plus'), kinds: ['sharpen_up', 'then_now', 'keep_song', 'fact_source'], courses: true },
    { key: 'free', title: tk('admin.usage.area.free'), kinds: [], lessons: true },
    { key: 'hum', title: tk('admin.usage.area.hum'), kinds: ['hum_start', 'hum_found', 'hum_saved', 'hum_click', 'hum_tap'] },
    { key: 'studio', title: tk('admin.usage.area.studio'), kinds: ['take_use', 'take_undo', 'track_add', 'track_rename', 'exp_song', 'exp_practice', 'exp_stems'] },
    { key: 'onboarding', title: tk('admin.usage.area.onboarding'), kinds: ['share_note', 'card_named', 'su_progress', 'su_reminders', 'su_share', 'su_boards', 'card_day2'] },
    { key: 'melody', title: tk('admin.usage.area.melody'), kinds: ['sug_melody', 'sug_part', 'sug_switch'] },
];
const KINDS = {
    app_open: tk('admin.usage.kind.app_open'),
    sign_up: tk('admin.usage.kind.sign_up'),
    lesson_start: tk('admin.usage.kind.lesson_start'),
    lesson_done: tk('admin.usage.kind.lesson_done'),
    unit_done: tk('admin.usage.kind.unit_done'),
    song_created: tk('admin.usage.kind.song_created'),
    take_recorded: tk('admin.usage.kind.take_recorded'),
    harmonise_used: tk('admin.usage.kind.harmonise_used'),
    export_done: tk('admin.usage.kind.export_done'),
    share_created: tk('admin.usage.kind.share_created'),
    result_shared: tk('admin.usage.kind.result_shared'),
    open_game: tk('admin.usage.kind.open_game'),
    open_serious: tk('admin.usage.kind.open_serious'),
    game_on: tk('admin.usage.kind.game_on'),
    daily_viewed: tk('admin.usage.kind.daily_viewed'),
    daily_sung: tk('admin.usage.kind.daily_sung'),
    game_off: tk('admin.usage.kind.game_off'),
    sharpen_up: tk('admin.usage.kind.sharpen_up'),
    then_now: tk('admin.usage.kind.then_now'),
    keep_song: tk('admin.usage.kind.keep_song'),
    fact_source: tk('admin.usage.kind.fact_source'),
    hum_start: tk('admin.usage.kind.hum_start'),
    hum_found: tk('admin.usage.kind.hum_found'),
    hum_saved: tk('admin.usage.kind.hum_saved'),
    hum_click: tk('admin.usage.kind.hum_click'),
    hum_tap: tk('admin.usage.kind.hum_tap'),
    take_use: tk('admin.usage.kind.take_use'),
    take_undo: tk('admin.usage.kind.take_undo'),
    track_add: tk('admin.usage.kind.track_add'),
    track_rename: tk('admin.usage.kind.track_rename'),
    exp_song: tk('admin.usage.kind.exp_song'),
    exp_practice: tk('admin.usage.kind.exp_practice'),
    exp_stems: tk('admin.usage.kind.exp_stems'),
    share_note: tk('admin.usage.kind.share_note'),
    card_named: tk('admin.usage.kind.card_named'),
    su_progress: tk('admin.usage.kind.su_progress'),
    su_reminders: tk('admin.usage.kind.su_reminders'),
    su_share: tk('admin.usage.kind.su_share'),
    su_boards: tk('admin.usage.kind.su_boards'),
    card_day2: tk('admin.usage.kind.card_day2'),
    sug_melody: tk('admin.usage.kind.sug_melody'),
    sug_part: tk('admin.usage.kind.sug_part'),
    sug_switch: tk('admin.usage.kind.sug_switch'),
};
const TOP_COUNTRIES = 10;
const WEEK = 7;

/** A country's flag and name, or "Not known" (as People shows it). */
function countryOf(code) {
    if (!code || !/^[A-Z]{2}$/.test(code)) return t('admin.usage.countryUnknown');
    const flag = String.fromCodePoint(...[...code].map((c) => 0x1f1e6 + c.charCodeAt(0) - 65));
    try {
        return `${flag} ${new Intl.DisplayNames([locale.lang], { type: 'region' }).of(code) ?? code}`;
    } catch (error) {
        console.info('No country names here', error?.message ?? error);
        return `${flag} ${code}`;
    }
}

/** Each day of the range, oldest first, as 'YYYY-MM-DD' (UTC, as the server keeps them). */
function daysBack(count, now = new Date()) {
    const out = [];
    for (let i = count - 1; i >= 0; i--) out.push(new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate() - i)).toISOString().slice(0, 10));
    return out;
}

const percent = (value, most) => (most ? Math.max(value ? 2 : 0, Math.round((value / most) * 100)) : 0);
const getJson = (path) => fetch(path).then((r) => (r.ok ? r.json() : null)).catch(() => null);

/** "Updated 12 seconds ago": seconds under a minute, then as the app says times. */
function updatedAgo(at, now) {
    const seconds = Math.max(0, Math.round((now - at) / 1000));
    if (seconds < 5) return t('common.justNow');
    if (seconds < 60) return new Intl.RelativeTimeFormat(locale.lang, { numeric: 'auto' }).format(-seconds, 'second');
    return timeAgo(at, now);
}

export const UsageSection = {
    components: { EmptyState, LoadFailed },
    props: { me: { type: Object, required: true } },
    data: () => ({ days: 1, kind: 'app_open', counts: null, failed: false, loadingMore: false, cursor: null, course: null, catalogue: [], updatedAt: null, now: Date.now(), refreshing: false, staleSince: null }),
    setup: () => ({ WINDOWS, AREAS, KINDS, formatNumber, countryOf, percent }),
    template: `
        <section class="space-y-8" aria-labelledby="usage-h">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 id="usage-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.usage.title') }}</h1>
                    <p class="mt-1 max-w-prose text-muted">{{ t('admin.usage.intro') }}</p>
                </div>
                <div class="flex flex-col items-start gap-2 sm:items-end">
                    <div class="seg flex" role="radiogroup" :aria-label="t('admin.usage.range')">
                        <button v-for="d in WINDOWS" :key="d" type="button" role="radio" :aria-checked="days === d" class="seg-option" @click="setDays(d)">{{ d === 1 ? t('admin.usage.today') : t('admin.usage.days', { count: d }) }}</button>
                    </div>
                    <p v-if="updatedAt" class="flex flex-wrap items-center gap-x-3 text-sm text-muted" data-usage-updated>
                        <span>{{ staleSince ? t('admin.usage.notUpdated', { when: updated }) : t('admin.usage.updated', { when: updated }) }}</span>
                        <button type="button" class="btn-ghost btn-sm" :aria-busy="refreshing ? 'true' : null" :disabled="refreshing" @click="refresh"><span v-if="refreshing" class="spinner"></span>{{ t('admin.usage.refresh') }}</button>
                    </p>
                </div>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!counts" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!anything" icon="list" :title="days === 1 ? t('admin.usage.noneToday') : t('admin.usage.none')" />
            <template v-else>
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-lg font-bold text-cream">{{ days === 1 ? t('admin.usage.totalsToday') : t('admin.usage.totals', { count: days }) }}</h2>
                    <p class="flex gap-4 text-sm text-muted"><span><i class="me-1.5 inline-block size-2.5 rounded-full bg-accent align-middle"></i>{{ t('admin.usage.accounts') }}</span><span><i class="me-1.5 inline-block size-2.5 rounded-full bg-cream/40 align-middle"></i>{{ t('admin.usage.guests') }}</span></p>
                </div>
                <div class="grid items-start gap-6 xl:grid-cols-2" data-usage-totals>
                    <section v-for="area in AREAS" :key="area.key" class="min-w-0 space-y-3" :aria-labelledby="'area-' + area.key" :data-usage-area="area.key">
                        <h3 :id="'area-' + area.key" class="font-bold text-cream">{{ t(area.title) }}</h3>
                        <ul v-if="area.kinds.length" class="divide-y divide-line rounded-2xl border border-line">
                            <li v-for="k in area.kinds" :key="k" class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 px-4 py-1 text-sm sm:grid-cols-[minmax(0,12rem)_1fr_auto]" :data-kind="k">
                                <button type="button" class="min-h-11 truncate text-start text-cream hover:underline" :aria-pressed="kind === k" @click="choose(k)">{{ t(KINDS[k]) }}</button>
                                <span class="hidden h-2.5 overflow-hidden rounded-full bg-raised sm:flex" aria-hidden="true"><i class="bg-accent" :style="{ width: percent(sum(k).account, most(area)) + '%' }"></i><i class="bg-cream/40" :style="{ width: percent(sum(k).guest, most(area)) + '%' }"></i></span>
                                <span class="whitespace-nowrap text-end tabular-nums" :class="total(k) ? 'text-cream' : 'text-muted'">{{ formatNumber(total(k)) }} <span v-if="total(k)" class="hidden text-muted md:inline">{{ t('admin.usage.split', { accounts: formatNumber(sum(k).account), guests: formatNumber(sum(k).guest) }) }}</span></span>
                            </li>
                        </ul>
                        <div v-if="area.courses || area.lessons" class="rounded-2xl border border-line" :data-usage-detail="area.key">
                            <div class="grid grid-cols-[minmax(0,1fr)_4.5rem_4.5rem] gap-4 border-b border-line px-4 py-2 text-xs font-semibold text-muted">
                                <span>{{ area.courses ? t('admin.usage.course') : t('admin.usage.lesson') }}</span><span class="text-end">{{ t('admin.usage.started') }}</span><span class="text-end">{{ t('admin.usage.finished') }}</span>
                            </div>
                            <ul v-if="(area.courses ? byCourse : byLesson).length" class="divide-y divide-line">
                                <li v-for="row in area.courses ? byCourse : byLesson" :key="row.id" class="grid grid-cols-[minmax(0,1fr)_4.5rem_4.5rem] items-center gap-4 px-4 py-2.5 text-sm tabular-nums" :data-detail-id="row.id">
                                    <span class="min-w-0 break-words text-cream" translate="no"><span v-if="row.place" class="me-2 text-muted">{{ row.place }}</span>{{ row.label }}</span>
                                    <span class="text-end" :class="row.started ? 'text-cream' : 'text-muted'">{{ formatNumber(row.started) }}</span>
                                    <span class="text-end" :class="row.finished ? 'text-cream' : 'text-muted'">{{ formatNumber(row.finished) }}</span>
                                </li>
                            </ul>
                            <p v-else class="px-4 py-3 text-sm text-muted">{{ t('admin.usage.noLessons') }}</p>
                        </div>
                    </section>
                </div>

                <section v-if="days > 1" ref="daily" class="space-y-3" aria-labelledby="daily-h">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <h2 id="daily-h" class="text-lg font-bold text-cream">{{ t('admin.usage.dayByDay', { kind: t(KINDS[kind]) }) }}</h2>
                        <label class="text-sm text-muted">{{ t('admin.usage.which') }}
                            <select v-model="kind" class="input mt-1">
                                <optgroup v-for="area in AREAS.filter((a) => a.kinds.length)" :key="area.key" :label="t(area.title)"><option v-for="k in area.kinds" :key="k" :value="k">{{ t(KINDS[k]) }}</option></optgroup>
                            </select>
                        </label>
                    </div>
                    <div class="overflow-x-auto rounded-2xl border border-line p-4">
                        <div class="flex h-40 min-w-full items-end gap-1" data-usage-days role="img" :aria-label="t('admin.usage.dayByDay', { kind: t(KINDS[kind]) })">
                            <div v-for="d in daily" :key="d.date" class="flex h-full min-w-1.5 flex-1 flex-col justify-end" :title="d.label + ': ' + formatNumber(d.guest + d.account)">
                                <i class="block bg-cream/40" :style="{ height: percent(d.guest, mostDay) + '%' }"></i>
                                <i class="block rounded-b-sm bg-accent" :style="{ height: percent(d.account, mostDay) + '%' }"></i>
                            </div>
                        </div>
                        <p class="mt-2 flex justify-between text-xs text-muted"><span>{{ daily[0]?.label }}</span><span>{{ daily.at(-1)?.label }}</span></p>
                    </div>
                    <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4" data-usage-weeks>
                        <li v-for="w in weekly" :key="w.from" class="rounded-2xl bg-panel p-4"><p class="text-sm text-muted">{{ t('admin.usage.weekOf', { date: w.label }) }}</p><p class="text-xl font-bold text-cream">{{ formatNumber(w.total) }}</p></li>
                    </ul>
                </section>

                <section class="space-y-3" aria-labelledby="countries-h">
                    <h2 id="countries-h" class="text-lg font-bold text-cream">{{ t('admin.usage.countries') }}</h2>
                    <ul class="divide-y divide-line rounded-2xl border border-line" data-usage-countries>
                        <li v-for="c in countries" :key="c.country ?? 'none'" class="grid grid-cols-[minmax(0,12rem)_1fr_auto] items-center gap-4 px-4 py-2.5 text-sm">
                            <span class="truncate" :class="c.country ? 'text-cream' : 'text-muted'">{{ countryOf(c.country) }}</span>
                            <span class="h-2.5 overflow-hidden rounded-full bg-raised" aria-hidden="true"><i class="block h-full bg-accent" :style="{ width: percent(c.count, countries[0].count) + '%' }"></i></span>
                            <span class="text-end text-cream">{{ formatNumber(c.count) }}</span>
                        </li>
                    </ul>
                </section>
            </template>
        </section>`,
    computed: {
        /** Each kind's total over the window (Today is one day), from the day rows: the server's totals cover its whole range. */
        totals() {
            const window = new Set(daysBack(this.days));
            const out = new Map();
            for (const d of this.counts?.days ?? []) {
                if (!window.has(d.date)) continue;
                const had = out.get(d.kind) ?? { guest: 0, account: 0 };
                out.set(d.kind, { guest: had.guest + d.guest, account: had.account + d.account });
            }
            return out;
        },
        anything() {
            return [...this.totals.values()].some((r) => r.guest + r.account > 0);
        },
        /** The Plus courses: each live one, and any other with counts, in the catalogue's order. */
        byCourse() {
            return this.catalogue
                .map((c) => ({ id: c.id, label: localized(c.title), live: c.status === 'live', ...this.startedFinished('pl', c.id) }))
                .filter((c) => c.live || c.started || c.finished);
        },
        /** The free course: each lesson with counts, as "2.3 Title", in the course's order. */
        byLesson() {
            return (this.course?.units ?? [])
                .flatMap((unit, u) => unit.lessons.map((lesson, l) => ({ id: lesson.id, place: `${u + 1}.${l + 1}`, label: localized(lesson.title), ...this.startedFinished('fl', lesson.id) })))
                .filter((row) => row.started || row.finished);
        },
        /** The chosen moment, each day of the range (a day with none is a zero). */
        daily() {
            const byDate = new Map((this.counts?.days ?? []).filter((d) => d.kind === this.kind).map((d) => [d.date, d]));
            return daysBack(this.days).map((date) => ({ date, label: formatDate(`${date}T12:00:00Z`, { month: 'short', day: 'numeric' }), guest: byDate.get(date)?.guest ?? 0, account: byDate.get(date)?.account ?? 0 }));
        },
        mostDay() {
            return Math.max(0, ...this.daily.map((d) => d.guest + d.account));
        },
        /** The same, a week at a time, the latest last (a part week at the start is left out). */
        weekly() {
            const days = this.daily;
            const weeks = [];
            for (let end = days.length; end - WEEK >= 0; end -= WEEK) {
                const week = days.slice(end - WEEK, end);
                weeks.unshift({ from: week[0].date, label: week[0].label, total: week.reduce((sum, d) => sum + d.guest + d.account, 0) });
            }
            return weeks.slice(-4);
        },
        countries() {
            const all = [...(this.counts?.countries ?? [])].sort((a, b) => b.count - a.count);
            const known = all.filter((c) => c.country).slice(0, TOP_COUNTRIES);
            const unknown = all.find((c) => !c.country);
            return unknown ? [...known, unknown] : known;
        },
        updated() {
            return this.updatedAt ? updatedAgo(this.updatedAt, this.now) : '';
        },
    },
    created() {
        this.load();
        // The courses' names for their rows, from the files the app itself reads.
        getJson('/course/course.json').then((course) => (this.course = course));
        getJson('/course/plus/courses.json').then((plus) => (this.catalogue = plus?.courses ?? []));
    },
    mounted() {
        // Near live: every 30 seconds while the page is in view; paused when it's hidden, and caught up on coming back.
        this.ticker = setInterval(() => document.visibilityState === 'visible' && (this.now = Date.now()), TICK_MS);
        this.poller = setInterval(() => document.visibilityState === 'visible' && this.refresh(), REFRESH_MS);
        this.onVisible = () => {
            if (document.visibilityState !== 'visible') return;
            this.now = Date.now();
            if (this.updatedAt && this.now - this.updatedAt >= REFRESH_MS) this.refresh();
        };
        document.addEventListener('visibilitychange', this.onVisible);
    },
    unmounted() {
        clearInterval(this.ticker);
        clearInterval(this.poller);
        document.removeEventListener('visibilitychange', this.onVisible);
    },
    methods: {
        sum(kind) {
            return this.totals.get(kind) ?? { guest: 0, account: 0 };
        },
        total(kind) {
            const { guest, account } = this.sum(kind);
            return guest + account;
        },
        /** An area's longest bar: its busiest moment. */
        most(area) {
            return Math.max(0, ...area.kinds.map((k) => this.total(k)));
        },
        startedFinished(prefix, id) {
            return { started: this.total(`${prefix}_s:${id}`), finished: this.total(`${prefix}_d:${id}`) };
        },
        /** A moment's name picks it for Day by day, below. */
        choose(kind) {
            this.kind = kind;
            this.$nextTick(() => this.$refs.daily?.scrollIntoView({ block: 'nearest' }));
        },
        setDays(days) {
            this.days = days;
            this.load();
        },
        async load() {
            // The answer is one object, not a page: loadInto's list shape, wrapped.
            await loadInto(this, 'counts', async () => ({ data: (await api.admin.counts.show({ days: this.days })).data }));
            if (!this.failed) this.fresh();
            // Day by day opens on the busiest moment, unless the one chosen has counts.
            if (this.total(this.kind)) return;
            const busiest = Object.keys(KINDS).reduce((best, k) => (this.total(k) > this.total(best) ? k : best), this.kind);
            this.kind = busiest;
        },
        /** The same numbers again, quietly: the page stays as it is, and a failed refresh says so and keeps them. */
        async refresh() {
            if (this.refreshing || this.failed || !this.counts) return;
            this.refreshing = true;
            const days = this.days;
            try {
                const counts = (await api.admin.counts.show({ days })).data;
                if (days !== this.days) return; // the range changed meanwhile: its own load answers
                this.counts = counts;
                this.fresh();
            } catch (error) {
                console.info('Usage did not refresh', error?.message ?? error);
                this.staleSince ??= Date.now();
            } finally {
                this.refreshing = false;
            }
        },
        fresh() {
            this.updatedAt = this.now = Date.now();
            this.staleSince = null;
        },
    },
};
