// The creator's first steps (docs/template-creator-plan.md, "Sing it" 1 and 2): Tempo and feel
// (type it, tap it, or slow, medium or fast, with Hear the click; the time signature; the pickup
// explained; headphones, the mic and the timing check), and the words (pasted, a line a phrase,
// shown split into syllables to fix, or no words: each phrase on "oo").

import { audio } from '../../audio.js';
import { fitLine, syllables } from '../../lyrics.js';
import { hearClick, readyToSing } from '../../creator-audio.js';
import { formatNumber, t, tk } from '../../i18n.js';
import { Icon } from '../icon.js';
import { MicPicker, micError } from '../mic.js';
import { Notice } from '../form.js';
import { TimingDialog } from '../dialogs.js';

const SPEEDS = [{ key: 'slow', bpm: 70, label: tk('create.tempo.slow') }, { key: 'medium', bpm: 96, label: tk('create.tempo.medium') }, { key: 'fast', bpm: 120, label: tk('create.tempo.fast') }];
const COUNTS = { '4/4': tk('create.tempo.counts.four'), '3/4': tk('create.tempo.counts.three'), '6/8': tk('create.tempo.counts.six') };
const SIGNATURES = { '4/4': tk('create.tempo.sig.four'), '3/4': tk('create.tempo.sig.three'), '6/8': tk('create.tempo.sig.six') };

