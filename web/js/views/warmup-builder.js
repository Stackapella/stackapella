// Making a warm-up (docs/warmup-builder-plan.md, the teacher's side): /warmup with no code. Start
// from one of ours or tap notes in (do to the do above, and so, la, ti below), tap a note in the
// pattern to take it out; then the sound, the speed, where it starts against the singer's comfortable
// note and how far it climbs. Hear it plays it in your own range; Share link gives the link
// (/warmup#<code>) with the device's share sheet, or copies it. No account and no server: the
// warm-up is the link. Only Settings' Practice row and the address lead here.

import { qrFor } from '../qr.js';
import { Exercise } from '../components/exercise.js';
import { Segmented, Stepper } from '../components/controls.js';
import { toast } from '../components/ui.js';
import { localized, t, tk } from '../i18n.js';
import { allLessons, learning } from '../stores/learning.js';
import { LIMITS, SOUNDS, SPEEDS, decodeWarmup, encodeWarmup, warmupExercise, warmupFromExercise, warmupRounds, warmupSeconds } from '../warmup.js';

const DEFAULT_HOME = 57;
// The notes to tap, as half steps from do: a major scale to the do above, and its top three below.
const NOTES = [
    { step: -5, name: 'so', low: true },
    { step: -3, name: 'la', low: true },
    { step: -1, name: 'ti', low: true },
    { step: 0, name: 'do' },
    { step: 2, name: 're' },
    { step: 4, name: 'mi' },
    { step: 5, name: 'fa' },
    { step: 7, name: 'so' },
    { step: 9, name: 'la' },
    { step: 11, name: 'ti' },
    { step: 12, name: 'do', high: true },
];
const PRESETS = [
    { key: 'five', steps: [0, 2, 4, 5, 7, 5, 4, 2, 0] },
    { key: 'three', steps: [0, 2, 4, 2, 0] },
    { key: 'arpeggio', steps: [0, 4, 7, 12, 7, 4, 0] },
    { key: 'nine', steps: [0, 2, 4, 5, 7, 9, 11, 12, 11, 9, 7, 5, 4, 2, 0] },
];
const PRESET_NAMES = { five: tk('warmup.make.preset.five'), three: tk('warmup.make.preset.three'), arpeggio: tk('warmup.make.preset.arpeggio'), nine: tk('warmup.make.preset.nine') };
const SOUND_LABELS = { ah: tk('warmup.make.sounds.ah'), ee: tk('warmup.make.sounds.ee'), oo: tk('warmup.make.sounds.oo'), oh: tk('warmup.make.sounds.oh'), hum: tk('warmup.make.sounds.hum'), trill: tk('warmup.make.sounds.trill') };
const SPEED_LABELS = { slow: tk('warmup.make.slow'), medium: tk('warmup.make.medium'), quick: tk('warmup.make.quick') };

const noteName = (step) => NOTES.find((n) => n.step === step) ?? { name: '?' };
const speedOf = (tempo) => Object.keys(SPEEDS).reduce((best, key) => (Math.abs(SPEEDS[key] - tempo) < Math.abs(SPEEDS[best] - tempo) ? key : best), 'medium');

// The warm-up being made, kept while the page is open, so trying it as a student and coming Back
// finds it as it was.
let draftCode = null;

function fresh() {
    const kept = draftCode ? decodeWarmup(draftCode) : null;
    if (!kept) return { steps: [...PRESETS[0].steps], preset: 'five', sound: 'ah', speed: 'medium', start: 0, climb: 4, name: '' };
    const preset = PRESETS.find((p) => p.steps.join() === kept.steps.join())?.key ?? null;
    return { steps: kept.steps, preset, sound: kept.sound, speed: speedOf(kept.tempo), start: kept.start, climb: kept.climb, name: kept.name };
}

/** Copies the link, and says so (or says to copy it by hand). */
async function copyLink(link) {
    try {
        await navigator.clipboard.writeText(link);
        toast(t('warmup.make.copied'));
    } catch (error) {
        console.warn('Copy failed', error);
        toast(t('warmup.make.copyFailed'), { tone: 'error', seconds: 8 });
    }
}

