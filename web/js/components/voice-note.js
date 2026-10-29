// The note when a real voice exists but the practice voice plays (docs/more-features-plan.md,
// feature 16): in another key, with the way back to the real one and whether that key suits the
// singer's range; at a slower speed; or when it couldn't load. The studio and Sing both show it.
// Dismissed, it stays away until the key changes.

import { t } from '../i18n.js';
import { keyLabel } from '../theme.js';
import * as sessionStore from '../stores/session.js';
import { session } from '../stores/session.js';
import { learning, loadLearning } from '../stores/learning.js';
import { Icon } from './icon.js';

const SHOWN_ENOUGH = 7; // semitones: a fifth, the least range worth judging a key by

/**
 * Whether the focused part, in the song's own key, sits in the range the singer has shown. Until
 * they've shown most of an octave (a range check, or the course's notes), it says nothing rather
 * than guess.
 */
function fit(key) {
    const range = learning.progress.range;
    const part = session.focusPart && sessionStore.templatePart(session.focusPart);
    if (!range || range.high - range.low < SHOWN_ENOUGH || !part?.range) return null;
    return part.range.low >= range.low && part.range.high <= range.high ? t('studio.originalFits', { key }) : t('studio.originalStretch', { key });
}

/**
 * The note when a real voice exists but the practice voice plays: `{ text, action, fit }`, or null.
 * `useRange(range)` is what "Sing in G with the real voice" does. Shared with Sing's Your part.
 */
export function voiceNote(useRange) {
    const project = session.project;
    const template = project?.template;
    if (!template || !session.practice) return null;
    const voices = Object.values(session.guideVoices);
    if (voices.some((v) => v.why === 'key')) {
        const original = keyLabel(template.key);
        return {
            text: t('studio.realVoiceKey', { key: original, current: keyLabel(project.key) }),
            action: { label: t('studio.singInOriginal', { key: original }), run: () => useRange({ transpose: 0, partId: session.focusPart?.templatePartKey }) },
            fit: fit(original),
        };
    }
    if (voices.some((v) => v.why === 'speed')) {
        return {
            text: t('studio.realVoiceSpeed', { percent: Math.round((session.practice.speed ?? 1) * 100) }),
            action: { label: t('studio.fullSpeed'), run: () => sessionStore.setSpeed(1) },
        };
    }
    if (voices.some((v) => v.why === 'failed')) return { text: t('studio.realVoiceFailed'), action: null };
    return null;
}

export const VoiceNote = {
    components: { Icon },
    emits: ['use-range'],
    template: `
        <div aria-live="polite">
            <div v-if="note" class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-line bg-part-6/10 px-4 py-2 text-sm text-cream">
                <span>{{ note.text }}</span>
                <button v-if="note.action" type="button" class="font-semibold text-part-6 underline" @click="note.action.run">{{ note.action.label }}</button>
                <span v-if="note.fit" class="text-muted">{{ note.fit }}</span>
                <button type="button" class="ms-auto text-muted" :aria-label="t('common.dismiss')" @click="dismiss"><Icon name="close" :size="16" /></button>
            </div>
        </div>`,
    created() {
        loadLearning();
    },
    computed: {
        note() {
            if (session.practice?.voiceNoteDismissed === `${session.project?.transpose || 0}`) return null;
            return voiceNote((range) => this.$emit('use-range', range));
        },
    },
    methods: {
        dismiss() {
            session.practice.voiceNoteDismissed = `${session.project.transpose || 0}`;
            sessionStore.savePractice();
        },
    },
};
