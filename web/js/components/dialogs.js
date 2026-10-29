import { audio, chooseMic, chooseRoute, checkTiming, currentMicId, engine, looksWireless, measuredFor, micChoices, openMic, ROUTES } from '../audio.js';
import { styleOf } from '../band.js';
import { Switch } from './controls.js';
import { formatBytes } from './cloud.js';
import { hzToMidi, midiName, rankParts } from '../dsp.js';
import { hear, LISTEN_CLARITY, listener } from '../listen.js';
import { easeWindow, targetWindow, WIDE } from '../listening.js';
import { pitchDisplay, stepDisplay } from '../pitch-display.js';
import { trackFrame } from '../pitchtrack.js';
import { PitchBar } from './listening.js';
import { LevelMeter } from './level-meter.js';
import { micDb, readyMic } from '../mic-check.js';
import { formatList, formatNumber, t, tk } from '../i18n.js';
import { lengthBeats, partsFor, singerOptions, templatePartName } from '../templates.js';
import { formatTime, keyChoiceLabel, keyLabel, KEYS, PART_COLORS, TIME_SIGNATURES, transposeKey } from '../theme.js';
import { Icon } from './icon.js';
import { micError, MicPicker, WirelessNote } from './mic.js';
import { Modal } from './ui.js';

const RANGE_HOLD_SECONDS = 1; // a note held this long counts toward the range
const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
import { setRange } from '../stores/learning.js';
import { prefs } from '../stores/prefs.js';
import { exportNeedsPlus, isPlus } from '../stores/plan.js';

const DIFFICULTY = { beginner: tk('templates.difficulty.beginner'), intermediate: tk('templates.difficulty.intermediate'), advanced: tk('templates.difficulty.advanced') };
const ROOM_CHOICES = [
    { value: 'dry', label: tk('polish.room.dry') },
    { value: 'small', label: tk('polish.room.small') },
    { value: 'hall', label: tk('polish.room.hall') },
    { value: 'chapel', label: tk('polish.room.chapel') },
];


export { micError }; // record-setup.js and others reach it here

// The studio dialogs' fields, labels and buttons: the shared ones (css/input.css, the forms system).
const field = 'input mt-1';
const label = 'label';
const primary = 'primary btn-primary';
const secondary = 'btn-secondary';

/** A kind note when the chosen mic looks wireless: its delay can change while you sing. */

/** A project's settings (and the template project's key and singers). Only the title is required. */
// Song settings' backing style (js/band.js), for a song with chords.
const BAND_CHOICES = [{ value: 'gentle', label: tk('band.style.gentle') }, { value: 'pop', label: tk('band.style.pop') }, { value: 'swing', label: tk('band.style.swing') }];
const BAND_HINTS = { gentle: tk('band.hint.gentle'), pop: tk('band.hint.pop'), swing: tk('band.hint.swing') };

export const ProjectDialog = {
    components: { Icon, Modal, Switch },
    // canTemplate: the project has something sung to make a template from.
    props: { project: { type: Object, default: null }, canTemplate: Boolean },
    emits: ['save', 'close', 'range', 'template'],
    data() {
        const p = this.project ?? {};
        return {
            transpose: p.transpose ?? 0,
            singers: p.singers ?? p.template?.parts.length ?? 0,
            title: p.title ?? '',
            bpm: p.bpm ?? 90,
            key: p.key ?? '',
            timeSignature: p.timeSignature ?? '4/4',
            countInBars: p.countInBars ?? 1,
            metronome: p.metronome ?? true,
            taps: [],
            was: null,
            backingStyle: styleOf(p.practice?.chordsSound),
            BAND_CHOICES,
            BAND_HINTS,
            KEYS,
            keyChoiceLabel,
            TIME_SIGNATURES,
            field,
            label,
            primary,
            secondary,
        };
    },
    template: `
        <Modal :title="t('project.settingsTitle')" @close="$emit('close')">
            <!-- 105.png: the title, then a row each with its control on the right; Save as template, Cancel and Save (once something's changed). -->
            <form class="divide-y divide-line" data-project-settings @submit.prevent="save">
                <label class="block pb-4" :class="label">{{ t('project.title') }}
                    <input v-model="title" :class="field" maxlength="80" :placeholder="t('project.namePlaceholder')" />
                </label>
                <template v-if="project?.template && !project.beta">
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-4">
                        <div class="min-w-0"><p class="font-semibold text-cream">{{ t('project.key') }}</p><button type="button" class="text-sm font-semibold text-accent-strong hover:underline" @click="$emit('range')">{{ t('project.findKey') }}</button></div>
                        <div class="stepper">
                            <button type="button" :disabled="transpose === -7" :aria-label="t('project.downSemitone')" @click="transpose -= 1"><Icon name="minus" :size="18" /></button>
                            <span class="min-w-16 px-1 text-center font-[650] text-cream" role="status" translate="no">{{ shownKey }}</span>
                            <button type="button" :disabled="transpose === 7" :aria-label="t('project.upSemitone')" @click="transpose += 1"><Icon name="plus" :size="18" /></button>
                        </div>
                    </div>
                    <div v-if="project.template.chords?.length" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-4" data-backing-style>
                        <div class="min-w-0"><p class="font-semibold text-cream">{{ t('project.backing') }}</p><p class="text-sm text-muted">{{ t(BAND_HINTS[backingStyle]) }}</p></div>
                        <div class="seg flex" role="radiogroup" :aria-label="t('project.backing')">
                            <button v-for="s in BAND_CHOICES" :key="s.value" type="button" role="radio" :aria-checked="backingStyle === s.value ? 'true' : 'false'" class="seg-option" @click="backingStyle = s.value">{{ t(s.label) }}</button>
                        </div>
                    </div>
                    <div v-if="singerChoices.length > 1" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-4">
                        <div class="min-w-0"><p class="font-semibold text-cream">{{ t('project.parts') }}</p><p class="text-sm text-muted" translate="no">{{ partNames }}</p></div>
                        <div class="seg flex" role="radiogroup" :aria-label="t('project.parts')">
                            <button v-for="n in singerChoices" :key="n" type="button" role="radio" :aria-checked="singers === n ? 'true' : 'false'" class="seg-option grid size-10 place-items-center px-0" @click="singers = n">{{ n }}</button>
                        </div>
                    </div>
                </template>
                <template v-if="!project?.template">
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-4">
                        <div class="min-w-0"><p class="font-semibold text-cream">{{ t('project.tempo') }}</p><p class="text-sm text-muted">{{ t('project.hint.tempo') }}</p></div>
                        <div class="flex items-center gap-2">
                            <input v-model.number="bpm" :class="field" class="w-24" type="number" min="30" max="300" inputmode="numeric" :aria-label="t('project.tempo')" :placeholder="t('project.none')" />
                            <button type="button" class="btn-secondary btn-sm" @click="tap">{{ t('project.tap') }}</button>
                        </div>
                    </div>
                    <label class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-4">
                        <span class="min-w-0"><span class="block font-semibold text-cream">{{ t('project.key') }}</span><span class="block text-sm text-muted">{{ t('project.hint.key') }}</span></span>
                        <select v-model="key" :class="field" class="w-auto">
                            <option value="">{{ t('project.none') }}</option>
                            <option v-for="k in KEYS" :key="k" :value="k">{{ keyChoiceLabel(k) }}</option>
                        </select>
                    </label>
                    <label class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-4">
                        <span class="min-w-0"><span class="block font-semibold text-cream">{{ t('project.timeSignature') }}</span><span class="block text-sm text-muted">{{ t('project.hint.timeSignature') }}</span></span>
                        <select v-model="timeSignature" :class="field" class="w-auto">
                            <option v-for="sig in TIME_SIGNATURES" :key="sig">{{ sig }}</option>
                        </select>
                    </label>
                </template>
                <Switch :on="countInBars > 0" :label="t('studio.setup.countIn')" :hint="t('project.hint.countIn')" class="py-3" @change="countInBars = $event ? 1 : 0" />
                <Switch :on="metronome" :label="t('project.metronomeShort')" :hint="t('project.hint.click')" class="py-3" @change="metronome = $event" />
                <p v-if="!project?.template" class="py-3 text-xs text-muted">{{ t('project.remembered') }}</p>
                <div class="-mx-6 flex flex-wrap items-center justify-end gap-3 px-6 pt-5">
                    <button v-if="canTemplate" type="button" class="me-auto min-h-11 text-sm text-muted underline hover:text-cream" @click="$emit('template')">{{ t('project.saveAsTemplate') }}</button>
                    <button type="button" :class="secondary" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" :class="primary" :disabled="!changed">{{ t('common.save') }}</button>
                </div>
            </form>
        </Modal>`,
    created() {
        this.was = JSON.stringify(this.fields());
    },
    computed: {
        singerChoices() {
            return this.project?.template ? singerOptions(this.project.template) : [];
        },
        /** "Soprano, Alto, Tenor, Bass": the parts sung with this many singers. */
        partNames() {
            return formatList(partsFor(this.project.template, this.singers).map((p) => templatePartName(p)));
        },
        changed() {
            return JSON.stringify(this.fields()) !== this.was;
        },
        shownKey() {
            const key = transposeKey(this.project?.template?.key, this.transpose);
            return this.transpose ? t('project.shiftedKey', { key: keyLabel(key), shift: formatNumber(this.transpose, { signDisplay: 'always' }) }) : keyLabel(key);
        },
    },
    methods: {
        tap() {
            const now = performance.now();
            this.taps = [...this.taps.filter((t) => now - t < 3000), now].slice(-5);
            if (this.taps.length >= 2) {
                const gaps = this.taps.slice(1).map((t, i) => t - this.taps[i]);
                this.bpm = Math.round(60000 / (gaps.reduce((a, b) => a + b, 0) / gaps.length));
            }
        },
        save() {
            this.$emit('save', this.fields());
        },
        /** What Save sends: a template song keeps its own tempo, key and time; its key moves by transpose. */
        fields() {
            return {
                title: this.title.trim(),
                bpm: Number.isFinite(this.bpm) && this.bpm >= 30 && this.bpm <= 300 ? this.bpm : null,
                key: this.key || null,
                timeSignature: this.timeSignature,
                countInBars: this.countInBars,
                metronome: this.metronome,
                ...(this.project?.template ? { transpose: this.transpose, singers: this.singers, bpm: this.project.bpm, key: this.project.key, timeSignature: this.project.timeSignature } : {}),
                ...(this.project?.template?.chords?.length ? { backingStyle: this.backingStyle } : {}),
            };
        },
    },
};

