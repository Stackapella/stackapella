// Signing in (docs/backend-plan.md, "UX notes": one screen with Apple, Google and email, the last
// method used first) and the links in our emails: /signin (and ?mode=signup or forgot), /verify,
// /reset, /email/confirm and /email/revert. An account is never needed to learn or record, so
// every screen has a way back. `?next=` is where to go afterwards (a path in the app), `?invite=` a
// beta invite carried through a sign-up.

import { count } from '../usage.js';
import { api, ApiError } from '../api.js';
import { formatWait, t, tk } from '../i18n.js';
import { goBack, navigate, route } from '../router.js';
import { account, lastMethod, linkToken, loadAuthConfig, providerSignIn, ref, signedIn } from '../stores/account.js';
import { local, session as tabStorage } from '../storage.js';

// What an ask was for (`?for=`), as sign-up's title: the reason in the person's words.
const SIGN_UP = tk('auth.signUpTitle');
const REASONS = { progress: tk('auth.reason.progress'), reminders: tk('auth.reason.reminders'), share: tk('auth.reason.share'), boards: tk('auth.reason.boards') };
import { TurnstileBox } from '../turnstile.js';
import { Field, Notice } from '../components/form.js';
import { Icon, Logo } from '../components/icon.js';
import { toast } from '../components/ui.js';
import { InviteAccept } from '../components/invite-card.js';
import { BirthDate } from '../components/birth.js';

const PROVIDERS = { google: tk('auth.google'), apple: tk('auth.apple') };
/** What a refusal means, in plain words (contract/api.md, "Accounts and sign-in"). */
const REFUSED = {
    invalid_credentials: tk('auth.error.invalidCredentials'),
    email_unverified: tk('auth.error.emailUnverified'),
    blocked: tk('auth.error.blocked'),
    suspended: tk('auth.error.suspended'),
    turnstile_required: tk('auth.error.turnstile'),
    turnstile_failed: tk('auth.error.turnstile'),
    paused: tk('auth.error.paused'),
    link_expired: tk('auth.error.linkExpired'),
    email_taken: tk('auth.error.emailTaken'),
    too_young: tk('auth.error.tooYoung'),
    offline: tk('common.offline'),
};
const RESEND_SECONDS = 60;

/** A path inside the app, or the projects page (never somewhere else). */
export function safeNext(next) {
    return typeof next === 'string' && /^\/(?!\/)[\w\-/?=&%.]*$/.test(next) ? next : '/';
}

/** A password field with Show and Hide inside it, at its end (one control instead of a checkbox). */
const PasswordInput = {
    props: { modelValue: { type: String, default: '' }, id: { type: String, required: true }, autocomplete: { type: String, required: true }, describedby: { type: String, default: null }, invalid: { type: [Boolean, String], default: null }, minlength: { type: Number, default: null } },
    emits: ['update:modelValue'],
    data: () => ({ show: false }),
    template: `
        <div class="relative">
            <input :id="id" :value="modelValue" :type="show ? 'text' : 'password'" :autocomplete="autocomplete" required :minlength="minlength" class="input pe-20" :aria-describedby="describedby" :aria-invalid="invalid" @input="$emit('update:modelValue', $event.target.value)" />
            <button type="button" class="btn-ghost btn-sm absolute end-1.5 top-1/2 min-h-9 -translate-y-1/2" :aria-pressed="show ? 'true' : 'false'" :aria-label="t('auth.showPassword')" :aria-controls="id" @click="show = !show">{{ show ? t('auth.hide') : t('auth.show') }}</button>
        </div>`,
};

