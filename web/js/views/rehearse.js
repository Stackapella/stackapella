// Ready by rehearsal's plan for one part (/rehearse/<song>/<part>; docs/rehearsal-ready-plan.md, "The plan"): the part
// and the song, the days to the rehearsal, a row a day (Today's with Start and about how long), the whole part's day last
// with the ready check. Quiet links under it: I know most of it, Add to my calendar, Send myself the link, Change the day,
// Remove this plan. No plan yet: the date asked, one field. No "behind", no red, no streak; both skins (game mode
// restyles the card as its stage card, never a new row).

import { formatDate, formatNumber, t } from '../i18n.js';
import { navigate } from '../router.js';
import { AppScreen } from '../components/page.js';
import { confirmAction } from '../components/confirm.js';
import { Icon } from '../components/icon.js';
import { loadLearning } from '../stores/learning.js';
import { daysLeft, dayNow, isReady } from '../rehearsal-plan.js';
import { isoDate, localDate } from '../rehearsal.js';
import { loadTemplate, phrases as templatePhrases } from '../templates.js';
import { addToCalendar, planFor, planTitle, refreshPlan, removePlan, sendPlanLink, startDay, startPlan } from '../stores/rehearsals.js';

const PASSES = 6; // a day's phrases heard, sung along and sung on your own, a few times each: for "about N minutes"

