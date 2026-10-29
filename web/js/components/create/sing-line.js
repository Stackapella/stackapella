// Sing the melody, line by line (docs/template-creator-plan.md, "Sing it" 3, and "When things go
// wrong"): first who's singing the tune (an octave down for a lower voice); then the line on the
// stage, Get ready through the count-in, the click, a live pitch line as you sing, and it settles
// into notes with the syllables on them. Then Next line, Again, Hear the notes or Hear my take, and
// a plain message with its way out when the mic is silent, nothing was caught, the room is noisy,
// the singing ran ahead of or behind the click or sped up, or the syllables and notes don't match.

import { audio } from '../../audio.js';
import { hzToMidi, midiName } from '../../dsp.js';
import { formatNumber, t, tk } from '../../i18n.js';
import { trackInWorker } from '../../pitchtrack.js';
import { transcribe } from '../../transcribe.js';
import { gridFor, splitNote } from '../../creator.js';
import { hearNotes, hearTake, readyToSing, recordLine } from '../../creator-audio.js';
import { PART_COLORS, beatsPerBar } from '../../theme.js';
import { joinSyllables } from '../practice.js';
import { CountIn } from '../count-in.js';
import { Icon } from '../icon.js';
import { MicPicker, micError } from '../mic.js';
import { Notice } from '../form.js';
import { Roll } from './roll.js';
import { refit } from './setup.js';
import { voiceType } from '../../course.js';
import { knownRange, loadLearning } from '../../stores/learning.js';

const AHEAD_MS = 90; // further off the click than this, on average, is worth saying
const COLOR = PART_COLORS[3]; // the tune's colour (painted in the theme showing)
const KEY_NAMES = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B']; // i18n-ignore: key names
const SHARP_NAMES = ['C', 'C#', 'D', 'D#', 'E', 'F', 'Gb', 'G', 'G#', 'A', 'A#', 'B']; // i18n-ignore: key names
const OCTAVES = [
    { octave: 0, label: tk('create.sing.octaveHigh'), title: tk('create.sing.highTitle'), sub: tk('create.sing.highSub') },
    { octave: 12, label: tk('create.sing.octaveLow'), title: tk('create.sing.lowTitle'), sub: tk('create.sing.lowSub') },
];

