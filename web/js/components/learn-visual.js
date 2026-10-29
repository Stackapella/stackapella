// The pictures beside a course's Learn steps (Hillel: no learn screen is just words), one family
// in the person's accent: calm, slow, and still under reduced motion.
// - BreathVisual: a living flower of translucent petals that blooms and turns as you breathe in
//   (4 s) and folds back and dims as you let the air out on "sss" (6 s), a soft glow behind and a
//   few motes drifting off with the out-breath, with the count in words. A canvas, about 60 frames a
//   second only while it's on screen; a still flower with the words fading slowly under reduced motion.
// - LearnVisual: the rest, as small animated drawings: a pitch ladder, a steady line, a drone and
//   your note settling together, a vowel's mouth shapes, a phrase's arc, two notes ringing, a round,
//   the bass's floor, three parts balancing, a swell, a vibrato wave, words lighting up, an echo.

import { t, tk } from '../i18n.js';

const IN_SECONDS = 4;
const OUT_SECONDS = 6;
const still = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
/** A CSS colour (hex, as the theme gives it) at `alpha`, for the canvas (which has no color-mix). */
function rgba(hex, alpha) {
    const full = hex.length === 4 ? '#' + [...hex.slice(1)].map((c) => c + c).join('') : hex;
    const [r, g, b] = [1, 3, 5].map((i) => parseInt(full.slice(i, i + 2), 16) || 0);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}
const ease = (x) => 0.5 - 0.5 * Math.cos(Math.PI * Math.max(0, Math.min(1, x)));

/** Where a breath is at `seconds` into the cycle: { phase: 'in' | 'out', open: 0 to 1, left } (seconds left in the phase). */
export function breathAt(seconds) {
    const t = ((seconds % (IN_SECONDS + OUT_SECONDS)) + IN_SECONDS + OUT_SECONDS) % (IN_SECONDS + OUT_SECONDS);
    if (t < IN_SECONDS) return { phase: 'in', open: ease(t / IN_SECONDS), left: Math.ceil(IN_SECONDS - t) };
    return { phase: 'out', open: 1 - ease((t - IN_SECONDS) / OUT_SECONDS), left: Math.ceil(IN_SECONDS + OUT_SECONDS - t) };
}

