// A fresh timing check, offered (master plan 15b): one quiet line under a take's result when the
// singer listens over Bluetooth with a measured timing, after headphones reconnect or every
// RECHECK_TAKES takes (audio.js recheckDue). Never during a take, never a dialog, never in a new
// singer's first week; the check itself is one tap away (Sing's TimingDialog).

import { recheckDue, recheckShown } from '../audio.js';
import { inFirstWeek } from './voice-care.js';

export const TimingLine = {
    emits: ['check'],
    data: () => ({ reason: null }),
    template: `
        <p v-if="reason" class="text-sm text-muted" role="status" data-timing-recheck :data-reason="reason">
            {{ reason === 'reconnect' ? t('timing.recheck.reconnect') : t('timing.recheck.takes') }}
            <button type="button" class="link" data-timing-recheck-open @click="$emit('check')">{{ t('timing.recheck.check') }}</button>
        </p>`,
    mounted() {
        // Read once, as the take ends: the line never appears or changes mid-screen.
        const due = inFirstWeek() ? null : recheckDue();
        if (due) {
            this.reason = due;
            recheckShown();
        }
    },
};
