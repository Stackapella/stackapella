// Sing's frame (docs/decisions.md, "the Sing redesign", after Hillel's 2a and 2b): the header with
// the part chip, the modes as chips with Words or Notes beside them, the band for your turn, the
// mic level, the first-note card, and one calm transport row (Loop, Play,
// Record; one big Stop while recording). Settings and Your part are sheets (sheets.js).

import { engine } from '../../audio.js';
import { MODES } from '../../practice.js';
import { formatTime } from '../../theme.js';
import { Icon } from '../icon.js';
import { tk } from '../../i18n.js';
import { CountIn } from '../count-in.js';

/** The mic's level as five bars, live (your turn and recording). */
export const MicLevel = {
    props: { color: { type: String, default: 'currentColor' } },
    data: () => ({ level: 0 }),
    template: `
        <span class="inline-flex h-6 items-end gap-0.5" role="meter" aria-valuemin="0" aria-valuemax="5" :aria-valuenow="lit" :aria-label="t('mic.level')">
            <span v-for="i in 5" :key="i" class="w-1.5 rounded-full" :style="{ height: (6 + i * 3.5) + 'px', background: color, opacity: i <= lit ? 1 : 0.25 }"></span>
        </span>`,
    computed: {
        // A sung voice is around 0.05 to 0.5 peak: spread over five bars on a log scale.
        lit: (vm) => Math.max(0, Math.min(5, Math.round((Math.log10(Math.max(vm.level, 1e-4)) + 3) * 2))),
    },
    mounted() {
        const tick = () => {
            this.level = engine.inputLevel();
            this.frame = requestAnimationFrame(tick);
        };
        tick();
    },
    unmounted() {
        cancelAnimationFrame(this.frame);
    },
};

/**
 * The song and where you are, and your part with what's happening ("Alto · Listening"); the part
 * chip opens Your part, with the mic level beside it on a wide screen. While recording, the header is the red "Recording 0:07" and the mic level.
 */
export const SingHeader = {
    components: { Icon, CountIn },
    props: {
        title: { type: String, required: true },
        named: { type: Boolean, default: true }, // the song has a title (else a placeholder, in italics)
        info: { type: String, default: '' },
        partName: { type: String, required: true },
        color: { type: String, required: true },
        chip: { type: String, default: '' },
        recording: Boolean,
        recordedSeconds: { type: Number, default: 0 },
        countIn: { type: Number, default: null },
        about: { type: String, default: '' }, // your part's range (and who sings its guide), on the details line
        badge: { type: String, default: '' }, // on Settings: what's changed from the defaults
        marked: Boolean, // a dot on Settings while What you hear has anything changed
    },
    emits: ['back', 'part', 'settings'],
    setup: () => ({ formatTime }),
    template: `
        <header class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 pb-2 pt-[max(0.75rem,env(safe-area-inset-top))] md:px-6">
            <template v-if="recording">
                <span class="flex items-center gap-1.5 rounded-full bg-record px-3 py-1 text-sm font-bold tabular-nums text-record-label" role="status">
                    <span class="size-2 animate-pulse rounded-full bg-record-label" aria-hidden="true"></span>
                    <CountIn v-if="countIn !== null" :beat="countIn" /><template v-else>{{ t('sing.phase.recording', { time: formatTime(recordedSeconds) }) }}</template>
                </span>
            </template>
            <template v-else>
                <button type="button" class="grid size-11 shrink-0 place-items-center rounded-full text-cream hover:bg-raised" :aria-label="t('common.back')" @click="$emit('back')"><Icon name="back" :size="22" /></button>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-bold md:text-lg" :class="named ? 'text-cream' : 'italic text-muted'" :translate="named ? 'no' : null">{{ title }}</p>
                    <p class="truncate text-xs text-muted md:text-sm">{{ info }}<template v-if="about"> · <span class="text-cream" translate="no">{{ partName }}</span> {{ about }}</template></p>
                </div>
                <button type="button" class="relative grid size-11 shrink-0 place-items-center rounded-full glass-bubble text-cream sm:order-last" :aria-label="t('sing.settings.open')" data-settings-open @click="$emit('settings')">
                    <span v-if="marked && !badge" class="absolute end-0 top-0 size-3 rounded-full bg-accent ring-2 ring-ink" data-hearing-changed aria-hidden="true"></span>
                    <Icon name="sliders" :size="20" />
                    <span v-if="badge" class="absolute -end-2 -top-2 rounded-full bg-raised px-1.5 text-[10px] font-bold text-cream">{{ badge }}</span>
                </button>
                <div class="flex shrink-0 items-center max-sm:hidden">
                    <button type="button" class="flex min-h-11 shrink-0 items-center gap-2 rounded-full glass-bubble bg-raised/60 py-1.5 pe-2.5 ps-3 text-sm font-semibold text-cream hover:bg-raised" :aria-label="t('sing.yourPart.open', { part: partName })" @click="$emit('part')">
                        <span class="size-2.5 rounded-full" :style="{ background: color }" aria-hidden="true"></span>
                        <span translate="no">{{ partName }}</span>
                        <span v-if="chip" class="hidden font-normal text-muted sm:inline">· {{ chip }}</span>
                        <Icon name="chevron" :size="16" class="text-muted" />
                    </button>
                </div>
            </template>
        </header>`,
};

