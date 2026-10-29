// The Note lane (the Sing redesign, after Hillel's 2b): your notes as bars in your colour, each
// carrying its word and its name ("grace G4"), scrolling toward a vertical now line; the parts
// either side of yours faint above and below at their real pitches; a keyboard down the side with
// the note now lit; the chord names along the top while the chords play; a looped phrase tinted;
// and, on your turn, your voice as a line (stage 3). Drawn on a canvas each frame from the
// position; the screen reader gets the now card instead. Since Hillel's arcade pass (ux-spec 5.7c):
// the notes are glass gems (js/glass.js), the pitch camera (js/camera.js) frames the notes ahead
// and your voice, and the stage has faint pitch lines, a floor glow, drifting specks and a beam.

import { appearance } from '../../stores/appearance.js';
import { LanePainter } from '../../lane-painter.js';

// The drawing is js/lane-painter.js's (shared with the landing's embedded lane); this is its Vue side.
export const Lane = {
    props: {
        notes: { type: Array, required: true }, // yours: { beat, length, pitch }
        words: { type: Array, default: () => [] }, // your syllables: { beat, text }
        others: { type: Array, default: () => [] }, // [{ name, color, notes, above }]
        transpose: { type: Number, default: 0 },
        beatSeconds: { type: Number, required: true },
        position: { type: Number, default: 0 },
        color: { type: String, required: true },
        state: { type: String, default: 'listen' }, // listen, turn or recording: the now line's colour
        noteName: { type: Function, required: true }, // MIDI to a name, your way ('' for none)
        keyboard: { type: Boolean, default: true },
        chords: { type: Array, default: null }, // [{ seconds, name }] while the chords play
        loop: { type: Object, default: null }, // { startSeconds, endSeconds }
        voice: { type: Array, default: () => [] }, // your voice: [{ seconds, midi }] (live)
        lit: { type: Object, default: () => new Set() }, // the beats of the notes you hit
        hits: { type: Object, default: () => new Map() }, // beat: [[fromSeconds, toSeconds]] where you were in tune
        tolerance: { type: Number, default: 35 }, // cents either side: the in-tune band (bars are at least this tall)
        hint: { type: String, default: null }, // 'higher' or 'lower', shown with an arrow at the head
        hintText: { type: String, default: '' }, // the hint in words, as a chip beside the head (the recording stage)
        trail: { type: String, default: null }, // your voice's ribbon and dot, if not in your colour (game mode's on-fire run)
    },
    template: `<div class="relative h-full w-full"><canvas ref="canvas" class="touch-pan-y block h-full w-full" aria-hidden="true"></canvas></div>`,
    watch: {
        // A new theme, or a colour that came with it: its tokens again, and drawn again.
        'appearance.version'() {
            this.painter?.readTokens();
            this.draw();
        },
    },
    mounted() {
        this.painter = new LanePainter(this.$refs.canvas, this.$el);
        this.observer = new ResizeObserver(() => this.draw());
        this.observer.observe(this.$refs.canvas);
        this.draw();
    },
    unmounted() {
        this.observer?.disconnect();
        this.painter?.destroy();
    },
    created() {
        // Any prop that changes (the position each frame, the voice, a hit) draws again, through the
        // painter: the template reads none of them, so nothing re-renders by itself.
        this.$watch(() => Object.keys(this.$props).map((key) => this[key]), () => this.draw());
    },
    setup: () => ({ appearance }),
    methods: {
        draw() {
            this.painter?.set({ ...this.$props }).draw();
        },
    },
};

/** The note now, big: its name, its word, and what's next. */
export const NowCard = {
    props: { note: { type: String, default: '' }, word: { type: String, default: '' }, next: { type: String, default: '' }, color: { type: String, required: true }, outlined: Boolean, hint: { type: String, default: null } },
    template: `
        <div class="flex items-center gap-4 rounded-3xl border border-edge bg-panel p-3" aria-live="off">
            <div class="grid size-16 shrink-0 place-items-center rounded-2xl border-2 text-center" :style="outlined ? { borderColor: color } : { background: color, borderColor: color, color: 'var(--color-ink)' }">
                <span class="text-xl font-extrabold leading-none tabular-nums" :class="outlined ? 'text-cream' : ''">{{ note || '·' }}<span class="block text-[11px] font-semibold opacity-70">{{ t('sing.card.now') }}</span></span>
            </div>
            <div class="min-w-0 flex-1">
                <p v-if="word" class="truncate text-2xl font-extrabold text-cream" translate="no">{{ t('sing.card.word', { word }) }}</p>
                <p v-if="hint" class="flex items-center gap-1.5 text-sm font-bold text-cream" role="status"><span class="grid size-5 place-items-center rounded-full text-[10px] text-ink" :style="{ background: color }" aria-hidden="true">{{ hint === 'higher' ? '▲' : '▼' }}</span>{{ hint === 'higher' ? t('sing.hint.higher') : t('sing.hint.lower') }}</p>
                <p v-else class="truncate text-sm text-muted">{{ next }}</p>
            </div>
        </div>`,
};
