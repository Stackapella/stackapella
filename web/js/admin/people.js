// The people (contract: admin.people.*, admin.securityEvents.list?user=, admin.audit.list?user=):
// everyone with an account, searched by name, handle or email, filtered (suspended, plan, 18 or over,
// beta singer or store tester) and sorted (newest, last active); and a person's page with what the
// API sends about them (never their recordings or songs), what was done about them, their recent
// sign-in events, and the
// changes Hillel can make: suspend and unsuspend, the trust level, recovering a lost sign-in, a
// refund of Plus bought on the web, and the store tester mark. Each change goes through the
// ChangeSheet (a reason for the audit log, the sudo note), titled with exactly what it does.

import { api, ApiError, call } from '../api.js';
import { formatDate, formatNumber, locale, t, tk } from '../i18n.js';
import { EmptyState, Field, Notice, StatusChip } from '../components/form.js';
import { Icon } from '../components/icon.js';
import { toast } from '../components/ui.js';
import { navigate, route } from '../router.js';
import { ChangeSheet, errorsFrom, LoadFailed, loadInto, refused, when } from './common.js';
import { EVENTS as EVENT_LABELS } from './safety.js';

const SUSPENDED = [
    { value: '', label: tk('admin.people.filter.everyone') },
    { value: '0', label: tk('admin.people.filter.active') },
    { value: '1', label: tk('admin.people.filter.suspended') },
];
const PLANS = [
    { value: '', label: tk('admin.people.filter.anyPlan') },
    { value: 'plus', label: tk('admin.people.filter.plus') },
    { value: 'free', label: tk('admin.people.filter.free') },
];
const AGES = [
    { value: '', label: tk('admin.people.filter.anyAge') },
    { value: '1', label: tk('admin.people.filter.adult') },
    { value: '0', label: tk('admin.people.filter.under18') },
];
const ROLES = [
    { value: '', label: tk('admin.people.filter.anyRole') },
    { value: 'singer', label: tk('admin.people.filter.singer') },
    { value: 'tester', label: tk('admin.people.filter.tester') },
];
const SORTS = [
    { value: 'newest', label: tk('admin.people.sort.newest') },
    { value: 'active', label: tk('admin.people.sort.active') },
];
// How Plus came: the billing state's own sources.
const SOURCES = { stripe: tk('admin.people.source.stripe'), apple: tk('admin.people.source.apple'), google: tk('admin.people.source.google'), coach: tk('admin.people.source.coach'), invoice: tk('admin.people.source.invoice'), demo: tk('admin.people.source.demo') };
const TRUST = [0, 1, 2, 3];
// What the server says when it refuses a change, for this screen's own refusals.
const REFUSALS = { staff_account: tk('admin.people.refused.staff'), email_taken: tk('admin.people.refused.emailTaken'), no_payment: tk('admin.people.refused.noPayment') };

const day = (time) => (time ? formatDate(time, { dateStyle: 'medium' }) : '');
// The list's columns: Person, Plan, Joined (and from where), Last active, Activity, Status (a fixed last column, so a chip never moves the others).
const COLUMNS = 'md:grid-cols-[minmax(0,1.5fr)_5rem_8rem_6rem_minmax(0,1.3fr)_9.5rem]';
const SUMMARY_COUNTRIES = 8;

/** A country (its two letters, as Cloudflare names it) as its flag and its name in the admin's language, or "Not known". */
function countryOf(code) {
    if (!code || !/^[A-Z]{2}$/.test(code)) return t('admin.people.countryUnknown');
    const flag = String.fromCodePoint(...[...code].map((c) => 0x1f1e6 + c.charCodeAt(0) - 65));
    let name = code;
    try {
        name = new Intl.DisplayNames([locale.lang], { type: 'region' }).of(code) ?? code;
    } catch (error) {
        console.info('No country names here', error?.message ?? error);
    }
    return `${flag} ${name}`;
}

