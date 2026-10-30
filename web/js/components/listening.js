// Listening for your voice, the same everywhere the app listens for a pitch (js/listening.js):
// - PitchBar: your pitch as a marker gliding along a bar, wide (the whole human range) until it
//   settles, then zoomed to the octave around it, with a ring that fills as you hold the note.
// - NoteFinder: "Find your note" hands-free (Hillel: no "Start listening"; it listens the moment
//   the step opens, and measuring starts when a voice is heard). The mic check the first time
//   (js/mic-check.js), then Listening (a ring that breathes with the mic, and the level meter with
//   its voice line), Hearing you, Got it. It listens until it hears a held note, nudging gently
//   when nothing comes; at the end the note, big, with Play it and what it means.

import { audio, chooseMic, currentMicId, engine, micChoices, openMic } from '../audio.js';
import { checkState, looksLikeInterface, micNeedsCheck, rememberMic, stepVoice, voiceWatch } from '../mic-check.js';
import { hearNotes } from '../creator-audio.js';
import { midiName } from '../dsp.js';
import { t, tk } from '../i18n.js';
import { HOLD_SECONDS, hear, LISTEN_CLARITY, listener, trouble } from '../listen.js';
import { across, easeWindow, partsFor, targetWindow, ticks, WIDE } from '../listening.js';
import { pitchDisplay, stepDisplay } from '../pitch-display.js';
import { showMicHelp } from '../mic-help.js';
import { trackFrame } from '../pitchtrack.js';
import { playSound } from '../sounds.js';
import { Icon } from './icon.js';
import { LevelMeter, LevelSegments } from './level-meter.js';
import { micError, MicPicker } from './mic.js';

const still = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
const NUDGES = { quiet: tk('listening.nudge.quiet'), silent: tk('listening.nudge.silent'), unsteady: tk('listening.nudge.unsteady') };
const NUDGE_SECONDS = 6; // nothing held this long: a gentle word
const SPEAK_AFTER_MS = 800; // a phase said to a screen reader once it has held this long (a timer for words, never audio)
const HELD_KEEP_SECONDS = 3; // the last of what the mic heard, kept in memory for the chord moment (never saved)
const HELD_SECONDS = 2; // what the chord moment plays back: the end of the held note

/** The bar: `pitch` (MIDI, smoothed) or null, in the window `view` ({ lo, hi }), `hold` 0 to 1. */
export const PitchBar = {
    props: { plain: Boolean, pitch: { type: Number, default: null }, alpha: { type: Number, default: 1 }, view: { type: Object, default: () => ({ ...WIDE }) }, hold: { type: Number, default: 0 }, color: { type: String, default: 'var(--color-part-4)' }, label: String },
    template: `
        <div class="relative h-24 w-full select-none" role="img" :aria-label="label">
            <div class="absolute inset-x-0 top-10 h-3 rounded-full bg-raised"></div>
            <div v-for="tick in marks" :key="tick.midi" class="absolute top-7 flex -translate-x-1/2 flex-col items-center" :style="{ left: tick.at * 100 + '%' }" aria-hidden="true">
                <span class="w-px rounded-full" :class="tick.major ? 'h-9 bg-edge' : 'h-5 translate-y-2 bg-line'"></span>
                <span v-if="tick.label && !wideAndPlain" class="mt-1 tabular-nums text-[11px] text-muted" translate="no">{{ tick.label }}</span>
            </div>
            <!-- Plain (the note finder), while it's wide: Lower and Higher at the ends, not C2 to C6 (onboarding review 2, 14).
                 Zoomed in on a held note, the nearby notes are named: that's your note, said. -->
            <div v-if="wideAndPlain" class="absolute inset-x-0 top-16 flex justify-between text-xs text-muted" aria-hidden="true"><span>{{ t('listening.lower') }}</span><span>{{ t('listening.higher') }}</span></div>
            <div v-if="pitch !== null" class="absolute top-[2.875rem] -translate-x-1/2 -translate-y-1/2" :style="{ left: across(pitch, view) * 100 + '%', opacity: alpha }" aria-hidden="true">
                <svg width="44" height="44" viewBox="0 0 44 44" class="block">
                    <circle cx="22" cy="22" r="19" fill="none" stroke="currentColor" stroke-width="3" class="text-line" />
                    <circle cx="22" cy="22" r="19" fill="none" :stroke="color" stroke-width="3" stroke-linecap="round" :stroke-dasharray="119.4" :stroke-dashoffset="119.4 * (1 - hold)" transform="rotate(-90 22 22)" />
                    <circle cx="22" cy="22" r="11" :fill="color" />
                </svg>
            </div>
        </div>`,
    setup: () => ({ across }),
    computed: {
        marks() {
            return ticks(this.view);
        },
        /** The wide view of a plain bar: words at its ends instead of note names. */
        wideAndPlain() {
            return this.plain && this.view.hi - this.view.lo > 24;
        },
    },
};

