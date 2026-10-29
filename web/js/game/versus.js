// A challenge as a fighting game's versus (docs/game-elements-plan.md; the proposal's board 06, after
// Super Street Fighter II and King of Fighters): "Here comes a new challenger!" on a red band for a
// second (a tap skips it), then the two of you on a diagonal split, their score against your best on
// that part, a slanted VS, and the board's rule in one plain line. Every number is real: their score
// from the challenge, yours from the board (or "No score yet"). The challenger is drawn in the part's
// colour, you in your accent. Reduced motion: no slides, the band shows still.

import { formatNumber } from '../i18n.js';
import { cue } from './chip.js';
import { still } from './effects.js';
import { run } from './motion.js';

export const BANNER_MS = 1000;
const VOICES = ['soprano', 'alto', 'tenor', 'bass'];

/** The theme's colour for a part (the four voices' own; others by their place). */
export function partColourVar(partKey, index = 0) {
    const voice = VOICES.indexOf(partKey);
    return `var(--color-part-${(voice >= 0 ? voice : index % 4) + 1})`;
}

/** A name's initial, for the portrait. */
export const initial = (name) => ((name || '').trim()[0] ?? '?').toUpperCase();

export const Versus = {
    props: {
        by: { type: String, default: null }, // the challenger's name, or null ("someone")
        score: { type: Number, required: true },
        song: { type: String, required: true },
        part: { type: String, required: true }, // the part's name
        colour: { type: String, required: true }, // partColourVar's
        yours: { type: Number, default: null }, // your best on the part, or null
        me: { type: String, default: '' }, // your name, signed in
        heading: { type: String, required: true }, // the plain sentence, for screen readers
        busy: Boolean,
    },
    emits: ['sing'],
    data: () => ({ banner: true, still: still() }),
    setup: () => ({ formatNumber, initial }),
    template: `
        <main class="ge-vs" :class="still ? 'is-still' : ''" :style="{ '--ge-part': colour }" data-challenge data-game-versus @click="still = true">
            <h1 class="sr-only">{{ heading }}</h1>
            <p class="sr-only">{{ yours === null ? t('game.versus.none') : t('game.versus.yoursSr', { score: formatNumber(yours) }) }}</p>
            <div v-if="banner" class="ge-vs-banner" aria-hidden="true" data-game-challenger @click="skip">
                <p class="ge-vs-band">{{ t('game.versus.challenger') }}</p>
                <p class="ge-vs-sent">{{ by ? t('game.versus.sent', { name: by }) : t('game.versus.sentSomeone') }}</p>
                <p class="ge-vs-what"><span translate="no">{{ song }}</span> · <span translate="no">{{ part }}</span> · {{ formatNumber(score) }}</p>
            </div>
            <template v-else>
                <p class="ge-vs-song" aria-hidden="true"><span translate="no">{{ song }}</span> · <span translate="no">{{ part }}</span></p>
                <section class="ge-vs-them" aria-hidden="true">
                    <span class="ge-vs-face">{{ by ? initial(by) : '?' }}</span>
                    <span class="ge-vs-role">{{ t('game.versus.them') }}</span>
                    <b translate="no">{{ by || t('game.versus.someone') }}</b>
                    <span class="ge-vs-score">{{ formatNumber(score) }}</span>
                </section>
                <p class="ge-vs-vs" aria-hidden="true">{{ t('game.versus.vs') }}</p>
                <section class="ge-vs-you" aria-hidden="true">
                    <span class="ge-vs-face">{{ me ? initial(me) : '1' }}</span>
                    <span class="ge-vs-role">{{ t('game.home.player') }}</span>
                    <b>{{ t('game.versus.you') }}</b>
                    <span class="ge-vs-score">{{ yours === null ? t('game.versus.none') : formatNumber(yours) }}</span>
                </section>
                <div class="ge-vs-foot">
                    <p class="ge-vs-rule">{{ t('game.versus.rule', { score: formatNumber(score) }) }}</p>
                    <button type="button" class="btn-primary btn-lg w-full" :disabled="busy" @click="$emit('sing')">{{ t('boards.challenge.sing', { part }) }}</button>
                </div>
            </template>
        </main>`,
    mounted() {
        cue('challenger');
        this.going = run(BANNER_MS, () => {}, { motion: true });
        this.going.done.then(() => (this.banner = false));
        this.key = () => this.skip();
        window.addEventListener('keydown', this.key, { once: true });
    },
    unmounted() {
        this.going?.stop();
        window.removeEventListener('keydown', this.key);
    },
    methods: {
        skip() {
            this.going?.skip();
        },
    },
};
