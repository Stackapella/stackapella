// Plus (`/plus`; docs/pricing-plan.md and docs/features.md, which the words here follow; Hillel's
// 89.png): what Plus gets you first (three highlights, one tied to the songs you've saved), then the
// price beside them, yearly first with the saving worked out ("Save 33%", "$3.33 a month, billed
// yearly"), in dollars, euros or pounds; a button naming what you're buying; the renewal, the tax and
// the launch-price promise in plain sentences; and Free and Plus side by side, only the differences
// first. No free trial (Hillel, 2026-11-21: "better to charge people from the start"; someone already in one still
// sees when it ends); Checkout shows the tax, the renewal and how to cancel
// before anything is paid. On Plus: the plan as it stands, and Manage (Stripe's portal). Opened from
// an upgrade moment (`?from=`), it says what led here first.

import { AccountWall } from '../components/account-wall.js';
import { AppScreen } from '../components/page.js';
import { ApiError } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { goBack, navigate, route } from '../router.js';
import { account, selfHosted } from '../stores/account.js';
import { teams } from '../teams.js';
import { ownChoirOn } from '../own-choir.js';
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
    // Shared private arrangements need everyone's own Plus (plus_private, Hillel 2026-11-17).
    private_shared: tk('plus.moment.privateShared'),
    // Downloading a choir made from your voice (Hillel, 2026-11-21; listening and sharing stay free).
    choir_download: tk('plus.moment.choirDownload'),
    choir_settings: tk('plus.moment.choirSettings'), // Choir settings' Plus sheet (Checkout's moment, for Admin's choir line)
    choir_friends: tk('plus.moment.choirFriends'),
    // More songs in your own voice, the free one used (once per email, ever).
    choir_song: tk('plus.moment.choirSong'),
};
// Once Plus is on, the way back to what led here (components/plus-moment.js keepNext), by its moment.
const NEXT_WORDS = { group_start: tk('plus.next.groupStart'), invite_friend: tk('plus.next.inviteFriend'), private_shared: tk('plus.next.inviteFriend'), choir_download: tk('plus.next.choirDownload'), choir_song: tk('plus.next.choirDownload') };
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
        // Hear yourself as a choir (own_choir; Hillel's "Free to learn. Plus for what you make."): the first song free,
        // about 15 a month with Plus (60 minutes of singing), and downloading them.
        { what: tk('plus.row.choirSongs'), free: tk('plus.free.choirSongs'), plus: tk('plus.plus.choirSongs'), choir: true },
        { what: tk('plus.row.choirDownload'), free: null, plus: tk('plus.yes'), choir: true },
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
const CURRENCIES = ['usd', 'eur', 'gbp'];
const STATUS = { trialing: tk('plus.status.trialing'), active: tk('plus.status.active'), past_due: tk('plus.status.pastDue'), canceled: tk('plus.status.canceled'), pending: tk('plus.status.pending') };

