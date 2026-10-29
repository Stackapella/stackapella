// Start from chords (docs/song-builder-plan.md, "The flow", steps 2 to 4): pick a feel (the
// progressions by family, each playable; the blues as one card with its forms, turnarounds and
// feels), then a key for your voice (from the range the app knows, or a voice type) with Higher and
// Lower and a tempo, then sing or hum over the backing as it loops: the count-in, a few times round,
// and the tune is written down from it. The chords then become the alto, tenor and bass (the parts
// step), so the backing you hummed over is the harmony others sing. Each card shows its chords as
// Shape (no theory: height is distance from home, colour its job), Numbers or Letters, lit in turn as
// it plays; the whole card is the choice, and its play button only plays.

import { audio, engine } from '../../audio.js';
import { hzToMidi, midiName } from '../../dsp.js';
import { formatNumber, t, tk } from '../../i18n.js';
import { trackFrame, trackInWorker } from '../../pitchtrack.js';
import { transcribe } from '../../transcribe.js';
import { backingFor, renderSongBacking } from '../../backing.js';
import { STYLES as BAND_STYLES } from '../../band.js';
import { playBackingLoop, readyToSing } from '../../creator-audio.js';
import { gridFor } from '../../creator.js';
import { learning, loadLearning } from '../../stores/learning.js';
import { BLUES_FORMS, blues, chordName, chordOf, chordsFor, FAMILIES, FEELS, keyName, nextKey, PROGRESSIONS, suggestKey, tuneSpan, TURNAROUNDS, VOICE_RANGES } from '../../theory.js';
import { PART_COLORS } from '../../theme.js';
import { Icon } from '../icon.js';
import { MicPicker, micError } from '../mic.js';
import { Notice } from '../form.js';
import { RangeDialog } from '../dialogs.js';
import { Switch } from '../controls.js';
import { Roll } from './roll.js';
import { ChordChart } from './chord-chart.js';
import { CountIn } from '../count-in.js';
import { ChordWalk, chordFamily, chordNames } from './chord-walk.js';
import { prefs } from '../../stores/prefs.js';

const VOICES = { soprano: tk('create.chords.voice.soprano'), alto: tk('create.chords.voice.alto'), tenor: tk('create.chords.voice.tenor'), baritone: tk('create.chords.voice.baritone'), bass: tk('create.chords.voice.bass') };
const LOW_VOICES = new Set(['tenor', 'baritone', 'bass']);
const COLOR = PART_COLORS[3];

/** The draft's progression as a key ({ tonic, mode }) from its template's key name. */
function keyOf(draft) {
    const name = draft.template.key || (draft.progression?.mode === 'minor' ? 'Am' : 'C'); // i18n-ignore: key names
    const minor = name.endsWith('m');
    const note = minor ? name.slice(0, -1) : name;
    const index = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B'].indexOf(note); // i18n-ignore: key names
    const sharps = ['C', 'C#', 'D', 'D#', 'E', 'F', 'Gb', 'G', 'G#', 'A', 'A#', 'B'].indexOf(note); // i18n-ignore: key names
    return { tonic: index >= 0 ? index : Math.max(0, sharps), mode: minor ? 'minor' : 'major' };
}

/** The backing as chosen (Hillel's MVP): the band's style, the groove, and the "oo" voices (off unless asked). */
export const backingChoice = (draft) => ({ style: 'gentle', drums: true, voices: false, ...(draft.backing ?? {}) });
const BAND_WORDS = { gentle: [tk('band.style.gentle'), tk('band.hint.gentle')], pop: [tk('band.style.pop'), tk('band.hint.pop')], swing: [tk('band.style.swing'), tk('band.hint.swing')] };

