// The review after a take (Hillel's 79.png; it was "Just recorded"): one clear decision in a sticky
// bar (Record again, or Keep it and record the next part), the score as the headline ("Close: 88%
// on pitch", no stars here: lessons keep theirs), the one thing to try with the words it was about,
// each phrase's percent and what went wrong in it, the picked phrase's pitch line against its notes
// (glass gems, troubled notes marked and labelled), Play and Re-sing that phrase, a small mixer
// (hear it with the others, tap a part to mute it, Polished), the fine-tune tiles (nudge the
// timing, trim the ends), and the parts across the top. Two columns on a computer, stacked on a
// phone. When every part is yours: the celebration, Share and the studio.

import { formatNumber, t, tk } from '../../i18n.js';
import { hzToMidi } from '../../dsp.js';
import { oneThing, phraseReview } from '../../report.js';
import { formatTime } from '../../theme.js';
import { Icon } from '../icon.js';
import { sk } from '../../skin.js';

const MAX_NUDGE_MS = 150;
const LEVEL = { excellent: tk('report.level.excellent'), close: tk('report.level.close'), gettingThere: tk('report.level.gettingThere'), tricky: tk('report.level.tricky') };
const TROUBLE = { wavered: tk('sing.review.wavered'), scooped: tk('sing.review.scooped'), off: tk('sing.review.off') };
const TROUBLE_COUNT = { wavered: tk('sing.review.waveredCount'), scooped: tk('sing.review.scoopedCount'), off: tk('sing.review.offCount') };
const ONE_THING = { wavered: tk('sing.review.oneWavered'), scooped: tk('sing.review.oneScooped'), off: tk('sing.review.oneOff') };
const NO_WORDS = { wavered: tk('sing.review.noWordsWavered'), scooped: tk('sing.review.noWordsScooped'), off: tk('sing.review.noWordsOff') };
const FIX = { wavered: tk('sing.review.fixWavered'), scooped: tk('sing.review.fixScooped'), off: tk('sing.review.fixOff') };
const CHART = { width: 560, height: 170, pad: 14 };

/** One part's waveform as thin bars in its colour (its take, or its guide), with the playhead. */
const PeakRow = {
    props: { peaks: { type: [Array, Float32Array], default: null }, color: { type: String, required: true }, at: { type: Number, default: null } },
    // The canvas sits absolutely in a box of its own height: its drawn width (in pixels) would
    // otherwise be the row's minimum, and on a narrow phone the review scrolled sideways (Hillel).
    template: `<div class="relative h-9 min-w-0"><canvas ref="canvas" class="touch-pan-y absolute inset-0 h-full w-full" aria-hidden="true"></canvas><span v-if="at !== null" class="pointer-events-none absolute inset-y-0 w-0.5 bg-cream" :style="{ left: at * 100 + '%' }" aria-hidden="true"></span></div>`,
    watch: {
        color() {
            this.draw();
        },
    },
    mounted() {
        this.draw();
    },
    methods: {
        draw() {
            const canvas = this.$refs.canvas;
            const ratio = window.devicePixelRatio || 1;
            const width = canvas.clientWidth;
            const height = canvas.clientHeight;
            canvas.width = width * ratio;
            canvas.height = height * ratio;
            const g = canvas.getContext('2d');
            g.scale(ratio, ratio);
            g.fillStyle = this.color;
            const peaks = this.peaks ?? [];
            for (let x = 0; x < width; x += 3) {
                const peak = peaks[Math.floor((x / width) * peaks.length)] ?? 0;
                const bar = Math.max(0.75, Math.sqrt(peak) * height * 0.45);
                g.fillRect(x, height / 2 - bar, 2, bar * 2);
            }
        },
    },
};

