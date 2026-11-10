// Finding a note in your voice, the one way everywhere (Hillel, 2026-11-02: "make sure we have a single (find
// your note) UI throughout the app which follows this approach, or better"; his Comfortable Note v2). Lesson 1's
// comfortable note and the range check's lowest and highest notes all use this component.
//
// - A big live note in a ring that fills as the note is held (the real hold, never a show).
// - A status title and a line under it: finding, hold it (the seconds left), found.
// - A Lower to Higher scale under a fixed needle, wide until the voice settles, then zooming in on the note held
//   (the shared, honest window: js/listening.js targetWindow), Wide, Closer, Locked.
// - The mic named, with its level and Change.
// - Kept from before: it listens the moment it opens (no "Start listening"), the first hum on a new mic is the
//   mic check too ("Got you, your mic works"), the no-sound card when nothing comes, gentle nudges, the chord
//   moment's clip (the end of the held note, in memory only), Play it, Try another note.
//
// `mode`: 'note' (a comfortable note: `found` once one is held for HOLD_SECONDS, then it stops), or 'lowest' /
// 'highest' (the range check: `held` each time a note is held for `holdSeconds`, and it keeps listening; the
// host keeps the lowest or highest and passes it back as `best`).

import { audio, chooseMic, currentMicId, engine, micChoices, openMic } from '../audio.js';
import { checkState, looksLikeInterface, micNeedsCheck, rememberMic, stepVoice, voiceWatch } from '../mic-check.js';
import { hearNotes } from '../creator-audio.js';
import { midiName } from '../dsp.js';
import { t, tk } from '../i18n.js';
import { HOLD_SECONDS, hear, LISTEN_CLARITY, listener, trouble } from '../listen.js';
import { partsFor } from '../listening.js';
import { easeSpan, scaleTicks, targetSpan, WIDE_SPAN, zoomOf } from '../capture-scale.js';
import { pitchDisplay, stepDisplay } from '../pitch-display.js';
import { showMicHelp } from '../mic-help.js';
import { trackFrame } from '../pitchtrack.js';
import { playSound } from '../sounds.js';
import { Icon } from './icon.js';
import { LevelSegments } from './level-meter.js';
import { micError, MicPicker } from './mic.js';

const still = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
const NUDGES = { quiet: tk('listening.nudge.quiet'), silent: tk('listening.nudge.silent'), unsteady: tk('listening.nudge.unsteady') };
const NUDGE_SECONDS = 6; // nothing held this long: a gentle word
const SPEAK_AFTER_MS = 800; // a phase said to a screen reader once it has held this long (a timer for words, never audio)
const HELD_KEEP_SECONDS = 3; // the last of what the mic heard, kept in memory for the chord moment (never saved)
const HELD_SECONDS = 2; // what the chord moment plays back: the end of the held note
const RING = 2 * Math.PI * 69; // the hold ring's circumference (r 69 in a 152 box)
const ZOOMS = [tk('capture.zoom.wide'), tk('capture.zoom.closer'), tk('capture.zoom.locked')];
const LEVEL_BARS = [0.2, 0.4, 0.6, 0.8, 1];
// The range check's words, by which end it's listening for.
const RANGE_WORDS = {
    lowest: { listening: tk('capture.lowest.listening'), more: tk('capture.lowest.more') },
    highest: { listening: tk('capture.highest.listening'), more: tk('capture.highest.more') },
};

