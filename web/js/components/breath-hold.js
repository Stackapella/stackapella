// A long note (docs/courses/breath.md): the Breath and stamina course's hold, a course step of its own
// (`breathhold`, registered in js/lesson-steps.js). Over a drone, you hold one note as long as it's
// easy; the breath bar fills as you go, with your goal marked. The take ends half a second after you
// go quiet, so stopping is the win: a steady stop seals the bar, and nothing is gained by going past
// the goal. The goal is your own: the first hold's steady seconds (your baseline) plus a little, never
// over 15 seconds, and no goal at all before a first hold sets one. Everything that sounds is scheduled on the audio clock by the engine; the
// screen only reads the position and the mic. Measured by pitch-skills.js `sustain`.
//
// The step: `{ kind: 'breathhold', exercise: { step, lyric, drone, lead, max, goal: { plus },
// baseline, generous, compare: { step }, under: { steps } } }`, each explained in docs/courses/breath.md.

import { audio, engine, leadSeconds, unlock } from '../audio.js';
import { readyMic } from '../mic-check.js';
import { formatNumber, t, tk } from '../i18n.js';
import { trackFrame, trackInWorker } from '../pitchtrack.js';
import { renderPart } from '../synth.js';
import { Icon } from './icon.js';
import { showMicHelp } from '../mic-help.js';
import { LISTEN_CLARITY } from '../listen.js';
import { HeadphonesDialog } from './dialogs.js';
import { KeyControl } from './key-control.js';
import { local, session as tabStorage } from '../storage.js';
import { centsOf } from '../vibrato.js';
import * as db from '../db.js';
import { GOAL_MAX, holdGoal, sustain } from '../pitch-skills.js';
import { effect } from '../game/effects.js';

const LEAD = 2; // seconds of drone before you sing (the exercise's `lead`)
const QUIET_ENDS = 0.5; // this long quiet after singing ends the take
const SPEAKER_SETTLE = 0.15; // through a speaker, the drone's tail has died away by then
const WAIT_FOR_VOICE = 10; // seconds to wait for a first sound before the take ends unheard

/** Headphones on (this visit, or "don't remind me"), the speaker chosen, or not asked yet (null). */
function listening() {
    if (tabStorage.get('stackapella.headphones') === '1' || local.get('stackapella.headphones.never') === '1') return 'headphones';
    return tabStorage.get('stackapella.speaker') === '1' ? 'speaker' : null;
}

// How the hold ended, in words (en.json `breathhold.end`).
const ENDING = { steady: tk('breathhold.end.steady'), sagged: tk('breathhold.end.sagged'), wobbled: tk('breathhold.end.wobbled') };
const ENDING_SHORT = { steady: tk('breathhold.short.steady'), sagged: tk('breathhold.short.sagged'), wobbled: tk('breathhold.short.wobbled') };

