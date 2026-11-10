// The install card (docs/go-to-market-plan.md, docs/operations-plan.md "Keeping songs safe"): an
// installed app has no browser bar in the way, and where Chrome installs it (one tap, on Android and
// desktops) its storage is kept better. Before anything is recorded it's a plain offer, and "Not
// now" ends it. Once there are songs it says they're only on this device, and comes back as the ask
// ledger allows (js/asks.js: 14 days after any Not now, one ask a day).
// Safari is different (docs/account-nudges-plan.md, F1): an app added to the Home Screen or the
// Dock keeps its own storage, apart from Safari's, so it starts fresh and keeps nothing that's here.
// There the card never promises that installing keeps anything: to someone signed in it's the plain
// offer (sign in there and the account comes along); to a guest with songs here, the ask is a free
// account; a guest with nothing recorded isn't asked at all (the session wrap asks for the account).

import { reactive } from 'vue';
import { askNot, askShown, askYes, daysSinceArrival, first } from '../funnel.js';
import { formatList, t } from '../i18n.js';
import { navigate } from '../router.js';
import { askDue, askNotNow, askSeen, daysAway } from '../asks.js';
import { learning } from '../stores/learning.js';
import { account } from '../stores/account.js';
import { Icon } from './icon.js';
import { deviceOf } from '../mic-help.js';

/** Chrome's kept install offer (the session wrap asks with it too). */
export const install = reactive({ prompt: null });

/** Chrome's offer to install, kept for the card (call once, at start). */
export function listenForInstall() {
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        install.prompt = event;
    });
    window.addEventListener('appinstalled', () => {
        install.prompt = null;
        first('f_install'); // the funnel: installed on this device
    });
}

export const installed = () => window.matchMedia?.('(display-mode: standalone)').matches || navigator.standalone === true;
// An iPad says it's a Mac; its touch screen gives it away.
export const iphone = () => /iPhone|iPad/.test(navigator.userAgent) || (/Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints > 1);
// Safari on a Mac installs from its File menu (Add to Dock); it fires no install prompt.
export const macSafari = () => /Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints <= 1 && /Safari\//.test(navigator.userAgent) && !/Chrome|Chromium|Edg|Firefox|OPR/.test(navigator.userAgent);

/**
 * What the card is, if anything: `offer` (install, plainly), `songs` (they're only here: install),
 * `keep` (they're only here, and on Safari installing won't keep them: a free account), `account`
 * (N2, docs/account-nudges-plan.md: from day 7, a guest's songs where installing is done or can't
 * keep them, the iPhone's added app, Firefox, a desktop without the offer: a free account), or null.
 */
export function installForm({ safari, prompt, songs, signedIn, isInstalled = false, days = null }) {
    if (!isInstalled && safari && !prompt) return signedIn ? 'offer' : songs ? 'keep' : null;
    if (!isInstalled && prompt) return songs ? 'songs' : 'offer';
    return !signedIn && songs && days !== null && days >= 7 ? 'account' : null;
}

/**
 * N4, the Safari loss card (docs/account-nudges-plan.md): Safari (iPhone, iPad, Mac), not the added
 * app, storage not made persistent, a guest with something to keep, back after 4 or more days away
 * (Safari's 7-day rule is close). It takes the install card's place that visit.
 */
export function riskShows({ safari, isInstalled, persisted, signedIn, value, away }) {
    return safari && !isInstalled && persisted === false && !signedIn && value && away >= 4;
}

