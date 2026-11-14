// "A new version is ready" (js/version.js), for everyone: at the top centre with the toasts, held
// while someone sings, records or plays, or is part way through a lesson or a workout (until its end
// screen), and Refresh sends what's waiting first. (The build badge
// is gone, Hillel 2026-10-27: the build goes with a message from Contact instead.)

import { engine } from '../audio.js';
import { formatDate, t } from '../i18n.js';
import { account } from '../stores/account.js';
import { cloud, syncNow } from '../stores/cloud.js';
import { sessionBusy } from '../stores/session-live.js';
import { route } from '../router.js';
import { version } from '../version.js';
import { REFRESH_MS, reloadIntoNew } from '../sw-update.js';
import { Icon } from './icon.js';
import { toast } from './ui.js';

const SAVE_FIRST_MS = 8000; // Refresh waits this long, at most, for the cloud save to finish

/** What Refresh says while it works: Saving first only when a signed-in account has changes waiting, else Updating. */
export function refreshStage({ signedIn, pending }) {
    return signedIn && pending > 0 ? 'saving' : 'updating';
}

/** Whether reloading now would cut into singing: recording, counting in, or anything playing. */
const singing = () => sessionBusy() || !!engine.playing;

/** Whether a lesson or a workout is under way: a step showing, not yet its end screen (or the map). */
const practising = () => (route.name === 'learn' && !document.querySelector('[data-lesson-end]')) || (route.name === 'workout' && !document.querySelector('[data-workout-end]'));

/**
 * "A new version of Stackapella is ready", with Refresh and Not now. Held while someone sings or is
 * part way through a lesson or a workout (and shown when they stop); Not now hides it until the next
 * new version.
 */
export const NewVersion = {
    components: { Icon },
    setup: () => ({ version }),
    data: () => ({ busy: false, dismissed: null, refreshing: null }), // refreshing: 'saving' (a real save first) or 'updating'
    template: `
        <div v-if="shown" class="pointer-events-auto flex w-full max-w-lg flex-wrap items-center gap-3 rounded-2xl border border-line bg-raised px-4 py-3 text-sm text-cream shadow-xl" role="status" data-new-version>
            <Icon name="download" :size="20" class="shrink-0 text-accent" />
            <span class="min-w-40 flex-1 font-semibold">{{ t('version.ready') }}</span>
            <span class="ms-auto flex items-center gap-2">
                <button type="button" class="btn-ghost btn-sm" @click="dismissed = version.now ?? 'found'">{{ t('version.notNow') }}</button>
                <button type="button" class="btn-primary btn-sm" :disabled="!!refreshing" @click="refresh">{{ refreshing === 'saving' ? t('version.saving') : refreshing === 'updating' ? t('version.updating') : t('version.refresh') }}</button>
            </span>
        </div>`,
    computed: {
        shown() {
            return this.version.ready && !this.busy && this.dismissed !== (this.version.now ?? 'found');
        },
    },
    mounted() {
        // Singing is read each second (the engine's state isn't reactive); this only shows or hides the prompt.
        this.busy = singing() || practising();
        this.watching = setInterval(() => (this.busy = singing() || practising()), 1000);
    },
    unmounted() {
        clearInterval(this.watching);
    },
    methods: {
        /** Sends what's waiting for the account (a few seconds at most), then reloads. */
        async refresh() {
            // Saving first only when there's something to save (Hillel saw "Saving first" with nothing changed); then Updating.
            this.refreshing = refreshStage({ signedIn: !!account.me, pending: cloud.pending });
            if (this.refreshing === 'saving') {
                await Promise.race([syncNow().catch((error) => console.warn('Saving before the refresh failed', error)), new Promise((resolve) => setTimeout(resolve, SAVE_FIRST_MS))]);
                this.refreshing = 'updating';
            }
            // Into the new build: its waiting worker takes over first if it's ready in a few seconds, else the reload comes
            // from the network (never the old build again, never an open-ended wait: js/sw-update.js REFRESH_MS).
            await reloadIntoNew({ ms: REFRESH_MS });
        },
    },
};