export const TempoStep = {
    components: { Icon, MicPicker, Notice, TimingDialog },
    props: { draft: { type: Object, required: true } },
    emits: ['change'],
    data: () => ({ taps: [], clicking: null, timing: false, micProblem: null, audio }),
    setup: () => ({ SPEEDS, SIGNATURES, COUNTS }),
    template: `
        <section class="space-y-6">
            <div>
                <h2 class="text-4xl font-extrabold tracking-tight text-cream">{{ t('create.tempo.title') }}</h2>
                <p class="mt-2 text-muted">{{ draft.mode === 'sing' ? t('create.tempo.hintSing') : t('create.tempo.hintWrite') }}</p>
            </div>
            <div class="card space-y-6 p-5 md:p-7">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <button type="button" class="glass-bubble grid size-11 place-items-center rounded-full text-cream transition" :aria-label="t('create.tempo.slower')" @click="setTempo(draft.template.tempo - 1)"><Icon name="minus" :size="18" /></button>
                        <label class="flex items-baseline gap-1.5">
                            <span class="sr-only">{{ t('create.tempo.bpm') }}</span>
                            <input type="number" min="40" max="240" step="1" class="bg-transparent text-6xl font-extrabold tracking-tight text-cream tabular-nums outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none focus-visible:rounded-lg focus-visible:ring-2 focus-visible:ring-accent" :value="draft.template.tempo" :style="{ width: String(draft.template.tempo).length + 0.4 + 'ch' }" @change="setTempo($event.target.value)" />
                            <span class="text-sm font-semibold text-muted" aria-hidden="true">{{ t('create.tempo.unit') }}</span>
                        </label>
                        <button type="button" class="glass-bubble grid size-11 place-items-center rounded-full text-cream transition" :aria-label="t('create.tempo.faster')" @click="setTempo(draft.template.tempo + 1)"><Icon name="plus" :size="18" /></button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-secondary" @click="tap">{{ t('create.tempo.tap') }}</button>
                        <button type="button" class="btn-secondary" @click="click"><Icon :name="clicking ? 'stop' : 'play'" :size="16" /> {{ clicking ? t('studio.stop') : t('create.tempo.hear') }}</button>
                    </div>
                </div>
                <input v-fill type="range" min="40" max="240" step="1" class="range w-full" :value="draft.template.tempo" :aria-label="t('create.tempo.bpm')" @input="setTempo($event.target.value)" />
                <div class="grid grid-cols-3 gap-2" role="group" :aria-label="t('create.tempo.speeds')">
                    <button v-for="s in SPEEDS" :key="s.key" type="button" class="radio-card flex-col gap-0 p-3" :aria-pressed="draft.template.tempo === s.bpm" @click="setTempo(s.bpm)">
                        <span class="block font-semibold text-cream">{{ t(s.label) }}</span>
                        <span class="block text-sm text-muted tabular-nums">{{ t('create.tempo.bpmValue', { bpm: s.bpm }) }}</span>
                    </button>
                </div>
                <p v-if="taps.length > 1" class="text-sm text-muted" role="status">{{ t('create.tempo.tapping') }}</p>
            </div>
            <fieldset class="card space-y-5 p-5 md:p-7">
                <legend class="sr-only">{{ t('create.tempo.signature') }}</legend>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-lg font-bold text-cream" aria-hidden="true">{{ t('create.tempo.signature') }}</p>
                    <div class="seg flex">
                        <label v-for="(label, s) in SIGNATURES" :key="s" class="seg-option tap-44">
                            <input type="radio" class="sr-only" name="signature" :value="s" :checked="draft.template.timeSignature === s" @change="setTemplate({ timeSignature: s })" />{{ t(label) }}
                        </label>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-4" aria-hidden="true">
                    <span class="flex items-center gap-3">
                        <span v-for="n in beatsShown" :key="n" class="rounded-full" :class="n === 1 || (draft.template.timeSignature === '6/8' && n === 4) ? 'size-5 bg-accent' : 'size-3 bg-line'"></span>
                    </span>
                    <span class="text-sm text-muted">{{ t(COUNTS[draft.template.timeSignature] ?? COUNTS['4/4']) }}</span>
                </div>
                <p class="text-sm text-muted">{{ t('create.tempo.straight') }}</p>
            </fieldset>
            <div v-if="draft.mode === 'sing'" class="grid gap-4 md:grid-cols-2">
                <div class="card space-y-2 p-5">
                    <p class="text-xs font-bold uppercase tracking-widest text-accent">{{ t('create.tempo.pickupEyebrow') }}</p>
                    <p class="font-semibold text-cream">{{ t('create.tempo.pickupTitle') }}</p>
                    <p class="text-sm text-muted">{{ t('create.tempo.pickupHint') }}</p>
                </div>
                <div class="card space-y-3 p-5">
                    <p class="text-xs font-bold uppercase tracking-widest text-accent">{{ t('create.tempo.beforeEyebrow') }}</p>
                    <p class="flex items-center gap-2 font-semibold text-cream"><Icon name="headphones" :size="18" /> {{ t('create.tempo.headphonesTitle') }}</p>
                    <p class="text-sm text-muted">{{ t('create.tempo.headphonesHint') }}</p>
                    <MicPicker v-if="audio.mic" />
                    <div class="flex flex-wrap gap-2">
                        <button v-if="!audio.mic" type="button" class="btn-secondary btn-sm" @click="openMic"><Icon name="mic" :size="16" /> {{ t('create.tempo.mic') }}</button>
                        <button type="button" class="btn-ghost btn-sm" @click="checkTiming"><Icon name="timing" :size="16" /> {{ t('create.tempo.timing') }}</button>
                    </div>
                    <p v-if="micProblem" class="text-sm text-bad" role="alert">{{ micProblem }}</p>
                </div>
            </div>
            <TimingDialog v-if="timing" @done="timing = false" @skip="timing = false" @close="timing = false" />
        </section>`,
    computed: {
        /** The beat dots: a big one for each click (ONE, and FOUR in 6/8, which clicks twice a bar). */
        beatsShown() {
            return { '3/4': 3, '6/8': 6 }[this.draft.template.timeSignature] ?? 4;
        },
    },
    unmounted() {
        this.clicking?.stop();
    },
    methods: {
        setTemplate(fields) {
            this.$emit('change', { template: { ...this.draft.template, ...fields } });
        },
        setTempo(value) {
            const bpm = Math.round(Number(value));
            if (bpm >= 40 && bpm <= 240) this.setTemplate({ tempo: bpm });
        },
        /** Tap tempo: the average of the last few taps (a pause of two seconds starts again). */
        tap() {
            const now = performance.now();
            if (this.taps.length && now - this.taps.at(-1) > 2000) this.taps = [];
            this.taps = [...this.taps, now].slice(-6);
            if (this.taps.length < 3) return;
            const gaps = this.taps.slice(1).map((at, i) => at - this.taps[i]);
            this.setTempo(60000 / (gaps.reduce((a, b) => a + b, 0) / gaps.length));
        },
        async click() {
            if (this.clicking) {
                await this.clicking.stop();
                return;
            }
            this.clicking = await hearClick({ tempo: this.draft.template.tempo, timeSignature: this.draft.template.timeSignature });
            await this.clicking.done;
            this.clicking = null;
        },
        async openMic() {
            try {
                await readyToSing();
                this.micProblem = null;
            } catch (error) {
                this.micProblem = micError(error, () => this.openMic());
            }
        },
        async checkTiming() {
            await this.openMic();
            if (!this.micProblem) this.timing = true;
        },
    },
};

/** A line's syllables written the way people fix them: "A-maz-ing grace,". */
export const splitText = (sylls) => sylls.join(' ').replace(/- /g, '-');

/** Back from "A-maz-ing grace," to syllables: each word's pieces, a "-" on all but its last. */
export function fromSplitText(text) {
    return text
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .flatMap((word) => {
            const pieces = word.split('-').filter(Boolean);
            return pieces.map((piece, i) => (i < pieces.length - 1 ? `${piece}-` : piece));
        });
}

