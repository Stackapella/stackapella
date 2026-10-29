// The trace lane (docs/plus-courses-build-plan.md, section 5): a Vibrato course step. The target
// scrolls toward you as a soft ribbon, a wave to ride or a flat line to hold, over a drone; your
// pitch is a line on it. Where you ride the ribbon it fills with your colour (missed stretches stay
// glass, never red), each peak is a gem that pops as you reach it, and even waves in a row count up.
// Hear it first (the guide sings the target), then sing it; afterwards, Hear yourself: your take
// played back with your line over the whole target, and what was measured (js/vibrato.js), plainly.
// Everything that sounds is scheduled on the audio clock by the engine; the screen only reads the
// position and the mic. Emits `done` ({ percent, tip, measure, parts, sung }) as the exercise does.

import { audio, engine, leadSeconds, unlock } from '../audio.js';
import { readyMic } from '../mic-check.js';
import { formatNumber, t, tk } from '../i18n.js';
import { trackFrame, trackInWorker } from '../pitchtrack.js';
import { STRICTNESS } from '../scoring.js';
import { renderPart } from '../synth.js';
import { token } from '../colors.js';
import { solidColor } from './gems.js';
import { mixColor } from '../glass.js';
import { Icon } from './icon.js';
import { showMicHelp } from '../mic-help.js';
import { LISTEN_CLARITY } from '../listen.js';
import { HeadphonesDialog } from './dialogs.js';
import { KeyControl } from './key-control.js';
import { local, session as tabStorage } from '../storage.js';
import { heardTime } from '../../engine/clock.js';
import { peaksOf, scoreWave, targetAt, targetPlan, UNEVEN } from '../vibrato.js';
import { PLAY_SCORERS } from '../lesson-scores.js';
import * as db from '../db.js';

const SPAN = 2.4; // seconds across the lane while singing
const NOW_AT = 0.3; // the "now" line, this far across
const HEIGHT = 220;
const COMBO_SHOWN = 3;
const GLOW_EVERY = 8; // the ribbon glows after this many even waves in a row
const POP_MS = 120;
const PEAK_NEAR = 0.07; // a reading this close to a peak (s) can pop it
const HIT_BINS = 30; // the ribbon's fill is kept in thirtieths of a second
const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

/** Headphones on (this visit, or "don't remind me"), the speaker chosen, or not asked yet (null). */
function listening() {
    if (tabStorage.get('stackapella.headphones') === '1' || local.get('stackapella.headphones.never') === '1') return 'headphones';
    return tabStorage.get('stackapella.speaker') === '1' ? 'speaker' : null;
}

/** The guide's notes for Hear it: each segment sung as its kind (a steered wave, straight, a bloom, a natural hold). */
export function guideNotes(plan, pitch) {
    const beat = plan.beatSeconds;
    return plan.segments.map((s) => {
        if (s.kind === 'path') return { beat: s.from / beat, length: s.seconds / beat, pitch, vibrato: { cents: 0 }, bend: s.points.map(([at, steps]) => [at, steps * 100]), voice: s.voice ?? null };
        const vibrato = s.kind === 'wave' ? { hz: s.hz, cents: s.cents, after: 0 }
            : s.kind === 'straight' ? { cents: 0 }
                : s.kind === 'bloom' ? { hz: 5.5, cents: 30, after: s.straight ?? 1.5, jitter: 0.2 } : { hz: 5.5, cents: 28, after: 0.4, jitter: 0.2 };
        return { beat: s.from / beat, length: s.seconds / beat, pitch, vibrato };
    });
}

