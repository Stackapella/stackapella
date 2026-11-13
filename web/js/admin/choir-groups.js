// Admin > Choir groups (docs/choir-tracks-plan.md, "How we'll know"; contract: admin.groups.list): the groups and
// how much their singers practise their own songs, week by week, so stage 1 can be read ("3 send a song and their
// singers practise it in the week before rehearsal"). A plain table, newest groups first as the server orders
// them (singers over the last two weeks, then the newest): each group's name, director, members and songs, and its
// last 8 weeks as a small bar of singers, with this week's singers and the 8 weeks' takes and scored runs. Totals
// only: never which member. A group paused because its leader's Plus ended (docs/pricing-plan.md, top: readOnly
// true) is tagged, and the paused are counted.

import { api } from '../api.js';
import { formatDate, formatNumber, t } from '../i18n.js';
import { StatusChip } from '../components/form.js';
import { LoadFailed, zoneName } from './common.js';

const week = (date) => formatDate(`${date}T12:00:00Z`, { month: 'short', day: 'numeric', timeZone: 'UTC' });
const sum = (weeks, key) => weeks.reduce((n, w) => n + (w[key] ?? 0), 0);

/** A group's last 8 weeks as bars of singers, each against the group's members (how much of the choir sang). */
const WeekBars = {
    props: { group: { type: Object, required: true } },
    setup: () => ({ week }),
    template: `
        <span class="inline-flex h-10 shrink-0 items-end gap-1" role="img" :aria-label="label" data-group-weeks>
            <span v-for="w in group.weeks" :key="w.week" class="w-2.5 rounded-sm" :class="w.singers ? 'bg-accent' : 'bg-raised'" :style="{ height: height(w) }" :title="t('admin.choirGroups.weekTitle', { week: week(w.week), singers: w.singers, takes: w.takes, scores: w.scores })" :data-week="w.week" :data-singers="w.singers"></span>
        </span>`,
    computed: {
        label() {
            return t('admin.choirGroups.weeksLabel', { singers: this.group.weeks.map((w) => w.singers).join(', '), members: this.group.members });
        },
    },
    methods: {
        // A week nobody sang is a sliver, so the eight still read as weeks; a week the whole choir sang is full height.
        height(w) {
            if (!w.singers) return '3px';
            return `${Math.max(20, Math.round((100 * w.singers) / Math.max(1, this.group.members, w.singers)))}%`;
        },
    },
};

