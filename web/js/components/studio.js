// The studio's tracks (Hillel's 117.png, after the stack of voices): each part a compact track, its
// record-pick circle, its colour dot and name (which opens its panel: pan, timing, trim and more),
// small S and M, one level slider with its value, and its waveform filling a lane on a ruler that fits
// the song; the last row is the next voice to add (a plain song), which fills an empty studio (a big
// record button, the mic listening, and headphones, count-in and click as visible toggles). No
// coloured edge on a row (Hillel's rule): a row's colour is its dot and waveform.

import { formatNumber, t } from '../i18n.js';
import { Switch } from './controls.js';
import { Icon } from './icon.js';
import { MicLevel } from './sing/frame.js';
import { dismissToast, toasts } from './ui.js';
import { game } from '../skin.js';

/** Headphones, count-in and click: what recording will do, as visible choices. */
export const SetupChoices = {
    components: { Icon, Switch },
    // polished: Hear it polished, as the transport's fourth chip once something's recorded (null: not offered).
    props: { headphones: Boolean, countIn: { type: Number, default: 1 }, click: Boolean, bpm: { type: Number, default: null }, compact: Boolean, polished: { type: Boolean, default: null } },
    emits: ['headphones', 'count-in', 'click', 'polished'],
    computed: {
        chips() {
            return [
                { key: 'headphones', on: this.headphones, label: t('studio.setup.headphonesChip'), flip: () => this.$emit('headphones', !this.headphones) },
                { key: 'countIn', on: this.countIn > 0, label: t('studio.countIn'), flip: () => this.$emit('count-in', this.countIn ? 0 : 1) },
                { key: 'click', on: this.click, label: t('studio.setup.click'), flip: () => this.$emit('click', !this.click) },
                ...(this.polished === null ? [] : [{ key: 'polished', on: this.polished, label: t('polish.name'), flip: () => this.$emit('polished', !this.polished) }]),
            ];
        },
    },
    template: `
        <!-- Compact (the transport, 53.png): each a chip with a small switch in it, on in the accent. -->
        <div v-if="compact" class="flex flex-wrap gap-2">
            <button v-for="c in chips" :key="c.key" type="button" role="switch" :aria-checked="c.on ? 'true' : 'false'" class="tap-44 flex items-center gap-2 rounded-full glass-bubble py-1.5 pe-3 ps-1.5 text-sm font-semibold transition hover:bg-raised" :class="c.on ? 'text-cream' : 'text-muted'" @click="c.flip()">
                <span class="relative h-4 w-7 shrink-0 rounded-full transition" :class="c.on ? 'bg-accent' : 'bg-line'" aria-hidden="true"><span class="absolute top-0.5 size-3 rounded-full bg-cream transition-[inset-inline-start]" :style="{ insetInlineStart: c.on ? '0.875rem' : '0.125rem' }"></span></span>{{ c.label }}
            </button>
        </div>
        <div v-else class="space-y-3">
            <div class="flex items-center gap-3 rounded-2xl bg-ink/50 py-1.5 pe-1 ps-3">
                <Icon name="headphones" :size="20" class="shrink-0 text-muted" />
                <Switch :on="headphones" :label="t('studio.setup.headphones')" :hint="t('studio.setup.headphonesHint')" @change="$emit('headphones', $event)" />
            </div>
            <div class="flex items-center gap-3 rounded-2xl bg-ink/50 py-1.5 pe-1 ps-3">
                <Icon name="metronome" :size="20" class="shrink-0 text-muted" />
                <!-- On or Off (Hillel's call): one bar of clicks when on. -->
                <Switch :on="countIn > 0" :label="t('studio.setup.countIn')" :hint="t('studio.setup.countInHint')" @change="$emit('count-in', $event ? 1 : 0)" />
            </div>
            <div class="flex items-center gap-3 rounded-2xl bg-ink/50 py-1.5 pe-1 ps-3">
                <Icon name="timing" :size="20" class="shrink-0 text-muted" />
                <Switch :on="click" :label="t('studio.setup.clickWhile')" :hint="bpm ? t('studio.setup.clickHint', { bpm }) : t('studio.setup.clickHintFree')" @change="$emit('click', $event)" />
            </div>
        </div>`,
};

let rows = 0;

