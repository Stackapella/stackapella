// How loud the mic hears you, on every screen that listens (Hillel: always show we hear the volume):
// a bar that fills with your level, and the "voice detected" line across it. When the fill crosses
// the line, the line and the fill light in the accent and it says "Hearing you", so people see why
// listening started, or why it hasn't. The maths is js/mic-check.js's.

import { t } from '../i18n.js';
import { meterLevel, stepVoice, VOICE_LINE, voiceWatch } from '../mic-check.js';

/** The level as segments across a card (the no-sound card): lit as sound comes in. */
export const LevelSegments = {
    props: { db: { type: Number, default: null } },
    setup: () => ({ COUNT: 24, VOICE_LINE }),
    template: `
        <div class="flex h-4 w-full gap-1" role="meter" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="percent" :aria-label="t('mic.level')" data-level-segments>
            <span v-for="i in COUNT" :key="i" class="min-w-0 flex-1 rounded-sm transition-colors duration-75" :class="i <= lit ? (i > COUNT * VOICE_LINE ? 'bg-accent' : 'bg-muted') : 'bg-raised'"></span>
        </div>`,
    computed: {
        percent: (vm) => Math.round(meterLevel(vm.db) * 100),
        lit: (vm) => Math.round(meterLevel(vm.db) * 24),
    },
};

export const LevelMeter = {
    props: {
        db: { type: Number, default: null }, // the level now, RMS dBFS (null: nothing yet)
        segments: Boolean, // as a row of small segments (101.png) instead of a bar with the voice line
    },
    data: () => ({ watch: voiceWatch(), last: 0 }),
    setup: () => ({ VOICE_LINE }),
    template: `
        <div class="flex w-full items-center gap-3" data-level-meter :data-hearing="hearing ? 'true' : 'false'">
            <div v-if="segments" class="flex h-2.5 min-w-0 flex-1 gap-1" role="meter" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="percent" :aria-valuetext="words" :aria-label="t('mic.level')">
                <span v-for="i in 24" :key="i" class="min-w-0 flex-1 rounded-sm transition-colors duration-75" :class="i <= Math.round(percent * 0.24) ? (hearing ? 'bg-accent' : 'bg-muted/60') : 'bg-raised'"></span>
            </div>
            <div v-else class="relative h-2.5 min-w-0 flex-1 rounded-full bg-raised" role="meter" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="percent" :aria-valuetext="words" :aria-label="t('mic.level')">
                <div class="h-full rounded-full transition-[width] duration-75" :class="hearing ? 'bg-accent' : 'bg-muted/50'" :style="{ width: percent + '%' }"></div>
                <span class="absolute -inset-y-2 w-1 -translate-x-1/2 rounded-full transition-colors" :class="hearing ? 'bg-accent-strong' : 'bg-muted'" :style="{ left: VOICE_LINE * 100 + '%' }" aria-hidden="true"></span>
            </div>
            <span class="shrink-0 text-sm font-semibold transition-colors" :style="segments ? null : { width: '7rem' }" :class="hearing ? 'text-accent-strong' : 'text-muted'" aria-hidden="true">{{ words }}</span>
        </div>`,
    computed: {
        percent: (vm) => Math.round(meterLevel(vm.db) * 100),
        hearing: (vm) => vm.watch.hearing && vm.watch.voiced > 0,
        words: (vm) => (vm.hearing ? t('meter.hearing') : t('meter.listening')),
    },
    watch: {
        db(value) {
            const now = performance.now();
            const dt = this.last ? Math.min(0.1, (now - this.last) / 1000) : 0;
            this.last = now;
            this.watch = stepVoice(this.watch, value ?? -Infinity, dt);
        },
    },
};
