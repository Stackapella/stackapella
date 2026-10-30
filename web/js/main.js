import { createApp, defineAsyncComponent, reactive, watch } from 'vue';
import { watchErrors } from './errors.js';
import { setDraftsFor } from './templates.js';
import { api, apiEvents } from './api.js';
import { storageEvents } from './db.js';
import { allWords, formatDate, formatNumber, loadLanguage, localized, t, timeAgo, tk } from './i18n.js';
import { navigate, route } from './router.js';
import { session } from './stores/session.js';
import { toast, Toasts } from './components/ui.js';
import { ConfirmHost } from './components/confirm.js';
import { watchPlan } from './stores/plan.js';
import { CloudConflicts } from './components/cloud.js';
import { LearnView } from './views/learn.js';
import { cameBackFromSignIn } from './components/account.js';
import { BirthHost } from './components/birth.js';
import { NewVersion } from './components/version.js';
import { MicHelpHost } from './components/mic-help.js';
import { MicCheckHost } from './components/mic-check.js';
import './game/fonts.js'; // game mode's two fonts, fetched only once it's on
import { checkVersion, newVersionFound, version, watchVersion } from './version.js';
import { listenForInstall } from './components/install.js';
import { fill, fitSegments, radioArrows } from './components/controls.js';
import { account, keepLinkToken, loadAccount, loadAuthConfig, rememberRef } from './stores/account.js';
import { signedInSync, startSync } from './stores/cloud.js';
import { adoptAccountAppearance, appearance, game } from './stores/appearance.js';
import { paint, paintInk, paintText } from './colors.js';
import { loadOutbox, outbox, sendAll } from './beta-outbox.js';
import { count } from './usage.js';
import { arrive, first } from './funnel.js';
import { lazyView } from './lazy.js';
import { LOADING_DELAY_MS, screenLoading } from './components/screen-loading.js';
import { learning, loadPlusCourses } from './stores/learning.js';
import { courseVisible } from './courses.js';
import { nameScreens } from './screen-name.js';
import { COOL_DOWN } from './stores/learning.js';

