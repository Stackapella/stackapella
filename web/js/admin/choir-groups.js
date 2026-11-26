// Admin > Choir groups (docs/choir-tracks-plan.md, "How we'll know"; contract: admin.groups.list): the groups and
// how much their singers practise their own songs, week by week, so stage 1 can be read ("3 send a song and their
// singers practise it in the week before rehearsal"). A plain table, newest groups first as the server orders
// them (singers over the last two weeks, then the newest): each group's name, director, members and songs, and its
// last 8 weeks as a small bar of singers, with this week's singers and the 8 weeks' takes and scored runs. Totals
// only: never which member. A group paused because its leader's Plus ended (docs/pricing-plan.md, top: readOnly
// true) is tagged, and the paused are counted.

import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { Field, StatusChip } from '../components/form.js';
import { Modal, toast } from '../components/ui.js';
import { ChangeSheet, errorsFrom, LoadFailed, refused, zoneName } from './common.js';

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

const SEAT_STATUS = {
    none: { label: tk('admin.choirGroups.seats.status.none'), tone: 'muted' },
    free_trial: { label: tk('admin.choirGroups.seats.status.trial'), tone: 'info' },
    trialing: { label: tk('admin.choirGroups.seats.status.trial'), tone: 'info' },
    active: { label: tk('admin.choirGroups.seats.status.active'), tone: 'good' },
    past_due: { label: tk('admin.choirGroups.seats.status.pastDue'), tone: 'warn' },
    canceled: { label: tk('admin.choirGroups.seats.status.canceled'), tone: 'warn' },
    lapsed: { label: tk('admin.choirGroups.seats.status.lapsed'), tone: 'bad' },
};

/**
 * A group's choir seats for staff (admin.groups.seats.show): the pack's state, who holds the seats, who is seated by
 * their own Plus and who waits, the director's moves this month, and Stripe's page for the renewal date. Staff can move
 * a seat (not counted against the director's 3 in 30 days) or fill the seats again from Stripe, each in sudo with a reason.
 */
