// The admin (/admin): Hillel's and the Editors' screens, after iaw's admin (docs/conventions.md).
// The server's own pages sign people in and check the second factor; this reads admin.me and sends
// anyone it refuses to them. Every admin route is also checked on the server: hiding a screen is
// never the protection. This code loads only when /admin is opened, and is never precached.

import { nextTick } from 'vue';
import { api, adminSignOut } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { navigate, route } from '../router.js';
import { Icon, Logo } from '../components/icon.js';
import { Notice, StatusChip } from '../components/form.js';
import { toast } from '../components/ui.js';
import { AuditSection } from './audit.js';
import { FeedbackSection } from './feedback.js';
import { ReportsSection } from './reports.js';
import { IdeasSection } from './ideas.js';
import { RequestsSection } from './requests.js';
import { MudeoSection } from './mudeo.js';
import { GuidesSection } from './guides.js';
import { ReviewSection } from './review.js';
import { SafetySection, SwitchesSection } from './safety.js';
import { SingersSection } from './singers.js';
import { PeopleSection } from './people.js';
import { UsageSection } from './usage.js';
import { change, OverviewSection } from './growth.js';
import { SourcesSection } from './sources.js';
import { ChoirGroupsSection } from './choir-groups.js';
import { RevenueSection } from './revenue.js';
import { LiveSection } from './live.js';
import { CorrectionsSection } from './corrections.js';
import { SearchSection } from './search.js';
import { CohortsSection } from './cohorts.js';
import { ActivitySection } from './activity.js';
import { SchoolSection } from './school.js';
import { CodeWatchSection } from './code-watch.js';
import { ExperimentsSection } from './experiments.js';
import { OutreachSection } from './outreach.js';
import { FunnelSection } from './funnel.js';
import { DropoffsSection } from './dropoffs.js';
import { SharingSection } from './sharing.js';
import { BreadthSection } from './breadth.js';
import { SupportSection } from './support.js';
import { LinksSection } from './links.js';
import { PostsSection } from './posts.js';
import { sendToSignIn } from './auth.js';
import { StoreTestersCard } from './testers.js';
import { NeedsCard, homeCount } from './needs.js';

// The menu by job (Hillel, 2026-11-10: "i find the admin header navigation confusing, for example why are switches under
// people?"; he approved this structure): what's waiting for you, the accounts and groups, the songs and voices, getting
// the word out, the numbers, and the settings. Feature switches never live under Safety, which keeps the emergency
// controls. The header shows the groups, then the open group's pages; a page's count adds to its group's. Only the
// Inbox's pages count (Hillel, 2026-11-12: "dont include the count in library and marketing"): a badge is for what
// waits on him, never a backlog. The paths never change with the groups, so old links keep working.
export const GROUPS = [
    { key: 'inbox', label: tk('admin.nav.group.inbox') },
    { key: 'users', label: tk('admin.nav.group.users') },
    { key: 'library', label: tk('admin.nav.group.library') },
    { key: 'marketing', label: tk('admin.nav.group.marketing') },
    { key: 'numbers', label: tk('admin.nav.group.numbers') },
    { key: 'settings', label: tk('admin.nav.group.settings') },
];