/** A date as short as it can be on one line: Today, Yesterday, "Oct 28", or "Oct 28, 2025" from another year. */
function shortDay(time, now = new Date()) {
    if (!time) return '';
    const date = new Date(time);
    const days = Math.round((new Date(now.getFullYear(), now.getMonth(), now.getDate()) - new Date(date.getFullYear(), date.getMonth(), date.getDate())) / 86400000);
    if (days === 0) return t('admin.people.today');
    if (days === 1) return t('admin.people.yesterday');
    return formatDate(date, date.getFullYear() === now.getFullYear() ? { month: 'short', day: 'numeric' } : { month: 'short', day: 'numeric', year: 'numeric' });
}

/** The list's plan: Plus or Free (the page says until when, and how it came). */
function listPlan(person) {
    const plus = person.plan ? person.plan.plus : !!person.plusPaidUntil && new Date(person.plusPaidUntil) > new Date();
    return plus ? t('admin.people.plus') : t('admin.people.free');
}

// The person's numbers, by the name the server sends them under.
const STAT_NAMES = { songs: tk('admin.people.stat.songs'), takes: tk('admin.people.stat.takes'), lessonsDone: tk('admin.people.stat.lessonsDone'), stars: tk('admin.people.stat.stars'), level: tk('admin.people.stat.level'), streakWeeks: tk('admin.people.stat.streakWeeks'), practiceMinutes: tk('admin.people.stat.practiceMinutes'), storageBytes: tk('admin.people.stat.storageBytes'), publicHarmonies: tk('admin.people.stat.publicHarmonies'), groups: tk('admin.people.stat.groups'), lastActivity: tk('admin.people.stat.lastActivity') };
// What someone did last, as the stats name it.
const LAST_THINGS = { practice: tk('admin.people.last.practice'), take: tk('admin.people.last.take'), song: tk('admin.people.last.song'), share: tk('admin.people.last.share'), sign_in: tk('admin.people.last.sign_in') };

/** A row's activity in a few words: songs and lessons (when the server sends them). */
function activityOf(person) {
    const s = person.stats;
    if (!s) return '';
    return t('admin.people.activity', { songs: s.songs ?? 0, lessons: s.lessonsDone ?? 0 });
}

/** Under it, the weekly goal's streak, or the last thing they did. */
function lastThing(person) {
    const s = person.stats;
    if (!s) return '';
    if (s.streakWeeks > 1) return t('admin.people.streak', { weeks: s.streakWeeks });
    const last = s.lastActivity;
    return last && LAST_THINGS[last.kind] ? t('admin.people.lastThing', { thing: t(LAST_THINGS[last.kind]), when: shortDay(last.at) }) : '';
}

/** A person's status as chips: suspended, staff, a store tester, an address not yet verified. */
function chipsOf(person) {
    const chips = [];
    if (person.suspendedAt) chips.push({ tone: 'bad', label: t('admin.people.chip.suspended') });
    if (person.staff) chips.push({ tone: 'info', label: t('admin.people.chip.staff', { role: person.staff }) });
    if (person.storeTesterSince) chips.push({ tone: 'good', label: t('admin.people.chip.tester') });
    if (person.betaSinger) chips.push({ tone: 'info', label: t('admin.people.chip.singer') });
    if (person.adult === false) chips.push({ tone: 'warn', label: t('admin.people.chip.under18') });
    if (!person.emailVerified) chips.push({ tone: 'warn', label: t('admin.people.chip.unverified') });
    return chips;
}

/** Plus until a date (and how it came, when the page says), or Free. */
function planOf(person) {
    const plan = person.plan;
    if (plan) {
        if (!plan.plus) return t('admin.people.free');
        const until = plan.until ? t('admin.people.plusUntil', { date: day(plan.until) }) : t('admin.people.plus');
        return SOURCES[plan.source] ? t('admin.people.via', { plan: until, source: t(SOURCES[plan.source]) }) : until;
    }
    return person.plusPaidUntil && new Date(person.plusPaidUntil) > new Date() ? t('admin.people.plusUntil', { date: day(person.plusPaidUntil) }) : t('admin.people.free');
}

