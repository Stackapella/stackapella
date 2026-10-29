// Re-sing a phrase (docs/more-features-plan.md, feature 11): pick the phrase, sing just that bit
// with the others, then hear it in place and keep it or try again. The joins, the loudness match
// and the "sounds different" check are js/patch.js's; nothing is saved until Keep it.

import { Modal } from '../ui.js';
import { Icon } from '../icon.js';

/** The phrases to choose from, and (once one is chosen) what to sing so it joins smoothly. */
export const ResingSheet = {
    components: { Modal },
    props: { phrases: { type: Array, required: true }, plan: { type: Object, default: null } },
    emits: ['pick', 'start', 'close'],
    template: `
        <Modal :title="t('sing.resing.title')" @close="$emit('close')">
            <div v-if="!plan" class="space-y-3">
                <p class="text-sm text-muted">{{ t('sing.resing.pick') }}</p>
                <ul class="space-y-2">
                    <li v-for="p in phrases" :key="p.index">
                        <button type="button" class="card-interactive block w-full p-3 text-start text-cream" translate="no" @click="$emit('pick', p.index)">{{ p.label }}</button>
                    </li>
                </ul>
            </div>
            <div v-else class="space-y-4">
                <p class="text-cream">{{ t('sing.resing.ready') }}</p>
                <p v-if="plan.singFrom" class="rounded-2xl border border-line p-3 text-sm text-cream">{{ t('sing.resing.startAt', { word: plan.singFrom }) }}</p>
                <p v-if="plan.singTo" class="rounded-2xl border border-line p-3 text-sm text-cream">{{ t('sing.resing.singOn', { word: plan.singTo }) }}</p>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" class="btn-secondary" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="button" class="btn-primary" data-autofocus @click="$emit('start')">{{ t('sing.resing.start') }}</button>
                </div>
            </div>
        </Modal>`,
};

/** The re-sung phrase on trial: hear it in place, keep it, or try again. */
export const ResungCard = {
    components: { Icon },
    props: { different: Boolean, hearing: Boolean, line: { type: String, default: '' } },
    emits: ['hear', 'keep', 'again', 'whole', 'cancel'],
    template: `
        <section class="flex w-full max-w-lg flex-col items-center gap-5 p-6 text-center" aria-labelledby="resung-title">
            <h2 id="resung-title" ref="heading" tabindex="-1" class="text-2xl font-extrabold outline-none">{{ t('sing.resing.doneTitle') }}</h2>
            <p v-if="line" class="text-lg text-cream" translate="no">{{ line }}</p>
            <p v-if="different" class="rounded-2xl border border-part-6/40 bg-part-6/10 p-3 text-sm text-cream" role="note">{{ t('sing.resing.different') }}</p>
            <div class="flex flex-wrap justify-center gap-3">
                <button type="button" class="btn-secondary" :aria-pressed="hearing" @click="$emit('hear')"><Icon :name="hearing ? 'stop' : 'play'" :size="16" /> {{ hearing ? t('studio.stop') : t('sing.resing.hear') }}</button>
                <button v-if="different" type="button" class="btn-primary" @click="$emit('again')">{{ t('sing.resing.again') }}</button>
                <button type="button" :class="different ? 'btn-secondary' : 'btn-primary'" @click="$emit('keep')">{{ t('sing.resing.keep') }}</button>
                <button v-if="!different" type="button" class="btn-secondary" @click="$emit('again')">{{ t('sing.resing.again') }}</button>
            </div>
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm">
                <button v-if="different" type="button" class="btn-ghost" @click="$emit('whole')">{{ t('sing.resing.whole') }}</button>
                <button type="button" class="btn-ghost" @click="$emit('cancel')">{{ t('sing.resing.cancel') }}</button>
            </div>
        </section>`,
    mounted() {
        this.$refs.heading.focus();
    },
};
