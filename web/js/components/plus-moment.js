// An upgrade moment (docs/plus-plan.md; Hillel's 99.png): when the free plan stops something (a fourth
// song), a short sheet says what happened and the two ways on: the slots used ("3 of 3"), a way to make
// room (Manage songs) and Get Plus. Never a toast that leaves the person guessing; never more than once
// for the same tap.

import { navigate } from '../router.js';
import { selfHosted } from '../stores/account.js';
import { FREE_PROJECT_LIMIT } from '../stores/projects.js';
import { Icon } from './icon.js';
import { Modal } from './ui.js';

export const PlusMoment = {
    components: { Icon, Modal },
    props: {
        title: { type: String, required: true },
        text: { type: String, required: true },
        from: { type: String, required: true }, // where it came from, for the Plus page (?from=)
        other: { type: String, default: '' }, // a second way out (Manage songs), if there is one
        full: Boolean, // the free plan's songs all used: its slots shown, "3 of 3"
    },
    emits: ['close', 'other'],
    template: `
        <Modal :title="title" @close="$emit('close')">
            <div class="space-y-5" data-plus-moment>
                <p class="text-cream/85">{{ text }}</p>
                <div v-if="full" class="flex items-center gap-3" data-slots>
                    <span class="flex flex-1 gap-1.5" aria-hidden="true"><span v-for="i in FREE_PROJECT_LIMIT" :key="i" class="h-2 flex-1 rounded-full bg-part-2"></span></span>
                    <span class="shrink-0 text-sm tabular-nums text-muted">{{ t('settings.plan.ofLimit', { used: FREE_PROJECT_LIMIT, limit: FREE_PROJECT_LIMIT }) }}</span>
                </div>
                <div class="flex flex-wrap justify-end gap-3">
                    <button v-if="other" type="button" class="btn-secondary" @click="$emit('other')">{{ other }}</button>
                    <button v-else type="button" class="btn-secondary" @click="$emit('close')">{{ t('common.notNow') }}</button>
                    <button v-if="!selfHosted()" type="button" class="btn-primary" @click="navigate('/plus?from=' + from)">{{ t('plus.subscribe') }}</button>
                </div>
            </div>
        </Modal>`,
    setup: () => ({ navigate, selfHosted, FREE_PROJECT_LIMIT }),
};
