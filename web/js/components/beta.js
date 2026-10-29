// The studio's beta mode (docs/beta-singers-plan.md, "Setup" and "The quality check"): the
// banner saying what's being recorded for Hillel (with his note pinned to its phrase), the
// 10-second sound check, and the report before sending: the take's score and the app's verdict
// against the bar Hillel sets for a guide, kind and honest, with the phrases to work on.

import { audio, engine, openMic, unlock } from '../audio.js';
import { formatList, t, tk } from '../i18n.js';
import { trackInWorker } from '../pitchtrack.js';
import { checkTake, DEFAULT_BAR, levelChecks, phrasesToFix, TRIES_BEFORE_SUGGESTING, verdict } from '../quality.js';
import { betaTries, countTry } from '../beta.js';
import { local } from '../storage.js';
import { showMicHelp } from '../mic-help.js';
import { Field, Notice, StatusChip } from './form.js';
import { Icon } from './icon.js';
import { Modal } from './ui.js';

const SOUND_CHECK_SECONDS = 10;
const QUIET_SECONDS = 2; // first, to hear the room on its own

const CHECKS = {
    levelGood: { text: tk('beta.check.levelGood'), fix: null },
    inTune: { text: tk('beta.check.inTune'), fix: null },
    silent: { text: tk('beta.check.silent'), fix: tk('beta.fix.silent') },
    quiet: { text: tk('beta.check.quiet'), fix: tk('beta.fix.quiet') },
    clipping: { text: tk('beta.check.clipping'), fix: tk('beta.fix.clipping') },
    noise: { text: tk('beta.check.noise'), fix: tk('beta.fix.noise') },
    bleed: { text: tk('beta.check.bleed'), fix: tk('beta.fix.bleed') },
    unheard: { text: tk('beta.check.unheard'), fix: tk('beta.fix.unheard') },
    pitch: { text: tk('beta.check.pitch'), fix: tk('beta.fix.pitch') },
    late: { text: tk('beta.check.late'), fix: tk('beta.fix.late') },
    early: { text: tk('beta.check.early'), fix: tk('beta.fix.early') },
};
// Why a phrase is under the bar, said kindly.
const REASON = { flat: tk('beta.reason.flat'), sharp: tk('beta.reason.sharp'), mixed: tk('beta.reason.mixed'), late: tk('beta.reason.late'), early: tk('beta.reason.early'), uneven: tk('beta.reason.uneven') };
// The verdict: an icon and words, never colour alone.
const VERDICT = {
    ready: { icon: 'check', tone: 'text-good', box: 'border-good/40 bg-good/10', title: tk('beta.verdict.ready'), body: tk('beta.verdict.readyBody') },
    close: { icon: 'loop', tone: 'text-warn', box: 'border-warn/40 bg-warn/10', title: tk('beta.verdict.close'), body: tk('beta.verdict.closeBody') },
    notReady: { icon: 'alert', tone: 'text-muted', box: 'border-edge bg-raised', title: tk('beta.verdict.notReady'), body: tk('beta.verdict.notReadyBody') },
};

/** Whether this device and mic have had their sound check. */
/** A beta part's status, as My parts and the home show it. */
export const BETA_STATUS = {
    to_record: { label: tk('beta.status.to_record'), tone: 'muted' },
    submitted: { label: tk('beta.status.submitted'), tone: 'info' },
    in_review: { label: tk('beta.status.in_review'), tone: 'info' },
    retake: { label: tk('beta.status.retake'), tone: 'warn' },
    guide: { label: tk('beta.status.guide'), tone: 'good' },
    accepted: { label: tk('beta.status.accepted'), tone: 'good' },
    rejected: { label: tk('beta.status.rejected'), tone: 'muted' },
};

export function soundChecked() {
    return !!audio.mic && local.get('stackapella.beta.soundCheck') === audio.mic.label;
}

