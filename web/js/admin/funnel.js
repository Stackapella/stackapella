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

export const FunnelSection = {
    components: { LoadFailed, Notice },
    props: { me: { type: Object, required: true } },
    data: () => ({ experiment: null, days: 30, report: null, failed: false, loadingMore: false, cursor: null }),
    setup: () => ({ RANGES, STEPS, ASKS, ARRIVALS, formatNumber, rateOf, points, day }),
    template: `
        <section class="space-y-8" aria-labelledby="funnel-h">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 id="funnel-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.funnel.title') }}</h1>
                    <p class="mt-1 max-w-prose text-muted">{{ t('admin.funnel.intro') }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <div class="seg flex" role="radiogroup" :aria-label="t('admin.funnel.which')" data-funnel-experiments>
                        <button type="button" role="radio" :aria-checked="experiment === null" class="seg-option" @click="choose(null)">{{ t('admin.funnel.everyone') }}</button>
                        <button v-for="e in experiments" :key="e.id" type="button" role="radio" :aria-checked="experiment === e.id" class="seg-option" translate="no" @click="choose(e.id)">{{ e.id }}</button>
                    </div>
                    <div v-if="experiment === null" class="seg flex" role="radiogroup" :aria-label="t('admin.usage.range')">
                        <button v-for="d in RANGES" :key="d" type="button" role="radio" :aria-checked="days === d" class="seg-option" @click="setDays(d)">{{ t('admin.usage.days', { count: d }) }}</button>
                    </div>
                </div>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!report" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <div v-if="chosen" class="space-y-3" data-funnel-about>
                    <p class="text-sm text-muted">{{ about }}</p>
                    <Notice v-if="report.split && !report.split.ok" tone="bad" data-funnel-split>{{ t('admin.funnel.splitBad', { shares: shares, p: report.split.pValue }) }}</Notice>
                    <Notice v-if="!report.enough" tone="warn" data-funnel-early>{{ t('admin.funnel.notEnough', { arrivals: formatNumber(fewestArrivals), planned: formatNumber(chosen.plannedPerArm) }) }}</Notice>
                    <Notice v-else-if="tooSoon" tone="warn" data-funnel-early>{{ t('admin.funnel.tooSoon', { date: day(chosen.readFrom) }) }}</Notice>
                </div>
                <p class="text-sm text-muted">{{ t('admin.funnel.span', { from: day(report.from), to: day(report.to) }) }}</p>

                <section class="space-y-3" aria-labelledby="steps-h">
                    <h2 id="steps-h" class="text-lg font-bold text-cream">{{ t('admin.funnel.steps') }}</h2>
                    <div class="overflow-x-auto rounded-2xl border border-line">
                        <table class="w-full text-sm" data-funnel-steps>
                            <thead class="text-start text-xs text-muted">
                                <tr class="border-b border-line">
                                    <th scope="col" class="px-4 py-2.5 text-start font-semibold">{{ t('admin.funnel.step.title') }}</th>
                                    <th v-for="arm in arms" :key="arm" scope="col" class="px-4 py-2.5 text-end font-semibold" translate="no">{{ arm === 'all' ? t('admin.funnel.devices') : t('admin.funnel.arm', { arm: arm.toUpperCase() }) }}</th>
                                    <th v-if="arms.length === 2" scope="col" class="px-4 py-2.5 text-end font-semibold">{{ t('admin.funnel.difference') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <tr v-for="s in report.steps" :key="s.kind" :class="{ 'bg-raised': s.kind === chosen?.primary }">
                                    <th scope="row" class="min-w-44 px-4 py-2.5 text-start font-normal text-cream">{{ stepName(s.kind) }} <span v-if="s.kind === chosen?.primary" class="ms-1 text-xs font-semibold text-accent">{{ t('admin.funnel.primaryTag') }}</span><span v-else-if="s.kind === chosen?.guardrail" class="ms-1 text-xs font-semibold text-warn">{{ t('admin.funnel.guardrailTag') }}</span></th>
                                    <td v-for="a in s.arms" :key="a.arm" class="whitespace-nowrap px-4 py-2.5 text-end tabular-nums text-cream">{{ formatNumber(a.total) }} <span v-if="!ARRIVALS.includes(s.kind)" class="text-muted">{{ rateOf(a.rate) }}</span></td>
                                    <td v-if="arms.length === 2" class="whitespace-nowrap px-4 py-2.5 text-end tabular-nums" :class="real(s.difference) ? (s.difference.points > 0 ? 'text-good' : 'text-bad') : 'text-muted'">
                                        <template v-if="s.difference">{{ points(s.difference.points) }} <span class="text-xs">{{ t('admin.funnel.interval', { low: points(s.difference.low), high: points(s.difference.high) }) }}</span></template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-muted">{{ t('admin.funnel.stepsNote') }}<template v-if="arms.length === 2"> {{ t('admin.funnel.stepsNoteArms') }}</template></p>
                </section>

                <section class="space-y-3" aria-labelledby="asks-h">
                    <h2 id="asks-h" class="text-lg font-bold text-cream">{{ t('admin.funnel.asks') }}</h2>
                    <div class="overflow-x-auto rounded-2xl border border-line">
                        <table class="w-full text-sm" data-funnel-asks>
                            <thead class="text-xs text-muted">
                                <tr class="border-b border-line">
                                    <th scope="col" class="px-4 py-2.5 text-start font-semibold">{{ t('admin.funnel.ask.title') }}</th>
                                    <th v-for="arm in arms" :key="arm" scope="col" class="px-4 py-2.5 text-end font-semibold" translate="no">{{ arm === 'all' ? t('admin.funnel.devices') : t('admin.funnel.arm', { arm: arm.toUpperCase() }) }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <tr v-for="a in report.asks" :key="a.ask">
                                    <th scope="row" class="px-4 py-2.5 text-start font-normal text-cream">{{ ASKS[a.ask] ? t(ASKS[a.ask]) : a.ask }}</th>
                                    <td v-for="arm in a.arms" :key="arm.arm" class="whitespace-nowrap px-4 py-2.5 text-end tabular-nums text-cream">{{ t('admin.funnel.yesOf', { yes: formatNumber(arm.yes), shown: formatNumber(arm.shown) }) }} <span class="text-muted">{{ rateOf(arm.rate) }}</span></td>
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
            const arrivals = this.arms.map((arm) => this.report.steps.filter((s) => ARRIVALS.includes(s.kind)).reduce((sum, s) => sum + (s.arms.find((a) => a.arm === arm)?.total ?? 0), 0));
            return arrivals.length ? Math.min(...arrivals) : 0;
        },
        /** Day-2 and week-1 returns need a week after enrolment stops. */
        tooSoon() {
            return !this.chosen?.readFrom || new Date().toISOString().slice(0, 10) < this.chosen.readFrom;
        },
        /** When it started, whether it still enrols, the planned arrivals and the steps it's read by. */
        about() {
            const e = this.chosen;
            return [
                e.start ? t('admin.funnel.started', { date: day(e.start) }) : t('admin.funnel.notStarted'),
                e.enrolling ? t('admin.funnel.enrolling') : e.enrolUntil ? t('admin.funnel.enrolledUntil', { date: day(e.enrolUntil) }) : null,
                t('admin.funnel.plannedPerArm', { count: formatNumber(e.plannedPerArm) }),
                t('admin.funnel.primary', { step: this.stepName(e.primary) }),
                e.guardrail ? t('admin.funnel.guardrail', { step: this.stepName(e.guardrail) }) : null,
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
