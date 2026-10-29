// The note editor (docs/template-creator-plan.md, "The note editor"), for Write it, a copy, one of
// yours, a file, or fixing anything by hand. On a computer or tablet: the part rail (each part with
// its range, Mute, and Edit this part), the tools (Notes or Words, Draw or Select, the grid, Lane or
// Staff, zoom, undo and redo, play), the lane, and the inspector for the selected note. Every note
// sounds when it's placed, moved or picked; each change is announced; a list shows the phrase's
// notes as text; `?` opens the shortcuts. On a phone, fixing rather than composing: tap a note for
// a sheet (higher, lower, longer, shorter, its syllable, delete, add one after, or hold to sing its
// pitch).

import { markRaw } from 'vue';
import { midiName, hzToMidi } from '../../dsp.js';
import { formatNumber, t, tk } from '../../i18n.js';
import { syllables as splitWords } from '../../lyrics.js';
import { readyToSing, hearNotes, playTemplate } from '../../creator-audio.js';
import { engine } from '../../audio.js';
import { trackFrame } from '../../pitchtrack.js';
import { gridFor, joinNotes, splitNote, tidy, withNotes, wordsFrom } from '../../creator.js';
import { clefFor, staffLayout, staffPitch } from '../../sing.js';
import { beatsPerBar, PART_COLORS } from '../../theme.js';
import { templatePartName } from '../../templates.js';
import { parseKey } from '../../notes.js';
import { Icon } from '../icon.js';
import { micError } from '../mic.js';
import { PitchBar } from '../listening.js';
import { LevelMeter } from '../level-meter.js';
import { pitchDisplay, stepDisplay } from '../../pitch-display.js';
import { Modal } from '../ui.js';
import { StaffLine } from '../sing/staff.js';
import { Roll } from './roll.js';

const KEYS = ['soprano', 'alto', 'tenor', 'bass'];
const NAMES = { soprano: 'Soprano', alto: 'Alto', tenor: 'Tenor', bass: 'Bass' }; // i18n-ignore: the file's English names
// Where a new part starts, if it has no notes yet.
const HOME = { soprano: 67, alto: 62, tenor: 55, bass: 48 };
// Lengths for the keys 1 to 6, in beats (a quarter is a beat; 6/8's beat is a dotted quarter).
const LENGTHS = [0.25, 0.5, 1, 2, 3, 4];
const LENGTHS_68 = [1 / 6, 1 / 3, 2 / 3, 1, 1.5, 2];
// Beats are kept to 4 decimals (a third is 0.3333): two the same within this are one beat.
const near = (a, b) => Math.abs(a - b) < 1e-3;
const LENGTH_NAMES = [tk('create.edit.len.sixteenth'), tk('create.edit.len.eighth'), tk('create.edit.len.quarter'), tk('create.edit.len.half'), tk('create.edit.len.dottedHalf'), tk('create.edit.len.whole')];
const SHORTCUTS = [
    [tk('create.keys.arrowsUpDown'), tk('create.keys.semitone')],
    [tk('create.keys.shiftUpDown'), tk('create.keys.octave')],
    [tk('create.keys.arrowsLeftRight'), tk('create.keys.moveGrid')],
    [tk('create.keys.altLeftRight'), tk('create.keys.nextNote')],
    [tk('create.keys.numbers'), tk('create.keys.lengths')],
    [tk('create.keys.space'), tk('create.keys.play')],
    [tk('create.keys.undo'), tk('create.keys.undoWhat')],
    [tk('create.keys.redo'), tk('create.keys.redoWhat')],
    [tk('create.keys.delete'), tk('create.keys.deleteWhat')],
    [tk('create.keys.selectAll'), tk('create.keys.selectAllWhat')],
    [tk('create.keys.wordsSpace'), tk('create.keys.wordsSpaceWhat')],
    [tk('create.keys.wordsDash'), tk('create.keys.wordsDashWhat')],
    [tk('create.keys.wordsUnderscore'), tk('create.keys.wordsUnderscoreWhat')],
    [tk('create.keys.wordsEnter'), tk('create.keys.wordsEnterWhat')],
];

