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
        <div class="space-y-2" data-headphones-pick :data-answer="answer">
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted">{{ t('ownChoir.headphones.label') }}</p>
            <Segmented :model-value="answer" :options="options" :label="t('ownChoir.headphones.label')" full class="[&_.seg-option]:min-h-11" @update:model-value="pick" />
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
    },
    template: `
        <section class="space-y-5" data-first-note>
            <div class="space-y-1">
                <h2 class="text-xl font-bold text-cream">{{ t('ownChoir.firstNote.title') }}</h2>
                <p class="text-muted">{{ t('ownChoir.firstNote.hint', { song }) }}</p>
            </div>
            <VoiceCapture v-if="sung == null" :key="round" mode="note" color="var(--color-accent)" @found="found" @no-sound="(now) => (silent = now)" />
            <p v-if="sung == null && silent" class="text-sm text-cream" role="status" data-first-note-silent>{{ t('ownChoir.firstNote.noMic') }}</p>
            <div v-else-if="sung != null" class="card space-y-4 p-5" role="status" data-first-note-heard>
                <p class="text-cream">{{ t('ownChoir.firstNote.heard', { note, song }) }}</p>
                <p v-if="level" class="text-sm" :class="level === 'ok' ? 'text-muted' : 'text-cream'" :data-first-note-level="level">{{ level === 'low' ? t('ownChoir.level.low') : level === 'loud' ? t('ownChoir.level.loud') : t('ownChoir.level.ok') }}</p>
                <button type="button" class="btn-primary w-full sm:w-auto" data-first-note-use @click="use">{{ t('ownChoir.firstNote.use', { note }) }}</button>
                <button type="button" class="link inline-flex min-h-11 items-center text-sm" data-first-note-again @click="again">{{ t('ownChoir.firstNote.again') }}</button>
            </div>
            <button type="button" class="link-quiet inline-flex min-h-11 items-center text-sm" data-first-note-usual @click="count('choir_first_note_skipped'); $emit('go', 0)">{{ t('ownChoir.firstNote.usual') }}</button>
        </section>`,
};
