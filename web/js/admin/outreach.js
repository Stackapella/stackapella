// Admin > Growth > Outreach (docs/outreach-plan.md; contract admin.outreach.*): one organization, one
// person, one email, sent by Hillel's own press. The list (filters, statuses, what needs him: follow-ups
// due and replies to read, replies no organization matched), an organization's page (its published
// contact and the page it came from, its fit and notes, the draft in plain text, Send with today's cap
// and the do-not-contact list shown, a test to himself, the follow-up when it's due, the replies, the
// outcome, a partner code), and Results by group and angle from the anonymous ?ref= counts. Never a
// batch, never a person tracked. Admin only; works on a phone.

import { ApiError, api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { navigate, route } from '../router.js';
import { EmptyState, Field, Notice, StatusChip } from '../components/form.js';
import { Icon } from '../components/icon.js';
import { Modal, toast } from '../components/ui.js';
import { ChangeSheet, LoadFailed, errorsFrom, refused, screen, when } from './common.js';

const STATUSES = ['to_research', 'ready', 'drafted', 'sent', 'replied', 'partner', 'closed', 'do_not_contact'];
const STATUS = {
    to_research: { tone: 'muted', label: tk('admin.outreach.status.toResearch') },
    ready: { tone: 'info', label: tk('admin.outreach.status.ready') },
    drafted: { tone: 'info', label: tk('admin.outreach.status.drafted') },
    sent: { tone: 'muted', label: tk('admin.outreach.status.sent') },
    replied: { tone: 'good', label: tk('admin.outreach.status.replied') },
    partner: { tone: 'good', label: tk('admin.outreach.status.partner') },
    closed: { tone: 'muted', label: tk('admin.outreach.status.closed') },
    do_not_contact: { tone: 'bad', label: tk('admin.outreach.status.doNotContact') },
};
const ANGLES = { school: tk('admin.outreach.angle.school'), choir_groups: tk('admin.outreach.angle.choirGroups'), course: tk('admin.outreach.angle.course'), practice_tool: tk('admin.outreach.angle.practiceTool') };
const OUTCOMES = { interested: tk('admin.outreach.outcome.interested'), joined: tk('admin.outreach.outcome.joined'), declined: tk('admin.outreach.outcome.declined'), no_reply: tk('admin.outreach.outcome.noReply'), other: tk('admin.outreach.outcome.other') };
const REASONS = { no_thanks: tk('admin.outreach.reason.noThanks'), unsubscribe: tk('admin.outreach.reason.unsubscribe'), complaint: tk('admin.outreach.reason.complaint'), bounce: tk('admin.outreach.reason.bounce'), manual: tk('admin.outreach.reason.manual') };
// What a refused Send (or mark sent, or a new draft) means, in plain words.
const REFUSALS = {
    outreach_off: tk('admin.outreach.refused.off'),
    outreach_daily_cap: tk('admin.outreach.refused.cap'),
    outreach_suppressed: tk('admin.outreach.refused.suppressed'),
    outreach_follow_up_not_due: tk('admin.outreach.refused.notDue'),
    outreach_already_sent: tk('admin.outreach.refused.alreadySent'),
    outreach_no_address: tk('admin.outreach.refused.noAddress'),
    outreach_not_a_draft: tk('admin.outreach.refused.notDraft'),
    outreach_send_failed: tk('admin.outreach.refused.failed'),
    outreach_no_admin_email: tk('admin.outreach.refused.noAdminEmail'),
    outreach_test_limit: tk('admin.outreach.refused.testLimit'),
};
const RESULT_COLUMNS = [
    { key: 'organizations', label: tk('admin.outreach.results.col.organizations') },
    { key: 'emailed', label: tk('admin.outreach.results.col.emailed') },
    { key: 'replied', label: tk('admin.outreach.results.col.replied') },
    { key: 'responseRate', label: tk('admin.outreach.results.col.rate'), rate: true },
    { key: 'positive', label: tk('admin.outreach.results.col.positive') },
    { key: 'bounced', label: tk('admin.outreach.results.col.bounced') },
    { key: 'arrivals', label: tk('admin.outreach.results.col.arrivals'), counted: true },
    { key: 'lesson1', label: tk('admin.outreach.results.col.lesson1'), counted: true },
    { key: 'signedUp', label: tk('admin.outreach.results.col.signedUp'), counted: true },
    { key: 'schoolCodes', label: tk('admin.outreach.results.col.schoolCodes'), counted: true },
    { key: 'partners', label: tk('admin.outreach.results.col.partners') },
    { key: 'redemptions', label: tk('admin.outreach.results.col.redemptions') },
];

/** A refusal said in its own words when it's one of outreach's, else the admin's usual way. */
function outreachRefused(error) {
    const code = error instanceof ApiError ? error.code : null;
    if (code && REFUSALS[code]) toast(t(REFUSALS[code]), { tone: 'error', seconds: 10 });
    else refused(error);
}

const day = (at) => (at ? formatDate(at, { dateStyle: 'medium' }) : '');

// ---- The list ---------------------------------------------------------------------------------------

const OutreachList = {
    components: { EmptyState, Field, Icon, LoadFailed, Notice, StatusChip },
    props: { me: { type: Object, required: true } },
    data: () => ({ rows: null, summary: null, unfiled: [], failed: false, status: '', group: '', angle: '', q: '', adding: false, add: blank(), busy: false, errors: null, imported: null, filing: {} }),
    setup: () => ({ STATUS, STATUSES, ANGLES, screen, day, formatNumber, when }),
    template: `
        <div class="space-y-6" data-outreach>
            <!-- What needs you, and today's sending: the cap and whether sending is set up. -->
            <section v-if="summary" class="card space-y-3 p-5" aria-labelledby="outreach-today-h" data-outreach-summary>
                <h2 id="outreach-today-h" class="sr-only">{{ t('admin.outreach.today') }}</h2>
                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <p class="text-cream"><span class="font-bold tabular-nums">{{ t('admin.outreach.sentToday', { sent: formatNumber(summary.sentToday), cap: formatNumber(summary.cap) }) }}</span></p>
                    <button type="button" class="link tap-44" :class="summary.followUpsDue ? '' : 'pointer-events-none no-underline text-muted'" data-follow-ups @click="status = 'follow_up_due'; load()">{{ t('admin.outreach.followUpsDue', { count: summary.followUpsDue }) }}</button>
                    <span :class="summary.unreadReplies ? 'font-semibold text-cream' : 'text-muted'" data-unread-replies>{{ t('admin.outreach.unreadReplies', { count: summary.unreadReplies }) }}</span>
                    <span v-if="summary.sendBySoon" class="font-semibold text-warn" data-send-by-soon>{{ t('admin.outreach.sendBySoon', { count: summary.sendBySoon }) }}</span>
                </div>
                <Notice v-if="!summary.configured" tone="warn" data-outreach-off>{{ t('admin.outreach.notConfigured') }}</Notice>
            </section>

            <!-- Replies no organization matched: filed by hand. -->
            <section v-if="unfiled.length" class="card space-y-3 p-5" aria-labelledby="outreach-unfiled-h" data-outreach-unfiled>
                <h2 id="outreach-unfiled-h" class="font-bold text-cream">{{ t('admin.outreach.unfiled', { count: unfiled.length }) }}</h2>
                <ul class="divide-y divide-line">
                    <li v-for="r in unfiled" :key="r.id" class="space-y-2 py-3 first:pt-0">
                        <p class="text-sm"><span class="font-semibold text-cream" translate="no">{{ r.fromEmail }}</span> <span class="text-muted">· {{ when(r.receivedAt) }}</span></p>
                        <p class="text-sm text-cream" translate="no">{{ r.subject }}</p>
                        <p class="line-clamp-3 whitespace-pre-line text-sm text-muted" translate="no">{{ r.body }}</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <select v-model="filing[r.id]" class="input !w-auto !py-2" :aria-label="t('admin.outreach.fileTo')">
                                <option :value="undefined" disabled>{{ t('admin.outreach.fileTo') }}</option>
                                <option v-for="o in rows ?? []" :key="o.id" :value="o.id" translate="no">{{ o.name }}</option>
                            </select>
                            <button type="button" class="btn-secondary btn-sm" :disabled="!filing[r.id] || busy" @click="file(r)">{{ t('admin.outreach.file') }}</button>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Filters -->
            <form class="flex flex-wrap items-end gap-3" role="search" @submit.prevent="load">
                <label class="block min-w-0 flex-1 space-y-1.5 sm:max-w-xs"><span class="label">{{ t('admin.outreach.search') }}</span><input v-model="q" type="search" class="input" :placeholder="t('admin.outreach.searchHint')" /></label>
                <label class="block space-y-1.5"><span class="label">{{ t('admin.outreach.statusLabel') }}</span>
                    <select v-model="status" class="input !w-auto" @change="load"><option value="">{{ t('admin.outreach.all') }}</option><option value="follow_up_due">{{ t('admin.outreach.status.followUpDue') }}</option><option v-for="s in STATUSES" :key="s" :value="s">{{ t(STATUS[s].label) }}</option></select>
                </label>
                <label class="block space-y-1.5"><span class="label">{{ t('admin.outreach.angleLabel') }}</span>
                    <select v-model="angle" class="input !w-auto" @change="load"><option value="">{{ t('admin.outreach.all') }}</option><option v-for="(label, a) in ANGLES" :key="a" :value="a">{{ t(label) }}</option></select>
                </label>
                <label v-if="groups.length" class="block space-y-1.5"><span class="label">{{ t('admin.outreach.groupLabel') }}</span>
                    <select v-model="group" class="input !w-auto" @change="load"><option value="">{{ t('admin.outreach.all') }}</option><option v-for="g in groups" :key="g" :value="g" translate="no">{{ g }}</option></select>
                </label>
                <button type="submit" class="btn-secondary">{{ t('admin.outreach.find') }}</button>
            </form>

            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!rows" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!rows.length" icon="list" :title="t('admin.outreach.none')" :body="t('admin.outreach.noneBody')" />
            <!-- A phone: a card an organization; wider: a table. -->
            <ul v-else-if="screen.phone" class="space-y-3" data-outreach-cards>
                <li v-for="o in rows" :key="o.id">
                    <a :href="'/admin/outreach/' + o.id" class="card-interactive block space-y-1.5 p-4" :data-outreach-row="o.id" @click.prevent="navigate('/admin/outreach/' + o.id)">
                        <span class="flex items-start justify-between gap-3"><span class="font-bold text-cream" translate="no">{{ o.name }}</span><StatusChip :tone="STATUS[o.status]?.tone ?? 'muted'" :label="t(STATUS[o.status]?.label ?? STATUS.ready.label)" /></span>
                        <span class="block text-sm text-muted" translate="no">{{ [o.group, o.country, o.reach].filter(Boolean).join(' · ') }}</span>
                        <span v-if="o.followUpDue || o.unreadReplies || o.sendBySoon || o.pastSendBy" class="flex flex-wrap gap-2">
                            <StatusChip v-if="o.pastSendBy" tone="bad" :label="t('admin.outreach.pastSendBy')" />
                            <StatusChip v-else-if="o.sendBySoon" tone="warn" :label="t('admin.outreach.sendByDate', { date: day(o.sendBy) })" />
                            <StatusChip v-if="o.followUpDue" tone="warn" :label="t('admin.outreach.status.followUpDue')" />
                            <StatusChip v-if="o.unreadReplies" tone="info" :label="t('admin.outreach.unreadReplies', { count: o.unreadReplies })" />
                        </span>
                    </a>
                </li>
            </ul>
            <div v-else class="card overflow-x-auto">
                <table class="data-table" data-outreach-table>
                    <thead><tr><th>{{ t('admin.outreach.col.name') }}</th><th>{{ t('admin.outreach.col.group') }}</th><th>{{ t('admin.outreach.angleLabel') }}</th><th>{{ t('admin.outreach.statusLabel') }}</th><th>{{ t('admin.outreach.col.lastSent') }}</th></tr></thead>
                    <tbody>
                        <tr v-for="o in rows" :key="o.id" :data-outreach-row="o.id">
                            <td><a :href="'/admin/outreach/' + o.id" class="link font-semibold" translate="no" @click.prevent="navigate('/admin/outreach/' + o.id)">{{ o.name }}</a><span class="block text-xs text-muted" translate="no">{{ [o.country, o.reach].filter(Boolean).join(' · ') }}</span></td>
                            <td class="text-cream" translate="no">{{ o.group }}</td>
                            <td class="text-muted">{{ t(ANGLES[o.angle] ?? ANGLES.course) }}</td>
                            <td><span class="flex flex-wrap gap-1.5"><StatusChip :tone="STATUS[o.status]?.tone ?? 'muted'" :label="t(STATUS[o.status]?.label ?? STATUS.ready.label)" /><StatusChip v-if="o.pastSendBy" tone="bad" :label="t('admin.outreach.pastSendBy')" /><StatusChip v-else-if="o.sendBySoon" tone="warn" :label="t('admin.outreach.sendByDate', { date: day(o.sendBy) })" /><StatusChip v-if="o.followUpDue" tone="warn" :label="t('admin.outreach.status.followUpDue')" /><StatusChip v-if="o.unreadReplies" tone="info" :label="t('admin.outreach.unreadReplies', { count: o.unreadReplies })" /></span></td>
                            <td class="whitespace-nowrap text-muted">{{ day(o.lastSentAt) || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Add one organization, or import the research file. -->
            <section class="grid gap-4 lg:grid-cols-2 lg:items-start">
                <details class="card p-5" :open="adding" @toggle="adding = $event.target.open" data-outreach-add>
                    <summary class="cursor-pointer py-1 font-bold text-cream">{{ t('admin.outreach.add') }}</summary>
                    <form class="mt-4 space-y-4" @submit.prevent="create">
                        <Field :label="t('admin.outreach.field.name')" :error="errors?.name" required v-slot="f"><input :id="f.id" v-model="add.name" class="input" required maxlength="200" /></Field>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <Field :label="t('admin.outreach.field.group')" :help="t('admin.outreach.field.groupHelp')" :error="errors?.group" v-slot="f"><input :id="f.id" v-model="add.group" class="input" maxlength="60" /></Field>
                            <Field :label="t('admin.outreach.angleLabel')" :error="errors?.angle" required v-slot="f"><select :id="f.id" v-model="add.angle" class="input"><option v-for="(label, a) in ANGLES" :key="a" :value="a">{{ t(label) }}</option></select></Field>
                            <Field :label="t('admin.outreach.field.country')" :error="errors?.country" v-slot="f"><input :id="f.id" v-model="add.country" class="input" maxlength="2" :placeholder="'US'" /></Field>
                            <Field :label="t('admin.outreach.field.reach')" :error="errors?.reach" v-slot="f"><input :id="f.id" v-model="add.reach" class="input" maxlength="100" /></Field>
                        </div>
                        <Field :label="t('admin.outreach.field.fit')" :help="t('admin.outreach.field.fitHelp')" :error="errors?.fit" v-slot="f"><textarea :id="f.id" v-model="add.fit" class="input" rows="2" maxlength="1000"></textarea></Field>
                        <Field :label="t('admin.outreach.field.contact')" :help="t('admin.outreach.field.contactHelp')" :error="errors?.contact" v-slot="f"><input :id="f.id" v-model="add.contact" class="input" maxlength="300" /></Field>
                        <Field :label="t('admin.outreach.field.source')" :help="t('admin.outreach.field.sourceHelp')" :error="errors?.source" :required="!!add.contact" v-slot="f"><input :id="f.id" v-model="add.source" type="url" class="input" maxlength="500" :required="!!add.contact" /></Field>
                        <button type="submit" class="btn-primary" :disabled="busy">{{ busy ? t('common.saving') : t('admin.outreach.addButton') }}</button>
                    </form>
                </details>
                <div class="card space-y-3 p-5" data-outreach-import>
                    <p class="font-bold text-cream">{{ t('admin.outreach.import') }}</p>
                    <p class="text-sm text-muted">{{ t('admin.outreach.importHelp') }}</p>
                    <label class="btn-secondary w-fit cursor-pointer"><input type="file" accept=".json,application/json" class="sr-only" :disabled="busy" @change="importFile" /> {{ t('admin.outreach.chooseFile') }}</label>
                    <div v-if="imported" class="space-y-1 text-sm" role="status" data-outreach-imported>
                        <p class="text-cream">{{ t('admin.outreach.imported', { added: formatNumber(imported.added), updated: formatNumber(imported.updated) }) }}</p>
                        <ul v-if="imported.skipped?.length" class="list-disc space-y-0.5 ps-5 text-muted">
                            <li v-for="s in imported.skipped" :key="s.row"><span translate="no">{{ t('admin.outreach.skippedRow', { row: s.row, name: s.name ?? '' }) }}</span> {{ s.reason }}</li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>`,
    computed: {
        groups() {
            return [...new Set((this.rows ?? []).map((o) => o.group).filter(Boolean))].sort();
        },
    },
    created() {
        const q = new URLSearchParams(location.search);
        if (q.get('status')) this.status = q.get('status');
        this.load();
    },
    methods: {
        navigate,
        async load() {
            this.failed = false;
            try {
                const answer = await api.admin.outreach.list({ status: this.status || undefined, group: this.group || undefined, angle: this.angle || undefined, q: this.q.trim() || undefined });
                this.rows = answer.data;
                this.summary = answer.summary ?? null;
                this.unfiled = answer.unfiled ?? [];
            } catch (error) {
                console.warn('Outreach did not load', error);
                this.failed = t('admin.outreach.failed');
            }
        },
        async create() {
            this.busy = true;
            this.errors = null;
            try {
                const fields = Object.fromEntries(Object.entries(this.add).map(([k, v]) => [k, typeof v === 'string' ? v.trim() || null : v]));
                const made = (await api.admin.outreach.create(fields)).data;
                toast(t('admin.outreach.added', { name: made.name }));
                this.add = blank();
                this.adding = false;
                navigate('/admin/outreach/' + made.id);
            } catch (error) {
                this.errors = errorsFrom(error);
                if (!this.errors) outreachRefused(error);
            } finally {
                this.busy = false;
            }
        },
        /** The research file: { rows: [...] } or the rows themselves, read here and sent as rows. */
        async importFile(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (!file) return;
            this.busy = true;
            try {
                const parsed = JSON.parse(await file.text());
                const rows = Array.isArray(parsed) ? parsed : parsed.rows;
                if (!Array.isArray(rows)) throw new SyntaxError('no rows');
                this.imported = (await api.admin.outreach.import(rows)).data;
                await this.load();
            } catch (error) {
                if (error instanceof SyntaxError) toast(t('admin.outreach.importUnreadable'), { tone: 'error' });
                else outreachRefused(error);
            } finally {
                this.busy = false;
            }
        },
        async file(reply) {
            this.busy = true;
            try {
                await api.admin.outreach.replies.read(reply.id, { targetId: this.filing[reply.id] });
                toast(t('admin.outreach.filed'));
                await this.load();
            } catch (error) {
                outreachRefused(error);
            } finally {
                this.busy = false;
            }
        },
    },
};

function blank() {
    return { name: '', group: '', angle: 'choir_groups', country: '', reach: '', fit: '', contact: '', source: '', notes: '' };
}

// ---- One organization -------------------------------------------------------------------------------

const OutreachTarget = {
    components: { ChangeSheet, Field, Icon, LoadFailed, Modal, Notice, StatusChip },
    props: { id: { type: String, required: true }, me: { type: Object, required: true } },
    data: () => ({ target: null, summary: null, failed: false, busy: false, stale: null, testOff: null, edits: {}, fit: '', notes: '', outcomeNote: '', asking: null, reply: { body: '', from: '', subject: '' }, logging: false, suppressReason: 'no_thanks', suppressNote: '', partner: { seats: 30, days: 365 }, errors: null }),
    setup: () => ({ STATUS, ANGLES, OUTCOMES, REASONS, day, formatNumber, when }),
    template: `
        <div class="space-y-6" data-outreach-target>
            <a href="/admin/outreach" class="btn-ghost btn-sm" @click.prevent="navigate('/admin/outreach')"><Icon name="back" :size="16" /> {{ t('admin.outreach.back') }}</a>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!target" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <header class="space-y-2">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <h1 class="text-2xl font-extrabold tracking-tight text-cream" translate="no">{{ target.name }}</h1>
                        <span class="flex flex-wrap gap-2"><StatusChip :tone="STATUS[target.status]?.tone ?? 'muted'" :label="t(STATUS[target.status]?.label ?? STATUS.ready.label)" /><StatusChip v-if="target.followUpDue" tone="warn" :label="t('admin.outreach.status.followUpDue')" /></span>
                    </div>
                    <p class="text-sm text-muted" translate="no">{{ [target.group, target.country, target.reach, t(ANGLES[target.angle] ?? ANGLES.course)].filter(Boolean).join(' · ') }}</p>
                    <p v-if="target.link" class="break-all text-sm"><span class="text-muted">{{ t('admin.outreach.theirLink') }}</span> <a :href="target.link" target="_blank" rel="noopener noreferrer" class="link tap-44" translate="no">{{ target.link }}</a></p>
                </header>
                <Notice v-if="target.suppressed || target.status === 'do_not_contact'" tone="bad" data-outreach-suppressed>{{ t('admin.outreach.suppressedNote') }}</Notice>
                <!-- The first email's send-by day (its wording is about something timely), and when a follow-up may go. -->
                <Notice v-else-if="target.pastSendBy && !sentFirst" tone="warn" data-past-send-by>{{ t('admin.outreach.pastSendByNote', { date: day(target.sendBy) }) }}</Notice>
                <p v-else-if="target.sendBy && !sentFirst" class="text-sm" :class="target.sendBySoon ? 'font-semibold text-warn' : 'text-muted'" data-send-by>{{ t('admin.outreach.sendByLine', { date: day(target.sendBy) }) }}</p>
                <p v-if="target.tooEarly && target.sendNotBefore && !sentFirst" class="text-sm font-semibold text-warn" data-too-early>{{ t('admin.outreach.heldUntil', { date: day(target.sendNotBefore) }) }}</p>
                <p v-if="target.followUpNotBefore && sentFirst && !followUpSent" class="text-sm text-muted">{{ t('admin.outreach.followUpNotBefore', { date: day(target.followUpNotBefore) }) }}</p>

                <div class="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:items-start">
                    <div class="space-y-6">
                        <!-- The emails: drafts to edit and send, then what was sent. -->
                        <section class="space-y-4" aria-labelledby="outreach-emails-h">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <h2 id="outreach-emails-h" class="text-lg font-bold text-cream">{{ t('admin.outreach.emails') }}</h2>
                                <p v-if="summary" class="text-sm tabular-nums text-muted" data-outreach-cap>{{ t('admin.outreach.sentToday', { sent: formatNumber(summary.sentToday), cap: formatNumber(summary.cap) }) }}</p>
                            </div>
                            <Notice v-if="summary && !summary.configured" tone="warn" data-outreach-off>{{ t('admin.outreach.notConfigured') }}</Notice>
                            <div v-if="!drafts.length && canWrite" class="flex flex-wrap gap-3">
                                <button v-if="!sentFirst" type="button" class="btn-primary" :disabled="busy" data-write-first @click="write()">{{ t('admin.outreach.writeFirst') }}</button>
                                <button v-else-if="target.followUpDue" type="button" class="btn-primary" :disabled="busy" data-write-follow-up @click="write('follow_up')">{{ t('admin.outreach.writeFollowUp') }}</button>
                                <p v-else-if="target.followUpFrom && !followUpSent" class="text-sm text-muted">{{ t('admin.outreach.followUpFrom', { date: day(target.followUpFrom) }) }}</p>
                            </div>
                            <article v-for="m in drafts" :key="m.id" class="card space-y-3 p-5" :data-draft="m.id">
                                <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted">{{ m.kind === 'follow_up' ? t('admin.outreach.followUpDraft') : t('admin.outreach.firstDraft') }}</p>
                                <label class="block space-y-1.5"><span class="label">{{ t('admin.outreach.subject') }}</span><input v-model="edit(m).subject" class="input" maxlength="300" data-draft-subject /></label>
                                <label class="block space-y-1.5"><span class="label">{{ t('admin.outreach.body') }}</span><textarea v-model="edit(m).body" class="input min-h-64 font-[inherit] leading-relaxed" rows="14" maxlength="10000" data-draft-body></textarea></label>
                                <p class="help">{{ t('admin.outreach.plainText') }}</p>
                                <p class="text-sm text-muted">{{ target.contact?.email ? t('admin.outreach.to', { to: target.contact.email }) : t('admin.outreach.formOnly') }}</p>
                                <!-- Marketing's note on when to send (some are checks to make on the day), next to Send. -->
                                <p v-if="target.timingNote" class="rounded-xl bg-raised p-3 text-sm text-cream" data-timing-note><span class="font-semibold">{{ t('admin.outreach.timingNote') }}</span> <span translate="no">{{ target.timingNote }}</span></p>
                                <!-- One primary: Send (or, for a contact form, Mark sent once it's sent there). -->
                                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                                    <button v-if="target.contact?.email" type="button" class="btn-primary" :disabled="busy || blocked(m)" data-draft-send @click="send(m)"><Icon name="arrow" :size="16" /> {{ t('admin.outreach.send') }}</button>
                                    <template v-else>
                                        <a v-if="target.contact?.formUrl" :href="target.contact.formUrl" target="_blank" rel="noopener noreferrer" class="btn-secondary">{{ t('admin.outreach.openForm') }}</a>
                                        <button type="button" class="btn-primary" :disabled="busy || blocked(m)" data-draft-mark-sent @click="markSent(m)">{{ t('admin.outreach.markSent') }}</button>
                                    </template>
                                    <button v-if="canTest" type="button" class="btn-secondary" :disabled="busy" data-draft-test @click="test(m)">{{ t('admin.outreach.test') }}</button>
                                    <!-- Right under the test on a phone; its own row under the buttons wider. -->
                                    <p v-if="canTest && ((summary && !summary.configured) || testOff === m.id)" class="text-sm text-muted sm:order-last sm:basis-full" data-test-off>{{ t('admin.outreach.testOff') }}</p>
                                    <button type="button" class="btn-ghost" :disabled="busy || !dirty(m)" @click="save(m)">{{ t('admin.outreach.saveDraft') }}</button>
                                    <button type="button" class="btn-ghost text-bad sm:ms-auto" :disabled="busy" @click="discard(m)">{{ t('admin.outreach.discard') }}</button>
                                </div>
                            </article>
                            <ul v-if="sent.length" class="card divide-y divide-line" data-outreach-sent>
                                <li v-for="m in sent" :key="m.id" class="space-y-1 p-4">
                                    <p class="flex flex-wrap items-center justify-between gap-2 text-sm"><span class="font-semibold text-cream" translate="no">{{ m.subject }}</span><span class="text-muted">{{ day(m.sentAt) }}</span></p>
                                    <p class="text-xs text-muted">{{ m.bouncedAt ? t('admin.outreach.bounced', { note: m.bounceNote ?? '' }) : m.deliveredAt ? t('admin.outreach.delivered') : m.sentVia === 'form' ? t('admin.outreach.viaForm') : t('admin.outreach.sentLine') }}</p>
                                    <details><summary class="link-quiet inline-flex min-h-11 cursor-pointer items-center text-sm">{{ t('admin.outreach.showEmail') }}</summary><p class="whitespace-pre-line text-sm text-cream" translate="no">{{ m.body }}</p></details>
                                </li>
                            </ul>
                        </section>

                        <!-- Their replies, and one logged by hand. -->
                        <section class="space-y-3" aria-labelledby="outreach-replies-h">
                            <h2 id="outreach-replies-h" class="text-lg font-bold text-cream">{{ t('admin.outreach.replies') }}</h2>
                            <p v-if="!target.replyLog?.length" class="text-sm text-muted">{{ t('admin.outreach.noReplies') }}</p>
                            <ul v-else class="space-y-3">
                                <li v-for="r in target.replyLog" :key="r.id" class="card space-y-2 p-4" :data-reply="r.id">
                                    <p class="flex flex-wrap items-center justify-between gap-2 text-sm"><span class="font-semibold text-cream" translate="no">{{ r.fromEmail }}</span><span class="text-muted">{{ when(r.receivedAt) }}</span></p>
                                    <p v-if="r.subject" class="text-sm text-cream" translate="no">{{ r.subject }}</p>
                                    <p class="whitespace-pre-line text-sm text-cream" translate="no">{{ r.body }}</p>
                                    <button v-if="!r.readAt" type="button" class="btn-secondary btn-sm" :disabled="busy" data-reply-read @click="read(r)">{{ t('admin.outreach.markRead') }}</button>
                                </li>
                            </ul>
                            <details class="card p-4" :open="logging" @toggle="logging = $event.target.open">
                                <summary class="cursor-pointer py-1 text-sm font-semibold text-cream">{{ t('admin.outreach.logReply') }}</summary>
                                <form class="mt-3 space-y-3" @submit.prevent="logReply">
                                    <label class="block space-y-1.5"><span class="label">{{ t('admin.outreach.replyBody') }}</span><textarea v-model="reply.body" class="input" rows="4" required maxlength="10000"></textarea></label>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <label class="block space-y-1.5"><span class="label">{{ t('admin.outreach.replyFrom') }}</span><input v-model="reply.from" type="email" class="input" :placeholder="target.contact?.email ?? ''" /></label>
                                        <label class="block space-y-1.5"><span class="label">{{ t('admin.outreach.subject') }}</span><input v-model="reply.subject" class="input" maxlength="300" /></label>
                                    </div>
                                    <p class="help">{{ t('admin.outreach.replyNoHelp') }}</p>
                                    <button type="submit" class="btn-secondary" :disabled="busy || !reply.body.trim()">{{ t('admin.outreach.logReplyButton') }}</button>
                                </form>
                            </details>
                        </section>
                    </div>

                    <aside class="space-y-6">
                        <!-- Their published contact and where it was published. -->
                        <section class="card space-y-2 p-5 text-sm" aria-labelledby="outreach-contact-h">
                            <h2 id="outreach-contact-h" class="font-bold text-cream">{{ t('admin.outreach.contact') }}</h2>
                            <p v-if="target.contact?.email" class="break-all text-cream" translate="no">{{ target.contact.email }}</p>
                            <a v-if="target.contact?.formUrl" :href="target.contact.formUrl" target="_blank" rel="noopener noreferrer" class="link tap-44 break-all" translate="no">{{ target.contact.formUrl }}</a>
                            <p v-if="!target.contact?.email && !target.contact?.formUrl" class="text-muted">{{ t('admin.outreach.noContact') }}</p>
                            <p v-if="target.contact?.sourceUrl" class="text-muted">{{ t('admin.outreach.publishedOn') }} <a :href="target.contact.sourceUrl" target="_blank" rel="noopener noreferrer" class="link tap-44 break-all" translate="no">{{ target.contact.sourceUrl }}</a></p>
                        </section>
                        <!-- Why it fits, and notes: saved together. -->
                        <form class="card space-y-3 p-5" @submit.prevent="saveFields">
                            <label class="block space-y-1.5"><span class="label">{{ t('admin.outreach.field.fit') }}</span><textarea v-model="fit" class="input" rows="3" maxlength="1000"></textarea></label>
                            <label class="block space-y-1.5"><span class="label">{{ t('admin.outreach.notes') }}</span><textarea v-model="notes" class="input" rows="3" maxlength="5000"></textarea></label>
                            <button type="submit" class="btn-secondary btn-sm" :disabled="busy || (fit === (target.fit ?? '') && notes === (target.notes ?? ''))">{{ t('common.save') }}</button>
                        </form>
                        <!-- How it ended. Declined puts them on the do-not-contact list for good (asked first). -->
                        <section class="card space-y-3 p-5" aria-labelledby="outreach-outcome-h" data-outreach-outcome>
                            <h2 id="outreach-outcome-h" class="font-bold text-cream">{{ t('admin.outreach.outcomeTitle') }}</h2>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="(label, o) in OUTCOMES" :key="o" type="button" class="chip tap-44" :aria-pressed="target.outcome === o ? 'true' : 'false'" :class="target.outcome === o ? 'ring-2 ring-cream text-cream' : ''" :disabled="busy" :data-outcome="o" @click="o === 'declined' ? (asking = 'declined') : setOutcome(o)">{{ t(label) }}</button>
                            </div>
                            <label class="block space-y-1.5"><span class="label">{{ t('admin.outreach.outcomeNote') }}</span><input v-model="outcomeNote" class="input" maxlength="500" @change="saveOutcomeNote" /></label>
                        </section>
                        <!-- What their link brought, from the anonymous counts. -->
                        <section v-if="target.results" class="card space-y-2 p-5 text-sm" aria-labelledby="outreach-brought-h" data-outreach-brought>
                            <h2 id="outreach-brought-h" class="font-bold text-cream">{{ t('admin.outreach.brought') }}</h2>
                            <p v-if="target.messages.some((m) => m.sentAt) && !target.results.tracked" class="text-muted" data-not-tracked>{{ t('admin.outreach.notTracked') }}</p>
                            <p v-else-if="target.results.few" class="text-muted">{{ t('admin.outreach.few') }}</p>
                            <dl v-else class="grid grid-cols-2 gap-x-4 gap-y-1">
                                <dt class="text-muted">{{ t('admin.outreach.results.col.arrivals') }}</dt><dd class="tabular-nums text-cream">{{ formatNumber(target.results.arrivals) }}</dd>
                                <dt class="text-muted">{{ t('admin.outreach.results.col.lesson1') }}</dt><dd class="tabular-nums text-cream">{{ formatNumber(target.results.lesson1) }}</dd>
                                <dt class="text-muted">{{ t('admin.outreach.results.col.signedUp') }}</dt><dd class="tabular-nums text-cream">{{ formatNumber(target.results.signedUp) }}</dd>
                                <dt class="text-muted">{{ t('admin.outreach.results.col.schoolCodes') }}</dt><dd class="tabular-nums text-cream">{{ formatNumber(target.results.schoolCodes) }}</dd>
                            </dl>
                        </section>
                        <!-- A partner: a code for their members (sudo, as every school change). -->
                        <section class="card space-y-3 p-5 text-sm" aria-labelledby="outreach-partner-h" data-outreach-partner>
                            <h2 id="outreach-partner-h" class="font-bold text-cream">{{ t('admin.outreach.partnerTitle') }}</h2>
                            <ul v-if="target.partnerCodes?.length" class="space-y-1.5">
                                <li v-for="c in target.partnerCodes" :key="c.id" class="flex flex-wrap items-center justify-between gap-2"><span class="font-mono tracking-wider text-cream" translate="no">{{ c.code }}</span><span class="tabular-nums text-muted">{{ t('admin.school.seatsOf', { used: formatNumber(c.used), seats: formatNumber(c.seats) }) }} · {{ t('admin.outreach.until', { date: day(c.expiresAt) }) }}</span></li>
                            </ul>
                            <p v-else class="text-muted">{{ t('admin.outreach.partnerHelp') }}</p>
                            <button type="button" class="btn-secondary btn-sm" :disabled="busy || target.status === 'do_not_contact'" data-make-partner @click="asking = 'partner'">{{ t('admin.outreach.makePartner') }}</button>
                        </section>
                        <button v-if="target.status !== 'do_not_contact'" type="button" class="btn-ghost btn-sm text-bad" data-suppress @click="asking = 'suppress'">{{ t('admin.outreach.suppress') }}</button>
                    </aside>
                </div>
            </template>

            <Modal v-if="stale" :title="stale.why === 'early' ? t('admin.outreach.earlyTitle') : t('admin.outreach.staleTitle')" @close="stale = null">
                <p class="text-cream" data-stale-body>{{ stale.why === 'early' ? t('admin.outreach.earlyBody', { date: day(target.sendNotBefore) }) : t('admin.outreach.staleBody', { date: day(target.sendBy) }) }}</p>
                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="stale = null">{{ t('common.cancel') }}</button>
                    <button type="button" class="btn-primary" :disabled="busy" data-send-anyway @click="sendAnyway">{{ stale.how === 'send' ? t('admin.outreach.sendAnyway') : t('admin.outreach.markSentAnyway') }}</button>
                </div>
            </Modal>
            <Modal v-if="asking === 'declined'" :title="t('admin.outreach.declineTitle', { name: target.name })" @close="asking = null">
                <p class="text-cream">{{ t('admin.outreach.declineBody') }}</p>
                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="asking = null">{{ t('common.cancel') }}</button>
                    <button type="button" class="btn-danger" :disabled="busy" @click="setOutcome('declined'); asking = null">{{ t('admin.outreach.declineConfirm') }}</button>
                </div>
            </Modal>
            <Modal v-if="asking === 'suppress'" :title="t('admin.outreach.suppressTitle', { name: target.name })" @close="asking = null">
                <form class="space-y-4" @submit.prevent="suppress">
                    <p class="text-cream">{{ t('admin.outreach.suppressBody', { email: target.contact?.email ?? '' }) }}</p>
                    <label class="block space-y-1.5"><span class="label">{{ t('admin.outreach.suppressWhy') }}</span><select v-model="suppressReason" class="input"><option v-for="(label, r) in REASONS" :key="r" :value="r">{{ t(label) }}</option></select></label>
                    <label class="block space-y-1.5"><span class="label">{{ t('admin.outreach.notes') }}</span><input v-model="suppressNote" class="input" maxlength="500" /></label>
                    <div class="flex flex-wrap justify-end gap-3">
                        <button type="button" class="btn-secondary" @click="asking = null">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-danger" :disabled="busy || !target.contact?.email">{{ t('admin.outreach.suppressConfirm') }}</button>
                    </div>
                </form>
            </Modal>
            <ChangeSheet v-if="asking === 'partner'" :title="t('admin.outreach.partnerSheet', { name: target.name })" :me="me" :busy="busy" :errors="errors" :reasoned="false" :fields="['seats', 'days']" @close="asking = null" @confirm="makePartner">
                <p class="text-cream">{{ t('admin.outreach.partnerBody') }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field :label="t('admin.outreach.partnerSeats')" :error="errors?.seats" required v-slot="f"><input :id="f.id" v-model.number="partner.seats" type="number" min="1" max="100000" inputmode="numeric" class="input" required /></Field>
                    <Field :label="t('admin.outreach.partnerDays')" :help="t('admin.outreach.partnerDaysHelp')" :error="errors?.days" v-slot="f"><input :id="f.id" v-model.number="partner.days" type="number" min="30" max="730" inputmode="numeric" class="input" /></Field>
                </div>
            </ChangeSheet>
        </div>`,
    computed: {
        drafts: (vm) => (vm.target?.messages ?? []).filter((m) => m.status === 'draft'),
        sent: (vm) => (vm.target?.messages ?? []).filter((m) => m.status !== 'draft'),
        sentFirst: (vm) => vm.sent.some((m) => m.kind === 'first'),
        followUpSent: (vm) => vm.sent.some((m) => m.kind === 'follow_up'),
        canWrite: (vm) => vm.target && !['do_not_contact', 'closed', 'partner'].includes(vm.target.status) && !vm.target.suppressed,
        /** A test to Hillel himself (never counted, never marked sent). */
        canTest: () => typeof api.admin.outreach.drafts.test === 'function',
    },
    watch: {
        id() {
            this.load();
        },
    },
    created() {
        this.load();
    },
    methods: {
        navigate,
        /** Send and Mark sent wait while they're on the do-not-contact list, or for a first email while today's cap of new ones is reached; a follow-up never waits for the cap (the server says it too). */
        blocked(message) {
            return !!this.target?.suppressed || this.target?.status === 'do_not_contact' || (message?.kind === 'first' && !!this.summary && this.summary.sentToday >= this.summary.cap);
        },
        async load() {
            this.failed = false;
            try {
                const [shown, list] = await Promise.all([api.admin.outreach.show(this.id), api.admin.outreach.list({ q: undefined }).catch(() => null)]);
                this.take(shown.data);
                this.summary = list?.summary ?? this.summary;
            } catch (error) {
                console.warn('The organization did not load', error);
                this.failed = error instanceof ApiError && error.status === 404 ? t('admin.outreach.gone') : t('admin.outreach.failed');
            }
        },
        take(target) {
            this.target = target;
            this.fit = target.fit ?? '';
            this.notes = target.notes ?? '';
            this.outcomeNote = target.outcomeNote ?? '';
            this.edits = {};
        },
        edit(m) {
            if (!this.edits[m.id]) this.edits[m.id] = { subject: m.subject ?? '', body: m.body ?? '' };
            return this.edits[m.id];
        },
        dirty(m) {
            const e = this.edits[m.id];
            return !!e && (e.subject !== (m.subject ?? '') || e.body !== (m.body ?? ''));
        },
        async run(work, done) {
            this.busy = true;
            try {
                const answer = await work();
                if (answer?.data?.messages) this.take(answer.data);
                else await this.load();
                if (done) toast(done);
                return true;
            } catch (error) {
                outreachRefused(error);
                return false;
            } finally {
                this.busy = false;
            }
        },
        write(kind) {
            return this.run(() => api.admin.outreach.drafts.create(this.target.id, { kind }));
        },
        /** Saved first when edited, so what's sent is what's on screen. */
        async saveFirst(m) {
            if (!this.dirty(m)) return true;
            try {
                await api.admin.outreach.drafts.update(m.id, this.edits[m.id]);
                return true;
            } catch (error) {
                outreachRefused(error);
                return false;
            }
        },
        async save(m) {
            this.busy = true;
            const ok = await this.saveFirst(m);
            this.busy = false;
            if (ok) {
                toast(t('admin.saved'));
                await this.load();
            }
        },
        async send(m, ok = {}) {
            if (!(await this.saveFirst(m))) return;
            await this.sendOr(() => api.admin.outreach.drafts.send(m.id, ok), t('admin.outreach.sentToast', { name: this.target.name }), { m, how: 'send', ok });
        },
        async markSent(m, ok = {}) {
            if (!(await this.saveFirst(m))) return;
            await this.sendOr(() => api.admin.outreach.drafts.sent(m.id, ok), t('admin.outreach.markedSent'), { m, how: 'sent', ok });
        },
        /**
         * Send or mark sent. Past its send-by day, or before its send-not-before day, ask first ("Send
         * anyway?"), then again with that said yes to (both, when both apply: asked one at a time).
         */
        async sendOr(work, done, stale) {
            this.busy = true;
            try {
                const answer = await work();
                if (answer?.data?.messages) this.take(answer.data);
                else await this.load();
                toast(done);
                await this.refreshSummary();
            } catch (error) {
                if (error instanceof ApiError && error.code === 'outreach_past_send_by') this.stale = { ...stale, why: 'stale' };
                else if (error instanceof ApiError && error.code === 'outreach_too_early') this.stale = { ...stale, why: 'early' };
                else outreachRefused(error);
            } finally {
                this.busy = false;
            }
        },
        sendAnyway() {
            const { m, how, ok, why } = this.stale;
            this.stale = null;
            const yes = { ...ok, ...(why === 'early' ? { earlyOk: true } : { staleOk: true }) };
            return how === 'send' ? this.send(m, yes) : this.markSent(m, yes);
        },
        async test(m) {
            if (!(await this.saveFirst(m))) return;
            this.busy = true;
            try {
                // A test changes nothing here (the draft, the cap, the status): nothing to reload.
                const sent = (await api.admin.outreach.drafts.test(m.id)).data;
                toast(t('admin.outreach.testSent', { email: sent?.sentTo ?? this.me.email }));
            } catch (error) {
                // Not set up yet: one calm line by the button, never a raw error.
                if (error instanceof ApiError && error.code === 'outreach_off') this.testOff = m.id;
                else outreachRefused(error);
            } finally {
                this.busy = false;
            }
        },
        discard(m) {
            return this.run(() => api.admin.outreach.drafts.delete(m.id), t('admin.outreach.discarded'));
        },
        read(r) {
            return this.run(() => api.admin.outreach.replies.read(r.id));
        },
        async logReply() {
            const ok = await this.run(() => api.admin.outreach.replies.create(this.target.id, { body: this.reply.body.trim(), from: this.reply.from.trim() || undefined, subject: this.reply.subject.trim() || undefined }), t('admin.outreach.replyLogged'));
            if (ok) {
                this.reply = { body: '', from: '', subject: '' };
                this.logging = false;
            }
        },
        saveFields() {
            return this.run(() => api.admin.outreach.update(this.target.id, { fit: this.fit.trim() || null, notes: this.notes.trim() || null }), t('admin.saved'));
        },
        setOutcome(outcome) {
            return this.run(() => api.admin.outreach.update(this.target.id, { outcome, outcomeNote: this.outcomeNote.trim() || null }), t('admin.saved'));
        },
        saveOutcomeNote() {
            if ((this.outcomeNote.trim() || null) === (this.target.outcomeNote ?? null)) return;
            return this.run(() => api.admin.outreach.update(this.target.id, { outcomeNote: this.outcomeNote.trim() || null }));
        },
        async suppress() {
            const ok = await this.run(() => api.admin.outreach.suppress({ email: this.target.contact.email, reason: this.suppressReason, note: this.suppressNote.trim() || undefined }), t('admin.outreach.suppressedToast'));
            if (ok) this.asking = null;
        },
        async makePartner() {
            this.busy = true;
            this.errors = null;
            try {
                const code = (await api.admin.school.createPartner({ name: this.target.name, seats: this.partner.seats, days: this.partner.days || undefined, targetId: this.target.id })).data;
                toast(t('admin.outreach.partnerMade', { code: code.code }));
                this.asking = null;
                await this.load();
            } catch (error) {
                this.errors = errorsFrom(error) ?? {};
                if (!errorsFrom(error)) refused(error);
            } finally {
                this.busy = false;
            }
        },
        async refreshSummary() {
            this.summary = (await api.admin.outreach.list({}).catch(() => null))?.summary ?? this.summary;
        },
    },
};

// ---- Results -------------------------------------------------------------------------------------------

const OutreachResults = {
    components: { LoadFailed },
    data: () => ({ report: null, failed: false }),
    setup: () => ({ RESULT_COLUMNS, ANGLES, screen, formatNumber }),
    template: `
        <div class="space-y-6" data-outreach-results>
            <p class="max-w-prose text-sm text-muted">{{ t('admin.outreach.results.intro') }}</p>
            <LoadFailed v-if="failed" :message="t('admin.outreach.failed')" @retry="load" />
            <p v-else-if="!report" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <section v-for="table in tables" :key="table.key" class="space-y-3" :aria-labelledby="'outreach-res-' + table.key">
                    <h2 :id="'outreach-res-' + table.key" class="text-lg font-bold text-cream">{{ t(table.title) }}</h2>
                    <p v-if="!table.rows.length" class="text-sm text-muted">{{ t('admin.outreach.results.none') }}</p>
                    <p v-else-if="table.rows.some((r) => r.emailed > r.tracked)" class="text-sm text-muted" data-not-tracked-help>{{ t('admin.outreach.results.notTrackedHelp') }}</p>
                    <!-- A card a row (twelve figures don't fit a table without scrolling sideways): two across on a wider screen. -->
                    <ul v-else class="grid gap-3 md:grid-cols-2">
                        <li v-for="r in table.rows" :key="r.key" class="card p-4" :data-result-row="r.key">
                            <p class="font-bold text-cream" translate="no">{{ table.name(r.key) }}</p>
                            <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm sm:grid-cols-3">
                                <div v-for="c in RESULT_COLUMNS" :key="c.key"><dt class="text-xs text-muted">{{ t(c.label) }}</dt><dd class="tabular-nums text-cream">{{ cell(r, c) }}</dd></div>
                            </dl>
                        </li>
                    </ul>
                </section>
            </template>
        </div>`,
    computed: {
        tables() {
            const d = this.report ?? {};
            return [
                { key: 'group', title: tk('admin.outreach.results.byGroup'), rows: d.byGroup ?? [], name: (key) => key },
                { key: 'angle', title: tk('admin.outreach.results.byAngle'), rows: d.byAngle ?? [], name: (key) => (ANGLES[key] ? t(ANGLES[key]) : key) },
            ];
        },
    },
    created() {
        this.load();
    },
    methods: {
        cell(row, column) {
            // Emails since the plain links (2026-11-05) carry no tag: their visits can't be counted, so no 0 that reads as a failure.
            if (column.counted && row.emailed > 0 && row.tracked === 0) return t('admin.outreach.results.notTracked');
            const v = row[column.key];
            if (column.rate) return v == null ? '-' : formatNumber(v, { style: 'percent', maximumFractionDigits: 0 });
            return formatNumber(v ?? 0);
        },
        async load() {
            this.failed = false;
            try {
                this.report = (await api.admin.outreach.results()).data;
            } catch (error) {
                console.warn('Outreach results did not load', error);
                this.failed = true;
            }
        },
    },
};

// ---- The section ---------------------------------------------------------------------------------------

export const OutreachSection = {
    components: { OutreachList, OutreachResults, OutreachTarget },
    props: { me: { type: Object, required: true } },
    data: () => ({ view: 'list' }),
    setup: () => ({ route }),
    template: `
        <OutreachTarget v-if="route.params.id" :id="route.params.id" :me="me" />
        <div v-else class="space-y-6">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-cream">{{ t('admin.outreach.title') }}</h1>
                <p class="mt-1 max-w-prose text-sm text-muted">{{ t('admin.outreach.intro') }}</p>
            </div>
            <div class="seg w-fit" role="tablist" :aria-label="t('admin.outreach.title')">
                <button type="button" role="tab" class="seg-option" :aria-selected="view === 'list' ? 'true' : 'false'" :aria-pressed="view === 'list' ? 'true' : 'false'" data-outreach-tab="list" @click="view = 'list'">{{ t('admin.outreach.organizations') }}</button>
                <button type="button" role="tab" class="seg-option" :aria-selected="view === 'results' ? 'true' : 'false'" :aria-pressed="view === 'results' ? 'true' : 'false'" data-outreach-tab="results" @click="view = 'results'">{{ t('admin.outreach.results.title') }}</button>
            </div>
            <OutreachList v-if="view === 'list'" :me="me" />
            <OutreachResults v-else />
        </div>`,
};
