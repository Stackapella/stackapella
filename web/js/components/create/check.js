// Check the notes (docs/template-creator-plan.md, "Sing it" 4): the whole tune on the lane, the
// key as a chip you can change, and "3 notes to check" (the ones the transcriber wasn't sure of, and
// octave slips), one at a time: hear it, move it, split or join it, or keep it. Any note can be
// picked and fixed the same way, and any line sung again. The fixes go into the sung lines, so
// singing one line again keeps the others as fixed.

import { midiName } from '../../dsp.js';
import { formatNumber, t } from '../../i18n.js';
import { fixFor, gridFor, joinNotes, splitNote } from '../../creator.js';
import { hearNotes, playTemplate } from '../../creator-audio.js';
import { keyLabel, KEYS, beatsPerBar, PART_COLORS } from '../../theme.js';
import { Icon } from '../icon.js';
import { parseKey } from '../../notes.js';
import { Roll } from './roll.js';
import { refit } from './setup.js';

const COLOR = PART_COLORS[3]; // the tune's colour (painted in the theme showing)

export const CheckStep = {
    components: { Icon, Roll },
    props: { draft: { type: Object, required: true } },
    emits: ['change', 'line', 'resing', 'editor'],
    data: () => ({ selected: null, player: null, playhead: null, said: '', lastFix: null }),
    setup: () => ({ KEYS, keyLabel, midiName, formatNumber, COLOR }),
    template: `
        <section class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-4xl font-extrabold tracking-tight text-cream">{{ t('create.check.title') }}</h2>
                    <p class="mt-2 text-muted">{{ t('create.check.hint') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="flex min-h-11 items-center gap-2 rounded-full border border-line px-4 text-sm text-muted">{{ t('create.check.key') }}
                        <select class="bg-transparent font-semibold text-cream" :value="draft.template.key" @change="setKey($event.target.value)">
                            <option v-for="k in KEYS" :key="k" :value="k">{{ keyLabel(k) }}</option>
                        </select>
                    </label>
                    <button type="button" class="btn-secondary" @click="play"><Icon :name="player ? 'stop' : 'play'" :size="16" /> {{ player ? t('studio.stop') : t('create.check.play') }}</button>
                </div>
            </div>
            <div class="space-y-2">
                <Roll :parts="[{ key: 'tune', color: paint(COLOR), notes: melody }]" :beats-per-bar="perBar" :pickup-beats="draft.template.pickupBeats" :end-beat="end" :zoom="44" :key-of="keyOf" :selected="selected === null ? [] : [selected]" :to-check="toCheck" :playhead="playhead" :label="t('create.check.label', { count: melody.length })" @select="(i) => pick(i)" />
                <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-muted" aria-hidden="true">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-4 rounded-sm" :style="{ background: paint(COLOR) }"></span>{{ t('create.check.legendSung') }}</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-4 rounded-sm border border-dashed border-warn"></span>{{ t('create.check.legendListen') }}</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-4 rounded-sm bg-raised"></span>{{ t('create.check.legendKey') }}</span>
                    <button type="button" class="btn-ghost btn-sm ms-auto" @click="$emit('editor')">{{ t('create.check.editor') }}</button>
                </div>
            </div>

            <div v-if="toCheck.length && selected === null" class="flex flex-wrap items-center gap-4 rounded-3xl border border-warn/40 bg-warn/5 p-5">
                <span class="size-2.5 shrink-0 rounded-full bg-warn" aria-hidden="true"></span>
                <div class="min-w-0 flex-1">
                    <p class="text-lg font-bold text-cream">{{ t('create.check.toCheck', { count: toCheck.length }) }}</p>
                    <p class="text-sm text-muted">{{ t('create.check.toCheckHint') }}</p>
                </div>
                <button v-if="fixes.length" type="button" class="btn-secondary" data-fix-all @click="fixAll">{{ t('create.check.fixAll', { count: fixes.length }) }}</button>
                <button type="button" class="btn-lg rounded-full bg-warn px-6 font-bold text-ink" @click="pick(toCheck[0])">{{ t('create.check.start') }}</button>
            </div>
            <!-- What a fix changed, with Undo. -->
            <div v-if="lastFix" class="card flex flex-wrap items-center gap-3 p-4" data-fixed>
                <p class="min-w-0 flex-1 text-sm text-cream">{{ t('create.check.fixed', { count: lastFix.changes.length }) }} <span class="text-muted" translate="no">{{ lastFix.changes.join(', ') }}</span></p>
                <button type="button" class="btn-secondary btn-sm" data-undo-fix @click="undoFix">{{ t('common.undo') }}</button>
            </div>
            <p v-if="!toCheck.length && selected === null" class="flex items-center gap-2 text-good" role="status"><Icon name="check" :size="18" /> {{ t('create.check.allGood') }}</p>

            <div v-if="selected !== null && note" class="card space-y-4 p-5">
                <div class="flex flex-wrap items-baseline gap-3">
                    <p class="text-2xl font-extrabold text-cream" translate="no">{{ midiName(note.pitch) }}<span v-if="note.lyric" class="ms-2 text-muted">{{ note.lyric }}</span></p>
                    <p v-if="toCheck.includes(selected)" class="text-sm font-semibold text-warn">{{ t('create.check.unsure', { done: checkedCount + 1, total: checkedCount + toCheck.length }) }}</p>
                </div>
                <p v-if="fixHere" class="text-sm text-muted">{{ fixHere.reason === 'octave' ? t('create.check.whyOctave') : t('create.check.whyKey', { key: keyLabel(draft.template.key) }) }}</p>
                <div class="flex flex-wrap gap-2">
                    <button v-if="fixHere" type="button" class="btn-primary btn-sm" data-fix-it @click="fixOne"><Icon name="check" :size="16" /> {{ t('create.check.fixIt', { note: midiName(fixHere.pitch) }) }}</button>
                    <button type="button" class="btn-secondary btn-sm" @click="hear"><Icon name="play" :size="16" /> {{ t('create.check.hear') }}</button>
                    <button type="button" class="btn-secondary btn-sm" :aria-label="t('create.check.upLabel')" @click="nudge(1)"><Icon name="plus" :size="16" /> {{ t('create.check.up') }}</button>
                    <button type="button" class="btn-secondary btn-sm" :aria-label="t('create.check.downLabel')" @click="nudge(-1)"><Icon name="minus" :size="16" /> {{ t('create.check.down') }}</button>
                    <button type="button" class="btn-secondary btn-sm" @click="split">{{ t('create.check.split') }}</button>
                    <button type="button" class="btn-secondary btn-sm" :disabled="!canJoin" @click="join">{{ t('create.check.join') }}</button>
                    <button type="button" class="btn-primary btn-sm ms-auto" @click="keep">{{ toCheck.includes(selected) ? t('create.check.keep') : t('create.check.close') }}</button>
                </div>
            </div>
            <p class="sr-only" aria-live="polite">{{ said }}</p>

            <div class="space-y-3">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="text-xl font-bold text-cream">{{ t('create.check.linesTitle') }}</h3>
                    <p class="text-sm text-muted">{{ t('create.check.linesHint') }}</p>
                </div>
                <ul class="divide-y divide-line overflow-hidden rounded-3xl border border-line">
                    <li v-for="(line, i) in draft.lines" :key="i" class="flex items-center gap-4 px-4 py-3">
                        <span class="w-7 shrink-0 text-sm text-muted tabular-nums">{{ String(i + 1).padStart(2, '0') }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold text-cream" translate="no">{{ line.text }}</span>
                            <span class="block text-xs" :class="checkCounts[i] ? 'text-warn' : 'text-muted'">{{ t('create.check.lineNotes', { count: line.notes?.length ?? 0 }) }}<template v-if="checkCounts[i]"> · {{ t('create.check.lineToCheck', { count: checkCounts[i] }) }}</template></span>
                        </span>
                        <button type="button" class="glass-bubble grid size-11 shrink-0 place-items-center rounded-full text-cream transition" :aria-label="t('create.check.playLine', { number: i + 1 })" :disabled="!line.notes?.length" @click="hearLine(i)"><Icon name="play" :size="16" /></button>
                        <button type="button" class="btn-secondary btn-sm" @click="$emit('resing', i)">{{ t('create.check.resing') }}</button>
                    </li>
                </ul>
            </div>
        </section>`,
    computed: {
        perBar() {
            return beatsPerBar(this.draft.template.timeSignature);
        },
        melody() {
            return (this.draft.template.parts.find((p) => p.melody) ?? this.draft.template.parts[0])?.guide.notes ?? [];
        },
        end() {
            const last = this.melody.at(-1);
            return last ? last.beat + last.length : 8;
        },
        toCheck() {
            const unsure = this.draft.unsure ?? [];
            return this.melody.map((n, i) => (unsure.some((b) => Math.abs(b - n.beat) < 1e-6) ? i : -1)).filter((i) => i >= 0);
        },
        keyOf() {
            return parseKey(this.draft.template.key);
        },
        /** The notes worth a listen that have a safe fix: [{ i, fix }]. */
        fixes() {
            return this.toCheck.map((i) => ({ i, fix: fixFor(this.melody, i, this.keyOf) })).filter((f) => f.fix);
        },
        fixHere() {
            return this.toCheck.includes(this.selected) ? (this.fixes.find((f) => f.i === this.selected)?.fix ?? null) : null;
        },
        /** How many notes to check in each sung line. */
        checkCounts() {
            const counts = this.draft.lines.map(() => 0);
            for (const i of this.toCheck) {
                const at = this.origin[i];
                if (at) counts[at[0]] += 1;
            }
            return counts;
        },
        checkedCount() {
            return this.draft.checked ?? 0;
        },
        note() {
            return this.melody[this.selected] ?? null;
        },
        /** Where each melody note came from: [line, note in it]. */
        origin() {
            const out = [];
            this.draft.lines.forEach((line, i) => (line.notes ?? []).forEach((_, j) => out.push([i, j])));
            return out;
        },
        canJoin() {
            const at = this.origin[this.selected];
            return !!at && at[1] < (this.draft.lines[at[0]].notes?.length ?? 0) - 1;
        },
    },
    unmounted() {
        this.player?.stop();
    },
    methods: {
        pick(i) {
            this.selected = i;
            this.hear();
        },
        /** Plays one sung line's notes, from its first. */
        hearLine(i) {
            const notes = this.draft.lines[i]?.notes;
            if (!notes?.length) return;
            const from = notes[0].beat;
            hearNotes(notes.map((n) => ({ ...n, beat: n.beat - from })), { tempo: this.draft.template.tempo });
        },
        hear() {
            const n = this.note;
            if (n) hearNotes([{ beat: 0, length: Math.min(n.length, 2), pitch: n.pitch }], { tempo: this.draft.template.tempo });
        },
        setKey(key) {
            this.$emit('change', { keyChosen: true, template: { ...this.draft.template, key } });
        },
        /** Changes a sung line's notes (where the selected note came from), keeping its words fitted. */
        editLine(change) {
            this.lastFix = null; // an edit by hand: Undo would bring back the wrong notes
            const at = this.origin[this.selected];
            if (!at) return;
            const [i, j] = at;
            const line = this.draft.lines[i];
            const notes = change([...line.notes], j);
            const fitted = refit({ ...line, notes });
            this.$emit('line', i, { notes: fitted.notes, lyrics: fitted.lyrics, mismatch: fitted.mismatch });
        },
        nudge(step) {
            this.editLine((notes, j) => {
                notes[j] = { ...notes[j], pitch: notes[j].pitch + step };
                return notes;
            });
            this.said = midiName(this.note.pitch + step);
            this.$nextTick(() => this.hear());
        },
        split() {
            this.editLine((notes, j) => splitNote(notes, j, gridFor(this.draft.template.timeSignature) / 2));
            this.said = t('create.check.didSplit');
        },
        join() {
            this.editLine((notes, j) => joinNotes(notes, j));
            this.said = t('create.check.didJoin');
        },
        /** Kept as it is (no longer to check), and on to the next one. */
        keep() {
            if (this.toCheck.includes(this.selected)) {
                this.editLine((notes, j) => {
                    notes[j] = { ...notes[j], sure: true };
                    return notes;
                });
                this.$emit('change', { checked: this.checkedCount + 1 });
                this.onToNext();
            } else this.selected = null;
        },
        onToNext() {
            this.$nextTick(() => {
                const next = this.toCheck.find((i) => i > this.selected) ?? this.toCheck[0];
                this.selected = next ?? null;
                if (next !== undefined) this.hear();
            });
        },
        /**
         * Applies fixes ([{ i, fix }]) to the sung lines they came from, each fixed note then sure, and
         * keeps the lines as they were for Undo, with what changed ("G♯4 → A4 (grace)").
         */
        applyFixes(list) {
            const before = new Map();
            const lines = new Map();
            const changes = [];
            for (const { i, fix } of list) {
                const at = this.origin[i];
                if (!at) continue;
                const [l, j] = at;
                const line = this.draft.lines[l];
                if (!before.has(l)) before.set(l, { notes: line.notes, lyrics: line.lyrics, mismatch: line.mismatch });
                const notes = lines.get(l) ?? [...line.notes];
                notes[j] = { ...notes[j], pitch: fix.pitch, sure: true };
                lines.set(l, notes);
                const was = this.melody[i];
                changes.push(`${midiName(was.pitch)} → ${midiName(fix.pitch)}${was.lyric ? ` (${was.lyric.replace(/-$/, '')})` : ''}`);
            }
            for (const [l, notes] of lines) {
                const fitted = refit({ ...this.draft.lines[l], notes });
                this.$emit('line', l, { notes: fitted.notes, lyrics: fitted.lyrics, mismatch: fitted.mismatch });
            }
            this.$emit('change', { checked: this.checkedCount + changes.length });
            this.lastFix = { changes, before, checked: this.checkedCount };
            this.said = t('create.check.fixed', { count: changes.length });
        },
        fixOne() {
            this.applyFixes([{ i: this.selected, fix: this.fixHere }]);
            this.onToNext();
        },
        fixAll() {
            this.applyFixes(this.fixes);
        },
        /** The lines as they were before the last fix. */
        undoFix() {
            const { before, checked } = this.lastFix;
            for (const [l, line] of before) this.$emit('line', l, line);
            this.$emit('change', { checked });
            this.lastFix = null;
            this.said = t('create.check.undone');
        },
        async play() {
            if (this.player) {
                await this.player.stop();
                return;
            }
            const melodyOnly = { ...this.draft.template, parts: this.draft.template.parts.filter((p) => p.melody) };
            this.player = await playTemplate(melodyOnly, { onFrame: (beat) => (this.playhead = beat) });
            await this.player.done;
            this.player = null;
            this.playhead = null;
        },
    },
};
