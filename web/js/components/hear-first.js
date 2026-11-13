// Hear it first (experiment mic2, arm b; docs/improvement-log.md row 11; the first-week UX review, 2026-11-12): before
// lesson 1 asks for the mic, one tap plays four voices singing one chord, then the same chord with one voice gone, and
// says "That gap is for your voice". Proof before the ask: the tap also wakes the audio on an iPhone, so the first sound
// comes about a second in instead of never. `filled` (Can't sing right now's Listen through it) plays the chord with the
// practice voice in the gap instead. Played on the audio clock, as one buffer; the lanes light by CSS transitions with
// the same delays, never timers.

import { engine, unlock } from '../audio.js';
import { midiName } from '../dsp.js';
import { renderPart } from '../synth.js';
import { Icon } from './icon.js';

// Four voices, low to high (C3 G3 E4 C5: an open C major), and the one that drops out: the third, in the middle.
export const VOICES = [48, 55, 64, 72];
export const GAP = 2; // the index in VOICES of the voice that leaves room for yours
const CHORD_SECONDS = 2.2;
const REST_SECONDS = 0.5;
const LEVEL = 0.2; // each voice
const FADE = 0.15;
const PEAK = 0.9;

/**
 * The chord, then the chord without VOICES[GAP], as one clip: `{ samples, sampleRate, second }` (second: where the gap's
 * chord starts, in seconds). `filled`: the second chord keeps every voice (the guide sings your part).
 */
export function hearFirstClip(sampleRate, { filled = false } = {}) {
    const chord = Math.round(CHORD_SECONDS * sampleRate);
    const rest = Math.round(REST_SECONDS * sampleRate);
    const out = new Float32Array(chord * 2 + rest);
    const voices = VOICES.map((pitch) => renderPart([{ beat: 0, length: CHORD_SECONDS, pitch, vibrato: { cents: 0 } }], { bpm: 60, sampleRate }));
    const fade = FADE * sampleRate;
    for (const [start, skip] of [[0, -1], [chord + rest, filled ? -1 : GAP]]) {
        voices.forEach((voice, v) => {
            if (v === skip) return;
            for (let i = 0; i < chord && i < voice.length; i++) out[start + i] += voice[i] * LEVEL * Math.min(1, i / fade, (chord - 1 - i) / fade);
        });
    }
    let peak = 0;
    for (const v of out) peak = Math.max(peak, Math.abs(v));
    if (peak > PEAK) for (let i = 0; i < out.length; i++) out[i] *= PEAK / peak;
    return { samples: out, sampleRate, second: (chord + rest) / sampleRate };
}

export const HearFirst = {
    components: { Icon },
    props: { color: { type: String, required: true }, filled: Boolean },
    emits: ['heard'],
    data: () => ({ playing: false, heard: false, starting: false, light: null }), // light: { second, seconds } while it plays
    setup: () => ({ midiName, GAP }),
    template: `
        <section class="w-full max-w-xl rounded-3xl border border-edge bg-panel p-5" aria-labelledby="hear-first-title" data-hear-first :data-playing="playing ? 'true' : 'false'" :data-heard="heard ? 'true' : 'false'">
            <div class="flex items-center gap-4">
                <button type="button" class="grid size-16 shrink-0 place-items-center rounded-full bubble bubble-primary" :class="!heard && !playing ? 'listen-breathe' : ''" data-disc="play" :aria-label="playing ? t('mic2.playing') : t('mic2.play')" :aria-busy="starting ? 'true' : 'false'" :disabled="playing" data-hear-first-play @click="play"><Icon :name="playing ? 'volume' : 'play'" :size="26" /></button>
                <div class="min-w-0">
                    <h2 id="hear-first-title" class="text-lg font-bold text-cream">{{ filled ? t('mic2.filledTitle') : t('mic2.playTitle') }}</h2>
                    <p class="text-sm text-muted">{{ filled ? t('mic2.filledLead') : t('mic2.playLead') }}</p>
                </div>
            </div>
            <ol class="mt-4 space-y-2" :aria-label="t('mic2.lanes')">
                <li v-for="lane in lanes" :key="lane.index" class="flex items-center gap-3" :data-hear-lane="lane.index === GAP ? 'gap' : 'voice'">
                    <span class="w-20 shrink-0 text-xs font-semibold" :class="lane.index === GAP ? 'text-cream' : 'text-muted'">{{ lane.index === GAP ? (filled ? t('mic2.guideHere') : t('mic2.yourVoice')) : t('mic2.voice') }}</span>
                    <span class="relative h-2.5 flex-1 overflow-hidden rounded-full" :class="lane.index === GAP && !filled ? 'border border-dashed border-cream/70' : ''" :style="{ background: lane.index === GAP && !filled ? 'transparent' : lane.index === GAP ? color : 'var(--color-muted)', opacity: lane.index === GAP || playing || heard ? 1 : 0.45 }" aria-hidden="true">
                        <!-- Playing: a light sweeps each lane for as long as it sounds; the gap's lane only in the first chord. -->
                        <span class="absolute inset-y-0 start-0 bg-white/45 motion-reduce:hidden" :style="sweep(lane.index)" data-hear-sweep></span>
                    </span>
                    <span class="w-9 shrink-0 text-end text-xs tabular-nums text-muted" translate="no">{{ midiName(lane.pitch) }}</span>
                </li>
            </ol>
            <p v-if="heard" class="mt-4 font-semibold text-cream" role="status" data-hear-first-line>{{ filled ? t('mic2.filledLine') : t('mic2.gapLine') }}</p>
        </section>`,
    computed: {
        lanes: () => VOICES.map((pitch, index) => ({ pitch, index })).reverse(), // high to low, as a score reads
    },
    unmounted() {
        this.left = true;
        this.source?.stop();
    },
    methods: {
        /** A lane's light: across the whole clip, or (the gap, unfilled) only through the first chord. */
        sweep(index) {
            if (!this.light) return { width: '0%', transition: 'none' };
            const seconds = index === GAP && !this.filled ? this.light.second - REST_SECONDS : this.light.seconds;
            return { width: '100%', transition: `width ${seconds}s linear` };
        },
        async play() {
            if (this.playing) return;
            this.starting = true;
            try {
                await unlock(); // the tap wakes the audio (an iPhone's first sound)
            } catch (error) {
                console.warn('The audio could not start for Hear it first', error);
                this.starting = false;
                return;
            }
            this.starting = false;
            if (this.left || !engine.ctx) return;
            const { samples, sampleRate, second } = hearFirstClip(engine.sampleRate ?? engine.ctx.sampleRate, { filled: this.filled });
            const source = engine.ctx.createBufferSource();
            source.buffer = engine.toBuffer(samples, sampleRate);
            source.connect(engine.playBus);
            source.onended = () => {
                if (this.left) return;
                this.playing = false;
                this.light = null;
                this.heard = true;
                this.$emit('heard');
            };
            this.source = source;
            source.start(engine.ctx.currentTime + 0.05);
            this.playing = true;
            this.light = null;
            const seconds = samples.length / sampleRate;
            requestAnimationFrame(() => requestAnimationFrame(() => this.playing && (this.light = { second, seconds }))); // drawn at 0 first, so it moves
        },
    },
};
