// Admin > Content > Requests (docs/more-features-plan.md section 8, N7; contract admin.requests.*): every song
// request, held ones first (words the filter held, or two people's reports), then by votes, each with its
// reports' count. Approve a held one, Hide, set where it stands, mark it Ready with the song it became
// (that tells everyone who asked or voted), or Merge it into another (its votes move, one each).

import { ApiError, api } from '../api.js';
import { formatNumber, t, tk } from '../i18n.js';
import { EmptyState, StatusChip } from '../components/form.js';
import { toast } from '../components/ui.js';
import { LoadFailed, loadInto, refused, when } from './common.js';

const STATUS = {
    requested: tk('requests.status.requested'),
    checking: tk('requests.status.checking'),
    arranging: tk('requests.status.arranging'),
    ready: tk('requests.status.ready'),
    cant: tk('requests.status.cant'),
};

export const RequestsSection = {
    components: { EmptyState, LoadFailed, StatusChip },
    props: { me: { type: Object, required: true } },
    data: () => ({ requests: null, cursor: null, failed: false, loadingMore: false, busy: null, ready: {}, merge: {} }),
    setup: () => ({ STATUS, formatNumber, when }),
    template: `
        <section class="space-y-6" aria-labelledby="requests-h" data-admin-requests>
            <div>
                <h1 id="requests-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.requests.title') }}</h1>
                <p class="mt-1 max-w-prose text-sm text-muted">{{ t('admin.requests.intro') }}</p>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!requests" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!requests.length" icon="list" :title="t('admin.requests.none')" />
            <ul v-else class="space-y-3">
                <li v-for="r in requests" :key="r.id" class="card space-y-3 p-5" :data-admin-request="r.id">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-bold text-cream" translate="no">{{ r.title }}<span v-if="r.writer" class="font-normal text-muted"> · {{ r.writer }}</span></p>
                            <p v-if="r.why" class="text-sm text-muted" translate="no">{{ r.why }}</p>
                            <p class="text-xs text-muted">{{ t('admin.requests.line', { votes: formatNumber(r.votes), when: when(r.createdAt) }) }}</p>
                        </div>
                        <span class="flex flex-wrap gap-2">
                            <StatusChip v-if="r.held" tone="warn" :label="t('admin.requests.held')" data-request-held />
                            <StatusChip v-if="r.hidden" tone="muted" :label="t('admin.requests.hidden')" />
                            <StatusChip v-if="r.mergedInto" tone="muted" :label="t('admin.requests.merged')" />
                            <StatusChip v-if="r.reports" tone="bad" :label="t('admin.requests.reports', { count: r.reports })" />
                        </span>
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <label class="block space-y-1.5"><span class="label">{{ t('admin.requests.status') }}</span>
                            <select class="input !w-auto" :value="r.status" :disabled="busy === r.id" :data-request-status-select="r.id" @change="setStatus(r, $event.target.value)">
                                <option v-for="(label, s) in STATUS" :key="s" :value="s" :disabled="s === 'ready'">{{ t(label) }}</option>
                            </select>
                        </label>
                        <form class="flex flex-wrap items-end gap-2" @submit.prevent="markReady(r)">
                            <label class="block space-y-1.5"><span class="label">{{ t('admin.requests.readyAs') }}</span><input v-model.trim="ready[r.id]" class="input !w-44" :placeholder="t('admin.requests.readyHint')" translate="no" /></label>
                            <button type="submit" class="btn-secondary btn-sm" :disabled="busy === r.id || !ready[r.id]">{{ t('admin.requests.ready') }}</button>
                        </form>
                        <form class="flex flex-wrap items-end gap-2" @submit.prevent="mergeInto(r)">
                            <label class="block space-y-1.5"><span class="label">{{ t('admin.requests.mergeInto') }}</span>
                                <select v-model="merge[r.id]" class="input !w-auto">
                                    <option :value="undefined" disabled>{{ t('admin.requests.mergePick') }}</option>
                                    <option v-for="o in requests.filter((x) => x.id !== r.id && !x.mergedInto)" :key="o.id" :value="o.id" translate="no">{{ o.title }}</option>
                                </select>
                            </label>
                            <button type="submit" class="btn-secondary btn-sm" :disabled="busy === r.id || !merge[r.id]">{{ t('admin.requests.merge') }}</button>
                        </form>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="r.held" type="button" class="btn-primary btn-sm" :disabled="busy === r.id" data-request-approve @click="change(r, { approve: true })">{{ t('admin.requests.approve') }}</button>
                        <button type="button" class="btn-ghost btn-sm" :class="r.hidden ? '' : 'text-bad'" :disabled="busy === r.id" data-request-hide @click="change(r, { hidden: !r.hidden })">{{ r.hidden ? t('admin.requests.show') : t('admin.requests.hide') }}</button>
                    </div>
                </li>
            </ul>
            <button v-if="cursor && !failed" type="button" class="btn-secondary" :disabled="loadingMore" @click="load(true)">{{ loadingMore ? t('admin.loading') : t('admin.loadMore') }}</button>
        </section>`,
    created() {
        this.load();
    },
    methods: {
        load(more = false) {
            return loadInto(this, 'requests', () => api.admin.requests.list({ cursor: more ? this.cursor : undefined }), { more });
        },
        async change(r, fields, done = t('admin.saved')) {
            this.busy = r.id;
            try {
                const saved = (await api.admin.requests.update(r.id, fields)).data;
                this.requests = this.requests.map((x) => (x.id === r.id ? saved : x));
                toast(done);
                return true;
            } catch (error) {
                if (error instanceof ApiError && error.code === 'validation_failed') toast(Object.values(error.errors ?? {}).flat().join(' ') || t('admin.requestFailed'), { tone: 'error' });
                else refused(error);
                return false;
            } finally {
                this.busy = null;
            }
        },
        setStatus(r, status) {
            return this.change(r, { status });
        },
        /** Ready with the song it became: everyone who asked or voted is told, once. */
        async markReady(r) {
            if (await this.change(r, { status: 'ready', templateId: this.ready[r.id] }, t('admin.requests.readyDone'))) this.ready[r.id] = '';
        },
        async mergeInto(r) {
            if (await this.change(r, { mergeInto: this.merge[r.id] }, t('admin.requests.merged'))) await this.load();
        },
    },
};