// The take's words (en.json `wave`), named here so the words check sees every one.
const LINE = {
    straight: tk('wave.line.straight'), path: tk('wave.line.path'), bloom: tk('wave.line.bloom'), bloomNot: tk('wave.line.bloomNot'),
    freeVibrato: tk('wave.line.freeVibrato'), freeStraight: tk('wave.line.freeStraight'), tremble: tk('wave.line.free.tremble'), uneven: tk('wave.line.free.uneven'),
    noWave: tk('wave.line.noWave'), even: tk('wave.line.wave.even'), fair: tk('wave.line.wave.fair'), unevenWave: tk('wave.line.wave.uneven'),
};
const CUE = { higher: tk('wave.cue.higher'), lower: tk('wave.cue.lower') };
const TIP = { smaller: tk('wave.tip.smaller'), slower: tk('wave.tip.slower'), faster: tk('wave.tip.faster'), wave: tk('wave.tip.wave') };

/** A take's pitch line, small (Then and now): `[[seconds, cents], ...]` as an SVG path, 120 by 40. */
export function miniPath(trace) {
    const heard = (trace ?? []).filter(([, c]) => c !== null);
    if (heard.length < 2) return '';
    const t0 = heard[0][0];
    const span = Math.max(0.1, heard.at(-1)[0] - t0);
    const range = Math.max(60, ...heard.map(([, c]) => Math.abs(c)));
    return heard.map(([t, c], i) => `${i ? 'L' : 'M'}${((t - t0) / span * 120).toFixed(1)} ${(20 - (c / range) * 18).toFixed(1)}`).join(' ');
}

/** What the take's numbers say, as words: `{ key, values }` for one line, or null. */
export function measuredLine(part, total) {
    const m = part?.measure;
    if (!m || m.kind === 'unclear') return null;
    const rate = (hz) => formatNumber(Math.round(hz * 10) / 10, { maximumFractionDigits: 1 });
    const width = (c) => formatNumber(Math.max(5, Math.round(c / 5) * 5));
    const even = m.evenness === null ? null : m.evenness <= 0.1 ? 'even' : m.evenness <= UNEVEN ? 'fair' : 'uneven';
    if (part.kind === 'path') return { key: LINE.path, values: { percent: formatNumber(Math.round((part.inside ?? 0) * 100)) } };
    if (part.kind === 'straight') return { key: LINE.straight, values: { seconds: formatNumber(Math.round(part.straightSeconds * 10) / 10), total: formatNumber(Math.round(total * 10) / 10) } };
    if (part.kind === 'bloom') {
        const straightFor = formatNumber(Math.round((m.onset ?? m.seconds) * 10) / 10);
        return { key: part.bloomed ? LINE.bloom : LINE.bloomNot, values: { seconds: straightFor } };
    }
    if (part.kind === 'free') {
        if (m.kind === 'vibrato') return { key: LINE.freeVibrato, values: { rate: rate(m.hz), cents: width(m.width) } };
        if (m.kind === 'tremble' || m.kind === 'uneven') return { key: LINE[m.kind], values: {} };
        return { key: LINE.freeStraight, values: {} };
    }
    if (m.hz === null) return { key: LINE.noWave, values: {} };
    return { key: even === 'uneven' ? LINE.unevenWave : LINE[even], values: { rate: rate(m.hz), cents: width(m.width) } };
}

