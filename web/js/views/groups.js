// Choir groups (contract/api.md, "Choir groups"; free for everyone, docs/choir-tracks-plan.md): a director
// makes a group, invites members by the group's link (no email from us), shares songs
// (the library's, or their own templates), says who sings which part, and sees who has learned it
// (started, or recorded). Members join, learn the songs shared with them, on the part they were given,
// and leave when they like. For now, 18 and over: making or joining asks once.
// - /groups: the groups you direct or sing in, and Start a group.
// - /groups/<id>: a group (the director's view, or a member's).
// - /choir/<token>: the group's link: what it is, and Join.

import { AccountWall } from '../components/account-wall.js';
import { AppScreen } from '../components/page.js';
import { api, uploadTo } from '../api.js';
import { countShare, shareRef, tried } from '../usage.js';
import { first } from '../funnel.js';
import { formatDate, locale, t, tk } from '../i18n.js';
import { goBack, navigate } from '../router.js';
import { account } from '../stores/account.js';
import { learnGroupSong } from '../group-songs.js';
import { loadMine, mine } from '../stores/templates.js';
import { listTemplates, loadTemplate, templatePartName } from '../templates.js';
import { EmptyState, Field } from '../components/form.js';
import { Icon, Logo } from '../components/icon.js';
import { paint } from '../colors.js';
import { PART_COLORS } from '../theme.js';
import { markRaw } from 'vue';
import { Modal, toast } from '../components/ui.js';
import { GameOnly, sk } from '../skin.js';
import { confirmAction } from '../components/confirm.js';
import { InviteAccept, InviteCard } from '../components/invite-card.js';
import { askBirth } from '../components/birth.js';
import { daysUntil, isoDate, rehearsalDates } from '../rehearsal.js';

const STATUS = {
    none: { tone: 'text-muted', label: tk('groups.status.none') },
    started: { tone: 'text-warn', label: tk('groups.status.started') },
    recorded: { tone: 'text-good', label: tk('groups.status.recorded') },
};
const REFUSED = {
    group_limit: tk('groups.refused.groupLimit'),
    group_full: tk('groups.refused.full'),
    director_only: tk('groups.refused.directorOnly'),
    not_found: tk('groups.refused.notFound'),
    offline: tk('common.offline'),
};
/**
 * Learning tracks' two kinds of piece, for now (docs/choir-tracks-plan.md, "Rights check"): the server's `rights` values,
 * each with the sentence the director confirms, word for word the one the server keeps as the record
 * (server/lang/en/support.php, tracks.rights). Choosing one is the confirmation: the sentence says "I confirm".
 */
const RIGHTS = {
    'public-domain': { name: tk('groups.tracks.publicDomain'), sentence: tk('groups.tracks.publicDomainSentence') },
    'own-arrangement': { name: tk('groups.tracks.ownArrangement'), sentence: tk('groups.tracks.ownArrangementSentence') },
};
/**
 * "Send us your song": the score's files (contract/api.md, groups.tracks.scores.*). Their type by extension first,
 * since browsers often give none for MusicXML; 25 MB a file, 10 a request.
 */
