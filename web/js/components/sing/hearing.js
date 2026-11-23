// What you hear (Hillel: "lets combone what youe hear into the settings panel"): Session settings'
// first section, also open during a take: every part on or off, yours being your guide (off while
// recording unless you turn it on, mid-take too) with its level beside it; the melody's row has a
// small Melody tag. Remembered (the session store keeps it per song on this device, never in the
// project); changes apply at once, recording included. Without headphones, it says the others play
// softer while you record.

import { t, tk } from '../../i18n.js';
import { GUIDE_LEVELS } from '../../practice.js';
import { Icon } from '../icon.js';
import { Segmented, Switch } from '../controls.js';

const LEVELS = [{ value: GUIDE_LEVELS.soft, label: tk('sing.hear.soft') }, { value: GUIDE_LEVELS.medium, label: tk('sing.hear.medium') }, { value: GUIDE_LEVELS.loud, label: tk('sing.hear.loud') }];

export const HearingSection = {
    components: { Icon, Segmented, Switch },
    props: {
        rows: { type: Array, required: true }, // [{ id, name, off, melody }]: every part but yours
        guideLevel: { type: Number, default: GUIDE_LEVELS.medium },
        recording: Boolean, // your guide is off while recording unless you turn it on
        speakers: Boolean,
        voices: { type: String, default: '' }, // who sings your guide
        split: Boolean, // Split ears (shown only with headphones)
    },
    emits: ['toggle', 'level', 'voices', 'split'],
    computed: {
        yours() {
            return this.rows.find((r) => r.yours) ?? null;
        },
        others() {
            return this.rows.filter((r) => !r.yours);
        },
        levels: () => LEVELS.map((l) => ({ value: l.value, label: t(l.label) })),
    },
    // Hillel's Session settings design (2026-11-23): rows with the title and its hint at the start, the control at the end;
    // the guide's level inline beside its name, faded while the guide is off; the slot (Session settings' drone) before
    // the no-headphones note, which is said in the flow (a dot and its bold first words), never a box.
    template: `
        <section class="space-y-[18px] px-6 py-[22px]" :aria-label="t('sing.hear.title')" data-hearing>
            <h3 class="t-section" aria-hidden="true">{{ t('sing.hear.title') }}</h3>
            <template v-if="yours">
                <Switch row :on="!yours.off" :label="t('sing.hear.yours', { part: yours.name })" :hint="recording ? t('sing.hear.yoursRecording') : t('sing.hear.yoursHint')" @change="$emit('toggle', yours.id)" />
                <div class="set-row flex-wrap items-center gap-y-2" :data-off="yours.off ? '' : null">
                    <p id="hear-level" class="set-row-title flex-1 whitespace-nowrap">{{ t('sing.hear.guide') }}</p>
                    <Segmented :model-value="guideLevel" kind="inset" :options="levels" labelledby="hear-level" @update:model-value="$emit('level', $event)" />
                </div>
                <button v-if="!recording && voices" type="button" class="flex min-h-11 w-full items-center gap-4 text-start" @click="$emit('voices')">
                    <span class="min-w-0 flex-1"><span class="set-row-title block">{{ t('sing.settings.voices') }}</span><span class="t-hint mt-[3px] block truncate" translate="no">{{ voices }}</span></span>
                    <Icon name="chevron" :size="18" class="shrink-0 -rotate-90 text-subtle rtl:rotate-90" />
                </button>
            </template>
            <!-- The other parts, one row of toggle chips (the coordinator's review: a switch each made the column scroll); each
                 says on with a tick as well as its fill, and the melody's keeps its tag. -->
            <div v-if="others.length" class="set-row flex-wrap items-center" data-other-parts>
                <p id="hear-others" class="set-row-title min-w-24 flex-1">{{ t('sing.hear.others') }}</p>
                <div class="flex flex-wrap gap-2" role="group" aria-labelledby="hear-others">
                    <button v-for="r in others" :key="r.id" type="button" role="switch" class="filter-chip" :aria-checked="r.off ? 'false' : 'true'" @click="$emit('toggle', r.id)">
                        <Icon v-if="!r.off" name="check" :size="14" aria-hidden="true" />{{ r.name }}<span v-if="r.melody" class="text-[0.6875rem] font-medium opacity-80">· {{ t('sing.hear.melody') }}</span>
                    </button>
                </div>
            </div>
            <Switch v-if="!speakers" row :on="split" :label="t('sing.hear.split')" :hint="t('sing.hear.splitHint')" data-split-ears @change="$emit('split', $event)" />
            <slot />
            <p v-if="speakers" class="alert-inline" data-speakers><span><b>{{ t('sing.hear.speakersLead') }}</b> {{ t('sing.hear.speakersRest') }}</span></p>
        </section>`,
};
