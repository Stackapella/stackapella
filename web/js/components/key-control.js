// "Not in your range?" (Hillel's 128.png; docs/decisions.md, "your key"): Lower and Higher move a
// lesson's exercises or a song's part a semitone, with the key between them. Everything that sounds
// and everything scored moves with it; the choice sticks for that lesson or song.

import { t } from '../i18n.js';
import { Icon } from './icon.js';

/** "Eb" as "E♭", "F#m" as "F♯ minor". */
export function prettyKey(key) {
    const minor = key.endsWith('m');
    const note = (minor ? key.slice(0, -1) : key).replace(/^([A-G])b$/, '$1♭').replace('#', '♯');
    return minor ? t('music.minorKey', { note }) : note;
}

export const KeyControl = {
    components: { Icon },
    props: {
        musicKey: { type: String, required: true },
        canLower: { type: Boolean, default: true },
        canHigher: { type: Boolean, default: true },
    },
    emits: ['lower', 'higher'],
    setup: () => ({ prettyKey }),
    template: `
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2" data-key-control>
            <span class="text-sm text-muted">{{ t('keyControl.question') }}</span>
            <!-- The same compact − and + as the warm-up's starting note (Hillel's design). -->
            <div class="flex items-center gap-1 rounded-full border border-line p-1" role="group" :aria-label="t('keyControl.label')">
                <button type="button" class="grid size-11 place-items-center rounded-full glass-bubble text-cream disabled:opacity-40" :aria-label="t('keyControl.lower')" :title="t('keyControl.lower')" :disabled="!canLower" @click="$emit('lower')"><Icon name="minus" :size="16" /></button>
                <span class="min-w-16 px-2 text-center text-sm font-semibold text-cream" role="status" translate="no">{{ t('keyControl.key', { key: prettyKey(musicKey) }) }}</span>
                <button type="button" class="grid size-11 place-items-center rounded-full glass-bubble text-cream disabled:opacity-40" :aria-label="t('keyControl.higher')" :title="t('keyControl.higher')" :disabled="!canHigher" @click="$emit('higher')"><Icon name="plus" :size="16" /></button>
            </div>
        </div>`,
};