/**
 * Find your note, hands-free: emits `found` (MIDI) once a note is held, `skip` for Skip for now.
 * It starts by itself. Only when the page hasn't been tapped yet (a reload straight into the step)
 * does it wait for a tap anywhere: browsers start no audio before one.
 */
export const NoteFinder = {
    components: { Icon, LevelMeter, LevelSegments, MicPicker, PitchBar },
    props: { color: { type: String, default: 'var(--color-part-4)' } },
    emits: ['found', 'skip', 'no-sound'],
    data: () => ({ phase: 'idle', shown: null, markerAlpha: 1, view: { ...WIDE }, hold: 0, level: 0, db: null, nudge: null, why: null, found: null, showMic: false, switching: false, checking: false, micWorks: false, spoken: '' }),
    setup: () => ({ HOLD_SECONDS, micChoices, currentMicId }),
    template: `
        <div class="space-y-5" data-listening :data-phase="phase">
            <div class="relative overflow-hidden rounded-3xl border border-line bg-panel p-6">
                <!-- No sound, or very little (Hillel's Scarlett mockup): what's wrong, the level, the mic, what to check, Try again. It keeps listening underneath. -->
                <div v-if="noSound" class="flex flex-col gap-5" data-no-sound :data-why="why">
                    <p class="flex items-center gap-2.5 font-bold text-cream" role="status"><span class="size-2.5 shrink-0 rounded-full bg-warn" aria-hidden="true"></span>{{ why === 'silent' ? t('micCheck.noSound', { name: micName }) : t('micCheck.quietly', { name: micName }) }}</p>
                    <LevelSegments :db="db" />
                    <label class="block text-sm text-muted">{{ t('micCheck.microphone') }}
                        <select class="input mt-1" :value="currentMicId()" :disabled="switching" @change="pickMic($event.target.value)">
                            <option v-for="m in micChoices()" :key="m.deviceId" :value="m.deviceId" translate="no">{{ m.systemDefault ? t('mic.systemDefaultNamed', { name: m.label }) : m.label }}</option>
                        </select>
                    </label>
                    <div class="space-y-3 border-t border-line pt-5">
                        <p class="font-bold text-cream">{{ t('micCheck.checkTitle') }}</p>
                        <ol class="space-y-2.5">
                            <li v-for="(item, i) in checks" :key="i" class="flex items-start gap-3 text-sm text-cream"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-raised text-xs font-bold tabular-nums" aria-hidden="true">{{ i + 1 }}</span><span class="pt-0.5">{{ item }}</span></li>
                        </ol>
                    </div>
                    <button type="button" class="btn-secondary self-start" @click="again">{{ t('common.tryAgain') }}</button>
                </div>
                <div v-else-if="phase !== 'found'" class="flex flex-col items-center gap-4 text-center">
                    <div class="relative grid size-20 place-items-center" aria-hidden="true">
                        <span class="absolute inset-0 rounded-full transition-transform duration-150" :style="{ background: color, opacity: 0.18, transform: 'scale(' + (0.7 + level * 0.6) + ')' }"></span>
                        <span v-if="phase === 'listening'" class="listen-breathe absolute inset-2 rounded-full border-2" :style="{ borderColor: color }"></span>
                        <span class="relative grid size-12 place-items-center rounded-full text-ink" :style="{ background: color }"><Icon name="mic" :size="22" /></span>
                    </div>
                    <p class="text-lg font-bold text-cream" data-status>{{ status }}</p>
                    <!-- Said to a screen reader once a phase has held a moment: a wavering voice flips between
                         Hearing and Holding many times a second, and saying each would drown the lesson. -->
                    <p class="sr-only" aria-live="polite">{{ spoken }}</p>
                    <p v-if="nudge" class="text-sm text-muted" role="status">{{ t(nudge) }}</p>
                    <!-- The first voice on a new mic is the mic check too: one hum, not two asks. -->
                    <p v-else-if="micWorks" class="flex items-center gap-2 text-sm text-good" role="status" data-mic-works><Icon name="check" :size="16" />{{ t('micCheck.gotYou') }}</p>
                    <PitchBar :pitch="shown" :alpha="markerAlpha" :view="view" :hold="hold" :color="color" :label="barLabel" plain />
                    <div v-if="listeningNow" class="flex w-full max-w-sm items-center gap-3"><span class="shrink-0 text-xs text-muted">{{ t('listening.micLevel') }}</span><LevelMeter :db="db" /></div>
                    <button v-if="phase === 'blocked'" type="button" class="btn-secondary" @click="start"><Icon name="mic" :size="18" /> {{ t('listening.micAgain') }}</button>
                </div>
                <div v-else class="flex flex-col items-center gap-3 text-center">
                    <!-- Said once as one line (the note and what it means), not with the buttons under it. -->
                    <p class="sr-only" role="status">{{ t('listening.yourNote') }}: {{ noteName(found) }}. {{ t(partsFor(found)) }}</p>
                    <p class="text-sm font-bold uppercase tracking-[0.14em] text-muted" aria-hidden="true">{{ t('listening.yourNote') }}</p>
                    <p class="text-6xl font-extrabold tracking-tight" :style="{ color }" translate="no" aria-hidden="true" data-note>{{ noteName(found) }}</p>
                    <p class="max-w-sm text-cream" aria-hidden="true">{{ t(partsFor(found)) }}</p>
                    <div class="mt-2 flex flex-wrap justify-center gap-3">
                        <button type="button" class="btn-secondary" @click="playIt"><Icon name="play" :size="16" /> {{ t('listening.playIt') }}</button>
                        <button type="button" class="btn-ghost" @click="again">{{ t('listening.again') }}</button>
                    </div>
                </div>
            </div>
            <div v-if="phase !== 'found'" class="flex flex-wrap items-center justify-center gap-4 text-sm">
                <button v-if="listeningNow" type="button" class="link-quiet min-h-11" @click="again">{{ t('listening.startOver') }}</button>
                <button v-if="listeningNow && !micShown && !noSound" type="button" class="link-quiet min-h-11" @click="showMic = true">{{ t('listening.changeMic') }}</button>
            </div>
            <MicPicker v-if="listeningNow && micShown && !noSound" />
        </div>`,
    computed: {
        status() {
            return {
                idle: t('listening.starting'),
                waiting: t('listening.tapToStart'),
                blocked: t('listening.noMic'),
                starting: t('listening.starting'),
                listening: t('listening.listening'),
                hearing: t('listening.hearing'),
                holding: t('listening.holding'),
            }[this.phase];
        },
        listeningNow: (vm) => ['listening', 'hearing', 'holding'].includes(vm.phase),
        /** The mic picker: on asking, or when nothing is heard (the wrong mic is the usual reason). */
        micShown() {
            return this.showMic;
        },
        /** Nothing heard for a while, or only very quietly: the no-sound card. */
        noSound: (vm) => vm.listeningNow && (vm.why === 'silent' || vm.why === 'quiet'),
        micName: () => audio.mic?.label || t('micHelp.yourMic'),
        /** Things to check: an interface's inputs, gain and 48V; otherwise a computer's or a headset's mic. */
        checks() {
            if (looksLikeInterface(audio.mic?.label)) return [t('micCheck.checkInputWeb'), t('micCheck.checkGain', { number: 1 }), t('micCheck.checkPhantom')];
            return [t('micCheck.checkRightMic'), t('micCheck.checkMuted'), t('micCheck.checkLevel')];
        },
        barLabel() {
            return this.shown === null ? t('listening.barEmpty') : t('listening.barAt', { note: midiName(this.shown) });
        },
    },
    watch: {
        noSound(now) {
            this.$emit('no-sound', now);
        },
        status: {
            immediate: true,
            handler(now) {
                clearTimeout(this.speakTimer);
                this.speakTimer = setTimeout(() => (this.spoken = now ?? ''), SPEAK_AFTER_MS);
            },
        },
    },
    mounted() {
        // The tap that brought the person here (Continue) lets the audio start: listen at once.
        // Without one (a reload into this step), the first tap anywhere starts it.
        if (engine.unlocked || (navigator.userActivation?.hasBeenActive ?? true)) {
            this.start();
            return;
        }
        this.phase = 'waiting';
        this.onTap = () => {
            this.stopWaiting();
            this.start();
        };
        document.addEventListener('pointerdown', this.onTap, { once: true });
        document.addEventListener('keydown', this.onTap, { once: true });
    },
    unmounted() {
        clearTimeout(this.speakTimer);
        this.left = true;
        this.stopWaiting();
        this.halt();
    },
    methods: {
        partsFor,
        noteName: (midi) => midiName(Math.round(midi)),
        stopWaiting() {
            if (!this.onTap) return;
            document.removeEventListener('pointerdown', this.onTap);
            document.removeEventListener('keydown', this.onTap);
            this.onTap = null;
        },
        async start() {
            this.phase = 'starting';
            try {
                if (!(await openMic())) {
                    this.phase = 'blocked';
                    return;
                }
            } catch (error) {
                this.phase = 'blocked';
                showMicHelp(error, () => this.start());
                return;
            }
            if (this.left) return; // the step was left while the mic opened
            this.listen();
        },
        listen() {
            let state = listener();
            let last = performance.now();
            let display = pitchDisplay();
            // The first time on this device (or with new mics), the first voice heard while finding
            // the note is the mic check as well: one hum does both (docs/onboarding-review.md, step 2).
            // Nothing heard: the no-sound card, with the level, the picker and what to check.
            let voice = voiceWatch();
            this.checking = micNeedsCheck();
            this.micWorks = false;
            this.phase = 'listening';
            this.keepHeld();
            this.nudge = null;
            this.shown = null;
            this.hold = 0;
            this.view = { ...WIDE };
            const tick = () => {
                const now = performance.now();
                const dt = Math.min(0.1, (now - last) / 1000);
                last = now;
                // A suspended context reads silence: nothing counts toward "can't hear" until it runs.
                const frame = engine.ctx?.state === 'running' ? engine.inputFrame() : null;
                let rang = false; // the mic-heard sound, once a tick
                if (frame) {
                    const reading = trackFrame(frame, engine.sampleRate, { minClarity: LISTEN_CLARITY });
                    state = hear(state, reading, dt);
                    this.level = Math.max(0, Math.min(1, (reading.loudness + 60) / 45));
                    this.db = reading.loudness;
                    // The marker: the shared display (it holds, then fades, through a breath).
                    display = stepDisplay(display, { midi: reading.hz > 0 ? 12 * Math.log2(reading.hz / 440) + 69 : null, dt, mode: 'hold' });
                    this.shown = display.shown;
                    this.markerAlpha = display.alpha;
                    if (this.checking) {
                        voice = stepVoice(voice, reading.loudness, dt);
                        if (checkState(voice) === 'ok') {
                            this.checking = false;
                            this.micWorks = true;
                            rememberMic();
                            playSound('micReady');
                            rang = true;
                        }
                    }
                }
                this.hold = Math.min(1, state.held / HOLD_SECONDS);
                this.view = easeWindow(this.view, targetWindow(state.note, state.held), dt, { still: still() });
                this.phase = this.shown === null ? 'listening' : state.held >= 0.5 ? 'holding' : 'hearing';
                // Nothing held for a while: a gentle word, and it keeps listening.
                const why = state.total >= NUDGE_SECONDS && state.held < 0.5 ? trouble(state) : null;
                this.nudge = why ? NUDGES[why] : null;
                // Back from no sound because a voice came through (not Try again, a mic change or a recount): the mic-heard sound.
                if (!rang && (this.why === 'silent' || this.why === 'quiet') && (why === 'unsteady' || state.held > 0)) playSound('micReady');
                this.why = why;
                if (why && state.total >= NUDGE_SECONDS * 3) state = { ...listener(), loudest: state.loudest }; // start the count again
                if (state.found) {
                    const clip = this.heldClip(); // before halt() lets the kept sound go
                    this.halt();
                    this.found = state.note;
                    this.phase = 'found';
                    this.$emit('found', state.note, clip);
                    return;
                }
                this.frame = requestAnimationFrame(tick);
            };
            this.frame = requestAnimationFrame(tick);
        },
        halt() {
            cancelAnimationFrame(this.frame);
            this.frame = null;
            this.stopKeeping();
        },
        /**
         * The chord moment (docs/onboarding-review.md, step 3): while finding the note, the last few
         * seconds the mic heard are kept in memory (never saved or sent), so the note's end can be
         * heard again in a chord.
         */
        keepHeld() {
            this.stopKeeping();
            if (!engine.ctx || !engine.recorder) return;
            const sampleRate = engine.ctx.sampleRate;
            const kept = { chunks: [], frames: 0, sampleRate };
            this.held = kept;
            engine
                .startCapture(Math.ceil(engine.ctx.currentTime * sampleRate), null, (chunk) => {
                    kept.chunks.push(chunk);
                    kept.frames += chunk.length;
                    while (kept.chunks.length > 1 && kept.frames - kept.chunks[0].length >= HELD_KEEP_SECONDS * sampleRate) kept.frames -= kept.chunks.shift().length;
                })
                .catch(() => {}); // replaced or cancelled: nothing to keep
        },
        stopKeeping() {
            if (this.held) engine.cancelCapture();
            this.held = null;
        },
        /** The end of the held note, `{ samples, sampleRate }`, or null when too little was heard. */
        heldClip() {
            const kept = this.held;
            if (!kept || kept.frames < HELD_SECONDS * kept.sampleRate) return null;
            const all = new Float32Array(kept.frames);
            let at = 0;
            for (const chunk of kept.chunks) {
                all.set(chunk, at);
                at += chunk.length;
            }
            return { samples: all.slice(all.length - Math.round(HELD_SECONDS * kept.sampleRate)), sampleRate: kept.sampleRate };
        },
        again() {
            this.halt();
            this.found = null;
            if (audio.mic) this.listen();
            else this.start();
        },
        async playIt() {
            await hearNotes([{ beat: 0, length: 2, pitch: Math.round(this.found) }], { tempo: 60 });
        },
        skip() {
            this.halt();
            this.$emit('skip');
        },
        async pickMic(deviceId) {
            this.switching = true;
            try {
                await chooseMic(deviceId);
                this.again();
            } catch (error) {
                micError(error);
            } finally {
                this.switching = false;
            }
        },
    },
};