/**
 * The three modes as chips (the whole song; the coach sets the mode part by part), Settings with
 * a badge while slowed or with chords, your part's range and voice, and Words or Notes, one tap.
 */
export const ModeBar = {
    props: {
        mode: { type: String, default: 'listen' },
    },
    emits: ['mode'],
    setup: () => ({ MODES }),
    template: `
        <div class="flex justify-center overflow-x-auto px-4 pb-2 [scrollbar-width:none] md:px-6">
            <div class="inline-flex shrink-0 gap-1 rounded-full border border-line p-1" role="group" :aria-label="t('sing.mode')">
                <button v-for="(label, value) in MODES" :key="value" type="button" class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold transition"
                    :class="mode === value ? 'glass-bubble-solid' : 'text-muted hover:text-cream'" :aria-pressed="mode === value" @click="$emit('mode', value)">{{ t(label) }}</button>
            </div>
        </div>`,
};

/** Your turn: a band in your part's colour at the top, with Settings; the screen stays as it was. */
export const TurnBand = {
    components: { Icon },
    props: { color: { type: String, required: true }, others: { type: String, default: '' }, marked: Boolean },
    emits: ['settings'],
    template: `
        <div class="flex items-center gap-3 px-5 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))] md:px-6" :style="{ background: color, color: paintText(color) }" role="status">
            <div class="min-w-0 flex-1">
                <p class="text-3xl font-extrabold tracking-tight">{{ t('sing.turn.title') }}</p>
                <p class="text-sm font-semibold">{{ others ? t('sing.turn.band') : t('sing.turn.bandAlone') }}</p>
            </div>
            <button type="button" class="relative grid size-11 shrink-0 place-items-center rounded-full border-2" :style="{ borderColor: paintText(color) }" :aria-label="t('sing.settings.open')" data-settings-open @click="$emit('settings')">
                <span v-if="marked" class="absolute end-0 top-0 size-3 rounded-full bg-accent ring-2 ring-ink" data-hearing-changed aria-hidden="true"></span>
                <Icon name="sliders" :size="20" />
            </button>
        </div>`,
};

/**
 * One calm row: Loop, Play, Record (labelled). Recording: one big Stop. A coach's turn: Loop,
 * Pause, Again. On a wide screen, the time sits to the left.
 */
