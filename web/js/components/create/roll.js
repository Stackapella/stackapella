// The creator's note lane (docs/template-creator-plan.md, "The note editor", after the Note lane of
// the singing UX): notes as bars in their part's colour, labelled with the syllable and the note's
// name, the bar lines (the pickup before the first), the other parts faint behind, the notes to
// check marked with an icon as well as a colour, the playhead and the live pitch line. Since
// Hillel's editor mockup (ux-spec 4.x, 5.7c): the notes are glass gems (the SVG form of the .gem
// recipe; since Hillel's review of 2026-10-28, js/glass.js's gemBar on a canvas under the SVG, the one
// recipe for every note), the other parts dashed ghosts, a bar ruler on top, octave labels down the side, the
// key's rows lighter, a selected note rimmed and glowing, a dragged one lifted. SVG, so a note is
// a target: with `editable`, a tap selects (Shift adds), a drag moves it (in pitch and
// time, snapped to the grid) or, from its end, changes its length, and a tap on empty lane adds a
// note there. Screen readers get the list view and the editor's announcements instead.

import { midiName } from '../../dsp.js';
import { Gems, gemInk } from '../gems.js';
import { formatNumber, t } from '../../i18n.js';
import { Playhead } from '../playhead.js';

const ROW = 18; // a semitone's height: room for a gem with its word inside
const RULER = 20; // the bar ruler along the top
const GEM = 24; // a note's gem: taller than its row (chunky), centred on it
const MAJOR = [0, 2, 4, 5, 7, 9, 11];
const MINOR = [0, 2, 3, 5, 7, 8, 10];
const EDGE_PX = 10; // grab a note's end within this to change its length

