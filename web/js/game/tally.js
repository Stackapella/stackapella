// Stage clear (docs/game-elements-plan.md; the proposal's board 02, after Sonic 2's act clear), as a
// reskin of the lesson end's own pieces (Hillel: "reskinned these elements to be more game like"): the
// star gems drop one by one as they already do, the +XP chip counts up to the real XP, "Lesson 3 of 5
// done" reads "Stage 2-3 clear", the word lands in the display face and the level bar is a power bar
// that fills (the ge- rules on [data-lesson-end]). Nothing waits on it: a tap or any key lands it.
// Reduced motion: the final numbers, still. With Game sounds on, chip cues on the audio clock: a note as
// each new star lands, the count's end after them, and the level-up as its line shakes in.

import { engine } from '../audio.js';
import { cue } from './chip.js';
import { effect, still } from './effects.js';
import { countAt, run } from './motion.js';

export const COUNT_AT = 300; // ms: the XP starts counting as the first star drops
export const COUNT_MS = 700;
const STAR_GAP = 0.3; // s: the gems' own stagger (results.js, StarDiscs)
const STAR_LAND = 0.2; // s: a gem's pop lands this far into its 0.5 s spring
const LEVEL_AT = 1.4; // s: the level-up line's shake (the ge-shake rule on [data-lesson-end])

/**
 * The stage clear's cues from `at` (the audio clock's seconds, the screen's start): one rising note for
 * each star that pops (only new ones pop), the count's end at `countEnd` or just after the stars, and
 * the level-up when there is one. Each returns `{ stop }`; a cue that may not sound is left out.
 */
export function stageCues(end, at, countEnd, counted) {
    const fresh = Math.min(end?.querySelectorAll('.gem-pop').length ?? 0, 3);
    const sounds = [];
    let after = at;
    for (let i = 0; i < fresh; i++) {
        const sound = cue(`star${i + 1}`, { at: at + STAR_LAND + STAR_GAP * i });
        if (sound) sounds.push(sound);
        after = sound?.end ?? after;
    }
    const tally = counted ? cue('tallyEnd', { at: Math.max(at + countEnd, after + 0.04) }) : null;
    if (tally) sounds.push(tally);
    const level = end?.querySelector('[data-level-up]') ? cue('levelUp', { at: Math.max(at + LEVEL_AT, (tally?.end ?? after) + 0.04) }) : null;
    if (level) sounds.push(level);
    return sounds;
}

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
        this.sounds = !still() && engine.ctx ? stageCues(this.$el.closest('[data-lesson-end]'), engine.ctx.currentTime, total / 1000, this.to > 0) : [];
        this.land = () => {
            this.going.skip();
            this.stopSounds();
            this.$el.closest('[data-lesson-end]')?.classList.add('is-landed'); // the CSS parts land too
        };
        window.addEventListener('keydown', this.land);
        window.addEventListener('pointerdown', this.land);
    },
    methods: {
        stopSounds() {
            for (const sound of this.sounds ?? []) sound.stop();
        },
    },
    unmounted() {
        this.going?.stop();
        this.stopSounds();
        window.removeEventListener('keydown', this.land);
        window.removeEventListener('pointerdown', this.land);
    },
};
