// The chord picker's picture of a progression (Hillel: "find a graphical way to represent the 6
// main chords in a key ... HOME, the 4 and 5 ... and all relative minors"; "for the minor maybe show
// the colors washed out"; "show letters by default but add toggle to show number or numerals and
// remember the option"). A key's six chords are three families, as the circle of fifths orders them:
// IV with its relative minor ii (Lift), I with vi (Home), V with iii (Pull back home). Each chord is
// its family's glass shape, a minor in the family's colour washed out (paler and greyer); a minor key
// counts from its relative major, so i is Home's washed shape. A chord outside the six (a ♭VII) is a
// grey dashed outline. Under each, its name: letters in C (a minor progression in A minor, C's
// relative, so every card spells the same six chords), scale numbers (1 4 5 6m) or Roman numerals.

import { chordName, chordOf, parseNumeral } from '../../theory.js';
import { mixColor } from '../../glass.js';
import { appearance } from '../../stores/appearance.js';
import { Gems, solidColor } from '../gems.js';

export const FAMILY_COLORS = { lift: 'var(--color-part-3)', home: 'var(--color-part-4)', pull: 'var(--color-part-1)' };
const HEIGHT = { lift: 26, home: 18, pull: 34 };
// A chord's family, counted from the major key (a minor key from its relative major).
const PLACE = { 5: 'lift', 0: 'home', 7: 'pull', 2: 'lift', 9: 'home', 4: 'pull' };
const MINOR_QUALITIES = ['min', 'min7', 'min6', 'dim', 'dim7'];
// Scale numbers from the tonic, a flat for a step between (♭7 for a ♭VII); a quality's mark after.
const DEGREES = { 0: '1', 1: '♭2', 2: '2', 3: '♭3', 4: '3', 5: '4', 6: '♭5', 7: '5', 8: '♭6', 9: '6', 10: '♭7', 11: '7' };
const MARKS = { maj: '', min: 'm', dim: '°', aug: '+', sus2: 'sus2', sus4: 'sus4', dim7: '°7', hdim7: 'ø7', dom7: '7', min7: 'm7', maj7: 'maj7', maj6: '6', min6: 'm6', add9: 'add9' };

/** A numeral's family and look in a progression of `mode`: `{ family, minor }` (family null outside the six). */
export function chordFamily(numeral, mode) {
    const parsed = parseNumeral(numeral);
    if (!parsed) return { family: null, minor: false };
    const fromMajor = mode === 'minor' ? (parsed.step + 9) % 12 : parsed.step;
    return { family: PLACE[fromMajor] ?? null, minor: MINOR_QUALITIES.includes(parsed.quality) };
}

/** A chord's three names: `{ letters }` in C (A minor for a minor progression), `{ numbers }` and `{ numerals }`. */
export function chordNames(numeral, mode) {
    const parsed = parseNumeral(numeral);
    const key = mode === 'minor' ? { tonic: 9, mode: 'minor' } : { tonic: 0, mode: 'major' };
    return {
        letters: chordName(chordOf(numeral, key.tonic), key),
        numbers: parsed ? DEGREES[parsed.step] + (MARKS[parsed.quality] ?? '') : numeral,
        numerals: numeral.replace(/^b/, '♭').replace(/^#/, '♯'),
    };
}

export const ChordWalk = {
    components: { Gems },
    props: {
        bars: { type: Array, required: true }, // [[{ numeral, family, minor, names }]]
        label: { type: String, default: 'letters' }, // 'letters', 'numbers' or 'numerals'
        now: { type: Number, default: -1 }, // the chord playing, counted through the bars
        faint: Boolean,
    },
    template: `
        <div class="relative flex flex-wrap items-end gap-x-3 gap-y-3">
            <Gems measure />
            <span v-for="(bar, b) in bars" :key="b" class="flex items-end gap-1.5">
                <span v-for="(c, k) in bar" :key="k" class="flex min-w-6 flex-col items-center gap-1" :data-chord="c.numeral" :data-minor="c.minor ? '' : null">
                    <span v-if="c.family" class="relative block w-6" :data-gem="colorOf(c)" :data-gem-state="index(b, k) === now ? 'now' : faint ? 'faint' : null" :style="{ height: HEIGHT[c.family] + 'px' }"></span>
                    <span v-else class="block h-[18px] w-6 rounded-lg border-[2.5px] border-dashed border-muted transition" :style="{ opacity: faint && index(b, k) !== now ? 0.45 : 1 }"></span>
                    <span class="whitespace-nowrap text-xs font-semibold text-cream" translate="no">{{ c.names[label] }}</span>
                </span>
            </span>
        </div>`,
    setup: () => ({ HEIGHT }),
    computed: {
        /** Each family's colour, and its minor's washed out, in the theme showing (the gems are drawn on a canvas). */
        colors() {
            void appearance.theme; // a theme change repaints
            const grey = appearance.theme.endsWith('-light') ? '#c9c4bc' : '#77746f';
            return Object.fromEntries(Object.entries(FAMILY_COLORS).map(([family, color]) => {
                const solid = solidColor(color);
                return [family, { major: solid, minor: mixColor(solid, grey, 0.55) }];
            }));
        },
    },
    methods: {
        colorOf(c) {
            return this.colors[c.family][c.minor ? 'minor' : 'major'];
        },
        /** A chord's place counted through the bars. */
        index(b, k) {
            let n = k;
            for (let i = 0; i < b; i++) n += this.bars[i].length;
            return n;
        },
    },
};