export const SECTIONS = [
    { name: 'home', group: 'inbox', path: '/admin', label: tk('admin.nav.home'), hint: null, icon: 'home', role: 'editor', count: (c) => c.home, words: tk('admin.needs.homeCount') },
    { name: 'support', group: 'inbox', path: '/admin/support', label: tk('admin.nav.support'), hint: tk('admin.nav.supportHint'), icon: 'info', role: 'admin' },
    { name: 'feedback', group: 'inbox', path: '/admin/feedback', label: tk('admin.nav.feedback'), hint: tk('admin.nav.feedbackHint'), icon: 'info', role: 'editor', count: (c) => c.unread, words: tk('admin.nav.unreadCount') },
    { name: 'corrections', group: 'inbox', path: '/admin/corrections', label: tk('admin.nav.corrections'), hint: tk('admin.nav.correctionsHint'), icon: 'note', role: 'editor', count: (c) => c.corrections, words: tk('admin.nav.correctionsCount') },
    { name: 'reports', group: 'inbox', path: '/admin/reports', label: tk('admin.nav.reports'), hint: tk('admin.nav.reportsHint'), icon: 'alert', role: 'admin' },
    { name: 'review', group: 'inbox', path: '/admin/review', label: tk('admin.nav.review'), hint: tk('admin.nav.reviewHint'), icon: 'mic', role: 'editor', count: (c) => c.waiting, words: tk('admin.nav.waitingCount') },
    { name: 'people', group: 'users', path: '/admin/people', label: tk('admin.nav.people'), hint: tk('admin.nav.peopleHint'), icon: 'list', role: 'admin' },
    { name: 'groups', group: 'users', path: '/admin/groups', label: tk('admin.nav.choirGroups'), hint: tk('admin.nav.choirGroupsHint'), icon: 'list', role: 'admin' },
    { name: 'school', group: 'users', path: '/admin/school', label: tk('admin.nav.school'), hint: tk('admin.nav.schoolHint'), icon: 'list', role: 'admin' },
    { name: 'guides', group: 'library', path: '/admin/guides', label: tk('admin.nav.guides'), hint: tk('admin.nav.guidesHint'), icon: 'note', role: 'editor' },
    { name: 'singers', group: 'library', path: '/admin/singers', label: tk('admin.nav.singers'), hint: tk('admin.nav.singersHint'), icon: 'headphones', role: 'admin' },
    { name: 'links', group: 'library', path: '/admin/links', label: tk('admin.nav.links'), hint: tk('admin.nav.linksHint'), icon: 'arrow', role: 'admin' },
    { name: 'requests', group: 'library', path: '/admin/requests', label: tk('admin.nav.requests'), hint: tk('admin.nav.requestsHint'), icon: 'list', role: 'admin' },
    { name: 'ideas', group: 'library', path: '/admin/ideas', label: tk('admin.nav.ideas'), hint: tk('admin.nav.ideasHint'), icon: 'mic', role: 'admin' },
    { name: 'outreach', group: 'marketing', path: '/admin/outreach', label: tk('admin.nav.outreach'), hint: tk('admin.nav.outreachHint'), icon: 'arrow', role: 'admin' },
    { name: 'posts', group: 'marketing', path: '/admin/posts', label: tk('admin.nav.posts'), hint: tk('admin.nav.postsHint'), icon: 'sparkle', role: 'admin' },
    { name: 'search', group: 'marketing', path: '/admin/search', label: tk('admin.nav.search'), hint: tk('admin.nav.searchHint'), icon: 'arrow', role: 'admin' },
    { name: 'mudeo', group: 'marketing', path: '/admin/mudeo', label: tk('admin.nav.campaigns'), hint: tk('admin.nav.campaignsHint'), icon: 'arrow', role: 'admin' },
    { name: 'growth', group: 'numbers', path: '/admin/growth', label: tk('admin.nav.growth'), hint: tk('admin.nav.growthHint'), icon: 'sparkle', role: 'admin' },
    { name: 'live', group: 'numbers', path: '/admin/live', label: tk('admin.nav.live'), hint: tk('admin.nav.liveHint'), icon: 'wave', role: 'admin' },
    { name: 'usage', group: 'numbers', path: '/admin/usage', label: tk('admin.nav.usage'), hint: tk('admin.nav.usageHint'), icon: 'sparkle', role: 'admin' },
    { name: 'activity', group: 'numbers', path: '/admin/activity', label: tk('admin.nav.activity'), hint: tk('admin.nav.activityHint'), icon: 'wave', role: 'admin' },
    { name: 'funnel', group: 'numbers', path: '/admin/funnel', label: tk('admin.nav.funnel'), hint: tk('admin.nav.funnelHint'), icon: 'list', role: 'admin' },
    { name: 'dropoffs', group: 'numbers', path: '/admin/dropoffs', label: tk('admin.nav.dropoffs'), hint: tk('admin.nav.dropoffsHint'), icon: 'list', role: 'admin' },
    { name: 'sources', group: 'numbers', path: '/admin/sources', label: tk('admin.nav.sources'), hint: tk('admin.nav.sourcesHint'), icon: 'arrow', role: 'admin' },
    { name: 'breadth', group: 'numbers', path: '/admin/breadth', label: tk('admin.nav.breadth'), hint: tk('admin.nav.breadthHint'), icon: 'sparkle', role: 'admin' },
    { name: 'sharing', group: 'numbers', path: '/admin/sharing', label: tk('admin.nav.sharing'), hint: tk('admin.nav.sharingHint'), icon: 'arrow', role: 'admin' },
    { name: 'cohorts', group: 'numbers', path: '/admin/cohorts', label: tk('admin.nav.cohorts'), hint: tk('admin.nav.cohortsHint'), icon: 'list', role: 'admin' },
    { name: 'experiments', group: 'numbers', path: '/admin/experiments', label: tk('admin.nav.experiments'), hint: tk('admin.nav.experimentsHint'), icon: 'split', role: 'admin' },
    { name: 'revenue', group: 'numbers', path: '/admin/revenue', label: tk('admin.nav.revenue'), hint: tk('admin.nav.revenueHint'), icon: 'sparkle', role: 'admin' },
    { name: 'switches', group: 'settings', path: '/admin/switches', label: tk('admin.nav.switches'), hint: tk('admin.nav.switchesHint'), icon: 'split', role: 'admin' },
    { name: 'safety', group: 'settings', path: '/admin/safety', label: tk('admin.nav.safety'), hint: tk('admin.nav.safetyHint'), icon: 'shield', role: 'admin' },
    { name: 'audit', group: 'settings', path: '/admin/audit', label: tk('admin.nav.audit'), hint: tk('admin.nav.auditHint'), icon: 'list', role: 'admin' },
    { name: 'code-watch', group: 'settings', path: '/admin/code-watch', label: tk('admin.nav.codeWatch'), hint: tk('admin.nav.codeWatchHint'), icon: 'shield', role: 'admin' },
];

