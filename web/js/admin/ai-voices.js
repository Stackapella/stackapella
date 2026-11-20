// AI voices (docs/ai-voices-build-plan.md, phase 1; contract: admin.aiVoices.list and .withdraw). Each AI voice: the name
// its label carries, who it's based on, their consent (date, version, payment noted) or none yet (then its guides stay
// in Admin only), active or withdrawn, and the AI guides it sings now. Withdraw (an admin, in sudo) retires every one of
// them and purges them from the CDN; its styled sheet names exactly what goes (never a browser dialog). Shown whatever
// the ai_voices switch says: the switch decides only what the app and the site offer.

import { api } from '../api.js';
import { formatDate, formatList, t } from '../i18n.js';
import { templatePartName } from '../templates.js';
import { EmptyState, StatusChip } from '../components/form.js';
import { toast } from '../components/ui.js';
import { ChangeSheet, LoadFailed, loadInto, refused, when } from './common.js';

/** "Silent Night: Alto and Soprano; Amazing Grace: Bass", a voice's guides grouped by song, in the order given. */
export function guidesLine(guides) {
    const songs = new Map();
    for (const guide of guides) {
        const key = `${guide.templateId}@${guide.version}`;
        if (!songs.has(key)) songs.set(key, { title: guide.title, parts: [] });
        songs.get(key).parts.push(templatePartName({ key: guide.partKey }));
    }
    return [...songs.values()].map((song) => t('admin.aiVoices.songParts', { song: song.title, parts: formatList(song.parts) })).join('; ');
}

export const AiVoicesSection = {
    components: { ChangeSheet, EmptyState, LoadFailed, StatusChip },
    props: { me: { type: Object, required: true } },
    data: () => ({ voices: null, failed: false, withdrawing: null, busy: false, errors: null }),
    setup: () => ({ guidesLine, when }),
    template: `
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.aiVoices') }}</h1>
                <p class="mt-1 max-w-prose text-sm text-muted">{{ t('admin.aiVoices.intro') }}</p>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!voices" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!voices.length" icon="sparkle" :title="t('admin.aiVoices.none')" />
            <ul v-else class="card divide-y divide-line" data-ai-voices>
                <li v-for="voice in voices" :key="voice.id" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-start" :data-ai-voice-row="voice.id">
                    <div class="min-w-0 flex-1 space-y-1">
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="text-lg font-bold text-cream" translate="no">{{ voice.name }}</span>
                            <StatusChip :tone="voice.status === 'active' ? 'good' : 'muted'" :label="voice.status === 'active' ? t('admin.aiVoices.active') : t('admin.aiVoices.withdrawn')" data-ai-voice-status />
                            <StatusChip v-if="voice.status === 'active' && !voice.consent" tone="warn" :label="t('admin.aiVoices.adminOnly')" data-ai-voice-no-consent />
                        </p>
                        <p class="text-sm text-cream">{{ voice.basedOn ? t('admin.aiVoices.basedOn', { name: voice.basedOn }) : t('admin.aiVoices.notBased') }}<template v-if="voice.basedOn && typeof voice.credited === 'boolean'"> · <span data-ai-voice-credited>{{ voice.credited ? t('admin.aiVoices.credited') : t('admin.aiVoices.notCredited') }}</span></template></p>
                        <p class="text-sm text-muted" data-ai-voice-consent>{{ consentLine(voice) }}</p>
                        <p v-if="voice.status === 'withdrawn'" class="text-sm text-muted">{{ t('admin.aiVoices.withdrawnOn', { date: when(voice.withdrawnAt) }) }}</p>
                        <p v-else class="text-sm text-muted" data-ai-voice-guides>{{ voice.guides.length ? t('admin.aiVoices.sings', { songs: guidesLine(voice.guides) }) : t('admin.aiVoices.singsNothing') }}</p>
                    </div>
                    <button v-if="isAdmin && voice.status === 'active'" type="button" class="btn-ghost btn-sm shrink-0 self-start text-bad" data-ai-voice-withdraw @click="ask(voice)">{{ t('admin.aiVoices.withdraw') }}</button>
                </li>
            </ul>
            <ChangeSheet v-if="withdrawing" :title="t('admin.aiVoices.withdrawTitle', { name: withdrawing.name })" :confirm-label="t('admin.aiVoices.withdrawConfirm', { name: withdrawing.name })" :me="me" :busy="busy" :errors="errors" :reasoned="false" @close="withdrawing = null" @confirm="withdraw">
                <p class="text-cream" data-ai-voice-removes>{{ withdrawing.guides.length ? t('admin.aiVoices.withdrawBody', { count: withdrawing.guides.length, songs: guidesLine(withdrawing.guides) }) : t('admin.aiVoices.withdrawBodyNone') }}</p>
                <p class="text-sm text-muted">{{ t('admin.aiVoices.withdrawAfter') }}</p>
            </ChangeSheet>
        </div>`,
    computed: {
        isAdmin: (vm) => vm.me.role === 'admin',
    },
    created() {
        this.load();
    },
    methods: {
        load() {
            return loadInto(this, 'voices', () => api.admin.aiVoices.list());
        },
        /** "Consent 21 Nov 2026 · version ai-voice-1 · payment noted", or that none is recorded yet. */
        consentLine(voice) {
            const c = voice.consent;
            if (!c) return t('admin.aiVoices.noConsent');
            const parts = [t('admin.aiVoices.consentOn', { date: formatDate(`${c.date}T12:00:00`, { day: 'numeric', month: 'short', year: 'numeric' }) }), t('admin.aiVoices.consentVersion', { version: c.version })];
            parts.push(c.paymentNoted ? t('admin.aiVoices.paymentNoted') : t('admin.aiVoices.paymentNotNoted'));
            return parts.join(' · ');
        },
        ask(voice) {
            this.errors = null;
            this.withdrawing = voice;
        },
        async withdraw() {
            this.busy = true;
            try {
                const { meta } = await api.admin.aiVoices.withdraw(this.withdrawing.id);
                this.withdrawing = null;
                toast(t('admin.aiVoices.withdrawnToast', { count: meta?.retired ?? 0 }));
                await this.load();
            } catch (error) {
                if (error?.code === 'already_withdrawn') {
                    this.withdrawing = null;
                    toast(t('admin.aiVoices.alreadyWithdrawn'));
                    await this.load();
                } else {
                    refused(error);
                }
            } finally {
                this.busy = false;
            }
        },
    },
};
