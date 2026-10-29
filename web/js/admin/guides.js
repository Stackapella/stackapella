// Real voices per song (docs/beta-singers-plan.md, "Guides per part" and "Progress"): each part's
// default voice and its alternates, "3 of 4 parts are real voices", which parts still need a
// singer, the default switched in one click (learners hear it at once), and a guide removed on a
// singer's request (in sudo).

import { api } from '../api.js';
import { t } from '../i18n.js';
import { templatePartName } from '../templates.js';
import { EmptyState, StatusChip } from '../components/form.js';
import { Icon } from '../components/icon.js';
import { toast } from '../components/ui.js';
import { ChangeSheet, LoadFailed, loadInto, refused, when } from './common.js';

export const GuidesSection = {
    components: { ChangeSheet, EmptyState, LoadFailed, StatusChip, Icon },
    props: { me: { type: Object, required: true } },
    data: () => ({ songs: null, failed: false, playing: null, removing: null, busy: false, errors: null }),
    setup: () => ({ templatePartName, when }),
    template: `
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.guides') }}</h1>
                <p class="mt-1 max-w-prose text-sm text-muted">{{ t('admin.guides.intro') }}</p>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!songs" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!songs.length" icon="mic" :title="t('admin.guides.none')" />
            <ul v-else class="space-y-4">
                <li v-for="song in songs" :key="song.id" class="card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-cream" translate="no">{{ song.title }}</h2>
                            <p class="text-sm text-muted">{{ t('admin.guides.progress', { done: withVoice(song), total: song.parts.length }) }}<template v-if="needed(song).length"> · {{ t('admin.guides.needed', { parts: needed(song) }) }}</template></p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <StatusChip v-if="!song.reviewed" tone="warn" :label="t('admin.guides.notReviewed')" />
                            <StatusChip tone="muted" :label="t('admin.guides.version', { version: song.version })" />
                        </div>
                    </div>
                    <p v-if="song.olderGuides" class="mt-2 text-sm text-warn">{{ t('admin.guides.older', { count: song.olderGuides }) }}</p>
                    <ul class="mt-4 divide-y divide-line rounded-2xl border border-line">
                        <li v-for="part in song.parts" :key="part.key" class="px-3 py-2.5">
                            <p class="text-sm font-bold text-cream">{{ templatePartName(part) }}</p>
                            <p v-if="!part.guides.length" class="text-sm text-muted">{{ t('admin.guides.synthOnly') }}</p>
                            <ul v-else class="mt-1 space-y-1.5">
                                <li v-for="guide in part.guides" :key="guide.id" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                    <button type="button" class="btn-ghost btn-sm" :aria-pressed="playing?.id === guide.id" :aria-label="playing?.id === guide.id ? t('studio.stop') : t('admin.guides.play', { name: who(guide) })" @click="play(guide)"><Icon :name="playing?.id === guide.id ? 'stop' : 'play'" :size="14" /></button>
                                    <span class="min-w-0 flex-1 text-cream"><span translate="no">{{ who(guide) }}</span> <span class="text-muted">· {{ when(guide.publishedAt) }}<template v-if="guide.pitchPercent !== null && guide.pitchPercent !== undefined"> · {{ t('admin.review.onPitch', { percent: guide.pitchPercent }) }}</template></span></span>
                                    <StatusChip v-if="isBest(part, guide)" tone="info" :label="t('admin.guides.best')" />
                                    <StatusChip v-if="guide.isDefault" tone="good" :label="t('admin.guides.default')" />
                                    <template v-else-if="isAdmin">
                                        <button type="button" class="btn-ghost btn-sm" :disabled="busy" @click="makeDefault(song, guide)">{{ t('admin.guides.makeDefault') }}</button>
                                    </template>
                                    <StatusChip v-if="!guide.isDefault" tone="muted" :label="t('admin.guides.alternate')" />
                                    <button v-if="isAdmin" type="button" class="btn-ghost btn-sm text-bad" @click="ask(song, guide)">{{ t('admin.guides.remove') }}</button>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </li>
            </ul>
            <ChangeSheet v-if="removing" :title="t('admin.guides.removeTitle', { name: who(removing.guide) })" :me="me" :busy="busy" :errors="errors" :reasoned="false" @close="removing = null" @confirm="remove">
                <p class="text-cream">{{ t('admin.guides.removeBody') }}</p>
            </ChangeSheet>
        </div>`,
    computed: {
        isAdmin: (vm) => vm.me.role === 'admin',
    },
    created() {
        this.load();
    },
    unmounted() {
        this.playing?.audio.pause();
    },
    methods: {
        load() {
            return loadInto(this, 'songs', () => api.admin.beta.guides.list());
        },
        withVoice(song) {
            return song.parts.filter((p) => p.guides.length).length;
        },
        needed(song) {
            return song.parts.filter((p) => !p.guides.length).map((p) => templatePartName(p)).join(', ');
        },
        /** The credit learners see, else the singer's name, else (a guide of Hillel's own) none. */
        /** The version of a part with the best pitch score, when there are versions to compare. */
        isBest(part, guide) {
            const scored = part.guides.filter((g) => Number.isFinite(g.pitchPercent));
            return scored.length > 1 && guide.pitchPercent === Math.max(...scored.map((g) => g.pitchPercent));
        },
        who(guide) {
            return guide.recording.credit || guide.singer?.name || t('admin.guides.anonymous');
        },
        /** Plays (or stops) a guide: one at a time. */
        play(guide) {
            const same = this.playing?.id === guide.id;
            this.playing?.audio.pause();
            this.playing = null;
            if (same) return;
            const sound = new Audio(guide.recording.url);
            sound.onended = () => (this.playing = null);
            sound.play().catch((error) => {
                console.warn('Playback', error);
                this.playing = null;
                toast(t('admin.guides.playFailed'), { tone: 'error' });
            });
            this.playing = { id: guide.id, audio: sound };
        },
        async makeDefault(song, guide) {
            this.busy = true;
            try {
                await api.admin.beta.guides.default(guide.id);
                const part = song.parts.find((p) => p.key === guide.partKey);
                part.guides.forEach((g) => (g.isDefault = g.id === guide.id));
                part.guides.sort((a, b) => b.isDefault - a.isDefault);
                toast(t('admin.guides.defaultSet'));
            } catch (error) {
                refused(error);
            } finally {
                this.busy = false;
            }
        },
        ask(song, guide) {
            this.errors = null;
            this.removing = { song, guide };
        },
        async remove() {
            this.busy = true;
            try {
                await api.admin.beta.guides.delete(this.removing.guide.id);
                this.removing = null;
                toast(t('admin.guides.removed'));
                await this.load(); // an alternate may now be the default
            } catch (error) {
                refused(error);
            } finally {
                this.busy = false;
            }
        },
    },
};
