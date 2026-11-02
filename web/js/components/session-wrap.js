// The first session's wrap (docs/onboarding-review.md, session 1 step 5; onboarding item 5): on lesson
// 3's end in the free course, on the day this device arrived, a card under the result (never a
// modal): what the session gave ("your note A3, 3 lessons, 8 stars"), the weekly goal chosen rather
// than given (3 looks chosen, nothing is kept until a tap), and exactly one keep-it ask for this
// device: install where it can (Chrome's one tap, the iPhone's Share then Add to Home Screen, the
// Mac's Add to Dock), else an account. "Not now" is a real answer, kept in the ask ledger (js/asks.js):
// every ask waits 14 days, and after three Not nows they stop.

import { navigate } from '../router.js';
import { formatList, t } from '../i18n.js';
import { midiName } from '../dsp.js';
import { local } from '../storage.js';
import { askDue, askNotNow, askSeen } from '../asks.js';
import { account } from '../stores/account.js';
import { learning, setGoal } from '../stores/learning.js';
import { askNot, askShown, askYes, daysSinceArrival, first, funnelRecord } from '../funnel.js';
import { install, installed, iphone, macSafari } from './install.js';
import { loadReminder, pushSupported } from '../reminders.js';
import { Icon } from './icon.js';
import { game, sk } from '../skin.js';
import { experiment } from '../experiments.js';
import { levelFor } from '../course.js';
import { signUpFor } from './account-wall.js';

const GOALS = [2, 3, 5];
const SUGGESTED = 3;
/**
 * Which keep-it ask fits this device: 'prompt' (one tap), 'iphone', 'mac', 'account', or null. On
 * Safari an added app keeps its own storage, apart from Safari's (docs/account-nudges-plan.md, F1),
 * so installing keeps nothing that's here: a guest is asked for the account, and only someone
 * signed in (whose account comes along) is offered the app.
 */
export function keepAskFor({ isInstalled = installed(), prompt = !!install.prompt, isIphone = iphone(), isMac = macSafari(), signedIn = !!account.me } = {}) {
    if (!isInstalled && prompt) return 'prompt';
    if (!isInstalled && signedIn && isIphone) return 'iphone';
    if (!isInstalled && signedIn && isMac) return 'mac';
    return signedIn ? null : 'account';
}

/** The ledger's name (and the funnel's) for a keep-it ask. */
const ledgerName = (ask) => (ask === 'account' ? 'account' : 'install');

export const SessionWrap = {
    components: { Icon },
    props: { lessons: { type: Number, required: true }, stars: { type: Number, required: true } },
    data: () => ({ goal: null, answered: false }),
    setup: () => ({ GOALS, SUGGESTED, learning, midiName, sk }),
    template: `
        <section class="w-full space-y-5 rounded-3xl border border-edge bg-panel p-5 text-start" aria-labelledby="wrap-title" data-session-wrap>
            <h2 id="wrap-title" class="text-lg font-bold text-cream">{{ learning.progress.home !== null ? sk('wrap.titleSerious', 'wrap.title', { note: midiName(Math.round(learning.progress.home)), lessons, stars }) : sk('wrap.titleNoNoteSerious', 'wrap.titleNoNote', { lessons, stars }) }}</h2>
            <div class="space-y-2" data-wrap-goal>
                <p :id="'wrap-goal-' + $.uid" class="font-semibold text-cream">{{ t('wrap.goal') }}</p>
                <div class="seg flex sm:w-fit" role="radiogroup" :aria-labelledby="'wrap-goal-' + $.uid">
                    <button v-for="g in GOALS" :key="g" type="button" role="radio" :aria-checked="chosenGoal === g ? 'true' : 'false'" class="seg-option flex-1 sm:flex-none" :class="suggested === g ? 'ring-2 ring-inset ring-accent/60' : ''" :data-suggested="suggested === g ? '' : null" @click="chooseGoal(g)">{{ t('wrap.days', { count: g }) }}</button>
                </div>
                <p v-if="goal" class="text-sm text-muted" role="status">{{ t('wrap.goalKept', { count: goal }) }}</p>
                <p v-else-if="suggested" class="text-sm text-muted">{{ t('wrap.goalSuggested', { count: suggested }) }}</p>
            </div>
            <div v-if="ask" class="space-y-3 border-t border-line pt-4" data-wrap-keep :data-ask="ask">
                <!-- Installing is said for what it is, never as a way to keep what's here: on Safari an added app starts fresh, so a guest there is asked for the account. -->
                <p class="font-semibold text-cream">{{ ask === 'account' ? sk('wrap.accountSerious', 'wrap.account', { stars }) : t('wrap.installApp') }}</p>
                <p v-if="ask === 'iphone'" class="text-sm text-muted">{{ t('install.iphone') }} {{ t('install.signInThere') }}</p>
                <p v-else-if="ask === 'mac'" class="text-sm text-muted">{{ t('install.mac') }} {{ t('install.signInThere') }}</p>
                <div class="flex flex-wrap items-center gap-3">
                    <button v-if="ask === 'prompt'" type="button" class="btn-primary" data-wrap-install @click="installNow"><Icon name="download" :size="16" /> {{ t('install.button') }}</button>
                    <button v-else-if="ask === 'account'" type="button" class="btn-primary" data-wrap-account @click="makeAccount">{{ t('wrap.makeAccount') }}</button>
                    <button type="button" class="tap-44 btn-ghost btn-sm" data-wrap-not-now @click="notNow">{{ t('install.later') }}</button>
                </div>
            </div>
        </section>`,
    computed: {
        /** The goal chosen: the one tapped, here or before on this device; null until then. */
        chosenGoal() {
            return this.goal ?? (funnelRecord().firsts.f_goal ? learning.progress.goal : null);
        },
        /** Until one is chosen, 3 is suggested: an outline, never shown as chosen (onboarding review 2, 15). */
        suggested() {
            return this.chosenGoal === null ? SUGGESTED : null;
        },
        ask() {
            const ask = this.answered ? null : keepAskFor();
            return ask && askDue(ledgerName(ask)) ? ask : null;
        },
    },
    watch: {
        ask: {
            handler(ask) {
                if (!ask) return;
                askShown(ledgerName(ask));
                askSeen(ledgerName(ask)); // today's one ask: Home's cards wait for another day
            },
            immediate: true,
        },
    },
    mounted() {
        askShown('goal');
    },
    methods: {
        async chooseGoal(goal) {
            this.goal = goal;
            await setGoal(goal);
        },
        async installNow() {
            const prompt = install.prompt;
            if (!prompt) return;
            install.prompt = null;
            prompt.prompt();
            const choice = await prompt.userChoice.catch(() => null);
            if (choice?.outcome === 'accepted') {
                askYes('install');
                first('f_install');
            }
            this.answered = true;
        },
        makeAccount() {
            askYes('account');
            navigate('/signin?mode=signup&for=progress');
        },
        notNow() {
            askNotNow(ledgerName(this.ask));
            this.answered = true;
        },
    },
};

