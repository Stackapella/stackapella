// Plus (`/plus`; docs/pricing-plan.md and docs/features.md, which the words here follow; Hillel's
// 89.png): what Plus gets you first (three highlights, one tied to the songs you've saved), then the
// price beside them, yearly first with the saving worked out ("Save 33%", "$3.33 a month, billed
// yearly"), in dollars, euros or pounds; a button naming what you're buying; the renewal, the tax and
// the launch-price promise in plain sentences; and Free and Plus side by side, only the differences
// first. The 7-day trial where it's offered; Checkout shows the tax, the renewal and how to cancel
// before anything is paid. On Plus: the plan as it stands, and Manage (Stripe's portal). Opened from
// an upgrade moment (`?from=`), it says what led here first.

import { AccountWall } from '../components/account-wall.js';
import { AppScreen } from '../components/page.js';
import { ApiError } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { goBack, navigate, route } from '../router.js';
import { account, selfHosted } from '../stores/account.js';
import { teams } from '../teams.js';
import { checkout, guessCurrency, isPlus, loadBilling, loadPrices, plan, portal } from '../stores/plan.js';
import { Notice } from '../components/form.js';
import { Icon, Logo } from '../components/icon.js';
import { keptNext, NEXT_KEY } from '../components/plus-moment.js';
import { session } from '../storage.js';

// The upgrade moments (contract/api.md billing.checkout's `moment`) and what each says first. Learning alone is free,
// history included; teaming up is Plus: whoever starts a shared song or a choir group, everyone they invite joining free
// (Hillel, 2026-11-12; docs/pricing-plan.md, top). Never in front of a course, song, workout or your history, and never
// asked of someone joining.
const MOMENTS = {
    storage_limit: tk('plus.moment.storageLimit'),
    mp3: tk('plus.moment.mp3'),
    stems: tk('plus.moment.stems'),
    private_template: tk('plus.moment.privateTemplate'),
    // Teaming up is Plus (docs/pricing-plan.md, top): starting a choir group, inviting a friend (frontend's words).
    group_start: tk('plus.moment.groupStart'),
    invite_friend: tk('plus.moment.inviteFriend'),
};
// Once Plus is on, the way back to what led here (components/plus-moment.js keepNext), by its moment.
const NEXT_WORDS = { group_start: tk('plus.next.groupStart'), invite_friend: tk('plus.next.inviteFriend') };
const CARRY_ON = tk('plus.next.carryOn');
// docs/features.md, "Free and Plus, side by side": the extras Plus adds for you, then what's free for every
// singer. `free: null` is a dash (Free hasn't it); a row the same on both shows only under Everything.
const GROUPS = [
    { name: tk('plus.group.you'), rows: [
        { what: tk('plus.row.storage'), free: tk('plus.free.storage'), plus: tk('plus.plus.storage') },
        { what: tk('plus.row.mp3'), free: null, plus: tk('plus.yes') },
        { what: tk('plus.row.stems'), free: tk('plus.free.stems'), plus: tk('plus.yes'), note: tk('plus.row.stemsNote') },
        { what: tk('plus.row.templates'), free: tk('plus.free.templates'), plus: tk('plus.unlimited') },
        // Teaming up (Hillel, 2026-11-12): starting one is Plus; joining one you're invited to is free on either plan. Only
        // while the plus_teams switch is on (`teams`); until then both are free, in the group below (`solo`).
        { what: tk('plus.row.friends'), free: tk('plus.free.joinInvited'), plus: tk('plus.plus.startInvite'), teams: true },
        { what: tk('plus.row.choir'), free: tk('plus.free.joinInvited'), plus: tk('plus.plus.startChoir'), teams: true },
    ] },
    // Free for every singer (Hillel, 2026-11-06 and 11-07): the same on both, so only under Everything.
    { name: tk('plus.group.free'), rows: [
        { what: tk('plus.row.songs'), free: tk('plus.allSongs'), plus: tk('plus.allSongs') },
        // Your own recorded guides: free for every singer (Hillel, 2026-11-12: "feels odd to pay us for us to use your voice"),
        // listed once the app has the screen to record one for your arrangement (the coordinator's rule: never a promise).
        { what: tk('plus.row.course'), free: tk('plus.everyCourse'), plus: tk('plus.everyCourse') },
        { what: tk('plus.row.workout'), free: tk('plus.asMany'), plus: tk('plus.asMany') },
        { what: tk('plus.row.report'), free: tk('plus.plus.report'), plus: tk('plus.plus.report') },
        { what: tk('plus.row.projects'), free: tk('plus.asFit'), plus: tk('plus.asFit') },
        { what: tk('plus.row.friends'), free: tk('plus.yes'), plus: tk('plus.yes'), solo: true },
        { what: tk('plus.row.choir'), free: tk('plus.yes'), plus: tk('plus.yes'), solo: true },
        { what: tk('plus.row.share'), free: tk('plus.yes'), plus: tk('plus.yes') },
        { what: tk('plus.row.preview'), free: tk('plus.yes'), plus: tk('plus.yes') },
        { what: tk('plus.row.wav'), free: tk('plus.yes'), plus: tk('plus.yes'), note: tk('plus.row.wavNote') },
    ] },
];
const HIGHLIGHT_COLORS = ['var(--color-part-2)', 'var(--color-part-3)', 'var(--color-part-4)'];
const CURRENCIES = ['usd', 'eur', 'gbp'];
const STATUS = { trialing: tk('plus.status.trialing'), active: tk('plus.status.active'), past_due: tk('plus.status.pastDue'), canceled: tk('plus.status.canceled'), pending: tk('plus.status.pending') };