/** What's being recorded for Hillel, his note on the phrase he pinned it to, and Check and send. */
export const BetaBanner = {
    components: { Icon },
    props: { partName: String, song: String, note: String, phrase: Number, looping: Boolean, canSend: Boolean, bar: { type: Object, default: () => DEFAULT_BAR } },
    emits: ['loop', 'send', 'parts', 'tell'],
    template: `
        <div class="border-b border-line bg-part-4/10 px-4 py-2.5 text-sm text-cream">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <span class="chip-accent">{{ t('beta.badge') }}</span>
                <span>{{ t('beta.studio.recordingFor', { part: partName, song }) }}</span>
                <a href="/beta" class="btn-go" @click.prevent="$emit('parts')">{{ t('beta.parts.title') }}</a>
                <button type="button" class="btn-ghost btn-sm" @click="$emit('tell')">{{ t('beta.tell.button') }}</button>
                <button v-if="canSend" type="button" class="btn-primary btn-sm ms-auto" @click="$emit('send')">{{ t('beta.studio.checkAndSend') }}</button>
            </div>
            <p class="mt-1.5 text-muted">{{ t('beta.bar.line', { pitch: bar.pitchPercent, timing: bar.timingPercent }) }}</p>
            <p v-if="note" class="mt-2 flex flex-wrap items-start gap-x-2 gap-y-1">
                <span class="flex min-w-0 flex-1 items-start gap-2"><Icon name="info" :size="16" class="mt-0.5 shrink-0 text-warn" /><span><span class="font-semibold">{{ t('beta.parts.hillelSays') }}</span> <span translate="no">{{ note }}</span></span></span>
                <button v-if="phrase !== null && phrase !== undefined" type="button" class="btn-sm" :class="looping ? 'btn-secondary' : 'btn-ghost'" :aria-pressed="looping ? 'true' : 'false'" data-loop-phrase @click="$emit('loop', phrase)"><Icon name="loop" :size="14" /> {{ t('beta.studio.loopPhrase', { number: phrase + 1 }) }}</button>
            </p>
        </div>`,
};