/** Shown before the first recording of each session. */
export const HeadphonesDialog = {
    components: { Modal, Icon },
    // speakerOption: the course can go on without headphones (it quiets what plays under the voice).
    // action: the primary's words (Start recording, unless the screen says otherwise).
    props: { speakerOption: Boolean, action: { type: String, default: '' } },
    emits: ['ok', 'close', 'speaker'],
    data: () => ({ never: false, picked: audio.route === 'bluetooth' ? 'bluetooth' : 'wired', primary, secondary }),
    setup: () => ({ measuredFor, KINDS: { wired: { name: tk('headphones.wired'), line: tk('headphones.wiredLine') }, bluetooth: { name: tk('headphones.bluetooth'), line: tk('headphones.bluetoothLine') } } }),
    // 102.png: the two kinds as radio cards, Ready or Needs timing check; Don't ask again, Not now, Start recording.
    template: `
        <Modal :title="t('headphones.title')" :subtitle="t('headphones.body')" wide @close="$emit('close')">
            <div class="space-y-5" data-headphones>
                <div class="space-y-2" role="radiogroup" :aria-label="t('headphones.kind')">
                    <button v-for="(kind, name) in KINDS" :key="name" type="button" role="radio" :aria-checked="picked === name ? 'true' : 'false'" class="radio-card w-full items-center gap-4 p-4" @click="pick(name)">
                        <span class="radio-mark" aria-hidden="true"></span>
                        <span class="min-w-0 flex-1"><span class="block font-semibold text-cream">{{ t(kind.name) }}</span><span class="block text-sm text-muted">{{ t(kind.line) }}</span></span>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold" :class="ready(name) ? 'bg-good/15 text-good' : 'bg-warn/15 text-warn'">{{ ready(name) ? t('headphones.ready') : t('headphones.needsCheck') }}</span>
                    </button>
                </div>
                <div class="-mx-6 flex flex-wrap items-center justify-end gap-3 border-t border-line px-6 pt-5">
                    <label class="check-row me-auto basis-full text-muted sm:basis-auto"><input v-model="never" type="checkbox" class="checkbox" /> {{ t('headphones.dontAsk') }}</label>
                    <button v-if="speakerOption" type="button" :class="secondary" @click="$emit('speaker')">{{ t('headphones.none') }}</button>
                    <button v-else type="button" :class="secondary" @click="$emit('close')">{{ t('headphones.notNow') }}</button>
                    <button type="button" :class="primary" @click="$emit('ok', never)">{{ action || t('headphones.start') }}</button>
                </div>
            </div>
        </Modal>`,
    methods: {
        /** Wired is ready as it is; Bluetooth once its timing is measured with this mic. */
        ready(name) {
            return name === 'wired' || this.measuredFor(name);
        },
        pick(name) {
            this.picked = name;
            chooseRoute(name);
        },
    },
};