/**
 * A part's row. The lane (its waveform, and anything over it) comes in the `lane` slot, and the
 * controls shown when it's open in the `open` slot; the row draws its own playhead.
 */
export const VoiceRow = {
    components: { Icon },
    props: {
        name: { type: String, required: true },
        letter: { type: String, required: true },
        color: { type: String, required: true },
        tags: { type: Array, default: () => [] }, // [{ text, tone: 'you' | 'friend' | 'guide' | 'rec' }]
        when: { type: String, default: '' },
        guide: Boolean, // no take yet: the letter is an outline
        solo: Boolean,
        muted: Boolean,
        audible: { type: Boolean, default: true },
        open: Boolean,
        recording: Boolean,
        position: { type: Number, default: 0 },
        lengthSeconds: { type: Number, required: true },
        volume: { type: Number, default: 1 },
        pan: { type: Number, default: 0 },
        tall: Boolean,
        pickable: Boolean, // its circle picks it to record
        picked: Boolean,
    },
    emits: ['toggle', 'solo', 'mute', 'volume', 'pan', 'seek', 'pick'],
    data: () => ({ nameId: `voice-${++rows}` }),
    template: `
        <section class="flex flex-col rounded-2xl border bg-raised/30 p-2 transition md:flex-row md:items-stretch md:gap-3" :class="recording ? 'border-record ring-2 ring-record/40' : open ? 'border-cream/30 bg-raised/60' : 'border-line'" :aria-labelledby="nameId" data-track>
            <!-- A container: Solo and Mute say their names wherever the row has room (Hillel), S and M only when it's narrow. -->
            <div class="@container flex flex-col justify-center gap-2 md:w-[352px] md:shrink-0">
                <div class="flex items-center gap-2.5">
                    <!-- The armed ring (Hillel's studio redesign): tap to choose the track that records; red when it's armed. -->
                    <button v-if="pickable" type="button" class="tap-44 grid size-8 shrink-0 place-items-center rounded-full border-2 transition" :class="picked ? 'border-record' : 'border-line hover:border-record/70'" :aria-pressed="picked ? 'true' : 'false'" :aria-label="t('studio.pickNamed', { name })" @click="$emit('pick')" data-pick>
                        <span class="size-3.5 rounded-full transition" :class="picked ? 'bg-record' : 'bg-transparent'" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-start" :aria-expanded="open" @click="$emit('toggle')">
                        <span class="size-2.5 shrink-0 rounded-full" :style="{ background: color }" aria-hidden="true"></span>
                        <span :id="nameId" class="truncate font-bold text-cream" translate="no" data-track-name>{{ name }}</span>
                        <span v-for="(tag, i) in tags" :key="i" class="shrink-0 rounded-md px-1.5 py-px text-[11px] font-semibold" :class="tag.tone === 'rec' ? 'bg-record text-record-label' : tag.tone === 'guide' ? 'bg-ink/60 text-muted' : ''" :style="tag.tone === 'you' || tag.tone === 'friend' ? { background: 'color-mix(in srgb, ' + color + ' 14%, transparent)', color: paintInk(color) } : null" translate="no">{{ tag.text }}</span>
                        <span v-if="muted" class="shrink-0 text-xs text-muted">{{ t('studio.muted') }}</span>
                    </button>
                    <slot name="meter" />
                    <button type="button" class="tap-44 grid h-8 min-w-8 shrink-0 place-items-center rounded-lg px-1.5 text-xs font-bold transition @[19rem]:px-2.5" :class="solo ? 'bg-accent text-accent-ink' : 'glass-bubble text-cream'" :aria-pressed="solo" :aria-label="t('studio.soloNamed', { name })" data-solo @click="$emit('solo')"><span class="@[19rem]:hidden" aria-hidden="true">{{ t('studio.row.soloShort') }}</span><span class="hidden @[19rem]:inline" aria-hidden="true">{{ t('studio.row.solo') }}</span></button>
                    <button type="button" class="tap-44 grid h-8 min-w-8 shrink-0 place-items-center rounded-lg px-1.5 text-xs font-bold transition @[19rem]:px-2.5" :class="muted ? 'bg-cream text-ink' : !audible ? 'border border-cream/60 bg-cream/25 text-cream' : 'glass-bubble text-cream'" :aria-pressed="muted" :aria-label="t('studio.muteNamed', { name })" :aria-describedby="!muted && !audible ? nameId + '-solo' : null" :data-implied-mute="!muted && !audible ? '' : null" data-mute @click="$emit('mute')"><span class="@[19rem]:hidden" aria-hidden="true">{{ t('studio.row.muteShort') }}</span><span class="hidden @[19rem]:inline" aria-hidden="true">{{ t('studio.row.mute') }}</span></button>
                    <span v-if="!muted && !audible" :id="nameId + '-solo'" class="sr-only">{{ t('studio.silentBySolo') }}</span>
                </div>
                <p v-if="when" class="-mt-1.5 truncate ps-[2.625rem] text-xs text-muted" data-when>{{ when }}</p>
            </div>
            <div class="flex min-w-0 flex-1 flex-col">
                <!-- Time runs left to right in every language, as on a staff. -->
                <div dir="ltr" class="relative mt-2 shrink-0 cursor-pointer overflow-hidden rounded-lg bg-ink/40 transition md:mt-0 md:h-auto md:min-h-14 md:flex-1 md:shrink" :class="[tall || open ? 'h-20' : 'h-14', audible ? '' : 'opacity-50 grayscale']" data-lane @pointerdown="$emit('seek', $event)">
                    <slot name="lane" />
                    <span v-if="picked && !recording" class="pointer-events-none absolute end-2 top-2 flex items-center gap-1.5 rounded-full bg-record px-2.5 py-1 text-[11px] font-bold text-record-label" data-ready><span class="size-1.5 rounded-full bg-record-label" aria-hidden="true"></span>{{ t('studio.readyToRecord') }}</span>
                    <!-- A phone's playhead, per lane (wider screens draw one line across every track). -->
                    <span class="pointer-events-none absolute inset-y-0 w-0.5 md:hidden" :class="recording ? 'bg-record' : 'bg-cream'" :style="{ left: playhead + '%' }" aria-hidden="true"></span>
                </div>
                <div v-if="open" class="mt-3 px-2"><slot name="open" /></div>
            </div>
        </section>`,
    computed: {
        playhead() {
            return Math.max(0, Math.min(100, (Math.max(0, this.position) / this.lengthSeconds) * 100));
        },
    },
};

