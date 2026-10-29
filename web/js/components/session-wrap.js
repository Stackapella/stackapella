// The first session's wrap (docs/onboarding-review.md, session 1 step 5; onboarding item 5): on lesson
// 3's end in the free course, on the day this device arrived, a card under the result (never a
// modal): what the session gave ("your note A3, 3 lessons, 8 stars"), the weekly goal chosen rather
// than given (3 looks chosen, nothing is kept until a tap), and exactly one keep-it ask for this
// device: install where it can (Chrome's one tap, the iPhone's Share then Add to Home Screen, the
// Mac's Add to Dock), else an account. "Not now" is a real answer: kept on this device, the ask
// comes back no sooner than three days later, and after three it stops asking.

import { navigate } from '../router.js';
import { t } from '../i18n.js';
import { midiName } from '../dsp.js';
import { local } from '../storage.js';
import { account } from '../stores/account.js';
import { learning, setGoal } from '../stores/learning.js';
import { askShown, askYes, daysSinceArrival, first, funnelRecord } from '../funnel.js';
import { install, installed, iphone, macSafari } from './install.js';
import { loadReminder, pushSupported } from '../reminders.js';
import { Icon } from './icon.js';
import { sk } from '../skin.js';

const GOALS = [2, 3, 5];
const SUGGESTED = 3;
const NOT_NOW = 'stackapella.keepAsk'; // { nots, at }: how many times the ask was put off, and the last time
const AGAIN_AFTER = 3 * 24 * 3600 * 1000;
const MOST_NOT_NOWS = 3;

function notNows() {
    try {
        const kept = JSON.parse(local.get(NOT_NOW) || 'null');
        return kept && typeof kept === 'object' ? { nots: kept.nots ?? 0, at: kept.at ?? 0 } : { nots: 0, at: 0 };
    } catch (error) {
        console.info('The keep-it answer was unreadable', error?.message ?? error);
        return { nots: 0, at: 0 };
    }
}

/** Whether the keep-it ask may show now: not after three Not nows, nor within three days of one. */
export function keepAskDue(kept = notNows(), now = Date.now()) {
    return kept.nots < MOST_NOT_NOWS && (!kept.nots || now - kept.at >= AGAIN_AFTER);
}

/** Which keep-it ask fits this device: 'prompt' (one tap), 'iphone', 'mac', 'account', or null. */
export function keepAskFor({ isInstalled = installed(), prompt = !!install.prompt, isIphone = iphone(), isMac = macSafari(), signedIn = !!account.me } = {}) {
    if (!isInstalled && prompt) return 'prompt';
    if (!isInstalled && isIphone) return 'iphone';
    if (!isInstalled && isMac) return 'mac';
    return signedIn ? null : 'account';
}