// Views load when they're opened, from the same version as the page (js/lazy.js reloads otherwise).
// While one loads, its frame shows after a moment (components/screen-loading.js), never a blank page.
const ahead = new Map(); // each view's loader, for fetching it ahead (below)
function screen(load, frame = {}) {
    const loader = lazyView(load);
    const view = defineAsyncComponent({ loader, loadingComponent: screenLoading(frame), delay: LOADING_DELAY_MS });
    ahead.set(view, loader);
    return view;
}
const plusCourseTitle = () => localized(learning.catalogue.find((c) => c.id === route.params.id)?.title) || t('courses.title');
// The admin and the styleguide load only when opened (and are never precached for singers).
const AdminView = defineAsyncComponent(lazyView(() => import('./admin/shell.js').then((m) => m.AdminView)));
const StyleguideView = defineAsyncComponent(lazyView(() => import('./admin/styleguide.js').then((m) => m.StyleguideView)));
// The views a first visit's lesson never needs load when they're opened (a phone on a slow line gets to
// its first note sooner).
// The home and sign-in too (the coordinator's first-load step): lesson 1, the landing's Start, needs neither.
const ProjectsView = screen(() => import('./views/projects.js').then((m) => m.ProjectsView), { home: true });
const AuthView = screen(() => import('./views/auth.js').then((m) => m.AuthView));
const StudioView = screen(() => import('./views/studio.js').then((m) => m.StudioView));
const SingView = screen(() => import('./views/sing.js').then((m) => m.SingView));
const SettingsView = screen(() => import('./views/settings.js').then((m) => m.SettingsView), { title: tk('settings.title') });
const StorageView = screen(() => import('./views/storage.js').then((m) => m.StorageView), { title: tk('cloud.storage.title') });
const VoiceView = screen(() => import('./views/voice.js').then((m) => m.VoiceView), { title: tk('voice.title') });
const WorkoutView = screen(() => import('./views/workout.js').then((m) => m.WorkoutView));
const PlusView = screen(() => import('./views/plus.js').then((m) => m.PlusView), { title: tk('plus.title') });
const RemindersView = screen(() => import('./views/reminders.js').then((m) => m.RemindersView), { title: tk('reminders.title') });
const CourseView = screen(() => import('./views/course.js').then((m) => m.CourseView), { title: tk('course.hub.title') });
const PlusCourseView = screen(() => import('./views/plus-course.js').then((m) => m.PlusCourseView), { title: plusCourseTitle });
const BetaView = screen(() => import('./views/beta.js').then((m) => m.BetaView));
// The creator and a template's page load when they're opened (most visits never need them).
const CreateView = screen(() => import('./views/create.js').then((m) => m.CreateView));
const TemplateView = screen(() => import('./views/template.js').then((m) => m.TemplateView));
const JoinView = screen(() => import('./views/join.js').then((m) => m.JoinView));
const EarlyView = screen(() => import('./views/early.js').then((m) => m.EarlyView));
const GroupsView = screen(() => import('./views/groups.js').then((m) => m.GroupsView));
const ChallengeView = screen(() => import('./views/challenge.js').then((m) => m.ChallengeView));
const GroupView = screen(() => import('./views/groups.js').then((m) => m.GroupView));
const ChoirJoinView = screen(() => import('./views/groups.js').then((m) => m.ChoirJoinView));
// Songs arriving from the old address (docs/app-subdomain-plan.md).
const MovedView = screen(() => import('./views/moved.js').then((m) => m.MovedView));
const ContactView = screen(() => import('./views/contact.js').then((m) => m.ContactView), { title: tk('contact.title') });
// Ideas (docs/ideas-app-plan.md), behind the server's flag: the shelf and one tune.
// Start fresh on this device (/start-fresh): for testing on a phone, linked from nowhere (/reset is the password reset's).
const ResetView = screen(() => import('./views/reset.js').then((m) => m.ResetView));
const IdeasView = screen(() => import('./views/ideas.js').then((m) => m.IdeasView));
const IdeaView = screen(() => import('./views/ideas.js').then((m) => m.IdeaView));
const FIRST_INSTALL_WAIT_MS = 15000; // a timer for when to install, never for audio

