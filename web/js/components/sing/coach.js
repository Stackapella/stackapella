// Part by part (docs/singing-ux-plan.md, "Coach steps", "Phrase path", "Your turn card", "Step
// done"): four steps (listen, sing along, on your own, record), each one plain sentence and one big
// action; the song as a path of phrases, ticked as you practise; the Your turn card while you sing
// a phrase back; and the step-done screen. Next and Skip to record are always there.

import { STEPS } from '../../sing.js';
import { tk } from '../../i18n.js';
import { Gems, gemInk } from '../gems.js';
import { Icon } from '../icon.js';
import { KeyControl } from '../key-control.js';

const STEP = {
    listen: { name: tk('sing.coach.step.listen'), hint: tk('sing.coach.hint.listen'), action: tk('sing.coach.action.listen') },
    singAlong: { name: tk('sing.coach.step.singAlong'), hint: tk('sing.coach.hint.singAlong'), action: tk('sing.coach.action.singAlong') },
    own: { name: tk('sing.coach.step.own'), hint: tk('sing.coach.hint.own'), action: tk('sing.coach.action.own') },
    record: { name: tk('sing.coach.step.record'), hint: tk('sing.coach.hint.record'), action: tk('sing.coach.action.record') },
};
const DONE = { listen: tk('sing.coach.done.listen'), singAlong: tk('sing.coach.done.singAlong'), own: tk('sing.coach.done.own') };

