// Ideas held for review (docs/ideas-app-plan.md, slice 4; contract/api.md, admin.ideas.*): a new
// writer's first two, and any that reports held, oldest first; each with its writer, note, parts,
// its reports (count and reasons) and a link to hear it. Approve lists it; Hide takes it off the
// shelf with a note, which its writer is told and the audit log keeps.

import { api } from '../api.js';
import { t, tk } from '../i18n.js';
import { EmptyState, StatusChip } from '../components/form.js';
import { toast } from '../components/ui.js';
import { ChangeSheet, errorsFrom, LoadFailed, refused, when } from './common.js';

const REASONS = {
    copyright: tk('admin.reports.reason.copyright'),
    offensive: tk('admin.reports.reason.offensive'),
    spam: tk('admin.reports.reason.spam'),
    personal: tk('admin.reports.reason.personal'),
    other: tk('admin.reports.reason.other'),
};

export const IdeasSection = {
    components: { ChangeSheet, EmptyState, LoadFailed, StatusChip },
    props: { me: { type: Object, required: true } },
    data: () => ({ items: null, failed: false, hiding: null, busy: false, errors: null }),
    setup: () => ({ REASONS, when }),
    template: `
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.ideas') }}</h1>
                <p class="text-sm text-muted">{{ t('admin.ideas.intro') }}</p>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load()" />
            <p v-else-if="!items" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!items.length" icon="mic" :title="t('admin.ideas.none')" />
            <ul v-else class="space-y-4">
                <li v-for="idea in items" :key="idea.id" class="card space-y-3 p-5" :data-held-idea="idea.id">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a :href="'/ideas/' + idea.id" target="_blank" rel="noopener" class="font-bold text-cream hover:underline" translate="no">{{ idea.title }}</a>
                            <p class="text-sm text-muted"><span translate="no">{{ idea.writer.name }}</span> · {{ t('admin.ideas.heldSince', { when: when(idea.heldAt) }) }}</p>
                        </div>
                        <StatusChip v-if="idea.reports?.count" tone="warn" :label="t('admin.ideas.reports', { count: idea.reports.count })" />
                        <StatusChip v-else tone="info" :label="t('admin.ideas.newWriter')" />
                    </div>
                    <p v-if="idea.note" class="whitespace-pre-line text-cream" translate="no">{{ idea.note }}</p>
                    <p class="text-sm text-muted">{{ idea.parts.map((p) => p.name).join(' · ') }}</p>
                    <p v-if="idea.reports?.reasons?.length" class="text-sm text-cream">{{ idea.reports.reasons.map((r) => REASONS[r] ? t(REASONS[r]) : r).join(', ') }}</p>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="btn-primary btn-sm" :disabled="busy" @click="approve(idea)">{{ t('admin.ideas.approve') }}</button>
                        <button type="button" class="btn-secondary btn-sm" :disabled="busy" @click="hiding = idea; errors = null">{{ t('admin.ideas.hide') }}</button>
                    </div>
                </li>
            </ul>
            <ChangeSheet v-if="hiding" :title="t('admin.ideas.hideTitle', { title: hiding.title })" :me="me" :busy="busy" :errors="errors" :needs-sudo="false" @close="hiding = null" @confirm="hide">
                <p class="text-sm text-muted">{{ t('admin.ideas.hideHint') }}</p>
            </ChangeSheet>
        </div>`,
    created() {
        this.load();
    },
    methods: {
        async load() {
            this.failed = false;
            try {
                this.items = (await api.admin.ideas.held()).data;
            } catch (error) {
                refused(error);
                this.failed = t('admin.ideas.failed');
            }
        },
        async decide(idea, decision, note = null) {
            this.busy = true;
            try {
                this.items = (await api.admin.ideas.decide({ params: { id: idea.id }, body: { decision, note } })).data;
                return true;
            } catch (error) {
                this.errors = errorsFrom(error);
                if (!this.errors) refused(error);
                return false;
            } finally {
                this.busy = false;
            }
        },
        async approve(idea) {
            if (await this.decide(idea, 'approve')) toast(t('admin.ideas.approved', { title: idea.title }));
        },
        async hide(note) {
            const idea = this.hiding;
            if (await this.decide(idea, 'hide', note)) {
                this.hiding = null;
                toast(t('admin.ideas.hidden', { title: idea.title }));
            }
        },
    },
};
