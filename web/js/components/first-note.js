// The choir's 30 seconds, before singing (Hillel, 2026-11-22: "once you pick the song, before you learn it, we ask them
// to sing the first note they want to sing on. We use our existing note matching UI to show them their comfortable
// note for the song, then we transpose the song to their key to record it."). The app's one find-your-note UI
// (components/voice-capture.js, 'note'), then the note heard, with Sing it from there, Try another note and Use the
// usual key. `go` carries the semitones to move the tune (0: as written).

import { t } from '../i18n.js';
import { nameNote } from '../note-names.js';
import { VoiceCapture } from './voice-capture.js';
import { clipPick, levelVerdict, takeLevel } from '../own-choir.js';
import { count } from '../usage.js';
import { Segmented } from './controls.js';

/**
 * The semitones that put the tune's first note on the one sung, kept within a fifth either way: further than that, the
 * octave that keeps the move smaller (the worker finds the octave they sang in).
 */
export function shiftTo(sung, first) {
    if (!Number.isFinite(sung) || !Number.isFinite(first)) return 0;
    let shift = Math.round(sung) - Math.round(first);
    while (shift > 7) shift -= 12;
    while (shift < -7) shift += 12;
    return shift;
}

const HEADPHONES = 'stackapella.clipHeadphones';

/** The headphones answer remembered on this device ('yes', 'no'), else null. */
export function rememberedHeadphones() {
    try {
        return localStorage.getItem(HEADPHONES);
    } catch {
        return null; // storage blocked: asked each time
    }
}

/** Set the headphones answer ('yes' or 'no'), remembered on this device. */
export function rememberHeadphones(answer) {
    try {
        localStorage.setItem(HEADPHONES, answer);
    } catch (error) {
        console.info('The headphones answer was not kept', error?.message ?? error);
    }
    count(`choir_headphones_${answer}`);
}

/**
 * Hillel: "merge the headphone selection into the song selection page. You make two choices and click sing to start".
 * Headphones or not, beside the tune chosen, remembered; its hint says what each means.
 */
export const HeadphonesPick = {
    components: { Segmented },
    data: () => ({ answer: rememberedHeadphones() === 'no' ? 'no' : 'yes' }),
    computed: {
        options: () => [
            { value: 'yes', label: t('ownChoir.headphones.yesShort') },
            { value: 'no', label: t('ownChoir.headphones.noShort') },
        ],
    },
    methods: {
        pick(answer) {
            this.answer = answer;
            clipPick.noHeadphones = answer === 'no';
            rememberHeadphones(answer);
        },
    },
    template: `
        <div class="flex flex-col gap-2.5" data-headphones-pick :data-answer="answer">
            <p id="headphones-pick-label" class="text-sm font-semibold text-cream/80">{{ t('ownChoir.headphones.label') }}</p>
            <Segmented :model-value="answer" :options="options" labelledby="headphones-pick-label" full class="[&_.seg-option]:min-h-11 [&_.seg-option]:text-[15px]" @update:model-value="pick" />
            <p class="text-sm text-muted">{{ answer === 'no' ? t('ownChoir.headphones.noHint') : t('ownChoir.headphones.yesHint') }}</p>
        </div>`,
};

