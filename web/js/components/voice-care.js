// Voice care's note (docs/voice-care-plan.md, slice 3): one quiet line under a take's result or a
// lesson's end, once 20 or 40 minutes have been sung today on this device, each said once a day.
// Never during a take, never a dialog, never in the way of Continue; the cool-down one tap away.

import { navigate } from '../router.js';
import { sk } from '../skin.js';
import { learning } from '../stores/learning.js';
import { markShown, noteDue } from '../voice-day.js';

const WEEK = 7 * 86400000;

/**
 * A new singer's first week stays as it is (Hillel's rule): no note and no cool-down offered while
 * the course started under seven days ago, as Home's week one counts it.
 */
export function inFirstWeek(now = Date.now()) {
    const started = learning.progress?.startedAt;
    return !started || now - started < WEEK;
}

export const VoiceCareLine = {
    data: () => ({ minutes: null }),
    setup: () => ({ navigate, sk }),
    template: `
        <p v-if="minutes" class="text-sm text-muted" role="status" data-voice-care :data-minutes="minutes">
            {{ minutes >= 40 ? sk('voiceCare.forty', 'voiceCare.fortyGame', { minutes }) : sk('voiceCare.twenty', 'voiceCare.twentyGame', { minutes }) }}
            <a href="/cooldown" class="link" data-voice-care-cooldown @click.prevent="navigate('/cooldown')">{{ t('voiceCare.coolDown') }}</a>
        </p>`,
    mounted() {
        // Read once, as the take or the lesson ends: a note never appears or changes mid-screen.
        const due = inFirstWeek() ? null : noteDue();
        if (due) {
            this.minutes = due;
            markShown(due);
        }
    },
};
