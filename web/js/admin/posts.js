// Our posts on X and Bluesky (M10; Hillel: "where are the posts to review?"): the queue waiting for
// approval first, each with its text as it will go out, the network, the song or harmony it's about
// (with its link) and its status. Approve sends it two minutes from now (Cancel undoes it until
// then); Cancel turns a post down, after a styled confirm; Retry sends a failed one again. Filters
// for what was approved, sent, failed or cancelled. The nav's count is the posts waiting.

import { reactive } from 'vue';
import { api } from '../api.js';
import { t, tk } from '../i18n.js';
import { EmptyState, StatusChip } from '../components/form.js';
import { toast } from '../components/ui.js';
import { ChangeSheet, LoadFailed, loadInto, refused, when } from './common.js';

const FILTERS = {
    awaiting: tk('admin.posts.filter.awaiting'),
    queued: tk('admin.posts.filter.queued'),
    posted: tk('admin.posts.filter.posted'),
    failed: tk('admin.posts.filter.failed'),
    cancelled: tk('admin.posts.filter.cancelled'),
    all: tk('admin.posts.filter.all'),
};
const STATUS = {
    awaiting: { label: tk('admin.posts.status.awaiting'), tone: 'warn' },
    queued: { label: tk('admin.posts.status.queued'), tone: 'info' },
    posting: { label: tk('admin.posts.status.posting'), tone: 'info' },
    posted: { label: tk('admin.posts.status.posted'), tone: 'good' },
    failed: { label: tk('admin.posts.status.failed'), tone: 'bad' },
    cancelled: { label: tk('admin.posts.status.cancelled'), tone: 'muted' },
};
const DONE = { approve: tk('admin.posts.approved'), retry: tk('admin.posts.retried'), cancel: tk('admin.posts.cancelled') };
const NETWORKS = { x: tk('admin.posts.network.x'), bluesky: tk('admin.posts.network.bluesky') };
const OCCASIONS = { new_song: tk('admin.posts.occasion.newSong'), month: tk('admin.posts.occasion.month'), featured: tk('admin.posts.occasion.featured') };

/** The posts waiting for approval, for the nav's count: `count`, and `more` when there's a further page. */
export const socialWaiting = reactive({
    count: 0,
    more: false,
    async refresh() {
        try {
            const page = await api.admin.social.list({ status: 'awaiting' });
            this.count = page.data.length;
            this.more = !!page.meta?.nextCursor;
        } catch (error) {
            console.warn('The posts waiting could not be counted', error);
        }
    },
});

/** The link a post carries (the song's or harmony's page), else none. */
const linkIn = (text) => /https?:\/\/\S+/.exec(text ?? '')?.[0] ?? null;

