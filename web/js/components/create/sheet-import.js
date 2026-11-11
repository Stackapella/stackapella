// Sheet music in (docs/sheet-music-plan.md): the one sheet after New song's Open a file reads a
// MusicXML or MIDI file. Each line the file holds is a row, named as the file names it, with what
// it sings as (or left out), and, where it has two notes at once, the top or the bottom ones. Then
// where the words come from, what changed, the one-line confirmation of the right to use it, and
// Make the song. The singer decides every voice: nothing is worked out from the notes
// (docs/sheet-music-patent-notes.md).

import { midiName } from '../../dsp.js';
import { formatNumber, t } from '../../i18n.js';
import { MAX_LINES, namedVoice, picksProblem, SING_AS, toTemplate } from '../../sheet-import.js';
import { keyLabel } from '../../theme.js';
import { Modal } from '../ui.js';

export const SheetImport = {
    components: { Modal },
    // rightsRecord: staff importing a director's score (Admin > Support's tracks row) show the director's confirmation
    // instead of asking for one (docs/score-import-plan.md, section 5).
    props: { score: { type: Object, required: true }, busy: Boolean, rightsRecord: { type: Object, default: null } },
    emits: ['make', 'close'],
    data() {
        const lines = this.score.lines;
        return {
            picks: Object.fromEntries(lines.map((l) => [l.id, { as: namedVoice(l) ?? '', take: 'top' }])),
            wordsFrom: lines.find((l) => l.words)?.id ?? '',
            rights: !!this.rightsRecord,
            SING_AS,
        };
    },
    template: `
        <Modal :title="score.title ? t('sheet.titleOf', { title: score.title }) : t('sheet.title')" :title-is-content="!!score.title" @close="$emit('close')">
            <form class="space-y-5" data-sheet-import @submit.prevent="make">
                <p class="text-sm text-muted">{{ summary }}</p>
                <fieldset class="space-y-2">
                    <legend class="mb-2 text-sm font-semibold text-muted">{{ t('sheet.lines', { count: score.lines.length, most: MAX }) }}</legend>
                    <div v-for="line in score.lines" :key="line.id" class="space-y-3 rounded-2xl border border-line p-3.5" data-sheet-line>
                        <div class="flex items-end justify-between gap-3">
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-cream">{{ nameOf(line) }}</span>
                                <span class="block text-sm text-muted" translate="no">{{ midiName(line.range.low) }} {{ t('sheet.to') }} {{ midiName(line.range.high) }} · {{ t('sheet.notes', { count: line.notes.length }) }}</span>
                            </span>
                            <label class="label w-40 shrink-0">{{ t('sheet.singsAs') }}
                                <select v-model="picks[line.id].as" class="input mt-1">
                                    <option value="">{{ t('sheet.leaveOut') }}</option>
                                    <option v-for="voice in SING_AS" :key="voice" :value="voice">{{ voiceName(voice) }}</option>
                                </select>
                            </label>
                        </div>
                        <label v-if="line.chordNotes && picks[line.id].as" class="label">{{ t('sheet.twoAtOnce', { count: line.chordNotes }) }}
                            <select v-model="picks[line.id].take" class="input mt-1">
                                <option value="top">{{ t('sheet.top') }}</option>
                                <option value="bottom">{{ t('sheet.bottom') }}</option>
                            </select>
                        </label>
                    </div>
                </fieldset>
                <label v-if="wordLines.length > 1" class="label">{{ t('sheet.wordsFrom') }}
                    <select v-model="wordsFrom" class="input mt-1"><option v-for="line in wordLines" :key="line.id" :value="line.id">{{ nameOf(line) }}</option></select>
                </label>
                <p v-else-if="!wordLines.length" class="text-sm text-muted">{{ t('sheet.noWords') }}</p>
                <ul v-if="notices.length" class="list-disc space-y-1 ps-5 text-sm text-muted" data-sheet-notices><li v-for="(n, i) in notices" :key="i">{{ n }}</li></ul>
                <p v-if="problemText" class="text-sm text-cream" role="status">{{ problemText }}</p>
                <p v-if="rightsRecord" class="text-sm text-muted" translate="no" data-sheet-rights-record>{{ t('sheet.rightsFrom', { who: rightsRecord.confirmedBy }) }}</p>
                <template v-else>
                    <p class="text-sm text-muted">{{ t('sheet.private') }}</p>
                    <label class="check-row"><input v-model="rights" type="checkbox" class="checkbox" data-sheet-rights /><span>{{ t('sheet.rights') }}</span></label>
                </template>
                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-line pt-4">
                    <button type="button" class="btn-secondary" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" class="primary btn-primary" :disabled="!ready || busy">{{ t('sheet.make') }}</button>
                </div>
            </form>
        </Modal>`,
    computed: {
        MAX: () => MAX_LINES,
        /** "Key of G · 4/4 · 84 a minute": what the song will be. */
        summary() {
            const s = this.score;
            return [t('studio.meta.key', { key: keyLabel(s.key) }), s.timeSignature, t('studio.meta.bpm', { bpm: formatNumber(s.tempo) })].join(' · ');
        },
        wordLines() {
            return this.score.lines.filter((l) => l.words);
        },
        chosen() {
            return this.score.lines.map((l) => ({ id: l.id, ...this.picks[l.id], as: this.picks[l.id].as || null }));
        },
        problem() {
            return picksProblem(this.chosen);
        },
        problemText() {
            // 'none' isn't said: the button waits until a line is picked.
            if (this.problem === 'tooMany') return t('sheet.problem.tooMany', { most: MAX_LINES });
            if (this.problem === 'twice') return t('sheet.problem.twice');
            if (this.problem === 'mixed') return t('sheet.problem.mixed');
            return null;
        },
        ready() {
            return !this.problem && this.rights;
        },
        /** What was changed or left out, said before the song is made. */
        notices() {
            return this.score.notices.map((n) => {
                if (n.kind === 'repeats') return t('sheet.notice.repeats', { count: n.count });
                if (n.kind === 'jumps') return t('sheet.notice.jumps');
                if (n.kind === 'verses') return t('sheet.notice.verses', { count: n.count });
                if (n.kind === 'metreChanges') return t('sheet.notice.metreChanges');
                if (n.kind === 'tempoChanges') return t('sheet.notice.tempoChanges');
                if (n.kind === 'graces') return t('sheet.notice.graces', { count: n.count });
                if (n.kind === 'metreWritten') return t('sheet.notice.metreWritten', { from: n.from, to: n.to });
                return null;
            }).filter(Boolean);
        },
    },
    methods: {
        midiName,
        voiceName(voice) {
            return { soprano: t('parts.soprano'), alto: t('parts.alto'), tenor: t('parts.tenor'), bass: t('parts.bass'), lead: t('parts.lead'), baritone: t('parts.baritone') }[voice];
        },
        /** A row's name as the file has it: its part (and which voice, when the part has two), or a MIDI track. */
        nameOf(line) {
            const name = line.part || (line.track ? t('sheet.track', { number: line.track }) : t('sheet.unnamed'));
            if (line.voicesInPart <= 1) return name;
            return line.track ? t('sheet.lineChannel', { name, number: line.voice }) : t('sheet.lineVoice', { name, number: line.voice });
        },
        make() {
            if (!this.ready) return;
            this.$emit('make', toTemplate(this.score, { picks: this.chosen, wordsFrom: this.wordsFrom || null, title: this.score.title || t('create.home.untitled') }));
        },
    },
};