/** The timing check: measures the delay between what's played and what the mic hears. */
export const TimingDialog = {
    components: { Modal, Icon, LevelMeter, WirelessNote },
    // required: a measured delay is needed (a beta guide), so there's no "use an estimate".
    props: { beforeRecording: Boolean, required: Boolean },
    emits: ['done', 'skip', 'close'],
    data: () => ({ step: 'choose', progress: 0, result: null, db: null, details: false, error: null, audio, ROUTES, primary, secondary, field, label, formatNumber }),
    setup: () => ({ micChoices, currentMicId, looksWireless }),
    template: `
        <Modal :title="t('timing.title')" :subtitle="step === 'choose' ? t('timing.intro') : ''" wide :dismissable="step !== 'measuring'" @close="$emit('close')">
            <!-- 101.png: the mic with its level, what you record with as three choices and what to do for it, then Skip and Start the check. -->
            <div v-if="step === 'choose'" class="space-y-5" data-timing>
                <div class="space-y-2">
                    <label for="timing-mic" :class="label">{{ t('timing.mic') }}</label>
                    <div class="relative">
                        <Icon name="mic" :size="18" class="pointer-events-none absolute start-4 top-1/2 -translate-y-1/2 text-muted" />
                        <select id="timing-mic" :class="field" class="ps-11" :value="currentMicId()" @change="switchMic($event.target.value)">
                            <option v-for="m in micChoices()" :key="m.deviceId" :value="m.deviceId" translate="no">{{ m.systemDefault ? t('mic.systemDefaultNamed', { name: m.label }) : m.label }}</option>
                        </select>
                    </div>
                    <LevelMeter :db="db" segments />
                </div>
                <WirelessNote v-if="looksWireless(audio.mic?.label)" />
                <div class="space-y-2">
                    <p id="timing-route" :class="label">{{ t('timing.routeQuestion') }}</p>
                    <div class="seg grid grid-cols-3 rounded-2xl" role="radiogroup" aria-labelledby="timing-route">
                        <button v-for="(route, name) in ROUTES" :key="name" type="button" role="radio" :aria-checked="audio.route === name ? 'true' : 'false'" class="seg-option px-2 py-2 leading-tight" @click="pickRoute(name)">{{ t(route.label) }}</button>
                    </div>
                    <p class="rounded-2xl bg-raised/60 px-4 py-3 text-cream" aria-live="polite">{{ t(ROUTES[audio.route]?.hint ?? ROUTES.wired.hint) }}</p>
                </div>
                <p v-if="error" class="rounded-xl bg-record/15 p-3 text-sm text-cream">{{ error }}</p>
                <div class="-mx-6 flex flex-wrap items-center justify-end gap-3 border-t border-line px-6 pt-5">
                    <button v-if="beforeRecording && !required" type="button" class="me-auto min-h-11 text-sm text-muted underline hover:text-cream" @click="$emit('skip')">{{ t('timing.skip') }}</button>
                    <p v-if="required" class="me-auto text-sm text-muted">{{ t('timing.requiredForGuides') }}</p>
                    <button type="button" :class="primary" @click="measure">{{ t('timing.start') }}</button>
                </div>
            </div>
            <div v-else-if="step === 'measuring'" class="flex flex-col items-center gap-5 py-6 text-center">
                <div class="flex h-16 items-center gap-1.5">
                    <span v-for="i in 9" :key="i" class="w-2 animate-pulse rounded-full bg-part-4" :style="{ height: (20 + ((i * 37) % 44)) + 'px', animationDelay: (i * 90) + 'ms' }"></span>
                </div>
                <p class="text-cream">{{ t('timing.listening') }}</p>
                <div class="h-2 w-full max-w-xs overflow-hidden rounded-full bg-ink"><div class="h-full rounded-full bg-part-4 transition-[width]" :style="{ width: progress * 100 + '%' }"></div></div>
            </div>
            <div v-else-if="step === 'result'" class="space-y-4 text-center">
                <div class="mx-auto grid size-16 place-items-center rounded-full bg-part-3/20 text-part-3"><Icon name="check" :size="32" /></div>
                <p class="text-lg text-cream">{{ t('timing.result', { ms: Math.round(result.ms) }) }}</p>
                <button type="button" class="text-sm text-muted underline" @click="details = !details">{{ details ? t('timing.hideDetails') : t('timing.showDetails') }}</button>
                <dl v-if="details" class="mx-auto grid max-w-xs grid-cols-2 gap-1 text-start text-sm text-muted">
                    <dt>{{ t('timing.measured') }}</dt><dd class="text-cream">{{ t('common.ms', { ms: formatNumber(result.ms, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }) }}</dd>
                    <dt>{{ t('timing.spread') }}</dt><dd class="text-cream">{{ t('common.ms', { ms: formatNumber(result.spreadMs, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }) }}</dd>
                    <dt>{{ t('timing.estimate') }}</dt><dd class="text-cream">{{ t('common.ms', { ms: formatNumber(result.estimateMs, { maximumFractionDigits: 1 }) }) }}</dd>
                    <dt>{{ t('studio.menu.mic') }}</dt><dd class="truncate text-cream" translate="no">{{ audio.mic?.label }}</dd>
                </dl>
                <div class="flex justify-center gap-3 pt-2">
                    <button type="button" :class="secondary" @click="step = 'choose'">{{ t('timing.again') }}</button>
                    <button type="button" :class="primary" @click="$emit('done')">{{ beforeRecording ? t('timing.startRecording') : t('common.done') }}</button>
                </div>
            </div>
            <div v-else class="space-y-4 text-center">
                <p class="text-cream">{{ t('timing.failed') }}</p>
                <p class="text-sm text-muted">{{ t('timing.failedHint') }}</p>
                <div class="flex justify-center gap-3">
                    <button v-if="beforeRecording && !required" type="button" :class="secondary" @click="$emit('skip')">{{ t('timing.useEstimate') }}</button>
                    <button type="button" :class="primary" @click="step = 'choose'">{{ t('common.tryAgain') }}</button>
                </div>
            </div>
        </Modal>`,
    async mounted() {
        try {
            await openMic();
        } catch (error) {
            this.error = micError(error);
        }
        const meter = () => {
            this.db = micDb();
            this.frame = requestAnimationFrame(meter);
        };
        meter();
    },
    unmounted() {
        cancelAnimationFrame(this.frame);
    },
    methods: {
        async switchMic(deviceId) {
            try {
                await chooseMic(deviceId);
                this.error = null;
            } catch (error) {
                this.error = micError(error);
            }
        },
        pickRoute(name) {
            chooseRoute(name);
        },
        async measure() {
            this.step = 'measuring';
            this.progress = 0;
            try {
                this.result = await checkTiming({ onProgress: (p) => (this.progress = p) });
                this.step = this.result.ok ? 'result' : 'failed';
            } catch (error) {
                // AbortError: a call or the screen locking stopped the audio mid-check.
                this.error = error?.name === 'AbortError' ? t('timing.interrupted') : micError(error);
                this.step = 'choose';
            }
        },
    },
};

