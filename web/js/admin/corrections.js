// The Corrections inbox (Inbox > Corrections; docs/corrections-plan.md): what readers sent through "Suggest a
// correction" on a song, part, course or tool page, newest first. State and Kind filters, remembered; each item opens
// its page in a new tab, to read it in context; Accept (we'll fix it), Reject and Done (fixed), each with a note, in a
// styled sheet that names the action. Accepting leads to Done once it's fixed.

import { api } from '../api.js';
import { t, tk } from '../i18n.js';
import { EmptyState, Field, StatusChip } from '../components/form.js';
import { Modal, toast } from '../components/ui.js';
import { errorsFrom, LoadFailed, loadInto, refused, remembers, when } from './common.js';

export const STATES = ['new', 'accepted', 'done', 'rejected', 'all'];
export const KINDS = ['all', 'fact', 'words', 'notes', 'other'];
const STATE_NAMES = { new: tk('admin.corrections.state.new'), accepted: tk('admin.corrections.state.accepted'), done: tk('admin.corrections.state.done'), rejected: tk('admin.corrections.state.rejected'), all: tk('admin.corrections.state.all') };
const KIND_NAMES = { all: tk('admin.corrections.kind.all'), fact: tk('admin.corrections.kind.fact'), words: tk('admin.corrections.kind.words'), notes: tk('admin.corrections.kind.notes'), other: tk('admin.corrections.kind.other') };
const PAGE_TYPES = { song: tk('admin.corrections.page.song'), part: tk('admin.corrections.page.part'), course: tk('admin.corrections.page.course'), lesson: tk('admin.corrections.page.lesson'), tool: tk('admin.corrections.page.tool') };
const TONES = { new: 'info', accepted: 'warn', done: 'good', rejected: 'muted' };
// What each action asks, and whether its note is required: a refusal always says why.
export const ACTIONS = {
    accepted: { title: tk('admin.corrections.act.acceptTitle'), button: tk('admin.corrections.act.accept'), note: tk('admin.corrections.act.acceptNote'), required: false },
    rejected: { title: tk('admin.corrections.act.rejectTitle'), button: tk('admin.corrections.act.reject'), note: tk('admin.corrections.act.rejectNote'), required: true },
    done: { title: tk('admin.corrections.act.doneTitle'), button: tk('admin.corrections.act.done'), note: tk('admin.corrections.act.doneNote'), required: false },
};

/** The actions open to an item in its state: a new one is accepted or rejected; an accepted one is done (or, after all, rejected). */
export function actionsFor(state) {
    return { new: ['accepted', 'rejected'], accepted: ['done', 'rejected'] }[state] ?? [];
}

