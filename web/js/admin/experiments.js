// Growth's Experiments (docs/experiments-plan.md, sections 1 and 3; contract: admin.experiments.list,
// .create and .update, and admin.funnel.show?experiment=<id> for the results): the registry, one
// experiment at a time on Funnel's layout. Its card says what it tests and how it's read; a verdict
// in plain words shows only once its planned sample and read date are reached (a fixed horizon:
// looking early is fine, acting early isn't, so the page withholds it); and its steps per arm are the
// Funnel's own. Starting, stopping, deciding and closing go through the change sheet, in sudo. A new
// experiment starts as a draft, with a calculator for the sample it's read at.

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { Field, Notice } from '../components/form.js';
import { toast } from '../components/ui.js';
import { ChangeSheet, errorsFrom, LoadFailed, refused } from './common.js';
import { FunnelSection, STEPS } from './funnel.js';

const STATUS = { draft: tk('admin.experiments.status.draft'), running: tk('admin.experiments.status.running'), reading: tk('admin.experiments.status.reading'), decided: tk('admin.experiments.status.decided'), closed: tk('admin.experiments.status.closed') };
// The next step from each status (contract: admin.experiments.update's `action`).
const NEXT = { draft: 'start', running: 'stop', reading: 'decide', decided: 'close' };
const ACTIONS = { start: tk('admin.experiments.action.start'), stop: tk('admin.experiments.action.stop'), decide: tk('admin.experiments.action.decide'), close: tk('admin.experiments.action.close') };
const ACTION_WHAT = { start: tk('admin.experiments.actionWhat.start'), stop: tk('admin.experiments.actionWhat.stop'), decide: tk('admin.experiments.actionWhat.decide'), close: tk('admin.experiments.actionWhat.close') };
const VERDICTS = { draft: tk('admin.experiments.verdict.draft'), running: tk('admin.experiments.verdict.running'), better: tk('admin.experiments.verdict.better'), worse: tk('admin.experiments.verdict.worse'), none: tk('admin.experiments.verdict.none') };
// A step refused because the experiment moved on meanwhile (another tab, another admin).
const REFUSED = { experiment_started: tk('admin.experiments.refused.started'), experiment_not_draft: tk('admin.experiments.refused.notDraft'), experiment_not_running: tk('admin.experiments.refused.notRunning'), experiment_not_reading: tk('admin.experiments.refused.notReading'), experiment_running: tk('admin.experiments.refused.running') };
const SKINS = { serious: tk('admin.experiments.audience.serious'), game: tk('admin.experiments.audience.game') };
const SIZES = { phone: tk('admin.experiments.audience.phone'), desktop: tk('admin.experiments.audience.desktop') };
const SAID = { created: tk('admin.experiments.created'), saved: tk('admin.experiments.saved'), done: tk('admin.experiments.done') };
const AUDIENCE = { new: tk('admin.experiments.audience.new'), firstWeek: tk('admin.experiments.audience.firstWeek'), signedIn: tk('admin.experiments.audience.signedIn') };
// The steps an experiment may be read by or guarded with: the funnel's own (each counted once a device).
const METRICS = ['f_lesson1', 'f_lesson2', 'f_lesson3', 'f_unit1', 'f_note', 'f_take', 'f_harmony', 'f_account', 'f_install', 'f_day2', 'f_week1', 'f_day7', 'f_plus'];
const MOST_GUARDRAILS = 3;
const Z = 1.96 + 0.84; // a 95% two-sided test at 80% power
const EMPTY = () => ({ id: '', what: '', hypothesis: '', metric: 'f_lesson3', guardrails: [], audience: { new: true, firstWeek: false }, excludes: [], plannedPerArm: 1500, base: 30, lift: 5 });

const day = (date) => (date ? formatDate(`${date}T12:00:00Z`, { month: 'short', day: 'numeric' }) : '');

