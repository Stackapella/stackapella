// Sing's two sheets (the Sing redesign): Settings, for what you set once (the view, slow practice,
// the chords, note names, the keyboard and the other parts on the lane, the voices), and Your
// part (each part with its range and voice; yours opens to its voices and the kind note when the
// real voice can't follow your key; check your range; learn it part by part or the whole song).

import { styleOf } from '../../band-style.js';
import { t, tk } from '../../i18n.js';
import { NOTE_STYLES } from '../../notes.js';
import { midiName } from '../../dsp.js';
import { prefs } from '../../stores/prefs.js';
import { Modal } from '../ui.js';
import { Icon } from '../icon.js';
import { AiVoiceLabel, voiceCredit } from '../../ai-voices.js';
import { HearingSection } from './hearing.js';
import { Segmented, Switch } from '../controls.js';

const SPEEDS = [1, 0.85, 0.7];
// Letters say themselves as a note in the naming in use (D4, Re4 with fixed Do: note-names.js).
const styleLabel = (n) => (n === 'letters' ? midiName(62) : t(STYLE_LABELS[n]));
const STYLE_LABELS = { solfege: tk('sing.settings.names.solfege'), numbers: tk('sing.settings.names.numbers'), off: tk('sing.settings.names.off') };

// The band's styles (js/band.js), for the chords underneath.
const BAND_STYLES = [{ value: 'gentle', label: tk('band.style.gentle') }, { value: 'pop', label: tk('band.style.pop') }, { value: 'swing', label: tk('band.style.swing') }];
// The shortcuts, Space first; Space and R work even with shortcuts off (sing.settings.shortcutsHint). i18n-ignore: key names
const SHORTCUTS = [{ key: tk('sing.settings.keys.space'), label: tk('sing.settings.keys.play'), always: true }, { key: 'R', label: tk('sing.settings.keys.record'), always: true }, { key: 'L', label: tk('sing.settings.keys.loop') }, { key: 'N', label: tk('sing.settings.keys.next') }, { key: 'M', label: tk('sing.settings.keys.mute') }];

