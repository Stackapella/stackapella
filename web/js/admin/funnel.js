// Funnel (docs/onboarding-review.md, section 4; contract: admin.funnel.show): the onboarding funnel
// from the anonymous counts, each step a device's first, as a share of arrivals. With an experiment
// chosen: each arm side by side, the difference with its 95% interval, whether the arms split
// 50/50 (a bucketing check, not a result) and whether there are enough arrivals to read it yet.
// Without one: every device over 7, 30 or 90 days. Totals only: nothing follows a device.

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { Notice } from '../components/form.js';
import { LoadFailed, loadInto } from './common.js';

const RANGES = [7, 30, 90];
const ARRIVALS = ['f_arrive', 'f_arrive_start'];
// Each step's name (config/counts.php `funnel`, in its order); a kind not named here shows as its code.
const STEPS = {
    f_site_start: tk('admin.funnel.step.f_site_start'),
    f_arrive: tk('admin.funnel.step.f_arrive'),
    f_arrive_start: tk('admin.funnel.step.f_arrive_start'),
    f_mic: tk('admin.funnel.step.f_mic'),
    f_note: tk('admin.funnel.step.f_note'),
    f_note_skip: tk('admin.funnel.step.f_note_skip'),
    f_lesson1: tk('admin.funnel.step.f_lesson1'),
    f_chord: tk('admin.funnel.step.f_chord'),
    f_lesson2: tk('admin.funnel.step.f_lesson2'),
    f_lesson3: tk('admin.funnel.step.f_lesson3'),
    f_unit1: tk('admin.funnel.step.f_unit1'),
    f_goal: tk('admin.funnel.step.f_goal'),
    f_install: tk('admin.funnel.step.f_install'),
    f_name: tk('admin.funnel.step.f_name'),
    f_account: tk('admin.funnel.step.f_account'),
    f_share: tk('admin.funnel.step.f_share'),
    f_remind: tk('admin.funnel.step.f_remind'),
    f_take: tk('admin.funnel.step.f_take'),
    f_harmony: tk('admin.funnel.step.f_harmony'),
    f_day2: tk('admin.funnel.step.f_day2'),
    f_week1: tk('admin.funnel.step.f_week1'),
    f_goal_met: tk('admin.funnel.step.f_goal_met'),
};
const ASKS = {
    install: tk('admin.funnel.ask.install'),
    account: tk('admin.funnel.ask.account'),
    name: tk('admin.funnel.ask.name'),
    share: tk('admin.funnel.ask.share'),
    goal: tk('admin.funnel.ask.goal'),
    remind: tk('admin.funnel.ask.remind'),
};

const day = (date) => (date ? formatDate(`${date}T12:00:00Z`, { month: 'short', day: 'numeric', year: 'numeric' }) : '');
const rateOf = (rate) => (rate === null || rate === undefined ? '' : `${formatNumber(Math.round(rate * 1000) / 10)}%`);
const points = (n) => {
    const rounded = Math.round(n * 10) / 10 || 0; // never "-0"
    return `${rounded > 0 ? '+' : ''}${formatNumber(rounded)}`;
};

// Experiment arms (docs/admin-growth-plan.md): A blue and B orange, a pair told apart with colour
// blindness on light and dark, used for the arms only and always with their letters.
export const ARM_COLORS = { a: '#3b82f6', b: '#d97706', all: 'var(--color-accent)' };
// What each experiment changes, and its arms' names (the server sends ids only); an unknown one is its id.
const EXPERIMENTS = { onb1: tk('admin.funnel.experiment.onb1') };
const ARM_NAMES = { 'onb1:a': tk('admin.funnel.armName.onb1.a'), 'onb1:b': tk('admin.funnel.armName.onb1.b') };
const MARK_POINTS = 10; // the range mark spans -10 to +10 points
// The main path, in order: each of these reached only by those who reached the one before, so what
// was lost between them is meaningful. Side steps (a skipped note, a share, a reminder) aren't on it.
const PATH = ['f_mic', 'f_note', 'f_lesson1', 'f_lesson2', 'f_lesson3', 'f_unit1'];

