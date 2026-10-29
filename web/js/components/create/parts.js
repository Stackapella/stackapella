// The other parts (docs/template-creator-plan.md, "Sing it" 5): suggest them (three harmony
// styles to hear and choose: Simple, Warm and Bright), then "Try another chord" on any bar, with
// the chord names only under Advanced; or write them in the editor; or keep just the tune. A copy
// or a template with parts keeps its own. Then who sings with 2, 3 or 4 singers.

import { markRaw } from 'vue';
import { harmonize, STYLES } from '../../arrange.js';
import { chordName, parseKey } from '../../notes.js';
import { formatNumber, t, tk } from '../../i18n.js';
import { finishParts } from '../../creator.js';
import { playTemplate } from '../../creator-audio.js';
import { beatsPerBar } from '../../theme.js';
import { templatePartName } from '../../templates.js';
import { Icon } from '../icon.js';
import { INSTRUMENTS, withInstruments } from '../../instrument-parts.js';

const STYLE_WORDS = {
    simple: { name: tk('create.parts.style.simple'), hint: tk('create.parts.style.simpleHint') },
    warm: { name: tk('create.parts.style.warm'), hint: tk('create.parts.style.warmHint') },
    bright: { name: tk('create.parts.style.bright'), hint: tk('create.parts.style.brightHint') },
};
const INSTRUMENT_WORDS = { piano: tk('create.parts.instrument.piano'), guitar: tk('create.parts.instrument.guitar'), 'bass-guitar': tk('create.parts.instrument.bass'), drums: tk('create.parts.instrument.drums') };
// A template has at most six parts: the four voices, and two instruments.
const MOST_INSTRUMENTS = 2;