export const WaveLane = {
    components: { HeadphonesDialog, Icon, KeyControl },
    props: {
        exercise: { type: Object, required: true },
        base: { type: Number, required: true }, // the learner's comfortable note (MIDI)
        strictness: { type: String, default: 'relaxed' },
        color: { type: String, default: 'var(--color-part-4)' },
        musicKey: { type: String, default: null },
        canLower: { type: Boolean, default: true },
        canHigher: { type: Boolean, default: true },
        // This visit's earlier steps (views/learn.js `kept`): `exercise.under: { step }` plays that take beneath.
        earlier: { type: Object, default: () => ({ results: {}, takes: {} }) },
        personal: { type: Object, default: () => ({}) },
    },
    emits: ['done', 'practised', 'lower', 'higher'],
    data: () => ({ then: null, cue: null, width: 0, running: null, scoring: false, position: 0, combo: 0, result: null, take: null, trace: [], asking: false, speaker: listening() === 'speaker', playing: false, error: null }),
    template: `
        <div class="space-y-4" data-wave-lane>
            <div class="lane-stage relative overflow-hidden rounded-2xl border border-line bg-ink" :style="{ height: HEIGHT + 'px' }" role="img" :aria-label="label">
                <canvas ref="canvas" class="absolute inset-0 size-full" aria-hidden="true"></canvas>
                <p v-if="!running && !result" class="absolute start-3 top-3 rounded-full bg-raised px-3 py-1 text-xs font-bold text-cream" translate="no">{{ exercise.lyric ?? '' }}</p>
            </div>
            <p class="min-h-6 text-center text-sm font-semibold" :style="{ color: phase === 'sing' ? color : null }" :class="phase === 'sing' ? '' : 'text-muted'" aria-live="polite">{{ phaseWords }}</p>
            <!-- The lock cue (exercise.cue): which way to go, only while you're outside the ribbon. -->
            <p v-if="running === 'sing' && exercise.cue" class="min-h-6 text-center text-sm font-semibold text-cream" role="status" data-wave-cue>{{ cue ? t(CUE[cue]) : '' }}</p>
            <div v-if="running === 'sing' && combo >= COMBO_SHOWN && comboOn" class="flex justify-center"><span class="rounded-full bg-raised px-3 py-1 text-sm font-semibold text-cream" data-wave-combo>{{ t('wave.combo', { count: combo }) }}</span></div>

            <!-- Hear yourself: the take played back, and what was measured. -->
            <section v-if="result && !running" class="space-y-3 rounded-2xl border border-edge bg-panel p-4" data-wave-result>
                <div class="flex items-center gap-3">
                    <button v-if="take" type="button" class="grid size-11 shrink-0 place-items-center rounded-full glass-bubble-solid" :aria-label="playing ? t('studio.stop') : t('wave.hearYourself')" @click="listenBack"><Icon :name="playing ? 'stop' : 'play'" :size="18" /></button>
                    <div class="min-w-0">
                        <p class="font-bold text-cream">{{ result.percent === null ? t('course.notHeard') : t('wave.hearYourself') }}</p>
                        <p v-if="line" class="text-sm text-cream" data-wave-line>{{ t(line.key, line.values) }}</p>
                    </div>
                </div>
                <p v-if="result.tip && TIP[result.tip]" class="text-sm text-muted" data-wave-tip>{{ t(TIP[result.tip]) }}</p>
            </section>

            <!-- Then and now (exercise.thenAndNow): the first lesson's take, kept on this device, beside today's. -->
            <section v-if="exercise.thenAndNow && result && !running" class="space-y-3 rounded-2xl border border-edge bg-panel p-4" data-then-and-now>
                <h2 class="font-bold text-cream">{{ t('wave.then.title') }}</h2>
                <template v-if="then">
                    <div v-for="row in thenRows" :key="row.id" class="flex items-center gap-3">
                        <button type="button" class="grid size-10 shrink-0 place-items-center rounded-full glass-bubble-solid" :aria-label="playing === row.id ? t('studio.stop') : t('wave.then.hear', { which: row.name })" @click="hearOne(row.id)"><Icon :name="playing === row.id ? 'stop' : 'play'" :size="16" /></button>
                        <svg viewBox="0 0 120 40" class="h-10 w-28 shrink-0" aria-hidden="true" data-trace><path :d="row.path" fill="none" stroke="currentColor" stroke-width="2" :class="row.id === 'now' ? 'text-cream' : 'text-muted'" /></svg>
                        <p class="min-w-0 text-sm"><span class="block font-semibold text-cream">{{ row.name }}</span><span v-if="row.line" class="text-muted">{{ t(row.line.key, row.line.values) }}</span></p>
                    </div>
                </template>
                <p v-else class="text-sm text-muted">{{ t('wave.then.none') }}</p>
            </section>

            <p v-if="error" class="text-center text-sm text-bad" role="alert">{{ error }}</p>
            <p v-if="speaker" class="text-center text-sm text-muted">{{ t('course.speakerUnder') }}</p>
            <HeadphonesDialog v-if="asking" speaker-option :action="t('headphones.on')" @ok="headphonesOn" @speaker="useSpeaker" @close="asking = false" />
            <div class="flex flex-col items-center gap-5">
                <KeyControl v-if="musicKey && !running && !scoring" class="w-full" :music-key="musicKey" :can-lower="canLower" :can-higher="canHigher" @lower="$emit('lower')" @higher="$emit('higher')" />
                <div class="flex items-end justify-center gap-6">
                    <button v-if="!running" type="button" class="btn-secondary" :disabled="scoring" @click="start('listen')"><Icon name="play" :size="16" /> {{ t('wave.hear') }}</button>
                    <button v-if="running !== 'listen'" type="button" class="group flex flex-col items-center gap-2 disabled:opacity-50" :disabled="scoring" @click="running ? stop() : start('sing')">
                        <span class="grid size-20 place-items-center rounded-full glass-bubble-solid ring-8 ring-raised/70 transition group-hover:scale-105"><Icon :name="running ? 'stop' : 'mic'" :size="30" /></span>
                        <span class="text-sm font-semibold text-cream">{{ running ? t('studio.stop') : result ? t('wave.again') : t('wave.sing') }}</span>
                    </button>
                    <button v-else type="button" class="btn-secondary" @click="stop()"><Icon name="stop" :size="16" /> {{ t('studio.stop') }}</button>
                </div>
            </div>
        </div>`,
    setup: () => ({ HEIGHT, COMBO_SHOWN, TIP, CUE }),
    computed: {
        plan: (vm) => targetPlan(vm.exercise),
        pitch: (vm) => vm.base + (vm.exercise.step ?? 0),
        peaks: (vm) => peaksOf(vm.plan),
        /** The combo counts steered waves only: where the wave should come by itself it would reward forcing. */
        comboOn: (vm) => vm.exercise.combo !== false && vm.plan.segments.some((s) => s.kind === 'wave'),
        /** The cents shown either side of the note: room for the widest wave and its band. */
        range: (vm) => Math.max(100, ...vm.plan.segments.map((s) => ((s.kind === 'path' ? Math.max(...s.points.map(([, steps]) => Math.abs(steps * 100))) : s.cents ?? 0) + s.band) * 1.25)),
        phase() {
            if (!this.running) return null;
            if (this.running === 'listen') return 'listen';
            return this.position < this.plan.start ? 'lead' : 'sing';
        },
        phaseWords() {
            if (this.scoring) return t('course.scoring');
            if (this.phase === 'listen') return t('wave.listening');
            if (this.phase === 'lead') return t('wave.lead');
            if (this.phase === 'sing') return this.targetNow?.fade < 1 ? t('wave.keepGoing') : t('course.phase.turn');
            return '';
        },
        targetNow: (vm) => targetAt(vm.plan, vm.position),
        /** What was measured, in words: the course's own line when its scorer gives one, else the lane's. */
        line: (vm) => (vm.result ? vm.result.ownLine ?? measuredLine(vm.result.parts?.at(-1), vm.plan.end - vm.plan.start) : null),
        label: (vm) => t('wave.label', { seconds: formatNumber(Math.round(vm.plan.end - vm.plan.start)) }),
        /** Then and now's two rows: the kept first take and today's, each with its line drawn small. */
        thenRows() {
            if (!this.then || !this.result) return [];
            return [
                { id: 'then', name: t('wave.then.then'), line: this.then.line, path: miniPath(this.then.trace) },
                { id: 'now', name: t('wave.then.now'), line: this.line, path: miniPath(this.trace.map((r) => [r.t, r.cents])) },
            ];
        },
    },
    watch: {
        result() {
            this.$nextTick(() => this.draw());
        },
    },
    mounted() {
        const measure = () => {
            this.width = this.$el?.clientWidth ?? 0;
            this.draw();
        };
        this.resize = new ResizeObserver(measure);
        this.resize.observe(this.$el);
        // The canvas takes its colours from the theme: drawn again when the theme changes.
        this.themed = new MutationObserver(() => this.draw());
        this.themed.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
        measure();
    },
    unmounted() {
        this.resize?.disconnect();
        this.themed?.disconnect();
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
            this.start(this.wanted);
        },
        useSpeaker() {
            tabStorage.set('stackapella.speaker', '1');
            this.asking = false;
            this.start(this.wanted);
        },
        /** Hear it ('listen'): the guide sings the target over the drone. Sing ('sing'): your take, recorded. */
        async start(mode) {
            this.error = null;
            this.wanted = mode;
            if (mode === 'sing' && !listening()) {
                this.asking = true;
                return;
            }
            this.speaker = listening() === 'speaker';
            try {
                await unlock();
                if (mode === 'sing' && !(await readyMic())) return;
            } catch (error) {
                showMicHelp(error, () => this.start(mode));
                return;
            }
            this.source?.stop();
            const sr = engine.sampleRate;
            const bpm = this.exercise.tempo ?? 60;
            const beat = this.plan.beatSeconds;
            [...engine.parts.keys()].forEach((id) => engine.removePart(id));
            engine.projectId = null;
            // The drone throughout; through a speaker, only before your turn (the mic must hear only you).
            const droneTo = mode === 'sing' && this.speaker ? this.plan.start : this.plan.end + 0.3;
            // A drone is dead straight (no guide vibrato): it's the floor you tune against.
            if (this.exercise.drone) engine.setPart('ex:drone', renderPart([{ beat: 0, length: droneTo / beat, pitch: this.base + this.exercise.drone.step, vibrato: { cents: 0 } }], { bpm, sampleRate: sr }), sr, { volume: 0.5, pan: 0, audible: true, offsetSeconds: 0 });
            // An earlier step's take beneath this one (your own voice to sing with); never through a speaker while you sing.
            const under = this.exercise.under ? this.earlier?.takes?.[this.exercise.under.step] : null;
            if (under && !(mode === 'sing' && this.speaker)) engine.setPart('ex:under', under.samples, under.sampleRate, { volume: 0.7, pan: 0, audible: true, offsetSeconds: 0 });
            if (mode === 'listen') engine.setPart('ex:guide', renderPart(guideNotes(this.plan, this.pitch), { bpm, sampleRate: sr }), sr, { volume: 0.9, pan: 0, audible: true, offsetSeconds: 0 });
            this.result = mode === 'sing' ? null : this.result;
            this.take = mode === 'sing' ? null : this.take;
            this.trace = [];
            this.hits = new Set();
            this.popped = new Map();
            this.missed = new Set();
            this.combo = 0;
            this.bestCombo = 0;
            this.glowUntil = 0;
            this.near = 0;
            this.cue = null;
            this.running = mode;
            this.startedAt = performance.now();
            const latencyFrames = audio.latencyFrames ?? engine.estimatedLatencyFrames();
            // The live line's lag, taken off: the mic frame's middle, and the way in (half the round trip).
            this.lag = 1024 / sr + latencyFrames / sr / 2;
            engine.play({ fromSeconds: 0, bpm, record: mode === 'sing', latencyFrames, leadSeconds: leadSeconds() });
            const tick = () => {
                const position = engine.positionSeconds();
                if (position === null || !this.running) return;
                this.position = Math.max(0, position);
                if (this.position >= this.plan.end + 0.3) {
                    this.stop();
                    return;
                }
                if (this.running === 'sing') this.hear();
                this.draw();
                this.frame = requestAnimationFrame(tick);
            };
            this.frame = requestAnimationFrame(tick);
        },
        /** One live reading: kept for the line, the ribbon filled where it's inside, a peak popped. */
        hear() {
            const frame = engine.inputFrame();
            const reading = frame ? trackFrame(frame, engine.sampleRate, { minClarity: LISTEN_CLARITY }) : null;
            const at = this.position - this.lag;
            if (!reading?.hz || at < this.plan.start - 0.2) {
                this.trace.push({ t: at, cents: null });
                return;
            }
            const raw = 1200 * Math.log2(reading.hz / (440 * 2 ** ((this.pitch - 69) / 12)));
            const cents = raw - 1200 * Math.round(raw / 1200); // in the note's octave
            this.trace.push({ t: at, cents });
            const target = targetAt(this.plan, at);
            if (!target) return;
            const inside = Math.abs(cents - target.cents) <= target.band;
            if (inside) this.hits.add(Math.round(at * HIT_BINS));
            // The lock cue: how near (the ribbon's glow, graded) and which way when outside it.
            if (this.exercise.cue) {
                const off = cents - target.cents;
                this.near = Math.max(0, 1 - Math.abs(off) / (target.band * 3));
                this.cue = inside ? null : off < 0 ? 'higher' : 'lower';
            }
            this.peaks.forEach((p, i) => {
                if (this.popped.has(i) || this.missed.has(i)) return;
                if (inside && Math.abs(p.t - at) <= PEAK_NEAR) {
                    this.popped.set(i, performance.now());
                    this.combo++;
                    this.bestCombo = Math.max(this.bestCombo, this.combo);
                    if (this.comboOn && this.combo % GLOW_EVERY === 0) this.glowUntil = performance.now() + 500;
                } else if (at - p.t > PEAK_NEAR) {
                    this.missed.add(i);
                    this.combo = 0;
                }
            });
        },
        async stop() {
            cancelAnimationFrame(this.frame);
            const mode = this.running;
            const finished = this.position >= this.plan.end;
            this.running = null;
            let take = null;
            try {
                take = await engine.stop();
            } catch (error) {
                console.warn('Stopped before the recording began', error);
            }
            this.draw();
            if (mode !== 'sing') return;
            this.$emit('practised', (performance.now() - this.startedAt) / 1000);
            if (!take || !finished) return;
            this.scoring = true;
            try {
                const track = await trackInWorker(take.samples, take.sampleRate);
                const tolerance = STRICTNESS[this.strictness] ?? STRICTNESS.relaxed;
                const score = scoreWave(track, this.plan, { midi: this.pitch, tolerance, generous: !!this.exercise.generous });
                // A course's own measure (js/lesson-scores.js, e.g. Perfect your pitch's 'lock') gives the stars when the step names one.
                const scorer = PLAY_SCORERS[this.exercise.score];
                if (scorer) {
                    const beat = this.plan.beatSeconds;
                    const target = this.plan.segments.map((s) => ({ start: s.from, end: s.to, pitch: this.pitch }));
                    const notes = this.plan.segments.map((s) => ({ beat: s.from / beat, length: s.seconds / beat, pitch: this.pitch, step: this.exercise.step ?? 0 }));
                    const own = scorer({ track, dots: [], target, notes, beatSeconds: beat, strictness: this.strictness, exercise: { ...this.exercise, notes } });
                    score.percent = own.percent;
                    score.tip = own.tip ?? score.tip;
                    score.personal = own.personal ?? null; // a value the course keeps (a flip note, a baseline)
                    score.ownLine = own.line ?? null; // the course's own words for what it measured
                    score.label = own.label ?? null; // and its name for the percent (the result's heading)
                }
                // The whole take's line, for Hear yourself (in the note's octave).
                const ref = 440 * 2 ** ((this.pitch - 69) / 12);
                this.trace = Array.from(track.hz, (hz, i) => ({ t: i / track.rate, cents: hz > 0 ? ((c) => c - 1200 * Math.round(c / 1200))(1200 * Math.log2(hz / ref)) : null })).filter((r) => r.t >= this.plan.start - 0.3 && r.t <= this.plan.end + 0.2);
                this.take = { samples: take.samples, sampleRate: take.sampleRate };
                // The ribbon's fill afterwards: where the whole take's line was inside it.
                this.hits = new Set();
                for (const r of this.trace) {
                    const target = r.cents === null ? null : targetAt(this.plan, r.t);
                    if (target && Math.abs(r.cents - target.cents) <= target.band) this.hits.add(Math.round(r.t * HIT_BINS));
                }
                this.result = score;
                await this.keepOrCompare(score);
                this.$emit('done', { percent: score.percent, tip: score.tip, measure: score.measure, parts: score.parts, sung: score.percent === null ? [] : [this.pitch], insights: [], take: this.take, personal: score.personal ?? null, line: score.ownLine ?? null, label: score.label ?? null, minStars: this.exercise.generous && score.percent !== null ? 2 : null });
            } catch (error) {
                console.error('Scoring failed', error);
                this.$emit('done', { percent: null, sung: [] });
            } finally {
                this.scoring = false;
            }
        },
        /**
         * Then and now: a step with `keep` saves its first take on this device (never synced: the
         * audio stays here), and a step with `thenAndNow` loads it to show beside today's.
         */
        async keepOrCompare(score) {
            try {
                if (this.exercise.keep) {
                    const key = `course:${this.exercise.keep}`;
                    if (!(await db.get('learning', key))) {
                        const line = measuredLine(score.parts?.at(-1), this.plan.end - this.plan.start);
                        await db.putAudio(key, this.take.samples);
                        await db.put('learning', { sampleRate: this.take.sampleRate, line, trace: this.trace.map((r) => [Math.round(r.t * 100) / 100, r.cents === null ? null : Math.round(r.cents)]), at: Date.now() }, key);
                    }
                }
                if (this.exercise.thenAndNow) {
                    const key = `course:${this.exercise.thenAndNow}`;
                    const kept = await db.get('learning', key);
                    const samples = kept ? await db.getAudio(key) : null;
                    this.then = kept && samples ? { ...kept, samples } : null;
                }
            } catch (error) {
                console.error('Then and now not kept', error);
            }
        },
        /** One take of Then and now, on the audio clock; tapping the one playing stops it. */
        hearOne(which) {
            const was = this.playing;
            this.source?.stop();
            if (was === which) return;
            const take = which === 'then' ? { samples: this.then.samples, sampleRate: this.then.sampleRate } : this.take;
            const source = engine.ctx.createBufferSource();
            source.buffer = engine.toBuffer(take.samples, take.sampleRate);
            source.connect(engine.playBus);
            source.onended = () => {
                if (this.source === source) {
                    this.source = null;
                    this.playing = false;
                }
            };
            source.start(engine.ctx.currentTime + 0.03);
            this.source = source;
            this.playing = which;
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
                this.position = 0;
                this.draw();
            };
            const at = engine.ctx.currentTime + 0.03;
            source.start(at);
            this.source = source;
            this.playing = true;
            // The playhead over the whole target follows the audio clock (it schedules nothing).
            const tick = () => {
                if (!this.playing) return;
                this.position = Math.max(0, heardTime(engine.ctx) - at);
                this.draw();
                requestAnimationFrame(tick);
            };
            tick();
        },
        // ---- Drawing ----
        /** The lane: the ribbon, its gems, your line, and the "now" line (or the playhead, hearing yourself). */
        draw() {
            const canvas = this.$refs.canvas;
            if (!canvas || !this.width) return;
            const dpr = window.devicePixelRatio || 1;
            const w = this.width;
            const h = HEIGHT;
            if (canvas.width !== Math.round(w * dpr)) {
                canvas.width = Math.round(w * dpr);
                canvas.height = Math.round(h * dpr);
            }
            const g = canvas.getContext('2d');
            g.setTransform(dpr, 0, 0, dpr, 0, 0);
            g.clearRect(0, 0, w, h);
            // Singing (or hearing it), the lane scrolls; before and after, it shows the whole target.
            const whole = !this.running;
            const t0 = whole ? this.plan.start - 0.3 : this.position - SPAN * NOW_AT;
            const t1 = whole ? this.plan.end + 0.2 : t0 + SPAN;
            const x = (t) => ((t - t0) / (t1 - t0)) * w;
            const y = (c) => h / 2 - (c / this.range) * (h / 2 - 14);
            const colour = solidColor(this.color);
            const glass = mixColor(colour, token('raised', '#2a2a2a'), 0.78);
            const now = performance.now();
            // The note's centre, faint.
            g.strokeStyle = token('line', '#444');
            g.lineWidth = 1;
            g.beginPath();
            g.moveTo(0, h / 2);
            g.lineTo(w, h / 2);
            g.stroke();
            // The ribbon, a slice at a time: glass, or your colour where you rode it; faded as the ghost wave goes.
            const step = (t1 - t0) / Math.max(60, Math.round(w / 3));
            const glow = now < (this.glowUntil ?? 0) && !reducedMotion();
            // The lock cue's glow: brighter the nearer you are (no animation: it follows your voice).
            const cueGlow = this.exercise.cue && this.running === 'sing' ? 18 * (this.near ?? 0) : 0;
            for (let t = Math.max(t0, this.plan.start); t < Math.min(t1, this.plan.end); t += step) {
                const a = targetAt(this.plan, t);
                const b = targetAt(this.plan, Math.min(t + step, this.plan.end - 1e-6));
                if (!a || !b) continue;
                const ridden = this.hits?.has(Math.round(t * HIT_BINS));
                g.globalAlpha = 0.25 + 0.75 * a.fade;
                g.fillStyle = ridden ? colour : glass;
                g.shadowBlur = glow ? 14 : cueGlow;
                g.shadowColor = colour;
                // Each slice a hair wider than its step, so no seams show between them.
                g.beginPath();
                g.moveTo(x(t) - 0.4, y(a.cents + a.band));
                g.lineTo(x(t + step) + 0.4, y(b.cents + b.band));
                g.lineTo(x(t + step) + 0.4, y(b.cents - b.band));
                g.lineTo(x(t) - 0.4, y(a.cents - a.band));
                g.closePath();
                g.fill();
            }
            g.globalAlpha = 1;
            g.shadowBlur = 0;
            // The peaks as gems: popped ones filled, a quick swell as they pop.
            this.peaks.forEach((p, i) => {
                if (p.t < t0 || p.t > t1) return;
                const poppedAt = this.popped?.get(i);
                const swell = poppedAt && !reducedMotion() ? 1 + 0.15 * Math.max(0, 1 - (now - poppedAt) / POP_MS) : 1;
                g.beginPath();
                g.arc(x(p.t), y(p.cents), 5.5 * swell, 0, Math.PI * 2);
                g.fillStyle = poppedAt ? colour : token('ink', '#111');
                g.strokeStyle = poppedAt ? token('cream', '#fff') : glass;
                g.lineWidth = 2;
                g.fill();
                g.stroke();
            });
            // Your line.
            g.strokeStyle = token('cream', '#fff');
            g.lineWidth = 2.5;
            g.lineJoin = 'round';
            g.beginPath();
            let drawing = false;
            let last = null;
            for (const r of this.trace) {
                if (r.t < t0 || r.t > t1) continue;
                if (r.cents === null || (last !== null && r.t - last > 0.08)) {
                    drawing = false;
                    if (r.cents === null) continue;
                }
                const cy = y(Math.max(-this.range, Math.min(this.range, r.cents)));
                if (drawing) g.lineTo(x(r.t), cy);
                else g.moveTo(x(r.t), cy);
                drawing = true;
                last = r.t;
            }
            g.stroke();
            // Now, or the playhead hearing yourself.
            if (this.running || this.playing) {
                g.fillStyle = this.phase === 'sing' || this.playing ? colour : token('cream', '#fff');
                g.fillRect(x(this.position) - 1.5, 0, 3, h);
            }
        },
    },
};
