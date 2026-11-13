// The Staff (docs/singing-ux-plan.md, "Staff"): the current line as a music stand would show it, from
// staffLayout (js/sing.js): the clef by part, the key signature, the notes with their accidentals,
// ledger lines, stems and the words beneath, and the note being sung lit. It pages line by line.
// SVG, so it's sharp at any size; it only changes when the line or the lit note does.
// On a phone (Sing's `wrap`): a note is never closer to the next than their words need, and a line too long to read at
// that width wraps at its bar lines into rows, each with its clef and key, as printed music does (busy 6/8 lines shrank
// until their words ran together). It shows the row being sung and the next, turning like a stand's page, so a long line
// never pushes the words off the screen. A line that fits stays one row, as it always was; the editor keeps exact spacing.

const GAP = 10; // between staff lines
const TOP = 34; // room above the staff for high ledger lines
const LEFT = 64; // the clef and key signature
const BEAT = 46; // width per beat
const METRE = 22; // room for a time signature where the metre changes (docs/meters-plan.md)
// Where each sharp and flat of a signature sits (0 is the bottom line), on the treble clef; the bass
// clef's sit two steps lower.
const SHARP_AT = [8, 5, 9, 6, 3, 7, 4];
const FLAT_AT = [4, 7, 3, 6, 2, 5, 1];
const GLYPH = { sharp: '\u266F', flat: '\u266D', natural: '\u266E', doubleSharp: '\u{1D12A}', doubleFlat: '\u{1D12B}' };
const CLEF = { treble: '\u{1D11E}', treble8: '\u{1D120}', bass: '\u{1D122}' };
const MUSIC_FONT = "'Noto Music', 'Apple Symbols', 'Segoe UI Symbol', serif"; // i18n-ignore: fonts with the clefs
const CHAR = 8; // a word's width per letter at 13px, generously (the game skin's pixel face is the widest)
const NOTE_GAP = 18; // heads never closer than this
const WORD_GAP = 6; // between two words
const MIN_SCALE = 0.85; // a line drawn smaller than this on its screen wraps instead

const wordWidth = (note) => (note.lyric ? note.lyric.length * CHAR : 0);

/**
 * Each note's x from the line's first beat: by its beat (BEAT a beat), pushed right only where its head or its word would
 * touch the one before. `end`: where the line's last note's time ends. `spaced` false: by beat alone (the editor's).
 */
export function staffSpacing(notes, { spaced = true } = {}) {
    const start = notes[0]?.beat ?? 0;
    const xs = [];
    let shift = 0;
    notes.forEach((n, i) => {
        let x = (n.beat - start) * BEAT + shift;
        if (spaced && i > 0) {
            const need = Math.max(NOTE_GAP + (n.accidental ? 10 : 0), (wordWidth(notes[i - 1]) + wordWidth(n)) / 2 + WORD_GAP);
            if (x < xs[i - 1] + need) {
                shift += xs[i - 1] + need - x;
                x = xs[i - 1] + need;
            }
        }
        xs.push(x);
    });
    const last = notes.at(-1);
    const end = last ? Math.max(xs.at(-1) + last.length * BEAT, xs.at(-1) + wordWidth(last) / 2 + WORD_GAP) : 4 * BEAT;
    return { xs, end };
}

/** A row's width in the staff's units (its px at full size): the clef and key, the notes spaced, at least four beats. */
export function staffRowWidth(notes, signatureCount, { spaced = true } = {}) {
    return LEFT + signatureCount * 7 + Math.max(4 * BEAT, staffSpacing(notes, { spaced }).end + 14) + 12;
}

/**
 * Where a line breaks into rows to be read at `room` px: at bar lines, as many bars to a row as fit (at least one). Returns
 * the note index each row starts at. One row when the whole line fits at MIN_SCALE or better.
 */
