// Sheet music in (docs/sheet-music-plan.md): the one sheet after New song's Open a file reads a
// MusicXML or MIDI file. Each line the file holds is a row, named as the file names it, with what
// it sings as (or left out), and, where it has two notes at once, the top or the bottom ones. Then
// where the words come from, what changed, the one-line confirmation of the right to use it, and
// Make the song. The singer decides every voice: nothing is worked out from the notes
// (docs/sheet-music-patent-notes.md).

import { api } from '../../api.js';
import { midiName } from '../../dsp.js';
import { formatNumber, t, tk } from '../../i18n.js';
import { MAX_LINES, namedVoice, picksProblem, SING_AS, toTemplate } from '../../sheet-import.js';
import { keyLabel } from '../../theme.js';
import { Modal } from '../ui.js';

// The two sentences of "Send us your song", word for word (groups.js; docs/choir-tracks-plan.md): the director's rights record.
const WHOSE = { 'public-domain': tk('groups.tracks.publicDomainSentence'), 'own-arrangement': tk('groups.tracks.ownArrangementSentence') };

export const SheetImport = {
    components: { Modal },
    // rightsRecord: staff importing a director's score (Admin > Support's tracks row) show the director's confirmation
    // instead of asking for one (docs/score-import-plan.md, section 5).
    // director: a choir director making tracks for their group (section 4), { groups: [{ id, name }], groupId }: the tune's
    // line, the proposals from templates.check, whose music it is (the two sentences of Send us your song) and the group.
    props: { score: { type: Object, required: true }, busy: Boolean, rightsRecord: { type: Object, default: null }, director: { type: Object, default: null } },
    emits: ['make', 'close'],
    data() {
        const lines = this.score.lines;
        return {
            picks: Object.fromEntries(lines.map((l) => [l.id, { as: namedVoice(l) ?? '', take: 'top' }])),
            wordsFrom: lines.find((l) => l.words)?.id ?? '',
            rights: !!this.rightsRecord,
            tune: '', // '' the usual (soprano or lead, else the first picked), a line's id, or 'none'
            whose: '', // the director's: 'public-domain' or 'own-arrangement'
            groupId: this.director?.groupId ?? this.director?.groups?.[0]?.id ?? '',
            checked: null, // templates.check's answer for the draft as it is now: { for, problems, advice }
            checkFailed: false,
            SING_AS,
            WHOSE,
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
                <label v-if="(director || rightsRecord) && tuneLines.length > 1" class="label" data-sheet-tune>{{ t('sheet.tune') }}
                    <select v-model="tuneChoice" class="input mt-1">
                        <option v-for="line in tuneLines" :key="line.id" :value="line.id">{{ voiceName(picks[line.id].as) }}</option>
                        <option value="none">{{ t('sheet.tuneNone') }}</option>
                    </select>
                </label>
                <ul v-if="notices.length" class="list-disc space-y-1 ps-5 text-sm text-muted" data-sheet-notices><li v-for="(n, i) in notices" :key="i">{{ n }}</li></ul>
                <p v-if="problemText" class="text-sm text-cream" role="status">{{ problemText }}</p>
                <!-- The director's proposals (templates.check): what stops the song, and what singers will meet; nothing is applied. -->
                <section v-if="director && draft" class="space-y-2" data-sheet-proposals aria-live="polite">
                    <h3 class="text-sm font-semibold text-cream">{{ t('sheet.proposals.title') }}</h3>
                    <p v-if="!checkedNow && !checkFailed" class="text-sm text-muted">{{ t('sheet.proposals.checking') }}</p>
                    <p v-else-if="checkFailed" class="text-sm text-muted">{{ t('sheet.proposals.failed') }}</p>
                    <template v-else>
                        <div v-if="checkedNow.problems.length" class="space-y-1" data-sheet-check-problems>
                            <p class="text-sm font-semibold text-warn">{{ t('sheet.proposals.problems') }}</p>
                            <ul class="list-disc space-y-1 ps-5 text-sm text-cream" translate="no"><li v-for="(p, i) in checkedNow.problems" :key="i">{{ p }}</li></ul>
                        </div>
                        <template v-if="checkedNow.advice.length">
                            <ul class="list-disc space-y-1 ps-5 text-sm text-muted" translate="no" data-sheet-advice><li v-for="(a, i) in checkedNow.advice" :key="i">{{ a.text }}</li></ul>
                            <p class="text-xs text-muted">{{ t('sheet.proposals.unchanged') }}</p>
                        </template>
                        <p v-else-if="!checkedNow.problems.length" class="text-sm text-muted" data-sheet-advice-none>{{ t('sheet.proposals.none') }}</p>
                    </template>
                </section>
                <template v-if="director">
                    <fieldset class="space-y-2" data-sheet-whose>
                        <legend class="mb-2 text-sm font-semibold text-cream">{{ t('sheet.director.whose') }}</legend>
                        <label v-for="(w, key) in WHOSE" :key="key" class="check-row items-start"><input v-model="whose" type="radio" name="sheet-whose" :value="key" class="radio mt-0.5" :data-sheet-whose-option="key" /><span class="text-sm">{{ t(w) }}</span></label>
                        <p class="text-xs text-muted">{{ t('sheet.director.inCopyright') }}</p>
                    </fieldset>
                    <label class="label" data-sheet-group>{{ t('sheet.director.for') }}
                        <select v-model="groupId" class="input mt-1" translate="no"><option v-for="g in director.groups" :key="g.id" :value="g.id">{{ g.name }}</option></select>
                    </label>
                    <p class="text-sm text-muted">{{ t('sheet.director.private') }}</p>
                </template>
                <p v-else-if="rightsRecord" class="text-sm text-muted" translate="no" data-sheet-rights-record>{{ t('sheet.rightsFrom', { who: rightsRecord.confirmedBy }) }}</p>
                <template v-else>
                    <p class="text-sm text-muted">{{ t('sheet.private') }}</p>
                    <label class="check-row"><input v-model="rights" type="checkbox" class="checkbox" data-sheet-rights /><span>{{ t('sheet.rights') }}</span></label>
                </template>
                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-line pt-4">
                    <button type="button" class="btn-secondary" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" class="primary btn-primary" :disabled="!ready || busy">{{ director ? t('sheet.director.make') : t('sheet.make') }}</button>
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
            if (this.director) return !this.problem && !!this.whose && !!this.groupId && !!this.checkedNow && !this.checkedNow.problems.length;
            return !this.problem && this.rights;
        },
        /** The picked lines, in score order: any can be the tune. */
        tuneLines() {
            return this.score.lines.filter((l) => this.picks[l.id].as);
        },
        /** The tune's line as shown: the director's choice, else the one the song would take (soprano or lead, else the first). */
        tuneChoice: {
            get() {
                if (this.tune === 'none' || this.tuneLines.some((l) => l.id === this.tune)) return this.tune;
                const order = ['soprano', 'lead', 'alto', 'tenor', 'baritone', 'bass'];
                return [...this.tuneLines].sort((a, b) => order.indexOf(this.picks[a.id].as) - order.indexOf(this.picks[b.id].as)).find((l) => ['soprano', 'lead'].includes(this.picks[l.id].as))?.id ?? this.tuneLines[0]?.id ?? '';
            },
            set(value) {
                this.tune = value;
            },
        },
        /** The song as the picks make it now (null until they make one). */
        draft() {
            if (this.problem) return null;
            return toTemplate(this.score, { picks: this.chosen, wordsFrom: this.wordsFrom || null, melody: this.tune || null, title: this.score.title || t('create.home.untitled'), id: this.draftId });
        },
        draftId: () => crypto.randomUUID(),
        /** templates.check's answer, when it's for the draft as it is now. */
        checkedNow() {
            return this.checked && this.draft && this.checked.for === this.draftKey ? this.checked : null;
        },
        draftKey() {
            return this.draft ? JSON.stringify(this.draft.parts.map((p) => [p.key, p.melody ?? false, p.guide.notes.length])) + this.wordsFrom : '';
        },
        /** What was changed or left out, said before the song is made. */
        notices() {
            return this.score.notices.map((n) => {
                if (n.kind === 'repeats') return t('sheet.notice.repeats', { count: n.count });
                if (n.kind === 'jumpFollowed') return this.jumpText(n);
                if (n.kind === 'versesWritten') return t('sheet.notice.versesWritten', { count: n.count });
                if (n.kind === 'versesLeftOut') return t('sheet.notice.versesLeftOut', { verses: n.verses });
                if (n.kind === 'fermatas') return t('sheet.notice.fermatas', { bars: n.bars, count: n.count });
                if (n.kind === 'tempoWords') return t('sheet.notice.tempoWords', { bars: n.bars, count: n.count });
                if (n.kind === 'dynamics') return t('sheet.notice.dynamics', { bars: n.bars, count: n.count });
                if (n.kind === 'accents') return t('sheet.notice.accents', { bars: n.bars, count: n.count });
                if (n.kind === 'soloTutti') return t('sheet.notice.soloTutti', { bars: n.bars, count: n.count });
                if (n.kind === 'divisi') {
                    const line = this.score.lines.find((l) => l.id === n.line);
                    return line ? t('sheet.notice.divisi', { name: this.nameOf(line), bars: n.bars, count: n.count }) : null;
                }
                if (n.kind === 'verses') return t('sheet.notice.verses', { count: n.count });
                if (n.kind === 'metreChanges') return t('sheet.notice.metreChanges');
                if (n.kind === 'tempoChanges') return t('sheet.notice.tempoChanges');
                if (n.kind === 'graces') return t('sheet.notice.graces', { count: n.count });
                if (n.kind === 'metreWritten') return t('sheet.notice.metreWritten', { from: n.from, to: n.to });
                return null;
            }).filter(Boolean);
        },
    },
    watch: {
        // The director's proposals follow the picks, checked a moment after the last change.
        draftKey: {
            immediate: true,
            handler() {
                clearTimeout(this.checkTimer);
                if (!this.director || !this.draft) return;
                this.checkFailed = false;
                this.checkTimer = setTimeout(() => this.check(), 400);
            },
        },
    },
    beforeUnmount() {
        clearTimeout(this.checkTimer);
    },
    methods: {
        midiName,
        async check() {
            const key = this.draftKey;
            try {
                const { data } = await api.templates.check(JSON.parse(JSON.stringify(this.draft)));
                if (key === this.draftKey) this.checked = { for: key, problems: data.problems ?? [], advice: data.advice ?? [] };
            } catch (error) {
                console.warn('The draft was not checked', error);
                if (key === this.draftKey) this.checkFailed = true;
            }
        },
        /** The D.C. or D.S. followed, and where it ends. */
        jumpText(n) {
            if (n.jump === 'ds') return n.coda ? t('sheet.notice.jumpDsCoda') : n.fine ? t('sheet.notice.jumpDsFine') : t('sheet.notice.jumpDs');
            return n.coda ? t('sheet.notice.jumpDcCoda') : n.fine ? t('sheet.notice.jumpDcFine') : t('sheet.notice.jumpDc');
        },
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
            const template = toTemplate(this.score, { picks: this.chosen, wordsFrom: this.wordsFrom || null, melody: this.tune || null, title: this.score.title || t('create.home.untitled') });
            this.$emit('make', template, this.director ? { rights: this.whose, groupId: this.groupId } : undefined);
        },
    },
};
