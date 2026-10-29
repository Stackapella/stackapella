// Shared UI: modal sheet, toasts, and the waveform canvas.

import { nextTick, reactive } from 'vue';
import { Icon } from './icon.js';
import { route } from '../router.js';

let dialogs = 0;

/** A dialog: a bottom sheet on phones, a centred card from tablet up. */
let openSheets = 0; // sheets open now (one can open over another); the page is locked while any is open

export const Modal = {
    components: { Icon },
    // titleIsContent: a song's or person's name, not our words. tone: a status header (77.png): the
    // header tinted and a dot before the title, 'bad', 'busy' or 'good', with `subtitle` under it.
    // closer: the close button (a confirmation has its own safe answer instead).
    props: { title: String, titleIsContent: Boolean, wide: Boolean, dismissable: { type: Boolean, default: true }, closer: { type: Boolean, default: true }, tone: { type: String, default: null }, subtitle: { type: String, default: '' }, dot: { type: String, default: '' } },
    emits: ['close'],
    data: () => ({ headingId: `dialog-${++dialogs}` }),
    setup: () => ({ TONES: { bad: 'bg-record/15', busy: 'bg-warn/15', good: 'bg-good/15' } }),
    template: `
        <Teleport to="body">
            <div class="fixed inset-0 z-40 flex items-end justify-center overscroll-none bg-black/60 md:items-center md:p-6" @click.self="dismissable && $emit('close')">
                <section ref="panel" role="dialog" aria-modal="true" :aria-labelledby="headingId" tabindex="-1"
                    class="max-h-[92dvh] w-full overflow-y-auto overscroll-contain rounded-t-3xl border border-edge bg-panel p-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] shadow-2xl outline-none md:rounded-3xl"
                    :class="wide ? 'md:max-w-xl' : 'md:max-w-md'">
                    <header class="mb-4 flex items-start justify-between gap-4" :class="tone ? '-mx-6 -mt-6 rounded-t-3xl px-6 pb-5 pt-6 transition-colors ' + TONES[tone] : ''" :data-tone="tone">
                        <div class="flex min-w-0 items-start gap-3">
                            <span v-if="tone" class="mt-1 grid size-8 shrink-0 place-items-center rounded-full bg-ink/40" aria-hidden="true"><span class="size-3 rounded-full" :class="tone === 'bad' ? 'bg-record' : tone === 'busy' ? 'animate-pulse bg-warn' : 'bg-good'"></span></span>
                            <span v-if="dot" class="mt-2 size-3 shrink-0 rounded-full" :style="{ background: dot }" aria-hidden="true"></span>
                            <div class="min-w-0">
                                <h2 :id="headingId" class="text-xl font-bold text-cream" :translate="titleIsContent ? 'no' : null">{{ title }}</h2>
                                <p v-if="subtitle" class="mt-1 text-sm text-cream/85" aria-live="polite">{{ subtitle }}</p>
                            </div>
                        </div>
                        <div class="-mt-1 flex shrink-0 items-center gap-2">
                            <slot name="actions" />
                            <button v-if="dismissable && closer" type="button" class="-me-2 rounded-full p-2 text-muted hover:text-cream" @click="$emit('close')" :aria-label="t('common.close')">
                                <Icon name="close" />
                            </button>
                        </div>
                    </header>
                    <slot />
                </section>
            </div>
        </Teleport>`,
    mounted() {
        // Hillel: scrolling the sheet scrolled the screen behind. The page holds still while a sheet
        // is open, and the sheet's own scroll stops at its ends (overscroll-contain above).
        if (openSheets++ === 0) document.documentElement.classList.add('overflow-hidden');
        // A toast from the screen behind (its actions are for that screen) would sit on the sheet's title.
        toasts.list = toasts.list.filter((item) => !item.actions?.length);
        // Focus moves into the sheet, stays there (Tab wraps), and goes back where it was on close.
        this.returnTo = document.activeElement;
        this.onKey = (event) => {
            if (event.key === 'Escape' && this.dismissable) {
                this.$emit('close');
            } else if (event.key === 'Tab') {
                const focusable = [...this.$refs.panel.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]):not([tabindex="-1"]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')];
                if (!focusable.length) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    last.focus();
                    event.preventDefault();
                } else if (!event.shiftKey && document.activeElement === last) {
                    first.focus();
                    event.preventDefault();
                }
            }
        };
        document.addEventListener('keydown', this.onKey);
        nextTick(() => (this.$refs.panel.querySelector('[data-autofocus]') ?? this.$refs.panel.querySelector('input:not([disabled]):not([tabindex="-1"]),select:not([disabled]),textarea:not([disabled]),button.primary,.btn-primary') ?? this.$refs.panel).focus());
    },
    unmounted() {
        if (--openSheets === 0) document.documentElement.classList.remove('overflow-hidden');
        document.removeEventListener('keydown', this.onKey);
        this.returnTo?.focus?.();
    },
};

export const toasts = reactive({ list: [] });
let nextToast = 1;

/** Shows a short message; `actions` are `{ label, run }` buttons. */
export function toast(message, { actions = [], tone = 'info', seconds = 6 } = {}) {
    const id = nextToast++;
    toasts.list.push({ id, message, actions, tone, seconds });
    setTimeout(() => dismissToast(id), seconds * 1000);
}

export function dismissToast(id) {
    toasts.list = toasts.list.filter((t) => t.id !== id);
}