export const InstallCard = {
    components: { Icon },
    // songs: a song recorded only here; here: the guest's songs and takes on this device, for N2's line.
    props: { songs: { type: Boolean, default: false }, here: { type: Object, default: null } },
    setup: () => ({ install }),
    data: () => ({ answered: false, iphone: iphone(), mac: macSafari(), installed: installed(), persisted: null }),
    template: `
        <section v-if="shown" class="card flex flex-wrap items-center gap-4 p-5" aria-labelledby="install-title" data-install data-ask :data-form="form">
            <Icon :name="{ offer: 'download', risk: 'shield' }[form] ?? 'lock'" :size="24" class="text-part-4" />
            <div class="min-w-52 flex-1">
                <h2 id="install-title" class="font-bold text-cream">{{ form === 'offer' ? t('install.title') : form === 'risk' ? t('install.riskTitle') : t('install.safeTitle') }}</h2>
                <p v-if="form === 'risk'" class="text-sm text-muted">{{ t('install.risk', { what: riskWhat }) }}</p>
                <p v-else-if="form === 'keep'" class="text-sm text-muted">{{ t('install.keepSafari') }}</p>
                <p v-else-if="form === 'account'" class="text-sm text-muted">{{ t('install.keepHere', { songs: here.songs, takes: here.takes }) }}</p>
                <p v-else class="text-sm text-muted">{{ form === 'songs' ? t('install.safe') : safari && !install.prompt ? t('install.whySafari') : t('install.why') }} <template v-if="iphone && !install.prompt">{{ t('install.iphone') }}</template><template v-else-if="mac && !install.prompt">{{ t('install.mac') }}</template></p>
            </div>
            <div class="flex items-center gap-3">
                <button v-if="form === 'keep' || form === 'account' || form === 'risk'" type="button" class="btn-secondary" data-install-account @click="makeAccount">{{ t('wrap.makeAccount') }}</button>
                <button v-else-if="install.prompt" type="button" class="btn-secondary" @click="ask">{{ t('install.button') }}</button>
                <button type="button" class="tap-44 btn-ghost btn-sm" @click="dismiss">{{ t('install.later') }}</button>
            </div>
        </section>`,
    computed: {
        safari() {
            return this.iphone || this.mac;
        },
        /** What this device keeps (N2 and N4): lessons with stars and the guest's own songs. */
        starred: () => Object.values(learning.progress.lessons ?? {}).filter((l) => l?.stars).length,
        risk() {
            const value = this.starred >= 3 || (this.here?.songs ?? 0) > 0;
            return riskShows({ safari: this.safari, isInstalled: this.installed, persisted: this.persisted, signedIn: !!account.me, value, away: daysAway() }) && askDue('acct_risk');
        },
        /** "9 lessons and 2 songs" */
        riskWhat() {
            const what = [];
            if (this.starred) what.push(t('install.riskLessons', { count: this.starred }));
            if (this.here?.songs) what.push(t('install.riskSongs', { count: this.here.songs }));
            return formatList(what);
        },
        form() {
            if (this.risk) return 'risk';
            return installForm({ safari: this.safari, prompt: !!install.prompt, songs: this.songs && (this.here?.songs ?? 0) > 0, signedIn: !!account.me, isInstalled: this.installed, days: daysSinceArrival() });
        },
        /** The ask ledger's name: the account for the keep form; the songs form apart from the plain offer, which Later ends. */
        askName() {
            return { keep: 'account', account: 'acct_songs', risk: 'acct_risk', songs: 'install_songs' }[this.form] ?? 'install';
        },
        /** Within the ask ledger's caps (js/asks.js): one ask a day, the 14-day snooze, and so on. */
        shown() {
            // Never on a brand-new guest's first home (the coordinator, 2026-11-08: Start singing and nothing competing):
            // once a lesson is done or a song is kept here, or signed in.
            const begun = !!account.me || this.starred > 0 || (this.here?.songs ?? 0) > 0;
            return begun && !!this.form && !this.answered && askDue(this.askName);
        },
    },
    created() {
        // Whether Safari has promised to keep this site's storage (the loss card only shows when it hasn't).
        navigator.storage?.persisted?.().then((kept) => (this.persisted = kept), (error) => console.info('Storage persistence unknown', error?.message ?? error));
    },
    watch: {
        // The funnel: the install ask, counted the first time this device sees it.
        shown: {
            handler(shown) {
                if (!shown) return;
                askShown({ keep: 'account', account: 'acct_songs', risk: 'acct_risk' }[this.form] ?? 'install');
                askSeen(this.askName);
            },
            immediate: true,
        },
    },
    methods: {
        async ask() {
            const prompt = install.prompt;
            install.prompt = null;
            prompt.prompt();
            const choice = await prompt.userChoice.catch(() => null);
            if (choice?.outcome === 'accepted') {
                this.installed = true;
                askYes('install');
                first('f_install');
            }
        },
        makeAccount() {
            askYes({ account: 'acct_songs', risk: 'acct_risk' }[this.form] ?? 'account');
            navigate(`/signin?mode=signup&for=${this.form === 'risk' ? 'risk' : 'songs'}`);
        },
        /** Later: the plain offer is put away for good on this device; the others come back with the ledger. */
        dismiss() {
            askNot(this.askName);
            askNotNow(this.askName, { forGood: this.form === 'offer' });
            this.answered = true;
        },
    },
};

/**
 * Settings' "Install the app" (Hillel: always an option to install, as Home's card can be put away):
 * whenever this isn't the installed app, whatever happened to the card. Where the browser offered
 * it, Install opens its prompt; on an iPhone or a Mac's Safari, the steps; in an app's own browser
 * (Instagram, TikTok), open the page in a browser first; elsewhere, the browser's own menu.
 */
export const InstallRow = {
    components: { Icon },
    setup: () => ({ install }),
    data: () => ({ installed: installed(), iphone: iphone(), mac: macSafari(), inApp: deviceOf(navigator.userAgent, { touchPoints: navigator.maxTouchPoints ?? 0 }).inApp }),
    template: `
        <div v-if="!installed" class="card flex flex-wrap items-center justify-between gap-4 p-5" data-install-row>
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-cream">{{ t('settings.install.title') }}</p>
                <p class="text-sm text-muted" data-install-how>{{ how }}</p>
            </div>
            <button v-if="install.prompt && !inApp" type="button" class="btn-secondary btn-sm shrink-0" data-install-go @click="ask"><Icon name="download" :size="16" /> {{ t('install.button') }}</button>
        </div>`,
    computed: {
        /** What installing takes here, in a line. */
        how() {
            if (this.inApp) return t('settings.install.inApp', { app: this.inApp });
            if (install.prompt) return t('install.why');
            if (this.iphone) return t('install.iphone');
            if (this.mac) return t('install.mac');
            return t('settings.install.menu');
        },
    },
    methods: {
        async ask() {
            const prompt = install.prompt;
            install.prompt = null;
            prompt.prompt();
            const choice = await prompt.userChoice.catch(() => null);
            if (choice?.outcome === 'accepted') {
                this.installed = true;
                first('f_install');
            }
        },
    },
};
