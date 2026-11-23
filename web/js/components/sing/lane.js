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
import { t } from '../../i18n.js';
import { KaraokeLine } from './stage.js';

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
        ghost: { type: Array, default: () => [] }, // game mode's ghost: your best take's line to race
        flat: Boolean, // Hillel's Notes view look (js/lane-painter.js LANE_DEFAULTS.flat): Sing's lane
        perBar: { type: Number, default: 0 }, // beats in a bar, for the numbered bar lines (with flat)
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

/**
 * The note now, as a row (Hillel's Notes view design, 2026-11-23): the note in a tile in your colour ("now" under it),
 * the line being sung with its syllable lit (Sing's one KaraokeLine, so the words are read in one way) and what's next,
 * and from a wide screen a pitch meter: your voice against the note, the state in words first (In tune, Slightly sharp)
 * and the colour repeating it (green, amber, grey; docs/design-rules.md "Live readings"). Without a line (a part with no
 * words), the word as before.
 */
const TUNE_CENTS = 35; // in tune within this many cents either way (the lane's band)
const SLIGHT_CENTS = 60; // past this, sharp or flat outright

export const NowCard = {
    components: { KaraokeLine },
    props: {
        note: { type: String, default: '' },
        word: { type: String, default: '' },
        next: { type: String, default: '' },
        color: { type: String, required: true },
        outlined: Boolean,
        hint: { type: String, default: null },
        line: { type: Object, default: null }, // the line now: { syllables: [{ text, seconds }] }
        position: { type: Number, default: 0 },
        tuning: { type: Object, default: null }, // your voice now: { cents }, or null
        live: Boolean, // the mic is listening (your turn, or recording)
    },
    template: `
        <div class="flex items-center gap-5" aria-live="off" data-now-row>
            <div class="grid size-[72px] shrink-0 place-items-center rounded-[18px] border-2 text-center" :style="outlined ? { borderColor: color } : { background: color, borderColor: color, color: paintText(color) }" data-now-tile>
                <span class="leading-none" :class="outlined ? 'text-cream' : ''"><span class="block text-[26px] font-bold tabular-nums" translate="no">{{ note || '·' }}</span><span class="mt-1 block text-[11px] font-medium">{{ t('sing.card.now') }}</span></span>
            </div>
            <div class="min-w-0 flex-1 space-y-1.5">
                <KaraokeLine v-if="line" :line="line" :position="position" :color="color" class="text-2xl font-semibold leading-tight tracking-[-0.01em] max-md:line-clamp-2 md:truncate md:text-[34px]" />
                <p v-else-if="word" class="truncate text-2xl font-semibold text-cream md:text-[34px]" translate="no">{{ t('sing.card.word', { word }) }}</p>
                <p v-if="hint" data-ui="facts" class="flex items-center gap-1.5 text-sm font-bold text-cream" role="status"><span class="grid size-5 place-items-center rounded-full text-[10px] text-ink" :style="{ background: color }" aria-hidden="true">{{ hint === 'higher' ? '▲' : '▼' }}</span>{{ hint === 'higher' ? t('sing.hint.higher') : t('sing.hint.lower') }}</p>
                <p v-else class="truncate text-sm text-subtle">{{ next }}</p>
            </div>
            <div class="hidden w-[200px] shrink-0 flex-col gap-2 lg:flex" data-pitch-meter :data-state="meter.state">
                <p class="flex justify-between text-[13px]"><span class="text-subtle">{{ t('sing.meter.label') }}</span><span class="font-semibold" :class="meter.tone">{{ meter.words }}</span></p>
                <div class="relative h-2.5 rounded-full border border-edge bg-raised" aria-hidden="true">
                    <span class="absolute inset-y-0 left-[35%] right-[35%] bg-good/20"></span>
                    <span class="absolute -inset-y-1 left-1/2 w-px bg-cream/40"></span>
                    <span class="absolute top-1/2 size-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full transition-[left] duration-100 motion-reduce:transition-none" :class="meter.dot" :style="{ left: meter.at + '%' }"></span>
                </div>
                <p class="flex justify-between text-[11px] text-subtle" aria-hidden="true"><span>{{ t('sing.meter.flat') }}</span><span>{{ t('sing.meter.sharp') }}</span></p>
            </div>
        </div>`,
    computed: {
        meter() {
            const cents = this.tuning?.cents;
            if (!this.live || cents == null) return { state: 'ready', words: t('sing.meter.ready'), tone: 'text-subtle', dot: 'bg-subtle/60', at: 50 };
            const off = Math.abs(cents);
            const at = Math.max(4, Math.min(96, 50 + cents * 0.5));
            if (off <= TUNE_CENTS) return { state: 'in-tune', words: t('sing.meter.inTune'), tone: 'text-good', dot: 'bg-good', at };
            const words = cents > 0 ? (off <= SLIGHT_CENTS ? t('sing.meter.slightlySharp') : t('sing.meter.sharpNow')) : off <= SLIGHT_CENTS ? t('sing.meter.slightlyFlat') : t('sing.meter.flatNow');
            return { state: 'off', words, tone: 'text-warn', dot: 'bg-warn', at };
        },
    },
};
