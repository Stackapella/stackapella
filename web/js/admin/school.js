// Admin > People > School (docs/school-offer-plan.md, "Admin sees everything needed against misuse"):
// every class code with its teacher, seats used of seats and dates; a code's page lists who redeemed
// it and when, with Close for the code and End for one student's school Plus (both audited, in
// sudo, with a reason). Teachers never see any of this; only Admin does.

import { nextTick } from 'vue';
import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { ChangeSheet, errorsFrom, LoadFailed, refused, screen } from './common.js';
import { toast } from '../components/ui.js';
import { classState } from '../views/school.js';

// What each change says before (the sheet) and after (a toast).
const BODIES = { close: tk('admin.school.closeBody'), end: tk('admin.school.endBody'), approve: tk('admin.school.approveBody'), seats: tk('admin.school.seatsBody'), partnerSeats: tk('admin.school.partnerSeatsBody') };
const DONE = { close: tk('admin.school.closed'), end: tk('admin.school.ended'), approve: tk('admin.school.approvedToast'), seats: tk('admin.school.seatsToast'), partnerSeats: tk('admin.school.seatsToast') };
const STATES = { pending: tk('school.state.pending'), open: tk('school.state.open'), full: tk('school.state.full'), closed: tk('school.state.closed'), expired: tk('school.state.expired') };