export const Roll = {
    props: {
        parts: { type: Array, required: true }, // [{ key, color, notes }]
        focus: { type: String, default: null }, // the part being edited; the others faint
        beatsPerBar: { type: Number, required: true },
        pickupBeats: { type: Number, default: 0 },
        endBeat: { type: Number, default: 8 },
        zoom: { type: Number, default: 48 }, // pixels a beat
        selected: { type: Array, default: () => [] }, // indexes in the focus part
        toCheck: { type: Array, default: () => [] }, // indexes in the focus part
        playhead: { type: Number, default: null },
        live: { type: Array, default: () => [] }, // [{ beat, midi }]: the pitch line as you sing
        grid: { type: Number, default: 0.5 },
        editable: Boolean,
        label: { type: String, default: '' },
        noteName: { type: Function, default: midiName },
        height: { type: Number, default: null }, // fixed rows (else fitted to the notes)
        keyOf: { type: Object, default: null }, // { tonic, minor }: the key's rows lighter (else the black keys darker)
        // The note editor: the playhead always shows and moves (dragged, by keys, or a press on the ruler: `seek`).
        seekable: Boolean,
    },
    emits: ['select', 'edit', 'add', 'seek'],
    components: { Gems, Playhead },
    data: () => ({ drag: null, hover: null }),
    template: `
        <div class="flex overflow-hidden rounded-2xl border border-edge bg-ink">
            <!-- The octave labels, staying put while the lane scrolls. -->
            <svg width="34" :height="rows * ROW + RULER + 4" class="block shrink-0 border-e border-edge bg-panel" aria-hidden="true">
                <text v-for="p in octaves" :key="p" x="30" :y="y(p) + ROW / 2 + 3.5" text-anchor="end" font-size="10" font-weight="700" class="fill-muted" translate="no">{{ noteName(p) }}</text>
            </svg>
            <div ref="scroller" class="relative min-w-0 flex-1 overflow-x-auto overflow-y-hidden [scrollbar-width:thin]">
            <!-- Three layers: the lane (rows, ruler, bars, the other parts), the gems on a canvas, then the
                 notes' targets, words and marks, the pitch line and the playhead. -->
            <svg :width="width" :height="rows * ROW + RULER + 4" class="block" aria-hidden="true">
                <rect v-for="r in rows" :key="'r' + r" x="0" :y="(r - 1) * ROW + RULER" :width="width" :height="ROW" :class="inKey(top - r + 1) ? 'fill-cream/[0.035]' : 'fill-black/20'" />
                <rect x="0" y="0" :width="width" :height="RULER" class="fill-panel" />
                <line v-for="b in bars" :key="'b' + b.beat" :x1="x(b.beat)" :x2="x(b.beat)" :y1="b.bar ? 0 : RULER" :y2="rows * ROW + RULER + 4" stroke="currentColor" :stroke-opacity="b.bar ? 0.3 : 0.08" class="text-cream" />
                <!-- The pickup bar named only where it fits before bar 1 (a short pickup at a small zoom has no room). -->
                <text v-if="pickupBeats > 0 && pickupBeats * zoom >= 64" :x="x(0) + 5" y="14" font-size="10" font-weight="800" letter-spacing="0.08em" class="fill-muted uppercase">{{ t('create.edit.pickupBar') }}</text>
                <text v-for="b in bars.filter((b) => b.bar)" :key="'n' + b.beat" :x="x(b.beat) + 5" y="14" font-size="11" font-weight="700" class="fill-muted">{{ t('create.edit.bar', { bar: b.number }) }}</text>
                <g v-for="part in behind" :key="part.key">
                    <rect v-for="(n, i) in part.notes.filter((m) => Number.isFinite(m.pitch))" :key="i" :x="x(n.beat)" :y="y(n.pitch) + 2" :width="barWidth(n, 3)" :height="ROW - 4" rx="6" :fill="part.color" fill-opacity="0.08" :stroke="part.color" stroke-opacity="0.55" stroke-width="1.25" stroke-dasharray="4 3" />
                </g>
            </svg>
            <Gems :bricks="gems" :width="width" :height="rows * ROW + RULER + 4" />
            <svg :width="width" :height="rows * ROW + RULER + 4" class="absolute start-0 top-0 block select-none" :class="editable ? 'touch-none' : ''" role="img" :aria-label="label"
                @pointerdown="down($event, null)" @pointermove="move" @pointerup="up" @pointercancel="drag = null">
                <g v-if="front">
                    <g v-for="(n, i) in shown" :key="i" :data-note="i" class="cursor-pointer" @pointerdown.stop="down($event, i)" @pointerenter="hover = i" @pointerleave="hover = hover === i ? null : hover">
                        <rect :x="x(n.beat)" :y="gemY(n)" :width="barWidth(n, 6)" :height="GEM" rx="11" fill="transparent" />
                        <rect v-if="toCheck.includes(i) && !selected.includes(i)" :x="x(n.beat) - 2" :y="gemY(n) - 2" :width="barWidth(n, 6) + 4" :height="GEM + 4" rx="13" fill="none" stroke="var(--color-warn)" stroke-width="1.5" stroke-dasharray="3 2" />
                        <text v-if="toCheck.includes(i)" :x="x(n.beat) - 1" :y="gemY(n) - 3" font-size="11" class="fill-warn" aria-hidden="true">!</text>
                        <text v-if="n.length * zoom > 26" :x="x(n.beat) + 6" :y="y(n.pitch) + ROW / 2 + 4 - (lifted(i) ? 2 : 0)" font-size="12" :fill="gemInk(front.color)" font-weight="800" translate="no">{{ (n.lyric ?? '').replace(/-$/, '') }}<tspan v-if="n.length * zoom > 60" dx="4" fill-opacity="0.65">{{ noteName(n.pitch) }}</tspan></text>
                        <rect v-if="editable" :x="x(n.beat) + barWidth(n, 6) - 6" :y="gemY(n)" width="6" :height="GEM" fill="transparent" class="cursor-ew-resize" />
                    </g>
                </g>
                <polyline v-for="(points, k) in liveLines" :key="'v' + k" :points="points" fill="none" stroke="var(--color-cream)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
                <g v-if="playhead !== null && !seekable" :style="{ filter: 'drop-shadow(0 0 5px var(--color-record))' }">
                    <rect :x="x(playhead) - 1.25" y="0" width="2.5" :height="rows * ROW + RULER + 4" class="fill-record" />
                    <circle :cx="x(playhead)" :cy="RULER / 2" r="4.5" class="fill-record" />
                </g>
            </svg>
            <Playhead v-if="seekable" :beat="playhead ?? 0" :x="x" :beat-at="(px) => (px - 8) / zoom" :height="rows * ROW + RULER + 4" :max="endBeat" :big-step="beatsPerBar" :label="t('create.edit.playhead')" :value-text="t('create.edit.playheadAt', { where: whereOf(playhead ?? 0) })" @seek="(beat) => $emit('seek', beat)" />
            </div>
        </div>`,
    setup: () => ({ ROW, RULER, GEM, gemInk }),
    computed: {
        /** The focus part's notes as gems (js/glass.js): a selected one rimmed and glowing, a hovered one glowing a little, a dragged one lifted. */
        gems() {
            if (!this.front) return [];
            return this.shown.map((n, i) => {
                const on = this.selected.includes(i);
                return { x: this.x(n.beat), y: this.gemY(n) - (this.lifted(i) ? 2 : 0), w: this.barWidth(n, 6), h: GEM, color: this.front.color, rim: on, glow: on ? 0.7 : this.hover === i ? 0.35 : 0, lift: this.lifted(i) ? 3 : 0, faint: this.toCheck.includes(i) && !on };
            });
        },
        front() {
            return this.parts.find((p) => p.key === this.focus) ?? (this.focus ? null : this.parts[0]) ?? null;
        },
        behind() {
            return this.parts.filter((p) => p !== this.front);
        },
        /** The focus part's notes, as the drag would leave them. */
        shown() {
            const notes = this.front?.notes ?? [];
            if (!this.drag?.moved) return notes;
            const { indexes, dBeat, dPitch, dLength } = this.drag;
            return notes.map((n, i) => (indexes.includes(i) ? { ...n, beat: Math.max(0, n.beat + dBeat), pitch: n.pitch + dPitch, length: Math.max(this.grid, n.length + dLength) } : n));
        },
        pitches() {
            // Only real pitches set the range: one NaN made every coordinate NaN and crushed the roll.
            const all = this.parts.flatMap((p) => p.notes.map((n) => n.pitch)).concat(this.live.map((l) => Math.round(l.midi))).filter(Number.isFinite);
            return all.length ? all : [60, 72];
        },
        top() {
            const high = Math.max(...this.pitches) + 3;
            const low = Math.min(...this.pitches) - 3;
            const rows = this.height ?? Math.max(18, high - low + 1);
            return Math.round((high + low) / 2 + rows / 2);
        },
        rows() {
            return this.height ?? Math.max(18, Math.max(...this.pitches) - Math.min(...this.pitches) + 7);
        },
        /** The Cs shown, for the labels down the side. */
        octaves() {
            const out = [];
            for (let p = this.top - this.rows + 1; p <= this.top; p++) if (((p % 12) + 12) % 12 === 0) out.push(p);
            return out;
        },
        width() {
            return Math.max(320, (this.endBeat + this.beatsPerBar) * this.zoom + 16);
        },
        bars() {
            const out = [];
            const per = this.beatsPerBar;
            for (let beat = 0, k = 0; beat <= this.endBeat + per; beat += 1, k++) {
                const fromBar = beat - this.pickupBeats;
                const bar = fromBar >= 0 && Math.abs(fromBar % per) < 1e-6;
                out.push({ beat, bar, number: bar ? fromBar / per + 1 : null });
            }
            return out;
        },
        /** The pitch line, broken where the voice stopped (a gap, or a leap of more than a fifth). */
        liveLines() {
            const lines = [];
            let last = null;
            for (const l of this.live) {
                if (!last || l.beat - last.beat > 0.3 || Math.abs(l.midi - last.midi) > 7) lines.push([]);
                lines.at(-1).push(`${this.x(l.beat)},${this.y(l.midi) + ROW / 2 - 1}`);
                last = l;
            }
            return lines.map((points) => points.join(' '));
        },
    },
    watch: {
        playhead(beat) {
            // Keep the playhead in view while it plays.
            const box = this.$refs.scroller;
            if (beat === null || !box) return;
            const at = this.x(beat);
            if (at < box.scrollLeft + 40 || at > box.scrollLeft + box.clientWidth - 80) box.scrollLeft = Math.max(0, at - 80);
        },
    },
    methods: {
        x(beat) {
            return 8 + beat * this.zoom;
        },
        /** "bar 3, beat 2" (the pickup's beats before bar 1), for the playhead's screen reader words. */
        whereOf(beat) {
            const after = beat - this.pickupBeats;
            if (after < 0) return t('create.edit.inPickup');
            return t('create.edit.barBeat', { bar: Math.floor(after / this.beatsPerBar) + 1, beat: formatNumber(Math.round(((after % this.beatsPerBar) + 1) * 100) / 100) });
        },
        y(pitch) {
            return (this.top - pitch) * ROW + RULER + 1;
        },
        barWidth(note, least) {
            return Math.max(least, note.length * this.zoom - 2);
        },
        /** A row in the song's key (lighter), or with no key, a white key. */
        inKey(pitch) {
            const pc = ((pitch % 12) + 12) % 12;
            if (!this.keyOf) return ![1, 3, 6, 8, 10].includes(pc);
            return (this.keyOf.minor ? MINOR : MAJOR).includes(((pc - this.keyOf.tonic) % 12 + 12) % 12);
        },
        /** A gem's top: centred on its row. */
        gemY(n) {
            return this.y(n.pitch) + (ROW - GEM) / 2;
        },
        /** A note being dragged: lifted, with a deeper shadow. */
        lifted(i) {
            return !!this.drag?.moved && this.drag.indexes.includes(i);
        },
        point(event) {
            const box = event.currentTarget.ownerSVGElement?.getBoundingClientRect() ?? event.currentTarget.getBoundingClientRect();
            return { px: event.clientX - box.left, py: event.clientY - box.top };
        },
        snap(beat) {
            return Math.round(beat / this.grid) * this.grid;
        },
        down(event, index) {
            if (this.seekable && index === null && this.point(event).py < RULER) {
                // A press on the ruler: the playhead there, on the grid.
                this.$emit('seek', Math.min(this.endBeat, Math.max(0, Math.round((this.point(event).px - 8) / this.zoom / this.grid) * this.grid)));
                return;
            }
            if (!this.editable && index === null) return;
            const { px, py } = this.point(event);
            if (index === null) {
                // An empty spot: a new note there (in draw mode the editor decides).
                const beat = Math.max(0, Math.floor((px - 8) / this.zoom / this.grid) * this.grid);
                this.$emit('add', { beat, pitch: Math.round(this.top - (py - RULER - 1) / ROW + 0.5) });
                return;
            }
            const additive = event.shiftKey || event.metaKey || event.ctrlKey;
            this.$emit('select', index, { additive });
            if (!this.editable) return;
            const note = this.front.notes[index];
            const end = this.x(note.beat) + note.length * this.zoom;
            const indexes = this.selected.includes(index) && !additive ? [...this.selected] : [index];
            this.drag = { indexes, startX: px, startY: py, resize: end - px < EDGE_PX, dBeat: 0, dPitch: 0, dLength: 0, moved: false };
            // Keep the drag when the pointer leaves the lane (a real pointer: a scripted one has none to keep).
            if (event.isTrusted) event.currentTarget.ownerSVGElement?.setPointerCapture?.(event.pointerId);
        },
        move(event) {
            const drag = this.drag;
            if (!drag) return;
            const { px, py } = this.point(event);
            const beats = this.snap((px - drag.startX) / this.zoom);
            if (drag.resize) drag.dLength = beats;
            else {
                drag.dBeat = beats;
                drag.dPitch = -Math.round((py - drag.startY) / ROW);
            }
            drag.moved = drag.moved || Math.abs(px - drag.startX) > 3 || Math.abs(py - drag.startY) > 3;
        },
        up() {
            const drag = this.drag;
            this.drag = null;
            if (drag?.moved && (drag.dBeat || drag.dPitch || drag.dLength)) this.$emit('edit', { indexes: drag.indexes, dBeat: drag.dBeat, dPitch: drag.dPitch, dLength: drag.dLength });
        },
    },
};
