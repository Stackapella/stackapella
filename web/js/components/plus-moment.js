// An upgrade moment (docs/plus-plan.md; Hillel's 99.png): when the free plan stops something (singing
// with a friend, MP3 or stems, storage nearly full: docs/pricing-plan.md, 2026-11-07), a short sheet says
// what happened and the ways on: another way out if there is one, and Get Plus. Never a toast that leaves
// the person guessing; never more than once for the same tap; never in front of a course, song or workout.

import { navigate } from '../router.js';
import { selfHosted } from '../stores/account.js';
import { Icon } from './icon.js';
import { Modal } from './ui.js';

export const PlusMoment = {
    components: { Icon, Modal },
    props: {
        title: { type: String, required: true },
        text: { type: String, required: true },
        from: { type: String, required: true }, // where it came from, for the Plus page (?from=)
        other: { type: String, default: '' }, // a second way out (Manage songs), if there is one
    },
    emits: ['close', 'other'],
    template: `
        <Modal :title="title" @close="$emit('close')">
            <div class="space-y-5" data-plus-moment>
                <p class="text-cream/85">{{ text }}</p>
                <div class="flex flex-wrap justify-end gap-3">
                    <button v-if="other" type="button" class="btn-secondary" @click="$emit('other')">{{ other }}</button>
                    <button v-else type="button" class="btn-secondary" @click="$emit('close')">{{ t('common.notNow') }}</button>
                    <button v-if="!selfHosted()" type="button" class="btn-primary" @click="navigate('/plus?from=' + from)">{{ t('plus.subscribe') }}</button>
                </div>
            </div>
        </Modal>`,
    setup: () => ({ navigate, selfHosted }),
};