export const BreathVisual = {
    data: () => ({ phase: 'in', left: IN_SECONDS, still: still() }),
    template: `
        <figure class="mx-auto w-full" :aria-label="t('learnVisual.breathLabel')">
            <div class="relative aspect-square w-full">
            <canvas ref="canvas" class="touch-pan-y absolute inset-0 size-full" aria-hidden="true"></canvas>
            <figcaption class="absolute inset-0 grid place-items-center text-center" aria-live="off">
                <span class="transition-opacity duration-1000" :class="still ? '' : ''">
                    <span class="block text-2xl font-extrabold tracking-tight text-cream drop-shadow">{{ phase === 'in' ? t('learnVisual.breatheIn') : t('learnVisual.breatheOut') }}</span>
                    <span class="mt-1 block text-sm font-semibold tabular-nums text-cream/80">{{ left }}</span>
                </span>
            </figcaption>
            </div>
            <p class="mt-2 flex justify-center gap-5 text-sm text-muted" aria-hidden="true">
                <span>{{ t('learnVisual.inFor', { seconds: 4 }) }}</span><span>{{ t('learnVisual.outFor', { seconds: 6 }) }}</span>
            </p>
        </figure>`,
    mounted() {
        this.start = performance.now();
        this.motes = Array.from({ length: 18 }, (_, i) => ({ angle: (i / 18) * Math.PI * 2 + i, speed: 0.6 + (i % 5) * 0.12, size: 1 + (i % 3) }));
        this.visible = true;
        this.watcher = new IntersectionObserver(([entry]) => {
            this.visible = entry.isIntersecting;
            if (this.visible && !this.frame) this.frame = requestAnimationFrame(this.draw);
        });
        this.watcher.observe(this.$el);
        this.resize = new ResizeObserver(() => this.size());
        this.resize.observe(this.$el);
        this.size();
        this.frame = requestAnimationFrame(this.draw);
    },
    unmounted() {
        cancelAnimationFrame(this.frame);
        clearTimeout(this.stillTimer);
        this.watcher?.disconnect();
        this.resize?.disconnect();
    },
    methods: {
        size() {
            const canvas = this.$refs.canvas;
            if (!canvas) return;
            const box = canvas.getBoundingClientRect();
            const ratio = Math.min(2, devicePixelRatio || 1);
            canvas.width = Math.max(1, Math.round(box.width * ratio));
            canvas.height = Math.max(1, Math.round(box.height * ratio));
            this.ratio = ratio;
        },
        draw(now) {
            this.frame = null;
            const canvas = this.$refs.canvas;
            if (!canvas) return;
            const seconds = (now - this.start) / 1000;
            // Reduced motion: the flower stays half open; only the words change, slowly.
            const breath = breathAt(seconds);
            const open = this.still ? 0.6 : breath.open;
            this.phase = breath.phase;
            this.left = breath.left;
            const g = canvas.getContext('2d');
            const w = canvas.width;
            const h = canvas.height;
            const r = Math.min(w, h) / 2;
            const style = getComputedStyle(document.documentElement);
            const accent = style.getPropertyValue('--color-accent').trim() || '#4ccfb0';
            const strong = style.getPropertyValue('--color-accent-strong').trim() || accent;
            g.clearRect(0, 0, w, h);
            g.save();
            g.translate(w / 2, h / 2);
            // The glow behind, breathing with it.
            const glow = g.createRadialGradient(0, 0, r * 0.05, 0, 0, r * (0.55 + 0.4 * open));
            glow.addColorStop(0, rgba(accent, 0.3 + 0.25 * open));
            glow.addColorStop(1, rgba(accent, 0));
            g.fillStyle = glow;
            g.fillRect(-r, -r, 2 * r, 2 * r);
            // The petals: two rings, turning slowly, opening with the breath.
            const turn = this.still ? 0.4 : seconds * 0.12 + open * 0.6;
            for (const [count, reach, width, colour, alpha, spin] of [[7, 0.62, 0.3, accent, 0.28, 1], [7, 0.44, 0.24, strong, 0.32, -1.3]]) {
                for (let i = 0; i < count; i++) {
                    const angle = (i / count) * Math.PI * 2 + turn * spin;
                    const distance = r * reach * (0.18 + 0.82 * open) * 0.5;
                    g.save();
                    g.rotate(angle);
                    g.translate(distance, 0);
                    g.beginPath();
                    g.ellipse(0, 0, r * reach * (0.3 + 0.7 * open) * 0.5, r * width * (0.45 + 0.55 * open), 0, 0, Math.PI * 2);
                    g.fillStyle = colour;
                    g.globalAlpha = alpha * (0.55 + 0.45 * open);
                    g.fill();
                    g.restore();
                }
            }
            g.globalAlpha = 1;
            // Motes drift off with the out-breath.
            if (!this.still && breath.phase === 'out') {
                const out = 1 - open;
                for (const m of this.motes) {
                    const d = r * (0.3 + out * 0.6 * m.speed);
                    g.globalAlpha = Math.max(0, 0.55 * (1 - out)) * (0.5 + 0.5 * Math.sin(m.angle * 3));
                    g.fillStyle = strong;
                    g.beginPath();
                    g.arc(Math.cos(m.angle + out * 0.4) * d, Math.sin(m.angle + out * 0.4) * d, m.size * this.ratio, 0, Math.PI * 2);
                    g.fill();
                }
                g.globalAlpha = 1;
            }
            g.restore();
            if (this.visible && !this.still) this.frame = requestAnimationFrame(this.draw);
            else if (this.still) this.stillTimer = setTimeout(() => (this.frame = requestAnimationFrame(this.draw)), 1000); // the words, once a second
        },
    },
};

