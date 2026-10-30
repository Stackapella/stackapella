// The share sheet (Hillel's 84.png; docs/more-features-plan.md features 1, 4 and 12): sharing means a
// link. A preview card shows what listeners get (the title, edited in place); all the parts or one;
// Polished; what the link does in one line; this song's earlier link to use again; Create link. Then
// the link itself with a big Copy, and Message, Email and More, X and Bluesky; Stop sharing asks once;
// and Share as a video. Keeping a copy for yourself belongs to Export.

import { ApiError, api } from '../api.js';
import { t, timeAgo, tk } from '../i18n.js';
import { formatTime } from '../theme.js';
import { navigate } from '../router.js';
import { account } from '../stores/account.js';
import { prefs } from '../stores/prefs.js';
import { session } from '../stores/session.js';
import { makeVideo, sharedFrom, shareProject } from '../stores/sharing.js';
import { blueskyIntent, videoSection, xIntent } from '../share.js';
import { Switch } from './controls.js';
import { Icon } from './icon.js';
import { Modal, toast } from './ui.js';
import { Notice } from './form.js';
import { confirmAction } from './confirm.js';

const STEPS = { saving: tk('share.step.saving'), mixing: tk('share.step.mixing'), sending: tk('share.step.sending'), processing: tk('share.step.processing') };
const REFUSED = {
    email_unverified: tk('share.refused.unverified'),
    storage_limit: tk('share.refused.storage'),
    project_missing: tk('share.refused.notSaved'),
    no_takes: tk('share.refused.noTakes'),
    mix_mismatch: tk('share.refused.mismatch'),
    paused: tk('share.refused.paused'),
    rate_limited: tk('share.refused.limit'),
    harmony_failed: tk('share.refused.failed'),
    harmony_unavailable: tk('share.refused.unavailable'),
    offline: tk('common.offline'),
    slow: tk('share.refused.slow'),
    validation_failed: tk('share.refused.title'),
};

