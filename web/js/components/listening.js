// The pitch bar (PitchBar): a voice's marker gliding along a bar that zooms in, with a ring that fills as a note
// is held. Used where a held note is shown against a written one (the creator's editor). Finding a note in your
// voice (a comfortable note, the range) is components/voice-capture.js, the one way everywhere.

import { across, ticks, WIDE } from '../listening.js';

/** The bar: `pitch` (MIDI, smoothed) or null, in the window `view` ({ lo, hi }), `hold` 0 to 1. */
export const PitchBar = {
    props: { plain: Boolean, pitch: { type: Number, default: null }, alpha: { type: Number, default: 1 }, view: { type: Object, default: () => ({ ...WIDE }) }, hold: { type: Number, default: 0 }, color: { type: String, default: 'var(--color-part-4)' }, label: String },
    template: `
        <div class="relative h-24 w-full select-none" role="img" :aria-label="label">
            <div class="absolute inset-x-0 top-10 h-3 rounded-full bg-raised"></div>
            <div v-for="tick in marks" :key="tick.midi" class="absolute top-7 flex -translate-x-1/2 flex-col items-center" :style="{ left: tick.at * 100 + '%' }" aria-hidden="true">
                <span class="w-px rounded-full" :class="tick.major ? 'h-9 bg-edge' : 'h-5 translate-y-2 bg-line'"></span>
                <span v-if="tick.label && !wideAndPlain" class="mt-1 tabular-nums text-[11px] text-muted" translate="no">{{ tick.label }}</span>
            </div>
            <!-- Plain (the note finder), while it's wide: Lower and Higher at the ends, not C2 to C6 (onboarding review 2, 14).
                 Zoomed in on a held note, the nearby notes are named: that's your note, said. -->
            <div v-if="wideAndPlain" class="absolute inset-x-0 top-16 flex justify-between text-xs text-muted" aria-hidden="true"><span>{{ t('listening.lower') }}</span><span>{{ t('listening.higher') }}</span></div>
            <div v-if="pitch !== null" class="absolute top-[2.875rem] -translate-x-1/2 -translate-y-1/2" :style="{ left: across(pitch, view) * 100 + '%', opacity: alpha }" aria-hidden="true">
                <svg width="44" height="44" viewBox="0 0 44 44" class="block">
                    <circle cx="22" cy="22" r="19" fill="none" stroke="currentColor" stroke-width="3" class="text-line" />
                    <circle cx="22" cy="22" r="19" fill="none" :stroke="color" stroke-width="3" stroke-linecap="round" :stroke-dasharray="119.4" :stroke-dashoffset="119.4 * (1 - hold)" transform="rotate(-90 22 22)" />
                    <circle cx="22" cy="22" r="11" :fill="color" />
                </svg>
            </div>
        </div>`,
    setup: () => ({ across }),
    computed: {
        marks() {
            return ticks(this.view);
        },
        /** The wide view of a plain bar: words at its ends instead of note names. */
        wideAndPlain() {
            return this.plain && this.view.hi - this.view.lo > 24;
        },
    },
};