export function staffRows(layout, signatureCount, room) {
    const notes = layout.notes;
    if (!room || notes.length < 2 || staffRowWidth(notes, signatureCount) * MIN_SCALE <= room) return [0];
    const cuts = [...new Set(layout.bars.map((beat) => notes.findIndex((n) => n.beat >= beat - 1e-6)).filter((i) => i > 0))].sort((a, b) => a - b);
    const starts = [0];
    let from = 0;
    for (;;) {
        const ends = [...cuts.filter((c) => c > from), notes.length];
        let end = ends[0];
        for (const c of ends.slice(1)) {
            if (staffRowWidth(notes.slice(from, c), signatureCount) <= room) end = c;
            else break;
        }
        if (end >= notes.length) break;
        starts.push(end);
        from = end;
    }
    return starts;
}

const StaffRow = {
    props: {
        layout: { type: Object, required: true },
        current: { type: Number, default: -1 }, // index of the lit note, in this row
        offset: { type: Number, default: 0 }, // this row's first note in the whole line (data-staff-note stays the line's)
        spaced: Boolean, // by the words (staffSpacing), not by beat alone
        fit: { type: Number, default: null }, // a wrapped row: drawn at its own width (px), never stretched to the screen's
        color: { type: String, required: true },
        // The note editor's staff: a note pressed is picked, a press elsewhere places a note there
        // (`pick`, index; `place`, { beat, position } from the line's start), with two beats' room after.
        editable: Boolean,
    },
    emits: ['pick', 'place'],
    setup: () => ({ GAP, BEAT, METRE, GLYPH, CLEF, MUSIC_FONT }),
    template: `
        <svg ref="svg" :viewBox="'0 0 ' + width + ' ' + height" class="max-h-56 overflow-visible" :class="[editable ? 'cursor-crosshair touch-none' : '', fit ? 'block' : 'w-full']" :style="fit ? { width: fit + 'px', maxWidth: '100%' } : null" role="img" :aria-label="label" translate="no" @pointerdown="place">
            <line v-for="i in 5" :key="'l' + i" :x1="0" :x2="width" :y1="y(2 * (i - 1))" :y2="y(2 * (i - 1))" stroke="currentColor" stroke-opacity="0.45" stroke-width="1" />
            <text :x="4" :y="clefY" :font-size="layout.clef === 'bass' ? 34 : 44" fill="currentColor" :font-family="MUSIC_FONT">{{ CLEF[layout.clef] }}</text>
            <text v-for="(at, i) in signature" :key="'k' + i" :x="34 + i * 7" :y="y(at) + 5" font-size="18" fill="currentColor" :font-family="MUSIC_FONT">{{ GLYPH[layout.signature.type] }}</text>
            <line v-for="b in layout.bars" :key="'b' + b" :x1="barX(b)" :x2="barX(b)" :y1="y(8)" :y2="y(0)" stroke="currentColor" stroke-opacity="0.45" />
            <!-- A new time signature where the metre changes, after its bar line: stacked figures, as printed. -->
            <g v-for="m in changes" :key="'m' + m.beat" fill="currentColor" font-weight="700" font-size="17" text-anchor="middle" data-staff-metre>
                <text :x="barX(m.beat) + METRE / 2" :y="y(6) + 6">{{ m.timeSignature.split('/')[0] }}</text>
                <text :x="barX(m.beat) + METRE / 2" :y="y(2) + 6">{{ m.timeSignature.split('/')[1] }}</text>
            </g>
            <g v-for="(n, i) in layout.notes" :key="'n' + i" :fill="i === current ? color : 'currentColor'" :stroke="i === current ? color : 'currentColor'" :class="editable ? 'cursor-pointer' : ''" :data-staff-note="i + offset" @pointerdown.stop="editable && $emit('pick', i)">
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
        changes: (vm) => vm.layout.changes ?? [],
        beats() {
            const last = this.layout.notes[this.layout.notes.length - 1];
            return last ? last.beat + last.length - this.start : 4;
        },
        width() {
            if (this.spaced) return staffRowWidth(this.layout.notes, this.signature.length) + this.changes.length * METRE;
            return LEFT + this.signature.length * 7 + (Math.max(4, this.beats) + (this.editable ? 2 : 0)) * BEAT + 12 + this.changes.length * METRE;
        },
        /** Each note's x from the row's start (by beat, or by the words when `spaced`). */
        spacing: (vm) => staffSpacing(vm.layout.notes, { spaced: vm.spaced }),
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
            const room = this.changes.filter((m) => m.beat <= beat + 1e-6).length * METRE; // each time signature written so far
            return LEFT + this.signature.length * 7 + this.along(beat) + 14 + room;
        },
        /** A beat's place along the row: each note where it's spaced, a beat between two notes in proportion (a bar line). */
        along(beat) {
            const notes = this.layout.notes;
            const { xs } = this.spacing;
            if (!this.spaced || !notes.length) return (beat - this.start) * BEAT;
            if (beat < notes[0].beat) return (beat - notes[0].beat) * BEAT;
            let i = notes.length - 1;
            while (i > 0 && notes[i].beat > beat + 1e-6) i--;
            if (i === notes.length - 1) return xs[i] + (beat - notes[i].beat) * BEAT;
            const span = notes[i + 1].beat - notes[i].beat;
            return xs[i] + (span > 0 ? ((beat - notes[i].beat) / span) * (xs[i + 1] - xs[i]) : 0);
        },
        /** A bar line's x: before the bar's first note, and before a time signature written there. */
        barX(beat) {
            const here = this.changes.some((m) => Math.abs(m.beat - beat) < 1e-6) ? METRE : 0;
            return this.x(beat) - BEAT / 4 - here;
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

/** The line as rows (`wrap`), or one row as it always was. */
export const StaffLine = {
    components: { StaffRow },
    props: {
        layout: { type: Object, required: true },
        current: { type: Number, default: -1 }, // index of the lit note
        color: { type: String, required: true },
        editable: Boolean, // the note editor's staff (StaffRow says what that means)
        wrap: Boolean, // Sing's: spaced by the words, and in rows when the screen is too narrow for the line
    },
    emits: ['pick', 'place'],
    data: () => ({ room: 0 }),
    template: `
        <div ref="box" class="space-y-2" data-staff :data-rows="rows.length" :data-row-at="at">
            <StaffRow v-for="row in shown" :key="row.from" :layout="row.layout" :offset="row.from" :current="current - row.from" :color="color" :editable="editable" :spaced="wrap" :fit="row.fit" @pick="(i) => $emit('pick', i + row.from)" @place="(at) => $emit('place', at)" />
        </div>`,
    computed: {
        signatureCount: (vm) => vm.layout.signature?.count ?? 0,
        /** The row with the lit note (the first before any is lit). */
        at() {
            if (this.current < 0) return 0;
            let at = 0;
            this.rows.forEach((row, r) => {
                if (row.from <= this.current) at = r;
            });
            return at;
        },
        /** That row and the next. */
        shown: (vm) => vm.rows.slice(vm.at, vm.at + 2),
        rows() {
            const notes = this.layout.notes;
            const starts = this.wrap ? staffRows(this.layout, this.signatureCount, this.room) : [0];
            if (starts.length === 1) return [{ from: 0, layout: this.layout, fit: null }];
            return starts.map((from, r) => {
                const to = starts[r + 1] ?? notes.length;
                const first = notes[from].beat;
                const next = to < notes.length ? notes[to].beat : Infinity;
                const sub = notes.slice(from, to);
                return {
                    from,
                    layout: { ...this.layout, notes: sub, bars: this.layout.bars.filter((b) => b > first + 1e-6 && b < next - 1e-6), changes: (this.layout.changes ?? []).filter((m) => m.beat >= first - 1e-6 && m.beat < next - 1e-6) },
                    fit: staffRowWidth(sub, this.signatureCount),
                };
            });
        },
    },
    mounted() {
        if (!this.wrap || typeof ResizeObserver === 'undefined') return;
        this.observer = new ResizeObserver(() => (this.room = this.$refs.box?.clientWidth ?? 0));
        this.observer.observe(this.$refs.box);
        this.room = this.$refs.box.clientWidth;
    },
    beforeUnmount() {
        this.observer?.disconnect();
    },
};
