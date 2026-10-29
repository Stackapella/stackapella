// The admin (/admin): Hillel's and the Editors' screens, after iaw's admin (docs/conventions.md).
// The server's own pages sign people in and check the second factor; this reads admin.me and sends
// anyone it refuses to them. Every admin route is also checked on the server: hiding a screen is
// never the protection. This code loads only when /admin is opened, and is never precached.

import { api, adminSignOut } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { navigate, route } from '../router.js';
import { Icon, Logo } from '../components/icon.js';
import { Notice, StatusChip } from '../components/form.js';
import { toast } from '../components/ui.js';
import { AuditSection } from './audit.js';
import { FeedbackSection } from './feedback.js';
import { ReportsSection } from './reports.js';
import { MudeoSection } from './mudeo.js';
import { GuidesSection } from './guides.js';
import { ReviewSection } from './review.js';
import { SafetySection } from './safety.js';
import { SingersSection } from './singers.js';
import { PeopleSection } from './people.js';
import { UsageSection } from './usage.js';
import { FunnelSection } from './funnel.js';
import { SupportSection } from './support.js';
import { LinksSection } from './links.js';
import { PostsSection, socialWaiting } from './posts.js';
import { sendToSignIn } from './auth.js';
import { StoreTestersCard } from './testers.js';

// The menu in five groups (Hillel: "please re-org the menu header in admin"): what needs you today,
// the people, the content, how it's growing, and the system. The header shows the groups, then the
// open group's screens; counts are the ones the overview and the posts queue already give.
const GROUPS = [
    { key: 'today', label: tk('admin.nav.group.today') },
    { key: 'people', label: tk('admin.nav.group.people') },
    { key: 'content', label: tk('admin.nav.group.content') },
    { key: 'growth', label: tk('admin.nav.group.growth') },
    { key: 'system', label: tk('admin.nav.group.system') },
];

const SECTIONS = [
    { name: 'home', group: 'today', path: '/admin', label: tk('admin.nav.home'), hint: null, icon: 'home', role: 'editor' },
    { name: 'review', group: 'today', path: '/admin/review', label: tk('admin.nav.review'), hint: tk('admin.nav.reviewHint'), icon: 'mic', role: 'editor', count: (c) => c.waiting, words: tk('admin.nav.waitingCount') },
    { name: 'feedback', group: 'today', path: '/admin/feedback', label: tk('admin.nav.feedback'), hint: tk('admin.nav.feedbackHint'), icon: 'info', role: 'editor', count: (c) => c.unread, words: tk('admin.nav.unreadCount') },
    { name: 'support', group: 'today', path: '/admin/support', label: tk('admin.nav.support'), hint: tk('admin.nav.supportHint'), icon: 'info', role: 'admin' },
    { name: 'reports', group: 'today', path: '/admin/reports', label: tk('admin.nav.reports'), hint: tk('admin.nav.reportsHint'), icon: 'alert', role: 'admin' },
    { name: 'people', group: 'people', path: '/admin/people', label: tk('admin.nav.people'), hint: tk('admin.nav.peopleHint'), icon: 'list', role: 'admin' },
    { name: 'singers', group: 'people', path: '/admin/singers', label: tk('admin.nav.singers'), hint: tk('admin.nav.singersHint'), icon: 'headphones', role: 'admin' },
    { name: 'safety', group: 'people', path: '/admin/safety', label: tk('admin.nav.safety'), hint: tk('admin.nav.safetyHint'), icon: 'shield', role: 'admin' },
    { name: 'guides', group: 'content', path: '/admin/guides', label: tk('admin.nav.guides'), hint: tk('admin.nav.guidesHint'), icon: 'note', role: 'editor' },
    { name: 'posts', group: 'content', path: '/admin/posts', label: tk('admin.nav.posts'), hint: tk('admin.nav.postsHint'), icon: 'sparkle', role: 'admin', count: (c) => c.posts, words: tk('admin.posts.waitingCount') },
    { name: 'usage', group: 'growth', path: '/admin/usage', label: tk('admin.nav.usage'), hint: tk('admin.nav.usageHint'), icon: 'sparkle', role: 'admin' },
    { name: 'funnel', group: 'growth', path: '/admin/funnel', label: tk('admin.nav.funnel'), hint: tk('admin.nav.funnelHint'), icon: 'list', role: 'admin' },
    { name: 'links', group: 'growth', path: '/admin/links', label: tk('admin.nav.links'), hint: tk('admin.nav.linksHint'), icon: 'arrow', role: 'admin' },
    { name: 'mudeo', group: 'growth', path: '/admin/mudeo', label: tk('admin.nav.mudeo'), hint: tk('admin.nav.mudeoHint'), icon: 'arrow', role: 'admin' },
    { name: 'audit', group: 'system', path: '/admin/audit', label: tk('admin.nav.audit'), hint: tk('admin.nav.auditHint'), icon: 'list', role: 'admin' },
];