export const SettingsSheet = {
    components: { Modal, Segmented, Switch, Icon, HearingSection },
    props: {
        speed: { type: Number, default: 1 },
        speedNote: { type: String, default: '' }, // the kind note when slowing means the practice voice
        ladder: Boolean, // Build up to speed (the tempo ladder)
        bpm: { type: Number, default: 0 }, // the song's tempo at full speed
        hasChords: Boolean,
        chords: Boolean,
        chordsLevel: { type: Number, default: 0.5 },
        chordsSound: { type: String, default: 'pad' },
        drone: { type: String, default: 'off' }, // a drone to tune against: 'off', 'key' or 'chord' (docs/tune-chord-plan.md)
        droneLevel: { type: Number, default: 0.4 },
        sample: { type: String, default: '' }, // your part's first notes, named as chosen ("B♭2 C3 D3")
        color: { type: String, required: true },
        hearing: { type: Object, required: true }, // What you hear: { rows, guideLevel, speakers, voices }
        recording: Boolean, // during a take only What you hear shows
        partName: { type: String, default: '' }, // a phone's part picker (the header's chip on a wide screen)
        partColor: { type: String, default: '' },
    },
    emits: ['part', 'toggle', 'level', 'speed', 'ladder', 'chords', 'chords-level', 'chords-sound', 'drone', 'drone-level', 'voices', 'split', 'reset', 'close'],
    setup: () => ({ SPEEDS, NOTE_STYLES, styleLabel, SHORTCUTS, prefs, BAND_STYLES, styleOf }),
    // Hillel's Session settings design (2026-11-23, docs/design-rules.md "The 2026-11-23 language"): a panel with its
    // header over a hairline, then two balanced columns split by a hairline on a wide screen: What you hear (with the
    // drone) and Practice | Display and Controls. Short choices sit inline at a row's end; longer ones go full width
    // under their label, its live sample at the label's end. During a take only What you hear shows.
    // Before it, 67.png: Display (Show, Note names with a sample, the keyboard, the other parts), Practice (Speed
    // with the tempo it plays at, Chords underneath, Guide voices), Controls (shortcuts and their keys).
    template: `
        <Modal :title="t('sing.settings.title')" panel :wider="!recording" @close="$emit('close')">
            <template #actions>
                <span v-if="!recording" class="hidden text-xs text-subtle md:inline">{{ t('sing.settings.applyNow') }}</span>
                <button v-if="!recording" type="button" class="btn-secondary btn-sm tap-44" @click="$emit('reset')">{{ t('sing.settings.reset') }}</button>
            </template>
            <div class="hairline-grid" :class="recording ? '' : 'lg:grid-cols-2'" data-session-settings>
                <div class="flex flex-col divide-y divide-edge">
                    <button v-if="partName && !recording" type="button" class="flex w-full items-center gap-4 px-6 pt-[22px] text-start sm:hidden" data-part-picker @click="$emit('part')">
                        <span class="size-3 shrink-0 rounded-full" :style="{ background: partColor }" aria-hidden="true"></span>
                        <span class="min-w-0 flex-1"><span class="t-section block">{{ t('sing.yourPart.title') }}</span><span class="set-row-title mt-1 block" translate="no">{{ partName }}</span></span>
                        <Icon name="chevron" :size="18" class="shrink-0 -rotate-90 text-subtle rtl:rotate-90" />
                    </button>
                    <HearingSection v-bind="hearing" :recording="recording" :class="partName && !recording ? 'max-sm:border-t-0' : ''" @toggle="$emit('toggle', $event)" @level="$emit('level', $event)" @voices="$emit('voices')" @split="$emit('split', $event)">
                        <!-- A drone to tune against (docs/tune-chord-plan.md): the key's note, or each chord tuned pure -->
                        <div v-if="!recording" class="space-y-3" data-drone>
                            <div class="space-y-[3px]">
                                <div class="set-row flex-wrap items-center gap-y-2">
                                    <p id="ss-drone" class="set-row-title flex-1 whitespace-nowrap">{{ t('sing.drone.title') }}</p>
                                    <Segmented :model-value="drone" kind="inset" :options="droneChoices" labelledby="ss-drone" @update:model-value="$emit('drone', $event)" />
                                </div>
                                <p class="t-hint">{{ drone === 'chord' ? t('sing.drone.chordHint') : drone === 'key' ? t('sing.drone.keyHint') : t('sing.drone.hint') }}</p>
                            </div>
                            <label v-if="drone !== 'off'" class="flex items-center gap-3 text-[0.8125rem] text-subtle">{{ t('sing.drone.level') }}
                                <input v-fill type="range" min="0" max="1" step="0.05" :value="droneLevel" class="range flex-1" @input="$emit('drone-level', Number($event.target.value))" />
                            </label>
                        </div>
                    </HearingSection>
                    <section v-if="!recording" class="flex-1 space-y-[18px] px-6 py-[22px]" :aria-label="t('sing.settings.practice')">
                        <h3 class="t-section" aria-hidden="true">{{ t('sing.settings.practice') }}</h3>
                        <div class="space-y-2">
                            <p class="flex items-baseline gap-3"><span id="ss-speed" class="set-row-title flex-1">{{ t('sing.speed') }}</span><span v-if="bpm" class="tabular-nums text-xs text-subtle">{{ t('sing.settings.tempo', { n: Math.round(bpm * speed) }) }}</span></p>
                            <Segmented :model-value="speed" kind="inset" full :options="speedChoices" labelledby="ss-speed" @update:model-value="$emit('speed', $event)" />
                            <p class="t-hint">{{ t('sing.settings.fullSpeedRecording') }}</p>
                            <p v-if="speedNote" class="t-hint">{{ speedNote }}</p>
                        </div>
                        <Switch row :on="ladder" :label="t('sing.ladder.label')" :hint="t('sing.ladder.hint')" data-ladder @change="$emit('ladder', $event)" />
                        <div v-if="hasChords" class="space-y-3">
                            <Switch row :on="chords" :label="t('sing.chords.title')" :hint="t('sing.chords.hint')" @change="$emit('chords', $event)" />
                            <div v-if="chords" class="flex flex-wrap items-center gap-3">
                                <Segmented :model-value="styleOf(chordsSound)" kind="inset" :options="bandChoices" :label="t('sing.chords.sound')" @update:model-value="$emit('chords-sound', $event)" />
                                <label class="flex min-w-40 flex-1 items-center gap-3 text-[0.8125rem] text-subtle">{{ t('sing.chords.level') }}
                                    <input v-fill type="range" min="0" max="1" step="0.05" :value="chordsLevel" class="range flex-1" @input="$emit('chords-level', Number($event.target.value))" />
                                </label>
                            </div>
                        </div>
                    </section>
                </div>
                <div v-if="!recording" class="flex flex-col divide-y divide-edge" data-settings-column>
                    <section class="space-y-[18px] px-6 py-[22px]" :aria-label="t('sing.settings.display')">
                        <h3 class="t-section" aria-hidden="true">{{ t('sing.settings.display') }}</h3>
                        <div class="space-y-2">
                            <p class="flex items-baseline gap-3"><span id="ss-names" class="set-row-title flex-1">{{ t('sing.settings.names.title') }}</span><span v-if="sample" class="tabular-nums text-xs text-subtle" translate="no">{{ sample }}</span></p>
                            <Segmented :model-value="prefs.noteNames" kind="inset" full :options="nameChoices" labelledby="ss-names" @update:model-value="prefs.noteNames = $event" />
                        </div>
                        <Switch row :on="prefs.keyboard" :label="t('sing.settings.keyboard')" :hint="t('sing.settings.keyboardHint')" @change="prefs.keyboard = $event" />
                        <Switch row :on="prefs.showOthers" :label="t('sing.settings.others')" :hint="t('sing.settings.othersHint')" @change="prefs.showOthers = $event" />
                        <!-- Tune the chord's meter (docs/tune-chord-plan.md): off until turned on -->
                        <Switch row :on="!!prefs.tuningMeter" :label="t('sing.settings.ring')" :tag="t('sing.settings.ringTag')" :hint="t('sing.settings.ringHintShort')" data-ring-switch @change="prefs.tuningMeter = $event" />
                    </section>
                    <section class="flex-1 space-y-3.5 px-6 py-[22px]" :aria-label="t('sing.settings.controls')">
                        <h3 class="t-section" aria-hidden="true">{{ t('sing.settings.controls') }}</h3>
                        <Switch row :on="prefs.shortcuts" :label="t('sing.settings.shortcuts')" @change="prefs.shortcuts = $event" />
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-[0.8125rem]">
                            <div v-for="k in SHORTCUTS" :key="k.key" class="flex items-center gap-2.5 transition-opacity" :class="prefs.shortcuts || k.always ? '' : 'opacity-35'"><dt><kbd class="kbd-key" translate="no">{{ k.key.length > 1 ? t(k.key) : k.key }}</kbd></dt><dd class="text-cream">{{ t(k.label) }}</dd></div>
                        </dl>
                        <p class="t-hint">{{ t('sing.settings.shortcutsHint') }}</p>
                    </section>
                </div>
            </div>
        </Modal>`,
    computed: {
        droneChoices() {
            return [{ value: 'off', label: t('sing.drone.off') }, { value: 'key', label: t('sing.drone.key') }, ...(this.hasChords ? [{ value: 'chord', label: t('sing.drone.chord') }] : [])];
        },
        speedChoices: () => SPEEDS.map((value) => ({ value, label: t('sing.speedPercent', { percent: Math.round(value * 100) }) })),
        nameChoices: () => NOTE_STYLES.map((value) => ({ value, label: styleLabel(value) })),
        bandChoices: () => BAND_STYLES.map((b) => ({ value: b.value, label: t(b.label) })),
    },
};

