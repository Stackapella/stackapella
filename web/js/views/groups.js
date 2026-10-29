// Choir groups (contract/api.md, "Choir groups"; docs/pricing-plan.md, "A choir plan"): a director
// with Plus makes a group, invites members by the group's link (no email from us), shares songs
// (the library's, or their own templates), says who sings which part, and sees who has learned it
// (started, or recorded). Members join free, learn the songs shared with them (Plus songs too, while
// they're in it) and leave when they like. For now, 18 and over: making or joining asks once.
// - /groups: the groups you direct or sing in, and Start a group.
// - /groups/<id>: a group (the director's view, or a member's).
// - /choir/<token>: the group's link: what it is, and Join.

import { PageHeader } from '../components/page.js';
import { api } from '../api.js';
import { formatDate, locale, t, tk } from '../i18n.js';
import { goBack, navigate } from '../router.js';
import { account } from '../stores/account.js';
import { canCreateProject, FREE_PROJECT_LIMIT, LimitReached, startFromTemplate } from '../stores/projects.js';
import { loadMine, mine } from '../stores/templates.js';
import { listTemplates, loadTemplate, templatePartName } from '../templates.js';
import { EmptyState, Field, Notice } from '../components/form.js';
import { Icon, Logo } from '../components/icon.js';
import { paint } from '../colors.js';
import { PART_COLORS } from '../theme.js';
import { Modal, toast } from '../components/ui.js';
import { confirmAction } from '../components/confirm.js';
import { PlusMoment } from '../components/plus-moment.js';
import { InviteAccept, InviteCard } from '../components/invite-card.js';
import { askBirth } from '../components/birth.js';

const STATUS = {
    none: { tone: 'text-muted', label: tk('groups.status.none') },
    started: { tone: 'text-warn', label: tk('groups.status.started') },
    recorded: { tone: 'text-good', label: tk('groups.status.recorded') },
};
const REFUSED = {
    plus_required: tk('groups.refused.plus'),
    group_limit: tk('groups.refused.groupLimit'),
    group_full: tk('groups.refused.full'),
    director_only: tk('groups.refused.directorOnly'),
    not_found: tk('groups.refused.notFound'),
    offline: tk('common.offline'),
};
const refusal = (error) => t(REFUSED[error?.code] ?? tk('groups.refused.other'));

/** "Soprano or Bass", as the person's language lists alternatives. */
const formatOr = (items) => new Intl.ListFormat(locale.lang, { type: 'disjunction' }).format(items);

/** "today", "yesterday", "3 days ago", then the date. */
function activeWhen(time, now = Date.now()) {
    const days = Math.floor((new Date(now).setHours(0, 0, 0, 0) - new Date(time).setHours(0, 0, 0, 0)) / 86400000);
    if (days < 7) return new Intl.RelativeTimeFormat(locale.lang, { numeric: 'auto' }).format(-days, 'day');
    return formatDate(time, { day: 'numeric', month: 'short' });
}

/** The page's frame: the shared page header (back, title, the page's actions), and the page. */
const Frame = {
    components: { PageHeader },
    props: { title: { type: String, required: true }, back: { type: String, default: '/' }, titleIsContent: Boolean },
    template: `
        <div class="mx-auto flex min-h-dvh max-w-3xl flex-col px-4 pb-16 pt-[max(0.5rem,env(safe-area-inset-top))] md:px-8">
            <PageHeader :title="title" :back="back" :title-is-content="titleIsContent"><template #aside><slot name="aside" /></template></PageHeader>
            <slot />
        </div>`,
};

/** "I'm 18 or over": asked once, when making or joining a group (kept on the account). */
const AdultCheck = {
    props: { modelValue: Boolean },
    emits: ['update:modelValue'],
    template: `
        <label class="check-row rounded-2xl bg-raised p-3"><input type="checkbox" class="checkbox" :checked="modelValue" @change="$emit('update:modelValue', $event.target.checked)" />
            <span>{{ t('groups.adult') }}<span class="block text-xs text-muted">{{ t('groups.adultHint') }}</span></span>
        </label>`,
};

