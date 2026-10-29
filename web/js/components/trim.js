// The Trim sheet (Hillel's 82.png; docs/more-features-plan.md, feature 17): "Nothing is deleted". The
// whole take with big handles, the cut ends hatched, and how much is kept; each end in a zoomed view
// (a breath or a click is invisible at the song's scale) with its time, -/+ (a beat with Snap to beat,
// else 0.05 s, said), Hear start or Hear ending, and a suggestion when the silence finder sees one
// ("Cut the breath: start at 0:00.62"); Line up every part as a card; Reset, Play trimmed part, Done.
// Every change plays at once; the screen offers one Undo when the sheet closes.

import { token } from '../colors.js';
import { appearance } from '../stores/appearance.js';
import { t, tk } from '../i18n.js';
import { formatTime } from '../theme.js';
import { silenceTrims, snap, trimsOf } from '../trim.js';
import { Icon } from './icon.js';
import { Modal } from './ui.js';
import { Switch } from './controls.js';

const FINE_SECONDS = 0.05;
const ZOOM_SECONDS = 1.5; // each end's view: this long either side of the cut
const SUGGEST_SECONDS = 0.04; // a suggestion this close to the edge isn't one
const SHORTEST_SECONDS = 0.2; // the kept stretch never gets shorter than this
const STEPS = { start: [tk('trim.startEarlier'), tk('trim.startLater')], end: [tk('trim.endEarlier'), tk('trim.endLater')] };

