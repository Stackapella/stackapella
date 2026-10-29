// The beta singers (docs/beta-singers-plan.md, "Hillel's side"): invite someone with the parts to
// sing, and the link, shown once, to copy or share himself (the app sends nothing); a new link for
// a lost or forwarded one; revoke; a status message to copy; what's owed, marked paid in sudo; and
// removal on request ("forget", in sudo, which can't be undone).

import { api, ApiError } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { listTemplates, templatePartName } from '../templates.js';
import { EmptyState, Field, Notice, StatusChip } from '../components/form.js';
import { Icon } from '../components/icon.js';
import { Modal, toast } from '../components/ui.js';
import { ChangeSheet, errorsFrom, LoadFailed, loadInto, refused, when } from './common.js';
import { navigate } from '../router.js';
import { Segmented } from '../components/controls.js';

const LINK = {
    active: { tone: 'good', label: tk('admin.singers.link.active') },
    expired: { tone: 'warn', label: tk('admin.singers.link.expired') },
    revoked: { tone: 'muted', label: tk('admin.singers.link.revoked') },
    accepted: { tone: 'good', label: tk('admin.singers.link.accepted') },
};
export const ASSIGNMENT = {
    to_record: { tone: 'muted', label: tk('admin.assignment.to_record') },
    submitted: { tone: 'info', label: tk('admin.assignment.submitted') },
    in_review: { tone: 'info', label: tk('admin.assignment.in_review') },
    retake: { tone: 'warn', label: tk('admin.assignment.retake') },
    guide: { tone: 'good', label: tk('admin.assignment.guide') },
    accepted: { tone: 'good', label: tk('admin.assignment.accepted') },
    rejected: { tone: 'muted', label: tk('admin.assignment.rejected') },
};
/** What Hillel can send a singer about a part, since the app sends nothing itself. */
const STATUS_MESSAGE = {
    to_record: tk('admin.statusMessage.to_record'),
    submitted: tk('admin.statusMessage.submitted'),
    in_review: tk('admin.statusMessage.submitted'),
    retake: tk('admin.statusMessage.retake'),
    guide: tk('admin.statusMessage.guide'),
    accepted: tk('admin.statusMessage.accepted'),
    rejected: tk('admin.statusMessage.rejected'),
};
/** The same for a course clip: no song or part, the clip's brief in their place. */
const CLIP_MESSAGE = {
    to_record: tk('admin.clipMessage.to_record'),
    submitted: tk('admin.clipMessage.submitted'),
    in_review: tk('admin.clipMessage.submitted'),
    retake: tk('admin.clipMessage.retake'),
    guide: tk('admin.clipMessage.accepted'),
    accepted: tk('admin.clipMessage.accepted'),
    rejected: tk('admin.clipMessage.rejected'),
};
/**
 * The course clips in recording sessions, most useful first (docs/courses/clips-to-record.md): one
 * singer records a whole session in one sitting, on one mic, so the clips it compares differ only
 * in what's asked.
 */
const CLIP_SESSIONS = [
    { id: 1, label: tk('admin.clips.session.pop'), clips: ['pop.breathy', 'pop.bright', 'pop.twang', 'pop.styled'] },
    { id: 2, label: tk('admin.clips.session.breath'), clips: ['breath.onset-balanced', 'breath.onset-breathy', 'breath.onset-hard', 'breath.end-steady', 'breath.end-ranout'] },
    { id: 3, label: tk('admin.clips.session.yodel'), clips: ['yodel.hook-clean', 'yodel.hook-slid', 'yodel.roll-clean', 'yodel.roll-smeared'] },
    { id: 4, label: tk('admin.clips.session.vibrato'), clips: ['vibrato.straight', 'vibrato.natural', 'vibrato.wobble', 'vibrato.straight-high', 'vibrato.natural-high', 'vibrato.bloom', 'vibrato.wide', 'vibrato.steered'] },
];
const SESSION_MINUTES = 20; // a session, shared between its clips

/** An assignment's name: the song and part, or a course clip's slot. */
function assignmentTitle(a) {
    return a.kind === 'clip' ? t('admin.review.clipTitle', { slot: a.clipId }) : `${a.templateTitle ?? ''}, ${templatePartName({ key: a.partKey })}`;
}

const CURRENCIES = ['USD', 'EUR', 'GBP', 'ILS', 'CAD', 'AUD'];

