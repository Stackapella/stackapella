// The template creator (docs/template-creator-plan.md), at /create and /create/<draft>: one step at
// a time in one frame (Hillel's mockups): the step and its number in the header with Draft saved
// (drafts autosave, and Unfinished on the home brings them back), a labelled progress bar, the step,
// and a footer that stays in view with Back, what Next is waiting for, and Next with the next
// step's name.
// - Sing it: tempo and feel, the words, the melody sung line by line, check the notes, the other
//   parts, name and save.
// - Write it: tempo and feel, the editor, the other parts, name and save.
// - Start from chords (docs/song-builder-plan.md): pick a feel, a key for your voice and a tempo,
//   sing or hum over the backing, then the other parts (voiced on those chords), name and save.
// - Make a copy, edit one of yours, an imported file, or a project's recorded part: the editor, the
//   other parts, name and save.
// The steps are in js/components/create/; the plain parts in js/creator.js.

import { t, tk } from '../i18n.js';
import { goBack, navigate } from '../router.js';
import { harmonize } from '../arrange.js';
import { copyOf, finishParts, LICENCE, melodyFromLines, newTemplate, partOf, problemsOf, sectionFrom, MELODY_KEY } from '../creator.js';
import { leaveCreator } from '../creator-audio.js';
import { findKey } from '../transcribe.js';
import { parseKey } from '../notes.js';
import { loadTemplate } from '../templates.js';
import { discardDraft, loadDraft, loadMine, mine, ownTemplate, saveDraft, saveTemplate } from '../stores/templates.js';
import { adoptTemplate } from '../stores/projects.js';
import { withInstruments } from '../instrument-parts.js';
import { account } from '../stores/account.js';
import { session as tabStorage } from '../storage.js';
import { TempoStep, WordsStep } from '../components/create/setup.js';
import { SingStep } from '../components/create/sing-line.js';
import { CheckStep } from '../components/create/check.js';
import { PartsStep } from '../components/create/parts.js';
import { SaveStep } from '../components/create/save.js';
import { EditorStep } from '../components/create/editor.js';
import { ChordsKeyStep, ChordsStep, chosenLetters, HumStep } from '../components/create/chords.js';
import { HumTuneStep } from '../components/create/hum-tune.js';
import { chordsFor } from '../theory.js';
import { Icon } from '../components/icon.js';
import { toast } from '../components/ui.js';
import { confirmAction } from '../components/confirm.js';
import { count } from '../usage.js';

const STEP_NAMES = {
    tempo: tk('create.step.tempo'),
    chords: tk('create.step.chords'),
    key: tk('create.step.key'),
    hum: tk('create.step.hum'),
    humtune: tk('create.step.humTune'),
    words: tk('create.step.words'),
    sing: tk('create.step.sing'),
    check: tk('create.step.check'),
    edit: tk('create.step.edit'),
    parts: tk('create.step.parts'),
    save: tk('create.step.save'),
};

/** The steps for a draft: Sing it, Write it, or starting from notes already written. */
export function stepsFor(draft) {
    if (draft.mode === 'sing') return ['tempo', 'words', 'sing', 'check', 'parts', ...(draft.useEditor ? ['edit'] : []), 'save'];
    if (draft.mode === 'write') return ['tempo', 'edit', 'parts', 'save'];
    // Hum a tune (docs/hum-a-tune-plan.md): hum it in your own time, then the notes to check in the editor, the parts, save.
    if (draft.mode === 'hum') return ['humtune', 'edit', 'parts', 'save'];
    if (draft.mode === 'chords') return ['chords', 'key', 'hum', 'parts', ...(draft.useEditor ? ['edit'] : []), 'save'];
    // Harmonise this: the take's notes to check, then the parts, which go into the song itself.
    if (draft.mode === 'harmonise') return ['edit', 'parts'];
    return ['edit', 'parts', 'save'];
}

