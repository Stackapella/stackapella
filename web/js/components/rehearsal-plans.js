// Home's line for a rehearsal plan (docs/rehearsal-ready-plan.md: "Home shows it where a group's rehearsal shows"): one
// row a plan still on, soonest first, at most two: "Alto, Silent Night · 4 days to rehearsal", today's phrases under it,
// and Start, one tap into Sing on them. Nothing at all without a plan.

import { formatNumber, t } from '../i18n.js';
import { navigate } from '../router.js';
import { dayNow, daysLeft } from '../rehearsal-plan.js';
import { planTitle, plans, startDay } from '../stores/rehearsals.js';

const SHOWN = 2;

export const RehearsalPlans = {
    data: () => ({ titles: {}, busy: null }),
    template: `
        <ul v-if="rows.length" class="card divide-y divide-line px-4" data-rehearsal-plans>
            <li v-for="r in rows" :key="r.key" class="flex flex-wrap items-center gap-3 py-3" :data-rehearsal-plan="r.key">
                <a :href="r.href" class="min-w-0 flex-1" @click.prevent="navigate(r.href)">
                    <span class="block truncate font-semibold text-cream" translate="no">{{ titles[r.key] || '' }}</span>
                    <span class="t-hint block truncate">{{ r.line }}</span>
                </a>
                <button v-if="r.day" type="button" class="btn-secondary btn-sm" :disabled="busy === r.key" data-rehearsal-start @click="start(r)">{{ t('rehearse.start') }}</button>
            </li>
        </ul>`,
    setup: () => ({ navigate }),
    computed: {
        rows() {
            return plans.value.slice(0, SHOWN).map((plan) => {
                const left = daysLeft(plan);
                const day = dayNow(plan);
                const when = left <= 0 ? t('rehearse.whenToday') : t('rehearse.homeDays', { days: formatNumber(left) });
                const what = !day ? '' : day.whole ? t('rehearse.whole') : day.phrases[0] === day.phrases[1] ? t('rehearse.phrase', { n: formatNumber(day.phrases[0] + 1) }) : t('rehearse.phrases', { from: formatNumber(day.phrases[0] + 1), to: formatNumber(day.phrases[1] + 1) });
                return { key: `${plan.songId}:${plan.partKey}`, plan, day, href: `/rehearse/${encodeURIComponent(plan.songId)}/${encodeURIComponent(plan.partKey)}`, line: what ? t('rehearse.homeLine', { when, what }) : when };
            });
        },
    },
    watch: {
        rows: {
            immediate: true,
            async handler(rows) {
                for (const r of rows) if (!this.titles[r.key]) this.titles = { ...this.titles, [r.key]: await planTitle(r.plan).catch(() => '') };
            },
        },
    },
    methods: {
        async start(r) {
            this.busy = r.key;
            try {
                await startDay(r.plan, r.day);
            } finally {
                this.busy = null;
            }
        },
    },
};