export const JustRecorded = {
    components: { Icon, PeakRow },
    props: {
        outcome: { type: Object, required: true }, // sessionStore.finishRecording's
        partName: { type: String, required: true },
        nextName: { type: String, default: '' },
        hearing: Boolean,
        nudgeMs: { type: Number, default: 0 },
        lining: Boolean, // working out the suggestion
        lined: { type: Number, default: null }, // the suggestion, ms (null: none needed or none found)
        canResing: Boolean, // a song's part with phrases: one can be sung again
        color: { type: String, default: 'var(--color-part-3)' }, // your part's
        stack: { type: Array, default: () => [] }, // [{ id, name, color, peaks, you, done, muted }]
        polished: Boolean, // Hear it polished is on
        // The take's report (null while it's worked out, or for a part with no notes):
        score: { type: Object, default: null },
        notes: { type: Array, default: () => [] }, // the part's notes, in the key sung (with their words)
        track: { type: Object, default: null }, // { hz, rate }
        beatSeconds: { type: Number, default: 0.5 },
        offsetSeconds: { type: Number, default: 0 },
        phrases: { type: Array, default: () => [] }, // [startBeat, endBeat]
        phraseLabels: { type: Array, default: () => [] }, // each phrase's words
        position: { type: Number, default: 0 },
        lengthSeconds: { type: Number, default: 1 },
        bleed: Boolean,
        sungBy: { type: String, default: '' }, // the song and who sang it, once two or more of its parts are yours (onboarding step 10)
    },
    emits: ['hear', 'again', 'next', 'nudge', 'nudged', 'line-up', 'studio', 'resing', 'trim', 'polish', 'share', 'hear-phrase'],
    data: () => ({ nudging: false, picked: null }),
    setup: () => ({ MAX_NUDGE_MS, CHART, formatNumber, formatTime, sk }),
    template: `
        <section class="flex min-h-full w-full flex-col" aria-labelledby="recorded-title" data-take-review>
            <div class="mx-auto w-full max-w-6xl flex-1 space-y-6 px-4 pb-8 pt-5 md:px-8">
                <!-- The parts, honestly: done, next, then the rest. -->
                <ol v-if="stack.length > 1" class="flex flex-wrap gap-2" :aria-label="t('sing.parts')">
                    <li v-for="row in stack" :key="row.id" class="flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold" :class="row.you ? 'border-cream text-cream' : row.done ? 'border-edge text-cream' : row.name === nextName ? 'border-accent text-cream' : 'border-edge text-muted'" translate="no">
                        <Icon v-if="row.done || row.you" name="check" :size="12" />{{ row.name }}<span v-if="!row.done && !row.you && row.name === nextName" class="text-accent-strong">· {{ t('sing.review.next') }}</span>
                    </li>
                </ol>
                <header class="flex flex-wrap items-end justify-between gap-3">
                    <div class="min-w-0">
                        <p v-if="sungBy" class="mb-2 text-lg font-bold text-cream" data-sung-by>{{ sungBy }}</p>
                        <p class="text-xs font-bold uppercase tracking-[0.14em]" :style="{ color: paintInk(color) }" translate="no">{{ t('sing.review.eyebrow', { part: partName }) }}</p>
                        <h2 id="recorded-title" ref="heading" tabindex="-1" class="mt-1 text-4xl font-extrabold tracking-tight text-cream outline-none">{{ headline }}</h2>
                        <p class="mt-1 text-muted">{{ outcome.everyPart ? t('sing.recorded.everyPartHint') : t('sing.review.hint') }}</p>
                    </div>
                    <button type="button" class="btn-ghost btn-sm" @click="$emit('studio')">{{ t('sing.openStudio') }}</button>
                </header>
                <p v-if="bleed && !(score && score.status !== 'scored')" class="rounded-2xl bg-raised p-3 text-sm text-cream" role="status">{{ sk('report.bleedSerious', 'report.bleed') }}</p>
                <slot name="report" />
                <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-2 lg:items-start">
                    <div class="space-y-6">
                        <!-- Hear it with the others: a small mixer. -->
                        <div class="card space-y-3 p-4">
                            <div class="flex items-center gap-3">
                                <button type="button" class="glass-bubble-solid grid size-12 shrink-0 place-items-center rounded-full" :aria-label="hearing ? t('studio.stop') : t('sing.recorded.hearOthers')" @click="$emit('hear')"><Icon :name="hearing ? 'stop' : 'play'" :size="20" /></button>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-cream" :data-replay="hearing ? '' : null">{{ hearing ? sk('sing.recorded.hearOthers', 'game.replay') : t('sing.recorded.hearOthers') }}</p>
                                    <p class="tabular-nums text-xs text-muted">{{ formatTime(Math.max(0, position)) }} / {{ formatTime(lengthSeconds) }}<template v-if="phraseNow !== null"> · {{ t('sing.review.phrase', { number: phraseNow + 1 }) }}</template></p>
                                </div>
                                <button type="button" role="switch" :aria-checked="polished ? 'true' : 'false'" class="tap-44 flex items-center gap-2 rounded-full glass-bubble py-1.5 pe-3 ps-1.5 text-sm font-semibold" :class="polished ? 'text-cream' : 'text-muted'" @click="$emit('polish', !polished)">
                                    <span class="relative h-4 w-7 shrink-0 rounded-full transition" :class="polished ? 'bg-accent' : 'bg-line'" aria-hidden="true"><span class="absolute top-0.5 size-3 rounded-full bg-cream transition-[inset-inline-start]" :style="{ insetInlineStart: polished ? '0.875rem' : '0.125rem' }"></span></span>{{ t('polish.name') }}
                                </button>
                            </div>
                            <ul class="space-y-1.5">
                                <li v-for="row in stack" :key="row.id" class="flex items-center gap-2">
                                    <span class="flex w-32 shrink-0 items-center gap-1.5 truncate text-xs font-semibold" :class="row.muted ? 'text-muted' : 'text-cream'">
                                        <span class="size-2 shrink-0 rounded-full" :style="{ background: row.color }"></span><span class="truncate" translate="no">{{ row.you ? t('home.lead.you', { part: row.name }) : row.name }}</span><span v-if="row.muted" class="ms-auto shrink-0 font-bold">{{ t('sing.hear.offShort') }}</span>
                                    </span>
                                    <span class="min-w-0 flex-1 rounded-lg bg-ink px-1" :class="row.muted ? 'opacity-40' : ''" :style="row.you ? { boxShadow: 'inset 0 0 0 1.5px ' + row.color } : null"><PeakRow :peaks="row.peaks" :color="row.color" :at="hearing ? Math.max(0, position) / lengthSeconds : null" /></span>
                                </li>
                            </ul>
                            <p class="text-xs text-muted">{{ t('sing.review.inSettings') }}</p>
                        </div>
                        <!-- Fine-tune the take: two tiles. -->
                        <div class="space-y-2">
                            <p class="label">{{ t('sing.review.fineTune') }}</p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <button type="button" class="card p-4 text-start hover:border-line" :aria-expanded="nudging" @click="nudging = !nudging"><span class="block font-bold text-cream">{{ t('sing.review.nudgeTitle') }}</span><span class="block text-sm text-muted">{{ t('sing.review.nudgeHint') }}</span></button>
                                <button type="button" class="card p-4 text-start hover:border-line" @click="$emit('trim')"><span class="block font-bold text-cream">{{ t('sing.review.trimTitle') }}</span><span class="block text-sm text-muted">{{ t('sing.review.trimHint') }}</span></button>
                                <!-- No phrase feedback (not heard, or still working it out): Re-sing is here instead. -->
                                <button v-if="canResing && !review.length" type="button" class="card p-4 text-start hover:border-line sm:col-span-2" @click="$emit('resing')"><span class="block font-bold text-cream">{{ t('sing.review.resingTitle') }}</span><span class="block text-sm text-muted">{{ t('sing.review.resingHint') }}</span></button>
                            </div>
                            <div v-if="nudging" class="card space-y-3 p-4">
                                <label class="block text-sm font-semibold text-cream" for="nudge">{{ t('sing.recorded.nudge', { ms: nudgeLabel }) }}</label>
                                <input id="nudge" type="range" dir="ltr" :min="-MAX_NUDGE_MS" :max="MAX_NUDGE_MS" step="5" :value="nudgeMs" class="range w-full [--p:0%]" @input="$emit('nudge', Number($event.target.value))" @change="$emit('nudged')" />
                                <p class="text-xs text-muted">{{ t('sing.recorded.nudgeHelp') }}</p>
                                <div class="flex flex-wrap items-center gap-3">
                                    <button type="button" class="btn-secondary btn-sm" :disabled="lining" @click="$emit('line-up')">{{ lining ? t('sing.recorded.lining') : t('sing.recorded.lineUp') }}</button>
                                    <span v-if="lined !== null && !lining" class="text-sm text-muted" role="status">{{ lined === 0 ? t('sing.recorded.linedFine') : t('sing.recorded.lined', { ms: formatNumber(lined) }) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- The feedback: the one thing to try, the phrases, the picked one's pitch line. -->
                    <div v-if="review.length" class="card space-y-4 p-4">
                        <div v-if="one">
                            <p class="label">{{ t('sing.review.oneThing') }}</p>
                            <p class="mt-1 text-cream" data-one-thing>{{ oneLine }}</p>
                        </div>
                        <p v-else class="text-cream">{{ t('sing.review.allSteady') }}</p>
                        <ul class="space-y-2" :aria-label="t('report.phrases')">
                            <li v-for="p in review" :key="p.index">
                                <button type="button" class="flex w-full items-center gap-3 rounded-2xl border p-3 text-start transition" :class="p.index === pickedIndex ? 'border-cream/50 bg-raised' : 'border-edge hover:border-line'" :aria-pressed="p.index === pickedIndex ? 'true' : 'false'" @click="picked = p.index">
                                    <span class="min-w-0 flex-1"><span class="block text-xs text-muted">{{ t('sing.review.phrase', { number: p.index + 1 }) }}</span><span class="block truncate font-semibold text-cream" translate="no">{{ phraseLabels[p.index] || '' }}</span></span>
                                    <span class="shrink-0 text-end"><span class="block tabular-nums text-lg font-extrabold" :class="p.percent >= 90 ? 'text-part-ink-2' : 'text-part-ink-1'">{{ t('report.percent', { percent: p.percent }) }}</span><span class="block text-xs text-muted">{{ p.troubles.length ? troubleCount(p) : t('sing.review.steady') }}</span></span>
                                </button>
                            </li>
                        </ul>
                        <svg v-if="chart" :viewBox="'0 0 ' + CHART.width + ' ' + CHART.height" class="block h-auto w-full rounded-2xl bg-ink" role="img" :aria-label="chartLabel">
                            <defs><linearGradient id="rv-gem" x1="0" y1="0" x2="0" y2="1" :style="{ '--g': color }"><stop offset="0" style="stop-color: color-mix(in oklab, var(--g) 58%, white)" /><stop offset="0.48" style="stop-color: var(--g)" /><stop offset="1" style="stop-color: color-mix(in oklab, var(--g) 68%, black)" /></linearGradient></defs>
                            <g v-for="n in chart.targets" :key="'t' + n.note">
                                <rect :x="n.x" :y="n.y - 6" :width="n.w" height="12" rx="6" fill="url(#rv-gem)" :style="{ stroke: n.trouble ? 'var(--color-part-1)' : 'color-mix(in oklab, ' + color + ' 55%, black)' }" :stroke-width="n.trouble ? 2 : 1" />
                                <rect :x="n.x + 2" :y="n.y - 5" :width="glossOf(n)" height="5" rx="3" fill="#fff" fill-opacity="0.45" />
                            </g>
                            <polyline v-for="(points, k) in chart.line" :key="'l' + k" :points="points" fill="none" class="stroke-cream" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
                            <g v-for="n in chart.targets.filter((m) => m.trouble && m.label)" :key="'c' + n.note">
                                <rect :x="n.x" :y="chipY(n)" :width="chipWidth(n)" height="18" rx="9" class="fill-part-1" />
                                <text :x="n.x + 7" :y="chipY(n) + 12.5" font-size="11" font-weight="700" class="fill-part-text-1" translate="no">{{ n.label }}</text>
                            </g>
                        </svg>
                        <p v-if="chart" class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" aria-hidden="true">
                            <span class="flex items-center gap-1.5"><span class="h-2 w-4 rounded-full" :style="{ background: color }"></span>{{ t('sing.review.legendTarget') }}</span>
                            <span class="flex items-center gap-1.5"><span class="h-0.5 w-4 rounded-full bg-cream"></span>{{ t('sing.review.legendYou') }}</span>
                            <span class="flex items-center gap-1.5"><span class="h-2 w-4 rounded-full border-2 border-part-1"></span>{{ t('sing.review.legendTrouble') }}</span>
                        </p>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" class="btn-secondary" @click="$emit('hear-phrase', pickedIndex)">{{ t('sing.review.playPhrase', { number: pickedIndex + 1 }) }}</button>
                            <button v-if="canResing" type="button" class="btn-secondary" @click="$emit('resing', pickedIndex)">{{ t('sing.review.resingPhrase', { number: pickedIndex + 1 }) }}</button>
                        </div>
                    </div>
                    <p v-else-if="!score && notes.length" class="text-sm text-muted" role="status">{{ t('report.working') }}</p>
                    <p v-else-if="score && score.status !== 'scored'" class="text-sm text-cream" role="status">{{ sk('report.unheardSerious', 'report.unheard') }}</p>
                </div>
            </div>
            <!-- One clear decision, always in view. -->
            <div class="sticky bottom-0 z-10 border-t border-edge bg-ink/95 px-4 py-3 backdrop-blur md:px-8">
                <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-3">
                    <p class="me-auto text-sm text-muted">{{ t('sing.review.saved') }}</p>
                    <button type="button" class="btn-secondary" @click="$emit('again')">{{ t('sing.recorded.again') }}</button>
                    <button v-if="outcome.everyPart" type="button" class="btn-primary" @click="$emit('share')">{{ t('share.title') }}</button>
                    <button v-else-if="nextName" type="button" class="btn-primary" @click="$emit('next')">{{ t('sing.review.keepNext', { part: nextName }) }} <Icon name="arrow" :size="16" /></button>
                    <button v-else type="button" class="btn-primary" @click="$emit('studio')">{{ t('sing.openStudio') }}</button>
                </div>
            </div>
        </section>`,
    computed: {
        nudgeLabel() {
            return this.nudgeMs > 0 ? `+${formatNumber(this.nudgeMs)}` : formatNumber(this.nudgeMs);
        },
        /** The score as the headline: "Close: 88% on pitch" (or the celebration, or the part recorded while it's worked out). */
        headline() {
            if (this.outcome.everyPart) return t('sing.recorded.everyPart');
            if (this.score?.status === 'scored') return t('report.summary', { level: t(LEVEL[this.score.word]), percent: this.score.percent });
            return t('sing.recorded.title', { part: this.partName });
        },
        review() {
            return phraseReview(this.score, this.notes, this.phrases);
        },
        one() {
            return oneThing(this.review);
        },
        /** "In phrase 1, 'grace' and 'sound' wavered. Take a fuller breath before..." */
        oneLine() {
            const one = this.one;
            const words = one.words.map((w) => t('sing.review.quoted', { word: w }));
            const said = words.length ? t(ONE_THING[one.kind], { number: one.phrase + 1, words: words.join(t('sing.review.and')) }) : t(NO_WORDS[one.kind], { number: one.phrase + 1, count: one.count });
            return `${said} ${t(FIX[one.kind])}`;
        },
        pickedIndex() {
            return this.picked ?? this.one?.phrase ?? this.review[0]?.index ?? 0;
        },
        phraseNow() {
            const beat = this.position / this.beatSeconds;
            const i = this.phrases.findIndex(([a, b]) => beat >= a && beat < b);
            return i >= 0 ? i : null;
        },
        /** The picked phrase's notes and your line, scaled to the chart. */
        chart() {
            const phrase = this.phrases[this.pickedIndex];
            if (!phrase || !this.notes.length) return null;
            const [startBeat, endBeat] = phrase;
            const inside = this.notes.map((n, i) => ({ ...n, i })).filter((n) => n.beat >= startBeat - 1e-9 && n.beat < endBeat - 1e-9);
            if (!inside.length) return null;
            const troubles = new Map((this.review.find((p) => p.index === this.pickedIndex)?.troubles ?? []).map((x) => [x.note, x]));
            const lo = Math.min(...inside.map((n) => n.pitch)) - 2;
            const hi = Math.max(...inside.map((n) => n.pitch)) + 2;
            const { width, height, pad } = CHART;
            const x = (beat) => pad + ((beat - startBeat) / (endBeat - startBeat)) * (width - pad * 2);
            const y = (midi) => pad + 16 + (1 - (Math.max(lo, Math.min(hi, midi)) - lo) / (hi - lo)) * (height - pad * 2 - 16);
            const targets = inside.map((n) => {
                const trouble = troubles.get(n.i);
                return { note: n.i, x: x(n.beat), w: Math.max(6, x(n.beat + n.length) - x(n.beat) - 3), y: y(n.pitch), trouble: trouble?.kind ?? null, label: trouble ? t(TROUBLE[trouble.kind], { word: trouble.lyric ? t('sing.review.quoted', { word: trouble.lyric }) : '' }).trim() : '' };
            });
            const line = [];
            const track = this.track;
            if (track) {
                const step = Math.max(1, Math.round(track.rate / 30));
                let run = [];
                for (let f = 0; f < track.hz.length; f += step) {
                    const beat = (f / track.rate + this.offsetSeconds) / this.beatSeconds;
                    const hz = track.hz[f];
                    if (beat < startBeat || beat >= endBeat || !hz) {
                        if (run.length > 1) line.push(run.join(' '));
                        run = [];
                        continue;
                    }
                    // In the part's own octave (a voice an octave off is drawn on its notes).
                    const midi = hzToMidi(hz);
                    const near = inside.find((n) => beat >= n.beat && beat < n.beat + n.length)?.pitch ?? (lo + hi) / 2;
                    run.push(`${x(beat).toFixed(1)},${y(midi + 12 * Math.round((near - midi) / 12)).toFixed(1)}`);
                }
                if (run.length > 1) line.push(run.join(' '));
            }
            return { targets, line };
        },
        chartLabel() {
            const p = this.review.find((r) => r.index === this.pickedIndex);
            return t('sing.review.chartLabel', { number: this.pickedIndex + 1, percent: p?.percent ?? 0 });
        },
    },
    mounted() {
        this.$refs.heading.focus();
    },
    methods: {
        troubleCount(p) {
            const counts = {};
            for (const x of p.troubles) counts[x.kind] = (counts[x.kind] ?? 0) + 1;
            const kind = Object.keys(counts).sort((a, b) => counts[b] - counts[a])[0];
            return t(TROUBLE_COUNT[kind], { count: counts[kind] });
        },
        glossOf(n) {
            return Math.max(2, n.w * 0.75);
        },
        chipY(n) {
            return Math.max(2, n.y - 30);
        },
        chipWidth(n) {
            return n.label.length * 6.4 + 14;
        },
    },
};
