// The chord moment (docs/onboarding-review.md, session 1 step 3; onboarding item 3): on lesson 1's
// end, the last two seconds of your held note (kept in memory by the note finder, never saved or
// sent) play back once by themselves, and two guide voices join them as a warm major chord, a third
// and a fifth placed to suit your range. Three lanes show it, yours lit. The same in both skins: the
// wonder is musical. Played on the audio clock, after the stars have had their moment.

import { engine } from '../audio.js';
import { first } from '../funnel.js';
import { midiName } from '../dsp.js';
import { renderPart } from '../synth.js';
import { Icon } from './icon.js';

const START_AFTER = 1.6; // seconds after the end screen shows: the stars and their chime first
const GUIDE_LEVEL = 0.32; // each guide voice, under yours
const VOICE_RMS = 0.16; // your note, brought to a steady level whatever the mic's gain
const FADE_IN = 0.12;
const FADE_OUT = 0.35;
const PEAK = 0.95;

/**
 * The chord around your note (MIDI, may be fractional), as three pitches low to high with yours
 * marked: low voices are the chord's root (a third and a fifth above), high ones its top (the root
 * and the third below), and the middle its third, so the chord sits around you.
 */
export function chordAround(note) {
    const others = note < 57 ? [4, 7] : note > 67 ? [-7, -3] : [-4, 3];
    return [{ pitch: note, yours: true }, ...others.map((step) => ({ pitch: note + step, yours: false }))].sort((a, b) => a.pitch - b.pitch);
}

/** Your clip and the two guide voices, mixed: `{ samples, sampleRate }`. */
export function chordMix(clip, note) {
    const { sampleRate } = clip;
    const length = clip.samples.length;
    let sum = 0;
    for (const v of clip.samples) sum += v * v;
    const gain = VOICE_RMS / Math.max(1e-4, Math.sqrt(sum / length));
    const out = clip.samples.map((v) => v * gain);
    for (const part of chordAround(note).filter((p) => !p.yours)) {
        const guide = renderPart([{ beat: 0, length: length / sampleRate, pitch: part.pitch, vibrato: { cents: 0 } }], { bpm: 60, sampleRate });
        for (let i = 0; i < length && i < guide.length; i++) out[i] += guide[i] * GUIDE_LEVEL;
    }
    // The three come in and go out together.
    const fadeIn = FADE_IN * sampleRate;
    const fadeOut = FADE_OUT * sampleRate;
    for (let i = 0; i < length; i++) out[i] *= Math.min(1, i / fadeIn, (length - 1 - i) / fadeOut);
    let peak = 0;
    for (const v of out) peak = Math.max(peak, Math.abs(v));
    if (peak > PEAK) for (let i = 0; i < length; i++) out[i] *= PEAK / peak;
    return { samples: out, sampleRate };
}

export const ChordMoment = {
    components: { Icon },
    props: { clip: { type: Object, required: true }, note: { type: Number, required: true }, color: { type: String, required: true } },
    data: () => ({ playing: false, heard: false, sweep: null }), // sweep: { seconds, after } while the chord plays
    setup: () => ({ midiName }),
    template: `
        <section class="w-full max-w-xl rounded-3xl border border-edge bg-panel p-5" aria-labelledby="chord-moment-title" data-chord-moment :data-playing="playing ? 'true' : 'false'">
            <div class="flex items-center gap-4">
                <button type="button" class="glass-bubble-solid grid size-14 shrink-0 place-items-center rounded-full" :aria-label="playing ? t('chordMoment.playing') : t('chordMoment.play')" :disabled="playing" data-chord-play @click="play(0)"><Icon :name="playing ? 'volume' : 'play'" :size="22" /></button>
                <h2 id="chord-moment-title" class="text-lg font-bold text-cream">{{ t('chordMoment.title') }}</h2>
            </div>
            <ol class="mt-4 space-y-2" :aria-label="t('chordMoment.lanes')">
                <li v-for="lane in lanes" :key="lane.pitch" class="flex items-center gap-3">
                    <span class="w-14 shrink-0 text-xs font-semibold" :class="lane.yours ? 'text-cream' : 'text-muted'">{{ lane.yours ? t('chordMoment.you') : t('chordMoment.guide') }}</span>
                    <span class="relative h-2.5 flex-1 overflow-hidden rounded-full transition-opacity duration-500" :style="{ background: lane.yours ? color : 'var(--color-muted)', opacity: lane.yours ? 1 : playing || heard ? 0.75 : 0.3 }" aria-hidden="true">
                        <!-- Playing: a light sweeps across, for as long as the chord sounds (a transition, never a timer). -->
                        <span class="absolute inset-y-0 start-0 bg-white/45 motion-reduce:hidden" :style="sweep ? { width: '100%', transition: 'width ' + sweep.seconds + 's linear ' + sweep.after + 's' } : { width: '0%', transition: 'none' }" data-chord-sweep></span>
                    </span>
                    <span class="w-9 shrink-0 text-end text-xs tabular-nums text-muted" translate="no">{{ midiName(Math.round(lane.pitch)) }}</span>
                </li>
            </ol>
            <p v-if="heard" class="mt-4 text-cream" role="status" data-chord-line>{{ t('chordMoment.line') }}</p>
        </section>`,
    computed: {
        lanes() {
            return [...chordAround(this.note)].reverse(); // high to low, as a score reads
        },
    },
    mounted() {
        this.play(START_AFTER); // once, by itself
    },
    unmounted() {
        this.left = true;
        this.source?.stop();
    },
    methods: {
        /** Plays the chord `after` seconds from now, on the audio clock. */
        play(after) {
            if (this.playing || !engine.ctx) return;
            const { samples, sampleRate } = chordMix(this.clip, this.note);
            const source = engine.ctx.createBufferSource();
            source.buffer = engine.toBuffer(samples, sampleRate);
            source.connect(engine.playBus);
            source.onended = () => {
                if (this.left) return;
                this.playing = false;
                this.sweep = null;
                this.heard = true;
                first('f_chord'); // the funnel: the chord moment played to its end
            };
            this.source = source;
            source.start(engine.ctx.currentTime + after);
            this.playing = true;
            this.sweep = null;
            const seconds = samples.length / sampleRate;
            requestAnimationFrame(() => requestAnimationFrame(() => this.playing && (this.sweep = { seconds, after }))); // drawn at 0 first, so it moves
        },
    },
};
