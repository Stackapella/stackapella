// The bonus pop-ups' slot (docs/game-elements-plan.md; the proposal's boards 01 and 05; Hillel: "Yes,
// HUD strip", then "reskinned these elements"): no strip of its own; it sits beside Sing's combo, or
// floats under the recording stage's header, never on the lane. A screen reader hears a pop-up at most
// once a phrase. Sing's HUD is a reskin of what Sing already shows (the ge- rules on its own pieces).

import { still } from './effects.js';
import { WORDS } from './popups.js';

/** The slot's pop-up: a burst in yellow (a combo) or pink (a phrase, a combo bonus), 1.2 s, keyed so each plays once. */
export const PopupSlot = {
    props: { queue: { type: Object, required: true } },
    setup: () => ({ WORDS, still }),
    template: `
        <span class="ge-slot" data-game-slot>
            <span v-if="queue.now" :key="queue.now.id" class="ge-pop" :class="[queue.now.kind === 'combo' ? '' : 'ge-pop--pink', still() ? 'ge-pop--still' : '']" aria-hidden="true" :data-game-popup="queue.now.kind">
                <span class="ge-burst"></span>
                <span class="ge-pop-text">{{ t(WORDS[queue.now.kind], { count: queue.now.count ?? 0 }) }}</span>
            </span>
            <span class="sr-only" role="status">{{ queue.say ? t(WORDS[queue.say.kind], { count: queue.say.count ?? 0 }) : '' }}</span>
        </span>`,
};
