// Cloud save on the screen (docs/backend-plan.md, C3 to C6): "Changed on another device" with Keep
// mine or Use theirs, the storage meter (amber from 80%, with "See what's using space"), the line
// that says whether everything is saved to the account, and sizes in words.

import * as db from '../db.js';
import { formatNumber, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { cloud, settle } from '../stores/cloud.js';
import { session } from '../stores/session.js';
import { shownTitle } from '../stores/projects.js';
import { Modal, toast } from './ui.js';

/** "4.2 MB", "1.1 GB", in the person's language. */
export function formatBytes(bytes) {
    const gb = bytes / 1024 ** 3;
    if (gb >= 1) return formatNumber(gb, { style: 'unit', unit: 'gigabyte', maximumFractionDigits: 1 });
    const mb = bytes / 1024 ** 2;
    return formatNumber(mb, { style: 'unit', unit: 'megabyte', maximumFractionDigits: mb < 10 ? 1 : 0 });
}

/** One conflict at a time, asked when nothing is being sung (never in the middle of a take). */
export const CloudConflicts = {
    components: { Modal },
    data: () => ({ shown: null, busy: false }),
    setup: () => ({ cloud, session }),
    template: `
        <Modal v-if="shown && !session.recording && !session.playing" :key="shown.conflict.id" :title="t('cloud.conflict.title')" :dismissable="false">
            <div class="space-y-5">
                <p class="text-cream">{{ shown.text }}</p>
                <p class="text-sm text-muted">{{ deleting ? t('cloud.conflict.deleteHint') : t('cloud.conflict.hint') }}</p>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" class="btn-secondary" :disabled="busy" @click="choose('theirs')">{{ deleting ? t('cloud.conflict.keepTheirs') : t('cloud.conflict.theirs') }}</button>
                    <button type="button" class="btn-primary" :disabled="busy" @click="choose('mine')">{{ deleting ? t('cloud.conflict.deleteIt') : t('cloud.conflict.mine') }}</button>
                </div>
            </div>
        </Modal>`,
    computed: {
        deleting() {
            return ['deleteProject', 'deletePart'].includes(this.shown?.conflict.kind);
        },
    },
    watch: {
        'cloud.conflicts': {
            async handler(list) {
                const conflict = list[0] ?? null;
                if (!conflict) {
                    this.shown = null;
                    return;
                }
                if (this.shown?.conflict.id === conflict.id) return;
                this.shown = { conflict, text: await this.describe(conflict) };
            },
            immediate: true,
            deep: true,
        },
    },
    methods: {
        async describe(conflict) {
            if (conflict.kind === 'deleteProject') return t('cloud.conflict.deletedProject', { title: shownTitle(conflict.theirs) });
            if (conflict.kind === 'deletePart') {
                const project = await db.get('projects', conflict.theirs?.projectId ?? conflict.projectId);
                return t('cloud.conflict.deletedPart', { part: conflict.theirs?.name ?? '', title: shownTitle(project) });
            }
            if (conflict.kind === 'project') {
                const local = await db.get('projects', conflict.id);
                return t('cloud.conflict.project', { title: shownTitle(local ?? conflict.theirs) });
            }
            const part = await db.get('parts', conflict.id);
            const project = part ? await db.get('projects', part.projectId) : null;
            return t('cloud.conflict.part', { part: part?.name ?? conflict.theirs?.name ?? '', title: shownTitle(project) });
        },
        async choose(choice) {
            this.busy = true;
            try {
                await settle(this.shown.conflict, choice);
                toast(this.deleting ? (choice === 'mine' ? t('cloud.conflict.deleted') : t('cloud.conflict.keptTheirs')) : choice === 'mine' ? t('cloud.conflict.keptMine') : t('cloud.conflict.tookTheirs'));
            } catch (error) {
                console.error('The conflict was not settled', error);
                toast(error?.code === 'offline' ? t('common.offline') : t('common.couldNotSave'));
            } finally {
                this.busy = false;
            }
        },
    },
};

const LOCKED = { limit: tk('locked.limit'), plusSong: tk('locked.plusSong'), offline: tk('locked.offline') };
const LOCKED_ACTION = { limit: tk('locked.choose'), plusSong: tk('export.seePlus') };

/** A project that can be played and exported, not changed: why, and the way out. */
export const LockedBanner = {
    props: { reason: { type: String, required: true } },
    setup: () => ({ LOCKED, LOCKED_ACTION, navigate }),
    template: `
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-2xl bg-raised px-4 py-3 text-sm text-cream" role="status">
            <span class="min-w-0 flex-1">{{ t(LOCKED[reason]) }}</span>
            <a v-if="reason === 'limit'" href="/storage" class="btn-go" @click.prevent="navigate('/storage')">{{ t(LOCKED_ACTION.limit) }}</a>
            <a v-else-if="reason === 'plusSong'" href="/plus?from=plus_song" class="btn-go" @click.prevent="navigate('/plus?from=plus_song')">{{ t(LOCKED_ACTION.plusSong) }}</a>
        </div>`,
};

/** The storage meter: what the account uses of its allowance, amber from 80%. */
export const StorageMeter = {
    props: { usage: { type: Object, required: true }, link: Boolean }, // usage.show's data
    setup: () => ({ formatBytes, navigate }),
    template: `
        <div class="space-y-2">
            <div class="flex items-baseline justify-between gap-3 text-sm">
                <span class="font-semibold text-cream">{{ t('cloud.meter.used', { used: formatBytes(usage.storage.usedBytes), limit: formatBytes(usage.storage.limitBytes) }) }}</span>
                <span v-if="usage.projects.limit !== null" class="text-muted">{{ t('cloud.meter.projects', { used: usage.projects.used, limit: usage.projects.limit }) }}</span>
            </div>
            <div class="h-2.5 overflow-hidden rounded-full bg-raised" role="meter" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100" :aria-label="t('cloud.meter.label')">
                <div class="h-full rounded-full" :class="share >= 0.8 ? 'bg-warn' : 'bg-good'" :style="{ width: Math.max(2, Math.min(100, percent)) + '%' }"></div>
            </div>
            <p v-if="share >= 1" class="text-sm text-warn">{{ t('cloud.meter.full') }}</p>
            <p v-else-if="share >= 0.8" class="text-sm text-warn">{{ t('cloud.meter.nearlyFull') }}</p>
            <p v-if="share >= 0.8 && usage.plan !== 'plus'" class="text-sm text-cream">{{ t('plus.moment.storageLimit') }} <a href="/plus?from=storage_limit" class="link" @click.prevent="navigate('/plus?from=storage_limit')">{{ t('export.seePlus') }}</a></p>
            <a v-if="link" href="/storage" class="btn-go" @click.prevent="navigate('/storage')">{{ t('cloud.meter.see') }}</a>
        </div>`,
    computed: {
        share() {
            return this.usage.storage.limitBytes ? this.usage.storage.usedBytes / this.usage.storage.limitBytes : 0;
        },
        percent() {
            return Math.round(this.share * 100);
        },
    },
};

/** Whether everything is in the account: waiting changes, offline, or a reason takes wait. */
export const SyncStatus = {
    setup: () => ({ cloud }),
    template: `<p class="text-sm" :class="tone" role="status">{{ text }}</p>`,
    computed: {
        text() {
            if (cloud.offline) return t('cloud.status.offline');
            if (cloud.waiting === 'email_unverified') return t('cloud.status.unverified');
            if (cloud.waiting === 'storage_limit') return t('cloud.status.full');
            if (cloud.waiting === 'project_read_only') return t('cloud.status.readOnly');
            if (cloud.pending) return t('cloud.status.pending', { count: cloud.pending });
            return cloud.lastSynced ? t('cloud.status.saved') : t('cloud.status.starting');
        },
        tone() {
            return cloud.waiting ? 'text-warn' : 'text-muted';
        },
    },
};