/** A new draft. `template` when starting from notes (a copy, one of yours, a file, a project). */
export function newDraft(mode, template = null, extra = {}) {
    const start = template ?? newTemplate();
    return {
        id: crypto.randomUUID(),
        mode,
        step: stepsFor({ mode })[0],
        template: start,
        lines: [], // Sing it: [{ text, syllables, notes (local beats), lyrics, problems }]
        noWords: false,
        phraseCount: 4,
        octave: null, // Sing it: semitones the tune is moved (a lower voice singing it an octave down: 12)
        keyChosen: false,
        keyMidi: null, // Start from chords: the chosen key's tonic as MIDI (Lower and Higher step from it)
        harmony: template && template.parts.length > 1 ? 'keep' : null, // keep, suggest, write or none
        style: 'simple',
        choices: {},
        useEditor: false,
        authorship: null, // mine, traditional, other
        source: '',
        visibility: 'private',
        libraryOptIn: false,
        editing: null, // the id of one of yours being changed
        progression: null, // Start from chords: { id, name, bars, mode, swing }
        loops: 2, // Start from chords: times round the backing while singing
        backing: { style: 'gentle', drums: true, voices: false },
        instruments: [], // the instrument parts to add under suggested parts (js/instrument-parts.js)
        ...extra,
    };
}

