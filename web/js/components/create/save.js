// Name and save (docs/template-creator-plan.md, "Sing it" 6): the title, words by, tune by, how
// hard it is, and who wrote the song (I did; it's traditional or public domain, with an optional
// source; someone else, which keeps it private). Who may open it (private, or anyone with the link)
// and the opt-in to list it in the library when that opens. The checks show as friendly fixes;
// Try it as a learner opens the singing screens on it; Export saves the file. Save is in the frame's
// footer (views/create.js), beside what it still needs; a preview of the song card sits beside the
// form, with that checklist under it.

import { t, tk } from '../../i18n.js';
import { navigate } from '../../router.js';
import { exportFile } from '../../creator.js';
import { canCreateProject, FREE_PROJECT_LIMIT, LimitReached, startFromTemplate } from '../../stores/projects.js';
import { singerOptions, templatePartName } from '../../templates.js';
import { keyLabel } from '../../theme.js';
import { Field, Notice } from '../form.js';
import { Icon } from '../icon.js';
import { toast } from '../ui.js';

const AUTHORSHIP = { mine: tk('create.save.mine'), traditional: tk('create.save.traditional'), other: tk('create.save.other') };
const VISIBILITY = [
    { key: 'private', title: tk('create.save.private'), hint: tk('create.save.privateHint') },
    { key: 'unlisted', title: tk('create.save.unlisted'), hint: tk('create.save.unlistedHint') },
];
const PREVIEW_H = 112; // the preview's height, in its own units
const DIFFICULTY = { beginner: tk('templates.difficulty.beginner'), intermediate: tk('templates.difficulty.intermediate'), advanced: tk('templates.difficulty.advanced') };

/** Hands a file to the person (a download). */
export function downloadFile(blob, name) {
    const url = URL.createObjectURL(blob);
    const link = Object.assign(document.createElement('a'), { href: url, download: name });
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 60000);
}

