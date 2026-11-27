// Contact us (contract/api.md, support.create; Hillel's 112.png; docs/feedback-plan.md): from Settings, the help pages,
// an error message's "Tell us what happened", the choir ask and the site's 404, a message to us: what it's about as
// chips, the message (its placeholder asking what helps for that topic), an email to reply to, and what goes along with
// it. Replies come by email. In shield mode Cloudflare's check comes first, as at sign-up. Send diagnostics
// (/contact?diagnostics=1, from the mic's help) is the same form about sound, sent as `diagnostics`, with what goes
// along shown open: the device and this tab's sound problems (js/sound-log.js), each line as it's sent.
//
// With the feedback2 switch on (auth.config's `feedback2`): no topic is chosen for you (a link may start on one, as an
// error's starts on Something's broken); the details go along only for Recording or sound and Something's broken, shown
// as "Sent with it" above Send with Change, each item its own tick; a guest's email is optional ("only if you'd like a
// reply"), an account's is shown read-only with "Don't reply to me"; Back and Done go to `from` when it's an app path
// on this host, else Settings; and a guest who left no email is told we can't reply. The choir ask's "Something's off"
// opens it with the choir song and what's off as chips. Off, the form is as it was: Recording or sound chosen, the
// email required, Back to Settings. Either way the device goes as a kind and a browser family, never the user agent.
// The address is read through js/feedback.js, which checks every value a link carries.