// The small drawings: SVG with gentle CSS motion (class "lv-move", which reduced motion stops).
const DRAWINGS = {
    ladder: `<g class="lv-dim">${[0, 1, 2, 3, 4].map((i) => `<rect x="70" y="${30 + i * 36}" width="160" height="8" rx="4" />`).join('')}</g>
        <circle class="lv-move lv-climb" cx="150" cy="178" r="13" />`,
    steady: `<path class="lv-dim" d="M40 150 H260" stroke-width="3" fill="none" stroke-dasharray="6 8" />
        <path d="M40 120 H260" stroke-width="8" stroke-linecap="round" fill="none" class="lv-line" />
        <circle class="lv-move lv-slide" cx="40" cy="120" r="12" />`,
    drone: `<path class="lv-dim" d="M30 110 Q75 80 120 110 T210 110 T300 110" stroke-width="6" fill="none" />
        <path class="lv-move lv-settle lv-line" d="M30 130 Q75 90 120 130 T210 130 T300 130" stroke-width="6" fill="none" />`,
    vowels: `<ellipse class="lv-move lv-mouth" cx="150" cy="120" rx="34" ry="30" />`,
    echo: `<rect class="lv-dim" x="58" y="98" width="84" height="44" rx="12" /><rect class="lv-move lv-pulse" x="158" y="98" width="84" height="44" rx="12" />`,
    words: [0, 1, 2, 3].map((i) => `<rect class="lv-move lv-word" style="animation-delay:${i * 0.6}s" x="${30 + i * 62}" y="105" width="52" height="30" rx="10" />`).join(''),
    phrase: `<path class="lv-line" d="M30 170 Q150 30 270 170" stroke-width="8" fill="none" stroke-linecap="round" />
        <circle class="lv-dim" cx="30" cy="170" r="10" /><circle class="lv-move lv-along" r="12" />`,
    harmony: `<path class="lv-line" d="M40 100 H260" stroke-width="10" stroke-linecap="round" fill="none" />
        <path class="lv-move lv-ring lv-line2" d="M40 140 H260" stroke-width="10" stroke-linecap="round" fill="none" />`,
    round: [0, 1, 2].map((i) => `<path class="lv-move lv-enter lv-line" style="animation-delay:${i * 0.8}s" d="M${40 + i * 50} ${80 + i * 40} H${260}" stroke-width="8" stroke-linecap="round" fill="none" />`).join(''),
    bass: `<g class="lv-dim">${[0, 1, 2].map((i) => `<rect x="${60 + i * 70}" y="${60 + (i % 2) * 30}" width="40" height="${100 - (i % 2) * 30}" rx="8" />`).join('')}</g>
        <rect class="lv-move lv-floor" x="40" y="176" width="220" height="16" rx="8" />`,
    blend: [0, 1, 2].map((i) => `<rect class="lv-move lv-level" style="animation-delay:${i * 0.5}s" x="${70 + i * 60}" y="60" width="36" height="130" rx="10" />`).join(''),
    dynamics: `<path class="lv-move lv-swell lv-fill" d="M30 120 Q150 40 270 120 Q150 200 30 120 Z" />`,
    vibrato: `<path class="lv-move lv-wave lv-line" d="M20 120 q20 -14 40 0 t40 0 t40 0 t40 0 t40 0 t40 0 t40 0" stroke-width="6" fill="none" stroke-linecap="round" />`,
};

const LABELS = {
    ladder: tk('learnVisual.ladder'),
    steady: tk('learnVisual.steady'),
    drone: tk('learnVisual.drone'),
    vowels: tk('learnVisual.vowels'),
    echo: tk('learnVisual.echo'),
    words: tk('learnVisual.words'),
    phrase: tk('learnVisual.phrase'),
    harmony: tk('learnVisual.harmony'),
    round: tk('learnVisual.round'),
    bass: tk('learnVisual.bass'),
    blend: tk('learnVisual.blend'),
    dynamics: tk('learnVisual.dynamics'),
    vibrato: tk('learnVisual.vibrato'),
};

export const LearnVisual = {
    components: { BreathVisual },
    props: { type: { type: String, required: true } },
    template: `
        <BreathVisual v-if="type === 'breath'" />
        <figure v-else-if="drawing" class="learn-visual mx-auto aspect-[3/2] w-full rounded-3xl bg-raised/60 p-4" role="img" :aria-label="label">
            <svg viewBox="0 0 300 220" class="size-full" aria-hidden="true" v-html="drawing"></svg>
        </figure>`,
    computed: {
        drawing: (vm) => DRAWINGS[vm.type] ?? null,
        label: (vm) => (LABELS[vm.type] ? t(LABELS[vm.type]) : ''),
    },
};