export const PostsSection = {
    components: { ChangeSheet, EmptyState, LoadFailed, StatusChip },
    props: { me: { type: Object, required: true } },
    data: () => ({ items: null, failed: false, cursor: null, loadingMore: false, status: 'awaiting', cancelling: null, busy: null }),
    setup: () => ({ FILTERS, STATUS, NETWORKS, OCCASIONS, linkIn, when }),
    template: `
        <div class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.posts') }}</h1>
                <div class="seg flex flex-wrap" role="radiogroup" :aria-label="t('admin.posts.show')">
                    <button v-for="(word, s) in FILTERS" :key="s" type="button" role="radio" :aria-checked="status === s" class="seg-option" @click="status = s; load()">{{ t(word) }}</button>
                </div>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load()" />
            <p v-else-if="!items" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!items.length" icon="check" :title="status === 'awaiting' ? t('admin.posts.noneWaiting') : t('admin.posts.none')" />
            <ul v-else class="space-y-4">
                <li v-for="post in items" :key="post.id" class="card space-y-4 p-5" :data-post="post.id">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-bold text-cream">{{ t(NETWORKS[post.network] ?? post.network) }} · {{ OCCASIONS[post.occasion] ? t(OCCASIONS[post.occasion]) : post.occasion }}</p>
                            <p class="text-sm text-muted">
                                <template v-if="post.about.kind === 'gone'">{{ t('admin.posts.gone') }}</template>
                                <template v-else>{{ post.about.kind === 'song' ? t('admin.posts.aboutSong') : t('admin.posts.aboutHarmony') }} <a v-if="linkIn(post.text)" :href="linkIn(post.text)" target="_blank" rel="noopener" class="link" translate="no">{{ post.about.title }}</a><span v-else translate="no">{{ post.about.title }}</span></template>
                                · {{ when(post.createdAt) }}
                            </p>
                        </div>
                        <StatusChip :tone="STATUS[post.status]?.tone ?? 'muted'" :label="STATUS[post.status] ? t(STATUS[post.status].label) : post.status" />
                    </div>
                    <!-- The post as it will read, its own words: never HTML. -->
                    <p class="whitespace-pre-line rounded-2xl bg-raised p-4 text-cream [overflow-wrap:anywhere]" translate="no">{{ post.text }}</p>
                    <p v-if="post.status === 'queued' && post.scheduledFor" class="text-sm text-muted">{{ t('admin.posts.goesAt', { when: when(post.scheduledFor) }) }}</p>
                    <p v-if="post.status === 'posted'" class="text-sm text-muted">{{ t('admin.posts.postedAt', { when: when(post.postedAt) }) }} <a v-if="post.url" :href="post.url" target="_blank" rel="noopener" class="link">{{ t('admin.posts.view') }}</a></p>
                    <p v-if="post.status === 'failed' && post.error" class="text-sm text-bad" translate="no">{{ post.error }}</p>
                    <div v-if="['awaiting', 'queued', 'failed'].includes(post.status)" class="flex flex-wrap gap-3">
                        <button v-if="post.status === 'awaiting'" type="button" class="btn-primary btn-sm" :disabled="busy === post.id" data-approve @click="act(post, 'approve')">{{ t('admin.posts.approve') }}</button>
                        <button v-if="post.status === 'failed'" type="button" class="btn-primary btn-sm" :disabled="busy === post.id" data-retry @click="act(post, 'retry')">{{ t('admin.posts.retry') }}</button>
                        <button v-if="post.status !== 'failed'" type="button" class="btn-ghost btn-sm text-bad" :disabled="busy === post.id" data-cancel @click="cancelling = post">{{ post.status === 'queued' ? t('admin.posts.undo') : t('admin.posts.cancel') }}</button>
                    </div>
                </li>
            </ul>
            <button v-if="cursor && !failed" type="button" class="btn-secondary" :disabled="loadingMore" @click="load(true)">{{ loadingMore ? t('admin.loading') : t('admin.loadMore') }}</button>
            <ChangeSheet v-if="cancelling" :title="t('admin.posts.cancelTitle', { network: t(NETWORKS[cancelling.network] ?? cancelling.network), title: cancelling.about.title })" :me="me" :busy="busy === cancelling.id" :reasoned="false" :needs-sudo="false" @close="cancelling = null" @confirm="act(cancelling, 'cancel')">
                <p class="text-sm text-muted">{{ cancelling.status === 'queued' ? t('admin.posts.undoHint') : t('admin.posts.cancelHint') }}</p>
            </ChangeSheet>
        </div>`,
    created() {
        this.load();
    },
    methods: {
        load(more = false) {
            return loadInto(this, 'items', () => api.admin.social.list({ status: this.status, cursor: more ? this.cursor : undefined }), { more });
        },
        /** Approve, Retry or Cancel: the post as it is now, in place; one that left this filter goes. */
        async act(post, action) {
            this.busy = post.id;
            try {
                const { data } = await api.admin.social[action](post.id);
                Object.assign(post, data);
                if (this.status !== 'all' && post.status !== this.status) this.items = this.items.filter((p) => p !== post);
                toast(t(DONE[action]));
                this.cancelling = null;
                socialWaiting.refresh();
            } catch (error) {
                refused(error);
            } finally {
                this.busy = null;
            }
        },
    },
};