/** Choose the microphone, see it hears you, and whether its timing has been checked. */
export const MicDialog = {
    components: { Modal, Icon, LevelMeter, WirelessNote },
    emits: ['timing', 'close'],
    setup: () => ({ audio, micChoices, currentMicId, looksWireless, ROUTES, primary, secondary, label }),
    data: () => ({ level: 0, db: null, silentFor: 0, error: null, switching: null }),
    template: `
        <Modal :title="t('studio.menu.mic')" @close="$emit('close')">
            <div class="space-y-5">
                <p v-if="error" class="rounded-xl bg-record/15 p-3 text-sm text-cream">{{ error }}</p>
                <p v-else-if="!audio.mic" class="text-muted">{{ t('mic.asking') }}</p>
                <fieldset v-else class="space-y-2">
                    <legend :class="label">{{ t('mic.recordWith') }}</legend>
                    <label v-for="m in micChoices()" :key="m.deviceId" class="radio-card items-center p-3">
                        <input type="radio" name="mic" class="radio" :checked="currentMicId() === m.deviceId" :disabled="!!switching" @change="pick(m.deviceId)" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold text-cream" translate="no">{{ m.label }}</span>
                            <span v-if="m.systemDefault || switching === m.deviceId || looksWireless(m.label)" class="text-xs text-muted">{{ switching === m.deviceId ? t('mic.switching') : [m.systemDefault ? t('mic.systemDefault') : null, looksWireless(m.label) ? t('mic.wireless') : null].filter(Boolean).join(' · ') }}</span>
                        </span>
                    </label>
                </fieldset>
                <WirelessNote v-if="audio.mic && looksWireless(audio.mic.label)" />
                <div v-if="audio.mic" class="flex items-center gap-3 text-muted">
                    <Icon name="mic" :size="18" class="shrink-0" />
                    <LevelMeter :db="db" />
                </div>
                <p v-if="audio.mic && level === 0 && silentFor > 40" class="text-sm text-muted">{{ t('mic.pureSilence') }}</p>
                <div v-if="audio.mic" class="flex items-center justify-between gap-3 rounded-2xl bg-ink/60 p-3 text-sm">
                    <span class="text-muted">
                        {{ t('mic.timingFor', { route: t(ROUTES[audio.route].label) }) }}
                        <span class="text-cream">{{ timing }}</span>
                    </span>
                    <button type="button" class="shrink-0 font-semibold text-part-4 hover:underline" @click="$emit('timing')">{{ audio.latencyFrames === null || audio.latencyEstimated ? t('mic.checkTiming') : t('mic.checkAgain') }}</button>
                </div>
                <div class="flex justify-end">
                    <button type="button" :class="primary" @click="$emit('close')">{{ t('common.done') }}</button>
                </div>
            </div>
        </Modal>`,
    computed: {
        timing() {
            if (audio.latencyFrames === null) return t('mic.notChecked');
            const ms = Math.round((audio.latencyFrames * 1000) / engine.sampleRate);
            return audio.latencyEstimated ? t('mic.estimated', { ms }) : t('mic.measured', { ms });
        },
    },
    async mounted() {
        try {
            await openMic();
        } catch (error) {
            this.error = micError(error);
        }
        const meter = () => {
            this.level = engine.inputLevel();
            this.db = micDb();
            this.silentFor = this.level === 0 ? this.silentFor + 1 : 0;
            this.frame = requestAnimationFrame(meter);
        };
        meter();
    },
    unmounted() {
        cancelAnimationFrame(this.frame);
    },
    methods: {
        async pick(deviceId) {
            this.switching = deviceId;
            try {
                await chooseMic(deviceId);
                this.error = null;
            } catch (error) {
                this.error = micError(error);
            } finally {
                this.switching = null;
            }
        },
    },
};

/** Plain words for mic failures. */

/**
 * Export (Hillel's 83.png): what to export first (the whole song, a practice track, or each part
 * in a ZIP), then how it sounds (Polished with its room, the piano chords), then the file (its name
 * with the extension, WAV or MP3). A muted part is said, with Include it. Plus choices can be picked:
 * a line says so and the button becomes Get Plus. The foot says what you'll get.
 */