/** Plays a progression's backing loop in the draft's key and tempo (one at a time across the steps). */
const looping = {
    data: () => ({ playing: null, player: null, loopBeat: null }),
    unmounted() {
        this.stopLoop();
    },
    methods: {
        async playLoop(id, bars, { mode, swing = 0.5 } = {}) {
            if (this.playing === id) return this.stopLoop();
            await this.stopLoop();
            const key = { ...keyOf(this.draft), mode: mode ?? keyOf(this.draft).mode };
            const backing = backingFor(bars, { key, timeSignature: '4/4', style: this.draft.style ?? 'simple' });
            const bpm = this.draft.template.tempo;
            const samples = await renderSongBacking(backing, { bpm, sampleRate: engine.sampleRate, swing, ...backingChoice(this.draft) });
            this.player = await playBackingLoop(samples, { bpm, perBar: backing.perBar, beats: backing.endBeat, countIn: false }); // a preview starts at once; only singing over it counts in
            this.playing = id;
            // The chart follows the audio clock (it only reads where the loop is).
            const follow = () => {
                if (!this.player) return;
                this.loopBeat = this.player.beat();
                this.followFrame = requestAnimationFrame(follow);
            };
            this.followFrame = requestAnimationFrame(follow);
        },
        /** The bar of an `n`-bar progression playing now, or -1. */
        playingBar(n) {
            return this.loopBeat === null || this.loopBeat < 0 ? -1 : Math.floor(this.loopBeat / 4) % n;
        },
        async stopLoop() {
            cancelAnimationFrame(this.followFrame);
            this.loopBeat = null;
            this.playing = null;
            const player = this.player;
            this.player = null;
            if (player) await player.stop();
        },
    },
};

const LABELS = { letters: tk('create.chords.labels.letters'), numbers: tk('create.chords.labels.numbers'), numerals: tk('create.chords.labels.numerals') };

/** A progression's chords by bar: `{ numeral, letters, family, minor, names }`, `letters` in the draft's key, `names` the card's (in C). */
function chordList(draft, p) {
    const key = { tonic: keyOf(draft).tonic, mode: p.mode };
    const chords = chordsFor(p.bars, { tonic: key.tonic });
    let at = 0;
    return p.bars.map((bar) => bar.map((numeral) => ({ numeral, letters: chordName(chords[at++], key), names: chordNames(numeral, p.mode), ...chordFamily(numeral, p.mode) })));
}

/** The chosen chords in letters, for the footer ("C · G · Am · F"), a chord held for bars on end once ("C7 ×4"). */
export function chosenLetters(draft) {
    if (!draft.progression) return '';
    const runs = [];
    for (const { letters } of chordList(draft, draft.progression).flat()) {
        if (runs.at(-1)?.letters === letters) runs.at(-1).count++;
        else runs.push({ letters, count: 1 });
    }
    return runs.map((r) => (r.count > 1 ? `${r.letters} ${t('create.chords.repeats', { count: r.count })}` : r.letters)).join(' · ');
}