export const Coach = {
    components: { Icon, KeyControl },
    props: {
        partName: { type: String, required: true },
        color: { type: String, required: true },
        step: { type: String, required: true },
        phrases: { type: Array, required: true }, // [{ index, label }]
        counts: { type: Object, required: true }, // phrase index -> times practised in this step
        here: { type: Number, default: 0 }, // the phrase you're on
        playing: Boolean,
        // The song's key (128.png), while nothing's recorded in it: Lower and Higher move the whole song.
        musicKey: { type: String, default: null },
        canLower: { type: Boolean, default: true },
        canHigher: { type: Boolean, default: true },
    },
    emits: ['action', 'phrase', 'step', 'other-part', 'lower', 'higher'],
    setup: () => ({ STEP, STEPS }),
    // 66.png: "Step 1 of 4" over the steps as bars, the step and its line, the key, Next; the phrases
    // as cards (the one you're on outlined in your colour); Choose another part and Skip to record.
    // The step's action is the transport's Play on a wide screen; a phone, with no transport while
    // coaching, keeps it as the big button.
    template: `
        <section class="flex min-h-0 flex-col overflow-y-auto" :aria-label="t('sing.coach.title', { part: partName })" data-coach>
            <div class="space-y-5 px-5 pb-5 pt-7">
                <p class="text-xs font-bold uppercase tracking-[0.14em]" :style="{ color: paintInk(color) }">{{ t('sing.coach.stepOf', { step: stepIndex + 1, total: STEPS.length }) }}</p>
                <ol class="grid grid-cols-4 gap-1.5" :aria-label="t('sing.coach.steps')">
                    <li v-for="(s, i) in STEPS" :key="s">
                        <button type="button" class="w-full text-start" :aria-current="i === stepIndex ? 'step' : null" @click="$emit('step', s)">
                            <span class="block h-1 rounded-full" :style="{ background: i <= stepIndex ? color : 'var(--color-line)' }" aria-hidden="true"></span>
                            <span class="mt-2 block text-xs font-semibold leading-tight" :class="i === stepIndex ? 'text-cream' : 'text-muted'">{{ t(STEP[s].name) }}</span>
                        </button>
                    </li>
                </ol>
                <div>
                    <h2 class="text-3xl font-extrabold tracking-tight text-cream">{{ t(STEP[step].name) }}</h2>
                    <p class="mt-2 text-muted">{{ t(STEP[step].hint, { part: partName }) }}</p>
                </div>
                <KeyControl v-if="musicKey" :music-key="musicKey" :can-lower="canLower" :can-higher="canHigher" @lower="$emit('lower')" @higher="$emit('higher')" />
                <button type="button" class="btn-primary btn-lg w-full" :class="step === 'record' ? '' : 'md:hidden'" @click="$emit('action')"><Icon :name="step === 'record' ? 'record' : 'play'" :size="18" /> {{ t(STEP[step].action, { part: partName, number: here + 1 }) }}</button>
                <button v-if="step !== 'record' && stepIndex < STEPS.length - 1" type="button" class="btn-secondary w-full" @click="$emit('step', STEPS[stepIndex + 1])">{{ t('sing.coach.next', { step: t(STEP[STEPS[stepIndex + 1]].name) }) }}</button>
            </div>
            <div v-if="step !== 'record'" class="px-5">
                <div class="flex items-baseline justify-between gap-3">
                    <h3 class="font-bold text-cream">{{ t('sing.coach.phrasesTitle') }}</h3>
                    <span class="tabular-nums text-sm text-muted">{{ t('sing.coach.heardOf', { done: doneCount, total: phrases.length }) }}</span>
                </div>
                <ol class="mt-3 space-y-2 pb-4" :aria-label="t('sing.coach.path')">
                    <li v-for="p in phrases" :key="p.index">
                        <button type="button" class="flex w-full items-center gap-3 rounded-2xl border p-3 text-start transition" :class="p.index === here ? '' : 'border-transparent hover:bg-raised/40'" :style="p.index === here ? { borderColor: color } : null" :aria-current="p.index === here ? 'true' : null" @click="$emit('phrase', p.index)">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full border-2 text-sm font-bold" :style="counts[p.index] ? { background: color, borderColor: color, color: paintText(color) } : p.index === here ? { borderColor: color, color: 'var(--color-cream)' } : { borderColor: 'var(--color-line)', color: 'var(--color-muted)' }">
                                <Icon v-if="counts[p.index]" name="check" :size="16" /><template v-else>{{ p.index + 1 }}</template>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate font-bold" :class="counts[p.index] || p.index <= here ? 'text-cream' : 'text-cream/60'" translate="no">{{ p.label }}</span>
                                <span class="block tabular-nums text-xs text-muted">{{ counts[p.index] ? t('sing.coach.practised', { count: counts[p.index] }) : p.start ? t('sing.coach.startsOn', { note: p.start }) : t('sing.coach.notYet') }}</span>
                            </span>
                        </button>
                    </li>
                </ol>
            </div>
            <div class="mt-auto flex flex-col items-start gap-1 p-5 text-sm">
                <button type="button" class="link-quiet min-h-9" @click="$emit('other-part')">{{ t('sing.coach.otherPart') }}</button>
                <button v-if="step !== 'record'" type="button" class="link-quiet min-h-9" @click="$emit('step', 'record')">{{ t('sing.coach.skipToRecord') }}</button>
            </div>
        </section>`,
    computed: {
        stepIndex: (vm) => STEPS.indexOf(vm.step),
        doneCount: (vm) => vm.phrases.filter((p) => vm.counts[p.index]).length,
    },
};


/** A step done: a tick, a kind sentence, the checklist, the next step, or once more. */
export const StepDone = {
    components: { Icon },
    props: { step: { type: String, required: true }, partName: { type: String, required: true }, color: { type: String, required: true }, phrases: { type: Array, required: true }, next: String },
    emits: ['next', 'again'],
    setup: () => ({ DONE, STEP }),
    template: `
        <div class="m-auto flex max-w-lg flex-col items-center gap-5 p-6 text-center">
            <span class="grid size-16 place-items-center rounded-full" :style="{ background: color, color: paintText(color) }"><Icon name="check" :size="32" /></span>
            <h2 ref="heading" tabindex="-1" class="text-2xl font-extrabold text-cream outline-none">{{ t(DONE[step], { part: partName }) }}</h2>
            <ul class="w-full space-y-1.5 text-start text-sm text-cream">
                <li v-for="p in phrases" :key="p.index" class="flex items-center gap-2"><Icon name="check" :size="16" :style="{ color }" /> <span translate="no" class="truncate">{{ p.label }}</span></li>
            </ul>
            <div class="flex flex-wrap justify-center gap-3">
                <button v-if="next" type="button" class="btn-primary" @click="$emit('next')">{{ t('sing.coach.next', { step: t(STEP[next].name) }) }}</button>
                <button type="button" class="btn-secondary" @click="$emit('again')">{{ t('sing.coach.onceMore') }}</button>
            </div>
        </div>`,
    mounted() {
        this.$refs.heading.focus();
    },
};

