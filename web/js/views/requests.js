// Song requests (docs/more-features-plan.md section 8, N7; contract requests.*): the most wanted songs,
// each with its votes and where it stands, a vote in one tap, "Yours", and Request a song in a sheet
// that shows requests like the title first, so people vote instead of asking again. A request never
// shows who asked (no one's name, an under-18's included). Reports go to the admins (reports.create's
// requestId). Shown while `song_requests` is on, and to staff before (Hillel: off until he turns it on).

import { ApiError, api } from '../api.js';
import { t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { account } from '../stores/account.js';
import { AccountWall } from '../components/account-wall.js';
import { AppScreen } from '../components/page.js';
import { EmptyState, Field } from '../components/form.js';
import { Icon } from '../components/icon.js';
import { Modal, toast } from '../components/ui.js';

/** Whether this account sees song requests: the switch on, or staff previewing it. */
export const requestsOpen = () => !!(account.config?.songRequests || account.me?.songRequests || account.me?.staff);

const STATUS = {
    requested: tk('requests.status.requested'),
    checking: tk('requests.status.checking'),
    arranging: tk('requests.status.arranging'),
    ready: tk('requests.status.ready'),
    cant: tk('requests.status.cant'),
};
const SORTS = { wanted: tk('requests.sort.wanted'), newest: tk('requests.sort.newest') };
const REASONS = { spam: tk('requests.report.reasons.spam'), offensive: tk('requests.report.reasons.offensive'), personal: tk('requests.report.reasons.personal'), other: tk('requests.report.reasons.other') };
// The server's refusals, said plainly.
const REFUSED = { email_unverified: tk('requests.refused.unverified'), rate_limited: tk('requests.refused.limit'), requests_closed: tk('requests.refused.closed') };
const FAILED = tk('requests.refused.failed');

function refusal(error) {
    const code = error instanceof ApiError ? error.code : null;
    toast(t(REFUSED[code] ?? FAILED), { tone: 'error' });
}

/** One request: its title, writer and why, where it stands, and its votes (one tap, pressed when it's yours). */
const RequestRow = {
    components: { Icon },
    props: { request: { type: Object, required: true } },
    emits: ['vote', 'report'],
    setup: () => ({ STATUS, account }),
    template: `
        <li class="flex items-start gap-3 py-4" :data-request="request.id">
            <button type="button" data-ui="vote" class="tap-44 flex w-14 shrink-0 flex-col items-center gap-0.5 rounded-xl border py-2 text-cream" :class="request.voted ? 'border-accent bg-accent/15' : 'border-line hover:bg-raised'" :aria-pressed="request.voted ? 'true' : 'false'" :aria-label="request.voted ? t('requests.unvoteLabel', { title: request.title, count: request.votes }) : t('requests.voteLabel', { title: request.title, count: request.votes })" data-request-vote @click="$emit('vote', request)">
                <!-- Voted shows a check, not only a colour (design rules: state never by colour alone). -->
                <Icon :name="request.voted ? 'check' : 'arrow'" :size="14" :class="request.voted ? '' : '-rotate-90'" />
                <span class="text-sm font-bold tabular-nums" data-votes>{{ request.votes }}</span>
            </button>
            <div class="min-w-0 flex-1 space-y-1">
                <p class="font-bold text-cream" translate="no">{{ request.title }}<span v-if="request.writer" class="font-normal text-muted"> · {{ request.writer }}</span></p>
                <p v-if="request.why" class="text-sm text-muted" translate="no">{{ request.why }}</p>
                <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                    <span class="chip" :data-request-status="request.status">{{ t(STATUS[request.status] ?? STATUS.requested) }}</span>
                    <a v-if="request.status === 'ready' && request.template" :href="'/t/' + request.template.id" class="link tap-44" @click.prevent="navigate('/t/' + request.template.id)">{{ t('requests.learnIt') }}</a>
                    <span v-if="request.status === 'cant'" class="text-muted">{{ t('requests.cantLine') }}</span>
                    <span v-if="request.mine" class="text-muted">{{ t('requests.youAsked') }}</span>
                </p>
            </div>
            <button v-if="account.me && !request.mine" type="button" class="tap-44 grid size-8 shrink-0 place-items-center rounded-full text-muted hover:bg-raised hover:text-cream" :aria-label="t('requests.report.button', { title: request.title })" data-request-report @click="$emit('report', request)"><Icon name="more" :size="16" /></button>
        </li>`,
    methods: { navigate },
};

/** Request a song: requests like the title show first (vote instead), then the request itself. */
const AskSheet = {
    components: { Field, Modal, RequestRow },
    props: { title: { type: String, default: '' } },
    emits: ['close', 'asked', 'vote'],
    data() {
        return { fields: { title: this.title, writer: '', why: '' }, like: [], busy: false, errors: null, timer: null };
    },
    template: `
        <Modal :title="t('requests.ask.title')" @close="$emit('close')">
            <form class="space-y-4" data-request-ask @submit.prevent="send">
                <Field :label="t('requests.ask.song')" :error="errors?.title" required v-slot="f">
                    <input :id="f.id" v-model="fields.title" class="input w-full" maxlength="120" required autocomplete="off" :aria-describedby="f.describedby" data-ask-title @input="find" />
                </Field>
                <!-- Already asked for: vote for it instead of asking again. -->
                <section v-if="like.length" class="space-y-1 rounded-xl border border-line p-3" aria-labelledby="ask-like-h" data-ask-like>
                    <p id="ask-like-h" class="text-sm font-semibold text-cream">{{ t('requests.ask.like') }}</p>
                    <ul class="divide-y divide-line"><RequestRow v-for="r in like" :key="r.id" :request="r" @vote="voteLike" /></ul>
                </section>
                <Field :label="t('requests.ask.writer')" :help="t('requests.ask.writerHelp')" :error="errors?.writer" v-slot="f">
                    <input :id="f.id" v-model="fields.writer" class="input w-full" maxlength="120" :aria-describedby="f.describedby" />
                </Field>
                <Field :label="t('requests.ask.why')" :help="t('requests.ask.whyHelp')" :error="errors?.why" v-slot="f">
                    <textarea :id="f.id" v-model="fields.why" class="input w-full" rows="2" maxlength="300" :aria-describedby="f.describedby"></textarea>
                </Field>
                <p class="text-sm text-muted">{{ t('requests.ask.note') }}</p>
                <div class="flex flex-col gap-3 sm:flex-row-reverse sm:justify-start">
                    <button type="submit" class="btn-primary" :disabled="busy || !fields.title.trim()">{{ busy ? t('common.saving') : t('requests.ask.send') }}</button>
                    <button type="button" class="btn-secondary" @click="$emit('close')">{{ t('common.cancel') }}</button>
                </div>
            </form>
        </Modal>`,
    created() {
        if (this.fields.title) this.find();
    },
    unmounted() {
        clearTimeout(this.timer);
    },
    methods: {
        /** Requests like the title, a moment after typing stops (a page timer, never audio). */
        find() {
            clearTimeout(this.timer);
            const q = this.fields.title.trim();
            if (q.length < 3) {
                this.like = [];
                return;
            }
            this.timer = setTimeout(async () => {
                try {
                    this.like = (await api.requests.list({ q })).data.slice(0, 3);
                } catch (error) {
                    console.info('Requests like it did not load', error?.message ?? error);
                }
            }, 300);
        },
        /** A vote on a request like it: kept here, and in the page's list. */
        async voteLike(request) {
            try {
                const now = (await (request.voted ? api.requests.unvote(request.id) : api.requests.vote(request.id))).data;
                this.like = this.like.map((r) => (r.id === now.id ? now : r));
                this.$emit('vote', now);
            } catch (error) {
                refusal(error);
            }
        },
        async send() {
            this.busy = true;
            this.errors = null;
            try {
                const answer = await api.requests.create({ title: this.fields.title.trim(), writer: this.fields.writer.trim() || null, why: this.fields.why.trim() || null });
                // The same song asked for already: the server voted for it instead.
                // Asked before by someone else: the server voted for it instead (it's not yours).
                toast(answer.data.mine ? t('requests.ask.sent', { title: answer.data.title }) : t('requests.ask.voted', { title: answer.data.title }));
                this.$emit('asked', answer.data);
            } catch (error) {
                if (error instanceof ApiError && error.code === 'validation_failed') this.errors = error.errors ?? {};
                else refusal(error);
            } finally {
                this.busy = false;
            }
        },
    },
};

/** Report a request: a reason, a note, then thanks; the admins look (two reports hold it). */
const ReportRequest = {
    components: { Modal },
    props: { request: { type: Object, required: true } },
    emits: ['close'],
    data: () => ({ reason: 'spam', note: '', busy: false }),
    setup: () => ({ REASONS }),
    template: `
        <Modal :title="t('requests.report.title')" @close="$emit('close')">
            <form class="space-y-4" data-request-report-sheet @submit.prevent="send">
                <p class="font-semibold text-cream" translate="no">{{ request.title }}</p>
                <fieldset class="space-y-3">
                    <legend class="label">{{ t('requests.report.why') }}</legend>
                    <label v-for="(label, r) in REASONS" :key="r" class="check-row"><input v-model="reason" type="radio" :value="r" class="radio" /> {{ t(label) }}</label>
                </fieldset>
                <label class="block space-y-1.5"><span class="label">{{ t('requests.report.note') }}</span><textarea v-model="note" class="input min-h-20 w-full" maxlength="500" rows="3"></textarea></label>
                <div class="flex flex-col gap-3 sm:flex-row-reverse sm:justify-start">
                    <button type="submit" class="btn-primary" :disabled="busy">{{ t('requests.report.send') }}</button>
                    <button type="button" class="btn-secondary" :disabled="busy" @click="$emit('close')">{{ t('common.cancel') }}</button>
                </div>
            </form>
        </Modal>`,
    methods: {
        async send() {
            this.busy = true;
            try {
                await api.reports.create({ requestId: this.request.id, reason: this.reason, note: this.note.trim(), website: '' });
                toast(t('requests.report.thanks'));
                this.$emit('close');
            } catch (error) {
                console.warn('The request report was not sent', error);
                toast(t('requests.report.failed'), { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },
    },
};

export const RequestsView = {
    components: { AccountWall, AskSheet, EmptyState, Icon, AppScreen, ReportRequest, RequestRow },
    data: () => ({ list: null, cursor: null, failed: false, busy: false, sort: 'wanted', yours: false, asking: false, askTitle: '', reporting: null, walled: false }),
    setup: () => ({ SORTS, account }),
    template: `
        <AppScreen :title="t('requests.title')" back="/" measure="max-w-3xl" data-requests>
            <div class="mt-4 space-y-6">
                <EmptyState v-if="!open" icon="info" :title="t('requests.closed')" :body="t('requests.closedHint')" />
                <template v-else>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <p class="max-w-prose text-muted">{{ t('requests.intro') }}</p>
                        <button type="button" class="btn-primary" data-request-open @click="ask()">{{ t('requests.ask.button') }}</button>
                    </div>
                    <AccountWall v-if="walled" reason="requests" bare />
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="seg inline-flex" role="radiogroup" :aria-label="t('requests.sort.label')">
                            <button v-for="(label, key) in SORTS" :key="key" type="button" role="radio" :aria-checked="sort === key ? 'true' : 'false'" class="seg-option" @click="setSort(key)">{{ t(label) }}</button>
                        </div>
                        <button v-if="account.me" type="button" class="filter-chip" :aria-pressed="yours ? 'true' : 'false'" data-requests-yours @click="yours = !yours">{{ t('requests.yours') }}</button>
                    </div>
                    <p v-if="failed" class="text-sm text-bad" role="alert">{{ t('requests.failed') }} <button type="button" class="link" @click="load">{{ t('common.tryAgain') }}</button></p>
                    <p v-else-if="list === null" class="text-muted" role="status">{{ t('requests.loading') }}</p>
                    <p v-else-if="!shown.length" class="text-muted" data-requests-empty>{{ yours ? t('requests.emptyYours') : t('requests.empty') }}</p>
                    <ul v-else class="card divide-y divide-line px-4" data-requests-list>
                        <RequestRow v-for="r in shown" :key="r.id" :request="r" @vote="vote" @report="reporting = $event" />
                    </ul>
                    <button v-if="cursor" type="button" class="btn-secondary w-full" :disabled="busy" @click="more">{{ t('requests.more') }}</button>
                </template>
            </div>
            <AskSheet v-if="asking" :title="askTitle" @close="asking = false" @asked="asked" @vote="put" />
            <ReportRequest v-if="reporting" :request="reporting" @close="reporting = null" />
        </AppScreen>`,
    computed: {
        open: () => requestsOpen(),
        /** Yours: the ones you asked for or voted. */
        shown() {
            return this.yours ? (this.list ?? []).filter((r) => r.mine || r.voted) : this.list ?? [];
        },
    },
    created() {
        const q = new URLSearchParams(location.search);
        if (this.open) this.load();
        if (q.get('ask') !== null) this.ask(q.get('title') ?? '');
    },
    methods: {
        async load() {
            this.failed = false;
            this.list = null;
            try {
                const page = await api.requests.list({ sort: this.sort });
                this.list = page.data;
                this.cursor = page.meta?.nextCursor ?? null;
            } catch (error) {
                console.warn('Requests did not load', error);
                this.failed = true;
            }
        },
        async more() {
            this.busy = true;
            try {
                const page = await api.requests.list({ sort: this.sort, cursor: this.cursor });
                this.list = [...this.list, ...page.data.filter((r) => !this.list.some((had) => had.id === r.id))];
                this.cursor = page.meta?.nextCursor ?? null;
            } catch (error) {
                console.warn('More requests did not load', error);
            } finally {
                this.busy = false;
            }
        },
        setSort(sort) {
            this.sort = sort;
            this.load();
        },
        /** Asking and voting need an account: a guest gets the wall, said once here. */
        ask(title = '') {
            if (!account.me) {
                this.walled = true;
                return;
            }
            this.askTitle = title;
            this.asking = true;
        },
        asked(request) {
            this.asking = false;
            this.put(request);
        },
        /** Keep a request's new state in the list (or add it at the top). */
        put(request) {
            if (!this.list) return;
            const i = this.list.findIndex((r) => r.id === request.id);
            if (i < 0) this.list = [request, ...this.list];
            else this.list.splice(i, 1, request);
        },
        async vote(request) {
            if (!account.me) {
                this.walled = true;
                return;
            }
            try {
                this.put((await (request.voted ? api.requests.unvote(request.id) : api.requests.vote(request.id))).data);
            } catch (error) {
                refusal(error);
            }
        },
    },
};