/** Where the next voice goes: the whole screen in an empty studio, a row once there are parts. */
export const AddVoice = {
    components: { Icon, MicLevel, SetupChoices },
    props: {
        first: Boolean, // nothing recorded yet
        number: { type: Number, default: 1 }, // the voice it will be
        name: { type: String, required: true }, // its name: Melody, Harmony 1...
        color: { type: String, required: true },
        after: { type: String, default: '' }, // the first part's name, to sing along with
        mic: { type: String, default: '' },
        hearing: Boolean, // the mic is open
        headphones: Boolean,
        countIn: { type: Number, default: 1 },
        click: Boolean,
        bpm: { type: Number, default: null },
    },
    emits: ['record', 'mic', 'headphones', 'count-in', 'click'],
    template: `
        <!-- The voice up next (53.png): its card in the track column, tinted, and its lane as a big
             tinted panel saying what to do; recording starts from the transport's Record. -->
        <section class="grid gap-3 md:grid-cols-[13rem_minmax(0,1fr)]" :aria-label="first ? t('studio.add.firstTitle') : t('studio.add.title', { number: formatNumber(number) })" data-add-voice>
            <button type="button" class="flex items-center gap-3 rounded-2xl border p-4 text-start md:flex-col md:items-start md:justify-center md:gap-1" :style="{ borderColor: 'color-mix(in srgb, ' + color + ' 45%, transparent)', background: 'color-mix(in srgb, ' + color + ' 9%, var(--color-panel))' }" @click="$emit('record')">
                <span class="flex items-center gap-2 font-bold text-cream" translate="no"><span class="gem size-3 rounded-full" :style="{ '--gem': color }" aria-hidden="true"></span>{{ name }}</span>
                <span class="text-sm text-muted">{{ t('studio.add.upNext') }}</span>
            </button>
            <div class="rounded-2xl border p-5 md:p-6" :class="first ? 'md:py-8' : ''" :style="{ borderColor: 'color-mix(in srgb, ' + color + ' 40%, transparent)', background: 'color-mix(in srgb, ' + color + ' 7%, transparent)' }">
                <h2 class="font-extrabold tracking-tight text-cream" :class="first ? 'text-3xl md:text-4xl' : 'text-xl'">{{ first ? t('studio.add.firstTitle') : t('studio.add.nextTitle', { name }) }}</h2>
                <p class="mt-2 max-w-lg text-muted">{{ first ? t('studio.add.firstHint') : after ? t('studio.add.hint', { name: after }) : t('studio.add.hintPlain') }}</p>
                <!-- A phone has no room for these in the transport: the mic and the choices here. -->
                <div class="mt-4 space-y-3 md:hidden">
                    <p class="flex flex-wrap items-center gap-2 text-sm text-muted">
                        <Icon name="mic" :size="16" /><span translate="no">{{ mic }}</span><MicLevel v-if="hearing" :color="color" />
                        <button type="button" class="btn-ghost btn-sm" @click="$emit('mic')">{{ t('studio.add.change') }}</button>
                    </p>
                    <SetupChoices compact :headphones="headphones" :count-in="countIn" :click="click" @headphones="$emit('headphones', $event)" @count-in="$emit('count-in', $event)" @click="$emit('click', $event)" />
                </div>
            </div>
        </section>`,
    setup: () => ({ formatNumber }),
};