/** Devices per arm to see `lift` points on a `base`% step, at 95% and 80% power (two proportions). */
export function sampleFor(base, lift) {
    const p1 = base / 100;
    const p2 = (base + lift) / 100;
    if (!(p1 > 0 && p1 < 1) || !(p2 > p1 && p2 < 1)) return null; // a change past 100% can't be found
    return Math.ceil((Z * Z * (p1 * (1 - p1) + p2 * (1 - p2))) / (p2 - p1) ** 2 / 50) * 50;
}

/**
 * The verdict, or why there isn't one yet (the fixed horizon): `report` is admin.funnel.show's for the
 * experiment. A guardrail where B is worse beyond chance outranks a better metric.
 */
export function verdictOf(experiment, report, today = new Date().toISOString().slice(0, 10)) {
    if (experiment.status === 'draft') return { key: 'draft' };
    if (experiment.status === 'running') return { key: 'running' };
    if (!report?.enough || !experiment.readFrom || today < experiment.readFrom) return { key: 'notYet' };
    const step = (kind) => report.steps.find((s) => s.kind === kind);
    const worse = (experiment.guardrails ?? []).find((kind) => step(kind)?.difference && step(kind).difference.high < 0);
    if (worse) return { key: 'guardrail', step: worse };
    const difference = step(experiment.metric)?.difference;
    if (difference && difference.low > 0) return { key: 'better' };
    if (difference && difference.high < 0) return { key: 'worse' };
    return { key: 'none' };
}

