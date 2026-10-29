// The count-in, everywhere it shows (Hillel: "1 2 3 4, start"): the beat of the bar counting up with
// its click, 1 to the meter's last beat, then recording starts; never a countdown or a word after
// it. The number is drawn from the heard audio clock by whoever passes `beat` (no timers). A screen
// reader hears "Counting in" once (the changing number is hidden from it). Beat 0: counting in, before
// the first click (nothing shown yet).

import { formatNumber } from '../i18n.js';
import { effect } from '../game/effects.js';

/** The beat's number as shown ("3"). */
export function countInText(beat) {
    return formatNumber(beat);
}

export const CountIn = {
    props: { beat: { type: Number, required: true } },
    setup: () => ({ countInText, effect }),
    // Game mode (docs/game-elements-plan.md): READY? before the first click (the mic's settle), then the
    // beats in HUD digits; still no word after them.
    template: `<span class="tabular-nums ge-count" :data-count-in="beat"><span aria-hidden="true" :class="!effect('titles') ? '' : beat ? 'ge-count-beat' : 'ge-ready'" :key="beat">{{ beat ? countInText(beat) : effect('titles') ? t('game.hud.ready') : '' }}</span><span class="sr-only">{{ t('countIn.label') }}</span></span>`,
};