export const BreathHold = {
    components: { HeadphonesDialog, Icon, KeyControl },
    props: {
        exercise: { type: Object, required: true },
        base: { type: Number, required: true }, // the learner's comfortable note (MIDI)
        strictness: { type: String, default: 'relaxed' },
        color: { type: String, default: 'var(--color-part-4)' },
        musicKey: { type: String, default: null },
        canLower: { type: Boolean, default: true },
        canHigher: { type: Boolean, default: true },
        earlier: { type: Object, default: () => ({ results: {}, takes: {} }) }, // this visit's earlier steps
        personal: { type: Object, default: () => ({}) }, // the course's personal values: `baseline`
    },
    emits: ['done', 'practised', 'lower', 'higher'],
    data: () => ({ thenKept: null, running: false, scoring: false, position: 0, startedAt: null, quietSince: null, result: null, take: null, asking: false, speaker: listening() === 'speaker', playing: false }),
    template: `
        <div class="space-y-5" data-breath-hold>
            <!-- The breath bar: fills as you hold, your goal marked; a steady stop past it seals it. -->
            <div class="space-y-2">
                <div class="relative h-6 overflow-hidden rounded-full bg-raised" role="meter" aria-valuemin="0" :aria-valuemax="scale" :aria-valuenow="shownSeconds" :aria-label="t('breathhold.bar', { seconds: formatNumber(shownSeconds), goal: goal ? formatNumber(goal) : '' })" :style="[sealed ? { boxShadow: '0 0 0 3px ' + color } : null, { '--meter-seg': meterSegment + '%' }]" data-breath-bar :data-sealed="sealed ? 'true' : 'false'">
                    <div class="h-full rounded-full transition-[width] duration-100" :style="{ width: (100 * Math.min(1, meterSeconds / scale)) + '%', background: color }" data-breath-fill></div>
                    <span v-if="before" class="absolute inset-y-0 w-0.5 bg-muted" :style="{ left: (100 * Math.min(1, before / scale)) + '%' }" aria-hidden="true"></span>
                    <span v-if="goal" class="absolute -inset-y-1 w-1 rounded-full bg-cream" :style="{ left: (100 * Math.min(1, goal / scale)) + '%' }" aria-hidden="true" data-breath-goal><span v-if="effect('hud')" class="ge-goal">{{ t('game.breath.goal') }}</span></span>
                </div>
                <div class="flex justify-between text-xs text-muted tabular-nums">
                    <span>{{ t('breathhold.seconds', { seconds: formatNumber(shownSeconds) }) }}</span>
                    <span v-if="goal">{{ t('breathhold.goal', { seconds: formatNumber(goal) }) }}</span>
                </div>
            </div>
            <p class="min-h-6 text-center text-sm font-semibold" :class="phase === 'hold' ? 'text-cream' : 'text-muted'" aria-live="polite">{{ phaseWords }}</p>

            <!-- Hear yourself: the take, how long it was steady, and how it ended. -->
            <section v-if="result && !running" class="space-y-2 rounded-2xl border border-edge bg-panel p-4" data-breath-result>
                <div class="flex items-center gap-3">
                    <button v-if="take" type="button" class="grid size-11 shrink-0 place-items-center rounded-full glass-bubble-solid" :aria-label="playing ? t('studio.stop') : t('breathhold.hearYourself')" @click="listenBack()"><Icon :name="playing ? 'stop' : 'play'" :size="18" /></button>
                    <div class="min-w-0">
                        <p class="font-bold text-cream">{{ result.percent === null ? t('course.notHeard') : t('breathhold.hearYourself') }}</p>
                        <p v-if="result.tail" class="text-sm text-cream" role="status" data-breath-line>{{ t(ENDING[result.tail], { seconds: formatNumber(result.steady) }) }}</p>
                        <p v-if="more" class="text-sm font-semibold" :style="{ color }">{{ t('breathhold.more', { seconds: formatNumber(more) }) }}</p>
                    </div>
                </div>
                <!-- Then and now (exercise.thenAndNow): your first hold, kept on this device; steadiness and the ending, never length alone. -->
                <div v-if="exercise.thenAndNow" class="flex flex-wrap items-center gap-3 border-t border-line pt-3" data-then-and-now>
                    <template v-if="thenKept">
                        <p class="min-w-0 flex-1 text-sm text-cream">{{ t('breathhold.thenNow', { then: t(ENDING_SHORT[thenKept.tail] ?? ENDING_SHORT.steady), now: t(ENDING_SHORT[result.tail] ?? ENDING_SHORT.steady), thenShare: share(thenKept), nowShare: share(result) }) }}</p>
                        <button type="button" class="btn-ghost btn-sm" @click="hearThen"><Icon name="play" :size="14" /> {{ t('breathhold.hearThen') }}</button>
                    </template>
                    <p v-else class="text-sm text-muted">{{ t('breathhold.thenNone') }}</p>
                </div>
            </section>

            <p v-if="speaker && exercise.drone" class="text-center text-sm text-muted">{{ t('course.speakerUnder') }}</p>
            <HeadphonesDialog v-if="asking" speaker-option simple :action="t('headphones.on')" @ok="headphonesOn" @speaker="useSpeaker" @close="asking = false" />
            <div class="flex flex-col items-center gap-5">
                <KeyControl v-if="musicKey && !running && !scoring" class="w-full" :music-key="musicKey" :can-lower="canLower" :can-higher="canHigher" @lower="$emit('lower')" @higher="$emit('higher')" />
                <button type="button" class="group flex flex-col items-center gap-2 disabled:opacity-50" :disabled="scoring" @click="running ? stop() : start()">
                    <span class="grid size-20 place-items-center rounded-full glass-bubble-solid ring-8 ring-raised/70 transition group-hover:scale-105"><Icon :name="running ? 'stop' : 'mic'" :size="30" /></span>
                    <span class="text-sm font-semibold text-cream">{{ running ? t('studio.stop') : result ? t('breathhold.again') : t('breathhold.start') }}</span>
                </button>
            </div>
        </div>`,
    setup: () => ({ formatNumber, ENDING, ENDING_SHORT, effect }),
    computed: {
        lead: (vm) => vm.exercise.lead ?? LEAD,
        /** Where the hold is listened for: just before the lead ends; through a speaker, only once the drone has stopped (the mic would hear it). */
        listenFrom: (vm) => (vm.speaker && vm.exercise.drone ? vm.lead + SPEAKER_SETTLE : vm.lead - 0.3),
        max: (vm) => Math.min(GOAL_MAX + 5, vm.exercise.max ?? GOAL_MAX + 5),
        /** An earlier try's steady seconds: this visit's (`compare: { step }`), or your baseline (`compare: 'baseline'`). */
        before() {
            if (this.exercise.compare === 'baseline') return this.personal?.baseline ?? null;
            return this.exercise.compare ? this.earlier?.results?.[this.exercise.compare.step]?.steady ?? null : null;
        },
        /** Your goal: your baseline (kept, or this visit's first hold) plus the step's `goal.plus`, never over 15 s; none without a baseline. */
        goal() {
            if (!this.exercise.goal) return null;
            return holdGoal(this.personal?.baseline ?? this.before ?? null, this.exercise.goal.plus ?? 0);
        },
        held: (vm) => (vm.startedAt === null ? 0 : Math.max(0, (vm.quietSince ?? vm.position) - vm.startedAt)),
        shownSeconds: (vm) => Math.round(10 * (vm.result && !vm.running ? vm.result.steady ?? 0 : vm.held)) / 10,
        // With no goal the bar grows with you (it never shows a length to fill).
        scale: (vm) => Math.max(vm.goal ?? 0, vm.shownSeconds * 1.25, vm.before ?? 0, 4) * 1.1,
        /**
         * Game mode's super meter (docs/game-elements-plan.md, idea 16; agreed with courses-skills): the
         * fill stops at your goal (full at GOAL, never a reason to push past it), and the bar reads as
         * three segments to the goal (or to the scale's end with no goal). The aria numbers are unchanged.
         */
        meterSeconds: (vm) => (effect('hud') && vm.goal ? Math.min(vm.shownSeconds, vm.goal) : vm.shownSeconds),
        meterSegment: (vm) => (vm.goal ? (100 * Math.min(1, vm.goal / vm.scale)) / 3 : 100 / 3),
        sealed: (vm) => !!vm.result && !vm.running && vm.result.tail === 'steady' && (!vm.goal || vm.result.steady >= vm.goal),
        // "More steady seconds" only against a try this visit, never your baseline (an 8-second then-and-now would always look short).
        more: (vm) => (vm.result && vm.before && typeof vm.exercise.compare === 'object' && vm.result.steady - vm.before >= 0.5 ? Math.round(10 * (vm.result.steady - vm.before)) / 10 : 0),
        phase() {
            if (!this.running) return null;
            return this.position < this.lead || this.startedAt === null ? 'breathe' : 'hold';
        },
        phaseWords() {
            if (this.scoring) return t('course.scoring');
            if (this.phase === 'breathe') return t('breathhold.breathe');
            if (this.phase === 'hold') return t('breathhold.hold');
            return this.result ? '' : t('breathhold.ready');
        },
    },
    unmounted() {
        cancelAnimationFrame(this.frame);
        this.source?.stop();
        if (this.running) engine.stop().catch(() => {});
        [...engine.parts.keys()].filter((id) => id.startsWith('ex:')).forEach((id) => engine.removePart(id));
    },
    methods: {
        headphonesOn(never) {
            tabStorage.set('stackapella.headphones', '1');
            if (never) local.set('stackapella.headphones.never', '1');
            this.asking = false;
            this.start();
        },
        useSpeaker() {
            tabStorage.set('stackapella.speaker', '1');
            this.asking = false;
            this.start();
        },
        /** The drone, then your hold, recorded; it ends when you go quiet (or at the step's longest). */
        async start() {
            if (!listening()) {
                this.asking = true;
                return;
            }
            this.speaker = listening() === 'speaker';
            try {
                await unlock();
                if (!(await readyMic())) return;
            } catch (error) {
                showMicHelp(error, () => this.start());
                return;
            }
            this.source?.stop();
            const sr = engine.sampleRate;
            const bpm = 60;
            [...engine.parts.keys()].forEach((id) => engine.removePart(id));
            engine.projectId = null;
            // The drone: under the whole hold with headphones; through a speaker, only before you sing.
            if (this.exercise.drone) {
                const to = this.speaker ? this.lead : this.lead + this.max + 1;
                engine.setPart('ex:drone', renderPart([{ beat: 0, length: to, pitch: this.base + this.exercise.drone.step, vibrato: { cents: 0 } }], { bpm, sampleRate: sr }), sr, { volume: 0.4, pan: 0, audible: true, offsetSeconds: 0 });
            }
            // Earlier takes beneath this one (`under: { steps: [n, ...] }`, a chord of your own holds): never through a speaker.
            const unders = (this.exercise.under?.steps ?? []).map((n) => this.earlier?.takes?.[n]).filter(Boolean);
            if (unders.length && !this.speaker) {
                const longest = Math.max(...unders.map((u) => u.samples.length));
                const mixedTakes = new Float32Array(longest);
                for (const u of unders) for (let i = 0; i < u.samples.length; i++) mixedTakes[i] += u.samples[i] / unders.length;
                engine.setPart('ex:under', mixedTakes, unders[0].sampleRate, { volume: 0.8, pan: 0, audible: true, offsetSeconds: 0 });
            }
            Object.assign(this, { result: null, take: null, startedAt: null, quietSince: null, position: 0, running: true });
            this.began = performance.now();
            const latencyFrames = audio.latencyFrames ?? engine.estimatedLatencyFrames();
            engine.play({ fromSeconds: 0, bpm, record: true, latencyFrames, leadSeconds: leadSeconds() });
            const tick = () => {
                const position = engine.positionSeconds();
                if (position === null || !this.running) return;
                this.position = Math.max(0, position);
                this.hear();
                const quietFor = this.quietSince === null ? 0 : this.position - this.quietSince;
                const ended = (this.startedAt !== null && quietFor >= QUIET_ENDS) || this.position >= this.lead + this.max || (this.startedAt === null && this.position >= this.lead + WAIT_FOR_VOICE);
                if (ended) {
                    this.stop();
                    return;
                }
                this.frame = requestAnimationFrame(tick);
            };
            this.frame = requestAnimationFrame(tick);
        },
        /** One live reading: when the hold began, and how long it's been quiet since. */
        hear() {
            if (this.position < this.listenFrom) return;
            const frame = engine.inputFrame();
            const reading = frame ? trackFrame(frame, engine.sampleRate, { minClarity: LISTEN_CLARITY }) : null;
            if (reading?.hz) {
                if (this.startedAt === null) this.startedAt = this.position;
                this.quietSince = null;
            } else if (this.startedAt !== null && this.quietSince === null) this.quietSince = this.position;
        },
        async stop() {
            cancelAnimationFrame(this.frame);
            this.running = false;
            let take = null;
            try {
                take = await engine.stop();
            } catch (error) {
                console.warn('Stopped before the recording began', error);
            }
            this.$emit('practised', (performance.now() - this.began) / 1000);
            if (!take) return;
            this.scoring = true;
            try {
                const track = await trackInWorker(take.samples, take.sampleRate);
                const pitch = this.base + (this.exercise.step ?? 0);
                const cents = centsOf(track, { from: Math.max(0, this.listenFrom), to: track.hz.length / track.rate, midi: pitch });
                const measure = sustain(cents, track.rate, { goal: this.goal, generous: !!this.exercise.generous });
                this.take = { samples: take.samples, sampleRate: take.sampleRate };
                this.result = measure;
                // The first hold is your baseline: later goals grow from your own steady seconds.
                const personal = this.exercise.baseline && measure.percent !== null ? { baseline: measure.steady } : undefined;
                await this.keepOrCompare(measure);
                this.$emit('done', { percent: measure.percent, steady: measure.steady, tail: measure.tail, held: measure.held, sung: measure.percent === null ? [] : [pitch], insights: [], take: this.take, personal });
            } catch (error) {
                console.error('Scoring failed', error);
                this.result = { percent: null };
                this.$emit('done', { percent: null, sung: [] });
            } finally {
                this.scoring = false;
            }
        },
        /** How much of a hold was steady, as a whole percent (then and now compare this, not length). */
        share: (m) => formatNumber(Math.round((100 * (m.steady ?? 0)) / Math.max(0.1, m.held ?? 0))),
        /** Then and now: `keep` saves the first hold on this device (never synced); `thenAndNow` loads it. */
        async keepOrCompare(measure) {
            try {
                if (this.exercise.keep && measure.percent !== null) {
                    const key = `course:${this.exercise.keep}`;
                    if (!(await db.get('learning', key))) {
                        await db.putAudio(key, this.take.samples);
                        await db.put('learning', { sampleRate: this.take.sampleRate, steady: measure.steady, held: measure.held, tail: measure.tail, at: Date.now() }, key);
                    }
                }
                if (this.exercise.thenAndNow) {
                    const key = `course:${this.exercise.thenAndNow}`;
                    const kept = await db.get('learning', key);
                    const samples = kept ? await db.getAudio(key) : null;
                    this.thenKept = kept && samples ? { ...kept, samples } : null;
                }
            } catch (error) {
                console.error('Then and now not kept', error);
            }
        },
        hearThen() {
            if (this.playing) this.source?.stop();
            const source = engine.ctx.createBufferSource();
            source.buffer = engine.toBuffer(this.thenKept.samples, this.thenKept.sampleRate);
            source.connect(engine.playBus);
            source.onended = () => {
                this.playing = false;
                this.source = null;
            };
            source.start(engine.ctx.currentTime + 0.03);
            this.source = source;
            this.playing = true;
        },
        /** Hear yourself: the take as it was recorded, on the audio clock. */
        listenBack() {
            if (this.playing) {
                this.source?.stop();
                return;
            }
            const source = engine.ctx.createBufferSource();
            source.buffer = engine.toBuffer(this.take.samples, this.take.sampleRate);
            source.connect(engine.playBus);
            source.onended = () => {
                this.playing = false;
                this.source = null;
            };
            source.start(engine.ctx.currentTime + 0.03);
            this.source = source;
            this.playing = true;
        },
    },
};
