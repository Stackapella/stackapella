// NEW RECORD (docs/game-elements-plan.md; the proposal's board 03, after Daytona USA): only on a real
// personal best, a take the board counted (the server's `improved`), in the best line's own place for
// "a new best", with "up from 82" only when the server says what the best was before (`before`). The
// letters drop in one by one in the part colours, then ride one wave; a tap lands them. Reduced
// motion: still.

import { cue } from './chip.js';
import { still } from './effects.js';
import { run } from './motion.js';

/** "NEW RECORD" as letters, each with its place in the part colours (spaces kept). */
export function letters(word) {
    let lit = 0;
    return [...word].map((char) => (char === ' ' ? { char, space: true } : { char, colour: (lit++ % 4) + 1 }));
}

export const NewRecord = {
    props: { best: { type: Number, required: true }, before: { type: Number, default: null } },
    data: () => ({ landed: still() }),
    setup: () => ({ letters }),
    // Inline, in the place of the best line's "a new best" (Hillel: "reskinned these elements").
    template: `
        <span class="ge-record" :class="landed ? 'is-landed' : ''" data-game-record @click="land"><span class="text-muted"> · </span><span class="ge-record-word" role="status" :aria-label="t('game.record.title')"><span v-for="(l, i) in letters(t('game.record.title'))" :key="i" aria-hidden="true" :class="l.space ? 'ge-record-space' : 'ge-record-letter ge-c' + l.colour" :style="{ '--i': i }">{{ l.char }}</span></span><span v-if="before !== null && before < best" class="ge-record-line"> {{ t('game.record.from', { before }) }}</span></span>`,
    mounted() {
        this.sound = cue('record');
        // The drop and the wave run in CSS; this only marks when they're done, so a tap can land them sooner.
        this.going = run(1600, () => {}, { motion: !this.landed });
        this.going.done.then(() => (this.landed = true));
    },
    unmounted() {
        this.going?.stop();
        this.sound?.stop(); // left at once: the fanfare doesn't follow you
    },
    methods: {
        land() {
            this.going?.skip();
        },
    },
};
