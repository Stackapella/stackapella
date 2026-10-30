// Self-check (docs/plus-courses-build-plan.md, 6a, "What we can't hear yet"): for what the app
// can't measure yet (a belt's colour, diction, a beatbox sound, an accent). Hear the model, record
// yourself over the same drone or chords, play yours and the model back (one after the other, or
// together), then answer two or three self-check questions. It never makes up a score: it says "We
// don't score this yet", and the step is done once you've recorded and answered (its lesson's stars
// are for practising). Every sound is scheduled on the audio clock.
//
// The step: `{ kind: 'selfcheck', text, exercise: { tempo, lead?, model: { notes: [{ beat, length,
// step, vibrato? }], drone?, chords? }, along?, questions: [{ en }] } }`: `lead` beats of the drone
// or chords before you sing, `along` the model singing with you (headphones only).

import { audio, engine, leadSeconds, unlock } from '../audio.js';
import { readyMic } from '../mic-check.js';
import { localized, t } from '../i18n.js';
import { renderBacking, renderPart } from '../synth.js';
import { Icon } from './icon.js';
import { showMicHelp } from '../mic-help.js';
import { HeadphonesDialog } from './dialogs.js';
import { local, session as tabStorage } from '../storage.js';
import { renderClip } from './choose.js';
import { clipBuffer, publishedFor } from '../course-clips.js';

/** Headphones on (this visit, or "don't remind me"), the speaker chosen, or not asked yet (null). */
function listening() {
    if (tabStorage.get('stackapella.headphones') === '1' || local.get('stackapella.headphones.never') === '1') return 'headphones';
    return tabStorage.get('stackapella.speaker') === '1' ? 'speaker' : null;
}

/**
 * The model's length in beats: its notes', or for a model that's only a real singer's clip and a
 * description (no notes), its `seconds` at the tempo.
 */
export function modelBeats(model, tempo = 72) {
    if (model.notes?.length) return Math.max(...model.notes.map((n) => n.beat + n.length));
    return ((model.seconds ?? 4) * tempo) / 60;
}

/** Whether a self-check is done: recorded, and every question answered. */
export function selfCheckDone(recorded, questions, answers) {
    return recorded && questions.every((_, i) => answers[i] === 'yes' || answers[i] === 'notYet');
}