const HomeSection = {
    components: { Icon, StatusChip, StoreTestersCard },
    props: { me: { type: Object, required: true }, groups: { type: Array, required: true }, overview: { type: Object, default: null } },
    template: `
        <div class="space-y-6">
            <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.home.title') }}</h1>
            <section class="card p-6" aria-labelledby="me-heading">
                <h2 id="me-heading" class="text-lg font-bold text-cream">{{ t('admin.home.signedInAs', { name: me.name }) }}</h2>
                <p class="mt-1 text-sm text-muted" translate="no">{{ me.email }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <StatusChip tone="info" :label="me.role === 'admin' ? t('admin.role.admin') : t('admin.role.editor')" />
                    <StatusChip :tone="sudo ? 'good' : 'muted'" :label="sudo ? t('admin.home.sudoUntil', { time: formatDate(me.sudoUntil, { timeStyle: 'short' }) }) : t('admin.home.noSudo')" />
                </div>
            </section>
            <section v-if="overview" class="card p-6" aria-labelledby="beta-heading">
                <h2 id="beta-heading" class="text-lg font-bold text-cream">{{ t('admin.home.beta') }}</h2>
                <dl class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div v-for="stat in stats" :key="stat.label"><dt class="text-sm text-muted">{{ stat.label }}</dt><dd class="text-2xl font-extrabold text-cream"><a v-if="stat.path" :href="stat.path" class="hover:text-accent" @click.prevent="navigate(stat.path)">{{ stat.value }}</a><template v-else>{{ stat.value }}</template></dd></div>
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
    components: { Icon, Logo, Notice, HomeSection, SafetySection, AuditSection, ReviewSection, SingersSection, GuidesSection, FeedbackSection, ReportsSection, MudeoSection, SupportSection, LinksSection, PeopleSection, PostsSection, UsageSection, FunnelSection },
    data: () => ({ me: null, failed: false, overview: null }),
    setup: () => ({ route, socialWaiting, formatNumber }),
    template: `
        <div class="min-h-dvh bg-ink">
            <header class="sticky top-0 z-30 border-b border-line bg-ink/90 backdrop-blur">
                <div class="container-page flex min-h-14 flex-wrap items-center gap-x-3 gap-y-1 py-2">
                    <a href="/admin" class="flex shrink-0 items-center gap-2" @click.prevent="navigate('/admin')"><Logo :size="20" /><span class="font-bold text-muted">{{ t('admin.title') }}</span></a>
                    <nav v-if="me" class="order-last w-full space-y-1 pb-1" :aria-label="t('admin.title')">
                        <!-- The groups, each with what's waiting in it; then the open group's screens. Calm in both skins. -->
                        <ul class="-mx-1 flex gap-1 overflow-x-auto px-1 [scrollbar-width:none]" data-nav-groups>
                            <li v-for="g in groups" :key="g.key" class="shrink-0">
                                <a :href="g.sections[0].path" class="inline-flex min-h-9 items-center gap-1.5 rounded-full px-3.5 text-sm font-semibold transition" :class="group === g.key ? 'bg-(--fill-2) text-cream' : 'text-muted hover:bg-(--fill) hover:text-cream'" :aria-current="group === g.key ? 'true' : null" @click.prevent="navigate(g.sections[0].path)">
                                    {{ t(g.label) }}<span v-if="g.count" class="rounded-full bg-(--fill-3) px-1.5 text-xs font-bold text-cream tabular-nums" :aria-label="t('admin.nav.groupCount', { count: g.count })" data-group-count>{{ formatNumber(g.count) }}</span>
                                </a>
                            </li>
                        </ul>
                        <ul class="-mx-1 flex gap-1 overflow-x-auto px-1 [scrollbar-width:none]" data-nav-sections>
                            <li v-for="s in shown" :key="s.name" class="shrink-0">
                                <a :href="s.path" class="inline-flex min-h-9 items-center gap-1.5 rounded-full px-3 text-sm font-medium transition" :class="current === s.name ? 'bg-(--fill) text-cream' : 'text-muted hover:text-cream'" :aria-current="current === s.name ? 'page' : null" @click.prevent="navigate(s.path)">
                                    <Icon :name="s.icon" :size="16" /> {{ t(s.label) }}<span v-if="countOf(s)" class="rounded-full bg-(--fill-3) px-1.5 text-xs font-bold text-cream tabular-nums" :aria-label="t(s.words, { count: countOf(s) })" :data-nav-count="s.name === 'posts' ? '' : null">{{ formatNumber(countOf(s)) }}{{ s.name === 'posts' && socialWaiting.more ? '+' : '' }}</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <div class="ms-auto flex items-center gap-2">
                        <span v-if="me" class="hidden text-sm text-muted md:inline" translate="no">{{ me.name }}</span>
                        <a href="/" class="btn-ghost btn-sm" @click.prevent="toApp">{{ t('admin.backToApp') }}</a>
                        <button v-if="me" type="button" class="btn-ghost btn-sm" @click="signOut">{{ t('admin.signOut') }}</button>
                    </div>
                </div>
            </header>
            <main class="container-page py-8">
                <p v-if="!me && !failed" class="text-muted" role="status">{{ t('admin.loading') }}</p>
                <Notice v-else-if="failed" tone="bad">{{ t('admin.loadFailed') }} <button type="button" class="link" @click="load">{{ t('common.tryAgain') }}</button></Notice>
                <HomeSection v-else-if="current === 'home'" :me="me" :groups="groups" :overview="overview" />
                <SafetySection v-else-if="current === 'safety'" :me="me" />
                <AuditSection v-else-if="current === 'audit'" :me="me" />
                <ReviewSection v-else-if="current === 'review'" :me="me" />
                <SingersSection v-else-if="current === 'singers'" :me="me" />
                <PeopleSection v-else-if="current === 'people'" :me="me" />
                <UsageSection v-else-if="current === 'usage'" :me="me" />
                <FunnelSection v-else-if="current === 'funnel'" :me="me" />
                <GuidesSection v-else-if="current === 'guides'" :me="me" />
                <FeedbackSection v-else-if="current === 'feedback'" :me="me" />
                <ReportsSection v-else-if="current === 'reports'" :me="me" />
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
            return this.sections.find((s) => s.name === this.current)?.group ?? 'today';
        },
        shown() {
            return this.groups.find((g) => g.key === this.group)?.sections ?? [];
        },
        current() {
            const asked = route.params.section ?? 'home';
            return this.sections.some((s) => s.name === asked) ? asked : 'home';
        },
    },
    watch: {
        // The counts follow what was just done (a take decided, feedback read).
        current() {
            if (this.me) this.loadOverview();
        },
    },
    created() {
        this.load();
    },
    methods: {
        navigate,
        async load() {
            this.failed = false;
            try {
                this.me = (await api.admin.me()).data;
                if (this.me.role === 'admin') socialWaiting.refresh(); // the nav's count
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
            try {
                this.overview = (await api.admin.overview()).data;
            } catch (error) {
                console.warn('The overview failed to load', error); // the admin works without it
            }
        },
        countOf(s) {
            const b = this.overview?.beta;
            return s.count?.({ waiting: b?.waiting ?? 0, unread: b?.unreadFeedback ?? 0, posts: socialWaiting.count ?? 0 }) ?? 0;
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
