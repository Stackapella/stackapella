// Appeals (Inbox > Appeals; contract/api.md admin.appeals.*, the DSA's internal complaint handling; the finish-line audit,
// 2026-11-13): someone told what was done to what they shared, or to their account, asked for a review. Waiting ones
// oldest first, or the decided ones. Each shows the notice as the person saw it, what they wrote, and Decide: keep it or
// reverse it (which undoes it where it can), with a note the person reads, in sudo.

import { api } from '../api.js';
import { t, tk } from '../i18n.js';
import { EmptyState, Field, Notice, StatusChip } from '../components/form.js';
import { Modal, toast } from '../components/ui.js';
import { KINDS, RULES } from '../notice-words.js';
import { inSudo } from './auth.js';
import { errorsFrom, LoadFailed, loadInto, refused, when } from './common.js';

export const STATUSES = ['waiting', 'decided'];
const STATUS_NAMES = { waiting: tk('admin.appeals.status.waiting'), decided: tk('admin.appeals.status.decided') };
export const DECISIONS = {
    kept: { word: tk('admin.appeals.kept'), tone: 'muted' },
    reversed: { word: tk('admin.appeals.reversed'), tone: 'good' },
};
const REFUSALS = { appeal_decided: tk('admin.appeals.alreadyDecided'), cannot_reverse: tk('admin.appeals.cannotReverse') };