export const ExperimentsSection = {
    components: { ChangeSheet, Field, FunnelSection, LoadFailed, Notice },
    props: { me: { type: Object, required: true } },
    data: () => ({ list: null, failed: false, chosenId: null, report: null, acting: null, winner: 'b', busy: false, errors: null, form: null }),
    setup: () => ({ STATUS, ACTIONS, ACTION_WHAT, AUDIENCE, METRICS, MOST_GUARDRAILS, NEXT, day, formatNumber }),
    template: `
        <section class="space-y-6" aria-labelledby="experiments-h" data-experiments>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 id="experiments-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.experiments.title') }}</h1>
                    <p class="mt-1 max-w-prose text-muted">{{ t('admin.experiments.intro') }}</p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <label v-if="list?.length" class="space-y-1 text-sm text-muted">
                        <span class="block">{{ t('admin.funnel.show') }}</span>
                        <select class="input w-auto" :value="chosenId" data-experiments-pick @change="choose($event.target.value)">
                            <option v-for="e in list" :key="e.id" :value="e.id" translate="no">{{ t('admin.experiments.option', { id: e.id, status: t(STATUS[e.status] ?? STATUS.draft) }) }}</option>
                        </select>
                    </label>
                    <button type="button" class="btn-secondary" data-experiments-new @click="openForm()">{{ t('admin.experiments.new') }}</button>
                </div>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!list" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <p v-else-if="!list.length" class="card p-5 text-muted">{{ t('admin.experiments.none') }}</p>
            <template v-else-if="chosen">
                <!-- What it tests and how it's read, in one card. -->
                <div class="card space-y-3 p-5" data-experiment-card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-lg font-bold text-cream"><span translate="no">{{ chosen.id }}</span>: {{ chosen.what }}</p>
                            <p v-if="chosen.hypothesis" class="text-sm text-muted">{{ chosen.hypothesis }}</p>
                        </div>
                        <span class="shrink-0 rounded-md border border-line px-2 py-0.5 text-xs font-semibold text-cream" data-experiment-status>{{ t(STATUS[chosen.status] ?? STATUS.draft) }}</span>
                    </div>
                    <dl class="grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
                        <div class="flex gap-2"><dt class="text-muted">{{ t('admin.experiments.arms') }}</dt><dd class="text-cream">{{ armsLine }}</dd></div>
                        <div class="flex gap-2"><dt class="text-muted">{{ t('admin.experiments.readBy') }}</dt><dd class="text-cream">{{ stepName(chosen.metric) }}</dd></div>
                        <div class="flex gap-2"><dt class="text-muted">{{ t('admin.experiments.guardrails') }}</dt><dd class="text-cream">{{ chosen.guardrails?.length ? chosen.guardrails.map(stepName).join(', ') : t('admin.experiments.noGuardrails') }}</dd></div>
                        <div class="flex gap-2"><dt class="text-muted">{{ t('admin.experiments.who') }}</dt><dd class="text-cream">{{ audienceLine }}</dd></div>
                        <div class="flex gap-2"><dt class="text-muted">{{ t('admin.experiments.planned') }}</dt><dd class="text-cream">{{ t('admin.experiments.perArm', { count: formatNumber(chosen.plannedPerArm) }) }}</dd></div>
                        <div class="flex gap-2"><dt class="text-muted">{{ t('admin.experiments.when') }}</dt><dd class="text-cream">{{ whenLine }}</dd></div>
                    </dl>
                    <!-- The verdict, only at the horizon; before it, why not yet. -->
                    <p class="rounded-xl px-3 py-2 text-sm font-semibold" :class="verdict.key === 'better' ? 'bg-good/15 text-cream' : verdict.key === 'guardrail' || verdict.key === 'worse' ? 'bg-bad/15 text-cream' : 'bg-raised text-cream'" data-experiment-verdict :data-verdict="verdict.key">{{ verdictWords }}</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <button v-if="NEXT[chosen.status]" type="button" class="btn-primary" :data-experiment-action="NEXT[chosen.status]" @click="acting = NEXT[chosen.status]">{{ t(ACTIONS[NEXT[chosen.status]]) }}</button>
                        <button v-if="chosen.status === 'draft'" type="button" class="btn-ghost" data-experiment-edit @click="openForm(chosen)">{{ t('admin.experiments.edit') }}</button>
                        <span v-if="chosen.winner" class="text-sm text-muted">{{ t('admin.experiments.winner', { arm: chosen.winner.toUpperCase() }) }}</span>
                    </div>
                </div>
                <!-- Its steps per arm: the Funnel's own, for this experiment. -->
                <FunnelSection v-if="chosen.status !== 'draft'" :key="chosen.id" :me="me" :fixed="chosen.id" @report="report = $event" />
            </template>

            <ChangeSheet v-if="acting" :title="t(ACTIONS[acting])" :me="me" :busy="busy" :errors="errors" :reasoned="false" @close="closeSheet" @confirm="act">
                <p class="text-cream">{{ t(ACTION_WHAT[acting], { id: chosen.id }) }}</p>
                <div v-if="acting === 'decide'" class="seg flex" role="radiogroup" :aria-label="t('admin.experiments.winnerPick')">
                    <button v-for="a in chosen.arms" :key="a.arm" type="button" role="radio" :aria-checked="winner === a.arm" class="seg-option" @click="winner = a.arm">{{ a.arm.toUpperCase() }}</button>
                </div>
            </ChangeSheet>

            <!-- A new experiment, or a draft's fields: a draft until it's started. -->
            <ChangeSheet v-if="form" :title="form.editing ? t('admin.experiments.editTitle') : t('admin.experiments.newTitle')" :me="me" :busy="busy" :errors="errors" :reasoned="false" :fields="['id', 'what', 'hypothesis', 'metric', 'guardrails', 'plannedPerArm']" @close="closeSheet" @confirm="save">
                <Field v-if="!form.editing" :label="t('admin.experiments.field.id')" :help="t('admin.experiments.field.idHelp')" :error="errors?.id" required v-slot="f">
                    <input :id="f.id" v-model.trim="form.id" class="input" required pattern="[a-z][a-z0-9-]{1,23}" maxlength="24" translate="no" :aria-describedby="f.describedby" :aria-invalid="f.invalid" data-field-id />
                </Field>
                <Field :label="t('admin.experiments.field.what')" :error="errors?.what" required v-slot="f">
                    <input :id="f.id" v-model="form.what" class="input" required maxlength="120" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                </Field>
                <Field :label="t('admin.experiments.field.hypothesis')" :help="t('admin.experiments.field.hypothesisHelp')" :error="errors?.hypothesis" v-slot="f">
                    <textarea :id="f.id" v-model="form.hypothesis" rows="2" maxlength="300" class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid"></textarea>
                </Field>
                <Field :label="t('admin.experiments.field.metric')" :error="errors?.metric" v-slot="f">
                    <select :id="f.id" v-model="form.metric" class="input" :aria-describedby="f.describedby"><option v-for="m in METRICS" :key="m" :value="m">{{ stepName(m) }}</option></select>
                </Field>
                <fieldset class="space-y-2">
                    <legend class="label">{{ t('admin.experiments.field.guardrails', { most: MOST_GUARDRAILS }) }}</legend>
                    <div class="grid gap-1 sm:grid-cols-2">
                        <label v-for="m in METRICS.filter((x) => x !== form.metric)" :key="m" class="check-row"><input v-model="form.guardrails" type="checkbox" class="checkbox" :value="m" :disabled="!form.guardrails.includes(m) && form.guardrails.length >= MOST_GUARDRAILS" /> {{ stepName(m) }}</label>
                    </div>
                    <p v-if="errors?.guardrails" class="field-error">{{ errors.guardrails.join(' ') }}</p>
                </fieldset>
                <fieldset class="space-y-2">
                    <legend class="label">{{ t('admin.experiments.field.audience') }}</legend>
                    <label v-for="(label, key) in AUDIENCE" :key="key" class="check-row"><input v-model="form.audience[key]" type="checkbox" class="checkbox" /> {{ t(label) }}</label>
                    <p class="text-xs text-muted">{{ t('admin.experiments.field.firstWeekNote') }}</p>
                </fieldset>
                <fieldset v-if="others.length" class="space-y-2">
                    <legend class="label">{{ t('admin.experiments.field.excludes') }}</legend>
                    <label v-for="e in others" :key="e.id" class="check-row"><input v-model="form.excludes" type="checkbox" class="checkbox" :value="e.id" /> <span translate="no">{{ e.id }}</span></label>
                </fieldset>
                <!-- The sample it's read at, from the calculator: a step's rate today and the smallest change worth finding. -->
                <fieldset class="space-y-2 rounded-2xl border border-line p-3.5" data-experiment-calculator>
                    <legend class="label px-1">{{ t('admin.experiments.field.planned') }}</legend>
                    <div class="grid grid-cols-2 gap-3">
                        <Field :label="t('admin.experiments.field.base')" v-slot="f"><input :id="f.id" v-model.number="form.base" type="number" min="1" max="98" class="input" data-calc-base /></Field>
                        <Field :label="t('admin.experiments.field.lift')" v-slot="f"><input :id="f.id" v-model.number="form.lift" type="number" min="1" max="50" class="input" data-calc-lift /></Field>
                    </div>
                    <p class="text-sm text-cream" data-calc-result>{{ calculated ? t('admin.experiments.field.needs', { count: formatNumber(calculated) }) : t('admin.experiments.field.needsNone') }}</p>
                    <Field :label="t('admin.experiments.field.perArm')" :error="errors?.plannedPerArm" v-slot="f">
                        <input :id="f.id" v-model.number="form.plannedPerArm" type="number" min="100" step="50" class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid" data-field-planned />
                    </Field>
                    <button v-if="calculated && calculated !== form.plannedPerArm" type="button" class="btn-ghost btn-sm" @click="form.plannedPerArm = calculated">{{ t('admin.experiments.field.useIt') }}</button>
                </fieldset>
            </ChangeSheet>
        </section>`,
    computed: {
        chosen() {
            return this.list?.find((e) => e.id === this.chosenId) ?? null;
        },
        others() {
            return (this.list ?? []).filter((e) => e.id !== this.form?.id && e.status !== 'closed');
        },
        /** "A 50%, B 50% (A is today's app)". */
        armsLine() {
            return t('admin.experiments.armsLine', { arms: this.chosen.arms.map((a) => `${a.arm.toUpperCase()} ${a.weight}%`).join(', ') });
        },
        audienceLine() {
            const a = this.chosen.audience ?? {};
            const parts = Object.keys(AUDIENCE).filter((k) => a[k]).map((k) => t(AUDIENCE[k]));
            if (SKINS[a.skin]) parts.push(t(SKINS[a.skin]));
            if (SIZES[a.size]) parts.push(t(SIZES[a.size]));
            const who = parts.length ? parts.join(', ') : t('admin.experiments.audience.everyone');
            return this.chosen.excludes?.length ? t('admin.experiments.notIn', { who, ids: this.chosen.excludes.join(', ') }) : who;
        },
        whenLine() {
            const e = this.chosen;
            if (!e.startsOn) return t('admin.experiments.notStarted');
            return [t('admin.experiments.started', { date: day(e.startsOn) }), e.endsOn ? t('admin.experiments.ended', { date: day(e.endsOn) }) : null, e.readFrom ? t('admin.experiments.readFrom', { date: day(e.readFrom) }) : null].filter(Boolean).join(' · ');
        },
        verdict() {
            return verdictOf(this.chosen, this.report);
        },
        verdictWords() {
            const v = this.verdict;
            if (v.key === 'guardrail') return t('admin.experiments.verdict.guardrail', { step: this.stepName(v.step) });
            if (v.key === 'notYet') return t('admin.experiments.verdict.notYet', { date: day(this.chosen.readFrom) || t('admin.experiments.verdict.readDate'), count: formatNumber(this.chosen.plannedPerArm) });
            return t(VERDICTS[v.key]);
        },
        calculated() {
            return this.form ? sampleFor(this.form.base, this.form.lift) : null;
        },
    },
    created() {
        this.load();
    },
    methods: {
        stepName: (kind) => (STEPS[kind] ? t(STEPS[kind]) : kind),
        choose(id) {
            this.chosenId = id;
            this.report = null;
        },
        async load() {
            this.failed = false;
            try {
                this.list = (await api.admin.experiments.list()).data;
                if (!this.list.some((e) => e.id === this.chosenId)) this.chosenId = this.list[0]?.id ?? null;
            } catch (error) {
                refused(error);
                this.failed = t('admin.experiments.failed');
            }
        },
        openForm(experiment = null) {
            this.errors = null;
            this.form = experiment
                ? { ...EMPTY(), ...experiment, audience: { ...(experiment.audience ?? {}) }, guardrails: [...(experiment.guardrails ?? [])], excludes: [...(experiment.excludes ?? [])], editing: true }
                : EMPTY();
        },
        closeSheet() {
            if (this.busy) return;
            this.acting = null;
            this.form = null;
            this.errors = null;
        },
        async save() {
            const { id, what, hypothesis, metric, guardrails, audience, excludes, plannedPerArm, editing } = this.form;
            const body = { what, hypothesis, metric, guardrails, audience: Object.fromEntries(Object.entries(audience).filter(([, on]) => on)), excludes, plannedPerArm };
            await this.send(async () => (editing ? api.admin.experiments.update(id, body) : api.admin.experiments.create({ id, arms: [{ arm: 'a', weight: 50 }, { arm: 'b', weight: 50 }], ...body })), editing ? SAID.saved : SAID.created, id);
        },
        async act() {
            const body = this.acting === 'decide' ? { action: 'decide', winner: this.winner } : { action: this.acting };
            await this.send(() => api.admin.experiments.update(this.chosen.id, body), SAID.done, this.chosen.id);
        },
        /** One change: the sheet closes on success and the list is read again; a refusal stays in the sheet. */
        async send(request, words, id) {
            this.busy = true;
            this.errors = null;
            try {
                await request();
                this.busy = false;
                this.closeSheet();
                toast(t(words, { id }));
                this.chosenId = id;
                await this.load();
            } catch (error) {
                this.busy = false;
                const fields = errorsFrom(error);
                if (fields) this.errors = fields;
                else if (REFUSED[error?.code]) this.errors = { action: [t(REFUSED[error.code])] };
                else {
                    refused(error);
                    this.errors = {};
                }
            }
        },
    },
};