/**
 * Back on another day of the first week (onboarding step 7), the reminders offer, where install is
 * done or can't be (Home's install card offers install itself): "Want a nudge on your practice
 * days?". A guest makes an account first (reminders need one), signed in it's the reminders screen.
 * Not now as every ask's (js/asks.js): 14 days, and never after three.
 */
export const RemindAsk = {
    data: () => ({ due: false, answered: false }),
    template: `
        <section v-if="shown" class="flex flex-wrap items-center gap-4 rounded-3xl border border-edge bg-panel p-5" aria-labelledby="remind-ask-title" data-remind-ask data-ask>
            <div class="min-w-52 flex-1">
                <h2 id="remind-ask-title" class="font-bold text-cream">{{ t('wrap.remindTitle') }}</h2>
                <p class="text-sm text-muted">{{ t('wrap.remindWhy') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn-primary" data-remind-yes @click="yes">{{ t('reminders.ask') }}</button>
                <button type="button" class="tap-44 btn-ghost btn-sm" data-remind-not-now @click="notNow">{{ t('install.later') }}</button>
            </div>
        </section>`,
    computed: {
        /**
         * Home's install card asks first (one ask a screen): while it can show (Chrome's offer, an
         * iPhone or Safari on a Mac, not installed, not put off), this waits. Chrome's offer can
         * arrive after the page opens, so it's watched.
         */
        installAsks() {
            return !installed() && askDue('install') && !!(install.prompt || iphone() || macSafari());
        },
        shown() {
            return this.due && !this.answered && !this.installAsks;
        },
    },
    watch: {
        shown: {
            handler(shown) {
                if (!shown) return;
                askShown('remind');
                askSeen('remind');
            },
            immediate: true,
        },
    },
    async created() {
        const days = daysSinceArrival();
        if (days === null || days < 1 || days > 6 || !pushSupported() || !askDue('remind')) return;
        if (account.me) {
            const reminder = await loadReminder().catch(() => null);
            if (reminder?.thisDevice || reminder?.enabled) return;
        }
        this.due = true;
    },
    methods: {
        yes() {
            navigate(account.me ? '/settings/reminders' : '/signin?mode=signup&for=reminders&next=/settings/reminders');
        },
        notNow() {
            askNotNow('remind');
            this.answered = true;
        },
    },
};

const WEEK_SEEN = 'stackapella.firstWeekSeen'; // the first week's card, put away

