// mudeo email (docs/mudeo-transition-plan.md, "The admin tool" and "Sending it (through the app)"):
// the one email to mudeo's old users, sent through the app 50 at a time. The counts and today's
// count against the daily cap (editable, in sudo); Send a test to me; Send the next batch, then its
// result (delivered, bounced, failed and the bounce rate, with a stop for the day past 5%); the
// opt-outs and bounces (search, or paste several); the CSV import (sudo); and the early-access
// link's controls (its URL, who joined, the cap, Pause, Replace with a new code). Sending from your
// own email (the next 50 as one line, Mark them sent, Undo) stays as a fallback. Every action is in
// the audit log.

import { api } from '../api.js';
import { formatNumber, t, tk } from '../i18n.js';
import { Field, Notice, StatusChip } from '../components/form.js';
import { Modal, toast } from '../components/ui.js';
import { confirmAction } from '../components/confirm.js';
import { errorsFrom, LoadFailed, refused, when } from './common.js';
import { explain, inSudo, sendToSignIn } from './auth.js';

const STATUS = {
    to_send: { tone: 'muted', label: tk('admin.mudeo.status.toSend') },
    sent: { tone: 'good', label: tk('admin.mudeo.status.sent') },
    opted_out: { tone: 'warn', label: tk('admin.mudeo.status.optedOut') },
    bounced: { tone: 'bad', label: tk('admin.mudeo.status.bounced') },
};
const SEND_REFUSED = {
    news_not_set_up: tk('admin.mudeo.refused.newsNotSetUp'),
    postal_address_missing: tk('admin.mudeo.refused.postal'),
    batch_sending: tk('admin.mudeo.refused.sending'),
    nothing_to_send: tk('admin.mudeo.refused.nothing'),
};
const SKIPPED = {
    invalid: tk('admin.mudeo.skipped.invalid'),
    duplicate: tk('admin.mudeo.skipped.duplicate'),
    test: tk('admin.mudeo.skipped.test'),
    suppressed: tk('admin.mudeo.skipped.suppressed'),
    hasAccount: tk('admin.mudeo.skipped.hasAccount'),
    alreadyListed: tk('admin.mudeo.skipped.alreadyListed'),
};
const BOUNCE_STOP = 0.05; // past this, stop for the day (the plan's rule)
const POLL_MS = 3000; // a batch is sent by a queued job: its result is looked at again until it's in

const LINK_WORDS = {
    earlyAccess: { title: tk('admin.mudeo.linkTitle'), hint: tk('admin.mudeo.linkHint'), joined: tk('admin.mudeo.joined'), replace: tk('admin.mudeo.replaceBody') },
    preview: { title: tk('admin.preview.title'), hint: tk('admin.preview.hint'), joined: tk('admin.preview.joined'), replace: tk('admin.links.replaceBody') },
    invite: { title: tk('admin.invite.title'), hint: tk('admin.invite.hint'), joined: tk('admin.invite.joined'), replace: tk('admin.links.replaceBody') },
};

/**
 * A link into the beta before launch, and its controls: mudeo's early-access link (it lets people
 * make an account while sign-ups are paused), or Hillel's preview link (it lets someone look around,
 * with no account). Its URL with Copy, who it let in against its cap, Pause, and Replace with a new
 * code (the old one closes).
 */
