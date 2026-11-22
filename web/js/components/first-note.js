// The choir's 30 seconds, before singing (Hillel, 2026-11-22: "once you pick the song, before you learn it, we ask them
// to sing the first note they want to sing on. We use our existing note matching UI to show them their comfortable
// note for the song, then we transpose the song to their key to record it."). The app's one find-your-note UI
// (components/voice-capture.js, 'note'), then the note heard, with Sing it from there, Try another note and Use the
// usual key. `go` carries the semitones to move the tune (0: as written).

import { t } from '../i18n.js';
import { nameNote } from '../note-names.js';
import { VoiceCapture } from './voice-capture.js';

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

/**
 * After the first note (Hillel: "a clear no headphone option where the user just sings and we don't play any sounds
 * while recording"): I have headphones (the tune plays, as ever) or No headphones (nothing plays; a count-in to see,
 * then the words lighting up in time). The answer is remembered and offered first. `choose` carries `silent`.
 */
export const HeadphonesChoice = {
    emits: ['choose'],
    computed: {
        order() {
            return rememberedHeadphones() === 'no' ? ['no', 'yes'] : ['yes', 'no'];
        },
    },
    methods: {
        choose(answer) {
            try {
                localStorage.setItem(HEADPHONES, answer);
            } catch (error) {
                console.info('The headphones answer was not kept', error?.message ?? error);
            }
            this.$emit('choose', answer === 'no');
        },
    },
    template: `
        <section class="space-y-4" data-headphones>
            <h2 class="text-xl font-bold text-cream">{{ t('ownChoir.headphones.title') }}</h2>
            <div class="grid gap-3">
                <button v-for="(answer, i) in order" :key="answer" type="button" class="card flex min-h-11 flex-col items-start gap-1 p-5 text-start hover:bg-raised/60"
                    :class="i === 0 ? 'border-accent' : ''" :data-headphones-answer="answer" @click="choose(answer)">
                    <span class="font-semibold text-cream">{{ answer === 'yes' ? t('ownChoir.headphones.yes') : t('ownChoir.headphones.no') }}</span>
                    <span class="text-sm text-muted">{{ answer === 'yes' ? t('ownChoir.headphones.yesHint') : t('ownChoir.headphones.noHint') }}</span>
                </button>
            </div>
        </section>`,
};

export const FirstNoteStep = {
    components: { VoiceCapture },
    props: {
        first: { type: Number, default: null }, // the tune's first melody note (MIDI), as written
        song: { type: String, default: '' },
    },
    emits: ['go'],
    data: () => ({ sung: null, round: 0, silent: false }),
    computed: {
        note() {
            return this.sung == null ? '' : nameNote(Math.round(this.sung));
        },
    },
    methods: {
        found(midi) {
            this.sung = midi;
        },
        again() {
            this.sung = null;
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
                <button type="button" class="btn-primary w-full sm:w-auto" data-first-note-use @click="use">{{ t('ownChoir.firstNote.use', { note }) }}</button>
                <button type="button" class="link inline-flex min-h-11 items-center text-sm" data-first-note-again @click="again">{{ t('ownChoir.firstNote.again') }}</button>
            </div>
            <button type="button" class="link-quiet inline-flex min-h-11 items-center text-sm" data-first-note-usual @click="$emit('go', 0)">{{ t('ownChoir.firstNote.usual') }}</button>
        </section>`,
};
