// The install card (docs/go-to-market-plan.md, docs/operations-plan.md "Keeping songs safe"): an
// installed app keeps its songs better (Safari can clear a website's storage after a week unused)
// and has no browser bar in the way. Chrome and Android offer it with one tap; on an iPhone the
// card says how (Share, then Add to Home Screen). Before anything is recorded it's a plain offer,
// and "Not now" ends it. Once there are songs it says they're only on this device, and comes back
// at most once a week.

import { reactive } from 'vue';
import { askShown, askYes, first } from '../funnel.js';
import { t } from '../i18n.js';
import { local } from '../storage.js';
import { Icon } from './icon.js';

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

const WEEK = 7 * 24 * 3600 * 1000;
export const installed = () => window.matchMedia?.('(display-mode: standalone)').matches || navigator.standalone === true;
// An iPad says it's a Mac; its touch screen gives it away.
export const iphone = () => /iPhone|iPad/.test(navigator.userAgent) || (/Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints > 1);
// Safari on a Mac installs from its File menu (Add to Dock); it fires no install prompt.
export const macSafari = () => /Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints <= 1 && /Safari\//.test(navigator.userAgent) && !/Chrome|Chromium|Edg|Firefox|OPR/.test(navigator.userAgent);

export const InstallCard = {
    components: { Icon },
    props: { songs: { type: Boolean, default: false } },
    setup: () => ({ install }),
    data: () => ({ offerDismissed: local.get('stackapella.installDismissed') === '1', safeDismissedAt: Number(local.get('stackapella.safeCardDismissed') || 0), iphone: iphone(), mac: macSafari(), installed: installed() }),
    template: `
        <section v-if="shown" class="card flex flex-wrap items-center gap-4 p-5" aria-labelledby="install-title" data-install>
            <Icon :name="songs ? 'lock' : 'download'" :size="24" class="text-part-4" />
            <div class="min-w-52 flex-1">
                <h2 id="install-title" class="font-bold text-cream">{{ songs ? t('install.safeTitle') : t('install.title') }}</h2>
                <p class="text-sm text-muted">{{ songs ? t('install.safe') : t('install.why') }} <template v-if="iphone && !install.prompt">{{ t('install.iphone') }}</template><template v-else-if="mac && !install.prompt">{{ t('install.mac') }}</template></p>
            </div>
            <div class="flex items-center gap-3">
                <button v-if="install.prompt" type="button" class="btn-primary" @click="ask">{{ t('install.button') }}</button>
                <button type="button" class="tap-44 btn-ghost btn-sm" @click="dismiss">{{ t('install.later') }}</button>
            </div>
        </section>`,
    computed: {
        dismissed() {
            return this.songs ? Date.now() - this.safeDismissedAt < WEEK : this.offerDismissed;
        },
        shown() {
            return !this.installed && !this.dismissed && !this.askedToday && !!(install.prompt || this.iphone || this.mac);
        },
        /** The session wrap offered install today: one ask a session, so this card waits for another day. */
        askedToday: () => local.get('stackapella.installAskedOn') === new Date().toDateString(),
    },
    watch: {
        // The funnel: the install ask, counted the first time this device sees it.
        shown: {
            handler(shown) {
                if (shown) askShown('install');
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
        dismiss() {
            if (this.songs) {
                this.safeDismissedAt = Date.now();
                local.set('stackapella.safeCardDismissed', String(this.safeDismissedAt));
            } else {
                local.set('stackapella.installDismissed', '1');
                this.offerDismissed = true;
            }
        },
    },
};