export const ExportDialog = {
    components: { Modal, Switch },
    setup: () => ({ formatList, formatTime }),
    // parts: [{ id, name, muted }]; seconds: the song's length, for the size.
    props: { title: { type: String, default: '' }, defaultName: String, busy: Boolean, credits: { type: Array, default: () => [] }, hasChords: Boolean, parts: { type: Array, default: () => [] }, seconds: { type: Number, default: 0 }, sampleRate: { type: Number, default: 48000 } },
    emits: ['export', 'close', 'upgrade', 'unmute'],
    data() {
        return { name: this.defaultName, format: 'wav', chords: false, polish: prefs.polish, room: prefs.room, what: 'song', partId: this.parts[0]?.id ?? null, kind: 'without', clicks: true, field, label, primary, secondary, ROOM_CHOICES };
    },
    template: `
        <Modal :title="title ? t('export.titleOf', { title }) : t('export.title')" :title-is-content="!!title" @close="$emit('close')">
            <form class="space-y-5" data-export @submit.prevent="send">
                <fieldset class="space-y-2">
                    <legend class="mb-2 text-sm font-semibold text-muted">{{ t('export.what') }}</legend>
                    <label v-for="choice in whats" :key="choice.value" class="radio-card items-center p-3.5">
                        <input v-model="what" type="radio" name="what" :value="choice.value" class="radio" />
                        <span class="min-w-0 flex-1"><span class="block font-semibold text-cream">{{ choice.name }}</span><span class="block text-sm text-muted">{{ choice.hint }}</span></span>
                        <span v-if="choice.plus && !plus" class="chip-accent shrink-0">{{ t('home.plus') }}</span>
                    </label>
                    <div v-if="what === 'track'" class="space-y-3 rounded-2xl border border-line p-3.5">
                        <label :class="label">{{ t('export.trackPart') }}
                            <select v-model="partId" :class="field"><option v-for="p in parts" :key="p.id" :value="p.id">{{ p.name }}</option></select>
                        </label>
                        <label class="check-row"><input v-model="kind" type="radio" value="without" class="radio" /><span>{{ t('export.without') }}<span class="block text-xs text-muted">{{ t('export.withoutHint') }}</span></span></label>
                        <label class="check-row"><input v-model="kind" type="radio" value="louder" class="radio" /><span>{{ t('export.louder') }}<span class="block text-xs text-muted">{{ t('export.louderHint') }}</span></span></label>
                        <label class="check-row"><input v-model="clicks" type="checkbox" class="checkbox" />{{ t('export.clicks') }}</label>
                    </div>
                </fieldset>
                <!-- A part muted in the studio isn't in the mix: said, with a one-tap fix -->
                <p v-for="p in mutedParts" :key="p.id" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-warn/40 bg-warn/10 px-4 py-3 text-sm text-cream" role="status">
                    <span>{{ t('export.mutedPart', { name: p.name }) }}</span>
                    <button type="button" class="btn-secondary btn-sm" @click="$emit('unmute', p.id)">{{ t('export.includeIt') }}</button>
                </p>
                <fieldset class="space-y-2">
                    <legend class="mb-2 text-sm font-semibold text-muted">{{ t('export.sound') }}</legend>
                    <div class="divide-y divide-line rounded-2xl border border-line">
                        <div class="space-y-3 p-4">
                            <Switch :on="polish" :label="t('polish.name')" :hint="t('export.polishedHint')" @change="polish = $event" />
                            <div v-if="polish" class="flex items-center gap-3">
                                <span class="text-sm text-muted">{{ t('polish.room.label') }}</span>
                                <div class="seg flex flex-1 flex-wrap rounded-3xl" role="radiogroup" :aria-label="t('polish.room.label')">
                                    <button v-for="choice in ROOM_CHOICES" :key="choice.value" type="button" role="radio" :aria-checked="room === choice.value ? 'true' : 'false'" class="seg-option flex-1 whitespace-nowrap" @click="room = choice.value">{{ t(choice.label) }}</button>
                                </div>
                            </div>
                        </div>
                        <Switch v-if="hasChords" :on="chords" :label="t('export.chords')" :hint="t('export.chordsHint')" class="px-4 py-3" @change="chords = $event" />
                    </div>
                </fieldset>
                <fieldset class="space-y-2">
                    <legend class="mb-2 text-sm font-semibold text-muted">{{ t('export.file') }}</legend>
                    <div class="flex flex-wrap gap-2">
                        <label class="relative block w-full min-w-0">
                            <span class="sr-only">{{ t('export.fileName') }}</span>
                            <input v-model="name" maxlength="80" class="input pe-16" />
                            <span class="input-icon end-[18px] text-sm" translate="no">.{{ extension }}</span>
                        </label>
                        <div v-if="what !== 'stems'" class="seg ms-auto flex" role="radiogroup" :aria-label="t('export.format')">
                            <button v-for="f in ['wav', 'mp3']" :key="f" type="button" role="radio" :aria-checked="format === f ? 'true' : 'false'" class="seg-option flex items-center gap-1.5" @click="format = f" translate="no">{{ f.toUpperCase() }}<span v-if="f === 'mp3' && !plus" class="chip-accent">{{ t('home.plus') }}</span></button>
                        </div>
                    </div>
                    <p class="text-xs text-muted">{{ what === 'stems' ? t('export.stemsHint') : format === 'mp3' ? t('export.mp3Hint') : t('export.wavHint') }}</p>
                </fieldset>
                <p v-if="locked" class="text-sm text-cream" role="status">{{ what === 'stems' ? t('plus.moment.stems') : t('plus.moment.mp3') }}</p>
                <p v-if="credits.length" class="text-sm text-cream">{{ t('export.credits', { names: formatList(credits) }) }}</p>
                <div class="flex flex-wrap items-center gap-3 border-t border-line pt-4">
                    <span class="me-auto text-xs tabular-nums text-muted" translate="no">{{ summary }}</span>
                    <button type="button" :class="secondary" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" :class="primary" :disabled="busy">{{ busy ? t('export.mixing') : locked ? t('plus.subscribe') : t('export.exportAs', { format: extension.toUpperCase() }) }}</button>
                </div>
            </form>
        </Modal>`,
    computed: {
        plus: () => isPlus(),
        whats() {
            return [
                { value: 'song', name: t('export.song'), hint: t('export.songHint', { count: this.parts.length }) },
                ...(this.parts.length > 1 ? [{ value: 'track', name: t('export.track'), hint: t('export.trackHint') }] : []),
                ...(this.parts.length > 1 ? [{ value: 'stems', name: t('export.stems'), hint: t('export.stemsChoice'), plus: true }] : []),
            ];
        },
        mutedParts() {
            return this.what === 'song' ? this.parts.filter((p) => p.muted) : [];
        },
        /** The file it makes: a ZIP of WAVs for each part, else the format picked. */
        extension() {
            return this.what === 'stems' ? 'zip' : this.format;
        },
        /** MP3 and each part are Plus (docs/features.md): picked, a line says so and the button is Get Plus. */
        locked() {
            return exportNeedsPlus(this.what === 'stems' ? 'stems' : this.format);
        },
        /** "WAV · about 6.5 MB · 0:37": stereo 16-bit at the mix's rate, MP3 at about 128 kbps, each recorded part a mono WAV at its own. */
        summary() {
            const seconds = this.seconds || 0;
            const stems = this.parts.reduce((sum, p) => sum + (p.sampleRate ?? 0) * 2, 0);
            const bytes = this.what === 'stems' ? seconds * stems : this.format === 'mp3' ? seconds * 16000 : seconds * this.sampleRate * 4;
            return t('export.summary', { format: this.extension.toUpperCase(), size: formatBytes(bytes), time: formatTime(seconds) });
        },
    },
    methods: {
        send() {
            // Remembered for next time (and Hear it polished plays the same room).
            Object.assign(prefs, { polish: this.polish, room: this.room });
            if (this.locked) {
                this.$emit('upgrade', this.what === 'stems' ? 'stems' : this.format);
                return;
            }
            const track = this.what === 'track' && this.partId ? { partId: this.partId, kind: this.kind, click: this.clicks } : null;
            this.$emit('export', { name: this.name.trim() || t('export.defaultName'), format: this.what === 'stems' ? 'stems' : this.format, chords: this.chords, room: this.polish ? this.room : null, track });
        },
    },
};