/**
 * Your first week (docs/onboarding-review.md, step 11): on Home from the seventh day of the device's
 * first week (until the tenth), what the week gave: "Your first week: 3 days sung, 11 lessons, note
 * A3, range G2 to D4." Plain facts in both skins (game mode's rewards stay where they are). Put away
 * with Got it, for good.
 *
 * keep1 (docs/account-nudges-plan.md, N1 and section 6): for a guest with something to keep (3
 * lessons with stars, or a song recorded) on days 7 to 13, the card is the experiment's exposure.
 * Both arms see it until day 13; arm b adds the keep row ("Keep this week: your note, 86 stars and
 * level 3, on any device. It's free.") with Make a free account and Not now, which puts the whole
 * card away (in place of Got it). Within the ask ledger's caps; never in week one.
 */
export const FirstWeek = {
    props: { songs: { type: Boolean, default: false } }, // a song recorded on this device
    data: () => ({ seen: local.get(WEEK_SEEN) === '1' }),
    setup: () => ({ learning, sk }),
    template: `
        <section v-if="shown" class="rounded-3xl border border-edge bg-panel p-5" aria-labelledby="first-week-title" data-first-week>
            <div class="flex flex-wrap items-center gap-4">
                <div class="min-w-52 flex-1">
                    <h2 id="first-week-title" class="font-bold text-cream">{{ t('wrap.week.title') }}</h2>
                    <p class="text-sm text-cream">{{ facts }}</p>
                </div>
                <button v-if="!keep" type="button" class="tap-44 btn-ghost btn-sm" data-first-week-done @click="done">{{ t('wrap.week.done') }}</button>
            </div>
            <div v-if="keep" class="mt-4 space-y-3 border-t border-line pt-4" data-first-week-keep>
                <p class="text-cream">{{ keepLine }}</p>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-secondary" data-first-week-account @click="makeAccount">{{ t('wrap.makeAccount') }}</button>
                    <button type="button" class="tap-44 btn-ghost btn-sm" data-first-week-not-now @click="notNow">{{ t('install.later') }}</button>
                </div>
            </div>
        </section>`,
    computed: {
        days: () => daysSinceArrival(),
        /** Lessons with stars since this device arrived, as the week's facts count them. */
        weekLessons() {
            const since = Date.parse(funnelRecord().arrived);
            return Object.values(learning.progress.lessons ?? {}).filter((l) => l?.stars && l.at >= since).length;
        },
        facts() {
            if (this.seen || this.days === null) return null;
            const arrived = funnelRecord().arrived;
            const sung = Object.keys(learning.progress.practice ?? {}).filter((day) => day >= arrived && (learning.progress.practice[day] ?? 0) > 0).length;
            if (!sung && !this.weekLessons) return null;
            const home = learning.progress.home;
            const range = learning.progress.range;
            const parts = [t('wrap.week.days', { count: sung }), t('wrap.week.lessons', { count: this.weekLessons })];
            if (home !== null) parts.push(t('wrap.week.note', { note: midiName(Math.round(home)) }));
            if (range && range.high > range.low) parts.push(t('wrap.week.range', { low: midiName(range.low), high: midiName(range.high) }));
            return parts.join(', ') + '.';
        },
        /** Lessons with stars, ever: three of them (or a song) is something worth keeping. */
        starred() {
            return Object.values(learning.progress.lessons ?? {}).filter((l) => l?.stars);
        },
        /** keep1's arm, drawn only for a guest it's for (at exposure): 'a', 'b', or null. */
        arm() {
            if (account.me || !account.loaded || this.seen || !this.facts || this.days < 7 || this.days > 13) return null;
            if (this.starred.length < 3 && !this.songs) return null;
            if (!askDue('acct_week')) return null;
            const { arm, ready } = experiment('keep1');
            return ready ? arm : null;
        },
        shown() {
            return !!this.facts && ((this.days >= 6 && this.days <= 9) || !!this.arm);
        },
        keep() {
            return this.arm === 'b';
        },
        /** "Keep this week: your note, 86 stars and level 3, on any device. It's free." (serious: lessons, no stars). */
        keepLine() {
            const what = [];
            if (learning.progress.home !== null) what.push(t('wrap.week.keepNote'));
            if (this.starred.length) {
                if (!game()) what.push(t('wrap.week.keepLessons', { count: this.starred.length }));
                else {
                    what.push(t('wrap.week.keepStars', { count: this.starred.reduce((sum, l) => sum + l.stars, 0) }));
                    what.push(t('wrap.week.keepLevel', { level: levelFor(learning.progress.xp?.total ?? 0).level }));
                }
            }
            if (this.songs) what.push(t('wrap.week.keepSongs'));
            return t('wrap.week.keep', { what: formatList(what) });
        },
    },
    watch: {
        keep: {
            handler(keep) {
                if (!keep) return;
                askShown('acct_week');
                askSeen('acct_week');
            },
            immediate: true,
        },
    },
    methods: {
        done() {
            local.set(WEEK_SEEN, '1');
            this.seen = true;
        },
        makeAccount() {
            askYes('acct_week');
            navigate(signUpFor('week', '/'));
        },
        notNow() {
            askNot('acct_week');
            askNotNow('acct_week');
            this.done();
        },
    },
};
