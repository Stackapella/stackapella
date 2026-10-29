// The invite family's card (Hillel's 75.png): who invited you (from the link's own data), what it's
// for, "Learn to sing harmony, one part at a time", and a four-part preview drawn from a real song's
// notes with your part lit; then the page's own actions (its slot). Signed out, the page shows an
// email with Accept invite (a sign-in link by email) and the providers. The views: join.js, early.js (and /invite), groups.js
// (/choir). Centred on the screen; "I already have an account" sits in the header.

import { api, ApiError } from '../api.js';
import { formatWait, t, tk } from '../i18n.js';
import { TurnstileBox } from '../turnstile.js';
import { navigate } from '../router.js';
import { account, loadAuthConfig, providerSignIn } from '../stores/account.js';
import { loadTemplate, templatePartName } from '../templates.js';
import { PART_COLORS } from '../theme.js';
import { paint } from '../colors.js';
import { Gems } from './gems.js';
import { Icon, Logo } from './icon.js';

const BEATS = 16; // the preview's stretch: the song's first line or so

export const InviteCard = {
    components: { Gems, Logo },
    props: {
        from: { type: String, default: '' }, // who invited you
        avatar: { type: String, default: null },
        kind: { type: String, default: '' }, // the line under it: the kind of invite, its expiry
        lead: { type: String, default: '' },
        song: { type: String, default: 'amazing-grace' }, // the template drawn
        part: { type: String, default: 'alto' }, // the part lit
        signIn: { type: String, default: '/signin' }, // where "I already have an account" goes
    },
    data: () => ({ parts: [] }),
    setup: () => ({ account, navigate }),
    template: `
        <div class="mx-auto flex min-h-dvh w-full max-w-lg flex-col px-4 pb-10 pt-[max(1rem,env(safe-area-inset-top))]">
            <header class="flex items-center justify-between gap-3 py-3">
                <a href="/" @click.prevent="navigate('/')"><Logo /></a>
                <a v-if="account.loaded && !account.me" :href="signIn" class="btn-secondary btn-sm min-h-11" @click.prevent="navigate(signIn)">{{ t('invite.haveAccount') }}</a>
            </header>
            <div class="flex flex-1 items-center py-6">
                <section class="card w-full space-y-5 p-6 md:p-7">
                    <slot name="top">
                        <div v-if="from" class="flex items-center gap-3">
                            <img v-if="avatar" :src="avatar" alt="" class="size-11 shrink-0 rounded-full object-cover" />
                            <span v-else class="gem grid size-11 shrink-0 place-items-center rounded-full text-sm font-extrabold" style="--gem: var(--color-part-4); --gem-ink: var(--color-ink)" aria-hidden="true">{{ initials }}</span>
                            <span class="min-w-0">
                                <span class="block font-bold text-cream" translate="no">{{ t('invite.from', { name: from }) }}</span>
                                <span v-if="kind" class="block text-sm text-muted">{{ kind }}</span>
                            </span>
                        </div>
                        <div>
                            <h1 class="text-3xl font-extrabold tracking-tight text-cream">{{ t('invite.headline') }}</h1>
                            <p v-if="lead" class="mt-2 text-muted">{{ lead }}</p>
                        </div>
                        <div v-if="rows.length" class="relative space-y-1.5 rounded-2xl border border-line bg-ink/40 p-4" aria-hidden="true">
                            <Gems measure />
                            <div v-for="row in rows" :key="row.key" class="grid grid-cols-[4.5rem_1fr] items-center gap-3">
                                <span class="truncate text-xs" :class="row.lit ? 'font-bold text-cream' : 'text-muted'">{{ row.name }}</span>
                                <span class="relative block h-2.5">
                                    <span v-for="(n, i) in row.notes" :key="i" class="absolute top-0 h-2.5" :style="{ insetInlineStart: n.x + '%', width: n.w + '%' }">
                                        <span class="block h-full" :data-gem="row.color" :data-gem-state="row.lit ? null : 'faint'"></span>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </slot>
                    <slot />
                </section>
            </div>
        </div>`,
    computed: {
        initials() {
            return this.from.split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toLocaleUpperCase();
        },
        /** Each part's first notes as bars along one line, your part lit. */
        rows() {
            return this.parts.slice(0, 4).map((p, i) => ({
                key: p.key,
                name: templatePartName(p),
                lit: p.key === this.part,
                color: paint(p.color ?? PART_COLORS[i % PART_COLORS.length]),
                notes: p.guide.notes.filter((n) => n.beat < BEATS).map((n) => ({ x: (n.beat / BEATS) * 100, w: Math.max(1.5, (Math.min(n.length, BEATS - n.beat) / BEATS) * 100 - 1) })),
            }));
        },
    },
    async created() {
        try {
            this.parts = (await loadTemplate(this.song)).parts;
        } catch (error) {
            console.warn('The invite preview did not load', error);
        }
    },
};

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PROVIDERS = { apple: tk('invite.apple'), google: tk('invite.google') };
const REFUSED = { blocked: tk('auth.error.blocked'), turnstile_required: tk('auth.error.turnstile'), turnstile_failed: tk('auth.error.turnstile'), offline: tk('common.offline') };