export const PlusView = {
    components: { AccountWall, AppScreen, Notice, Icon, Logo },
    data: () => ({ interval: 'year', currency: guessCurrency(), busy: false, notice: null, failed: false, everything: false, next: keptNext() }),
    setup: () => ({ account, selfHosted, plan, navigate, goBack, MOMENTS, NEXT_WORDS, CARRY_ON, GROUPS, CURRENCIES, STATUS, HIGHLIGHT_COLORS, formatDate, formatNumber }),
    template: `
        <AppScreen :title="t('plus.title')" back="/" measure="" content-class="gap-8 md:gap-12" data-ui="screen" class="bg-ink">
                <!-- A self-hosted install: everything is included, nothing to buy. -->
                <p v-if="selfHosted()" class="card p-6 text-cream" role="status" data-self-hosted>{{ t('plus.included') }}</p>
                <div v-if="!selfHosted()" class="mt-4 grid gap-8 md:mt-6 md:grid-cols-[minmax(0,1fr)_minmax(0,25rem)] md:gap-12">
                    <section class="space-y-6" aria-labelledby="plus-heading">
                        <p v-if="moment" class="font-semibold text-accent-strong">{{ t(MOMENTS[moment]) }}</p>
                        <h2 id="plus-heading" class="text-4xl font-extrabold tracking-tight text-cream md:text-5xl">{{ teamsOn ? t('plus.headline') : t('plus.headlineSolo') }}</h2>
                        <p class="text-lg text-muted">{{ teamsOn ? t('plus.story') : t('plus.storySolo') }}</p>
                        <ul class="space-y-5">
                            <li v-for="(h, i) in highlights" :key="h.title" class="flex gap-4">
                                <span class="gem grid size-10 shrink-0 place-items-center rounded-xl" :style="{ '--gem': HIGHLIGHT_COLORS[i], '--gem-ink': 'var(--color-ink)' }" aria-hidden="true"><Icon :name="h.icon" :size="20" /></span>
                                <span class="min-w-0">
                                    <span class="block font-bold text-cream">{{ h.title }}</span>
                                    <span class="block text-muted">{{ h.text }}</span>
                                </span>
                            </li>
                        </ul>
                    </section>

                    <div class="space-y-4">
                        <Notice v-if="notice && onPlus" :tone="notice.tone">{{ notice.text }}</Notice>
                        <!-- Back from Checkout before Stripe has told us: confirming, never a second Checkout. -->
                        <section v-if="pending" class="card space-y-2 p-6" role="status">
                            <p class="font-semibold text-cream">{{ t('plus.status.pending') }}</p>
                            <p class="text-sm text-muted">{{ t('plus.pendingHint') }}</p>
                        </section>
                        <!-- On Plus: the plan as it stands -->
                        <section v-else-if="onPlus" class="card space-y-3 p-6" aria-labelledby="yours-heading">
                            <h2 id="yours-heading" class="text-lg font-bold text-cream">{{ t('plus.yours') }}</h2>
                            <p v-if="billing" class="text-cream">{{ statusText }}</p>
                            <Notice v-if="billing?.notice === 'payment_failed'" tone="warn">{{ t('plus.notice.paymentFailed', { date: formatDate(billing.pastDueUntil) }) }}</Notice>
                            <button v-if="next" type="button" class="btn-primary self-start" data-plus-next @click="goNext">{{ t(NEXT_WORDS[next.from] ?? CARRY_ON) }}</button>
                            <button v-if="billing?.subscription" type="button" class="btn-secondary self-start" :disabled="busy" @click="manage">{{ t('plus.manage') }}</button>
                            <p v-else-if="billing" class="text-sm text-muted">{{ t('plus.byInvoice') }}</p>
                        </section>

                        <!-- Not on Plus: the price and the trial -->
                        <section v-else class="card space-y-5 p-5 md:p-6" aria-labelledby="price-heading">
                            <h2 id="price-heading" class="sr-only">{{ t('plus.price') }}</h2>
                            <Notice v-if="billing?.notice === 'card_had_trial'" tone="info">{{ t('plus.notice.cardHadTrial') }}</Notice>
                            <div class="space-y-3" role="radiogroup" :aria-label="t('plus.billing')">
                                <label v-for="choice in ['year', 'month']" :key="choice" class="radio-card items-center p-4">
                                    <input v-model="interval" type="radio" :value="choice" class="radio" />
                                    <span class="min-w-0 flex-1">
                                        <span class="flex flex-wrap items-center gap-2 font-bold text-cream">{{ choice === 'year' ? t('plus.yearly') : t('plus.monthly') }}<span v-if="choice === 'year' && saving" class="rounded-full bg-good/15 px-2 py-0.5 text-xs font-bold text-good">{{ t('plus.save', { percent: saving }) }}</span></span>
                                        <span v-if="priceOf(choice)" class="block text-sm text-muted">{{ choice === 'year' ? t('plus.perMonthYearly', { price: money(priceOf(choice).amountCents / 12) }) : t('plus.billedMonthly') }}</span>
                                    </span>
                                    <span v-if="priceOf(choice)" class="shrink-0 text-end">
                                        <span class="block text-xl font-extrabold text-cream tabular-nums">{{ money(priceOf(choice).amountCents) }}</span>
                                        <span class="block text-xs text-muted">{{ choice === 'year' ? t('plus.perYear') : t('plus.perMonthShort') }}</span>
                                    </span>
                                </label>
                            </div>
                            <p data-ui="facts" class="flex items-start gap-2 rounded-xl bg-accent/10 px-3 py-2.5 text-sm font-semibold text-cream"><Icon name="sparkle" :size="16" class="mt-0.5 shrink-0 text-accent" />{{ t('plus.launchPrice') }}</p>
                            <AccountWall v-if="notice?.signIn" reason="plus" />
                            <Notice v-else-if="notice" :tone="notice.tone">{{ notice.text }}</Notice>
                            <button type="button" class="btn-primary btn-lg w-full" :disabled="busy || failed || (!!account.me && !plan.loaded)" @click="start">{{ buyLabel }}</button>
                            <p class="text-sm text-muted">{{ trialOffered ? t('plus.smallTrial', { days: plan.prices?.trial?.days ?? 7 }) : interval === 'year' ? t('plus.renewsYear') : t('plus.renewsMonth') }}</p>
                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                                <label class="flex items-center gap-2 text-sm text-muted">{{ t('plus.currency') }}
                                    <select v-model="currency" class="input w-auto py-1.5"><option v-for="c in CURRENCIES" :key="c" :value="c" translate="no">{{ c.toUpperCase() }}</option></select>
                                </label>
                                <p class="text-sm text-muted">{{ priceOf(interval)?.taxInclusive ? t('plus.taxIncluded') : t('plus.taxAdded') }}</p>
                            </div>
                        </section>
                    </div>
                </div>

                <section class="space-y-4" aria-labelledby="compare-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="compare-heading" class="text-2xl font-extrabold tracking-tight text-cream">{{ t('plus.compare') }}</h2>
                        <div class="seg inline-flex" role="radiogroup" :aria-label="t('plus.show')">
                            <button v-for="all in [false, true]" :key="String(all)" type="button" role="radio" :aria-checked="everything === all ? 'true' : 'false'" class="seg-option" @click="everything = all">{{ all ? t('plus.everything') : t('plus.differences') }}</button>
                        </div>
                    </div>
                    <div class="card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[20rem] text-sm">
                                <thead><tr class="border-b border-line"><th class="px-5 py-3 text-start font-semibold text-muted" scope="col"><span class="sr-only">{{ t('plus.what') }}</span></th><th class="px-3 py-3 text-start font-semibold text-muted" scope="col">{{ t('plus.free.title') }}</th><th class="px-3 py-3 pe-5 text-start font-bold text-accent" scope="col">{{ t('plus.plus.title') }}</th></tr></thead>
                                <tbody v-for="group in shownGroups" :key="group.name">
                                    <tr><th colspan="3" class="px-5 pt-5 pb-2 text-start text-xs font-bold tracking-widest text-muted uppercase" scope="colgroup">{{ t(group.name) }}</th></tr>
                                    <tr v-for="row in group.rows" :key="row.what" class="border-t border-line">
                                        <th class="px-5 py-3 text-start font-semibold text-cream" scope="row">{{ t(row.what) }}<span v-if="row.note" class="block text-xs font-normal text-muted">{{ t(row.note, { polished: t('polish.name') }) }}</span></th>
                                        <td class="px-3 py-3 text-muted"><template v-if="row.free">{{ t(row.free) }}</template><template v-else><span aria-hidden="true">–</span><span class="sr-only">{{ t('plus.no') }}</span></template></td>
                                        <td class="px-3 py-3 pe-5 font-semibold text-cream">{{ t(row.plus) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="border-t border-line px-5 py-4 text-sm text-muted">{{ t('plus.footnote') }}</p>
                    </div>
                </section>
        </AppScreen>`,
    computed: {
        teamsOn: () => teams.on,
        billing: () => plan.billing,
        /** What Plus gets you (the room and the extras), and what stays free for every singer. */
        highlights() {
            // Sing together leads only while teaming up is Plus (the plus_teams switch); until then it's in what's free.
            return [
                this.teamsOn ? { icon: 'mic', title: t('plus.highlight.together'), text: t('plus.highlight.togetherText') } : null,
                { icon: 'list', title: t('plus.highlight.extras'), text: t('plus.highlight.extrasText') },
                { icon: 'note', title: t('plus.highlight.free'), text: this.teamsOn ? t('plus.highlight.freeText') : t('plus.highlight.freeTextSolo') },
            ].filter(Boolean);
        },
        /** Yearly's saving over twelve months of Monthly, in the currency shown. */
        saving() {
            const year = this.priceOf('year')?.amountCents;
            const month = this.priceOf('month')?.amountCents;
            return year && month ? Math.round((1 - year / (12 * month)) * 100) : 0;
        },
        /** The button says what you're buying: "Get Plus yearly · $39.99", or the trial. */
        buyLabel() {
            if (this.trialOffered) return t('plus.startTrial', { days: plan.prices?.trial?.days ?? 7 });
            const price = this.priceOf(this.interval);
            if (!price) return t('plus.subscribe');
            return t(this.interval === 'year' ? tk('plus.getYearly') : tk('plus.getMonthly'), { price: this.money(price.amountCents) });
        },
        /** The table: only the rows where Free and Plus differ, unless Everything. */
        shownGroups() {
            const shown = (r) => (r.teams ? this.teamsOn : r.solo ? !this.teamsOn : true);
            return GROUPS.map((g) => ({ ...g, rows: g.rows.filter(shown).filter((r) => this.everything || r.free !== r.plus) }));
        },
        onPlus: () => isPlus(),
        moment: () => {
            const from = new URLSearchParams(location.search).get('from') ?? route.params.from;
            return from && MOMENTS[from] ? from : null;
        },
        /** Back from Checkout, before the payment is confirmed. */
        pending: () => plan.billing?.status === 'pending' && !isPlus(),
        /** The trial, when the account (or a visitor) would get it for this interval (the prices' offer if the plan didn't load). */
        trialOffered() {
            const offer = account.me && plan.billing ? plan.billing.trial : plan.prices?.trial;
            return !!offer && (offer.intervals ?? []).includes(this.interval);
        },
        statusText() {
            const b = this.billing;
            const s = b?.subscription;
            if (!b || !STATUS[b.status]) return t('plus.status.active');
            const date = b.status === 'trialing' ? s?.trialEndsAt : b.status === 'canceled' ? s?.endsAt : b.status === 'past_due' ? b.pastDueUntil : s?.renewsAt;
            return t(STATUS[b.status], { date: date ? formatDate(date) : '' });
        },
    },
    unmounted() {
        clearTimeout(this.recheck);
    },
    watch: {
        // Confirming a Checkout: the plan is read again every few seconds until Stripe has told us.
        pending(now) {
            clearTimeout(this.recheck);
            const again = () => (this.recheck = setTimeout(() => loadBilling().then(() => this.pending && again()), 4000));
            if (now) again();
        },
        // Opened straight from a link, the account arrives after the page: then the plan is read.
        'account.me.id': {
            async handler(id) {
                if (!id) return;
                await loadBilling();
                if (plan.billing?.subscription?.currency) this.currency = plan.billing.subscription.currency; // Stripe keeps a customer to one
            },
            immediate: true,
        },
    },
    async created() {
        try {
            await loadPrices();
        } catch (error) {
            console.warn('Prices not loaded', error);
            this.failed = true;
            this.notice = { tone: 'bad', text: error?.code === 'offline' ? t('common.offline') : t('plus.notLoaded') };
        }
    },
    methods: {
        /** Back to what led here (a choir to start), once: the way back is forgotten as it's taken. */
        goNext() {
            const { path } = this.next;
            session.remove(NEXT_KEY);
            navigate(path);
        },
        priceOf(interval) {
            return plan.prices?.prices.find((p) => p.interval === interval && p.currency === this.currency) ?? null;
        },
        money(cents) {
            return formatNumber(Math.floor(cents) / 100, { style: 'currency', currency: this.currency.toUpperCase() });
        },
        async start() {
            this.notice = null;
            if (!account.me) {
                this.notice = { signIn: true }; // the account wall, in the notice's place
                return;
            }
            this.busy = true;
            try {
                await checkout({ interval: this.interval, currency: this.currency, trial: this.trialOffered, moment: this.moment ?? 'settings' });
            } catch (error) {
                this.notice = { tone: 'bad', text: refusal(error) };
            } finally {
                this.busy = false;
            }
        },
        async manage() {
            this.busy = true;
            try {
                await portal();
            } catch (error) {
                this.notice = { tone: 'bad', text: refusal(error) };
            } finally {
                this.busy = false;
            }
        },
    },
};

function refusal(error) {
    if (!(error instanceof ApiError)) return t('plus.failed');
    if (error.code === 'email_unverified') return t('plus.verifyFirst');
    if (error.code === 'already_plus') return t('plus.alreadyPlus');
    if (error.code === 'trial_used') return t('plus.trialUsed');
    if (error.code === 'paused') return t('plus.paused');
    if (error.code === 'no_subscription') return t('plus.noSubscription');
    if (error.code === 'rate_limited') return t('auth.error.wait');
    if (error.code === 'offline') return t('common.offline');
    return t('plus.failed');
}