export const VoiceCapture = {
    components: { Icon, LevelSegments, MicPicker },
    props: {
        mode: { type: String, default: 'note' }, // 'note', 'lowest' or 'highest'
        color: { type: String, default: 'var(--color-part-1)' }, // the live colour (--capture-live); found, the accent (--capture-done)
        holdSeconds: { type: Number, default: HOLD_SECONDS },
        best: { type: Number, default: null }, // the range check's note so far (the host's)
    },
    emits: ['found', 'held', 'no-sound', 'again'],
    data: () => ({ phase: 'idle', shown: null, markerAlpha: 1, span: WIDE_SPAN, centre: 57, hold: 0, level: 0, db: null, nudge: null, why: null, found: null, showMic: false, switching: false, checking: false, micWorks: false, spoken: '', heldNote: null }),
    setup: () => ({ micChoices, currentMicId, RING, LEVEL_BARS }),
    template: `
        <div class="space-y-4" data-voice-capture data-listening :data-mode="mode" :data-phase="phase">
            <!-- The arcade's hooks (the game agent's): data-ui capture and its parts, the phase as searching, holding or done,
                 --hold and --level, and the colours as --capture-live and --capture-done, so the skin re-points tokens only. -->
            <div class="relative overflow-hidden rounded-3xl border border-line bg-panel px-5 pb-4 pt-5 sm:px-6" data-ui="capture" :data-phase="skinPhase" :style="{ '--capture-live': color, '--capture-done': 'var(--color-accent)', '--hold': ringFill, '--level': level }">
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
                <div v-else class="flex flex-col items-center gap-4 text-center">
                    <!-- The note, live, in a ring that fills as it's held. -->
                    <div class="relative size-[9.5rem]" aria-hidden="true" data-ui="capture-hold">
                        <svg viewBox="0 0 152 152" class="absolute inset-0 size-full -rotate-90">
                            <circle cx="76" cy="76" r="69" fill="none" stroke="var(--color-raised)" stroke-width="8" />
                            <circle cx="76" cy="76" r="69" fill="none" :stroke="accent" stroke-width="8" stroke-linecap="round" data-ring :stroke-dasharray="RING" :stroke-dashoffset="RING * (1 - ringFill)" :class="still ? '' : 'transition-[stroke-dashoffset] duration-100 ease-linear'" />
                        </svg>
                        <div class="absolute inset-[0.875rem] flex flex-col items-center justify-center gap-1 rounded-full bg-ink/60">
                            <span class="text-[2.625rem] font-extrabold leading-none tabular-nums" :style="{ color: noteColor, opacity: noteOpacity }" translate="no" data-note data-ui="capture-note">{{ noteLabel }}</span>
                            <span class="text-[0.8125rem] text-muted" data-ui="capture-note-sub">{{ noteSub }}</span>
                        </div>
                    </div>
                    <div class="flex min-h-11 flex-col gap-1" data-ui="capture-status">
                        <p class="text-lg font-bold text-cream" data-status>{{ statusTitle }}</p>
                        <p class="text-sm text-muted">{{ statusSub }}</p>
                    </div>
                    <!-- Said to a screen reader once a phase has held a moment: a wavering voice flips between
                         Hearing and Holding many times a second, and saying each would drown the lesson. -->
                    <p class="sr-only" aria-live="polite">{{ spoken }}</p>
                    <p v-if="nudge" class="text-sm text-muted" role="status">{{ t(nudge) }}</p>
                    <!-- The first voice on a new mic is the mic check too: one hum, not two asks. -->
                    <p v-else-if="micWorks" data-ui="facts" class="flex items-center gap-2 text-sm text-good" role="status" data-mic-works><Icon name="check" :size="16" />{{ t('micCheck.gotYou') }}</p>
                    <!-- Lower to Higher under a fixed needle: wide until the voice settles, then close on the note held, and locked on it once found. -->
                    <div class="w-full space-y-1.5">
                        <div class="relative h-16 overflow-hidden" :style="{ maskImage: 'linear-gradient(90deg, transparent, #000 18%, #000 82%, transparent)', WebkitMaskImage: 'linear-gradient(90deg, transparent, #000 18%, #000 82%, transparent)' }" role="img" :aria-label="scaleLabel" data-scale :data-zoom="zoom" data-ui="capture-strip">
                            <div class="absolute inset-x-0 top-5 h-px bg-line"></div>
                            <div class="absolute top-0 h-10 -translate-x-1/2 rounded-md" :style="{ left: '50%', width: band + '%', background: accent, opacity: 0.12 }"></div>
                            <div v-for="tick in ticks" :key="tick.midi" class="absolute top-0 flex -translate-x-1/2 flex-col items-center gap-1.5" :style="{ left: tick.at * 100 + '%' }" data-tick :data-active="tick.active ? '' : null">
                                <span class="w-0.5 rounded-full" :class="tick.active ? 'mt-1.5 h-7' : tick.natural ? 'mt-3 h-[1.125rem] bg-muted/70' : 'mt-4 h-2.5 bg-line'" :style="tick.active ? { background: accent } : null"></span>
                                <span v-if="tick.label" class="whitespace-nowrap font-semibold tabular-nums" :class="tick.active ? 'text-[0.9375rem]' : 'text-[0.6875rem] text-muted'" :style="tick.active ? { color: accent } : null" translate="no">{{ tick.label }}</span>
                            </div>
                            <div class="absolute top-0 h-10 w-0.5 -translate-x-1/2 rounded-full" :style="{ left: '50%', background: accent, opacity: shown === null ? 0.35 : 1 }"></div>
                        </div>
                        <div class="flex items-center justify-between text-xs text-muted" aria-hidden="true" data-ui="capture-zoom">
                            <span>{{ t('capture.lower') }}</span>
                            <span class="flex items-center gap-1" data-zoom-dots><span v-for="i in 3" :key="i" class="size-1.5 rounded-full" :class="i - 1 <= zoom ? '' : 'bg-line'" :style="i - 1 <= zoom ? { background: accent } : null"></span><span class="ms-1">{{ t(zoomWord) }}</span></span>
                            <span>{{ t('capture.higher') }}</span>
                        </div>
                    </div>
                    <p v-if="phase === 'found' && mode === 'note'" class="max-w-sm text-cream" data-suits>{{ t(partsFor(found)) }}</p>
                    <button v-if="phase === 'blocked'" type="button" class="btn-secondary" @click="start"><Icon name="mic" :size="18" /> {{ t('listening.micAgain') }}</button>
                    <!-- The mic, named, with its level and Change. -->
                    <div class="flex w-full items-center gap-2.5 border-t border-line pt-3" data-mic-row data-ui="capture-mic">
                        <span class="flex h-4 shrink-0 items-end gap-[3px]" aria-hidden="true" data-ui="capture-level"><span v-for="bar in LEVEL_BARS" :key="bar" class="w-[3px] rounded-full" :class="level >= bar * 0.9 ? 'bg-cream' : 'bg-line'" :style="{ height: 4 + bar * 12 + 'px' }"></span></span>
                        <span class="min-w-0 flex-1 truncate text-start text-[0.8125rem] text-muted" translate="no">{{ micName }}</span>
                        <button type="button" class="link-quiet min-h-11 text-[0.8125rem] font-semibold" :aria-expanded="showMic ? 'true' : 'false'" @click="showMic = !showMic">{{ t('capture.change') }}</button>
                    </div>
                </div>
            </div>
            <MicPicker v-if="showMic && !noSound" />
            <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-1 text-sm">
                <button v-if="phase === 'found' && mode === 'note'" type="button" class="link-quiet inline-flex min-h-11 items-center gap-1.5" @click="playIt"><Icon name="play" :size="14" /> {{ t('listening.playIt') }}</button>
                <button v-if="phase === 'found' || listeningNow" type="button" class="link-quiet min-h-11" data-try-another @click="again">{{ mode === 'note' ? t('capture.tryAnother') : t('listening.startOver') }}</button>
            </div>
        </div>`,
    computed: {
        still,
        listeningNow: (vm) => ['listening', 'hearing', 'holding'].includes(vm.phase),
        noSound: (vm) => vm.listeningNow && (vm.why === 'silent' || vm.why === 'quiet'),
        micName: () => audio.mic?.label || t('micHelp.yourMic'),
        checks() {
            if (looksLikeInterface(audio.mic?.label)) return [t('micCheck.checkInputWeb'), t('micCheck.checkGain', { number: 1 }), t('micCheck.checkPhantom')];
            return [t('micCheck.checkRightMic'), t('micCheck.checkMuted'), t('micCheck.checkLevel')];
        },
        /**
         * The note named: only a settled one, the note found (or the range's best so far). Never the voice's
         * note while it's still settling, whose letter would flash from one name to the next (Hillel,
         * 2026-11-02: "dont show the specific note ... until it finished"); the ring and the words say
         * it's hearing you meanwhile.
         */
        shownNote() {
            if (this.found !== null) return Math.round(this.found);
            if (this.mode !== 'note' && this.best !== null) return this.best;
            return null;
        },
        noteLabel: (vm) => (vm.shownNote === null ? '·' : midiName(vm.shownNote)),
        /** The live colour while finding and holding, the accent once found (tokens the arcade can re-point). */
        accent: (vm) => (vm.phase === 'found' ? 'var(--capture-done)' : 'var(--capture-live)'),
        /** The phase as the skin reads it: searching, holding or done. */
        skinPhase: (vm) => (vm.phase === 'found' ? 'done' : vm.phase === 'holding' ? 'holding' : 'searching'),
        noteColor: (vm) => (vm.phase === 'found' || vm.phase === 'holding' || (vm.shownNote !== null && vm.phase !== 'hearing') ? vm.accent : 'var(--color-cream)'),
        /** The dot in the ring is faint until a voice is heard; a settled note is full. */
        noteOpacity: (vm) => (vm.shownNote !== null || vm.phase === 'hearing' || vm.phase === 'holding' ? 1 : 0.35),
        noteSub() {
            if (this.phase === 'found') return t('capture.sub.found');
            if (this.phase === 'holding') return t('capture.sub.steady');
            if (this.phase === 'hearing') return t('capture.sub.hearing');
            return t('capture.sub.listening');
        },
        /** The ring: the hold so far, full once found. */
        ringFill: (vm) => (vm.phase === 'found' ? 1 : vm.hold),
        secondsLeft: (vm) => Math.max(1, Math.ceil((1 - vm.hold) * vm.holdSeconds)),
        statusTitle() {
            const range = this.mode !== 'note';
            return {
                idle: t('listening.starting'),
                starting: t('listening.starting'),
                waiting: t('listening.tapToStart'),
                blocked: t('listening.noMic'),
                listening: range ? t(RANGE_WORDS[this.mode].listening) : t('capture.listening'),
                hearing: t('capture.hearing'),
                holding: t('capture.holding'),
                found: t('capture.found'),
            }[this.phase];
        },
        statusSub() {
            if (this.phase === 'found') return t('capture.foundSub', { note: midiName(Math.round(this.found)) });
            if (this.phase === 'holding') return t('capture.holdingSub', { seconds: this.secondsLeft });
            if (this.phase === 'hearing') return t('capture.hearingSub');
            if (this.phase === 'listening') {
                if (this.mode !== 'note' && this.best !== null) return t(RANGE_WORDS[this.mode].more, { note: midiName(this.best) });
                return t('capture.listeningSub');
            }
            return '';
        },
        /** The scale's ticks, named only once the note is found: names sliding past the needle are letters flashing too. */
        ticks: (vm) => scaleTicks(vm.centre, vm.span).map((tick) => (vm.phase === 'found' ? tick : { ...tick, label: null })),
        zoom: (vm) => (vm.phase === 'found' ? 2 : zoomOf(vm.span)),
        zoomWord: (vm) => ZOOMS[vm.zoom],
        /** The band round the needle: most of a semitone, so it narrows as the scale widens. */
        band: (vm) => Math.min(30, (0.7 / vm.span) * 100),
        scaleLabel() {
            if (this.phase === 'found') return t('listening.barAt', { note: midiName(Math.round(this.found)) });
            return this.shown === null ? t('listening.barEmpty') : t('capture.sub.hearing');
        },
        spokenNow() {
            if (this.phase === 'found') return `${t('capture.found')}: ${midiName(Math.round(this.found))}. ${t(partsFor(this.found))}`;
            return this.statusTitle;
        },
    },
    watch: {
        noSound(now) {
            this.$emit('no-sound', now);
        },
        spokenNow: {
            immediate: true,
            handler(now) {
                clearTimeout(this.speakTimer);
                this.speakTimer = setTimeout(() => (this.spoken = now ?? ''), SPEAK_AFTER_MS);
            },
        },
        mode() {
            // The range check's next step: the same capture, listening for the other end.
            this.heldNote = null;
            this.hold = 0;
            this.span = WIDE_SPAN;
        },
    },
    mounted() {
        // The tap that brought the person here lets the audio start: listen at once. Without one (a reload into
        // this step), the first tap anywhere starts it.
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
            if (this.left) return; // left while the mic opened
            this.listen();
        },
        listen() {
            cancelAnimationFrame(this.frame);
            let state = listener();
            let last = performance.now();
            let display = pitchDisplay();
            // The first time on this device (or with new mics), the first voice heard is the mic check as well.
            let voice = voiceWatch();
            this.checking = micNeedsCheck();
            this.micWorks = false;
            this.phase = 'listening';
            if (this.mode === 'note') this.keepHeld();
            this.nudge = null;
            this.shown = null;
            this.hold = 0;
            this.heldNote = null;
            this.span = WIDE_SPAN;
            const tick = () => {
                const now = performance.now();
                const dt = Math.min(0.1, (now - last) / 1000);
                last = now;
                // A suspended context reads silence: nothing counts toward "can't hear" until it runs.
                const frame = engine.ctx?.state === 'running' ? engine.inputFrame() : null;
                let rang = false;
                if (frame) {
                    const reading = trackFrame(frame, engine.sampleRate, { minClarity: LISTEN_CLARITY });
                    state = hear(state, reading, dt);
                    this.level = Math.max(0, Math.min(1, (reading.loudness + 60) / 45));
                    this.db = reading.loudness;
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
                this.hold = Math.min(1, state.held / this.holdSeconds);
                this.span = easeSpan(this.span, targetSpan(state.held, this.holdSeconds), dt, { still: still() });
                if (this.shown !== null) this.centre = this.shown; // the needle stays on the voice; silent, where it was
                this.phase = this.shown === null ? 'listening' : state.held >= 0.5 ? 'holding' : 'hearing';
                const why = state.total >= NUDGE_SECONDS && state.held < 0.5 ? trouble(state) : null;
                this.nudge = why ? NUDGES[why] : null;
                if (!rang && (this.why === 'silent' || this.why === 'quiet') && (why === 'unsteady' || state.held > 0)) playSound('micReady');
                this.why = why;
                if (why && state.total >= NUDGE_SECONDS * 3) state = { ...listener(), loudest: state.loudest }; // count again
                if (this.mode === 'note' && state.found) {
                    const clip = this.heldClip(); // before halt() lets the kept sound go
                    this.halt();
                    this.found = state.note;
                    this.phase = 'found';
                    this.$emit('found', state.note, clip);
                    return;
                }
                // The range check: each note held long enough is offered once; the host keeps the lowest or highest.
                if (this.mode !== 'note' && state.held >= this.holdSeconds && state.note !== null) {
                    const note = Math.round(state.note);
                    if (note !== this.heldNote) {
                        this.heldNote = note;
                        this.$emit('held', note);
                    }
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
         * The chord moment (docs/onboarding-review.md, step 3): while finding the note, the last few seconds the
         * mic heard are kept in memory (never saved or sent), so the note's end can be heard again in a chord.
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
            this.$emit('again');
            if (audio.mic) this.listen();
            else this.start();
        },
        async playIt() {
            await hearNotes([{ beat: 0, length: 2, pitch: Math.round(this.found) }], { tempo: 60 });
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
