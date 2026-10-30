// Start fresh on this device (/start-fresh, as /reset is the password reset's; Hillel: "a link I can use to easily clear everything in the app
// when I'm testing on my phone"): not linked from anywhere. The page is its own confirm: what goes,
// counted, then Start fresh. It signs out here, deletes everything this device keeps (the songs,
// progress, settings, look, the app's files and its service worker) and opens the website as a
// brand-new visitor. Signed in or not, and only ever this device: the account is never touched.

import { PageHeader } from '../components/page.js';
import { t } from '../i18n.js';
import { navigate } from '../router.js';
import { publicSiteUrl } from '../hosts.js';
import { account, signOut } from '../stores/account.js';
import { deviceSummary, startFresh } from '../device.js';
import { Notice } from '../components/form.js';

export const ResetView = {
    components: { Notice, PageHeader },
    data: () => ({ summary: null, busy: false, failed: false }),
    setup: () => ({ account }),
    template: `
        <div class="mx-auto flex min-h-dvh max-w-xl flex-col px-4 pb-16 pt-[max(0.5rem,env(safe-area-inset-top))] md:px-8" data-reset>
            <PageHeader :title="t('reset.title')" back="/" />
            <div class="mt-4 space-y-5">
                <p class="text-cream">{{ t('reset.body') }}</p>
                <dl class="space-y-2 rounded-2xl border border-line bg-panel p-4 text-sm">
                    <div v-for="row in rows" :key="row.label" class="flex items-center justify-between gap-4"><dt class="text-muted">{{ row.label }}</dt><dd class="font-bold tabular-nums text-cream">{{ row.value }}</dd></div>
                </dl>
                <p class="text-sm text-muted">{{ account.me ? t('reset.signedIn', { email: account.me.email }) : t('reset.signedOut') }}</p>
                <Notice v-if="failed" tone="bad">{{ t('reset.failed') }}</Notice>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" class="btn-secondary" :disabled="busy" data-autofocus @click="navigate('/')">{{ t('reset.keep') }}</button>
                    <button type="button" class="btn-danger" :disabled="busy" data-reset-go @click="go">{{ busy ? t('reset.going') : t('reset.go') }}</button>
                </div>
            </div>
        </div>`,
    computed: {
        rows() {
            const s = this.summary;
            return [
                { label: t('settings.wipe.songs'), value: s ? s.songs : '' },
                ...(s?.courseTakes ? [{ label: t('settings.wipe.courseTakes'), value: s.courseTakes }] : []),
                ...(s?.drafts ? [{ label: t('settings.wipe.drafts'), value: s.drafts }] : []),
                { label: t('reset.also'), value: t('reset.alsoWhat') },
            ];
        },
    },
    async created() {
        this.summary = await deviceSummary().catch((error) => {
            console.warn('What this device keeps could not be counted', error);
            return null;
        });
    },
    methods: {
        navigate,
        async go() {
            this.busy = true;
            this.failed = false;
            try {
                // Signed out here first, so the account can't bring anything straight back: a
                // sign-out that fails (offline) stops it, rather than leave you signed in.
                if (account.me) await signOut();
                await startFresh();
                location.replace(publicSiteUrl());
            } catch (error) {
                console.error('This device was not cleared', error);
                this.failed = true;
                this.busy = false;
            }
        },
    },
};