export const RehearseView = {
    components: { AppScreen, Icon },
    props: { song: { type: String, required: true }, part: { type: String, required: true } },
    data: () => ({ plan: null, template: null, title: '', loaded: false, busy: false, date: '', changing: false }),
    template: `
        <AppScreen :title="t('rehearse.screen')" back="/" compact measure="max-w-xl" data-rehearse>
            <p v-if="!loaded" class="text-muted" role="status">{{ t('common.loading') }}</p>
            <template v-else>
                <div class="space-y-2 pt-2">
                    <h1 class="t-title text-cream" translate="no" data-rehearse-title>{{ title }}</h1>
                    <p v-if="plan" class="t-lead" data-rehearse-when>{{ whenLine }}</p>
                </div>
                <!-- No plan yet (or a new day): the date, one field. -->
                <form v-if="!plan || changing" class="mt-6 space-y-3" data-rehearse-ask @submit.prevent="make">
                    <label class="block space-y-1.5">
                        <span class="font-semibold text-cream">{{ t('rehearse.ask') }}</span>
                        <input v-model="date" type="date" class="input" :min="today" required data-rehearse-date />
                    </label>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                        <button type="submit" class="btn-primary" :disabled="!date || busy" data-rehearse-make>{{ t('rehearse.make') }}</button>
                        <button v-if="changing" type="button" class="link tap-44 text-sm" @click="changing = false">{{ t('common.cancel') }}</button>
                    </div>
                </form>
                <template v-if="plan && !changing">
                    <ol class="card mt-6 divide-y divide-line overflow-hidden" data-ui="stage-card" data-rehearse-days>
                        <li v-for="d in rows" :key="d.date" class="flex items-center gap-4 px-5 py-4" :data-rehearse-day="d.date" :data-done="d.doneAt ? '' : null" :data-now="d.now ? '' : null">
                            <span class="w-20 shrink-0 text-sm" :class="d.now ? 'font-semibold text-cream' : 'text-subtle'">{{ d.label }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block" :class="d.doneAt ? 'text-muted' : 'text-cream'">{{ d.what }}</span>
                                <span v-if="d.whole" class="t-hint block">{{ t('rehearse.readyCheck') }}</span>
                                <span v-else-if="d.now && d.minutes" class="t-hint block">{{ t('rehearse.minutes', { minutes: formatNumber(d.minutes) }) }}</span>
                            </span>
                            <Icon v-if="d.doneAt" name="check" :size="18" class="shrink-0 text-muted" :aria-label="t('rehearse.done')" />
                            <button v-else-if="d.now && !ready" type="button" class="btn-primary shrink-0" :disabled="busy" data-rehearse-start @click="start(d)">{{ t('rehearse.start') }}</button>
                        </li>
                    </ol>
                    <!-- Ready (the ready check passed; the coordinator, 2026-11-24): the days go quiet, one line and one way to keep it fresh. -->
                    <div v-if="ready" class="mt-4 space-y-1" role="status" data-rehearse-ready>
                        <p class="text-cream">{{ t('rehearse.readyFor', { day: weekday }) }}</p>
                        <p class="text-sm text-muted">{{ t('rehearse.readyKeep') }}</p>
                        <button type="button" class="link tap-44 text-sm" :disabled="busy" data-rehearse-sing-through @click="knowIt">{{ t('rehearse.singThrough') }}</button>
                    </div>
                    <!-- The two that help today, then the plan's housekeeping as one quiet row (the coordinator: five stacked links was a lot). -->
                    <div class="mt-6 flex flex-col items-start gap-1">
                        <button v-if="!ready && hasPhraseDays" type="button" class="link tap-44 text-sm" data-rehearse-know @click="knowIt">{{ t('rehearse.knowIt') }}</button>
                        <button type="button" class="link tap-44 text-sm" data-rehearse-calendar @click="calendar">{{ t('rehearse.calendar') }}</button>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-x-5 border-t border-line pt-2">
                        <button v-if="canShare" type="button" class="link-quiet tap-44 text-[13px]" data-rehearse-share @click="share">{{ t('rehearse.share') }}</button>
                        <button type="button" class="link-quiet tap-44 text-[13px]" data-rehearse-change @click="changing = true; date = plan.date">{{ t('rehearse.change') }}</button>
                        <button type="button" class="link-quiet tap-44 text-[13px]" data-rehearse-remove @click="remove">{{ t('rehearse.remove') }}</button>
                    </div>
                </template>
            </template>
        </AppScreen>`,
    setup: () => ({ formatNumber }),
    computed: {
        today: () => isoDate(new Date()),
        canShare: () => typeof navigator.share === 'function',
        weekday() {
            return this.plan ? formatDate(localDate(this.plan.date), { weekday: 'long' }) : '';
        },
        /** "Thursday, 4 days to rehearsal", "Tomorrow" or "Today". */
        whenLine() {
            const left = daysLeft(this.plan);
            if (left <= 0) return t('rehearse.whenToday');
            if (left === 1) return t('rehearse.whenTomorrow', { day: this.weekday });
            return t('rehearse.when', { day: this.weekday, days: formatNumber(left) });
        },
        /** Ready, and no later check said "Nearly" (rehearsal-merge.js isReady). */
        ready() {
            return isReady(this.plan);
        },
        hasPhraseDays() {
            return this.plan.days.some((d) => d.phrases && !d.doneAt);
        },
        rows() {
            const now = dayNow(this.plan);
            const today = this.today;
            const beats = templatePhrases(this.template ?? {}).map((p) => p.endBeat - p.startBeat);
            const beatSeconds = 60 / (this.template?.tempo || 90);
            return this.plan.days.map((d) => {
                const label = d.date === today ? t('rehearse.today') : formatDate(localDate(d.date), { weekday: 'short' });
                const what = d.whole ? t('rehearse.whole') : d.phrases[0] === d.phrases[1] ? t('rehearse.phrase', { n: formatNumber(d.phrases[0] + 1) }) : t('rehearse.phrases', { from: formatNumber(d.phrases[0] + 1), to: formatNumber(d.phrases[1] + 1) });
                const seconds = d.phrases ? beats.slice(d.phrases[0], d.phrases[1] + 1).reduce((sum, b) => sum + b, 0) * beatSeconds * PASSES : 0;
                return { ...d, label, what, now: now?.date === d.date, minutes: seconds ? Math.max(2, Math.round(seconds / 60)) : 0 };
            });
        },
    },
    async created() {
        await loadLearning();
        this.template = await loadTemplate(this.song).catch(() => null);
        const plan = planFor(this.song, this.part);
        this.plan = plan ? await refreshPlan(plan) : null;
        this.title = this.plan ? await planTitle(this.plan) : this.template ? await planTitle({ songId: this.song, partKey: this.part }) : '';
        this.loaded = true;
    },
    methods: {
        async make() {
            if (!this.date) return;
            this.busy = true;
            try {
                this.plan = await startPlan({ songId: this.song, partKey: this.part, date: this.date });
                this.title = await planTitle(this.plan);
                this.changing = false;
            } finally {
                this.busy = false;
            }
        },
        async start(day) {
            this.busy = true;
            try {
                await startDay(this.plan, day);
            } finally {
                this.busy = false;
            }
        },
        /** I know most of it: straight to the ready check, which says which phrases to go over. */
        knowIt() {
            this.start(this.plan.days.find((d) => d.whole));
        },
        calendar() {
            addToCalendar(this.plan, this.title, (d) => (d.whole ? t('rehearse.whole') : t('rehearse.phrases', { from: d.phrases[0] + 1, to: d.phrases[1] + 1 })));
        },
        share() {
            sendPlanLink(this.plan, this.title);
        },
        async remove() {
            const yes = await confirmAction({ title: t('rehearse.removeTitle', { title: this.title }), body: t('rehearse.removeBody'), action: t('rehearse.removeAction') });
            if (!yes) return;
            await removePlan(this.song, this.part);
            navigate('/');
        },
    },
};