export const WarmupBuilder = {
    components: { Exercise, Segmented, Stepper },
    data: () => ({ ...fresh(), hearing: false, link: null, qr: null, canShare: typeof navigator.share === 'function' }),
    setup: () => ({ NOTES, PRESETS, PRESET_NAMES, LIMITS }),
    template: `
        <div class="mt-2 space-y-5" data-warmup-builder>
            <section class="card space-y-4 p-5" aria-labelledby="warmup-pattern">
                <div>
                    <h2 id="warmup-pattern" class="text-lg font-bold text-cream">{{ t('warmup.make.pattern') }}</h2>
                    <p class="text-sm text-muted">{{ t('warmup.make.patternHint', { count: LIMITS.notes }) }}</p>
                </div>
                <div class="flex flex-wrap gap-2" role="group" :aria-label="t('warmup.make.startFrom')">
                    <button v-for="p in PRESETS" :key="p.key" type="button" class="filter-chip min-h-11" :aria-pressed="preset === p.key ? 'true' : 'false'" data-warmup-preset @click="usePreset(p)">{{ t(PRESET_NAMES[p.key]) }}</button>
                </div>
                <!-- Or a lesson's exercise as the start (slice 5): the course's sung patterns the builder can make. -->
                <label v-if="fromLessons.length" class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm" data-warmup-from-lesson>
                    <span class="text-muted">{{ t('warmup.make.fromLesson') }}</span>
                    <select class="input w-auto max-w-full" :value="''" @change="useLesson($event.target.value); $event.target.value = ''">
                        <option value="" disabled>{{ t('warmup.make.pickLesson') }}</option>
                        <option v-for="l in fromLessons" :key="l.key" :value="l.key">{{ l.title }}</option>
                    </select>
                </label>
                <ol class="flex min-h-14 flex-wrap items-center gap-2 rounded-2xl border border-line p-2" :aria-label="t('warmup.make.yourPattern')" data-warmup-pattern>
                    <li v-for="(step, i) in steps" :key="i">
                        <button type="button" class="filter-chip min-h-11 min-w-11 justify-center" :aria-label="t('warmup.make.remove', { number: i + 1, note: label(step) })" @click="remove(i)">{{ label(step) }}</button>
                    </li>
                    <li v-if="!steps.length" class="px-2 text-sm text-muted">{{ t('warmup.make.empty') }}</li>
                </ol>
                <div class="space-y-2">
                    <div class="flex flex-wrap gap-2" role="group" :aria-label="t('warmup.make.addNote')" data-warmup-notes>
                        <button v-for="n in NOTES" :key="n.step" type="button" class="filter-chip min-h-11 min-w-11 flex-col justify-center gap-0 border border-line" :aria-label="t('warmup.make.add', { note: label(n.step) })" :disabled="steps.length >= LIMITS.notes" @click="add(n.step)">
                            <span translate="no">{{ n.name }}</span><span v-if="n.low || n.high" class="text-[0.625rem] font-medium text-muted" aria-hidden="true">{{ n.low ? t('warmup.make.low') : t('warmup.make.high') }}</span>
                        </button>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-muted tabular-nums" role="status">{{ t('warmup.make.count', { count: steps.length, most: LIMITS.notes }) }}</p>
                        <button v-if="steps.length" type="button" class="btn-ghost btn-sm" @click="clear">{{ t('warmup.make.clear') }}</button>
                    </div>
                </div>
            </section>

            <section class="card divide-y divide-line overflow-hidden" :aria-label="t('warmup.make.how')">
                <div class="space-y-2 p-5">
                    <p id="warmup-sound" class="font-semibold text-cream">{{ t('warmup.make.sound') }}</p>
                    <Segmented v-model="sound" :options="soundOptions" labelledby="warmup-sound" data-warmup-sound />
                </div>
                <div class="space-y-2 p-5">
                    <p id="warmup-speed" class="font-semibold text-cream">{{ t('warmup.make.speed') }}</p>
                    <Segmented v-model="speed" :options="speedOptions" labelledby="warmup-speed" />
                </div>
                <div class="flex flex-wrap items-center justify-between gap-4 p-5">
                    <div class="min-w-0 flex-1"><p class="font-semibold text-cream">{{ t('warmup.make.start') }}</p><p class="text-sm text-muted">{{ startWords }}</p></div>
                    <Stepper v-model="start" :min="LIMITS.start[0]" :max="LIMITS.start[1]" :label="t('warmup.make.start')" :decrease="t('keyControl.lower')" :increase="t('keyControl.higher')" data-warmup-start />
                </div>
                <div class="flex flex-wrap items-center justify-between gap-4 p-5">
                    <div class="min-w-0 flex-1"><p class="font-semibold text-cream">{{ t('warmup.make.climb') }}</p><p class="text-sm text-muted">{{ climbWords }}</p></div>
                    <Stepper v-model="climb" :min="LIMITS.climb[0]" :max="LIMITS.climb[1]" :label="t('warmup.make.climb')" :decrease="t('warmup.make.fewer')" :increase="t('warmup.make.more')" data-warmup-climb />
                </div>
                <label class="block space-y-2 p-5">
                    <span class="block font-semibold text-cream">{{ t('warmup.make.name') }}</span>
                    <input v-model="name" class="input" type="text" :maxlength="LIMITS.name" :placeholder="t(PRESET_NAMES.five)" autocomplete="off" data-warmup-name-input />
                    <span class="block text-sm text-muted">{{ t('warmup.make.nameHint') }}</span>
                </label>
            </section>

            <Exercise v-if="warmup" ref="exercise" bare :key="code + (hearing ? ':hear' : '')" :exercise="preview" :base="home + warmup.start" mode="listen" :color="paint('#4ccfb0')" :rest-beats="0" @done="hearing = false" />
            <p class="text-sm text-muted">{{ t('warmup.safety') }} {{ warmup ? t('warmup.make.lasts', { rounds: rounds, minutes }) : '' }}</p>
            <div class="flex gap-3">
                <button type="button" class="btn-secondary flex-1 sm:flex-none" :disabled="!warmup" @click="hear">{{ hearing ? t('studio.stop') : t('warmup.make.hear') }}</button>
                <button type="button" class="btn-primary flex flex-1 items-center justify-center gap-2 sm:flex-none" :disabled="!warmup" data-warmup-share @click="share">{{ t('warmup.make.share') }}</button>
            </div>
            <section v-if="link" class="card space-y-3 p-5" aria-live="polite" data-warmup-link>
                <label class="block font-semibold text-cream" for="warmup-link">{{ t('warmup.make.linkTitle') }}</label>
                <input id="warmup-link" class="input text-xs" readonly :value="link" translate="no" @focus="$event.target.select()" />
                <div class="flex flex-wrap gap-3">
                    <button type="button" class="btn-secondary btn-sm" @click="copy">{{ t('warmup.make.copy') }}</button>
                    <button type="button" class="btn-ghost btn-sm" data-warmup-try @click="tryIt">{{ t('warmup.make.try') }}</button>
                </div>
                <p class="text-sm text-muted">{{ t('warmup.make.linkHint') }}</p>
                <!-- For a class: the link as a QR code to put on the board (Hillel: yes). White, so it scans in either skin. -->
                <figure v-if="qr" class="flex flex-col items-center gap-2 border-t border-line pt-4" data-warmup-qr>
                    <svg :viewBox="'-2 -2 ' + (qr.size + 4) + ' ' + (qr.size + 4)" class="size-44 rounded-md bg-white" role="img" :aria-label="t('warmup.make.qr')" shape-rendering="crispEdges"><path :d="qr.path" fill="#000" /></svg>
                    <figcaption class="text-sm text-muted">{{ t('warmup.make.qrHint') }}</figcaption>
                </figure>
            </section>
        </div>`,
    computed: {
        /** The free course's sung exercises the builder can make, one a lesson (its first that fits), in course order. */
        fromLessons() {
            const seen = new Set();
            return allLessons().flatMap(({ lesson }) => {
                for (const step of lesson.steps ?? []) {
                    const warmup = step.exercise && (step.kind === 'play' || step.kind === 'try') ? warmupFromExercise(step.exercise) : null;
                    if (!warmup || seen.has(warmup.steps.join())) continue;
                    seen.add(warmup.steps.join());
                    return [{ key: lesson.id, title: localized(lesson.title), warmup }];
                }
                return [];
            });
        },
        home: () => learning.progress.home ?? DEFAULT_HOME,
        soundOptions: () => SOUNDS.map((value) => ({ value, label: t(SOUND_LABELS[value]) })),
        speedOptions: () => Object.keys(SPEEDS).map((value) => ({ value, label: t(SPEED_LABELS[value]) })),
        draft() {
            return { steps: this.steps, sound: this.sound, tempo: SPEEDS[this.speed], start: this.start, climb: this.climb, name: this.name };
        },
        /** The link's code: null until there's a note to sing. */
        code() {
            return this.steps.length ? encodeWarmup(this.draft) : null;
        },
        warmup() {
            return this.code ? { ...this.draft, name: this.name.trim() } : null;
        },
        preview() {
            return { ...warmupExercise(this.warmup), turn: false };
        },
        rounds() {
            return this.warmup ? warmupRounds(this.warmup).length : 0;
        },
        minutes() {
            return Math.max(1, Math.round(warmupSeconds(this.warmup, this.rounds) / 60));
        },
        startWords() {
            if (this.start === 0) return t('warmup.make.startOn');
            const count = Math.abs(this.start);
            return this.start < 0 ? t('warmup.make.startBelow', { count }) : t('warmup.make.startAbove', { count });
        },
        climbWords() {
            return this.climb === 0 ? t('warmup.make.climbNone') : t('warmup.make.climbUp', { count: this.climb, rounds: this.climb * 2 + 1 });
        },
    },
    watch: {
        code(code) {
            draftCode = code;
            // A changed warm-up is a different link: the old one stays with whoever has it.
            this.link = null;
            if (this.hearing) this.stopHearing();
        },
    },
    methods: {
        label(step) {
            const n = noteName(step);
            return n.low ? t('warmup.make.lowNote', { note: n.name }) : n.high ? t('warmup.make.highNote', { note: n.name }) : n.name;
        },
        /** A lesson's exercise as the start: its notes, sound, speed and start; named after the lesson when the name is free. */
        useLesson(key) {
            const from = this.fromLessons.find((l) => l.key === key);
            if (!from) return;
            const w = from.warmup;
            const named = !this.name.trim() || PRESETS.some((q) => t(PRESET_NAMES[q.key]) === this.name.trim()) || this.fromLessons.some((l) => l.title === this.name.trim());
            Object.assign(this, { steps: [...w.steps], sound: w.sound, speed: speedOf(w.tempo), start: w.start, preset: null });
            if (named) this.name = from.title.slice(0, LIMITS.name);
        },
        usePreset(p) {
            const named = !this.name.trim() || PRESETS.some((q) => t(PRESET_NAMES[q.key]) === this.name.trim());
            this.steps = [...p.steps];
            this.preset = p.key;
            if (named) this.name = t(PRESET_NAMES[p.key]);
        },
        add(step) {
            if (this.steps.length >= LIMITS.notes) return;
            this.steps = [...this.steps, step];
            this.preset = null;
        },
        remove(i) {
            this.steps = this.steps.filter((_, j) => j !== i);
            this.preset = null;
        },
        clear() {
            this.steps = [];
            this.preset = null;
        },
        async hear() {
            if (this.hearing) {
                await this.stopHearing();
                return;
            }
            this.hearing = true;
            await this.$nextTick();
            this.$refs.exercise?.start();
        },
        async stopHearing() {
            this.hearing = false;
            await this.$refs.exercise?.stop();
        },
        async share() {
            if (!this.code) return;
            this.link = `${location.origin}/warmup#${this.code}`;
            this.showQr(this.link);
            if (!this.canShare) {
                await copyLink(this.link);
                return;
            }
            const title = this.name.trim() || t('warmup.untitled');
            try {
                await navigator.share({ title, text: t('warmup.make.shareText', { name: title }), url: this.link });
            } catch (error) {
                if (error?.name !== 'AbortError') console.warn('Share failed', error);
            }
        },
        copy() {
            copyLink(this.link);
        },
        /** The link's QR code, drawn once the small library has loaded (offline, there's simply none). */
        async showQr(link) {
            this.qr = null;
            try {
                const qr = await qrFor(link);
                if (this.link === link) this.qr = qr;
            } catch (error) {
                console.info('No QR code', error?.message ?? error);
            }
        },
        /** Opens the link here, as a student would (the page reads the fragment); Back returns to the draft. */
        tryIt() {
            location.hash = this.code;
        },
    },
};
