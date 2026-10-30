// What you hear (Hillel: "lets combone what youe hear into the settings panel"): Session settings'
// first section, also open during a take: every part on or off, yours being your guide (off while
// recording unless you turn it on, mid-take too) with its level beside it; the melody's row has a
// small Melody tag. Remembered (the session store keeps it per song on this device, never in the
// project); changes apply at once, recording included. Without headphones, it says the others play
// softer while you record.

import { tk } from '../../i18n.js';
import { GUIDE_LEVELS } from '../../practice.js';
import { Icon } from '../icon.js';
import { Switch } from '../controls.js';

const LEVELS = [{ value: GUIDE_LEVELS.soft, label: tk('sing.hear.soft') }, { value: GUIDE_LEVELS.medium, label: tk('sing.hear.medium') }, { value: GUIDE_LEVELS.loud, label: tk('sing.hear.loud') }];

export const HearingSection = {
    components: { Icon, Switch },
    props: {
        rows: { type: Array, required: true }, // [{ id, name, off, melody }]: every part but yours
        guideLevel: { type: Number, default: GUIDE_LEVELS.medium },
        recording: Boolean, // your guide is off while recording unless you turn it on
        speakers: Boolean,
        voices: { type: String, default: '' }, // who sings your guide
        split: Boolean, // Split ears (shown only with headphones)
    },
    emits: ['toggle', 'level', 'voices', 'split'],
    setup: () => ({ LEVELS }),
    template: `
        <section class="space-y-5 px-6 pb-6" :aria-label="t('sing.hear.title')" data-hearing>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted" aria-hidden="true">{{ t('sing.hear.title') }}</p>
            <template v-for="r in rows" :key="r.id">
                <Switch :on="!r.off" :label="r.yours ? t('sing.hear.yours', { part: r.name }) : r.name" :hint="r.yours ? (recording ? t('sing.hear.yoursRecording') : t('sing.hear.yoursHint')) : ''" :tag="r.melody ? t('sing.hear.melody') : ''" @change="$emit('toggle', r.id)" />
                <fieldset v-if="r.yours">
                <legend class="mb-2 text-sm font-semibold text-cream">{{ t('sing.hear.guide') }}</legend>
                <div class="grid grid-cols-3 rounded-2xl border border-line p-1">
                    <button v-for="l in LEVELS" :key="l.value" type="button" class="min-h-10 rounded-xl text-sm font-semibold" :class="guideLevel === l.value ? 'glass-bubble-solid' : 'text-muted hover:text-cream'" :aria-pressed="guideLevel === l.value ? 'true' : 'false'" @click="$emit('level', l.value)">{{ t(l.label) }}</button>
                </div>
            </fieldset>
            <button v-if="r.yours && !recording && voices" type="button" class="flex w-full items-center gap-4 text-start" @click="$emit('voices')">
                <span class="min-w-0 flex-1"><span class="block font-semibold text-cream">{{ t('sing.settings.voices') }}</span><span class="block truncate text-sm text-muted" translate="no">{{ voices }}</span></span>
                <Icon name="chevron" :size="18" class="shrink-0 -rotate-90 text-muted rtl:rotate-90" />
            </button>
            </template>
            <Switch v-if="!speakers" :on="split" :label="t('sing.hear.split')" :hint="t('sing.hear.splitHint')" data-split-ears @change="$emit('split', $event)" />
            <p v-if="speakers" class="rounded-xl bg-ink/40 p-3 text-sm text-cream" data-speakers>{{ t('sing.hear.speakers') }}</p>
        </section>`,
};