export const SingStep = {
    components: { CountIn, Icon, MicPicker, Notice, Roll },
    props: { draft: { type: Object, required: true } },
    emits: ['change', 'line', 'tempo'],
    data: () => ({ fromRange: false, state: 'idle', beat: 0, live: [], recorder: null, problem: null, micProblem: null, takes: {}, joining: false, audio, COLOR }),
    setup: () => ({ joinSyllables, midiName, formatNumber, OCTAVES }),
    template: `
        <section class="space-y-6">
            <div>
                <h2 class="text-4xl font-extrabold tracking-tight text-cream">{{ t('create.sing.title') }}</h2>
                <p class="mt-2 text-muted">{{ t('create.sing.hint') }}</p>
            </div>

            <!-- Your range is known (Hillel): the voice is chosen from it, said in a line with Change. -->
            <p v-if="fromRange && draft.octave !== null" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-cream" data-voice-from-range>
                <span class="font-semibold">{{ t('create.sing.fromRange', { voice: t(OCTAVES.find((o) => o.octave === draft.octave).title) }) }}</span>
                <button v-if="!sungAny" type="button" class="btn-ghost btn-sm" @click="fromRange = false">{{ t('create.sing.changeVoice') }}</button>
            </p>
            <fieldset v-else class="space-y-3">
                <legend class="sr-only">{{ t('create.sing.octaveTitle') }}</legend>
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1" aria-hidden="true">
                    <p class="text-xl font-bold text-cream">{{ t('create.sing.octaveTitle') }}</p>
                    <p class="text-sm text-muted">{{ t('create.sing.octaveHint') }}</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2" role="radiogroup" :aria-label="t('create.sing.octaveTitle')">
                    <button v-for="o in OCTAVES" :key="o.octave" type="button" role="radio" :aria-checked="draft.octave === o.octave" :aria-label="t(o.label)" :disabled="sungAny && draft.octave !== o.octave"
                        class="radio-card p-4 disabled:opacity-45" @click="$emit('change', { octave: o.octave })">
                        <span class="radio-mark" aria-hidden="true"></span>
                        <span><span class="block font-semibold text-cream">{{ t(o.title) }}</span><span class="block text-sm text-muted">{{ t(o.sub) }}</span></span>
                    </button>
                </div>
            </fieldset>

            <div class="card space-y-5 p-5 md:p-7" :class="draft.octave === null ? 'opacity-60' : ''">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <ol class="flex gap-2 overflow-x-auto pb-1 [scrollbar-width:thin]" :aria-label="t('create.sing.lines')">
                        <li v-for="(l, i) in draft.lines" :key="i">
                            <button type="button" class="filter-chip" :aria-current="i === index ? 'true' : null" :disabled="busy" @click="choose(i)">
                                <Icon v-if="l.notes?.length" name="check" :size="14" class="text-good" /><span v-else class="size-1.5 rounded-full bg-line" aria-hidden="true"></span><span>{{ t('create.sing.lineN', { number: formatNumber(i + 1) }) }}</span>
                            </button>
                        </li>
                    </ol>
                    <span class="text-sm text-muted tabular-nums">{{ t('create.sing.sungOf', { done: sungCount, total: draft.lines.length }) }}</span>
                </div>
                <div class="relative space-y-4">
                    <p v-if="state === 'counting'" class="text-4xl font-extrabold text-cream" role="status"><CountIn :beat="countBeat" /></p>
                    <p v-else-if="state === 'recording'" class="text-lg font-bold" :style="{ color: paintInk(COLOR) }" role="status">{{ beat < perBar ? t('create.sing.pickupNow') : t('create.sing.singNow') }}</p>
                    <p v-else class="text-xs font-bold uppercase tracking-widest text-muted">{{ t('create.sing.lineOf', { number: formatNumber(index + 1), total: formatNumber(draft.lines.length) }) }}</p>
                    <p translate="no" class="text-4xl font-extrabold leading-tight tracking-tight text-cream md:text-6xl">{{ line ? (draft.noWords ? t('create.sing.onOo') : joinSyllables(line.syllables.map((text) => ({ text })))) : '' }}</p>
                    <p v-if="next && !draft.noWords" translate="no" class="text-xl font-bold text-cream/40 md:text-3xl">{{ joinSyllables(next.syllables.map((text) => ({ text }))) }}</p>
                    <Roll v-if="state === 'recording' || state === 'counting'" :parts="[]" :live="live" :beats-per-bar="perBar" :pickup-beats="perBar" :end-beat="liveEnd" :zoom="40" :playhead="liveAt" :label="t('create.sing.liveLabel')" />
                    <Roll v-else-if="line?.notes?.length" :parts="[{ key: 'tune', color: paint(COLOR), notes: shownNotes }]" :beats-per-bar="perBar" :pickup-beats="perBar" :end-beat="lineEnd" :zoom="40" :to-check="unsureHere" :label="t('create.sing.notesLabel', { count: line.notes.length })" />
                    <Roll v-else :parts="[]" :beats-per-bar="perBar" :pickup-beats="perBar" :end-beat="perBar * 3" :zoom="40" :height="12" :label="t('create.sing.liveLabel')" />
                    <p v-if="state === 'working'" class="text-muted" role="status">{{ t('create.sing.working') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button v-if="state === 'recording' || state === 'counting'" type="button" class="btn-primary btn-lg" @click="recorder.stop()"><Icon name="stop" :size="18" /> {{ t('create.sing.done') }}</button>
                    <template v-else>
                        <button v-if="!line?.notes?.length" type="button" class="btn-primary btn-lg" :disabled="busy || draft.octave === null" @click="sing"><Icon name="record" :size="18" /> {{ t('create.sing.singLine', { number: formatNumber(index + 1) }) }}</button>
                        <template v-else>
                            <button v-if="index < draft.lines.length - 1" type="button" class="btn-primary" :disabled="busy" @click="choose(index + 1); sing()"><Icon name="record" :size="18" /> {{ t('create.sing.nextLine') }}</button>
                            <button type="button" class="btn-secondary" :disabled="busy" @click="sing"><Icon name="repeat" :size="18" /> {{ t('create.sing.again') }}</button>
                            <button type="button" class="btn-secondary" :disabled="busy" @click="hearNotes"><Icon name="note" :size="18" /> {{ t('create.sing.hearNotes') }}</button>
                            <button v-if="takes[index]" type="button" class="btn-secondary" :disabled="busy" @click="hearTake"><Icon name="headphones" :size="18" /> {{ t('create.sing.hearTake') }}</button>
                        </template>
                        <!-- Hillel: "offer to play the key note first". Held two seconds in the guide's voice; never a step you must take. -->
                        <button v-if="draft.octave !== null" type="button" class="btn-ghost btn-sm" :disabled="busy" data-key-note @click="playKeyNote"><Icon name="note" :size="16" /> {{ t('create.sing.keyNote', { note: keyNote.name }) }}</button>
                    </template>
                    <p class="ms-auto flex flex-wrap items-center gap-2 text-sm text-muted">
                        <span>{{ draft.octave === null ? t('create.sing.chooseVoice') : t('create.sing.tip', { bpm: draft.template.tempo }) }}</span>
                        <button type="button" class="btn-ghost btn-sm" :disabled="busy" @click="$emit('tempo')">{{ t('create.sing.changeTempo') }}</button>
                    </p>
                </div>
            </div>

            <template v-if="draft.octave !== null">
                <Notice v-if="problem" :tone="problem.tone">
                    <p>{{ problem.text }}</p>
                    <MicPicker v-if="problem.kind === 'silent' && audio.mic" class="mt-3" />
                    <div v-if="problem.kind === 'ahead' || problem.kind === 'behind'" class="mt-2 flex flex-wrap gap-3">
                        <button v-if="problem.kind === 'ahead'" type="button" class="btn-secondary btn-sm" @click="slower">{{ t('create.sing.slower', { bpm: Math.round(draft.template.tempo * 0.85) }) }}</button>
                        <button type="button" class="btn-ghost btn-sm" @click="problem = null">{{ t('create.sing.keepIt') }}</button>
                    </div>
                </Notice>

                <Notice v-if="line?.mismatch" tone="warn">
                    <p>{{ t('create.sing.mismatch', { syllables: line.mismatch.syllables, notes: line.mismatch.notes }) }}</p>
                    <div class="mt-2 flex flex-wrap gap-3">
                        <button type="button" class="btn-secondary btn-sm" @click="splitLongest">{{ t('create.sing.split', { note: midiName(line.notes[line.mismatch.split].pitch) }) }}</button>
                        <button type="button" class="btn-ghost btn-sm" :aria-expanded="joining" @click="joining = !joining">{{ t('create.sing.join') }}</button>
                    </div>
                    <div v-if="joining" class="mt-3 flex flex-wrap items-center gap-1" translate="no">
                        <template v-for="(s, k) in line.syllables" :key="k">
                            <span class="rounded-lg bg-ink px-2 py-1 font-semibold text-cream">{{ s }}</span>
                            <button v-if="k < line.syllables.length - 1" type="button" class="rounded-full px-1.5 text-part-4 hover:bg-raised" :aria-label="t('create.sing.joinThese', { first: s, second: line.syllables[k + 1] })" @click="joinAt(k)">+</button>
                        </template>
                    </div>
                </Notice>
                <p v-if="micProblem" class="text-sm text-bad" role="alert">{{ micProblem }}</p>

            </template>
        </section>`,
    computed: {
        perBar() {
            return beatsPerBar(this.draft.template.timeSignature);
        },
        /** The count-in bar's beat now, counting up with its click (1 to the meter's last); 0 before the first click. */
        countBeat() {
            return Math.max(0, Math.floor(this.beat) + this.perBar + 1);
        },
        index() {
            const current = this.draft.current;
            if (Number.isInteger(current) && current < this.draft.lines.length) return current;
            const first = this.draft.lines.findIndex((l) => !l.notes?.length);
            return first < 0 ? 0 : first;
        },
        line() {
            return this.draft.lines[this.index] ?? null;
        },
        next() {
            return this.draft.lines[this.index + 1] ?? null;
        },
        busy() {
            return this.state !== 'idle';
        },
        /** The song's key note, where your voice sings it: the key so far (C before a line is sung). */
        keyNote() {
            const name = (this.draft.template.key || 'C').replace(/m$/, ''); // i18n-ignore: key names
            const tonic = Math.max(0, KEY_NAMES.indexOf(name), SHARP_NAMES.indexOf(name));
            const pitch = 60 - this.draft.octave + tonic - (tonic > 9 ? 12 : 0); // around middle C, or the C below for a low voice
            return { name, pitch };
        },
        sungCount() {
            return this.draft.lines.filter((l) => l.notes?.length).length;
        },
        /** Once a line is sung, the voice it was sung in stays. */
        sungAny() {
            return this.sungCount > 0;
        },
        shownNotes() {
            return (this.line?.notes ?? []).map((n, i) => ({ ...n, lyric: this.line.lyrics?.[i] ?? undefined }));
        },
        unsureHere() {
            return (this.line?.notes ?? []).map((n, i) => (n.sure === false ? i : -1)).filter((i) => i >= 0);
        },
        liveEnd() {
            return Math.max(this.perBar * 3, this.beat + 2);
        },
        liveAt() {
            return Math.max(0, this.beat);
        },
        lineEnd() {
            const last = this.line?.notes?.at(-1);
            return last ? last.beat + last.length : this.perBar * 2;
        },
    },
    async mounted() {
        // With your range known, the voice is chosen from it: a tenor or bass sings it an octave down.
        await loadLearning();
        const range = knownRange();
        if (!range) return;
        this.fromRange = true;
        if (this.draft.octave === null) this.$emit('change', { octave: ['tenor', 'bass'].includes(voiceType(range)) ? 12 : 0 });
    },
    unmounted() {
        this.recorder?.stop();
    },
    methods: {
        choose(i) {
            this.problem = null;
            this.joining = false;
            this.$emit('change', { current: i });
        },
        /** Sings the line: the count-in, then recording until Done or a long quiet. */
        async sing() {
            this.problem = null;
            this.micProblem = null;
            try {
                if (!(await readyToSing())) return;
            } catch (error) {
                this.micProblem = micError(error, () => this.sing());
                return;
            }
            const index = this.index;
            this.live = [];
            this.beat = -Infinity; // before the engine's first frame: nothing counted yet
            this.state = 'counting';
            this.recorder = recordLine({
                tempo: this.draft.template.tempo,
                timeSignature: this.draft.template.timeSignature,
                onFrame: ({ beat, reading }) => {
                    this.beat = beat;
                    if (beat >= 0 && this.state === 'counting') this.state = 'recording';
                    if (beat >= 0 && reading?.hz > 0) this.live.push({ beat, midi: hzToMidi(reading.hz) });
                },
            });
            const take = await this.recorder.done;
            this.recorder = null;
            if (!take) {
                this.state = 'idle';
                return;
            }
            if (take.silent) {
                this.state = 'idle';
                this.problem = { kind: 'silent', tone: 'bad', text: t('create.sing.silent') };
                return;
            }
            this.state = 'working';
            try {
                const track = await trackInWorker(take.samples, take.sampleRate);
                const result = transcribe(track, { beatSeconds: 60 / this.draft.template.tempo, startSeconds: 0, startBeat: 0, grid: gridFor(this.draft.template.timeSignature), octave: this.draft.octave });
                this.takes = { ...this.takes, [index]: take };
                if (result.empty) {
                    this.problem = { kind: 'empty', tone: 'warn', text: t('create.sing.empty') };
                    return;
                }
                const line = refit({ ...this.draft.lines[index], notes: result.notes });
                this.$emit('line', index, { notes: line.notes, lyrics: line.lyrics, mismatch: line.mismatch });
                this.$emit('change', { current: index });
                this.problem = result.noisy
                    ? { kind: 'noisy', tone: 'warn', text: t('create.sing.noisy') }
                    : result.aheadMs > AHEAD_MS
                      ? { kind: 'ahead', tone: 'info', text: t('create.sing.ahead') }
                      : result.aheadMs < -AHEAD_MS
                        ? { kind: 'behind', tone: 'info', text: t('create.sing.behind') }
                        : result.spedUp
                          ? { kind: 'spedUp', tone: 'info', text: t('create.sing.spedUp') }
                          : null;
            } catch (error) {
                console.error('The line was not transcribed', error);
                this.problem = { kind: 'failed', tone: 'bad', text: t('create.sing.failed') };
            } finally {
                this.state = 'idle';
            }
        },
        slower() {
            this.$emit('change', { template: { ...this.draft.template, tempo: Math.max(40, Math.round(this.draft.template.tempo * 0.85)) } });
            this.problem = null;
        },
        /** Fewer notes than syllables: the longest note becomes two. */
        splitLongest() {
            const line = this.line;
            const notes = splitNote(line.notes, line.mismatch.split, gridFor(this.draft.template.timeSignature) / 2);
            const fitted = refit({ ...line, notes });
            this.$emit('line', this.index, { notes: fitted.notes, lyrics: fitted.lyrics, mismatch: fitted.mismatch });
        },
        /** Two syllables sung on one note: joined into one ("ev-" "ery" to "every"). */
        joinAt(k) {
            const line = this.line;
            const sylls = [...line.syllables];
            const joined = sylls[k].replace(/-$/, '') + sylls[k + 1];
            sylls.splice(k, 2, joined);
            const fitted = refit({ ...line, syllables: sylls });
            this.$emit('line', this.index, { syllables: sylls, lyrics: fitted.lyrics, mismatch: fitted.mismatch });
            if (!fitted.mismatch) this.joining = false;
        },
        playKeyNote() {
            const tempo = this.draft.template.tempo;
            hearNotes([{ beat: 0, length: (2 * tempo) / 60, pitch: this.keyNote.pitch }], { tempo });
        },
        hearNotes() {
            hearNotes(this.line.notes, { tempo: this.draft.template.tempo });
        },
        hearTake() {
            hearTake(this.takes[this.index]);
        },
    },
};