export const ChordsStep = {
    components: { ChordWalk, Icon },
    mixins: [looping],
    props: { draft: { type: Object, required: true } },
    emits: ['change'],
    data: () => ({ family: 'pop', form: 'twelve', turnaround: 'plain', feel: 'shuffle' }),
    setup: () => ({ FAMILIES, BLUES_FORMS, TURNAROUNDS, FEELS, LABELS, prefs }),
    template: `
        <section class="space-y-6">
            <div>
                <h2 class="text-3xl font-extrabold tracking-tight text-cream">{{ t('create.chords.title') }}</h2>
                <p class="mt-1 text-muted">{{ t('create.chords.hint') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                <div class="flex flex-wrap gap-2" role="radiogroup" :aria-label="t('create.chords.families')">
                    <button v-for="f in FAMILIES" :key="f.id" type="button" role="radio" :aria-checked="family === f.id ? 'true' : 'false'" class="filter-chip" @click="family = f.id">{{ t(f.name) }}</button>
                </div>
                <!-- Hillel: letters by default, numbers or numerals at a tap, remembered on this device. -->
                <div class="seg inline-flex" role="radiogroup" :aria-label="t('create.chords.labels.label')" data-chord-labels>
                    <button v-for="(word, id) in LABELS" :key="id" type="button" role="radio" :aria-checked="prefs.chordLabels === id ? 'true' : 'false'" class="seg-option" @click="prefs.chordLabels = id">{{ t(word) }}</button>
                </div>
            </div>
            <ul class="grid gap-3 sm:grid-cols-2" role="radiogroup" :aria-label="t('create.chords.title')" @keydown="arrow">
                <li v-for="(p, i) in cards" :key="p.id" :class="p.blues ? 'sm:col-span-2' : ''">
                    <div class="card relative flex h-full gap-4 p-4 transition" :class="chosen(p) ? 'bg-(--sel-wash) ring-2 ring-(--selected)' : 'hover:border-muted/60'">
                        <button type="button" class="glass-bubble-solid relative z-10 grid size-11 shrink-0 place-items-center rounded-full" :aria-label="playing === p.id ? t('studio.stop') : t('create.chords.play', { name: t(p.name) })" @click="playLoop(p.id, p.bars, { mode: p.mode, swing: p.swing ?? 0.5 })"><Icon :name="playing === p.id ? 'stop' : 'play'" :size="20" /></button>
                        <div class="min-w-0 flex-1 space-y-3">
                            <button type="button" role="radio" :aria-checked="chosen(p) ? 'true' : 'false'" :tabindex="i === focusAt ? 0 : -1" class="block w-full pe-8 text-start outline-none after:absolute after:inset-0 after:rounded-[inherit] focus-visible:after:ring-2 focus-visible:after:ring-accent" data-chords @click="choose(p)">
                                <span class="block font-bold text-cream">{{ t(p.name) }}</span>
                                <span class="block text-sm text-muted">{{ t(p.feel) }}</span>
                            </button>
                            <div v-if="p.blues" class="relative z-10 grid gap-3 sm:grid-cols-3">
                                <label class="block text-sm font-semibold text-muted">{{ t('create.chords.form') }}
                                    <select v-model="form" class="input mt-1" @change="choose(theBlues)"><option v-for="(f, id) in BLUES_FORMS" :key="id" :value="id">{{ t(f.name) }}</option></select>
                                </label>
                                <label class="block text-sm font-semibold text-muted">{{ t('create.chords.turnaround') }}
                                    <select v-model="turnaround" class="input mt-1" :disabled="BLUES_FORMS[form].fixed" @change="choose(theBlues)"><option v-for="(f, id) in TURNAROUNDS" :key="id" :value="id">{{ t(f.name) }}</option></select>
                                </label>
                                <label class="block text-sm font-semibold text-muted">{{ t('create.chords.feel') }}
                                    <select v-model="feel" class="input mt-1" @change="choose(theBlues)"><option v-for="(f, id) in FEELS" :key="id" :value="id">{{ t(f.name) }}</option></select>
                                </label>
                            </div>
                            <ChordWalk :bars="chordList(draft, p)" :label="prefs.chordLabels" :now="nowAt(p)" :faint="playing === p.id" aria-hidden="true" />
                        </div>
                        <span v-if="chosen(p)" class="gem pointer-events-none absolute end-3 top-3 grid size-7 place-items-center rounded-full" aria-hidden="true"><Icon name="check" :size="16" /></span>
                    </div>
                </li>
            </ul>
        </section>`,
    computed: {
        theBlues() {
            return { ...blues({ form: this.form, turnaround: this.turnaround, feel: this.feel }), blues: true, feel: tk('create.chords.bluesHint') };
        },
        /** The family's cards: its progressions, or the blues as one card with its choices. */
        cards() {
            return this.family === 'blues' ? [this.theBlues] : PROGRESSIONS.filter((p) => p.family === this.family);
        },
        /** The card that takes Tab: the chosen one, or the first. */
        focusAt() {
            return Math.max(0, this.cards.findIndex((p) => this.chosen(p)));
        },
    },
    created() {
        // Back on this step, the family of the chords chosen.
        const id = this.draft.progression?.id;
        const family = PROGRESSIONS.find((p) => p.id === id)?.family ?? (id ? 'blues' : null);
        if (family) this.family = family;
        if (family === 'blues') [, this.form, this.turnaround, this.feel] = id.split('-');
    },
    methods: {
        chordList,
        chosen(p) {
            return p.blues ? !!this.draft.progression && !PROGRESSIONS.some((q) => q.id === this.draft.progression.id) : this.draft.progression?.id === p.id;
        },
        /** The chord sounding now, counted through the bars (the loop's beat, read from the audio clock), or -1. */
        nowAt(p) {
            if (this.playing !== p.id) return -1;
            const b = this.playingBar(p.bars.length);
            if (b < 0) return -1;
            const k = Math.floor(((this.loopBeat % 4) * p.bars[b].length) / 4);
            return p.bars.slice(0, b).reduce((n, bar) => n + bar.length, 0) + k;
        },
        /** Arrow keys move the choice through the cards, as in any group of radio buttons. */
        arrow(event) {
            const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];
            if (!step || event.target.getAttribute('role') !== 'radio') return;
            event.preventDefault();
            const n = this.cards.length;
            const next = (this.focusAt + step + n) % n;
            this.choose(this.cards[next]);
            this.$nextTick(() => this.$el.querySelectorAll('[data-chords]')[next]?.focus());
        },
        choose(p) {
            const progression = { id: p.id, name: p.name, bars: p.bars, mode: p.mode, swing: p.swing ?? 0.5 };
            const fields = { progression };
            // A blues suggests its feel's tempo; a key of the right mode until the next step chooses.
            const template = { ...this.draft.template, timeSignature: '4/4', ...(p.tempo ? { tempo: p.tempo } : {}) };
            if (p.mode !== keyOf(this.draft).mode) {
                template.key = p.mode === 'minor' ? 'Am' : 'C'; // i18n-ignore: key names
                // A placeholder key: the key step suggests one again for this mode.
                Object.assign(fields, { keyChosen: false, keyMidi: null });
            }
            this.$emit('change', { ...fields, template });
        },
    },
};