export const EditorStep = {
    components: { Icon, Modal, Roll, StaffLine, PitchBar, LevelMeter },
    props: { draft: { type: Object, required: true } },
    emits: ['change', 'template'],
    data: (vm) => ({
        focus: (vm.draft.template.parts.find((p) => p.melody) ?? vm.draft.template.parts[0])?.key ?? 'soprano',
        selected: [],
        mode: 'notes',
        tool: 'draw',
        gridIndex: 1,
        zoom: 48,
        view: 'lane',
        muted: [],
        history: [],
        future: [],
        said: '',
        player: null,
        playhead: null,
        cursor: 0, // where Play starts: the playhead you move (a note picked moves it there)
        shortcuts: false,
        phone: window.matchMedia('(max-width: 767px)').matches,
        sheet: false,
        word: '',
        listOpen: false,
        holding: null,
        solo: null, // a part heard alone
        phoneHelp: false,
        coarse: window.matchMedia('(pointer: coarse)').matches, // a touch screen: the help says tap, not click
        helpOpen: true,
    }),
    setup: () => ({ KEYS, SHORTCUTS, LENGTH_NAMES, midiName, templatePartName, formatNumber }),
    template: `
        <section class="space-y-4">
            <div>
                <h2 class="text-3xl font-extrabold tracking-tight text-cream">{{ t('create.edit.title') }}</h2>
                <p class="mt-1 text-muted">{{ phone ? t('create.edit.phoneHint') : t('create.edit.hint') }}</p>
            </div>
            <!-- The toolbar card (Hillel's 60.png): the tools on the left, help and Play all on the right. -->
            <div class="card flex flex-wrap items-center gap-2 p-2" role="toolbar" :aria-label="t('create.edit.tools')">
                <div v-if="!phone" class="seg flex" role="radiogroup" :aria-label="t('create.edit.mode')">
                    <button v-for="m in ['notes', 'words']" :key="m" type="button" role="radio" :aria-checked="mode === m" class="seg-option tap-44" @click="setMode(m)">{{ m === 'notes' ? t('create.edit.notes') : t('create.edit.words') }}</button>
                </div>
                <div v-if="!phone && mode === 'notes'" class="seg flex" role="radiogroup" :aria-label="t('create.edit.tool')">
                    <button v-for="k in ['draw', 'select']" :key="k" type="button" role="radio" :aria-checked="tool === k" class="seg-option tap-44" @click="tool = k">{{ k === 'draw' ? t('create.edit.draw') : t('create.edit.select') }}</button>
                </div>
                <label v-if="!phone" class="flex items-center gap-1.5 rounded-full border border-edge px-3 py-1.5 text-sm text-muted">{{ t('create.edit.snap') }}
                    <select v-model.number="gridIndex" class="bg-transparent font-semibold text-cream">
                        <option v-for="(g, i) in grids" :key="i" :value="i">{{ t(g.label) }}</option>
                    </select>
                </label>
                <div class="seg flex" role="radiogroup" :aria-label="t('create.edit.view')">
                    <button v-for="v in ['lane', 'staff']" :key="v" type="button" role="radio" :aria-checked="view === v" class="seg-option tap-44" @click="view = v">{{ v === 'lane' ? t('create.edit.lane') : t('create.edit.staff') }}</button>
                </div>
                <button type="button" class="btn-ghost btn-sm" :disabled="!history.length" :aria-label="t('common.undo')" :title="t('common.undo')" @click="undo"><Icon name="undo" :size="16" /><span class="hidden xl:inline">{{ t('common.undo') }}</span></button>
                <button type="button" class="btn-ghost btn-sm" :disabled="!future.length" :aria-label="t('create.edit.redo')" :title="t('create.edit.redo')" @click="redo"><Icon name="redo" :size="16" /><span class="hidden xl:inline">{{ t('create.edit.redo') }}</span></button>
                <span class="ms-auto"></span>
                <button v-if="!phone && !helpOpen" type="button" class="btn-ghost btn-sm" @click="helpOpen = true">{{ t('create.edit.showHelp') }}</button>
                <!-- ?: the keys on a computer; on a phone (no keys), how to fix notes there. -->
                <button type="button" class="grid size-10 place-items-center rounded-full glass-bubble font-bold text-cream" :aria-label="phone ? t('create.edit.help') : t('create.keys.title')" @click="phone ? (phoneHelp = true) : (shortcuts = true)">?</button>
                <button v-if="!phone" type="button" class="btn-ghost btn-sm" :aria-label="t('studio.rewind')" :title="t('studio.rewind')" :disabled="!player && cursor === 0" data-rewind @click="seek(0)"><Icon name="rewind" :size="16" /></button>
                <button type="button" class="btn-primary btn-sm" @click="play"><Icon :name="player ? 'stop' : 'play'" :size="16" /> {{ player ? t('studio.stop') : t('create.edit.playAll') }}</button>
            </div>

            <div class="grid gap-4" :class="phone ? '' : helpOpen || note ? 'md:grid-cols-[13rem_minmax(0,1fr)_15rem]' : 'md:grid-cols-[13rem_minmax(0,1fr)]'">
                <!-- The parts: a card each (a chip row on a phone), the one being edited outlined in the accent. -->
                <ul class="flex gap-2 overflow-x-auto pb-1 md:flex-col md:overflow-visible md:pb-0" :aria-label="t('create.edit.parts')">
                    <li v-for="part in parts" :key="part.key" class="cursor-pointer border bg-panel transition" :class="[phone ? 'shrink-0 rounded-full px-3 py-2' : 'min-w-40 rounded-2xl p-3', part.key === focus ? 'border-accent ring-1 ring-accent' : 'border-edge hover:border-line']" @click="setFocus(part.key)">
                        <div class="flex items-center gap-2">
                            <span class="gem size-3.5 shrink-0 rounded-full" :style="{ '--gem': colorOf(part.key) }" aria-hidden="true"></span>
                            <button type="button" class="min-w-0 flex-1 truncate text-start font-bold text-cream" :aria-pressed="part.key === focus" @click.stop="setFocus(part.key)">{{ templatePartName(part) }}</button>
                            <span v-if="part.key === focus && !phone" class="text-[10px] font-bold uppercase tracking-[0.14em] text-accent-strong">{{ t('create.edit.editing') }}</span>
                        </div>
                        <template v-if="!phone">
                        <p class="mt-1 tabular-nums text-xs text-muted" translate="no">{{ part.guide.notes.length ? t('create.edit.rangeCount', { low: midiName(part.range.low), high: midiName(part.range.high), count: part.guide.notes.length }) : t('create.edit.empty') }}</p>
                        <div class="mt-2 flex gap-1.5">
                            <button type="button" class="chip tap-44" :class="muted.includes(part.key) ? 'border-cream text-cream' : 'hover:text-cream'" :aria-pressed="muted.includes(part.key)" @click.stop="toggleMute(part.key)">{{ t('create.edit.mute') }}</button>
                            <button type="button" class="chip tap-44" :class="solo === part.key ? 'border-accent text-cream' : 'hover:text-cream'" :aria-pressed="solo === part.key" @click.stop="solo = solo === part.key ? null : part.key">{{ t('create.edit.solo') }}</button>
                        </div>
                        </template>
                    </li>
                    <li v-if="missing.length && !phone" class="min-w-40">
                        <label class="block rounded-2xl border border-dashed border-line p-3 text-xs text-muted">{{ t('create.edit.addPart') }}
                            <select class="input mt-1 py-1 text-sm" @change="addPart($event.target.value); $event.target.value = ''">
                                <option value="">{{ t('create.edit.choosePart') }}</option>
                                <option v-for="k in missing" :key="k" :value="k">{{ templatePartName({ key: k, name: k }) }}</option>
                            </select>
                        </label>
                    </li>
                </ul>

                <div class="min-w-0 space-y-3">
                    <Roll v-if="view === 'lane'" :parts="rollParts" :focus="focus" :beats-per-bar="perBar" :pickup-beats="draft.template.pickupBeats" :end-beat="end" :zoom="phone ? 36 : zoom" :selected="selected" :playhead="player ? playhead : cursor" :seekable="!phone" :grid="grid.beats" :key-of="keyOf" :editable="!phone && mode === 'notes'" :label="t('create.edit.laneLabel', { part: templatePartName(focusPart), count: notes.length })"
                        @select="pick" @edit="dragged" @add="added" @seek="seek" />
                    <div v-else class="overflow-x-auto rounded-2xl border border-edge bg-panel p-4 text-cream">
                        <StaffLine v-if="staff" :layout="staff" :current="staffCurrent" :color="colorOf(focus)" :editable="!phone && mode === 'notes'" class="w-full" @pick="(i) => pick(phraseNotes[i].index)" @place="placed" />
                        <p v-else class="text-muted">{{ t('create.edit.staffEmpty') }}</p>
                    </div>
                    <!-- Hillel: zoom under the notes, at the bottom right, so the toolbar keeps to one line. -->
                    <div v-if="!phone && view === 'lane'" class="-mt-1 flex justify-end gap-1" data-zoom>
                        <button type="button" class="btn-ghost btn-sm" :aria-label="t('create.edit.zoomOut')" :title="t('create.edit.zoomOut')" :disabled="zoom <= 16" @click="zoom = Math.max(16, zoom - 12)"><Icon name="minus" :size="16" /></button>
                        <button type="button" class="btn-ghost btn-sm" :aria-label="t('create.edit.zoomIn')" :title="t('create.edit.zoomIn')" :disabled="zoom >= 160" @click="zoom = Math.min(160, zoom + 12)"><Icon name="plus" :size="16" /></button>
                    </div>
                    <div v-if="mode === 'words' && !phone" class="card flex flex-wrap items-center gap-3 p-3">
                        <label class="text-sm font-semibold text-muted" for="words-input">{{ t('create.edit.wordFor', { note: note ? midiName(note.pitch) : '' }) }}</label>
                        <input id="words-input" ref="words" v-model="word" class="input w-48 font-semibold" translate="no" autocomplete="off" :disabled="!note" @keydown="wordKey" @paste="pasteWords" />
                        <p class="text-xs text-muted">{{ t('create.edit.wordsHint') }}</p>
                    </div>
                    <button type="button" class="btn-ghost btn-sm" :aria-expanded="listOpen" @click="listOpen = !listOpen">{{ listOpen ? t('create.edit.hideList') : t('create.edit.showList') }}</button>
                    <ol v-if="listOpen" class="space-y-1 text-sm text-cream" :aria-label="t('create.edit.listLabel')">
                        <li v-for="(n, i) in phraseNotes" :key="i"><button type="button" class="w-full rounded-lg px-2 py-1.5 text-start hover:bg-raised" :class="selected.includes(n.index) ? 'bg-raised text-cream' : ''" :aria-current="selected.includes(n.index) ? 'true' : null" @click="pick(n.index)">{{ describe(n) }}</button></li>
                    </ol>
                </div>

                <!-- The help panel: the selected note's details, or how to write this part (gone on a phone: ? has it). -->
                <!-- The selected note's details stay when the help is hidden (Hide help hides only the how-to). -->
                <aside v-if="!phone && (helpOpen || note)" class="card space-y-3 self-start p-5" :aria-label="note ? t('create.edit.inspector') : t('create.edit.help')">
                    <template v-if="note">
                        <p class="text-2xl font-extrabold text-cream" translate="no">{{ midiName(note.pitch) }}</p>
                        <div class="flex gap-2">
                            <button type="button" class="btn-secondary btn-sm" :aria-label="t('create.check.upLabel')" @click="nudgePitch(1)"><Icon name="plus" :size="16" /></button>
                            <button type="button" class="btn-secondary btn-sm" :aria-label="t('create.check.downLabel')" @click="nudgePitch(-1)"><Icon name="minus" :size="16" /></button>
                        </div>
                        <p class="text-sm text-muted">{{ t('create.edit.starts', { where: whereOf(note.beat) }) }}</p>
                        <label class="block text-sm text-muted">{{ t('create.edit.length') }}
                            <select class="input mt-1 py-1" :value="lengthIndex(note.length)" @change="setLength(lengths[$event.target.value])">
                                <option v-if="lengthIndex(note.length) < 0" :value="-1">{{ formatNumber(note.length) }}</option>
                                <option v-for="(l, i) in lengths" :key="i" :value="i">{{ t(LENGTH_NAMES[i]) }}</option>
                            </select>
                        </label>
                        <label class="block text-sm text-muted">{{ t('create.edit.syllable') }}
                            <input class="input mt-1 py-1" translate="no" :value="note.lyric ?? ''" @change="setLyric($event.target.value)" />
                        </label>
                        <label class="check-row"><input type="checkbox" class="checkbox" :checked="!!note.br" @change="setBr($event.target.checked)" /> {{ t('create.edit.newLine') }}</label>
                        <!-- The note's actions, together: each says its key, and why when it can't be done. -->
                        <div class="grid grid-cols-3 gap-2 border-t border-line pt-3" role="group" :aria-label="t('create.edit.noteActions')" data-note-actions>
                            <button v-for="a in noteActions" :key="a.key" type="button" class="btn-secondary btn-sm h-auto flex-col gap-1 rounded-xl px-1 py-2" :class="a.key === 'delete' ? 'text-bad' : ''" :disabled="!!a.why" :title="a.why || a.tip" :data-action="a.key" @click="a.run()"><Icon :name="a.icon" :size="16" /><span>{{ a.label }}</span></button>
                        </div>
                        <p v-if="noteActions.some((a) => a.why)" class="text-xs text-muted">{{ noteActions.filter((a) => a.why).map((a) => a.why).join(' ') }}</p>
                    </template>
                    <template v-else>
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-bold text-cream">{{ notes.length ? t('create.edit.howTitle') : t('create.edit.emptyTitle', { part: templatePartName(focusPart) }) }}</h3>
                            <button type="button" class="-me-2 -mt-1 grid size-9 shrink-0 place-items-center rounded-full text-muted hover:text-cream" :aria-label="t('create.edit.hideHelp')" @click="helpOpen = false"><Icon name="close" :size="16" /></button>
                        </div>
                        <p class="text-sm text-cream">{{ tool === 'draw' ? (coarse ? t('create.edit.howDrawTap') : t('create.edit.howDraw')) : coarse ? t('create.edit.howSelectTap') : t('create.edit.howSelect') }}</p>
                        <p class="text-sm text-muted">{{ t('create.edit.others') }}</p>
                    </template>
                </aside>
            </div>
            <p v-if="phone && !notes.length" class="text-sm text-muted">{{ t('create.edit.phoneEmpty') }}</p>
            <button v-if="phone" type="button" class="btn-secondary" @click="addAfter"><Icon name="plus" :size="18" /> {{ t('create.edit.addNote') }}</button>
            <p class="sr-only" aria-live="polite">{{ said }}</p>

            <Modal v-if="phone && sheet && note" :title="midiName(note.pitch)" title-is-content @close="sheet = false">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" class="btn-secondary" @click="nudgePitch(1)"><Icon name="plus" :size="18" /> {{ t('create.check.up') }}</button>
                        <button type="button" class="btn-secondary" @click="nudgePitch(-1)"><Icon name="minus" :size="18" /> {{ t('create.check.down') }}</button>
                        <button type="button" class="btn-secondary" @click="stepLength(1)">{{ t('create.edit.longer') }}</button>
                        <button type="button" class="btn-secondary" @click="stepLength(-1)">{{ t('create.edit.shorter') }}</button>
                    </div>
                    <button type="button" class="btn-secondary w-full select-none" :class="holding ? 'ring-2 ring-part-4' : ''" @pointerdown.prevent="holdStart" @pointerup="holdEnd" @pointerleave="holdEnd" @pointercancel="holdEnd" @contextmenu.prevent>
                        <Icon name="mic" :size="18" /> {{ holding ? t('create.edit.listening') : t('create.edit.hold') }}
                    </button>
                    <PitchBar v-if="holding" :pitch="holding.shown" :alpha="holding.alpha" :view="{ lo: note.pitch - 6, hi: note.pitch + 6 }" :label="holding.shown === null ? t('listening.barEmpty') : t('listening.barAt', { note: midiName(holding.shown) })" />
                    <LevelMeter v-if="holding" :db="holding.db" />
                    <label class="block text-sm text-muted">{{ t('create.edit.syllable') }}
                        <input class="input mt-1" translate="no" :value="note.lyric ?? ''" @change="setLyric($event.target.value)" />
                    </label>
                    <button type="button" class="btn-secondary w-full" @click="addAfter">{{ t('create.edit.addAfter') }}</button>
                    <!-- Split, Join and Delete in one compact row, as on a computer. -->
                    <div class="grid grid-cols-3 gap-2" role="group" :aria-label="t('create.edit.noteActions')">
                        <button v-for="a in noteActions" :key="a.key" type="button" class="btn-secondary btn-sm h-auto flex-col gap-1 rounded-xl px-1 py-2" :class="a.key === 'delete' ? 'text-bad' : ''" :disabled="!!a.why" :data-action="a.key" @click="a.run(); if (a.key === 'delete') sheet = false"><Icon :name="a.icon" :size="16" /><span>{{ a.label }}</span></button>
                    </div>
                    <p v-if="noteActions.some((a) => a.why)" class="text-xs text-muted">{{ noteActions.filter((a) => a.why).map((a) => a.why).join(' ') }}</p>
                    <p class="text-xs text-muted">{{ t('create.edit.biggerScreen') }}</p>
                </div>
            </Modal>

            <Modal v-if="phoneHelp" :title="t('create.edit.help')" @close="phoneHelp = false">
                <div class="space-y-3 text-sm">
                    <p class="text-cream">{{ t('create.edit.phoneHint') }}</p>
                    <p class="text-muted">{{ t('create.edit.others') }}</p>
                </div>
            </Modal>
            <Modal v-if="shortcuts" :title="t('create.keys.title')" wide @close="shortcuts = false">
                <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                    <template v-for="([keys, what], i) in SHORTCUTS" :key="i"><dt class="font-semibold text-cream">{{ t(keys) }}</dt><dd class="text-muted">{{ t(what) }}</dd></template>
                </dl>
            </Modal>
        </section>`,
    computed: {
        template() {
            return this.draft.template;
        },
        /** The song's key, for the lane's rows (parseKey: { tonic, minor }). */
        keyOf() {
            return this.template.key ? parseKey(this.template.key) : null;
        },
        perBar() {
            return beatsPerBar(this.template.timeSignature);
        },
        grids() {
            const six = this.template.timeSignature === '6/8';
            return six
                ? [{ beats: 1, label: tk('create.edit.gridDotted') }, { beats: 1 / 3, label: tk('create.edit.gridEighth') }, { beats: 1 / 6, label: tk('create.edit.gridSixteenth') }]
                : [{ beats: 1, label: tk('create.edit.gridQuarter') }, { beats: 0.5, label: tk('create.edit.gridEighth') }, { beats: 0.25, label: tk('create.edit.gridSixteenth') }];
        },
        grid() {
            return this.grids[this.gridIndex] ?? this.grids[1];
        },
        lengths() {
            return this.template.timeSignature === '6/8' ? LENGTHS_68 : LENGTHS;
        },
        parts() {
            return [...this.template.parts].sort((a, b) => KEYS.indexOf(a.key) - KEYS.indexOf(b.key));
        },
        missing() {
            return KEYS.filter((k) => !this.template.parts.some((p) => p.key === k));
        },
        focusPart() {
            return this.template.parts.find((p) => p.key === this.focus) ?? this.template.parts[0] ?? { key: this.focus, name: this.focus, guide: { notes: [] } };
        },
        notes() {
            return this.focusPart.guide.notes;
        },
        note() {
            return this.selected.length === 1 ? this.notes[this.selected[0]] ?? null : null;
        },
        rollParts() {
            return this.parts.filter((p) => !this.muted.includes(p.key) || p.key === this.focus).map((p) => ({ key: p.key, color: this.colorOf(p.key), notes: p.guide.notes }));
        },
        end() {
            const all = this.template.parts.flatMap((p) => p.guide.notes);
            return all.length ? Math.max(...all.map((n) => n.beat + n.length)) + this.perBar : this.perBar * 4;
        },
        /** The phrase the selection is in (the line it starts), for the list and the staff. */
        phraseNotes() {
            const at = this.selected[0] ?? 0;
            let start = 0;
            for (let i = 0; i <= Math.min(at, this.notes.length - 1); i++) if (this.notes[i].br) start = i;
            let stop = this.notes.length;
            for (let i = start + 1; i < this.notes.length; i++) {
                if (this.notes[i].br) {
                    stop = i;
                    break;
                }
            }
            return this.notes.slice(start, stop).map((n, k) => ({ ...n, index: start + k }));
        },
        staff() {
            const line = this.phraseNotes;
            if (!line.length) return null;
            const first = line[0].beat;
            const pickup = (((this.template.pickupBeats - first) % this.perBar) + this.perBar) % this.perBar;
            return staffLayout(line.map((n) => ({ ...n, beat: n.beat - first })), { key: this.template.key, clef: clefFor(this.focus), beatsPerBar: this.perBar, pickupBeats: pickup });
        },
        /** The selected note's actions: Split, Join and Delete, each with its key, or why it can't be done. */
        noteActions() {
            const n = this.note;
            const i = this.selected[0];
            return [
                { key: 'split', icon: 'split', label: t('create.check.split'), tip: t('create.edit.splitTip'), why: n && n.length < this.grid.beats * 2 - 1e-9 ? t('create.edit.cantSplit') : '', run: () => this.split() },
                { key: 'join', icon: 'join', label: t('create.edit.joinShort'), tip: t('create.edit.joinTip'), why: i >= this.notes.length - 1 ? t('create.edit.cantJoin') : '', run: () => this.join() },
                { key: 'delete', icon: 'trash', label: t('create.edit.delete'), tip: t('create.edit.deleteTip'), why: '', run: () => this.remove() },
            ];
        },
        staffCurrent() {
            return this.phraseNotes.findIndex((n) => this.selected.includes(n.index));
        },
    },
    watch: {
        'template.timeSignature'() {
            this.gridIndex = 1;
        },
    },
    mounted() {
        this.onKey = (event) => {
            // A key a control already used (the arrow keys in a segmented choice) isn't the editor's.
            if (event.defaultPrevented) return;
            if (event.target.closest?.('input,textarea,select,[role=dialog]')) return;
            if (event.key === ' ' && event.target.closest?.('button,a')) return; // Space presses the button
            this.key(event);
        };
        window.addEventListener('keydown', this.onKey);
    },
    unmounted() {
        window.removeEventListener('keydown', this.onKey);
        this.player?.stop();
        this.holdEnd();
    },
    methods: {
        colorOf(key) {
            return this.paint(PART_COLORS[[3, 0, 5, 2][KEYS.indexOf(key)] ?? 4]);
        },
        describe(n) {
            if (!Number.isFinite(n.pitch)) return t('create.edit.rest', { length: this.lengthName(n.length) });
            return t('create.edit.describe', { note: midiName(n.pitch), length: this.lengthName(n.length), syllable: n.lyric ? n.lyric.replace(/-$/, '') : t('create.edit.held') });
        },
        lengthIndex(length) {
            return this.lengths.findIndex((l) => near(l, length));
        },
        lengthName(length) {
            const i = this.lengthIndex(length);
            return i >= 0 ? t(LENGTH_NAMES[i]) : t('create.edit.beats', { count: formatNumber(Math.round(length * 100) / 100) });
        },
        whereOf(beat) {
            const into = beat - this.template.pickupBeats;
            if (into < 0) return t('create.edit.inPickup');
            return t('create.edit.barBeat', { bar: formatNumber(Math.floor(into / this.perBar) + 1), beat: formatNumber(Math.round(((into % this.perBar) + 1) * 100) / 100) });
        },
        /** A change to the focus part's notes: kept for undo, announced, and the note sounded. */
        commit(notes, { select = this.selected, sound = true } = {}) {
            this.history = [...this.history.slice(-99), this.template];
            this.future = [];
            const next = withNotes(this.template, this.focus, notes);
            this.$emit('template', next);
            this.selected = select.filter((i) => i < next.parts.find((p) => p.key === this.focus).guide.notes.length);
            this.$nextTick(() => {
                if (this.note) {
                    this.said = this.describe(this.note);
                    if (sound) this.sound(this.note);
                }
            });
        },
        sound(note) {
            hearNotes([{ beat: 0, length: Math.min(note.length, 1.5), pitch: note.pitch }], { tempo: this.template.tempo });
        },
        undo() {
            if (!this.history.length) return;
            this.future = [this.template, ...this.future];
            const before = this.history.at(-1);
            this.history = this.history.slice(0, -1);
            this.$emit('template', before);
            this.selected = [];
            this.said = t('create.edit.undone');
        },
        redo() {
            if (!this.future.length) return;
            this.history = [...this.history, this.template];
            const [next, ...rest] = this.future;
            this.future = rest;
            this.$emit('template', next);
            this.selected = [];
            this.said = t('create.edit.redone');
        },
        setMode(mode) {
            this.mode = mode;
            if (mode === 'words') {
                if (!this.selected.length && this.notes.length) this.selected = [0];
                this.word = this.note?.lyric ?? '';
                this.$nextTick(() => this.$refs.words?.focus());
            }
        },
        setFocus(key) {
            this.focus = key;
            this.selected = [];
        },
        toggleMute(key) {
            this.muted = this.muted.includes(key) ? this.muted.filter((k) => k !== key) : [...this.muted, key];
        },
        addPart(key) {
            if (!key) return;
            this.history = [...this.history.slice(-99), this.template];
            const part = { key, name: NAMES[key], range: { low: HOME[key], high: HOME[key] }, guide: { type: 'notes', notes: [] } };
            this.$emit('template', { ...this.template, parts: [...this.template.parts, part] });
            this.focus = key;
            this.selected = [];
        },
        pick(index, { additive = false } = {}) {
            this.selected = additive ? (this.selected.includes(index) ? this.selected.filter((i) => i !== index) : [...this.selected, index]) : [index];
            if (!this.player && this.notes[index]) this.cursor = this.notes[index].beat; // Play starts at the note picked, as it always has
            if (this.note) {
                this.sound(this.note);
                this.said = this.describe(this.note);
                this.word = this.note.lyric ?? '';
            }
            if (this.phone) this.sheet = true;
            if (this.mode === 'words') this.$nextTick(() => this.$refs.words?.focus());
        },
        /** A drag on the lane: notes moved or lengthened. */
        dragged({ indexes, dBeat, dPitch, dLength }) {
            const moved = this.notes.map((n, i) => (indexes.includes(i) ? { ...n, beat: Math.max(0, n.beat + dBeat), pitch: n.pitch + dPitch, length: Math.max(this.grid.beats, n.length + dLength) } : n));
            if (this.landsOnAnother(moved, indexes)) return;
            this.commitMoved(moved, indexes);
        },
        /** A move that would put a note on another's beat would lose one of them: it isn't made, and that's said. */
        landsOnAnother(moved, indexes) {
            const still = moved.filter((_, i) => !indexes.includes(i));
            if (!indexes.some((i) => still.some((n) => near(n.beat, moved[i].beat)))) return false;
            this.said = t('create.edit.noteThere');
            return true;
        },
        commitMoved(moved, indexes) {
            const keep = indexes.map((i) => moved[i]);
            const tidied = tidy(moved);
            this.commit(moved, { select: keep.map((n) => tidied.findIndex((m) => near(m.beat, n.beat) && m.pitch === n.pitch)).filter((i) => i >= 0) });
        },
        /** A tap on empty lane: in Draw, a note there (a beat long, or the grid's if longer). */
        added({ beat, pitch }) {
            if (this.tool !== 'draw' || this.mode !== 'notes') {
                this.selected = [];
                return;
            }
            const note = { beat, length: Math.max(1, this.grid.beats), pitch };
            const next = tidy([...this.notes, note]);
            this.commit([...this.notes, note], { select: [next.findIndex((n) => near(n.beat, note.beat))] });
        },
        /** A press on the staff (Draw): a note there, on the grid, at the staff line's pitch in the key; Select clears the choice. */
        placed({ beat, position }) {
            const first = this.phraseNotes[0]?.beat ?? 0;
            const snapped = Math.round((first + beat) / this.grid.beats) * this.grid.beats;
            this.added({ beat: snapped, pitch: staffPitch(position, { key: this.template.key, clef: clefFor(this.focus) }) });
        },
        /** On a phone: a note after the selected one (or at the start), on its pitch. */
        addAfter() {
            const last = this.note ?? this.notes.at(-1);
            const note = last ? { beat: last.beat + last.length, length: last.length, pitch: last.pitch } : { beat: 0, length: 1, pitch: HOME[this.focus] ?? 60 };
            const next = tidy([...this.notes, note]);
            this.commit([...this.notes, note], { select: [next.findIndex((n) => near(n.beat, note.beat))] });
        },
        nudgePitch(step) {
            if (!this.selected.length) return;
            this.commit(this.notes.map((n, i) => (this.selected.includes(i) ? { ...n, pitch: n.pitch + step } : n)));
        },
        moveBy(beats) {
            if (!this.selected.length) return;
            const moved = this.notes.map((n, i) => (this.selected.includes(i) ? { ...n, beat: Math.max(0, n.beat + beats) } : n));
            if (this.landsOnAnother(moved, this.selected)) return;
            this.commitMoved(moved, this.selected);
        },
        /**
         * A new length. One note made longer than the gap before the next pushes the notes after it
         * along (else it would be cut back to the gap, and nothing would seem to happen), and says so.
         */
        setLength(length) {
            if (!this.selected.length || !length) return;
            if (this.selected.length === 1) {
                const at = this.selected[0];
                const note = this.notes[at];
                const following = this.notes.filter((n, i) => i !== at && n.beat > note.beat + 1e-3);
                const nextBeat = Math.min(...following.map((n) => n.beat));
                const push = note.beat + length - nextBeat;
                if (following.length && push > 1e-3) {
                    this.commit(this.notes.map((n, i) => (i === at ? { ...n, length } : n.beat > note.beat + 1e-3 ? { ...n, beat: n.beat + push } : n)), { select: [at] });
                    this.$nextTick(() => (this.said = `${this.describe(this.note)} ${t('create.edit.pushedAlong')}`));
                    return;
                }
            }
            this.commit(this.notes.map((n, i) => (this.selected.includes(i) ? { ...n, length } : n)));
        },
        stepLength(step) {
            const i = this.lengthIndex(this.note?.length ?? 1);
            const next = this.lengths[Math.max(0, Math.min(this.lengths.length - 1, (i < 0 ? 2 : i) + step))];
            this.setLength(next);
        },
        setLyric(text) {
            const value = text.trim();
            this.commit(this.notes.map((n, i) => {
                if (!this.selected.includes(i)) return n;
                const { lyric, ...rest } = n;
                return value ? { ...rest, lyric: value } : rest;
            }), { sound: false });
        },
        setBr(on) {
            this.commit(this.notes.map((n, i) => {
                if (!this.selected.includes(i)) return n;
                const { br, ...rest } = n;
                return on ? { ...rest, br: true } : rest;
            }), { sound: false });
        },
        split() {
            if (this.note) this.commit(splitNote(this.notes, this.selected[0], this.grid.beats));
        },
        join() {
            if (this.note) this.commit(joinNotes(this.notes, this.selected[0]));
        },
        remove() {
            if (!this.selected.length) return;
            const first = Math.min(...this.selected);
            this.commit(this.notes.filter((_, i) => !this.selected.includes(i)), { select: this.notes.length > this.selected.length ? [Math.min(first, this.notes.length - this.selected.length - 1)] : [], sound: false });
            this.said = t('create.edit.deleted');
        },
        /** The keyboard (docs/template-creator-plan.md, "Keyboard"). */
        key(event) {
            if (this.phone || this.shortcuts) return;
            const mod = event.metaKey || event.ctrlKey;
            if (event.key === '?') this.shortcuts = true;
            else if (mod && event.key.toLowerCase() === 'z') event.shiftKey ? this.redo() : this.undo();
            else if (mod && event.key.toLowerCase() === 'a') this.selected = this.notes.map((_, i) => i);
            else if (event.key === ' ') this.play();
            else if (event.key === 'ArrowUp' || event.key === 'ArrowDown') this.nudgePitch((event.key === 'ArrowUp' ? 1 : -1) * (event.shiftKey ? 12 : 1));
            else if ((event.key === 'ArrowLeft' || event.key === 'ArrowRight') && event.altKey) {
                const at = this.selected[0] ?? -1;
                const next = Math.max(0, Math.min(this.notes.length - 1, at + (event.key === 'ArrowRight' ? 1 : -1)));
                if (this.notes.length) this.pick(next);
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') this.moveBy((event.key === 'ArrowRight' ? 1 : -1) * this.grid.beats);
            else if (/^[1-6]$/.test(event.key)) this.setLength(this.lengths[Number(event.key) - 1]);
            else if (event.key === 'Delete' || event.key === 'Backspace') this.remove();
            else if (event.key === 'Home') this.seek(0);
            else if (!mod && event.key.toLowerCase() === 's' && this.note) this.split();
            else if (!mod && event.key.toLowerCase() === 'j' && this.note) this.join();
            else return;
            event.preventDefault();
        },
        /** Words mode: Space to the next note, "-" joins to the next syllable, "_" holds the vowel, Enter starts a line. */
        wordKey(event) {
            const i = this.selected[0];
            if (i === undefined) return;
            const typed = this.word.trim();
            let lyric = null;
            let br = null;
            if (event.key === ' ') lyric = typed || null;
            else if (event.key === '-') lyric = typed ? `${typed.replace(/-$/, '')}-` : null;
            else if (event.key === '_') lyric = '';
            else if (event.key === 'Enter') {
                lyric = typed || null;
                br = true;
            } else return;
            event.preventDefault();
            const notes = this.notes.map((n, k) => {
                if (k === i) {
                    const { lyric: _, ...rest } = n;
                    return lyric ? { ...rest, lyric } : rest;
                }
                if (k === i + 1 && br) return { ...n, br: true };
                return n;
            });
            const next = Math.min(this.notes.length - 1, i + 1);
            this.commit(notes, { select: [next], sound: false });
            this.$nextTick(() => {
                this.word = this.note?.lyric ?? '';
                if (this.note) this.sound(this.note);
            });
        },
        /** Pasting a line fits its syllables from the cursor on. */
        pasteWords(event) {
            const text = event.clipboardData?.getData('text') ?? '';
            if (!/\s/.test(text.trim())) return; // one syllable: typed as usual
            event.preventDefault();
            const i = this.selected[0] ?? 0;
            const sylls = splitWords(text.replace(/\n/g, ' '));
            this.commit(wordsFrom(this.notes, i, sylls), { select: [Math.min(this.notes.length - 1, i + sylls.length)], sound: false });
            this.said = t('create.edit.pasted', { count: sylls.length });
        },
        /** The playhead moved (dragged, a key, the ruler, Back to the start): Play starts there; playing, it plays on from there. */
        async seek(beat) {
            this.cursor = Math.max(0, beat);
            if (!this.player) return;
            await this.player.stop();
            this.play();
        },
        /** Plays every part heard from the playhead; again stops. */
        async play() {
            if (this.player) {
                await this.player.stop();
                return;
            }
            const from = this.cursor;
            const muted = this.solo ? this.template.parts.map((p) => p.key).filter((k) => k !== this.solo) : this.muted;
            const player = await playTemplate(this.template, { fromBeat: from, muted, focus: this.focus, onFrame: (beat) => (this.playhead = beat) });
            this.player = markRaw(player); // raw, so `this.player === player` below can be true (a reactive copy never is)
            await player.done;
            if (this.player === player) {
                this.player = null;
                this.playhead = null;
            }
        },
        /** Phone: hold to sing the note's pitch; the steady pitch when you let go sets it. */
        async holdStart() {
            this.pressed = true; // let go before the mic opened: nothing to listen for
            try {
                // The first time, the mic check's sheet comes up (the hold ends there): hold again after.
                if (!(await readyToSing()) || !this.pressed) return;
            } catch (error) {
                micError(error); // the mic help says why; holding again sings once it works
                return;
            }
            const readings = [];
            // The voice as a marker gliding on a bar around the note (never a flickering name).
            this.holding = { shown: null, alpha: 1, db: null, readings };
            let display = pitchDisplay();
            let last = performance.now();
            const tick = () => {
                if (!this.holding) return;
                const now = performance.now();
                const dt = Math.min(0.1, (now - last) / 1000);
                last = now;
                const frame = engine.inputFrame();
                const reading = frame ? trackFrame(frame, engine.sampleRate) : null;
                if (reading?.hz > 0) readings.push(hzToMidi(reading.hz));
                this.holding.db = reading?.loudness ?? null;
                display = stepDisplay(display, { midi: reading?.hz > 0 ? hzToMidi(reading.hz) : null, dt, mode: 'hold' });
                this.holding.shown = display.shown;
                this.holding.alpha = display.alpha;
                this.holding.frame = requestAnimationFrame(tick);
            };
            tick();
        },
        holdEnd() {
            this.pressed = false;
            const holding = this.holding;
            if (!holding) return;
            cancelAnimationFrame(holding.frame);
            this.holding = null;
            const steady = holding.readings.slice(Math.floor(holding.readings.length / 3)).sort((a, b) => a - b);
            if (steady.length < 5 || !this.note) return;
            const pitch = Math.round(steady[steady.length >> 1]);
            this.commit(this.notes.map((n, i) => (this.selected.includes(i) ? { ...n, pitch } : n)));
        },
    },
};
