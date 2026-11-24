// The choir gallery's review (docs/choir-gallery-plan.md, section 4; contract: admin.choirGallery.list, .decide). Every
// song a singer adds is heard here before anyone else hears it: its mix played on the page, its reports beside it, and
// the checklist. Approve, or Decline with a reason: the singer reads a kind sentence for it in their own language (the
// app's words), or staff's own sentence for "other". A held item (a report) is released or taken down for good.

import { api } from '../api.js';
import { t, tk } from '../i18n.js';
import { EmptyState } from '../components/form.js';
import { toast } from '../components/ui.js';
import { LoadFailed, when } from './common.js';

const STATUSES = ['pending', 'held', 'approved', 'declined'];
const REASONS = ['noise', 'broken', 'not_own', 'not_library', 'other'];
const CHECKS = [tk('admin.choirGallery.checks.child'), tk('admin.choirGallery.checks.known'), tk('admin.choirGallery.checks.library'), tk('admin.choirGallery.checks.noise')];
const STATUS_WORDS = { pending: tk('admin.choirGallery.status.pending'), held: tk('admin.choirGallery.status.held'), approved: tk('admin.choirGallery.status.approved'), declined: tk('admin.choirGallery.status.declined') };
const REASON_WORDS = { noise: tk('admin.choirGallery.reasons.noise'), broken: tk('admin.choirGallery.reasons.broken'), not_own: tk('admin.choirGallery.reasons.not_own'), not_library: tk('admin.choirGallery.reasons.not_library'), other: tk('admin.choirGallery.reasons.other') };

export const ChoirGallerySection = {
    components: { EmptyState, LoadFailed },
    props: { me: { type: Object, required: true } },
    data: () => ({ status: 'pending', items: null, failed: false, busy: null, declining: null, reason: 'noise', reasonText: '' }),
    setup: () => ({ STATUSES, REASONS, CHECKS, STATUS_WORDS, REASON_WORDS, when }),
    template: `
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-cream">{{ t('admin.nav.choirGallery') }}</h1>
                <p class="mt-1 max-w-prose text-sm text-muted">{{ t('admin.choirGallery.intro') }}</p>
            </div>
            <div class="seg max-w-full overflow-x-auto" role="radiogroup" :aria-label="t('admin.choirGallery.statusLabel')" data-gallery-review-status>
                <button v-for="s in STATUSES" :key="s" type="button" role="radio" class="seg-option tap-44" :aria-checked="status === s ? 'true' : 'false'" :data-status="s" @click="pick(s)">{{ t(STATUS_WORDS[s]) }}</button>
            </div>
            <section class="card p-5" aria-labelledby="gallery-checks-h" data-gallery-checklist>
                <h2 id="gallery-checks-h" class="font-semibold text-cream">{{ t('admin.choirGallery.checksTitle') }}</h2>
                <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-muted">
                    <li v-for="c in CHECKS" :key="c">{{ t(c) }}</li>
                </ul>
            </section>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!items" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!items.length" icon="sparkle" :title="t('admin.choirGallery.none')" />
            <ul v-else class="space-y-4" data-gallery-review>
                <li v-for="item in items" :key="item.id" class="card space-y-3 p-5" :data-gallery-item="item.id">
                    <div>
                        <h2 class="text-lg font-bold text-cream" translate="no">{{ item.title }}</h2>
                        <p class="text-sm text-muted">{{ t('admin.choirGallery.added', { when: when(item.addedAt), parts: (item.parts ?? []).length }) }}<template v-if="item.changed"> · {{ t('admin.choirGallery.changed') }}</template></p>
                    </div>
                    <audio :src="item.mixUrl" controls preload="none" class="w-full" data-gallery-audio></audio>
                    <p v-for="(r, i) in item.reports ?? []" :key="i" class="text-sm text-cream" data-gallery-report>{{ t('admin.choirGallery.report', { reason: r.reason, when: when(r.at) }) }}</p>
                    <p v-if="item.decision" class="text-sm text-muted">{{ t('admin.choirGallery.decided', { status: item.decision.status, reason: item.decision.reasonText || item.decision.reason || '', when: when(item.decision.at) }) }}</p>
                    <div v-if="declining === item.id" class="space-y-3" data-gallery-decline>
                        <label class="block text-sm font-semibold text-cream">{{ t('admin.choirGallery.reasonLabel') }}
                            <select v-model="reason" class="input mt-1 w-full" data-gallery-reason>
                                <option v-for="r in REASONS" :key="r" :value="r">{{ t(REASON_WORDS[r]) }}</option>
                            </select>
                        </label>
                        <label v-if="reason === 'other'" class="block text-sm font-semibold text-cream">{{ t('admin.choirGallery.reasonText') }}
                            <textarea v-model="reasonText" rows="2" maxlength="300" class="input mt-1 w-full" data-gallery-reason-text></textarea>
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn-primary btn-sm" :disabled="busy === item.id || (reason === 'other' && !reasonText.trim())" data-gallery-decline-send @click="decide(item, item.status === 'pending' ? 'decline' : 'takedown')">{{ item.status === 'pending' ? t('admin.choirGallery.decline') : t('admin.choirGallery.takedown') }}</button>
                            <button type="button" class="btn-secondary btn-sm" data-gallery-decline-cancel @click="declining = null">{{ t('admin.choirGallery.cancel') }}</button>
                        </div>
                    </div>
                    <div v-else-if="item.status === 'pending' || item.status === 'held'" class="flex flex-wrap gap-2">
                        <button type="button" class="btn-primary btn-sm" :disabled="busy === item.id" :data-gallery-approve="item.id" @click="decide(item, item.status === 'held' ? 'release' : 'approve')">{{ item.status === 'held' ? t('admin.choirGallery.release') : t('admin.choirGallery.approve') }}</button>
                        <button type="button" class="btn-secondary btn-sm" :disabled="busy === item.id" :data-gallery-decline-open="item.id" @click="openDecline(item)">{{ item.status === 'held' ? t('admin.choirGallery.takedown') : t('admin.choirGallery.decline') }}</button>
                    </div>
                    <div v-else-if="item.status === 'approved'">
                        <button type="button" class="btn-secondary btn-sm" :data-gallery-decline-open="item.id" @click="openDecline(item)">{{ t('admin.choirGallery.takedown') }}</button>
                    </div>
                </li>
            </ul>
        </div>`,
    mounted() {
        this.load();
    },
    methods: {
        pick(status) {
            this.status = status;
            this.load();
        },
        async load() {
            this.failed = false;
            this.items = null;
            try {
                this.items = (await api.admin.choirGallery.list({ status: this.status })).data;
            } catch (error) {
                console.warn('The choir gallery queue did not load', error);
                this.failed = t('admin.loadFailed');
            }
        },
        openDecline(item) {
            this.declining = item.id;
            this.reason = 'noise';
            this.reasonText = '';
        },
        async decide(item, decision) {
            this.busy = item.id;
            const declining = decision === 'decline' || decision === 'takedown';
            try {
                await api.admin.choirGallery.decide(item.id, {
                    decision,
                    reason: declining ? this.reason : null,
                    reasonText: declining && this.reason === 'other' ? this.reasonText.trim() : null,
                });
                this.items = this.items.filter((i) => i.id !== item.id);
                this.declining = null;
                toast(t('admin.saved'));
            } catch (error) {
                console.warn('The choir gallery decision was not saved', error);
                toast(t('admin.requestFailed'));
            } finally {
                this.busy = null;
            }
        },
    },
};
