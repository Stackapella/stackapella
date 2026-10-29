// Ideas (docs/ideas-app-plan.md, slice 1; contract/api.md, "Ideas"): tunes shared for real voices.
// - /ideas: the shelf. Waiting for voices or Complete, a part's chips to find tunes that need one,
//   and Your tunes (signed in), 24 a page with More.
// - /ideas/<id>: one tune. Its writer and note; its parts, melody first, each with its range, how
//   many voices and Sing this part; Hear the tune (its guides); the voices so far, each heard on its
//   own; Report; and for its writer, held or hidden said plainly, Delete, and Remove on a voice.

import { markRaw } from 'vue';
import { PageHeader } from '../components/page.js';
import { ApiError, call } from '../api.js';
import { midiName } from '../dsp.js';
import { t, timeAgo, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { account } from '../stores/account.js';
import { deleteIdea, deleteVoice, ideas, ideasOpen, listIdeas, myIdeas, showIdea } from '../stores/ideas.js';
import { canCreateProject, LimitReached, startFromTemplate } from '../stores/projects.js';
import { validateTemplate } from '../templates.js';
import { playTemplate } from '../creator-audio.js';
import { paint } from '../colors.js';
import { PART_COLORS } from '../theme.js';
import { EmptyState, Notice } from '../components/form.js';
import { Icon } from '../components/icon.js';
import { Modal, toast } from '../components/ui.js';
import { confirmAction } from '../components/confirm.js';
import { PlusMoment } from '../components/plus-moment.js';

const TABS = { open: tk('ideas.tabs.open'), complete: tk('ideas.tabs.complete'), mine: tk('ideas.tabs.mine') };
const NEEDS = { soprano: tk('parts.soprano'), alto: tk('parts.alto'), tenor: tk('parts.tenor'), bass: tk('parts.bass') };
const partColor = (index) => paint(PART_COLORS[(index * 3) % PART_COLORS.length]);

/** The page's frame: the shared header, and the page. */
const Frame = {
    components: { PageHeader },
    props: { title: { type: String, required: true }, back: { type: String, default: '/' } },
    template: `
        <div class="mx-auto flex min-h-dvh max-w-3xl flex-col px-4 pb-16 pt-[max(0.5rem,env(safe-area-inset-top))] md:px-8">
            <PageHeader :title="title" :back="back"><template #aside><slot name="aside" /></template></PageHeader>
            <div class="mt-4 space-y-6"><slot /></div>
        </div>`,
};

/** A tune on the shelf: its title, writer, note and parts (a filled dot has a voice). */
const IdeaCard = {
    props: { idea: { type: Object, required: true } },
    setup: () => ({ partColor }),
    template: `
        <a :href="'/ideas/' + idea.id" class="card-interactive block space-y-3 p-5" :data-idea="idea.id" @click.prevent="navigate('/ideas/' + idea.id)">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                <h2 class="text-lg font-bold text-cream" translate="no">{{ idea.title }}</h2>
                <p v-if="idea.mine && idea.status !== 'listed'" class="text-xs font-semibold text-warn">{{ idea.status === 'held' ? t('ideas.status.held') : t('ideas.status.hidden') }}</p>
                <p v-else-if="idea.complete" class="text-xs font-semibold text-good">{{ t('ideas.complete') }}</p>
            </div>
            <p class="text-sm text-muted">{{ t('ideas.by', { name: idea.writer.name }) }} · {{ timeAgo(idea.createdAt) }}</p>
            <p v-if="idea.note" class="line-clamp-2 text-sm text-cream">{{ idea.note }}</p>
            <ul class="flex flex-wrap gap-x-4 gap-y-1 text-sm" :aria-label="t('ideas.partsLabel')">
                <li v-for="(part, i) in idea.parts" :key="part.key" class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full border-2" :style="{ borderColor: partColor(i), background: part.voices ? partColor(i) : 'transparent' }" aria-hidden="true"></span>
                    <span class="text-cream">{{ part.name }}</span>
                    <span class="text-muted">{{ part.voices ? t('ideas.voices', { count: part.voices }) : t('ideas.needsVoice') }}</span>
                </li>
            </ul>
        </a>`,
    methods: { navigate, timeAgo },
};

export const IdeasView = {
    components: { Frame, IdeaCard, EmptyState, Icon },
    data: () => ({ tab: 'open', needs: null, list: null, cursor: null, failed: null, busy: false }),
    setup: () => ({ TABS, NEEDS, ideas, account }),
    template: `
        <Frame :title="t('ideas.title')">
            <p class="text-muted">{{ t('ideas.intro') }}</p>
            <p v-if="ideas.open === null" class="text-muted" role="status">{{ t('ideas.loading') }}</p>
            <EmptyState v-else-if="ideas.open === false" icon="info" :title="t('ideas.closed')" :body="t('ideas.closedHint')" />
            <template v-else>
                <div class="seg flex sm:w-fit" role="radiogroup" :aria-label="t('ideas.title')">
                    <button v-for="(label, key) in TABS" v-show="key !== 'mine' || account.me" :key="key" type="button" role="radio" :aria-checked="tab === key ? 'true' : 'false'" class="seg-option flex-1 sm:flex-none" @click="choose(key)">{{ t(label) }}</button>
                </div>
                <div v-if="tab === 'open'" class="flex flex-wrap items-center gap-2" role="group" :aria-label="t('ideas.needsLabel')">
                    <span class="text-sm text-muted">{{ t('ideas.needsLabel') }}</span>
                    <button v-for="(label, key) in NEEDS" :key="key" type="button" class="filter-chip" :aria-pressed="needs === key ? 'true' : 'false'" @click="need(key)">{{ t(label) }}</button>
                </div>
                <p v-if="failed" class="text-sm text-bad" role="alert">{{ failed }}</p>
                <p v-else-if="list === null" class="text-muted" role="status">{{ t('ideas.loading') }}</p>
                <p v-else-if="!list.length" class="text-muted" data-ideas-empty>{{ tab === 'mine' ? t('ideas.emptyMine') : needs ? t('ideas.emptyNeeds', { part: t(NEEDS[needs]) }) : t('ideas.empty') }}</p>
                <div v-else class="space-y-3" data-ideas-list>
                    <IdeaCard v-for="idea in list" :key="idea.id" :idea="idea" />
                </div>
                <button v-if="cursor" type="button" class="btn-secondary w-full" :disabled="busy" @click="more">{{ t('ideas.more') }}</button>
            </template>
        </Frame>`,
    async created() {
        if (await ideasOpen()) this.load();
    },
    methods: {
        choose(tab) {
            this.tab = tab;
            this.needs = null;
            this.load();
        },
        need(key) {
            this.needs = this.needs === key ? null : key;
            this.load();
        },
        async fetch(cursor = null) {
            return this.tab === 'mine' ? myIdeas({ cursor }) : listIdeas({ tab: this.tab, needs: this.needs, cursor });
        },
        async load() {
            this.list = null;
            this.failed = null;
            const asked = `${this.tab}:${this.needs}`;
            try {
                const answer = await this.fetch();
                if (asked !== `${this.tab}:${this.needs}`) return; // another tab chosen meanwhile
                this.list = answer.data;
                this.cursor = answer.meta?.nextCursor ?? null;
            } catch (error) {
                console.warn('The ideas did not load', error);
                this.failed = error?.code === 'offline' ? t('common.offline') : t('ideas.failed');
            }
        },
        async more() {
            this.busy = true;
            try {
                const answer = await this.fetch(this.cursor);
                this.list.push(...answer.data);
                this.cursor = answer.meta?.nextCursor ?? null;
            } catch (error) {
                console.warn('More ideas did not load', error);
                toast(t('ideas.failed'), { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },
    },
};

// The reasons a tune can be reported (reports.create's reason), the likely one first.
const REASONS = {
    copyright: tk('ideas.report.reasons.copyright'),
    offensive: tk('ideas.report.reasons.offensive'),
    spam: tk('ideas.report.reasons.spam'),
    other: tk('ideas.report.reasons.other'),
};

/** Report a tune: a reason, an optional note, then thanks. */
const ReportIdea = {
    components: { Modal },
    props: { idea: { type: Object, required: true } },
    emits: ['close'],
    data: () => ({ reason: 'copyright', note: '', website: '', busy: false }),
    setup: () => ({ REASONS }),
    template: `
        <Modal :title="t('ideas.report.title')" @close="$emit('close')">
            <form class="space-y-4" data-idea-report @submit.prevent="send">
                <fieldset class="space-y-3">
                    <legend class="label">{{ t('ideas.report.why') }}</legend>
                    <label v-for="(label, r) in REASONS" :key="r" class="check-row"><input v-model="reason" type="radio" :value="r" class="radio" /> {{ t(label) }}</label>
                </fieldset>
                <label class="block space-y-1.5"><span class="label">{{ t('ideas.report.note') }}</span><textarea v-model="note" class="input min-h-20 w-full" maxlength="500" rows="3"></textarea></label>
                <input v-model="website" type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true" />
                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" class="btn-secondary" :disabled="busy" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn-primary" :disabled="busy">{{ t('ideas.report.send') }}</button>
                </div>
            </form>
        </Modal>`,
    methods: {
        async send() {
            this.busy = true;
            try {
                await call('reports.create', { body: { ideaId: this.idea.id, reason: this.reason, note: this.note.trim(), website: this.website } });
                toast(t('ideas.report.thanks'));
                this.$emit('close');
            } catch (error) {
                console.warn('The report was not sent', error);
                toast(error instanceof ApiError && error.status === 404 ? t('ideas.gone') : t('ideas.report.failed'), { tone: 'error' });
                this.busy = false;
            }
        },
    },
};

export const IdeaView = {
    components: { Frame, EmptyState, Icon, Notice, ReportIdea, PlusMoment },
    props: { id: { type: String, required: true } },
    data: () => ({ idea: null, missing: false, failed: null, player: null, hearing: false, playingVoice: null, reporting: false, starting: null, limitOpen: false }),
    setup: () => ({ account, partColor, midiName }),
    template: `
        <Frame :title="idea ? idea.title : t('ideas.title')" back="/ideas">
            <p v-if="failed" class="text-bad" role="alert">{{ failed }}</p>
            <EmptyState v-else-if="missing" icon="info" :title="t('ideas.gone')" :body="t('ideas.goneHint')">
                <a href="/ideas" class="btn-primary" @click.prevent="navigate('/ideas')">{{ t('ideas.seeAll') }}</a>
            </EmptyState>
            <p v-else-if="!idea" class="text-muted" role="status">{{ t('ideas.loading') }}</p>
            <template v-else>
                <section class="space-y-2" data-idea-about>
                    <p class="text-muted">{{ t('ideas.by', { name: idea.writer.name }) }} · {{ timeAgo(idea.createdAt) }}</p>
                    <p v-if="idea.note" class="text-lg text-cream">{{ idea.note }}</p>
                    <Notice v-if="idea.mine && idea.status === 'held'" tone="info">{{ t('ideas.heldHint') }}</Notice>
                    <Notice v-else-if="idea.mine && idea.status === 'hidden'" tone="bad">{{ t('ideas.hiddenHint') }}</Notice>
                    <p v-if="idea.complete" class="font-semibold text-good" data-idea-complete>{{ t('ideas.completeLine') }}</p>
                </section>

                <button type="button" class="btn-secondary" :disabled="!song" data-idea-hear @click="hear"><Icon :name="hearing ? 'stop' : 'play'" :size="16" /> {{ hearing ? t('ideas.stop') : t('ideas.hear') }}</button>

                <section class="space-y-3" aria-labelledby="idea-parts">
                    <h2 id="idea-parts" class="text-lg font-bold text-cream">{{ t('ideas.parts') }}</h2>
                    <ul class="card divide-y divide-line overflow-hidden">
                        <li v-for="(part, i) in idea.parts" :key="part.key" class="flex flex-wrap items-center gap-3 p-4" :data-idea-part="part.key">
                            <span class="size-3 shrink-0 rounded-full" :style="{ background: partColor(i) }" aria-hidden="true"></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-cream">{{ part.name }}</p>
                                <p class="text-sm text-muted">{{ t('ideas.range', { low: midiName(part.range.low), high: midiName(part.range.high) }) }} · {{ part.voices ? t('ideas.voices', { count: part.voices }) : t('ideas.needsVoice') }}</p>
                            </div>
                            <button type="button" class="btn-primary btn-sm" :disabled="!song || starting !== null" @click="sing(part)">{{ t('ideas.sing') }}</button>
                        </li>
                    </ul>
                </section>

                <section class="space-y-3" aria-labelledby="idea-voices">
                    <h2 id="idea-voices" class="text-lg font-bold text-cream">{{ t('ideas.voicesTitle') }}</h2>
                    <p v-if="!voices.length" class="text-sm text-muted">{{ t('ideas.noVoices') }}</p>
                    <ul v-else class="card divide-y divide-line overflow-hidden">
                        <li v-for="voice in voices" :key="voice.id" class="flex flex-wrap items-center gap-3 p-4" :data-idea-voice="voice.id">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-cream">{{ t('ideas.voiceLine', { name: voice.singer.name, part: partName(voice.partKey) }) }}</p>
                                <p class="text-sm text-muted">{{ voice.status === 'ready' ? timeAgo(voice.createdAt) : voice.status === 'failed' ? t('ideas.voiceFailed') : t('ideas.voiceProcessing') }}</p>
                            </div>
                            <button v-if="voice.status === 'ready' && voice.url" type="button" class="btn-secondary btn-sm" @click="playVoice(voice)"><Icon :name="playingVoice === voice.id ? 'stop' : 'play'" :size="16" /> {{ playingVoice === voice.id ? t('ideas.stop') : t('ideas.play') }}</button>
                            <button v-if="voice.mine || idea.mine" type="button" class="tap-44 btn-ghost btn-sm text-bad" @click="removeVoice(voice)">{{ t('ideas.removeVoice') }}</button>
                        </li>
                    </ul>
                </section>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                    <button v-if="idea.mine" type="button" class="btn-ghost btn-sm text-bad" data-idea-delete @click="remove">{{ t('ideas.delete') }}</button>
                    <button v-else type="button" class="tap-44 btn-ghost btn-sm" data-idea-report-open @click="reporting = true">{{ t('ideas.report.open') }}</button>
                </div>
            </template>
            <ReportIdea v-if="reporting" :idea="idea" @close="reporting = false" />
            <PlusMoment v-if="limitOpen" :title="t('plus.moment.projectTitle')" :text="t('plus.moment.projectLimit')" full from="project_limit" @close="limitOpen = false" />
        </Frame>`,
    computed: {
        song() {
            const template = this.idea?.song?.template;
            if (!template) return null;
            try {
                return validateTemplate(template);
            } catch (error) {
                console.warn('The tune could not be read', error);
                return null;
            }
        },
        voices() {
            return this.idea?.voiceList ?? [];
        },
    },
    async created() {
        await this.load();
    },
    unmounted() {
        this.stopAll();
    },
    methods: {
        navigate,
        timeAgo,
        partName(key) {
            return this.idea.parts.find((p) => p.key === key)?.name ?? key;
        },
        async load() {
            try {
                this.idea = await showIdea(this.id);
            } catch (error) {
                if (error instanceof ApiError && error.status === 404) this.missing = true;
                else {
                    console.warn('The idea did not load', error);
                    this.failed = error?.code === 'offline' ? t('common.offline') : t('ideas.failed');
                }
            }
        },
        stopAll() {
            this.player?.stop();
            this.player = null;
            this.hearing = false;
            this.audio?.pause();
            this.audio = null;
            this.playingVoice = null;
        },
        /** The tune's parts as its guides, together; again stops it. */
        async hear() {
            const was = this.hearing;
            this.stopAll();
            if (was || !this.song) return;
            this.hearing = true;
            const player = await playTemplate(this.song, { singers: this.idea.voicing });
            this.player = markRaw(player);
            await player.done;
            if (this.player === player) this.stopAll();
        },
        /** One voice on its own (a temporary link); again stops it. */
        playVoice(voice) {
            const was = this.playingVoice === voice.id;
            this.stopAll();
            if (was) return;
            const audio = new Audio(voice.url);
            this.audio = markRaw(audio);
            this.playingVoice = voice.id;
            audio.addEventListener('ended', () => this.audio === audio && this.stopAll());
            audio.play().catch((error) => {
                console.warn('The voice did not play', error);
                toast(t('ideas.playFailed'), { tone: 'error' });
                this.stopAll();
            });
        },
        /** Sing this part: the tune as a project of your own, on that part, carrying the idea. */
        async sing(part) {
            if (!canCreateProject()) {
                this.limitOpen = true;
                return;
            }
            this.starting = part.key;
            this.stopAll();
            try {
                const project = await startFromTemplate(this.song, { singers: this.idea.voicing, focusKey: part.key, idea: { id: this.idea.id, writer: this.idea.writer.name, partKey: part.key } });
                navigate(`/p/${project.id}/sing`);
            } catch (error) {
                if (error instanceof LimitReached) this.limitOpen = true;
                else {
                    console.error('Singing the idea failed', error);
                    toast(t('projects.couldNotStart'), { tone: 'error' });
                }
            } finally {
                this.starting = null;
            }
        },
        async removeVoice(voice) {
            const sure = await confirmAction({ title: t('ideas.removeVoiceTitle', { name: voice.singer.name, part: this.partName(voice.partKey) }), body: t('ideas.removeVoiceBody'), action: t('ideas.removeVoice') });
            if (!sure) return;
            try {
                await deleteVoice(voice.id);
                await this.load();
            } catch (error) {
                console.warn('The voice was not removed', error);
                toast(t('ideas.failed'), { tone: 'error' });
            }
        },
        async remove() {
            const sure = await confirmAction({ title: t('ideas.deleteTitle', { title: this.idea.title }), body: t('ideas.deleteBody'), action: t('ideas.delete') });
            if (!sure) return;
            try {
                await deleteIdea(this.idea.id);
                toast(t('ideas.deleted'));
                navigate('/ideas');
            } catch (error) {
                console.warn('The idea was not deleted', error);
                toast(t('ideas.failed'), { tone: 'error' });
            }
        },
    },
};

export { ideas, ideasOpen };
