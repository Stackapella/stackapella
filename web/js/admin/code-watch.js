// Admin > System > Code watch (docs/code-watch-plan.md): public repositories holding our marker strings
// (a weekly GitHub code search) and other websites calling our API (over 20 calls a day, with the
// last 30 days' count), as the server lists them (admin.codeWatch.list): repository names and domains
// only, nothing about a person. Each can carry a note and be dismissed (and brought back); dismissed
// ones sit in their own list below. The setup line says whether the search can run at all.

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { LoadFailed } from './common.js';
import { toast } from '../components/ui.js';

const KINDS = { marker: tk('admin.codeWatch.kind.marker'), domain: tk('admin.codeWatch.kind.domain') };

export const CodeWatchSection = {
    components: { LoadFailed },
    props: { me: { type: Object, required: true } },
    data: () => ({ report: null, failed: false, editing: null, note: '', saving: null }),
    setup: () => ({ KINDS, formatNumber }),
    template: `
        <section class="space-y-6" aria-labelledby="codewatch-h" data-code-watch>
            <div>
                <h1 id="codewatch-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.codeWatch.title') }}</h1>
                <p class="mt-1 max-w-prose text-sm text-muted">{{ t('admin.codeWatch.intro') }}</p>
            </div>
            <LoadFailed v-if="failed" :message="t('admin.codeWatch.failed')" @retry="load" />
            <p v-else-if="!report" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <p class="text-sm" :class="report.setup.search && report.setup.markers ? 'text-muted' : 'text-warn'" data-code-watch-setup>{{ setupLine }}</p>
                <section v-for="list in lists" :key="list.key" class="space-y-3" :aria-labelledby="'codewatch-' + list.key">
                    <h2 :id="'codewatch-' + list.key" class="text-lg font-bold text-cream">{{ t(list.title, { count: list.hits.length }) }}</h2>
                    <p v-if="!list.hits.length" class="text-sm text-muted">{{ t(list.empty) }}</p>
                    <ul v-else class="space-y-3">
                        <li v-for="h in list.hits" :key="h.id" class="card space-y-2 p-5" :data-hit="h.key" :data-kind="h.kind">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-muted">{{ t(KINDS[h.kind] ?? KINDS.domain) }}</p>
                                    <a v-if="h.url" :href="h.url" target="_blank" rel="noopener noreferrer" class="link tap-44 break-all font-semibold" translate="no">{{ h.key }}</a>
                                    <p v-else class="break-all font-semibold text-cream" translate="no">{{ h.key }}</p>
                                </div>
                                <p class="shrink-0 text-sm text-muted sm:text-end">{{ t('admin.codeWatch.seen', { first: day(h.firstSeenAt), last: day(h.lastSeenAt) }) }}<span v-if="h.calls != null" class="block font-semibold text-cream">{{ t('admin.codeWatch.calls', { count: formatNumber(h.calls) }) }}</span></p>
                            </div>
                            <form v-if="editing === h.id" class="space-y-2" @submit.prevent="save(h, { note: note.trim() || null })">
                                <textarea v-model="note" rows="2" maxlength="1000" class="input" :aria-label="t('admin.codeWatch.note')"></textarea>
                                <div class="flex gap-3">
                                    <button type="submit" class="btn-primary btn-sm" :disabled="saving === h.id">{{ saving === h.id ? t('common.saving') : t('common.save') }}</button>
                                    <button type="button" class="btn-secondary btn-sm" @click="editing = null">{{ t('common.cancel') }}</button>
                                </div>
                            </form>
                            <p v-else-if="h.note" class="text-sm text-cream" data-hit-note>{{ h.note }}</p>
                            <div v-if="editing !== h.id" class="flex flex-wrap gap-3">
                                <button type="button" class="btn-secondary btn-sm" @click="editing = h.id; note = h.note ?? ''">{{ h.note ? t('admin.codeWatch.editNote') : t('admin.codeWatch.addNote') }}</button>
                                <button type="button" class="tap-44 btn-ghost btn-sm" :disabled="saving === h.id" :data-hit-dismiss="h.dismissedAt ? null : ''" :data-hit-restore="h.dismissedAt ? '' : null" @click="save(h, { dismissed: !h.dismissedAt })">{{ h.dismissedAt ? t('admin.codeWatch.restore') : t('admin.codeWatch.dismiss') }}</button>
                            </div>
                        </li>
                    </ul>
                </section>
            </template>
        </section>`,
    computed: {
        lists() {
            const hits = this.report?.hits ?? [];
            return [
                { key: 'open', title: tk('admin.codeWatch.open'), empty: tk('admin.codeWatch.noneOpen'), hits: hits.filter((h) => !h.dismissedAt) },
                { key: 'dismissed', title: tk('admin.codeWatch.dismissed'), empty: tk('admin.codeWatch.noneDismissed'), hits: hits.filter((h) => h.dismissedAt) },
            ];
        },
        setupLine() {
            const { markers, search } = this.report.setup;
            if (!search) return t('admin.codeWatch.noSearch');
            if (!markers) return t('admin.codeWatch.noMarkers');
            return t('admin.codeWatch.setup', { count: markers });
        },
    },
    created() {
        this.load();
    },
    methods: {
        day(at) {
            return at ? formatDate(at, { dateStyle: 'medium' }) : '-';
        },
        async load() {
            this.failed = false;
            try {
                this.report = (await api.admin.codeWatch.list()).data;
            } catch (error) {
                console.warn('Code watch did not load', error);
                this.failed = true;
            }
        },
        async save(hit, body) {
            this.saving = hit.id;
            try {
                const saved = (await api.admin.codeWatch.update(hit.id, body)).data;
                this.report = { ...this.report, hits: this.report.hits.map((h) => (h.id === hit.id ? saved : h)) };
                this.editing = null;
            } catch (error) {
                console.warn('The code watch change was not saved', error);
                toast(t('admin.codeWatch.notSaved'), { tone: 'error' });
            } finally {
                this.saving = null;
            }
        },
    },
};
