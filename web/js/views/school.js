// The school offer (docs/school-offer-plan.md; Hillel: "teacher-led, no groups"), behind auth.config's
// `schools`. Two screens:
// - /settings/classes, a teacher's class codes (Settings > For teachers, never a top-level button):
//   make one (a name for themselves, 1 to 60 seats), copy its link, see seats used, change the seats
//   or the name, close it. Numbers only: who joined is never shown, to anyone but Admin.
// - /class and /class/<code>, a student's join: the code (from the link, or typed), then Plus free
//   for a year, said plainly with its end date. Signed out, the account wall comes first.
// Words for every refusal the server gives (adult_required, school_already_redeemed, ...).

import { api, ApiError } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { account, loadAccount, loadAuthConfig } from '../stores/account.js';
import { loadBilling } from '../stores/plan.js';
import { AccountWall } from '../components/account-wall.js';
import { PageHeader } from '../components/page.js';
import { Notice } from '../components/form.js';
import { Icon } from '../components/icon.js';
import { toast } from '../components/ui.js';
import { confirmAction } from '../components/confirm.js';

const MOST_SEATS = 60;
/** What a refusal means, said for the person (contract/api.md, "School codes"). */
const REFUSALS = {
    adult_required: tk('school.error.adult'),
    email_unverified: tk('school.error.email'),
    age_unconfirmed: tk('school.error.age'),
    school_too_many_codes: tk('school.error.tooMany'),
    school_seat_allowance: tk('school.error.allowance'),
    school_seats_below_used: tk('school.error.belowUsed'),
    school_already_redeemed: tk('school.error.alreadyRedeemed'),
    school_already_plus: tk('school.error.alreadyPlus'),
    school_code_unknown: tk('school.error.unknown'),
    school_code_full: tk('school.error.full'),
    rate_limited: tk('school.error.tooFast'),
};
const refusal = (error) => (error instanceof ApiError && REFUSALS[error.code] ? t(REFUSALS[error.code]) : error?.code === 'offline' ? t('common.offline') : t('school.error.other'));