/**
 * Signed out: your email and Accept invite, which sends a sign-in link to that address (auth.link.send:
 * it signs in, or makes the account, and comes back to `next`), then says to check your email, with
 * Send it again after a minute; or Apple and Google. Never says whether the address has an account.
 */
export const InviteAccept = {
    components: { Icon, TurnstileBox },
    props: { next: { type: String, required: true }, early: { type: String, default: null }, button: { type: String, default: '' } },
    data: () => ({ email: '', error: null, config: null, sentTo: null, wait: 0, busy: false, turnstile: null, turnstileKey: 0 }),
    setup: () => ({ PROVIDERS, formatWait }),
    template: `
        <div v-if="sentTo" class="space-y-4" role="status">
            <p class="text-lg font-bold text-cream">{{ t('invite.sent.title') }}</p>
            <p class="text-muted">{{ t('invite.sent.body', { email: sentTo }) }}</p>
            <p v-if="error" class="field-error" role="alert"><Icon name="alert" :size="16" class="mt-px shrink-0" /><span>{{ error }}</span></p>
            <div class="flex flex-wrap gap-3">
                <button type="button" class="btn-secondary" :disabled="wait > 0 || busy" @click="send(sentTo)">{{ wait > 0 ? t('auth.sent.resendIn', { wait: formatWait(wait) }) : t('invite.sent.again') }}</button>
                <button type="button" class="btn-ghost" @click="sentTo = null">{{ t('auth.sent.wrongAddress') }}</button>
            </div>
        </div>
        <form v-else class="space-y-4" novalidate @submit.prevent="accept">
            <div class="space-y-1.5">
                <label for="invite-email" class="label">{{ t('invite.email') }}</label>
                <input id="invite-email" v-model="email" type="email" inputmode="email" autocomplete="email" autocapitalize="off" spellcheck="false" class="input" :placeholder="t('auth.emailPlaceholder')" :aria-invalid="error ? 'true' : null" :aria-describedby="error ? 'invite-email-error' : null" />
                <p v-if="error" id="invite-email-error" class="field-error"><Icon name="alert" :size="16" class="mt-px shrink-0" /><span>{{ error }}</span></p>
            </div>
            <TurnstileBox v-if="config?.turnstileSiteKey" :key="turnstileKey" :site-key="config.turnstileSiteKey" @token="turnstile = $event" @failed="turnstile = null" />
            <button type="submit" class="btn-primary btn-lg w-full" :disabled="busy">{{ busy ? t('auth.working') : button || t('invite.accept') }}</button>
            <template v-if="providers.length">
                <p class="flex items-center gap-3 text-sm text-muted"><span class="h-px flex-1 bg-line"></span>{{ t('invite.orWith') }}<span class="h-px flex-1 bg-line"></span></p>
                <div class="grid grid-cols-2 gap-3">
                    <button v-for="p in providers" :key="p" type="button" class="btn-secondary min-h-11 justify-center" @click="provider(p)">{{ t(PROVIDERS[p]) }}</button>
                </div>
            </template>
        </form>`,
    computed: {
        providers() {
            return (this.config?.methods ?? ['apple', 'google']).filter((m) => m in PROVIDERS).sort((a, b) => (a === 'apple' ? -1 : b === 'apple' ? 1 : 0));
        },
    },
    created() {
        loadAuthConfig().then((config) => (this.config = config), () => {});
    },
    unmounted() {
        clearInterval(this.waitTimer);
    },
    methods: {
        accept() {
            const email = this.email.trim();
            if (!EMAIL.test(email)) {
                this.error = t('auth.error.emailShape');
                return;
            }
            this.send(email);
        },
        async send(email) {
            this.busy = true;
            this.error = null;
            try {
                await api.auth.link.send({ email, intended: this.next, early: this.early, turnstileToken: this.turnstile });
                this.sentTo = email;
                // Send it again after a minute (the screen's own countdown; nothing plays).
                this.wait = 60;
                clearInterval(this.waitTimer);
                this.waitTimer = setInterval(() => {
                    this.wait = Math.max(0, this.wait - 1);
                    if (!this.wait) clearInterval(this.waitTimer);
                }, 1000);
            } catch (error) {
                if (!(error instanceof ApiError)) console.error('The sign-in link was not sent', error);
                this.error = error?.code === 'rate_limited' ? t('auth.error.waitFor', { wait: formatWait(error.retryAfter ?? 600) }) : error?.code === 'validation_failed' ? (error.errors?.email ?? []).join(' ') || t('auth.error.emailShape') : t(REFUSED[error?.code] ?? tk('auth.error.generic'));
            } finally {
                this.busy = false;
                if (this.config?.turnstileSiteKey) {
                    this.turnstile = null;
                    this.turnstileKey++;
                }
            }
        },
        provider(name) {
            providerSignIn(name, { intended: this.next, early: this.early });
        },
    },
};
