// Note bricks drawn by js/glass.js's gemBar, the one recipe for every note (Hillel, 2026-10-28: "I much
// prefer Canvas gemBar to the CSS .gem bricks"). One canvas behind a view's notes, at the screen's
// pixel ratio, redrawn on a resize and a theme change. The words on a brick stay DOM text over it
// (crisp, read aloud, and the view's own targets), coloured with gemInk(). Two ways to say where:
// - `bricks`: [{ x, y, w, h, color, ... }] in px from the canvas's top left (or a function of
//   { width, height } returning them), for a view that knows its geometry (a lane, a roll).
// - `measure`: every [data-gem] element in the canvas's parent is a brick where it sits, its colour
//   the attribute's value, and data-gem-state 'dim', 'now', 'ghost' or 'faint'. For bricks laid out
//   by CSS (the previews, the chord shapes).
// A brick's states, as the Sing lane's: `dim` (missed or past), `rim` (the note now; true for cream),
// `glow` 0 to 1, `ghost` (only to listen to, dashed), `lift` (dragged, px), `faint` (half there),
// `next` (breathing as it nears), `hitAt` (performance.now() of a hit: it flashes, pops and
// sparkles), `fill` 0 to 1 with `fillColor` (a held note filling). Motion stops for reduced motion.

import { token } from '../colors.js';
import { gemBar, shades, sparkles } from '../glass.js';
import { appearance } from '../stores/appearance.js';

const HIT_SECONDS = 0.5;
const stillMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
const isLight = () => appearance.theme.endsWith('-light');

/** A colour a canvas can use: a token (`var(--color-x)`) read from `el`, else as it is. */
export function solidColor(value, el = document.documentElement) {
    const m = /^var\((--[\w-]+)\)$/.exec(String(value ?? '').trim());
    return m ? getComputedStyle(el).getPropertyValue(m[1]).trim() || '#888888' : value;
}

/** The words on a brick of `color`: dark or white by the colour (AA), as the lane's. */
export function gemInk(color) {
    return shades(solidColor(color), { light: isLight() }).ink;
}

export const Gems = {
    props: {
        bricks: { type: [Array, Function], default: null },
        measure: Boolean,
        width: { type: Number, default: null }, // px; else the parent's box
        height: { type: Number, default: null },
    },
    template: `<canvas ref="canvas" class="pointer-events-none absolute start-0 top-0 block" :style="{ width: width === null ? '100%' : width + 'px', height: height === null ? '100%' : height + 'px' }" aria-hidden="true"></canvas>`,
    data: () => ({ box: { width: 0, height: 0 } }),
    watch: {
        'appearance.theme'() { this.$nextTick(this.draw); },
    },
    created() {
        this.appearance = appearance;
    },
    mounted() {
        const canvas = this.$refs.canvas;
        this.resize = new ResizeObserver(() => {
            this.box = { width: canvas.clientWidth, height: canvas.clientHeight };
            this.draw();
        });
        this.resize.observe(canvas);
        if (this.measure && canvas.parentElement) {
            this.resize.observe(canvas.parentElement);
            // What CSS lays out changes without a prop: redraw after any change in the parent.
            this.changes = new MutationObserver(() => this.draw());
            this.changes.observe(canvas.parentElement, { subtree: true, childList: true, attributes: true, attributeFilter: ['data-gem', 'data-gem-state', 'style', 'class'] });
        }
        this.stop = this.$watch(() => (typeof this.bricks === 'function' ? this.bricks(this.box) : this.bricks), () => this.draw(), { deep: true });
        this.draw();
    },
    beforeUnmount() {
        this.resize?.disconnect();
        this.changes?.disconnect();
        this.stop?.();
        cancelAnimationFrame(this.frame);
    },
    methods: {
        list() {
            if (this.measure) {
                const canvas = this.$refs.canvas;
                const origin = canvas.getBoundingClientRect();
                return [...canvas.parentElement.querySelectorAll('[data-gem]')].map((el) => {
                    const r = el.getBoundingClientRect();
                    const state = el.dataset.gemState ?? '';
                    return { x: r.left - origin.left, y: r.top - origin.top, w: r.width, h: r.height, color: solidColor(el.dataset.gem, el), dim: state === 'dim', rim: state === 'now', glow: state === 'now' ? 0.8 : 0, ghost: state === 'ghost', faint: state === 'faint' };
                });
            }
            const bricks = typeof this.bricks === 'function' ? this.bricks(this.box) : this.bricks;
            return (bricks ?? []).map((b) => ({ ...b, color: solidColor(b.color, this.$refs.canvas) }));
        },
        draw() {
            const canvas = this.$refs.canvas;
            if (!canvas) return;
            const ratio = window.devicePixelRatio || 1;
            const w = Math.round(canvas.clientWidth * ratio);
            const h = Math.round(canvas.clientHeight * ratio);
            if (!w || !h) return;
            if (canvas.width !== w || canvas.height !== h) {
                canvas.width = w;
                canvas.height = h;
            }
            const g = canvas.getContext('2d');
            g.setTransform(ratio, 0, 0, ratio, 0, 0);
            g.clearRect(0, 0, canvas.clientWidth, canvas.clientHeight);
            const light = isLight();
            const still = stillMotion();
            const now = performance.now();
            const cream = token('cream', '#f5efe6');
            let moving = false;
            this.list().forEach((b, index) => {
                const age = b.hitAt ? (now - b.hitAt) / 1000 : Infinity;
                const fresh = !still && age < HIT_SECONDS;
                const breathe = !still && b.next ? 0.35 + 0.35 * Math.sin(now / 180) : 0;
                moving ||= fresh || breathe > 0;
                const options = {
                    glow: fresh ? 1 - age / HIT_SECONDS : Math.max(b.glow ?? 0, breathe),
                    rim: b.rim === true ? cream : b.rim || null,
                    dim: !!b.dim,
                    ghost: !!b.ghost,
                    lift: b.lift ?? 0,
                    pop: fresh ? 1 + 0.05 * Math.sin(Math.PI * Math.min(1, age / 0.25)) : 1,
                    light,
                };
                g.globalAlpha = b.faint ? 0.5 : 1;
                gemBar(g, b.x, b.y, b.w, b.h, b.color, options);
                if (b.fill > 0 && b.fillColor) {
                    g.save();
                    g.beginPath();
                    g.rect(b.x - 2, b.y - 20, b.w * b.fill + 2, b.h + 40);
                    g.clip();
                    gemBar(g, b.x, b.y, b.w, b.h, solidColor(b.fillColor, canvas), { ...options, glow: 0.8 });
                    g.restore();
                }
                g.globalAlpha = 1;
                if (fresh) sparkles(g, b.x + b.w / 2, b.y + b.h / 2, age, b.color, { seed: index });
            });
            // Breathing and a hit's burst move on their own: drawn each frame only while they last.
            cancelAnimationFrame(this.frame);
            if (moving) this.frame = requestAnimationFrame(() => this.draw());
        },
    },
};