export const ChoirGroupsSection = {
    components: { LoadFailed, StatusChip, WeekBars },
    props: { me: { type: Object, required: true } },
    data: () => ({ report: null, failed: false }),
    setup: () => ({ formatNumber, zoneName }),
    template: `
        <section class="space-y-6" aria-labelledby="choir-groups-h" data-choir-groups>
            <div>
                <h1 id="choir-groups-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.choirGroups.title') }}</h1>
                <p class="mt-1 text-sm text-muted">{{ report ? t('admin.choirGroups.intro', { shown: groups.length, total: report.total, zone: zoneName(report.timezone) }) : t('admin.choirGroups.introLoading') }}<template v-if="report?.paused"> {{ t('admin.choirGroups.pausedCount', { count: report.paused }) }}</template></p>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!report" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <p v-else-if="!groups.length" class="card p-5 text-muted" data-choir-groups-none>{{ t('admin.choirGroups.none') }}</p>
            <template v-else>
                <!-- Wider screens: one table. -->
                <div class="card hidden overflow-x-auto p-4 sm:block md:p-5">
                    <table class="w-full text-sm" data-choir-groups-table>
                        <thead class="text-xs text-muted">
                            <tr class="border-b border-line">
                                <th scope="col" class="py-2 pe-2 text-start font-semibold">{{ t('admin.choirGroups.col.group') }}</th>
                                <th scope="col" class="px-2 py-2 text-start font-semibold">{{ t('admin.choirGroups.col.director') }}</th>
                                <th scope="col" class="px-2 py-2 text-end font-semibold">{{ t('admin.choirGroups.col.members') }}</th>
                                <th scope="col" class="px-2 py-2 text-end font-semibold">{{ t('admin.choirGroups.col.songs') }}</th>
                                <th scope="col" class="px-2 py-2 text-start font-semibold">{{ t('admin.choirGroups.col.weeks') }}</th>
                                <th scope="col" class="px-2 py-2 text-end font-semibold">{{ t('admin.choirGroups.col.thisWeek') }}</th>
                                <th scope="col" class="px-2 py-2 text-end font-semibold">{{ t('admin.choirGroups.col.takes') }}</th>
                                <th scope="col" class="ps-2 py-2 text-end font-semibold">{{ t('admin.choirGroups.col.scores') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="g in groups" :key="g.id" :data-group="g.id">
                                <th scope="row" class="py-2.5 pe-2 text-start font-normal"><span class="flex flex-wrap items-center gap-x-2 font-semibold text-cream">{{ g.name }}<StatusChip v-if="paused(g)" tone="muted" :label="t('admin.choirGroups.paused')" data-paused /></span><span class="block text-xs text-muted">{{ t('admin.choirGroups.started', { date: formatDate(g.createdAt, { dateStyle: 'medium' }) }) }}</span></th>
                                <td class="px-2 py-2.5 text-cream">{{ g.director?.name ?? '' }}</td>
                                <td class="px-2 py-2.5 text-end tabular-nums text-cream" data-col="members">{{ formatNumber(g.members) }}</td>
                                <td class="px-2 py-2.5 text-end tabular-nums text-cream" data-col="songs">{{ formatNumber(g.songs) }}</td>
                                <td class="px-2 py-2.5"><WeekBars :group="g" /></td>
                                <td class="px-2 py-2.5 text-end tabular-nums" data-col="thisWeek"><b class="text-cream">{{ formatNumber(thisWeek(g)) }}</b></td>
                                <td class="px-2 py-2.5 text-end tabular-nums text-cream" data-col="takes">{{ formatNumber(total(g, 'takes')) }}</td>
                                <td class="ps-2 py-2.5 text-end tabular-nums text-cream" data-col="scores">{{ formatNumber(total(g, 'scores')) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Phones: a card a group, in the same order. -->
                <ul class="space-y-3 sm:hidden" data-choir-groups-cards>
                    <li v-for="g in groups" :key="g.id" class="card p-4" :data-group="g.id">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-x-2 font-semibold text-cream">{{ g.name }}<StatusChip v-if="paused(g)" tone="muted" :label="t('admin.choirGroups.paused')" data-paused /></p>
                                <p class="text-sm text-muted">{{ g.director?.name ?? '' }} · {{ t('admin.choirGroups.started', { date: formatDate(g.createdAt, { dateStyle: 'medium' }) }) }}</p>
                            </div>
                            <WeekBars :group="g" />
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                            <div class="flex items-baseline justify-between gap-2"><dt class="text-muted">{{ t('admin.choirGroups.col.members') }}</dt><dd class="tabular-nums text-cream">{{ formatNumber(g.members) }}</dd></div>
                            <div class="flex items-baseline justify-between gap-2"><dt class="text-muted">{{ t('admin.choirGroups.col.songs') }}</dt><dd class="tabular-nums text-cream">{{ formatNumber(g.songs) }}</dd></div>
                            <div class="flex items-baseline justify-between gap-2"><dt class="text-muted">{{ t('admin.choirGroups.col.thisWeek') }}</dt><dd class="tabular-nums text-cream"><b>{{ formatNumber(thisWeek(g)) }}</b></dd></div>
                            <div class="flex items-baseline justify-between gap-2"><dt class="text-muted">{{ t('admin.choirGroups.col.takes') }}</dt><dd class="tabular-nums text-cream">{{ formatNumber(total(g, 'takes')) }}</dd></div>
                            <div class="flex items-baseline justify-between gap-2"><dt class="text-muted">{{ t('admin.choirGroups.col.scores') }}</dt><dd class="tabular-nums text-cream">{{ formatNumber(total(g, 'scores')) }}</dd></div>
                        </dl>
                    </li>
                </ul>
                <p class="text-xs text-muted">{{ t('admin.choirGroups.note') }}</p>
            </template>
        </section>`,
    computed: {
        groups() {
            return this.report?.groups ?? [];
        },
    },
    created() {
        this.load();
    },
    methods: {
        formatDate,
        thisWeek: (g) => g.weeks.at(-1)?.singers ?? 0,
        paused: (g) => g.readOnly === true,
        total: (g, key) => sum(g.weeks, key),
        async load() {
            this.failed = false;
            this.report = null;
            try {
                this.report = (await api.admin.groups.list()).data;
            } catch (error) {
                console.warn('Choir groups did not load', error);
                this.failed = t('admin.choirGroups.failed');
            }
        },
    },
};