export const GroupsView = {
    components: { Frame, EmptyState, Field, Icon, Modal, Notice, AdultCheck },
    data: () => ({ groups: null, failed: null, making: false, name: '', adult: false, busy: false, error: null }),
    setup: () => ({ account, navigate }),
    template: `
        <Frame :title="t('groups.title')">
            <template #aside><button v-if="account.me" type="button" class="btn-primary btn-sm" @click="making = true"><Icon name="plus" :size="16" /> {{ t('groups.start') }}</button></template>
            <Notice v-if="account.loaded && !account.me" tone="info">{{ t('groups.signIn') }} <a href="/signin?next=/groups" class="link" @click.prevent="navigate('/signin?next=/groups')">{{ t('auth.signIn') }}</a></Notice>
            <p v-else-if="failed" class="card p-6 text-cream" role="alert">{{ failed }}</p>
            <p v-else-if="!groups" class="text-muted" role="status">{{ t('create.loading') }}</p>
            <EmptyState v-else-if="!groups.length" icon="list" :title="t('groups.none')" :body="t('groups.noneHint')">
                <button type="button" class="btn-primary" @click="making = true">{{ t('groups.start') }}</button>
            </EmptyState>
            <ul v-else class="space-y-3">
                <li v-for="g in groups" :key="g.id">
                    <a :href="'/groups/' + g.id" class="card-interactive flex items-center gap-4 p-4" @click.prevent="navigate('/groups/' + g.id)">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-part-6 text-lg font-extrabold text-part-text-6" aria-hidden="true">{{ g.name.slice(0, 1) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-bold text-cream" translate="no">{{ g.name }}</span>
                            <span class="text-sm text-muted">{{ g.role === 'director' ? t('groups.youDirectCount', { count: Math.max(0, g.members - 1) }) : t('groups.directedBy', { name: g.director.name }) + ' · ' + t('groups.memberCount', { count: g.members }) }} · {{ t('groups.songCount', { count: g.songs }) }}</span>
                        </span>
                        <Icon name="arrow" :size="18" class="text-muted" />
                    </a>
                </li>
            </ul>
            <Modal v-if="making" :title="t('groups.start')" @close="making = false">
                <form class="space-y-4" @submit.prevent="create">
                    <p class="text-muted">{{ t('groups.startHint') }}</p>
                    <Field :label="t('groups.name')" required :error="error" v-slot="f">
                        <input :id="f.id" v-model="name" class="input" maxlength="80" required translate="no" :placeholder="t('groups.namePlaceholder')" />
                    </Field>
                    <AdultCheck v-model="adult" />
                    <Notice v-if="account.me?.plan !== 'plus'" tone="info">{{ t('groups.plusNeeded') }} <a href="/plus?from=choir" class="link" @click.prevent="navigate('/plus?from=choir')">{{ t('export.seePlus') }}</a></Notice>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="btn-secondary" @click="making = false">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary" :disabled="busy || !adult || !name.trim()">{{ t('groups.make') }}</button>
                    </div>
                </form>
            </Modal>
        </Frame>`,
    watch: {
        'account.me.id': {
            handler(id) {
                if (id) this.load();
            },
            immediate: true,
        },
    },
    methods: {
        async load() {
            try {
                this.groups = (await api.groups.list()).data;
            } catch (error) {
                this.failed = refusal(error);
            }
        },
        async create() {
            this.busy = true;
            this.error = null;
            try {
                const { data } = await api.groups.create({ name: this.name.trim(), adult: true });
                navigate(`/groups/${data.id}`);
            } catch (error) {
                if (error?.code === 'validation_failed') this.error = error.errors?.name ?? t('groups.nameInvalid');
                // 18 and over: someone who hasn't said when they were born is asked once, then it tries again.
                else if (error?.code === 'adult_required' && !account.me?.birthGiven) {
                    this.busy = false;
                    if (await askBirth()) return this.create();
                } else this.error = refusal(error);
            } finally {
                this.busy = false;
            }
        },
    },
};