export const SelfCheck = {
    components: { HeadphonesDialog, Icon },
    inheritAttrs: false, // the lesson view passes a sung step's props (the key, strictness): not used here
    props: {
        exercise: { type: Object, required: true },
        base: { type: Number, required: true },
        color: { type: String, default: 'var(--color-part-4)' },
    },
    emits: ['done', 'practised'],
    data: () => ({ recording: false, take: null, playing: null, answers: {}, asking: false, progress: 0, error: null, published: null }),
    setup: () => ({ localized }),
    template: `
        <div class="space-y-5" data-selfcheck>
            <ol class="space-y-3">
                <!-- 1. Hear the model -->
                <li class="flex flex-wrap items-center gap-3 rounded-2xl border border-edge bg-panel p-4">
                    <span class="w-5 text-center text-xl font-extrabold tabular-nums text-accent-strong" aria-hidden="true">1</span>
                    <span class="min-w-0 flex-1"><span class="block font-bold text-cream">{{ t('selfcheck.model') }}</span>
                        <span v-if="published?.credit" class="block text-xs text-muted" translate="no">{{ t('choose.sungBy', { name: published.credit }) }}</span>
                        <span v-else-if="!canHearModel && model.description" class="block text-sm text-muted" translate="no" data-selfcheck-described>{{ localized(model.description) }}</span></span>
                    <button v-if="canHearModel" type="button" class="btn-secondary btn-sm" :disabled="recording" @click="play('model')"><Icon :name="playing === 'model' ? 'stop' : 'play'" :size="14" /> {{ playing === 'model' ? t('studio.stop') : t('selfcheck.hear') }}</button>
                </li>
                <!-- 2. Record yourself -->
                <li class="space-y-3 rounded-2xl border border-edge bg-panel p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="w-5 text-center text-xl font-extrabold tabular-nums text-accent-strong" aria-hidden="true">2</span>
                        <span class="min-w-0 flex-1 font-bold text-cream">{{ t('selfcheck.record') }}</span>
                        <button type="button" class="btn-primary btn-sm" @click="recording ? stop() : record()" data-selfcheck-record><Icon :name="recording ? 'stop' : 'mic'" :size="14" /> {{ recording ? t('studio.stop') : take ? t('selfcheck.recordAgain') : t('selfcheck.start') }}</button>
                    </div>
                    <div v-if="recording" class="h-2 overflow-hidden rounded-full bg-raised" role="progressbar" :aria-valuenow="percentDone" aria-valuemin="0" aria-valuemax="100" :aria-label="t('selfcheck.recording')"><div class="h-full rounded-full" :style="{ width: percentDone + '%', background: color }"></div></div>
                    <p v-if="recording" class="text-sm text-muted" role="status">{{ progressWords }}</p>
                </li>
                <!-- 3. Compare -->
                <li class="flex flex-wrap items-center gap-3 rounded-2xl border border-edge bg-panel p-4" :class="take ? '' : 'opacity-60'">
                    <span class="w-5 text-center text-xl font-extrabold tabular-nums text-accent-strong" aria-hidden="true">3</span>
                    <span class="min-w-0 flex-1 font-bold text-cream">{{ t('selfcheck.compare') }}</span>
                    <div class="flex w-full flex-wrap gap-2 sm:w-auto">
                        <button type="button" class="btn-secondary btn-sm" :disabled="!take || recording" @click="play('yours')" data-selfcheck-yours><Icon :name="playing === 'yours' ? 'stop' : 'play'" :size="14" /> {{ t('selfcheck.yours') }}</button>
                        <button v-if="canHearModel" type="button" class="btn-secondary btn-sm" :disabled="!take || recording" @click="play('both')"><Icon :name="playing === 'both' ? 'stop' : 'play'" :size="14" /> {{ t('selfcheck.together') }}</button>
                    </div>
                </li>
            </ol>
            <!-- 4. Ask yourself -->
            <section v-if="take" class="space-y-3" :aria-label="t('selfcheck.ask')" data-selfcheck-questions>
                <h2 class="font-bold text-cream">{{ t('selfcheck.ask') }}</h2>
                <div v-for="(q, i) in questions" :key="i" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line px-4 py-3">
                    <p class="min-w-0 flex-1 text-cream" translate="no">{{ localized(q) }}</p>
                    <div class="flex gap-2" role="radiogroup" :aria-label="localized(q)">
                        <button type="button" role="radio" class="filter-chip" :aria-checked="answers[i] === 'yes' ? 'true' : 'false'" @click="answer(i, 'yes')">{{ t('selfcheck.yes') }}</button>
                        <button type="button" role="radio" class="filter-chip" :aria-checked="answers[i] === 'notYet' ? 'true' : 'false'" @click="answer(i, 'notYet')">{{ t('selfcheck.notYet') }}</button>
                    </div>
                </div>
                <p v-if="allAnswered && notYets" class="text-sm text-muted" role="status">{{ t('selfcheck.notYetLine') }}</p>
            </section>
            <p class="text-sm text-muted" data-selfcheck-honest>{{ t('selfcheck.honest') }}</p>
            <p v-if="error" class="text-sm text-bad" role="alert">{{ error }}</p>
            <HeadphonesDialog v-if="asking" speaker-option simple :action="t('headphones.on')" @ok="headphonesOn" @speaker="useSpeaker" @close="asking = false" />
        </div>`,
    computed: {
        model: (vm) => vm.exercise.model,
        questions: (vm) => vm.exercise.questions ?? [],
        tempo: (vm) => vm.exercise.tempo ?? 72,
        beatSeconds: (vm) => 60 / (vm.exercise.tempo ?? 72),
        leadBeats: (vm) => vm.exercise.lead ?? (vm.exercise.model.drone || vm.exercise.model.chords ? 2 : 0),
        /** The take: the lead-in, the model's length, and a moment after. */
        totalSeconds: (vm) => (vm.leadBeats + modelBeats(vm.exercise.model, vm.tempo)) * vm.beatSeconds + 0.5,
        /** The model can be heard: a real singer's recording, or the guide's notes (else it's described). */
        canHearModel: (vm) => !!vm.published || !!vm.exercise.model.notes?.length,
        percentDone: (vm) => Math.round(vm.progress * 100),
        allAnswered: (vm) => vm.questions.every((_, i) => !!vm.answers[i]),
        notYets: (vm) => Object.values(vm.answers).filter((a) => a === 'notYet').length,
        progressWords() {
            const sung = this.progress * this.totalSeconds;
            return sung < this.leadBeats * this.beatSeconds ? t('selfcheck.lead') : t('selfcheck.recording');
        },
        /** Something sounds while you sing: through a speaker, the mic would hear it too. */
        soundsUnder: (vm) => !!vm.exercise.model.drone || !!vm.exercise.model.chords || !!vm.exercise.along,
    },
    async created() {
        // A real singer's model, once it's recorded and published (clips.json's slot).
        if (this.exercise.model.clip) this.published = (await publishedFor([this.exercise.model.clip]))[this.exercise.model.clip] ?? null;
    },
    unmounted() {
        this.source?.stop();
        this.under?.stop();
        cancelAnimationFrame(this.frame);
        if (this.recording) engine.stop().catch(() => {});
        [...engine.parts.keys()].filter((id) => id.startsWith('ex:')).forEach((id) => engine.removePart(id));
    },
    methods: {
        headphonesOn(never) {
            tabStorage.set('stackapella.headphones', '1');
            if (never) local.set('stackapella.headphones.never', '1');
            this.asking = false;
            this.record();
        },
        useSpeaker() {
            tabStorage.set('stackapella.speaker', '1');
            this.asking = false;
            this.record();
        },
        /** The model as one sound, shifted by the lead-in so it lines up with your take. */
        modelSamples(sampleRate, { leadIn = false } = {}) {
            const shift = leadIn ? this.leadBeats : 0;
            const clip = { ...this.model, notes: this.model.notes.map((n) => ({ ...n, beat: n.beat + shift })) };
            return renderClip(clip, this.base, { tempo: this.tempo, sampleRate });
        },
        /** Record yourself: the drone or chords under you (and the model, sung along, with headphones). */
        async record() {
            this.error = null;
            this.source?.stop();
            if (this.soundsUnder && !listening()) {
                this.asking = true;
                return;
            }
            const speaker = listening() === 'speaker';
            try {
                await unlock();
                if (!(await readyMic())) return;
            } catch (error) {
                showMicHelp(error, () => this.record());
                return;
            }
            const sr = engine.sampleRate;
            const bpm = this.tempo;
            const end = this.leadBeats + modelBeats(this.model, bpm);
            [...engine.parts.keys()].forEach((id) => engine.removePart(id));
            engine.projectId = null;
            // Through a speaker, what plays under you rests once you start, so the mic hears only you.
            const under = speaker ? this.leadBeats : end;
            if (this.model.drone && under > 0) engine.setPart('ex:drone', renderPart([{ beat: 0, length: under, pitch: this.base + this.model.drone.step, vibrato: { cents: 0 } }], { bpm, sampleRate: sr }), sr, { volume: 0.5, pan: 0, audible: true, offsetSeconds: 0 });
            if (this.model.chords && under > 0) engine.setPart('ex:chords', renderBacking(this.model.chords, { bpm, sampleRate: sr, endBeat: under, transpose: ((this.base % 12) + 12) % 12 }), sr, { volume: 0.8, pan: 0, audible: true, offsetSeconds: 0 });
            if (this.exercise.along && !speaker) {
                const notes = this.model.notes.map((n) => ({ beat: n.beat + this.leadBeats, length: n.length, pitch: this.base + n.step, vibrato: n.vibrato ?? null }));
                engine.setPart('ex:guide', renderPart(notes, { bpm, sampleRate: sr }), sr, { volume: 0.6, pan: 0, audible: true, offsetSeconds: 0 });
            }
            this.recording = true;
            this.startedAt = performance.now();
            engine.play({ fromSeconds: 0, bpm, record: true, latencyFrames: audio.latencyFrames ?? engine.estimatedLatencyFrames(), leadSeconds: leadSeconds() });
            const tick = () => {
                const position = engine.positionSeconds();
                if (position === null || !this.recording) return;
                this.progress = Math.min(1, Math.max(0, position) / this.totalSeconds);
                if (position >= this.totalSeconds) {
                    this.stop();
                    return;
                }
                this.frame = requestAnimationFrame(tick);
            };
            this.frame = requestAnimationFrame(tick);
        },
        async stop() {
            cancelAnimationFrame(this.frame);
            const finished = this.progress >= 0.99;
            this.recording = false;
            let take = null;
            try {
                take = await engine.stop();
            } catch (error) {
                console.warn('Stopped before the recording began', error);
            }
            this.$emit('practised', (performance.now() - this.startedAt) / 1000);
            if (take && finished) {
                this.take = { samples: take.samples, sampleRate: take.sampleRate };
                this.check();
            }
        },
        /**
         * Hear one: the model, yours, or both together (the model quieter, lined up with your take).
         * Tapping the one playing stops it. On the audio clock.
         */
        async play(which) {
            const was = this.playing;
            this.source?.stop();
            this.under?.stop();
            if (was === which) return;
            await unlock();
            // A real singer's model: the recording itself (with yours, both start together on the clock).
            const recording = this.published && which !== 'yours' ? await clipBuffer(this.published.url).catch((error) => (console.warn('Model clip not played', error), null)) : null;
            if (recording) {
                this.playRecording(which, recording);
                return;
            }
            if (which !== 'yours' && !this.exercise.model.notes?.length) return;
            // Yours at its own rate (the model rendered to match, to mix them); the model alone at the context's.
            const sr = which === 'model' ? engine.sampleRate : this.take.sampleRate;
            let samples;
            if (which === 'model') samples = this.modelSamples(sr);
            else {
                samples = Float32Array.from(this.take.samples);
                if (which === 'both') {
                    const model = this.modelSamples(sr, { leadIn: true });
                    for (let i = 0; i < Math.min(samples.length, model.length); i++) samples[i] += 0.6 * model[i];
                }
            }
            const source = engine.ctx.createBufferSource();
            source.buffer = engine.toBuffer(samples, sr);
            source.connect(engine.playBus);
            source.onended = () => {
                if (this.source === source) {
                    this.source = null;
                    this.playing = null;
                }
            };
            source.start(engine.ctx.currentTime + 0.03);
            this.source = source;
            this.playing = which;
        },
        /** The recorded model alone, or under your take from its lead-in, on the audio clock. */
        playRecording(which, recording) {
            const at = engine.ctx.currentTime + 0.03;
            const start = (buffer, when, volume = 1) => {
                const source = engine.ctx.createBufferSource();
                source.buffer = buffer;
                const gain = engine.ctx.createGain();
                gain.gain.value = volume;
                source.connect(gain).connect(engine.playBus);
                source.start(when);
                return source;
            };
            const main = which === 'model' ? start(recording, at) : start(engine.toBuffer(this.take.samples, this.take.sampleRate), at);
            this.under = which === 'both' ? start(recording, at + this.leadBeats * this.beatSeconds, 0.6) : null;
            main.onended = () => {
                if (this.source === main) {
                    this.source = null;
                    this.playing = null;
                    this.under?.stop();
                }
            };
            this.source = main;
            this.playing = which;
        },
        answer(i, value) {
            this.answers = { ...this.answers, [i]: value };
            this.check();
        },
        /** Done once recorded and every question answered: the lesson goes on. */
        check() {
            if (selfCheckDone(!!this.take, this.questions, this.answers)) this.$emit('done', { percent: null, answers: this.questions.map((_, i) => this.answers[i]) });
        },
    },
};