/**
 * Part by part, between plays (66.png): the phrase you're on, its first note big (a tap plays it),
 * and its notes as a roll, yours in your colour and the others dashed where they come close.
 */
export const PhraseStage = {
    components: { Gems },
    setup: () => ({ gemInk }),
    props: {
        number: { type: Number, required: true },
        start: { type: String, default: '' },
        notes: { type: Array, required: true }, // [{ beat, length, pitch, name }] in the key sung
        others: { type: Array, default: () => [] }, // [{ beat, length, pitch }]
        from: { type: Number, required: true }, // the phrase's beats
        to: { type: Number, required: true },
        names: { type: Function, required: true }, // a row's note name
        color: { type: String, required: true },
    },
    emits: ['first-note'],
    template: `
        <section class="m-auto w-full max-w-5xl px-6 py-8 md:px-12" data-phrase-stage>
            <p class="text-sm font-bold uppercase tracking-[0.14em] text-muted">{{ t('sing.coach.phraseReady', { number }) }}</p>
            <div class="mt-1 flex flex-wrap items-baseline gap-x-4">
                <button type="button" class="text-7xl font-extrabold leading-none tracking-tight md:text-8xl" :style="{ color: paintInk(color) }" :aria-label="start ? t('sing.firstNoteOf', { note: start }) : t('sing.firstNotePlay')" translate="no" @click="$emit('first-note')">{{ start || '♪' }}</button>
                <span class="text-xl text-muted">{{ t('sing.coach.startsHere', { count: notes.length }) }}</span>
            </div>
            <div class="relative mt-6 overflow-hidden rounded-2xl border border-line bg-ink/40" role="img" :aria-label="t('sing.coach.rollLabel', { notes: notes.map((n) => n.name).join(', ') })">
                <Gems measure />
                <div v-for="row in rows" :key="row.pitch" class="flex h-6 border-b border-line/40 last:border-b-0" :class="row.sharp ? 'bg-raised/30' : ''">
                    <span class="w-11 shrink-0 border-e border-line px-1.5 text-end tabular-nums text-[10px] leading-6" :class="row.yours ? 'text-cream' : 'text-muted'" translate="no">{{ row.name }}</span>
                    <div class="relative flex-1">
                        <span v-for="(o, i) in row.others" :key="'o' + i" class="absolute inset-y-1 rounded-full border-[1.5px] border-dashed border-muted/60" :style="place(o)"></span>
                        <span v-for="(n, i) in row.notes" :key="i" class="absolute inset-y-0.5 truncate px-2.5 tabular-nums text-[10px] font-bold leading-[1.15rem]" :data-gem="color" :style="{ ...place(n), color: gemInk(color) }" translate="no">{{ n.name }}</span>
                    </div>
                </div>
            </div>
            <p class="mt-4 text-sm text-muted">{{ t('sing.coach.rollHint') }}</p>
        </section>`,
    computed: {
        rows() {
            const low = Math.min(...this.notes.map((n) => n.pitch)) - 1;
            const high = Math.max(...this.notes.map((n) => n.pitch)) + 1;
            const rows = [];
            for (let pitch = high; pitch >= low; pitch--) {
                const notes = this.notes.filter((n) => n.pitch === pitch);
                rows.push({ pitch, name: this.names(pitch), sharp: [1, 3, 6, 8, 10].includes(((pitch % 12) + 12) % 12), yours: notes.length > 0, notes, others: this.others.filter((o) => o.pitch === pitch) });
            }
            return rows;
        },
    },
    methods: {
        place(note) {
            const span = Math.max(1e-6, this.to - this.from);
            const left = ((note.beat - this.from) / span) * 100;
            return { left: `calc(${left}% + 2px)`, width: `calc(${(note.length / span) * 100}% - 4px)` };
        },
    },
};