const GREETINGS = { morning: tk('admin.home.greeting.morning'), afternoon: tk('admin.home.greeting.afternoon'), evening: tk('admin.home.greeting.evening') };

const HomeSection = {
    components: { Icon, NeedsCard, StatusChip, StoreTestersCard },
    props: { me: { type: Object, required: true }, groups: { type: Array, required: true }, overview: { type: Object, default: null }, overviewFailed: { type: Boolean, default: false } },
    emits: ['retry'],
    setup: () => ({ formatNumber }),
    template: `
        <div class="space-y-6">
            <!-- Hillel's Home layout (2026-11-11): it opens on him and what needs him. The day,
                 then a greeting by the time of day; the account and its role at the far right of the same row. -->
            <header class="flex flex-wrap items-end justify-between gap-x-4 gap-y-2" data-home-head>
                <div class="min-w-0">
                    <p class="text-sm text-muted" data-home-date><time :datetime="todayIso">{{ today }}</time></p>
                    <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-cream sm:text-3xl" data-home-greeting>{{ greeting }}</h1>
                </div>
                <div class="flex min-w-0 flex-wrap items-center gap-2.5 text-sm text-muted">
                    <span class="min-w-0 break-all" translate="no">{{ me.email }}</span>
                    <StatusChip tone="info" :label="me.role === 'admin' ? t('admin.role.admin') : t('admin.role.editor')" />
                </div>
            </header>
            <!-- A change needs a fresh second-factor check: one line, only while it applies (it was a chip on a card). -->
            <p v-if="!sudo" class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-warn/40 bg-warn/10 px-4 py-3 text-sm" data-home-sudo>
                <span class="size-2 shrink-0 rounded-full bg-warn" aria-hidden="true"></span>
                <span class="min-w-0 flex-1 text-cream">{{ t('admin.home.sudoNeeded') }}</span>
                <a :href="sudoPath" class="tap-44 inline-flex items-center gap-1 font-semibold text-warn hover:underline">{{ t('admin.home.sudoConfirm') }}<span aria-hidden="true" class="rtl:-scale-x-100">→</span></a>
            </p>
            <!-- What needs you now (admin/needs.js): a card for each kind that has something, side by side from 900 px. -->
            <NeedsCard :outreach="overview?.outreach ?? null" :not-synced="overview?.songsNotSynced ?? []" :sitemap="overview?.sitemapProblems ?? []" :corrections="overview?.corrections?.new ?? 0" :admin="me.role === 'admin'" :loaded="!!overview" :failed="overviewFailed" @retry="$emit('retry')" />
            <!-- The last 7 days against the 7 before (docs/admin-analytics-plan.md, slice 1), as Hillel drew it: one ruled
                 panel, each a label over a big number with its change small beside it; five across from 900 px, two on a
                 phone. The two all-time counts say so, since they aren't the week's. -->
            <section v-if="overview?.growth && me.role === 'admin'" class="space-y-3" aria-labelledby="home-growth-heading" data-home-growth>
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <h2 id="home-growth-heading" class="text-xl font-bold text-cream">{{ t('admin.home.growth.title') }} <span class="ms-1 text-sm font-normal text-muted">{{ t('admin.home.growth.vsBefore') }}</span></h2>
                    <a href="/admin/growth" class="link tap-44 inline-flex min-h-6 items-center gap-1 text-sm" @click.prevent="navigate('/admin/growth')">{{ t('admin.home.growth.open') }}<span aria-hidden="true" class="rtl:-scale-x-100">→</span></a>
                </div>
                <dl class="card grid grid-cols-2 overflow-hidden min-[900px]:grid-cols-5" data-home-panel>
                    <div v-for="tile in growthTiles" :key="tile.key" class="-mb-px -me-px min-w-0 border-b border-e border-line p-4 sm:p-5" :data-tile="tile.key">
                        <dt class="text-sm text-muted">{{ tile.label }}</dt>
                        <dd class="mt-2 flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
                            <span class="text-3xl font-extrabold tabular-nums tracking-tight text-cream sm:text-4xl" data-tile-value>{{ formatNumber(tile.value) }}</span>
                            <span v-if="tile.week" class="text-sm font-semibold tabular-nums" :class="tile.dir === 'up' ? 'text-up' : tile.dir === 'down' ? 'text-down' : 'text-muted'" :title="tile.against" data-change :data-dir="tile.dir"><span v-if="tile.dir === 'up' || tile.dir === 'down'" class="me-0.5" aria-hidden="true">{{ tile.dir === 'up' ? '↑' : '↓' }}</span>{{ tile.text }}<span class="sr-only"> {{ tile.against }}</span></span>
                            <span v-else class="text-xs text-muted" data-all-time>{{ tile.against }}</span>
                        </dd>
                    </div>
                </dl>
            </section>
            <section v-if="overview" class="card p-6" aria-labelledby="beta-heading">
                <h2 id="beta-heading" class="text-lg font-bold text-cream">{{ t('admin.home.beta') }}</h2>
                <dl class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div v-for="stat in stats" :key="stat.label"><dt class="text-sm text-muted">{{ stat.label }}</dt><dd class="text-2xl font-extrabold text-cream"><a v-if="stat.path" :href="stat.path" class="tap-44 hover:text-accent" @click.prevent="navigate(stat.path)">{{ stat.value }}</a><template v-else>{{ stat.value }}</template></dd></div>
                </dl>
            </section>
            <StoreTestersCard v-if="me.role === 'admin'" />
            <section v-for="g in groups" :key="g.key" class="space-y-3" :aria-labelledby="'home-group-' + g.key">
                <h2 :id="'home-group-' + g.key" class="eyebrow text-muted">{{ t(g.label) }}</h2>
                <ul class="grid gap-4 sm:grid-cols-2">
                    <li v-for="s in g.sections.filter((x) => x.name !== 'home')" :key="s.name">
                        <a :href="s.path" class="card-interactive flex items-start gap-4 p-5" @click.prevent="navigate(s.path)">
                            <Icon :name="s.icon" :size="24" class="mt-0.5 text-accent" />
                            <span><span class="block font-bold text-cream">{{ t(s.label) }}</span><span class="text-sm text-muted">{{ t(s.hint) }}</span></span>
                        </a>
                    </li>
                </ul>
            </section>
        </div>`,
    computed: {
        sudo() {
            return this.me.sudoUntil && new Date(this.me.sudoUntil) > new Date();
        },
        /** The second-factor check, back to Home after. */
        sudoPath: () => `/admin/second-factor?sudo=1&intended=${encodeURIComponent('/admin')}`,
        today: () => formatDate(new Date(), { weekday: 'long', day: 'numeric', month: 'long' }),
        todayIso: () => new Date().toLocaleDateString('sv'), // YYYY-MM-DD, this device's day
        /** Good morning (before noon), afternoon (before 6), or evening, by the first name. */
        greeting() {
            const hour = new Date().getHours();
            const name = (this.me.name ?? '').trim().split(/\s+/)[0] ?? '';
            return t(GREETINGS[hour < 12 ? 'morning' : hour < 18 ? 'afternoon' : 'evening'], { name });
        },
        /** The last 7 days (with the change on the 7 before, once the server sends `growth.previous`), then all-time songs. */
        growthTiles() {
            const g = this.overview.growth;
            const h = this.overview.harmonies ?? {};
            const week = (key, label) => {
                const before = g.previous?.[key];
                const moved = typeof before === 'number' ? change(g[key] ?? 0, before) : { dir: 'none', text: '-' };
                return { key, label: t(label), value: g[key] ?? 0, against: t('admin.home.growth.vsWeekBefore'), week: true, ...moved };
            };
            const total = (key, label) => ({ key, label: t(label), value: h[key] ?? 0, against: t('admin.home.growth.allTime'), week: false, dir: 'none', text: '' });
            return [
                week('signUps', tk('admin.home.growth.signUps')),
                week('upgrades', tk('admin.home.growth.upgrades')),
                week('activeSingers', tk('admin.home.growth.active')),
                total('finished', tk('admin.home.growth.finished')),
                total('secondSongs', tk('admin.home.growth.second')),
            ];
        },
        stats() {
            const b = this.overview.beta;
            return [
                { label: t('admin.home.waiting'), value: formatNumber(b.waiting), path: '/admin/review' },
                { label: t('admin.home.unread'), value: formatNumber(b.unreadFeedback), path: '/admin/feedback' },
                { label: t('admin.home.singers'), value: t('admin.home.consentedOf', { consented: formatNumber(b.consented), singers: formatNumber(b.singers) }), path: this.me.role === 'admin' ? '/admin/singers' : null },
                { label: t('admin.home.realVoices'), value: t('admin.home.partsOf', { done: formatNumber(b.partsWithVoices), total: formatNumber(b.parts) }), path: '/admin/guides' },
                { label: t('admin.home.guides'), value: formatNumber(b.guides), path: '/admin/guides' },
            ];
        },
    },
    methods: { navigate },
};