/** A check's message as a fix to make, in the person's words. */
export function friendlyProblem(problem) {
    if (/^title is missing/.test(problem)) return t('create.fix.title');
    const part = /^part "(\w+)": guide needs notes/.exec(problem);
    if (part) return t('create.fix.emptyPart', { part: templatePartName({ key: part[1], name: part[1] }) });
    if (/starts before the previous note ends/.test(problem)) return t('create.fix.overlap');
    if (/outside the part's range/.test(problem)) return t('create.fix.range');
    return t('create.fix.other', { problem });
}

export const SaveStep = {
    components: { Field, Icon, Notice },
    props: { draft: { type: Object, required: true }, finished: { type: Object, required: true }, problems: { type: Array, required: true }, saving: Boolean },
    emits: ['change', 'save', 'goto'],
    data: () => ({ trying: false }),
    setup: () => ({ AUTHORSHIP, DIFFICULTY, VISIBILITY, PREVIEW_H, friendlyProblem, keyLabel }),
    template: `
        <section class="space-y-6">
            <div>
                <h2 class="text-4xl font-extrabold tracking-tight text-cream">{{ t('create.save.title') }}</h2>
                <p class="mt-2 text-muted">{{ t('create.save.hint') }}</p>
            </div>
            <div class="grid items-start gap-8 md:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
                <form class="space-y-8" @submit.prevent="$emit('save')">
                    <div class="space-y-4">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-muted">{{ t('create.save.about') }}</h3>
                        <Field :label="t('create.save.name')" required v-slot="f">
                            <input :id="f.id" class="input text-lg font-bold" maxlength="120" translate="no" :value="draft.template.title" @input="setTemplate({ title: $event.target.value })" />
                        </Field>
                        <Field :label="t('create.save.description')" v-slot="f">
                            <input :id="f.id" class="input" maxlength="500" translate="no" :value="draft.template.description" @input="setTemplate({ description: $event.target.value })" />
                        </Field>
                        <fieldset>
                            <legend class="label">{{ t('create.save.difficulty') }}</legend>
                            <div class="seg mt-2 flex-wrap">
                                <label v-for="(label, key) in DIFFICULTY" :key="key" class="seg-option tap-44">
                                    <input type="radio" class="sr-only" name="difficulty" :value="key" :checked="draft.template.difficulty === key" @change="setTemplate({ difficulty: key })" />{{ t(label) }}
                                </label>
                            </div>
                        </fieldset>
                    </div>

                    <fieldset class="space-y-3">
                        <legend class="mb-3 text-xs font-bold uppercase tracking-widest text-muted">{{ t('create.save.who') }}</legend>
                        <label v-for="(label, key) in AUTHORSHIP" :key="key" class="radio-card items-center">
                            <input type="radio" name="authorship" class="radio" :value="key" :checked="draft.authorship === key" @change="$emit('change', { authorship: key, ...(key === 'other' ? { visibility: 'private', libraryOptIn: false } : {}) })" />
                            <span class="font-semibold text-cream">{{ t(label) }}</span>
                        </label>
                        <Field v-if="draft.authorship === 'traditional'" :label="t('create.save.source')" :help="t('create.save.sourceHint')" v-slot="f">
                            <input :id="f.id" class="input" maxlength="200" translate="no" :value="draft.source" @input="$emit('change', { source: $event.target.value })" />
                        </Field>
                        <p v-if="draft.authorship === 'other'" class="text-sm text-muted">{{ t('create.save.otherHint') }}</p>
                        <div class="grid gap-4 pt-1 sm:grid-cols-2">
                            <Field :label="t('create.save.wordsBy')" v-slot="f">
                                <input :id="f.id" class="input" maxlength="120" translate="no" :value="draft.template.credits.words" @input="setCredits({ words: $event.target.value })" />
                            </Field>
                            <Field :label="t('create.save.tuneBy')" v-slot="f">
                                <input :id="f.id" class="input" maxlength="120" translate="no" :value="draft.template.credits.tune" @input="setCredits({ tune: $event.target.value })" />
                            </Field>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3" :disabled="draft.authorship === 'other'">
                        <legend class="mb-3 text-xs font-bold uppercase tracking-widest text-muted">{{ t('create.save.visibility') }}</legend>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label v-for="v in VISIBILITY" :key="v.key" class="radio-card has-[:disabled]:cursor-default has-[:disabled]:opacity-45">
                                <input type="radio" name="visibility" class="radio mt-px" :checked="(draft.visibility === 'unlisted') === (v.key === 'unlisted')" @change="$emit('change', { visibility: v.key })" />
                                <span><span class="block font-semibold text-cream">{{ t(v.title) }}</span><span class="block text-sm text-muted">{{ t(v.hint) }}</span></span>
                            </label>
                        </div>
                        <label class="check-row"><input type="checkbox" class="checkbox" :checked="draft.libraryOptIn" @change="$emit('change', { libraryOptIn: $event.target.checked })" /><span class="text-sm text-cream">{{ t('create.save.library') }}</span></label>
                    </fieldset>

                    <Notice v-if="problemsBut('title').length" tone="warn">
                        <p class="font-semibold">{{ t('create.save.fixes') }}</p>
                        <ul class="mt-1 list-disc space-y-1 ps-5">
                            <li v-for="(p, i) in problemsBut('title')" :key="i">{{ friendlyProblem(p) }}</li>
                        </ul>
                        <button type="button" class="btn-ghost btn-sm mt-2" @click="$emit('goto', 'edit')">{{ t('create.save.openEditor') }}</button>
                    </Notice>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="btn-secondary" :disabled="trying || problemsBut('title').length > 0" @click="tryIt"><Icon name="play" :size="18" /> {{ t('create.save.try') }}</button>
                        <button type="button" class="btn-ghost" :disabled="problemsBut('title').length > 0" @click="exportIt"><Icon name="download" :size="18" /> {{ t('create.save.export') }}</button>
                    </div>
                </form>

                <aside class="order-first space-y-4 md:sticky md:top-4 md:order-none" :aria-label="t('create.save.preview')">
                    <h3 class="text-xs font-bold uppercase tracking-widest text-muted">{{ t('create.save.preview') }}</h3>
                    <div class="card overflow-hidden">
                        <svg :viewBox="'0 0 300 ' + PREVIEW_H" class="block h-28 w-full bg-ink/60" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="save-gem-shade" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity="0.38" /><stop offset="0.48" stop-color="#fff" stop-opacity="0" /><stop offset="0.5" stop-color="#000" stop-opacity="0" /><stop offset="1" stop-color="#000" stop-opacity="0.32" /></linearGradient>
                            </defs>
                            <g v-for="(n, i) in previewNotes" :key="i">
                                <rect :x="n.x" :y="n.y" :width="n.w" height="5" rx="2.5" :fill="'var(--color-part-' + n.part + ')'" />
                                <rect :x="n.x" :y="n.y" :width="n.w" height="5" rx="2.5" fill="url(#save-gem-shade)" />
                            </g>
                        </svg>
                        <div class="space-y-3 p-5">
                            <p class="text-xl font-extrabold" :class="draft.template.title.trim() ? 'text-cream' : 'text-muted'" translate="no">{{ draft.template.title.trim() || t('create.untitled') }}</p>
                            <p class="text-sm" :class="draft.template.description.trim() ? 'text-cream' : 'text-muted'" translate="no">{{ draft.template.description.trim() || t('create.save.noLine') }}</p>
                            <p class="text-sm text-muted" translate="no">{{ creditLine || t('create.save.noCredits') }}</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="chip">{{ t(DIFFICULTY[draft.template.difficulty] ?? DIFFICULTY.beginner) }}</span>
                                <span class="chip">{{ t('create.save.partsCount', { count: finished.parts.length }) }}</span>
                                <span class="chip">{{ t('create.frame.summary.tempo', { bpm: draft.template.tempo, signature: draft.template.timeSignature }) }}</span>
                                <span class="chip">{{ keyLabel(draft.template.key) }}</span>
                            </div>
                            <p class="flex items-center gap-2 border-t border-line pt-3 text-sm text-muted"><span class="size-2 rounded-full" :class="draft.visibility === 'unlisted' ? 'bg-accent' : 'bg-muted'" aria-hidden="true"></span>{{ draft.visibility === 'unlisted' ? t('create.save.unlisted') : t('create.save.onlyYou') }}</p>
                        </div>
                    </div>
                    <ul class="space-y-1.5 text-sm" :aria-label="t('create.save.needs')">
                        <li v-for="c in checklist" :key="c.key" class="flex items-center gap-2" :class="c.done ? 'text-cream' : 'text-muted'">
                            <span class="grid size-5 place-items-center rounded-full border" :class="c.done ? 'border-good bg-good text-ink' : 'border-line'" aria-hidden="true"><Icon v-if="c.done" name="check" :size="12" /></span>
                            <span>{{ t(c.label) }}<span class="sr-only">{{ c.done ? t('create.save.done') : t('create.save.notYet') }}</span></span>
                        </li>
                    </ul>
                </aside>
            </div>
        </section>`,
    computed: {
        /** What Save needs, ticked off as it's done. */
        checklist() {
            return [
                { key: 'title', label: tk('create.save.needTitle'), done: !!this.draft.template.title.trim() },
                { key: 'who', label: tk('create.save.needWho'), done: !!this.draft.authorship },
            ];
        },
        creditLine() {
            const { words, tune } = this.draft.template.credits;
            return [words && t('create.save.wordsCredit', { name: words }), tune && t('create.save.tuneCredit', { name: tune })].filter(Boolean).join(' · ');
        },
        /** Every part's notes, small, on one scale: the song at a glance. */
        previewNotes() {
            const parts = this.finished.parts;
            const notes = parts.flatMap((p, i) => (p.guide?.notes ?? []).filter((n) => Number.isFinite(n.pitch)).map((n) => ({ ...n, part: (i % 8) + 1 })));
            if (!notes.length) return [];
            const end = Math.max(...notes.map((n) => n.beat + n.length)) || 1;
            const low = Math.min(...notes.map((n) => n.pitch));
            const high = Math.max(...notes.map((n) => n.pitch));
            const span = Math.max(1, high - low);
            return notes.map((n) => ({ x: (n.beat / end) * 296 + 2, w: Math.max(2, (n.length / end) * 296 - 1.5), y: 6 + ((high - n.pitch) / span) * (PREVIEW_H - 16), part: n.part }));
        },
    },
    methods: {
        setTemplate(fields) {
            this.$emit('change', { template: { ...this.draft.template, ...fields } });
        },
        setCredits(fields) {
            this.setTemplate({ credits: { ...this.draft.template.credits, ...fields } });
        },
        problemsBut(kind) {
            return this.problems.filter((p) => !(kind === 'title' && /^title is missing/.test(p)));
        },
        /** The draft as a project, to sing as a learner would (the draft stays). */
        async tryIt() {
            if (!canCreateProject()) {
                toast(t('projects.limitForTemplate', { limit: FREE_PROJECT_LIMIT }));
                return;
            }
            this.trying = true;
            try {
                const template = JSON.parse(JSON.stringify({ ...this.finished, title: this.finished.title || t('create.untitled') }));
                const project = await startFromTemplate(template, { singers: Math.max(...singerOptions(template)) });
                navigate(`/p/${project.id}/sing`);
            } catch (error) {
                if (!(error instanceof LimitReached)) console.error('Trying the template failed', error);
                toast(error instanceof LimitReached ? t('projects.limit', { limit: FREE_PROJECT_LIMIT }) : t('projects.couldNotStart'), { tone: 'error' });
            } finally {
                this.trying = false;
            }
        },
        exportIt() {
            const { blob, name } = exportFile({ ...this.finished, title: this.finished.title || t('create.untitled') });
            downloadFile(blob, name);
        },
    },
};
