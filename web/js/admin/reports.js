// Reports and takedowns (docs/trust-and-safety-plan.md, "Reports"; the copyright policy): shared
// harmonies with open reports, hidden by reports, or with a held title, oldest first; each with
// its player, its owner (and their takedowns before), and its reports. Hide takes the page down
// (for a copyright notice or by Hillel), Restore puts it back as the owner left it (on one that
// isn't hidden, it dismisses the reports and releases a held title), and Remove deletes its files
// for good (in sudo). Each asks for a note, which the audit log keeps.

import { api } from '../api.js';
import { t, tk } from '../i18n.js';
import { EmptyState, Field, StatusChip } from '../components/form.js';
import { toast } from '../components/ui.js';
import { ChangeSheet, errorsFrom, LoadFailed, loadInto, refused, when } from './common.js';

const REASONS = {
    copyright: tk('admin.reports.reason.copyright'),
    offensive: tk('admin.reports.reason.offensive'),
    spam: tk('admin.reports.reason.spam'),
    personal: tk('admin.reports.reason.personal'),
    other: tk('admin.reports.reason.other'),
};
const MODERATION = { under_review: tk('admin.reports.underReview'), hidden: tk('admin.reports.hidden'), removed: tk('admin.reports.removed') };
const ACTIONS = { hide: tk('admin.reports.hideTitle'), restore: tk('admin.reports.restoreTitle'), dismiss: tk('admin.reports.dismissTitle'), remove: tk('admin.reports.removeTitle') };

