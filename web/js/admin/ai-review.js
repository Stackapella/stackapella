// AI review (docs/ai-voices-build-plan.md, phase 1c; contract: admin.aiGuides.list, .approve, .reject). Every new AI
// guide waits here before anyone hears it: by song, each part played alone, its written part alone (the practice voice
// on the song's own notes) or both together, its checks beside it (pitch, the notes over 15 and 30 cents, dropouts,
// length), and Approve or Reject one part or the whole song. A reject needs a note: the part goes back on the voices
// lane's list with it. Only approved guides are offered to the app and the site, and only with ai_voices on.

import { api } from '../api.js';
import { formatList, formatNumber, t, tk } from '../i18n.js';
import { templatePartName, validateTemplate } from '../templates.js';
import { EmptyState, StatusChip } from '../components/form.js';
import { Icon } from '../components/icon.js';
import { toast } from '../components/ui.js';
import { ChangeSheet, errorsFrom, LoadFailed, loadInto, refused, remembers, when } from './common.js';
import { arrangePair, clear, loadPair, playFrom, position, stop } from './ai-review-mix.js';

const STATUSES = ['pending', 'approved', 'rejected'];
const STATUS_WORDS = { pending: tk('admin.aiReview.status.pending'), approved: tk('admin.aiReview.status.approved'), rejected: tk('admin.aiReview.status.rejected') };
const HEAR = ['ai', 'written', 'both'];
const HEAR_WORDS = { ai: tk('admin.aiReview.hear.ai'), written: tk('admin.aiReview.hear.written'), both: tk('admin.aiReview.hear.both') };
const OVER = 30; // cents: a note further off than this is a flag, as the voices lane's gate is

/** Whether a part's checks call for a closer listen: a note over 30 cents (the voices lane's gate); dropouts are in its line. */
export function flagged(checks) {
    if (!checks) return false;
    return (checks.notesOver30 ?? 0) > 0 || (checks.pitchMaxCents ?? 0) > OVER;
}

/** "Pitch median 8¢, worst 34¢ · 1 note over 30¢, 3 over 15¢ · 0 dropouts · 1:03", from a part's checks. */
export function checksLine(c) {
    if (!c) return '';
    const parts = [t('admin.aiReview.checks.pitch', { median: formatNumber(Math.round(c.pitchMedianCents ?? 0)), worst: formatNumber(Math.round(c.pitchMaxCents ?? 0)) }), t('admin.aiReview.checks.over', { over30: c.notesOver30 ?? 0, over15: c.notesOver15 ?? 0 }), t('admin.aiReview.checks.dropouts', { count: c.dropouts ?? 0 })];
    if (Number.isFinite(c.notesMeasured) && Number.isFinite(c.notesTotal)) parts.push(t('admin.aiReview.checks.measured', { measured: c.notesMeasured, total: c.notesTotal }));
    return parts.join(' · ');
}