export const GroupView = {
    components: { Frame, Field, Icon, Modal, Notice, PlusMoment },
    props: { id: { type: String, required: true } },
    data: () => ({ group: null, failed: null, link: null, inviting: false, sharing: false, songs: [], pick: '', busy: false, editing: null, starting: null, renaming: false, newName: '', renameError: null, limitOpen: false, templates: {} }),
    setup: () => ({ account, STATUS, templatePartName, formatDate, navigate, paint, PART_COLORS }),
    template: `
        <Frame :title="group?.name ?? t('groups.title')" back="/groups" title-is-content>
            <template #aside><button v-if="director && !group.readOnly" type="button" class="btn-primary btn-sm" :aria-expanded="inviting ? 'true' : 'false'" aria-controls="group-link" @click="invite">{{ t('groups.invite') }}</button></template>
            <p v-if="failed" class="card p-6 text-cream" role="alert">{{ failed }}</p>
            <p v-else-if="!group" class="text-muted" role="status">{{ t('create.loading') }}</p>
            <div v-else class="space-y-8">
                <p class="-mt-2 text-muted">{{ director ? t('groups.youDirectCount', { count: singers.length }) : t('groups.directedBy', { name: group.director.name }) + ' · ' + t('groups.memberCount', { count: group.memberCount }) }}</p>
                <Notice v-if="group.readOnly" tone="warn">{{ director ? t('groups.readOnlyDirector') : t('groups.readOnlyMember') }}</Notice>

                <!-- Invite singers: the group's link, to send however you like. -->
                <section v-if="director && inviting" id="group-link" class="card space-y-3 p-5" :aria-label="t('groups.linkTitle')">
                    <p class="text-cream">{{ t('groups.linkHint') }}</p>
                    <div v-if="shownLink" class="flex flex-wrap items-center gap-2">
                        <input class="input min-w-0 flex-1 basis-56" :value="shownLink" readonly :aria-label="t('groups.linkTitle')" translate="no" @focus="$event.target.select()" />
                        <button type="button" class="btn-primary" @click="copy">{{ t('groups.copyLink') }}</button>
                        <button v-if="canShare" type="button" class="btn-secondary" @click="share">{{ t('share.share') }}</button>
                    </div>
                    <p v-else-if="group.link" class="text-sm text-muted">{{ t('groups.linkHidden') }}</p>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-muted">{{ group.link ? t('groups.linkMadeReset', { date: formatDate(group.link.createdAt, { day: 'numeric', month: 'short' }) }) : '' }}</p>
                        <button v-if="group.link" type="button" class="btn-secondary btn-sm" :disabled="busy" @click="makeLink">{{ shownLink ? t('groups.resetLink') : t('groups.newLink') }}</button>
                    </div>
                </section>

                <section class="space-y-3" aria-labelledby="group-songs">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="group-songs" class="text-xl font-extrabold tracking-tight text-cream">{{ t('groups.songsTitle') }}</h2>
                        <button v-if="director" type="button" class="btn-secondary btn-sm" :disabled="group.readOnly" @click="openShare"><Icon name="plus" :size="16" /> {{ t('groups.shareSong') }}</button>
                    </div>
                    <p v-if="!group.songs.length" class="text-muted">{{ director ? t('groups.noSongsDirector') : t('groups.noSongs') }}</p>
                    <ul class="space-y-3">
                        <li v-for="song in group.songs" :key="song.templateId" class="card space-y-4 p-4 md:p-5">
                            <div class="flex flex-wrap items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-cream" translate="no">{{ song.title }}</p>
                                    <p v-if="director && !anyAssigned(song)" class="text-sm text-warn">{{ t('groups.nobodyYet') }}</p>
                                    <p v-else-if="director" class="text-sm text-muted">{{ summary(song) }}</p>
                                    <p v-else-if="myPart(song)" class="text-sm text-muted">{{ t('groups.yourPart', { part: partName(song, myPart(song).partKey) }) }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button v-if="director" type="button" class="btn-sm" :class="anyAssigned(song) ? 'btn-secondary' : 'btn-secondary border-warn/60 text-warn'" :aria-expanded="editing === song.templateId ? 'true' : 'false'" @click="editing = editing === song.templateId ? null : song.templateId">{{ editing === song.templateId ? t('groups.doneAssigning') : t('groups.assign') }}</button>
                                    <button type="button" class="btn-primary btn-sm" :disabled="starting === song.templateId" @click="learn(song)">{{ t('groups.openSong') }}</button>
                                </div>
                            </div>
                            <!-- The part strip: each of the song's own parts, who covers it and how far they've got. -->
                            <ul v-if="partsOf(song).length" class="grid grid-cols-2 gap-2 sm:grid-cols-[repeat(auto-fit,minmax(8.5rem,1fr))]" :aria-label="t('groups.whoSings')">
                                <li v-for="(p, i) in partsOf(song)" :key="p.key" class="rounded-[18px] bg-(--fill) p-3 text-sm">
                                    <span class="flex items-center gap-2 font-semibold text-cream"><span class="size-2.5 shrink-0 rounded-full" :style="{ background: paint(p.color ?? PART_COLORS[i % PART_COLORS.length]) }" aria-hidden="true"></span><span class="truncate">{{ templatePartName(p) }}</span></span>
                                    <span v-for="m in coverOf(song, p.key)" :key="m.id" class="mt-1.5 block">
                                        <span class="block truncate font-semibold text-cream" translate="no">{{ m.name }}</span>
                                        <span class="block text-xs font-semibold" :class="STATUS[assignmentOf(m, song).status]?.tone ?? STATUS.none.tone">{{ t(STATUS[assignmentOf(m, song).status]?.label ?? STATUS.none.label) }}</span>
                                    </span>
                                    <span v-if="!coverOf(song, p.key).length" class="mt-1.5 block text-muted">{{ t('groups.nobody') }}</span>
                                </li>
                            </ul>
                            <div v-if="director && editing === song.templateId" class="space-y-3 rounded-2xl bg-raised p-3">
                                <ul class="divide-y divide-line text-sm">
                                    <li v-for="m in singers" :key="m.id" class="flex flex-wrap items-center gap-3 py-2">
                                        <span class="min-w-0 flex-1 text-cream" translate="no">{{ m.name }}</span>
                                        <select class="input w-auto py-1" :value="assignmentOf(m, song)?.partKey ?? ''" :aria-label="t('groups.partFor', { name: m.name })" @change="assign(song, m, $event.target.value)">
                                            <option value="">{{ t('groups.noPart') }}</option>
                                            <option v-for="p in partsOf(song)" :key="p.key" :value="p.key">{{ templatePartName(p) }}</option>
                                        </select>
                                    </li>
                                    <li v-if="!singers.length" class="py-2 text-muted">{{ t('groups.noMembersYet') }}</li>
                                </ul>
                                <button type="button" class="btn-ghost btn-sm text-bad" @click="unshare(song)">{{ t('groups.unshare') }}</button>
                            </div>
                        </li>
                    </ul>
                </section>

                <section class="space-y-3" aria-labelledby="group-singers">
                    <h2 id="group-singers" class="text-xl font-extrabold tracking-tight text-cream">{{ t('groups.membersTitle') }}</h2>
                    <ul class="card divide-y divide-line">
                        <li v-for="m in members" :key="m.id" class="flex items-center gap-3 px-4 py-3">
                            <img v-if="m.avatarUrl" :src="m.avatarUrl" alt="" class="size-10 shrink-0 rounded-full object-cover" />
                            <span v-else class="grid size-10 shrink-0 place-items-center rounded-full font-bold" :class="m.role === 'director' ? 'gem' : 'bg-raised text-cream'" :style="m.role === 'director' ? { '--gem': 'var(--color-part-1)', '--gem-ink': 'var(--color-ink)' } : null" aria-hidden="true">{{ m.name.slice(0, 1) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold text-cream" translate="no">{{ m.name }}<span v-if="m.id === account.me?.id" class="font-normal text-muted"> {{ t('together.you') }}</span></span>
                                <span class="block text-sm text-muted">{{ aboutMember(m) }}</span>
                            </span>
                            <span v-if="m.role === 'director'" class="text-sm text-muted">{{ t('groups.director') }}</span>
                            <button v-else-if="director" type="button" class="btn-secondary btn-sm" @click="remove(m)">{{ t('together.remove') }}</button>
                        </li>
                    </ul>
                </section>

                <section class="space-y-3" aria-labelledby="group-group">
                    <h2 id="group-group" class="text-xl font-extrabold tracking-tight text-cream">{{ t('groups.groupTitle') }}</h2>
                    <div class="card divide-y divide-line">
                        <button v-if="director" type="button" class="flex min-h-14 w-full items-center justify-between gap-3 px-4 text-start font-semibold text-cream hover:bg-raised/60" @click="rename">{{ t('groups.renameRow') }} <Icon name="chevron" :size="18" class="-rotate-90 text-muted rtl:rotate-90" /></button>
                        <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-bad">{{ director ? t('groups.endRow') : t('groups.leaveRow') }}</span>
                                <span class="block text-sm text-muted">{{ director ? t('groups.endBody') : t('groups.leaveBody') }}</span>
                            </span>
                            <button type="button" class="btn-secondary btn-sm border-bad/60 text-bad" @click="director ? end() : leave()">{{ director ? t('groups.endButton') : t('groups.leaveButton') }}</button>
                        </div>
                    </div>
                </section>
            </div>

            <Modal v-if="renaming" :title="t('groups.rename')" @close="renaming = false">
                <form class="space-y-4" @submit.prevent="saveName">
                    <Field :label="t('groups.name')" required :error="renameError" v-slot="f">
                        <input :id="f.id" v-model="newName" class="input" maxlength="80" required translate="no" />
                    </Field>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="btn-secondary" @click="renaming = false">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary" :disabled="busy || !newName.trim()">{{ t('common.save') }}</button>
                    </div>
                </form>
            </Modal>
            <Modal v-if="sharing" :title="t('groups.shareSong')" @close="sharing = false">
                <form class="space-y-4" @submit.prevent="shareSong">
                    <label class="block text-sm text-muted">{{ t('groups.whichSong') }}
                        <select v-model="pick" class="input mt-1" required>
                            <optgroup v-if="ownSongs.length" :label="t('groups.yourTemplates')">
                                <option v-for="s in ownSongs" :key="s.id" :value="s.id" translate="no">{{ s.title }}</option>
                            </optgroup>
                            <optgroup :label="t('groups.library')">
                                <option v-for="s in songs" :key="s.id" :value="s.id" translate="no">{{ s.title }}</option>
                            </optgroup>
                        </select>
                    </label>
                    <p class="text-sm text-muted">{{ t('groups.shareHint') }}</p>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="btn-secondary" @click="sharing = false">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary" :disabled="busy || !pick">{{ t('groups.share') }}</button>
                    </div>
                </form>
            </Modal>
            <PlusMoment v-if="limitOpen" :title="t('plus.moment.projectTitle')" :text="t('plus.moment.projectLimit')" full from="project_limit" @close="limitOpen = false" />
        </Frame>`,
    computed: {
        director() {
            return this.group?.role === 'director';
        },
        singers() {
            return (this.group?.members ?? []).filter((m) => m.role !== 'director');
        },
        me() {
            return this.group?.members.find((m) => m.id === account.me?.id) ?? null;
        },
        ownSongs() {
            return mine.list.filter((r) => r.state === 'saved').map((r) => ({ id: r.id, title: r.template.title }));
        },
        canShare: () => typeof navigator.share === 'function',
        /** The link to send: just made here, or the one the server keeps for the director. */
        shownLink() {
            return this.link ?? this.group?.link?.url ?? null;
        },
        /** The director first, then the singers as they joined. */
        members() {
            return [...(this.group?.members ?? [])].sort((a, b) => (a.role === 'director' ? -1 : b.role === 'director' ? 1 : 0));
        },
    },
    async created() {
        await this.load();
    },
    methods: {
        async load() {
            try {
                this.group = (await api.groups.show(this.id)).data;
                this.loadParts();
            } catch (error) {
                this.failed = refusal(error);
            }
        },
        /** Each song's parts (for assigning), loaded as each song is. */
        async loadParts() {
            for (const song of this.group.songs) {
                if (this.templates[song.templateId]) continue;
                try {
                    this.templates = { ...this.templates, [song.templateId]: await loadTemplate(song.templateId) };
                } catch (error) {
                    console.warn('A group song did not load', song.templateId, error);
                }
            }
        },
        partsOf(song) {
            return this.templates[song.templateId]?.parts ?? [];
        },
        partName(song, key) {
            const part = this.partsOf(song).find((p) => p.key === key);
            return part ? templatePartName(part) : templatePartName({ key, name: key });
        },
        assignmentOf(member, song) {
            return member.assignments.find((a) => a.templateId === song.templateId) ?? null;
        },
        /** The singers on a part of a song. */
        coverOf(song, key) {
            return this.singers.filter((m) => this.assignmentOf(m, song)?.partKey === key);
        },
        anyAssigned(song) {
            return this.singers.some((m) => this.assignmentOf(m, song));
        },
        /** "2 of 4 parts covered · 1 recorded · no Soprano or Bass". */
        summary(song) {
            const parts = this.partsOf(song);
            const open = parts.filter((p) => !this.coverOf(song, p.key).length);
            const recorded = this.singers.filter((m) => this.assignmentOf(m, song)?.status === 'recorded').length;
            const bits = [];
            if (parts.length) bits.push(t('groups.covered', { count: parts.length - open.length, total: parts.length }));
            bits.push(t('groups.recordedCount', { count: recorded }));
            if (open.length && open.length < parts.length) bits.push(t('groups.noOne', { parts: formatOr(open.map((p) => templatePartName(p))) }));
            return bits.join(' · ');
        },
        /** Under a singer's name: their parts and when they last worked on one, or when they joined. */
        aboutMember(m) {
            if (m.role === 'director') return t('groups.directorDoes');
            const parts = m.assignments.map((a) => {
                const song = this.group.songs.find((s) => s.templateId === a.templateId);
                return song ? t('groups.partIn', { part: this.partName(song, a.partKey), title: song.title }) : null;
            }).filter(Boolean);
            const last = Math.max(0, ...m.assignments.map((a) => Date.parse(a.updatedAt) || 0));
            const when = last ? t('groups.active', { when: activeWhen(last) }) : t('groups.joined', { date: formatDate(m.joinedAt, { day: 'numeric', month: 'short' }) });
            return [...parts, when].join(' · ');
        },
        /** Invite singers: shows the link, making the group's first one if there's none yet. */
        async invite() {
            this.inviting = !this.inviting;
            if (this.inviting && !this.group.link) await this.makeLink();
        },
        myPart(song) {
            return this.me ? this.assignmentOf(this.me, song) : null;
        },
        /** Saves who sings which part of a song: the whole list for that song. */
        async assign(song, member, partKey) {
            const assignments = this.singers
                .map((m) => ({ memberId: m.id, partKey: m.id === member.id ? partKey : this.assignmentOf(m, song)?.partKey ?? '' }))
                .filter((a) => a.partKey);
            try {
                this.group = (await api.groups.assignments.update(this.id, song.templateId, { assignments })).data;
            } catch (error) {
                toast(refusal(error), { tone: 'error' });
            }
        },
        /** A project from the song, on your part. */
        async learn(song) {
            if (!canCreateProject()) {
                this.limitOpen = true;
                return;
            }
            this.starting = song.templateId;
            try {
                const template = this.templates[song.templateId] ?? (await loadTemplate(song.templateId));
                const project = await startFromTemplate(template, { focusKey: this.myPart(song)?.partKey ?? null });
                navigate(`/p/${project.id}/sing`);
            } catch (error) {
                if (!(error instanceof LimitReached)) console.error('The group song did not start', error);
                if (error instanceof LimitReached) this.limitOpen = true;
                else toast(error?.code === 'plus_required' ? t('groups.refused.plusSong') : t('projects.couldNotStart'), { tone: 'error' });
            } finally {
                this.starting = null;
            }
        },
        async makeLink() {
            if (this.group.link && !(await confirmAction({ title: t('groups.newLinkTitle'), body: t('groups.newLinkBody'), action: t('groups.newLink') }))) return;
            this.busy = true;
            try {
                this.link = (await api.groups.link.create(this.id)).data.url;
                this.group.link = { createdAt: new Date().toISOString() };
            } catch (error) {
                toast(refusal(error), { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },
        async copy() {
            try {
                await navigator.clipboard.writeText(this.shownLink);
                toast(t('share.copied'));
            } catch {
                toast(t('share.copyFailed'), { tone: 'error' });
            }
        },
        share() {
            // Closing the share sheet rejects too: nothing to say.
            navigator.share({ title: this.group.name, url: this.shownLink }).catch((error) => console.info('Not shared', error.name));
        },
        async openShare() {
            this.sharing = true;
            this.pick = '';
            await loadMine().catch((error) => console.warn('Your songs did not load', error));
            this.songs = (await listTemplates().catch(() => [])).map((s) => ({ id: s.id, title: s.title }));
        },
        async shareSong() {
            this.busy = true;
            try {
                this.group = (await api.groups.songs.create(this.id, { templateId: this.pick })).data;
                this.sharing = false;
                this.loadParts();
            } catch (error) {
                toast(refusal(error), { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },
        async unshare(song) {
            if (!(await confirmAction({ title: t('groups.unshareTitle', { title: song.title }), body: t('groups.unshareBody'), action: t('groups.unshare') }))) return;
            await api.groups.songs.delete(this.id, song.templateId).catch((error) => toast(refusal(error), { tone: 'error' }));
            this.editing = null;
            await this.load();
        },
        async remove(member) {
            if (!(await confirmAction({ title: t('groups.removeTitle', { name: member.name }), body: t('groups.removeBody'), action: t('together.remove') }))) return;
            await api.groups.members.delete(this.id, member.id).catch((error) => toast(refusal(error), { tone: 'error' }));
            await this.load();
        },
        rename() {
            this.newName = this.group.name;
            this.renameError = null;
            this.renaming = true;
        },
        async saveName() {
            this.busy = true;
            try {
                this.group = (await api.groups.update(this.id, { name: this.newName.trim() })).data;
                this.renaming = false;
            } catch (error) {
                this.renameError = error?.code === 'validation_failed' ? error.errors?.name ?? t('groups.nameInvalid') : refusal(error);
            } finally {
                this.busy = false;
            }
        },
        async end() {
            if (!(await confirmAction({ title: t('groups.endTitle', { name: this.group.name }), body: t('groups.endBody'), action: t('groups.end') }))) return;
            try {
                await api.groups.delete(this.id);
                toast(t('groups.ended', { name: this.group.name }));
                navigate('/groups', { replace: true });
            } catch (error) {
                toast(refusal(error), { tone: 'error' });
            }
        },
        async leave() {
            if (!(await confirmAction({ title: t('groups.leaveTitle', { name: this.group.name }), body: t('groups.leaveBody'), action: t('groups.leave') }))) return;
            try {
                await api.groups.members.delete(this.id, account.me.id);
                toast(t('groups.left', { name: this.group.name }));
                navigate('/groups', { replace: true });
            } catch (error) {
                toast(refusal(error), { tone: 'error' });
            }
        },
    },
};

export const ChoirJoinView = {
    components: { AdultCheck, InviteAccept, InviteCard },
    props: { token: { type: String, required: true } },
    data: () => ({ group: null, problem: null, adult: false, joining: false, needsAdult: false }),
    setup: () => ({ account, navigate }),
    template: `
        <InviteCard :from="group?.director.name ?? ''" :kind="group ? t('groups.joinKind', { name: group.name, count: group.members }) : ''" :lead="group ? t('groups.joinLead', { name: group.name }) : ''" :sign-in="'/signin?next=/choir/' + token">
            <template v-if="problem || !group" #top>
                <p v-if="problem" class="text-lg text-cream" role="alert">{{ problem }}</p>
                <p v-else class="text-muted" role="status">{{ t('create.loading') }}</p>
                <button v-if="problem" type="button" class="btn-secondary" @click="navigate('/')">{{ t('common.backToProjects') }}</button>
            </template>
            <template v-if="group && !problem">
                <template v-if="account.me">
                    <AdultCheck v-if="needsAdult" v-model="adult" />
                    <button type="button" class="btn-primary btn-lg w-full" :disabled="joining || (needsAdult && !adult)" @click="join">{{ joining ? t('join.joining') : t('groups.join') }}</button>
                </template>
                <InviteAccept v-else-if="account.loaded" :next="'/choir/' + token" />
            </template>
        </InviteCard>`,
    async created() {
        try {
            this.group = (await api.groups.join.show(this.token)).data;
        } catch (error) {
            this.problem = error?.code === 'not_found' ? t('groups.linkGone') : refusal(error);
        }
    },
    methods: {
        async join() {
            this.joining = true;
            try {
                // The 18-and-over answer is asked only when the account hasn't given it yet.
                const { data } = await api.groups.join(this.token, this.adult ? { adult: true } : {});
                navigate(`/groups/${data.id}`, { replace: true });
            } catch (error) {
                if (error?.code === 'adult_required' && !account.me?.birthGiven) {
                    this.joining = false;
                    if (await askBirth()) return this.join();
                } else if (error?.code === 'adult_required') this.needsAdult = true;
                else this.problem = refusal(error);
            } finally {
                this.joining = false;
            }
        },
    },
};
