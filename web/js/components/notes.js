// The focus part's notes as glossy bricks by pitch (a simple piano roll), the note being sung lit up.
// This is what shows a beginner which notes to sing, each ending where its singing does, with a
// breath mark (a comma) before a new line, as Sing's lane draws them. Drawn on a canvas, with the
// current phrase's note names as text for screen readers.

import { midiName } from '../dsp.js';
import { gemBar } from '../glass.js';
import { breathComma } from '../lane-painter.js';
import { sungEnd } from '../scoring.js';

export const NotesView = {
    props: {
        notes: { type: Array, required: true }, // { beat, length, pitch, br }
        transpose: { type: Number, default: 0 },
        beatSeconds: { type: Number, required: true },
        lengthSeconds: { type: Number, required: true },
        color: { type: String, required: true },
        position: { type: Number, default: 0 },
        label: { type: String, default: '' }, // the current phrase's notes, for screen readers
    },
    template: `
        <div class="relative h-full w-full">
            <canvas ref="canvas" class="touch-pan-y block h-full w-full" aria-hidden="true"></canvas>
            <span class="sr-only">{{ label }}</span>
            <span v-if="current" class="pointer-events-none absolute -translate-x-1/2 rounded-md bg-ink/90 px-1.5 py-0.5 text-[11px] font-bold text-cream"
                :style="{ left: current.x + 'px', top: Math.max(0, current.y - 22) + 'px' }">{{ current.name }}</span>
        </div>`,
    data: () => ({ current: null }),
    mounted() {
        this.observer = new ResizeObserver(() => this.draw());
        this.observer.observe(this.$refs.canvas);
        this.draw();
    },
    unmounted() {
        this.observer.disconnect();
    },
    watch: {
        $props: { deep: false, handler() { this.draw(); } },
        position() {
            this.draw();
        },
    },
    methods: {
        draw() {
            const canvas = this.$refs.canvas;
            if (!canvas) return;
            const ratio = window.devicePixelRatio || 1;
            const width = canvas.clientWidth;
            const height = canvas.clientHeight;
            canvas.width = Math.round(width * ratio);
            canvas.height = Math.round(height * ratio);
            const g = canvas.getContext('2d');
            g.scale(ratio, ratio);
            g.clearRect(0, 0, width, height);
            if (!this.notes.length || !width) return;
            const pitches = this.notes.map((n) => n.pitch + this.transpose);
            const low = Math.min(...pitches) - 1;
            const high = Math.max(...pitches) + 1;
            const row = height / (high - low + 1);
            const x = (seconds) => (seconds / this.lengthSeconds) * width;
            const light = (document.documentElement.dataset.theme ?? '').endsWith('-light');
            // A brick readable at any size: most of a row, never thinner than 8 px or thicker than 26.
            const brick = Math.max(8, Math.min(26, row * 0.78));
            let current = null;
            const cream = getComputedStyle(canvas).getPropertyValue('--color-cream').trim() || '#f3ead8';
            for (const [index, note] of this.notes.entries()) {
                const start = note.beat * this.beatSeconds;
                const end = sungEnd(this.notes, index) * this.beatSeconds;
                const pitch = note.pitch + this.transpose;
                const y = (high - pitch) * row + (row - brick) / 2;
                const now = this.position >= start && this.position < end;
                gemBar(g, x(start), y, Math.max(4, x(end) - x(start) - 2), brick, this.color, { glow: now ? 0.8 : 0, rim: now ? 'rgba(255,255,255,0.9)' : null, dim: !now && this.position > 0, light });
                if (now) current = { x: x(start) + Math.min(14, (x(end) - x(start)) / 2), y: (high - pitch) * row, name: midiName(pitch) };
                // Breathe here: the comma just after the note, where the next line begins.
                if (end < (note.beat + note.length) * this.beatSeconds - 1e-6) breathComma(g, x(end) + 3, Math.max(6, y - 6), Math.max(8, Math.min(11, brick * 0.5)), cream);
            }
            this.current = current;
        },
    },
};
