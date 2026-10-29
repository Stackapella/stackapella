// One styled confirmation for the whole app (docs/decisions.md, "no browser dialogs"): never the
// browser's own confirm, alert or prompt. `await confirmAction({...})` says yes or no. It names the
// thing, what happens to it and (Hillel's 78.png) what's lost; the action is its own clearly
// labelled button (never OK). A destructive one focuses the safe answer ("Keep it"), so Enter
// doesn't destroy; Escape, the backdrop and that button say no, and there's no extra close. Only
// discarding a draft offers "Don't ask again" (never a destructive one); Settings turns it back on.

import { reactive } from 'vue';
import { t, timeAgo } from '../i18n.js';
import { local } from '../storage.js';
import { deleteProject, shownTitle } from '../stores/projects.js';
import { formatTime } from '../theme.js';
import { Modal, toast } from './ui.js';

/** How long an Undo stays on screen after a delete (the confirmations promise it). */
export const UNDO_SECONDS = 10;

/** The confirmations that can be skipped: only discarding a draft (never a destructive one). */
export const SKIPPABLE = ['discardDraft'];
const skipKey = (kind) => `stackapella.confirm.skip.${kind}`;

const state = reactive({ open: null });

/** Whether this kind of confirmation was turned off on this device. */
export const skipped = (kind) => local.get(skipKey(kind)) === '1';

/** Whether any confirmation is turned off (Settings offers to turn them back on). */
export const anySkipped = () => SKIPPABLE.some(skipped);

/** Every confirmation asks again. */
export function askAgain() {
    SKIPPABLE.forEach((kind) => local.remove(skipKey(kind)));
}

/**
 * Asks, and resolves true to go ahead. `title` names it ("Delete 'Silent Night'?"), `body` says
 * what happens, `details` what's lost (`[{ label, value }]`), `action` labels the button ("Delete
 * song") and `cancel` the safe one ("Keep it"). `skippable` names its kind in SKIPPABLE; skipped,
 * it resolves true at once.
 */
export function confirmAction({ title, body = null, details = null, action, cancel = null, destructive = true, skippable = null }) {
    if (skippable && !SKIPPABLE.includes(skippable)) throw new Error(`Not a skippable confirmation: ${skippable}`);
    if (skippable && skipped(skippable)) return Promise.resolve(true);
    state.open?.resolve(false); // one at a time: a new question answers the old one with no
    return new Promise((resolve) => {
        state.open = { title, body, details, action, cancel, destructive, skippable, never: false, resolve };
    });
}

export const ConfirmHost = {
    components: { Modal },
    setup: () => ({ state }),
    template: `
        <Modal v-if="state.open" :key="state.open.title" :title="state.open.title" :closer="false" @close="answer(false)">
            <form class="space-y-5" @submit.prevent="answer(true)">
                <p v-if="state.open.body" class="text-cream">{{ state.open.body }}</p>
                <dl v-if="state.open.details?.length" class="space-y-2 rounded-2xl border border-line bg-ink/40 p-4 text-sm">
                    <div v-for="row in state.open.details" :key="row.label" class="flex items-center justify-between gap-4"><dt class="text-muted">{{ row.label }}</dt><dd class="font-bold tabular-nums text-cream">{{ row.value }}</dd></div>
                </dl>
                <label v-if="state.open.skippable" class="check-row text-muted"><input v-model="state.open.never" type="checkbox" class="checkbox" @keydown.enter.prevent="answer(true)" /> {{ t('confirm.dontAsk') }}
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" class="btn-secondary" :data-autofocus="state.open.destructive ? '' : null" @click="answer(false)">{{ state.open.cancel ?? t('common.cancel') }}</button>
                    <button type="submit" :data-autofocus="state.open.destructive ? null : ''" :class="state.open.destructive ? 'btn-danger' : 'btn-primary'">{{ state.open.action }}</button>
                </div>
            </form>
        </Modal>`,
    methods: {
        answer(yes) {
            const open = state.open;
            if (!open) return;
            if (yes && open.never && open.skippable) local.set(skipKey(open.skippable), '1');
            state.open = null;
            open.resolve(yes);
        },
    },
};

/**
 * Deleting a song (Hillel's 78.png, with Storage's 88.png): what's lost (its recorded parts, its
 * length, when it was last edited), where it goes (Recently deleted for 30 days when the account
 * has it, else off this device), and Undo in a toast with a countdown. Resolves true when it went.
 */
export async function deleteSong(project) {
    const title = shownTitle(project);
    const edited = project.updatedAt ? timeAgo(project.updatedAt) : '';
    const sure = await confirmAction({
        title: t('confirm.deleteProject.title', { title }),
        body: project.version > 0 ? t('confirm.deleteProject.bodyBin', { seconds: UNDO_SECONDS }) : t('confirm.deleteProject.bodyDevice', { seconds: UNDO_SECONDS }),
        details: [
            { label: t('confirm.deleteProject.recorded'), value: String(project.recordedCount ?? 0) },
            project.length >= 1 && { label: t('confirm.deleteProject.length'), value: formatTime(project.length) },
            edited && { label: t('confirm.deleteProject.edited'), value: edited.charAt(0).toLocaleUpperCase() + edited.slice(1) },
        ].filter(Boolean),
        action: t('confirm.deleteProject.action'),
        cancel: t('confirm.keep'),
    });
    if (!sure) return false;
    const undo = await deleteProject(project.id);
    toast(t('confirm.deleteProject.done', { title }), { seconds: UNDO_SECONDS, actions: [{ label: t('common.undo'), run: undo }] });
    return true;
}