/**
 * At the very top, just inside the safe area and above everything (Hillel's call): over the top of
 * the screen rather than on the content; tapping one dismisses it.
 */
export const Toasts = {
    directives: {
        countdown: { mounted: (el, { value }) => el.animate([{ transform: 'scaleX(1)' }, { transform: 'scaleX(0)' }], { duration: value * 1000, easing: 'linear', fill: 'forwards' }) },
    },
    setup: () => ({ toasts, dismissToast, route }),
    // In a lesson, under its header (a badge's toast once covered Back and the lesson's name while singing).
    template: `
        <div class="pointer-events-none fixed inset-x-0 z-50 flex flex-col items-center gap-2 px-3" :class="route.name === 'learn' ? 'top-[calc(env(safe-area-inset-top)+5rem)]' : 'top-[calc(env(safe-area-inset-top)+1rem)]'" aria-live="polite">
            <slot />
            <!-- The studio shows its messages in its own card above the transport (components/studio.js StudioNotice). -->
            <div v-for="item in route.name === 'studio' ? [] : toasts.list" :key="item.id"
                class="pointer-events-auto relative flex max-w-md items-center gap-3 overflow-hidden rounded-2xl border px-4 py-3 text-sm shadow-xl" :data-toast-tone="item.tone"
                :class="item.tone === 'error' ? 'border-record/50 bg-[color-mix(in_srgb,var(--color-record)_16%,var(--color-raised))] text-cream' : 'border-line bg-raised text-cream'">
                <span class="flex-1" @click="dismissToast(item.id)">{{ item.message }}</span>
                <button v-for="action in item.actions" :key="action.label" type="button" class="font-semibold text-accent-strong hover:underline"
                    @click="action.run(); dismissToast(item.id)">{{ action.label }}</button>
                <!-- With an Undo: a bar runs down the time left (78.png) -->
                <span v-if="item.actions.length" v-countdown="item.seconds" class="absolute inset-x-0 bottom-0 h-0.5 origin-left bg-accent" aria-hidden="true"></span>
            </div>
        </div>`,
};

/**
 * Draws a waveform as rounded vertical bars, the way the design does. `peaks` hold the loudest
 * sample per `framesPerPeak` frames; the canvas spans `lengthSeconds` of the timeline.
 */
export const Waveform = {
    props: {
        peaks: { type: Object, default: null },
        framesPerPeak: Number,
        sampleRate: Number,
        offsetSeconds: { type: Number, default: 0 },
        lengthSeconds: { type: Number, required: true },
        color: String,
        dim: Boolean,
        version: { type: Number, default: 0 }, // bump to redraw when peaks grow in place
        trimStartFrames: { type: Number, default: 0 }, // trimmed ends show faint (feature 17)
        trimEndFrames: { type: Number, default: 0 },
    },
    template: `<canvas ref="canvas" class="touch-pan-y block h-full w-full"></canvas>`,
    mounted() {
        this.observer = new ResizeObserver(() => this.draw());
        this.observer.observe(this.$refs.canvas);
        this.draw();
    },
    unmounted() {
        this.observer.disconnect();
    },
    watch: {
        $props: { deep: true, handler() { this.draw(); } },
    },
    methods: {
        draw() {
            const canvas = this.$refs.canvas;
            if (!canvas) {
                return;
            }
            const ratio = window.devicePixelRatio || 1;
            const width = canvas.clientWidth;
            const height = canvas.clientHeight;
            canvas.width = Math.round(width * ratio);
            canvas.height = Math.round(height * ratio);
            const g = canvas.getContext('2d');
            g.scale(ratio, ratio);
            g.clearRect(0, 0, width, height);
            const peaks = this.peaks;
            if (!peaks || !peaks.length || !width) {
                return;
            }
            const pxPerSecond = width / this.lengthSeconds;
            const secondsPerPeak = this.framesPerPeak / this.sampleRate;
            const step = width < 500 ? 3 : 4; // a bar every few pixels, as in the design
            const barWidth = step - 1.5;
            const middle = height / 2;
            g.fillStyle = this.color;
            // Dimmed (a guide, a muted part): fainter on a dark page, less so on a light one, where pale bars wash out.
            const level = this.dim ? (document.documentElement.dataset.theme?.endsWith('-light') ? 0.5 : 0.3) : 1;
            const keptFrom = this.trimStartFrames / this.framesPerPeak;
            const keptTo = peaks.length - this.trimEndFrames / this.framesPerPeak;
            for (let x = Math.max(0, this.offsetSeconds * pxPerSecond); x < width; x += step) {
                const from = Math.floor((x / pxPerSecond - this.offsetSeconds) / secondsPerPeak);
                const to = Math.floor(((x + step) / pxPerSecond - this.offsetSeconds) / secondsPerPeak);
                if (from >= peaks.length) {
                    break;
                }
                let peak = 0;
                for (let i = Math.max(0, from); i <= Math.min(peaks.length - 1, to); i++) {
                    peak = Math.max(peak, peaks[i]);
                }
                // Square root, so quiet singing still shows, with a dot for silence.
                const bar = Math.max(1.5, Math.min(1, Math.sqrt(peak) * 1.15) * (height * 0.46));
                g.globalAlpha = from >= keptFrom && from < keptTo ? level : level * 0.25;
                g.beginPath();
                g.roundRect(x, middle - bar, barWidth, bar * 2, barWidth / 2);
                g.fill();
            }
        },
    },
};
