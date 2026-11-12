// The home (docs/decisions.md, "the home page", direction C: the stack). The course leads; under
// it, bands in the order js/home.js gives: beta parts or the workout, Your songs (New song first,
// each card with Play and a More menu), then the song library (Preview and Info on each, a tap
// starts it). On a wide screen, the week and level, and the install card, sit at the side.

import { closeMic, engine, unlock } from '../audio.js';
import { CreditLine } from '../components/credit.js';
import { api } from '../api.js';
import { StorageMeter } from '../components/cloud.js';
import { formatNumber, locale, t, timeAgo, tk } from '../i18n.js';
import { renderGuide } from '../guides.js';
import { partVoice } from '../guide-voice.js';
import { publicSiteUrl } from '../hosts.js';
import { navigate } from '../router.js';
import { betaWaiting, homeBands, sangThisWeek } from '../home.js';
import { creditsFor, loadCatalogue } from '../stores/catalogue.js';
import { toRaw } from 'vue';
import { createProject, deleteProject, loadProjects, projects, saveProject, shownTitle, startFromTemplate } from '../stores/projects.js';
import { ideas, ideasOpen } from '../stores/ideas.js';
import { learning } from '../stores/learning.js';
import { account, loadAccount } from '../stores/account.js';
import { canStart, lengthBeats, listTemplates, loadTemplate, onDraftsChange, partsFor, singerOptions, templatePartName } from '../templates.js';
import { formatTime, keyLabel, PART_COLORS } from '../theme.js';
import { HomeBand, HomeCourses, HomeLead, SongCover, WeekCard, WorkoutStrip } from '../components/home.js';
import { PlayerChip } from '../game/title-screen.js';
import { dailyState, lazyComponent, loadDaily } from '../game/modules.js';

const DailyTag = lazyComponent('daily', 'DailyTag'); // game mode's only: loaded with game mode
import { effect } from '../game/effects.js';
import { dailyOpen, loadPick } from '../daily-pick.js';
import { game, sk } from '../skin.js';
import { inFirstWeek } from '../components/voice-care.js';
import { InstallCard } from '../components/install.js';
import { signUpFor } from '../components/account-wall.js';
import { FirstWeek, RemindAsk } from '../components/session-wrap.js';
import { BETA_STATUS } from '../components/beta.js';
import { Notice, StatusChip } from '../components/form.js';
import { Modal } from '../components/ui.js';
import { ThemeSwitch } from '../components/appearance.js';
import { readImport } from '../creator.js';
import { session as tabStorage } from '../storage.js';
import { deleteTemplate, discardDraft, loadMine, mine, saveTemplate } from '../stores/templates.js';
import { SheetImport } from '../components/create/sheet-import.js';
import { readSheetFile } from '../sheet-import.js';
import { count, tried } from '../usage.js';
import { lastLooked, lookedNow, newsPick } from '../song-news.js';
import { leftProject, loadShared } from '../stores/cloud.js';
import { friendlyProblem } from '../components/create/save.js';
import { TemplateDialog } from '../components/dialogs.js';
import { Icon, Logo } from '../components/icon.js';
import { toast } from '../components/ui.js';
import { learnGroupSong, myGroupParts } from '../group-songs.js';
import { daysUntil, rehearsalDates } from '../rehearsal.js';
import { confirmAction, deleteSong, UNDO_SECONDS } from '../components/confirm.js';
import { CardMenu } from '../components/card-menu.js';
import * as sessionStore from '../stores/session.js';
import { session } from '../stores/session.js';

const DIFFICULTY = { beginner: tk('templates.difficulty.beginner'), intermediate: tk('templates.difficulty.intermediate'), advanced: tk('templates.difficulty.advanced') };
// The bands' marks are the accent: nothing on the home that isn't a part wears a part's colour (Hillel's call).
const BAND_COLORS = { beta: 'var(--color-accent)', projects: 'var(--color-accent)', songs: 'var(--color-accent)', workout: 'var(--color-accent)' };
const PHONE_SONGS = 6; // cards in a phone's sideways band before See all
/** The songs grid's columns (as its classes: 3 from md, 4 from xl), or 0 on a phone, where the band scrolls. */
// Hillel: three songs a row from a tablet up (Your songs and the library alike); a phone's band scrolls.
const songColumns = () => (window.matchMedia('(min-width: 1024px)').matches ? 4 : window.matchMedia('(min-width: 768px)').matches ? 3 : 0);
// Learn a harmony's filters (42.png): every song, yours, drafts (not yet checked), on Plus.
// The library is the catalogue only (docs/home-create-plan.md): your own songs and drafts are in Your songs.

/** A draft's template when it has a tune to play (a tempo and notes in a part), else null. */
const playableDraft = (draft) => (draft.template?.tempo && draft.template.parts?.some((p) => p.guide?.notes?.length) ? draft.template : null);

// A collection in season, said on its pick (song-news.js): its words by the server's season key.
const SEASON_WORDS = { carols: tk('home.songs.news.carols') };