/** "$12.50" in the singer's currency (or a plain number when none is set). */
export function money(cents, currency) {
    if (cents === null || cents === undefined) return '';
    if (!currency) return formatNumber(cents / 100, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    try {
        return formatNumber(cents / 100, { style: 'currency', currency });
    } catch {
        return `${formatNumber(cents / 100)} ${currency}`;
    }
}

/** A singer's name, or how a forgotten one is shown (their name is cleared). */
export function singerName(singer) {
    return singer?.name || t('admin.singers.forgotten');
}

/** An expiry that hasn't passed, else none (a new link for an expired one shouldn't start expired). */
function stillAhead(time) {
    return time && new Date(time) > new Date() ? time : null;
}

/** Copies `text`, and says so (or says to copy it by hand). True when it was copied. */
export async function copyText(text, done = t('admin.copied')) {
    try {
        await navigator.clipboard.writeText(text);
        toast(done);
        return true;
    } catch (error) {
        console.warn('Copy failed', error);
        toast(t('admin.copyFailed'), { tone: 'error', seconds: 8 });
        return false;
    }
}

/** "2026-10-31" for a time, as the date where Hillel is (what a date field shows). */
function localDate(time) {
    const d = new Date(time);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/** The link, shown once: copy it or share it (Messages, WhatsApp) before closing. */
const LinkSheet = {
    components: { Modal, Notice, Icon },
    props: { link: { type: String, required: true }, name: { type: String, required: true } },
    emits: ['close'],
    data: () => ({ copied: false, canShare: typeof navigator.share === 'function' }),
    template: `
        <Modal :title="t('admin.singers.linkTitle', { name })" @close="$emit('close')">
            <div class="space-y-4">
                <Notice tone="warn">{{ t('admin.singers.linkOnce') }}</Notice>
                <label class="sr-only" for="singer-link">{{ t('admin.singers.linkLabel') }}</label>
                <input id="singer-link" class="input tabular-nums text-xs" readonly :value="link" translate="no" @focus="$event.target.select()" />
                <div class="flex flex-wrap justify-end gap-3">
                    <button v-if="canShare" type="button" class="btn-secondary" @click="share">{{ t('admin.singers.share') }}</button>
                    <button type="button" class="btn-primary" @click="copy"><Icon :name="copied ? 'check' : 'list'" :size="16" /> {{ copied ? t('admin.copied') : t('admin.singers.copyLink') }}</button>
                </div>
                <p class="help">{{ t('admin.singers.linkHelp') }}</p>
            </div>
        </Modal>`,
    methods: {
        async copy() {
            this.copied = await copyText(this.link);
        },
        async share() {
            try {
                await navigator.share({ title: t('admin.singers.shareTitle'), text: t('admin.singers.shareText', { name: this.name }), url: this.link });
            } catch (error) {
                if (error?.name !== 'AbortError') console.warn('Share failed', error);
            }
        },
    },
};

/** Inviting a singer, or changing their details (`singer`): parts only when inviting. */
const SingerForm = {
    components: { Modal, Field, Notice, Icon },
    props: { singer: Object, templates: { type: Array, required: true } },
    emits: ['close', 'saved'],
    data() {
        const s = this.singer;
        return {
            form: {
                name: s?.name ?? '',
                email: s?.email ?? '',
                note: s?.note ?? '',
                expires: s?.expiresAt ? localDate(s.expiresAt) : '',
                rate: s ? (s.hourlyRateCents === null ? '' : String(s.hourlyRateCents / 100)) : '30',
                currency: s?.currency ?? 'USD',
            },
            parts: s ? [] : [{ templateId: this.templates[0]?.id ?? '', partKey: this.templates[0]?.parts[0]?.key ?? '', minutes: 30 }],
            busy: false,
            errors: null,
        };
    },
    setup: () => ({ CURRENCIES, templatePartName }),
    template: `
        <Modal :title="singer ? t('admin.singers.editTitle') : t('admin.singers.inviteTitle')" wide :dismissable="!busy" @close="$emit('close')">
            <form class="space-y-5" @submit.prevent="save">
                <Field :label="t('admin.singers.name')" :error="errors?.name" required v-slot="f">
                    <input :id="f.id" v-model="form.name" class="input" maxlength="120" required autocomplete="off" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                </Field>
                <Field :label="t('admin.singers.email')" :help="t('admin.singers.emailHelp')" :error="errors?.email" v-slot="f">
                    <input :id="f.id" v-model="form.email" type="email" class="input" autocomplete="off" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                </Field>
                <Field :label="t('admin.singers.note')" :help="t('admin.singers.noteHelp')" :error="errors?.note" v-slot="f">
                    <textarea :id="f.id" v-model="form.note" rows="2" maxlength="1000" class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid"></textarea>
                </Field>
                <div class="grid gap-4 sm:grid-cols-3">
                    <Field :label="t('admin.singers.rate')" :error="errors?.hourlyRateCents" v-slot="f">
                        <input :id="f.id" v-model="form.rate" type="number" min="0" step="0.5" inputmode="decimal" class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                    </Field>
                    <Field :label="t('admin.singers.currency')" :error="errors?.currency" required v-slot="f">
                        <select :id="f.id" v-model="form.currency" class="input" :aria-describedby="f.describedby"><option v-for="c in CURRENCIES" :key="c" :value="c" translate="no">{{ c }}</option></select>
                    </Field>
                    <Field :label="t('admin.singers.expires')" :help="t('admin.singers.expiresHelp')" :error="errors?.expiresAt" v-slot="f">
                        <input :id="f.id" v-model="form.expires" type="date" class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                    </Field>
                </div>
                <fieldset v-if="!singer" class="space-y-3">
                    <legend class="label">{{ t('admin.singers.parts') }}</legend>
                    <div v-for="(part, i) in parts" :key="i" class="grid grid-cols-[1fr_1fr_5rem_auto] items-end gap-2">
                        <div>
                            <label class="sr-only" :for="'song-' + i">{{ t('admin.singers.song') }}</label>
                            <select :id="'song-' + i" v-model="part.templateId" class="input" @change="part.partKey = partsOf(part.templateId)[0]?.key ?? ''">
                                <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id" translate="no">{{ tpl.title }}{{ tpl.reviewed ? '' : ' (' + t('admin.singers.draft') + ')' }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="sr-only" :for="'part-' + i">{{ t('admin.singers.part') }}</label>
                            <select :id="'part-' + i" v-model="part.partKey" class="input">
                                <option v-for="p in partsOf(part.templateId)" :key="p.key" :value="p.key">{{ templatePartName(p) }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="sr-only" :for="'minutes-' + i">{{ t('admin.singers.minutes') }}</label>
                            <input :id="'minutes-' + i" v-model.number="part.minutes" type="number" min="5" max="600" class="input" :title="t('admin.singers.minutes')" />
                        </div>
                        <button type="button" class="btn-ghost btn-sm" :disabled="parts.length === 1" :aria-label="t('admin.singers.removePartRow')" @click="parts.splice(i, 1)"><Icon name="trash" :size="16" /></button>
                    </div>
                    <p class="help">{{ t('admin.singers.minutesHelp') }}</p>
                    <button type="button" class="btn-secondary btn-sm" @click="addRow"><Icon name="plus" :size="14" /> {{ t('admin.singers.addPartRow') }}</button>
                </fieldset>
                <Notice v-if="otherErrors.length" tone="bad">{{ otherErrors.join(' ') }}</Notice>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" :disabled="busy" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn-primary" :disabled="busy || !form.name.trim()">{{ busy ? t('common.saving') : singer ? t('common.save') : t('admin.singers.invite') }}</button>
                </div>
            </form>
        </Modal>`,
    computed: {
        otherErrors() {
            const shown = ['name', 'email', 'note', 'hourlyRateCents', 'currency', 'expiresAt'];
            return Object.entries(this.errors ?? {}).filter(([field]) => !shown.includes(field)).flatMap(([, messages]) => messages);
        },
    },
    methods: {
        partsOf(templateId) {
            return this.templates.find((tpl) => tpl.id === templateId)?.parts ?? [];
        },
        addRow() {
            const last = this.parts[this.parts.length - 1];
            const templateId = last?.templateId ?? this.templates[0]?.id ?? '';
            this.parts.push({ templateId, partKey: this.partsOf(templateId)[0]?.key ?? '', minutes: last?.minutes ?? 30 });
        },
        fields() {
            const f = this.form;
            return {
                name: f.name.trim(),
                email: f.email.trim() || null,
                note: f.note.trim() || null,
                // The end of that day where Hillel is.
                expiresAt: f.expires ? new Date(`${f.expires}T23:59:59`).toISOString().replace(/\.\d+Z$/, 'Z') : null,
                hourlyRateCents: f.rate === '' ? null : Math.round(Number(f.rate) * 100),
                currency: f.currency,
            };
        },
        async save() {
            this.busy = true;
            this.errors = null;
            try {
                const answer = this.singer
                    ? await api.admin.beta.singers.update(this.singer.id, this.fields())
                    : await api.admin.beta.singers.create({ ...this.fields(), parts: this.parts.map((p) => ({ templateId: p.templateId, partKey: p.partKey, minutes: Number(p.minutes) || 30 })) });
                this.$emit('saved', answer);
            } catch (error) {
                this.errors = errorsFrom(error);
                if (!this.errors) refused(error);
            } finally {
                this.busy = false;
            }
        },
    },
};

/** Asking a singer for another part, a course clip, or a whole session of clips. */
const PartSheet = {
    components: { Modal, Field, Notice, Segmented },
    props: { singer: { type: Object, required: true }, templates: { type: Array, required: true }, clips: { type: Array, default: () => [] } },
    emits: ['close', 'saved'],
    data() {
        return { kind: 'part', templateId: this.templates[0]?.id ?? '', partKey: this.templates[0]?.parts[0]?.key ?? '', clipId: '', session: null, minutes: 30, busy: false, errors: null };
    },
    setup: () => ({ templatePartName, formatNumber }),
    template: `
        <Modal :title="t('admin.singers.addPartTitle', { name: singerName })" :dismissable="!busy" @close="$emit('close')">
            <form class="space-y-5" data-add-part @submit.prevent="save">
                <div class="space-y-1.5">
                    <p id="add-kind" class="label">{{ t('admin.clips.what') }}</p>
                    <Segmented v-model="kind" :options="kinds" labelledby="add-kind" full />
                </div>
                <template v-if="kind === 'part'">
                    <Field :label="t('admin.singers.song')" :error="errors?.templateId" required v-slot="f">
                        <select :id="f.id" v-model="templateId" class="input" @change="partKey = parts[0]?.key ?? ''">
                            <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id" translate="no">{{ tpl.title }}{{ tpl.reviewed ? '' : ' (' + t('admin.singers.draft') + ')' }}</option>
                        </select>
                    </Field>
                    <Field :label="t('admin.singers.part')" :error="errors?.partKey" required v-slot="f">
                        <select :id="f.id" v-model="partKey" class="input"><option v-for="p in parts" :key="p.key" :value="p.key">{{ templatePartName(p) }}</option></select>
                    </Field>
                </template>
                <template v-else-if="kind === 'clip'">
                    <Field :label="t('admin.clips.clip')" :help="chosenClip?.sing" :error="errors?.clipId" required v-slot="f">
                        <select :id="f.id" v-model="clipId" class="input" required :aria-describedby="f.describedby" data-clip-choice>
                            <option value="" disabled>{{ open.length ? t('admin.clips.pick') : t('admin.clips.noneOpen') }}</option>
                            <option v-for="c in open" :key="c.id" :value="c.id" translate="no">{{ c.id }}</option>
                        </select>
                    </Field>
                </template>
                <template v-else>
                    <Field :label="t('admin.clips.sessionLabel')" :help="sessionHelp" :error="errors?.clipId" required v-slot="f">
                        <select :id="f.id" v-model="session" class="input" required :aria-describedby="f.describedby" data-session-choice>
                            <option :value="null" disabled>{{ t('admin.clips.pickSession') }}</option>
                            <option v-for="sn in sessions" :key="sn.id" :value="sn.id" :disabled="!sn.open.length">{{ t(sn.label) }} ({{ t('admin.clips.openCount', { count: sn.open.length }) }})</option>
                        </select>
                    </Field>
                </template>
                <Field v-if="kind !== 'session'" :label="t('admin.singers.minutes')" :help="t('admin.singers.minutesHelp')" :error="errors?.minutes" required v-slot="f">
                    <input :id="f.id" v-model.number="minutes" type="number" min="5" max="600" class="input" required :aria-describedby="f.describedby" />
                </Field>
                <Notice v-if="errors && !Object.keys(errors).length" tone="bad">{{ t('admin.requestFailed') }}</Notice>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" :disabled="busy" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn-primary" :disabled="busy || (kind === 'clip' && !clipId) || (kind === 'session' && !session)">{{ busy ? t('common.saving') : kind === 'session' ? t('admin.clips.addSession') : t('admin.singers.addPart') }}</button>
                </div>
            </form>
        </Modal>`,
    computed: {
        parts() {
            return this.templates.find((tpl) => tpl.id === this.templateId)?.parts ?? [];
        },
        singerName: (vm) => singerName(vm.singer),
        kinds: () => [
            { value: 'part', label: t('admin.clips.kind.part') },
            { value: 'clip', label: t('admin.clips.kind.clip') },
            { value: 'session', label: t('admin.clips.kind.session') },
        ],
        /** The clips nobody has yet (and not already this singer's). */
        open() {
            const theirs = new Set(this.singer.assignments.map((a) => a.clipId).filter(Boolean));
            return this.clips.filter((c) => c.status === 'open' && !c.retired && !theirs.has(c.id));
        },
        chosenClip() {
            return this.clips.find((c) => c.id === this.clipId) ?? null;
        },
        sessions() {
            const open = new Set(this.open.map((c) => c.id));
            return CLIP_SESSIONS.map((sn) => ({ ...sn, open: sn.clips.filter((id) => open.has(id)) }));
        },
        sessionHelp() {
            const sn = this.sessions.find((x) => x.id === this.session);
            return sn ? t('admin.clips.sessionHelp', { count: sn.open.length, minutes: formatNumber(SESSION_MINUTES), list: sn.open.join(', ') }) : t('admin.clips.sessionWhy');
        },
    },
    watch: {
        kind(kind) {
            this.errors = null;
            this.minutes = kind === 'clip' ? 10 : 30;
        },
    },
    methods: {
        async save() {
            this.busy = true;
            this.errors = null;
            try {
                if (this.kind === 'session') {
                    // One clip at a time; one taken since the list loaded (a 422 on clipId) is skipped. What
                    // was added stays added if a later one fails, so the list is reloaded either way.
                    const sn = this.sessions.find((x) => x.id === this.session);
                    const each = Math.max(5, Math.round(SESSION_MINUTES / sn.open.length));
                    let added = 0;
                    for (const clipId of sn.open) {
                        try {
                            await api.admin.beta.assignments.create(this.singer.id, { clipId, minutes: each });
                            added++;
                        } catch (error) {
                            if (!(error instanceof ApiError && error.status === 422 && error.errors?.clipId)) {
                                if (added) this.$emit('saved', null);
                                throw error;
                            }
                        }
                    }
                    if (!added) this.errors = { clipId: [t('admin.clips.noneAdded')] };
                    else this.$emit('saved', null);
                    return;
                }
                const ask = this.kind === 'clip' ? { clipId: this.clipId } : { templateId: this.templateId, partKey: this.partKey };
                const { data } = await api.admin.beta.assignments.create(this.singer.id, { ...ask, minutes: Number(this.minutes) || 30 });
                this.$emit('saved', data);
            } catch (error) {
                this.errors = errorsFrom(error);
                if (!this.errors) refused(error);
            } finally {
                this.busy = false;
            }
        },
    },
};

/**
 * Singers in an order for picking the best: as the server sends them (newest first), best on pitch
 * (unscored last), or most accepted (then most leading their part).
 */
export function orderSingers(singers, order) {
    const list = [...singers];
    if (order === 'pitch') return list.sort((a, b) => (b.stats?.pitchPercent ?? -1) - (a.stats?.pitchPercent ?? -1));
    if (order === 'accepted') return list.sort((a, b) => (b.stats?.accepted ?? 0) - (a.stats?.accepted ?? 0) || (b.stats?.defaults ?? 0) - (a.stats?.defaults ?? 0));
    return list;
}

const SingerCard = {
    components: { StatusChip, Icon },
    props: { singer: { type: Object, required: true }, clips: { type: Object, default: () => ({}) } },
    emits: ['act'],
    setup: () => ({ LINK, ASSIGNMENT, money, when, templatePartName, assignmentTitle, singerName, formatDate, formatNumber, navigate }),
    computed: {
        /** Every take of theirs, whatever its status, in the review queue. */
        takesPath() {
            return `/admin/review?status=&singer=${encodeURIComponent(this.singer.id)}`;
        },
    },
    template: `
        <li class="card p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="text-lg font-bold text-cream" translate="no">{{ singerName(singer) }}</h3>
                    <p v-if="singer.email" class="text-sm text-muted" translate="no">{{ singer.email }}</p>
                    <p v-if="singer.note" class="mt-1 text-sm text-muted" translate="no">{{ singer.note }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <StatusChip :tone="LINK[singer.link]?.tone ?? 'muted'" :label="t((LINK[singer.link] ?? LINK.expired).label)" />
                    <StatusChip :tone="singer.consent.current ? 'good' : 'warn'" :label="singer.consent.current ? t('admin.singers.consented') : singer.consent.acceptedAt ? t('admin.singers.consentOld') : t('admin.singers.notConsented')" />
                </div>
            </div>
            <dl class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <div class="flex gap-2"><dt class="text-muted">{{ t('admin.singers.credit') }}</dt><dd class="text-cream" translate="no">{{ singer.credit.mode === 'anonymous' ? t('admin.singers.anonymous') : singer.credit.name || t('admin.singers.noCredit') }}</dd></div>
                <div class="flex gap-2"><dt class="text-muted">{{ t('admin.singers.lastSeen') }}</dt><dd class="text-cream"><time v-if="singer.lastSeenAt" :datetime="singer.lastSeenAt">{{ when(singer.lastSeenAt) }}</time><template v-else>{{ t('admin.singers.never') }}</template></dd></div>
                <div class="flex gap-2"><dt class="text-muted">{{ t('admin.singers.owed') }}</dt><dd class="text-cream">{{ money(singer.owedCents, singer.currency) }} <span class="text-muted">{{ t('admin.singers.paidSoFar', { amount: money(singer.paidCents, singer.currency) }) }}</span></dd></div>
                <div v-if="singer.expiresAt" class="flex gap-2"><dt class="text-muted">{{ t('admin.singers.expires') }}</dt><dd class="text-cream"><time :datetime="singer.expiresAt">{{ formatDate(singer.expiresAt) }}</time></dd></div>
            </dl>
            <dl v-if="singer.stats" class="mt-4 grid grid-cols-2 gap-3 rounded-[18px] bg-(--fill) p-3 text-sm sm:grid-cols-5">
                <div><dt class="text-xs text-muted">{{ t('admin.singers.stat.takes') }}</dt><dd class="text-lg font-bold text-cream">{{ formatNumber(singer.stats.takes) }}</dd></div>
                <div><dt class="text-xs text-muted">{{ t('admin.singers.stat.accepted') }}</dt><dd class="text-lg font-bold text-cream">{{ formatNumber(singer.stats.accepted) }}</dd></div>
                <div><dt class="text-xs text-muted">{{ t('admin.singers.stat.retakes') }}</dt><dd class="text-lg font-bold text-cream">{{ formatNumber(singer.stats.retakes) }}</dd></div>
                <div><dt class="text-xs text-muted">{{ t('admin.singers.stat.onApp') }}</dt><dd class="text-lg font-bold text-cream">{{ t('admin.singers.stat.onAppValue', { published: singer.stats.published, defaults: singer.stats.defaults }) }}</dd></div>
                <div><dt class="text-xs text-muted">{{ t('admin.singers.stat.pitch') }}</dt><dd class="text-lg font-bold text-cream">{{ singer.stats.pitchPercent === null ? t('admin.singers.stat.noScore') : t('admin.singers.stat.pitchValue', { percent: singer.stats.pitchPercent }) }}</dd></div>
            </dl>
            <ul class="mt-4 divide-y divide-line rounded-2xl border border-line">
                <li v-for="a in singer.assignments" :key="a.id" class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2.5 text-sm">
                    <span class="min-w-0 flex-1 text-cream"><span translate="no">{{ assignmentTitle(a) }}</span> <span class="text-muted">{{ t('admin.singers.aboutMinutes', { minutes: a.minutes }) }}</span><span v-if="a.kind === 'clip' && clips[a.clipId]" class="block text-[0.8125rem] text-muted" data-clip-brief>{{ clips[a.clipId].sing }}</span></span>
                    <StatusChip :tone="ASSIGNMENT[a.status]?.tone ?? 'muted'" :label="t((ASSIGNMENT[a.status] ?? ASSIGNMENT.to_record).label)" />
                    <button type="button" class="btn-ghost btn-sm" @click="$emit('act', 'message', a)">{{ t('admin.singers.copyMessage') }}</button>
                    <button v-if="a.status === 'to_record'" type="button" class="btn-ghost btn-sm" @click="$emit('act', 'removePart', a)">{{ t('admin.singers.removePart') }}</button>
                </li>
                <li v-if="!singer.assignments.length" class="px-3 py-2.5 text-sm text-muted">{{ t('admin.singers.noParts') }}</li>
            </ul>
            <div v-if="singer.name" class="mt-4 flex flex-wrap gap-2">
                <a v-if="singer.stats?.takes" :href="takesPath" class="btn-secondary btn-sm" @click.prevent="navigate(takesPath)">{{ t('admin.singers.theirTakes') }}</a>
                <button type="button" class="btn-secondary btn-sm" @click="$emit('act', 'addPart')"><Icon name="plus" :size="14" /> {{ t('admin.singers.addPart') }}</button>
                <button type="button" class="btn-secondary btn-sm" @click="$emit('act', 'link')">{{ t('admin.singers.newLink') }}</button>
                <button type="button" class="btn-secondary btn-sm" @click="$emit('act', 'edit')">{{ t('admin.singers.edit') }}</button>
                <button v-if="singer.owedCents > 0" type="button" class="btn-secondary btn-sm" @click="$emit('act', 'paid')">{{ t('admin.singers.markPaid') }}</button>
                <button v-if="singer.link !== 'revoked'" type="button" class="btn-ghost btn-sm" @click="$emit('act', 'revoke')">{{ t('admin.singers.revoke') }}</button>
                <button type="button" class="btn-ghost btn-sm text-bad" @click="$emit('act', 'forget')">{{ t('admin.singers.forget') }}</button>
            </div>
        </li>`,
};

/**
 * The bar a beta take must meet to be sent (admin.beta.bar.*): the app tells singers whether a take
 * is ready against it, and the server refuses one under it. Shown with the singers, changed in place.
 */
export const BarCard = {
    components: { Field, Icon },
    data: () => ({ bar: null, editing: null, busy: false, errors: null }),
    template: `
        <section v-if="bar" class="card flex flex-wrap items-center justify-between gap-3 p-4" aria-labelledby="bar-heading">
            <div v-if="!editing">
                <h2 id="bar-heading" class="font-bold text-cream">{{ t('admin.singers.bar.title') }}</h2>
                <p class="text-sm text-muted">{{ t('admin.singers.bar.now', { pitch: bar.pitchPercent, timing: bar.timingPercent }) }}</p>
            </div>
            <button v-if="!editing" type="button" class="btn-secondary btn-sm" @click="editing = { ...bar }">{{ t('admin.singers.bar.change') }}</button>
            <form v-else class="flex w-full flex-wrap items-end gap-3" @submit.prevent="save">
                <h2 id="bar-heading" class="w-full font-bold text-cream">{{ t('admin.singers.bar.title') }}</h2>
                <Field :label="t('admin.singers.bar.pitch')" :error="errors?.pitchPercent" v-slot="f">
                    <input :id="f.id" v-model.number="editing.pitchPercent" type="number" min="50" max="100" required class="input w-28" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                </Field>
                <Field :label="t('admin.singers.bar.timing')" :error="errors?.timingPercent" v-slot="f">
                    <input :id="f.id" v-model.number="editing.timingPercent" type="number" min="50" max="100" required class="input w-28" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                </Field>
                <p class="w-full text-sm text-muted">{{ t('admin.singers.bar.help') }}</p>
                <div class="flex gap-2">
                    <button type="button" class="btn-ghost btn-sm" :disabled="busy" @click="editing = null; errors = null">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn-primary btn-sm" :disabled="busy">{{ busy ? t('common.saving') : t('common.save') }}</button>
                </div>
            </form>
        </section>`,
    async created() {
        try {
            this.bar = (await api.admin.beta.bar.show()).data;
        } catch (error) {
            refused(error);
        }
    },
    methods: {
        async save() {
            this.busy = true;
            this.errors = null;
            try {
                this.bar = (await api.admin.beta.bar.update({ body: { pitchPercent: this.editing.pitchPercent, timingPercent: this.editing.timingPercent } })).data;
                this.editing = null;
                toast(t('admin.singers.bar.saved'));
            } catch (error) {
                this.errors = errorsFrom(error);
                if (!this.errors) refused(error);
            } finally {
                this.busy = false;
            }
        },
    },
};

export const SingersSection = {
    components: { BarCard, SingerCard, SingerForm, PartSheet, LinkSheet, ChangeSheet, EmptyState, LoadFailed, Modal, Field, Notice, Icon },
    props: { me: { type: Object, required: true } },
    data: () => ({ singers: null, failed: false, templates: [], clips: [], sheet: null, link: null, busy: false, errors: null, typed: '', order: 'newest' }),
    setup: () => ({ singerName, money, assignmentTitle }),
    template: `
        <div class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.singers') }}</h1>
                    <p class="mt-1 max-w-prose text-sm text-muted">{{ t('admin.singers.intro') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="sr-only" for="singers-order">{{ t('admin.singers.order') }}</label>
                    <select v-if="singers?.length > 1" id="singers-order" v-model="order" class="input !w-auto !py-2">
                        <option value="newest">{{ t('admin.singers.orderNewest') }}</option>
                        <option value="pitch">{{ t('admin.singers.orderPitch') }}</option>
                        <option value="accepted">{{ t('admin.singers.orderAccepted') }}</option>
                    </select>
                    <button type="button" class="btn-primary" :disabled="!templates.length" @click="sheet = { kind: 'invite' }"><Icon name="plus" :size="16" /> {{ t('admin.singers.invite') }}</button>
                </div>
            </div>
            <BarCard v-if="me.role === 'admin'" />
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!singers" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!singers.length" icon="mic" :title="t('admin.singers.none')" :body="t('admin.singers.noneHint')" />
            <ul v-else class="space-y-4">
                <SingerCard v-for="s in ordered" :key="s.id" :singer="s" :clips="clipsById" @act="(kind, assignment) => act(s, kind, assignment)" />
            </ul>

            <SingerForm v-if="sheet?.kind === 'invite' || sheet?.kind === 'edit'" :singer="sheet.singer" :templates="templates" @close="sheet = null" @saved="saved" />
            <PartSheet v-if="sheet?.kind === 'addPart'" :singer="sheet.singer" :templates="templates" :clips="clips" @close="sheet = null" @saved="partAdded" />
            <LinkSheet v-if="link" :link="link.url" :name="link.name" @close="link = null" />
            <ChangeSheet v-if="sheet?.kind === 'link'" :needs-sudo="false" :title="t('admin.singers.newLinkTitle', { name: singerName(sheet.singer) })" :me="me" :busy="busy" :errors="errors" :reasoned="false" @close="sheet = null" @confirm="newLink">
                <p class="text-cream">{{ t('admin.singers.newLinkBody') }}</p>
            </ChangeSheet>
            <ChangeSheet v-if="sheet?.kind === 'revoke'" :needs-sudo="false" :title="t('admin.singers.revokeTitle', { name: singerName(sheet.singer) })" :me="me" :busy="busy" :errors="errors" :reasoned="false" @close="sheet = null" @confirm="revoke">
                <p class="text-cream">{{ t('admin.singers.revokeBody') }}</p>
            </ChangeSheet>
            <ChangeSheet v-if="sheet?.kind === 'paid'" :title="t('admin.singers.paidTitle', { amount: money(sheet.singer.owedCents, sheet.singer.currency), name: singerName(sheet.singer) })" :me="me" :busy="busy" :errors="errors" :reasoned="false" @close="sheet = null" @confirm="paid">
                <p class="text-cream">{{ t('admin.singers.paidBody') }}</p>
            </ChangeSheet>
            <ChangeSheet v-if="sheet?.kind === 'removePart'" :needs-sudo="false" :title="t('admin.singers.removePartTitle')" :me="me" :busy="busy" :errors="errors" :reasoned="false" @close="sheet = null" @confirm="removePart">
                <p class="text-cream" translate="no">{{ assignmentTitle(sheet.assignment) }}</p>
            </ChangeSheet>
            <Modal v-if="sheet?.kind === 'forget'" :title="t('admin.singers.forgetTitle', { name: singerName(sheet.singer) })" :dismissable="!busy" @close="sheet = null">
                <form class="space-y-4" @submit.prevent="forget">
                    <p class="text-cream">{{ t('admin.singers.forgetBody') }}</p>
                    <Notice tone="bad">{{ t('admin.singers.forgetCannotUndo') }}</Notice>
                    <Field :label="t('admin.singers.forgetType', { name: sheet.singer.name })" required v-slot="f">
                        <input :id="f.id" v-model="typed" class="input" autocomplete="off" required :aria-describedby="f.describedby" />
                    </Field>
                    <Notice v-if="!sudo" tone="info">{{ t('admin.sudoFirst') }}</Notice>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="btn-secondary" :disabled="busy" @click="sheet = null">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-danger" :disabled="busy || typed.trim() !== sheet.singer.name.trim()">{{ busy ? t('common.saving') : t('admin.singers.forget') }}</button>
                    </div>
                </form>
            </Modal>
        </div>`,
    computed: {
        ordered() {
            return orderSingers(this.singers ?? [], this.order);
        },
        sudo() {
            return !!this.me.sudoUntil && new Date(this.me.sudoUntil) > new Date();
        },
        clipsById() {
            return Object.fromEntries(this.clips.map((c) => [c.id, c]));
        },
    },
    created() {
        this.load();
        // Every song, drafts included: a singer can be lined up before the musician's check.
        listTemplates({ drafts: true }).then(
            (list) => (this.templates = list),
            (error) => console.error('Templates failed to load', error),
        );
        this.loadClips();
    },
    methods: {
        load() {
            return loadInto(this, 'singers', () => api.admin.beta.singers.list());
        },
        /** The course clips, for Add part and for naming a clip on a singer's list. */
        async loadClips() {
            try {
                this.clips = (await api.admin.beta.clips.list()).data;
            } catch (error) {
                console.warn('The clips failed to load', error); // songs can still be added
            }
        },
        /** Puts the server's copy of a singer in the list. */
        replace(singer) {
            const i = this.singers.findIndex((s) => s.id === singer.id);
            if (i >= 0) this.singers.splice(i, 1, singer);
            else this.singers.unshift(singer);
        },
        act(singer, kind, assignment = null) {
            this.errors = null;
            this.typed = '';
            if (kind === 'message') {
                const first = (singer.credit.name || singer.name).split(' ')[0];
                const words = assignment.kind === 'clip'
                    ? t(CLIP_MESSAGE[assignment.status], { name: first, clip: this.clipsById[assignment.clipId]?.sing ?? assignment.clipId })
                    : t(STATUS_MESSAGE[assignment.status], { name: first, part: templatePartName({ key: assignment.partKey }), song: assignment.templateTitle });
                copyText(words, t('admin.singers.messageCopied'));
                return;
            }
            this.sheet = { kind, singer, assignment };
        },
        saved({ data, link }) {
            this.replace(data);
            this.sheet = null;
            if (link) this.link = { url: link, name: singerName(data) };
            else toast(t('admin.saved'));
        },
        partAdded() {
            this.sheet = null;
            toast(t('admin.singers.partAdded'));
            this.load();
            this.loadClips();
        },
        /** Runs a change from a sheet, keeping it open (with the refusal) if it fails. */
        async change(request) {
            this.busy = true;
            this.errors = null;
            try {
                return await request();
            } catch (error) {
                this.errors = errorsFrom(error);
                if (!this.errors) {
                    if (error instanceof ApiError && error.code === 'has_submissions') {
                        toast(t('admin.singers.hasSubmissions'), { tone: 'error', seconds: 10 });
                        this.sheet = null;
                    } else {
                        refused(error);
                    }
                }
                return null;
            } finally {
                this.busy = false;
            }
        },
        async newLink() {
            const answer = await this.change(() => api.admin.beta.singers.link(this.sheet.singer.id, { expiresAt: stillAhead(this.sheet.singer.expiresAt) }));
            if (answer) this.saved(answer);
        },
        async revoke() {
            const answer = await this.change(() => api.admin.beta.singers.revoke(this.sheet.singer.id));
            if (answer) this.saved(answer);
        },
        async paid() {
            const answer = await this.change(() => api.admin.beta.singers.paid(this.sheet.singer.id));
            if (answer) this.saved(answer);
        },
        async forget() {
            const answer = await this.change(() => api.admin.beta.singers.forget(this.sheet.singer.id));
            if (answer) {
                this.replace(answer.data);
                this.sheet = null;
                toast(t('admin.singers.forgetDone'));
            }
        },
        async removePart() {
            const ok = await this.change(() => api.admin.beta.assignments.delete(this.sheet.assignment.id).then(() => true));
            if (ok) {
                this.sheet = null;
                toast(t('admin.singers.partRemoved'));
                this.load();
            }
        },
    },
};