/** 18 or over, under 18, or not given. */
function ageOf(person) {
    return person.adult === true ? t('admin.people.adult') : person.adult === false ? t('admin.people.under18') : t('admin.people.ageUnknown');
}

const PeopleList = {
    components: { EmptyState, Icon, LoadFailed, StatusChip },
    data: () => ({ people: null, cursor: null, failed: false, loadingMore: false, q: '', suspended: '', plan: '', adult: '', role: '', sort: 'newest', country: '', countries: null }),
    setup: () => ({ SUSPENDED, PLANS, AGES, ROLES, SORTS, COLUMNS, chipsOf, listPlan, shortDay, activityOf, lastThing, countryOf, formatNumber, when }),
    template: `
        <section class="space-y-6" aria-labelledby="people-h">
            <div>
                <h1 id="people-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.people.title') }}</h1>
                <p class="mt-1 text-muted">{{ t('admin.people.intro') }}</p>
            </div>
            <section v-if="countries && countries.length" aria-labelledby="countries-h" class="space-y-2" data-countries>
                <h2 id="countries-h" class="text-sm font-semibold text-muted">{{ t('admin.people.byCountry') }}</h2>
                <ul class="flex flex-wrap gap-2">
                    <li v-for="c in topCountries" :key="c.country ?? 'none'">
                        <button v-if="c.country" type="button" class="filter-chip" :aria-pressed="country === c.country" @click="pickCountry(c.country)">{{ countryOf(c.country) }} <b class="ms-1">{{ formatNumber(c.people) }}</b></button>
                        <span v-else class="chip min-h-8 px-3 text-[0.84375rem]">{{ countryOf(null) }} <b class="ms-1">{{ formatNumber(c.people) }}</b></span>
                    </li>
                </ul>
            </section>
            <form class="flex flex-wrap items-end gap-3" role="search" @submit.prevent="load()">
                <label class="min-w-0 flex-1 text-sm text-muted">{{ t('admin.people.search') }}
                    <input v-model.trim="q" type="search" class="input mt-1 w-full" :placeholder="t('admin.people.searchHint')" />
                </label>
                <button type="submit" class="btn-secondary">{{ t('admin.people.find') }}</button>
                <div class="flex basis-full flex-wrap gap-3">
                    <label v-for="f in filters" :key="f.name" class="text-sm text-muted">{{ t(f.label) }}
                        <select v-model="$data[f.name]" class="input mt-1" @change="load()">
                            <option v-for="o in f.options" :key="o.value" :value="o.value">{{ o.plain ? o.label : t(o.label) }}</option>
                        </select>
                    </label>
                </div>
            </form>
            <LoadFailed v-if="failed" :message="failed" @retry="load()" />
            <p v-else-if="!people" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!people.length" icon="list" :title="t('admin.people.none')" />
            <!-- One grid for the header and every row, so the columns line up whatever a row holds; a phone stacks each row as a card, each value with its label. -->
            <div v-else class="overflow-hidden rounded-2xl border border-line" data-people>
                <div class="hidden gap-x-4 border-b border-line bg-panel px-4 py-2 text-xs font-semibold uppercase tracking-wider text-muted md:grid" :class="COLUMNS" aria-hidden="true">
                    <span>{{ t('admin.people.col.person') }}</span><span>{{ t('admin.people.plan') }}</span><span>{{ t('admin.people.joinedLabel') }}</span><span>{{ t('admin.people.lastActiveLabel') }}</span><span>{{ t('admin.people.col.activity') }}</span><span>{{ t('admin.people.col.status') }}</span>
                </div>
                <ul class="divide-y divide-line">
                    <li v-for="person in people" :key="person.id">
                        <a :href="'/admin/people/' + person.id" class="grid grid-cols-2 gap-x-4 gap-y-2 px-4 py-3 text-sm hover:bg-raised md:items-center md:gap-y-0" :class="COLUMNS" @click.prevent="open(person)">
                            <span class="col-span-2 min-w-0 md:col-span-1">
                                <span v-if="person.name" class="block truncate text-base font-semibold text-cream" translate="no">{{ person.name }}</span>
                                <span v-else class="block text-base text-muted">{{ t('admin.people.noName') }}</span>
                                <span class="block truncate text-muted" translate="no">{{ person.email }}</span>
                            </span>
                            <span><span class="block text-xs text-muted md:sr-only">{{ t('admin.people.plan') }}</span><span class="text-cream">{{ listPlan(person) }}</span></span>
                            <span class="min-w-0"><span class="block text-xs text-muted md:sr-only">{{ t('admin.people.joinedLabel') }}</span><span class="block whitespace-nowrap text-cream">{{ shortDay(person.createdAt) }}</span><span v-if="'country' in person" class="block truncate text-muted" :title="countryOf(person.country)">{{ countryOf(person.country) }}</span></span>
                            <span><span class="block text-xs text-muted md:sr-only">{{ t('admin.people.lastActiveLabel') }}</span><span class="whitespace-nowrap text-cream">{{ (person.lastActiveAt ?? person.lastSignInAt) ? shortDay(person.lastActiveAt ?? person.lastSignInAt) : t('admin.people.never') }}</span></span>
                            <span class="min-w-0"><span class="block text-xs text-muted md:sr-only">{{ t('admin.people.col.activity') }}</span><span class="block truncate text-cream">{{ activityOf(person) }}</span><span v-if="lastThing(person)" class="block truncate text-muted">{{ lastThing(person) }}</span></span>
                            <span class="col-span-2 flex flex-wrap gap-1.5 md:col-span-1"><span class="sr-only">{{ t('admin.people.col.status') }}</span><StatusChip v-for="c in chipsOf(person)" :key="c.label" :tone="c.tone" :label="c.label" /></span>
                        </a>
                    </li>
                </ul>
            </div>
            <button v-if="cursor" type="button" class="btn-secondary" :disabled="loadingMore" @click="load(true)">{{ t('admin.loadMore') }}</button>
        </section>`,
    computed: {
        topCountries() {
            const known = (this.countries ?? []).filter((c) => c.country).slice(0, SUMMARY_COUNTRIES);
            const unknown = (this.countries ?? []).find((c) => !c.country);
            return unknown ? [...known, unknown] : known;
        },
        filters() {
            return [
            { name: 'suspended', label: tk('admin.people.filter.label'), options: SUSPENDED },
            { name: 'plan', label: tk('admin.people.filter.planLabel'), options: PLANS },
            { name: 'adult', label: tk('admin.people.filter.ageLabel'), options: AGES },
            { name: 'role', label: tk('admin.people.filter.roleLabel'), options: ROLES },
            { name: 'country', label: tk('admin.people.filter.countryLabel'), options: [{ value: '', label: t('admin.people.filter.anyCountry'), plain: true }, ...(this.countries ?? []).filter((c) => c.country).map((c) => ({ value: c.country, label: countryOf(c.country), plain: true }))] },
            { name: 'sort', label: tk('admin.people.sort.label'), options: SORTS },
            ];
        },
    },
    created() {
        this.load();
        this.loadCountries();
    },
    methods: {
        async loadCountries() {
            try {
                this.countries = (await api.admin.people.countries()).data;
            } catch (error) {
                console.warn('The countries did not load', error);
                this.countries = [];
            }
        },
        pickCountry(code) {
            this.country = this.country === code ? '' : code;
            this.load();
        },
        load(more = false) {
            const query = { q: this.q, suspended: this.suspended, plan: this.plan, adult: this.adult, role: this.role, country: this.country, sort: this.sort === 'newest' ? '' : this.sort };
            const asked = Object.fromEntries(Object.entries(query).filter(([, v]) => v !== ''));
            return loadInto(this, 'people', () => api.admin.people.list({ ...asked, cursor: more ? this.cursor : undefined }), { more });
        },
        open(person) {
            navigate(`/admin/people/${person.id}`);
        },
    },
};