export const WordsStep = {
    components: { Icon, Notice },
    props: { draft: { type: Object, required: true } },
    emits: ['change'],
    setup: () => ({ splitText, formatNumber }),
    data: (vm) => ({ text: vm.draft.lines.filter((l) => !l.noWords).map((l) => l.text).join('\n') }),
    template: `
        <section class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-4xl font-extrabold tracking-tight text-cream">{{ t('create.words.title') }}</h2>
                    <p class="mt-2 text-muted">{{ t('create.words.hint') }}</p>
                </div>
                <div class="seg flex" role="group" :aria-label="t('create.words.kind')">
                    <button type="button" class="seg-option tap-44" :aria-pressed="!draft.noWords" @click="draft.noWords && setNoWords(false)">{{ t('create.words.withWords') }}</button>
                    <button type="button" class="seg-option tap-44" :aria-pressed="draft.noWords" @click="!draft.noWords && setNoWords(true)">{{ t('create.words.noWords') }}</button>
                </div>
            </div>
            <div v-if="draft.noWords" class="card space-y-3 p-5 md:p-7">
                <label class="block text-sm font-semibold text-muted">{{ t('create.words.phrases') }}
                    <input type="number" min="1" max="40" class="input mt-1 w-28" :value="draft.phraseCount" @change="setPhrases($event.target.value)" />
                </label>
                <p class="text-sm text-muted">{{ t('create.words.noneHint') }}</p>
            </div>
            <div v-else class="grid items-start gap-4 md:grid-cols-2">
                <div class="card space-y-3 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <label for="create-words" class="font-semibold text-cream">{{ t('create.words.paste') }}</label>
                        <button v-if="!text.trim()" type="button" class="btn-ghost btn-sm" @click="example">{{ t('create.words.example') }}</button>
                    </div>
                    <textarea id="create-words" v-model="text" rows="6" class="input font-semibold leading-relaxed md:min-h-64" translate="no" :placeholder="t('create.words.placeholder')" @input="setText"></textarea>
                    <p class="text-sm text-muted">{{ t('create.words.enterHint') }}</p>
                </div>
                <div class="card space-y-3 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-cream">{{ t('create.words.splitTitle') }}</h3>
                        <span class="text-sm text-muted tabular-nums">{{ t('create.frame.summary.words', { count: draft.lines.length }) }}</span>
                    </div>
                    <template v-if="draft.lines.length">
                        <p class="text-sm text-muted">{{ t('create.words.splitHint') }}</p>
                        <ol class="space-y-2">
                            <li v-for="(line, i) in draft.lines" :key="i" class="flex items-center gap-3 rounded-2xl border border-line bg-raised/60 p-2.5">
                                <span class="w-6 shrink-0 text-center text-sm font-bold text-muted tabular-nums">{{ formatNumber(i + 1) }}</span>
                                <input class="input min-w-0 flex-1 font-semibold" translate="no" :value="splitText(line.syllables)" :aria-label="t('create.words.splitLine', { number: i + 1 })" @change="setSplit(i, $event.target.value)" />
                                <span class="hidden shrink-0 text-xs text-muted sm:inline">{{ t('create.words.count', { count: line.syllables.length }) }}</span>
                            </li>
                        </ol>
                    </template>
                    <p v-else class="grid min-h-48 place-items-center rounded-2xl border border-dashed border-line p-6 text-center text-sm text-muted">{{ t('create.words.empty') }}</p>
                </div>
            </div>
        </section>`,
    methods: {
        /** The lines from the words, keeping what was sung for lines that didn't change. */
        setText() {
            const texts = this.text.split('\n').map((l) => l.trim()).filter(Boolean);
            const lines = texts.map((text, i) => {
                const before = this.draft.lines[i];
                if (before && before.text === text && !before.noWords) return before;
                return refit({ ...(before ?? {}), text, syllables: syllables(text), noWords: false });
            });
            this.$emit('change', { lines });
        },
        setSplit(i, value) {
            const lines = [...this.draft.lines];
            lines[i] = refit({ ...lines[i], syllables: fromSplitText(value) });
            this.$emit('change', { lines });
        },
        /** Fills the box with the placeholder's lines, to see how it works. */
        example() {
            this.text = t('create.words.placeholder');
            this.setText();
        },
        setNoWords(on) {
            if (on) this.setPhrases(this.draft.phraseCount, true);
            else {
                this.$emit('change', { noWords: false, lines: [] });
                this.text = '';
            }
        },
        /** No words: each phrase sung on "oo". */
        setPhrases(value, on = this.draft.noWords) {
            const count = Math.max(1, Math.min(40, Math.round(Number(value)) || 1));
            const lines = Array.from({ length: count }, (_, i) => {
                const before = this.draft.lines[i];
                return before?.noWords ? before : refit({ ...(before ?? {}), text: t('create.words.phraseName', { number: i + 1 }), syllables: ['oo'], noWords: true });
            });
            this.$emit('change', { noWords: on, phraseCount: count, lines });
        },
    },
};

/** A line's words fitted to its notes again (when both are there). */
export function refit(line) {
    if (!line.notes?.length) return { ...line, lyrics: null, mismatch: null };
    const fit = fitLine(line.syllables, line.notes);
    return { ...line, lyrics: fit.lyrics ?? null, mismatch: fit.mismatch ? { ...fit.mismatch, split: fit.split } : null };
}