const App = {
    components: { ProjectsView, StudioView, SingView, AuthView, SettingsView, StorageView, VoiceView, WorkoutView, PlusView, RemindersView, CloudConflicts, LearnView, CourseView, PlusCourseView, BirthHost, NewVersion, AdminView, StyleguideView, BetaView, CreateView, TemplateView, JoinView, EarlyView, GroupsView, GroupView, ChoirJoinView, MovedView, ContactView, IdeasView, IdeaView, ResetView, Toasts, ConfirmHost, MicHelpHost, MicCheckHost, ChallengeView },
    setup: () => ({ route, COOL_DOWN }),
    template: `
        <AdminView v-if="route.name === 'admin'" />
        <!-- Every screen's one main landmark (the admin has its own shell). -->
        <main v-else id="main">
        <StudioView v-if="route.name === 'studio'" :key="route.params.id" :id="route.params.id" />
        <SingView v-else-if="route.name === 'sing'" :key="'sing-' + route.params.id" :id="route.params.id" />
        <StyleguideView v-else-if="route.name === 'styleguide'" />
        <BetaView v-else-if="route.name === 'beta'" :token="route.params.token ?? null" />
        <CreateView v-else-if="route.name === 'create'" :draft-id="route.params.draft ?? null" />
        <TemplateView v-else-if="route.name === 'template'" :key="'t-' + route.params.id" :id="route.params.id" />
        <ChallengeView v-else-if="route.name === 'challenge'" :key="'c-' + route.params.token" :token="route.params.token" />
        <JoinView v-else-if="route.name === 'join'" :key="'j-' + route.params.token" :token="route.params.token" />
        <EarlyView v-else-if="route.name === 'early'" :key="'e-' + route.params.code" :code="route.params.code" />
        <EarlyView v-else-if="route.name === 'invite'" :key="'i-' + route.params.code" :code="route.params.code" kind="invite" />
        <GroupsView v-else-if="route.name === 'groups'" />
        <GroupView v-else-if="route.name === 'group'" :key="'g-' + route.params.id" :id="route.params.id" />
        <ChoirJoinView v-else-if="route.name === 'choir'" :key="'c-' + route.params.token" :token="route.params.token" />
        <MovedView v-else-if="route.name === 'moved'" />
        <ContactView v-else-if="route.name === 'contact'" />
        <IdeasView v-else-if="route.name === 'ideas'" />
        <ResetView v-else-if="route.name === 'fresh'" />
        <IdeaView v-else-if="route.name === 'idea'" :key="'idea-' + route.params.id" :id="route.params.id" />
        <AuthView v-else-if="route.name === 'auth'" :key="route.params.page" />
        <SettingsView v-else-if="route.name === 'settings'" />
        <StorageView v-else-if="route.name === 'storage'" />
        <VoiceView v-else-if="route.name === 'voice'" />
        <WorkoutView v-else-if="route.name === 'workout'" />
        <PlusView v-else-if="route.name === 'plus'" />
        <RemindersView v-else-if="route.name === 'reminders'" />
        <CourseView v-else-if="route.name === 'course'" />
        <LearnView v-else-if="route.name === 'learn'" :key="'learn-' + route.params.id" :id="route.params.id" />
        <!-- Voice care's cool-down (docs/voice-care-plan.md): a standalone, unscored lesson. -->
        <LearnView v-else-if="route.name === 'cooldown'" key="cooldown" :id="COOL_DOWN" />
        <PlusCourseView v-else-if="route.name === 'plusCourse'" :key="'course-' + route.params.id" :id="route.params.id" />
        <ProjectsView v-else />
        </main>
        <BirthHost />
        <Toasts><NewVersion /></Toasts>
        <ConfirmHost />
        <MicHelpHost />
        <MicCheckHost />
        <CloudConflicts />`,
};

storageEvents.blocked = () => toast(t('common.closeOtherTabs'), { seconds: 30 });
// The API says this app is too old for it (426): "A new version is ready", as after a deploy.
apiEvents.outdated = () => newVersionFound();
watchVersion();
apiEvents.ageUnconfirmed = () => {
    if (account.me) account.me = { ...account.me, birthGiven: false }; // When were you born? asks
};
storageEvents.replaced = () => toast(t('common.updatedElsewhere'), { seconds: 600, actions: [{ label: t('common.reload'), run: () => location.reload() }] });