/**
 * Your part: each part with its range and who sings its guide; yours opens to its voices (each
 * real singer, the practice voice last) and the kind note; check your range; the way to sing.
 */
export const PartSheet = {
    components: { AiVoiceLabel, Modal, Icon },
    props: {
        parts: { type: Array, required: true }, // [{ slot, name, range, voiceLabel }]
        focusId: { type: String, default: null },
        voice: { type: Object, default: null }, // the focus part's guideVoices entry
        keyNote: { type: Object, default: null }, // { text, action, fit } when the real voice can't follow the key
        way: { type: String, default: 'whole' },
        disabled: Boolean,
        lend: Boolean, // your part has no real voice yet: Lend your voice's quiet line
        aiCta: Boolean, // Be the voice beside an AI voice (js/ai-voices.js aiCtaOpen): emits `be-voice`
    },
    emits: ['select', 'voice', 'range', 'way', 'close', 'lend', 'be-voice'],
    setup: () => ({ voiceCredit }),
    template: `
        <Modal :title="t('sing.yourPart.title')" @close="$emit('close')">
            <div class="space-y-4">
                <p class="t-hint text-sm">{{ t('sing.yourPart.intro') }}</p>
                <ul class="space-y-2">
                    <li v-for="p in parts" :key="p.slot.id" class="rounded-2xl border" :class="p.slot.id === focusId ? 'bg-raised/40' : 'border-edge'" :style="p.slot.id === focusId ? { borderColor: paint(p.slot.color) } : null">
                        <button type="button" class="flex w-full items-center gap-3 p-3 text-start disabled:opacity-60" :disabled="disabled" :aria-pressed="p.slot.id === focusId" @click="$emit('select', p.slot.id)">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full border-2 text-sm font-bold" :style="p.slot.id === focusId ? { background: paint(p.slot.color), borderColor: paint(p.slot.color), color: 'var(--color-ink)' } : { borderColor: paint(p.slot.color) }" translate="no">{{ p.name.slice(0, 1) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-cream"><span translate="no">{{ p.name }}</span><span v-if="p.slot.id === focusId" class="chip-tag ms-2 align-middle">{{ t('sing.yourPart.yours') }}</span></span>
                                <span class="block truncate text-[13px] text-subtle">{{ p.range }}</span>
                            </span>
                            <span class="hidden max-w-40 truncate text-xs text-subtle sm:block" translate="no">{{ p.voiceLabel }}</span>
                        </button>
                        <fieldset v-if="p.slot.id === focusId && voice?.versions?.length" class="space-y-2 px-3 pb-3" :disabled="disabled">
                            <legend class="mb-1 text-sm font-semibold text-cream">{{ t('sing.yourPart.voiceOf', { part: p.name }) }}</legend>
                            <label v-for="v in voice.versions" :key="v.id" class="radio-card px-3 py-2.5">
                                <input type="radio" name="singVoice" class="radio mt-px" :checked="chosen(v)" :disabled="blocked" @change="$emit('voice', [v.id])" />
                                <span v-if="v.source === 'ai'" class="min-w-0 flex-1 text-sm"><span class="block font-semibold text-cream"><AiVoiceLabel :based-on="v.aiBasedOn ?? null" :choir="!!v.choir" /></span><button v-if="aiCta" type="button" class="link tap-44" :aria-label="t('aiVoice.goPart', { part: p.name })" data-ai-voice-go @click.prevent.stop="$emit('be-voice')">{{ t('voices.be.go') }}</button></span>
                                <span v-else class="min-w-0 flex-1 text-sm"><span class="block font-semibold text-cream" translate="no">{{ voiceCredit(v) }}</span><span class="text-muted">{{ v.default ? t('part.defaultVoice') : t('part.otherVoice') }}</span></span>
                            </label>
                            <label class="radio-card px-3 py-2.5">
                                <input type="radio" name="singVoice" class="radio mt-px" :checked="voice.chosen.includes('synth') || blocked" @change="$emit('voice', 'synth')" />
                                <span class="min-w-0 flex-1 text-sm"><span class="block font-semibold text-cream">{{ blocked ? t('studio.badge.practiceInKey') : t('part.practiceVoice') }}</span><span class="text-muted">{{ t('part.practiceVoiceHint') }}</span></span>
                            </label>
                            <div v-if="keyNote" class="space-y-1" role="note">
                                <p class="alert-inline" style="--alert-c: var(--color-accent)"><span>{{ keyNote.text }}</span></p>
                                <button v-if="keyNote.action" type="button" class="btn-ghost btn-sm" @click="keyNote.action.run(); $emit('close')">{{ keyNote.action.label }}</button>
                                <p v-if="keyNote.fit" class="t-hint ps-4">{{ keyNote.fit }}</p>
                            </div>
                        </fieldset>
                        <p v-if="p.slot.id === focusId && lend" class="px-3 pb-3 text-sm text-muted" data-lend-line>{{ t('voices.partLine') }} <button type="button" class="link min-h-11" @click="$emit('lend')">{{ t('voices.partLineLink') }}</button></p>
                    </li>
                </ul>
                <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                    <button type="button" class="btn-ghost btn-sm" :disabled="disabled" @click="$emit('range')"><Icon name="mic" :size="14" /> {{ t('sing.yourPart.range') }}</button>
                    <button type="button" class="btn-ghost btn-sm" :disabled="disabled" @click="$emit('way', way === 'parts' ? 'whole' : 'parts')">{{ way === 'parts' ? t('sing.yourPart.whole') : t('sing.yourPart.parts') }}</button>
                </div>
            </div>
        </Modal>`,
    computed: {
        blocked() {
            return ['key', 'version', 'speed'].includes(this.voice?.why);
        },
    },
    methods: {
        chosen(version) {
            return !this.blocked && !this.voice.chosen.includes('synth') && this.voice.chosen.includes(version.id);
        },
    },
};