export const BetaLinkCard = {
    components: { LoadFailed, StatusChip },
    props: { kind: { type: String, required: true }, me: { type: Object, required: true } },
    data: () => ({ link: null, cap: null, failed: null, busy: false }),
    setup: () => ({ formatNumber, when, LINK_WORDS }),
    template: `
        <section class="card space-y-4 p-5" :aria-labelledby="kind + '-heading'">
            <h2 :id="kind + '-heading'" class="text-lg font-bold text-cream">{{ t(LINK_WORDS[kind].title) }}</h2>
            <p class="text-sm text-muted">{{ t(LINK_WORDS[kind].hint) }}</p>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <template v-else-if="link">
                <div class="flex flex-wrap items-center gap-2 rounded-2xl bg-raised p-3">
                    <code class="min-w-0 flex-1 truncate text-sm text-cream">{{ link.url }}</code>
                    <button type="button" class="btn-secondary btn-sm" @click="copy">{{ t('share.copy') }}</button>
                </div>
                <p class="text-sm text-cream">{{ t(LINK_WORDS[kind].joined, { joined: formatNumber(link.joined), cap: formatNumber(link.cap) }) }} <StatusChip :tone="link.paused ? 'warn' : 'good'" :label="link.paused ? t('admin.mudeo.paused') : t('admin.mudeo.open')" /></p>
                <div v-if="kind === 'invite'" class="flex flex-wrap gap-3">
                    <button type="button" class="btn-secondary btn-sm" :disabled="busy" @click="update({ paused: !link.paused })">{{ link.paused ? t('admin.mudeo.resume') : t('admin.mudeo.pause') }}</button>
                    <button type="button" class="btn-ghost btn-sm text-bad" :disabled="busy" @click="replace">{{ t('admin.mudeo.replace') }}</button>
                </div>
                <form v-else class="flex flex-wrap items-end gap-3" @submit.prevent="update({ cap })">
                    <label class="block text-sm text-muted">{{ t('admin.mudeo.linkCap') }}
                        <input v-model.number="cap" type="number" min="1" max="100000" class="input mt-1 w-32" />
                    </label>
                    <button type="submit" class="btn-secondary btn-sm" :disabled="busy || cap === link.cap">{{ t('common.save') }}</button>
                    <button type="button" class="btn-secondary btn-sm" :disabled="busy" @click="update({ paused: !link.paused })">{{ link.paused ? t('admin.mudeo.resume') : t('admin.mudeo.pause') }}</button>
                    <button type="button" class="btn-ghost btn-sm text-bad" :disabled="busy" @click="replace">{{ t('admin.mudeo.replace') }}</button>
                </form>
                <ul v-if="link.earlier?.length" class="space-y-1 text-xs text-muted">
                    <li v-for="(e, i) in link.earlier" :key="i">{{ t('admin.mudeo.earlier', { joined: formatNumber(e.joined), from: when(e.createdAt), to: when(e.closedAt) }) }}</li>
                </ul>
            </template>
            <p v-else class="text-muted" role="status">{{ t('admin.loading') }}</p>
        </section>`,
    created() {
        this.load();
    },
    methods: {
        async load() {
            this.failed = null;
            try {
                this.set((await api.admin[this.kind].show()).data);
            } catch (error) {
                if (!sendToSignIn(error)) this.failed = explain(error) ?? t('admin.requestFailed');
            }
        },
        set(link) {
            this.link = link;
            this.cap = link.cap;
        },
        async update(fields) {
            this.busy = true;
            try {
                this.set((await api.admin[this.kind].update(fields)).data);
            } catch (error) {
                refused(error);
            } finally {
                this.busy = false;
            }
        },
        async replace() {
            if (!(await confirmAction({ title: t('admin.mudeo.replaceTitle'), body: t(LINK_WORDS[this.kind].replace), action: t('admin.mudeo.replace') }))) return;
            this.busy = true;
            try {
                this.set((await api.admin[this.kind].replace()).data);
                toast(t('admin.mudeo.replaced'));
            } catch (error) {
                refused(error);
            } finally {
                this.busy = false;
            }
        },
        async copy() {
            try {
                await navigator.clipboard.writeText(this.link.url);
                toast(t('share.copied'));
            } catch {
                toast(t('share.copyFailed'), { tone: 'error' });
            }
        },
    },
};

