// Listen and choose (docs/plus-courses-build-plan.md, 6a; the course review's M5): no mic. Each
// round plays two or three short clips from the guide voice and asks a question: tap the clip that
// answers it ("Which one is straight?"), or hear one clip and tap a word ("Major or minor?"). A short
// why follows every answer, right or not. Only the first answer to a round counts, so the stars are
// for hearing it, not for guessing twice. Emits `done` ({ percent }) once every round is answered.
// The clips are scheduled on the audio clock, as every sound is.
//
// The step: `{ kind: 'choose', text, exercise: { tempo, rounds: [{ prompt, clips: [{ label?, notes:
// [{ beat, length, step, vibrato? }], drone?, chords? }], options?, answer, why }] } }`. With
// `options` (words), `answer` indexes them; without, it indexes the clips.

import { engine, unlock } from '../audio.js';
import { localized, t } from '../i18n.js';
import { choosePercent, playableRounds, renderClip } from '../ear-rounds.js';
import { Icon } from './icon.js';
import { clipBuffer, clipCredit, publishedFor } from '../course-clips.js';
import { ReportVoice } from './report-voice.js';

const LETTERS = ['A', 'B', 'C', 'D'];

export const Choose = {
    components: { Icon, ReportVoice },
    inheritAttrs: false, // the lesson view passes a sung step's props (the key, strictness): not used here
    props: {
        exercise: { type: Object, required: true },
        base: { type: Number, required: true },
        color: { type: String, default: 'var(--color-part-4)' },
        personal: { type: Object, default: () => ({}) }, // the course's own values (a whistle step: `personal.whistle`)
    },
    emits: ['done', 'practised'],
    data: () => ({ round: 0, answers: [], picked: null, playing: null, finished: false, recorded: {}, reporting: null }),
    setup: () => ({ localized, LETTERS, clipCredit }),
    template: `
        <div class="space-y-5" data-choose>
            <p class="tabular-nums text-xs font-bold uppercase tracking-[0.14em] text-muted">{{ t('choose.round', { round: Math.min(round + 1, rounds.length), total: rounds.length }) }}</p>
            <template v-if="!finished">
                <h2 class="text-xl font-bold text-cream" translate="no">{{ localized(current.prompt) }}</h2>
                <!-- The clips: hear each as often as you like. Without words to pick, a clip is the answer. -->
                <ul class="grid gap-3" :class="current.clips.length > 2 ? 'sm:grid-cols-3' : 'sm:grid-cols-2'">
                    <li v-for="(clip, i) in current.clips" :key="round + '-' + i" class="flex items-center gap-3 rounded-2xl border p-3" :class="clipClass(i)" :data-clip="i">
                        <button type="button" class="grid size-12 shrink-0 place-items-center bubble bubble-primary" data-disc="play" :aria-label="t('choose.hear', { name: clipName(clip, i) })" @click="play(i)"><Icon :name="playing === i ? 'stop' : 'play'" :size="18" /></button>
                        <span class="min-w-0 flex-1"><span class="block font-bold text-cream" translate="no">{{ clipName(clip, i) }}</span><span v-if="clip.clip && recorded[clip.clip]?.credit" class="block text-xs text-muted" translate="no">{{ clipCredit(recorded[clip.clip]) }} <button type="button" class="link-quiet" :aria-label="t('voices.report.button', { who: clipCredit(recorded[clip.clip]) })" data-report-clip @click.stop="reporting = clip.clip">{{ t('voices.report.short') }}</button></span></span>
                        <button v-if="!current.options" type="button" class="btn-secondary btn-sm" :disabled="picked !== null" @click="answer(i)">{{ t('choose.this') }}</button>
                    </li>
                </ul>
                <div v-if="current.options" class="flex flex-wrap gap-2" role="group" :aria-label="localized(current.prompt)">
                    <button v-for="(option, i) in current.options" :key="i" type="button" class="btn-secondary" :class="optionClass(i)" :disabled="picked !== null" @click="answer(i)" translate="no">{{ localized(option) }}</button>
                </div>
                <div v-if="picked !== null" class="space-y-3 rounded-2xl border border-edge bg-panel p-4" role="status" data-choose-why>
                    <p class="font-bold" :class="picked === current.answer ? 'text-cream' : 'text-muted'">{{ picked === current.answer ? t('choose.right') : t('choose.notQuite', { answer: answerName }) }}</p>
                    <p v-if="current.why" class="text-sm text-cream" translate="no">{{ localized(current.why) }}</p>
                    <button type="button" class="btn-primary btn-sm" @click="nextRound">{{ round + 1 < rounds.length ? t('choose.next') : t('choose.finish') }}</button>
                </div>
            </template>
            <div v-else class="space-y-2 rounded-2xl border border-edge bg-panel p-4" role="status" data-choose-done>
                <p class="text-xl font-extrabold text-cream">{{ t('choose.score', { right: right, total: rounds.length }) }}</p>
                <p class="text-sm text-muted">{{ right === rounds.length ? t('choose.allRight') : t('choose.again') }}</p>
                <button type="button" class="btn-secondary btn-sm" @click="restart">{{ t('choose.playAgain') }}</button>
            </div>
            <ReportVoice v-if="reporting" :clip-slot="reporting" :who="clipCredit(recorded[reporting])" @close="reporting = null" />
        </div>`,
    computed: {
        rounds: (vm) => playableRounds(vm.exercise.rounds ?? [], vm.recorded),
        current: (vm) => vm.rounds[vm.round] ?? null,
        right: (vm) => vm.rounds.filter((r, i) => vm.answers[i] === r.answer).length,
        answerName() {
            const r = this.current;
            return r.options ? localized(r.options[r.answer]) : this.clipName(r.clips[r.answer], r.answer);
        },
    },
    async created() {
        // Real singers' recordings, for the clips that name a slot (published ones only).
        const slots = (this.exercise.rounds ?? []).flatMap((r) => r.clips.map((c) => c.clip));
        if (slots.some(Boolean)) this.recorded = await publishedFor(slots);
    },
    unmounted() {
        this.source?.stop();
    },
    methods: {
        clipName(clip, i) {
            return clip.label ? localized(clip.label) : t('choose.clip', { letter: LETTERS[i] });
        },
        clipClass(i) {
            if (this.picked === null || this.current.options) return 'border-edge';
            if (i === this.current.answer) return 'border-cream';
            return i === this.picked ? 'border-line opacity-70' : 'border-edge';
        },
        optionClass(i) {
            if (this.picked === null) return '';
            return i === this.current.answer ? 'ring-2 ring-cream' : i === this.picked ? 'opacity-70' : '';
        },
        /** A clip, on the audio clock; tapping the one playing stops it. */
        async play(i) {
            const was = this.playing;
            this.source?.stop();
            if (was === i) return;
            await unlock();
            const clip = this.current.clips[i];
            const sampleRate = engine.sampleRate;
            // A real singer's recording when there is one; its own notes otherwise (or if it won't load).
            let buffer = null;
            const published = clip.clip ? this.recorded[clip.clip] : null;
            if (published) buffer = await clipBuffer(published.url).catch((error) => (console.warn('Clip not played', error), null));
            if (!buffer && !clip.notes?.length) return;
            if (!buffer) buffer = engine.toBuffer(renderClip(clip, this.base, { tempo: this.exercise.tempo ?? 72, sampleRate, exercise: this.exercise, whistle: this.personal.whistle ?? null }), sampleRate);
            const source = engine.ctx.createBufferSource();
            source.buffer = buffer;
            source.connect(engine.playBus);
            source.onended = () => {
                if (this.source === source) {
                    this.source = null;
                    this.playing = null;
                }
            };
            source.start(engine.ctx.currentTime + 0.03);
            this.source = source;
            this.playing = i;
        },
        /** The first answer to a round is the one that counts. */
        answer(i) {
            if (this.picked !== null) return;
            this.picked = i;
            if (this.answers[this.round] === undefined) this.answers = [...this.answers.slice(0, this.round), i];
        },
        nextRound() {
            this.source?.stop();
            this.picked = null;
            if (this.round + 1 < this.rounds.length) {
                this.round++;
                return;
            }
            this.finished = true;
            this.$emit('done', { percent: choosePercent(this.rounds, this.answers), sung: [], insights: [] });
        },
        /** Play again: the rounds from the start (the stars keep the best, as every lesson's do). */
        restart() {
            this.round = 0;
            this.answers = [];
            this.picked = null;
            this.finished = false;
        },
    },
};
