// Report a lent voice (docs/lend-your-voice-plan.md, "Moderation"; contract reports.create's `guideId`
// or `clipSlot`): a song part's real voice, or a course clip's. A reason, an optional note, then
// thanks. Reports never take a voice down on their own: the admins look at it.

import { ApiError, api } from '../api.js';
import { t, tk } from '../i18n.js';
import { Modal, toast } from './ui.js';

// The likely reasons first: a recording made without someone's OK, then content.
const REASONS = {
    personal: tk('voices.report.reasons.personal'),
    offensive: tk('voices.report.reasons.offensive'),
    copyright: tk('voices.report.reasons.copyright'),
    spam: tk('voices.report.reasons.spam'),
    other: tk('voices.report.reasons.other'),
};

export const ReportVoice = {
    components: { Modal },
    // One of `guideId` (a song part's real voice) or `clipSlot` (a course clip's); `who` is its credit line.
    props: { guideId: { type: String, default: null }, clipSlot: { type: String, default: null }, who: { type: String, default: '' } },
    emits: ['close'],
    data: () => ({ reason: 'personal', note: '', website: '', busy: false }),
    setup: () => ({ REASONS }),
    template: `
        <Modal :title="t('voices.report.title')" @close="$emit('close')">
            <form class="space-y-4" data-voice-report @submit.prevent="send">
                <p v-if="who" class="text-cream" translate="no">{{ who }}</p>
                <fieldset class="space-y-3">
                    <legend class="label">{{ t('voices.report.why') }}</legend>
                    <label v-for="(label, r) in REASONS" :key="r" class="check-row"><input v-model="reason" type="radio" :value="r" class="radio" /> {{ t(label) }}</label>
                </fieldset>
                <label class="block space-y-1.5"><span class="label">{{ t('voices.report.note') }}</span><textarea v-model="note" class="input min-h-20 w-full" maxlength="500" rows="3"></textarea></label>
                <input v-model="website" type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true" />
                <p class="text-sm text-muted">{{ t('voices.report.what') }}</p>
                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" class="btn-secondary" :disabled="busy" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn-primary" :disabled="busy">{{ t('voices.report.send') }}</button>
                </div>
            </form>
        </Modal>`,
    methods: {
        async send() {
            this.busy = true;
            const which = this.guideId ? { guideId: this.guideId } : { clipSlot: this.clipSlot };
            try {
                await api.reports.create({ ...which, reason: this.reason, note: this.note.trim(), website: this.website });
                toast(t('voices.report.thanks'));
                this.$emit('close');
            } catch (error) {
                if (error instanceof ApiError && error.status === 404) {
                    toast(t('voices.report.gone'));
                    this.$emit('close');
                } else {
                    console.warn('The voice report was not sent', error);
                    toast(t('voices.report.failed'), { tone: 'error' });
                }
            } finally {
                this.busy = false;
            }
        },
    },
};