/** A part's settings: name, level, pan, timing nudge, re-record and delete. */
export const PartDialog = {
    components: { Modal, Icon },
    // voice: the session's guideVoices entry for a template slot ({ kind, credit, why }), or null.
    // theirs: in a friend's project, the name of who sang this part (it's theirs to change), else null.
    props: { part: { type: Object, required: true }, shownName: { type: String, required: true }, color: { type: String, default: '' }, slot: Boolean, recorded: Boolean, harmonise: Boolean, voice: { type: Object, default: null }, recording: Boolean, theirs: { type: String, default: null } },
    emits: ['update', 'rerecord', 'delete', 'close', 'clear', 'practise', 'voice', 'trim', 'harmonise'],
    data() {
        return { name: this.shownName, field, label, primary, secondary };
    },
    template: `
        <!-- 107.png: Practise this part; the guide voice as radio cards; Mix (Volume, Pan, Timing with their values); Your take: Delete take, Trim, Re-record. -->
        <Modal :title="shownName" title-is-content :dot="color" @close="close">
            <div class="-mx-6 divide-y divide-line" data-part-panel>
                <div v-if="slot" class="px-6 py-5 first:pt-0"><button type="button" :class="[primary, 'w-full']" @click="$emit('practise')">{{ t('part.practise') }}</button></div>
                <fieldset v-if="voice && voice.versions?.length" class="space-y-2 px-6 py-5 first:pt-0" :disabled="recording">
                    <legend class="mb-2 text-sm font-semibold text-muted">{{ t('part.guideVoice') }}</legend>
                    <p v-if="blocked" class="text-sm text-muted">{{ voice.why === 'key' ? t('part.realVoiceOtherKey') : voice.why === 'version' ? t('part.realVoiceOtherVersion') : t('part.realVoiceOtherSpeed') }}</p>
                    <label v-for="v in voice.versions" :key="v.id" class="radio-card">
                        <input type="radio" name="guideVoice" class="radio mt-px" :checked="chosen(v)" :disabled="blocked" @change="$emit('voice', [v.id])" />
                        <span class="min-w-0">
                            <span class="block font-semibold text-cream">{{ v.credit ? t('part.sungBy', { name: v.credit }) : t('part.sungByAnonymous') }}</span>
                            <span class="text-sm text-muted">{{ v.default ? t('part.defaultVoice') : t('part.otherVoice') }}<template v-if="voice.why === 'failed' && voice.chosen.includes(v.id)"> · {{ t('part.realVoiceFailed') }}</template></span>
                        </span>
                    </label>
                    <label class="radio-card">
                        <input type="radio" name="guideVoice" class="radio mt-px" :checked="voice.chosen.includes('synth') || blocked" @change="$emit('voice', 'synth')" />
                        <span class="min-w-0"><span class="block font-semibold text-cream">{{ t('part.practiceVoice') }}</span><span class="text-sm text-muted">{{ t('part.practiceVoiceHint') }}</span></span>
                    </label>
                </fieldset>
                <div class="space-y-4 px-6 py-5 first:pt-0">
                    <p v-if="theirs !== null" class="rounded-2xl bg-raised p-3 text-sm text-cream">{{ t('together.theirPart', { name: theirs }) }}</p>
                    <label v-else-if="!slot" :class="label">{{ t('part.name') }}
                        <input v-model="name" :class="field" maxlength="40" @change="$emit('update', { name: name.trim() || part.name })" />
                    </label>
                    <p class="text-sm font-semibold text-muted">{{ t('part.mix') }}</p>
                    <label class="block">
                        <span class="flex items-baseline justify-between font-semibold text-cream">{{ t('part.volume') }}<span class="tabular-nums text-sm font-normal text-muted">{{ volumeLabel }}</span></span>
                        <input v-fill type="range" min="0" max="1.5" step="0.01" :value="part.volume" class="range mt-2 w-full" @input="$emit('update', { volume: +$event.target.value })" />
                    </label>
                    <label class="block">
                        <span class="flex items-baseline justify-between font-semibold text-cream">{{ t('part.pan') }}<span class="tabular-nums text-sm font-normal text-muted">{{ panLabel }}</span></span>
                        <input type="range" dir="ltr" min="-1" max="1" step="0.05" :value="part.pan" class="range mt-2 w-full [--p:0%]" @input="$emit('update', { pan: +$event.target.value })" @dblclick="$emit('update', { pan: 0 })" />
                    </label>
                    <label class="block">
                        <span class="flex items-baseline justify-between font-semibold text-cream">{{ t('part.timing') }}<span class="tabular-nums text-sm font-normal text-muted">{{ nudgeLabel }}</span></span>
                        <input type="range" dir="ltr" min="-60" max="60" step="1" :value="nudgeMs" class="range mt-2 w-full [--p:0%]" @input="nudge(+$event.target.value)" />
                        <span class="mt-1 block text-sm text-muted">{{ t('part.timingHint') }}</span>
                    </label>
                </div>
                <div class="flex flex-wrap items-center gap-3 px-6 pt-5">
                    <span class="me-auto text-sm font-semibold text-muted" :class="slot && recorded ? 'basis-full sm:basis-auto' : ''">{{ slot && recorded ? t('part.yourTake') : '' }}</span>
                    <template v-if="theirs !== null"><!-- someone else's: not theirs to clear or delete --></template>
                    <button v-else-if="slot && recorded" type="button" class="min-h-11 rounded-full px-3 text-sm font-semibold text-record hover:bg-record/10" @click="$emit('clear')">{{ t('part.clearTake') }}</button>
                    <button v-else-if="!slot" type="button" class="min-h-11 rounded-full px-3 text-sm font-semibold text-record hover:bg-record/10" @click="$emit('delete')">{{ t('part.delete') }}</button>
                    <button v-if="recorded && theirs === null" type="button" class="btn-secondary btn-sm" :disabled="recording" @click="$emit('trim')">{{ t('trim.button') }}</button>
                    <!-- Harmonise my song: an own song's take, its tune, with the other parts suggested under it. -->
                    <button v-if="harmonise && recorded && theirs === null" type="button" class="btn-secondary btn-sm" :disabled="recording" @click="$emit('harmonise')">{{ t('harmonise.button') }}</button>
                    <button v-if="theirs === null || slot" type="button" class="btn-secondary btn-sm" @click="$emit('rerecord')"><span class="inline-flex items-center gap-2"><span class="size-2 rounded-full bg-record" aria-hidden="true"></span> {{ theirs !== null ? t('together.singItToo') : !recorded ? t('practice.record') : t('part.rerecord') }}</span></button>
                </div>
            </div>
        </Modal>`,
    computed: {
        /** The real voices can't play in this key, speed or version: the practice voice does. */
        blocked() {
            return ['key', 'version', 'speed'].includes(this.voice?.why);
        },
        volumeLabel() {
            return t('part.percent', { percent: Math.round((this.part.volume ?? 1) * 100) });
        },
        panLabel() {
            const p = Math.round(this.part.pan * 100);
            return p === 0 ? t('part.panCentre') : p < 0 ? t('part.panLeft', { percent: -p }) : t('part.panRight', { percent: p });
        },
        nudgeMs() {
            return Math.round((this.part.nudgeSeconds ?? 0) * 1000);
        },
        nudgeLabel() {
            const ms = this.nudgeMs;
            return ms === 0 ? t('part.asRecorded') : ms < 0 ? t('part.earlier', { ms: -ms }) : t('part.later', { ms });
        },
    },
    methods: {
        /** A version that's chosen (or the default, when none is) and playing. */
        chosen(version) {
            return !this.blocked && !this.voice.chosen.includes('synth') && this.voice.chosen.includes(version.id);
        },
        nudge(ms) {
            this.$emit('update', { nudgeSeconds: ms / 1000 });
        },
        close() {
            if (this.name.trim() && this.name.trim() !== this.shownName) {
                this.$emit('update', { name: this.name.trim() });
            }
            this.$emit('close');
        },
    },
};

/**
 * Range check: sing your lowest, then your highest comfortable note. Suggests the part that suits
 * your voice, and the key that fits it.
 */
const STRIP_LOW = 36; // C2
const STRIP_HIGH = 84; // C6

