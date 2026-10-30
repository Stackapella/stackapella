// Sing's two sheets (the Sing redesign): Settings, for what you set once (the view, slow practice,
// the chords, note names, the keyboard and the other parts on the lane, the voices), and Your
// part (each part with its range and voice; yours opens to its voices and the kind note when the
// real voice can't follow your key; check your range; learn it part by part or the whole song).

import { styleOf } from '../../band.js';
import { tk } from '../../i18n.js';
import { NOTE_STYLES } from '../../notes.js';
import { prefs } from '../../stores/prefs.js';
import { Modal } from '../ui.js';
import { Icon } from '../icon.js';
import { HearingSection } from './hearing.js';
import { Switch } from '../controls.js';

const VIEWS = [
    { value: 'words', label: tk('sing.view.words') },
    { value: 'notes', label: tk('sing.view.notes') },
    { value: 'staff', label: tk('sing.view.staff') },
];
const SPEEDS = [1, 0.85, 0.7];
const STYLE_LABELS = { letters: tk('sing.settings.names.letters'), solfege: tk('sing.settings.names.solfege'), numbers: tk('sing.settings.names.numbers'), off: tk('sing.settings.names.off') };

// The band's styles (js/band.js), for the chords underneath.
const BAND_STYLES = [{ value: 'gentle', label: tk('band.style.gentle') }, { value: 'pop', label: tk('band.style.pop') }, { value: 'swing', label: tk('band.style.swing') }];
// The single-letter shortcuts (Space always plays). i18n-ignore: key names
const SHORTCUTS = [{ key: 'R', label: tk('sing.settings.keys.record') }, { key: 'L', label: tk('sing.settings.keys.loop') }, { key: 'N', label: tk('sing.settings.keys.next') }, { key: 'M', label: tk('sing.settings.keys.mute') }];