import { api, APP_VERSION, ApiError } from '../api.js';
import { audio, engine } from '../audio.js';
import { BROWSER_NAMES, CHOIR_ISSUES, browserFamily, deviceKind, readContact } from '../feedback.js';
import { soundLog } from '../sound-log.js';
import { formatWait, locale, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { account, loadAuthConfig } from '../stores/account.js';
import { appearance } from '../stores/appearance.js';
import { count } from '../usage.js';
import { TurnstileBox } from '../turnstile.js';
import { Field, Notice } from '../components/form.js';
import { Switch } from '../components/controls.js';
import { Icon } from '../components/icon.js';
import { AppScreen } from '../components/page.js';

// What it's about, and the support category each is sent as (contract/api.md support.create).
const TOPICS = {
    recording: { word: tk('contact.topic.recording'), placeholder: tk('contact.placeholder.recording'), category: 'recording', details: true },
    broken: { word: tk('contact.topic.broken'), placeholder: tk('contact.placeholder.broken'), category: 'bug', details: true },
    idea: { word: tk('contact.topic.idea'), placeholder: tk('contact.placeholder.idea'), category: 'idea', details: false },
    account: { word: tk('contact.topic.account'), placeholder: tk('contact.placeholder.account'), category: 'account', details: false },
    other: { word: tk('contact.topic.other'), placeholder: tk('contact.placeholder.other'), category: 'other', details: false },
};
// What's off, from the choir ask (support.create's context.choirIssue).
const ISSUES = { voice: tk('contact.choirIssue.voice'), timing: tk('contact.choirIssue.timing'), notes: tk('contact.choirIssue.notes'), other: tk('contact.choirIssue.other') };
const DEVICES = { phone: tk('contact.device.phone'), tablet: tk('contact.device.tablet'), computer: tk('contact.device.computer') };
const REFUSED = { turnstile_required: tk('contact.refused.check'), turnstile_failed: tk('contact.refused.check'), offline: tk('common.offline') };
const SKIN_ON = tk('contact.context.on');
const SUMMARY_GAME_ON = tk('contact.summary.gameOn');
const SUMMARY_GAME_OFF = tk('contact.summary.gameOff');
const SKIN_OFF = tk('contact.context.off');
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** The device, as "Phone, Safari". */
const deviceWords = (kind, family) => [t(DEVICES[kind]), BROWSER_NAMES[family]].filter(Boolean).join(', ');

export const ContactView = {
    components: { Field, Switch, Icon, Notice, AppScreen, TurnstileBox },
    data() {
        const q = readContact(location.search);
        return {
            q,
            topic: q.topic,
            issue: null,
            message: '',
            email: '',
            noReply: false,
            details: null, // null: as the topic says (none chosen: off); true or false once chosen
            off: [], // the items turned off in the list, by key
            showSent: q.diagnostics,
            website: '',
            busy: false,
            sent: null,
            errors: null,
            refusal: null,
            config: null,
            turnstile: null,
            turnstileKey: 0,
        };
    },
    setup: () => ({ TOPICS, ISSUES, CHOIR_ISSUES, APP_VERSION, account, navigate }),
    template: `
        <AppScreen :title="q.diagnostics ? t('contact.diagnostics.title') : t('contact.title')" :back="backTo" measure="max-w-2xl" content-class="gap-4 pt-4 md:gap-6" data-ui="screen" class="bg-ink">
                <p class="-mt-3 text-muted md:-mt-5">{{ q.diagnostics ? t('contact.diagnostics.intro') : fresh ? t('contact.hint') : t('contact.intro') }}</p>
                <section v-if="sent" ref="sent" tabindex="-1" class="card space-y-3 p-6 outline-none" role="status" data-contact-sent>
                    <Icon name="check" :size="28" class="text-good" />
                    <h2 class="text-xl font-bold text-cream">{{ replyTo ? t('contact.sentTitle', { email: replyTo }) : t('contact.sentDone') }}</h2>
                    <p v-if="!replyTo && !account.me" class="text-cream" data-cant-reply>{{ t('contact.cantReply') }}</p>
                    <p v-if="sent.id" class="text-sm text-muted">{{ t('contact.reference', { id: sent.id }) }}</p>
                    <button type="button" class="btn-secondary self-start" data-contact-done @click="done">{{ fresh ? t('common.done') : t('contact.backHome') }}</button>
                </section>
                <form v-else class="flex flex-col gap-4" novalidate @submit.prevent="send">
                    <div class="card flex flex-col gap-5 p-5 md:p-6">
                        <div v-if="choirAsk" class="space-y-2" data-choir-issues>
                            <p id="contact-issue" class="text-sm font-semibold text-cream">{{ t('contact.choirIssue.title') }}</p>
                            <div class="flex flex-wrap gap-2" role="radiogroup" aria-labelledby="contact-issue">
                                <button v-for="id in CHOIR_ISSUES" :key="id" type="button" role="radio" :aria-checked="issue === id ? 'true' : 'false'" class="filter-chip min-h-11" :data-issue="id" @click="issue = issue === id ? null : id">{{ t(ISSUES[id]) }}</button>
                            </div>
                        </div>
                        <div v-else-if="!q.diagnostics" class="space-y-2" data-topics>
                            <p id="contact-topic" class="text-sm font-semibold text-cream">{{ t('contact.about') }} <span v-if="fresh" class="font-normal text-muted">{{ t('form.optional') }}</span></p>
                            <div class="flex flex-wrap gap-2" role="radiogroup" aria-labelledby="contact-topic">
                                <button v-for="(about, id) in TOPICS" :key="id" type="button" role="radio" :aria-checked="shownTopic === id ? 'true' : 'false'" class="filter-chip min-h-11" :data-topic="id" @click="choose(id)">{{ t(about.word) }}</button>
                            </div>
                        </div>
                        <Field :label="t('contact.message')" :error="errors?.message" required v-slot="f">
                            <textarea :id="f.id" v-model="message" rows="6" required maxlength="5000" class="input" :placeholder="placeholder" :aria-describedby="f.describedby" :aria-invalid="f.invalid" data-contact-message></textarea>
                        </Field>
                        <Field :label="t('contact.email')" :error="errors?.email" :required="!!account.me || !fresh" :help="!account.me && fresh ? t('contact.emailOptional') : null" v-slot="f">
                            <div v-if="account.me" class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <input :id="f.id" :value="account.me.email" type="email" readonly class="input min-w-0 flex-1 text-muted" />
                                    <a v-if="!fresh" href="/settings" class="btn-ghost btn-sm" @click.prevent="navigate('/settings')">{{ t('contact.changeEmail') }}</a>
                                </div>
                                <label v-if="fresh" class="flex min-h-11 items-center gap-3 text-sm text-cream" data-no-reply><input v-model="noReply" type="checkbox" class="checkbox" /> {{ t('contact.noReply') }}</label>
                            </div>
                            <input v-else :id="f.id" v-model="email" type="email" inputmode="email" autocomplete="email" :required="!fresh" class="input" :placeholder="t('contact.emailPlaceholder')" :aria-describedby="f.describedby" :aria-invalid="f.invalid" data-contact-email />
                        </Field>
                        <div v-if="fresh && !q.diagnostics" class="border-t border-line pt-3" data-sent-with>
                            <div class="flex flex-wrap items-center justify-between gap-x-4">
                                <p class="min-w-0 flex-1 text-sm text-muted">
                                    <template v-if="sentItems.length"><span class="font-semibold text-cream">{{ t('contact.sentWith') }}</span> <span data-sent-summary>{{ summary }}</span></template>
                                    <template v-else>{{ account.me ? t('contact.sentNothingAccount') : t('contact.sentNothingGuest') }}</template>
                                </p>
                                <button type="button" class="link-quiet min-h-11 text-sm text-accent" :aria-expanded="showSent ? 'true' : 'false'" aria-controls="contact-sent" data-sent-change @click="showSent = !showSent">{{ t('contact.changeEmail') }}</button>
                            </div>
                            <div v-if="showSent" id="contact-sent" class="mt-2 space-y-2">
                                <Switch :on="detailsOn" :label="t('contact.details')" @change="details = $event" />
                                <ul v-if="detailsOn" class="space-y-1 rounded-2xl bg-raised p-3 text-sm" data-diag-details>
                                    <li v-for="item in items" :key="item.key">
                                        <label class="flex min-h-11 items-start gap-3 py-1"><input type="checkbox" class="checkbox mt-0.5" :checked="!off.includes(item.key)" :data-item="item.key" @change="toggle(item.key, $event.target.checked)" />
                                            <span class="min-w-0"><span class="block font-semibold text-cream">{{ t(item.label) }}</span><span class="block whitespace-pre-line break-words text-muted" translate="no" :data-sent="item.key">{{ item.shown }}</span></span></label>
                                    </li>
                                </ul>
                                <p v-if="account.me" class="text-xs text-muted" data-sent-account>{{ t('contact.context.account') }}: <span translate="no">{{ account.me.email }}</span></p>
                                <p class="text-xs text-muted">{{ t('contact.sentAlong') }}</p>
                            </div>
                        </div>
                        <div v-else class="border-t border-line pt-3">
                            <Switch :on="detailsOn" :label="t('contact.details')" :hint="t('contact.detailsHint', { device: deviceShown, version: APP_VERSION })" @change="details = $event" />
                            <button type="button" class="link-quiet min-h-11 text-sm text-accent" :aria-expanded="showSent ? 'true' : 'false'" aria-controls="contact-sent" @click="showSent = !showSent">{{ showSent ? t('contact.hideSent') : t('contact.seeSent') }}</button>
                            <div v-if="showSent" id="contact-sent" class="space-y-2">
                                <dl v-if="detailsOn" data-diag-details class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-2xl bg-raised p-3 text-xs text-muted">
                                    <template v-for="item in items" :key="item.key"><dt class="font-semibold">{{ t(item.label) }}</dt><dd class="min-w-0 whitespace-pre-line break-words" translate="no" :data-sent="item.key">{{ item.shown }}</dd></template>
                                </dl>
                                <p class="text-xs text-muted">{{ detailsOn ? t('contact.sentAlong') : t('contact.sentNothing') }}</p>
                            </div>
                        </div>
                        <!-- Left empty by people; bots fill it in. -->
                        <div class="absolute -start-[9999px]" aria-hidden="true"><label>{{ t('auth.nickname') }}<input v-model="website" tabindex="-1" autocomplete="off" /></label></div>
                        <TurnstileBox v-if="config?.turnstileSiteKey" :key="turnstileKey" :site-key="config.turnstileSiteKey" @token="turnstile = $event" @failed="turnstile = null" />
                        <Notice v-if="refusal" tone="bad">{{ refusal }}</Notice>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-x-4 gap-y-2">
                        <p v-if="waiting" class="text-sm text-muted" role="status">{{ waiting }}</p>
                        <button type="submit" class="btn-primary" :disabled="busy || !!waiting" data-contact-send>{{ busy ? t('contact.sending') : t('contact.send') }}</button>
                    </div>
                </form>
        </AppScreen>`,
    computed: {
        /** The feedback2 switch (auth.config's `feedback2`). */
        fresh: () => !!account.config?.feedback2,
        choirAsk() {
            return this.fresh && this.q.choirAsk;
        },
        /** Where Back goes: `from` when it's an app path here (feedback2, and always for Send diagnostics), else Settings. */
        backTo() {
            return this.fresh || this.q.diagnostics ? this.q.back : '/settings';
        },
        /** The topic shown chosen: none until one is (feedback2), else Recording or sound as before. */
        shownTopic() {
            return this.topic ?? (this.fresh ? null : 'recording');
        },
        placeholder() {
            if (this.q.diagnostics) return t('contact.diagnostics.placeholder');
            return t(TOPICS[this.shownTopic]?.placeholder ?? tk('contact.placeholder.other'));
        },
        /** Whether what's listed goes along: as chosen, else on for a sound or broken topic, Send diagnostics and the choir ask. */
        detailsOn() {
            if (this.details !== null) return this.details;
            if (this.q.diagnostics || this.choirAsk) return true;
            return !!TOPICS[this.shownTopic]?.details;
        },
        /** A sound topic: the timing check and this visit's sound problems go too (always, with feedback2 off, as before). */
        sound() {
            return this.q.diagnostics || this.choirAsk || this.shownTopic === 'recording' || !this.fresh;
        },
        /** This tab's sound problems (js/sound-log.js), read as the form opens. */
        sounds: () => soundLog(),
        device: () => ({ kind: deviceKind(navigator.userAgent, navigator.maxTouchPoints ?? 0), family: browserFamily(navigator.userAgent) }),
        /** The app's language, named in itself ("English", "español"), or its code where the browser can't name it. */
        languageName() {
            try {
                return new Intl.DisplayNames([locale.lang], { type: 'language' }).of(locale.lang) ?? locale.lang;
            } catch (error) {
                console.debug('No language name', error?.message ?? error);
                return locale.lang;
            }
        },
        deviceShown() {
            return deviceWords(this.device.kind, this.device.family);
        },
        /** Everything that can go along, each with its label, what's shown and what's sent. */
        items() {
            const q = this.q;
            const page = q.page ?? q.from;
            const latency = audio.latencyFrames != null ? Math.round((audio.latencyFrames / (engine.sampleRate || 48000)) * 1000) : null;
            const skin = appearance.skin === 'serious' ? 'serious' : 'game';
            const list = [
                page && { key: 'page', label: tk('contact.context.page'), shown: page, sent: { page } },
                { key: 'appVersion', label: tk('contact.context.version'), shown: APP_VERSION, sent: { appVersion: APP_VERSION } },
                { key: 'device', label: tk('contact.context.device'), shown: this.deviceShown, sent: { device: this.device.kind, browser: this.device.family } },
                this.fresh && { key: 'language', label: tk('contact.context.language'), shown: this.languageName, sent: { language: locale.lang } },
                this.fresh && { key: 'skin', label: tk('contact.context.skin'), shown: t(skin === 'game' ? SKIN_ON : SKIN_OFF), sent: { skin } },
                this.sound && latency != null && { key: 'timingCheck', label: tk('contact.context.timing'), shown: t('common.ms', { ms: latency }), sent: { timingCheck: { latencyMs: latency } } },
                this.sound && this.sounds.length && { key: 'audioErrors', label: tk('contact.context.sound'), shown: this.sounds.join('\n'), sent: { audioErrors: this.sounds } },
                // Send diagnostics says so even when there's none, so the person knows what it looked for.
                q.diagnostics && !this.sounds.length && { key: 'audioErrors', label: tk('contact.context.sound'), shown: t('contact.diagnostics.noSound'), sent: {} },
                (q.templateId || q.partKey) && { key: 'song', label: tk('contact.context.song'), shown: [q.templateId, q.partKey].filter(Boolean).join(' · '), sent: { ...(q.templateId ? { templateId: q.templateId } : {}), ...(q.partKey ? { partKey: q.partKey } : {}) } },
                q.choirRequestId && { key: 'choir', label: tk('contact.context.choir'), shown: q.choirRequestId, sent: { choirRequestId: q.choirRequestId } },
                q.errorCode && { key: 'error', label: tk('contact.context.error'), shown: q.errorCode, sent: { errorCode: q.errorCode } },
            ];
            return list.filter(Boolean);
        },
        /** The items that go, as chosen. */
        sentItems() {
            return this.detailsOn ? this.items.filter((item) => !this.off.includes(item.key)) : [];
        },
        /** "This page · app 2026.11.27 · Phone, Safari", and what the moment adds. */
        summary() {
            const skin = this.items.find((item) => item.key === 'skin')?.sent.skin;
            const short = {
                page: t('contact.summary.page'),
                appVersion: t('contact.summary.version', { version: APP_VERSION }),
                device: this.deviceShown,
                language: this.languageName,
                skin: t(skin === 'game' ? SUMMARY_GAME_ON : SUMMARY_GAME_OFF),
            };
            return this.sentItems.map((item) => short[item.key] ?? t(item.label)).join(' · ');
        },
        context() {
            const context = Object.assign({}, ...this.sentItems.map((item) => item.sent));
            if (this.choirAsk && this.issue) context.choirIssue = this.issue;
            return context;
        },
        /** The address a reply goes to, or null: a guest who left none, or Don't reply to me. */
        replyTo() {
            if (account.me) return this.fresh && this.noReply ? null : account.me.email;
            return this.email.trim() || null;
        },
        /** What Send is waiting for, or nothing once it's ready. */
        waiting() {
            if (!this.message.trim()) return t('contact.need.message');
            const typed = this.email.trim();
            if (account.me) return '';
            if (!this.fresh && !EMAIL.test(typed)) return t('contact.need.email');
            if (this.fresh && typed !== '' && !EMAIL.test(typed)) return t('contact.need.validEmail');
            return '';
        },
        /** Where it was opened from, as support.create's `moment`. */
        moment() {
            return this.q.moment ?? (this.q.diagnostics ? 'mic' : null);
        },
    },
    created() {
        loadAuthConfig().then(
            (config) => {
                this.config = config;
                if (config?.feedback2) count(`fb_open:${this.moment ?? 'direct'}`);
            },
            (error) => console.warn('The contact settings did not load', error),
        );
    },
    mounted() {
        // Focus on the heading at open, so a screen reader starts at the top of the form.
        const heading = this.$el?.querySelector?.('h1');
        if (heading) {
            heading.tabIndex = -1;
            heading.focus({ preventScroll: true });
        }
    },
    methods: {
        /** A topic (tapped again with feedback2 on, none). The details follow it unless the person chose. */
        choose(id) {
            this.topic = this.fresh && this.topic === id ? null : id;
        },
        toggle(key, on) {
            this.off = on ? this.off.filter((k) => k !== key) : [...this.off, key];
        },
        done() {
            navigate(this.fresh ? this.q.back : '/');
        },
        async send() {
            if (this.waiting) return;
            this.busy = true;
            this.errors = null;
            this.refusal = null;
            try {
                const email = this.email.trim();
                const category = this.q.diagnostics ? 'diagnostics' : this.choirAsk ? 'recording' : TOPICS[this.shownTopic]?.category;
                const body = {
                    message: this.message.trim(),
                    ...(category ? { category } : {}),
                    ...(this.moment ? { moment: this.moment } : {}),
                    ...(!account.me && email ? { email } : {}),
                    ...(account.me && this.fresh && this.noReply ? { reply: false } : {}),
                    ...(Object.keys(this.context).length ? { context: this.context } : {}),
                    website: this.website,
                    ...(this.turnstile ? { turnstileToken: this.turnstile } : {}),
                };
                const { data } = await api.support.create({ body });
                this.sent = { id: data?.id ?? null };
                // The sent card replaces the form, which had focus: it takes it, and is announced (role=status).
                this.$nextTick(() => this.$refs.sent?.focus());
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
