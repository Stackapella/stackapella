// Storage (Hillel's 88.png; docs/more-features-plan.md, feature 15): the real limit first (the songs
// as slots), the space split by song with an honest line, the sync line; each song by size, opening to
// its current and older takes (Clear older takes, Delete song); and Recently deleted with countdowns,
// what a restore will do said before the tap, Delete now and Restore.

import { api, ApiError } from '../api.js';
import { formatDate, t, timeAgo } from '../i18n.js';
import { goBack, navigate } from '../router.js';
import { account, selfHosted } from '../stores/account.js';
import { cloud, syncNow } from '../stores/cloud.js';
import { loadProjects, projects, shownTitle } from '../stores/projects.js';
import { formatBytes, SyncStatus } from '../components/cloud.js';
import { EmptyState, Notice } from '../components/form.js';
import { PageHeader } from '../components/page.js';
import { Icon, Logo } from '../components/icon.js';
import { toast } from '../components/ui.js';
import { confirmAction, deleteSong } from '../components/confirm.js';
import { courseTakes, deleteCourseTakes } from '../device.js';

/** What a refused restore means, in words. */
function restoreRefusal(error, usage) {
    if (!(error instanceof ApiError)) return t('common.couldNotSave');
    if (error.code === 'offline') return t('common.offline');
    if (error.code === 'project_limit') return t('cloud.bin.projectLimit', { limit: usage?.projects.limit ?? 3 });
    if (error.code === 'storage_limit') return t('cloud.bin.storageLimit');
    if (error.code === 'part_gone') return t('cloud.bin.partGone');
    if (error.code === 'binned') return t('cloud.bin.projectBinned');
    return t('common.couldNotSave');
}