export const CreateView = {
    components: { Icon, TempoStep, WordsStep, SingStep, CheckStep, PartsStep, SaveStep, EditorStep, ChordsStep, ChordsKeyStep, HumStep, HumTuneStep },
    props: { draftId: { type: String, default: null } },
    data: () => ({ draft: null, savedAt: null, failed: null, saving: false, unclear: null }),
    setup: () => ({ STEP_NAMES, goBack, account }),
    template: `
        <div class="mx-auto flex min-h-dvh w-full flex-col px-4 pt-[max(1rem,env(safe-area-inset-top))] md:px-8" :class="wide ? 'max-w-6xl' : 'max-w-4xl'">
            <header class="flex items-center gap-3 pb-4">
                <button type="button" class="glass-bubble grid size-11 shrink-0 place-items-center rounded-full text-cream transition" :aria-label="t('create.leave')" @click="leave"><Icon name="back" :size="20" /></button>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm text-muted">{{ draft ? t('create.frame.stepOf', { step: stepIndex + 1, total: steps.length }) : t('create.title') }}</p>
                    <h1 class="truncate text-xl font-bold tracking-tight text-cream">{{ draft ? t(STEP_NAMES[draft.step]) : t('create.title') }}</h1>
                </div>
                <p v-if="savedAt" class="flex shrink-0 items-center gap-2 text-sm text-muted" role="status"><span class="size-2 rounded-full bg-accent" aria-hidden="true"></span>{{ t('create.draftSaved') }}</p>
            </header>
            <p v-if="failed" class="card p-6 text-cream" role="alert">{{ failed }}</p>
            <section v-else-if="unclear" class="card space-y-4 p-6" role="status">
                <h2 class="text-xl font-bold text-cream">{{ t('harmonise.unclear.title') }}</h2>
                <p class="text-muted">{{ t('harmonise.unclear.body') }}</p>
                <div class="flex flex-wrap gap-3">
                    <button type="button" class="btn-primary" @click="singAgain"><Icon name="mic" :size="18" /> {{ t('harmonise.unclear.again') }}</button>
                    <button type="button" class="btn-secondary" @click="writeInstead">{{ t('harmonise.unclear.tap') }}</button>
                </div>
            </section>
            <p v-else-if="!draft" class="text-muted" role="status">{{ t('create.loading') }}</p>
            <template v-else>
                <ol class="mb-8 flex gap-1.5" :aria-label="t('create.progress', { step: stepIndex + 1, total: steps.length })">
                    <li v-for="(s, i) in steps" :key="s" class="min-w-0 flex-1">
                        <span class="block h-1 rounded-full" :class="i <= stepIndex ? 'bg-accent' : 'bg-line'" aria-hidden="true"></span>
                        <span class="mt-1.5 line-clamp-2 block text-[11px] leading-tight break-words sm:text-xs" :class="i === stepIndex ? 'font-semibold text-cream' : 'text-muted'" :aria-current="i === stepIndex ? 'step' : null">{{ t(STEP_NAMES[s]) }}</span>
                    </li>
                </ol>
                <div class="flex-1">
                    <TempoStep v-if="draft.step === 'tempo'" :draft="draft" @change="change" />
                    <ChordsStep v-else-if="draft.step === 'chords'" :draft="draft" @change="change" />
                    <ChordsKeyStep v-else-if="draft.step === 'key'" :draft="draft" @change="change" />
                    <HumStep v-else-if="draft.step === 'hum'" :draft="draft" @change="change" @melody="setHummed" />
                    <HumTuneStep v-else-if="draft.step === 'humtune'" :draft="draft" @change="change" @tune="setHumTune" />
                    <WordsStep v-else-if="draft.step === 'words'" :draft="draft" @change="change" />
                    <SingStep v-else-if="draft.step === 'sing'" :draft="draft" @change="change" @line="setLine" @tempo="draft.step = 'tempo'" />
                    <CheckStep v-else-if="draft.step === 'check'" :draft="draft" @change="change" @line="setLine" @resing="resing" @editor="openEditor" />
                    <PartsStep v-else-if="draft.step === 'parts'" :draft="draft" @change="change" @arrange="arrange" @write="writeParts" />
                    <EditorStep v-else-if="draft.step === 'edit'" :draft="draft" @change="change" @template="setTemplate" />
                    <SaveStep v-else-if="draft.step === 'save'" :draft="draft" :finished="finished" :problems="problems" :saving="saving" @change="change" @save="save" @goto="goto" />
                </div>
                <nav class="sticky bottom-0 z-20 -mx-4 mt-10 border-t border-line bg-ink/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur md:-mx-8 md:px-8" :aria-label="t('create.frame.nav')">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <button v-if="stepIndex > 0" type="button" class="btn-secondary" :aria-label="t('create.frame.backTo', { step: t(STEP_NAMES[steps[stepIndex - 1]]) })" @click="goto(steps[stepIndex - 1])"><Icon name="back" :size="18" /> {{ t(STEP_NAMES[steps[stepIndex - 1]]) }}</button>
                        <p v-if="footerNote" class="order-first basis-full text-sm text-muted sm:order-none sm:ms-auto sm:basis-auto sm:text-end" :role="canGoOn ? null : 'status'"><b v-if="footerHead" class="block font-semibold text-cream">{{ footerHead }}</b>{{ footerNote }}</p>
                        <button v-if="stepIndex < steps.length - 1" type="button" class="btn-primary ms-auto sm:ms-0" :class="footerNote ? '' : 'sm:ms-auto'" :disabled="!canGoOn" @click="goto(steps[stepIndex + 1])">{{ t('create.frame.next', { step: t(STEP_NAMES[steps[stepIndex + 1]]) }) }} <Icon name="arrow" :size="18" /></button>
                        <button v-else-if="draft.mode === 'harmonise'" type="button" class="btn-primary ms-auto sm:ms-0" :class="footerNote ? '' : 'sm:ms-auto'" :disabled="saving || !canGoOn" @click="addToSong"><Icon name="plus" :size="18" /> {{ t('harmonise.add') }}</button>
                        <button v-else-if="draft.step === 'save'" type="button" class="btn-primary ms-auto sm:ms-0" :class="footerNote ? '' : 'sm:ms-auto'" :disabled="saving || !!problems.length || !draft.authorship" @click="save"><Icon name="check" :size="18" /> {{ saving ? t('create.save.saving') : t('create.save.save') }}</button>
                    </div>
                </nav>
            </template>
        </div>`,
    computed: {
        steps() {
            return stepsFor(this.draft);
        },
        stepIndex() {
            return Math.max(0, this.steps.indexOf(this.draft.step));
        },
        wide() {
            return this.draft && ['edit', 'check', 'key'].includes(this.draft.step);
        },
        /** The template as it would be saved: its parts in order with their versions, and the credits. */
        finished() {
            const d = this.draft;
            // The instruments chosen play under suggested parts (they need its chords).
            const done = withInstruments(finishParts(d.template), d.harmony === 'suggest' ? d.instruments ?? [] : []);
            const words = LICENCE[d.authorship] ?? done.licence.words;
            const { source: _, ...licence } = done.licence;
            return {
                ...done,
                title: done.title.trim(),
                credits: { ...done.credits, arrangement: done.credits.arrangement || account.me?.name || '' },
                licence: { ...licence, words, tune: words, ...(d.authorship === 'traditional' && d.source.trim() ? { source: d.source.trim() } : {}) },
            };
        },
        problems() {
            return problemsOf(this.finished);
        },
        /** What the step needs before Next. */
        canGoOn() {
            const d = this.draft;
            if (d.step === 'words') return d.noWords ? d.phraseCount > 0 : d.lines.some((l) => l.syllables.length);
            if (d.step === 'sing') return d.lines.some((l) => l.notes?.length);
            if (d.step === 'edit' || d.step === 'check') return d.template.parts.some((p) => p.guide.notes.length);
            if (d.step === 'parts') return !!d.harmony;
            if (d.step === 'chords') return !!d.progression;
            if (d.step === 'key') return !!d.keyChosen;
            if (d.step === 'hum' || d.step === 'humtune') return d.template.parts.some((p) => p.melody && p.guide.notes.length);
            return true;
        },
        /** Under Next: what it's waiting for, or a short summary of the step once it's ready. */
        footerNote() {
            if (!this.canGoOn) return this.waitingFor;
            const d = this.draft;
            if (d.step === 'tempo') return t('create.frame.summary.tempo', { bpm: d.template.tempo, signature: d.template.timeSignature });
            if (d.step === 'words') return t('create.frame.summary.words', { count: d.lines.length });
            if (d.step === 'sing') return t('create.frame.summary.sing', { done: d.lines.filter((l) => l.notes?.length).length, total: d.lines.length });
            if (d.step === 'save' && (!d.template.title.trim() || !d.authorship)) return t('create.frame.saveNeeds');
            if (d.step === 'chords') return chosenLetters(d);
            return '';
        },
        /** Above the footer's note on the chords step: what's chosen (its chords under it), or that nothing is yet. */
        footerHead() {
            if (this.draft.step !== 'chords') return '';
            return this.draft.progression ? t(this.draft.progression.name) : t('create.chords.noneYet');
        },
        waitingFor() {
            const step = this.draft.step;
            if (step === 'words') return t('create.need.words');
            if (step === 'sing') return t('create.need.sing');
            if (step === 'parts') return t('create.need.parts');
            if (step === 'edit' || step === 'check' || step === 'hum') return t('create.need.notes');
            if (step === 'chords') return t('create.chords.noneHint');
            if (step === 'key') return t('create.need.key');
            return '';
        },
    },
    watch: {
        draftId: {
            handler() {
                this.open();
            },
            immediate: true,
        },
    },
    unmounted() {
        clearTimeout(this.autosave);
        if (this.draft && this.dirty) saveDraft(this.plain()).catch((error) => console.warn('The draft was not saved on leaving', error));
        leaveCreator();
    },
    methods: {
        plain() {
            return JSON.parse(JSON.stringify(this.draft));
        },
        /** Opens the draft in the address, or starts one from the query (then the address names it). */
        async open() {
            this.failed = null;
            try {
                await loadMine();
                if (this.draftId) {
                    const draft = await loadDraft(this.draftId);
                    if (!draft) {
                        this.failed = t('create.draftGone');
                        return;
                    }
                    this.draft = draft;
                    return;
                }
                const query = new URLSearchParams(location.search);
                let draft;
                if (query.get('copy')) {
                    const song = await loadTemplate(query.get('copy'));
                    draft = newDraft('copy', copyOf(song, t('create.copyTitle', { title: song.title })));
                } else if (query.get('edit')) {
                    const own = await ownTemplate(query.get('edit'));
                    if (!own) throw new Error('not_yours');
                    const record = mine.list.find((r) => r.id === own.id);
                    draft = newDraft('edit', structuredClone(own), { editing: own.id, visibility: record?.visibility ?? 'private', libraryOptIn: !!record?.libraryOptIn, authorship: own.licence?.words === LICENCE.other ? 'other' : own.licence?.words === LICENCE.traditional ? 'traditional' : 'mine', source: own.licence?.source ?? '' });
                } else if (query.get('draft') === 'import') {
                    const imported = JSON.parse(tabStorage.get('stackapella.import') || 'null');
                    tabStorage.remove('stackapella.import');
                    if (!imported) throw new Error('no_import');
                    draft = newDraft('import', imported);
                } else if (query.get('from') && query.get('harmonise')) {
                    const { harmoniseFrom } = await import('../components/create/from-project.js');
                    const { template, clear } = await harmoniseFrom(query.get('from'), query.get('part'));
                    if (!clear) {
                        this.unclear = query.get('from');
                        return;
                    }
                    draft = newDraft('harmonise', template, { step: 'parts', harmony: 'suggest', harmonise: { projectId: query.get('from'), partId: query.get('part') } });
                } else if (query.get('from')) {
                    const { templateFromProject } = await import('../components/create/from-project.js');
                    draft = newDraft('project', await templateFromProject(query.get('from'), query.get('part')));
                } else {
                    draft = newDraft(['write', 'chords', 'hum'].includes(query.get('mode')) ? query.get('mode') : 'sing');
                    draft.template.sections = [];
                }
                this.draft = draft;
                if (draft.mode === 'harmonise') this.arrange({ style: draft.style, choices: {} });
                await saveDraft(this.plain());
                navigate(`/create/${draft.id}`, { replace: true });
            } catch (error) {
                console.warn('The creator could not start', error);
                this.failed = error?.code === 'offline' ? t('common.offline') : t('create.couldNotStart');
            }
        },
        /** A step changed the draft: kept a moment later (several changes go together). */
        change(fields = {}) {
            Object.assign(this.draft, fields);
            this.dirty = true;
            clearTimeout(this.autosave);
            this.autosave = setTimeout(async () => {
                this.dirty = false;
                this.savedAt = await saveDraft(this.plain());
            }, 600);
        },
        goto(step) {
            const d = this.draft;
            // Entering the parts step after the tune changed: the suggestion follows it.
            if (step === 'parts' && d.harmony === 'suggest') this.arrange({ style: d.style, choices: d.choices });
            // Naming it: the first line of the words is a good start.
            if (step === 'save' && !d.template.title && !d.noWords && d.lines[0]?.text) d.template.title = d.lines[0].text.replace(/[\s,.;:!?]+$/, '');
            this.change({ step });
            window.scrollTo(0, 0);
        },
        /** A line was sung: its notes and words go into the melody. */
        setLine(index, line) {
            const lines = [...this.draft.lines];
            lines[index] = { ...lines[index], ...line };
            this.change({ lines });
            this.rebuildMelody();
        },
        /** The melody from the sung lines: from beat 0, its pickup, its phrases and (unless chosen) its key. */
        rebuildMelody() {
            const d = this.draft;
            const { notes, pickupBeats, phrases, unsure } = melodyFromLines(d.lines, d.template.timeSignature);
            if (!notes.length) return;
            this.change({ unsure });
            this.setMelody(notes, { pickupBeats, phrases });
        },
        setMelody(notes, { pickupBeats = this.draft.template.pickupBeats, phrases = null } = {}) {
            const d = this.draft;
            notes = notes.filter((n) => Number.isInteger(n.pitch)); // a note always has a pitch (never NaN)
            if (!notes.length) return;
            const others = d.template.parts.filter((p) => !p.melody && p.key !== MELODY_KEY);
            const melody = partOf(MELODY_KEY, 'Soprano', notes, { melody: true }); // i18n-ignore: the file's English name
            const end = Math.max(...[melody, ...others].flatMap((p) => p.guide.notes.map((n) => n.beat + n.length)));
            const template = {
                ...d.template,
                pickupBeats,
                key: d.keyChosen ? d.template.key : findKey(notes).name,
                parts: [melody, ...others],
                sections: sectionFrom(phrases ?? linePhrases(notes), t('create.sectionName'), end),
            };
            this.change({ template });
        },
        /**
         * Start from chords: the tune sung over the backing, `loops` times round. The tune is the
         * melody, each time round a phrase, and the chords are the progression's, repeated under it.
         */
        /** Hum a tune: the tempo, the bars, the key and the notes found in the hum; its lines are the song's phrases. */
        setHumTune({ melody, octave }) {
            const d = this.draft;
            d.octave = octave;
            this.change({ keyChosen: true, template: { ...d.template, tempo: melody.tempo, timeSignature: melody.timeSignature, key: melody.key } });
            this.setMelody(melody.notes, { pickupBeats: melody.pickupBeats, phrases: melody.phrases.map((p) => [p.startBeat, p.endBeat]) });
        },
        setHummed({ notes, loops, loopBeats }) {
            const d = this.draft;
            const { tonic } = parseKey(d.template.key);
            // The progression's chords under each time round, up to where the tune ends (the account
            // refuses a chord past the song's end, and it would sound nothing anyway).
            const end = Math.max(...notes.filter((n) => Number.isInteger(n.pitch)).map((n) => n.beat + n.length));
            const chords = Array.from({ length: loops }, (_, i) => chordsFor(d.progression.bars, { tonic, perBar: 4, startBeat: i * loopBeats }))
                .flat()
                .filter((c, i) => i === 0 || c.beat < end - 1e-6)
                .map(({ beat, root, quality }) => ({ beat, root, quality, bass: null }));
            const phrases = Array.from({ length: loops }, (_, i) => [i * loopBeats, (i + 1) * loopBeats]);
            this.change({ keyChosen: true });
            this.setMelody(notes, { pickupBeats: 0, phrases });
            this.change({ template: { ...this.draft.template, chords }, harmony: null });
        },
        /** The editor changed the notes. */
        setTemplate(template) {
            this.change({ template, edited: true });
        },
        /** Sing a line again: changes made in the editor since go (the tune comes from the sung lines). */
        async resing(index) {
            if (this.draft.edited && !(await confirmAction({ title: t('create.resing.title'), body: t('create.resing.body'), action: t('create.resing.action') }))) return;
            this.change({ step: 'sing', current: index, edited: false });
        },
        openEditor() {
            this.change({ useEditor: true, step: 'edit' });
        },
        /** The other parts suggested for the tune, in a style; `choices` the chords tried. */
        arrange({ style, choices = {} }) {
            const d = this.draft;
            const melody = d.template.parts.find((p) => p.melody) ?? d.template.parts[0];
            if (!melody?.guide.notes.length) return;
            const { tonic, minor } = parseKey(d.template.key);
            const bare = { ...d.template, parts: [melody] };
            // Start from chords: the parts are voiced on the chords chosen (the backing sung over).
            const chords = d.mode === 'chords' ? d.template.chords : null;
            const { template } = harmonize(bare, { style, choices, key: { tonic, mode: minor ? 'minor' : 'major' }, chords });
            this.change({ template, harmony: 'suggest', style, choices });
        },
        /** Write the other parts: empty alto, tenor and bass in the editor. */
        writeParts() {
            const d = this.draft;
            const melody = d.template.parts.find((p) => p.melody) ?? d.template.parts[0];
            const names = { alto: 'Alto', tenor: 'Tenor', bass: 'Bass' }; // i18n-ignore: the file's English names
            const parts = [melody, ...Object.entries(names).filter(([key]) => key !== melody.key).map(([key, name]) => ({ key, name, range: { ...melody.range }, guide: { type: 'notes', notes: [] } }))];
            this.change({ template: { ...d.template, parts }, harmony: 'write', useEditor: true, step: 'edit' });
        },
        /** Saves under Your templates (and to the account), and the draft goes. */
        /** Harmonise this: the parts go into the song the take is in, and the studio opens on it. */
        async addToSong() {
            this.saving = true;
            try {
                const { projectId, partId } = this.draft.harmonise;
                await adoptTemplate(projectId, JSON.parse(JSON.stringify(this.finished)), partId); // plain: the draft's parts are reactive
                await discardDraft(this.draft.id);
                count('harmonise_used');
                this.draft = null;
                navigate(`/p/${projectId}`, { replace: true });
            } catch (error) {
                console.warn('The suggested parts were not added', error);
                this.failed = t('harmonise.failed');
            } finally {
                this.saving = false;
            }
        },
        /** A take too unclear to harmonise: back to the song to sing it again. */
        singAgain() {
            navigate(`/p/${this.unclear}`, { replace: true });
        },
        /** Or tap the notes in, in the creator's editor. */
        writeInstead() {
            this.unclear = null;
            navigate('/create?mode=write', { replace: true });
            this.open(); // the same page, so it's started here
        },
        async save() {
            if (this.problems.length || this.saving) return;
            this.saving = true;
            try {
                const d = this.draft;
                const record = await saveTemplate(this.finished, { visibility: d.authorship === 'other' ? 'private' : d.visibility, libraryOptIn: d.authorship === 'other' ? false : d.libraryOptIn });
                clearTimeout(this.autosave);
                this.dirty = false;
                await discardDraft(d.id);
                if (d.mode === 'hum') count('hum_saved'); // a hummed tune kept as a song
                toast(record.state === 'limit' || record.state === 'invalid' ? t('create.savedHereOnly') : t('create.saved'));
                navigate(`/t/${record.id}`, { replace: true });
            } catch (error) {
                console.error('The template was not saved', error);
                toast(t('create.notSaved'), { tone: 'error' });
            } finally {
                this.saving = false;
            }
        },
        /** Leaving keeps the draft (Unfinished, on the home). */
        async leave() {
            clearTimeout(this.autosave);
            if (this.draft) {
                this.dirty = false;
                await saveDraft(this.plain());
                toast(t('create.keptDraft'));
            }
            goBack('/');
        },
    },
};


/** Phrases from a melody's lines (a note marked `br` starts one). */
function linePhrases(notes) {
    const out = [];
    for (const n of notes) {
        if (n.br || !out.length) out.push([n.beat, n.beat + n.length]);
        else out.at(-1)[1] = n.beat + n.length;
    }
    return out;
}