export const AdminView = {
    components: { Icon, Logo, Notice, RequestsSection, HomeSection, SafetySection, SwitchesSection, AuditSection, ReviewSection, SingersSection, GuidesSection, FeedbackSection, ReportsSection, IdeasSection, MudeoSection, SupportSection, LinksSection, PeopleSection, PostsSection, OverviewSection, SourcesSection, ChoirGroupsSection, RevenueSection, CorrectionsSection, LiveSection, SearchSection, SchoolSection, CohortsSection, ActivitySection, CodeWatchSection, ExperimentsSection, OutreachSection, UsageSection, FunnelSection, DropoffsSection, SharingSection, BreadthSection },
    data: () => ({ me: null, failed: false, overview: null, overviewFailed: false }),
    setup: () => ({ route, formatNumber }),
    template: `
        <div class="min-h-dvh bg-ink">
            <!-- One bar (Hillel's redesign, 2026-10-30): the groups with what's waiting in them, then Back to app
                 and the account. Calm in both skins. A phone puts the groups on their own scrolling row. -->
            <header class="sticky top-0 z-30 border-b border-line bg-ink/90 backdrop-blur">
                <!-- The site header's grid (app-wrap; the logo's line 60 px, 80 from 900 px): the logo where the site's is. -->
                <div class="app-wrap flex flex-wrap items-center gap-x-5 md:flex-nowrap">
                    <a href="/admin" class="flex h-[60px] shrink-0 items-center gap-2 min-[900px]:h-20" @click.prevent="navigate('/admin')"><Logo /><span class="rounded-md border border-line px-1.5 py-0.5 text-xs font-semibold text-muted">{{ t('admin.title') }}</span></a>
                    <nav v-if="me" class="order-last -mx-1 w-full min-w-0 pb-2 md:order-none md:mx-0 md:w-auto md:pb-0" :aria-label="t('admin.nav.groupsLabel')">
                        <ul class="flex gap-1 overflow-x-auto px-1 pb-1 [scrollbar-width:none] md:px-0 md:pb-0" data-nav-groups>
                            <li v-for="g in groups" :key="g.key" class="shrink-0">
                                <a :href="g.sections[0].path" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium transition" :class="group === g.key ? 'bg-(--fill-2) text-cream' : 'text-muted hover:bg-(--fill) hover:text-cream'" :aria-current="group === g.key ? 'true' : null" @click.prevent="navigate(g.sections[0].path)">
                                    {{ t(g.label) }}<span v-if="g.count" class="min-w-5 rounded-full bg-accent px-1.5 text-center text-xs font-bold text-accent-ink tabular-nums" :aria-label="t('admin.nav.groupCount', { count: g.count })" data-group-count>{{ formatNumber(g.count) }}</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <div class="ms-auto flex shrink-0 items-center gap-2">
                        <a href="/" class="btn-ghost btn-sm" :aria-label="t('admin.backToApp')" @click.prevent="toApp"><span class="hidden sm:inline">{{ t('admin.backToApp') }}</span><span aria-hidden="true" class="sm:ms-1 rtl:-scale-x-100">↗</span></a>
                        <!-- The account: its initials, opening its name and Sign out. -->
                        <details v-if="me" ref="account" class="relative" @keydown.escape="closeAccount">
                            <summary class="grid size-9 cursor-pointer list-none place-items-center rounded-full bg-(--fill-2) text-xs font-bold text-cream [&::-webkit-details-marker]:hidden" :aria-label="t('admin.accountMenu', { name: me.name })" data-admin-account><span aria-hidden="true" translate="no">{{ initials }}</span></summary>
                            <div class="absolute end-0 top-full z-40 mt-2 w-56 space-y-2 rounded-2xl border border-line bg-panel p-3 shadow-xl">
                                <p class="truncate text-sm font-semibold text-cream" translate="no">{{ me.name }}</p>
                                <button type="button" class="btn-ghost btn-sm w-full justify-start" @click="signOut">{{ t('admin.signOut') }}</button>
                            </div>
                        </details>
                    </div>
                </div>
            </header>
            <!-- The open group's pages, as underline tabs (plain links: Tab reaches each, Enter opens it). The pages and
                 their content on the bar's grid too (app-wrap: Hillel, 2026-11-10, "make the app and admin header and page
                 width match the marketing site"), so the bar, the tabs, the content and the site share one edge. -->
            <nav v-if="me && shown.length > 1" class="app-wrap pt-6" :aria-label="t('admin.nav.pagesLabel', { group: t(groupLabel) })">
                <div class="border-b border-line">
                    <ul class="-mb-px flex gap-6 overflow-x-auto [scrollbar-width:none]" data-nav-sections>
                        <li v-for="s in shown" :key="s.name" class="shrink-0">
                            <a :href="s.path" class="inline-flex min-h-11 items-center gap-1.5 border-b-2 text-sm font-medium transition" :class="current === s.name ? 'border-accent text-cream' : 'border-transparent text-muted hover:text-cream'" :aria-current="current === s.name ? 'page' : null" @click.prevent="navigate(s.path)">
                                {{ t(s.label) }}<span v-if="countOf(s)" class="min-w-5 rounded-full bg-accent px-1.5 text-center text-xs font-bold text-accent-ink tabular-nums" :aria-label="t(s.words, { count: countOf(s) })" data-nav-count>{{ formatNumber(countOf(s)) }}</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            <main class="app-wrap py-8">
                <p v-if="!me && !failed" class="text-muted" role="status">{{ t('admin.loading') }}</p>
                <Notice v-else-if="failed" tone="bad">{{ t('admin.loadFailed') }} <button type="button" class="link" @click="load">{{ t('common.tryAgain') }}</button></Notice>
                <HomeSection v-else-if="current === 'home'" :me="me" :groups="groups" :overview="overview" :overview-failed="overviewFailed" @retry="loadOverview" />
                <SafetySection v-else-if="current === 'safety'" :me="me" />
                <SwitchesSection v-else-if="current === 'switches'" :me="me" />
                <AuditSection v-else-if="current === 'audit'" :me="me" />
                <ReviewSection v-else-if="current === 'review'" :me="me" />
                <SingersSection v-else-if="current === 'singers'" :me="me" />
                <PeopleSection v-else-if="current === 'people'" :me="me" />
                <OverviewSection v-else-if="current === 'growth'" :me="me" />
                <SourcesSection v-else-if="current === 'sources'" :me="me" />
                <ChoirGroupsSection v-else-if="current === 'groups'" :me="me" />
                <RevenueSection v-else-if="current === 'revenue'" :me="me" />
                <LiveSection v-else-if="current === 'live'" :me="me" />
                <CorrectionsSection v-else-if="current === 'corrections'" :me="me" @changed="loadOverview" />
                <SearchSection v-else-if="current === 'search'" :me="me" />
                <CohortsSection v-else-if="current === 'cohorts'" :me="me" />
                <ActivitySection v-else-if="current === 'activity'" :me="me" />
                <SchoolSection v-else-if="current === 'school'" :me="me" />
                <CodeWatchSection v-else-if="current === 'code-watch'" :me="me" />
                <ExperimentsSection v-else-if="current === 'experiments'" :me="me" />
                <OutreachSection v-else-if="current === 'outreach'" :me="me" />
                <UsageSection v-else-if="current === 'usage'" :me="me" />
                <FunnelSection v-else-if="current === 'funnel'" :me="me" />
                <DropoffsSection v-else-if="current === 'dropoffs'" :me="me" />
                <SharingSection v-else-if="current === 'sharing'" :me="me" />
                <BreadthSection v-else-if="current === 'breadth'" :me="me" />
                <GuidesSection v-else-if="current === 'guides'" :me="me" />
                <FeedbackSection v-else-if="current === 'feedback'" :me="me" />
                <ReportsSection v-else-if="current === 'reports'" :me="me" />
                <IdeasSection v-else-if="current === 'ideas'" :me="me" />
                <RequestsSection v-else-if="current === 'requests'" :me="me" />
                <SupportSection v-else-if="current === 'support'" :me="me" />
                <LinksSection v-else-if="current === 'links'" :me="me" />
                <PostsSection v-else-if="current === 'posts'" :me="me" />
                <MudeoSection v-else-if="current === 'mudeo'" :me="me" />
            </main>
        </div>`,
    computed: {
        sections() {
            return SECTIONS.filter((s) => s.role === 'editor' || this.me?.role === 'admin');
        },
        /** The groups this person can open, each with its screens and what's waiting in them. */
        groups() {
            return GROUPS.map((g) => {
                const sections = this.sections.filter((s) => s.group === g.key);
                return { ...g, sections, count: sections.reduce((sum, s) => sum + this.countOf(s), 0) };
            }).filter((g) => g.sections.length);
        },
        group() {
            return this.sections.find((s) => s.name === this.current)?.group ?? 'inbox';
        },
        shown() {
            return this.groups.find((g) => g.key === this.group)?.sections ?? [];
        },
        groupLabel() {
            return GROUPS.find((g) => g.key === this.group)?.label ?? GROUPS[0].label;
        },
        /** The account's initials for the bar (as the app makes them from a name). */
        initials() {
            const words = (this.me?.name ?? '').trim().split(/\s+/).filter(Boolean);
            return (words.length > 1 ? words[0][0] + words.at(-1)[0] : (words[0] ?? '?').slice(0, 2)).toUpperCase();
        },
        current() {
            const asked = route.params.section ?? 'home';
            return this.sections.some((s) => s.name === asked) ? asked : 'home';
        },
    },
    watch: {
        // The counts follow what was just done (a take decided, feedback read).
        current: {
            immediate: true,
            handler() {
                if (this.me) this.loadOverview();
                this.revealNav();
            },
        },
    },
    created() {
        this.load();
    },
    methods: {
        navigate,
        /** A phone's rows of groups and sections scroll: the open ones are brought into view, never cut off. */
        revealNav() {
            nextTick(() => document.querySelectorAll('[data-nav-sections] [aria-current=page], [data-nav-groups] [aria-current=true]').forEach((el) => el.scrollIntoView({ block: 'nearest', inline: 'nearest' })));
        },
        async load() {
            this.failed = false;
            try {
                this.me = (await api.admin.me()).data;
                this.revealNav();
                this.loadOverview();
            } catch (error) {
                if (!sendToSignIn(error)) {
                    console.error('admin.me failed', error);
                    this.failed = true;
                }
            }
        },
        /** The home's numbers, and the nav's counts (what's waiting and unread). */
        async loadOverview() {
            this.overviewFailed = false;
            try {
                this.overview = (await api.admin.overview()).data;
                this.revealNav(); // the counts widened the rows
            } catch (error) {
                console.warn('The overview failed to load', error); // the admin works without it
                this.overviewFailed = true;
            }
        },
        countOf(s) {
            const b = this.overview?.beta;
            return s.count?.({ waiting: b?.waiting ?? 0, unread: b?.unreadFeedback ?? 0, corrections: this.overview?.corrections?.new ?? 0, home: homeCount(this.overview) }) ?? 0;
        },
        closeAccount() {
            this.$refs.account?.removeAttribute('open');
            this.$refs.account?.querySelector('summary')?.focus();
        },
        /** Back to the app (the projects page); the admin stays signed in. */
        toApp() {
            navigate('/');
        },
        async signOut() {
            try {
                await adminSignOut();
                location.assign('/admin/sign-in');
            } catch (error) {
                console.error('Sign-out failed', error);
                toast(t('admin.signOutFailed'), { tone: 'error', seconds: 10 });
            }
        },
    },
};