export const ReportsSection = {
    components: { ChangeSheet, EmptyState, Field, LoadFailed, StatusChip },
    props: { me: { type: Object, required: true } },
    data: () => ({ items: null, failed: false, cursor: null, loadingMore: false, status: 'open', change: null, takedown: 'dmca', busy: false, errors: null }),
    setup: () => ({ REASONS, MODERATION, ACTIONS, when }),
    template: `
        <div class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.reports') }}</h1>
                <div class="seg flex" role="radiogroup" :aria-label="t('admin.reports.show')">
                    <button v-for="s in ['open', 'closed']" :key="s" type="button" role="radio" :aria-checked="status === s" class="seg-option" @click="status = s; load()">{{ s === 'open' ? t('admin.reports.open') : t('admin.reports.closed') }}</button>
                </div>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load()" />
            <p v-else-if="!items" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!items.length" icon="shield" :title="status === 'open' ? t('admin.reports.noneOpen') : t('admin.reports.noneClosed')" />
            <ul v-else class="space-y-4">
                <li v-for="item in items" :key="item.harmony.id" class="card space-y-4 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a :href="item.harmony.pageUrl" target="_blank" rel="noopener" class="font-bold text-cream hover:underline" translate="no">{{ item.harmony.title || t('project.untitled') }}</a>
                            <p class="text-sm text-muted"><span translate="no">{{ item.harmony.owner?.name ?? '' }}</span><template v-if="item.harmony.ownerTakedowns"> · {{ t('admin.reports.takedowns', { count: item.harmony.ownerTakedowns }) }}</template><template v-if="item.firstReportedAt"> · {{ t('admin.reports.firstReported', { when: when(item.firstReportedAt) }) }}</template></p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <StatusChip v-if="MODERATION[item.harmony.moderation]" :tone="item.harmony.moderation === 'under_review' ? 'warn' : 'bad'" :label="t(MODERATION[item.harmony.moderation])" />
                            <StatusChip v-if="item.harmony.titleHeld" tone="warn" :label="t('admin.reports.titleHeld')" />
                        </div>
                    </div>
                    <audio v-if="item.harmony.audioUrl" controls preload="none" :src="item.harmony.audioUrl" class="w-full"></audio>
                    <ul class="space-y-2 text-sm">
                        <li v-for="report in item.reports" :key="report.id" class="rounded-2xl bg-raised p-3">
                            <p class="text-cream"><span class="font-semibold">{{ REASONS[report.reason] ? t(REASONS[report.reason]) : report.reason }}</span> · <span class="text-muted">{{ report.reporter ? report.reporter.name : t('admin.reports.signedOut') }} · {{ when(report.createdAt) }}</span></p>
                            <p v-if="report.note" class="mt-1 whitespace-pre-line text-cream" translate="no">{{ report.note }}</p>
                        </li>
                    </ul>
                    <div v-if="item.harmony.moderation !== 'removed'" class="flex flex-wrap gap-3">
                        <button v-if="item.harmony.moderation !== 'hidden'" type="button" class="btn-secondary btn-sm" @click="open(item, 'hide')">{{ t('admin.reports.hide') }}</button>
                        <button type="button" class="btn-secondary btn-sm" @click="open(item, item.harmony.moderation ? 'restore' : 'dismiss')">{{ item.harmony.moderation ? t('admin.reports.restore') : t('admin.reports.dismiss') }}</button>
                        <button type="button" class="btn-ghost btn-sm text-bad" @click="open(item, 'remove')">{{ t('admin.reports.remove') }}</button>
                    </div>
                </li>
            </ul>
            <button v-if="cursor && !failed" type="button" class="btn-secondary" :disabled="loadingMore" @click="load(true)">{{ loadingMore ? t('admin.loading') : t('admin.loadMore') }}</button>
            <ChangeSheet v-if="change" :title="t(ACTIONS[change.action], { title: change.item.harmony.title || t('project.untitled') })" :me="me" :busy="busy" :errors="errors" :fields="['reason', 'note', 'takedown']"
                :reasoned="change.action !== 'restore' && change.action !== 'dismiss'" :needs-sudo="change.action === 'remove'" @close="change = null" @confirm="apply">
                <p class="text-sm text-muted">{{ change.action === 'remove' ? t('admin.reports.removeHint') : change.action === 'hide' ? t('admin.reports.hideHint') : t('admin.reports.restoreHint') }}</p>
                <Field v-if="change.action === 'hide' || change.action === 'remove'" :label="t('admin.reports.why')" :error="errors?.takedown" v-slot="f">
                    <select :id="f.id" v-model="takedown" class="input">
                        <option value="dmca">{{ t('admin.reports.dmca') }}</option>
                        <option value="admin">{{ t('admin.reports.byAdmin') }}</option>
                    </select>
                </Field>
            </ChangeSheet>
        </div>`,
    created() {
        this.load();
    },
    methods: {
        load(more = false) {
            return loadInto(this, 'items', () => api.admin.reports.list({ status: this.status, cursor: more ? this.cursor : undefined }), { more });
        },
        open(item, action) {
            this.errors = null;
            this.takedown = item.reports.some((r) => r.reason === 'copyright') ? 'dmca' : 'admin';
            this.change = { item, action };
        },
        /** The sheet's text is the note the audit log keeps (the API's `note`). */
        async apply(note) {
            const { item, action } = this.change;
            this.busy = true;
            try {
                const id = item.harmony.id;
                const { data } =
                    action === 'hide' ? await api.admin.harmonies.hide(id, { reason: this.takedown, note })
                    : action === 'remove' ? await api.admin.harmonies.remove(id, { reason: this.takedown, note })
                    : await api.admin.harmonies.restore(id, note ? { note } : {});
                Object.assign(item.harmony, data.harmony ?? data);
                if (action !== 'hide') this.items = this.items.filter((i) => i !== item); // dealt with
                toast(t('admin.saved'));
                this.change = null;
            } catch (error) {
                // The sheet's field is `reason`, the API's is `note`: their messages meet there.
                const errors = errorsFrom(error);
                this.errors = errors ? { takedown: errors.reason, reason: errors.note } : null;
                if (!this.errors) refused(error);
            } finally {
                this.busy = false;
            }
        },
    },
};