export const AuthView = {
    components: { BirthDate, Field, InviteAccept, Notice, Icon, Logo, PasswordInput, TurnstileBox },
    data() {
        const q = new URLSearchParams(location.search);
        // A beta invite waits in the tab (the beta page put it there); never in the address bar.
        const invite = tabStorage.get('stackapella.invite') || null;
        // mudeo's early-access link (/early/<code>) waits in the tab the same way.
        const early = tabStorage.get('stackapella.early') || null;
        const link = linkToken(); // the email link's token, already out of the address bar (main.js)
        return {
            mode: q.get('mode') === 'signup' ? 'signup' : q.get('mode') === 'forgot' ? 'forgot' : 'signin',
            // Why an ask sent them here (onboarding step 7): sign-up says so in its title.
            reason: REASONS[q.get('for')] ? q.get('for') : null,
            next: safeNext(q.get('next') ?? tabStorage.get('stackapella.next')),
            invite,
            early,
            token: link?.token ?? null,
            linkEmail: link?.email ?? '',
            config: null,
            form: { name: local.get('stackapella.shareName') ?? '', email: q.get('email') ?? '', password: '', birthMonth: null, birthYear: null, nickname: '' },
            step: 'email', // sign in: the email first, then the password
            busy: false,
            errors: null,
            refusal: null,
            refusalError: null,
            turnstile: null,
            turnstileKey: 0, // a new check after every submit: a token works once
            sentTo: null, // the address a link went to (check your email)
            sentKind: null, // verify | reset
            wait: 0,
            done: null, // a link's outcome: verified | reset | confirmed | reverted
            profile: false, // sign in by email made a new account: a bit about you first
            intended: '/', // where sign in by email goes next
            linkProblem: null, // used | expired
            revertedEmail: null,
            startedAt: performance.now(),
        };
    },
    template: `
        <div class="min-h-dvh bg-ink px-4 py-8 md:py-16">
            <div class="mx-auto flex w-full max-w-md flex-col gap-6">
                <header class="flex items-center justify-between gap-3">
                    <a href="/" @click.prevent="navigate('/')"><Logo /></a>
                    <button type="button" class="btn-secondary btn-sm min-h-11" @click="notNow">{{ t('auth.notNow') }}</button>
                </header>

                <!-- Check your email -->
                <template v-if="sentTo">
                    <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ t('auth.sent.title') }}</h1>
                    <p class="text-cream">{{ t(sentText, { email: sentTo }) }}</p>
                    <p class="text-sm text-muted">{{ t('auth.sent.spam') }}</p>
                    <TurnstileBox v-if="config?.turnstileSiteKey" :key="turnstileKey" :site-key="config.turnstileSiteKey" @token="turnstile = $event" @failed="turnstileFailed" />
                    <Notice v-if="refusal" tone="bad">{{ refusal }}</Notice>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="btn-secondary" :disabled="wait > 0 || busy" @click="resend">{{ wait > 0 ? t('auth.sent.resendIn', { wait: formatWait(wait) }) : t('auth.sent.resend') }}</button>
                        <button type="button" class="btn-ghost btn-sm" @click="sentTo = null">{{ t('auth.sent.wrongAddress') }}</button>
                    </div>
                </template>

                <!-- Sign in by email: the link from the email, then (a new account) a bit about you -->
                <template v-else-if="page === 'signin/link'">
                    <p v-if="busy && !profile" class="text-muted" role="status">{{ t('auth.checkingLink') }}</p>
                    <form v-else-if="profile" class="flex flex-col gap-5" novalidate @submit.prevent="saveProfile">
                        <div>
                            <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ t('auth.profile.title') }}</h1>
                            <p class="mt-2 text-muted">{{ t('auth.profile.intro') }}</p>
                        </div>
                        <Field :label="t('auth.name')" :help="t('auth.nameHelp')" :error="errors?.name" required v-slot="f">
                            <input :id="f.id" v-model="form.name" autocomplete="name" required maxlength="80" class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                        </Field>
                        <BirthDate v-model:month="form.birthMonth" v-model:year="form.birthYear" :hint="t('auth.born.why')" :error="bornError || ''" />
                        <Notice v-if="refusal && refusalCode !== 'too_young'" tone="bad">{{ refusal }}</Notice>
                        <button type="submit" class="btn-primary btn-lg w-full" :disabled="busy || !form.name.trim() || !(form.birthMonth && form.birthYear)">{{ busy ? t('auth.working') : t('auth.continue') }}</button>
                    </form>
                    <template v-else-if="linkProblem === 'expired'">
                        <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ t('auth.link.expiredTitle') }}</h1>
                        <p class="text-muted">{{ t('auth.link.expiredBody') }}</p>
                        <InviteAccept next="/" :button="t('auth.link.sendNew')" />
                    </template>
                    <template v-else-if="linkProblem === 'used'">
                        <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ t('auth.link.usedTitle') }}</h1>
                        <p class="text-muted">{{ t('auth.link.usedBody') }}</p>
                        <button type="button" class="btn-primary btn-lg w-full" @click="navigate('/signin')">{{ t('auth.signIn') }}</button>
                    </template>
                    <template v-else-if="refusal">
                        <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ t('auth.linkProblem') }}</h1>
                        <Notice tone="bad">{{ refusal }}</Notice>
                        <button type="button" class="btn-secondary" @click="navigate('/signin')">{{ t('auth.signIn') }}</button>
                    </template>
                </template>

                <!-- The links in our emails -->
                <template v-else-if="page === 'verify' || page === 'email/confirm' || page === 'email/revert'">
                    <p v-if="busy" class="text-muted" role="status">{{ t('auth.checkingLink') }}</p>
                    <template v-else-if="done">
                        <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ t(DONE[done].title) }}</h1>
                        <p class="text-muted">{{ t(DONE[done].body, { email: revertedEmail ?? '' }) }}</p>
                        <TurnstileBox v-if="done === 'reverted' && config?.turnstileSiteKey" :key="turnstileKey" :site-key="config.turnstileSiteKey" @token="turnstile = $event" @failed="turnstileFailed" />
                        <Notice v-if="done === 'reverted' && refusal" tone="bad">{{ refusal }}</Notice>
                        <button v-if="done === 'reverted'" type="button" class="btn-primary" :disabled="!revertedEmail || busy" @click="choosePassword">{{ t('auth.revert.choosePassword') }}</button>
                        <button v-else type="button" class="btn-primary" @click="go">{{ t('auth.continue') }}</button>
                    </template>
                    <template v-else-if="refusal">
                        <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ t('auth.linkProblem') }}</h1>
                        <Notice tone="bad">{{ refusal }}</Notice>
                        <button type="button" class="btn-secondary" @click="navigate('/signin')">{{ t('auth.signIn') }}</button>
                    </template>
                </template>

                <!-- A new password, from the reset link -->
                <form v-else-if="page === 'reset'" class="flex flex-col gap-5" @submit.prevent="reset">
                    <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ t('auth.reset.title') }}</h1>
                    <p class="text-muted" translate="no">{{ linkEmail }}</p>
                    <Field :label="t('auth.newPassword')" :help="t('auth.passwordHelp', { count: config?.passwordMinLength ?? 10 })" :error="errors?.password" required v-slot="f">
                        <PasswordInput :id="f.id" v-model="form.password" autocomplete="new-password" :minlength="config?.passwordMinLength ?? 10" :describedby="f.describedby" :invalid="f.invalid" />
                    </Field>
                    <Notice v-if="refusal" tone="bad">{{ refusal }} <a v-if="refusalCode === 'link_expired'" href="/signin?mode=forgot" class="link" @click.prevent="mode = 'forgot'; navigate('/signin?mode=forgot')">{{ t('auth.forgot.again') }}</a></Notice>
                    <button type="submit" class="btn-primary" :disabled="busy">{{ busy ? t('common.saving') : t('auth.reset.save') }}</button>
                </form>

                <!-- Forgot the password -->
                <form v-else-if="mode === 'forgot'" class="flex flex-col gap-5" @submit.prevent="forgot">
                    <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ t('auth.forgot.title') }}</h1>
                    <p class="text-muted">{{ t('auth.forgot.intro') }}</p>
                    <Field :label="t('auth.email')" :error="errors?.email" required v-slot="f">
                        <input :id="f.id" v-model="form.email" type="email" inputmode="email" autocomplete="email" required class="input" :placeholder="t('auth.emailPlaceholder')" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                    </Field>
                    <TurnstileBox v-if="config?.turnstileSiteKey" :key="turnstileKey" :site-key="config.turnstileSiteKey" @token="turnstile = $event" @failed="turnstileFailed" />
                    <Notice v-if="refusal" tone="bad">{{ refusal }}</Notice>
                    <button type="submit" class="btn-primary btn-lg w-full" :disabled="busy">{{ busy ? t('auth.working') : t('auth.forgot.send') }}</button>
                    <button type="button" class="link-quiet self-center text-sm" @click="setMode('signin')">{{ t('auth.backToSignIn') }}</button>
                </form>

                <!-- Sign in, or sign up -->
                <template v-else>
                    <div>
                        <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ mode === 'signup' ? t(REASONS[reason] ?? SIGN_UP) : t('auth.signInTitle') }}</h1>
                        <p class="mt-2 text-muted">{{ invite ? t('auth.inviteIntro') : early ? t('auth.earlyIntro') : t('auth.intro') }}</p>
                    </div>
                    <Notice v-if="config?.signups === 'paused' && mode === 'signup' && !invite && !early" tone="warn">{{ t('auth.error.paused') }}</Notice>
                    <div v-if="mode === 'signup' || step === 'email'" class="flex flex-col gap-3">
                        <button v-for="p in providers" :key="p" type="button" class="btn-secondary btn-lg w-full justify-center" @click="provider(p)">
                            {{ t(PROVIDERS[p]) }}<span v-if="p === last" class="ms-2 text-xs font-normal text-muted">{{ t('auth.lastUsed') }}</span>
                        </button>
                    </div>
                    <p v-if="hasPassword && providers.length && (mode === 'signup' || step === 'email')" class="flex items-center gap-3 text-sm text-muted"><span class="h-px flex-1 bg-line"></span>{{ t('auth.orEmail') }}<span class="h-px flex-1 bg-line"></span></p>
                    <form v-if="hasPassword" class="flex flex-col gap-5" novalidate @submit.prevent="submit">
                        <Field v-if="mode === 'signup'" :label="t('auth.name')" :help="t('auth.nameHelp')" :error="errors?.name" required v-slot="f">
                            <input :id="f.id" v-model="form.name" autocomplete="name" required maxlength="80" class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                        </Field>
                        <Field v-if="mode === 'signup' || step === 'email'" :label="t('auth.email')" :error="errors?.email" required v-slot="f">
                            <input :id="f.id" ref="email" v-model="form.email" type="email" inputmode="email" autocomplete="email" autocapitalize="off" spellcheck="false" required class="input" :placeholder="t('auth.emailPlaceholder')" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                        </Field>
                        <!-- The password step: the email above it, with Change. -->
                        <p v-else class="flex items-center justify-between gap-3 rounded-2xl bg-raised px-4 py-3">
                            <span class="min-w-0 truncate text-cream" translate="no">{{ form.email }}</span>
                            <button type="button" class="link-quiet min-h-11 shrink-0 text-sm" @click="changeEmail">{{ t('auth.change') }}</button>
                        </p>
                        <!-- The email again for password managers, on the password step. -->
                        <input v-if="mode === 'signin' && step === 'password'" type="email" :value="form.email" autocomplete="username" class="sr-only" tabindex="-1" aria-hidden="true" />
                        <Field v-if="mode === 'signup' || step === 'password'" :label="t('auth.password')" :help="mode === 'signup' ? t('auth.passwordHelp', { count: config?.passwordMinLength ?? 10 }) : null" :error="errors?.password" required>
                            <template #aside><button v-if="mode === 'signin'" type="button" class="min-h-11 text-sm text-muted underline decoration-transparent underline-offset-4 transition hover:text-cream hover:decoration-accent focus-visible:decoration-accent" @click="setMode('forgot')">{{ t('auth.forgot.link') }}</button></template>
                            <template #default="f"><PasswordInput :id="f.id" ref="password" v-model="form.password" :autocomplete="mode === 'signup' ? 'new-password' : 'current-password'" :describedby="f.describedby" :invalid="f.invalid" /></template>
                        </Field>
                        <!-- Left empty by people; bots fill it in. -->
                        <div class="absolute -left-[9999px]" aria-hidden="true"><label>{{ t('auth.nickname') }}<input v-model="form.nickname" tabindex="-1" autocomplete="off" /></label></div>
                        <!-- When you were born, month and year only: 13 and over can make an account, 18 opens the adult features. -->
                        <BirthDate v-if="mode === 'signup'" v-model:month="form.birthMonth" v-model:year="form.birthYear" :hint="t('auth.born.why')" :error="bornError || ''" />
                        <TurnstileBox v-if="config?.turnstileSiteKey && (mode === 'signup' || step === 'password')" :key="turnstileKey" :site-key="config.turnstileSiteKey" @token="turnstile = $event" @failed="turnstileFailed" />
                        <Notice v-if="refusal && refusalCode !== 'too_young'" tone="bad">{{ refusal }} <button v-if="refusalCode === 'email_unverified'" type="button" class="link" @click="resendVerification">{{ t('auth.sent.resend') }}</button></Notice>
                        <button type="submit" class="btn-primary btn-lg w-full" :disabled="busy || (mode === 'signup' && !(form.birthMonth && form.birthYear))" :aria-busy="busy ? 'true' : 'false'">{{ busy ? t('auth.working') : mode === 'signup' ? t('auth.createAccount') : step === 'email' ? t('auth.continue') : t('auth.signIn') }}</button>
                    </form>
                    <p v-if="mode === 'signup'" class="text-center text-sm text-muted">{{ t('auth.haveAccountQ') }} <button type="button" class="link-quiet min-h-11 font-semibold text-cream underline underline-offset-4" @click="setMode('signin')">{{ t('auth.signIn') }}</button></p>
                    <p v-else-if="signupsOpen" class="text-center text-sm text-muted">{{ t('auth.newHere') }} <button type="button" class="link-quiet min-h-11 font-semibold text-cream underline underline-offset-4" @click="setMode('signup')">{{ t('auth.createLink') }}</button></p>
                    <p v-else class="text-center text-sm text-muted">{{ t('auth.signupsSoon') }}</p>
                </template>
            </div>
        </div>`,
    setup: () => ({ REASONS, SIGN_UP, PROVIDERS, DONE: {
        verified: { title: tk('auth.verified.title'), body: tk('auth.verified.body') },
        reset: { title: tk('auth.reset.done'), body: tk('auth.reset.doneBody') },
        confirmed: { title: tk('auth.confirmed.title'), body: tk('auth.confirmed.body') },
        reverted: { title: tk('auth.revert.title'), body: tk('auth.revert.body') },
    }, formatWait }),
    computed: {
        page: () => route.params.page ?? 'signin',
        last: () => lastMethod(),
        /** Google and Apple, the last used first, as the server offers them (a method this app doesn't know yet isn't shown). */
        providers() {
            const offered = (this.config?.methods ?? ['google', 'apple', 'password']).filter((m) => m in PROVIDERS);
            return offered.sort((a, b) => (b === this.last) - (a === this.last));
        },
        /** Whether someone can make an account here now (paused sign-ups still open for an invite or early link). */
        signupsOpen() {
            return this.config?.signups !== 'paused' || !!this.invite || !!this.early;
        },
        hasPassword() {
            return (this.config?.methods ?? ['password']).includes('password');
        },
        sentText: (vm) => (vm.sentKind === 'reset' ? tk('auth.sent.reset') : tk('auth.sent.verify')),
        refusalCode() {
            return this.refusalError?.code ?? null;
        },
        bornError() {
            const e = this.errors ?? {};
            return [...(e.birthMonth ?? []), ...(e.birthYear ?? [])].join(' ') || (this.refusalCode === 'too_young' ? this.refusal : '');
        },
    },
    async created() {
        loadAuthConfig().then(
            (config) => (this.config = config),
            (error) => console.warn('The sign-in settings did not load', error),
        );
        if (this.page === 'signin/link') await this.openLink();
        else if (this.page === 'verify') await this.useLink(() => api.auth.verify(this.token), 'verified');
        else if (this.page === 'email/confirm') await this.useLink(() => api.account.email.confirm(this.token), 'confirmed');
        else if (this.page === 'email/revert') await this.useLink(() => api.account.email.revert(this.token), 'reverted');
    },
    mounted() {
        this.$nextTick(() => this.$refs.heading?.focus());
    },
    unmounted() {
        clearInterval(this.waitTimer);
    },
    watch: {
        mode() {
            this.$nextTick(() => this.$refs.heading?.focus());
        },
        sentTo() {
            this.$nextTick(() => this.$refs.heading?.focus());
        },
    },
    methods: {
        navigate,
        setMode(mode) {
            this.mode = mode;
            this.step = 'email';
            this.errors = null;
            this.refusal = null;
            this.refusalError = null;
            history.replaceState({}, '', mode === 'signin' ? '/signin' : `/signin?mode=${mode}`);
        },
        /** Each Turnstile token works once: after a request, the check starts again. */
        newCheck() {
            if (!this.config?.turnstileSiteKey) return;
            this.turnstile = null;
            this.turnstileKey++;
        },
        turnstileFailed() {
            this.refusal = t('auth.error.turnstileLoad');
        },
        /** Says what went wrong: field messages on their fields, everything else in words. */
        refuse(error) {
            this.refusalError = error instanceof ApiError ? error : null;
            if (error instanceof ApiError && error.code === 'validation_failed') {
                this.errors = error.errors ?? {};
                this.refusal = Object.keys(this.errors).length ? null : t('auth.error.generic');
                return;
            }
            if (error instanceof ApiError && error.code === 'rate_limited') {
                this.refusal = error.retryAfter ? t('auth.error.waitFor', { wait: formatWait(error.retryAfter) }) : t('auth.error.wait');
                return;
            }
            if (!(error instanceof ApiError) || !REFUSED[error.code]) console.error('Sign-in failed', error);
            this.refusal = t(REFUSED[error?.code] ?? tk('auth.error.generic'));
        },
        /** Not now: back to where they were, or the songs. */
        notNow() {
            goBack('/');
        },
        submit() {
            if (this.mode === 'signup') return this.signUp();
            if (this.step === 'email') return this.toPassword();
            return this.signIn();
        },
        /** Sign in's first step: the address only needs to look like one (nothing is looked up, so no one can learn who has an account). */
        toPassword() {
            this.refusal = null;
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.email.trim())) {
                this.errors = { email: [t('auth.error.emailShape')] };
                return;
            }
            this.errors = null;
            this.step = 'password';
            this.$nextTick(() => this.$el.querySelector('input[type=password], input[autocomplete=current-password]')?.focus());
        },
        changeEmail() {
            this.step = 'email';
            this.errors = null;
            this.refusal = null;
            this.$nextTick(() => this.$refs.email?.focus());
        },
        provider(name) {
            providerSignIn(name, { intended: this.next, invite: this.invite, early: this.early });
        },
        async signIn() {
            this.busy = true;
            this.errors = null;
            this.refusal = null;
            try {
                const { data } = await api.auth.signin({ email: this.form.email.trim(), password: this.form.password, remember: true, turnstileToken: this.turnstile }); // every web sign-in is remembered (the server keeps it 5 years)
                signedIn(data, 'password');
                this.go();
            } catch (error) {
                this.refuse(error);
                if (this.errors?.email) this.step = 'email';
            } finally {
                this.busy = false;
                this.newCheck();
            }
        },
        async signUp() {
            this.busy = true;
            this.errors = null;
            this.refusal = null;
            try {
                await api.auth.register({
                    name: this.form.name.trim(),
                    email: this.form.email.trim(),
                    password: this.form.password,
                    birthMonth: this.form.birthMonth,
                    birthYear: this.form.birthYear,
                    ref: ref(),
                    elapsedMs: Math.round(performance.now() - this.startedAt),
                    nickname: this.form.nickname,
                    turnstileToken: this.turnstile,
                    ...(this.invite ? { invite: this.invite } : {}),
                    ...(this.early ? { early: this.early } : {}),
                });
                tabStorage.set('stackapella.next', this.next);
                if (this.reason) count(`su_${this.reason}`); // an account made from an ask, by its reason (no one's details)
                this.sent(this.form.email.trim(), 'verify');
            } catch (error) {
                this.refuse(error);
            } finally {
                this.busy = false;
                this.newCheck();
            }
        },
        async forgot() {
            this.busy = true;
            this.refusal = null;
            try {
                await api.auth.password.forgot({ email: this.form.email.trim(), turnstileToken: this.turnstile });
                this.sent(this.form.email.trim(), 'reset');
            } catch (error) {
                this.refuse(error);
            } finally {
                this.busy = false;
                this.newCheck();
            }
        },
        async resendVerification() {
            await this.resend('verify', this.form.email.trim());
        },
        /** The link again, after the wait (a minute). */
        async resend(kind = this.sentKind, email = this.sentTo) {
            this.busy = true;
            try {
                if (kind === 'reset') await api.auth.password.forgot({ email, turnstileToken: this.turnstile });
                else await api.auth.verification.resend({ email, turnstileToken: this.turnstile });
                this.sent(email, kind);
                toast(t('auth.sent.again'));
            } catch (error) {
                this.refuse(error);
            } finally {
                this.busy = false;
                this.newCheck();
            }
        },
        sent(email, kind) {
            this.sentTo = email;
            this.sentKind = kind;
            this.wait = RESEND_SECONDS;
            clearInterval(this.waitTimer);
            this.waitTimer = setInterval(() => {
                this.wait = Math.max(0, this.wait - 1);
                if (!this.wait) clearInterval(this.waitTimer);
            }, 1000);
        },
        async reset() {
            this.busy = true;
            this.errors = null;
            this.refusal = null;
            try {
                const { data } = await api.auth.password.reset({ token: this.token, email: this.linkEmail, password: this.form.password });
                signedIn(data, 'password');
                toast(t('auth.reset.done'));
                this.go();
            } catch (error) {
                this.refuse(error);
            } finally {
                this.busy = false;
                this.newCheck();
            }
        },
        async useLink(request, done) {
            if (!this.token) {
                this.refusal = t(REFUSED.link_expired);
                return;
            }
            this.busy = true;
            try {
                const { data } = await request();
                if (done === 'reverted') {
                    this.revertedEmail = data.email;
                    account.me = null; // every session ended, this one too
                } else {
                    signedIn(data);
                }
                this.done = done;
            } catch (error) {
                this.refuse(error);
            } finally {
                this.busy = false;
                this.$nextTick(() => this.$refs.heading?.focus());
            }
        },
        /** Sign in by email: the link signs in (or makes the account), then on to where it was for. */
        async openLink() {
            if (!this.token) {
                this.linkProblem = 'expired';
                return;
            }
            this.busy = true;
            try {
                const answer = await api.auth.link.open(this.token);
                signedIn(answer.data, 'email');
                this.intended = safeNext(answer.intended);
                if (answer.needsProfile) {
                    this.form.name = answer.data.name ?? '';
                    this.profile = true;
                } else {
                    navigate(this.intended, { replace: true });
                }
            } catch (error) {
                if (error instanceof ApiError && error.code === 'link_used') this.linkProblem = 'used';
                else if (error instanceof ApiError && error.code === 'link_expired') this.linkProblem = 'expired';
                else this.refuse(error);
            } finally {
                this.busy = false;
                this.$nextTick(() => this.$refs.heading?.focus());
            }
        },
        /** A new account from a link: its name and when they were born, then on. */
        async saveProfile() {
            this.busy = true;
            this.errors = null;
            this.refusal = null;
            this.refusalError = null;
            try {
                const { data } = await api.account.update({ name: this.form.name.trim(), birthMonth: this.form.birthMonth, birthYear: this.form.birthYear });
                signedIn(data);
                navigate(this.intended, { replace: true });
            } catch (error) {
                this.refuse(error);
            } finally {
                this.busy = false;
            }
        },
        /** After the undo link: a reset link to the restored address, in one tap. */
        async choosePassword() {
            this.form.email = this.revertedEmail;
            this.done = null;
            await this.forgot();
        },
        /** On to where the person was going: a beta singer to their parts. */
        go() {
            const next = this.next;
            tabStorage.set('stackapella.next', '');
            tabStorage.set('stackapella.early', ''); // used: the account is made
            // A beta invite stays in the tab until the beta page has accepted it.
            if (this.invite && !account.me?.betaSinger) {
                navigate('/beta');
                return;
            }
            if (account.me?.betaSinger && (next === '/' || next.startsWith('/beta'))) navigate('/beta');
            else navigate(next);
        },
    },
};
