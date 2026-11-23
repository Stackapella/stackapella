// The whole song's Stage (docs/singing-ux-plan.md, "Big words", "Lit syllable", "Get ready"; the
// Sing redesign, after Hillel's 2a): the current line huge, with its hyphens ("A-maz-ing"), sung
// words bright and the current syllable in your part's colour (underlined while listening, a solid
// pill on your turn and while recording), with "Alto sings G4 on 'grace'" under it; the next line
// dim. Get ready is a card at the top a bar before an entry after a rest, a dot per beat. Words
// or Staff; a part with no words (a bass on "oo") shows the staff. On your turn, a slim tuning
// meter across the width (the coaching plan's "edge bar", never a stripe down an edge): a dot that
// sits in the middle when you're on the note, and the syllable glows when you hit it.


import { t, tk } from '../../i18n.js';
import { joinSyllables } from '../practice.js';
import { StaffLine } from './staff.js';
import { Icon } from '../icon.js';

/** Get ready: "Your turn in 2 beats", a dot per beat of the bar, the coming beats outlined. */
export const GetReady = {
    props: { beatsLeft: { type: Number, required: true }, beatsPerBar: { type: Number, required: true }, color: String },
    template: `
        <div class="flex items-center gap-4 rounded-3xl border-2 p-5" :style="{ borderColor: color, background: 'color-mix(in srgb, ' + color + ' 14%, var(--color-panel))' }" role="status">
            <div class="min-w-0 flex-1">
                <p class="text-2xl font-bold text-cream">{{ t('sing.getReady.card') }}</p>
                <p class="text-cream">{{ t('sing.getReady.inBeats', { count }) }}</p>
            </div>
            <div class="flex gap-2" aria-hidden="true">
                <span v-for="i in beatsPerBar" :key="i" class="size-6 rounded-full border-2" :style="{ borderColor: color, background: i <= beatsPerBar - count ? color : 'transparent' }"></span>
            </div>
        </div>`,
    computed: {
        count: (vm) => Math.max(1, Math.min(vm.beatsPerBar, Math.ceil(vm.beatsLeft - 1e-6))),
    },
};

/**
 * A line of words, karaoke style (the Stage, and the recording stage): sung words bright, the
 * syllable now in your colour (underlined, or a solid pill with `pill`, glowing with `glow`), the
 * coming ones dim. Its hyphens join syllables ("A-maz-ing").
 */
export const KaraokeLine = {
    // small: the studio's line (20 to 24 px), a thinner underline that fits its fixed height.
    // color: a part's colour, painted here in the theme showing (so a theme change repaints it); the underlined
    // syllable takes the part's ink, deeper on a light theme, so it passes AA.
    props: { line: { type: Object, required: true }, position: { type: Number, default: 0 }, color: { type: String, required: true }, pill: Boolean, glow: Boolean, small: Boolean },
    template: `
        <div translate="no">
            <template v-for="(s, i) in line.syllables" :key="i"><span class="rounded-lg transition-colors motion-reduce:transition-none"
                :class="s.borrowed ? 'opacity-40' : i === active ? (pill ? 'px-1.5' : small ? 'underline decoration-2 underline-offset-4' : 'underline decoration-[6px] underline-offset-8') : i < active || active < 0 ? 'text-cream' : 'text-cream/40'"
                :style="i === active ? (pill ? { background: paint(color), color: paintText(color), boxShadow: glow ? '0 0 22px ' + paint(color) : 'none' } : { color: paintInk(color) }) : null">{{ s.text }}</span>{{ s.text.endsWith('-') ? '' : ' ' }}</template>
        </div>`,
    computed: {
        active() {
            let index = -1;
            this.line.syllables.forEach((s, i) => {
                if (!s.borrowed && this.position >= s.seconds - 0.02) index = i;
            });
            return index;
        },
    },
};

// The Staff view's chips and their directions (Hillel's design: "NOW C4", "NEXT C4 same", "THEN D4 ↑ up").
const CHIPS = { now: tk('sing.chip.now'), next: tk('sing.chip.next'), then: tk('sing.chip.then') };
const DIRS = { up: tk('sing.chip.up'), down: tk('sing.chip.down'), same: tk('sing.chip.same') };

