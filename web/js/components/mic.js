// The mic, right where the app listens: which one (defaulting to the one chosen anywhere else), the
// note about wireless mics, and the live level with its voice line (components/level-meter.js). Used by the course's
// "Find your note" and other listening steps.

import { audio, chooseMic, currentMicId, looksWireless, micChoices } from '../audio.js';
import { micDb } from '../mic-check.js';
import { t } from '../i18n.js';
import { showMicHelp } from '../mic-help.js';
import { Icon } from './icon.js';
import { LevelMeter } from './level-meter.js';

/**
 * The mic didn't open: "Let's get your mic working" says why and how to fix it, and `retry` (the
 * screen's own next step) runs once it works. Returns null: nothing is shown in the screen itself.
 */
export function micError(error, retry = null) {
    showMicHelp(error, retry);
    return null;
}

/** Wireless mics drift: the note shown wherever one is chosen. */
export const WirelessNote = {
    components: { Icon },
    template: `
        <p class="flex gap-3 rounded-2xl border border-part-6/40 bg-part-6/10 p-3 text-sm text-cream" role="note">
            <Icon name="headphones" :size="18" class="mt-0.5 shrink-0 text-part-6" />
            <span>{{ t('mic.wirelessNote') }}</span>
        </p>`,
};


export const MicPicker = {
    components: { Icon, LevelMeter, WirelessNote },
    setup: () => ({ audio, micChoices, currentMicId, looksWireless }),
    data: () => ({ db: null, error: null, switching: false }),
    template: `
        <div v-if="audio.mic" class="space-y-3">
            <label class="block text-sm text-muted">{{ t('studio.menu.mic') }}
                <select class="input mt-1" :value="currentMicId()" :disabled="switching" @change="pick($event.target.value)">
                    <option v-for="m in micChoices()" :key="m.deviceId" :value="m.deviceId" translate="no">{{ m.systemDefault ? t('mic.systemDefaultNamed', { name: m.label }) : m.label }}</option>
                </select>
            </label>
            <WirelessNote v-if="looksWireless(audio.mic.label)" />
            <div class="flex items-center gap-3 text-muted">
                <Icon name="mic" :size="18" class="shrink-0" />
                <LevelMeter :db="db" />
            </div>
            <p v-if="error" class="text-sm text-bad" role="alert">{{ error }}</p>
        </div>`,
    mounted() {
        const meter = () => {
            this.db = micDb();
            this.frame = requestAnimationFrame(meter);
        };
        meter();
    },
    unmounted() {
        cancelAnimationFrame(this.frame);
    },
    methods: {
        async pick(deviceId) {
            this.switching = true;
            try {
                await chooseMic(deviceId);
                this.error = null;
            } catch (error) {
                this.error = micError(error);
            } finally {
                this.switching = false;
            }
        },
    },
};