export const Transport = {
    components: { Icon },
    props: {
        position: { type: Number, default: 0 },
        length: { type: Number, default: 0 },
        playing: Boolean,
        recording: Boolean,
        busy: Boolean,
        loop: Boolean,
        partName: { type: String, default: '' },
        turn: Boolean, // part by part, your turn: Again beside Pause
        bpm: { type: Number, default: 0 }, // the tempo it plays at now (98.png's Tempo), 0 for none
        slower: Boolean, // the tempo can go down a step
        faster: Boolean,
    },
    emits: ['play', 'record', 'loop', 'again', 'tempo'],
    setup: () => ({ formatTime }),
    template: `
        <div class="sing-controls px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-2 md:flex md:items-center md:gap-6 md:border-t md:border-line md:bg-panel md:px-6 md:py-3">
            <span class="hidden min-w-36 text-2xl font-bold tabular-nums text-cream md:inline">{{ formatTime(position) }} <span class="text-sm font-normal text-muted">/ {{ formatTime(length) }}</span></span>
            <div v-if="recording" class="flex flex-1 flex-col items-center gap-1">
                <button type="button" class="grid size-20 place-items-center rounded-full border-[3px] border-record text-record transition hover:bg-record/10 disabled:opacity-40" :disabled="busy" :aria-label="t('studio.stopRecording')" @click="$emit('record')"><Icon name="stop" :size="30" /></button>
                <span class="text-sm font-semibold text-cream">{{ t('sing.transport.stop') }}</span>
            </div>
            <div v-else-if="!turn" class="hidden flex-1 items-center justify-center gap-4 md:flex">
                <button type="button" class="btn-secondary" :aria-pressed="loop ? 'true' : 'false'" :class="loop ? 'ring-2 ring-cream/60' : ''" @click="$emit('loop')"><Icon name="loop" :size="16" /> {{ t('sing.transport.loopLine') }}</button>
                <button type="button" class="grid size-[4.5rem] place-items-center rounded-full glass-bubble-solid transition disabled:opacity-40" :disabled="busy" :aria-label="playing ? t('studio.pause') : t('studio.play')" @click="$emit('play')"><Icon :name="playing ? 'pause' : 'play'" :size="30" /></button>
                <button type="button" class="btn-secondary" :disabled="busy" :aria-label="t('sing.recordPart', { name: partName })" @click="$emit('record')"><span class="size-2.5 rounded-full bg-record" aria-hidden="true"></span> {{ t('sing.transport.record') }}</button>
            </div>
            <div v-if="!recording && !turn && bpm" class="hidden shrink-0 items-center gap-2 text-sm text-muted md:flex">
                {{ t('sing.transport.tempo') }}
                <button type="button" class="grid size-9 place-items-center rounded-full glass-bubble text-cream hover:bg-raised disabled:opacity-40" :disabled="!slower" :aria-label="t('sing.transport.slower')" @click="$emit('tempo', -1)">-</button>
                <span class="min-w-16 text-center tabular-nums text-cream">{{ t('studio.meta.bpm', { bpm }) }}</span>
                <button type="button" class="grid size-9 place-items-center rounded-full glass-bubble text-cream hover:bg-raised disabled:opacity-40" :disabled="!faster" :aria-label="t('sing.transport.faster')" @click="$emit('tempo', 1)">+</button>
            </div>
            <div v-if="!recording" class="flex flex-1 items-start justify-between md:justify-center md:gap-14" :class="turn ? '' : 'md:hidden'">
                <div class="flex w-20 flex-col items-center gap-1">
                    <button type="button" class="grid size-14 place-items-center rounded-full border transition" :class="loop ? 'border-cream bg-raised text-cream' : 'border-line text-cream hover:bg-raised'" :aria-pressed="loop" :aria-label="t('practice.loop')" @click="$emit('loop')"><Icon name="loop" :size="22" /></button>
                    <span class="text-xs font-semibold text-muted">{{ t('practice.loop') }}</span>
                </div>
                <button type="button" class="grid size-[4.5rem] place-items-center rounded-full glass-bubble-solid transition disabled:opacity-40" :disabled="busy" :aria-label="playing ? t('studio.pause') : t('studio.play')" @click="$emit('play')"><Icon :name="playing ? 'pause' : 'play'" :size="30" /></button>
                <div class="flex w-20 flex-col items-center gap-1">
                    <button v-if="turn" type="button" class="grid size-14 place-items-center rounded-full glass-bubble text-cream" :aria-label="t('sing.turn.again')" @click="$emit('again')"><Icon name="repeat" :size="22" /></button>
                    <button v-else type="button" class="grid size-14 place-items-center rounded-full border-2 border-record text-record transition hover:bg-record/10 disabled:opacity-40" :disabled="busy" :aria-label="t('sing.recordPart', { name: partName })" @click="$emit('record')"><Icon name="record" :size="22" /></button>
                    <span class="text-xs font-semibold text-muted">{{ turn ? t('sing.turn.again') : t('sing.transport.record') }}</span>
                </div>
            </div>
        </div>`,
};

/** Under the parts on a wide screen (98.png): the song's lines as segments, filled as they're sung; a tap goes there. */
export const LineBar = {
    props: { lines: { type: Array, required: true }, position: { type: Number, default: 0 } }, // [{ label, startSeconds, endSeconds }]
    emits: ['seek'],
    template: `
        <ol class="grid gap-1.5 px-6 pt-3" :style="{ gridTemplateColumns: 'repeat(' + lines.length + ', minmax(0, 1fr))' }" :aria-label="t('sing.lines')" data-line-bar>
            <li v-for="(line, i) in lines" :key="i">
                <button type="button" class="block w-full text-start" :aria-current="i === now ? 'true' : null" @click="$emit('seek', line.startSeconds)">
                    <span class="block h-1.5 overflow-hidden rounded-full bg-raised" aria-hidden="true"><span class="block h-full rounded-full bg-cream/70" :style="{ width: fill(line) + '%' }"></span></span>
                    <span class="mt-1.5 block truncate text-xs" :class="i === now ? 'text-cream' : 'text-muted'" translate="no">{{ line.label }}</span>
                </button>
            </li>
        </ol>`,
    computed: {
        now() {
            return this.lines.findIndex((l) => this.position >= l.startSeconds - 1e-6 && this.position < l.endSeconds);
        },
    },
    methods: {
        fill(line) {
            const span = Math.max(1e-6, line.endSeconds - line.startSeconds);
            return Math.round(Math.max(0, Math.min(1, (this.position - line.startSeconds) / span)) * 100);
        },
    },
};

