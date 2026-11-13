// "Let's get your mic working": the one modal every screen's mic failure opens (js/mic-help.js).
// What went wrong in plain words; this browser's steps (the others under "Using something else?");
// the system's switch on a Mac or a PC; a big Try again, and on an iPhone Reload and try; Copy link
// in an app's own browser; the raw error under Details for support. It tries again by itself when
// the browser says the mic is now allowed, and when the person comes back from Settings.

import { audio, engine, openMic } from '../audio.js';
import { t } from '../i18n.js';
import { adviceFor, boldParts, BROWSER_WORDS, CAUSES, closeMicHelp, DEVICE_WORDS, DRAWINGS, micHelp, placeHere, PLATFORMS, reloadForMic, SYSTEMS } from '../mic-help.js';
import { micDb } from '../mic-check.js';
import { Icon } from './icon.js';
import { LevelMeter } from './level-meter.js';
import { Modal, toast } from './ui.js';
import { count } from '../usage.js';

const HEARD_MS = 1500; // the meter shows this long once the mic works, then the screen carries on

export const MicHelpHost = {
    components: { Icon, LevelMeter, Modal },
    data: () => ({ micHelp, chosen: null, permission: null, picking: false, failedOnce: false, state: 'blocked', db: null, showHow: false }),
    setup: () => ({ CAUSES, PLATFORMS, SYSTEMS, DEVICE_WORDS, boldParts }),
    template: `
        <Modal v-if="micHelp.primer && !micHelp.open" :title="t('micHelp.primerTitle')" @close="micHelp.primer(false)">
            <div class="space-y-5" data-mic-primer>
                <p class="flex items-start gap-3 text-cream"><Icon name="mic" :size="24" class="mt-0.5 shrink-0 text-accent" /><span>{{ t('micHelp.primerBody') }}</span></p>
                <p class="text-sm text-muted">{{ t('micHelp.primerNext') }}</p>
                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" class="btn-ghost" @click="micHelp.primer(false)">{{ t('micHelp.notNow') }}</button>
                    <button type="button" class="btn-primary" @click="micHelp.primer(true)">{{ t('micHelp.primerGo') }}</button>
                </div>
            </div>
        </Modal>
        <!-- v2 (77.png): the state is the header (blocked, then checking, then hearing you); the setup as
             a sentence with Change; the browser's steps, numbered, beside a small drawing of where to
             click; the device's steps folded away until a retry fails; Try again last. -->
        <Modal v-if="micHelp.open" :title="headerTitle" :subtitle="headerLine" :tone="refusedGently ? null : state === 'hearing' ? 'good' : micHelp.trying ? 'busy' : 'bad'" wide @close="close">
            <div class="space-y-5" data-mic-help :data-cause="advice.cause" :data-state="state">
                <!-- A refusal as a choice (mic2's arm b): no error, a way on without the mic, the steps one tap away. -->
                <template v-if="refusedGently">
                    <p class="text-cream" data-mic-refused>{{ t('mic2.refused.body') }}</p>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <button type="button" class="btn-primary max-sm:w-full max-sm:justify-center" data-mic-refused-listen @click="listenInstead">{{ t('mic2.later.listen') }}</button>
                        <button type="button" class="btn-secondary max-sm:w-full max-sm:justify-center" data-mic-refused-how @click="showHow = true">{{ t('mic2.refused.how') }}</button>
                    </div>
                </template>
                <template v-else-if="state === 'hearing'">
                    <LevelMeter :db="db" />
                </template>
                <template v-else>
                    <p v-if="advice.showSteps || chosen" data-ui="facts" class="flex flex-wrap items-baseline gap-x-2 text-sm text-muted">
                        <span><span v-for="(part, i) in setupParts" :key="i" :class="part.bold ? 'font-bold text-cream' : ''">{{ part.text }}</span></span>
                        <button type="button" class="tap-44 font-semibold text-accent-strong" :aria-expanded="picking ? 'true' : 'false'" @click="picking = !picking">{{ t('micHelp.change') }}</button>
                    </p>
                    <div v-if="picking" class="flex flex-wrap gap-2">
                        <button v-for="(p, id) in PLATFORMS" :key="id" type="button" class="tap-44 chip hover:text-cream" :aria-pressed="platform === id ? 'true' : 'false'" :class="platform === id ? 'border-accent text-cream' : ''" @click="chosen = id; picking = false">{{ t(p.name) }}</button>
                    </div>
                    <div v-if="advice.reload || advice.copyLink" class="flex flex-wrap gap-2">
                        <button v-if="advice.copyLink" type="button" class="btn-secondary" @click="copyLink"><Icon name="arrow" :size="16" /> {{ t('micHelp.copyLink') }}</button>
                        <button v-if="advice.reload" type="button" class="btn-secondary" @click="reload"><Icon name="loop" :size="16" /> {{ t('micHelp.reload') }}</button>
                    </div>
                    <div v-if="advice.showSteps || chosen" class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                        <ol class="space-y-3" :aria-label="t('micHelp.stepsFor', { name: t(shown.name) })">
                            <li v-for="(step, i) in shown.steps" :key="step" class="flex items-start gap-3 text-cream">
                                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-cream text-sm font-extrabold text-ink" aria-hidden="true">{{ i + 1 }}</span>
                                <span class="pt-0.5"><span v-for="(part, k) in boldParts(t(step, { browser: shown.browser ?? '' }))" :key="k" :class="part.bold ? 'font-bold' : ''">{{ part.text }}</span></span>
                            </li>
                        </ol>
                        <!-- Where to click, drawn: the address bar's icon ringed, the Microphone switch on. -->
                        <div v-if="drawing" class="w-full rounded-2xl border border-edge bg-ink p-3 sm:w-44" aria-hidden="true" data-mic-drawing>
                            <div class="flex items-center gap-2 rounded-full bg-raised px-2 py-1.5">
                                <span class="grid size-5 place-items-center rounded-full ring-2 ring-accent"><Icon :name="drawing.icon" :size="12" class="text-cream" /></span>
                                <span class="h-1.5 flex-1 rounded-full bg-line"></span>
                            </div>
                            <div class="mt-2 flex items-center justify-between rounded-lg bg-raised px-2 py-1.5 text-[11px] font-semibold text-cream">{{ t(drawing.label) }}<span class="relative h-3.5 w-6 rounded-full bg-good"><span class="absolute end-0.5 top-0.5 size-2.5 rounded-full bg-cream"></span></span></div>
                        </div>
                    </div>
                    <details v-if="advice.system" ref="system" class="rounded-2xl border border-edge p-3" :open="failedOnce">
                        <summary class="cursor-pointer list-none">
                            <span class="block font-semibold text-cream">{{ t('micHelp.stillBlocked', { device: t(DEVICE_WORDS[advice.system]) }) }}</span>
                            <span class="block text-xs text-muted">{{ t('micHelp.stillBlockedHint', { device: t(DEVICE_WORDS[advice.system]) }) }}</span>
                        </summary>
                        <ol class="mt-3 space-y-2">
                            <li v-for="(step, i) in SYSTEMS[advice.system].steps" :key="step" class="flex items-start gap-3 text-sm text-cream">
                                <span class="grid size-6 shrink-0 place-items-center rounded-full border border-line text-xs font-bold" aria-hidden="true">{{ shown.steps.length + i + 1 }}</span>
                                <span><span v-for="(part, k) in boldParts(t(step))" :key="k" :class="part.bold ? 'font-bold' : ''">{{ part.text }}</span></span>
                            </li>
                        </ol>
                    </details>
                </template>
                <div v-if="!refusedGently" class="flex items-center justify-between gap-3 border-t border-edge pt-4">
                    <button type="button" class="btn-ghost btn-sm" :title="t('micHelp.copyDetails')" @click="copyDetails">{{ t('micHelp.details') }}</button>
                    <button v-if="state !== 'hearing'" type="button" class="btn-primary" :disabled="micHelp.trying" @click="tryAgain">{{ micHelp.trying ? t('micHelp.trying') : t('micHelp.tryAgain') }}</button>
                </div>
            </div>
        </Modal>`,
    computed: {
        userAgent: () => navigator.userAgent,
        advice() {
            return adviceFor(micHelp.error, placeHere());
        },
        platform() {
            return this.chosen ?? this.advice.platform;
        },
        shown() {
            return PLATFORMS[this.platform] ?? PLATFORMS.chrome;
        },
        /** "For Chrome on Mac": the setup as a sentence, the names in bold. */
        setupParts() {
            const [browser, device] = BROWSER_WORDS[this.platform] ?? [null, null];
            return boldParts(t('micHelp.setup', { browser: browser ? t(browser) : t('micHelp.yourBrowser'), device: t(device ?? DEVICE_WORDS[this.advice.device.os] ?? DEVICE_WORDS.other) }));
        },
        drawing() {
            return DRAWINGS[this.platform] ?? null;
        },
        /** The header carries the state: what's wrong, then checking, then hearing you. */
        /** A refusal on a screen that treats it as a choice, before Show me how. */
        refusedGently() {
            return !!micHelp.gentle && this.advice.cause === 'blocked' && !this.showHow && this.state !== 'hearing' && !micHelp.trying;
        },
        headerTitle() {
            if (this.refusedGently) return t('mic2.refused.title');
            if (this.state === 'hearing') return t('micHelp.hearing');
            if (micHelp.trying) return t('micHelp.checking');
            return t(CAUSES[this.advice.cause].title);
        },
        headerLine() {
            if (this.refusedGently) return '';
            if (this.state === 'hearing') return t('micHelp.hearingLine');
            if (micHelp.trying) return t('micHelp.checkingLine');
            if (this.failedOnce && this.advice.system) return t('micHelp.stillLine', { device: t(DEVICE_WORDS[this.advice.system]) });
            return t(CAUSES[this.advice.cause].short ?? CAUSES[this.advice.cause].body, { app: this.advice.device.inApp ?? '', browser: this.browserName });
        },
        browserName() {
            const [browser] = BROWSER_WORDS[this.advice.platform] ?? [];
            return browser ? t(browser) : t('micHelp.yourBrowser');
        },
        tried() {
            return (micHelp.attempts ?? []).map((a) => `${a.try}: ${a.ok ? 'ok' : a.error}`).join(', ') || '-';
        },
    },
    watch: {
        'micHelp.open': {
            handler(open) {
                if (open) Object.assign(this, { state: 'blocked', failedOnce: false, picking: false, showHow: false });
                if (open && !micHelp.attempts?.length) micHelp.attempts = engine.micAttempts ?? [];
                if (open) this.watchPermission();
                else this.unwatch();
            },
            immediate: true,
        },
    },
    unmounted() {
        this.unwatch();
    },
    methods: {
        /** Tries again the moment the browser says the mic is allowed, and on coming back to the page. */
        async watchPermission() {
            this.chosen = null;
            this.onVisible = () => document.visibilityState === 'visible' && this.recheck();
            document.addEventListener('visibilitychange', this.onVisible);
            try {
                this.status = await navigator.permissions?.query({ name: 'microphone' });
            } catch (error) {
                console.warn('The mic permission cannot be watched here', error);
                this.status = null;
            }
            if (!this.status) return;
            this.permission = this.status.state;
            this.status.onchange = () => {
                this.permission = this.status.state;
                if (this.status.state === 'granted') this.tryAgain();
            };
        },
        unwatch() {
            if (this.onVisible) document.removeEventListener('visibilitychange', this.onVisible);
            if (this.status) this.status.onchange = null;
            this.onVisible = null;
            this.status = null;
        },
        async recheck() {
            if (!micHelp.open || micHelp.trying) return;
            try {
                const state = (await navigator.permissions?.query({ name: 'microphone' }))?.state;
                this.permission = state ?? this.permission;
                if (state === 'granted') this.tryAgain();
            } catch (error) {
                console.warn('The mic permission could not be read', error);
            }
        },
        /** Opens the mic (from the tap); once it works, the screen that asked carries on. */
        async tryAgain() {
            if (micHelp.trying) return;
            micHelp.trying = true;
            try {
                await openMic();
            } catch (error) {
                Object.assign(micHelp, { error: { name: error?.name ?? 'Error', message: error?.message ?? '' }, attempts: engine.micAttempts ?? [], trying: false });
                // Still blocked after a try: the device's own switch is the likely cause, so its steps open.
                this.failedOnce = true;
                return;
            }
            // It works: the header turns to hearing you, with the meter, then the screen carries on.
            micHelp.trying = false;
            this.state = 'hearing';
            const meter = () => {
                this.db = micDb();
                this.frame = requestAnimationFrame(meter);
            };
            meter();
            await new Promise((resolve) => setTimeout(resolve, HEARD_MS));
            cancelAnimationFrame(this.frame);
            const carryOn = micHelp.retry;
            closeMicHelp();
            toast(t('micHelp.working', { name: audio.mic?.label || t('micHelp.yourMic') }));
            if (carryOn) await carryOn();
        },
        /** Details for support: one tap copies them, for a message. */
        async copyDetails() {
            const text = [`${t('micHelp.detailsError')}: ${micHelp.error?.name ?? ''}: ${micHelp.error?.message ?? ''}`, `${t('micHelp.detailsTried')}: ${this.tried}`, `${t('micHelp.detailsPermission')}: ${this.permission ?? '?'}`, `${t('micHelp.detailsBrowser')}: ${navigator.userAgent}`].join('\n');
            try {
                await navigator.clipboard.writeText(text);
                toast(t('micHelp.detailsCopied'));
            } catch (error) {
                console.warn('Copying the details failed', error);
                toast(text, { seconds: 15 });
            }
        },
        async copyLink() {
            try {
                await navigator.clipboard.writeText(location.href);
                toast(t('micHelp.copied'));
            } catch (error) {
                console.warn('Copying the link failed', error);
                toast(location.href, { seconds: 15 });
            }
        },
        reload: reloadForMic,
        close: closeMicHelp,
        /** Listen through it, from a refusal: the screen's own way on without the mic. */
        listenInstead() {
            const listen = micHelp.gentle?.listen;
            closeMicHelp();
            listen?.();
        },
    },
};