export const AppealsSection = {
    components: { EmptyState, Field, LoadFailed, Modal, Notice, StatusChip },
    props: { me: { type: Object, required: true } },
    emits: ['changed'],
    data: () => ({ status: 'waiting', items: null, failed: false, cursor: null, loadingMore: false, deciding: null, decision: 'kept', note: '', busy: false, errors: null, refusal: null }),
    setup: () => ({ STATUSES, STATUS_NAMES, DECISIONS, KINDS, RULES, when }),
    computed: {
        sudo: (vm) => inSudo(vm.me),
    },
    template: `
        <div class="space-y-6" data-appeals>
            <div class="space-y-1">
                <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.appeals') }}</h1>
                <p class="max-w-prose text-sm text-muted">{{ t('admin.appeals.intro') }}</p>
            </div>
            <div class="flex flex-wrap gap-2" role="radiogroup" :aria-label="t('admin.appeals.statusLabel')">
                <button v-for="s in STATUSES" :key="s" type="button" role="radio" class="filter-chip" :aria-checked="status === s ? 'true' : 'false'" :data-status="s" @click="setStatus(s)">{{ t(STATUS_NAMES[s]) }}</button>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load()" />
            <p v-else-if="!items" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!items.length" icon="check" :title="status === 'waiting' ? t('admin.appeals.noneWaiting') : t('admin.appeals.none')" />
            <ul v-else class="space-y-3" data-appeals-list>
                <li v-for="item in items" :key="item.id" class="card space-y-3 p-5" :data-appeal="item.id">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <p class="min-w-0 text-sm text-muted">
                            <a v-if="item.person" :href="'/admin/people/' + item.person.id" class="link font-semibold" translate="no">{{ item.person.name }}</a>
                            · {{ KINDS[item.kind] ? t(KINDS[item.kind].title) : item.kind }}<template v-if="item.about?.title"> · <span translate="no">{{ item.about.title }}</span></template>
                            · {{ t('admin.appeals.noticeOn', { when: when(item.createdAt) }) }}<template v-if="item.automatic"> · {{ t('admin.appeals.automatic') }}</template>
                        </p>
                        <StatusChip v-if="item.appeal?.decision" :tone="DECISIONS[item.appeal.decision].tone" :label="t(DECISIONS[item.appeal.decision].word)" data-appeal-decision />
                    </div>
                    <p class="text-sm text-muted"><span class="font-semibold text-cream">{{ t('admin.appeals.why') }}</span> {{ RULES[item.rule] ? t(RULES[item.rule]) : item.why }}<template v-if="item.note"> {{ t('admin.appeals.ourNote') }} <span class="text-cream" translate="no">{{ item.note }}</span></template></p>
                    <blockquote class="rounded-xl bg-raised p-3 text-cream" translate="no" data-appeal-text>
                        <p class="mb-1 text-xs text-muted">{{ t('admin.appeals.theyWrote', { when: when(item.appeal.at) }) }}</p>
                        <p class="whitespace-pre-line break-words">{{ item.appeal.text }}</p>
                    </blockquote>
                    <p v-if="item.appeal?.decision" class="text-sm text-muted" data-appeal-decided>{{ t('admin.appeals.decidedOn', { when: when(item.appeal.decidedAt) }) }}<template v-if="item.appeal.decisionNote">: <span class="text-cream" translate="no">{{ item.appeal.decisionNote }}</span></template></p>
                    <button v-else type="button" class="btn-secondary btn-sm" data-decide @click="open(item)">{{ t('admin.appeals.decide') }}</button>
                </li>
            </ul>
            <button v-if="cursor && !failed" type="button" class="btn-secondary" :disabled="loadingMore" @click="load(true)">{{ loadingMore ? t('admin.loading') : t('admin.loadMore') }}</button>

            <Modal v-if="deciding" :title="t('admin.appeals.decideTitle', { name: deciding.person?.name ?? '' })" :dismissable="!busy" @close="close">
                <form class="space-y-5" data-appeals-sheet @submit.prevent="decide">
                    <blockquote class="rounded-xl bg-raised p-3 text-sm text-cream" translate="no"><span class="line-clamp-4 whitespace-pre-line">{{ deciding.appeal.text }}</span></blockquote>
                    <div class="space-y-2" role="radiogroup" :aria-label="t('admin.appeals.decisionLabel')">
                        <label v-for="(d, key) in DECISIONS" :key="key" class="flex min-h-11 items-start gap-3 rounded-xl border border-line p-3 text-cream">
                            <input v-model="decision" type="radio" name="appeal-decision" :value="key" class="mt-1" :data-decision="key" />
                            <span><span class="block font-semibold">{{ t(d.word) }}</span><span class="block text-sm text-muted">{{ key === 'kept' ? t('admin.appeals.keptHint') : t('admin.appeals.reversedHint') }}</span></span>
                        </label>
                    </div>
                    <Field :label="t('admin.appeals.note')" :help="t('admin.appeals.noteHelp')" :error="errors?.note" required v-slot="f">
                        <textarea :id="f.id" v-model="note" rows="3" maxlength="1000" required class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid" data-appeal-note></textarea>
                    </Field>
                    <Notice v-if="refusal" tone="bad">{{ refusal }}</Notice>
                    <p v-else-if="errors && !errors.note" class="text-sm text-bad" role="alert">{{ t('admin.requestFailed') }}</p>
                    <Notice v-if="!sudo" tone="info">{{ t('admin.sudoFirst') }}</Notice>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="btn-secondary" :disabled="busy" @click="close">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary" :disabled="busy || !note.trim()" data-appeal-confirm>{{ busy ? t('common.saving') : decision === 'kept' ? t('admin.appeals.keepIt') : t('admin.appeals.reverseIt') }}</button>
                    </div>
                </form>
            </Modal>
        </div>`,
    created() {
        this.load();
    },
    methods: {
        load(more = false) {
            return loadInto(this, 'items', () => api.admin.appeals.list({ status: this.status === 'waiting' ? undefined : this.status, cursor: more ? this.cursor : undefined }), { more });
        },
        setStatus(status) {
            this.status = status;
            this.load();
        },
        open(item) {
            Object.assign(this, { deciding: item, decision: 'kept', note: '', errors: null, refusal: null });
        },
        close() {
            if (!this.busy) this.deciding = null;
        },
        async decide() {
            const item = this.deciding;
            this.busy = true;
            this.errors = null;
            this.refusal = null;
            try {
                const { data } = await api.admin.appeals.decide(item.id, { decision: this.decision, note: this.note.trim() });
                // Decided, it leaves the waiting list; the inbox's count follows (the shell asks again).
                this.items = this.status === 'waiting' ? this.items.filter((had) => had.id !== item.id) : this.items.map((had) => (had.id === item.id ? { ...had, ...data } : had));
                this.deciding = null;
                toast(data.appeal?.decision === 'reversed' ? t('admin.appeals.savedReversed') : t('admin.appeals.savedKept'));
                this.$emit('changed');
            } catch (error) {
                if (REFUSALS[error?.code]) this.refusal = t(REFUSALS[error.code]);
                else this.errors = errorsFrom(error) ?? {};
                refused(error);
            } finally {
                this.busy = false;
            }
        },
    },
};