export const PlusView = {
    components: { AccountWall, AppScreen, Notice, Icon, Logo },
    data: () => ({ interval: 'year', currency: guessCurrency(), busy: false, notice: null, failed: false, everything: false, next: keptNext(), consent: false, consentAsked: false }),
    setup: () => ({ account, selfHosted, plan, navigate, goBack, MOMENTS, NEXT_WORDS, CARRY_ON, GROUPS, CURRENCIES, STATUS, formatDate, formatNumber }),
    template: `
        <AppScreen :title="t('plus.title')" back="/" measure="" content-class="gap-8 md:gap-12" data-ui="screen" class="bg-ink">
                <!-- A self-hosted install: everything is included, nothing to buy. -->
                <p v-if="selfHosted()" class="card p-6 text-cream" role="status" data-self-hosted>{{ t('plus.included') }}</p>
                <div v-if="!selfHosted()" class="mt-4 grid gap-8 md:mt-6 md:grid-cols-[minmax(0,1fr)_minmax(0,25rem)] md:gap-12">
                    <section class="space-y-6" aria-labelledby="plus-heading">
                        <p v-if="moment" class="font-semibold text-accent-strong">{{ t(MOMENTS[moment]) }}</p>
                        <!-- The site's own words (ai-marketing: lang/en/plus.php's items, so app and site never drift): the headline,
                             then each point as its gain and its fact, the AI choir first. No lede: it could only say the headline
                             again or list the points under it (the no-repetition rule; the coordinator and ai-marketing). -->
                        <h2 id="plus-heading" class="t-title-lg text-cream">{{ t('plus.page.headline') }}</h2>
                        <ul class="card divide-y divide-line overflow-hidden" data-plus-points>
                            <li v-for="p in points" :key="p.key" class="flex gap-4 px-5 py-4" :data-plus-point="p.key">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-raised text-cream" aria-hidden="true"><Icon :name="p.icon" :size="20" /></span>
                                <span class="min-w-0">
                                    <span class="block font-semibold text-cream">{{ p.title }}</span>
                                    <span class="block text-muted">{{ p.text }}</span>
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
                            <h2 id="yours-heading" class="text-lg font-semibold text-cream">{{ t('plus.yours') }}</h2>
                            <p v-if="billing" class="text-cream">{{ statusText }}</p>
                            <Notice v-if="billing?.notice === 'payment_failed'" tone="warn">{{ t('plus.notice.paymentFailed', { date: formatDate(billing.pastDueUntil) }) }}</Notice>
                            <button v-if="next" type="button" class="btn-primary self-start" data-plus-next @click="goNext">{{ t(NEXT_WORDS[next.from] ?? CARRY_ON) }}</button>
                            <button v-if="billing?.subscription" type="button" class="btn-secondary self-start" :disabled="busy" @click="manage">{{ t('plus.manage') }}</button>
                            <p v-else-if="billing" class="text-sm text-muted">{{ t('plus.byInvoice') }}</p>
                        </section>

                        <!-- Not on Plus: the price -->
                        <section v-else class="card space-y-5 p-5 md:p-6" aria-labelledby="price-heading">
                            <h2 id="price-heading" class="sr-only">{{ t('plus.price') }}</h2>
                            <div class="space-y-3" role="radiogroup" :aria-label="t('plus.billing')">
                                <label v-for="choice in ['year', 'month']" :key="choice" class="radio-card items-center p-4">
                                    <input v-model="interval" type="radio" :value="choice" class="radio" />
                                    <span class="min-w-0 flex-1">
                                        <span class="flex flex-wrap items-center gap-2 font-semibold text-cream">{{ choice === 'year' ? t('plus.yearly') : t('plus.monthly') }}<span v-if="choice === 'year' && saving" class="rounded-full bg-good/15 px-2 py-0.5 text-xs font-bold text-good">{{ t('plus.save', { percent: saving }) }}</span></span>
                                        <span v-if="priceOf(choice)" class="block text-sm text-muted">{{ choice === 'year' ? t('plus.perMonthYearly', { price: money(priceOf(choice).amountCents / 12) }) : t('plus.billedMonthly') }}</span>
                                    </span>
                                    <span v-if="priceOf(choice)" class="shrink-0 text-end">
                                        <span class="block text-xl font-bold text-cream tabular-nums">{{ money(priceOf(choice).amountCents) }}</span>
                                        <span class="t-hint block">{{ choice === 'year' ? t('plus.perYear') : t('plus.perMonthShort') }}</span>
                                    </span>
                                </label>
                            </div>
                            <p data-ui="facts" class="flex items-start gap-2 rounded-xl bg-accent/10 px-3 py-2.5 text-sm font-semibold text-cream"><Icon name="sparkle" :size="16" class="mt-0.5 shrink-0 text-accent" />{{ t('plus.launchPrice') }}</p>
                            <!-- In the EU, EEA and UK, Plus starts at once only with this consent (the withdrawal right): the server's
                                 versioned words (billing.prices' startNowWords), ticked before Checkout. -->
                            <label v-if="consentNeeded" class="check-row" data-plus-consent><input v-model="consent" type="checkbox" class="checkbox" @change="notice = null" /><span class="text-sm">{{ consentWords }}</span></label>
                            <AccountWall v-if="notice?.signIn" reason="plus" />
                            <Notice v-else-if="notice" :tone="notice.tone">{{ notice.text }}</Notice>
                            <button type="button" class="btn-primary btn-lg w-full" :disabled="busy || failed || (!!account.me && !plan.loaded)" @click="start">{{ buyLabel }}</button>
                            <p class="text-sm text-muted">{{ interval === 'year' ? t('plus.renewsYear') : t('plus.renewsMonth') }}</p>
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
                        <h2 id="compare-heading" class="text-lg font-semibold text-cream">{{ t('plus.compare') }}</h2>
                        <div class="seg inline-flex" role="radiogroup" :aria-label="t('plus.show')">
                            <button v-for="all in [false, true]" :key="String(all)" type="button" role="radio" :aria-checked="everything === all ? 'true' : 'false'" class="seg-option" @click="everything = all">{{ all ? t('plus.everything') : t('plus.differences') }}</button>
                        </div>
                    </div>
                    <div class="card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[20rem] text-sm">
                                <thead><tr class="border-b border-line"><th class="px-5 py-3 text-start font-semibold text-muted" scope="col"><span class="sr-only">{{ t('plus.what') }}</span></th><th class="px-3 py-3 text-start font-semibold text-muted" scope="col">{{ t('plus.free.title') }}</th><th class="px-3 py-3 pe-5 text-start font-bold text-accent" scope="col">{{ t('plus.plus.title') }}</th></tr></thead>
                                <tbody v-for="group in shownGroups" :key="group.name">
                                    <tr><th colspan="3" class="t-section px-5 pt-5 pb-2 text-start" scope="colgroup">{{ t(group.name) }}</th></tr>
                                    <tr v-for="row in group.rows" :key="row.what" class="border-t border-line">
                                        <th class="px-5 py-3 text-start font-semibold text-cream" scope="row">{{ t(row.what) }}<span v-if="row.note" class="t-hint block font-normal">{{ t(row.note, { polished: t('polish.name') }) }}</span></th>
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
        /** The EU, EEA and UK consent to start at once: where billing.prices says so, or the server asked for it. */
        consentNeeded() {
            return this.consentAsked || !!plan.prices?.startNowConsent;
        },
        /** Its words: the server's, versioned (it records which); the same approved words here until they're served. */
        consentWords() {
            return plan.prices?.startNowWords?.text || t('plus.startNow');
        },
        teamsOn: () => teams.on,
        billing: () => plan.billing,
        /** Plus's numbers, from the server's config (auth.config plusLimits; an older server: none, the lines without them). */
        limits: () => account.config?.plusLimits ?? null,
        /**
         * What Plus is for, the site's list in its order and by the same switches (PlusFeatures): your own AI choir (while
         * own_choir is on), choir groups (plus_teams), room for every take, every part as its own file, and private
         * arrangements (plus_private, only when the AI choir isn't listed: three or four points, never five).
         */
        points() {
            const limits = this.limits;
            const gb = limits ? formatNumber(Math.round(limits.storageBytes / 1073741824), { style: 'unit', unit: 'gigabyte' }) : null;
            return [
                this.choirOn ? { key: 'choir', icon: 'sparkle', title: t('plus.page.choir'), text: limits ? t('plus.page.choirFact', { minutes: formatNumber(limits.choirMinutes), songs: formatNumber(limits.choirSongs) }) : t('plus.page.choirFactPlain') } : null,
                this.teamsOn ? { key: 'together', icon: 'mic', title: t('plus.page.together'), text: account.config?.choirSeats ? t('plus.page.togetherSeats') : t('plus.page.togetherFact') } : null,
                { key: 'storage', icon: 'list', title: t('plus.page.storage'), text: gb ? t('plus.page.storageFact', { storage: gb }) : t('plus.page.storageFactPlain') },
                { key: 'stems', icon: 'note', title: t('plus.page.stems'), text: t('plus.page.stemsFact') },
                !this.choirOn && teams.private ? { key: 'private', icon: 'lock', title: t('plus.page.private'), text: t('plus.page.privateFact') } : null,
            ].filter(Boolean);
        },
        /** Yearly's saving over twelve months of Monthly, in the currency shown. */
        saving() {
            const year = this.priceOf('year')?.amountCents;
            const month = this.priceOf('month')?.amountCents;
            return year && month ? Math.round((1 - year / (12 * month)) * 100) : 0;
        },
        /** The button says what you're buying: "Get Plus yearly · $39.99". */
        buyLabel() {
            const price = this.priceOf(this.interval);
            if (!price) return t('plus.subscribe');
            return t(this.interval === 'year' ? tk('plus.getYearly') : tk('plus.getMonthly'), { price: this.money(price.amountCents) });
        },
        /** The table: only the rows where Free and Plus differ, unless Everything. */
        shownGroups() {
            const shown = (r) => (r.choir ? this.choirOn : r.teams ? this.teamsOn : r.solo ? !this.teamsOn : true);
            return GROUPS.map((g) => ({ ...g, rows: g.rows.filter(shown).filter((r) => this.everything || r.free !== r.plus) }));
        },
        /** Hear yourself as a choir is on: its rows, and the headline and story that name it. */
        choirOn: () => ownChoirOn(),
        onPlus: () => isPlus(),
        moment: () => {
            const from = new URLSearchParams(location.search).get('from') ?? route.params.from;
            return from && MOMENTS[from] ? from : null;
        },
        /** Back from Checkout, before the payment is confirmed. */
        pending: () => plan.billing?.status === 'pending' && !isPlus(),
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
            if (this.consentNeeded && !this.consent) {
                this.notice = { tone: 'warn', text: t('plus.consentFirst') };
                return;
            }
            this.busy = true;
            try {
                await checkout({ interval: this.interval, currency: this.currency, moment: this.moment ?? 'settings', startNowConsent: this.consentNeeded && this.consent });
            } catch (error) {
                // The server asks for the consent where this page didn't know to (the account's country): the box, and why.
                if (error?.code === 'validation_failed' && error.errors?.startNowConsent) {
                    this.consentAsked = true;
                    this.notice = { tone: 'warn', text: t('plus.consentFirst') };
                } else this.notice = { tone: 'bad', text: refusal(error) };
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
    if (error.code === 'paused') return t('plus.paused');
    if (error.code === 'no_subscription') return t('plus.noSubscription');
    if (error.code === 'rate_limited') return t('auth.error.wait');
    if (error.code === 'offline') return t('common.offline');
    return t('plus.failed');
}