export const SettingsSheet = {
    components: { Modal, Switch, Icon, HearingSection },
    props: {
        speed: { type: Number, default: 1 },
        speedNote: { type: String, default: '' }, // the kind note when slowing means the practice voice
        ladder: Boolean, // Build up to speed (the tempo ladder)
        bpm: { type: Number, default: 0 }, // the song's tempo at full speed
        hasChords: Boolean,
        chords: Boolean,
        chordsLevel: { type: Number, default: 0.5 },
        chordsSound: { type: String, default: 'pad' },
        view: { type: String, default: 'words' },
        sample: { type: String, default: '' }, // your part's first notes, named as chosen ("B♭2 C3 D3")
        color: { type: String, required: true },
        hearing: { type: Object, required: true }, // What you hear: { rows, guideLevel, speakers, voices }
        recording: Boolean, // during a take only What you hear shows
        partName: { type: String, default: '' }, // a phone's part picker (the header's chip on a wide screen)
        partColor: { type: String, default: '' },
    },
    emits: ['part', 'toggle', 'level', 'view', 'speed', 'ladder', 'chords', 'chords-level', 'chords-sound', 'voices', 'split', 'reset', 'close'],
    setup: () => ({ VIEWS, SPEEDS, NOTE_STYLES, STYLE_LABELS, SHORTCUTS, prefs, BAND_STYLES, styleOf }),
    // 67.png: Display (Show, Note names with a sample, the keyboard, the other parts), Practice (Speed
    // with the tempo it plays at, Chords underneath, Guide voices), Controls (shortcuts and their keys).
    template: `
        <Modal :title="t('sing.settings.title')" @close="$emit('close')">
            <template #actions><button v-if="!recording" type="button" class="link-quiet min-h-11 text-sm" @click="$emit('reset')">{{ t('sing.settings.reset') }}</button></template>
            <div class="-mx-6 divide-y divide-line" data-session-settings>
                <button v-if="partName && !recording" type="button" class="flex w-full items-center gap-4 px-6 pb-6 text-start sm:hidden" data-part-picker @click="$emit('part')">
                    <span class="size-3 shrink-0 rounded-full" :style="{ background: partColor }" aria-hidden="true"></span>
                    <span class="min-w-0 flex-1"><span class="block text-xs font-bold uppercase tracking-[0.14em] text-muted">{{ t('sing.yourPart.title') }}</span><span class="block font-semibold text-cream" translate="no">{{ partName }}</span></span>
                    <Icon name="chevron" :size="18" class="shrink-0 -rotate-90 text-muted rtl:rotate-90" />
                </button>
                <HearingSection v-bind="hearing" :recording="recording" :class="partName && !recording ? 'max-sm:pt-6' : ''" @toggle="$emit('toggle', $event)" @level="$emit('level', $event)" @voices="$emit('voices')" @split="$emit('split', $event)" />
                <template v-if="!recording">
                <section class="space-y-5 px-6 py-6" :aria-label="t('sing.settings.display')">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted" aria-hidden="true">{{ t('sing.settings.display') }}</p>
                    <fieldset>
                        <legend class="mb-2 font-semibold text-cream">{{ t('sing.settings.show') }}</legend>
                        <div class="grid grid-cols-3 rounded-2xl border border-line p-1">
                            <button v-for="v in VIEWS" :key="v.value" type="button" class="min-h-10 rounded-xl text-sm font-semibold" :class="view === v.value ? 'glass-bubble-solid' : 'text-muted hover:text-cream'" :aria-pressed="view === v.value ? 'true' : 'false'" data-view @click="$emit('view', v.value)">{{ t(v.label) }}</button>
                        </div>
                    </fieldset>
                    <fieldset>
                        <div class="mb-2 flex items-baseline justify-between gap-3"><legend class="font-semibold text-cream">{{ t('sing.settings.names.title') }}</legend><span v-if="sample" class="tabular-nums text-xs text-muted" translate="no">{{ sample }}</span></div>
                        <div class="grid rounded-2xl border border-line p-1 grid-cols-4">
                            <button v-for="n in NOTE_STYLES" :key="n" type="button" class="min-h-10 rounded-xl text-sm font-semibold" :class="prefs.noteNames === n ? 'glass-bubble-solid' : 'text-muted hover:text-cream'" :aria-pressed="prefs.noteNames === n ? 'true' : 'false'" @click="prefs.noteNames = n">{{ t(STYLE_LABELS[n]) }}</button>
                        </div>
                    </fieldset>
                    <Switch :on="prefs.keyboard" :label="t('sing.settings.keyboard')" :hint="t('sing.settings.keyboardHint')" @change="prefs.keyboard = $event" />
                    <Switch :on="prefs.showOthers" :label="t('sing.settings.others')" :hint="t('sing.settings.othersHint')" @change="prefs.showOthers = $event" />
                </section>
                <section class="space-y-5 px-6 py-6" :aria-label="t('sing.settings.practice')">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted" aria-hidden="true">{{ t('sing.settings.practice') }}</p>
                    <fieldset>
                        <div class="mb-2 flex items-baseline justify-between gap-3"><legend class="font-semibold text-cream">{{ t('sing.speed') }}</legend><span v-if="bpm" class="tabular-nums text-xs text-muted">{{ t('studio.meta.bpm', { bpm: Math.round(bpm * speed) }) }}</span></div>
                        <div class="grid rounded-2xl border border-line p-1 grid-cols-3">
                            <button v-for="sp in SPEEDS" :key="sp" type="button" class="min-h-10 rounded-xl text-sm font-semibold" :class="speed === sp ? 'glass-bubble-solid' : 'text-muted hover:text-cream'" :aria-pressed="speed === sp ? 'true' : 'false'" @click="$emit('speed', sp)">{{ t('sing.speedPercent', { percent: Math.round(sp * 100) }) }}</button>
                        </div>
                        <p class="mt-2 text-xs text-muted">{{ t('sing.settings.fullSpeedRecording') }}</p>
                        <p v-if="speedNote" class="mt-1 text-sm text-muted">{{ speedNote }}</p>
                    </fieldset>
                    <Switch :on="ladder" :label="t('sing.ladder.label')" :hint="t('sing.ladder.hint')" data-ladder @change="$emit('ladder', $event)" />
                    <div v-if="hasChords" class="space-y-3">
                        <Switch :on="chords" :label="t('sing.chords.title')" :hint="t('sing.chords.hint')" @change="$emit('chords', $event)" />
                        <div v-if="chords" class="flex flex-wrap items-center gap-3">
                            <div class="flex rounded-full border border-line p-0.5" role="group" :aria-label="t('sing.chords.sound')">
                                <button v-for="snd in BAND_STYLES" :key="snd.value" type="button" class="rounded-full px-3 py-1.5 text-sm" :class="styleOf(chordsSound) === snd.value ? 'bg-raised font-semibold text-cream' : 'text-muted'" :aria-pressed="styleOf(chordsSound) === snd.value ? 'true' : 'false'" @click="$emit('chords-sound', snd.value)">{{ t(snd.label) }}</button>
                            </div>
                            <label class="flex flex-1 items-center gap-2 text-sm text-muted">{{ t('sing.chords.level') }}
                                <input v-fill type="range" min="0" max="1" step="0.05" :value="chordsLevel" class="range flex-1" @input="$emit('chords-level', Number($event.target.value))" />
                            </label>
                        </div>
                    </div>
                </section>
                <section class="space-y-4 px-6 pt-6" :aria-label="t('sing.settings.controls')">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted" aria-hidden="true">{{ t('sing.settings.controls') }}</p>
                    <Switch :on="prefs.shortcuts" :label="t('sing.settings.shortcuts')" @change="prefs.shortcuts = $event" />
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm" :class="prefs.shortcuts ? '' : 'opacity-50'">
                        <div v-for="k in SHORTCUTS" :key="k.key" class="flex items-center gap-2.5"><dt><kbd class="grid size-7 place-items-center rounded-md border border-line bg-raised tabular-nums text-xs font-bold text-cream">{{ k.key }}</kbd></dt><dd class="text-cream">{{ t(k.label) }}</dd></div>
                    </dl>
                    <p class="text-xs text-muted">{{ t('sing.settings.shortcutsHint') }}</p>
                </section>
                </template>
            </div>
        </Modal>`,
};

