// Your voice card (docs/onboarding-review.md, step 6; onboarding item 6): at the end of the free
// course's first unit, who your voice is so far: your note, the easy range around it on a keyboard,
// the parts your range suits, and Share (the note card, with your name if you add it). Identity is
// the most shareable thing a beginner has. The same in both skins.

import { midiName } from '../dsp.js';
import { t, tk } from '../i18n.js';
import { voiceType } from '../course.js';
import { easyRange } from '../results.js';
import { learning } from '../stores/learning.js';
import { NoteKeyboard } from './results.js';
import { ShareResult } from './share-result.js';

const SUITS = { soprano: tk('voiceCard.suits.soprano'), alto: tk('voiceCard.suits.alto'), tenor: tk('voiceCard.suits.tenor'), bass: tk('voiceCard.suits.bass') };

export const VoiceCard = {
    components: { NoteKeyboard, ShareResult },
    props: { entry: { type: Object, required: true }, lessonTitle: { type: String, required: true }, unitTitle: { type: String, required: true }, color: { type: String, required: true } },
    setup: () => ({ learning, midiName, easyRange }),
    template: `
        <section v-if="learning.progress.home !== null" class="w-full space-y-4 rounded-3xl border border-edge bg-panel p-5 text-center" aria-labelledby="voice-card-title" data-voice-card>
            <p id="voice-card-title" class="text-xs font-bold uppercase tracking-[0.14em] text-accent-strong">{{ t('voiceCard.title') }}</p>
            <p class="text-7xl font-extrabold leading-none tracking-tight text-cream" translate="no">{{ midiName(Math.round(learning.progress.home)) }}</p>
            <p class="text-cream">{{ line }}</p>
            <NoteKeyboard :home="learning.progress.home" :range="easyRange(learning.progress.home, learning.progress.range)" class="mx-auto max-w-xl" />
            <div class="flex justify-center">
                <ShareResult :label="t('voiceCard.share')" :note="{ name: midiName(Math.round(learning.progress.home)), line }" :entry="entry" :lesson-title="lessonTitle" :unit-title="unitTitle" :color="color" />
            </div>
        </section>`,
    computed: {
        /** "Your range suits alto parts best", or, with too little range yet, your note's own line. */
        line() {
            const type = voiceType(learning.progress.range);
            return type ? t(SUITS[type]) : t('voiceCard.note', { note: midiName(Math.round(learning.progress.home)) });
        },
    },
};