export const RangeDialog = {
    components: { Modal, Icon, LevelMeter, MicPicker, PitchBar },
    props: { template: { type: Object, required: true }, transpose: { type: Number, default: 0 }, parts: { type: Array, default: null } },
    emits: ['use', 'close'],
    setup: () => ({ midiName, primary, secondary, transposeKey, templatePartName, micChoices, currentMicId, STRIP_NAMES: ['C2', 'C3', 'C4', 'C5', 'C6'] }), // i18n-ignore: note names
    // The same listening as Find your note (js/listening.js): hands-free, the voice a marker gliding
    // on a bar that zooms in, and each step's note the lowest (or highest) one held for a second.
    data: () => ({ step: 'low', low: null, high: null, best: null, shown: null, markerAlpha: 1, view: { ...WIDE }, hold: 0, heard: false, db: null, error: null, showMic: false }),
    template: `
        <!-- 108.png: the two steps as a bar; the prompt; the note big once held, with Got it; the hold
             filling; where your voice is on C2 to C6; the mic, Try again and Next. -->
        <Modal :title="t('range.findTitle')" wide @close="$emit('close')">
            <div class="space-y-5" data-range :data-step="step">
                <template v-if="step !== 'result'">
                    <div class="grid grid-cols-2 gap-2" data-range-steps>
                        <div>
                            <span class="block h-1 rounded-full bg-accent" aria-hidden="true"></span>
                            <p class="mt-2 flex items-baseline justify-between gap-2 text-sm">
                                <button v-if="step === 'high'" type="button" class="font-semibold text-cream hover:underline" @click="restart('low')">{{ t('range.lowestNote') }}</button>
                                <span v-else class="font-semibold text-cream" aria-current="step">{{ t('range.lowestNote') }}</span>
                                <span class="tabular-nums text-cream" translate="no">{{ low !== null ? midiName(low) : '' }}</span>
                            </p>
                        </div>
                        <div>
                            <span class="block h-1 rounded-full" :class="step === 'high' ? 'bg-accent' : 'bg-raised'" aria-hidden="true"></span>
                            <p class="mt-2 text-sm font-semibold" :class="step === 'high' ? 'text-cream' : 'text-muted'" :aria-current="step === 'high' ? 'step' : null">{{ t('range.highestNote') }}</p>
                        </div>
                    </div>
                    <p class="text-lg font-bold text-cream">{{ step === 'low' ? t('range.singLowest') : t('range.singHighest') }}</p>
                    <div class="text-center" aria-live="polite">
                        <p class="text-6xl font-bold tabular-nums" :class="best !== null ? 'text-good' : shown !== null ? 'text-cream' : 'text-muted/40'" translate="no" data-range-note>{{ best !== null ? midiName(best) : shown !== null ? midiName(Math.round(shown)) : '·' }}</p>
                        <p class="mt-1 min-h-5 text-sm font-semibold" :class="best !== null ? 'text-good' : 'text-muted'">{{ best !== null ? t('range.gotIt') : !heard ? t('listening.listeningShort') : t('listening.hearing') }}</p>
                    </div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-raised" aria-hidden="true"><div class="h-full rounded-full bg-accent transition-[width] duration-100" :style="{ width: hold * 100 + '%' }"></div></div>
                    <div>
                        <div class="relative h-8 rounded-[10px] bg-(--fill)" role="img" :aria-label="shown === null ? t('listening.barEmpty') : t('listening.barAt', { note: midiName(shown) })">
                            <span v-if="best !== null" class="absolute inset-y-0 w-px bg-good/70" :style="{ left: stripAt(best) + '%' }" aria-hidden="true"></span>
                            <span v-if="shown !== null" class="absolute inset-y-1.5 w-1 -translate-x-1/2 rounded-full bg-accent" :style="{ left: stripAt(shown) + '%', opacity: markerAlpha }" aria-hidden="true"></span>
                        </div>
                        <p class="mt-1 flex justify-between tabular-nums text-xs text-muted" translate="no" aria-hidden="true"><span v-for="n in STRIP_NAMES" :key="n">{{ n }}</span></p>
                    </div>
                    <LevelMeter :db="db" segments />
                    <p v-if="error" class="text-sm text-muted" role="status">{{ error }}</p>
                    <div class="-mx-6 flex flex-wrap items-center gap-3 border-t border-line px-6 pt-5">
                        <label class="me-auto flex min-w-0 items-center gap-1.5 text-sm text-muted">
                            <Icon name="mic" :size="16" class="shrink-0" />
                            <select class="min-h-11 max-w-52 truncate bg-transparent text-sm text-muted" :aria-label="t('studio.menu.mic')" :value="currentMicId()" @change="switchMic($event.target.value)">
                                <option v-for="m in micChoices()" :key="m.deviceId" :value="m.deviceId" translate="no">{{ m.label }}</option>
                            </select>
                        </label>
                        <button type="button" :class="secondary" @click="restart(step)">{{ t('range.tryAgain') }}</button>
                        <button type="button" :class="primary" :disabled="best === null" @click="take">{{ t('range.next') }}</button>
                    </div>
                </template>
                <template v-else>
                    <p class="text-cream">{{ t('range.yourRange', { low: midiName(low), high: midiName(high) }) }}</p>
                    <ul class="space-y-2 text-start">
                        <li v-for="(r, i) in ranked" :key="r.part.key" class="flex items-center justify-between gap-3 rounded-[18px] p-3" :class="i === 0 ? 'bg-(--sel-wash) ring-2 ring-(--selected)' : 'bg-(--fill)'">
                            <span>
                                <span class="block font-semibold text-cream">{{ templatePartName(r.part) }}<span v-if="i === 0" class="ms-2 text-xs text-part-4">{{ t('range.bestForYou') }}</span></span>
                                <span class="text-sm text-muted">{{ r.fits ? (r.transpose ? t('range.fitsIn', { key: keyName(r.transpose) }) : t('range.fitsAsWritten')) : t('range.stretch') }}</span>
                            </span>
                            <button type="button" class="shrink-0 rounded-full glass-bubble px-3 py-1.5 text-sm font-semibold text-cream hover:bg-raised" @click="$emit('use', { transpose: r.transpose, partId: r.part.key })">{{ t('range.useThis') }}</button>
                        </li>
                    </ul>
                    <button type="button" :class="secondary" @click="low = high = null; restart('low')">{{ t('mic.checkAgain') }}</button>
                </template>
            </div>
        </Modal>`,
    computed: {
        ranked() {
            // The parts to sing: an instrument part isn't one.
            return rankParts({ low: this.low, high: this.high }, (this.parts ?? this.template.parts).filter((p) => p.kind !== 'instrument'));
        },
    },
    async mounted() {
        try {
            if (!(await readyMic())) return;
        } catch (error) {
            micError(error, () => this.listen());
            return;
        }
        this.listen();
    },
    unmounted() {
        cancelAnimationFrame(this.frame);
    },
    methods: {
        keyName(shift) {
            const total = shift;
            return t('project.shiftedKey', { key: keyLabel(transposeKey(this.template.key, total)), shift: formatNumber(total, { signDisplay: 'always' }) });
        },
        listen() {
            cancelAnimationFrame(this.frame);
            let state = listener();
            let last = performance.now();
            let display = pitchDisplay();
            const tick = () => {
                const now = performance.now();
                const dt = Math.min(0.1, (now - last) / 1000);
                last = now;
                const frame = engine.inputFrame();
                if (frame && this.step !== 'result') {
                    const reading = trackFrame(frame, engine.sampleRate, { minClarity: LISTEN_CLARITY });
                    state = hear(state, reading, dt);
                    this.db = reading.loudness;
                    display = stepDisplay(display, { midi: reading.hz > 0 ? hzToMidi(reading.hz) : null, dt, mode: 'hold' });
                    this.shown = display.shown;
                    this.markerAlpha = display.alpha;
                    this.heard = this.shown !== null;
                    this.hold = Math.min(1, state.held / RANGE_HOLD_SECONDS);
                    this.view = easeWindow(this.view, targetWindow(state.note, state.held), dt, { still: reducedMotion() });
                    // A note held for a second counts: the lowest (or highest) of them is this step's.
                    if (state.held >= RANGE_HOLD_SECONDS && state.note !== null) {
                        const note = Math.round(state.note);
                        this.best = this.best === null ? note : this.step === 'low' ? Math.min(this.best, note) : Math.max(this.best, note);
                        this.error = null;
                    }
                }
                this.frame = requestAnimationFrame(tick);
            };
            this.frame = requestAnimationFrame(tick);
        },
        /** Where a note sits on the strip under the note, C2 to C6, as a percentage. */
        stripAt(midi) {
            return Math.max(0, Math.min(100, ((midi - STRIP_LOW) / (STRIP_HIGH - STRIP_LOW)) * 100));
        },
        async switchMic(deviceId) {
            try {
                await chooseMic(deviceId);
            } catch (error) {
                micError(error);
            }
        },
        restart(step) {
            this.step = step;
            this.best = null;
            this.error = null;
            if (step === 'low') this.low = null;
        },
        take() {
            if (this.best === null) {
                this.error = t('range.noSteadyNote');
                return;
            }
            if (this.step === 'low') {
                this.low = this.best;
                this.step = 'high';
                this.best = null;
            } else {
                this.high = Math.max(this.best, this.low + 1);
                this.step = 'result';
                setRange(this.low, this.high);
            }
        },
    },
};

