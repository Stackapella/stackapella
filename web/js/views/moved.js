// /moved: songs arriving from the old address (docs/app-subdomain-plan.md, "Old installs"; js/move.js).
// Opened by the old address's "Stackapella has moved" page, it says it's ready, keeps each batch of
// records it's sent (from Stackapella's own hosts only, and only from the window that opened it),
// and answers each; a moving file (from an iPhone's home-screen app) can be opened here too.
// Nothing already on this address is overwritten.

import * as db from '../db.js';
import { t } from '../i18n.js';
import { keepLocal, keepRecords, MESSAGE, trustedOrigin, unpackFile } from '../move.js';
import { Notice } from '../components/form.js';
import { Icon, Logo } from '../components/icon.js';

export const MovedView = {
    components: { Icon, Logo, Notice },
    data: () => ({ stage: window.opener ? 'waiting' : 'file', received: 0, added: 0, error: null }),
    template: `
        <div class="mx-auto flex min-h-dvh max-w-xl flex-col justify-center gap-6 px-6 py-16">
            <Logo />
            <section class="card space-y-4 p-6 md:p-8" aria-live="polite">
                <template v-if="stage === 'waiting' || stage === 'receiving'">
                    <h1 class="text-3xl font-extrabold tracking-tight text-cream">{{ t('moved.arriving') }}</h1>
                    <p class="text-muted" role="status">{{ received ? t('moved.received', { count: received }) : t('moved.waiting') }}</p>
                </template>
                <template v-else-if="stage === 'done'">
                    <h1 class="text-3xl font-extrabold tracking-tight text-cream">{{ t('moved.here') }}</h1>
                    <p class="text-muted">{{ added ? t('moved.hereBody') : t('moved.nothingNew') }}</p>
                    <p class="text-sm text-muted">{{ t('moved.install') }}</p>
                    <a href="/" class="btn-primary" @click.prevent="open">{{ t('moved.open') }} <Icon name="arrow" :size="18" /></a>
                </template>
                <template v-else>
                    <h1 class="text-3xl font-extrabold tracking-tight text-cream">{{ t('moved.fileTitle') }}</h1>
                    <p class="text-muted">{{ t('moved.fileBody') }}</p>
                    <Notice v-if="error" tone="bad">{{ error }}</Notice>
                    <label class="btn-primary cursor-pointer">{{ t('moved.chooseFile') }}<input type="file" class="sr-only" accept=".stackapella,application/octet-stream" @change="fromFile" /></label>
                    <a href="/" class="btn-ghost" @click.prevent="open">{{ t('moved.skip') }}</a>
                </template>
            </section>
        </div>`,
    mounted() {
        if (!window.opener) return;
        this.onMessage = (event) => this.receive(event);
        window.addEventListener('message', this.onMessage);
        // Ready: the opener sends the first batch. Nothing private in this message, so any target.
        window.opener.postMessage({ type: MESSAGE.ready }, '*');
    },
    unmounted() {
        if (this.onMessage) window.removeEventListener('message', this.onMessage);
    },
    methods: {
        async receive(event) {
            if (event.source !== window.opener || !trustedOrigin(event.origin) || event.data?.type !== MESSAGE.batch) return;
            const reply = (message) => event.source.postMessage(message, event.origin);
            try {
                this.stage = 'receiving';
                const records = Array.isArray(event.data.records) ? event.data.records : [];
                this.added += await keepRecords(db, records);
                this.received += records.length;
                if (!event.data.done) return reply({ type: MESSAGE.ack });
                keepLocal(event.data.local);
                reply({ type: MESSAGE.done, added: this.added });
                this.finish();
            } catch (error) {
                console.error('Moving songs failed', error);
                this.stage = 'file';
                this.error = t('moved.failed');
            }
        },
        async fromFile(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            this.error = null;
            try {
                const { records, local } = await unpackFile(file);
                this.stage = 'receiving';
                this.added = await keepRecords(db, records);
                this.received = records.length;
                keepLocal(local);
                this.finish();
            } catch (error) {
                console.warn('Not a moving file', error);
                this.stage = 'file';
                this.error = t('moved.notAFile');
            }
        },
        finish() {
            this.stage = 'done';
            window.stackapellaTheme?.apply(); // the look chosen on the old address, now kept here too
        },
        /** Opens the app afresh, so every store reads what arrived. */
        open() {
            location.assign('/');
        },
    },
};