export const ProjectsView = {
    components: { StorageMeter, CreditLine, SheetImport, PlayerChip, DailyTag, CardMenu, HomeCourses, ThemeSwitch, Icon, Logo, TemplateDialog, HomeLead, HomeBand, SongCover, InstallCard, WeekCard, WorkoutStrip, StatusChip, Modal, Notice, RemindAsk, FirstWeek },
    setup: () => ({ templatePartName, ideas, session, mine, friendlyProblem, canStart, projects, account, learning, shownTitle, formatTime, timeAgo, PART_COLORS, lengthBeats, partsFor, keyLabel, DIFFICULTY, creditsFor, navigate, BAND_COLORS, BETA_STATUS, publicSiteUrl, effect, dailyState }),
    data: () => ({ storageUsage: null, locked: false, clip: null, templates: [], openTemplate: null, singers: 4, previewing: false, preparingPreview: false, starting: false, groups: null, choirParts: [], startingChoir: null, betaParts: null, avatarFailed: false, making: false, importProblems: null, sheetScore: null, sheetSaving: false, songsOpen: tabStorage.get('stackapella.songsOpen') === '1', lookedAt: lastLooked(), newsCounted: null, yoursOpen: false, songColumns: songColumns(), newing: false, showInfo: false, previewOf: null, playingSong: null, renaming: null, newName: '' }),
    template: `
        <div class="min-h-dvh">
            <!-- The app's top bar on the site header's grid (app-wrap app-top): the logo where the site's is. -->
            <header class="app-wrap app-top justify-between">
                <!-- The logo goes to the public site's landing page (Hillel's call); this page is the home. -->
                <a :href="publicSiteUrl()" class="-m-2 flex rounded-xl p-2" :aria-label="t('home.toSite')"><Logo /></a>
                <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                <ThemeSwitch v-if="account.loaded" />
                <a v-if="account.me" href="/settings" data-ui="player" class="flex min-w-0 items-center gap-2 rounded-full glass-bubble p-1 text-sm font-semibold text-cream hover:bg-raised sm:pe-3" :aria-label="plus ? t('home.accountLinkPlus', { name: account.me.name }) : t('home.accountLink', { name: account.me.name })" @click.prevent="navigate('/settings')">
                    <img v-if="account.me.avatarUrl && !avatarFailed" :src="account.me.avatarUrl" alt="" class="size-7 rounded-full object-cover" data-avatar @error="avatarFailed = true" />
                    <span v-else class="bubble bubble-sm grid size-8 place-items-center text-[11px] font-extrabold" aria-hidden="true" data-avatar>{{ initials }}</span>
                    <span class="hidden max-w-32 truncate sm:inline" translate="no">{{ account.me.name }}</span>
                    <!-- Game mode's title screen: you are P1, at your level (docs/game-elements-plan.md) -->
                    <PlayerChip v-if="effect('title') && learning.loaded && courseStarted" />
                    <span v-if="plus" class="chip-accent hidden sm:inline-flex">{{ t('home.plus') }}</span>
                </a>
                <!-- Hillel's way into admin: only when the live account.show says this account passed the full admin
                     gate (its admin field; editors and everyone else get no trace of it). The server guards /admin. -->
                <a v-if="account.me?.admin === true" href="/admin" class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-full px-2 text-sm text-muted hover:text-cream" :aria-label="t('home.admin')" :title="t('home.admin')" data-admin-link @click.prevent="navigate('/admin')"><Icon name="shield" :size="16" /><span class="hidden sm:inline">{{ t('home.admin') }}</span></a>
                <template v-if="!account.me && account.loaded">
                    <a href="/settings#look" class="hidden rounded-full p-1.5 text-muted hover:text-cream sm:block" :aria-label="t('appearance.title')" :title="t('appearance.title')" @click.prevent="navigate('/settings')"><Icon name="sliders" :size="18" /></a>
                    <a href="/signin" class="link-quiet inline-flex min-h-11 items-center text-sm" @click.prevent="navigate('/signin')">{{ t('auth.signIn') }}</a>
                </template>
                </div>
            </header>
            <!-- The content on the same row as the bar and the site's header (app-wrap, in px), so the logo, the controls and
                 the cards share their edges at any width and any browser text size (Hillel, 2026-11-09: "the whole header is
                 narrower"; a 72rem column grew past the 1200 px bar with Chrome's text set larger). -->
            <div class="app-wrap pb-16 pt-4">

                        <!-- Hillel's Home design: the course card, the workout and your songs share one width, so their edges line up. -->
            <div class="mt-2 space-y-5">
                <HomeLead :compact="order.compactCourse">
                    <template #week><WeekCard v-if="learning.loaded && courseStarted" bare /></template>
                </HomeLead>
                <InstallCard :songs="songsOnlyHere" :here="songsHere" />
                <RemindAsk />
                <FirstWeek :songs="songsOnlyHere" />
            </div>
            <div class="mt-10 space-y-10">
                <div class="min-w-0 space-y-10">

                    <!-- A choir member's parts to learn, first (docs/agents/handoff-frontend2.md, the groups review): only for someone given a part in a group, so nobody else's Home changes. -->
                    <HomeBand v-if="choirParts.length" id="band-choir" tone="green" :title="t('groups.learnTitle')" :color="paint(BAND_COLORS.beta)">
                        <template #aside><a href="/groups" class="btn-go" @click.prevent="navigate('/groups')">{{ t('home.seeAll') }}</a></template>
                        <ul class="card divide-y divide-line px-4" data-ui="rows">
                            <li v-for="c in choirParts" :key="c.groupId + c.templateId" class="flex flex-wrap items-center gap-3 py-3" data-choir-part>
                                <a :href="'/groups/' + c.groupId" class="min-w-0 flex-1" @click.prevent="navigate('/groups/' + c.groupId)">
                                    <span class="block truncate font-semibold text-cream" translate="no">{{ c.title }}</span>
                                    <span class="block truncate text-sm text-muted">{{ templatePartName({ key: c.partKey, name: c.partKey }) }} · <span :class="c.status === 'started' ? 'text-warn' : ''">{{ t(c.status === 'started' ? 'groups.status.started' : 'groups.status.none') }}</span> · <span translate="no">{{ c.groupName }}</span><span v-if="choirSoon(c)" data-rehearsal-soon> · {{ choirSoon(c) }}</span></span>
                                </a>
                                <button type="button" class="btn-secondary btn-sm" :disabled="startingChoir === c.templateId" @click="learnChoirPart(c)">{{ c.status === 'started' ? t('groups.keepLearning') : t('groups.learnPart') }}</button>
                            </li>
                        </ul>
                    </HomeBand>
                    <!-- Someone in a group with nothing to learn yet (no part given, or a director): their choir sits here too, above the library, not at the foot of Home (the choir singer's first-run walk). Nobody outside a group sees either band. -->
                    <HomeBand v-if="groups?.length && !choirParts.length" id="band-groups" tone="green" :title="t('groups.homeTitle')" :color="paint(BAND_COLORS.beta)">
                        <template #aside><a href="/groups" class="btn-go" @click.prevent="navigate('/groups')">{{ t('home.seeAll') }}</a></template>
                        <ul class="card divide-y divide-line px-4" data-ui="rows">
                            <li v-for="(g, i) in groups" :key="g.id">
                                <a :href="'/groups/' + g.id" class="flex items-center gap-3 py-3" @click.prevent="navigate('/groups/' + g.id)">
                                    <!-- The arcade screen: each choir in a player slot (P1, P2, ...), a look only -->
                                    <span v-if="effect('arcade')" class="gs-slot hidden" :data-slot="(i % 4) + 1" aria-hidden="true">{{ t('game.arcade.slot', { number: i + 1 }) }}</span>
                                    <span class="min-w-0 flex-1 truncate font-semibold text-cream" translate="no">{{ g.name }}</span>
                                    <span class="text-sm text-muted" data-ui="rows-count">{{ t('groups.songCount', { count: g.songs }) }}</span>
                                    <Icon name="arrow" :size="18" class="text-muted" />
                                </a>
                            </li>
                        </ul>
                    </HomeBand>

                    <template v-for="band in order.bands" :key="band">
                        <HomeBand v-if="band === 'beta' && betaParts?.length" id="band-beta" :title="t('projects.betaParts')" :color="paint(BAND_COLORS.beta)">
                            <template #aside><a href="/beta" class="btn-go" @click.prevent="navigate('/beta')">{{ t('home.seeAll') }}</a></template>
                            <ul class="card divide-y divide-line px-4">
                                <li v-for="part in betaShown" :key="part.id">
                                    <a href="/beta" class="flex items-center gap-3 py-3" @click.prevent="navigate('/beta')">
                                        <span class="size-2.5 shrink-0 rounded-full" :style="{ background: paint(BAND_COLORS.beta) }" aria-hidden="true"></span>
                                        <span class="min-w-0 flex-1 truncate font-semibold text-cream"><template v-if="part.clip"><span translate="no">{{ part.clip.courseTitle }}</span></template><template v-else><span translate="no">{{ part.template.title }}</span>: {{ partName(part) }}</template></span>
                                        <StatusChip :tone="BETA_STATUS[part.status]?.tone ?? 'muted'" :label="BETA_STATUS[part.status] ? t(BETA_STATUS[part.status].label) : part.status" />
                                    </a>
                                </li>
                            </ul>
                        </HomeBand>

                        <WorkoutStrip v-else-if="band === 'workout' && learning.loaded && courseStarted" :compact="trimmed" />
                        <HomeBand v-else-if="band === 'workout'" id="band-workout" :title="t('workout.title')" :color="paint(BAND_COLORS.workout)">
                            <a href="/workout" class="card flex items-center gap-4 p-4 hover:bg-raised/40" @click.prevent="navigate('/workout')">
                                <span class="grid size-12 shrink-0 place-items-center rounded-2xl" :style="{ color: 'var(--color-accent-ink)', background: paint(BAND_COLORS.workout) }"><Icon name="timing" :size="24" /></span>
                                <span class="min-w-0 flex-1 text-sm text-muted">{{ t('workout.card') }}</span>
                                <Icon name="arrow" :size="18" class="text-muted" />
                            </a>
                        </HomeBand>

                        <HomeBand v-else-if="band === 'songs' && templates.length" id="band-songs" tone="yellow" :title="firstVisit ? t('home.songs.first') : t('home.songs.library')" :count="trimmed && !songsOpen ? '' : t('home.songs.count', { count: allSongs.length })" :color="paint(BAND_COLORS.songs)">
                            <template #aside>
                                <!-- Hillel (2026-10-30): after the first week, three picked for your range and Browse all; the filter only while browsing. -->
                                <button v-if="trimmed && !songsOpen" type="button" class="btn-go" aria-controls="home-songs" :aria-expanded="'false'" data-browse-all @click="openSongs(true)">{{ t('home.songs.browseAllCount', { count: allSongs.length }) }}</button>
                            </template>
                            <ul id="home-songs" class="gap-3" :class="(songsOpen ? 'grid grid-cols-2 md:grid-cols-3' : '-mx-4 flex snap-x scroll-px-4 overflow-x-auto px-4 [scrollbar-width:none] md:mx-0 md:grid md:grid-cols-3 md:overflow-visible md:px-0') + (trimmed ? '' : ' lg:grid-cols-4')">
                                <li v-for="item in shownSongs" :key="item.key" class="group relative" :class="songsOpen ? '' : 'w-40 shrink-0 snap-start md:w-auto'" data-song-card :data-daily="dailyShown && dailyState().today?.templateId === item.tpl.id ? '' : null">
                                    <!-- Game mode's daily challenge: today's song card wears its tag (docs/game-elements-plan.md) -->
                                    <DailyTag v-if="dailyShown && dailyState().today?.templateId === item.tpl.id" :challenge="dailyState().today" />
                                    <button type="button" class="flex w-full flex-col gap-2 rounded-3xl p-1 text-start hover:bg-raised/50" data-ui="cartridge" @click="songTap(item)">
                                        <span class="relative block">
                                            <SongCover :song="item.tpl" :voices="item.tpl.parts.map((p) => p.key)" :seed="seedOf(item.tpl.id)" />
                                            <!-- The tags at the top left; the top right is the preview's Play. -->
                                            <span class="absolute end-14 start-3 top-3 flex flex-wrap gap-1">
                                                <!-- New, or Carol season (retention plan, idea 5): one pick at most, in the singer's range. -->
                                                <span v-if="!songsOpen && news?.item === item" data-ui="tag" data-tone="yellow" class="rounded-full bg-raised px-2 py-0.5 text-[10px] font-bold text-cream" data-news-tag :data-news="news.kind">{{ newsWords }}</span>
                                                <span v-if="creditsFor(item.tpl.id).length" data-ui="tag" data-tone="cyan" class="rounded-full bg-raised px-2 py-0.5 text-[10px] font-bold text-accent-strong">{{ t('home.songs.realVoices') }}</span>
                                                <span v-else-if="!item.tpl.reviewed" data-ui="tag" data-tone="pink" class="rounded-full bg-raised px-2 py-0.5 text-[10px] text-muted">{{ t('projects.draft') }}</span>
                                            </span>
                                        </span>
                                        <span class="px-1 font-bold leading-tight text-cream" translate="no" data-ui="cartridge-title">{{ item.tpl.title }}</span>
                                        <span class="px-1 text-xs text-muted" :aria-label="singerText(item.tpl) + ' · ' + t(DIFFICULTY[item.tpl.difficulty] ?? DIFFICULTY.beginner)"><span aria-hidden="true">{{ singerCount(item.tpl) }} · {{ t(DIFFICULTY[item.tpl.difficulty] ?? DIFFICULTY.beginner) }}</span></span>
                                    </button>
                                    <!-- Hillel: the preview's Play at the picture's top right, on hover (always on touch); a tap on the card still opens it. -->
                                    <button type="button" class="card-tools tap-44 absolute end-3 top-3 grid size-9 place-items-center rounded-full glass-bubble" :class="songOn(item.tpl.id) ? 'is-on bg-accent text-accent-ink' : 'bg-panel text-cream'" :aria-label="songOn(item.tpl.id) ? t('home.songs.stopNamed', { title: item.tpl.title }) : t('home.yours.playNamed', { title: item.tpl.title })" :aria-pressed="songOn(item.tpl.id) ? 'true' : 'false'" data-song-play @click="playLibrary(item.tpl)"><Icon :name="songOn(item.tpl.id) ? 'stop' : 'play'" :size="16" /></button>
                                </li>
                                <li v-if="!trimmed && !songsOpen && hiddenSongs" class="w-40 shrink-0 snap-start md:w-auto">
                                    <button type="button" class="flex w-full flex-col gap-2 rounded-3xl p-1 text-start hover:bg-raised/50" aria-controls="home-songs" :aria-expanded="'false'" data-browse-all @click="openSongs(true)">
                                        <span class="flex h-28 items-center justify-center gap-2 rounded-2xl border border-edge bg-panel px-3 text-center font-bold text-cream">{{ t('home.songs.browseAll') }} <Icon name="arrow" :size="18" /></span>
                                        <span class="px-1 text-xs text-muted">{{ t('home.songs.more', { count: hiddenSongs }) }}</span>
                                    </button>
                                </li>
                            </ul>
                            <!-- The end of the full library: ask for the song that isn't here (song requests, N7; while it's on, and staff before). -->
                            <p v-if="songsOpen && requestsOn" class="mt-4 text-center text-sm text-muted" data-request-song>{{ t('requests.notHere') }} <a href="/requests?ask" class="link tap-44" @click.prevent="navigate('/requests?ask')">{{ t('requests.askLink') }}</a></p>
                            <div v-if="songsOpen" class="mt-3 flex justify-center">
                                <button type="button" class="btn-secondary btn-sm" aria-controls="home-songs" :aria-expanded="songsOpen ? 'true' : 'false'" @click="openSongs(!songsOpen)">{{ t('home.songs.showFewer') }}</button>
                            </div>
                        </HomeBand>

                        <HomeBand v-else-if="band === 'projects'" id="band-projects" :title="t('projects.title')" :color="paint(BAND_COLORS.projects)">
                            <!-- The free plan's limit is storage (Hillel, 2026-11-07): its meter here once it's 80% full, with the way to Plus. -->
                            <StorageMeter v-if="storageNear" :usage="storageUsage" link class="mb-4" data-storage-meter />
                            <ul class="-mx-4 flex snap-x scroll-px-4 gap-3 overflow-x-auto px-4 [scrollbar-width:none] md:mx-0 md:grid md:grid-cols-3 md:overflow-visible md:px-0" :class="trimmed ? '' : 'lg:grid-cols-4'">
                                <!-- Hillel: the first song is a blank one with a plus (New song), then yours (after the first week, the two latest, and See all). -->
                                <li class="w-40 shrink-0 snap-start md:w-auto">
                                    <!-- Hillel's design: "+ New song" in a dashed card, how to start under it. -->
                                    <button type="button" class="flex w-full flex-col gap-2 rounded-3xl p-1 text-start hover:bg-raised/50" data-new-song @click="newing = true">
                                        <span class="flex h-28 items-center justify-center gap-3 rounded-2xl border-[1.5px] border-dashed border-line font-bold text-cream"><span class="bubble bubble-sm grid size-8 place-items-center" style="--bubble: var(--primary); --bubble-ink: var(--primary-ink)" aria-hidden="true" data-ui="plus-disc"><Icon name="plus" :size="18" /></span>{{ t('projects.new') }}</span>
                                        <span class="px-1 text-xs text-muted">{{ t('home.yours.newHint') }}</span>
                                    </button>
                                </li>
                                <li v-for="c in shownYours" :key="c.key" class="group relative w-40 shrink-0 snap-start md:w-auto" :data-kind="c.kind">
                                    <!-- Hillel: every song of yours looks the same, like a library song (the picture, its title and details underneath); only its tag differs. A tap opens it. -->
                                    <button type="button" class="flex w-full flex-col gap-2 rounded-3xl p-1 text-start hover:bg-raised/50" data-ui="cartridge" :aria-label="t('projects.open', { title: c.title })" @click="c.open()">
                                        <span class="relative block">
                                            <SongCover :class="c.kind === 'draft' ? 'border-dashed' : ''" :song="c.song" :parts="c.parts" :mine="c.mine" :seed="seedOf(c.key)" />
                                            <span v-if="c.tag" data-ui="tag" :data-tone="c.warn ? 'yellow' : 'cyan'" class="absolute start-3 top-3 rounded-full bg-raised px-2 py-0.5 text-[10px] font-bold" :class="c.warn ? 'text-warn' : 'text-accent-strong'" translate="no">{{ c.tag }}</span>
                                        </span>
                                        <span class="truncate pe-9 ps-1 font-bold leading-tight" data-ui="cartridge-title" :class="c.named ? 'text-cream' : 'italic text-muted'" :translate="c.named ? 'no' : null">{{ c.title }}</span>
                                        <span class="px-1 text-xs text-muted" :class="c.mine ? '' : 'truncate'" :data-parts-you="c.mine ? '' : null">{{ c.meta }}</span>
                                    </button>
                                    <button type="button" class="card-tools tap-44 absolute end-3 top-3 grid size-9 place-items-center rounded-full glass-bubble disabled:opacity-50" :class="songOn(c.key) ? 'is-on bg-accent text-accent-ink' : 'bg-panel text-cream'" :disabled="!c.play" :title="c.play ? null : c.why" :aria-label="!c.play ? c.why : songOn(c.key) ? t('home.songs.stopNamed', { title: c.title }) : t('home.yours.playNamed', { title: c.title })" :aria-pressed="songOn(c.key) ? 'true' : 'false'" data-song-play @click="c.play && c.play()"><Icon :name="songOn(c.key) ? 'stop' : 'play'" :size="16" /></button>
                                    <CardMenu class="card-tools absolute end-0 top-[7.1rem]" :label="t('home.yours.moreNamed', { title: c.title })" :items="c.menu" />
                                </li>
                            </ul>
                            <p v-if="!yourSongs.length" class="mt-3 text-sm text-muted">{{ t('home.yours.empty') }}</p>
                            <div v-if="trimmed && yourCards.length > 2" class="mt-3 flex justify-center">
                                <button type="button" class="btn-secondary btn-sm" :aria-expanded="yoursOpen ? 'true' : 'false'" data-yours-all @click="yoursOpen = !yoursOpen">{{ yoursOpen ? t('home.yours.showFewer') : t('home.yours.seeAll', { count: yourCards.length }) }}</button>
                            </div>
                        </HomeBand>
                    </template>

                    <!-- Hillel: the Plus courses at the bottom of Home, below the song library; not in a new singer's first week. -->
                    <HomeCourses v-if="!firstWeek" />
                </div>

                <aside class="space-y-4">
                </aside>
            </div>

            <TemplateDialog v-if="openTemplate && showInfo" :template="openTemplate" :credits="creditsFor(openTemplate.id)" :singers="singers" @singers="(n) => { singers = n; if (previewing && !locked) previewLevels(); }"
                :previewing="previewing" :preparing="preparingPreview" @copy="navigate('/create?copy=' + openTemplate.id)" @close="closeTemplate" @preview="preview" @start="start()" :locked="locked" @signup="navigate(signUpForSong)" />
            <Modal v-if="renaming" :title="t('home.yours.renameTitle')" @close="renaming = null">
                <form class="space-y-4" @submit.prevent="saveName">
                    <label class="block space-y-1"><span class="text-sm font-semibold text-cream">{{ t('home.yours.nameLabel') }}</span>
                        <input v-model="newName" type="text" maxlength="80" class="input w-full" data-autofocus /></label>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn-ghost" @click="renaming = null">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary" :disabled="!newName.trim()">{{ t('common.save') }}</button>
                    </div>
                </form>
            </Modal>
            <Modal v-if="newing" :title="t('projects.new')" @close="newing = false">
                <div class="space-y-3" data-new-sheet>
                    <button type="button" class="card-interactive flex w-full items-start gap-3 p-4 text-start" @click="makeOne">
                        <span class="bubble grid size-10 shrink-0 place-items-center"><Icon name="sparkle" :size="20" /></span>
                        <span><span class="block font-bold text-cream">{{ t('home.new.make') }}</span><span class="text-sm text-muted">{{ t('home.new.makeHint') }}</span></span>
                    </button>
                    <button type="button" class="card-interactive flex w-full items-start gap-3 p-4 text-start" @click="newProject">
                        <span class="bubble grid size-10 shrink-0 place-items-center"><Icon name="mic" :size="20" /></span>
                        <span><span class="block font-bold text-cream">{{ t('home.new.record') }}</span><span class="text-sm text-muted">{{ t('home.new.recordHint') }}</span></span>
                    </button>
                    <!-- Ideas (docs/ideas-app-plan.md), once open: someone's tune, waiting for your voice -->
                    <a v-if="ideas.open" href="/ideas" class="card-interactive flex w-full items-start gap-3 p-4 text-start" data-new-ideas @click.prevent="navigate('/ideas')">
                        <span class="bubble grid size-10 shrink-0 place-items-center"><Icon name="note" :size="20" /></span>
                        <span><span class="block font-bold text-cream">{{ t('home.new.ideas') }}</span><span class="text-sm text-muted">{{ t('home.new.ideasHint') }}</span></span>
                    </a>
                </div>
            </Modal>
            <!-- Make your own (Hillel's 93.png): each way in a card with its own tinted icon and a chevron; the same list, tighter. -->
            <Modal v-if="making" :title="t('create.home.make')" :subtitle="t('create.home.makeHint')" @close="making = false">
                <div class="space-y-2.5">
                    <button v-for="way in makeWays" :key="way.mode" type="button" class="card-interactive flex min-h-11 w-full items-center gap-3 p-4 text-start" data-make-option :data-new-hum="way.mode === 'hum' ? '' : null" @click="navigate('/create?mode=' + way.mode)">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-accent/10 text-accent" aria-hidden="true" data-make-icon><Icon :name="way.icon" :size="20" /></span>
                        <span class="min-w-0 flex-1"><span class="block font-bold text-cream" data-ui="option-title">{{ t(way.title) }}</span><span class="block text-sm text-muted">{{ t(way.hint) }}</span></span>
                        <Icon name="chevron" :size="18" class="shrink-0 -rotate-90 text-muted rtl:rotate-90" aria-hidden="true" />
                    </button>
                    <label class="card-interactive flex min-h-11 w-full cursor-pointer items-center gap-3 p-4 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-(--ring)" data-make-option>
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-accent/10 text-accent" aria-hidden="true" data-make-icon><Icon name="arrowDown" :size="20" /></span>
                        <span class="min-w-0 flex-1"><span class="block font-bold text-cream" data-ui="option-title">{{ t('create.home.import') }}</span><span class="block text-sm text-muted">{{ sheetMusic ? t('create.home.importHintSheet') : t('create.home.importHint') }}</span></span>
                        <Icon name="chevron" :size="18" class="shrink-0 -rotate-90 text-muted rtl:rotate-90" aria-hidden="true" />
                        <!-- Sheet music too (docs/sheet-music-plan.md), after the first week -->
                        <input type="file" :accept="sheetMusic ? '.json,application/json,.musicxml,.mxl,.xml,.mid,.midi,application/vnd.recordare.musicxml+xml,application/vnd.recordare.musicxml,audio/midi' : '.json,application/json'" class="sr-only" data-open-file @change="importFile($event.target.files[0]); $event.target.value = ''" />
                    </label>
                    <div v-if="importProblems" class="space-y-2">
                        <Notice tone="warn">
                            <p>{{ t('create.home.importBroken') }}</p>
                            <ul class="mt-1 list-disc ps-5 text-sm"><li v-for="(p, i) in importProblems.problems.slice(0, 5)" :key="i">{{ friendlyProblem(p) }}</li></ul>
                        </Notice>
                        <button type="button" class="btn-secondary" @click="openBroken">{{ t('create.home.openAnyway') }}</button>
                    </div>
                </div>
            </Modal>
            <SheetImport v-if="sheetScore" :score="sheetScore" :busy="sheetSaving" @close="sheetScore = null" @make="makeFromSheet" />
            <CreditLine />
            </div>
        </div>`,
    computed: {
        /** Make your own's ways in, each with its own icon; Hum a tune only for staff or once the server switches it on. */
        makeWays() {
            const hum = account.me?.staff || account.config?.humTune;
            return [
                { mode: 'sing', icon: 'mic', title: tk('create.home.sing'), hint: tk('create.home.singHint') },
                { mode: 'chords', icon: 'hash', title: tk('create.home.chords'), hint: tk('create.home.chordsHint') },
                ...(hum ? [{ mode: 'hum', icon: 'wave', title: tk('create.home.hum'), hint: tk('create.home.humHint') }] : []),
                { mode: 'write', icon: 'note', title: tk('create.home.write'), hint: tk('create.home.writeHint') },
            ];
        },
        plus: () => account.me?.plan === 'plus',
        /** Every library song opens with an account (2026-11-07); a guest's locked song asks for a free one. */
        signedIn: () => !!account.me,
        signUpForSong: (vm) => `/signin?mode=signup&for=songs&next=${encodeURIComponent('/?song=' + (vm.openTemplate?.id ?? ''))}`,
        /** Storage 80% full on the free plan, past the first week: Home shows the meter (usage.show, read once). */
        storageNear: (vm) => !vm.plus && !vm.firstWeek && !!vm.storageUsage?.storage?.limitBytes && vm.storageUsage.storage.usedBytes / vm.storageUsage.storage.limitBytes >= 0.8,
        /** The projects that count toward the free plan's limit (the course's own don't). */
        /** Songs of your own (not the course's, nor friends'): the first week reads it. */
        used: () => projects.list.filter((p) => !p.course && !p.shared).length,
        /**
         * Open a file takes sheet music after the first week (the coordinator's default for import),
         * once the server's sheetImport switch is on (it keeps imports private), and for staff before.
         */
        sheetMusic() {
            return !this.firstWeek && !!(account.me?.staff || account.config?.sheetImport);
        },
        /** Game mode's daily challenge on its song's card: not in a new singer's first week (Hillel's rule), like voice care. */
        /** Song requests (N7): while the switch is on, and for staff before. */
        requestsOn: () => !!(account.config?.songRequests || account.me?.songRequests || account.me?.staff),
        dailyShown() {
            return effect('daily') && !inFirstWeek(); // the song card's tag: past the first week, as before
        },
        /**
         * A new singer's first week (Hillel: "Yes, hide in week one"): Plus's courses and the free-song
         * meter with Get Plus wait until a song is made, unit 4 is reached, or the week is over.
         */
        firstWeek() {
            // From the course's start (a device that hasn't started is new); a song made ends it too.
            const started = learning.progress.startedAt;
            if (this.used > 0 || (started && Date.now() - started > 7 * 86400000)) return false;
            const early = (learning.course?.units ?? []).slice(0, 3).flatMap((u) => u.lessons.map((l) => l.id));
            return !early.length || !early.every((id) => learning.progress.lessons?.[id]?.stars);
        },
        /** Recordings kept only on this device: signed out, or past the plan (signed in, the rest are in the account). */
        songsOnlyHere: () => projects.all.some((p) => p.recorded && (!account.me || p.notSaved || p.beta || p.course)),
        /** A guest's own songs recorded here and their takes, for the install card's account form (N2). */
        songsHere() {
            const songs = projects.all.filter((p) => p.recorded && !p.course && !p.beta && !p.deleted);
            return { songs: songs.length, takes: songs.reduce((sum, p) => sum + (p.recordedCount ?? 0), 0) };
        },
        courseStarted: () => Object.keys(learning.progress.lessons).length > 0 || !!learning.progress.startedAt,
        firstVisit() {
            return !this.courseStarted && !this.used;
        },
        order() {
            return homeBands({ betaSinger: !!account.me?.betaSinger, betaWaiting: betaWaiting(this.betaParts), recentProject: sangThisWeek(projects.list), features: { workout: this.courseStarted } });
        },
        /** The library's cards: the catalogue, all of it or the Plus songs. */
        allSongs() {
            return this.templates.map((tpl, n) => ({ kind: 'song', key: tpl.id, tpl, n }));
        },
        /**
         * Your songs as one kind of card (Hillel: "make all the songs look the same"): a recording, a
         * song you wrote, a draft or a friend's song, each with its title, one line, a small tag, how
         * it opens, how it plays (or why it can't) and its More menu.
         */
        yourCards() {
            return this.yourSongs.map((item) => {
                if (item.kind === 'project') {
                    const p = item.p;
                    const tag = p.notSaved && account.me ? [t('cloud.notSaved'), true] : p.readOnly ? [t('cloud.readOnly'), true] : p.shared ? [p.shared.removed ? t('together.cardGone') : t('together.cardWith', { owner: p.shared.owner?.name ?? '' }), false] : p.course ? [t('home.projects.fromCourse'), false] : [null, false];
                    // A unit's song (retention row 8) with a take kept says how much of it is you: "1 of 4 parts is you"; in game
                    // mode its cover's rows for your parts are lit, the others hollow.
                    const yours = p.course?.unitSong && p.recordedCount > 0 && p.template;
                    const meta = yours ? t('home.yours.partsYou', { mine: p.recordedCount, count: p.partCount }) : [t('studio.partCount', { count: p.partCount }), t('projects.edited', { when: timeAgo(p.updatedAt) })].join(' · ');
                    return { key: 'p-' + p.id, kind: 'project', title: shownTitle(p), named: !!p.title?.trim(), tag: tag[0], warn: tag[1], meta, mine: yours ? p.yourParts : null, parts: Math.max(1, p.partCount), song: p.template ?? null, open: () => this.open(p), play: () => this.playProject(p), menu: this.projectActions(p) };
                }
                if (item.kind === 'written') {
                    const r = item.r;
                    return { key: 'w-' + r.id, kind: 'written', title: r.template.title, named: true, tag: r.imported || r.source === 'sheet-music' ? t('home.yours.sheet') : t('home.yours.written'), warn: false, meta: r.updatedAt ? t('projects.edited', { when: timeAgo(r.updatedAt) }) : '', parts: r.template.parts.length, song: r.template, open: () => navigate('/t/' + r.id), play: () => this.playWritten(r), menu: this.writtenActions(r) };
                }
                const d = item.d;
                const playable = playableDraft(d);
                return { key: 'd-' + d.id, kind: 'draft', title: this.draftTitle(d) || t('create.home.untitled'), named: !!this.draftTitle(d), tag: t('home.yours.draftTag'), warn: false, meta: d.updatedAt ? t('projects.edited', { when: timeAgo(d.updatedAt) }) : '', parts: d.template?.parts?.length || 1, song: d.template ?? null, open: () => navigate('/create/' + d.id), play: playable ? () => this.playWritten({ id: d.id, template: { ...playable, id: 'draft-' + d.id, version: 0 } }, 'd-') : null, why: t('home.yours.nothingToPlay'), menu: this.draftActions(d) };
            });
        },
        /** Your songs: recordings, songs you wrote and unfinished drafts, newest first (one place for all of yours). */
        yourSongs() {
            const items = [
                ...projects.list.map((p) => ({ kind: 'project', key: 'p-' + p.id, p, at: p.updatedAt ?? 0 })),
                ...mine.list.map((r) => ({ kind: 'written', key: 'w-' + r.id, r, at: r.updatedAt ?? 0 })),
                ...mine.drafts.map((d) => ({ kind: 'draft', key: 'd-' + d.id, d, at: d.updatedAt ?? 0 })),
            ];
            return items.sort((a, b) => b.at - a.at);
        },
        /** The account chip's initials (42.png): the first letters of the first two words. */
        initials() {
            const words = (account.me?.name || '?').trim().split(/\s+/);
            return (words[0][0] + (words[1]?.[0] ?? '')).toUpperCase();
        },
        /** Two rows from a tablet up; on a phone, where the band scrolls, the first few. Open, all of them. */
        /** After the first week Home is trimmed (Hillel, 2026-10-30): one card or one row a section. A new singer's stays as it is. */
        trimmed() {
            return !this.firstWeek;
        },
        /** Your songs, trimmed: the two latest beside New song, the rest under See all. */
        shownYours() {
            return this.trimmed && !this.yoursOpen ? this.yourCards.slice(0, 2) : this.yourCards;
        },
        /**
         * Three from the library for this singer: songs you can start first, then the ones whose
         * best part sits closest to your range (how far outside it, in semitones), in the library's order.
         */
        picked() {
            const sorted = this.ranked;
            // Game mode's daily challenge: today's song is among the three (its TODAY tag is the way in), first when it wasn't.
            const today = this.dailyItem;
            let three = sorted.slice(0, 3);
            if (today && !three.includes(today)) three = [today, ...three.slice(0, 2)];
            // At most one New or Carol season song (song-news.js), last, when it isn't among them already.
            if (this.news && !three.includes(this.news.item)) three = [...three.slice(0, 2), this.news.item];
            return three;
        },
        /** The library in Home's order for this singer: songs you can start first, then the closest to your range. */
        ranked() {
            return [...this.allSongs].sort((a, b) => Number(!canStart(a.tpl, this.signedIn)) - Number(!canStart(b.tpl, this.signedIn)) || this.outside(a.tpl) - this.outside(b.tpl) || a.n - b.n);
        },
        dailyItem() {
            return effect('daily') ? this.ranked.find((item) => item.tpl.id === dailyState().today?.templateId) ?? null : null;
        },
        /**
         * The one pick with news (retention plan, idea 5): a song opened since this device last looked at the library,
         * or a carol in carol season. Only once the picks show, only for a known range, only a song that fits it and can
         * be started, and never today's daily song (which wears its own tag).
         */
        news() {
            if (!this.trimmed || !learning.progress.range) return null;
            const fits = (tpl) => this.outside(tpl) === 0 && canStart(tpl, this.signedIn) && tpl.id !== this.dailyItem?.tpl.id;
            return newsPick(this.ranked, { since: this.lookedAt, fits });
        },
        newsWords() {
            if (!this.news) return '';
            return this.news.kind === 'new' ? sk('home.songs.news.new', 'home.songs.news.newGame') : t(SEASON_WORDS[this.news.season] ?? SEASON_WORDS.carols);
        },
        shownSongs() {
            if (this.songsOpen) return this.allSongs;
            if (this.trimmed) return this.picked;
            const cols = this.songColumns;
            if (!cols) return this.allSongs.slice(0, PHONE_SONGS);
            // Hillel: whole rows only. Up to two rows, See all taking a cell when some are left over.
            const n = this.allSongs.length;
            // Hillel: everything that fits in two rows shows (one short row is fine); whole rows only when some are hidden.
            if (n <= cols * 2) return this.allSongs;
            // The most rows (two at most) whose cells, See all among them, leave some songs for See all.
            for (let rows = 2; rows >= 1; rows--) if (rows * cols - 1 < n) return this.allSongs.slice(0, rows * cols - 1);
            return this.allSongs;
        },
        hiddenSongs() {
            return this.allSongs.length - this.shownSongs.length;
        },
        /** Up to three parts, those waiting on the singer first. */
        betaShown() {
            const waiting = (part) => (['to_record', 'retake'].includes(part.status) ? 0 : 1);
            return [...(this.betaParts ?? [])].sort((a, b) => waiting(a) - waiting(b)).slice(0, 3);
        },
    },
    created() {
        // Today's challenge (docs/daily-challenge-plan.md): game mode's module with its board and stamps, serious mode the
        // shared pick only (it never loads game mode's modules); in the first week only once it may be offered.
        if (dailyOpen()) {
            if (effect('daily')) loadDaily();
            else if (!game()) loadPick();
        }
        loadProjects();
        // The storage meter's numbers, on the free plan only (one read; Plus has room to spare).
        if (account.me && account.me.plan !== 'plus') api.usage.show().then(({ data }) => (this.storageUsage = data), (error) => console.warn('Storage not read', error));
        loadMine().catch((error) => console.warn('Your templates not loaded', error));
        if (account.me) this.loadGroups();
        // Friends' projects come to this device (the projects list hears the change).
        loadShared().catch((error) => error?.code !== 'offline' && console.warn('Shared projects not loaded', error));
        loadCatalogue(); // real voices' credits on the songs
        // Drafts start or stop showing (before the launch, an admin): the library again.
        this.stopDrafts = onDraftsChange(() => listTemplates().then((list) => (this.templates = list), (error) => console.warn('Templates not loaded', error)));
        listTemplates()
            .then((list) => {
                this.templates = list;
                // A link to a song (a shared harmony's "Learn this harmony", a song page's Start, What's next): it opens here.
                // A song part page's link names the part too (&part=alto): Start then gives you that part.
                const query = new URLSearchParams(location.search);
                const wanted = query.get('song');
                const song = wanted && list.find((s) => s.id === wanted);
                if (song) {
                    const part = query.get('part');
                    if (part && song.parts?.some((p) => p.key === part)) this.linkedPart = { song: song.id, part };
                    const url = new URL(location.href);
                    url.searchParams.delete('song');
                    url.searchParams.delete('part');
                    history.replaceState(history.state, '', url.pathname + url.search);
                    // Once the account is known: a Plus member must never see the song as locked.
                    if (account.loaded) this.infoSong(song);
                    else this.linkedSong = song;
                }
            })
            .catch((error) => console.warn('Templates not loaded', error));
    },
    mounted() {
        // A first look at Home's picks starts the library's clock: nothing is "new" the first time (song-news.js).
        if (!this.lookedAt) lookedNow();
        this.onResize = () => (this.songColumns = songColumns());
        window.addEventListener('resize', this.onResize);
    },
    beforeUnmount() {
        this.stopSongs();
        closeMic();
        window.removeEventListener('resize', this.onResize);
        this.stopDrafts?.();
    },
    watch: {
        /** A pick with news, counted once each time it shows. */
        'news.item.tpl.id': {
            handler(id) {
                if (id && !this.songsOpen && this.newsCounted !== id) {
                    this.newsCounted = id;
                    count('home_new_shown');
                }
            },
        },
        /** Ideas is asked about once the new-song sheet opens (closed, the server answers 404). */
        newing(open) {
            if (open) ideasOpen();
        },
        'account.me.betaSinger': {
            immediate: true,
            handler(singer) {
                if (singer && !this.betaParts) this.loadBetaParts();
            },
        },
        'account.me.id'(id) {
            if (id) this.loadGroups();
            if (id) loadShared().catch((error) => error?.code !== 'offline' && console.warn('Shared projects not loaded', error));
        },
        'account.loaded'(loaded) {
            if (loaded && this.linkedSong) {
                this.infoSong(this.linkedSong); // its sheet, as a tap on it opens
                this.linkedSong = null;
            }
        },
    },
    methods: {
        /** Shows every song, or two rows again; remembered for this visit only. */
        /** How far a song's best part sits outside the singer's range, in semitones (0: it fits; no range, 0). */
        outside(tpl) {
            const range = learning.progress.range;
            return range ? Math.min(99, ...(tpl.parts ?? []).filter((p) => p.range).map((p) => Math.max(0, range.low - p.range.low) + Math.max(0, p.range.high - range.high))) : 0;
        },
        /** A tap on a song card: its sheet; on the pick with news, counted. */
        songTap(item) {
            if (this.news?.item === item) count('home_new_tap');
            this.infoSong(item.tpl);
        },
        openSongs(open) {
            if (open) lookedNow(); // looked at the library: what opens after this is new
            this.songsOpen = open;
            tabStorage.set('stackapella.songsOpen', open ? '1' : '');
            if (!open) document.getElementById('band-songs')?.scrollIntoView({ block: 'nearest' });
        },
        /** The choir groups you sing in or direct, for their band (left out quietly if they can't load). */
        async loadGroups() {
            try {
                this.groups = (await api.groups.list()).data;
                if (account.me?.id) this.choirParts = await myGroupParts(account.me.id, this.groups);
            } catch (error) {
                if (error?.code !== 'offline') console.warn('Choir groups not loaded', error);
            }
        },
        /** The rehearsal in the week before it: "rehearsal Thursday", then "2 days to rehearsal" in the last three (never a nag). */
        choirSoon(c) {
            const next = rehearsalDates(c.rehearsal).next;
            if (!next) return '';
            const days = daysUntil(next);
            if (days < 0 || days > 6) return '';
            if (days <= 3) return t('groups.rehearsal.inDays', { days });
            return t('groups.rehearsal.onDay', { day: new Intl.DateTimeFormat(locale.lang, { weekday: 'long' }).format(next) });
        },
        /** A part a choir director gave you: your copy of the song once begun, else a new one, on Sing. */
        async learnChoirPart(c) {
            this.startingChoir = c.templateId;
            try {
                navigate(`/p/${await learnGroupSong(c)}/sing`);
            } catch (error) {
                console.error('The choir song did not start', error);
                toast(t('projects.couldNotStart'), { tone: 'error' });
            } finally {
                this.startingChoir = null;
            }
        },
        /** A beta singer's parts, for the band: quietly left out if they can't load. */
        async loadBetaParts() {
            try {
                this.betaParts = (await api.beta.parts.list()).data;
            } catch (error) {
                console.warn('Beta parts not loaded', error);
            }
        },
        partName: (part) => templatePartName({ key: part.partKey, name: part.partKey }),
        /**
         * A song's details. One the app doesn't carry is fetched whole when it can be started (every
         * library song with an account); a guest's song that needs one opens with its clip and the way to a free account.
         */
        async pickTemplate(template) {
            let song = template;
            this.locked = !canStart(template, this.signedIn);
            if (template.summary && !this.locked) {
                toast(t('templates.loading'), { seconds: 2 });
                try {
                    song = await loadTemplate(template.id);
                } catch (error) {
                    if (error?.code === 'account_required') this.locked = true; // a guest's song that needs a free account
                    else {
                        console.error('The song did not load', error);
                        toast(error?.code === 'offline' ? t('common.offline') : t('projects.couldNotStart'), { tone: 'error' });
                        return;
                    }
                }
            }
            this.singers = Math.max(...singerOptions(song));
            this.openTemplate = song;
        },
        /** Each of the song's parts, heard or not for the singers chosen (a few ms' ramp, no click). */
        previewLevels() {
            const singing = new Set(partsFor(this.openTemplate, this.singers).map((part) => part.key));
            for (const part of this.openTemplate.parts) {
                engine.updatePart(`preview:${part.key}`, { volume: 1, pan: 0, audible: singing.has(part.key), offsetSeconds: 0 });
            }
        },
        /** How many sing it, "2 to 4" (or "4 singers"): the card's short footer (Hillel). */
        singerCount(template) {
            const options = singerOptions(template);
            return options.length > 1 ? t('projects.singersRange', { low: options[0], high: options.at(-1) }) : t('projects.singerCount', { count: options[0] });
        },
        /** "For 2 to 4 singers": the footer said in full, for a screen reader. */
        singerText(template) {
            const options = singerOptions(template);
            const range = options.length > 1 ? t('projects.singerRange', { low: options[0], high: options.at(-1) }) : formatNumber(options[0]);
            return t('projects.forSingers', { range });
        },
        /**
         * Plays the whole template from its guides, from memory: nothing is saved. Every part is
         * rendered and playing, those the chosen number of singers doesn't sing at no level, so
         * changing the number never restarts it.
         */
        async preview() {
            if (this.previewing) {
                this.stopPreview();
                return;
            }
            // One thing plays at a time: a Your songs recording stops for this.
            if (this.playingSong) {
                this.playingSong = null;
                if (session.playing) await sessionStore.stopPlayback();
            }
            // A Plus song without Plus: its short clip, a finished mix (never its parts).
            if (this.locked) {
                this.clip = new Audio(this.openTemplate.previewUrl);
                this.clip.onended = () => this.stopPreview();
                this.clip.play().catch((error) => console.warn('The preview did not play', error));
                this.previewing = true;
                return;
            }
            const t = this.openTemplate;
            await unlock();
            this.preparingPreview = true;
            // Each part's real voice where the song has one, as Sing and the landing play it (guide-voice.js), else the
            // synth; one after another, as decoding every real voice at once can use hundreds of MB on a phone.
            const guides = [];
            for (const part of t.parts) {
                guides.push(await partVoice(t, part, engine.sampleRate, () => renderGuide({ templateId: t.id, templateVersion: t.version, part, tempo: t.tempo, sampleRate: engine.sampleRate })));
                if (this.openTemplate !== t) break;
            }
            this.preparingPreview = false;
            if (this.openTemplate !== t) return; // closed while it rendered
            [...engine.parts.keys()].forEach((id) => engine.removePart(id));
            engine.projectId = null; // the studio reloads its own parts next time
            t.parts.forEach((part, i) => engine.setPart(`preview:${part.key}`, guides[i].samples, engine.sampleRate, { volume: 1, pan: 0, audible: true, offsetSeconds: guides[i].offsetSeconds }));
            this.previewLevels();
            engine.play({});
            this.previewing = true;
            const end = (lengthBeats(t) * 60) / t.tempo + 0.2;
            const watch = () => {
                if (!this.previewing) return;
                if ((engine.positionSeconds() ?? end) >= end) {
                    this.stopPreview();
                    return;
                }
                this.previewFrame = requestAnimationFrame(watch);
            };
            watch();
        },
        stopPreview() {
            cancelAnimationFrame(this.previewFrame);
            if (this.clip) {
                this.clip.pause();
                this.clip = null;
            } else if (this.previewing) engine.stop();
            this.previewing = false;
        },
        closeTemplate() {
            this.stopPreview();
            this.openTemplate = null;
            this.showInfo = false;
        },
        /** Whether this card's Play is playing ('p-' a recording, 'w-' a written song, a library song's id). */
        songOn(key) {
            return key.startsWith('p-') ? this.playingSong === key && session.playing : this.previewOf === key && this.previewing;
        },
        /** Anything playing on Home stops: a library preview, a written song, a recording (one at a time). */
        async stopSongs() {
            this.stopPreview();
            this.previewOf = null;
            if (this.playingSong) {
                this.playingSong = null;
                if (session.playing) await sessionStore.stopPlayback();
            }
        },
        /** Play on a recording's card: its current mix (the takes, guides for the parts not sung yet). */
        async playProject(project) {
            const key = 'p-' + project.id;
            const again = this.songOn(key);
            await this.stopSongs();
            if (again) return;
            this.playingSong = key;
            try {
                await sessionStore.openSession(project.id);
                sessionStore.setScreen('studio');
                if (this.playingSong !== key || !(await sessionStore.ready())) return;
                session.position = 0;
                await sessionStore.togglePlay();
            } catch (error) {
                console.error('The song did not play', error);
                this.playingSong = null;
                toast(t('home.yours.notPlayed'), { tone: 'error' });
            }
        },
        /** Play on a library card: the song's preview, as its sheet plays it (a Plus song without Plus: its clip). */
        async playLibrary(template) {
            const again = this.songOn(template.id);
            await this.stopSongs();
            if (again) return;
            this.locked = !canStart(template, this.signedIn);
            this.openTemplate = template;
            this.previewOf = template.id;
            await this.preview();
        },
        /** Play on a song you wrote (or a draft with notes): its harmony, as a library song plays. */
        async playWritten(record, prefix = 'w-') {
            const key = prefix + record.id;
            const again = this.songOn(key);
            await this.stopSongs();
            if (again) return;
            this.locked = false;
            this.openTemplate = record.template;
            this.previewOf = key;
            await this.preview();
        },
        /** A recording's More menu: only what it can do (a friend's song is left, not deleted). */
        projectActions(project) {
            const theirs = project.shared && !project.shared.removed;
            return [
                { key: 'share', label: t('home.yours.share'), run: () => navigate(`/p/${project.id}?sheet=share`) },
                ...(!project.beta && !project.course ? [{ key: 'invite', label: t('home.yours.invite'), run: () => navigate(`/p/${project.id}?sheet=together`) }] : []),
                { key: 'studio', label: t('home.yours.openStudio'), run: () => navigate(`/p/${project.id}`) },
                ...(!theirs ? [{ key: 'rename', label: t('home.yours.rename'), run: () => this.rename(project) }] : []),
                theirs ? { key: 'leave', label: t('together.leave'), danger: true, run: () => this.remove(project) } : { key: 'delete', label: t('home.yours.delete'), danger: true, run: () => this.remove(project) },
            ];
        },
        writtenActions(record) {
            return [
                { key: 'edit', label: t('template.edit'), run: () => navigate('/create?edit=' + record.id) },
                { key: 'delete', label: t('home.yours.delete'), danger: true, run: () => this.removeWritten(record) },
            ];
        },
        draftActions(draft) {
            return [
                { key: 'resume', label: t('create.home.resume'), run: () => navigate('/create/' + draft.id) },
                { key: 'discard', label: t('create.home.discard'), danger: true, run: () => this.discard(draft) },
            ];
        },
        rename(project) {
            this.renaming = project;
            this.newName = project.title ?? '';
        },
        async saveName() {
            const project = this.renaming;
            this.renaming = null;
            try {
                await saveProject({ ...toRaw(project), title: this.newName.trim() });
            } catch (error) {
                console.error('Not renamed', error);
                toast(t('settings.failed'), { tone: 'error' });
            }
        },
        /** A written song's Delete: asked first, naming it, with Undo after. */
        async removeWritten(record) {
            const title = record.template.title;
            const sure = await confirmAction({ title: t('template.deleteTitle', { title }), body: t('template.deleteBody'), action: t('template.delete'), cancel: t('confirm.keep') });
            if (!sure) return;
            const undo = await deleteTemplate(record.id);
            toast(t('template.deleted', { title }), undo ? { seconds: UNDO_SECONDS, actions: [{ label: t('common.undo'), run: undo }] } : {});
        },
        /** A tap on a library song: its sheet (what it is, Play, Start). */
        async infoSong(template) {
            await this.stopSongs();
            await this.pickTemplate(template);
            if (this.openTemplate?.id === template.id) this.showInfo = true;
        },
        async start(range = null) {
            if (this.starting) return;
            this.starting = true;
            this.stopPreview();
            try {
                const linked = this.linkedPart?.song === this.openTemplate.id ? this.linkedPart.part : null; // the song part page's part
                const project = await startFromTemplate(this.openTemplate, { singers: this.singers, ...(range ? { transpose: range.transpose, focusKey: range.partId, keyChosen: true } : linked ? { focusKey: linked } : {}) });
                navigate(`/p/${project.id}/sing`); // a new template project starts by asking how to sing
            } catch (error) {
                console.error('Starting a template failed', error);
                toast(t('projects.couldNotStart'), { tone: 'error' });
            } finally {
                this.starting = false;
            }
        },
        open(project) {
            navigate(`/p/${project.id}`);
        },
        /** A song's cover seed, from its id: the same colours wherever it's listed. */
        seedOf: (id) => [...String(id)].reduce((hash, c) => (hash * 31 + c.charCodeAt(0)) >>> 0, 7),
        /** A draft's name: its title, or its first line of words. */
        draftTitle(draft) {
            return draft.template?.title || draft.lines?.find((l) => !l.noWords)?.text || '';
        },
        /** Discard, asked first (unless "Don't ask again"), then Undo while the toast's countdown runs. */
        async discard(draft) {
            const sure = await confirmAction({ title: t('create.home.discardTitle'), body: t('create.home.discardBody', { seconds: UNDO_SECONDS }), action: t('create.home.discard'), skippable: 'discardDraft' });
            if (!sure) return;
            const undo = await discardDraft(draft.id);
            if (undo) toast(t('create.home.discarded'), { seconds: UNDO_SECONDS, actions: [{ label: t('common.undo'), run: undo }] });
        },
        /** A template file: saved under Your templates when it checks out; a broken one can open in the editor. */
        async importFile(file) {
            this.importProblems = null;
            if (!file) return;
            if (!account.loaded) await loadAccount(); // whether sheet music is open to you is the account's to say
            if (this.sheetMusic && /\.(musicxml|mxl|xml|midi?)$/i.test(file.name)) {
                await this.openSheet(file);
                return;
            }
            const result = readImport(await file.text(), { taken: (id) => this.templates.some((t) => t.id === id) || mine.list.some((r) => r.id === id) });
            if (result.unreadable) {
                toast(t('create.home.importUnreadable'), { tone: 'error' });
                return;
            }
            if (result.problems) {
                this.importProblems = result;
                return;
            }
            const record = await saveTemplate({ ...result.template, version: 1 });
            this.making = false;
            toast(t('create.home.imported', { title: record.template.title }));
            navigate(`/t/${record.id}`);
        },
        /** A sheet music file: read on the device, then its lines to choose from (signed in, as the account keeps it private). */
        async openSheet(file) {
            if (!account.loaded) await loadAccount(); // still being checked: not the same as signed out
            if (!account.me) {
                toast(t('sheet.error.signIn'), { actions: [{ label: t('wrap.makeAccount'), run: () => navigate(signUpFor('sheet', '/')) }] });
                return;
            }
            const score = await readSheetFile(file);
            if (score.unreadable || score.unsupported === 'timewise') toast(t('sheet.error.unreadable'), { tone: 'error' });
            else if (score.unsupported === 'metre') toast(t('sheet.error.metre', { metre: score.metre ?? '?' }), { tone: 'error' });
            else if (score.unsupported === 'metreChange') toast(t('sheet.error.metreChange', { metre: score.metre ?? '?' }), { tone: 'error' });
            else if (score.unsupported) toast(t('sheet.error.empty'), { tone: 'error' });
            else {
                this.making = false;
                this.sheetScore = score;
            }
        },
        /** Make the song: saved as an import (kept private by the account), then opened. */
        async makeFromSheet(template) {
            this.sheetSaving = true;
            try {
                const record = await saveTemplate(template, { visibility: 'private', source: 'sheet-music' });
                count('sheet_import');
                tried('create'); // breadth: a song of your own, from sheet music
                this.sheetScore = null;
                toast(t('create.home.imported', { title: record.template.title }));
                navigate(`/t/${record.id}`);
            } catch (error) {
                console.error('The song from sheet music was not saved', error);
                toast(t('create.notSaved'), { tone: 'error' });
            } finally {
                this.sheetSaving = false;
            }
        },
        openBroken() {
            tabStorage.set('stackapella.import', JSON.stringify(this.importProblems.template));
            navigate('/create?draft=import');
        },
        /** New song's sheet: Make a harmony is the song builder's own choices. */
        makeOne() {
            this.newing = false;
            this.making = true;
        },
        newProject() {
            this.newing = false;
            this.create({}); // straight into the studio: named later, set as the last project was
        },
        async create(fields) {
            const project = await createProject(fields);
            navigate(`/p/${project.id}`);
        },
        async remove(project) {
            if (project.shared && !project.shared.removed) return this.leave(project, shownTitle(project));
            await deleteSong(project);
        },
        /** A friend's project: leaving it (your parts stay for the others) takes it off this device. */
        async leave(project, title) {
            const sure = await confirmAction({ title: t('together.leaveTitle', { title }), body: t('together.leaveBody'), action: t('together.leave') });
            if (!sure) return;
            try {
                await api.projects.members.delete(project.id, account.me.id, { keepParts: true });
                await leftProject(project.id);
                await deleteProject(project.id);
                toast(t('together.left', { title }));
            } catch (error) {
                toast(error?.code === 'offline' ? t('common.offline') : t('together.notLeft'), { tone: 'error' });
            }
        },
    },
};
