// The keyboard round your comfortable note (ux-spec 5.8): the easy range tinted, the note a gem. Drawn by the lesson's
// note result (components/results.js) and the voice card (components/voice-card.js); its own module so the voice card
// doesn't bring the lesson's results with it, which load after lesson 1 shows (components/lazy-lesson.js).

import { midiName } from '../dsp.js';
import { keyboard } from '../results.js';

export const NoteKeyboard = {
    props: { home: { type: Number, required: true }, range: { type: Object, required: true } },
    template: `
        <figure class="w-full" :aria-label="t('results.keyboard', { note: midiName(home), low: midiName(range.low), high: midiName(range.high) })" role="img">
            <div class="relative flex h-28 w-full gap-[3px] md:h-32" aria-hidden="true">
                <span v-for="key in keys.whites" :key="key.midi" class="flex flex-1 items-end justify-center rounded-b-lg pb-2 text-[11px] font-bold"
                    :class="key.isNote ? 'gem gem-bar rounded-t-sm' : key.inRange ? 'key-range' : 'key-white'" :style="key.isNote ? { '--gem-ink': 'var(--color-accent-ink)' } : null" translate="no">{{ key.name }}</span>
                <span v-for="key in keys.blacks" :key="key.midi" class="absolute top-0 h-[60%] -translate-x-1/2 rounded-b-md"
                    :class="key.isNote ? 'gem gem-bar z-[1]' : key.inRange ? 'key-black-range' : 'key-black'" :style="{ left: ((key.after + 1) / keys.whites.length) * 100 + '%', width: (60 / keys.whites.length) + '%' }"></span>
            </div>
            <figcaption class="mt-3 flex flex-wrap justify-center gap-x-5 gap-y-1 text-sm text-muted">
                <span class="flex items-center gap-2"><span class="key-range size-3 rounded-sm"></span>{{ range.guess ? t('results.legend.likely') : t('results.legend.easy') }}</span>
                <span class="flex items-center gap-2"><span class="size-3 rounded-sm bg-accent"></span>{{ t('results.legend.note') }}</span>
            </figcaption>
        </figure>`,
    setup: () => ({ midiName }),
    computed: {
        keys: (vm) => keyboard(vm.home, vm.range),
    },
};
