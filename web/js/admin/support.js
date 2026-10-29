// The support inbox (contract/api.md, admin.support.*): what people sent from Contact us, open ones
// first, then one in full: the message, the details that came with it, the answers sent, and a reply
// (from a saved reply, or written) that's emailed to them, closing it if done. Close and Reopen.

import { api } from '../api.js';
import { t, tk } from '../i18n.js';
import { navigate, route } from '../router.js';
import { EmptyState, Field, StatusChip } from '../components/form.js';
import { toast } from '../components/ui.js';
import { errorsFrom, LoadFailed, loadInto, refused, when } from './common.js';

const FILTERS = { open: tk('admin.support.filter.open'), answered: tk('admin.support.filter.answered'), closed: tk('admin.support.filter.closed'), all: tk('admin.support.filter.all') };
const STATUS = { open: { tone: 'info', label: tk('admin.support.status.open') }, answered: { tone: 'good', label: tk('admin.support.status.answered') }, closed: { tone: 'muted', label: tk('admin.support.status.closed') } };
const CATEGORY = { recording: tk('contact.category.recording'), account: tk('contact.category.account'), payments: tk('contact.category.payments'), coaching: tk('contact.category.coaching'), other: tk('contact.category.other'), bug: tk('contact.category.bug'), idea: tk('contact.category.idea'), diagnostics: tk('admin.support.diagnostics') };