// The key step's range chart, E2 to A5 on one scale, with its labelled notes.
const CHART = { low: 40, high: 81, marks: [40, 48, 60, 72, 81] };
const SECTIONS = ['verse', 'chorus'];

/** The voice type nearest a measured range (by its middle), to show it chosen. */
function nearestVoice(range) {
    const mid = (range.low + range.high) / 2;
    return Object.entries(VOICE_RANGES).reduce((best, [id, r]) => (Math.abs((r.low + r.high) / 2 - mid) < Math.abs((VOICE_RANGES[best].low + VOICE_RANGES[best].high) / 2 - mid) ? id : best), 'alto');
}

export const ChordsKeyStep = {
    components: { Switch, Icon, RangeDialog },
    mixins: [looping],
    props: { draft: { type: Object, required: true } },
    emits: ['change'],
    data: () => ({ voice: null, taps: [], checking: false }),
    setup: () => ({ VOICES, CHART, learning, midiName, formatNumber, BAND_STYLES, BAND_WORDS }),
    template: `
        <section class="space-y-6">
            <div>
                <h2 class="text-3xl font-extrabold tracking-tight text-cream">{{ t('create.chords.keyTitle') }}</h2>
                <p class="mt-1 text-muted">{{ t('create.chords.keyHint') }}</p>
            </div>
            <div class="grid items-start gap-4 md:grid-cols-2">
                <div class="card min-w-0 space-y-5 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 id="key-voice" class="font-bold text-cream">{{ t('create.chords.whichVoice') }}</h3>
                        <button type="button" class="link-quiet min-h-11 text-sm text-accent" @click="checking = true">{{ t('create.chords.checkRange') }}</button>
                    </div>
                    <!-- Always there, so a voice picked can be changed (and a known range overridden). -->
                    <div class="seg grid grid-cols-5" role="radiogroup" aria-labelledby="key-voice">
                        <button v-for="(word, id) in VOICES" :key="id" type="button" role="radio" :aria-checked="shownVoice === id ? 'true' : 'false'" class="seg-option min-w-0 truncate px-1 text-[13px] sm:px-2 sm:text-sm" @click="pickVoice(id)">{{ t(word) }}</button>
                    </div>
                    <div v-if="suggestion" class="space-y-4 border-t border-line pt-5">
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <p class="text-sm text-muted">{{ t('create.chords.key') }}</p>
                                <p class="text-3xl font-extrabold tracking-tight text-cream" role="status" data-key>{{ shownKey }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" class="glass-bubble grid size-11 place-items-center rounded-full text-cream" :aria-label="t('create.chords.lower')" :disabled="!lower" @click="setKey(lower)"><Icon name="chevron" :size="18" /></button>
                                <button type="button" class="glass-bubble grid size-11 place-items-center rounded-full text-cream" :aria-label="t('create.chords.higher')" :disabled="!higher" @click="setKey(higher)"><Icon name="chevron" :size="18" class="rotate-180" /></button>
                                <button type="button" class="btn-secondary" @click="playLoop('key', draft.progression.bars, { mode: draft.progression.mode, swing: draft.progression.swing })"><Icon :name="playing ? 'stop' : 'play'" :size="16" /> {{ playing ? t('studio.stop') : t('create.chords.hearIt') }}</button>
                            </div>
                        </div>
                        <div v-if="span" aria-hidden="true">
                            <div class="relative h-14 rounded-2xl border border-line bg-ink/40">
                                <span class="absolute top-3 h-2.5 rounded-full bg-muted/50" :style="bar(range)"></span>
                                <span class="absolute top-8 h-2.5" :style="bar(span)"><span class="gem gem-bar block h-full rounded-full" style="--gem: var(--color-accent)"></span></span>
                            </div>
                            <div class="relative mt-1.5 h-4 text-xs text-muted">
                                <span v-for="m in CHART.marks" :key="m" class="absolute -translate-x-1/2 first:translate-x-0 last:-translate-x-full" :style="{ insetInlineStart: pos(m) + '%' }">{{ midiName(m) }}</span>
                            </div>
                        </div>
                        <p v-if="span" class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                            <span class="flex items-center gap-1.5"><span class="h-2 w-3.5 rounded-sm bg-muted/50" aria-hidden="true"></span>{{ t('create.chords.yourVoice') }}</span>
                            <span class="flex items-center gap-1.5"><span class="h-2 w-3.5 rounded-sm bg-accent" aria-hidden="true"></span>{{ t('create.chords.theTune', { low: midiName(span.low), high: midiName(span.high) }) }}</span>
                        </p>
                        <p v-if="span" class="flex items-center gap-2 text-sm font-semibold" :class="fit === 'fits' ? 'text-good' : 'text-warn'"><span class="size-2 shrink-0 rounded-full" :class="fit === 'fits' ? 'bg-good' : 'bg-warn'" aria-hidden="true"></span>{{ verdict }}</p>
                    </div>
                </div>

                <div class="card min-w-0 divide-y divide-line p-5">
                    <div class="space-y-3 pb-5">
                        <h3 class="font-bold text-cream">{{ t('create.chords.tempo') }}</h3>
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="flex items-center gap-2">
                                <button type="button" class="glass-bubble grid size-11 place-items-center rounded-full text-cream" :aria-label="t('create.tempo.slower')" @click="setTempo(draft.template.tempo - 1)"><Icon name="minus" :size="18" /></button>
                                <label><span class="sr-only">{{ t('create.tempo.bpm') }}</span><input type="number" min="40" max="200" class="w-16 bg-transparent text-center text-2xl font-extrabold text-cream tabular-nums outline-none [appearance:textfield] focus-visible:rounded-lg focus-visible:ring-2 focus-visible:ring-accent [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none" :value="draft.template.tempo" @change="setTempo($event.target.value)" /></label>
                                <button type="button" class="glass-bubble grid size-11 place-items-center rounded-full text-cream" :aria-label="t('create.tempo.faster')" @click="setTempo(draft.template.tempo + 1)"><Icon name="plus" :size="18" /></button>
                            </div>
                            <span class="text-sm text-muted">{{ t('create.chords.perMinute') }}</span>
                            <button type="button" class="btn-secondary ms-auto" @click="tap">{{ t('create.tempo.tap') }}</button>
                        </div>
                        <p class="text-sm text-muted">{{ pace }}</p>
                    </div>
                    <div class="space-y-3 py-5">
                        <h3 id="key-feel" class="font-bold text-cream">{{ t('create.chords.feel') }}</h3>
                        <div class="seg grid grid-cols-2 rounded-2xl" role="radiogroup" aria-labelledby="key-feel">
                            <button v-for="s in [0.5, 2 / 3]" :key="s" type="button" role="radio" :aria-checked="swing === s ? 'true' : 'false'" class="seg-option min-h-13 py-2 text-center" @click="setSwing(s)">
                                <span class="block text-sm font-semibold">{{ s === 0.5 ? t('create.chords.straight') : t('create.chords.swung') }}</span>
                                <span class="block text-xs opacity-75">{{ s === 0.5 ? t('create.chords.straightHint') : t('create.chords.swungHint') }}</span>
                            </button>
                        </div>
                    </div>
                    <div class="pt-5">
                        <h3 class="font-bold text-cream">{{ t('create.chords.backing') }}</h3>
                        <div class="seg mt-3 inline-flex" role="radiogroup" :aria-label="t('create.chords.style')" data-band-style>
                            <button v-for="s in BAND_STYLES" :key="s" type="button" role="radio" :aria-checked="choice.style === s ? 'true' : 'false'" class="seg-option" @click="setBacking({ style: s })">{{ t(BAND_WORDS[s][0]) }}</button>
                        </div>
                        <p class="mt-2 text-sm text-muted">{{ t(BAND_WORDS[choice.style][1]) }}</p>
                        <Switch :on="choice.drums" :label="t('create.chords.drums')" :hint="t('create.chords.drumsHint')" @change="setBacking({ drums: $event })" />
                        <Switch :on="choice.voices" :label="t('create.chords.voices')" :hint="t('create.chords.voicesHint')" @change="setBacking({ voices: $event })" />
                    </div>
                </div>
            </div>
            <RangeDialog v-if="checking" :template="draft.template" :parts="[]" @close="checking = false" />
        </section>`,
    computed: {
        choice: (vm) => backingChoice(vm.draft),
        /** The range the app knows (the course's range check, or what's been sung), unless a voice type was picked. */
        range() {
            return this.voice ? VOICE_RANGES[this.voice] : learning.progress.range && learning.progress.range.high - learning.progress.range.low >= 9 ? learning.progress.range : null;
        },
        /** The voice shown chosen: the one picked, or the nearest to the range the app knows. */
        shownVoice() {
            return this.voice ?? (this.range ? nearestVoice(this.range) : null);
        },
        suggestion() {
            return this.range ? suggestKey(this.range, { mode: this.draft.progression?.mode ?? 'major', sections: SECTIONS }) : null;
        },
        /** Lower and Higher step from the key chosen now (not from the suggestion), so twice goes twice as far. */
        keyNow() {
            return this.draft.keyMidi ?? this.suggestion?.tonicMidi ?? null;
        },
        lower() {
            return this.range && this.keyNow !== null ? nextKey(this.range, this.keyNow, -1, { mode: this.draft.progression?.mode ?? 'major', sections: SECTIONS }) : null;
        },
        higher() {
            return this.range && this.keyNow !== null ? nextKey(this.range, this.keyNow, 1, { mode: this.draft.progression?.mode ?? 'major', sections: SECTIONS }) : null;
        },
        shownKey() {
            const name = this.draft.template.key || this.suggestion?.name;
            return name?.endsWith('m') ? t('music.minorKey', { note: name.slice(0, -1) }) : t('music.majorKey', { note: name });
        },
        /** Where the tune will likely sit in the key chosen. */
        span() {
            return this.keyNow === null ? null : tuneSpan(this.keyNow, { sections: SECTIONS });
        },
        fit() {
            if (!this.span || !this.range) return 'fits';
            if (this.span.high > this.range.high - 1) return 'high';
            return this.span.low < this.range.low + 1 ? 'low' : 'fits';
        },
        /** "Suggested for alto · fits comfortably", or what's off about the key chosen. */
        verdict() {
            if (this.fit === 'high') return t('create.chords.tooHigh');
            if (this.fit === 'low') return t('create.chords.tooLow');
            if (this.keyNow !== this.suggestion?.tonicMidi) return t('create.chords.fits');
            return this.voice ? t('create.chords.suggestedFor', { voice: t(VOICES[this.voice]).toLocaleLowerCase() }) : t('create.chords.suggestedRange');
        },
        /** The tempo in plain words: "Relaxed, walking pace · one beat every 0.67 s". */
        pace() {
            const bpm = this.draft.template.tempo;
            const feel = bpm < 70 ? tk('create.chords.pace.slow') : bpm < 100 ? tk('create.chords.pace.walking') : bpm < 125 ? tk('create.chords.pace.moving') : tk('create.chords.pace.quick');
            return t('create.chords.paceLine', { feel: t(feel), seconds: formatNumber(60 / bpm, { maximumFractionDigits: 2 }) });
        },
        swing() {
            return (this.draft.progression?.swing ?? 0.5) > 0.5 ? 2 / 3 : 0.5;
        },
    },
    watch: {
        suggestion: {
            handler(s) {
                if (s && !this.draft.keyChosen) this.setKey(s);
            },
            immediate: true,
        },
        // A range just measured (Check your range) leads, over a voice type picked before.
        'learning.progress.range'() {
            if (this.checking) this.voice = null;
        },
    },
    created() {
        loadLearning();
    },
    methods: {
        pos(midi) {
            return ((Math.min(CHART.high, Math.max(CHART.low, midi)) - CHART.low) / (CHART.high - CHART.low)) * 100;
        },
        bar(r) {
            return { insetInlineStart: this.pos(r.low) + '%', width: Math.max(2, this.pos(r.high) - this.pos(r.low)) + '%' };
        },
        pickVoice(id) {
            this.voice = id;
            this.$emit('change', { keyChosen: false, keyMidi: null, octave: LOW_VOICES.has(id) ? 12 : 0 });
        },
        setKey(option) {
            const range = this.range;
            const octave = this.voice ? (LOW_VOICES.has(this.voice) ? 12 : 0) : range && range.high < 67 ? 12 : 0;
            this.stopLoop();
            this.$emit('change', { keyChosen: true, keyMidi: option.tonicMidi ?? null, octave, template: { ...this.draft.template, key: option.name ?? keyName(option) } });
        },
        setTempo(value) {
            const bpm = Math.max(40, Math.min(200, Math.round(Number(value) || 90)));
            this.stopLoop();
            this.$emit('change', { template: { ...this.draft.template, tempo: bpm } });
        },
        /** Tap tempo: the average of the last few taps (on the person's own clock: nothing plays). */
        tap() {
            const now = performance.now();
            this.taps = [...this.taps.filter((x) => now - x < 3000), now].slice(-6);
            if (this.taps.length >= 3) {
                const gaps = this.taps.slice(1).map((x, i) => x - this.taps[i]);
                this.setTempo(60000 / (gaps.reduce((a, b) => a + b, 0) / gaps.length));
            }
        },
        setSwing(swing) {
            this.stopLoop();
            this.$emit('change', { progression: { ...this.draft.progression, swing } });
        },
        setBacking(fields) {
            this.stopLoop();
            this.$emit('change', { backing: { ...backingChoice(this.draft), ...fields } });
        },
    },
};

