// Settings (docs/backend-plan.md, "Settings"): the profile (name, handle, bio, avatar, the X and
// Bluesky handles), the email (re-verified on change), the password, the sign-in methods, Download
// my data, signing out (here or everywhere), and deleting the account. A change that needs a
// recent sign-in says so, with the way to sign in again.

import { GameModeSwitch, ThemeSwitch } from '../components/appearance.js';
import { GameElements } from '../game/settings.js';
import { api, ApiError } from '../api.js';
import { formatDate, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { account, loadAccount, loadAuthConfig, providerSignIn, selfHosted, signOut } from '../stores/account.js';
import { EmptyState, Field, Notice, StatusChip } from '../components/form.js';
import { PageHeader } from '../components/page.js';
import { Icon, Logo } from '../components/icon.js';
import { Modal, toast } from '../components/ui.js';
import { anySkipped, askAgain, confirmAction } from '../components/confirm.js';
import { learning, loadLearning, restartCourse, undoRestart } from '../stores/learning.js';
import { formatBytes, SyncStatus } from '../components/cloud.js';
import { appearanceSummary } from '../components/appearance.js';
import { Switch } from '../components/controls.js';
import { prefs } from '../stores/prefs.js';
import { setGlobal } from '../components/boards.js';
import { sk } from '../skin.js';
import { deleteEverythingHere, deviceSummary } from '../device.js';
import { ideas, ideasOpen } from '../stores/ideas.js';

const AVATAR_BYTES = 2 * 1024 * 1024;
const AVATAR_SIDE = 3000;
const METHODS = { google: tk('settings.methods.google'), apple: tk('settings.methods.apple'), password: tk('settings.methods.password') };
const HANDLE_REASONS = { taken: tk('settings.handle.taken'), reserved: tk('settings.handle.reserved'), invalid: tk('settings.handle.invalid') };
const EXPORT = { none: null, preparing: tk('settings.export.preparing'), ready: tk('settings.export.ready'), failed: tk('settings.export.failed') };

/** A photo's size from an image element (it reads the size without keeping a decoded copy). */
function imageSize(file) {
    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            resolve({ width: image.naturalWidth, height: image.naturalHeight });
            URL.revokeObjectURL(url);
        };
        image.onerror = () => {
            resolve(null);
            URL.revokeObjectURL(url);
        };
        image.src = url;
    });
}

/** What a refusal means here; null when the screen shows it on its fields. */
function refusalText(error) {
    if (!(error instanceof ApiError)) return t('settings.failed');
    if (error.code === 'validation_failed') return null;
    if (error.code === 'recent_sign_in_required') return t('settings.signInAgainFirst');
    if (error.code === 'last_method') return t('settings.methods.last');
    if (error.code === 'offline') return t('common.offline');
    if (error.code === 'rate_limited') return t('auth.error.wait');
    return t('settings.failed');
}

// The licence's credit names the site as written, in every language.
const CREDIT_SITE = 'stackapella.com'; // i18n-ignore: an address

// The sections, in order (87.png), and their names.
const SECTIONS = ['profile', 'account', 'game', 'practice', 'plan', 'data', 'device'];
const SECTION_WORDS = { profile: tk('settings.nav.profile'), account: tk('settings.nav.account'), game: tk('settings.nav.game'), practice: tk('settings.nav.practice'), plan: tk('settings.nav.plan'), data: tk('settings.nav.data'), device: tk('settings.nav.device') };

