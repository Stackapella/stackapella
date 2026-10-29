// The audit log (after iaw's admin/audit.blade.php): every admin, moderation and money action,
// newest first, with who, what, and the reason given.

import { api } from '../api.js';
import { formatDate, t, tk } from '../i18n.js';
import { EmptyState, Notice } from '../components/form.js';
import { explain, sendToSignIn } from './auth.js';

const FILTERS = [
    { value: '', label: tk('admin.audit.all') },
    { value: 'flag.', label: tk('admin.audit.flags') },
    { value: 'limit.', label: tk('admin.audit.limits') },
    { value: 'block.', label: tk('admin.audit.blocks') },
    { value: 'admin.', label: tk('admin.audit.admins') },
];

export const AuditSection = {
    components: { EmptyState, Notice },
    props: { me: { type: Object, required: true } },
    data: () => ({ entries: null, failed: null, cursor: null, loadingMore: false, action: '', ticket: 0 }),
    setup: () => ({ FILTERS }),
    template: `
        <div class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.audit') }}</h1>
                <div>
                    <label class="sr-only" for="audit-filter">{{ t('admin.audit.filter') }}</label>
                    <select id="audit-filter" v-model="action" class="input !w-auto !py-2" @change="load()">
                        <option v-for="f in FILTERS" :key="f.value" :value="f.value">{{ t(f.label) }}</option>
                    </select>
                </div>
            </div>
            <Notice v-if="failed" tone="bad">{{ failed }} <button type="button" class="link" @click="load()">{{ t('common.tryAgain') }}</button></Notice>
            <p v-else-if="!entries" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!entries.length" icon="list" :title="t('admin.audit.none')" />
            <div v-else class="card overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>{{ t('admin.when') }}</th><th>{{ t('admin.who') }}</th><th>{{ t('admin.audit.action') }}</th><th>{{ t('admin.audit.subject') }}</th><th>{{ t('admin.details') }}</th></tr></thead>
                    <tbody>
                        <tr v-for="entry in entries" :key="entry.id">
                            <td class="whitespace-nowrap text-muted"><time :datetime="entry.createdAt">{{ formatDate(entry.createdAt, { dateStyle: 'medium', timeStyle: 'short' }) }}</time></td>
                            <td class="text-cream" translate="no">{{ entry.actor?.name ?? t('admin.system') }}</td>
                            <td><code class="tabular-nums text-xs text-cream" translate="no">{{ entry.action }}</code></td>
                            <td><code v-if="entry.subject" class="tabular-nums text-xs text-muted" translate="no">{{ entry.subject.type }} {{ entry.subject.id }}</code></td>
                            <td class="text-xs text-muted">
                                <p v-if="entry.reason" class="text-cream" translate="no">{{ entry.reason }}</p>
                                <p v-if="entry.before">{{ t('admin.audit.before') }} <code translate="no">{{ short(entry.before) }}</code></p>
                                <p v-if="entry.after">{{ t('admin.audit.after') }} <code translate="no">{{ short(entry.after) }}</code></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button v-if="cursor && !failed" type="button" class="btn-secondary" :disabled="loadingMore" @click="load(true)">{{ loadingMore ? t('admin.loading') : t('admin.loadMore') }}</button>
        </div>`,
    created() {
        this.load();
    },
    methods: {
        short(value) {
            const text = JSON.stringify(value);
            return text.length > 160 ? `${text.slice(0, 159)}…` : text;
        },
        /** The first page, or the next (`more`); an older answer arriving last is ignored. */
        async load(more = false) {
            const ticket = ++this.ticket;
            this.loadingMore = more;
            try {
                const page = await api.admin.audit.list({ action: this.action, cursor: more ? this.cursor : null });
                if (ticket !== this.ticket) return;
                this.entries = more ? [...this.entries, ...page.data.filter((e) => !this.entries.some((had) => had.id === e.id))] : page.data;
                this.cursor = page.meta.nextCursor;
                this.failed = null;
            } catch (error) {
                if (ticket !== this.ticket || sendToSignIn(error)) return;
                console.error('admin.audit.list failed', error);
                this.failed = explain(error) ?? t('admin.requestFailed');
            } finally {
                if (ticket === this.ticket) this.loadingMore = false;
            }
        },
    },
};