const SeatsSheet = {
    components: { ChangeSheet, Field, Modal, StatusChip },
    props: { group: { type: Object, required: true }, me: { type: Object, required: true } },
    emits: ['close'],
    data: () => ({ seats: null, failed: false, acting: null, from: '', to: '', busy: false, errors: null }),
    setup: () => ({ SEAT_STATUS, formatNumber }),
    template: `
        <Modal :title="t('admin.choirGroups.seats.title', { name: group.name })" @close="$emit('close')">
            <div class="space-y-5" data-admin-seats>
                <p v-if="failed" class="text-bad" role="alert">{{ t('admin.choirGroups.seats.failed') }}</p>
                <p v-else-if="!seats" class="text-muted" role="status">{{ t('admin.loading') }}</p>
                <template v-else>
                    <div class="space-y-1">
                        <p class="flex flex-wrap items-center gap-2"><StatusChip :tone="(SEAT_STATUS[seats.status] ?? SEAT_STATUS.none).tone" :label="t((SEAT_STATUS[seats.status] ?? SEAT_STATUS.none).label)" :data-admin-seats-status="seats.status" /><span class="font-semibold tabular-nums text-cream" data-admin-seats-used>{{ t('admin.choirGroups.seats.used', { used: formatNumber(seats.used), bought: formatNumber(seats.bought) }) }}</span></p>
                        <p class="text-sm text-muted">{{ t('admin.choirGroups.seats.director', { name: seats.group.director?.name ?? '' }) }}<template v-if="seats.startedAt"> · {{ t('admin.choirGroups.seats.since', { date: day(seats.startedAt) }) }}</template><template v-if="seats.endsAt"> · {{ t('admin.choirGroups.seats.ends', { date: day(seats.endsAt) }) }}</template></p>
                        <p class="text-sm text-muted" data-admin-seats-moves>{{ t('admin.choirGroups.seats.moves', { count: seats.movesThisMonth, left: seats.movesLeft }) }}<template v-if="seats.nextMoveAt">{{ ' ' + t('admin.choirGroups.seats.nextMove', { date: day(seats.nextMoveAt) }) }}</template></p>
                        <a v-if="seats.stripeUrl" :href="seats.stripeUrl" target="_blank" rel="noopener noreferrer" class="link inline-flex min-h-11 items-center text-sm" data-admin-seats-stripe>{{ t('admin.choirGroups.seats.stripe') }}</a>
                    </div>
                    <div v-for="list in lists" :key="list.key" class="space-y-1.5" :data-admin-seats-list="list.key">
                        <h3 class="eyebrow text-muted">{{ t(list.title, { count: list.people.length }) }}</h3>
                        <ul v-if="list.people.length" class="divide-y divide-line rounded-xl border border-line text-sm">
                            <li v-for="p in list.people" :key="p.id" class="flex items-center justify-between gap-3 px-3 py-2"><span class="min-w-0 truncate text-cream" translate="no">{{ p.name }}</span><span v-if="p.since" class="shrink-0 text-xs text-muted">{{ day(p.since) }}</span></li>
                        </ul>
                        <p v-else class="text-sm text-muted">{{ t('admin.choirGroups.seats.nobody') }}</p>
                    </div>
                    <div class="flex flex-wrap justify-end gap-3">
                        <button type="button" class="btn-secondary btn-sm" data-admin-seats-sync @click="acting = 'sync'">{{ t('admin.choirGroups.seats.sync') }}</button>
                        <button v-if="seats.holders.length && seats.unseated.length" type="button" class="btn-secondary btn-sm" data-admin-seats-move @click="openMove">{{ t('admin.choirGroups.seats.move') }}</button>
                    </div>
                </template>
            </div>
        </Modal>
        <ChangeSheet v-if="acting" :title="acting === 'move' ? t('admin.choirGroups.seats.moveTitle') : t('admin.choirGroups.seats.syncTitle')" :me="me" :busy="busy" :errors="errors" :fields="['from', 'to']" :confirm-label="acting === 'move' ? t('admin.choirGroups.seats.moveGo') : t('admin.choirGroups.seats.syncGo')" @close="acting = null; errors = null" @confirm="act">
            <template v-if="acting === 'move'">
                <Field :label="t('admin.choirGroups.seats.from')" :error="errors?.from" required v-slot="f">
                    <select :id="f.id" v-model="from" class="input" required data-admin-seats-from><option v-for="p in seats.holders" :key="p.id" :value="p.id" translate="no">{{ p.name }}</option></select>
                </Field>
                <Field :label="t('admin.choirGroups.seats.to')" :error="errors?.to" required v-slot="f">
                    <select :id="f.id" v-model="to" class="input" required data-admin-seats-to><option v-for="p in seats.unseated" :key="p.id" :value="p.id" translate="no">{{ p.name }}</option></select>
                </Field>
                <p class="text-sm text-muted">{{ t('admin.choirGroups.seats.moveHint') }}</p>
            </template>
            <p v-else class="text-sm text-muted">{{ t('admin.choirGroups.seats.syncHint') }}</p>
        </ChangeSheet>`,
    computed: {
        lists() {
            const s = this.seats;
            return [
                { key: 'holders', title: tk('admin.choirGroups.seats.holders'), people: s.holders ?? [] },
                { key: 'own', title: tk('admin.choirGroups.seats.own'), people: s.own ?? [] },
                { key: 'unseated', title: tk('admin.choirGroups.seats.unseated'), people: s.unseated ?? [] },
            ];
        },
    },
    created() {
        this.load();
    },
    methods: {
        day: (iso) => formatDate(iso, { dateStyle: 'medium' }),
        async load() {
            try {
                this.seats = (await api.admin.groups.seats.show(this.group.id)).data;
            } catch (error) {
                console.warn('The group seats did not load', error);
                this.failed = true;
            }
        },
        openMove() {
            this.from = this.seats.holders[0]?.id ?? '';
            this.to = this.seats.unseated[0]?.id ?? '';
            this.acting = 'move';
        },
        async act(reason) {
            this.busy = true;
            this.errors = null;
            try {
                const body = this.acting === 'move' ? { action: 'move', from: this.from, to: this.to, reason } : { action: 'sync', reason };
                this.seats = (await api.admin.groups.seats.update(this.group.id, body)).data;
                toast(this.acting === 'move' ? t('admin.choirGroups.seats.moved') : t('admin.choirGroups.seats.synced'));
                this.acting = null;
            } catch (error) {
                const fields = errorsFrom(error);
                if (fields) this.errors = fields;
                else {
                    refused(error);
                    this.errors = {};
                }
            } finally {
                this.busy = false;
            }
        },
    },
};

export const ChoirGroupsSection = {
    components: { LoadFailed, SeatsSheet, StatusChip, WeekBars },
    props: { me: { type: Object, required: true } },
    data: () => ({ report: null, failed: false, seatsOf: null }),
    setup: () => ({ formatNumber, zoneName }),
    template: `
        <section class="space-y-6" aria-labelledby="choir-groups-h" data-choir-groups>
            <div>
                <h1 id="choir-groups-h" class="t-title text-cream">{{ t('admin.choirGroups.title') }}</h1>
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
                                <th scope="row" class="py-2.5 pe-2 text-start font-normal"><span class="flex flex-wrap items-center gap-x-2 font-semibold text-cream">{{ g.name }}<StatusChip v-if="paused(g)" tone="muted" :label="t('admin.choirGroups.paused')" data-paused /></span><span class="block text-xs text-muted">{{ t('admin.choirGroups.started', { date: formatDate(g.createdAt, { dateStyle: 'medium' }) }) }}</span><button type="button" class="link inline-flex min-h-11 items-center text-xs" :data-admin-seats-open="g.id" @click="seatsOf = g">{{ t('admin.choirGroups.seats.open') }}</button></th>
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
                                <button type="button" class="link inline-flex min-h-11 items-center text-sm" :data-admin-seats-open="g.id" @click="seatsOf = g">{{ t('admin.choirGroups.seats.open') }}</button>
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
            <SeatsSheet v-if="seatsOf" :group="seatsOf" :me="me" @close="seatsOf = null" />
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