const SCORE_TYPES = { pdf: 'application/pdf', musicxml: 'application/vnd.recordare.musicxml+xml', xml: 'application/vnd.recordare.musicxml+xml', mxl: 'application/vnd.recordare.musicxml', jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', heic: 'image/heic' };
const SCORE_ACCEPT = '.pdf,.musicxml,.xml,.mxl,.jpg,.jpeg,.png,.heic,application/pdf,image/jpeg,image/png,image/heic';
const SCORE_BYTES = 25 * 1024 * 1024;
const SCORE_FILES = 10;
const scoreType = (file) => SCORE_TYPES[file.name.split('.').pop().toLowerCase()] ?? (Object.values(SCORE_TYPES).includes(file.type) ? file.type : null);
async function sha256(file) {
    const digest = await crypto.subtle.digest('SHA-256', await file.arrayBuffer());
    return [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, '0')).join('');
}
/** Why a file didn't go (the server's check, or the way there). */
const SCORE_REFUSED = { score_type: tk('groups.tracks.scoreType'), upload_incomplete: tk('groups.tracks.scoreBroken'), upload_mismatch: tk('groups.tracks.scoreBroken'), rate_limited: tk('groups.tracks.scoreTooMuchToday') };
const scoreRefusal = (error) => (SCORE_REFUSED[error?.code] ? t(SCORE_REFUSED[error.code]) : refusal(error));
/** A request's status line (groups.tracks.list). */
const REQUEST_STATUS = { received: tk('groups.tracks.status.received'), delivered: tk('groups.tracks.status.delivered'), closed: tk('groups.tracks.status.closed') };
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
    components: { AppScreen },
    props: { title: { type: String, required: true }, back: { type: String, default: '/' }, titleIsContent: Boolean },
    template: `
        <!-- The site's full row (Hillel's width design, 2026-11-11: "apply these changes to all pages which will benefit"):
             the groups as cards across, a group's songs beside its singers. A phone stacks the same order. -->
        <AppScreen :title="title" :back="back" :title-is-content="titleIsContent" measure="" data-ui="groups">
            <template #aside><slot name="aside" /></template>
            <slot />
        </AppScreen>`,
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
    components: { AccountWall, Frame, EmptyState, Field, Icon, Modal, AdultCheck, GameOnly },
    data: () => ({ groups: null, failed: null, making: false, wantStart: new URLSearchParams(location.search).get('start') === '1', shareAfter: new URLSearchParams(location.search).get('share'), here: location.pathname + location.search, name: '', adult: false, busy: false, error: null }),
    setup: () => ({ account, navigate, sk }),
    template: `
        <Frame :title="sk('groups.title', 'game.groups.title')">
            <template #aside><button v-if="account.me" type="button" class="btn-primary btn-sm" @click="making = true"><Icon name="plus" :size="16" /> {{ sk('groups.start', 'game.groups.start') }}</button></template>
            <!-- The wall keeps the address, so ?start=1 (the directors' pages link here to start one) opens the start sheet after sign-up. -->
            <AccountWall v-if="account.loaded && !account.me" :reason="wantStart ? 'groupsStart' : 'groups'" :next="here" />
            <p v-else-if="failed" class="card p-6 text-cream" role="alert">{{ failed }}</p>
            <p v-else-if="!groups" class="text-muted" role="status">{{ t('create.loading') }}</p>
            <EmptyState v-else-if="!groups.length" icon="list" :title="t('groups.none')" :body="t('groups.noneHint')">
                <button type="button" class="btn-primary" @click="making = true">{{ sk('groups.start', 'game.groups.start') }}</button>
            </EmptyState>
            <div v-else class="space-y-3">
                <GameOnly><p class="ge-kick" data-groups-kick>{{ t('game.groups.kick') }}</p></GameOnly>
                <ul class="grid gap-3 min-[640px]:grid-cols-2">
                    <li v-for="g in groups" :key="g.id" class="min-w-0">
                        <a :href="'/groups/' + g.id" class="card-interactive flex h-full items-center gap-4 p-4" data-group-card @click.prevent="navigate('/groups/' + g.id)">
                            <span class="bubble grid size-11 shrink-0 place-items-center text-lg font-extrabold" style="--bubble: var(--color-part-6); --bubble-ink: var(--color-part-text-6)" aria-hidden="true" data-avatar>{{ g.name.slice(0, 1) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-bold text-cream" translate="no">{{ g.name }}</span>
                                <span class="text-sm text-muted">{{ g.role === 'director' ? t('groups.youDirectCount', { count: Math.max(0, g.members - 1) }) : t('groups.directedBy', { name: g.director.name }) + ' · ' + t('groups.memberCount', { count: g.members }) }} · {{ t('groups.songCount', { count: g.songs }) }}</span>
                            </span>
                            <Icon name="arrow" :size="18" class="text-muted" />
                        </a>
                    </li>
                </ul>
            </div>
            <Modal v-if="making" :title="sk('groups.start', 'game.groups.start')" @close="making = false">
                <!-- Under 18 by the account's own birth date: no "I'm 18 or over" to tick, a kind word instead. -->
                <div v-if="minor" class="space-y-4" data-too-young>
                    <p class="text-cream">{{ t('groups.tooYoungStart') }}</p>
                    <div class="flex justify-end"><button type="button" class="btn-secondary" @click="making = false">{{ t('common.close') }}</button></div>
                </div>
                <form v-else class="space-y-4" @submit.prevent="create">
                    <p class="text-muted">{{ t('groups.startHint') }}</p>
                    <Field :label="t('groups.name')" required :error="error" v-slot="f">
                        <input :id="f.id" v-model="name" class="input" maxlength="80" required translate="no" :placeholder="t('groups.namePlaceholder')" />
                    </Field>
                    <AdultCheck v-model="adult" />
                    <div class="flex justify-end gap-3">
                        <button type="button" class="btn-secondary" @click="making = false">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary" :disabled="busy || !adult || !name.trim()">{{ t('groups.make') }}</button>
                    </div>
                </form>
            </Modal>
        </Frame>`,
    computed: {
        /** Under 18 by the account's own birth date (given, and not an adult). */
        minor: () => !!account.me?.birthGiven && account.me?.isAdult === false,
    },
    watch: {
        /** The Start a group sheet shown: a choir's path begins (grp_start, this device's first). */
        making(open) {
            if (open) first('grp_start');
        },
        'account.me.id': {
            handler(id) {
                if (!id) return;
                this.load();
                // Arrived to start one (?start=1): the sheet opens once you're signed in, never over the wall, and the address is
                // made plain so a reload doesn't open it again.
                if (this.wantStart) {
                    this.wantStart = false;
                    this.making = true;
                    history.replaceState(history.state, '', '/groups');
                }
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
                first('grp_made');
                tried('together');
                navigate(`/groups/${data.id}` + (this.shareAfter ? `?share=${encodeURIComponent(this.shareAfter)}` : ''));
            } catch (error) {
                if (error?.code === 'validation_failed') this.error = error.errors?.name ?? t('groups.nameInvalid');
                // 18 and over: someone who hasn't said when they were born is asked once, then it tries again.
                else if (error?.code === 'adult_required' && !account.me?.birthGiven) {
                    this.busy = false;
                    if (await askBirth()) return this.create();
                } else if (error?.code === 'adult_required') this.error = t('groups.tooYoungStart'); // their birth date says under 18
                else this.error = refusal(error);
            } finally {
                this.busy = false;
            }
        },
    },
};

