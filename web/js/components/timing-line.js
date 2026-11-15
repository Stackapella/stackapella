// A fresh timing check, offered (master plan 15b): one quiet line under a take's result when the
// singer listens over Bluetooth with a measured timing, after headphones reconnect or every
// RECHECK_TAKES takes (audio.js recheckDue). Never during a take, never a dialog, never in a new
// singer's first week; the check itself is one tap away (Sing's TimingDialog).
// And the first check of all (Hillel, 2026-11-15: "Timing check after the first take"): a take recorded with the
// estimate, in the first week too, offers the check that lines it up (stores/session.js lineUpEstimated).

import { audio, recheckDue, recheckShown } from '../audio.js';
import { inFirstWeek } from './voice-care.js';

export const TimingLine = {
    props: { take: { type: Object, default: null } }, // the part whose take this is under: its recordedWith
    emits: ['check'],
    data: () => ({ reason: null }),
    template: `
        <p v-if="reason" class="text-sm text-muted" role="status" data-timing-recheck :data-reason="reason">
            {{ reason === 'estimated' ? t('timing.recheck.estimated') : reason === 'reconnect' ? t('timing.recheck.reconnect') : t('timing.recheck.takes') }}
            <button type="button" class="link" data-timing-recheck-open @click="$emit('check')">{{ t('timing.recheck.check') }}</button>
        </p>`,
    mounted() {
        // Read once, as the take ends: the line never appears or changes mid-screen.
        if (this.take?.recordedWith?.estimated && audio.latencyEstimated) {
            this.reason = 'estimated';
            return;
        }
        const due = inFirstWeek() ? null : recheckDue();
        if (due) {
            this.reason = due;
            recheckShown();
        }
    },
};