/**
 * Your part: each part with its range and who sings its guide; yours opens to its voices (each
 * real singer, the practice voice last) and the kind note; check your range; the way to sing.
 */
export const PartSheet = {
    components: { Modal, Icon },
    props: {
        parts: { type: Array, required: true }, // [{ slot, name, range, voiceLabel }]
        focusId: { type: String, default: null },
        voice: { type: Object, default: null }, // the focus part's guideVoices entry
        keyNote: { type: Object, default: null }, // { text, action, fit } when the real voice can't follow the key
        way: { type: String, default: 'whole' },
        disabled: Boolean,
    },
    emits: ['select', 'voice', 'range', 'way', 'close'],
    template: `
        <Modal :title="t('sing.yourPart.title')" @close="$emit('close')">
            <div class="space-y-4">
                <p class="text-sm text-muted">{{ t('sing.yourPart.intro') }}</p>
                <ul class="space-y-2">
                    <li v-for="p in parts" :key="p.slot.id" class="rounded-2xl border" :class="p.slot.id === focusId ? 'bg-raised/40' : 'border-line'" :style="p.slot.id === focusId ? { borderColor: paint(p.slot.color) } : null">
                        <button type="button" class="flex w-full items-center gap-3 p-3 text-start disabled:opacity-60" :disabled="disabled" :aria-pressed="p.slot.id === focusId" @click="$emit('select', p.slot.id)">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full border-2 text-sm font-bold" :style="p.slot.id === focusId ? { background: paint(p.slot.color), borderColor: paint(p.slot.color), color: 'var(--color-ink)' } : { borderColor: paint(p.slot.color) }" translate="no">{{ p.name.slice(0, 1) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-cream"><span translate="no">{{ p.name }}</span><span v-if="p.slot.id === focusId" class="ms-2 rounded-full bg-raised px-2 py-0.5 text-xs text-muted">{{ t('sing.yourPart.yours') }}</span></span>
                                <span class="block truncate text-sm text-muted">{{ p.range }}</span>
                            </span>
                            <span class="hidden max-w-40 truncate text-xs text-muted sm:block" translate="no">{{ p.voiceLabel }}</span>
                        </button>
                        <fieldset v-if="p.slot.id === focusId && voice?.versions?.length" class="space-y-2 px-3 pb-3" :disabled="disabled">
                            <legend class="mb-1 text-sm font-semibold text-cream">{{ t('sing.yourPart.voiceOf', { part: p.name }) }}</legend>
                            <label v-for="v in voice.versions" :key="v.id" class="radio-card px-3 py-2.5">
                                <input type="radio" name="singVoice" class="radio mt-px" :checked="chosen(v)" :disabled="blocked" @change="$emit('voice', [v.id])" />
                                <span class="min-w-0 flex-1 text-sm"><span class="block font-semibold text-cream" translate="no">{{ v.credit ? t('part.sungBy', { name: v.credit }) : t('part.sungByAnonymous') }}</span><span class="text-muted">{{ v.default ? t('part.defaultVoice') : t('part.otherVoice') }}</span></span>
                            </label>
                            <label class="radio-card px-3 py-2.5">
                                <input type="radio" name="singVoice" class="radio mt-px" :checked="voice.chosen.includes('synth') || blocked" @change="$emit('voice', 'synth')" />
                                <span class="min-w-0 flex-1 text-sm"><span class="block font-semibold text-cream">{{ blocked ? t('studio.badge.practiceInKey') : t('part.practiceVoice') }}</span><span class="text-muted">{{ t('part.practiceVoiceHint') }}</span></span>
                            </label>
                            <div v-if="keyNote" class="space-y-1 rounded-xl bg-ink/60 p-3 text-sm text-cream" role="note">
                                <p>{{ keyNote.text }}</p>
                                <button v-if="keyNote.action" type="button" class="btn-ghost btn-sm" @click="keyNote.action.run(); $emit('close')">{{ keyNote.action.label }}</button>
                                <p v-if="keyNote.fit" class="text-muted">{{ keyNote.fit }}</p>
                            </div>
                        </fieldset>
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