export const FirstNoteStep = {
    components: { VoiceCapture },
    setup: () => ({ count }),
    props: {
        first: { type: Number, default: null }, // the tune's first melody note (MIDI), as written
        song: { type: String, default: '' },
    },
    emits: ['go'],
    data: () => ({ sung: null, round: 0, silent: false, level: null }),
    computed: {
        note() {
            return this.sung == null ? '' : nameNote(Math.round(this.sung));
        },
        /** The note as read on the screen, with the music's own sharp and flat (F♯2, B♭2, Si♭4). */
        shown() {
            return this.note.replace('#', '\u266f').replace(/b(?=-?\d)/, '\u266d');
        },
        /** The move to the sung note, in semitones (-7 to +7). */
        shift() {
            return this.sung == null ? 0 : shiftTo(this.sung, this.first);
        },
        markerAt() {
            return 50 + (this.shift / 7) * 42;
        },
        where() {
            return this.shift < -1 ? t('ownChoir.firstNote.lower') : this.shift > 1 ? t('ownChoir.firstNote.higher') : t('ownChoir.firstNote.near');
        },
    },
    methods: {
        /** The note held, and how loud it was (ai-modal: a quiet take never reaches the worker): low, loud or ok. */
        found(midi, clip = null) {
            this.sung = midi;
            this.level = clip?.samples?.length ? levelVerdict(takeLevel(clip.samples, clip.sampleRate)) : null;
            count('choir_first_note_done');
            if (this.level) count(`choir_level_${this.level}`);
        },
        again() {
            this.sung = null;
            this.level = null;
            this.silent = false;
            this.round += 1;
        },
        use() {
            this.$emit('go', shiftTo(this.sung, this.first));
        },
        skip() {
            count('choir_first_note_skipped');
            this.$emit('go', 0);
        },
    },
    // Hillel's "Find your note" design (2026-11-23, the newer of his two): the journey's three steps across the top, the
    // ask and the shared capture; once heard, "You sang" over the note large and left-aligned, one line on what it does,
    // where it sits against the usual key, then the three ways on stacked (the primary, an outline, a text link).
    template: `
        <section class="mx-auto flex w-full max-w-[480px] flex-col gap-8" data-first-note>
            <ol class="step-progress" :aria-label="t('ownChoir.firstNote.steps')">
                <li aria-current="step">{{ t('ownChoir.firstNote.stepFind') }}</li>
                <li>{{ t('ownChoir.firstNote.stepSing') }}</li>
                <li>{{ t('ownChoir.firstNote.stepHear') }}</li>
            </ol>
            <template v-if="sung == null">
                <div class="space-y-2 text-center">
                    <h2 class="t-title text-cream">{{ t('ownChoir.firstNote.title') }}</h2>
                    <p class="t-lead">{{ t('ownChoir.firstNote.hint', { song }) }}</p>
                </div>
                <VoiceCapture :key="round" mode="note" color="var(--color-accent)" @found="found" @no-sound="(now) => (silent = now)" />
                <p v-if="silent" class="text-center text-sm text-cream" role="status" data-first-note-silent>{{ t('ownChoir.firstNote.noMic') }}</p>
                <button type="button" class="mx-auto inline-flex min-h-11 items-center text-[15px] font-medium text-muted underline decoration-current underline-offset-[3px] hover:text-cream" data-first-note-usual @click="skip">{{ t('ownChoir.firstNote.usual') }}</button>
            </template>
            <div v-else class="flex flex-col gap-6" role="status" data-first-note-heard>
                <div class="space-y-2">
                    <p class="text-sm text-subtle">{{ t('ownChoir.firstNote.youSang') }}</p>
                    <p class="t-hero text-cream" translate="no" data-first-note-sung>{{ shown }}</p>
                    <p class="t-lead">{{ t('ownChoir.firstNote.startsHere', { song }) }}</p>
                    <!-- Only a level worth acting on (too quiet or too loud); fine says nothing (the coordinator's review). -->
                    <p v-if="level && level !== 'ok'" class="text-sm text-cream" :data-first-note-level="level">{{ level === 'low' ? t('ownChoir.level.low') : t('ownChoir.level.loud') }}</p>
                </div>
                <!-- Where the note sits against the usual key: the usual start ticked in the middle, their note on the track. -->
                <div v-if="first != null" class="card space-y-2.5 rounded-2xl px-5 py-[18px]" data-first-note-range>
                    <div class="relative h-5" aria-hidden="true">
                        <span class="absolute inset-x-0 top-2 h-1 rounded-full bg-line"></span>
                        <span class="absolute top-1 h-3 w-0.5 -translate-x-1/2 bg-subtle" style="left: 50%"></span>
                        <span class="absolute top-0 size-5 -translate-x-1/2 rounded-full border-4 border-panel bg-cream" :style="{ left: markerAt + '%' }"></span>
                    </div>
                    <p class="flex justify-between gap-3 text-[0.8125rem] text-subtle"><span aria-hidden="true">{{ t('ownChoir.firstNote.low') }}</span><span class="text-center">{{ where }}</span><span aria-hidden="true">{{ t('ownChoir.firstNote.high') }}</span></p>
                </div>
                <div class="flex flex-col gap-3">
                    <button type="button" class="btn-primary btn-xl w-full" data-first-note-use @click="use">{{ t('ownChoir.firstNote.use', { note: shown }) }}</button>
                    <button type="button" class="btn-secondary min-h-[52px] w-full text-base" data-first-note-again @click="again">{{ t('ownChoir.firstNote.again') }}</button>
                    <button type="button" class="mx-auto inline-flex min-h-11 items-center text-[15px] font-medium text-muted underline decoration-current underline-offset-[3px] hover:text-cream" data-first-note-usual @click="skip">{{ t('ownChoir.firstNote.usual') }}</button>
                </div>
            </div>
        </section>`,
};