export const SupportSection = {
    components: { EmptyState, Field, LoadFailed, StatusChip },
    props: { me: { type: Object, required: true } },
    data: () => ({ messages: null, failed: false, cursor: null, loadingMore: false, filter: 'open', open: null, reply: '', close: true, sending: false, errors: null }),
    setup: () => ({ FILTERS, STATUS, CATEGORY, when, route }),
    template: `
        <div class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.support') }}</h1>
                <div class="seg flex" role="radiogroup" :aria-label="t('admin.support.show')">
                    <button v-for="(word, key) in FILTERS" :key="key" type="button" role="radio" :aria-checked="filter === key ? 'true' : 'false'" class="seg-option" @click="pick(key)">{{ t(word) }}</button>
                </div>
            </div>
            <div class="grid gap-6 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
                <div>
                    <LoadFailed v-if="failed" :message="failed" @retry="load()" />
                    <p v-else-if="!messages" class="text-muted" role="status">{{ t('admin.loading') }}</p>
                    <EmptyState v-else-if="!messages.length" icon="info" :title="t('admin.support.none')" />
                    <ul v-else class="space-y-2">
                        <li v-for="m in messages" :key="m.id">
                            <a :href="'/admin/support/' + m.id" class="card-interactive block p-4" :class="open?.id === m.id ? 'border-accent' : ''" @click.prevent="navigate('/admin/support/' + m.id)">
                                <span class="flex items-center justify-between gap-2"><span class="truncate font-bold text-cream" translate="no">{{ m.from.account?.name ?? m.from.email }}</span><StatusChip :tone="STATUS[m.status].tone" :label="t(STATUS[m.status].label)" /></span>
                                <span class="mt-1 block truncate text-sm text-cream" translate="no">{{ m.preview }}</span>
                                <span class="mt-1 block text-xs text-muted">{{ t(CATEGORY[m.category] ?? CATEGORY.other) }} · {{ when(m.createdAt) }}</span>
                            </a>
                        </li>
                    </ul>
                    <button v-if="cursor && !failed" type="button" class="btn-secondary mt-3" :disabled="loadingMore" @click="load(true)">{{ loadingMore ? t('admin.loading') : t('admin.loadMore') }}</button>
                </div>
                <section v-if="open" class="card space-y-5 p-6" aria-labelledby="message-heading">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 id="message-heading" class="text-lg font-bold text-cream" translate="no">{{ open.from.account?.name ?? open.from.email }}</h2>
                            <p class="text-sm text-muted"><span translate="no">{{ open.from.email }}</span> · {{ t(CATEGORY[open.category] ?? CATEGORY.other) }} · {{ when(open.createdAt) }}</p>
                        </div>
                        <button v-if="open.status === 'closed'" type="button" class="btn-secondary btn-sm" @click="setStatus('open')">{{ t('admin.support.reopen') }}</button>
                        <button v-else type="button" class="btn-ghost btn-sm" @click="setStatus('closed')">{{ t('admin.support.close') }}</button>
                    </div>
                    <p class="whitespace-pre-line text-cream" translate="no">{{ open.body }}</p>
                    <dl v-if="open.context" class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-2xl bg-raised p-3 text-xs text-muted">
                        <template v-for="(value, key) in open.context" :key="key"><dt class="font-semibold" translate="no">{{ key }}</dt><dd class="min-w-0 break-words" translate="no">{{ typeof value === 'object' ? JSON.stringify(value) : value }}</dd></template>
                    </dl>
                    <ol v-if="open.replies.length" class="space-y-3">
                        <li v-for="(r, i) in open.replies" :key="i" class="rounded-2xl border border-edge p-4">
                            <p class="text-xs text-muted"><span translate="no">{{ r.by?.name }}</span> · {{ when(r.sentAt) }}</p>
                            <p class="mt-1 whitespace-pre-line text-cream" translate="no">{{ r.body }}</p>
                        </li>
                    </ol>
                    <form class="space-y-3" @submit.prevent="send">
                        <Field :label="t('admin.support.reply')" :help="t('admin.support.replyHelp')" :error="errors?.body" required v-slot="f">
                            <textarea :id="f.id" v-model="reply" rows="6" class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid"></textarea>
                        </Field>
                        <div v-if="open.savedReplies.length" class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="text-muted">{{ t('admin.support.startFrom') }}</span>
                            <button v-for="s in open.savedReplies" :key="s.key" type="button" class="tap-44 chip hover:text-cream" @click="reply = s.body">{{ s.title }}</button>
                        </div>
                        <label class="check-row"><input v-model="close" type="checkbox" class="checkbox" /> {{ t('admin.support.closeAfter') }}</label>
                        <button type="submit" class="btn-primary" :disabled="sending || !reply.trim()">{{ sending ? t('admin.support.sending') : t('admin.support.send') }}</button>
                    </form>
                </section>
                <EmptyState v-else-if="messages?.length" icon="info" :title="t('admin.support.pick')" />
            </div>
        </div>`,
    watch: {
        'route.params.id': {
            handler(id) {
                if (id) this.show(id);
                else this.open = null;
            },
            immediate: true,
        },
    },
    created() {
        this.load();
    },
    methods: {
        load(more = false) {
            return loadInto(this, 'messages', () => api.admin.support.list({ query: { status: this.filter, cursor: more ? this.cursor : undefined } }), { more });
        },
        pick(filter) {
            this.filter = filter;
            this.messages = null;
            this.load();
        },
        async show(id) {
            try {
                this.open = (await api.admin.support.show({ params: { id } })).data;
                this.reply = '';
                this.errors = null;
            } catch (error) {
                refused(error);
            }
        },
        keep(message) {
            this.open = message;
            const i = this.messages?.findIndex((m) => m.id === message.id) ?? -1;
            if (i >= 0) this.messages.splice(i, 1, message);
        },
        async send() {
            this.sending = true;
            this.errors = null;
            try {
                this.keep((await api.admin.support.reply({ params: { id: this.open.id }, body: { body: this.reply.trim(), close: this.close } })).data);
                this.reply = '';
                toast(t('admin.support.sent'));
            } catch (error) {
                this.errors = errorsFrom(error);
                if (!this.errors) refused(error);
            } finally {
                this.sending = false;
            }
        },
        async setStatus(status) {
            try {
                this.keep((await api.admin.support.update({ params: { id: this.open.id }, body: { status } })).data);
            } catch (error) {
                refused(error);
            }
        },
        navigate,
    },
};
