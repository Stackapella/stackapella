// Stage clear (docs/game-elements-plan.md; the proposal's board 02, after Sonic 2's act clear), as a
// reskin of the lesson end's own pieces (Hillel: "reskinned these elements to be more game like"): the
// star gems drop one by one as they already do, the +XP chip counts up to the real XP, "Lesson 3 of 5
// done" reads "Stage 2-3 clear", the word lands in the display face and the level bar is a power bar
// that fills (the ge- rules on [data-lesson-end]). Nothing waits on it: a tap or any key lands it.
// Reduced motion: the final numbers, still. The count's end is a chip cue on the audio clock, only
// with Game sounds on.

import { engine } from '../audio.js';
import { cue } from './chip.js';
import { effect, still } from './effects.js';
import { countAt, run } from './motion.js';

export const COUNT_AT = 300; // ms: the XP starts counting as the first star drops
export const COUNT_MS = 700;

/** Whether a lesson end is a stage clear: game mode with Celebrations on. */
export const tallyOn = () => effect('celebrations');

/** A number counting up from 0 to `to` (its slot gets `n`), landing at once on a tap, a key or reduced motion. */
export const CountUp = {
    props: { to: { type: Number, required: true }, delay: { type: Number, default: COUNT_AT }, ms: { type: Number, default: COUNT_MS } },
    data: () => ({ n: 0 }),
    template: `<span class="tabular-nums" data-count-up><slot :n="n" /></span>`,
    mounted() {
        const total = this.delay + this.ms;
        this.going = run(total, (p) => (this.n = countAt(0, this.to, Math.max(0, (p * total - this.delay) / this.ms))), { motion: !still() });
        if (!still() && engine.ctx && this.to > 0) this.sound = cue('tallyEnd', { at: engine.ctx.currentTime + total / 1000 });
        this.land = () => {
            this.going.skip();
            this.sound?.stop();
            this.$el.closest('[data-lesson-end]')?.classList.add('is-landed'); // the CSS parts land too
        };
        window.addEventListener('keydown', this.land);
        window.addEventListener('pointerdown', this.land);
    },
    unmounted() {
        this.going?.stop();
        this.sound?.stop();
        window.removeEventListener('keydown', this.land);
        window.removeEventListener('pointerdown', this.land);
    },
};
