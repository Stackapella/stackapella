// Contact us (contract/api.md, support.create; Hillel's 112.png): from Settings (and the help pages'
// "Write to us from the app"), a message to Hillel: what it's about as chips, the message (its
// placeholder asking what helps for that topic), an email to reply to (the account's, signed in), and
// the device's details as a switch, on for a recording problem or something broken, with exactly what
// goes along a tap away (never any audio). Send says what it's waiting for until it's ready; the
// thanks names the email and the message's reference. Replies come by email. In shield mode
// Cloudflare's check comes first, as at sign-up.

import { api, APP_VERSION, ApiError } from '../api.js';
import { audio, engine } from '../audio.js';
import { describeDevice } from '../beta-outbox.js';
import { formatWait, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { account, loadAuthConfig } from '../stores/account.js';
import { TurnstileBox } from '../turnstile.js';
import { Field, Notice } from '../components/form.js';
import { Switch } from '../components/controls.js';
import { Icon } from '../components/icon.js';
import { PageHeader } from '../components/page.js';

// What it's about, and the support category each is sent as (contract/api.md support.create).
const TOPICS = {
    recording: { word: tk('contact.topic.recording'), placeholder: tk('contact.placeholder.recording'), category: 'recording', details: true },
    broken: { word: tk('contact.topic.broken'), placeholder: tk('contact.placeholder.broken'), category: 'bug', details: true },
    idea: { word: tk('contact.topic.idea'), placeholder: tk('contact.placeholder.idea'), category: 'idea', details: false },
    account: { word: tk('contact.topic.account'), placeholder: tk('contact.placeholder.account'), category: 'account', details: false },
    other: { word: tk('contact.topic.other'), placeholder: tk('contact.placeholder.other'), category: 'other', details: false },
};
const REFUSED = { turnstile_required: tk('contact.refused.check'), turnstile_failed: tk('contact.refused.check'), offline: tk('common.offline') };
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export const ContactView = {
    components: { Field, Switch, Icon, Notice, PageHeader, TurnstileBox },
    data: () => ({ topic: 'recording', message: '', email: '', details: true, detailsChosen: false, showSent: false, website: '', busy: false, sent: null, errors: null, refusal: null, config: null, turnstile: null, turnstileKey: 0 }),
    setup: () => ({ TOPICS, account, navigate }),
    template: `
        <div class="min-h-dvh bg-ink px-4 pb-16 pt-[max(0.5rem,env(safe-area-inset-top))] md:px-8">
            <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 md:gap-6">
                <PageHeader :title="t('contact.title')" back="/settings" />
                <p class="-mt-3 text-muted md:-mt-5">{{ t('contact.intro') }}</p>
                <section v-if="sent" class="card space-y-3 p-6" role="status">
                    <Icon name="check" :size="28" class="text-good" />
                    <h2 class="text-xl font-bold text-cream">{{ t('contact.sentTitle', { email: shownEmail }) }}</h2>
                    <p v-if="sent.id" class="text-sm text-muted">{{ t('contact.reference', { id: sent.id }) }}</p>
                    <button type="button" class="btn-secondary self-start" @click="navigate('/')">{{ t('contact.backHome') }}</button>
                </section>
                <form v-else class="flex flex-col gap-4" @submit.prevent="send">
                    <div class="card flex flex-col gap-5 p-5 md:p-6">
                        <div class="space-y-2">
                            <p id="contact-topic" class="text-sm font-semibold text-cream">{{ t('contact.about') }}</p>
                            <div class="flex flex-wrap gap-2" role="radiogroup" aria-labelledby="contact-topic">
                                <button v-for="(about, id) in TOPICS" :key="id" type="button" role="radio" :aria-checked="topic === id ? 'true' : 'false'" class="filter-chip" @click="choose(id)">{{ t(about.word) }}</button>
                            </div>
                        </div>
                        <Field :label="t('contact.message')" :error="errors?.message" required v-slot="f">
                            <textarea :id="f.id" v-model="message" rows="6" required maxlength="5000" class="input" :placeholder="t(TOPICS[topic].placeholder)" :aria-describedby="f.describedby" :aria-invalid="f.invalid"></textarea>
                        </Field>
                        <Field :label="t('contact.email')" :error="errors?.email" required v-slot="f">
                            <div v-if="account.me" class="flex items-center gap-2">
                                <input :id="f.id" :value="account.me.email" type="email" readonly class="input min-w-0 flex-1 text-muted" />
                                <a href="/settings" class="btn-ghost btn-sm" @click.prevent="navigate('/settings')">{{ t('contact.changeEmail') }}</a>
                            </div>
                            <input v-else :id="f.id" v-model="email" type="email" inputmode="email" autocomplete="email" required class="input" :placeholder="t('contact.emailPlaceholder')" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                        </Field>
                        <div class="border-t border-line pt-3">
                            <Switch :on="details" :label="t('contact.details')" :hint="t('contact.detailsHint', { device: context.device, version: context.appVersion })" @change="setDetails" />
                            <button type="button" class="link-quiet min-h-11 text-sm text-accent" :aria-expanded="showSent ? 'true' : 'false'" aria-controls="contact-sent" @click="showSent = !showSent">{{ showSent ? t('contact.hideSent') : t('contact.seeSent') }}</button>
                            <div v-if="showSent" id="contact-sent" class="space-y-2">
                                <dl v-if="details" class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-2xl bg-raised p-3 text-xs text-muted">
                                    <template v-for="(value, key) in shownContext" :key="key"><dt class="font-semibold">{{ t(key) }}</dt><dd class="min-w-0 break-words" translate="no">{{ value }}</dd></template>
                                </dl>
                                <p class="text-xs text-muted">{{ details ? t('contact.sentAlong') : t('contact.sentNothing') }}</p>
                            </div>
                        </div>
                        <!-- Left empty by people; bots fill it in. -->
                        <div class="absolute -start-[9999px]" aria-hidden="true"><label>{{ t('auth.nickname') }}<input v-model="website" tabindex="-1" autocomplete="off" /></label></div>
                        <TurnstileBox v-if="config?.turnstileSiteKey" :key="turnstileKey" :site-key="config.turnstileSiteKey" @token="turnstile = $event" @failed="turnstile = null" />
                        <Notice v-if="refusal" tone="bad">{{ refusal }}</Notice>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-x-4 gap-y-2">
                        <p v-if="waiting" class="text-sm text-muted" role="status">{{ waiting }}</p>
                        <button type="submit" class="btn-primary" :disabled="busy || !!waiting">{{ busy ? t('contact.sending') : t('contact.send') }}</button>
                    </div>
                </form>
            </div>
        </div>`,
    computed: {
        shownEmail() {
            return account.me?.email ?? this.email.trim();
        },
        /** What Send is waiting for, or nothing once it's ready. */
        waiting() {
            if (!this.message.trim()) return t('contact.need.message');
            if (!account.me && !EMAIL.test(this.email.trim())) return t('contact.need.email');
            return '';
        },
        /** What goes along with the message, as the person sees it before sending. */
        context() {
            return {
                appVersion: APP_VERSION,
                browser: navigator.userAgent,
                device: describeDevice(),
                page: new URLSearchParams(location.search).get('from'),
                ...(audio.latencyFrames != null ? { timingCheck: { latencyMs: Math.round((audio.latencyFrames / (engine.sampleRate || 48000)) * 1000) } } : {}),
            };
        },
        shownContext() {
            const c = this.context;
            return {
                [tk('contact.context.version')]: c.appVersion,
                [tk('contact.context.device')]: c.device,
                [tk('contact.context.browser')]: c.browser,
                ...(c.page ? { [tk('contact.context.page')]: c.page } : {}),
                ...(c.timingCheck ? { [tk('contact.context.timing')]: `${c.timingCheck.latencyMs} ms` } : {}),
            };
        },
    },
    created() {
        loadAuthConfig().then(
            (config) => (this.config = config),
            (error) => console.warn('The contact settings did not load', error),
        );
    },
    methods: {
        /** A topic, with the device's details on for a recording problem or something broken, unless the person chose. */
        choose(id) {
            this.topic = id;
            if (!this.detailsChosen) this.details = TOPICS[id].details;
        },
        setDetails(on) {
            this.details = on;
            this.detailsChosen = true;
        },
        async send() {
            this.busy = true;
            this.errors = null;
            this.refusal = null;
            try {
                const body = {
                    category: TOPICS[this.topic].category,
                    message: this.message.trim(),
                    ...(account.me ? {} : { email: this.email.trim() }),
                    ...(this.details ? { context: this.context } : {}),
                    website: this.website,
                    ...(this.turnstile ? { turnstileToken: this.turnstile } : {}),
                };
                const { data } = await api.support.create({ body });
                this.sent = { id: data?.id ?? null };
            } catch (error) {
                if (!(error instanceof ApiError)) throw error;
                if (error.code === 'validation_failed') this.errors = error.errors;
                else if (error.code === 'rate_limited') this.refusal = t('contact.refused.wait', { wait: formatWait(error.retryAfter ?? 600) });
                else this.refusal = t(REFUSED[error.code] ?? tk('contact.refused.other'));
                this.turnstile = null;
                this.turnstileKey++;
            } finally {
                this.busy = false;
            }
        },
    },
};