export const GroupView = {
    components: { Frame, Field, Icon, Modal },
    props: { id: { type: String, required: true } },
    data: () => ({ settingRehearsal: false, rehearsalDate: '', rehearsalWeekly: true, rehearsalError: null, asking: false, tracks: { piece: '', composer: '', voicing: '', note: '', rights: '' }, scores: [], requests: null, tracksErrors: null, tracksError: null, tracksSent: false, directorImport: null, group: null, failed: null, link: null, inviting: false, sharing: false, songs: [], pick: '', busy: false, editing: null, starting: null, renaming: false, newName: '', renameError: null, templates: {} }),
    setup: () => ({ account, STATUS, RIGHTS, SCORE_ACCEPT, SCORE_FILES, REQUEST_STATUS, templatePartName, formatDate, navigate, paint, PART_COLORS }),
    template: `
        <Frame :title="group?.name ?? t('groups.title')" back="/groups" title-is-content>
            <p v-if="failed" class="card p-6 text-cream" role="alert">{{ failed }}</p>
            <p v-else-if="!group" class="text-muted" role="status">{{ t('create.loading') }}</p>
            <div v-else class="space-y-8 min-[900px]:grid min-[900px]:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] min-[900px]:items-start min-[900px]:gap-x-8 min-[900px]:gap-y-8 min-[900px]:space-y-0">
                <!-- Invite singers sits by the count, not in the header, so a group's name keeps the width (a long one ran under it in the game skin). -->
                <div class="-mt-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-2 min-[900px]:col-span-2">
                    <p class="text-muted">{{ director ? t('groups.youDirectCount', { count: singers.length }) : t('groups.directedBy', { name: group.director.name }) + ' · ' + t('groups.memberCount', { count: group.memberCount }) }}</p>
                    <p v-if="noPartYet" class="basis-full text-cream" data-no-part-yet>{{ t('groups.noPartYet', { name: group.director.name }) }}</p>
                    <button v-if="director" type="button" class="btn-sm" :class="group.songs.length ? 'btn-primary' : 'btn-secondary'" :aria-expanded="inviting ? 'true' : 'false'" aria-controls="group-link" data-invite @click="invite">{{ t('groups.invite') }}</button>
                    <!-- The next rehearsal, when the director has set one (nothing at all otherwise): information, never a nag. -->
                    <p v-if="rehearsalLine" class="basis-full text-cream" data-rehearsal>{{ rehearsalLine }}</p>
                </div>

                <!-- Invite singers: the group's link, to send however you like. -->
                <section v-if="director && inviting" id="group-link" class="card space-y-3 p-5 min-[900px]:col-span-2" :aria-label="t('groups.linkTitle')">
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

                <!-- At left: the songs (what the group learns), and the director's tracks line under them. -->
                <div class="space-y-8">
                <section class="space-y-3" aria-labelledby="group-songs">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="group-songs" class="text-xl font-extrabold tracking-tight text-cream">{{ t('groups.songsTitle') }}</h2>
                        <!-- A new group's first step is a song, so its singers find something to learn when they join: Share a song is the page's primary until there is one. -->
                        <button v-if="director" type="button" class="btn-sm" :class="group.songs.length ? 'btn-secondary' : 'btn-primary'" data-share-song @click="openShare"><Icon name="plus" :size="16" /> {{ t('groups.shareSong') }}</button>
                    </div>
                    <p v-if="!group.songs.length" class="text-muted">{{ director ? t('groups.noSongsDirector') : t('groups.noSongs') }}</p>
                    <ul class="space-y-3">
                        <li v-for="song in group.songs" :key="song.templateId" class="card space-y-4 p-4 md:p-5">
                            <!-- The title and its line take the card's width; on a phone the buttons go under them, full width (beside them they squeezed the title to a word a line). -->
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start" data-group-song>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-cream" translate="no">{{ song.title }}</p>
                                    <p v-if="director && !anyAssigned(song)" class="text-sm text-warn">{{ t('groups.nobodyYet') }}</p>
                                    <!-- Assigning a choir song by song is one dropdown per singer: the parts they have in the group's last song, in one tap. -->
                                    <button v-if="director && copyPlan(song).length && (editing === song.templateId || !anyAssigned(song))" type="button" class="btn-secondary btn-sm mt-2" :disabled="busy" data-same-parts @click="copyParts(song)">{{ t('groups.sameParts', { title: copySource(song).title }) }}</button>
                                    <p v-else-if="director" class="text-sm text-muted">{{ summary(song) }}</p>
                                    <p v-else-if="myPart(song)" class="text-sm text-cream" data-my-part>{{ t('groups.yourPart', { part: partName(song, myPart(song).partKey) }) }} · <span class="font-semibold" :class="STATUS[myPart(song).status]?.tone ?? STATUS.none.tone">{{ t(STATUS[myPart(song).status]?.label ?? STATUS.none.label) }}</span><span v-if="soon(myPart(song))" data-rehearsal-soon> · {{ soon(myPart(song)) }}</span></p>
                                </div>
                                <div class="grid gap-2 sm:flex sm:shrink-0" :class="director ? 'grid-cols-2' : ''">
                                    <!-- One primary: a member's next part to learn; Open is secondary everywhere else. -->
                                    <button type="button" class="btn-sm order-last" :class="song.templateId === nextToLearn ? 'btn-primary' : 'btn-secondary'" :disabled="starting === song.templateId" @click="learn(song)">{{ myPart(song) ? t('groups.learnPart') : t('groups.openSong') }}</button>
                                    <button v-if="director" type="button" class="btn-sm" :class="anyAssigned(song) ? 'btn-secondary' : 'btn-secondary border-warn/60 text-warn'" :aria-expanded="editing === song.templateId ? 'true' : 'false'" @click="editing = editing === song.templateId ? null : song.templateId">{{ editing === song.templateId ? t('groups.doneAssigning') : t('groups.assign') }}</button>
                                </div>
                            </div>
                            <!-- The part strip: each of the song's own parts, who covers it and how far they've got. -->
                            <ul v-if="partsOf(song).length" class="grid grid-cols-2 gap-2 sm:grid-cols-[repeat(auto-fit,minmax(8.5rem,1fr))]" :aria-label="t('groups.whoSings')">
                                <li v-for="(p, i) in partsOf(song)" :key="p.key" class="rounded-tile bg-(--fill) p-3 text-sm" :class="myPart(song)?.partKey === p.key ? 'ring-2 ring-cream' : ''" :data-mine="myPart(song)?.partKey === p.key ? '' : null">
                                    <span class="flex items-center gap-2 font-semibold text-cream"><span class="size-2.5 shrink-0 rounded-full" :style="{ background: paint(p.color ?? PART_COLORS[i % PART_COLORS.length]) }" aria-hidden="true"></span><span class="truncate">{{ templatePartName(p) }}</span></span>
                                    <span v-for="m in coverOf(song, p.key)" :key="m.id" class="mt-1.5 block">
                                        <span class="block truncate font-semibold text-cream"><span translate="no">{{ m.name }}</span><span v-if="m.id === account.me?.id" class="ms-1 font-normal text-muted">{{ t('together.you') }}</span></span>
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
                <!-- Learning tracks of the choir's own piece (docs/choir-tracks-plan.md, stage 1): one quiet line, the director's only. -->
                <p v-if="director" class="-mt-4 text-sm text-muted" data-ask-tracks>{{ t('groups.tracks.ask') }} <button type="button" class="link inline-flex min-h-6 items-center" @click="openTracks">{{ t('groups.tracks.askLink') }}</button><template v-if="directorImport"><span class="mx-1.5" aria-hidden="true">·</span><button type="button" class="link inline-flex min-h-6 items-center" data-make-tracks @click="$refs.directorImport.open()">{{ t('groups.tracks.makeLink') }}</button></template></p>
                <!-- Make tracks from a score (docs/score-import-plan.md, section 4): the director's own way, where sheet_import allows it. -->
                <component :is="directorImport" v-if="directorImport" ref="directorImport" :group="{ id: group.id, name: group.name }" @send="openTracks" />
                <!-- The songs the director sent us, newest first, with where each has got to; a delivered one is in the group, ready to open (the director's in-app note: no email). -->
                <section v-if="director && requests?.length" class="space-y-3" aria-labelledby="group-requests" data-tracks-requests>
                    <h2 id="group-requests" class="text-sm font-bold text-muted">{{ t('groups.tracks.requestsTitle') }}</h2>
                    <ul class="card divide-y divide-line">
                        <li v-for="r in requests" :key="r.id" class="flex flex-wrap items-center gap-3 px-4 py-3" :data-tracks-request="r.status">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold text-cream" translate="no">{{ r.piece }}</span>
                                <span class="block text-sm" :class="r.status === 'delivered' ? 'font-semibold text-good' : 'text-muted'">{{ t(REQUEST_STATUS[r.status] ?? REQUEST_STATUS.received) }}</span>
                            </span>
                            <button v-if="r.status === 'delivered' && r.song" type="button" class="btn-primary btn-sm" data-tracks-open @click="openRequest(r)">{{ t('groups.tracks.open') }}</button>
                        </li>
                    </ul>
                </section>
                </div>

                <!-- At right: who's in it, and the group's own settings. -->
                <div class="space-y-8">

                <section class="space-y-3" aria-labelledby="group-singers">
                    <h2 id="group-singers" class="text-xl font-extrabold tracking-tight text-cream">{{ t('groups.membersTitle') }}</h2>
                    <ul class="card divide-y divide-line">
                        <li v-for="m in members" :key="m.id" class="flex items-center gap-3 px-4 py-3">
                            <img v-if="m.avatarUrl" :src="m.avatarUrl" alt="" class="size-10 shrink-0 rounded-full object-cover" data-avatar />
                            <span v-else class="bubble grid size-10 shrink-0 place-items-center font-bold" :class="m.role === 'director' ? '' : 'bubble-clear'" :style="m.role === 'director' ? { '--bubble': 'var(--color-part-1)', '--bubble-ink': 'var(--color-part-text-1)' } : { '--bubble-ink': 'var(--color-cream)' }" data-avatar aria-hidden="true">{{ m.name.slice(0, 1) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold text-cream"><span translate="no">{{ m.name }}</span><span v-if="m.id === account.me?.id" class="ms-1 font-normal text-muted">{{ t('together.you') }}</span></span>
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
                        <!-- The next rehearsal: tucked in here, never a step in starting a group (the coordinator, 2026-11-08). -->
                        <button v-if="director" type="button" class="flex min-h-14 w-full items-center justify-between gap-3 px-4 text-start font-semibold text-cream hover:bg-raised/60" data-rehearsal-row @click="openRehearsal">{{ t('groups.rehearsal.row') }} <span class="ms-auto text-sm font-normal text-muted">{{ rehearsalNext ? shortDate(rehearsalNext) : t('groups.rehearsal.notSet') }}</span><Icon name="chevron" :size="18" class="-rotate-90 text-muted rtl:rotate-90" /></button>
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
            <Modal v-if="settingRehearsal" :title="t('groups.rehearsal.title')" @close="settingRehearsal = false">
                <form class="space-y-4" data-rehearsal-form @submit.prevent="saveRehearsal">
                    <p class="text-muted">{{ t('groups.rehearsal.hint') }}</p>
                    <Field :label="t('groups.rehearsal.date')" required :error="rehearsalError" v-slot="f">
                        <input :id="f.id" v-model="rehearsalDate" type="date" class="input" :min="today()" required :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                    </Field>
                    <label class="check-row"><input v-model="rehearsalWeekly" type="checkbox" class="checkbox" /><span>{{ t('groups.rehearsal.weeklyLabel') }}</span></label>
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <button v-if="group.rehearsal" type="button" class="btn-ghost me-auto" :disabled="busy" @click="clearRehearsal">{{ t('groups.rehearsal.clear') }}</button>
                        <button type="button" class="btn-secondary" @click="settingRehearsal = false">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary" :disabled="busy || !rehearsalDate">{{ t('common.save') }}</button>
                    </div>
                </form>
            </Modal>
            <Modal v-if="asking" :title="t('groups.tracks.title')" @close="asking = false">
                <div v-if="tracksSent" class="space-y-4" role="status" data-tracks-sent>
                    <p class="text-cream">{{ t('groups.tracks.sent', { piece: tracksSent }) }}</p>
                    <div class="flex justify-end"><button type="button" class="btn-primary" @click="asking = false">{{ t('common.done') }}</button></div>
                </div>
                <form v-else class="space-y-4" novalidate data-tracks-form @submit.prevent="sendTracks">
                    <p class="text-muted">{{ t('groups.tracks.intro') }}</p>
                    <Field :label="t('groups.tracks.piece')" required :error="tracksErrors?.piece" v-slot="f">
                        <input :id="f.id" v-model="tracks.piece" class="input" maxlength="200" required translate="no" :placeholder="t('groups.tracks.piecePlaceholder')" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                    </Field>
                    <Field :label="t('groups.tracks.composer')" :error="tracksErrors?.composer" v-slot="f">
                        <input :id="f.id" v-model="tracks.composer" class="input" maxlength="200" translate="no" :aria-describedby="f.describedby" />
                    </Field>
                    <Field :label="t('groups.tracks.voicing')" :error="tracksErrors?.voicing" v-slot="f">
                        <input :id="f.id" v-model="tracks.voicing" class="input" maxlength="60" translate="no" :placeholder="t('groups.tracks.voicingPlaceholder')" :aria-describedby="f.describedby" />
                    </Field>
                    <!-- The score: each file goes as it's picked (a presigned PUT, then checked), private to us. -->
                    <fieldset class="space-y-2" data-tracks-scores>
                        <legend class="label">{{ t('groups.tracks.scores') }}</legend>
                        <p class="text-sm text-muted">{{ t('groups.tracks.scoresHint', { count: SCORE_FILES }) }}</p>
                        <ul v-if="scores.length" class="divide-y divide-line rounded-2xl border border-line">
                            <li v-for="(f, i) in scores" :key="f.key" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 text-sm" :data-score="f.status">
                                <span class="min-w-0 flex-1 truncate text-cream" translate="no">{{ f.name }}</span>
                                <span v-if="f.status === 'uploading'" class="text-muted" role="status">{{ t('groups.tracks.scoreSending') }}</span>
                                <span v-else-if="f.status === 'ready'" class="font-semibold text-good">{{ t('groups.tracks.scoreReady') }}</span>
                                <button type="button" class="btn-ghost btn-sm" :aria-label="t('groups.tracks.scoreRemove', { name: f.name })" @click="removeScore(i)"><Icon name="close" :size="16" /></button>
                                <p v-if="f.error" class="basis-full text-bad" role="alert">{{ f.error }}</p>
                            </li>
                        </ul>
                        <label v-if="scores.length < SCORE_FILES" class="btn-secondary btn-sm w-fit cursor-pointer focus-within:outline-2"><Icon name="plus" :size="16" /> {{ scores.length ? t('groups.tracks.scoreMore') : t('groups.tracks.scoreAdd') }}
                            <input type="file" class="sr-only" multiple :accept="SCORE_ACCEPT" data-score-input @change="addScores($event.target.files); $event.target.value = ''" />
                        </label>
                        <p v-if="tracksErrors?.scoreIds" class="field-error" role="alert">{{ tracksErrors.scoreIds }}</p>
                    </fieldset>
                    <fieldset class="space-y-2" data-tracks-rights>
                        <legend class="label">{{ t('groups.tracks.rights') }}</legend>
                        <label v-for="(words, r) in RIGHTS" :key="r" class="radio-card">
                            <input v-model="tracks.rights" type="radio" name="tracks-rights" class="radio mt-px" :value="r" />
                            <span><span class="block font-semibold text-cream">{{ t(words.name) }}</span><span class="block text-sm text-muted">{{ t(words.sentence) }}</span></span>
                        </label>
                        <p class="text-sm text-muted">{{ t('groups.tracks.rightsOther') }}</p>
                        <p v-if="tracksErrors?.rights" class="field-error" role="alert">{{ tracksErrors.rights }}</p>
                    </fieldset>
                    <Field :label="t('groups.tracks.note')" :error="tracksErrors?.note" v-slot="f">
                        <textarea :id="f.id" v-model="tracks.note" class="input min-h-24" maxlength="2000" :placeholder="t('groups.tracks.notePlaceholder')" :aria-describedby="f.describedby"></textarea>
                    </Field>
                    <p v-if="tracksError" class="field-error" role="alert">{{ tracksError }}</p>
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <p v-if="tracksNeeds" class="me-auto text-sm text-muted" data-tracks-needs>{{ tracksNeeds }}</p>
                        <button type="button" class="btn-secondary" @click="asking = false">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary" :disabled="busy || !!tracksNeeds">{{ busy ? t('groups.tracks.sending') : t('groups.tracks.send') }}</button>
                    </div>
                </form>
            </Modal>
            <Modal v-if="sharing" :title="t('groups.shareSong')" @close="sharing = false">
                <form class="space-y-4" @submit.prevent="shareSong">
                    <label class="block text-sm text-muted">{{ t('groups.whichSong') }}
                        <select v-model="pick" class="input mt-1" required>
                            <option value="" disabled>{{ t('groups.pickSong') }}</option>
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
        </Frame>`,
    computed: {
        director() {
            return this.group?.role === 'director';
        },
        /** Make tracks from a score: a director's, once sheet_import lets directors in (staff before). */
        canImport() {
            return this.director && !!(account.me?.staff || account.config?.sheetImport);
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
        /** The next rehearsal here (rehearsal.js: the server's, rolled on by weeks on this device), or null. */
        rehearsalNext() {
            return rehearsalDates(this.group?.rehearsal).next;
        },
        /** "Next rehearsal: Thursday 12 November · every week", or nothing without one. */
        rehearsalLine() {
            if (!this.rehearsalNext) return '';
            const line = t('groups.rehearsal.next', { date: this.longDate(this.rehearsalNext) });
            return this.group.rehearsal.weekly ? `${line} · ${t('groups.rehearsal.weekly')}` : line;
        },
        /** A member with songs to learn but no part in any of them yet: told so, and what to do meanwhile. */
        noPartYet() {
            return !this.director && !!this.group?.songs.length && !this.group.songs.some((s) => this.myPart(s));
        },
        /** A member's next song to learn: the first with a part of theirs not yet recorded (its Learn button is the page's primary). */
        nextToLearn() {
            if (this.director) return null;
            return this.group?.songs.find((s) => this.myPart(s) && this.myPart(s).status !== 'recorded')?.templateId ?? null;
        },
        /** What the request still needs before it can go (shown beside Send), or null. */
        tracksNeeds() {
            if (!this.tracks.piece.trim()) return t('groups.tracks.needsPiece');
            if (this.scores.some((f) => f.status === 'uploading')) return t('groups.tracks.needsSending');
            if (!this.scores.some((f) => f.status === 'ready')) return t('groups.tracks.needsScore');
            if (!this.tracks.rights) return t('groups.tracks.needsRights');
            return null;
        },
        /** The director first, then the singers as they joined. */
        members() {
            return [...(this.group?.members ?? [])].sort((a, b) => (a.role === 'director' ? -1 : b.role === 'director' ? 1 : 0));
        },
    },
    async created() {
        await this.load();
        const share = new URLSearchParams(location.search).get('share');
        if (share && this.director) {
            history.replaceState(history.state, '', location.pathname);
            this.pick = share;
            await this.shareSong();
        }
    },
    watch: {
        // The director's import is loaded once it may show, so its link opens the file picker at once (a tap's own turn).
        canImport: {
            immediate: true,
            async handler(on) {
                if (!on || this.directorImport) return;
                try {
                    this.directorImport = markRaw((await import('../components/create/director-import.js')).DirectorImport);
                } catch (error) {
                    console.error('Make tracks from a score did not load', error);
                }
            },
        },
    },
    methods: {
        async load() {
            try {
                this.group = (await api.groups.show(this.id)).data;
                this.loadParts();
                this.loadRequests();
            } catch (error) {
                this.failed = refusal(error);
            }
        },
        /** The songs the director sent us, and where each has got to (nothing for a member). */
        async loadRequests() {
            if (!this.director) return;
            try {
                this.requests = (await api.groups.tracks.list(this.id)).data;
            } catch (error) {
                console.warn('The tracks requests did not load', error);
            }
        },
        /** A delivered request's song: it's in the group, so it opens as the group's songs do. */
        openRequest(r) {
            const song = this.group.songs.find((s) => s.templateId === r.song.id);
            if (song) this.learn(song);
            else navigate('/t/' + r.song.id);
        },
        /** The score's files, each sent as it's picked: checked here for kind and size, then by the server. */
        addScores(files) {
            for (const file of files) {
                if (this.scores.length >= SCORE_FILES) {
                    toast(t('groups.tracks.scoreTooMany', { count: SCORE_FILES }), { tone: 'error' });
                    break;
                }
                const type = scoreType(file);
                const entry = { key: crypto.randomUUID(), name: file.name, id: null, status: 'failed', error: null };
                if (!type) entry.error = t('groups.tracks.scoreWrongKind', { name: file.name });
                else if (file.size > SCORE_BYTES) entry.error = t('groups.tracks.scoreTooBig', { name: file.name });
                else entry.status = 'uploading';
                this.scores.push(entry);
                if (entry.status === 'uploading') this.sendScore(this.scores.at(-1), file, type);
            }
        },
        async sendScore(entry, file, contentType) {
            try {
                const { data, upload } = await api.groups.tracks.scores.create(this.id, { name: file.name.slice(0, 200), contentType, bytes: file.size, sha256: await sha256(file) });
                entry.id = data.id;
                await uploadTo(upload, file);
                await api.groups.tracks.scores.complete(this.id, data.id);
                entry.status = 'ready';
            } catch (error) {
                entry.status = 'failed';
                entry.error = scoreRefusal(error);
            }
        },
        /** Takes a file out of the request (the server deletes a file no request uses, a day on). */
        removeScore(i) {
            this.scores.splice(i, 1);
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
            // Since the last rehearsal (the server's count, the director's only): how many of the singers given a part have begun it.
            const previous = rehearsalDates(this.group?.rehearsal).previous;
            const assigned = this.singers.filter((m) => this.assignmentOf(m, song)).length;
            if (song.sinceRehearsal && previous && assigned) bits.push(t('groups.rehearsal.since', { started: song.sinceRehearsal.started, count: assigned, date: this.shortDate(previous) }));
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
        /** The song to copy parts from: the newest other shared song where someone has a part this song also has. */
        copySource(song) {
            return [...this.group.songs].filter((s) => s.templateId !== song.templateId).sort((a, b) => Date.parse(b.sharedAt) - Date.parse(a.sharedAt))
                .find((s) => this.singers.some((m) => this.matchPart(song, s, this.assignmentOf(m, s)?.partKey))) ?? null;
        },
        /** This song's part for a part of another song: the same key, else the same name ("Tenor" and "Tenor"). */
        matchPart(song, from, key) {
            if (!key) return null;
            const parts = this.partsOf(song);
            const named = this.partsOf(from).find((p) => p.key === key);
            return (parts.find((p) => p.key === key) ?? (named ? parts.find((p) => templatePartName(p) === templatePartName(named)) : null))?.key ?? null;
        },
        /** Who'd get which part from the source song: singers with no part in this song yet. */
        copyPlan(song) {
            const from = this.copySource(song);
            if (!from) return [];
            return this.singers.filter((m) => !this.assignmentOf(m, song)).map((m) => ({ memberId: m.id, partKey: this.matchPart(song, from, this.assignmentOf(m, from)?.partKey) })).filter((a) => a.partKey);
        },
        /** Gives everyone their part from the source song in one save, keeping parts already given here. */
        async copyParts(song) {
            const kept = this.singers.map((m) => ({ memberId: m.id, partKey: this.assignmentOf(m, song)?.partKey ?? '' })).filter((a) => a.partKey);
            this.busy = true;
            try {
                this.group = (await api.groups.assignments.update(this.id, song.templateId, { assignments: [...kept, ...this.copyPlan(song)] })).data;
            } catch (error) {
                toast(refusal(error), { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },
        /** Saves who sings which part of a song: the whole list for that song. */
        async assign(song, member, partKey) {
            const assignments = this.singers
                .map((m) => ({ memberId: m.id, partKey: m.id === member.id ? partKey : this.assignmentOf(m, song)?.partKey ?? '' }))
                .filter((a) => a.partKey);
            try {
                this.group = (await api.groups.assignments.update(this.id, song.templateId, { assignments })).data;
                if (partKey) first('grp_assigned');
            } catch (error) {
                toast(refusal(error), { tone: 'error' });
            }
        },
        /** The song on your part: your own copy once you've begun it, else a new one; on to Sing. */
        async learn(song) {
            this.starting = song.templateId;
            try {
                const mine = this.myPart(song);
                navigate(`/p/${await learnGroupSong({ templateId: song.templateId, partKey: mine?.partKey ?? null, status: mine?.status ?? 'none', template: this.templates[song.templateId] ?? null })}/sing`);
            } catch (error) {
                console.error('The group song did not start', error);
                toast(t('projects.couldNotStart'), { tone: 'error' });
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
        /** The group's link as sent: tagged with where it came from (a source, never who). */
        sentLink() {
            const url = new URL(this.shownLink);
            url.searchParams.set('ref', shareRef('group'));
            return url.toString();
        },
        async copy() {
            first('grp_link');
            countShare('group', 'copy');
            try {
                await navigator.clipboard.writeText(this.sentLink());
                toast(t('share.copied'));
            } catch {
                toast(t('share.copyFailed'), { tone: 'error' });
            }
        },
        share() {
            // Closing the share sheet rejects too: nothing to say.
            first('grp_link');
            countShare('group', 'sheet');
            navigator.share({ title: this.group.name, url: this.sentLink() }).catch((error) => console.info('Not shared', error.name));
        },
        longDate(date) {
            return new Intl.DateTimeFormat(locale.lang, { weekday: 'long', day: 'numeric', month: 'long' }).format(date);
        },
        shortDate(date) {
            return new Intl.DateTimeFormat(locale.lang, { weekday: 'short', day: 'numeric', month: 'short' }).format(date);
        },
        today: () => isoDate(new Date()),
        /** In the three days before the next rehearsal, a part not yet recorded says so, plainly: "2 days to rehearsal". */
        soon(assignment) {
            const next = this.rehearsalNext;
            if (!next || !assignment || assignment.status === 'recorded') return '';
            const days = daysUntil(next);
            return days >= 0 && days <= 3 ? t('groups.rehearsal.inDays', { days }) : '';
        },
        openRehearsal() {
            const r = this.group.rehearsal;
            this.rehearsalDate = this.rehearsalNext ? isoDate(this.rehearsalNext) : '';
            this.rehearsalWeekly = r ? !!r.weekly : true;
            this.rehearsalError = null;
            this.settingRehearsal = true;
        },
        async saveRehearsal() {
            await this.setRehearsal({ date: this.rehearsalDate, weekly: this.rehearsalWeekly });
        },
        async clearRehearsal() {
            await this.setRehearsal(null);
        },
        async setRehearsal(rehearsal) {
            this.busy = true;
            this.rehearsalError = null;
            try {
                this.group = (await api.groups.update(this.id, { rehearsal })).data;
                this.settingRehearsal = false;
            } catch (error) {
                this.rehearsalError = error?.code === 'validation_failed' ? (error.errors?.['rehearsal.date'] ?? [t('groups.rehearsal.invalid')])[0] : refusal(error);
            } finally {
                this.busy = false;
            }
        },
        /** Ask for learning tracks: the form, fresh each time it opens (what was typed stays until it's sent). */
        openTracks() {
            this.tracksSent = false;
            this.tracksError = null;
            this.tracksErrors = null;
            this.asking = true;
        },
        async sendTracks() {
            this.busy = true;
            this.tracksError = null;
            this.tracksErrors = null;
            const f = this.tracks;
            const trimmed = (v) => v.trim() || undefined;
            try {
                const scoreIds = this.scores.filter((s) => s.status === 'ready').map((s) => s.id);
                await api.groups.tracks.create(this.id, { piece: f.piece.trim(), composer: trimmed(f.composer), voicing: trimmed(f.voicing), note: trimmed(f.note), rights: f.rights, rightsConfirmed: true, scoreIds });
                this.tracksSent = f.piece.trim();
                this.tracks = { piece: '', composer: '', voicing: '', note: '', rights: '' };
                this.scores = [];
                this.loadRequests();
            } catch (error) {
                if (error?.code === 'validation_failed' && error.errors) this.tracksErrors = Object.fromEntries(Object.entries(error.errors).map(([k, v]) => [k === 'rightsConfirmed' ? 'rights' : k.startsWith('scoreIds') ? 'scoreIds' : k, Array.isArray(v) ? v[0] : v]));
                else if (error?.code === 'rate_limited') this.tracksError = t('groups.tracks.tooMany');
                else this.tracksError = refusal(error);
            } finally {
                this.busy = false;
            }
        },
        async openShare() {
            this.sharing = true;
            this.pick = '';
            await loadMine().catch((error) => console.warn('Your songs did not load', error));
            const shared = new Set(this.group.songs.map((x) => x.templateId));
            this.pick = [...mine.list].filter((r) => r.state === 'saved' && !shared.has(r.id)).sort((a, b) => (b.updatedAt ?? 0) - (a.updatedAt ?? 0))[0]?.id ?? '';
            this.songs = (await listTemplates().catch(() => [])).map((s) => ({ id: s.id, title: s.title }));
        },
        async shareSong() {
            this.busy = true;
            try {
                this.group = (await api.groups.songs.create(this.id, { templateId: this.pick })).data;
                this.sharing = false;
                // Straight on to who sings what, when there's anyone to give a part to.
                if (this.singers.length) this.editing = this.pick;
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
    components: { InviteAccept, InviteCard },
    props: { token: { type: String, required: true } },
    // `back`: arrived back from this group's own link after signing up or in (?join=1, which the link's own sign-in sets), so
    // the join goes ahead at once; from anywhere else, never (the coordinator, 2026-11-08).
    data: () => ({ group: null, problem: null, joining: false, tooYoung: false, back: new URLSearchParams(location.search).get('join') === '1' }),
    setup: () => ({ account, navigate }),
    template: `
        <InviteCard :part="null" :from="group?.director.name ?? ''" :kind="group ? t('groups.joinKind', { name: group.name, count: group.members }) : ''" :lead="group ? t('groups.joinLead', { name: group.name }) : ''" :sign-in="'/signin?next=' + encodeURIComponent('/choir/' + token + '?join=1')">
            <template v-if="problem || !group" #top>
                <p v-if="problem" class="text-lg text-cream" role="alert">{{ problem }}</p>
                <p v-else class="text-muted" role="status">{{ t('create.loading') }}</p>
                <button v-if="problem" type="button" class="btn-secondary" @click="navigate('/')">{{ t('common.backToProjects') }}</button>
            </template>
            <template v-if="group && !problem">
                <!-- Under 18 by the account's own birth date: said plainly and kindly, never a tick to claim otherwise (groups are 18 and over). -->
                <template v-if="account.me && tooYoung">
                    <p class="text-cream" role="status" data-too-young>{{ t('groups.tooYoung') }}</p>
                    <a href="/" class="btn-primary btn-lg w-full" @click.prevent="navigate('/')">{{ t('groups.startSinging') }}</a>
                </template>
                <button v-else-if="account.me" type="button" class="btn-primary btn-lg w-full" :disabled="joining" @click="join">{{ joining ? t('join.joining') : t('groups.join') }}</button>
                <InviteAccept v-else-if="account.loaded" :next="'/choir/' + token + '?join=1'" />
            </template>
        </InviteCard>`,
    async created() {
        try {
            this.group = (await api.groups.join.show(this.token)).data;
        } catch (error) {
            this.problem = error?.code === 'not_found' ? t('groups.linkGone') : refusal(error);
        }
        this.joinOnReturn();
    },
    watch: {
        'account.me.id'() {
            this.joinOnReturn();
        },
    },
    methods: {
        /** Back from this link's own sign-up or sign-in: joined at once, once, the address made plain. */
        joinOnReturn() {
            if (!this.back || !this.group || !account.me?.id || this.joining) return;
            this.back = false;
            history.replaceState(history.state, '', `/choir/${this.token}`);
            this.join();
        },
        async join() {
            this.joining = true;
            try {
                // 18 and over is the account's birth date's to say (the server checks it): with none yet, it's asked first (askBirth).
                const { data } = await api.groups.join(this.token, account.me?.birthGiven ? { adult: true } : {});
                first('grp_joined');
                tried('together');
                toast(t('groups.joinedToast', { name: data.name }));
                navigate(`/groups/${data.id}`, { replace: true });
            } catch (error) {
                if ((error?.code === 'adult_required' || error?.code === 'age_unconfirmed') && !account.me?.birthGiven) {
                    this.joining = false;
                    if (await askBirth()) return this.join();
                } else if (error?.code === 'adult_required') this.tooYoung = true; // their birth date says under 18
                else this.problem = refusal(error);
            } finally {
                this.joining = false;
            }
        },
    },
};