export const CorrectionsSection = {
    mixins: [remembers('corrections', { state: STATES, kind: KINDS })],
    components: { EmptyState, Field, LoadFailed, Modal, StatusChip },
    props: { me: { type: Object, required: true } },
    emits: ['changed'],
    data: () => ({ state: 'new', kind: 'all', items: null, failed: false, cursor: null, loadingMore: false, acting: null, note: '', busy: false, errors: null }),
    setup: () => ({ STATES, KINDS, STATE_NAMES, KIND_NAMES, PAGE_TYPES, TONES, ACTIONS, actionsFor, when }),
    template: `
        <div class="space-y-6" data-corrections>
            <div class="space-y-1">
                <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.corrections') }}</h1>
                <p class="max-w-prose text-sm text-muted">{{ t('admin.corrections.intro') }}</p>
            </div>
            <!-- State as a compact menu; Kind as chips from sm up, and on a phone a menu too (five options: design rules). -->
            <div class="flex flex-wrap items-start gap-x-6 gap-y-3">
                <label class="grid gap-1 text-sm font-semibold text-cream">{{ t('admin.corrections.stateLabel') }}
                    <select v-model="state" class="input w-auto min-w-40" data-corrections-state @change="load()">
                        <option v-for="s in STATES" :key="s" :value="s">{{ t(STATE_NAMES[s]) }}</option>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold text-cream sm:hidden">{{ t('admin.corrections.kindLabel') }}
                    <select :value="kind" class="input w-auto min-w-40" data-corrections-kind-select @change="setKind($event.target.value)">
                        <option v-for="k in KINDS" :key="k" :value="k">{{ t(KIND_NAMES[k]) }}</option>
                    </select>
                </label>
                <div class="hidden gap-1 sm:grid">
                    <span id="corrections-kind-l" class="text-sm font-semibold text-cream">{{ t('admin.corrections.kindLabel') }}</span>
                    <div class="flex min-h-11 flex-wrap items-center gap-2" role="radiogroup" aria-labelledby="corrections-kind-l" data-corrections-kind>
                        <button v-for="k in KINDS" :key="k" type="button" role="radio" class="filter-chip" :aria-checked="kind === k ? 'true' : 'false'" :data-kind="k" @click="setKind(k)">{{ t(KIND_NAMES[k]) }}</button>
                    </div>
                </div>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load()" />
            <p v-else-if="!items" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!items.length" icon="info" :title="state === 'new' ? t('admin.corrections.noneNew') : t('admin.corrections.none')" />
            <ul v-else class="space-y-3" data-corrections-list>
                <li v-for="item in items" :key="item.id" class="card p-5" :data-correction="item.id" :data-state="item.state">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <p class="min-w-0 text-sm text-muted">
                            <a :href="item.page.path" target="_blank" rel="noopener" class="link font-semibold" translate="no" data-correction-page>{{ item.page.title }}<span class="sr-only"> {{ t('admin.corrections.newTab') }}</span><span aria-hidden="true" class="ms-1">↗</span></a>
                            · {{ PAGE_TYPES[item.page.type] ? t(PAGE_TYPES[item.page.type]) : item.page.type }} · {{ when(item.createdAt) }}<template v-if="item.lang && item.lang !== 'en'"> · <span class="uppercase" data-correction-lang>{{ item.lang }}</span></template>
                        </p>
                        <span class="flex flex-wrap gap-2">
                            <StatusChip tone="muted" :label="t(KIND_NAMES[item.kind] ?? KIND_NAMES.other)" data-correction-kind />
                            <StatusChip :tone="TONES[item.state] ?? 'muted'" :label="t(STATE_NAMES[item.state] ?? STATE_NAMES.new)" data-correction-state />
                        </span>
                    </div>
                    <p class="mt-3 whitespace-pre-line break-words text-cream" translate="no" :lang="item.lang || null" data-correction-text>{{ item.text }}</p>
                    <p v-if="item.source" class="mt-2 text-sm"><span class="text-muted">{{ t('admin.corrections.source') }}</span> <a :href="item.source" target="_blank" rel="noopener nofollow" class="link break-all" translate="no" data-correction-source>{{ item.source }}</a></p>
                    <p v-if="item.decision" class="mt-3 border-t border-line pt-3 text-sm text-muted" data-correction-decision>
                        {{ t('admin.corrections.decided', { state: t(STATE_NAMES[item.decision.state] ?? STATE_NAMES.new), name: item.decision.by?.name ?? '', when: when(item.decision.at) }) }}<template v-if="item.decision.note">: <span class="text-cream" translate="no">{{ item.decision.note }}</span></template>
                    </p>
                    <div v-if="actionsFor(item.state).length" class="mt-4 flex flex-wrap gap-3">
                        <button v-for="action in actionsFor(item.state)" :key="action" type="button" class="btn-secondary btn-sm" :data-act="action" @click="open(item, action)">{{ t(ACTIONS[action].button) }}</button>
                    </div>
                </li>
            </ul>
            <button v-if="cursor && !failed" type="button" class="btn-secondary" :disabled="loadingMore" @click="load(true)">{{ loadingMore ? t('admin.loading') : t('admin.loadMore') }}</button>

            <Modal v-if="acting" :title="t(ACTIONS[acting.action].title)" :dismissable="!busy" @close="close">
                <form class="space-y-5" data-corrections-sheet @submit.prevent="decide">
                    <blockquote class="rounded-xl bg-raised p-3 text-sm text-cream" translate="no"><span class="line-clamp-4 whitespace-pre-line">{{ acting.item.text }}</span></blockquote>
                    <Field :label="t(ACTIONS[acting.action].note)" :error="errors?.note" :required="ACTIONS[acting.action].required" v-slot="f">
                        <textarea :id="f.id" v-model="note" rows="3" maxlength="1000" class="input" :required="ACTIONS[acting.action].required" :aria-describedby="f.describedby" :aria-invalid="f.invalid" data-corrections-note></textarea>
                    </Field>
                    <p v-if="errors && !errors.note" class="text-sm text-bad" role="alert">{{ t('admin.requestFailed') }}</p>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="btn-secondary" :disabled="busy" @click="close">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary" :disabled="busy || (ACTIONS[acting.action].required && !note.trim())" data-corrections-confirm>{{ busy ? t('common.saving') : t(ACTIONS[acting.action].button) }}</button>
                    </div>
                </form>
            </Modal>
        </div>`,
    created() {
        this.load();
    },
    methods: {
        query(more) {
            return { state: this.state === 'all' ? undefined : this.state, kind: this.kind === 'all' ? undefined : this.kind, cursor: more ? this.cursor : undefined };
        },
        load(more = false) {
            return loadInto(this, 'items', () => api.admin.corrections.list(this.query(more)), { more });
        },
        setKind(kind) {
            this.kind = kind;
            this.load();
        },
        open(item, action) {
            Object.assign(this, { acting: { item, action }, note: '', errors: null });
        },
        close() {
            if (!this.busy) this.acting = null;
        },
        async decide() {
            const { item, action } = this.acting;
            this.busy = true;
            this.errors = null;
            try {
                const { data } = await api.admin.corrections.update(item.id, { state: action, note: this.note.trim() || null });
                // Shown only while it still matches the filter; the inbox's count follows (the shell asks again).
                const keep = this.state === 'all' || data.state === this.state;
                this.items = keep ? this.items.map((had) => (had.id === data.id ? data : had)) : this.items.filter((had) => had.id !== data.id);
                this.acting = null;
                toast(t('admin.corrections.saved', { state: t(STATE_NAMES[data.state] ?? STATE_NAMES.new) }));
                this.$emit('changed');
            } catch (error) {
                this.errors = errorsFrom(error) ?? {};
                refused(error);
            } finally {
                this.busy = false;
            }
        },
    },
};
