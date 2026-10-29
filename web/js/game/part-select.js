// Choose your part as a character select (docs/game-elements-plan.md; the proposal's board 09, after
// Street Fighter II's select grid, NBA Jam's rating bars and Mortal Kombat's lock-in), as a reskin of
// the part list: each part a portrait in its colour carrying its own numbers from the song itself:
// Range (its lowest to highest note), Busy (how many notes) and Leaps (how often it jumps a fourth or
// more) as small meters, and "Best for you" when the range check says so. A 1P cursor in your accent (it blinks 5 times, then rests) follows the pointer and the
// arrows; a tap or Enter locks it in (the portrait flashes once). No difficulty is invented.

import { tk } from '../i18n.js';
import { midiName } from '../dsp.js';
import { session, templatePart } from '../stores/session.js';
import { still } from './effects.js';
import { run } from './motion.js';
import { cue } from './chip.js';

export const LEAP = 5; // semitones: a fourth or more
export const CELLS = 8;
const LEAP_WORDS = { few: tk('game.select.few'), some: tk('game.select.some'), many: tk('game.select.many') };

/** A part's numbers from its notes: `{ low, high, notes, leaps, leapShare }`. */
export function partStats(notes = []) {
    const sung = [...notes].filter((n) => Number.isFinite(n.pitch)).sort((a, b) => a.beat - b.beat);
    let leaps = 0;
    for (let i = 1; i < sung.length; i++) if (Math.abs(sung[i].pitch - sung[i - 1].pitch) >= LEAP) leaps++;
    const pitches = sung.map((n) => n.pitch);
    return {
        low: pitches.length ? Math.min(...pitches) : null,
        high: pitches.length ? Math.max(...pitches) : null,
        notes: sung.length,
        leaps,
        leapShare: sung.length > 1 ? leaps / (sung.length - 1) : 0,
    };
}

/** Few, some or many leaps, by how often the part jumps. */
export const leapWord = (share) => (share < 0.1 ? 'few' : share < 0.25 ? 'some' : 'many');

/** Lit cells of CELLS for `value` of the most any part has (at least one when there's any). */
export const cellsOf = (value, most) => (value > 0 && most > 0 ? Math.max(1, Math.round((value / most) * CELLS)) : 0);

export const PartSelect = {
    props: {
        parts: { type: Array, required: true }, // the chooser's: [{ slot, name, range, recorded }]
        transpose: { type: Number, default: 0 },
        suggested: { type: String, default: null }, // the slot id the range check suggests
    },
    emits: ['choose'],
    data() {
        const at = Math.max(0, this.parts.findIndex((p) => p.slot.id === this.suggested));
        return { cursor: at, locking: null, blinking: !still() };
    },
    setup: () => ({ midiName, LEAP_WORDS, CELLS, leapWord, cellsOf }),
    // Each portrait carries its own numbers (the coordinator, after Hillel's "reskin" note): no stats
    // card. A tap chooses it, as the list did, after the lock-in flash; the 1P cursor follows the
    // pointer and the keyboard, starting on the part that fits your voice.
    template: `
        <ul class="ge-select-grid" data-game-select @keydown="key">
            <li v-for="(part, i) in parts" :key="part.slot.id">
                <button ref="portrait" type="button" class="ge-portrait" :class="[i === cursor ? 'is-picked' : '', i === cursor && blinking ? 'is-blinking' : '', locking === i ? 'is-locking' : '']" :style="{ '--ge-part': paint(part.slot.color) }"
                    :data-slot="part.slot.id" @click="lock(i)" @focus="cursor = i" @pointerenter="cursor = i">
                    <span v-if="i === cursor" class="ge-1p" aria-hidden="true">{{ t('game.select.cursor') }}</span>
                    <span class="ge-portrait-letter" aria-hidden="true" translate="no">{{ part.name.slice(0, 1) }}</span>
                    <span class="gem ge-portrait-gem" :style="{ '--gem': paint(part.slot.color) }" aria-hidden="true"></span>
                    <span class="ge-portrait-name" translate="no">{{ part.name }}</span>
                    <span v-if="part.slot.id === suggested" class="ge-fits">{{ t('range.bestForYou') }}</span>
                    <span class="ge-portrait-stats">
                        <span class="ge-stat"><span>{{ t('game.select.range') }}</span><span>{{ stats[i].low !== null ? t('sing.chooser.rangeShort', { low: midiName(stats[i].low + transpose), high: midiName(stats[i].high + transpose) }) : '' }}</span></span>
                        <span class="ge-stat"><span>{{ t('game.select.busy') }}</span><span class="ge-statbar" aria-hidden="true"><i v-for="n in CELLS" :key="n" :class="n <= cellsOf(stats[i].notes, most.notes) ? 'is-lit' : ''"></i></span><span class="sr-only">{{ t('game.select.notes', { count: stats[i].notes }) }}</span></span>
                        <span class="ge-stat"><span>{{ t('game.select.leaps') }}</span><span class="ge-statbar" aria-hidden="true"><i v-for="n in CELLS" :key="n" :class="n <= cellsOf(stats[i].leapShare, most.leapShare) ? 'is-lit' : ''"></i></span><span class="sr-only">{{ t(LEAP_WORDS[leapWord(stats[i].leapShare)]) }}</span></span>
                        <span v-if="part.recorded" class="ge-stat"><span>{{ t('sing.chooser.recorded') }}</span></span>
                    </span>
                </button>
            </li>
        </ul>`,
    computed: {
        stats() {
            return this.parts.map((p) => partStats(session.project ? templatePart(p.slot)?.guide?.notes ?? [] : []));
        },
        most() {
            return { notes: Math.max(0, ...this.stats.map((s) => s.notes)), leapShare: Math.max(0, ...this.stats.map((s) => s.leapShare)) };
        },
    },
    mounted() {
        // The 1P cursor blinks 5 times (twice a second), then rests.
        if (this.blinking) {
            this.blink = run(2500, () => {});
            this.blink.done.then(() => (this.blinking = false));
        }
    },
    unmounted() {
        this.blink?.stop();
        this.left = true; // left before the flash ended: nothing is chosen after the fact
        this.locked?.stop();
    },
    methods: {
        /** The arrows move the cursor (and the focus), by the grid's own columns (two on a phone, four wider). */
        key(event) {
            const style = getComputedStyle(this.$el);
            const rtl = style.direction === 'rtl';
            const columns = Math.max(1, style.gridTemplateColumns.split(' ').filter(Boolean).length);
            const step = { ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1, ArrowDown: columns, ArrowUp: -columns }[event.key];
            if (!step) return;
            event.preventDefault();
            const to = this.cursor + step;
            if (to < 0 || to >= this.parts.length) return;
            this.cursor = to;
            cue('cursor');
            this.$refs.portrait?.[to]?.focus();
        },
        /** The portrait flashes once, then the part is chosen; reduced motion chooses at once. */
        lock(i) {
            if (this.locking !== null) return;
            this.cursor = i;
            this.locking = i;
            cue('select');
            this.locked = run(still() ? 0 : 280, () => {});
            this.locked.done.then(() => {
                if (!this.left) this.$emit('choose', this.parts[i].slot.id);
            });
        },
    },
};
