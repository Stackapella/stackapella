// A lesson's title card (docs/game-elements-plan.md; the proposal's board 04, after Sonic 2): the
// first time a lesson opens on this device, bands in the unit's colour slide in with "Stage 2-3", the
// unit's name and the lesson's, hold, and slide away, 1.2 s in all. A tap or any key ends it at once
// (the tap stops here: it never also presses the button under the card), and nothing waits on it. Reduced motion: the card shows still, then goes. Not on a repeat visit.

import { local } from '../storage.js';
import { formatNumber } from '../i18n.js';
import { still } from './effects.js';
import { run } from './motion.js';

const KEY = 'stackapella.titlesSeen';
export const TITLE_MS = 1200;
export const STILL_MS = 800;

function seen() {
    try {
        const list = JSON.parse(local.get(KEY) || '[]');
        return Array.isArray(list) ? list : [];
    } catch (error) {
        console.warn('The title cards seen are unreadable; starting again', error);
        return [];
    }
}

/** Whether this lesson's card was shown on this device. */
export const titleSeen = (lessonId) => seen().includes(lessonId);

/** Marks this lesson's card as shown (the last 500 kept). */
export function markTitleSeen(lessonId) {
    const list = seen().filter((id) => id !== lessonId);
    list.push(lessonId);
    local.set(KEY, JSON.stringify(list.slice(-500)));
}

export const TitleCard = {
    props: {
        unit: { type: Number, required: true }, // 1-based
        lesson: { type: Number, required: true }, // 1-based, in its unit
        unitTitle: { type: String, required: true },
        lessonTitle: { type: String, required: true },
        colour: { type: String, required: true },
        ms: { type: Number, default: TITLE_MS }, // how long it shows (reduced motion: STILL_MS)
    },
    emits: ['done'],
    data: () => ({ still: still() }),
    setup: () => ({ formatNumber }),
    template: `
        <div class="ge-title" :class="still ? 'is-still' : ''" :style="{ '--ge-unit': colour }" aria-hidden="true" data-game-title @click.stop.prevent="end">
            <div class="ge-title-band">
                <span class="ge-title-stripes"><i></i><i></i><i></i><i></i></span>
            </div>
            <div class="ge-title-foot"><span class="ge-title-unit">{{ t('game.title.unit', { number: unit }) }}</span></div>
            <div class="ge-title-name">
                <span class="ge-title-stage">{{ t('game.title.stage', { unit, lesson }) }}</span>
                <b translate="no">{{ unitTitle }}</b>
                <i><span>{{ t('game.title.lesson', { number: lesson }) }}</span> · <span translate="no">{{ lessonTitle }}</span></i>
            </div>
            <span class="ge-title-act">{{ formatNumber(lesson) }}</span>
        </div>`,
    mounted() {
        this.going = run(this.still ? Math.min(this.ms, STILL_MS) : this.ms, () => {}, { motion: true });
        this.going.done.then(() => this.$emit('done'));
        // The key that ends it does only that (Enter never also presses what's under the card).
        this.key = (event) => {
            if (!(event.ctrlKey || event.metaKey || event.altKey)) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.end();
        };
        window.addEventListener('keydown', this.key, { once: true, capture: true });
    },
    unmounted() {
        this.going?.stop();
        window.removeEventListener('keydown', this.key, { capture: true });
    },
    methods: {
        end() {
            this.going?.skip();
        },
    },
};