/** Arrivals per arm ({ a: 1480, b: 1502 }), both ways in counted: what an experiment is sized by. */
export function arrivalsByArm(report) {
    const by = {};
    for (const step of report?.steps ?? []) if (ARRIVALS.includes(step.kind)) for (const a of step.arms) by[a.arm] = (by[a.arm] ?? 0) + a.total;
    return by;
}

/** How many of the step before didn't reach this one, as a share, or null where steps don't follow one another. */
export function lostBetween(before, after) {
    if (!(before > 0) || after === null || after === undefined || after > before) return null;
    return 1 - after / before;
}

export const FunnelSection = {
    components: { LoadFailed, Notice },
    props: { me: { type: Object, required: true } },
    data: () => ({ experiment: null, days: 30, report: null, failed: false, loadingMore: false, cursor: null }),
    setup: () => ({ RANGES, STEPS, ASKS, ARRIVALS, ARM_COLORS, MARK_POINTS, formatNumber, rateOf, points, day }),
    template: `
        <section class="space-y-6" aria-labelledby="funnel-h">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 id="funnel-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.funnel.title') }}</h1>
                    <p class="mt-1 max-w-prose text-muted">{{ t('admin.funnel.intro') }}</p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="space-y-1 text-sm text-muted">
                        <span class="block">{{ t('admin.funnel.show') }}</span>
                        <select class="input w-auto" :value="experiment ?? ''" data-funnel-experiments @change="choose($event.target.value || null)">
                            <option value="">{{ t('admin.funnel.everyone') }}</option>
                            <option v-for="e in experiments" :key="e.id" :value="e.id">{{ t('admin.funnel.experimentOption', { id: e.id }) }}</option>
                        </select>
                    </label>
                    <div v-if="experiment === null" class="seg flex" role="radiogroup" :aria-label="t('admin.usage.range')">
                        <button v-for="d in RANGES" :key="d" type="button" role="radio" :aria-checked="days === d" class="seg-option" @click="setDays(d)">{{ t('admin.usage.days', { count: d }) }}</button>
                    </div>
                </div>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!report" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <!-- One status card: what it changes, what it's read by, how it's going. -->
                <div v-if="chosen" class="card space-y-1.5 p-4" data-funnel-about>
                    <p class="text-cream"><b>{{ t('admin.funnel.whatItChanges', { id: chosen.id, what: changes(chosen.id) }) }}</b> {{ readBy }}</p>
                    <p class="text-sm text-muted">{{ about }}</p>
                </div>
                <Notice v-if="chosen && report.split && !report.split.ok" tone="bad" data-funnel-split>{{ t('admin.funnel.splitBad', { shares: shares, p: report.split.pValue }) }}</Notice>
                <Notice v-if="chosen && !report.enough" tone="warn" data-funnel-early>{{ t('admin.funnel.notEnough', { arrivals: formatNumber(fewestArrivals), planned: formatNumber(chosen.plannedPerArm) }) }}</Notice>
                <p v-if="!chosen" class="text-sm text-muted">{{ t('admin.funnel.span', { from: day(report.from), to: day(report.to) }) }}</p>
                <p v-else class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted" data-funnel-legend>
                    <span v-for="arm in arms" :key="arm" class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm" :style="{ background: ARM_COLORS[arm] }" aria-hidden="true"></span>{{ armLabel(arm) }}</span>
                    <span>{{ t('admin.funnel.differenceLegend') }}</span>
                </p>

                <!-- Each step's share of arrivals as a bar, an arm a bar, and what was lost between steps. -->
                <section class="card p-4 md:p-5" aria-labelledby="steps-h">
                    <h2 id="steps-h" class="sr-only">{{ t('admin.funnel.steps') }}</h2>
                    <ol class="divide-y divide-line" data-funnel-steps>
                        <li v-for="(s, i) in report.steps" :key="s.kind" class="py-3" :data-kind="s.kind">
                            <div class="grid gap-x-4 gap-y-2 md:grid-cols-[12rem_1fr_7rem_5rem] md:items-center">
                                <p class="font-semibold text-cream">{{ stepName(s.kind) }}<span v-if="s.kind === chosen?.primary"> {{ t('admin.funnel.primaryMark') }}</span></p>
                                <div class="space-y-1.5">
                                    <div v-for="a in s.arms" :key="a.arm" class="flex items-center gap-2">
                                        <span v-if="arms.length > 1" class="w-3 text-xs font-bold text-cream" translate="no">{{ a.arm.toUpperCase() }}</span>
                                        <div class="h-2.5 min-w-0 flex-1 overflow-hidden rounded-full bg-raised" aria-hidden="true"><div class="h-full rounded-full" :style="{ width: Math.min(100, Math.max(a.rate ? 1 : 0, (a.rate ?? 0) * 100)) + '%', background: ARM_COLORS[a.arm] ?? ARM_COLORS.all }"></div></div>
                                        <span class="w-24 text-end text-sm tabular-nums text-cream">{{ ARRIVALS.includes(s.kind) && arms.length === 1 ? formatNumber(a.total) : rateOf(a.rate) }}</span>
                                    </div>
                                </div>
                                <p v-if="arms.length === 2" class="text-end text-sm tabular-nums" data-funnel-difference>
                                    <template v-if="s.difference"><b class="text-cream">{{ t('admin.funnel.pts', { points: points(s.difference.points) }) }}</b><span class="block text-xs text-muted">{{ t('admin.funnel.range', { low: points(s.difference.low), high: points(s.difference.high) }) }}</span></template>
                                    <span v-else class="text-muted">-</span>
                                </p>
                                <!-- The 95% range around zero, -10 to +10 points (wider screens). -->
                                <svg v-if="arms.length === 2" class="hidden md:block" viewBox="0 0 80 16" width="80" height="16" aria-hidden="true">
                                    <template v-if="s.difference">
                                        <line x1="40" x2="40" y1="2" y2="14" class="stroke-muted" stroke-width="1" />
                                        <line :x1="markAt(s.difference.low)" :x2="markAt(s.difference.high)" y1="8" y2="8" class="stroke-cream" stroke-width="2" stroke-linecap="round" />
                                        <circle :cx="markAt(s.difference.points)" cy="8" r="3.5" class="fill-cream" />
                                    </template>
                                </svg>
                            </div>
                            <p v-if="lostLine(i)" class="mt-2 text-xs text-muted" data-funnel-lost>{{ lostLine(i) }}</p>
                        </li>
                    </ol>
                </section>

                <section class="card space-y-3 p-4 md:p-5" aria-labelledby="asks-h">
                    <h2 id="asks-h" class="text-lg font-bold text-cream">{{ t('admin.funnel.asks') }}</h2>
                    <div>
                        <table class="w-full text-sm" data-funnel-asks>
                            <thead class="text-xs text-muted">
                                <tr class="border-b border-line">
                                    <th scope="col" class="py-2.5 pe-4 text-start font-semibold">{{ t('admin.funnel.ask.title') }}</th>
                                    <th v-for="arm in arms" :key="arm" scope="col" class="px-2 py-2.5 text-end font-semibold sm:px-4" translate="no">{{ arm === 'all' ? t('admin.funnel.devices') : t('admin.funnel.arm', { arm: arm.toUpperCase() }) }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <tr v-for="a in report.asks" :key="a.ask">
                                    <th scope="row" class="py-2.5 pe-4 text-start font-normal text-cream">{{ ASKS[a.ask] ? t(ASKS[a.ask]) : a.ask }}</th>
                                    <td v-for="arm in a.arms" :key="arm.arm" class="px-2 py-2.5 text-end tabular-nums text-cream sm:px-4">{{ t('admin.funnel.yesOf', { yes: formatNumber(arm.yes), shown: formatNumber(arm.shown) }) }} <span class="block text-muted sm:inline">{{ rateOf(arm.rate) }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </section>`,
    computed: {
        experiments() {
            return this.report?.experiments ?? [];
        },
        chosen() {
            return this.experiments.find((e) => e.id === this.report?.experiment) ?? null;
        },
        arms() {
            return this.report?.steps[0]?.arms.map((a) => a.arm) ?? [];
        },
        /** The arm with the fewest arrivals: enough to read once even it has the planned number. */
        fewestArrivals() {
            const arrivals = Object.values(arrivalsByArm(this.report));
            return arrivals.length ? Math.min(...arrivals) : 0;
        },
        /** Day-2 and week-1 returns need a week after enrolment stops. */
        tooSoon() {
            return !this.chosen?.readFrom || new Date().toISOString().slice(0, 10) < this.chosen.readFrom;
        },
        /** "Read by: Lesson 3. Guardrail: Lesson 2." */
        readBy() {
            const e = this.chosen;
            return [t('admin.funnel.readBy', { step: this.stepName(e.primary) }), e.guardrail ? t('admin.funnel.guardrailIs', { step: this.stepName(e.guardrail) }) : null].filter(Boolean).join(' ');
        },
        /** When it enrolled, its arrivals per arm, whether the arms split evenly (a bucketing check) and when it can be read. */
        about() {
            const e = this.chosen;
            const arrivals = Object.values(arrivalsByArm(this.report)).map(formatNumber);
            return [
                !e.start ? t('admin.funnel.notStarted') : e.enrolling ? t('admin.funnel.enrollingSince', { date: day(e.start) }) : t('admin.funnel.enrolledFromTo', { from: day(e.start), to: day(e.enrolUntil) }),
                t('admin.funnel.arrivalsPerArm', { list: arrivals.join(t('admin.funnel.and')), planned: formatNumber(e.plannedPerArm) }),
                this.report.split?.ok ? t('admin.funnel.splitOk', { p: this.report.split.pValue }) : null,
                e.readFrom ? (this.tooSoon ? t('admin.funnel.readFrom', { date: day(e.readFrom) }) : t('admin.funnel.readyNow')) : null,
            ]
                .filter(Boolean)
                .join(' · ');
        },
        shares() {
            return (this.report.split?.arms ?? []).map((a) => `${a.arm.toUpperCase()} ${formatNumber(Math.round(a.share * 1000) / 10)}%`).join(', ');
        },
    },
    created() {
        this.load();
    },
    methods: {
        changes(id) {
            return EXPERIMENTS[id] ? t(EXPERIMENTS[id]) : id;
        },
        /** "A, lesson 1 as it is" where the arm is named, else "A". */
        armLabel(arm) {
            const name = ARM_NAMES[`${this.chosen?.id}:${arm}`];
            return name ? t('admin.funnel.armNamed', { arm: arm.toUpperCase(), name: t(name) }) : arm.toUpperCase();
        },
        /** Where a difference in points sits on the range mark, -10 to +10 across 80 (clamped). */
        markAt(value) {
            return 40 + (Math.max(-MARK_POINTS, Math.min(MARK_POINTS, value)) / MARK_POINTS) * 36;
        },
        /** "lost here: A 18%, B 18%" under a step, only where the next step follows from it. */
        lostLine(i) {
            const here = this.report.steps[i];
            const at = PATH.indexOf(here.kind);
            const next = at >= 0 ? this.report.steps.find((s) => s.kind === PATH[at + 1]) : null;
            if (!next) return null;
            const lost = here.arms.map((a) => [a.arm, lostBetween(a.rate, next.arms.find((b) => b.arm === a.arm)?.rate)]);
            if (lost.some(([, share]) => share === null)) return null;
            const shares = lost.map(([arm, share]) => (this.arms.length > 1 ? `${arm.toUpperCase()} ${rateOf(share)}` : rateOf(share))).join(', ');
            return t('admin.funnel.lostHere', { shares });
        },
        stepName(kind) {
            return STEPS[kind] ? t(STEPS[kind]) : kind;
        },
        /** An interval that leaves out 0: a real difference. */
        real(difference) {
            return !!difference && (difference.low > 0 || difference.high < 0);
        },
        choose(id) {
            this.experiment = id;
            this.load();
        },
        setDays(days) {
            this.days = days;
            this.load();
        },
        async load() {
            // The answer is one object, not a page: loadInto's list shape, wrapped.
            await loadInto(this, 'report', async () => ({ data: (await api.admin.funnel.show(this.experiment ? { experiment: this.experiment } : { days: this.days })).data }));
        },
    },
};