export const AiReviewSection = {
    components: { ChangeSheet, EmptyState, LoadFailed, StatusChip, Icon },
    mixins: [remembers('aiReview', { status: STATUSES, hear: HEAR })],
    props: { me: { type: Object, required: true } },
    data: () => ({ status: 'pending', hear: 'both', songs: null, counts: null, failed: false, playing: null, loading: null, rejecting: null, busy: false, errors: null }),
    setup: () => ({ STATUSES, STATUS_WORDS, HEAR, HEAR_WORDS, templatePartName, flagged, checksLine, when }),
    template: `
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-cream">{{ t('admin.nav.aiReview') }}</h1>
                <p class="mt-1 max-w-prose text-sm text-muted">{{ t('admin.aiReview.intro') }}</p>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="seg max-w-full overflow-x-auto" role="radiogroup" :aria-label="t('admin.aiReview.statusLabel')" data-ai-review-status>
                    <button v-for="s in STATUSES" :key="s" type="button" role="radio" class="seg-option tap-44" :aria-checked="status === s ? 'true' : 'false'" :data-status="s" @click="status = s">{{ t(STATUS_WORDS[s]) }}<span v-if="counts" class="ms-1.5 tabular-nums opacity-75" data-status-count>{{ formatCount(counts[s]) }}</span></button>
                </div>
                <div class="flex min-w-0 max-w-full items-center gap-2">
                    <span :id="'ai-hear-' + $.uid" class="text-sm font-semibold text-muted">{{ t('admin.aiReview.hearLabel') }}</span>
                    <div class="seg max-w-full overflow-x-auto" role="radiogroup" :aria-labelledby="'ai-hear-' + $.uid" data-ai-review-hear>
                        <button v-for="h in HEAR" :key="h" type="button" role="radio" class="seg-option tap-44" :aria-checked="hear === h ? 'true' : 'false'" :data-hear="h" @click="setHear(h)">{{ t(HEAR_WORDS[h]) }}</button>
                    </div>
                </div>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!songs" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!songs.length" icon="sparkle" :title="status === 'pending' ? t('admin.aiReview.nonePending') : t('admin.aiReview.none')" />
            <ul v-else class="space-y-4" data-ai-review>
                <li v-for="song in songs" :key="song.templateId + '@' + song.version" class="card p-5" :data-ai-review-song="song.templateId">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-cream" translate="no">{{ song.title }}</h2>
                            <p class="text-sm text-muted">{{ t('admin.guides.version', { version: song.version }) }} · {{ t('admin.aiReview.partsCount', { count: song.parts.length }) }}</p>
                        </div>
                        <div v-if="isAdmin && pendingOf(song).length > 1" class="flex flex-wrap gap-2">
                            <button type="button" class="btn-secondary btn-sm" :disabled="busy" data-ai-review-approve-song @click="approve(song, pendingOf(song))">{{ t('admin.aiReview.approveSong', { count: pendingOf(song).length }) }}</button>
                            <button type="button" class="btn-ghost btn-sm text-bad" :disabled="busy" data-ai-review-reject-song @click="askReject(song, pendingOf(song))">{{ t('admin.aiReview.rejectSong', { count: pendingOf(song).length }) }}</button>
                        </div>
                    </div>
                    <ul class="mt-4 divide-y divide-line rounded-2xl border border-line">
                        <li v-for="part in song.parts" :key="part.id" class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-3" :data-ai-review-part="part.partKey">
                            <button v-if="part.recording" type="button" class="btn-ghost btn-sm" :aria-pressed="playing === part.id" :aria-busy="loading === part.id ? 'true' : null" :aria-label="playing === part.id ? t('studio.stop') : t('admin.aiReview.play', { part: partName(part), hear: t(HEAR_WORDS[hear]) })" data-ai-review-play @click="play(song, part)"><Icon :name="playing === part.id ? 'stop' : 'play'" :size="14" /></button>
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-2 text-sm font-bold text-cream"><span translate="no">{{ partName(part) }}</span><span class="font-normal text-muted" translate="no">· {{ part.voice?.name }}</span>
                                    <StatusChip v-if="flagged(part.checks)" tone="warn" :label="t('admin.aiReview.listen')" data-ai-review-flag />
                                    <StatusChip v-if="part.status !== 'pending'" :tone="part.status === 'approved' ? 'good' : 'muted'" :label="t(STATUS_WORDS[part.status])" />
                                </p>
                                <p class="text-xs text-muted tabular-nums" data-ai-review-checks>{{ checksLine(part.checks) }}</p>
                                <p v-if="part.note" class="mt-1 text-xs text-cream" data-ai-review-note>{{ t('admin.aiReview.noteLine', { note: part.note }) }}</p>
                                <p v-if="part.reviewedAt" class="text-xs text-muted">{{ t('admin.aiReview.reviewed', { who: part.reviewedBy?.name ?? '', when: when(part.reviewedAt) }) }}</p>
                            </div>
                            <!-- Pending: approve or reject. Approved: it can still be rejected (its file goes, the part back on the list). -->
                            <div v-if="isAdmin && part.status !== 'rejected'" class="flex gap-2">
                                <button v-if="part.status === 'pending'" type="button" class="btn-secondary btn-sm" :disabled="busy" data-ai-review-approve @click="approve(song, [part])">{{ t('admin.aiReview.approve') }}</button>
                                <button type="button" class="btn-ghost btn-sm text-bad" :disabled="busy" data-ai-review-reject @click="askReject(song, [part])">{{ t('admin.aiReview.reject') }}</button>
                            </div>
                        </li>
                    </ul>
                </li>
            </ul>
            <ChangeSheet v-if="rejecting" :title="rejectTitle" :confirm-label="rejecting.parts.length > 1 ? t('admin.aiReview.rejectConfirmMany', { count: rejecting.parts.length }) : t('admin.aiReview.rejectConfirm', { part: partName(rejecting.parts[0]) })" :me="me" :busy="busy" :errors="errors" @close="rejecting = null" @confirm="reject">
                <p class="text-cream" data-ai-review-reject-body>{{ t('admin.aiReview.rejectBody') }}</p>
            </ChangeSheet>
        </div>`,
    computed: {
        isAdmin: (vm) => vm.me.role === 'admin',
        rejectTitle() {
            const r = this.rejecting;
            return t('admin.aiReview.rejectTitle', { parts: formatList(r.parts.map((p) => this.partName(p))), song: r.song.title });
        },
    },
    watch: {
        status() {
            this.stopPlaying();
            this.load();
        },
    },
    created() {
        this.templates = new Map(); // `${id}@${version}` → the song at that version, for the written parts
        this.pairs = new Map(); // a part's id → its decoded AI guide and written part
        this.load();
    },
    unmounted() {
        this.stopPlaying();
        clear();
    },
    methods: {
        load() {
            this.songs = null;
            return loadInto(this, 'songs', async () => {
                const answer = await api.admin.aiGuides.list({ status: this.status });
                this.counts = answer.meta?.counts ?? null;
                return answer;
            });
        },
        formatCount: (n) => (Number.isFinite(n) ? formatNumber(n) : ''),
        partName(part) {
            return part.partName || templatePartName({ key: part.partKey });
        },
        pendingOf(song) {
            return song.parts.filter((p) => p.status === 'pending');
        },
        async templateOf(song) {
            const key = `${song.templateId}@${song.version}`;
            if (!this.templates.has(key)) this.templates.set(key, api.templates.show(song.templateId, { version: song.version }).then(({ data }) => validateTemplate(data.template)));
            return this.templates.get(key);
        },
        /** Plays (or stops) a part as `hear` says, one at a time, from the start; it stops by itself at the end. */
        async play(song, part) {
            const same = this.playing === part.id;
            this.stopPlaying();
            if (same) return;
            this.loading = part.id; // the press answers at once (design rules: every play control loads the same way)
            try {
                if (!this.pairs.has(part.id)) this.pairs.set(part.id, await loadPair({ template: await this.templateOf(song), partKey: part.partKey, recording: part.recording }));
                if (this.loading !== part.id) return; // another press since
                arrangePair(this.pairs.get(part.id), this.hear);
                await playFrom(0);
                this.playing = part.id;
                this.watchEnd();
            } catch (error) {
                console.warn('The AI guide did not play', error);
                toast(t('admin.aiReview.playFailed'), { tone: 'error' });
            } finally {
                if (this.loading === part.id) this.loading = null;
            }
        },
        /** Reads the engine's own clock once a frame to see the end (nothing is scheduled on a timer). */
        watchEnd() {
            const at = position();
            if (!this.playing) return;
            if (!at || at.done) {
                this.stopPlaying();
                return;
            }
            this.frame = requestAnimationFrame(() => this.watchEnd());
        },
        stopPlaying() {
            cancelAnimationFrame(this.frame);
            if (this.playing) stop();
            this.playing = null;
            this.loading = null;
        },
        /** A new way to hear it: the part playing carries on in it from the start. */
        setHear(h) {
            this.hear = h;
            const id = this.playing;
            if (!id) return;
            const song = this.songs.find((s) => s.parts.some((p) => p.id === id));
            const part = song?.parts.find((p) => p.id === id);
            this.stopPlaying();
            if (part) this.play(song, part);
        },
        async approve(song, parts) {
            this.busy = true;
            try {
                // The ids shown, never the whole song by its id: a song's button acts only on what the admin saw.
                const { data } = await api.admin.aiGuides.approve({ ids: parts.map((p) => p.id) });
                toast(t('admin.aiReview.approved', { count: data?.approved ?? parts.length }));
                await this.load();
            } catch (error) {
                refused(error);
            } finally {
                this.busy = false;
            }
        },
        askReject(song, parts) {
            this.errors = null;
            this.rejecting = { song, parts };
        },
        async reject(note) {
            this.busy = true;
            try {
                const { song, parts } = this.rejecting;
                const { data } = await api.admin.aiGuides.reject({ ids: parts.map((p) => p.id), note });
                this.rejecting = null;
                toast(t('admin.aiReview.rejected', { count: data?.rejected ?? parts.length }));
                await this.load();
            } catch (error) {
                this.errors = errorsFrom(error);
                if (!this.errors) refused(error);
            } finally {
                this.busy = false;
            }
        },
    },
};