export const ShareSheet = {
    components: { Modal, Icon, Notice, Switch },
    props: { title: { type: String, default: '' }, parts: { type: Array, required: true } }, // parts: [{ id, name, color }] with a take
    emits: ['close'],
    data() {
        return { stage: 'choose', name: this.title, what: 'all', partId: this.parts[0]?.id ?? null, polish: prefs.polish, step: null, error: null, harmony: null, earlier: [], video: null, videoState: null, look: 'dark', words: true };
    },
    setup: () => ({ account, STEPS, navigate, timeAgo }),
    template: `
        <Modal :title="title ? t('share.titleOf', { title }) : t('share.title')" :title-is-content="!!title" wide :dismissable="stage !== 'working'" @close="$emit('close')">
            <!-- A guest: the way on is a real button, and sign-up keeps the reason (onboarding review 2, 9). -->
            <div v-if="!account.me" class="space-y-4" data-share-guest>
                <p class="text-cream">{{ t('share.signIn') }}</p>
                <a href="/signin?mode=signup&for=share" class="btn-primary" data-share-signup @click.prevent="signIn">{{ t('share.makeAccount') }}</a>
            </div>

            <form v-else-if="stage === 'choose'" class="space-y-5" data-share @submit.prevent="share">
                <!-- What they'll see: the title (edit it in place), the parts, the length -->
                <div class="flex items-center gap-4 rounded-[18px] bg-(--fill) p-4">
                    <span class="flex size-14 shrink-0 flex-col justify-center gap-1 rounded-xl bg-raised px-2.5" aria-hidden="true"><span v-for="(p, i) in shownParts" :key="i" class="block h-1 rounded-full" :style="{ background: p.color, width: (90 - i * 15) + '%' }"></span></span>
                    <div class="min-w-0 flex-1">
                        <label class="sr-only" for="share-title">{{ t('share.name') }}</label>
                        <input id="share-title" v-model="name" maxlength="120" class="w-full rounded-lg border border-transparent bg-transparent px-1 -mx-1 text-lg font-bold text-cream outline-none placeholder:text-muted hover:border-line focus:border-accent" :placeholder="t('project.untitled')" translate="no" />
                        <p class="text-sm text-muted">{{ previewLine }}</p>
                    </div>
                    <span class="self-start text-xs text-muted">{{ t('share.preview') }}</span>
                </div>
                <fieldset v-if="parts.length > 1" class="space-y-3">
                    <legend class="mb-2 text-sm font-semibold text-muted">{{ t('share.what') }}</legend>
                    <div class="seg grid grid-cols-2 rounded-2xl" role="radiogroup" :aria-label="t('share.what')">
                        <button v-for="w in ['all', 'part']" :key="w" type="button" role="radio" :aria-checked="what === w ? 'true' : 'false'" class="seg-option" @click="what = w">{{ w === 'all' ? t('share.allParts', { count: parts.length }) : t('share.onePart') }}</button>
                    </div>
                    <div v-if="what === 'part'" class="flex flex-wrap gap-2" role="radiogroup" :aria-label="t('share.onePart')">
                        <button v-for="p in parts" :key="p.id" type="button" role="radio" :aria-checked="partId === p.id ? 'true' : 'false'" class="filter-chip gap-2" @click="partId = p.id"><span class="size-2.5 rounded-full ring-1 ring-current/30" :style="{ background: p.color }" aria-hidden="true"></span><span translate="no">{{ p.name }}</span></button>
                    </div>
                </fieldset>
                <Switch :on="polish" :label="t('polish.name')" :hint="t('share.polishedHint')" class="rounded-2xl border border-line py-2 pe-2 ps-4" @change="polish = $event" />
                <p class="flex gap-2.5 text-sm text-muted"><Icon name="info" :size="18" class="mt-0.5 shrink-0" />{{ t('share.anyone') }}</p>
                <div v-if="before" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-dashed border-line px-4 py-3 text-sm text-cream" data-shared-before>
                    <span>{{ t('share.sharedBefore', { when: timeAgo(before.createdAt) }) }}</span>
                    <button type="button" class="btn-secondary btn-sm" @click="useBefore">{{ t('share.useThat') }}</button>
                </div>
                <div class="flex justify-end gap-3 border-t border-line pt-4">
                    <button type="button" class="btn-secondary" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn-primary">{{ t('share.createLink') }}</button>
                </div>
            </form>

            <div v-else-if="stage === 'working'" class="space-y-3" role="status">
                <p class="text-cream">{{ t(STEPS[step] ?? STEPS.saving) }}</p>
                <div class="h-1.5 overflow-hidden rounded-full bg-raised"><div class="h-full w-1/3 animate-pulse rounded-full bg-part-4"></div></div>
            </div>

            <div v-else-if="stage === 'error'" class="space-y-4">
                <Notice tone="bad">{{ error }}</Notice>
                <div class="flex justify-end gap-3"><button type="button" class="btn-secondary" @click="stage = 'choose'">{{ t('common.back') }}</button></div>
            </div>

            <div v-else-if="stage === 'done' && harmony.status !== 'ready'" class="space-y-4">
                <p class="text-cream" role="status">{{ t('share.stillProcessing') }}</p>
                <div class="flex justify-end"><button type="button" class="btn-primary" @click="$emit('close')">{{ t('common.done') }}</button></div>
            </div>

            <div v-else-if="stage === 'done'" class="space-y-5" data-share-done>
                <p class="font-semibold text-cream">{{ harmony.visibility === 'private' ? t('share.donePrivate') : t('share.done') }}</p>
                <template v-if="harmony.visibility === 'unlisted'">
                    <!-- The link is the result: big Copy, then the ways to send it -->
                    <div class="flex gap-2"><div class="relative min-w-0 flex-1"><input :value="harmony.pageUrl" readonly class="input pe-[46px]" :aria-label="t('share.link')" @focus="$event.target.select()" /><Icon name="lock" :size="18" class="input-icon end-4" /></div><button type="button" class="btn-primary" @click="copy(harmony.pageUrl)">{{ t('share.copy') }}</button></div>
                    <div class="flex flex-wrap gap-2">
                        <a :href="smsLink" class="btn-secondary btn-sm">{{ t('share.message') }}</a>
                        <a :href="mailLink" class="btn-secondary btn-sm">{{ t('share.email') }}</a>
                        <button v-if="canShare" type="button" class="btn-secondary btn-sm" @click="nativeShare">{{ t('share.more') }}</button>
                        <a :href="xLink" target="_blank" rel="noopener noreferrer" class="btn-secondary btn-sm">{{ t('share.postX') }}</a>
                        <a :href="blueskyLink" target="_blank" rel="noopener noreferrer" class="btn-secondary btn-sm">{{ t('share.postBluesky') }}</a>
                    </div>
                    <p v-if="harmony.titleHeld" class="text-sm text-muted">{{ t('share.titleHeld') }}</p>
                    <div class="space-y-2">
                        <!-- Featuring is for adults (the server refuses it otherwise: 403 adult_required). -->
                        <label v-if="account.me?.isAdult" class="check-row"><input type="checkbox" class="checkbox" :checked="!!harmony.mayFeature" aria-describedby="share-guides" @change="feature($event)" /><span>{{ t('share.feature') }}<span class="block text-xs text-muted">{{ t('share.featureHint') }}</span></span></label>
                        <p id="share-guides" class="flex gap-2 rounded-2xl bg-raised p-3 text-sm text-cream"><Icon name="info" :size="18" class="mt-0.5 shrink-0 text-accent" /><span>{{ t('share.guides') }} <a href="/legal/terms" target="_blank" rel="noopener" class="link">{{ t('share.guidesTerms') }}</a></span></p>
                    </div>
                </template>
                <section class="space-y-3 border-t border-line pt-4" aria-labelledby="video-heading">
                    <h3 id="video-heading" class="font-semibold text-cream">{{ t('share.video.title') }}</h3>
                    <p class="text-sm text-muted">{{ t('share.video.hint') }}</p>
                    <template v-if="!videoState">
                        <div class="seg" role="radiogroup" :aria-label="t('share.video.look')">
                            <label v-for="l in ['dark', 'light']" :key="l" class="seg-option"><input v-model="look" type="radio" name="share-look" :value="l" class="sr-only" />{{ l === 'dark' ? t('share.video.dark') : t('share.video.light') }}</label>
                        </div>
                        <label class="check-row"><input v-model="words" type="checkbox" class="checkbox" /> {{ t('share.video.words') }}</label>
                        <p v-if="harmony.visibility === 'private'" class="text-sm text-muted">{{ t('share.video.private') }}</p>
                        <button type="button" class="btn-secondary btn-sm" @click="makeTheVideo">{{ t('share.video.make') }}</button>
                    </template>
                    <div v-else-if="videoState === 'working'" class="space-y-2" role="status">
                        <p class="text-sm text-cream">{{ t('share.video.working') }}</p>
                        <div class="h-1.5 overflow-hidden rounded-full bg-raised"><div class="h-full rounded-full bg-part-4 transition-[width]" :style="{ width: Math.round((video?.progress ?? 0) * 100) + '%' }"></div></div>
                    </div>
                    <div v-else-if="videoState === 'ready'" class="flex flex-wrap gap-3">
                        <button v-if="canShare" type="button" class="btn-primary btn-sm" @click="shareVideo">{{ t('share.video.share') }}</button>
                        <a :href="video.url" class="btn-secondary btn-sm" download>{{ t('share.video.download') }}</a>
                    </div>
                    <Notice v-else-if="videoState === 'failed'" tone="bad">{{ t('share.video.failed') }}</Notice>
                </section>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                    <button v-if="harmony.visibility === 'unlisted'" type="button" class="btn-ghost btn-sm text-bad" @click="stop">{{ t('share.stop') }}</button>
                    <button type="button" class="btn-primary ms-auto" @click="$emit('close')">{{ t('common.done') }}</button>
                </div>
            </div>
        </Modal>`,
    computed: {
        canShare: () => typeof navigator.share === 'function',
        shownParts() {
            return this.what === 'part' ? this.parts.filter((p) => p.id === this.partId) : this.parts;
        },
        /** "Four-part harmony · 0:37 · polished". */
        previewLine() {
            const kind = this.what === 'part' ? this.parts.find((p) => p.id === this.partId)?.name ?? '' : t('share.kind', { count: this.parts.length });
            return [kind, formatTime(session.contentLength || 0), this.polish ? t('share.polishedTag') : null].filter(Boolean).join(' · ');
        },
        /** This song's last link, to use again (the rest live where every link is managed). */
        before() {
            return this.earlier.filter((h) => h.visibility === 'unlisted' && h.pageUrl && h.status === 'ready').sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))[0] ?? null;
        },
        smsLink() {
            return `sms:?&body=${encodeURIComponent(this.postWords)}`;
        },
        mailLink() {
            return `mailto:?subject=${encodeURIComponent(this.harmony?.title || t('project.untitled'))}&body=${encodeURIComponent(this.postWords)}`;
        },
        postWords() {
            return `${t('share.postText', { title: this.harmony?.title || t('project.untitled') })} ${this.harmony?.pageUrl ?? ''}`;
        },
        xLink() {
            return xIntent(this.postWords);
        },
        blueskyLink() {
            return blueskyIntent(this.postWords);
        },
    },
    async created() {
        if (!account.me) return;
        this.earlier = await sharedFrom(session.project.id).catch(() => []);
    },
    methods: {
        async share() {
            this.stage = 'working';
            this.error = null;
            try {
                prefs.polish = this.polish;
                this.harmony = await shareProject({ title: this.name, visibility: 'unlisted', partId: this.what === 'part' ? this.partId : null, room: this.polish ? prefs.room ?? 'small' : null }, (step) => (this.step = step));
                this.stage = 'done';
            } catch (error) {
                const code = error?.code;
                if (code === 'offline') {
                    console.warn('Sharing waits for a connection');
                    this.error = t('common.offline');
                    this.stage = 'error';
                    return;
                }
                console.error('Sharing failed', error);
                // A 422 is about the name only when the name is what it names.
                const nameRefused = code === 'validation_failed' && !!error?.errors?.title;
                this.error = code === 'validation_failed' && !nameRefused ? t('share.refused.generic') : REFUSED[code] ? t(REFUSED[code]) : t('share.refused.generic');
                this.stage = 'error';
            }
        },
        async copy(url) {
            try {
                await navigator.clipboard.writeText(url);
                toast(t('share.copied'));
            } catch {
                toast(t('share.copyFailed'));
            }
        },
        nativeShare() {
            navigator.share({ title: this.harmony.title || t('project.untitled'), text: t('share.postText', { title: this.harmony.title || t('project.untitled') }), url: this.harmony.pageUrl }).catch((error) => console.info('Not shared', error?.name));
        },
        async makeTheVideo() {
            this.videoState = 'working';
            try {
                const section = videoSection({ mixSeconds: this.harmony.durationSeconds, sections: session.project.template?.sections ?? [], beatSeconds: session.project.template ? 60 / session.project.bpm : 0 });
                this.video = await makeVideo(this.harmony, { ...section, look: this.look, words: this.words }, { makeUnlisted: true, onProgress: (v) => (this.video = v) });
                if (this.harmony.visibility === 'private') this.harmony = { ...this.harmony, visibility: 'unlisted' };
                this.videoState = 'ready';
            } catch (error) {
                console.error('The video was not made', error);
                this.videoState = error instanceof ApiError && error.code === 'rate_limited' ? null : 'failed';
                if (error instanceof ApiError && error.code === 'rate_limited') toast(t('share.refused.limit'));
            }
        },
        async shareVideo() {
            try {
                const blob = await (await fetch(this.video.url)).blob();
                const file = new File([blob], `${this.harmony.title || 'harmony'}.mp4`, { type: 'video/mp4' });
                if (navigator.canShare?.({ files: [file] })) await navigator.share({ files: [file], title: this.harmony.title });
                else window.open(this.video.url, '_blank', 'noopener');
            } catch {
                window.open(this.video.url, '_blank', 'noopener');
            }
        },
        /** The singer's yes (or no) to Stackapella featuring it on our own X and Bluesky. */
        async feature(event) {
            const yes = event.target.checked;
            try {
                this.harmony = (await api.harmonies.update(this.harmony.id, { mayFeature: yes })).data;
            } catch (error) {
                event.target.checked = !yes; // as it still is on the account
                toast(error?.code === 'singers_must_agree' ? t('share.refused.singersMustAgree') : t('share.refused.generic'));
            }
        },
        signIn() {
            navigate(`/signin?mode=signup&for=share&next=${encodeURIComponent(location.pathname)}`);
        },
        useBefore() {
            this.harmony = this.before;
            this.stage = 'done';
        },
        /** Stop sharing: asked once, then the link stops working for everyone. */
        async stop() {
            const title = this.harmony.title || t('project.untitled');
            const sure = await confirmAction({ title: t('share.stopTitle', { title }), body: t('share.deleteBody'), action: t('share.stop'), cancel: t('confirm.keep') });
            if (!sure) return;
            try {
                await api.harmonies.delete(this.harmony.id);
                this.earlier = this.earlier.filter((h) => h.id !== this.harmony.id);
                toast(t('share.stopped'));
                this.$emit('close');
            } catch {
                toast(t('share.refused.generic'));
            }
        },
    },
};
