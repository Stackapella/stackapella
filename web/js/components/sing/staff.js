// The Staff (docs/singing-ux-plan.md, "Staff"): the current line as a music stand would show it, from
// staffLayout (js/sing.js): the clef by part, the key signature, the notes with their accidentals,
// ledger lines, stems and the words beneath, and the note being sung lit. It pages line by line.
// SVG, so it's sharp at any size; it only changes when the line or the lit note does.

const GAP = 10; // between staff lines
const TOP = 34; // room above the staff for high ledger lines
const LEFT = 64; // the clef and key signature
const BEAT = 46; // width per beat
// Where each sharp and flat of a signature sits (0 is the bottom line), on the treble clef; the bass
// clef's sit two steps lower.
const SHARP_AT = [8, 5, 9, 6, 3, 7, 4];
const FLAT_AT = [4, 7, 3, 6, 2, 5, 1];
const GLYPH = { sharp: '\u266F', flat: '\u266D', natural: '\u266E', doubleSharp: '\u{1D12A}', doubleFlat: '\u{1D12B}' };
const CLEF = { treble: '\u{1D11E}', treble8: '\u{1D120}', bass: '\u{1D122}' };
const MUSIC_FONT = "'Noto Music', 'Apple Symbols', 'Segoe UI Symbol', serif"; // i18n-ignore: fonts with the clefs

export const StaffLine = {
    props: {
        layout: { type: Object, required: true },
        current: { type: Number, default: -1 }, // index of the lit note
        color: { type: String, required: true },
        // The note editor's staff: a note pressed is picked, a press elsewhere places a note there
        // (`pick`, index; `place`, { beat, position } from the line's start), with two beats' room after.
        editable: Boolean,
    },
    emits: ['pick', 'place'],
    setup: () => ({ GAP, BEAT, GLYPH, CLEF, MUSIC_FONT }),
    template: `
        <svg ref="svg" :viewBox="'0 0 ' + width + ' ' + height" class="max-h-56 overflow-visible" :class="editable ? 'cursor-crosshair touch-none' : ''" role="img" :aria-label="label" translate="no" @pointerdown="place">
            <line v-for="i in 5" :key="'l' + i" :x1="0" :x2="width" :y1="y(2 * (i - 1))" :y2="y(2 * (i - 1))" stroke="currentColor" stroke-opacity="0.45" stroke-width="1" />
            <text :x="4" :y="clefY" :font-size="layout.clef === 'bass' ? 34 : 44" fill="currentColor" :font-family="MUSIC_FONT">{{ CLEF[layout.clef] }}</text>
            <text v-for="(at, i) in signature" :key="'k' + i" :x="34 + i * 7" :y="y(at) + 5" font-size="18" fill="currentColor" :font-family="MUSIC_FONT">{{ GLYPH[layout.signature.type] }}</text>
            <line v-for="b in layout.bars" :key="'b' + b" :x1="x(b) - BEAT / 4" :x2="x(b) - BEAT / 4" :y1="y(8)" :y2="y(0)" stroke="currentColor" stroke-opacity="0.45" />
            <g v-for="(n, i) in layout.notes" :key="'n' + i" :fill="i === current ? color : 'currentColor'" :stroke="i === current ? color : 'currentColor'" :class="editable ? 'cursor-pointer' : ''" :data-staff-note="i" @pointerdown.stop="editable && $emit('pick', i)">
                <line v-for="p in n.ledgers" :key="p" :x1="x(n.beat) - 10" :x2="x(n.beat) + 10" :y1="y(p)" :y2="y(p)" stroke-width="1.2" />
                <text v-if="n.accidental" :x="x(n.beat) - 22" :y="y(n.position) + 5" font-size="17" stroke="none" :font-family="MUSIC_FONT">{{ GLYPH[n.accidental] }}</text>
                <ellipse :cx="x(n.beat)" :cy="y(n.position)" rx="6.2" ry="4.4" :transform="'rotate(-20 ' + x(n.beat) + ' ' + y(n.position) + ')'" :fill="n.hollow ? 'none' : undefined" stroke-width="1.6" />
                <line v-if="n.length < 4" :x1="stemX(n)" :x2="stemX(n)" :y1="y(n.position)" :y2="stemEnd(n)" stroke-width="1.3" />
                <path v-for="k in n.flags" :key="'f' + k" :d="flag(n, k)" fill="none" stroke-width="1.6" />
                <circle v-if="n.dotted" :cx="x(n.beat) + 11" :cy="y(n.position % 2 === 0 ? n.position + 1 : n.position)" r="1.8" stroke="none" />
                <text v-if="n.lyric" :x="x(n.beat)" :y="lyricY" font-size="13" text-anchor="middle" stroke="none" translate="no">{{ n.lyric }}</text>
            </g>
        </svg>`,
    computed: {
        start: (vm) => vm.layout.notes[0]?.beat ?? 0,
        beats() {
            const last = this.layout.notes[this.layout.notes.length - 1];
            return last ? last.beat + last.length - this.start : 4;
        },
        width() {
            return LEFT + this.signature.length * 7 + (Math.max(4, this.beats) + (this.editable ? 2 : 0)) * BEAT + 12;
        },
        lowest: (vm) => Math.min(0, ...vm.layout.notes.map((n) => n.position)),
        height: (vm) => TOP + 8 * (GAP / 2) + Math.max(0, -vm.lowest - 1) * (GAP / 2) + 40,
        lyricY: (vm) => vm.y(Math.min(-3, vm.lowest - 3)) + 6,
        clefY: (vm) => (vm.layout.clef === 'bass' ? vm.y(6) + 14 : vm.y(2) + 14),
        signature() {
            const s = this.layout.signature;
            const at = s.type === 'sharp' ? SHARP_AT : FLAT_AT;
            const shift = this.layout.clef === 'bass' ? -2 : 0;
            return at.slice(0, s.count).map((p) => p + shift);
        },
        label() {
            return this.layout.notes.map((n) => n.name).join(', ');
        },
    },
    methods: {
        /** A press on the staff, not on a note: where it is, as a beat from the line's start and a staff position. */
        place(event) {
            if (!this.editable) return;
            const svg = this.$refs.svg;
            const at = new DOMPoint(event.clientX, event.clientY).matrixTransform(svg.getScreenCTM().inverse());
            const beat = (at.x - LEFT - this.signature.length * 7 - 14) / BEAT + this.start;
            this.$emit('place', { beat: Math.max(this.start, beat), position: Math.round(8 - (at.y - TOP) / (GAP / 2)) });
        },
        x(beat) {
            return LEFT + this.signature.length * 7 + (beat - this.start) * BEAT + 14;
        },
        y(position) {
            return TOP + (8 - position) * (GAP / 2);
        },
        stemX(n) {
            return this.x(n.beat) + (n.stem === 'up' ? 5.6 : -5.6);
        },
        stemEnd(n) {
            return this.y(n.position) + (n.stem === 'up' ? -32 : 32);
        },
        /** An eighth's (or sixteenth's second) flag, curling away from the stem's end. */
        flag(n, k) {
            const x = this.stemX(n);
            const dir = n.stem === 'up' ? 1 : -1;
            const y = this.stemEnd(n) + dir * (k - 1) * 7;
            return `M ${x} ${y} q 8 ${dir * 6} 7 ${dir * 16}`;
        },
    },
};