// Someone who has used the app gets the app at "/" from now on, not the landing page the server
// shows first-time visitors (a functional cookie: no id, nothing about the person).
document.cookie = `sa_app=1; Max-Age=31536000; Path=/; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;

// A link from an email (or an invite) carries a token: it leaves the address bar before anything is
// fetched, so no request's referrer carries it, and waits in the tab for its screen.
keepLinkToken();

// Words first: every screen reads them through t() (docs/architecture.md, "Languages"). A first
// visit (no service worker yet, as js/preload.js decides) starts with lesson 1's own words, and
// fetches the rest once the page has loaded and the browser is idle.
const lessonFirst = !navigator.serviceWorker?.controller;
await loadLanguage(undefined, { lessonFirst }).catch((error) => console.error('The words did not load', error));
// Then (Hillel: "when I click some pages (ie settings or a course) the screen is blank for a second"),
// the screens most often opened next are fetched ahead, one at a time while the browser is idle, so
// they open at once: after the words, once the first screen is up (timers for the page, never audio).
const whenIdle = (run) => ('requestIdleCallback' in window ? requestIdleCallback(run, { timeout: 8000 }) : setTimeout(run, 1000));
async function fetchAhead() {
    await allWords();
    const ahead1 = (view) => new Promise((done) => whenIdle(() => ahead.get(view).prefetch().then(done)));
    for (const view of [ProjectsView, SettingsView, CourseView]) await ahead1(view);
    // A Plus course's page and the courses' data, when Plus courses show (asked now: the account has
    // loaded). Guests see them too once auth.config says so (courses.js courseVisible).
    const config = await loadAuthConfig().catch(() => null);
    if (courseVisible({ status: 'live' }, account.me, config)) {
        await ahead1(PlusCourseView);
        await new Promise((done) => whenIdle(() => loadPlusCourses().then(done)));
    }
    for (const view of [SingView, StudioView]) await ahead1(view);
}
const afterLoad = (run) => (document.readyState === 'complete' ? whenIdle(run) : window.addEventListener('load', () => whenIdle(run), { once: true }));
afterLoad(fetchAhead);
const app = createApp(App);
app.directive('fill', fill);
watchErrors(app); // to Sentry through /log/errors, once auth.config gives the DSN
// A part's colour as the chosen theme paints it (and the words on it, and small words in it): read
// through `appearance.version`, so every screen repaints when the theme changes.
const themed = (fn) => (color) => (appearance.version, fn(color));
Object.assign(app.config.globalProperties, { t, formatNumber, formatDate, timeAgo, paint: themed(paint), paintText: themed(paintText), paintInk: themed(paintInk) });
app.mount('#app');
nameScreens(); // each screen's heading as the tab's title, and focus on it after a move
count('app_open'); // each time the app is opened (a load): anonymous, with no ID (js/usage.js)
count(game() ? 'open_game' : 'open_serious'); // and in which skin

// Who's signed in (never needed to learn or record), and what a Google or Apple sign-in came back with.
rememberRef();
listenForInstall();
radioArrows();
fitSegments(); // a segmented control too wide for its row becomes a grid, never a wrapped pill
loadAccount();
// The funnel (js/funnel.js): this open counted as an arrival or a return, with the experiment's arm
// drawn on a first open while one enrols. Without the settings, no arm: the open still counts.
loadAuthConfig().then(
    (config) => arrive({ experiment: config?.experiment ?? null }),
    (error) => {
        console.warn('The sign-in settings did not load', error);
        arrive();
    },
);
if (window.matchMedia?.('(display-mode: standalone)').matches || navigator.standalone === true) first('f_install'); // opened as the installed app
watch(() => account.me?.id, (id) => id && first('f_account'), { immediate: true });
watch(() => account.me?.name, (name) => name && first('f_name'), { immediate: true });
// Cloud save: signed in, the device's projects go to the account, and others' changes come back.
startSync();
watchPlan(); // back from Checkout (in another window, on a phone), the plan is read again
watch(() => account.me?.id, (id) => id && signedInSync().catch((error) => console.warn('Cloud save did not start', error)), { immediate: true });
// Before launch (coming soon), someone the server hasn't let in (no beta pass, not signed in) sees
// only signing in, the admin and the invite pages; anything else goes to sign-in. The server's gate
// covers full page loads; this covers moving around inside the app. Asked again after a sign-in.
const OPEN_BEFORE_LAUNCH = new Set(['auth', 'admin', 'early', 'invite', 'join', 'choir', 'teacher', 'moved', 'contact']);
const gate = reactive({ comingSoon: false });
async function readGate() {
    try {
        const { data } = await api.auth.config();
        gate.comingSoon = !!data.comingSoon;
        setDraftsFor({ prelaunch: !!data.prelaunch }); // before the launch, every song shows, drafts too
    } catch (error) {
        console.warn('Coming soon not known: the app stays open', error);
        gate.comingSoon = false;
    }
}
readGate();
watch(() => account.me?.id, (id) => id && readGate());
watch(() => account.me?.staff, (staff) => setDraftsFor({ admin: staff === 'admin' }), { immediate: true });
watch([() => gate.comingSoon, () => route.name, () => route.params.token], ([closed, name, token]) => {
    if (closed && !account.me && !OPEN_BEFORE_LAUNCH.has(name) && !(name === 'beta' && token)) navigate('/signin', { replace: true });
});

// Signed in on a device that hasn't chosen a look: the one chosen on another device.
watch(() => account.me?.appearance, (theirs) => adoptAccountAppearance(theirs), { immediate: true });
cameBackFromSignIn().catch((error) => console.error('Reading the sign-in failed', error));

// A beta take recorded offline, or interrupted while sending, goes now that the app is open again.
loadOutbox()
    .then(() => outbox.takes.some((take) => take.state === 'waiting') && sendAll())
    .catch((error) => console.error('Sending waiting takes failed', error));

// Hillel: no zooming by accident. A double tap never zooms (touch-manipulation), and a pinch on a
// canvas (the lane, the charts) doesn't zoom the page (touch-pan-y on each). Pinch-zoom elsewhere
// stays: blocking it would break the accessibility statement (and iOS ignores it anyway).
document.documentElement.classList.add('touch-manipulation');

// The service worker makes the app installable and lets it open offline.
if ('serviceWorker' in navigator) {
    // A new version's worker taking over this page: "A new version is ready" (never a reload by
    // itself, which could cut into singing or a save). A first install, with no worker before, isn't
    // one, and nor is this page's own build's worker taking over after a Refresh (Hillel had to click
    // Refresh twice: the reloaded page, already the new build, said so again).
    const hadWorker = !!navigator.serviceWorker.controller;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        const build = new URL(navigator.serviceWorker.controller?.scriptURL ?? location.href).searchParams.get('build');
        if (hadWorker && build && build !== version.loaded) newVersionFound();
    });
    // Registered for the server's build (sw.js keeps one cache a build); with the build unknown
    // (offline), the worker in charge stays.
    // A first visit (no worker yet) installs it once the page has loaded and gone quiet, and, when it
    // opened on a lesson (the landing's Start), once that lesson is left or the page hidden: its
    // precache (the whole app, a few megabytes) never competes with a first lesson on a slow line,
    // where the mic's own files wait behind it (measured: 9 s from the tap to listening, not 0.5 s).
    const quiet = () =>
        new Promise((resolve) => {
            const idle = () => ('requestIdleCallback' in window ? requestIdleCallback(() => resolve(), { timeout: FIRST_INSTALL_WAIT_MS }) : setTimeout(resolve, FIRST_INSTALL_WAIT_MS));
            if (document.readyState === 'complete') idle();
            else addEventListener('load', idle, { once: true });
        });
    const lessonLeft = () =>
        route.name !== 'learn'
            ? Promise.resolve()
            : new Promise((resolve) => {
                  const lesson = route.params.id;
                  const stop = watch(
                      () => route.name !== 'learn' || route.params.id !== lesson,
                      (left) => left && done(),
                  );
                  const hidden = () => document.visibilityState === 'hidden' && done();
                  function done() {
                      stop();
                      document.removeEventListener('visibilitychange', hidden);
                      resolve();
                  }
                  document.addEventListener('visibilitychange', hidden);
              });
    const settled = hadWorker ? Promise.resolve() : lessonLeft().then(quiet);
    settled
        .then(() => checkVersion({ now: true }))
        .then(() => {
            if (!version.loaded && navigator.serviceWorker.controller) return null;
            return navigator.serviceWorker.register(version.loaded ? `/sw.js?build=${encodeURIComponent(version.loaded)}` : '/sw.js', { updateViaCache: 'none' });
        })
        .catch((error) => console.warn('Service worker not registered', error));
    // A new version that couldn't cache its files (sw.js precache) stays out; this one keeps running.
    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type === 'install-failed') console.error('A new version could not install: these files failed', event.data.failed);
        // A reminder tapped while the app is open: go there, unless a take is being recorded.
        if (event.data?.type === 'navigate' && typeof event.data.path === 'string' && event.data.path.startsWith('/') && !event.data.path.startsWith('//') && !session.recording) navigate(event.data.path);
    });
}
