// AI voices (docs/ai-voices-plan.md, track A; docs/ai-voices-build-plan.md, phase 1). A part nobody has recorded yet may be
// sung by an AI guide voice, behind the ai_voices switch (auth.config `aiVoices`, read per account). Hillel's rule: "We
// clearly mark which are AI voices waiting to be replaced". So wherever a voice is credited or chosen (the studio's part
// panel and its rows, Sing, the lessons built on a song, Home's song sheet, Share's credits), an AI voice says so in words
// through the one AiVoiceLabel below, never a plain singer credit, with Be the voice beside it where the singer can act.
//
// A guide recording says what it is (contract: guide.recording.json `source`, "recorded" or "ai", and `aiBasedOn`, the
// singer it's based on, or null). With the switch off the server sends no AI guide; the app strips any it still holds
// (a copy kept on the device, an older answer) unless auth.config says the switch is on, so off means nothing changes,
// offline included. An AI part still counts as having no real voice (`hasRealGuide` false), so Be the voice keeps asking.

import { account } from './stores/account.js';
import { aiOn, aiUse } from './ai-choice.js';
import { voiceAskOpen } from './be-the-voice.js';
import { formatList, locale, t } from './i18n.js';
import { publicSiteUrl } from './hosts.js';
import { count } from './usage.js';
import { Icon } from './components/icon.js';

/**
 * Whether AI guide voices are on for this account: auth.config `aiVoices`, known to be true, and AI features on here
 * (ai-choice.js; off, every AI guide gives way to the practice voice).
 */
export function aiVoicesOn() {
    return account.config?.aiVoices === true && aiOn();
}

/** Whether a guide recording (or a session voice entry) is an AI voice. */
export function isAi(recording) {
    return recording?.source === 'ai' || recording?.ai === true;
}

/** A recording the app may use: every recorded one, and an AI one only while the switch is on. */
export function allowed(recording) {
    return !!recording && (!isAi(recording) || aiVoicesOn());
}

/**
 * The credit line for an AI voice: "AI voice based on Daniella G.'s singing, with permission" when it's based on a named
 * singer, else "AI voice, waiting for a real singer" (agreed with marketing so the app and the site say the same).
 */
export function aiCredit(basedOn, choir = false) {
    // A part made from the singer's own singing (Hear yourself as a choir, js/own-choir.js) says so in its own words.
    if (choir) return t('ownChoir.listen.label');
    return basedOn ? t('aiVoice.basedOn', { name: basedOn }) : t('aiVoice.waiting');
}

/** Any guide's credit as the singer reads it: an AI voice's line, "Sung by Sarah K.", or "Sung by one of our singers". */
export function voiceCredit(recording) {
    if (isAi(recording)) return aiCredit(recording.aiBasedOn ?? null, !!recording.choir);
    return recording?.credit ? t('part.sungBy', { name: recording.credit }) : t('part.sungByAnonymous');
}

/**
 * The site's About AI voices page, in the app's language (/about/ai-voices, /es/about/ai-voices), on the site's host:
 * the label's words lead there for anyone who wants more (design rules, "AI: plain about it, never loud"). It's live
 * whenever the switch is, and the label only shows then.
 */
export function aboutUrl() {
    const path = locale.lang === 'es' ? '/es/about/ai-voices' : '/about/ai-voices';
    return new URL(path, new URL(publicSiteUrl(), location.href)).href;
}

/** Where About opens: a new tab in a browser; the same window in the installed app, so it doesn't jump out of it. */
export function aboutTarget() {
    const installed = globalThis.matchMedia?.('(display-mode: standalone)').matches || navigator.standalone === true;
    return installed ? '_self' : '_blank';
}

/** Whether Be the voice may sit beside the label in this project: the call is open, and not inside a lesson or a beta part. */
export function aiCtaOpen(project) {
    // Never in a choir of your own (js/own-choir.js): its parts are yours to learn, not a call for a voice.
    return voiceAskOpen() && !project?.course && !project?.beta && !project?.choir;
}

/**
 * Counted once a playback start in which at least one AI part sings (the build plan's anonymous `ai_heard`): `voices` are
 * the session's guideVoices entries of the parts that sound.
 */
export function countAiHeard(voices) {
    if (!voices.some((voice) => voice?.kind === 'real' && voice.ai)) return;
    count('ai_heard');
    aiUse('guide_heard'); // AI optional's measure: once a day, with the band (ai-choice.js)
}

/**
 * The one label for an AI voice. `tag`: the short "AI voice" tag (a part row, the mixer, the part picker); else the
 * credit line in the singer's place. `cta`: Be the voice beside the line (emits `voice`). `plain`: the tag carries no
 * look of its own, so a host's piece (the arcade's part portrait) dresses it: game mode reskins what's there. `parts`: the
 * names of a song's AI parts, for a line about the song ("The alto and bass are AI voices, waiting for real singers.").
 */
export const AiVoiceLabel = {
    components: { Icon },
    // dot: a small dot in the accent instead of the sparkle, for a quiet row among others (the song sheet; Hillel's design).
    // choir: a part made from the singer's own singing ("AI voice made from your singing").
    props: { basedOn: { type: String, default: null }, choir: Boolean, tag: Boolean, cta: Boolean, plain: Boolean, dot: Boolean, part: { type: String, default: '' }, parts: { type: Array, default: null } },
    emits: ['voice'],
    setup: () => ({ aboutUrl, aboutTarget }),
    computed: {
        words() {
            return this.parts ? t('aiVoice.parts', { count: this.parts.length, parts: formatList(this.parts) }) : aiCredit(this.basedOn, this.choir);
        },
    },
    template: `
        <span v-if="tag && plain" data-ai-voice="tag">{{ t('aiVoice.tag') }}</span>
        <span v-else-if="tag" data-ui="tag" data-tone="cyan" class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-raised px-2 py-0.5 text-[10px] font-bold text-accent-strong" data-ai-voice="tag"><Icon name="sparkle" :size="10" />{{ t('aiVoice.tag') }}</span>
        <span v-else-if="dot" class="flex gap-2.5" data-ai-voice="line">
            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>
            <span class="min-w-0"><a :href="aboutUrl()" :target="aboutTarget()" rel="noopener" class="link-quiet py-1" :title="t('aiVoice.about')" data-ai-voice-words @click.stop>{{ words }}</a><button v-if="cta" type="button" class="link tap-44 ms-1.5 font-semibold" :aria-label="part ? t('aiVoice.goPart', { part }) : null" data-ai-voice-go @click.prevent.stop="$emit('voice')">{{ t('voices.be.go') }}</button></span>
        </span>
        <span v-else class="inline-flex flex-wrap items-center gap-x-2 gap-y-0" data-ai-voice="line">
            <span class="inline-flex items-center gap-1.5"><Icon name="sparkle" :size="14" class="shrink-0 text-accent-strong" /><a :href="aboutUrl()" :target="aboutTarget()" rel="noopener" class="link-quiet py-1 underline decoration-dotted underline-offset-2" :title="t('aiVoice.about')" data-ai-voice-words @click.stop>{{ words }}</a></span>
            <button v-if="cta" type="button" class="link tap-44 text-sm" :aria-label="part ? t('aiVoice.goPart', { part }) : null" data-ai-voice-go @click.prevent.stop="$emit('voice')">{{ t('voices.be.go') }}</button>
        </span>`,
};