export const StorageView = {
    components: { PageHeader, EmptyState, Notice, Icon, Logo, SyncStatus },
    data: () => ({ usage: null, bin: null, failed: false, busy: null, notice: null, editable: [], openSong: null, kept: null }),
    setup: () => ({ account, cloud, projects, navigate, goBack, formatBytes, formatDate, shownTitle, selfHosted }),
    template: `
        <div class="min-h-dvh bg-ink px-4 pb-16 pt-[max(0.5rem,env(safe-area-inset-top))] md:px-8">
            <div class="mx-auto flex w-full max-w-2xl flex-col gap-8">
                <PageHeader :title="t('cloud.storage.title')" back="/settings" />

                <!-- The real limit first (88.png): the songs as slots, then the space split by song, then the sync line -->
                <section v-if="account.me" class="card space-y-5 p-5 md:p-6" data-storage-hero>
                    <p v-if="!usage && failed" class="text-sm text-muted">{{ failed === 'offline' ? t('common.offline') : t('cloud.storage.notLoaded') }}</p>
                    <p v-else-if="!usage" class="text-sm text-muted" role="status">{{ t('settings.loading') }}</p>
                    <template v-else>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm text-muted">{{ usage.plan === 'plus' ? t('settings.plan.plusName') : t('settings.plan.freeName') }}</p>
                                <p class="text-3xl font-extrabold tracking-tight text-cream">{{ usage.projects.limit !== null ? t('cloud.storage.songsOf', { used: usage.projects.used, limit: usage.projects.limit }) : t('cloud.storage.songsCount', { count: usage.projects.used }) }}</p>
                                <p v-if="usage.projects.limit !== null" class="text-sm text-cream/85">{{ t('settings.plan.songsLeft', { count: Math.max(0, usage.projects.limit - usage.projects.used) }) }}</p>
                            </div>
                            <a v-if="usage.plan !== 'plus' && !selfHosted()" href="/plus?from=storage" class="btn-primary btn-sm shrink-0" @click.prevent="navigate('/plus?from=storage')">{{ t('cloud.storage.unlimited') }}</a>
                        </div>
                        <div v-if="usage.projects.limit !== null" class="flex gap-1.5" aria-hidden="true"><span v-for="i in usage.projects.limit" :key="i" class="h-2 flex-1 rounded-full" :class="i <= usage.projects.used ? 'bg-good' : 'bg-raised'"></span></div>
                        <div class="space-y-2 border-t border-line pt-5">
                            <p class="flex justify-between gap-3 text-sm"><span class="font-semibold text-cream">{{ t('cloud.storage.space') }}</span><span class="tabular-nums text-muted">{{ t('settings.plan.ofLimit', { used: formatBytes(usage.storage.usedBytes), limit: formatBytes(usage.storage.limitBytes) }) }}</span></p>
                            <div class="flex h-2 overflow-hidden rounded-full bg-raised" role="meter" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="spacePercent" :aria-label="t('cloud.meter.label')">
                                <span v-for="song in spaceSongs" :key="song.id" class="h-full" :style="{ width: song.share * 100 + '%', background: song.color }"></span>
                            </div>
                            <ul v-if="spaceSongs.length" class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                                <li v-for="song in spaceSongs" :key="song.id" class="flex items-center gap-1.5"><span class="size-2 rounded-sm" :style="{ background: song.color }" aria-hidden="true"></span><span translate="no">{{ song.title }}</span> {{ formatBytes(song.bytes) }}</li>
                            </ul>
                            <p class="text-xs" :class="spacePercent >= 80 ? 'text-warn' : 'text-muted'">{{ spacePercent >= 100 ? t('cloud.meter.full') : spacePercent >= 80 ? t('cloud.meter.nearlyFull') : usage.plan === 'plus' ? t('cloud.storage.plentyPlus') : t('cloud.storage.plenty') }}</p>
                        </div>
                    </template>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                        <SyncStatus />
                        <button type="button" class="btn-secondary btn-sm" :disabled="busy === 'sync'" @click="syncAgain">{{ t('cloud.storage.syncNow') }}</button>
                    </div>
                </section>
                <Notice v-else tone="info">
                    {{ t('cloud.storage.signedOut') }}
                    <button type="button" class="link ms-1" @click="navigate('/signin?next=/storage')">{{ t('auth.signIn') }}</button>
                </Notice>

                <Notice v-if="notice" tone="bad">{{ notice }}</Notice>

                <!-- After a downgrade: which songs stay editable (the free plan's limit); the rest stay readable. -->
                <form v-if="account.me && overLimit" class="card space-y-3 p-6" aria-labelledby="editable-heading" @submit.prevent="saveEditable">
                    <h2 id="editable-heading" class="text-lg font-bold text-cream">{{ t('locked.editableTitle') }}</h2>
                    <p class="text-sm text-muted">{{ t('locked.editableHint', { limit: editableLimit }) }}</p>
                    <ul class="space-y-1">
                        <li v-for="p in choosable" :key="p.id">
                            <label class="flex items-center gap-3 rounded-xl p-2 hover:bg-raised">
                                <input v-model="editable" type="checkbox" :value="p.id" class="checkbox" :disabled="!editable.includes(p.id) && editable.length >= editableLimit" />
                                <span class="min-w-0 flex-1 truncate" :class="p.title?.trim() ? 'text-cream' : 'italic text-muted'" :translate="p.title?.trim() ? 'no' : null">{{ shownTitle(p) }}</span>
                            </label>
                        </li>
                    </ul>
                    <button type="submit" class="btn-primary btn-sm" :disabled="busy === 'editable'">{{ t('common.save') }}</button>
                </form>

                <!-- Your songs, biggest first: each opens to its current and older takes -->
                <section class="space-y-3" aria-labelledby="songs-heading">
                    <div class="flex items-baseline justify-between gap-3"><h2 id="songs-heading" class="text-lg font-bold text-cream">{{ t('cloud.storage.yourSongs') }}</h2><span class="text-xs text-muted">{{ t('cloud.storage.biggestFirst') }}</span></div>
                    <p v-if="!bySize.length" class="text-sm text-muted">{{ t('cloud.storage.noProjects') }}</p>
                    <ul v-else class="card divide-y divide-line overflow-hidden">
                        <li v-for="p in bySize" :key="p.id" :data-song="p.id">
                            <button type="button" class="flex w-full items-center gap-3 p-4 text-start hover:bg-raised/40" :aria-expanded="openSong === p.id ? 'true' : 'false'" @click="openSong = openSong === p.id ? null : p.id">
                                <span class="size-2.5 shrink-0 rounded-sm" :style="{ background: p.color }" aria-hidden="true"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-semibold" :class="p.title?.trim() ? 'text-cream' : 'italic text-muted'" :translate="p.title?.trim() ? 'no' : null">{{ shownTitle(p) }}</span>
                                    <span class="block truncate text-xs text-muted">{{ songLine(p) }}</span>
                                </span>
                                <span class="shrink-0 font-semibold tabular-nums text-cream">{{ formatBytes(p.size) }}</span>
                                <Icon name="chevron" :size="16" class="shrink-0 text-muted transition" :class="openSong === p.id ? 'rotate-180' : ''" />
                            </button>
                            <div v-if="openSong === p.id" class="space-y-3 px-4 pb-4 ps-[2.375rem]">
                                <dl class="space-y-1 text-sm">
                                    <div class="flex justify-between gap-3"><dt class="text-cream">{{ t('cloud.storage.current') }}</dt><dd class="tabular-nums text-cream">{{ formatBytes(p.size) }}</dd></div>
                                    <div v-if="p.binBytes" class="flex justify-between gap-3"><dt class="text-cream">{{ t('cloud.storage.older') }}</dt><dd class="tabular-nums text-cream">{{ formatBytes(p.binBytes) }}</dd></div>
                                </dl>
                                <div class="flex flex-wrap gap-2">
                                    <button v-if="p.binBytes" type="button" class="btn-secondary btn-sm" :disabled="busy === 'older' + p.id" @click="clearOlder(p)">{{ t('cloud.storage.clearOlder', { size: formatBytes(p.binBytes) }) }}</button>
                                    <button type="button" class="btn-secondary btn-sm text-bad" @click="remove(p)">{{ t('confirm.deleteProject.action') }}</button>
                                </div>
                            </div>
                        </li>
                    </ul>
                </section>

                <!-- The course's voice takes (Then and now), kept only on this device, never synced -->
                <section v-if="kept?.count" class="card flex flex-wrap items-center justify-between gap-4 p-5" aria-labelledby="course-takes-heading" data-course-takes>
                    <div class="min-w-0 flex-1"><h2 id="course-takes-heading" class="font-semibold text-cream">{{ t('cloud.storage.courseTakes') }}</h2><p class="text-sm text-muted">{{ t('cloud.storage.courseTakesHint', { count: kept.count, size: formatBytes(kept.bytes) }) }}</p></div>
                    <button type="button" class="btn-secondary btn-sm shrink-0 text-bad" :disabled="busy === 'courseTakes'" @click="removeCourseTakes">{{ t('cloud.storage.courseTakesDelete') }}</button>
                </section>

                <!-- Recently deleted: countdowns, the restore rules said before you tap, and Delete now -->
                <section v-if="account.me" class="space-y-3" aria-labelledby="bin-heading">
                    <div><h2 id="bin-heading" class="text-lg font-bold text-cream">{{ t('cloud.bin.title') }}</h2><p class="text-sm text-muted">{{ t('cloud.bin.kept') }}</p></div>
                    <p v-if="bin === null && !failed" class="text-sm text-muted" role="status">{{ t('settings.loading') }}</p>
                    <p v-else-if="bin && !bin.length" class="text-sm text-muted">{{ t('cloud.bin.empty') }}</p>
                    <ul v-else-if="bin" class="card divide-y divide-line overflow-hidden">
                        <li v-for="item in bin" :key="item.kind + item.id" class="flex flex-wrap items-center gap-3 p-4" data-bin-item>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-cream">
                                    <template v-if="item.kind === 'take'">{{ t('cloud.bin.take', { part: item.partName ?? '', title: item.title || t('project.untitled') }) }}</template>
                                    <span v-else translate="no">{{ item.title || t('project.untitled') }}</span>
                                </p>
                                <p class="text-xs" :class="daysLeft(item) <= 3 ? 'text-warn' : 'text-muted'">{{ t('cloud.bin.goneIn', { size: formatBytes(item.bytes), count: daysLeft(item) }) }}</p>
                                <p v-if="ruleOf(item)" class="mt-1 text-xs" :class="ruleOf(item).tone === 'warn' ? 'text-warn' : 'text-muted'">{{ ruleOf(item).text }}</p>
                            </div>
                            <button type="button" class="min-h-11 text-sm text-muted hover:text-cream" :disabled="busy === item.id" @click="purge(item)">{{ t('cloud.bin.deleteNow') }}</button>
                            <button type="button" class="btn-secondary btn-sm" :disabled="busy === item.id" @click="restore(item)">{{ item.kind === 'take' ? t('cloud.bin.restoreTake') : t('cloud.bin.restore') }}</button>
                        </li>
                    </ul>
                </section>
            </div>
        </div>`,
    computed: {
        editableLimit() {
            return this.usage?.projects.limit ?? 3;
        },
        /** Projects a free account can choose to keep editable (not a Plus song's, not the course's). */
        choosable: () => projects.list.filter((p) => !p.course && p.version > 0 && p.readOnly !== 'plus_song'),
        overLimit() {
            return this.usage?.plan === 'free' && projects.list.some((p) => p.readOnly === 'limit');
        },
        /** Each song's size (its current takes, as the account counts them, else on the device), its older takes, and its colour. */
        bySize() {
            const counted = new Map((this.usage?.storage.projects ?? []).map((row) => [row.projectId, row]));
            return projects.list
                .map((p) => ({ ...p, size: counted.get(p.id)?.bytes ?? p.bytes ?? p.deviceBytes ?? 0, binBytes: counted.get(p.id)?.binBytes ?? 0, color: p.thumbnail?.[0]?.color ?? 'var(--color-muted)' }))
                .sort((a, b) => b.size - a.size);
        },
        /** The space bar, split by song (88.png), each its share of the whole space. */
        spaceSongs() {
            const limit = this.usage?.storage.limitBytes;
            if (!limit) return [];
            return this.bySize.filter((p) => p.size > 0).map((p) => ({ id: p.id, title: shownTitle(p), bytes: p.size, color: p.color, share: p.size / limit }));
        },
        spacePercent() {
            const st = this.usage?.storage;
            return st?.limitBytes ? Math.min(100, Math.round((st.usedBytes / st.limitBytes) * 100)) : 0;
        },
    },
    watch: {
        // Opened straight from a link, the account arrives after the page.
        'account.me': {
            handler(me) {
                if (me && !this.usage) this.load();
            },
            immediate: true,
        },
    },
    created() {
        loadProjects();
        this.countCourseTakes();
    },
    methods: {
        async load() {
            if (!account.me) return;
            this.failed = false;
            try {
                const [usage, bin] = await Promise.all([api.usage.show(), api.bin.list()]);
                this.usage = usage.data;
                this.bin = bin.data;
                this.editable = projects.list.filter((p) => !p.course && p.version > 0 && !p.readOnly).map((p) => p.id);
            } catch (error) {
                console.warn('Storage not loaded', error);
                this.failed = error?.code === 'offline' ? 'offline' : true;
            }
        },
        async syncAgain() {
            this.busy = 'sync';
            try {
                await syncNow();
                await this.load();
            } finally {
                this.busy = null;
            }
        },
        async saveEditable() {
            this.busy = 'editable';
            this.notice = null;
            try {
                await api.projects.editable.update(this.editable);
                await syncNow(); // the projects' read-only marks come back down
                await loadProjects();
                toast(t('locked.editableSaved'));
            } catch (error) {
                this.notice = restoreRefusal(error, this.usage);
            } finally {
                this.busy = null;
            }
        },
        async restore(item) {
            this.busy = item.id;
            this.notice = null;
            try {
                if (item.kind === 'project') await api.projects.restore(item.id);
                else await api.takes.restore(item.id);
                await syncNow(); // it comes back down to this device
                await this.load();
                toast(item.kind === 'project' ? t('cloud.bin.restoredProject', { title: item.title || t('project.untitled') }) : t('cloud.bin.restoredTake', { part: item.partName ?? '' }));
            } catch (error) {
                this.notice = restoreRefusal(error, this.usage);
            } finally {
                this.busy = null;
            }
        },
        /** "4 parts · edited today · 8.0 MB in older takes". */
        songLine(p) {
            return [t('studio.partCount', { count: p.partCount ?? 0 }), t('projects.edited', { when: timeAgo(p.updatedAt) }), p.binBytes ? t('cloud.storage.olderLine', { size: formatBytes(p.binBytes) }) : null].filter(Boolean).join(' · ');
        },
        daysLeft(item) {
            return Math.max(0, Math.ceil((new Date(item.goesAt).getTime() - Date.now()) / 86400000));
        },
        /** What restoring will do, said before the tap: a take becomes an older take; a song may use the last free slot, or not fit. */
        ruleOf(item) {
            if (item.kind === 'take') return { tone: 'muted', text: t('cloud.bin.takeNote', { part: item.partName ?? '' }) };
            const limit = this.usage?.projects.limit;
            if (limit === null || limit === undefined) return null;
            const used = this.usage.projects.used;
            if (used >= limit) return { tone: 'warn', text: t('cloud.bin.projectLimit', { limit }) };
            if (used === limit - 1) return { tone: 'warn', text: t('cloud.bin.lastSlot') };
            return null;
        },
        async purge(item) {
            const title = item.kind === 'take' ? t('cloud.bin.take', { part: item.partName ?? '', title: item.title || t('project.untitled') }) : item.title || t('project.untitled');
            const sure = await confirmAction({ title: t('cloud.bin.deleteNowTitle', { title }), body: t('cloud.bin.deleteNowBody'), action: t('cloud.bin.deleteNowAction'), cancel: t('confirm.keep') });
            if (!sure) return;
            this.busy = item.id;
            try {
                await api.bin.purge(item.kind, item.id);
                toast(t('cloud.bin.deletedNow'));
                await this.load();
            } catch (error) {
                if (error?.code === 'not_found') await this.load();
                else this.notice = error?.code === 'offline' ? t('common.offline') : t('common.couldNotSave');
            } finally {
                this.busy = null;
            }
        },
        async countCourseTakes() {
            try {
                const takes = await courseTakes();
                this.kept = { count: takes.keys.length, bytes: takes.bytes };
            } catch (error) {
                console.warn('The course voice takes could not be counted', error);
            }
        },
        async removeCourseTakes() {
            const sure = await confirmAction({ title: t('cloud.storage.courseTakesTitle'), body: t('cloud.storage.courseTakesBody'), action: t('cloud.storage.courseTakesAction'), cancel: t('confirm.keep') });
            if (!sure) return;
            this.busy = 'courseTakes';
            try {
                await deleteCourseTakes();
                toast(t('cloud.storage.courseTakesDone'));
            } catch (error) {
                console.error('The course voice takes were not deleted', error);
                toast(t('cloud.storage.courseTakesFailed'), { tone: 'error' });
            } finally {
                this.busy = null;
                await this.countCourseTakes();
            }
        },
        async clearOlder(p) {
            this.busy = 'older' + p.id;
            try {
                const { data } = await api.bin.purgeTakes(p.id);
                toast(t('cloud.storage.cleared', { size: formatBytes(data.freedBytes ?? 0) }));
                await this.load();
            } catch (error) {
                this.notice = error?.code === 'offline' ? t('common.offline') : t('common.couldNotSave');
            } finally {
                this.busy = null;
            }
        },
        async remove(project) {
            if (!(await deleteSong(project))) return;
            await syncNow(); // the delete goes up, and the bin shows it
            await this.load();
        },
    },
};
