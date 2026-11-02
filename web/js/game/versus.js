// A challenge link in game mode (Hillel's 101.png, 2026-11-01): "You've been challenged!" on a tilted red banner,
// then who challenges you, the song and part, the real score to beat (and your own best, or "No score yet"), and
// Accept or Not now. Every number is real: their score from the challenge, yours from the board.

import { formatNumber } from '../i18n.js';
import { cue } from './chip.js';
import { still } from './effects.js';

const VOICES = ['soprano', 'alto', 'tenor', 'bass'];

/** The theme's colour for a part (the four voices' own; others by their place). */
export function partColourVar(partKey, index = 0) {
    const voice = VOICES.indexOf(partKey);
    return `var(--color-part-${(voice >= 0 ? voice : index % 4) + 1})`;
}

/** A name's initial, for the portrait. */
export const initial = (name) => ((name || '').trim()[0] ?? '?').toUpperCase();

/** A name's initials, up to two, for the challenger's box. */
export const initials = (name) => (name || '').trim().split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();

export const Versus = {
    props: {
        by: { type: String, default: null }, // the challenger's name, or null ("someone": a guest, or anyone under 18)
        score: { type: Number, required: true },
        song: { type: String, required: true },
        part: { type: String, required: true }, // the part's name
        colour: { type: String, required: true }, // partColourVar's
        yours: { type: Number, default: null }, // your best on the part, or null
        me: { type: String, default: '' },
        heading: { type: String, required: true }, // the plain sentence, for screen readers
        busy: Boolean,
    },
    emits: ['sing', 'later'],
    data: () => ({ done: still() }),
    setup: () => ({ formatNumber, initials }),
    // Hillel's 101.png (the handoff's plan): INCOMING and 1P vs 2P across the top, a tilted red banner between hazard
    // stripes ("You've been challenged!") slides in with one flash, then the challenger (initials in a square, the
    // name large, "Challenges you"), the card (the song, the part, the score to beat) and Accept or Not now, each in
    // turn. A tap or a key shows it all at once; reduced motion starts there. Faint scanlines over it all.
    template: `
        <main class="ge-vs" :class="done ? 'is-done' : ''" :style="{ '--ge-part': colour, '--ge-part-text': colour.replace('--color-part-', '--color-part-text-') }" data-challenge data-game-versus @click="skip">
            <h1 class="sr-only">{{ heading }}</h1>
            <p class="ge-vs-top" aria-hidden="true"><span class="ge-vs-incoming">{{ t('game.versus.incoming') }}</span><span>{{ t('game.versus.players') }}</span></p>
            <div class="ge-vs-banner" aria-hidden="true" data-game-challenger><i class="ge-vs-hazard"></i><p class="ge-vs-band">{{ t('game.versus.challenger') }}</p><i class="ge-vs-hazard"></i></div>
            <section class="ge-vs-who" aria-hidden="true">
                <span v-if="by" class="ge-vs-face">{{ initials(by) }}</span>
                <p class="ge-vs-name" translate="no">{{ by || t('game.versus.someone') }}</p>
                <p class="ge-vs-says">{{ t('game.versus.challengesYou') }}</p>
            </section>
            <dl class="ge-vs-card">
                <div><dt>{{ t('game.versus.song') }}</dt><dd class="ge-vs-title" translate="no">{{ song }}</dd><dd><span class="ge-vs-part" translate="no">{{ part }}</span></dd></div>
                <div class="ge-vs-beat"><dt>{{ t('game.versus.toBeat') }}</dt><dd class="ge-vs-score">{{ formatNumber(score) }}</dd><dd class="ge-vs-yours">{{ yours === null ? t('game.versus.none') : t('game.versus.yoursSr', { score: formatNumber(yours) }) }}</dd></div>
            </dl>
            <div class="ge-vs-foot">
                <button type="button" class="btn-primary btn-lg w-full" :disabled="busy" data-challenge-accept @click.stop="$emit('sing')">{{ t('game.versus.accept') }}</button>
                <a href="/" class="ge-vs-later" data-challenge-later @click.prevent.stop="$emit('later')">{{ t('game.versus.notNow') }}</a>
            </div>
        </main>`,
    mounted() {
        if (!this.done) cue('challenger');
        this.key = () => this.skip();
        window.addEventListener('keydown', this.key, { once: true });
    },
    unmounted() {
        window.removeEventListener('keydown', this.key);
    },
    methods: {
        /** Everything at once (a tap or a key during the intro). */
        skip() {
            this.done = true;
        },
    },
};