export const SchoolSection = {
    components: { ChangeSheet, LoadFailed },
    props: { me: { type: Object, required: true } },
    data: () => ({ codes: null, failed: false, chosen: null, detail: null, detailFailed: false, acting: null, busy: false, errors: null, seatAllowance: 300, partnerSeats: 30 }),
    setup: () => ({ STATES, classState, formatNumber }),
    template: `
        <section class="space-y-6" aria-labelledby="school-h" data-admin-school>
            <div>
                <h1 id="school-h" class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.school.title') }}</h1>
                <p class="mt-1 max-w-prose text-sm text-muted">{{ t('admin.school.intro') }}</p>
            </div>
            <LoadFailed v-if="failed" :message="t('admin.school.failed')" @retry="load" />
            <p v-else-if="!codes" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <p v-else-if="!codes.length" class="text-muted" data-school-empty>{{ t('admin.school.none') }}</p>
            <!-- A phone: a card a code (Hillel approves teachers from his phone), Approve right on a waiting teacher's. -->
            <ul v-else-if="phone" class="space-y-3" data-school-cards>
                <li v-for="c in codes" :key="c.id" class="card space-y-2 p-4" :data-school-code="c.code">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <button type="button" class="link tap-44 font-mono text-base tracking-wider" translate="no" @click="pick(c)">{{ c.code }}</button>
                            <p class="text-xs text-muted" translate="no">{{ c.name }}</p>
                        </div>
                        <p class="shrink-0 text-end text-sm text-cream">{{ t(STATES[classState(c)]) }}<span class="block tabular-nums text-xs text-muted">{{ t('admin.school.seatsOf', { used: formatNumber(c.used ?? 0), seats: formatNumber(c.seats) }) }}</span></p>
                    </div>
                    <p v-if="c.kind === 'partner'" class="text-sm text-cream" data-school-partner><span class="text-muted">{{ t('admin.school.partner') }}</span> <span translate="no">{{ c.partner?.name ?? '' }}</span></p>
                    <p v-else class="text-sm text-cream" translate="no">{{ c.teacher?.name ?? t('admin.school.noTeacher') }}<span v-if="c.teacher?.email" class="block break-all text-xs text-muted">{{ c.teacher.email }}</span></p>
                    <p class="text-xs text-muted">{{ day(c.createdAt) }}</p>
                    <div v-if="c.teacher && c.teacher.approved === false" class="flex flex-wrap items-center gap-3 pt-1">
                        <span class="rounded-md border border-warn px-1.5 py-0.5 text-xs text-warn" data-teacher-waiting>{{ t('admin.school.waiting') }}</span>
                        <button type="button" class="btn-primary btn-sm" data-school-approve-card @click="acting = { kind: 'approve', teacher: c.teacher }">{{ t('admin.school.approve') }}</button>
                    </div>
                </li>
            </ul>
            <div v-else class="card -mx-1 overflow-x-auto p-5 md:p-6">
                <table class="w-full text-sm">
                    <thead class="text-xs font-semibold uppercase tracking-wider text-muted">
                        <tr>
                            <th scope="col" class="pb-2 text-start font-semibold">{{ t('admin.school.col.code') }}</th>
                            <th scope="col" class="pb-2 ps-3 text-start font-semibold">{{ t('admin.school.col.teacher') }}</th>
                            <th scope="col" class="pb-2 ps-3 text-end font-semibold">{{ t('admin.school.col.seats') }}</th>
                            <th scope="col" class="pb-2 ps-3 text-start font-semibold">{{ t('admin.school.col.made') }}</th>
                            <th scope="col" class="pb-2 ps-3 text-start font-semibold">{{ t('admin.school.col.state') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in codes" :key="c.id" class="border-t border-line align-top" :data-school-code="c.code">
                            <td class="py-2"><button type="button" class="link font-mono tracking-wider" translate="no" @click="pick(c)">{{ c.code }}</button><span class="block text-xs text-muted" translate="no">{{ c.name }}</span></td>
                            <td v-if="c.kind === 'partner'" class="py-2 ps-3 text-cream" data-school-partner><span class="text-muted">{{ t('admin.school.partner') }}</span> <span translate="no">{{ c.partner?.name ?? '' }}</span></td>
                            <td v-else class="py-2 ps-3 text-cream" translate="no">{{ c.teacher?.name ?? t('admin.school.noTeacher') }}<span v-if="c.teacher?.email" class="block break-all text-xs text-muted">{{ c.teacher.email }}</span><span v-if="c.teacher && c.teacher.approved === false" class="mt-1 inline-block rounded-md border border-warn px-1.5 py-0.5 text-xs text-warn" data-teacher-waiting>{{ t('admin.school.waiting') }}</span></td>
                            <td class="py-2 ps-3 text-end tabular-nums text-cream">{{ t('admin.school.seatsOf', { used: formatNumber(c.used ?? 0), seats: formatNumber(c.seats) }) }}</td>
                            <td class="py-2 ps-3 whitespace-nowrap text-muted">{{ day(c.createdAt) }}</td>
                            <td class="py-2 ps-3 text-cream">{{ t(STATES[classState(c)]) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- One code: its students (account, when, until) and the two actions. -->
            <section v-if="chosen" class="card space-y-4 p-5 md:p-6" aria-labelledby="school-detail-h" data-school-detail>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="school-detail-h" class="font-mono text-xl font-bold tracking-wider text-cream" translate="no">{{ chosen.code }}</h2>
                        <p class="text-sm text-muted">{{ t('admin.school.detailLine', { name: chosen.name, made: day(chosen.createdAt), until: day(chosen.expiresAt) }) }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="chosen.kind === 'partner'" type="button" class="btn-secondary btn-sm" data-school-partner-seats @click="partnerSeats = chosen.seats; acting = { kind: 'partnerSeats', code: chosen }">{{ t('admin.school.changeSeats') }}</button>
                        <button v-if="classState(chosen) !== 'closed'" type="button" class="btn-secondary btn-sm" data-school-close @click="acting = { kind: 'close', code: chosen }">{{ t('admin.school.close') }}</button>
                    </div>
                </div>
                <!-- The teacher: approved or waiting (codes work only once approved), and their lifetime seats. -->
                <div v-if="chosen.teacher" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line p-4" data-school-teacher>
                    <div class="min-w-0 text-sm">
                        <p class="font-semibold text-cream" translate="no">{{ chosen.teacher.name }} <span class="font-normal text-muted">{{ chosen.teacher.email }}</span></p>
                        <p class="text-muted">{{ chosen.teacher.approved ? t('admin.school.approved') : t('admin.school.waitingLong') }} · {{ t('admin.school.allowance', { taken: formatNumber(chosen.teacher.seatsTaken ?? 0), allowance: formatNumber(chosen.teacher.seatAllowance ?? 0) }) }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="!chosen.teacher.approved" type="button" class="btn-primary btn-sm" data-school-approve @click="acting = { kind: 'approve', teacher: chosen.teacher }">{{ t('admin.school.approve') }}</button>
                        <button type="button" class="btn-secondary btn-sm" data-school-seats @click="seatAllowance = (chosen.teacher.seatAllowance ?? 300) + 100; acting = { kind: 'seats', teacher: chosen.teacher }">{{ t('admin.school.moreSeats') }}</button>
                    </div>
                </div>
                <p v-if="detailFailed" class="text-muted">{{ t('admin.school.failed') }}</p>
                <p v-else-if="!detail" class="text-muted" role="status">{{ t('admin.loading') }}</p>
                <p v-else-if="!detail.redemptions?.length" class="text-muted">{{ t('admin.school.noStudents') }}</p>
                <ul v-else class="divide-y divide-line text-sm">
                    <li v-for="r in detail.redemptions" :key="r.id" class="flex flex-wrap items-center justify-between gap-3 py-2.5" :data-redemption="r.id">
                        <span class="min-w-0"><span class="block text-cream" translate="no">{{ r.user?.name ?? t('admin.school.deleted') }}</span><span class="block text-xs text-muted">{{ r.endedAt ? t('admin.school.endedOn', { date: day(r.endedAt) }) : t('admin.school.redeemed', { at: day(r.at), until: day(r.plusUntil) }) }}</span></span>
                        <button v-if="!r.endedAt" type="button" class="tap-44 btn-ghost btn-sm" data-school-end @click="acting = { kind: 'end', redemption: r }">{{ t('admin.school.end') }}</button>
                    </li>
                </ul>
            </section>

            <ChangeSheet v-if="acting" :title="sheetTitle" :me="me" :busy="busy" :errors="errors" :fields="['seatAllowance', 'seats']" @close="acting = null; errors = null" @confirm="act">
                <p class="text-sm text-muted">{{ sheetBody }}</p>
                <label v-if="acting.kind === 'seats'" class="block space-y-1.5"><span class="block text-sm font-semibold text-cream">{{ t('admin.school.allowanceLabel') }}</span>
                    <input v-model.number="seatAllowance" type="number" min="0" max="5000" required inputmode="numeric" class="input w-40" data-school-allowance>
                    <span v-if="errors?.seatAllowance" class="block text-sm text-bad">{{ errors.seatAllowance.join(' ') }}</span></label>
                <label v-if="acting.kind === 'partnerSeats'" class="block space-y-1.5"><span class="block text-sm font-semibold text-cream">{{ t('admin.school.partnerSeatsLabel', { used: formatNumber(acting.code.used ?? 0) }) }}</span>
                    <input v-model.number="partnerSeats" type="number" :min="minSeats" max="100000" required inputmode="numeric" class="input w-40" data-school-partner-seats-input>
                    <span v-if="errors?.seats" class="block text-sm text-bad">{{ errors.seats.join(' ') }}</span></label>
            </ChangeSheet>
        </section>`,
    computed: {
        phone: () => screen.phone,
        /** A partner code's seats never go under those used. */
        minSeats() {
            return Math.max(1, this.acting?.code?.used ?? 0);
        },
        sheetTitle() {
            const a = this.acting;
            if (a.kind === 'close') return t('admin.school.closeTitle', { code: a.code.code });
            if (a.kind === 'end') return t('admin.school.endTitle', { name: a.redemption.user?.name ?? '' });
            if (a.kind === 'approve') return t('admin.school.approveTitle', { name: a.teacher.name ?? '' });
            if (a.kind === 'partnerSeats') return t('admin.school.partnerSeatsTitle', { code: a.code.code });
            return t('admin.school.seatsTitle', { name: a.teacher.name ?? '' });
        },
        sheetBody() {
            return t(BODIES[this.acting.kind]);
        },
    },
    created() {
        this.load();
    },
    methods: {
        day(at) {
            return at ? formatDate(at, { dateStyle: 'medium' }) : '-';
        },
        async load() {
            this.failed = false;
            try {
                this.codes = (await api.admin.school.list()).data;
            } catch (error) {
                console.warn('The class codes did not load', error);
                this.failed = true;
            }
        },
        /** A code tapped: its detail, scrolled to on a phone (it opens under the list). */
        async pick(c) {
            await this.open(c);
            await nextTick();
            if (this.phone) document.querySelector('[data-school-detail]')?.scrollIntoView({ block: 'start' });
        },
        async open(c) {
            this.chosen = c;
            this.detail = null;
            this.detailFailed = false;
            try {
                this.detail = (await api.admin.school.show(c.id)).data;
                this.chosen = { ...c, ...this.detail };
            } catch (error) {
                console.warn('The class code did not load', error);
                this.detailFailed = true;
            }
        },
        async act(reason) {
            this.busy = true;
            this.errors = null;
            try {
                const { kind } = this.acting;
                if (kind === 'close') await api.admin.school.update(this.acting.code.id, { action: 'close', reason });
                else if (kind === 'end') await api.admin.school.endRedemption(this.acting.redemption.id, { reason });
                else if (kind === 'approve') await api.admin.school.updateTeacher(this.acting.teacher.id, { approved: true, reason });
                else if (kind === 'partnerSeats') await api.admin.school.update(this.acting.code.id, { action: 'seats', seats: this.partnerSeats, reason });
                else await api.admin.school.updateTeacher(this.acting.teacher.id, { seatAllowance: this.seatAllowance, reason });
                toast(t(DONE[kind]));
                this.acting = null;
                await this.load();
                if (this.chosen) await this.open(this.codes.find((c) => c.id === this.chosen.id) ?? this.chosen);
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