export const MudeoSection = {
    components: { BetaLinkCard, Field, LoadFailed, Modal, Notice, StatusChip },
    props: { me: { type: Object, required: true } },
    data: () => ({
        list: null,
        failed: null,
        busy: null,
        next: null,
        query: '',
        found: null,
        pasted: '',
        marked: null,
        imported: null,
        cap: null,
    }),
    setup: () => ({ STATUS, formatNumber, formatPercent: (v) => formatNumber(v, { style: 'percent', maximumFractionDigits: 1 }), when, BOUNCE_STOP }),
    template: `
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.mudeo') }}</h1>
                <p class="mt-1 text-muted">{{ t('admin.mudeo.intro') }}</p>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!list" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <Notice v-if="list.newsReady === false" tone="warn">{{ t('admin.mudeo.newsNotSetUp') }}</Notice>
                <Notice v-if="list.postalAddressSet === false" tone="warn">{{ t('admin.mudeo.postalMissing') }}</Notice>

                <section class="card space-y-4 p-5" aria-labelledby="mudeo-counts">
                    <h2 id="mudeo-counts" class="text-lg font-bold text-cream">{{ t('admin.mudeo.countsTitle') }}</h2>
                    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <div v-for="c in counts" :key="c.label" class="rounded-2xl bg-raised p-3"><dt class="text-xs text-muted">{{ c.label }}</dt><dd class="text-2xl font-extrabold tabular-nums text-cream">{{ formatNumber(c.value) }}</dd></div>
                    </dl>
                    <p class="text-sm" :class="list.sentToday >= dailyCap ? 'text-bad' : list.sentToday >= warnAt ? 'text-warn' : 'text-muted'" role="status">{{ t('admin.mudeo.today', { sent: formatNumber(list.sentToday), cap: formatNumber(dailyCap) }) }}<template v-if="list.sentToday >= dailyCap"> {{ t('admin.mudeo.capReached') }}</template><template v-else-if="list.sentToday >= warnAt"> {{ t('admin.mudeo.nearCap') }}</template></p>
                    <form v-if="'dailyCap' in list" class="flex flex-wrap items-end gap-3" @submit.prevent="saveCap">
                        <label class="block text-sm text-muted">{{ t('admin.mudeo.cap') }}
                            <input v-model.number="cap" type="number" min="1" max="2000" class="input mt-1 w-28" />
                        </label>
                        <button type="submit" class="btn-secondary btn-sm" :disabled="busy === 'cap' || cap === list.dailyCap">{{ t('common.save') }}</button>
                        <span v-if="!sudo" class="text-xs text-muted">{{ t('admin.sudoFirst') }}</span>
                    </form>
                    <p v-if="list.importedAt" class="text-xs text-muted">{{ t('admin.mudeo.importedAt', { when: when(list.importedAt) }) }}</p>
                </section>

                <section class="card space-y-4 p-5" aria-labelledby="mudeo-send">
                    <h2 id="mudeo-send" class="text-lg font-bold text-cream">{{ t('admin.mudeo.sendTitle') }}</h2>
                    <p class="text-sm text-muted">{{ t('admin.mudeo.sendHint') }}</p>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="btn-secondary" :disabled="!!busy || list.newsReady === false" @click="sendTest">{{ busy === 'test' ? t('admin.mudeo.sendingTest') : t('admin.mudeo.test') }}</button>
                        <button type="button" class="btn-primary" :disabled="!!busy || !canSend" @click="sendBatch">{{ sending ? t('admin.mudeo.sending') : t('admin.mudeo.send', { count: Math.min(50, list.toSend) }) }}</button>
                    </div>
                    <p v-if="!sudo" class="text-xs text-muted">{{ t('admin.sudoFirst') }}</p>
                    <div v-if="list.lastBatch" class="space-y-2 rounded-2xl bg-raised p-4 text-sm" role="status">
                        <p class="font-semibold text-cream">{{ batchHeading }}</p>
                        <p v-if="list.lastBatch.mode === 'app' && list.lastBatch.status !== 'sending'" class="text-cream">{{ t('admin.mudeo.result', { delivered: formatNumber(list.lastBatch.delivered ?? 0), bounced: formatNumber(list.lastBatch.bounced ?? 0), failed: formatNumber(list.lastBatch.failed ?? 0), rate: formatPercent(list.lastBatch.bounceRate ?? 0) }) }}</p>
                        <Notice v-if="(list.lastBatch.bounceRate ?? 0) > BOUNCE_STOP" tone="bad">{{ t('admin.mudeo.stopToday') }}</Notice>
                        <Notice v-if="list.lastBatch.error" tone="bad">{{ list.lastBatch.error }}</Notice>
                    </div>
                </section>

                <section class="card space-y-4 p-5" aria-labelledby="mudeo-people">
                    <h2 id="mudeo-people" class="text-lg font-bold text-cream">{{ t('admin.mudeo.peopleTitle') }}</h2>
                    <form class="flex gap-2" role="search" @submit.prevent="search">
                        <label class="sr-only" for="mudeo-search">{{ t('admin.mudeo.search') }}</label>
                        <input id="mudeo-search" v-model="query" type="search" class="input flex-1" :placeholder="t('admin.mudeo.searchPlaceholder')" />
                        <button type="submit" class="btn-secondary" :disabled="!query.trim()">{{ t('admin.mudeo.search') }}</button>
                    </form>
                    <ul v-if="found" class="divide-y divide-line rounded-2xl border border-line text-sm">
                        <li v-for="c in found" :key="c.id" class="flex flex-wrap items-center gap-3 px-3 py-2">
                            <span class="min-w-0 flex-1"><span class="block truncate text-cream" translate="no">{{ c.email }}</span><span class="text-muted" translate="no">{{ c.name }}</span></span>
                            <StatusChip :tone="STATUS[c.status]?.tone ?? 'muted'" :label="t(STATUS[c.status]?.label ?? STATUS.to_send.label)" />
                            <template v-if="c.status === 'to_send' || c.status === 'sent'">
                                <button type="button" class="btn-ghost btn-sm" @click="mark([c.email], 'opted_out')">{{ t('admin.mudeo.optOut') }}</button>
                                <button type="button" class="btn-ghost btn-sm" @click="mark([c.email], 'bounced')">{{ t('admin.mudeo.bounced') }}</button>
                            </template>
                        </li>
                        <li v-if="!found.length" class="px-3 py-2 text-muted">{{ t('admin.mudeo.noneFound') }}</li>
                    </ul>
                    <form class="space-y-2" @submit.prevent>
                        <label class="block text-sm font-semibold text-muted" for="mudeo-paste">{{ t('admin.mudeo.paste') }}</label>
                        <textarea id="mudeo-paste" v-model="pasted" rows="3" class="input" translate="no" :placeholder="t('admin.mudeo.pastePlaceholder')"></textarea>
                        <div class="flex flex-wrap gap-3">
                            <button type="button" class="btn-secondary btn-sm" :disabled="!pastedList.length || busy === 'mark'" @click="mark(pastedList, 'opted_out')">{{ t('admin.mudeo.markOptedOut', { count: pastedList.length }) }}</button>
                            <button type="button" class="btn-secondary btn-sm" :disabled="!pastedList.length || busy === 'mark'" @click="mark(pastedList, 'bounced')">{{ t('admin.mudeo.markBounced', { count: pastedList.length }) }}</button>
                        </div>
                        <p v-if="marked" class="text-sm text-cream" role="status">{{ t('admin.mudeo.marked', { count: marked.marked }) }}<template v-if="marked.notFound.length"> {{ t('admin.mudeo.notFound', { emails: marked.notFound.join(', ') }) }}</template></p>
                    </form>
                </section>

                <BetaLinkCard kind="earlyAccess" :me="me" />

                <section class="card space-y-3 p-5" aria-labelledby="mudeo-import">
                    <h2 id="mudeo-import" class="text-lg font-bold text-cream">{{ t('admin.mudeo.importTitle') }}</h2>
                    <p class="text-sm text-muted">{{ t('admin.mudeo.importHint') }}</p>
                    <label class="btn-secondary btn-sm w-fit cursor-pointer" :class="busy === 'import' ? 'pointer-events-none opacity-50' : ''">
                        {{ busy === 'import' ? t('admin.mudeo.importing') : t('admin.mudeo.import') }}
                        <input type="file" accept=".csv,text/csv" class="sr-only" @change="importFile($event.target.files[0]); $event.target.value = ''" />
                    </label>
                    <span v-if="!sudo" class="block text-xs text-muted">{{ t('admin.sudoFirst') }}</span>
                    <p v-if="imported" class="text-sm text-cream" role="status">{{ t('admin.mudeo.imported', { imported: formatNumber(imported.imported) }) }} {{ skippedText }}</p>
                </section>

                <details class="card p-5">
                    <summary class="cursor-pointer font-bold text-cream">{{ t('admin.mudeo.fallbackTitle') }}</summary>
                    <div class="mt-4 space-y-3">
                        <p class="text-sm text-muted">{{ t('admin.mudeo.fallbackHint') }}</p>
                        <button type="button" class="btn-secondary btn-sm" :disabled="!!busy" @click="loadNext">{{ t('admin.mudeo.showNext') }}</button>
                        <template v-if="next">
                            <p v-if="!next.emails.length" class="text-sm text-muted">{{ t('admin.mudeo.allSent') }}</p>
                            <template v-else>
                                <textarea class="input text-xs" rows="4" readonly translate="no" :value="next.line"></textarea>
                                <Notice v-if="next.fits === false" tone="warn">{{ t('admin.mudeo.wontFit') }}</Notice>
                                <div class="flex flex-wrap gap-3">
                                    <button type="button" class="btn-secondary btn-sm" @click="copy(next.line)">{{ t('share.copy') }}</button>
                                    <button type="button" class="btn-primary btn-sm" :disabled="!!busy || next.fits === false" @click="markSent">{{ t('admin.mudeo.markSent', { count: next.ids.length }) }}</button>
                                </div>
                            </template>
                        </template>
                        <button v-if="list.lastBatch && list.lastBatch.mode !== 'app'" type="button" class="btn-ghost btn-sm" :disabled="!!busy" @click="undoBatch">{{ t('admin.mudeo.undo') }}</button>
                    </div>
                </details>
            </template>
        </div>`,
    computed: {
        sudo: (vm) => inSudo(vm.me),
        dailyCap() {
            return this.list?.dailyCap ?? this.list?.limit ?? 200;
        },
        warnAt() {
            return this.list?.warnAt ?? Math.floor(this.dailyCap * 0.8);
        },
        counts() {
            const l = this.list;
            return [
                { label: t('admin.mudeo.total'), value: l.total },
                { label: t('admin.mudeo.sent'), value: l.sent },
                { label: t('admin.mudeo.toSend'), value: l.toSend },
                { label: t('admin.mudeo.optedOut'), value: l.optedOut },
                { label: t('admin.mudeo.bouncedCount'), value: l.bounced },
            ];
        },
        sending() {
            return this.list?.lastBatch?.status === 'sending';
        },
        canSend() {
            const l = this.list;
            return l && l.newsReady !== false && l.postalAddressSet !== false && l.toSend > 0 && l.sentToday < this.dailyCap && !this.sending && !((l.lastBatch?.bounceRate ?? 0) > BOUNCE_STOP && this.sentToday(l.lastBatch));
        },
        batchHeading() {
            const b = this.list.lastBatch;
            if (b.status === 'sending') return t('admin.mudeo.batchSending', { count: formatNumber(b.count), when: when(b.sentAt) });
            if (b.status === 'failed') return t('admin.mudeo.batchFailed', { count: formatNumber(b.count), when: when(b.sentAt) });
            return b.mode === 'manual' ? t('admin.mudeo.batchMarked', { count: formatNumber(b.count), when: when(b.sentAt) }) : t('admin.mudeo.batchSent', { count: formatNumber(b.count), when: when(b.sentAt) });
        },
        pastedList() {
            return [...new Set(this.pasted.split(/[\s,;]+/).map((e) => e.trim().toLowerCase()).filter((e) => e.includes('@')))].slice(0, 500);
        },
        skippedText() {
            const s = this.imported?.skipped ?? {};
            const parts = Object.entries(s).filter(([why, n]) => n > 0 && SKIPPED[why]).map(([why, n]) => t(SKIPPED[why], { count: n }));
            return parts.length ? t('admin.mudeo.skippedList', { list: parts.join(', ') }) : '';
        },
    },
    created() {
        this.load();
    },
    unmounted() {
        clearTimeout(this.poll);
    },
    methods: {
        /** Whether a batch went today (a stop for the day only holds today). */
        sentToday(batch) {
            return !!batch?.sentAt && new Date(batch.sentAt).toDateString() === new Date().toDateString();
        },
        async load() {
            this.failed = null;
            try {
                this.setList((await api.admin.mudeo.show()).data);
            } catch (error) {
                if (!sendToSignIn(error)) this.failed = explain(error) ?? t('admin.requestFailed');
            }
        },
        setList(data) {
            this.list = data;
            this.cap = data.dailyCap ?? null;
            clearTimeout(this.poll);
            // A batch on its way: its result comes in by itself.
            if (data.lastBatch?.status === 'sending') this.poll = setTimeout(() => this.refresh(), POLL_MS);
        },
        async refresh() {
            try {
                this.setList((await api.admin.mudeo.show()).data);
            } catch (error) {
                refused(error);
            }
        },
        async run(name, action) {
            this.busy = name;
            try {
                return await action();
            } catch (error) {
                refused(error);
                return null;
            } finally {
                this.busy = null;
            }
        },
        async sendTest() {
            await this.run('test', async () => {
                try {
                    const { data } = await api.admin.mudeo.test();
                    toast(t('admin.mudeo.testSent', { email: data.sentTo }));
                } catch (error) {
                    if (!SEND_REFUSED[error?.code]) throw error;
                    toast(t(SEND_REFUSED[error.code]), { tone: 'error' });
                }
            });
        },
        async sendBatch() {
            const count = Math.min(50, this.list.toSend);
            const sure = await confirmAction({ title: t('admin.mudeo.sendTitleAsk', { count }), body: t('admin.mudeo.sendBody'), action: t('admin.mudeo.send', { count }), destructive: false });
            if (!sure) return;
            await this.run('send', async () => {
                try {
                    this.setList((await api.admin.mudeo.send()).data);
                } catch (error) {
                    if (error?.code === 'daily_limit') toast(t('admin.mudeo.refused.dailyLimit', { sent: error.body?.sentToday ?? this.list.sentToday, cap: error.body?.limit ?? this.dailyCap }), { tone: 'error' });
                    else if (SEND_REFUSED[error?.code]) toast(t(SEND_REFUSED[error.code]), { tone: 'error' });
                    else throw error;
                    await this.refresh();
                }
            });
        },
        async saveCap() {
            await this.run('cap', async () => this.setList((await api.admin.mudeo.update({ dailyCap: this.cap })).data));
        },
        async search() {
            await this.run('search', async () => (this.found = (await api.admin.mudeo.contacts({ q: this.query.trim() })).data));
        },
        async mark(emails, as) {
            await this.run('mark', async () => {
                this.marked = (await api.admin.mudeo.mark({ emails, as })).data;
                if (emails === this.pastedList) this.pasted = '';
                if (this.query.trim()) this.found = (await api.admin.mudeo.contacts({ q: this.query.trim() })).data;
                await this.refresh();
            });
        },
        async importFile(file) {
            if (!file) return;
            const form = new FormData();
            form.append('file', file);
            await this.run('import', async () => {
                this.imported = (await api.admin.mudeo.import(form)).data;
                await this.refresh();
            });
        },
        async loadNext() {
            await this.run('next', async () => (this.next = (await api.admin.mudeo.next()).data));
        },
        async markSent() {
            await this.run('mark-sent', async () => {
                try {
                    this.setList((await api.admin.mudeo.batches.create({ ids: this.next.ids })).data);
                    this.next = (await api.admin.mudeo.next()).data;
                } catch (error) {
                    if (error?.code !== 'daily_limit') throw error;
                    toast(t('admin.mudeo.refused.dailyLimit', { sent: error.body?.sentToday ?? this.list.sentToday, cap: error.body?.limit ?? this.dailyCap }), { tone: 'error' });
                }
            });
        },
        async undoBatch() {
            if (!(await confirmAction({ title: t('admin.mudeo.undoTitle'), body: t('admin.mudeo.undoBody'), action: t('admin.mudeo.undo') }))) return;
            await this.run('undo', async () => {
                this.setList((await api.admin.mudeo.batches.undo()).data);
                this.next = null;
            });
        },
        async copy(text) {
            try {
                await navigator.clipboard.writeText(text);
                toast(t('share.copied'));
            } catch {
                toast(t('share.copyFailed'), { tone: 'error' });
            }
        },
        errorsFrom,
    },
};
