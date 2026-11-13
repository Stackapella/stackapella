// One system of printed music (js/print-score.js's layout) as SVG: the staves with their clef, key and
// (on the first) time, the notes, rests, ties, triplets, breath marks, the chords over the tune and a
// label under each note. Shared by the printable parts (views/print.js, black on white) and the
// sight-singing stave (components/read-stave.js, in the skin's ink, the note being sung lit).

import { GAP, signaturePositions, STAFF } from '../print-score.js';

const HALF = GAP / 2;
const GLYPH = { sharp: '♯', flat: '♭', natural: '♮', doubleSharp: '\u{1D12A}', doubleFlat: '\u{1D12B}' };
const CLEF = { treble: '\u{1D11E}', treble8: '\u{1D120}', bass: '\u{1D122}' };
const MUSIC_FONT = "'Noto Music', 'Apple Symbols', 'Segoe UI Symbol', serif"; // i18n-ignore: fonts with the clefs

export const ScoreSystem = {
    props: {
        sys: { type: Object, required: true },
        layout: { type: Object, required: true }, // printLayout's: { edge, signature, time }
        songKey: { type: String, default: 'C' },
        ink: { type: String, default: '#000' }, // paper's black, or the skin's text colour
        nameOf: { type: Function, default: null }, // (staff, first) => the part's name, left of the staff
        labelOf: { type: Function, default: (item) => item.word }, // the text under a note: its word, or its name
        labelOpacity: { type: Number, default: 1 },
        lit: { type: Number, default: -1 }, // the written note being sung (its index), drawn in `litColor`
        litColor: { type: String, default: '' },
    },
    setup: () => ({ GAP, STAFF, GLYPH, CLEF, MUSIC_FONT }),
    template: `
        <svg :viewBox="'0 0 ' + width + ' ' + sys.height" class="block w-full overflow-visible" role="img">
            <!-- And over the top staff, what the beat was and is ("♩. = ♩"): the pulse goes on unchanged. -->
            <text v-for="bar in sys.bars.filter((b) => b.time)" :key="'eq' + bar.index" :x="bar.x + 4" :y="sys.staves[0].y - 8 - (sys.staves[0].chords.length ? 30 : 0)" font-size="13" :fill="ink" data-score-equal>{{ (bar.time.from === 'dotted' ? '♩.' : '♩') + ' = ' + (bar.time.to === 'dotted' ? '♩.' : '♩') }}</text>
            <text v-if="sys.barNumber" :x="edge" :y="sys.staves[0].y - 8 - (sys.staves[0].chords.length ? 16 : 0)" font-size="11" font-style="italic" :fill="ink">{{ sys.barNumber }}</text>
            <line v-if="sys.staves.length > 1" :x1="edge" :x2="edge" :y1="sys.staves[0].y" :y2="sys.staves.at(-1).y + STAFF" :stroke="ink" stroke-width="1.6" />
            <g v-for="st in sys.staves" :key="st.key + (st.cue ? '-cue' : '')" :opacity="st.cue ? 0.55 : 1" :fill="ink" :stroke="ink">
                <text v-if="nameOf" :x="edge - 8" :y="st.y + STAFF / 2 + 5" font-size="14" text-anchor="end" stroke="none">{{ nameOf(st, sys.first) }}</text>
                <line v-for="i in 5" :key="'l' + i" :x1="edge" :x2="sys.right" :y1="st.y + (i - 1) * GAP" :y2="st.y + (i - 1) * GAP" stroke-width="0.8" />
                <text :x="edge + 3" :y="st.clef === 'bass' ? y(st, 6) + 14 : y(st, 2) + 14" :font-size="st.clef === 'bass' ? 34 : 44" stroke="none" :font-family="MUSIC_FONT">{{ CLEF[st.clef] }}</text>
                <text v-for="(p, i) in signature(st)" :key="'k' + i" :x="edge + 32 + i * 8" :y="y(st, p) + 5" font-size="18" stroke="none" :font-family="MUSIC_FONT">{{ GLYPH[layout.signature.type] }}</text>
                <template v-if="sys.first">
                    <text :x="timeX(st)" :y="y(st, 6) + 7" font-size="21" font-weight="700" font-family="serif" text-anchor="middle" stroke="none">{{ layout.time.beats }}</text>
                    <text :x="timeX(st)" :y="y(st, 2) + 7" font-size="21" font-weight="700" font-family="serif" text-anchor="middle" stroke="none">{{ layout.time.beatType }}</text>
                </template>
                <line v-for="(bar, b) in sys.bars.slice(1)" :key="'b' + b" :x1="bar.x" :x2="bar.x" :y1="st.y" :y2="st.y + STAFF" stroke-width="1" />
                <!-- Where the metre changes (docs/meters-plan.md): the new time signature after its bar line. -->
                <g v-for="bar in sys.bars.filter((b) => b.time)" :key="'ts' + bar.index" font-size="18" font-weight="700" font-family="serif" text-anchor="middle" stroke="none" data-score-metre>
                    <text :x="bar.x + 11" :y="y(st, 6) + 6">{{ bar.time.beats }}</text>
                    <text :x="bar.x + 11" :y="y(st, 2) + 6">{{ bar.time.beatType }}</text>
                </g>
                <line :x1="sys.right" :x2="sys.right" :y1="st.y" :y2="st.y + STAFF" stroke-width="1" />
                <rect v-if="sys.last" :x="sys.right + 2" :y="st.y" width="4" :height="STAFF" stroke="none" />
                <text v-for="(c, i) in st.chords" :key="'c' + i" :x="c.x" :y="st.y - Math.max(10, (st.high - 8) * 5 + 10) - 4" font-size="14" font-weight="600" stroke="none">{{ c.name }}</text>
                <text v-for="(tr, i) in st.triplets" :key="'t' + i" :x="tr.x" :y="st.y - Math.max(8, (st.high - 8) * 5 + 8)" font-size="10" font-style="italic" text-anchor="middle" stroke="none">3</text>
                <path v-for="(tie, i) in st.ties" :key="'tie' + i" :d="tiePath(st, tie)" fill="none" stroke-width="1.1" />
                <g v-for="(it, i) in st.items" :key="'i' + i" :fill="isLit(it) ? litColor : ink" :stroke="isLit(it) ? litColor : ink" :data-score-note="it.kind === 'note' ? it.note : null" :data-lit="isLit(it) ? 'true' : null">
                    <template v-if="it.kind === 'rest'">
                        <rect v-if="it.type === 'whole'" :x="it.x - 6" :y="y(st, 6)" width="12" height="5" stroke="none" />
                        <rect v-else-if="it.type === 'half'" :x="it.x - 6" :y="y(st, 4) - 5" width="12" height="5" stroke="none" />
                        <path v-else-if="it.type === 'quarter'" :d="quarterRest(st, it)" fill="none" stroke-width="2" />
                        <g v-else>
                            <line :x1="it.x + 3" :y1="y(st, 5.5)" :x2="it.x - 2" :y2="y(st, it.type === 'eighth' ? 2 : 1)" stroke-width="1.3" />
                            <circle :cx="it.x - 1" :cy="y(st, 5.2)" r="2.2" stroke="none" />
                            <circle v-if="it.type !== 'eighth'" :cx="it.x - 2.5" :cy="y(st, 3.4)" r="2.2" stroke="none" />
                        </g>
                        <circle v-if="it.dot" :cx="it.x + 9" :cy="y(st, 5)" r="1.7" stroke="none" />
                    </template>
                    <template v-else>
                        <line v-for="p in it.ledgers" :key="'led' + p" :x1="it.x - 10" :x2="it.x + 10" :y1="y(st, p)" :y2="y(st, p)" stroke-width="1" />
                        <text v-if="it.accidental" :x="it.x - 20" :y="y(st, it.position) + 5" font-size="16" stroke="none" :font-family="MUSIC_FONT">{{ GLYPH[it.accidental] }}</text>
                        <ellipse :cx="it.x" :cy="y(st, it.position)" rx="6.2" ry="4.4" :transform="'rotate(-20 ' + it.x + ' ' + y(st, it.position) + ')'" :fill="it.hollow ? 'none' : isLit(it) ? litColor : ink" stroke-width="1.5" />
                        <line v-if="!it.stemless" :x1="stemX(it)" :x2="stemX(it)" :y1="y(st, it.position)" :y2="stemEnd(st, it)" stroke-width="1.2" />
                        <path v-for="k in it.flags" :key="'f' + k" :d="flag(st, it, k)" fill="none" stroke-width="1.5" />
                        <circle v-if="it.dot" :cx="it.x + 11" :cy="y(st, it.position % 2 === 0 ? it.position + 1 : it.position)" r="1.7" stroke="none" />
                        <text v-if="it.breath" :x="it.x + 14" :y="st.y - 3" font-size="16" stroke="none" font-family="serif">,</text>
                        <text v-if="!it.tieIn && labelOf(it, st)" :x="it.x" :y="st.lyricY" font-size="15" text-anchor="middle" stroke="none" :opacity="isLit(it) ? 1 : labelOpacity">{{ labelOf(it, st) }}</text>
                    </template>
                </g>
            </g>
        </svg>`,
    computed: {
        edge: (vm) => vm.layout.edge,
        /** The system's width: the page's, or (a reading line) only as wide as its music. */
        width: (vm) => Math.max(vm.sys.right + 8, vm.layout.width ?? 0),
    },
    methods: {
        isLit(it) {
            return it.kind === 'note' && this.lit >= 0 && it.note === this.lit;
        },
        y(st, position) {
            return st.y + (8 - position) * HALF;
        },
        signature(st) {
            return signaturePositions(this.songKey, st.clef);
        },
        timeX(st) {
            return this.edge + 32 + this.signature(st).length * 8 + 10;
        },
        stemX(it) {
            return it.x + (it.stem === 'up' ? 5.6 : -5.6);
        },
        stemEnd(st, it) {
            return this.y(st, it.position) + (it.stem === 'up' ? -32 : 32);
        },
        flag(st, it, k) {
            const x = this.stemX(it);
            const dir = it.stem === 'up' ? 1 : -1;
            const y = this.stemEnd(st, it) + dir * (k - 1) * 7;
            return `M ${x} ${y} q 8 ${dir * 6} 7 ${dir * 16}`;
        },
        tiePath(st, tie) {
            const y = this.y(st, tie.position) + (tie.up ? -6 : 6);
            const lift = tie.up ? -7 : 7;
            return `M ${tie.x1} ${y} Q ${(tie.x1 + tie.x2) / 2} ${y + lift} ${tie.x2} ${y}`;
        },
        /** A quarter rest, a zigzag down the middle of the staff. */
        quarterRest(st, it) {
            const top = this.y(st, 7);
            return `M ${it.x - 2} ${top} l 5 7 l -5 6 l 5 7 q -7 -3 -3 6`;
        },
    },
};