export const Stage = {
    components: { GetReady, KaraokeLine, StaffLine, Icon },
    emits: ['hear-note'],
    props: {
        line: Object, // the current line: { syllables: [{ text, seconds }] }
        next: Object,
        position: { type: Number, default: 0 },
        color: { type: String, required: true },
        turn: Boolean, // your turn or recording: the solid pill
        recording: Boolean,
        nowText: { type: String, default: '' },
        sings: { type: String, default: '' }, // what your part sings now, while listening
        comingUp: { type: String, default: '' }, // your first note, before you come in
        lineOf: { type: String, default: '' }, // which line of how many (98.png)
        start: { type: Object, default: null }, // { word, note } where you come in, while nothing plays
        getReady: Object, // { beatsLeft, beatsPerBar } a bar before an entry, or null
        staff: Object, // a staffLayout for the current line, when the staff shows
        current: { type: Number, default: -1 }, // the note sounding on the staff
        words: { type: Boolean, default: true }, // the part has words
        tuning: { type: Object, default: null }, // your voice now: { cents, lit } on your turn, or null
        hint: { type: String, default: null }, // 'higher' or 'lower' (the lane's words), on your turn
        chips: { type: Array, default: () => [] }, // the Staff view's: [{ key: now|next|then, name, dir: up|down|same|null }]
    },
    setup: () => ({ joinSyllables, CHIPS, DIRS }),
    template: `
        <div class="relative flex min-h-0 flex-1 flex-col justify-center gap-5 px-5 py-6 md:px-12" data-ui="sing-stage">
            <GetReady v-if="getReady" v-bind="getReady" :color="color" class="absolute inset-x-4 top-3 md:inset-x-12" />
            <!-- Hillel's Staff design (2026-11-23): which line and where you start in one row; the staff as wide as the stage,
                 each word and its note's name under its note (no second line of words: the coordinator's call 11); then the
                 note now, next and after as chips, and the next line's words at the end. -->
            <template v-if="staff">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <p v-if="turn && recording" class="text-lg font-bold" :style="{ color: paintInk(color) }">{{ t('sing.turn.title') }}</p>
                    <p v-else-if="lineOf" class="t-counter" data-line-of>{{ lineOf }}</p>
                    <span v-else></span>
                    <div v-if="start && !recording" class="flex w-fit max-w-full flex-wrap items-center gap-3 rounded-full border border-line py-1.5 pe-1.5 ps-4 text-[15px] text-cream" data-start-pill>
                        <span class="size-[9px] shrink-0 rounded-full" :style="{ background: color }" aria-hidden="true"></span>
                        <span translate="no">{{ start.word ? t('sing.startWith', { word: start.word.replace(/-$/, ''), note: start.note }) : t('sing.startOn', { note: start.note }) }}</span>
                        <button type="button" class="tap-44 inline-flex min-h-8 items-center gap-1.5 rounded-full bg-raised px-3.5 text-[13px] font-semibold text-cream hover:bg-line" @click="$emit('hear-note')"><Icon name="note" :size="14" /> {{ t('sing.hearNote') }}</button>
                    </div>
                    <p v-else-if="comingUp" class="text-sm text-subtle">{{ comingUp }}</p>
                </div>
                <slot />
                <StaffLine :layout="staff" :current="current" :color="color" wrap big class="mx-auto w-full max-w-[1500px] text-cream" />
                <div class="flex flex-wrap items-center justify-between gap-x-10 gap-y-4">
                    <ul v-if="chips.length" class="flex flex-wrap gap-2.5" aria-live="off" data-now-chips>
                        <li v-for="c in chips" :key="c.key" class="flex items-baseline gap-2.5 whitespace-nowrap rounded-xl px-4 py-2.5" :class="c.key === 'now' ? '' : 'bg-raised'" :style="c.key === 'now' ? { background: 'color-mix(in srgb, ' + paint(color) + ' 16%, var(--color-panel))' } : null" :data-chip="c.key">
                            <span class="text-xs font-bold uppercase tracking-[0.1em]" :class="c.key === 'now' ? '' : 'text-subtle'" :style="c.key === 'now' ? { color: paintInk(color) } : null">{{ t(CHIPS[c.key]) }}</span>
                            <span class="text-xl font-semibold tabular-nums" :class="c.key === 'now' ? '' : 'text-cream'" :style="c.key === 'now' ? { color: paintInk(color) } : null" translate="no">{{ c.name }}</span>
                            <span v-if="c.dir" class="text-sm text-subtle">{{ t(DIRS[c.dir]) }}</span>
                        </li>
                    </ul>
                    <p v-else-if="nowText" class="text-base font-semibold text-cream" aria-live="off">{{ nowText }}</p>
                    <p v-if="words && next" class="flex min-w-0 items-baseline gap-3" data-next-line><span class="shrink-0 text-xs font-bold uppercase tracking-[0.1em] text-subtle">{{ t('sing.chip.nextLine') }}</span><span class="truncate text-[22px] font-semibold text-muted" translate="no">{{ joinSyllables(next.syllables) }}</span></p>
                </div>
                <p v-if="turn" class="flex min-h-7 items-center gap-2 text-base font-bold text-cream" role="status" :aria-label="tuningLabel">
                    <template v-if="hint"><span class="grid size-7 place-items-center rounded-full text-ink" :style="{ background: color }" aria-hidden="true">{{ hint === 'higher' ? '▲' : '▼' }}</span>{{ hint === 'higher' ? t('sing.hint.higher') : t('sing.hint.lower') }}</template>
                    <template v-else-if="tuning?.lit"><span class="grid size-7 place-items-center rounded-full text-ink" :style="{ background: color }" aria-hidden="true">✓</span>{{ t('sing.tuning.on') }}</template>
                </p>
            </template>
            <template v-else>
            <p v-if="turn && recording" class="text-lg font-bold" :style="{ color: paintInk(color) }">{{ t('sing.turn.title') }}</p>
            <template v-else-if="lineOf || start">
                <p v-if="lineOf" class="t-counter" data-line-of>{{ lineOf }}</p>
                <div v-if="start" class="flex w-fit max-w-full flex-wrap items-center gap-3 rounded-full border border-line py-1.5 pe-1.5 ps-4 text-[15px] text-cream" data-start-pill>
                    <span class="size-[9px] shrink-0 rounded-full" :style="{ background: color }" aria-hidden="true"></span>
                    <span translate="no">{{ start.word ? t('sing.startWith', { word: start.word.replace(/-$/, ''), note: start.note }) : t('sing.startOn', { note: start.note }) }}</span>
                    <button type="button" class="tap-44 inline-flex min-h-8 items-center gap-1.5 rounded-full bg-raised px-3.5 text-[13px] font-semibold text-cream hover:bg-line" @click="$emit('hear-note')"><Icon name="note" :size="14" /> {{ t('sing.hearNote') }}</button>
                </div>
                <p v-else-if="comingUp" class="text-sm text-subtle">{{ comingUp }}</p>
            </template>
            <p v-else-if="comingUp" class="text-sm text-subtle">{{ comingUp }}</p>
            <slot />
            <KaraokeLine v-if="words && line" :line="line" :position="position" :color="color" :pill="turn" :glow="!!tuning?.lit" :class="staff ? 'text-2xl md:text-4xl' : 'text-5xl leading-[1.08] md:text-7xl'" class="font-bold tracking-tight" />
            <p v-else-if="!words && !staff" class="text-2xl text-muted">{{ t('sing.noWords') }}</p>
            <p v-if="!turn && sings" class="text-base font-bold md:text-lg" :style="{ color: paintInk(color) }" aria-live="off">{{ sings }}</p>
            <p v-else-if="nowText" class="text-base font-semibold text-cream md:text-lg" aria-live="off">{{ nowText }}</p>
            <p v-if="words && next" translate="no" class="text-2xl font-bold text-cream/45 md:text-4xl">{{ joinSyllables(next.syllables) }}</p>
            <p v-if="turn" class="flex min-h-7 items-center gap-2 text-base font-bold text-cream" role="status" :aria-label="tuningLabel">
                <template v-if="hint"><span class="grid size-7 place-items-center rounded-full text-ink" :style="{ background: color }" aria-hidden="true">{{ hint === 'higher' ? '▲' : '▼' }}</span>{{ hint === 'higher' ? t('sing.hint.higher') : t('sing.hint.lower') }}</template>
                <template v-else-if="tuning?.lit"><span class="grid size-7 place-items-center rounded-full text-ink" :style="{ background: color }" aria-hidden="true">✓</span>{{ t('sing.tuning.on') }}</template>
            </p>
            </template>
        </div>`,
    computed: {
        tuningLabel() {
            if (!this.tuning) return t('sing.tuning.none');
            if (this.tuning.lit) return t('sing.tuning.on');
            return this.tuning.cents < 0 ? t('sing.tuning.under', { cents: Math.round(-this.tuning.cents) }) : t('sing.tuning.over', { cents: Math.round(this.tuning.cents) });
        },
    },
};