/**
 * Before the mic is asked for, in an app's own browser (Instagram, TikTok, Facebook...), which often
 * can't share it (docs/onboarding-review.md, step 1): say so first, with how to open the page in
 * the phone's browser and Copy link. The lesson's own button carries on anyway.
 */
/**
 * An Android intent that opens `url` in Chrome, out of an app's own browser; where Chrome isn't installed, the browser
 * falls back to the same page.
 */
export function chromeIntent(url) {
    const page = new URL(url);
    return `intent://${page.host}${page.pathname}${page.search}#Intent;scheme=${page.protocol.replace(':', '')};package=com.android.chrome;S.browser_fallback_url=${encodeURIComponent(page.href)};end`;
}

export const InAppCard = {
    components: { Icon },
    props: { app: { type: String, required: true } },
    setup: () => ({ browser: /Android/.test(navigator.userAgent) ? 'Chrome' : 'Safari', chromeLink: /Android/.test(navigator.userAgent) ? chromeIntent(location.href) : null }), // i18n-ignore: browsers' names
    template: `
        <div class="space-y-3 rounded-2xl border border-line bg-panel p-4" role="status" data-in-app>
            <p class="flex items-center gap-2 font-bold text-cream"><Icon name="mic" :size="18" class="text-warn" />{{ t('course.mic.inAppTitle') }}</p>
            <p class="text-sm text-muted">{{ t('course.mic.inApp', { app, browser }) }}</p>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                <!-- Android: one tap out of the app's browser into Chrome, on this same page (the first-week UX review). -->
                <a v-if="chromeLink" :href="chromeLink" class="btn-secondary max-sm:w-full max-sm:justify-center" data-in-app-chrome @click="count('inapp_handoff')">{{ t('course.mic.openInChrome') }}</a>
                <button type="button" :class="chromeLink ? 'btn-ghost btn-sm self-start' : 'btn-secondary btn-sm'" @click="copyLink"><Icon name="arrow" :size="16" /> {{ t('micHelp.copyLink') }}</button>
            </div>
        </div>`,
    methods: {
        count,
        async copyLink() {
            try {
                await navigator.clipboard.writeText(location.href);
                toast(t('micHelp.copied'));
            } catch (error) {
                console.warn('Copying the link failed', error);
                toast(location.href, { seconds: 15 });
            }
        },
    },
};