/** A code as people type it: letters and digits only, upper case, the dash put back after four. */
export function cleanCode(typed) {
    const plain = String(typed ?? '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
    return plain.length > 4 ? `${plain.slice(0, 4)}-${plain.slice(4)}` : plain;
}

/** A class's state: 'open', 'full', 'closed' (by its teacher or Admin) or 'expired' (120 days on). */
export function classState(c, now = Date.now()) {
    if (['pending', 'open', 'full', 'closed', 'expired'].includes(c.status)) return c.status; // the server's word, when it sends one
    if (c.closedAt) return 'closed';
    if (c.expiresAt && Date.parse(c.expiresAt) <= now) return 'expired';
    if ((c.used ?? 0) >= c.seats) return 'full';
    return 'open';
}

const STATES = { pending: tk('school.state.pending'), open: tk('school.state.open'), full: tk('school.state.full'), closed: tk('school.state.closed'), expired: tk('school.state.expired') };

export const ClassesView = {
    components: { AccountWall, PageHeader, Notice, Icon },
    data: () => ({ classes: null, failed: false, name: '', seats: 30, making: false, error: null, editing: null, editName: '', editSeats: 0, saving: false, editError: null }),
    setup: () => ({ account, MOST_SEATS, STATES, classState, formatNumber }),
    template: `
        <div class="mx-auto min-h-dvh max-w-3xl px-4 pb-16 pt-[max(1rem,env(safe-area-inset-top))] md:px-8" data-classes>
            <PageHeader :title="t('school.classes.title')" back="/settings" />
            <AccountWall v-if="account.loaded && !account.me" reason="settings" next="/settings/classes" />
            <template v-else-if="account.me">
                <p class="max-w-prose text-muted">{{ t('school.classes.intro') }}</p>
                <Notice v-if="account.me.isAdult === false" tone="info" class="mt-5">{{ t('school.error.adult') }}</Notice>
                <template v-else>
                    <!-- Make a code: the one action, first. -->
                    <form class="card mt-6 space-y-4 p-5" data-class-new @submit.prevent="make">
                        <h2 class="text-lg font-bold text-cream">{{ t('school.classes.new') }}</h2>
                        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_9rem]">
                            <label class="space-y-1.5"><span class="block text-sm font-semibold text-cream">{{ t('school.classes.name') }}</span>
                                <input v-model.trim="name" required maxlength="80" class="input w-full" :placeholder="t('school.classes.namePlaceholder')" data-class-name></label>
                            <label class="space-y-1.5"><span class="block text-sm font-semibold text-cream">{{ t('school.classes.seats') }}</span>
                                <input v-model.number="seats" type="number" min="1" :max="MOST_SEATS" required inputmode="numeric" class="input w-full" data-class-seats></label>
                        </div>
                        <p class="text-sm text-muted">{{ t('school.classes.newHint') }}</p>
                        <p v-if="error" class="text-sm text-bad" role="alert">{{ error }}</p>
                        <button type="submit" class="btn-primary" :disabled="making || !name" data-class-make>{{ making ? t('common.saving') : t('school.classes.make') }}</button>
                    </form>

                    <section class="mt-8 space-y-3" aria-labelledby="classes-h">
                        <h2 id="classes-h" class="text-lg font-bold text-cream">{{ t('school.classes.yours') }}</h2>
                        <p v-if="failed" class="text-muted" role="status">{{ t('school.classes.failed') }} <button type="button" class="link" @click="load">{{ t('common.tryAgain') }}</button></p>
                        <p v-else-if="!classes" class="text-muted" role="status">{{ t('common.loading') }}</p>
                        <p v-else-if="!classes.length" class="text-muted" data-classes-empty>{{ t('school.classes.none') }}</p>
                        <ul v-else class="space-y-3">
                            <li v-for="c in classes" :key="c.id" class="card space-y-3 p-5" :data-class="c.code" :data-state="classState(c)">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-bold text-cream" translate="no">{{ c.name }}</p>
                                        <p class="font-mono text-lg tracking-widest text-cream" translate="no" data-class-code>{{ c.code }}</p>
                                    </div>
                                    <span class="rounded-md border border-line px-2 py-0.5 text-xs font-semibold text-muted" data-class-state>{{ t(STATES[classState(c)]) }}</span>
                                </div>
                                <p class="text-sm text-muted">{{ t('school.classes.seatsUsed', { used: formatNumber(c.used ?? 0), seats: formatNumber(c.seats) }) }} · {{ untilLine(c) }}</p>
                                <p v-if="classState(c) === 'pending'" class="text-sm text-cream" data-class-pending>{{ t('school.classes.pending') }}</p>
                                <!-- Change: the name and seats in place, with Save and Cancel together. -->
                                <form v-if="editing === c.id" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_9rem]" data-class-edit @submit.prevent="save(c)">
                                    <label class="space-y-1.5"><span class="block text-sm font-semibold text-cream">{{ t('school.classes.name') }}</span><input v-model.trim="editName" required maxlength="80" class="input w-full"></label>
                                    <label class="space-y-1.5"><span class="block text-sm font-semibold text-cream">{{ t('school.classes.seats') }}</span><input v-model.number="editSeats" type="number" :min="leastSeats(c)" :max="MOST_SEATS" required inputmode="numeric" class="input w-full"></label>
                                    <p v-if="editError" class="text-sm text-bad sm:col-span-2" role="alert">{{ editError }}</p>
                                    <div class="flex flex-wrap gap-3 sm:col-span-2">
                                        <button type="submit" class="btn-primary btn-sm" :disabled="saving">{{ saving ? t('common.saving') : t('common.save') }}</button>
                                        <button type="button" class="btn-secondary btn-sm" @click="editing = null">{{ t('common.cancel') }}</button>
                                    </div>
                                </form>
                                <div v-else-if="classState(c) !== 'closed' && classState(c) !== 'expired'" class="flex flex-wrap items-center gap-3">
                                    <button v-if="classState(c) !== 'pending'" type="button" class="btn-primary btn-sm" data-class-copy @click="copy(c)">{{ t('school.classes.copy') }}</button>
                                    <button type="button" class="btn-secondary btn-sm" data-class-change @click="startEdit(c)">{{ t('school.classes.change') }}</button>
                                    <button type="button" class="tap-44 btn-ghost btn-sm" data-class-close @click="close(c)">{{ t('school.classes.close') }}</button>
                                </div>
                            </li>
                        </ul>
                    </section>
                    <p class="mt-8 max-w-prose text-sm text-muted">{{ t('school.classes.daily') }} {{ t('school.classes.privacy') }}</p>
                </template>
            </template>
        </div>`,
    created() {
        if (account.me) this.load();
        else loadAccount().then(() => account.me && this.load());
    },
    methods: {
        /** Seats can't go below the students who joined. */
        leastSeats: (c) => Math.max(1, c.used ?? 0),
        untilLine(c) {
            const state = classState(c);
            if (state === 'closed') return t('school.classes.closedOn', { date: formatDate(c.closedAt) });
            if (state === 'expired') return t('school.classes.endedOn', { date: formatDate(c.expiresAt) });
            return t('school.classes.until', { date: formatDate(c.expiresAt) });
        },
        async load() {
            this.failed = false;
            try {
                this.classes = (await api.school.classes.list()).data;
            } catch (error) {
                console.warn('The class codes did not load', error);
                this.failed = true;
            }
        },
        async make() {
            this.making = true;
            this.error = null;
            try {
                const made = (await api.school.classes.create({ name: this.name, seats: Math.min(MOST_SEATS, Math.max(1, this.seats || 1)) })).data;
                this.classes = [made, ...(this.classes ?? [])];
                this.name = '';
                toast(made.status === 'pending' ? t('school.classes.madePending', { code: made.code }) : t('school.classes.made', { code: made.code }));
            } catch (error) {
                this.error = refusal(error);
            } finally {
                this.making = false;
            }
        },
        startEdit(c) {
            this.editing = c.id;
            this.editName = c.name;
            this.editSeats = c.seats;
            this.editError = null;
        },
        async save(c) {
            this.saving = true;
            this.editError = null;
            try {
                const saved = (await api.school.classes.update(c.id, { name: this.editName, seats: this.editSeats })).data;
                this.classes = this.classes.map((x) => (x.id === c.id ? saved : x));
                this.editing = null;
            } catch (error) {
                this.editError = refusal(error);
            } finally {
                this.saving = false;
            }
        },
        async close(c) {
            const sure = await confirmAction({ title: t('school.classes.closeTitle', { name: c.name }), body: t('school.classes.closeBody'), action: t('school.classes.closeAction') });
            if (!sure) return;
            try {
                const closed = (await api.school.classes.update(c.id, { action: 'close' })).data;
                this.classes = this.classes.map((x) => (x.id === c.id ? closed : x));
            } catch (error) {
                toast(refusal(error), { tone: 'error' });
            }
        },
        async copy(c) {
            const text = t('school.classes.shareText', { code: c.code, url: c.url });
            try {
                if (navigator.share) await navigator.share({ text: t('school.classes.shareLine'), url: c.url });
                else {
                    await navigator.clipboard.writeText(text);
                    toast(t('school.classes.copied'));
                }
            } catch (error) {
                if (error?.name !== 'AbortError') toast(t('school.classes.copyFailed'), { tone: 'error' });
            }
        },
    },
};

export const ClassJoinView = {
    components: { AccountWall, PageHeader },
    props: { code: { type: String, default: '' } },
    data: () => ({ typed: '', joining: false, error: null, joined: null, schools: null }),
    setup: () => ({ account, cleanCode }),
    template: `
        <div class="mx-auto flex min-h-dvh max-w-xl flex-col px-4 pb-16 pt-[max(1rem,env(safe-area-inset-top))] md:px-8" data-class-join>
            <PageHeader :title="t('school.join.title')" back="/" />
            <div class="my-auto">
                <section class="card space-y-5 p-6 md:p-8">
                    <template v-if="joined">
                        <h1 class="text-2xl font-extrabold tracking-tight text-cream" data-joined>{{ t('school.join.done') }}</h1>
                        <p class="text-cream">{{ t('school.join.doneUntil', { date: formatDate(joined) }) }}</p>
                        <p class="text-sm text-muted">{{ t('school.join.doneAfter') }}</p>
                        <button type="button" class="btn-primary w-full sm:w-auto" @click="navigate('/')">{{ t('school.join.start') }}</button>
                    </template>
                    <template v-else>
                        <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('school.join.heading') }}</h1>
                        <p class="text-cream">{{ t('school.join.what') }}</p>
                        <p class="text-sm text-muted">{{ t('school.join.terms') }}</p>
                        <p v-if="schools === false" class="text-muted" role="status" data-schools-off>{{ t('school.join.off') }}</p>
                        <AccountWall v-else-if="account.loaded && !account.me" reason="school" :next="here" bare />
                        <form v-else-if="account.me" class="space-y-4" @submit.prevent="join">
                            <label class="block space-y-1.5"><span class="block text-sm font-semibold text-cream">{{ t('school.join.code') }}</span>
                                <input :value="typed" @input="typed = cleanCode($event.target.value)" required autocomplete="off" autocapitalize="characters" spellcheck="false" class="input w-full font-mono text-lg tracking-widest" :placeholder="t('school.join.placeholder')" data-join-code></label>
                            <p v-if="error" class="text-sm text-bad" role="alert" data-join-error>{{ error }}</p>
                            <button type="submit" class="btn-primary w-full" :disabled="joining || typed.length < 9" data-join>{{ joining ? t('common.saving') : t('school.join.button') }}</button>
                        </form>
                    </template>
                </section>
            </div>
        </div>`,
    computed: {
        here() {
            return this.code ? `/class/${this.code}` : '/class';
        },
    },
    async created() {
        this.typed = cleanCode(this.code);
        const config = await loadAuthConfig().catch(() => null);
        this.schools = config ? config.schools !== false : null;
    },
    methods: {
        formatDate,
        async join() {
            this.joining = true;
            this.error = null;
            try {
                const billing = (await api.school.redeem({ code: this.typed })).data;
                this.joined = billing.schoolUntil;
                await loadAccount({ fresh: true });
                loadBilling();
            } catch (error) {
                this.error = refusal(error);
            } finally {
                this.joining = false;
            }
        },
    },
};
