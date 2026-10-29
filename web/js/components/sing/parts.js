// Every part at this moment (docs/singing-ux-plan.md, "Each part's current note and syllable"):
// compact chips on a phone, cards on a larger screen, each with the note it sings now, its word,
// and one status. Tapping a part makes it yours.

import { partSaying } from '../../sing.js';
import { t, tk } from '../../i18n.js';

export const STATUS = {
    singing: tk('sing.status.singing'),
    resting: tk('sing.status.resting'),
    getReady: tk('sing.status.resting'),
    you: tk('sing.status.you'),
    recording: tk('sing.status.recording'),
    recorded: tk('sing.status.recorded'),
    muted: tk('sing.status.muted'),
};

export const PartStrip = {
    props: {
        parts: { type: Array, required: true }, // [{ slot, name, status, note, syllable }]
        disabled: Boolean, // while recording, the part can't change
    },
    emits: ['select'],
    setup: () => ({ STATUS }),
    template: `
        <div>
            <ul class="flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] md:hidden" :aria-label="t('sing.parts')">
                <li v-for="p in parts" :key="p.slot.id" class="shrink-0">
                    <button type="button" class="flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition disabled:opacity-60" :disabled="disabled"
                        :class="p.status === 'you' || p.status === 'recording' ? 'border-transparent' : 'border-line text-cream'"
                        :style="p.status === 'you' || p.status === 'recording' ? { background: paint(p.slot.color), color: paintText(p.slot.color) } : null" :aria-pressed="p.status === 'you' || p.status === 'recording'"
                        :aria-label="saying(p)" @click="$emit('select', p.slot.id)">
                        <span v-if="p.status !== 'you' && p.status !== 'recording'" class="size-2.5 rounded-full" :style="{ background: paint(p.slot.color), opacity: p.note ? 1 : 0.35 }"></span>
                        <span class="font-semibold" translate="no">{{ p.name }}</span>
                        <span class="tabular-nums" :class="p.note ? '' : 'opacity-50'">{{ p.note ?? '·' }}</span>
                    </button>
                </li>
            </ul>
            <ul class="hidden gap-3 px-6 md:grid" :style="{ gridTemplateColumns: 'repeat(' + parts.length + ', minmax(0, 1fr))' }" :aria-label="t('sing.parts')">
                <li v-for="p in parts" :key="p.slot.id">
                    <button type="button" class="flex w-full flex-col gap-1 rounded-2xl border p-3 text-start transition disabled:opacity-60" :disabled="disabled"
                        :class="p.status === 'you' || p.status === 'recording' ? 'bg-raised' : 'border-line hover:bg-raised'"
                        :style="p.status === 'you' || p.status === 'recording' ? { borderColor: paint(p.slot.color) } : null" :aria-pressed="p.status === 'you' || p.status === 'recording'"
                        @click="$emit('select', p.slot.id)">
                        <span class="flex items-center gap-2 text-sm">
                            <span class="size-2.5 rounded-full" :style="{ background: paint(p.slot.color) }"></span>
                            <span class="font-semibold text-cream" translate="no">{{ p.name }}</span>
                            <span class="ms-auto text-xs" :class="p.status === 'recording' ? 'text-record' : 'text-muted'">{{ t(STATUS[p.status]) }}</span>
                        </span>
                        <span class="flex items-baseline gap-2">
                            <span class="text-2xl font-bold tabular-nums" :class="p.note ? 'text-cream' : 'text-muted/50'">{{ p.note ?? '·' }}</span>
                            <span v-if="p.syllable" class="truncate text-sm text-muted" translate="no">{{ p.syllable }}</span>
                        </span>
                    </button>
                </li>
            </ul>
        </div>`,
    methods: {
        saying(p) {
            const [key, values] = partSaying(p.name, { note: p.note, name: p.note, syllable: p.syllable });
            return `${t(key, values)}. ${t(STATUS[p.status])}`;
        },
    },
};
