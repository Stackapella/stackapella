// The recording stage (Hillel's 113.png, with the whole song's 98.png): recording strips everything
// else away. A thin red line across the top; the red Recording pill, the song with your part and
// the take, and the mic's level; your lane big (the screen puts it in the slot), then the words
// huge, the syllable now in your colour and underlined, the next line smaller; and a bottom bar
// with the red progress line, the time, Start over, one big Stop (Stop and keep), and how much
// has been on pitch so far. Space or Esc stops (the screen's keys).

import { formatTime } from '../../theme.js';
import { Icon } from '../icon.js';
import { CountIn } from '../count-in.js';
import { joinSyllables } from '../practice.js';
import { KaraokeLine } from './stage.js';

export const RecordingStage = {
    components: { Icon, KaraokeLine, CountIn },
    props: {
        title: { type: String, required: true },
        named: { type: Boolean, default: true }, // the song has a title (else a placeholder, in italics)
        partName: { type: String, required: true },
        color: { type: String, required: true },
        take: { type: Number, default: 1 },
        countIn: { type: Number, default: null },
        position: { type: Number, default: 0 },
        length: { type: Number, default: 0 },
        busy: Boolean,
        onPitch: { type: Number, default: null }, // percent of the notes sung so far that were lit
        line: { type: Object, default: null }, // the current line of words, or null (no words)
        next: { type: Object, default: null },
        hearing: { type: String, default: '' }, // who you hear while recording (Sing's hearLine)
    },
    emits: ['stop', 'again'],
    setup: () => ({ formatTime, joinSyllables }),
    template: `
        <div class="flex min-h-0 flex-1 flex-col" data-recording-stage>
            <div class="h-1 shrink-0 bg-record" aria-hidden="true"></div>
            <header class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 px-4 pb-2 pt-[max(0.75rem,env(safe-area-inset-top))] md:px-8 md:pt-5">
                <span class="flex items-center gap-1.5 justify-self-start rounded-full glass-record px-3.5 py-1.5 text-sm font-bold tabular-nums" role="status">
                    <span class="size-2 animate-pulse rounded-full bg-record-label" aria-hidden="true"></span>
                    <CountIn v-if="countIn !== null" :beat="countIn" /><template v-else>{{ t('sing.recordingStage.recording') }}</template>
                </span>
                <div class="min-w-0 text-center">
                    <p class="truncate text-sm font-bold md:text-base" :class="named ? 'text-cream' : 'italic text-muted'" :translate="named ? 'no' : null">{{ title }}</p>
                    <p class="flex items-center justify-center gap-1.5 text-xs text-muted md:text-sm">
                        <span class="size-2 shrink-0 rounded-full" :style="{ background: color }" aria-hidden="true"></span>
                        <span class="truncate"><span translate="no">{{ partName }}</span> · {{ t('sing.recordingStage.take', { number: take }) }}</span>
                    </p>
                </div>
                <span class="justify-self-end"><slot name="hearing" /></span>
            </header>
            <p v-if="hearing" class="px-4 text-center text-sm text-muted md:px-8" data-hear-line>{{ hearing }}</p>

            <div class="flex min-h-0 flex-1 flex-col justify-center gap-5 px-4 pb-4 text-cream md:gap-8 md:px-8">
                <div class="h-[38vh] min-h-48 shrink-0 overflow-hidden rounded-3xl border border-edge bg-panel md:h-[40vh]"><slot /></div>
                <div v-if="line" class="space-y-3">
                    <KaraokeLine :line="line" :position="position" :color="color" class="text-4xl font-extrabold leading-[1.1] tracking-tight md:text-7xl" />
                    <p v-if="next" translate="no" class="text-xl font-extrabold text-cream/45 md:text-3xl">{{ joinSyllables(next.syllables) }}</p>
                </div>
            </div>

            <footer class="shrink-0 border-t border-line px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 md:px-8">
                <div class="h-1 overflow-hidden rounded-full bg-raised" aria-hidden="true" data-take-progress><span class="block h-full rounded-full bg-record" :style="{ width: share * 100 + '%' }"></span></div>
                <!-- A phone: the time and the pitch share on a row, the buttons under them -->
                <div class="grid grid-cols-2 items-center gap-x-3 gap-y-2 pt-3 md:grid-cols-[1fr_auto_1fr]">
                    <span class="whitespace-nowrap text-base tabular-nums text-cream md:text-2xl" data-take-time>{{ formatTime(position) }} <span class="text-xs text-muted md:text-sm">/ {{ formatTime(length) }}</span></span>
                    <div class="order-3 col-span-2 flex items-center justify-center gap-3 md:order-none md:col-span-1 md:gap-4">
                        <button type="button" class="btn-secondary btn-sm" :disabled="busy" @click="$emit('again')">{{ t('sing.recordingStage.startOver') }}</button>
                        <button type="button" class="grid size-16 place-items-center rounded-full glass-record transition disabled:opacity-40 md:size-20" :disabled="busy" :aria-label="t('sing.recordingStage.stopKeep')" data-stop @click="$emit('stop')"><Icon name="stop" :size="28" /></button>
                        <span class="hidden text-sm text-muted md:inline">{{ t('sing.recordingStage.stopKeep') }}</span>
                    </div>
                    <span class="justify-self-end text-end text-xs text-muted md:text-sm" aria-live="off">{{ onPitch === null ? '' : t('sing.recordingStage.onPitch', { percent: onPitch }) }}</span>
                </div>
            </footer>
        </div>`,
    computed: {
        share: (vm) => (vm.length > 0 ? Math.min(1, Math.max(0, vm.position / vm.length)) : 0),
    },
};