/** A voice still to come: its card (Waiting) and a quiet lane with a hint. */
export const GhostRow = {
    props: { name: { type: String, required: true }, color: { type: String, required: true }, text: { type: String, required: true } },
    template: `
        <div class="grid gap-3 md:grid-cols-[13rem_minmax(0,1fr)]">
            <div class="flex items-center gap-3 rounded-2xl border border-edge bg-panel px-4 py-3 md:flex-col md:items-start md:gap-0.5">
                <span class="flex items-center gap-2 font-bold text-cream" translate="no"><span class="size-3 rounded-full border-2" :style="{ borderColor: color }" aria-hidden="true"></span>{{ name }}</span>
                <span class="text-sm text-muted">{{ t('studio.add.waiting') }}</span>
            </div>
            <div class="hidden items-center rounded-2xl border border-edge bg-panel/50 px-5 text-sm text-muted md:flex">{{ text }}</div>
        </div>`,
};

/** Plans one more voice: a dashed row, the stack's last. */
export const AddAnother = {
    components: { Icon },
    emits: ['add'],
    template: `
        <button type="button" class="flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-line py-3.5 text-sm font-semibold text-muted transition hover:border-cream/40 hover:text-cream" @click="$emit('add')"><Icon name="plus" :size="16" /> {{ t('studio.add.another') }}</button>`,
};

/**
 * The studio's one notice (Hillel's studio redesign: "the three stacked toasts are now a single
 * card that sits above the transport bar. It has Undo and also shows the badge. The header and
 * tracks are never covered"): the newest message with its actions, and in game mode a badge just
 * earned under it. It takes its place in the layout, so it never covers anything.
 */
export const StudioNotice = {
    components: { Icon },
    computed: {
        main: () => [...toasts.list].reverse().find((item) => item.tone !== 'badge') ?? null,
        badge: () => (game() ? [...toasts.list].reverse().find((item) => item.tone === 'badge') ?? null : null),
    },
    template: `
        <div v-if="main || badge" class="mx-3 mb-2 flex items-center gap-2 rounded-2xl border py-2 pe-2 ps-4 text-sm md:mx-5" :class="main?.tone === 'error' ? 'border-record/50 bg-[color-mix(in_srgb,var(--color-record)_12%,var(--color-raised))]' : 'border-line bg-raised'" role="status" data-studio-notice>
            <div class="min-w-0 flex-1 py-1">
                <p v-if="main" class="font-semibold text-cream">{{ main.message }}</p>
                <p v-if="badge" class="text-xs font-semibold text-warn" data-notice-badge>{{ badge.message }}</p>
            </div>
            <button v-for="action in main?.actions ?? []" :key="action.label" type="button" class="btn-ghost btn-sm text-accent-strong hover:text-accent-strong" @click="action.run(); dismiss()">{{ action.label }}</button>
            <button type="button" class="btn-ghost btn-sm size-9 shrink-0 px-0" :aria-label="t('common.dismiss')" @click="dismiss()"><Icon name="close" :size="16" /></button>
        </div>`,
    methods: {
        dismiss() {
            if (this.main) dismissToast(this.main.id);
            if (this.badge) dismissToast(this.badge.id);
        },
    },
};