export const PartsStep = {
    components: { Icon },
    props: { draft: { type: Object, required: true } },
    emits: ['change', 'arrange', 'write'],
    data: () => ({ player: null, playing: null, advanced: false, MOST_INSTRUMENTS }),
    setup: () => ({ STYLES, STYLE_WORDS, INSTRUMENT_WORDS, INSTRUMENT_KEYS: Object.keys(INSTRUMENTS), templatePartName, formatNumber }),
    template: `
        <section class="space-y-6">
            <div>
                <h2 class="text-4xl font-extrabold tracking-tight text-cream">{{ t('create.parts.title') }}</h2>
                <p class="mt-2 text-muted">{{ t('create.parts.hint') }}</p>
            </div>
            <div class="grid gap-3" role="radiogroup" :aria-label="t('create.parts.how')">
                <button v-for="o in options" :key="o.harmony" type="button" role="radio" :aria-checked="draft.harmony === o.harmony"
                    class="radio-card items-center gap-4 p-4 md:p-5" @click="o.pick()">
                    <span class="radio-mark" aria-hidden="true"></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2 font-bold text-cream">{{ t(o.title) }}<span v-if="o.recommended" class="text-xs font-bold uppercase tracking-widest text-accent">{{ t('create.parts.recommended') }}</span></span>
                        <span class="block text-sm text-muted">{{ t(o.hint) }}</span>
                    </span>
                    <span class="hidden shrink-0 gap-1.5 sm:flex" aria-hidden="true">
                        <span v-for="(part, i) in chipParts" :key="part.key" class="grid size-7 place-items-center rounded-lg text-xs font-extrabold"
                            :style="o.only && !part.melody ? null : { background: 'var(--color-part-' + (i % 8 + 1) + ')', color: 'var(--color-part-text-' + (i % 8 + 1) + ')' }" :class="o.only && !part.melody ? 'bg-raised text-muted' : ''">{{ templatePartName(part).slice(0, 1) }}</span>
                    </span>
                </button>
            </div>

            <template v-if="draft.harmony === 'suggest'">
                <div class="grid gap-3 md:grid-cols-3" role="radiogroup" :aria-label="t('create.parts.styles')">
                    <div v-for="style in STYLES" :key="style" class="card flex flex-col gap-3 p-4" :class="draft.style === style ? 'bg-(--sel-wash) ring-2 ring-(--selected)' : ''">
                        <button type="button" role="radio" :aria-checked="draft.style === style" class="text-start" @click="choose(style)">
                            <p class="font-bold text-cream">{{ t(STYLE_WORDS[style].name) }}</p>
                            <p class="text-sm text-muted">{{ t(STYLE_WORDS[style].hint) }}</p>
                        </button>
                        <button type="button" class="btn-secondary btn-sm mt-auto" @click="hear(style)"><Icon :name="playing === style ? 'stop' : 'play'" :size="16" /> {{ playing === style ? t('studio.stop') : t('create.parts.hear') }}</button>
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-bold text-cream">{{ t('create.parts.chordsTitle') }}</h3>
                        <label class="check-row text-muted"><input v-model="advanced" type="checkbox" class="checkbox" /> {{ t('create.parts.advanced') }}</label>
                    </div>
                    <ul class="divide-y divide-line rounded-2xl border border-line">
                        <li v-for="(slot, i) in slots" :key="i" class="flex flex-wrap items-center gap-3 px-4 py-2.5">
                            <span class="min-w-0 flex-1 text-cream">{{ barLabel(slot) }}<span v-if="advanced" class="ms-2 font-bold" translate="no">{{ chordName(slot.options[slot.choice], 0, draft.template.key) }}</span><span v-else-if="slot.choice > 0" class="ms-2 text-sm text-muted">{{ t('create.parts.tryOf', { n: formatNumber(slot.choice + 1), count: formatNumber(slot.options.length) }) }}</span></span>
                            <button type="button" class="btn-ghost btn-sm" :disabled="slot.options.length < 2" @click="another(i)">{{ t('create.parts.another') }}</button>
                            <button type="button" class="btn-ghost btn-sm" :aria-label="t('create.parts.hearFrom', { bar: barLabel(slot) })" @click="hear(draft.style, slot.beat)"><Icon name="play" :size="16" /></button>
                        </li>
                    </ul>
                    <p v-if="advanced && warnings.length" class="text-sm text-muted">{{ t('create.parts.parallels', { bars: warnings.join(', ') }) }}</p>
                </div>
                <!-- Instrument parts (docs/mvp-music-plan.md): played under the voices from the chords above. -->
                <fieldset class="space-y-3">
                    <legend class="font-bold text-cream">{{ t('create.parts.instruments') }}</legend>
                    <p class="text-sm text-muted">{{ t('create.parts.instrumentsHint') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="key in INSTRUMENT_KEYS" :key="key" class="filter-chip cursor-pointer"><input type="checkbox" class="sr-only" :checked="hasInstrument(key)" :disabled="!hasInstrument(key) && instruments.length >= MOST_INSTRUMENTS" @change="toggleInstrument(key)" />{{ t(INSTRUMENT_WORDS[key]) }}</label>
                    </div>
                </fieldset>
            </template>

            <div v-if="versions.length" class="card space-y-2 p-5">
                <h3 class="font-bold text-cream">{{ t('create.parts.versions') }}</h3>
                <ul class="space-y-1 text-sm text-cream">
                    <li v-for="v in versions" :key="v.count">{{ t('create.parts.version', { count: v.count, parts: v.parts }) }}</li>
                </ul>
            </div>
        </section>`,
    computed: {
        /** The ways to make the other parts, as cards (a copy with parts can keep its own). */
        options() {
            const all = [
                { harmony: 'suggest', title: tk('create.parts.suggest'), hint: tk('create.parts.suggestHint'), recommended: true, pick: () => this.choose(this.draft.style) },
                { harmony: 'write', title: tk('create.parts.write'), hint: tk('create.parts.writeHint'), pick: () => this.$emit('write') },
                { harmony: 'none', title: tk('create.parts.none'), hint: tk('create.parts.noneHint'), only: true, pick: () => this.tuneOnly() },
            ];
            return this.hadParts ? [{ harmony: 'keep', title: tk('create.parts.keep'), hint: tk('create.parts.keepHint'), pick: () => this.$emit('change', { harmony: 'keep' }) }, ...all] : all;
        },
        /** The parts the song will have, for the cards' chips: the template's own once it has them,
         * else the tune and the parts a suggestion adds. */
        chipParts() {
            const parts = this.draft.template.parts;
            if (parts.length > 1) return parts;
            const melody = parts.find((p) => p.melody) ?? parts[0];
            if (!melody?.guide.notes.length) return parts;
            const { tonic, minor } = parseKey(this.draft.template.key);
            return harmonize({ ...this.draft.template, parts: [melody] }, { style: this.draft.style, key: { tonic, mode: minor ? 'minor' : 'major' }, chords: this.givenChords }).template.parts;
        },
        hadParts() {
            return this.draft.harmony === 'keep' || (this.draft.mode !== 'sing' && this.draft.mode !== 'write' && this.draft.template.parts.length > 1 && this.draft.harmony !== 'suggest' && this.draft.harmony !== 'write');
        },
        /** Start from chords: the parts are voiced on the chords sung over (as views/create.js does), never new ones. */
        givenChords() {
            return this.draft.mode === 'chords' ? this.draft.template.chords : null;
        },
        arranged() {
            if (this.draft.harmony !== 'suggest') return null;
            const melody = this.draft.template.parts.find((p) => p.melody) ?? this.draft.template.parts[0];
            if (!melody?.guide.notes.length) return null;
            const { tonic, minor } = parseKey(this.draft.template.key);
            return harmonize({ ...this.draft.template, parts: [melody] }, { style: this.draft.style, choices: this.draft.choices, key: { tonic, mode: minor ? 'minor' : 'major' }, chords: this.givenChords }).result;
        },
        instruments() {
            return this.draft.instruments ?? [];
        },
        slots() {
            return this.arranged?.slots ?? [];
        },
        warnings() {
            return (this.arranged?.warnings ?? []).map((beat) => this.barNumber(beat));
        },
        versions() {
            const done = finishParts(this.draft.template);
            return Object.entries(done.voicings ?? {}).map(([count, keys]) => ({ count: Number(count), parts: keys.map((k) => templatePartName(done.parts.find((p) => p.key === k))).join(', ') }));
        },
    },
    unmounted() {
        this.player?.stop();
    },
    methods: {
        chordName,
        barNumber(beat) {
            const perBar = beatsPerBar(this.draft.template.timeSignature);
            return formatNumber(Math.floor((beat - this.draft.template.pickupBeats) / perBar) + 1);
        },
        barLabel(slot) {
            const perBar = beatsPerBar(this.draft.template.timeSignature);
            const intoBar = slot.beat - this.draft.template.pickupBeats;
            if (intoBar < 0) return t('create.parts.pickup');
            const half = this.draft.template.timeSignature === '4/4' && Math.abs(intoBar % perBar - 2) < 1e-6;
            return half ? t('create.parts.barHalf', { bar: this.barNumber(slot.beat) }) : t('create.parts.bar', { bar: this.barNumber(slot.beat) });
        },
        choose(style) {
            this.$emit('arrange', { style, choices: style === this.draft.style ? this.draft.choices : {} });
        },
        /** The next chord to try for a slot (round to the first after the last), heard at once: from the chord before it to its end. */
        async another(i) {
            const slot = this.slots[i];
            const choices = { ...this.draft.choices, [i]: (slot.choice + 1) % slot.options.length };
            this.$emit('arrange', { style: this.draft.style, choices });
            await this.$nextTick();
            this.hear(this.draft.style, this.slots[i - 1]?.beat ?? slot.beat, this.slots[i].endBeat);
        },
        hasInstrument(key) {
            return this.instruments.includes(key);
        },
        toggleInstrument(key) {
            this.$emit('change', { instruments: this.hasInstrument(key) ? this.instruments.filter((k) => k !== key) : [...this.instruments, key] });
        },
        tuneOnly() {
            const melody = this.draft.template.parts.find((p) => p.melody) ?? this.draft.template.parts[0];
            this.$emit('change', { harmony: 'none', template: { ...this.draft.template, parts: [melody], chords: undefined } });
        },
        /** Hears a style (or the chosen one from a bar) with every part. */
        async hear(style, fromBeat = 0, toBeat = null) {
            const was = this.playing;
            await this.player?.stop();
            if (was === style && !fromBeat && toBeat === null) return;
            const melody = this.draft.template.parts.find((p) => p.melody) ?? this.draft.template.parts[0];
            const { tonic, minor } = parseKey(this.draft.template.key);
            const { template } = harmonize({ ...this.draft.template, parts: [melody] }, { style, choices: style === this.draft.style ? this.draft.choices : {}, key: { tonic, mode: minor ? 'minor' : 'major' }, chords: this.givenChords });
            this.playing = style;
            const player = await playTemplate(withInstruments(template, this.instruments), { fromBeat, toBeat, singers: 4 });
            this.player = markRaw(player); // raw, so `this.player === player` below can be true (a reactive copy never is)
            await player.done;
            if (this.player === player) {
                this.player = null;
                this.playing = null;
            }
        },
    },
};