/** The sound check (once per device and mic): sing for 10 seconds, hear it back, see the level checks. */
export const SoundCheck = {
    components: { Modal, Notice, Icon },
    emits: ['close', 'done'],
    data: () => ({ step: 'ready', level: 0, result: null, samples: null, error: null, left: SOUND_CHECK_SECONDS, hush: true }),
    template: `
        <Modal :title="t('beta.sound.title')" :dismissable="step !== 'listening'" @close="$emit('close')">
            <div class="space-y-4">
                <template v-if="step === 'ready'">
                    <p class="text-cream">{{ t('beta.sound.intro') }}</p>
                    <Notice v-if="error" tone="bad">{{ error }}</Notice>
                    <div class="flex justify-end gap-3"><button type="button" class="btn-secondary" @click="$emit('close')">{{ t('common.cancel') }}</button><button type="button" class="btn-primary" @click="listen">{{ t('beta.sound.start') }}</button></div>
                </template>
                <template v-else-if="step === 'listening'">
                    <p class="text-cream" role="status">{{ hush ? t('beta.sound.hush') : t('beta.sound.listening', { seconds: left }) }}</p>
                    <div class="h-2 overflow-hidden rounded-full bg-ink"><div class="h-full rounded-full bg-part-4 transition-[width] duration-75" :style="{ width: Math.min(100, level * 140) + '%' }"></div></div>
                </template>
                <template v-else>
                    <ul class="space-y-2">
                        <li v-for="item in findings" :key="item.text" class="flex gap-2 text-sm" :class="item.good ? 'text-cream' : 'text-cream'">
                            <Icon :name="item.good ? 'check' : 'alert'" :size="18" :class="item.good ? 'text-good' : 'text-warn'" class="mt-0.5 shrink-0" />
                            <span>{{ item.text }}<span v-if="item.fix" class="block text-muted">{{ item.fix }}</span></span>
                        </li>
                    </ul>
                    <div class="flex flex-wrap justify-end gap-3">
                        <button type="button" class="btn-secondary" @click="playBack"><Icon name="play" :size="16" /> {{ t('beta.sound.hear') }}</button>
                        <button type="button" class="btn-secondary" @click="step = 'ready'">{{ t('beta.sound.again') }}</button>
                        <button type="button" class="btn-primary" @click="done">{{ t('beta.sound.done') }}</button>
                    </div>
                </template>
            </div>
        </Modal>`,
    computed: {
        findings() {
            const r = this.result;
            if (!r) return [];
            const out = [];
            const bad = r.silent || r.quiet || r.clipping || r.noise !== 'low';
            if (!bad) out.push({ good: true, text: t('beta.sound.good') });
            for (const code of ['silent', 'quiet', 'clipping']) if (r[code]) out.push({ good: false, text: t(CHECKS[code].text), fix: t(CHECKS[code].fix) });
            if (r.noise !== 'low') out.push({ good: false, text: t(CHECKS.noise.text), fix: t(CHECKS.noise.fix) });
            return out;
        },
    },
    unmounted() {
        cancelAnimationFrame(this.frame);
    },
    methods: {
        /** Records 2 quiet seconds and 10 sung ones from the mic, on the audio clock, with nothing playing. */
        async listen() {
            this.error = null;
            try {
                await unlock();
                if (!(await openMic())) return;
            } catch (error) {
                showMicHelp(error, () => this.listen());
                return;
            }
            this.step = 'listening';
            const sr = engine.sampleRate;
            const starts = engine.ctx.currentTime + 0.2;
            const from = Math.ceil(starts * sr);
            const ends = starts + QUIET_SECONDS + SOUND_CHECK_SECONDS;
            this.hush = true;
            const meter = () => {
                this.level = engine.inputLevel();
                this.hush = engine.ctx.currentTime < starts + QUIET_SECONDS;
                this.left = Math.max(0, Math.ceil(ends - engine.ctx.currentTime));
                this.frame = requestAnimationFrame(meter);
            };
            meter();
            try {
                const take = await engine.startCapture(from, from + Math.round((QUIET_SECONDS + SOUND_CHECK_SECONDS) * sr), null);
                this.samples = take.samples.subarray(Math.round(QUIET_SECONDS * take.sampleRate)); // the singing, to hear back
                this.sampleRate = take.sampleRate;
                this.result = levelChecks(take.samples, take.sampleRate, { quiet: [[0.2, QUIET_SECONDS - 0.2]] });
                this.step = 'result';
            } catch (error) {
                console.error('Sound check interrupted', error);
                this.error = t('beta.sound.interrupted');
                this.step = 'ready';
            } finally {
                cancelAnimationFrame(this.frame);
            }
        },
        playBack() {
            if (this.samples) engine.preview(this.samples, this.sampleRate);
        },
        done() {
            local.set('stackapella.beta.soundCheck', audio.mic?.label ?? '');
            this.$emit('done');
        },
    },
};

/**
 * Before sending: the app's verdict against the bar (Ready to send, Close, or Not ready yet), the
 * take's pitch and timing as percentages beside the bar, the phrases keeping it from the bar (each
 * with Loop, then Record again), the level checks, "Hear it as learners will", and a note to
 * Hillel. Send is offered only when the take meets the bar; after a few tries under it, a kind word
 * about another part or the course.
 */