/**
 * A song's Info (Hillel: simpler): what it is, its level, key and tempo, its parts and ranges (the
 * melody tagged), who wrote and sings it, and Start; How many singers, Check my range and Make a copy
 * behind More options, and Play (the harmony from its guides) beside Start. A Plus song without Plus:
 * its clip and the way to Plus.
 */
export const TemplateDialog = {
    components: { Modal, Icon },
    // locked: a Plus song without Plus (the catalogue's summary: no notes): its clip, and the way to Plus.
    props: { template: { type: Object, required: true }, used: Number, limit: { type: Number, default: null }, previewing: Boolean, preparing: Boolean, credits: { type: Array, default: () => [] }, locked: Boolean, singers: { type: Number, default: 4 } },
    emits: ['start', 'preview', 'close', 'plus', 'copy', 'singers'],
    setup: () => ({ midiName, formatTime, formatNumber, primary, secondary, singerOptions, templatePartName, keyLabel, DIFFICULTY, formatList }),
    template: `
        <Modal :title="template.title" title-is-content wide @close="$emit('close')">
            <div class="space-y-5">
                <p class="text-cream" translate="no">{{ template.description }}</p>
                <p v-if="locked" class="font-semibold text-part-2">{{ t('plus.moment.plusSong') }}</p>
                <p class="text-sm text-muted">{{ t(DIFFICULTY[template.difficulty] ?? DIFFICULTY.beginner) }} · {{ t('studio.meta.key', { key: keyLabel(template.key) }) }} · {{ t('studio.meta.bpm', { bpm: template.tempo }) }}<template v-if="!template.summary"> · {{ formatTime(seconds) }}</template></p>
                <p v-if="template.credits" class="text-xs text-muted">{{ [t('templates.credits', { words: template.credits.words, tune: template.credits.tune, arrangement: template.credits.arrangement }), template.reviewed ? '' : t('templates.draftNote')].filter(Boolean).join(' ') }}</p>
                <p v-if="credits.length" class="text-sm text-cream">{{ t('templates.sungBy', { names: formatList(credits) }) }}</p>
                <div v-if="locked" class="flex flex-wrap items-center gap-3">
                    <button v-if="template.previewUrl" type="button" :class="secondary" @click="$emit('preview')"><span class="inline-flex items-center gap-2"><Icon :name="previewing ? 'stop' : 'play'" :size="16" />{{ previewing ? t('studio.stop') : t('templates.previewClip') }}</span></button>
                    <button type="button" :class="[primary, 'ms-auto']" @click="$emit('plus')">{{ t('templates.onPlus') }}</button>
                </div>
                <div v-else class="space-y-3">
                    <!-- Hillel: "add back the 2/3/4 part toggle": how many sing it, from the song's own voicings; Start sings that many. -->
                    <div v-if="singerOptions(template).length > 1" class="flex flex-wrap items-center justify-between gap-3" data-song-singers>
                        <span :id="'singers-' + $.uid" class="text-sm font-semibold text-cream">{{ t('templates.singers.question') }}</span>
                        <div class="seg" role="radiogroup" :aria-labelledby="'singers-' + $.uid">
                            <button v-for="n in singerOptions(template)" :key="n" type="button" role="radio" class="seg-option tap-44" :aria-checked="singers === n ? 'true' : 'false'" @click="$emit('singers', n)">{{ formatNumber(n) }}</button>
                        </div>
                    </div>
                    <!-- Hillel: "show 3 buttons: play, edit and start. edit is the make a copy option." The same width, in a row. -->
                    <div class="grid grid-cols-3 gap-2 sm:gap-3">
                        <button type="button" :class="[secondary, 'w-full justify-center']" :aria-pressed="previewing ? 'true' : 'false'" data-song-play @click="$emit('preview')"><span class="inline-flex items-center gap-2"><Icon :name="previewing ? 'stop' : 'play'" :size="16" />{{ preparing ? t('templates.preparing') : previewing ? t('studio.stop') : t('templates.play') }}</span></button>
                        <button type="button" :class="[secondary, 'w-full justify-center']" :aria-label="t('templates.editLabel', { title: template.title })" data-song-edit @click="$emit('copy')">{{ t('template.edit') }}</button>
                        <button type="button" :class="[primary, 'w-full justify-center']" :disabled="full" data-song-start @click="$emit('start')">{{ t('templates.start') }}</button>
                    </div>
                </div>
                <p v-if="full" class="text-sm text-muted">{{ t('templates.atLimit', { limit }) }} <a href="/plus?from=project_limit" class="link" @click.prevent="$emit('plus')">{{ t('export.seePlus') }}</a></p>
            </div>
        </Modal>`,
    computed: {
        seconds() {
            return this.template.summary ? 0 : (lengthBeats(this.template) * 60) / this.template.tempo;
        },
        /** At the free plan's limit (Plus has none). */
        full() {
            return this.limit !== null && this.used >= this.limit;
        },
    },
};