export const SettingsView = {
    components: { GameElements, GameModeSwitch, PageHeader, ThemeSwitch, Field, Notice, StatusChip, EmptyState, Icon, Logo, Modal, SyncStatus, Switch },
    data: () => ({
        restarting: false,
        profile: null,
        profileFrom: null, // the account values the form was last filled with
        handleCheck: null,
        email: { address: '', password: '' },
        password: { current: '', next: '' },
        exportState: null,
        confirmDelete: false,
        deleteText: '',
        deletePassword: '',
        busy: null, // which section is saving
        errors: {},
        notice: {}, // section -> a refusal in words
        refusals: {}, // section -> the refusal itself (to offer signing in again)
        config: null,
        skipping: anySkipped(), // some confirmations turned off on this device
        usage: null, // the storage meter's
        editing: null, // 'email' or 'password': its change open under its row
        section: 'profile', // the section in view, marked in the sidebar
    }),
    setup: () => ({ ideas, learning, prefs, account, METHODS, HANDLE_REASONS, EXPORT, formatDate, formatBytes, SECTIONS, SECTION_WORDS, appearanceSummary, selfHosted, CREDIT_SITE, sk }),
    template: `
        <div class="min-h-dvh bg-ink px-4 pb-16 pt-[max(0.5rem,env(safe-area-inset-top))] md:px-8">
            <div class="mx-auto w-full max-w-5xl">
                <!-- How it looks is the header's Theme button, here as on every screen (Hillel's call). -->
                <PageHeader :title="t('settings.title')" back="/"><template #aside><ThemeSwitch /></template></PageHeader>
                <div class="mt-4 flex gap-8 md:mt-6">
                    <!-- The sections (87.png): on a wide screen, beside them; the one in view marked -->
                    <nav v-if="account.me && profile" class="sticky top-6 hidden w-48 shrink-0 self-start md:block" :aria-label="t('settings.title')">
                        <ul class="space-y-1">
                            <li v-for="key in SECTIONS" :key="key"><a :href="'#' + key" class="block rounded-xl px-3 py-2 text-sm font-semibold transition" :class="section === key ? 'bg-raised text-cream' : 'text-muted hover:text-cream'" :aria-current="section === key ? 'true' : null" @click.prevent="jump(key)">{{ t(SECTION_WORDS[key]) }}</a></li>
                        </ul>
                    </nav>
                    <div class="flex min-w-0 max-w-2xl flex-1 flex-col gap-8">
                <p v-if="!account.loaded" class="text-muted" role="status">{{ t('settings.loading') }}</p>
                <EmptyState v-else-if="!account.me" icon="lock" :title="t('settings.signedOut')" :body="t('settings.signedOutHint')">
                    <button type="button" class="btn-primary" @click="navigate('/signin?next=/settings')">{{ t('auth.signIn') }}</button>
                </EmptyState>
                <template v-else-if="profile">
                    <!-- Profile -->
                    <form id="profile" ref="profile" class="card scroll-mt-6 space-y-5 p-5 md:p-6" data-section @submit.prevent="saveProfile" :aria-label="t('settings.nav.profile')">
                        <div class="flex items-center gap-4">
                            <img v-if="account.me.avatarUrl" :src="account.me.avatarUrl" alt="" class="size-14 rounded-full object-cover" />
                            <span v-else class="grid size-14 place-items-center rounded-full bg-part-1 text-xl font-extrabold text-part-text-1" aria-hidden="true">{{ (account.me.name || '?').slice(0, 1) }}</span>
                            <div class="min-w-0 space-y-1.5">
                                <p class="truncate font-bold text-cream" translate="no">{{ account.me.name }}</p>
                                <div class="flex flex-wrap gap-2">
                                    <label class="btn-secondary btn-sm cursor-pointer">{{ t('settings.avatar.choose') }}<input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="avatar" /></label>
                                    <button v-if="account.me.avatarUrl" type="button" class="btn-ghost btn-sm" @click="removeAvatar">{{ t('settings.avatar.remove') }}</button>
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <Field :label="t('auth.name')" :help="account.me.betaSinger ? t('settings.nameCredit') : ''" :error="errors.name" required v-slot="f">
                                <input :id="f.id" v-model="profile.name" maxlength="80" required class="input" autocomplete="name" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                            </Field>
                            <Field :label="t('settings.handle.label')" :help="handleHelp" :error="errors.handle" v-slot="f">
                                <input :id="f.id" v-model="profile.handle" maxlength="30" class="input" autocomplete="username" spellcheck="false" :aria-describedby="f.describedby" :aria-invalid="f.invalid" @input="checkHandle" />
                            </Field>
                        </div>
                        <Field :label="t('settings.bio')" :count="t('settings.bioCount', { count: formatNumber((profile.bio || '').length), max: formatNumber(300) })" :error="errors.bio" v-slot="f">
                            <textarea :id="f.id" v-model="profile.bio" rows="3" maxlength="300" class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid"></textarea>
                        </Field>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <Field :label="t('settings.bluesky')" :error="errors.blueskyHandle" v-slot="f">
                                <input :id="f.id" v-model="profile.blueskyHandle" class="input" spellcheck="false" :aria-describedby="f.describedby" />
                            </Field>
                            <Field :label="t('settings.x')" :error="errors.xHandle" v-slot="f">
                                <input :id="f.id" v-model="profile.xHandle" class="input" spellcheck="false" :aria-describedby="f.describedby" />
                            </Field>
                        </div>
                        <p class="text-xs text-muted">{{ t('settings.profileNote') }}</p>
                        <Notice v-if="notice.profile" tone="bad">{{ notice.profile }}</Notice>
                        <!-- The worldwide boards (components/boards.js): 18 and over, saved at once -->
                        <div class="divide-y divide-line rounded-2xl border border-line">
                            <!-- Hide profile (contract: account.update hideProfile): saved at once; it never stops sharing or recording. -->
                            <div class="py-2 pe-2 ps-4" data-hide-profile><Switch :on="!!account.me.hideProfile" :label="t('settings.hide.title')" :hint="t('settings.hide.hint')" :disabled="busy === 'hide'" @change="setHidden" /></div>
                            <!-- Ideas (docs/ideas-app-plan.md): an email when someone sings your tune, once Ideas is open -->
                            <div v-if="ideas.open" class="py-2 pe-2 ps-4" data-idea-emails><Switch :on="account.me.ideaEmails !== false" :label="t('settings.ideaEmails.title')" :hint="t('settings.ideaEmails.hint')" :disabled="busy === 'ideaEmails'" @change="setIdeaEmails" /></div>
                            <div v-if="account.me?.isAdult" class="py-2 pe-2 ps-4" data-boards-global><Switch :on="!!account.me.boardsGlobal" :label="t('boards.settings.title')" :hint="t('boards.settings.hint')" @change="setBoardsGlobal" /></div>
                        </div>
                        <Notice v-if="notice.hide || notice.boards || notice.ideaEmails" tone="bad">{{ notice.hide || notice.boards || notice.ideaEmails }}</Notice>
                        <button type="submit" class="btn-primary" :disabled="busy === 'profile'">{{ busy === 'profile' ? t('common.saving') : t('common.save') }}</button>
                    </form>

                    <!-- Account and sign-in: each a row, its change opening under it -->
                    <section id="account" class="scroll-mt-6 space-y-3" data-section aria-labelledby="account-heading">
                        <h2 id="account-heading" class="text-lg font-bold text-cream">{{ t('settings.nav.account') }}</h2>
                        <div class="card divide-y divide-line overflow-hidden">
                            <div class="p-5" data-row="email">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="min-w-0"><p class="text-xs text-muted">{{ t('auth.email') }}</p><p class="truncate font-semibold text-cream" translate="no">{{ account.me.email }}</p></div>
                                    <button type="button" class="btn-secondary btn-sm" :aria-expanded="editing === 'email' ? 'true' : 'false'" @click="editing = editing === 'email' ? null : 'email'">{{ t('settings.change') }}</button>
                                </div>
                                <Notice v-if="account.me.pendingEmail" tone="info" class="mt-3">{{ t('settings.email.pending', { email: account.me.pendingEmail }) }}</Notice>
                                <form v-if="editing === 'email'" class="mt-4 space-y-4" @submit.prevent="changeEmail">
                                    <Field :label="t('settings.email.new')" :error="errors.email" v-slot="f">
                                        <input :id="f.id" v-model="email.address" type="email" class="input" autocomplete="email" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                                    </Field>
                                    <Field v-if="account.me.methods.password" :label="t('settings.currentPassword')" :help="t('settings.email.passwordHelp')" :error="errors.password" v-slot="f">
                                        <input :id="f.id" v-model="email.password" type="password" class="input" autocomplete="current-password" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                                    </Field>
                                    <Notice v-if="notice.email" tone="bad">{{ notice.email }} <button v-if="needsSignIn.email" type="button" class="link" @click="signInAgain">{{ t('settings.signInAgain') }}</button></Notice>
                                    <button type="submit" class="btn-secondary" :disabled="busy === 'email' || !email.address.trim()">{{ t('settings.email.change') }}</button>
                                </form>
                            </div>
                            <div class="p-5" data-row="password">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="min-w-0"><p class="text-xs text-muted">{{ t('auth.password') }}</p><p class="font-semibold text-cream">{{ account.me.methods.password ? t('settings.password.isSet') : t('settings.password.notSet') }}</p></div>
                                    <button type="button" class="btn-secondary btn-sm" :aria-expanded="editing === 'password' ? 'true' : 'false'" @click="editing = editing === 'password' ? null : 'password'">{{ account.me.methods.password ? t('settings.change') : t('settings.methods.add') }}</button>
                                </div>
                                <form v-if="editing === 'password'" class="mt-4 space-y-4" @submit.prevent="changePassword">
                                    <p class="text-sm text-muted">{{ account.me.methods.password ? t('settings.password.change') : t('settings.password.add') }}</p>
                                    <Field v-if="account.me.methods.password" :label="t('settings.currentPassword')" :error="errors.currentPassword" v-slot="f">
                                        <input :id="f.id" v-model="password.current" type="password" class="input" autocomplete="current-password" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                                    </Field>
                                    <Field :label="t('auth.newPassword')" :help="t('auth.passwordHelp', { count: config?.passwordMinLength ?? 10 })" :error="errors.passwordNew" v-slot="f">
                                        <input :id="f.id" v-model="password.next" type="password" class="input" autocomplete="new-password" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                                    </Field>
                                    <Notice v-if="notice.password" tone="bad">{{ notice.password }} <button v-if="needsSignIn.password" type="button" class="link" @click="signInAgain">{{ t('settings.signInAgain') }}</button></Notice>
                                    <button type="submit" class="btn-secondary" :disabled="busy === 'password' || !password.next">{{ account.me.methods.password ? t('settings.password.save') : t('settings.password.addButton') }}</button>
                                </form>
                            </div>
                            <div class="p-5">
                                <p class="text-xs text-muted">{{ t('settings.methods.title') }}</p>
                                <ul class="mt-2 space-y-1">
                                    <li v-for="(label, method) in METHODS" :key="method" class="flex min-h-11 items-center justify-between gap-3">
                                        <span class="flex items-center gap-2.5" :class="account.me.methods[method] ? 'font-semibold text-cream' : 'text-muted'"><span class="size-2 rounded-full" :class="account.me.methods[method] ? 'bg-good' : 'bg-line'" aria-hidden="true"></span>{{ t(label) }}<span class="sr-only"> · {{ account.me.methods[method] ? t('settings.methods.on') : t('settings.methods.off') }}</span></span>
                                        <button v-if="account.me.methods[method]" type="button" class="btn-secondary btn-sm" :disabled="busy === 'methods'" @click="removeMethod(method)">{{ t('settings.methods.remove') }}</button>
                                        <button v-else-if="method !== 'password'" type="button" class="btn-secondary btn-sm" @click="addMethod(method)">{{ t('settings.methods.add') }}</button>
                                    </li>
                                </ul>
                                <Notice v-if="notice.methods" tone="bad" class="mt-3">{{ notice.methods }} <button v-if="needsSignIn.methods" type="button" class="link" @click="signInAgain">{{ t('settings.signInAgain') }}</button></Notice>
                            </div>
                        </div>
                    </section>
                </template>

                <!-- Game mode (Hillel: "a section in the settings (not at the top)"), signed in or not. -->
                <section v-if="account.loaded" id="game" class="scroll-mt-6 space-y-3" data-section aria-labelledby="game-heading">
                    <h2 id="game-heading" class="text-lg font-bold text-cream">{{ t('settings.nav.game') }}</h2>
                    <div class="card px-5 py-3"><GameModeSwitch /></div>
                    <GameElements />
                </section>

                <!-- Practice and appearance (signed in or not: the appearance and this device's sounds) -->
                <section v-if="account.loaded" id="practice" class="scroll-mt-6 space-y-3" data-section aria-labelledby="practice-heading">
                    <h2 id="practice-heading" class="text-lg font-bold text-cream">{{ t('settings.nav.practice') }}</h2>
                    <div class="card divide-y divide-line overflow-hidden">
                        <div class="flex items-center justify-between gap-4 p-5"><div class="min-w-0"><p class="font-semibold text-cream">{{ t('appearance.title') }}</p><p class="text-sm text-muted">{{ appearanceSummary() }}</p></div><ThemeSwitch /></div>
                        <a v-if="account.me" href="/settings/reminders" class="flex items-center justify-between gap-4 p-5 hover:bg-raised/50" @click.prevent="navigate('/settings/reminders')"><span class="min-w-0"><span class="block font-semibold text-cream">{{ t('reminders.title') }}</span><span class="block text-sm text-muted">{{ t('reminders.settingsHint') }}</span></span><Icon name="chevron" :size="18" class="shrink-0 -rotate-90 text-muted rtl:rotate-90" /></a>
                        <a v-if="account.me" href="/groups" class="flex items-center justify-between gap-4 p-5 hover:bg-raised/50" @click.prevent="navigate('/groups')"><span class="min-w-0"><span class="block font-semibold text-cream">{{ t('groups.title') }}</span><span class="block text-sm text-muted">{{ t('groups.settingsHint') }}</span></span><Icon name="chevron" :size="18" class="shrink-0 -rotate-90 text-muted rtl:rotate-90" /></a>
                        <div class="py-3 pe-3 ps-5" data-sounds><Switch :on="prefs.sounds" :label="t('settings.device.sounds')" :hint="sk('settings.device.soundsHintSerious', 'settings.device.soundsHint')" @change="(on) => (prefs.sounds = on)" /></div>
                        <div class="flex flex-wrap items-center justify-between gap-4 p-5" data-restart>
                            <div class="min-w-0 flex-1"><p class="font-semibold text-cream">{{ t('settings.course.restart') }}</p><p class="text-sm text-muted">{{ learning.undoUntil ? t('settings.course.restartedUntil', { date: formatDate(learning.undoUntil, { day: 'numeric', month: 'long' }) }) : sk('settings.course.restartHintSerious', 'settings.course.restartHint') }}</p></div>
                            <button v-if="learning.undoUntil" type="button" class="btn-secondary btn-sm shrink-0" :disabled="restarting" @click="undoRestartCourse">{{ t('common.undo') }}</button>
                            <button v-else type="button" class="btn-secondary btn-sm shrink-0" :disabled="restarting || !Object.keys(learning.progress.lessons ?? {}).length" @click="restartCourseAsk">{{ t('settings.course.startOver') }}</button>
                        </div>
                        <label class="flex flex-wrap items-center justify-between gap-4 p-5" data-sing-way><span class="min-w-0"><span class="block font-semibold text-cream">{{ t('settings.device.singWay') }}</span><span class="block text-sm text-muted">{{ t('settings.device.singWayHint') }}</span></span>
                            <select v-model="prefs.singWay" class="input w-auto"><option :value="null">{{ t('settings.device.singWayAsk') }}</option><option value="parts">{{ t('settings.device.singWayParts') }}</option><option value="whole">{{ t('settings.device.singWayWhole') }}</option></select>
                        </label>
                        <div v-if="skipping" class="flex items-center justify-between gap-4 p-5"><p class="min-w-0 text-sm text-muted">{{ t('settings.device.skipping') }}</p><button type="button" class="btn-secondary btn-sm shrink-0" @click="askAgain">{{ t('settings.device.askAgain') }}</button></div>
                    </div>
                </section>

                <template v-if="account.me && profile">
                    <!-- Plan and storage: the songs as slots, the space as a bar -->
                    <section id="plan" class="scroll-mt-6 space-y-3" data-section aria-labelledby="plan-heading">
                        <h2 id="plan-heading" class="text-lg font-bold text-cream">{{ t('settings.nav.plan') }}</h2>
                        <div class="card space-y-5 p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0"><p class="font-bold text-cream">{{ account.me.plan === 'plus' ? t('settings.plan.plusName') : t('settings.plan.freeName') }}</p><p class="text-sm text-muted">{{ selfHosted() ? t('settings.plan.included') : account.me.plan === 'plus' ? t('settings.plan.plus') : t('settings.plan.freeLine') }}</p></div>
                                <a v-if="!selfHosted()" href="/plus?from=settings" class="btn-sm shrink-0" :class="account.me.plan === 'plus' ? 'btn-secondary' : 'btn-primary'" @click.prevent="navigate('/plus?from=settings')">{{ account.me.plan === 'plus' ? t('plus.manage') : t('settings.plan.seePlus') }}</a>
                            </div>
                            <div v-if="usage" class="grid gap-5 sm:grid-cols-2">
                                <div v-if="usage.projects.limit !== null" class="space-y-2">
                                    <p class="flex justify-between text-sm"><span class="font-semibold text-cream">{{ t('settings.plan.songs') }}</span><span class="tabular-nums text-muted">{{ t('settings.plan.ofLimit', { used: usage.projects.used, limit: usage.projects.limit }) }}</span></p>
                                    <div class="flex gap-1" aria-hidden="true"><span v-for="i in usage.projects.limit" :key="i" class="h-1.5 flex-1 rounded-full" :class="i <= usage.projects.used ? 'bg-good' : 'bg-raised'"></span></div>
                                    <p class="text-xs text-muted">{{ songsLeftLine }}</p>
                                </div>
                                <div class="space-y-2">
                                    <p class="flex justify-between text-sm"><span class="font-semibold text-cream">{{ t('settings.plan.storage') }}</span><span class="tabular-nums text-muted">{{ t('settings.plan.ofLimit', { used: formatBytes(usage.storage.usedBytes), limit: formatBytes(usage.storage.limitBytes) }) }}</span></p>
                                    <div class="h-1.5 overflow-hidden rounded-full bg-raised" role="meter" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="storagePercent" :aria-label="t('cloud.meter.label')"><div class="h-full rounded-full" :class="storagePercent >= 80 ? 'bg-warn' : 'bg-good'" :style="{ width: Math.max(2, storagePercent) + '%' }"></div></div>
                                    <a href="/storage" class="link text-xs" @click.prevent="navigate('/storage')">{{ t('cloud.meter.see') }}</a>
                                </div>
                            </div>
                            <a v-else href="/storage" class="btn-go" @click.prevent="navigate('/storage')">{{ t('cloud.meter.see') }}</a>
                            <SyncStatus />
                        </div>
                    </section>

                    <!-- Your data: download it all; sign out; delete the account -->
                    <section id="data" class="scroll-mt-6 space-y-3" data-section aria-labelledby="data-heading">
                        <h2 id="data-heading" class="text-lg font-bold text-cream">{{ t('settings.nav.data') }}</h2>
                        <div class="card divide-y divide-line overflow-hidden">
                            <div class="flex flex-wrap items-center justify-between gap-4 p-5">
                                <div class="min-w-0"><p class="font-semibold text-cream">{{ t('settings.export.title') }}</p><p class="text-sm text-muted">{{ exportState && EXPORT[exportState.status] ? t(EXPORT[exportState.status], { date: exportState.expiresAt ? formatDate(exportState.expiresAt) : '' }) : t('settings.export.intro') }}</p></div>
                                <div class="flex shrink-0 flex-wrap gap-2">
                                    <button v-if="exportState?.status !== 'preparing'" type="button" class="btn-secondary btn-sm" :disabled="busy === 'export'" @click="requestExport">{{ exportState?.status === 'ready' || exportState?.status === 'failed' ? t('settings.export.again') : t('settings.export.request') }}</button>
                                    <a v-if="exportState?.status === 'ready' && exportState.url" :href="exportState.url" class="btn-primary btn-sm" rel="noopener">{{ t('settings.export.download') }}</a>
                                </div>
                            </div>
                            <div class="space-y-3 p-5">
                                <p class="font-semibold text-cream">{{ t('settings.signOutTitle') }}</p>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="btn-secondary btn-sm" @click="leave(false)">{{ t('settings.signOut') }}</button>
                                    <button type="button" class="btn-secondary btn-sm" @click="leave(true)">{{ t('settings.signOutEverywhere') }}</button>
                                </div>
                                <p class="text-sm text-muted">{{ t('settings.signOutHint') }}</p>
                            </div>
                            <div class="flex items-center justify-between gap-4 p-5"><p class="min-w-0 text-sm text-muted">{{ t('settings.delete.hint') }}</p><button type="button" class="btn-ghost btn-sm shrink-0 text-bad" @click="confirmDelete = true">{{ t('settings.delete.button') }}</button></div>
                        </div>
                    </section>
                </template>
                <!-- This device: everything kept in this browser, deleted at once; signed in or not (a guest keeps the most here). -->
                <section v-if="account.loaded" id="device" class="scroll-mt-6 space-y-3" data-section aria-labelledby="device-heading" data-wipe>
                    <h2 id="device-heading" class="text-lg font-bold text-cream">{{ t('settings.nav.device') }}</h2>
                    <div class="card flex flex-wrap items-center justify-between gap-4 p-5">
                        <div class="min-w-0 flex-1"><p class="font-semibold text-cream">{{ t('settings.wipe.title') }}</p><p class="text-sm text-muted">{{ account.me ? t('settings.wipe.hint') : t('settings.wipe.hintGuest') }}</p></div>
                        <button type="button" class="btn-ghost btn-sm shrink-0 text-bad" :disabled="busy === 'wipe'" data-wipe-start @click="wipeDevice">{{ t('settings.wipe.button') }}</button>
                    </div>
                </section>
                <a href="/contact?from=/settings" class="card-interactive flex items-center gap-4 p-5" @click.prevent="navigate('/contact?from=/settings')">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-raised text-cream" aria-hidden="true"><Icon name="info" :size="20" /></span>
                    <span class="min-w-0 flex-1"><span class="block font-bold text-cream">{{ t('contact.title') }}</span><span class="block text-sm text-muted">{{ t('settings.contactHint') }}</span></span>
                    <Icon name="arrow" :size="18" class="text-muted" />
                </a>
                <!-- The licence's credit (the AAL): on a self-hosted install, the name, Stackapella and the address, never changed. -->
                <p v-if="selfHosted()" class="text-center text-xs text-muted" data-credit><span translate="no">{{ t('settings.credit') }}</span> · <a href="https://stackapella.com" class="link-quiet" rel="noopener" translate="no">{{ CREDIT_SITE }}</a></p>
                    </div>
                </div>
            </div>
            <Modal v-if="confirmDelete" :title="t('settings.delete.title')" :dismissable="busy !== 'delete'" @close="confirmDelete = false">
                <form class="space-y-4" @submit.prevent="deleteAccount">
                    <p class="text-cream">{{ t('settings.delete.body') }}</p>
                    <Notice tone="bad">{{ t('settings.delete.cannotUndo') }}</Notice>
                    <Field v-if="account.me?.methods.password" :label="t('settings.currentPassword')" :error="errors.deletePassword" v-slot="f">
                        <input :id="f.id" v-model="deletePassword" type="password" class="input" autocomplete="current-password" :aria-describedby="f.describedby" />
                    </Field>
                    <Field :label="t('settings.delete.type', { word: t('settings.delete.word') })" required v-slot="f">
                        <input :id="f.id" v-model="deleteText" class="input" autocomplete="off" required :aria-describedby="f.describedby" />
                    </Field>
                    <Notice v-if="notice.delete" tone="bad">{{ notice.delete }} <button v-if="needsSignIn.delete" type="button" class="link" @click="signInAgain">{{ t('settings.signInAgain') }}</button></Notice>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="btn-secondary" :disabled="busy === 'delete'" @click="confirmDelete = false">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-danger" :disabled="busy === 'delete' || deleteText.trim().toLowerCase() !== t('settings.delete.word').toLowerCase()">{{ t('settings.delete.confirm') }}</button>
                    </div>
                </form>
            </Modal>
        </div>`,
    computed: {
        storagePercent() {
            const st = this.usage?.storage;
            return st?.limitBytes ? Math.min(100, Math.round((st.usedBytes / st.limitBytes) * 100)) : 0;
        },
        /** "One more before you hit the limit." */
        songsLeftLine() {
            const projects = this.usage?.projects;
            if (!projects || projects.limit === null) return '';
            return t('settings.plan.songsLeft', { count: Math.max(0, projects.limit - projects.used) });
        },
        handleHelp() {
            const check = this.handleCheck;
            if (!check || check.handle !== this.profile?.handle) return t('settings.handle.help');
            return check.available ? t('settings.handle.available') : t(HANDLE_REASONS[check.reason] ?? HANDLE_REASONS.invalid);
        },
        needsSignIn() {
            return Object.fromEntries(Object.entries(this.refusals ?? {}).map(([section, error]) => [section, error?.code === 'recent_sign_in_required']));
        },
    },
    async created() {
        if (account.me) this.reset();
        loadAuthConfig().then((config) => (this.config = config), (error) => console.warn('Sign-in settings not loaded', error));
        loadLearning().catch((error) => console.warn('Course progress not loaded', error));
        ideasOpen();
        await loadAccount({ fresh: true });
        this.reset({ keepEdits: true }); // anything typed while it loaded stays
        if (account.me) this.loadExport();
        if (account.me) api.usage.show().then(({ data }) => (this.usage = data), (error) => console.warn('Storage not loaded', error));
    },
    // (One watch and one unmounted: a second of each used to replace the first, so an account
    // changing never reset the page and its timers were left running.)
    watch: {
        'account.me'(now, before) {
            if (now && (!before || now.id !== before.id)) this.reset();
        },
        profile(now) {
            if (now) this.$nextTick(() => this.watchSections());
        },
    },
    unmounted() {
        clearTimeout(this.handleTimer);
        clearTimeout(this.exportTimer);
        this.sections?.disconnect();
    },
    methods: {
        /** Start the course over: asked once, plainly, then undoable for 30 days (here, and in the toast). */
        async restartCourseAsk() {
            const sure = await confirmAction({ title: t('settings.course.confirmTitle'), body: t('settings.course.confirmBody'), action: t('settings.course.startOver') });
            if (!sure) return;
            this.restarting = true;
            try {
                await restartCourse();
                toast(t('settings.course.restarted'), { seconds: 10, actions: [{ label: t('common.undo'), run: () => this.undoRestartCourse() }] });
            } catch (error) {
                console.error('The course did not start over', error);
                toast(error?.code === 'offline' ? t('common.offline') : t('settings.failed'), { tone: 'error' });
            } finally {
                this.restarting = false;
            }
        },
        async undoRestartCourse() {
            this.restarting = true;
            try {
                await undoRestart();
                toast(sk('settings.course.undoneSerious', 'settings.course.undone'));
            } catch (error) {
                console.error('Starting over was not undone', error);
                toast(error?.code === 'offline' ? t('common.offline') : t('settings.failed'), { tone: 'error' });
            } finally {
                this.restarting = false;
            }
        },
        /** The sidebar marks the section nearest the top of the screen. */
        watchSections() {
            this.sections?.disconnect();
            this.sections = new IntersectionObserver((entries) => {
                const seen = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
                if (seen) this.section = seen.target.id;
            }, { rootMargin: '0px 0px -60% 0px' });
            this.$el.querySelectorAll?.('[data-section]').forEach((el) => this.sections.observe(el));
        },
        jump(key) {
            this.section = key;
            document.getElementById(key)?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        },
        navigate,
        /**
         * The profile's fields from the account. With `keepEdits` (the fresh account arriving after
         * the form showed the kept one), a field the person has already changed keeps their words.
         */
        reset({ keepEdits = false } = {}) {
            const me = account.me;
            const fresh = me ? { name: me.name ?? '', handle: me.handle ?? '', bio: me.bio ?? '', xHandle: me.xHandle ?? '', blueskyHandle: me.blueskyHandle ?? '' } : null;
            const shown = this.profile;
            const from = this.profileFrom;
            this.profile = fresh && keepEdits && shown && from ? Object.fromEntries(Object.entries(fresh).map(([key, value]) => [key, shown[key] !== from[key] ? shown[key] : value])) : fresh;
            this.profileFrom = fresh && { ...fresh }; // a copy: typing changes the form, never this
        },
        /** Runs a section's change, keeping its refusal by the section. */
        async run(section, request) {
            this.busy = section;
            this.errors = {};
            this.notice = { ...this.notice, [section]: null };
            this.refusals = { ...(this.refusals ?? {}), [section]: null };
            try {
                const answer = await request();
                if (answer?.data && 'methods' in answer.data) account.me = answer.data;
                return answer ?? true;
            } catch (error) {
                if (error instanceof ApiError && error.code === 'validation_failed') this.errors = error.errors ?? {};
                this.refusals = { ...this.refusals, [section]: error };
                this.notice = { ...this.notice, [section]: refusalText(error) };
                return null;
            } finally {
                this.busy = null;
            }
        },
        /** As the person types: is this handle free? (a moment after they stop) */
        checkHandle() {
            clearTimeout(this.handleTimer);
            const handle = this.profile.handle.trim();
            if (!handle || handle === account.me.handle) {
                this.handleCheck = null;
                return;
            }
            this.handleTimer = setTimeout(async () => {
                try {
                    this.handleCheck = (await api.account.handle.check(handle)).data;
                } catch (error) {
                    console.warn('The handle check failed', error);
                }
            }, 400);
        },
        /** Show or stop showing your best scores on the worldwide boards, at once. */
        /** Hide profile: everyone else sees "Anonymous", with no handle, picture or profile page. */
        async setHidden(on) {
            if (await this.run('hide', () => api.account.update({ hideProfile: on }))) toast(t('settings.saved'));
        },
        /** Ideas: an email when someone sings your tune (at most one a day per tune), at once. */
        async setIdeaEmails(on) {
            if (await this.run('ideaEmails', () => api.account.update({ ideaEmails: on }))) toast(t('settings.saved'));
        },
        async setBoardsGlobal(on) {
            if (await this.run('boards', () => setGlobal(on))) {
                account.me.boardsGlobal = on;
                toast(t('settings.saved'));
            }
        },
        async saveProfile() {
            const p = this.profile;
            const changed = Object.fromEntries(Object.entries({ name: p.name.trim(), handle: p.handle.trim() || null, bio: p.bio.trim() || null, xHandle: p.xHandle.trim() || null, blueskyHandle: p.blueskyHandle.trim() || null }).filter(([key, value]) => value !== (account.me[key] ?? null)));
            if (!Object.keys(changed).length) return;
            if (await this.run('profile', () => api.account.update(changed))) toast(t('settings.saved'));
        },
        /** A photo: checked here first (2 MB, 3000 pixels a side), then made square by the server. */
        async avatar(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (!file) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                toast(t('settings.avatar.notImage'), { tone: 'error' });
                return;
            }
            if (file.size > AVATAR_BYTES) {
                toast(t('settings.avatar.tooBig'), { tone: 'error' });
                return;
            }
            const size = await imageSize(file);
            if (!size) {
                toast(t('settings.avatar.notImage'), { tone: 'error' });
                return;
            }
            if (size.width > AVATAR_SIDE || size.height > AVATAR_SIDE) {
                toast(t('settings.avatar.tooLarge'), { tone: 'error' });
                return;
            }
            if (await this.run('profile', () => api.account.avatar.update(file))) toast(t('settings.avatar.saved'));
            else if (this.errors.avatar) toast(this.errors.avatar.join(' '), { tone: 'error' });
        },
        async removeAvatar() {
            await this.run('profile', () => api.account.avatar.delete());
        },
        async changeEmail() {
            if (await this.run('email', () => api.account.email.update({ email: this.email.address.trim(), password: this.email.password || null }))) {
                toast(t('settings.email.sent', { email: this.email.address.trim() }), { seconds: 10 });
                this.email = { address: '', password: '' };
                await loadAccount({ fresh: true });
            }
        },
        async changePassword() {
            const answer = await this.run('password', () => api.account.password.update({ currentPassword: this.password.current || null, password: this.password.next }));
            if (this.errors.password) this.errors = { ...this.errors, passwordNew: this.errors.password, password: null };
            if (answer) {
                toast(t('settings.password.saved'));
                this.password = { current: '', next: '' };
            }
        },
        addMethod(method) {
            providerSignIn(method, { intended: '/settings', link: true });
        },
        askAgain() {
            askAgain();
            this.skipping = false;
            toast(t('settings.device.askingAgain'));
        },
        async removeMethod(method) {
            const name = t(METHODS[method]);
            const sure = await confirmAction({ title: t('confirm.removeMethod.title', { method: name }), body: t('confirm.removeMethod.body', { method: name }), action: t('confirm.removeMethod.action', { method: name }) });
            if (!sure) return;
            if (await this.run('methods', () => api.account.methods.delete(method))) toast(t('settings.methods.removed'));
        },
        async loadExport() {
            try {
                this.exportState = (await api.account.export.show()).data;
                if (this.exportState.status === 'preparing') this.exportTimer = setTimeout(() => this.loadExport(), 15000);
            } catch (error) {
                console.warn('The export state did not load', error);
            }
        },
        async requestExport() {
            const answer = await this.run('export', () => api.account.export.create());
            if (answer) {
                this.exportState = answer.data;
                toast(t('settings.export.requested'), { seconds: 10 });
                clearTimeout(this.exportTimer);
                this.exportTimer = setTimeout(() => this.loadExport(), 15000);
            } else if (this.notice.export) {
                toast(this.notice.export, { tone: 'error' });
            }
        },
        async leave(everywhere) {
            try {
                await signOut({ everywhere });
                toast(everywhere ? t('settings.signedOutEverywhere') : t('settings.signedOutHere'));
                navigate('/');
            } catch (error) {
                console.error('Signing out failed', error);
                toast(refusalText(error), { tone: 'error' });
            }
        },
        /** A change that needs a recent sign-in: out, then back here once signed in again. */
        async signInAgain() {
            await signOut().catch((error) => console.warn('Signing out on this device failed', error));
            navigate('/signin?next=/settings');
        },
        /** Everything on this device, after a confirm naming what goes (with counts); signed out here first. */
        async wipeDevice() {
            const summary = await deviceSummary().catch((error) => {
                console.warn('What this device keeps could not be counted', error);
                return null;
            });
            const details = summary
                ? [
                      { label: t('settings.wipe.songs'), value: summary.songs },
                      account.me && summary.songs ? { label: t('settings.wipe.notBackedUp'), value: summary.notBackedUp } : null,
                      summary.courseTakes ? { label: t('settings.wipe.courseTakes'), value: summary.courseTakes } : null,
                      summary.drafts ? { label: t('settings.wipe.drafts'), value: summary.drafts } : null,
                      summary.betaTakes ? { label: t('settings.wipe.betaTakes'), value: summary.betaTakes } : null,
                  ].filter(Boolean)
                : null;
            const sure = await confirmAction({
                title: t('settings.wipe.confirmTitle'),
                body: account.me ? t('settings.wipe.body') : t('settings.wipe.bodyGuest'),
                details,
                action: t('settings.wipe.action'),
                cancel: t('confirm.keep'),
            });
            if (!sure) return;
            this.busy = 'wipe';
            try {
                // Signed out here first: signed in, the account would bring its songs straight back.
                if (account.me) await signOut();
                await deleteEverythingHere();
                location.replace('/');
            } catch (error) {
                console.error('This device was not cleared', error);
                toast(t('settings.wipe.failed'), { tone: 'error' });
                this.busy = null;
            }
        },
        async deleteAccount() {
            const answer = await this.run('delete', () => api.account.delete({ password: this.deletePassword || null }));
            if (this.errors.password) this.errors = { ...this.errors, deletePassword: this.errors.password };
            if (answer) {
                account.me = null;
                this.confirmDelete = false;
                toast(t('settings.delete.done'), { seconds: 12 });
                navigate('/');
            }
        },
    },
};