export const BetaReport = {
    components: { Modal, Notice, StatusChip, Field, Icon },
    props: {
        samples: { type: Object, required: true }, // Float32Array, the take as recorded
        sampleRate: { type: Number, required: true },
        notes: { type: Array, required: true },
        beatSeconds: { type: Number, required: true },
        startSeconds: { type: Number, default: 0 },
        phrases: { type: Array, required: true }, // [startBeat, endBeat]
        syllables: { type: Array, default: () => [] }, // the part's lyrics, { beat, text }, a word begun by another part included
        timing: { type: String, default: 'measured' },
        bar: { type: Object, default: () => DEFAULT_BAR },
        assignmentId: { type: String, default: null },
        hearing: Boolean, // the take is playing in the mix
        busy: Boolean,
        canUsePrevious: Boolean, // the take before this one can come back
    },
    emits: ['close', 'send', 'again', 'loop', 'hear', 'previous', 'tell', 'course'],
    data: () => ({ result: null, note: '', tries: null }),
    setup: () => ({ CHECKS, REASON, VERDICT }),
    template: `
        <Modal :title="t('beta.report.title')" wide :dismissable="!busy" @close="$emit('close')">
            <p class="text-muted" :class="result && 'sr-only'" role="status">{{ result ? t('beta.report.checked') : t('beta.report.checking') }}</p>
            <div v-if="result" class="space-y-5" data-verdict :data-verdict-is="verdictIs">
                <section class="flex items-start gap-3 rounded-2xl border p-4" :class="VERDICT[verdictIs].box" aria-live="polite">
                    <Icon :name="VERDICT[verdictIs].icon" :size="28" class="mt-0.5 shrink-0" :class="VERDICT[verdictIs].tone" />
                    <div>
                        <h3 class="text-xl font-extrabold text-cream">{{ t(VERDICT[verdictIs].title) }}</h3>
                        <p class="mt-1 text-cream">{{ unheard ? t('beta.verdict.unheardBody') : t(VERDICT[verdictIs].body) }}</p>
                    </div>
                </section>
                <dl v-if="!unheard" class="grid grid-cols-2 gap-3">
                    <div v-for="score in shownScores" :key="score.key" class="rounded-2xl bg-raised p-4">
                        <dt class="text-sm font-semibold text-muted">{{ t(score.label) }}</dt>
                        <dd class="mt-1 flex items-center gap-2 text-3xl font-extrabold tabular-nums text-cream">
                            {{ score.value === null ? '–' : t('beta.report.percent', { percent: score.value }) }}
                            <Icon v-if="score.value !== null" :name="score.met ? 'check' : 'alert'" :size="20" :class="score.met ? 'text-good' : 'text-warn'" :aria-label="score.met ? t('beta.report.meets') : t('beta.report.under')" />
                        </dd>
                        <dd class="mt-1 text-xs text-muted">{{ score.value === null ? t('beta.report.untimed') : t('beta.report.needs', { percent: score.bar }) }}</dd>
                    </div>
                </dl>
                <div v-if="toFix.length" class="space-y-2">
                    <h3 class="label">{{ t('beta.report.toFix') }}</h3>
                    <div v-for="phrase in toFix" :key="phrase.index" class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-edge p-3 text-sm text-cream">
                        <span class="min-w-0">
                            <span class="block font-semibold">{{ words(phrase.index) ? t('beta.report.phraseWords', { number: phrase.index + 1, words: words(phrase.index) }) : t('beta.report.phraseNumber', { number: phrase.index + 1 }) }}</span>
                            <span class="block text-muted">{{ t(REASON[phrase.why] ?? REASON.mixed) }}</span>
                        </span>
                        <button type="button" class="btn-secondary btn-sm" @click="$emit('loop', phrase.index)"><Icon name="loop" :size="14" /> {{ t('practice.loop') }}</button>
                    </div>
                    <p class="text-sm text-muted">{{ t('beta.report.thenAgain') }}</p>
                </div>
                <ul class="space-y-2">
                    <li v-for="check in shownChecks" :key="check.code" class="flex gap-2 text-sm text-cream">
                        <Icon :name="check.tone === 'good' ? 'check' : 'alert'" :size="18" :class="check.tone === 'good' ? 'text-good' : 'text-warn'" class="mt-0.5 shrink-0" />
                        <span>{{ t(CHECKS[check.code].text) }}<span v-if="CHECKS[check.code].fix" class="block text-muted">{{ t(CHECKS[check.code].fix) }}</span></span>
                    </li>
                </ul>
                <Notice v-if="suggestOther" tone="info">
                    <p class="font-semibold">{{ t('beta.report.otherTitle') }}</p>
                    <p class="mt-1">{{ t('beta.report.otherBody') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="btn-secondary btn-sm" @click="$emit('tell')">{{ t('beta.report.askOther') }}</button>
                        <button type="button" class="btn-secondary btn-sm" @click="$emit('course')">{{ t('beta.report.course') }}</button>
                    </div>
                </Notice>
                <button type="button" class="btn-secondary" :aria-pressed="hearing" @click="$emit('hear')"><Icon :name="hearing ? 'stop' : 'play'" :size="16" /> {{ hearing ? t('studio.stop') : t('beta.report.hear') }}</button>
                <Field v-if="ready" :label="t('beta.report.note')" :help="t('beta.report.noteHelp')" v-slot="f">
                    <textarea :id="f.id" v-model="note" rows="2" maxlength="1000" class="input" :aria-describedby="f.describedby"></textarea>
                </Field>
                <div class="flex flex-wrap justify-end gap-3">
                    <button v-if="canUsePrevious" type="button" class="btn-ghost" :disabled="busy" @click="$emit('previous')">{{ t('beta.report.previous') }}</button>
                    <button type="button" :class="ready ? 'btn-secondary' : 'btn-primary'" :disabled="busy" @click="$emit('again')">{{ t('beta.report.again') }}</button>
                    <button v-if="ready" type="button" class="btn-primary" :disabled="busy" @click="$emit('send', { note: note.trim(), scores: sentScores })">{{ busy ? t('beta.report.sending') : t('beta.report.send') }}</button>
                </div>
            </div>
        </Modal>`,
    computed: {
        verdictIs() {
            return verdict(this.result.scores, this.bar);
        },
        ready() {
            return this.verdictIs === 'ready';
        },
        unheard() {
            return this.result.scores.pitchPercent === null;
        },
        shownScores() {
            const { pitchPercent, timingPercent } = this.result.scores;
            return [
                { key: 'pitch', label: tk('beta.report.pitch'), value: pitchPercent, bar: this.bar.pitchPercent, met: pitchPercent >= this.bar.pitchPercent },
                { key: 'timing', label: tk('beta.report.timing'), value: timingPercent, bar: this.bar.timingPercent, met: timingPercent >= this.bar.timingPercent },
            ];
        },
        /** The phrases keeping it from the bar, in the song's order. */
        toFix() {
            return this.ready ? [] : phrasesToFix(this.result, this.bar).sort((a, b) => a.index - b.index);
        },
        /** The level checks: problems with their fixes, and (on a ready take) what went well. */
        shownChecks() {
            return this.result.checks.filter((c) => (c.tone !== 'good' || this.ready) && !(c.code === 'pitch' && this.toFix.length));
        },
        suggestOther() {
            return !this.ready && (this.tries?.under ?? 0) >= TRIES_BEFORE_SUGGESTING;
        },
        /** What goes with the take (contract/api.md, beta.submissions.create's `scores`). */
        sentScores() {
            return { ...this.result.scores, verdict: this.verdictIs, tries: this.tries?.checked ?? 1 };
        },
    },
    methods: {
        /** A phrase's first words, so it's clear which part to loop ("that saved a wretch"). */
        words(index) {
            const [start, end] = this.phrases[index] ?? [0, 0];
            const sung = this.syllables.filter((s) => s.beat >= start - 1e-6 && s.beat < end - 1e-6).map((s) => s.text);
            const text = sung.map((l, i) => (l.endsWith('-') ? l.slice(0, -1) : i < sung.length - 1 ? `${l} ` : l)).join('').trim();
            const all = text.split(/\s+/).filter(Boolean);
            return all.length > 6 ? `${all.slice(0, 6).join(' ')}...` : all.join(' ');
        },
    },
    async mounted() {
        // The pitch tracking takes a while on a phone: it runs in a worker, with the sheet showing.
        const options = { notes: this.notes, beatSeconds: this.beatSeconds, startSeconds: this.startSeconds, phrases: this.phrases, timing: this.timing };
        let result;
        try {
            const track = await trackInWorker(this.samples, this.sampleRate);
            result = checkTake(this.samples, this.sampleRate, { ...options, track });
        } catch (error) {
            console.error('The checks failed', error);
            result = checkTake(this.samples, this.sampleRate, options);
        }
        // Each take is counted once, however often its report is opened.
        if (this.assignmentId && !counted.has(this.samples)) {
            counted.add(this.samples);
            this.tries = countTry(this.assignmentId, verdict(result.scores, this.bar) === 'ready');
        } else if (this.assignmentId) {
            this.tries = betaTries(this.assignmentId);
        }
        this.result = result;
    },
};
const counted = new WeakSet();

export { formatList };
