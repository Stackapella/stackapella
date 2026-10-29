// The Tell Hillel inbox (docs/beta-singers-plan.md, "Feedback inbox"): singers' notes, newest
// first, each with its song, part and place, and a way to the takes of that part.

import { api } from '../api.js';
import { t } from '../i18n.js';
import { navigate } from '../router.js';
import { templatePartName } from '../templates.js';
import { EmptyState, StatusChip } from '../components/form.js';
import { refused, LoadFailed, loadInto, when } from './common.js';
import { singerName } from './singers.js';

export const FeedbackSection = {
    components: { EmptyState, LoadFailed, StatusChip },
    props: { me: { type: Object, required: true } },
    data: () => ({ notes: null, failed: false, cursor: null, loadingMore: false, unread: true, titles: {} }),
    setup: () => ({ templatePartName, singerName, when }),
    template: `
        <div class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.feedback') }}</h1>
                <label class="check-row"><input v-model="unread" type="checkbox" class="checkbox" @change="load()" /> {{ t('admin.feedback.unreadOnly') }}</label>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load()" />
            <p v-else-if="!notes" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!notes.length" icon="info" :title="unread ? t('admin.feedback.noneUnread') : t('admin.feedback.none')" />
            <ul v-else class="space-y-3">
                <li v-for="note in notes" :key="note.id" class="card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <p class="text-sm text-muted"><span class="font-bold text-cream" translate="no">{{ singerName(note.singer) }}</span> · {{ when(note.createdAt) }}</p>
                        <StatusChip :tone="note.readAt ? 'muted' : 'info'" :label="note.readAt ? t('admin.feedback.read') : t('admin.feedback.new')" />
                    </div>
                    <p class="mt-2 whitespace-pre-line text-cream" translate="no">{{ note.text }}</p>
                    <p v-if="note.templateId" class="mt-2 text-sm text-muted">
                        <span translate="no">{{ titles[note.templateId] ?? note.templateId }}</span><template v-if="note.partKey">, {{ templatePartName({ key: note.partKey }) }}</template><template v-if="note.beat !== null"> · {{ t('admin.feedback.atBeat', { beat: note.beat }) }}</template>
                    </p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <button v-if="note.templateId" type="button" class="btn-ghost btn-sm" @click="takesOf(note)">{{ t('admin.feedback.seeTakes') }}</button>
                        <button v-if="!note.readAt" type="button" class="btn-ghost btn-sm" @click="markRead(note)">{{ t('admin.feedback.markRead') }}</button>
                    </div>
                </li>
            </ul>
            <button v-if="cursor && !failed" type="button" class="btn-secondary" :disabled="loadingMore" @click="load(true)">{{ loadingMore ? t('admin.loading') : t('admin.loadMore') }}</button>
        </div>`,
    created() {
        this.load();
        import('../templates.js').then(({ listTemplates }) => listTemplates({ drafts: true })).then(
            (list) => (this.titles = Object.fromEntries(list.map((tpl) => [tpl.id, tpl.title]))),
            (error) => console.warn('Song titles failed to load', error),
        );
    },
    methods: {
        load(more = false) {
            return loadInto(this, 'notes', () => api.admin.beta.feedback.list({ unread: this.unread, cursor: more ? this.cursor : undefined }), { more });
        },
        async markRead(note) {
            try {
                const { data } = await api.admin.beta.feedback.read(note.id);
                note.readAt = data.readAt;
            } catch (error) {
                refused(error);
            }
        },
        takesOf(note) {
            const q = new URLSearchParams({ status: '', template: note.templateId });
            if (note.partKey) q.set('part', note.partKey);
            navigate(`/admin/review?${q}`);
        },
    },
};