export const HumStep = {
    components: { ChordChart, CountIn, Icon, MicPicker, Notice, Roll },
    props: { draft: { type: Object, required: true } },
    emits: ['change', 'melody'],
    data: () => ({ state: 'idle', beat: null, live: [], player: null, problem: null, micProblem: null, audio, take: null, COLOR }),
    setup: () => ({ formatNumber }),
    template: `
        <section class="space-y-6">
            <div>
                <h2 class="text-3xl font-extrabold tracking-tight text-cream">{{ t('create.chords.humTitle') }}</h2>
                <p class="mt-1 text-muted">{{ t('create.chords.humHint', { times: formatNumber(loops) }) }}</p>
            </div>
            <div class="card space-y-5 p-5 md:p-8">
                <ChordChart :bars="draft.progression.bars" :key-of="{ tonic: key.tonic, mode: draft.progression.mode }" :current="currentBar" />
                <p v-if="state === 'counting' && countBeat" class="text-4xl font-extrabold text-cream" role="status"><CountIn :beat="countBeat" /></p>
                <p v-else-if="state === 'recording'" class="text-lg font-bold text-cream" role="status">{{ t('create.chords.roundOf', { round: formatNumber(round), times: formatNumber(loops) }) }}</p>
                <Roll v-if="state === 'recording' || state === 'counting'" :parts="[]" :live="live" :beats-per-bar="4" :pickup-beats="0" :end-beat="loopBeats * loops" :zoom="20" :playhead="playhead" :label="t('create.sing.liveLabel')" />
                <Roll v-else-if="melodyNotes.length" :parts="[{ key: 'tune', color: paint(COLOR), notes: melodyNotes }]" :beats-per-bar="4" :pickup-beats="0" :end-beat="loopBeats * loops" :zoom="20" :label="t('create.sing.notesLabel', { count: melodyNotes.length })" />
                <p v-if="state === 'working'" class="text-muted" role="status">{{ t('create.sing.working') }}</p>
            </div>
            <Notice v-if="problem" :tone="problem.tone"><p>{{ problem.text }}</p><MicPicker v-if="problem.kind === 'silent' && audio.mic" class="mt-3" /></Notice>
            <p v-if="micProblem" class="text-sm text-bad" role="alert">{{ micProblem }}</p>
            <div class="flex flex-wrap items-center gap-3">
                <button v-if="state === 'recording' || state === 'counting'" type="button" class="btn-primary btn-lg" @click="finish"><Icon name="stop" :size="18" /> {{ t('create.sing.done') }}</button>
                <template v-else>
                    <button type="button" class="btn-primary btn-lg" :disabled="state !== 'idle'" @click="sing"><Icon name="record" :size="18" /> {{ melodyNotes.length ? t('create.sing.again') : t('create.chords.start') }}</button>
                    <label class="flex items-center gap-2 text-sm text-muted">{{ t('create.chords.times') }}
                        <select class="input w-auto py-1" :value="loops" @change="$emit('change', { loops: Number($event.target.value) })"><option v-for="n in [1, 2, 3, 4]" :key="n" :value="n">{{ formatNumber(n) }}</option></select>
                    </label>
                </template>
            </div>
        </section>`,
    computed: {
        /** The count-in bar's beat now (1 to 4, with its click; beats before 0 are the count-in), or null. */
        countBeat() {
            return this.beat !== null && this.beat < 0 ? Math.floor(this.beat) + 5 : null;
        },
        loops() {
            return this.draft.loops ?? 2;
        },
        key() {
            return keyOf(this.draft);
        },
        backing() {
            return backingFor(this.draft.progression.bars, { key: { tonic: this.key.tonic, mode: this.draft.progression.mode }, timeSignature: '4/4', style: this.draft.style ?? 'simple' });
        },
        loopBeats() {
            return this.backing.endBeat;
        },
        /** Each bar's chords in letters, for the chord row. */
        bars() {
            const key = { tonic: this.key.tonic, mode: this.draft.progression.mode };
            return this.draft.progression.bars.map((bar) => bar.map((numeral) => chordName(chordOf(numeral, key.tonic), key)).join(' '));
        },
        playhead() {
            return Math.max(0, this.beat ?? 0);
        },
        currentBar() {
            if (this.beat === null || this.beat < 0) return -1;
            return Math.floor((this.beat % this.loopBeats) / 4);
        },
        round() {
            return Math.min(this.loops, Math.floor(Math.max(0, this.beat ?? 0) / this.loopBeats) + 1);
        },
        melodyNotes() {
            return (this.draft.template.parts.find((p) => p.melody)?.guide.notes ?? []).map((n) => ({ ...n, pitch: n.pitch - (this.draft.octave ?? 0) }));
        },
    },
    unmounted() {
        cancelAnimationFrame(this.frame);
        this.player?.stop();
    },
    methods: {
        /** The count-in, then the loop round the chosen times, recording: stops itself at the end. */
        async sing() {
            this.problem = null;
            this.micProblem = null;
            try {
                if (!(await readyToSing())) return;
            } catch (error) {
                this.micProblem = micError(error, () => this.sing());
                return;
            }
            const bpm = this.draft.template.tempo;
            const samples = await renderSongBacking(this.backing, { bpm, sampleRate: engine.sampleRate, swing: this.draft.progression.swing ?? 0.5, ...backingChoice(this.draft) });
            this.live = [];
            this.state = 'counting';
            this.player = await playBackingLoop(samples, { bpm, perBar: 4, beats: this.loopBeats, record: true });
            // The screen follows the audio clock (it only reads the position and the mic).
            const follow = () => {
                const beat = this.player?.beat();
                if (beat === null || beat === undefined) return;
                this.beat = beat;
                if (beat >= 0 && this.state === 'counting') this.state = 'recording';
                const input = engine.inputFrame();
                const reading = beat >= 0 && input ? trackFrame(input, engine.sampleRate) : null;
                if (reading?.hz > 0) this.live.push({ beat, midi: hzToMidi(reading.hz) });
                if (beat >= this.loopBeats * this.loops) return this.finish();
                this.frame = requestAnimationFrame(follow);
            };
            this.frame = requestAnimationFrame(follow);
        },
        async finish() {
            cancelAnimationFrame(this.frame);
            const player = this.player;
            this.player = null;
            if (!player) return;
            const take = await player.stop();
            this.beat = null;
            if (!take || take.samples.length < take.sampleRate) {
                this.state = 'idle';
                return;
            }
            this.state = 'working';
            try {
                const track = await trackInWorker(take.samples, take.sampleRate);
                const result = transcribe(track, { beatSeconds: 60 / this.draft.template.tempo, startSeconds: 0, startBeat: 0, grid: gridFor('4/4'), octave: this.draft.octave ?? 0 });
                if (result.empty) {
                    this.problem = { kind: 'silent', tone: 'bad', text: t('create.sing.silent') };
                    return;
                }
                const loops = Math.max(1, Math.min(this.loops, Math.ceil((result.notes.at(-1).beat + result.notes.at(-1).length) / this.loopBeats)));
                this.$emit('melody', { notes: result.notes.filter((n) => n.beat < loops * this.loopBeats), loops, loopBeats: this.loopBeats });
                this.problem = result.noisy ? { kind: 'noisy', tone: 'warn', text: t('create.sing.noisy') } : null;
            } catch (error) {
                console.error('The tune was not written down', error);
                this.problem = { kind: 'failed', tone: 'bad', text: t('create.sing.failed') };
            } finally {
                this.state = 'idle';
            }
        },
    },
};