const PersonPage = {
    components: { ChangeSheet, Field, Icon, LoadFailed, Notice, StatusChip },
    props: { id: { type: String, required: true }, me: { type: Object, required: true } },
    data: () => ({ person: null, failed: null, events: null, audit: null, sheet: null, busy: false, errors: null, level: '', email: '', amount: '' }),
    setup: () => ({ TRUST, EVENT_LABELS, STAT_NAMES, chipsOf, planOf, ageOf, countryOf, day, when }),
    template: `
        <section class="space-y-6" aria-labelledby="person-h">
            <a href="/admin/people" class="btn-ghost btn-sm -ms-3" @click.prevent="back"><Icon name="back" :size="16" /> {{ t('admin.people.title') }}</a>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!person" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <div class="flex flex-wrap items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <h1 id="person-h" class="text-3xl font-extrabold tracking-tight text-cream" translate="no">{{ person.name }}</h1>
                        <p class="mt-1 text-muted" translate="no">{{ person.email }}<span v-if="person.handle"> · @{{ person.handle }}</span></p>
                    </div>
                    <span class="flex flex-wrap gap-1.5"><StatusChip v-for="c in chipsOf(person)" :key="c.label" :tone="c.tone" :label="c.label" /></span>
                </div>
                <dl class="grid gap-x-6 gap-y-4 rounded-2xl border border-line p-5 sm:grid-cols-2" data-person>
                    <div><dt class="text-sm text-muted">{{ t('admin.people.email') }}</dt><dd class="text-cream">{{ person.emailVerified ? t('admin.people.verified') : t('admin.people.notVerified') }}<span v-if="person.appleRelay"> · {{ t('admin.people.appleRelay') }}</span></dd></div>
                    <div><dt class="text-sm text-muted">{{ t('admin.people.signsIn') }}</dt><dd class="text-cream">{{ methods }}</dd></div>
                    <div><dt class="text-sm text-muted">{{ t('admin.people.joinedLabel') }}</dt><dd class="text-cream">{{ when(person.createdAt) }}</dd></div>
                    <div v-if="'country' in person"><dt class="text-sm text-muted">{{ t('admin.people.country') }}</dt><dd class="text-cream">{{ countryOf(person.country) }}</dd></div>
                    <div><dt class="text-sm text-muted">{{ t('admin.people.lastInLabel') }}</dt><dd class="text-cream">{{ person.lastSignInAt ? when(person.lastSignInAt) : t('admin.people.neverIn') }}</dd></div>
                    <div v-if="person.lastActiveAt"><dt class="text-sm text-muted">{{ t('admin.people.lastActiveLabel') }}</dt><dd class="text-cream">{{ when(person.lastActiveAt) }}</dd></div>
                    <div><dt class="text-sm text-muted">{{ t('admin.people.plan') }}</dt><dd class="text-cream">{{ planOf(person) }}</dd></div>
                    <div v-if="'adult' in person"><dt class="text-sm text-muted">{{ t('admin.people.age') }}</dt><dd class="text-cream">{{ ageOf(person) }}</dd></div>
                    <div v-if="person.betaSinger"><dt class="text-sm text-muted">{{ t('admin.people.beta') }}</dt><dd class="text-cream">{{ t('admin.people.betaSinger') }}</dd></div>
                    <div><dt class="text-sm text-muted">{{ t('admin.people.trust') }}</dt><dd class="text-cream">{{ t('admin.people.trustLevel', { level: person.trustLevel }) }}<span v-if="person.trustOverride !== null" class="text-muted"> · {{ t('admin.people.setByHand') }}<template v-if="person.trustNote">: {{ person.trustNote }}</template></span></dd></div>
                    <div v-if="person.storeTesterSince"><dt class="text-sm text-muted">{{ t('admin.people.testerSince') }}</dt><dd class="text-cream">{{ day(person.storeTesterSince) }}</dd></div>
                    <div v-if="person.suspendedAt"><dt class="text-sm text-muted">{{ t('admin.people.suspendedSince') }}</dt><dd class="text-cream">{{ when(person.suspendedAt) }}</dd></div>
                    <div v-if="person.heardFrom || person.ref"><dt class="text-sm text-muted">{{ t('admin.people.heardFrom') }}</dt><dd class="text-cream" translate="no">{{ [person.heardFrom, person.ref].filter(Boolean).join(' · ') }}</dd></div>
                </dl>

                <section v-if="statTiles.length" class="space-y-3" aria-labelledby="activity-h">
                    <h2 id="activity-h" class="text-lg font-bold text-cream">{{ t('admin.people.activityTitle') }}</h2>
                    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" data-person-stats>
                        <div v-for="tile in statTiles" :key="tile.key" class="rounded-2xl bg-panel p-4"><dt class="text-sm text-muted">{{ t(STAT_NAMES[tile.key]) }}</dt><dd class="mt-1 text-xl font-bold text-cream">{{ tile.value }}</dd></div>
                    </dl>
                </section>

                <section class="space-y-3" aria-labelledby="actions-h">
                    <h2 id="actions-h" class="text-lg font-bold text-cream">{{ t('admin.people.actions') }}</h2>
                    <Notice v-if="person.staff" tone="info">{{ t('admin.people.staffNote') }}</Notice>
                    <div class="flex flex-wrap gap-2">
                        <template v-if="!person.staff">
                            <button v-if="!person.suspendedAt" type="button" class="btn-secondary btn-sm text-record" @click="openSheet('suspend')">{{ t('admin.people.suspend') }}</button>
                            <button v-else type="button" class="btn-secondary btn-sm" @click="openSheet('unsuspend')">{{ t('admin.people.unsuspend') }}</button>
                        </template>
                        <button type="button" class="btn-secondary btn-sm" @click="openSheet('trust')">{{ t('admin.people.setTrust') }}</button>
                        <button v-if="!person.staff" type="button" class="btn-secondary btn-sm" @click="openSheet('recover')">{{ t('admin.people.recover') }}</button>
                        <button v-if="!person.plan || person.plan.source === 'stripe'" type="button" class="btn-secondary btn-sm" @click="openSheet('refund')">{{ t('admin.people.refund') }}</button>
                        <button type="button" class="btn-secondary btn-sm" @click="openSheet('boards')">{{ t('admin.people.boardsReset') }}</button>
                        <button type="button" class="btn-secondary btn-sm" @click="openSheet('tester')">{{ person.storeTesterSince ? t('admin.people.testerOff') : t('admin.people.testerOn') }}</button>
                    </div>
                </section>

                    <p v-if="person.plan && ['apple', 'google'].includes(person.plan.source)" class="text-sm text-muted">{{ t('admin.people.storeRefund') }}</p>

                <section class="space-y-3" aria-labelledby="audit-h">
                    <h2 id="audit-h" class="text-lg font-bold text-cream">{{ t('admin.people.audit') }}</h2>
                    <p v-if="!audit" class="text-sm text-muted" role="status">{{ t('admin.loading') }}</p>
                    <p v-else-if="!audit.length" class="text-sm text-muted">{{ t('admin.people.noAudit') }}</p>
                    <ul v-else class="divide-y divide-line rounded-2xl border border-line" data-person-audit>
                        <li v-for="e in audit" :key="e.id" class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 px-4 py-2.5 text-sm">
                            <span class="min-w-0"><code class="tabular-nums text-xs text-cream" translate="no">{{ e.action }}</code><span v-if="e.reason" class="ms-2 text-muted" translate="no">{{ e.reason }}</span></span>
                            <span class="text-muted">{{ e.actor ? e.actor.name + ' · ' : '' }}{{ when(e.createdAt) }}</span>
                        </li>
                    </ul>
                </section>

                <section class="space-y-3" aria-labelledby="events-h">
                    <h2 id="events-h" class="text-lg font-bold text-cream">{{ t('admin.people.events') }}</h2>
                    <p v-if="!events" class="text-sm text-muted" role="status">{{ t('admin.loading') }}</p>
                    <p v-else-if="!events.length" class="text-sm text-muted">{{ t('admin.people.noEvents') }}</p>
                    <ul v-else class="divide-y divide-line rounded-2xl border border-line">
                        <li v-for="e in events" :key="e.id" class="flex flex-wrap justify-between gap-2 px-4 py-2.5 text-sm"><span class="text-cream">{{ EVENT_LABELS[e.type] ? t(EVENT_LABELS[e.type]) : t('admin.events.other') }}</span><span class="text-muted">{{ when(e.createdAt) }}</span></li>
                    </ul>
                </section>

                <ChangeSheet v-if="sheet" :title="sheetTitle" :me="me" :busy="busy" :errors="errors" :fields="['level', 'email', 'amountCents']" :reasoned="sheet !== 'tester'" :needs-sudo="sheet !== 'tester'" @close="sheet = null" @confirm="confirm">
                    <p class="text-cream">{{ sheetBody }}</p>
                    <Field v-if="sheet === 'trust'" :label="t('admin.people.trust')" :error="errors?.level" v-slot="f">
                        <select :id="f.id" v-model="level" class="input w-full" :aria-describedby="f.describedby">
                            <option value="">{{ t('admin.people.trustEarned') }}</option>
                            <option v-for="n in TRUST" :key="n" :value="String(n)">{{ t('admin.people.trustLevel', { level: n }) }}</option>
                        </select>
                    </Field>
                    <Field v-if="sheet === 'recover'" :label="t('admin.people.newEmail')" :help="t('admin.people.newEmailHelp')" :error="errors?.email" required v-slot="f">
                        <input :id="f.id" v-model.trim="email" type="email" required class="input w-full" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                    </Field>
                    <Field v-if="sheet === 'refund'" :label="t('admin.people.amount')" :help="t('admin.people.amountHelp')" :error="errors?.amountCents" required v-slot="f">
                        <input :id="f.id" v-model.trim="amount" type="text" inputmode="decimal" required class="input w-full" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                    </Field>
                </ChangeSheet>
            </template>
        </section>`,
    computed: {
        /** The person's numbers, each only when the server sends it (counts and totals, never contents). */
        statTiles() {
            const s = { ...(this.person?.stats ?? {}), ...(this.person?.counts ?? {}) };
            const shown = {
                songs: (v) => formatNumber(v),
                takes: (v) => formatNumber(v),
                lessonsDone: (v) => formatNumber(v),
                stars: (v) => formatNumber(v),
                level: (v) => t('admin.people.statLevel', { level: v, xp: formatNumber(s.xp ?? 0) }),
                streakWeeks: (v) => t('admin.people.statStreak', { weeks: v }),
                practiceMinutes: (v) => t('admin.people.statMinutes', { lastWeek: v.lastWeek ?? 0, total: v.total ?? 0 }),
                storageBytes: (v) => formatNumber(v / 1e6, { maximumFractionDigits: 1, style: 'unit', unit: 'megabyte' }),
                publicHarmonies: (v) => formatNumber(v),
                groups: (v) => formatNumber(v),
                lastActivity: (v) => (LAST_THINGS[v.kind] ? t('admin.people.lastThing', { thing: t(LAST_THINGS[v.kind]), when: shortDay(v.at) }) : shortDay(v.at)),
            };
            return Object.entries(shown).filter(([key]) => s[key] !== undefined && s[key] !== null).map(([key, show]) => ({ key, value: show(s[key]) }));
        },
        methods() {
            const m = this.person?.methods ?? {};
            const names = [m.google && 'Google', m.apple && 'Apple', m.eventSchedule && 'Event Schedule', m.password && t('admin.people.password')].filter(Boolean); // i18n-ignore: brand names
            return names.join(', ') || t('admin.people.noMethod');
        },
        sheetTitle() {
            const name = this.person?.name ?? '';
            return {
                suspend: t('admin.people.sheet.suspend', { name }),
                unsuspend: t('admin.people.sheet.unsuspend', { name }),
                trust: t('admin.people.sheet.trust', { name }),
                recover: t('admin.people.sheet.recover', { name }),
                refund: t('admin.people.sheet.refund', { name }),
                boards: t('admin.people.sheet.boards', { name }),
                tester: this.person?.storeTesterSince ? t('admin.people.sheet.testerOff', { name }) : t('admin.people.sheet.testerOn', { name }),
            }[this.sheet];
        },
        sheetBody() {
            return {
                suspend: t('admin.people.sheet.suspendBody'),
                unsuspend: t('admin.people.sheet.unsuspendBody'),
                trust: t('admin.people.sheet.trustBody'),
                recover: t('admin.people.sheet.recoverBody'),
                refund: t('admin.people.sheet.refundBody'),
                boards: t('admin.people.sheet.boardsBody'),
                tester: this.person?.storeTesterSince ? t('admin.people.sheet.testerOffBody') : t('admin.people.sheet.testerOnBody'),
            }[this.sheet];
        },
    },
    watch: {
        id: { handler: 'load', immediate: true },
    },
    methods: {
        back() {
            navigate('/admin/people');
        },
        async load() {
            this.failed = null;
            this.person = null;
            this.events = null;
            this.audit = null;
            try {
                this.person = (await api.admin.people.show(this.id)).data;
            } catch (error) {
                refused(error);
                this.failed = error instanceof ApiError && error.code === 'not_found' ? t('admin.people.notFound') : t('admin.requestFailed');
                return;
            }
            const [audit, events] = await Promise.allSettled([api.admin.audit.list({ user: this.id }), api.admin.securityEvents.list({ user: this.id })]);
            if (audit.status === 'rejected') console.warn('The audit entries did not load', audit.reason);
            if (events.status === 'rejected') console.warn('The sign-in events did not load', events.reason);
            this.audit = audit.status === 'fulfilled' ? audit.value.data.slice(0, 10) : [];
            this.events = events.status === 'fulfilled' ? events.value.data.slice(0, 10) : [];
        },
        openSheet(name) {
            this.errors = null;
            this.level = this.person.trustOverride === null ? '' : String(this.person.trustOverride);
            this.email = '';
            this.amount = '';
            this.sheet = name;
        },
        async confirm(reason) {
            const id = this.person.id;
            const people = api.admin.people;
            this.busy = true;
            this.errors = null;
            try {
                const request = {
                    suspend: () => people.suspend(id, { reason }),
                    unsuspend: () => people.unsuspend(id, { reason }),
                    trust: () => people.trust.update(id, { level: this.level === '' ? null : Number(this.level), note: reason }),
                    recover: () => people.recover(id, { email: this.email, reason }),
                    refund: () => people.refund(id, { amountCents: Math.round(Number(this.amount.replace(',', '.')) * 100), note: reason }),
                    tester: () => people.storeTester.update(id, { tester: !this.person.storeTesterSince }),
                    boards: () => call('admin.boards.reset', { params: { id }, body: { reason } }),
                }[this.sheet];
                const answer = (await request()).data;
                if (this.sheet === 'refund' || this.sheet === 'boards') {
                    toast(this.sheet === 'refund' ? t('admin.people.refunded') : t('admin.people.boardsRemoved'));
                } else {
                    this.person = answer;
                    toast(t('admin.saved'));
                }
                this.sheet = null;
            } catch (error) {
                const fields = errorsFrom(error);
                if (fields) this.errors = fields;
                else if (error instanceof ApiError && REFUSALS[error.code]) this.errors = { refusal: [t(REFUSALS[error.code])] }; // said under the form
                else {
                    this.errors = {};
                    refused(error);
                }
            } finally {
                this.busy = false;
            }
        },
    },
};

export const PeopleSection = {
    components: { PeopleList, PersonPage },
    props: { me: { type: Object, required: true } },
    setup: () => ({ route }),
    template: `<PersonPage v-if="route.params.id" :id="route.params.id" :me="me" /><PeopleList v-else />`,
};