export const TrimSheet = {
    components: { Modal, Icon, Switch },
    props: {
        part: { type: Object, required: true }, // the session's part (frames, sampleRate, peaks, offsets, trims)
        name: { type: String, required: true },
        color: { type: String, required: true },
        beatSeconds: { type: Number, default: 0 },
        playing: Boolean,
        canLineUp: Boolean, // more than one recorded part
    },
    emits: ['trim', 'play-start', 'play-end', 'play-trimmed', 'stop', 'line-up', 'close'],
    data: () => ({ snapping: true, dragging: null }),
    setup: () => ({ formatTime, STEPS, appearance, FINE_SECONDS }),
    template: `
        <Modal :title="t('trim.title', { part: name })" :subtitle="t('trim.subtitle')" wide @close="$emit('close')">
            <div class="space-y-5" data-trim>
                <div>
                    <div class="relative rounded-2xl bg-ink p-2 select-none touch-none">
                        <canvas ref="canvas" class="block h-24 w-full cursor-ew-resize" aria-hidden="true" @pointerdown="grab" @pointermove="drag" @pointerup="drop" @pointercancel="drop"></canvas>
                    </div>
                    <p class="mt-1.5 flex justify-between text-xs tabular-nums text-muted"><span>{{ formatTime(0) }}</span><span class="font-semibold text-cream">{{ t('trim.keeping', { kept: seconds(keptFrames), total: seconds(part.frames) }) }}</span><span>{{ seconds(part.frames) }}</span></p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-for="edge in ['start', 'end']" :key="edge" class="space-y-3 rounded-2xl border border-line p-3" :data-edge="edge">
                        <p class="flex items-baseline justify-between"><span class="text-sm font-semibold text-muted">{{ edge === 'start' ? t('trim.start') : t('trim.end') }}</span><span class="text-2xl font-extrabold tabular-nums text-cream">{{ edgeLabel(edge) }}</span></p>
                        <canvas :ref="'zoom-' + edge" class="block h-16 w-full rounded-lg bg-ink" aria-hidden="true"></canvas>
                        <div class="grid grid-cols-[auto_1fr_auto] gap-2">
                            <button type="button" class="grid size-11 place-items-center rounded-xl glass-bubble text-cream" :aria-label="t(STEPS[edge][0])" @click="step(edge, -1)"><Icon name="minus" :size="16" /></button>
                            <button type="button" class="flex items-center justify-center gap-1.5 rounded-xl glass-bubble text-sm font-semibold text-cream" @click="playing ? $emit('stop') : $emit(edge === 'start' ? 'play-start' : 'play-end')"><Icon :name="playing ? 'stop' : 'play'" :size="14" /> {{ playing ? t('studio.stop') : edge === 'start' ? t('trim.hearStart') : t('trim.hearEnd') }}</button>
                            <button type="button" class="grid size-11 place-items-center rounded-xl glass-bubble text-cream" :aria-label="t(STEPS[edge][1])" @click="step(edge, 1)"><Icon name="plus" :size="16" /></button>
                        </div>
                        <button v-if="suggestion(edge) !== null" type="button" class="w-full rounded-xl border border-dashed border-accent/60 bg-accent/10 px-3 py-2 text-start text-sm font-semibold text-accent-strong" @click="setEdge(edge, suggestion(edge))">{{ edge === 'start' ? t('trim.suggestStart', { time: timeOf(suggestion(edge)) }) : t('trim.suggestEnd', { time: timeOf(part.frames - suggestion(edge)) }) }}</button>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <Switch v-if="beatSeconds" :on="snapping" :label="t('trim.snap')" class="w-auto! gap-3 rounded-full border border-line pe-1 ps-4" @change="snapping = $event" />
                    <span class="text-xs text-muted">{{ snapping && beatSeconds ? t('trim.stepBeat', { seconds: beatSeconds.toFixed(2) }) : t('trim.stepFine', { seconds: FINE_SECONDS }) }}</span>
                </div>
                <section v-if="canLineUp" class="flex flex-wrap items-center gap-3 rounded-2xl border border-line p-4" aria-labelledby="line-up-heading">
                    <div class="min-w-48 flex-1"><h3 id="line-up-heading" class="font-semibold text-cream">{{ t('trim.lineUp') }}</h3><p class="text-sm text-muted">{{ t('trim.lineUpHint', { name }) }}</p></div>
                    <button type="button" class="btn-secondary btn-sm" @click="$emit('line-up', 'start')">{{ t('trim.sameStart') }}</button>
                    <button type="button" class="btn-secondary btn-sm" @click="$emit('line-up', 'end')">{{ t('trim.sameEnd') }}</button>
                </section>
                <div class="flex flex-wrap items-center gap-3 border-t border-line pt-4">
                    <button type="button" class="btn-ghost btn-sm" :disabled="!trimmed" @click="$emit('trim', { start: 0, end: 0 })">{{ t('trim.reset') }}</button>
                    <button type="button" class="btn-secondary ms-auto" @click="playing ? $emit('stop') : $emit('play-trimmed')">{{ playing ? t('studio.stop') : t('trim.playTrimmed') }}</button>
                    <button type="button" class="btn-primary" @click="$emit('close')">{{ t('common.done') }}</button>
                </div>
            </div>
        </Modal>`,
    computed: {
        trims() {
            return trimsOf(this.part);
        },
        trimmed() {
            return this.trims.start > 0 || this.trims.end > 0;
        },
        at() {
            return (this.part.offsetSeconds ?? 0) + (this.part.nudgeSeconds ?? 0);
        },
        keptFrames() {
            return this.part.frames - this.trims.start - this.trims.end;
        },
        /** What the silence finder would keep: { start, end } frames, or null. */
        found() {
            return this.part.peaks?.length ? silenceTrims(this.part) : null;
        },
    },
    watch: {
        trims() {
            this.draw();
        },
        // A new theme: its text colour, and its part colours from the parent, drawn again.
        'appearance.version'() {
            this.cream = token('cream', '#f4eee6');
            this.draw();
        },
        color() {
            this.draw();
        },
    },
    mounted() {
        this.cream = token('cream', '#f4eee6');
        this.observer = new ResizeObserver(() => this.draw());
        this.observer.observe(this.$refs.canvas);
        this.draw();
    },
    unmounted() {
        this.observer?.disconnect();
    },
    methods: {
        /** An edge as the time it sits at in the song. */
        edgeLabel(edge) {
            return this.timeOf(edge === 'start' ? this.trims.start : this.part.frames - this.trims.end);
        },
        /** A frame of the take moved to the nearest beat of the song (with Snap to the beat on). */
        snapped(frame) {
            if (!this.snapping || !this.beatSeconds) return frame;
            return Math.round((snap(this.at + frame / this.part.sampleRate, this.beatSeconds) - this.at) * this.part.sampleRate);
        },
        /** Sets one edge (a frame of the take), keeping the kept stretch at least SHORTEST_SECONDS. */
        set(edge, frame) {
            const { frames, sampleRate } = this.part;
            const least = Math.round(SHORTEST_SECONDS * sampleRate);
            if (edge === 'start') {
                const start = Math.max(0, Math.min(frames - this.trims.end - least, frame));
                this.$emit('trim', { start, end: this.trims.end });
            } else {
                const end = Math.max(0, Math.min(frames - this.trims.start - least, frames - frame));
                this.$emit('trim', { start: this.trims.start, end });
            }
        },
        step(edge, direction) {
            const { frames, sampleRate } = this.part;
            const now = edge === 'start' ? this.trims.start : frames - this.trims.end;
            const size = this.snapping && this.beatSeconds ? this.beatSeconds : FINE_SECONDS;
            let next = Math.round(now + direction * size * sampleRate);
            if (this.snapping && this.beatSeconds) next = this.snapped(next);
            this.set(edge, next);
        },
        /** A suggested edge (frames from the take's start, or from its end), when it differs from now. */
        suggestion(edge) {
            if (!this.found) return null;
            const at = this.found[edge];
            const now = this.trims[edge];
            return Math.abs(at - now) / this.part.sampleRate > SUGGEST_SECONDS ? at : null;
        },
        /** One edge from a suggestion: the start as is, the end as frames before the take's end. */
        setEdge(edge, frames) {
            this.$emit('trim', edge === 'start' ? { start: frames, end: this.trims.end } : { start: this.trims.start, end: frames });
        },
        seconds(frames) {
            return t('trim.seconds', { seconds: (frames / this.part.sampleRate).toFixed(1) });
        },
        /** A frame of the take as the song time it sits at: "0:00.62". */
        timeOf(frame) {
            const seconds = Math.max(0, this.at + frame / this.part.sampleRate);
            return `${formatTime(seconds)}.${String(Math.floor((seconds % 1) * 100)).padStart(2, '0')}`;
        },
        frameAt(event) {
            const box = this.$refs.canvas.getBoundingClientRect();
            const share = Math.max(0, Math.min(1, (event.clientX - box.left) / box.width));
            return Math.round(share * this.part.frames);
        },
        grab(event) {
            const frame = this.frameAt(event);
            const end = this.part.frames - this.trims.end;
            // The nearer handle moves.
            this.dragging = Math.abs(frame - this.trims.start) <= Math.abs(frame - end) ? 'start' : 'end';
            this.$refs.canvas.setPointerCapture(event.pointerId);
            this.drag(event);
        },
        drag(event) {
            if (!this.dragging) return;
            this.set(this.dragging, this.snapped(this.frameAt(event)));
        },
        drop() {
            this.dragging = null;
        },
        draw() {
            const canvas = this.$refs.canvas;
            if (!canvas) return;
            const ratio = window.devicePixelRatio || 1;
            const width = canvas.clientWidth;
            const height = canvas.clientHeight;
            if (!width || !height) return;
            canvas.width = Math.round(width * ratio);
            canvas.height = Math.round(height * ratio);
            const g = canvas.getContext('2d');
            g.setTransform(ratio, 0, 0, ratio, 0, 0);
            const peaks = this.part.peaks ?? [];
            const startX = (this.trims.start / this.part.frames) * width;
            const endX = (1 - this.trims.end / this.part.frames) * width;
            for (let x = 0; x < width; x += 3) {
                const peak = peaks[Math.floor((x / width) * peaks.length)] ?? 0;
                const bar = Math.max(1, Math.sqrt(peak) * height * 0.45);
                g.globalAlpha = x >= startX && x <= endX ? 1 : 0.25;
                g.fillStyle = this.color;
                g.fillRect(x, height / 2 - bar, 2, bar * 2);
            }
            g.globalAlpha = 1;
            // The cut ends hatched, so what goes is plain at a glance.
            g.strokeStyle = this.cream;
            g.globalAlpha = 0.18;
            g.lineWidth = 1;
            for (const [from, to] of [[0, startX], [endX, width]]) {
                if (to - from < 1) continue;
                g.save();
                g.beginPath();
                g.rect(from, 0, to - from, height);
                g.clip();
                for (let x = from - height; x < to; x += 7) {
                    g.beginPath();
                    g.moveTo(x, height);
                    g.lineTo(x + height, 0);
                    g.stroke();
                }
                g.restore();
            }
            g.globalAlpha = 1;
            // Big handles: a bar the height of the view and a grip in the middle.
            g.fillStyle = this.cream;
            for (const x of [startX, endX]) {
                const cx = Math.min(width - 7, Math.max(7, x));
                g.fillRect(cx - 1.5, 0, 3, height);
                g.beginPath();
                g.roundRect(cx - 7, height / 2 - 14, 14, 28, 5);
                g.fill();
            }
            this.drawZoom('start');
            this.drawZoom('end');
        },
        /** One end, close up: ZOOM_SECONDS either side of the cut, the cut side grey and labelled. */
        drawZoom(edge) {
            const canvas = this.$refs['zoom-' + edge]?.[0] ?? this.$refs['zoom-' + edge];
            if (!canvas) return;
            const ratio = window.devicePixelRatio || 1;
            const width = canvas.clientWidth;
            const height = canvas.clientHeight;
            if (!width || !height) return;
            canvas.width = Math.round(width * ratio);
            canvas.height = Math.round(height * ratio);
            const g = canvas.getContext('2d');
            g.setTransform(ratio, 0, 0, ratio, 0, 0);
            const { frames, sampleRate } = this.part;
            const peaks = this.part.peaks ?? [];
            const perPeak = this.part.framesPerPeak || frames / Math.max(1, peaks.length);
            const cut = edge === 'start' ? this.trims.start : frames - this.trims.end;
            const span = ZOOM_SECONDS * sampleRate;
            const from = cut - span;
            const mid = width / 2;
            for (let x = 0; x < width; x += 3) {
                const frame = from + (x / width) * span * 2;
                const peak = frame < 0 || frame >= frames ? 0 : peaks[Math.floor(frame / perPeak)] ?? 0;
                const bar = Math.max(1, Math.sqrt(peak) * height * 0.42);
                const kept = edge === 'start' ? x >= mid : x <= mid;
                g.globalAlpha = kept ? 1 : 0.28;
                g.fillStyle = kept ? this.color : this.cream;
                g.fillRect(x, height / 2 - bar, 2, bar * 2);
            }
            g.globalAlpha = 1;
            g.fillStyle = this.cream;
            g.fillRect(mid - 1, 0, 2, height);
            g.globalAlpha = 0.7;
            g.font = '600 10px system-ui, sans-serif';
            g.textBaseline = 'bottom';
            const label = t('trim.cut');
            g.fillText(label, edge === 'start' ? 6 : width - 6 - g.measureText(label).width, height - 4);
            g.globalAlpha = 1;
        },
    },
};