export const SessionWrap = {
    components: { Icon },
    props: { lessons: { type: Number, required: true }, stars: { type: Number, required: true } },
    data: () => ({ goal: null, due: keepAskDue(), answered: false }),
    setup: () => ({ GOALS, SUGGESTED, learning, midiName, sk }),
    template: `
        <section class="w-full space-y-5 rounded-3xl border border-edge bg-panel p-5 text-start" aria-labelledby="wrap-title" data-session-wrap>
            <h2 id="wrap-title" class="text-lg font-bold text-cream">{{ learning.progress.home !== null ? sk('wrap.titleSerious', 'wrap.title', { note: midiName(Math.round(learning.progress.home)), lessons, stars }) : sk('wrap.titleNoNoteSerious', 'wrap.titleNoNote', { lessons, stars }) }}</h2>
            <div class="space-y-2" data-wrap-goal>
                <p :id="'wrap-goal-' + $.uid" class="font-semibold text-cream">{{ t('wrap.goal') }}</p>
                <div class="seg flex sm:w-fit" role="radiogroup" :aria-labelledby="'wrap-goal-' + $.uid">
                    <button v-for="g in GOALS" :key="g" type="button" role="radio" :aria-checked="shownGoal === g ? 'true' : 'false'" class="seg-option flex-1 sm:flex-none" @click="chooseGoal(g)">{{ t('wrap.days', { count: g }) }}</button>
                </div>
                <p v-if="goal" class="text-sm text-muted" role="status">{{ t('wrap.goalKept', { count: goal }) }}</p>
            </div>
            <div v-if="ask" class="space-y-3 border-t border-line pt-4" data-wrap-keep :data-ask="ask">
                <p class="font-semibold text-cream">{{ ask === 'account' ? sk('wrap.accountSerious', 'wrap.account', { stars }) : sk('wrap.installSerious', 'wrap.install', { stars }) }}</p>
                <p v-if="ask === 'iphone'" class="text-sm text-muted">{{ t('install.iphone') }}</p>
                <p v-else-if="ask === 'mac'" class="text-sm text-muted">{{ t('install.mac') }}</p>
                <div class="flex flex-wrap items-center gap-3">
                    <button v-if="ask === 'prompt'" type="button" class="btn-primary" data-wrap-install @click="installNow"><Icon name="download" :size="16" /> {{ t('install.button') }}</button>
                    <button v-else-if="ask === 'account'" type="button" class="btn-primary" data-wrap-account @click="makeAccount">{{ t('wrap.makeAccount') }}</button>
                    <button type="button" class="tap-44 btn-ghost btn-sm" data-wrap-not-now @click="notNow">{{ t('install.later') }}</button>
                </div>
            </div>
        </section>`,
    computed: {
        /** The goal shown chosen: the one tapped (here or before on this device), else 3 (shown, never kept until a tap). */
        shownGoal() {
            return this.goal ?? (funnelRecord().firsts.f_goal ? learning.progress.goal : SUGGESTED);
        },
        ask() {
            return this.due && !this.answered ? keepAskFor() : null;
        },
    },
    watch: {
        ask: {
            handler(ask) {
                if (ask === 'account') askShown('account');
                else if (ask) askShown('install');
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
            const kept = notNows();
            local.set(NOT_NOW, JSON.stringify({ nots: kept.nots + 1, at: Date.now() }));
            this.answered = true;
        },
    },
};

const REMIND_NOT_NOW = 'stackapella.remindAsk'; // as the keep-it ask's: how many times put off, and the last time

/**
 * Back on another day of the first week (onboarding step 7), the reminders offer, where install is
 * done or can't be (Home's install card offers install itself): "Want a nudge on your practice
 * days?". A guest makes an account first (reminders need one), signed in it's the reminders screen.
 * Not now as the wrap's: three days, and never after three.
 */
export const RemindAsk = {
    data: () => ({ due: false, answered: false }),
    template: `
        <section v-if="shown" class="flex flex-wrap items-center gap-4 rounded-3xl border border-edge bg-panel p-5" aria-labelledby="remind-ask-title" data-remind-ask>
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
            return !installed() && local.get('stackapella.installDismissed') !== '1' && !!(install.prompt || iphone() || macSafari());
        },
        shown() {
            return this.due && !this.answered && !this.installAsks;
        },
    },
    watch: {
        shown: {
            handler(shown) {
                if (shown) askShown('remind');
            },
            immediate: true,
        },
    },
    async created() {
        const days = daysSinceArrival();
        if (days === null || days < 1 || days > 6 || !pushSupported() || !keepAskDue(this.kept())) return;
        if (account.me) {
            const reminder = await loadReminder().catch(() => null);
            if (reminder?.thisDevice || reminder?.enabled) return;
        }
        this.due = true;
    },
    methods: {
        kept() {
            try {
                const kept = JSON.parse(local.get(REMIND_NOT_NOW) || 'null');
                return kept && typeof kept === 'object' ? { nots: kept.nots ?? 0, at: kept.at ?? 0 } : { nots: 0, at: 0 };
            } catch (error) {
                console.info('The reminders answer was unreadable', error?.message ?? error);
                return { nots: 0, at: 0 };
            }
        },
        yes() {
            navigate(account.me ? '/settings/reminders' : '/signin?mode=signup&for=reminders&next=/settings/reminders');
        },
        notNow() {
            const kept = this.kept();
            local.set(REMIND_NOT_NOW, JSON.stringify({ nots: kept.nots + 1, at: Date.now() }));
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
 */
export const FirstWeek = {
    data: () => ({ seen: local.get(WEEK_SEEN) === '1' }),
    setup: () => ({ learning }),
    template: `
        <section v-if="facts" class="flex flex-wrap items-center gap-4 rounded-3xl border border-edge bg-panel p-5" aria-labelledby="first-week-title" data-first-week>
            <div class="min-w-52 flex-1">
                <h2 id="first-week-title" class="font-bold text-cream">{{ t('wrap.week.title') }}</h2>
                <p class="text-sm text-cream">{{ facts }}</p>
            </div>
            <button type="button" class="tap-44 btn-ghost btn-sm" data-first-week-done @click="done">{{ t('wrap.week.done') }}</button>
        </section>`,
    computed: {
        facts() {
            const days = daysSinceArrival();
            if (this.seen || days === null || days < 6 || days > 9) return null;
            const arrived = funnelRecord().arrived;
            const sung = Object.keys(learning.progress.practice ?? {}).filter((day) => day >= arrived && (learning.progress.practice[day] ?? 0) > 0).length;
            const since = Date.parse(arrived);
            const lessons = Object.values(learning.progress.lessons ?? {}).filter((l) => l?.stars && l.at >= since).length;
            if (!sung && !lessons) return null;
            const home = learning.progress.home;
            const range = learning.progress.range;
            const parts = [t('wrap.week.days', { count: sung }), t('wrap.week.lessons', { count: lessons })];
            if (home !== null) parts.push(t('wrap.week.note', { note: midiName(Math.round(home)) }));
            if (range && range.high > range.low) parts.push(t('wrap.week.range', { low: midiName(range.low), high: midiName(range.high) }));
            return parts.join(', ') + '.';
        },
    },
    methods: {
        done() {
            local.set(WEEK_SEEN, '1');
            this.seen = true;
        },
    },
};
