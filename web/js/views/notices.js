// Statements of reasons (contract/api.md notices.*; the DSA; the finish-line audit, 2026-11-13): when something someone
// shared is hidden or taken down, or their account or coaching limited, they're told what was done and why, with our
// note, whether it was automatic, and the right to Ask for a review once, within 6 months. Two ways in: /notices (a
// signed-in person's own, from Settings) and /notice?token=... (the private link a suspended person gets by email; the
// token is taken out of the address at once, as /verify's is, js/stores/account.js keepLinkToken).
//
// LAWYER TO REVIEW: the words under notices.* in locales/en.json are a draft until Hillel's lawyer gives the final ones
// (docs/web-finish-audit-2026-11-13.md). Each screen says so in one quiet line (notices.draft) until DRAFT is false.

import { api, ApiError } from '../api.js';
import { formatDate, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { account, linkToken } from '../stores/account.js';
import { EmptyState, Field, Notice, StatusChip } from '../components/form.js';
import { AppScreen } from '../components/page.js';
import { Modal, toast } from '../components/ui.js';
import { KINDS, RULES } from '../notice-words.js';

export const DRAFT = true; // the lawyer hasn't given the final words yet

// The rule's own page, to read in full.
const RULE_PAGES = { community_guidelines: '/legal/community-guidelines', copyright: '/legal/copyright', terms: '/legal/terms', coach_agreement: '/legal/coach-agreement' };
const APPEAL_MAX = 2000;

/** Where a notice's review stands: `open` (can be asked), `waiting`, `kept`, `reversed`, or `closed` (too late, never asked). */
export function reviewState(notice) {
    if (notice.appeal?.decision === 'kept' || notice.appeal?.decision === 'reversed') return notice.appeal.decision;
    if (notice.appeal) return 'waiting';
    return notice.canAppeal ? 'open' : 'closed';
}

const STATE_CHIPS = { waiting: { tone: 'info', word: tk('notices.state.waiting') }, kept: { tone: 'muted', word: tk('notices.state.kept') }, reversed: { tone: 'good', word: tk('notices.state.reversed') } };

/** One notice in full, with its review: asked for here, in a sheet that says what happens next. */
export const NoticeCard = {
    components: { Field, Modal, Notice, StatusChip },
    props: {
        notice: { type: Object, required: true },
        token: { type: String, default: null }, // the private link's, when it came by email
    },
    emits: ['changed'],
    data: () => ({ asking: false, text: '', busy: false, error: null }),
    setup: () => ({ KINDS, RULES, RULE_PAGES, STATE_CHIPS, APPEAL_MAX, DRAFT, formatDate }),
    computed: {
        state() {
            return reviewState(this.notice);
        },
        what() {
            const words = KINDS[this.notice.kind];
            return words ? t(words.what) : this.notice.what;
        },
        why() {
            return RULES[this.notice.rule] ? t(RULES[this.notice.rule]) : this.notice.why;
        },
    },
    template: `
        <article class="card space-y-4 p-5 md:p-6" :data-notice="notice.id" :data-review="state">
            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                <div class="min-w-0 space-y-1">
                    <h2 class="text-lg font-bold text-cream">{{ KINDS[notice.kind] ? t(KINDS[notice.kind].title) : notice.what }}</h2>
                    <p class="text-sm text-muted">{{ formatDate(notice.createdAt, { dateStyle: 'medium' }) }}<template v-if="notice.about?.title"> · <span translate="no">{{ notice.about.title }}</span></template></p>
                </div>
                <StatusChip v-if="STATE_CHIPS[state]" :tone="STATE_CHIPS[state].tone" :label="t(STATE_CHIPS[state].word)" />
            </div>
            <dl class="space-y-3">
                <div><dt class="eyebrow text-muted">{{ t('notices.whatLabel') }}</dt><dd class="text-cream">{{ what }}</dd></div>
                <div><dt class="eyebrow text-muted">{{ t('notices.whyLabel') }}</dt><dd class="text-cream">{{ why }} <a v-if="RULE_PAGES[notice.rule]" :href="RULE_PAGES[notice.rule]" class="link" target="_blank" rel="noopener">{{ t('notices.readRule') }}</a></dd></div>
                <div v-if="notice.note"><dt class="eyebrow text-muted">{{ t('notices.noteLabel') }}</dt><dd class="whitespace-pre-line text-cream">{{ notice.note }}</dd></div>
                <div><dt class="eyebrow text-muted">{{ t('notices.howLabel') }}</dt><dd class="text-cream">{{ notice.automatic ? t('notices.automatic') : t('notices.byPerson') }}</dd></div>
            </dl>
            <!-- The review: asked for once, within 6 months; then waiting; then the decision and its note. -->
            <div class="space-y-2 border-t border-line pt-4" data-notice-review>
                <template v-if="state === 'open'">
                    <p class="text-sm text-muted">{{ t('notices.review.canAsk') }}</p>
                    <button type="button" class="btn-primary" data-ask-review @click="asking = true">{{ t('notices.review.ask') }}</button>
                </template>
                <p v-else-if="state === 'closed'" class="text-sm text-muted">{{ t('notices.review.closed') }}</p>
                <template v-else>
                    <p class="text-sm text-cream" role="status">{{ state === 'waiting' ? t('notices.review.waiting', { date: formatDate(notice.appeal.at, { dateStyle: 'medium' }) }) : state === 'kept' ? t('notices.review.kept', { date: formatDate(notice.appeal.decidedAt, { dateStyle: 'medium' }) }) : t('notices.review.reversed', { date: formatDate(notice.appeal.decidedAt, { dateStyle: 'medium' }) }) }}</p>
                    <p v-if="notice.appeal.decisionNote" class="whitespace-pre-line text-sm text-cream" data-decision-note>{{ notice.appeal.decisionNote }}</p>
                    <details class="text-sm text-muted"><summary class="tap-44 cursor-pointer">{{ t('notices.review.yourText') }}</summary><p class="whitespace-pre-line pt-1">{{ notice.appeal.text }}</p></details>
                </template>
                <p class="text-xs text-muted">{{ t('notices.rights') }}</p>
            </div>
            <Modal v-if="asking" :title="t('notices.review.title')" :dismissable="!busy" @close="asking = false">
                <form class="space-y-4" @submit.prevent="ask">
                    <p class="text-cream">{{ t('notices.review.body') }}</p>
                    <Field :label="t('notices.review.label')" :error="error" required v-slot="f">
                        <textarea :id="f.id" v-model="text" rows="5" required :maxlength="APPEAL_MAX" class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid" data-review-text></textarea>
                    </Field>
                    <div class="flex flex-wrap justify-end gap-3">
                        <button type="button" class="btn-secondary" :disabled="busy" @click="asking = false">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary" :disabled="busy || text.trim().length < 2" data-send-review>{{ busy ? t('notices.review.sending') : t('notices.review.send') }}</button>
                    </div>
                </form>
            </Modal>
        </article>`,
    methods: {
        async ask() {
            this.busy = true;
            this.error = null;
            try {
                const { data } = await api.notices.appeal(this.token ?? this.notice.id, this.text.trim());
                this.asking = false;
                this.text = '';
                toast(t('notices.review.sent'));
                this.$emit('changed', data);
            } catch (error) {
                if (!(error instanceof ApiError)) throw error;
                if (error.code === 'validation_failed') this.error = Object.values(error.errors ?? {}).flat()[0] ?? t('notices.review.failed');
                else if (error.code === 'appeal_closed') {
                    this.asking = false;
                    this.$emit('changed', { ...this.notice, canAppeal: false });
                    toast(t('notices.review.closed'));
                } else this.error = t('notices.review.failed');
            } finally {
                this.busy = false;
            }
        },
    },
};

/** /notices: the signed-in person's own, newest first. */
export const NoticesView = {
    components: { AppScreen, EmptyState, NoticeCard, Notice },
    data: () => ({ notices: null, failed: false }),
    setup: () => ({ account, DRAFT }),
    template: `
        <AppScreen :title="t('notices.title')" back="/settings" measure="max-w-2xl" content-class="gap-4 pt-4 md:gap-6" data-ui="screen" class="bg-ink">
            <p class="-mt-3 text-muted md:-mt-5">{{ t('notices.intro') }}</p>
            <Notice v-if="failed" tone="bad">{{ t('notices.loadFailed') }} <button type="button" class="link" @click="load">{{ t('common.tryAgain') }}</button></Notice>
            <p v-else-if="notices === null" class="text-muted" role="status">{{ t('common.loading') }}</p>
            <EmptyState v-else-if="!notices.length" icon="check" :title="t('notices.noneTitle')" :body="t('notices.none')" />
            <NoticeCard v-for="(notice, i) in notices ?? []" :key="notice.id" :notice="notice" @changed="(n) => (notices[i] = n)" />
            <p v-if="DRAFT" class="text-xs text-muted" data-notices-draft>{{ t('notices.draft') }}</p>
        </AppScreen>`,
    created() {
        if (!account.me) {
            navigate('/settings');
            return;
        }
        this.load();
    },
    methods: {
        async load() {
            this.failed = false;
            try {
                this.notices = (await api.notices.list()).data;
            } catch (error) {
                console.warn('The notices did not load', error);
                this.failed = true;
            }
        },
    },
};

/** /notice?token=...: one notice by its private link, for someone who can't sign in. */
export const NoticeView = {
    components: { AppScreen, NoticeCard, Notice },
    data: () => ({ token: linkToken()?.token ?? null, notice: null, missing: false }),
    setup: () => ({ DRAFT }),
    template: `
        <AppScreen :title="t('notices.oneTitle')" back="/" measure="max-w-2xl" content-class="gap-4 pt-4 md:gap-6" data-ui="screen" class="bg-ink">
            <Notice v-if="!token" tone="info">{{ t('notices.token.missing') }}</Notice>
            <Notice v-else-if="missing" tone="bad">{{ t('notices.token.notFound') }}</Notice>
            <p v-else-if="!notice" class="text-muted" role="status">{{ t('common.loading') }}</p>
            <NoticeCard v-else :notice="notice" :token="token" @changed="(n) => (notice = n)" />
            <p v-if="DRAFT" class="text-xs text-muted" data-notices-draft>{{ t('notices.draft') }}</p>
        </AppScreen>`,
    async created() {
        if (!this.token) return;
        try {
            this.notice = (await api.notices.show(this.token)).data;
        } catch (error) {
            if (!(error instanceof ApiError)) throw error;
            this.missing = true;
        }
    },
};
