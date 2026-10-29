// The beta singers' pages (docs/beta-singers-plan.md, "The singer's experience"): the invite link
// (/beta/<token>), signing up or in with it, the welcome, consent, and My parts (/beta), with Tell
// Hillel from anywhere. Singers have accounts: an invite is accepted by the account that opens it
// (contract/api.md, "Templates and the beta").

import { api, ApiError } from '../api.js';
import { formatDate, formatList, locale, t, timeAgo, tk } from '../i18n.js';
import { keyLabel } from '../theme.js';
import { local, session as tabStorage } from '../storage.js';
import { account, loadAccount, signOut } from '../stores/account.js';
import { navigate } from '../router.js';
import { catalogue, loadCatalogue } from '../stores/catalogue.js';
import { loadTemplate, phrases, templatePartName } from '../templates.js';
import { EmptyState, Field, Notice, StatusChip } from '../components/form.js';
import { BETA_STATUS as STATUS } from '../components/beta.js';
import { Icon, Logo } from '../components/icon.js';
import { Modal, toast } from '../components/ui.js';
import { Switch } from '../components/controls.js';
import { betaBar, keepBar, startBetaRecording, TemplateChanged } from '../beta.js';
import { discard, loadOutbox, outbox, sendAll } from '../beta-outbox.js';
import { BetaClip } from '../components/beta-clip.js';

// How a part is to be sung (its style: the part's own, else the song's), said on its card.
const STYLE_WORDS = { straight: tk('beta.parts.style.straight'), free: tk('beta.parts.style.free'), vibrato: tk('beta.parts.style.vibrato') };

const REFUSED = { not_yours: tk('beta.outbox.not_yours'), not_recordable: tk('beta.outbox.not_recordable'), template_changed: tk('beta.outbox.template_changed'), validation_failed: tk('beta.outbox.validation_failed'), lost: tk('beta.outbox.lost'), not_beta_singer: tk('beta.outbox.notSinger'), singer_revoked: tk('beta.outbox.notSinger'), below_bar: tk('beta.outbox.belowBar') };

const LINK_ENDED = { invite_not_found: tk('beta.ended.not_found'), invite_expired: tk('beta.ended.expired'), invite_revoked: tk('beta.ended.revoked') };

/** **Bold** in the agreement's text, as safe segments ({ text, bold }): never HTML. */
export function boldSegments(text) {
    return (text ?? '').split(/(\*\*[^*]+\*\*)/).filter(Boolean).map((part) => (part.startsWith('**') && part.endsWith('**') ? { text: part.slice(2, -2), bold: true } : { text: part, bold: false }));
}

/**
 * Whether this browser can record a guide well: an AudioWorklet, the mic, storage, and not an iOS
 * older than 16.4 (every iPhone browser is Safari's engine) or a Mac Safari older than 16.4.
 */
export function browserCanRecord(ua = navigator.userAgent, has = window) {
    const basics = !!(has.AudioWorkletNode && has.navigator?.mediaDevices?.getUserMedia && has.indexedDB);
    const before = (major, minor) => Number(major) < 16 || (Number(major) === 16 && Number(minor ?? 0) < 4);
    const ios = /(?:iPhone|iPad|iPod).* OS (\d+)_(\d+)/.exec(ua);
    const safari = /Version\/(\d+)\.(\d+).*Safari/.exec(ua);
    const old = ios ? before(ios[1], ios[2]) : !!safari && !/Chrome|Chromium|Edg|Firefox/.test(ua) && before(safari[1], safari[2]);
    return basics && !old;
}

const Agreement = {
    props: { agreement: { type: Object, required: true } },
    setup: () => ({ boldSegments }),
    template: `
        <div class="space-y-3 text-sm text-cream" translate="no">
            <template v-for="(block, i) in agreement.blocks" :key="i">
                <h3 v-if="block.type === 'heading'" class="pt-2 text-base font-bold">{{ block.text }}</h3>
                <p v-else-if="block.type === 'paragraph'"><template v-for="(seg, j) in boldSegments(block.text)" :key="j"><strong v-if="seg.bold">{{ seg.text }}</strong><template v-else>{{ seg.text }}</template></template></p>
                <ul v-else-if="block.type === 'list'" class="list-disc space-y-1 ps-5">
                    <li v-for="(item, k) in block.items" :key="k"><template v-for="(seg, j) in boldSegments(item)" :key="j"><strong v-if="seg.bold">{{ seg.text }}</strong><template v-else>{{ seg.text }}</template></template></li>
                </ul>
            </template>
        </div>`,
};

/** Tell Hillel: a note, with the song, part and place attached when there is one. */
export const TellHillel = {
    components: { Modal, Field, Notice },
    props: { context: { type: Object, default: () => ({}) } }, // { templateId, partKey, beat }
    emits: ['close'],
    data: () => ({ text: '', busy: false, error: null }),
    template: `
        <Modal :title="t('beta.tell.title')" @close="$emit('close')">
            <form class="space-y-4" @submit.prevent="send">
                <p class="text-sm text-muted">{{ t('beta.tell.intro') }}</p>
                <Field :label="t('beta.tell.note')" :error="error" required v-slot="f">
                    <textarea :id="f.id" v-model="text" rows="4" maxlength="2000" class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid"></textarea>
                </Field>
                <p v-if="context.templateId" class="text-xs text-muted">{{ t('beta.tell.attached') }}</p>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" :disabled="busy" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn-primary" :disabled="busy">{{ busy ? t('beta.tell.sending') : t('common.send') }}</button>
                </div>
            </form>
        </Modal>`,
    methods: {
        async send() {
            if (!this.text.trim()) {
                this.error = t('beta.tell.empty');
                return;
            }
            this.busy = true;
            try {
                await api.beta.feedback.create({ text: this.text.trim(), ...this.context });
                toast(t('beta.tell.sent'));
                this.$emit('close');
            } catch (error) {
                this.error = error instanceof ApiError && error.code === 'validation_failed' ? Object.values(error.errors ?? {}).flat().join(' ') || t('beta.tell.failed') : error?.code === 'offline' ? t('common.offline') : t('beta.tell.failed');
            } finally {
                this.busy = false;
            }
        },
    },
};

export const BetaView = {
    components: { Logo, Icon, Notice, StatusChip, EmptyState, Field, Agreement, TellHillel, Modal, Switch, BetaClip },
    props: { token: { type: String, default: null } },
    data: () => ({
        step: 'loading', // loading | retry | ended | invite | signin | notSinger | welcome | consent | parts
        invite: null, // the invite link's { name, status }
        ended: null, // why the link can't be used
        acceptError: null, // why accepting the invite failed, in words
        unverified: false, // the account's email isn't confirmed yet (accepting needs it)
        me: null,
        parts: null,
        clips: [],
        phraseOf: {}, // a retake's noted phrase, numbered from 1, by the part's id
        agreement: null,
        credit: { mode: 'name' },
        adult: false,
        consentErrors: null,
        busy: false,
        telling: false,
        editingCredit: false,
        canRecord: browserCanRecord(),
        // iPhone or iPad Safari, not the installed app: recording is better in the app.
        installHint: /iPhone|iPad/.test(navigator.userAgent) && !(navigator.standalone || matchMedia?.('(display-mode: standalone)').matches),
        playing: null, // the singer's own voice playing: { url, audio }
        consentGeneral: null, // a consent refusal that isn't about one field
        retryMessage: '',
    }),
    setup: () => ({ STATUS, STYLE_WORDS, LINK_ENDED, REFUSED, catalogue, account, templatePartName, formatDate, formatList, keyLabel, outbox, discard, betaBar, timeAgo }),
    template: `
        <div class="min-h-dvh bg-ink">
            <header class="border-b border-line">
                <div class="mx-auto flex max-w-3xl items-center gap-3 px-4 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))]">
                    <Logo :size="20" /><span class="chip-accent">{{ t('beta.badge') }}</span>
                    <button v-if="me" type="button" class="btn-ghost btn-sm ms-auto" @click="telling = true"><Icon name="info" :size="16" /> {{ t('beta.tell.button') }}</button>
                </div>
            </header>
            <div class="mx-auto max-w-3xl space-y-6 px-4 py-8 pb-[max(2rem,env(safe-area-inset-bottom))]">
                <p v-if="step === 'loading'" class="text-muted" role="status">{{ t('beta.loading') }}</p>

                <section v-else-if="step === 'retry'" class="card space-y-3 p-6 text-center">
                    <h1 class="text-xl font-bold text-cream">{{ t('beta.retry.title') }}</h1>
                    <p class="text-muted">{{ retryMessage }}</p>
                    <button type="button" class="btn-primary" @click="start">{{ t('common.tryAgain') }}</button>
                </section>

                <section v-else-if="step === 'ended'" class="card space-y-3 p-6 text-center">
                    <Icon name="alert" :size="32" class="mx-auto text-warn" />
                    <h1 class="text-xl font-bold text-cream">{{ t(LINK_ENDED[ended] ?? LINK_ENDED.invite_expired) }}</h1>
                    <p class="text-muted">{{ t('beta.ended.askHillel') }}</p>
                    <button v-if="!account.me" type="button" class="btn-secondary" @click="signIn('signin')">{{ t('beta.signIn.already') }}</button>
                </section>

                <section v-else-if="step === 'invite'" class="space-y-5">
                    <p class="eyebrow">{{ t('beta.welcome.eyebrow') }}</p>
                    <h1 class="text-3xl font-extrabold tracking-tight text-cream">{{ t('beta.welcome.title', { name: invite.name }) }}</h1>
                    <p class="max-w-prose text-cream">{{ t('beta.welcome.who') }}</p>
                    <p class="max-w-prose rounded-2xl border border-edge p-4 text-cream"><Icon name="info" :size="18" class="me-1 inline align-[-3px] text-accent" /> {{ t('beta.bar.line', { pitch: betaBar.pitchPercent, timing: betaBar.timingPercent }) }} <span class="text-muted">{{ t('beta.bar.checks') }}</span></p>
                    <p class="max-w-prose text-muted">{{ account.me ? t('beta.invite.signedIn', { name: account.me.name }) : t('beta.invite.account') }}</p>
                    <Notice v-if="acceptError" tone="bad">{{ acceptError }} <button v-if="unverified" type="button" class="link" :disabled="busy" @click="resendVerification">{{ t('auth.sent.resend') }}</button></Notice>
                    <div v-if="account.me" class="flex flex-wrap gap-3">
                        <button type="button" class="btn-primary btn-lg" :disabled="busy" @click="join">{{ t('beta.invite.join', { name: account.me.name }) }}</button>
                        <button type="button" class="btn-secondary btn-lg" :disabled="busy" @click="otherAccount">{{ t('beta.notSinger.otherAccount') }}</button>
                    </div>
                    <div v-else class="flex flex-wrap gap-3">
                        <button type="button" class="btn-primary btn-lg" @click="signIn('signup')">{{ t('beta.invite.makeAccount') }}</button>
                        <button type="button" class="btn-secondary btn-lg" @click="signIn('signin')">{{ t('beta.invite.haveAccount') }}</button>
                    </div>
                    <Notice v-if="installHint" tone="info">{{ t('beta.invite.install') }}</Notice>
                </section>

                <section v-else-if="step === 'signin'" class="card space-y-4 p-6 text-center">
                    <h1 class="text-2xl font-bold text-cream">{{ t('beta.signIn.title') }}</h1>
                    <p class="text-muted">{{ invite?.status === 'accepted' ? t('beta.signIn.accepted') : t('beta.signIn.intro') }}</p>
                    <button type="button" class="btn-primary" @click="signIn('signin')">{{ t('auth.signIn') }}</button>
                </section>

                <section v-else-if="step === 'notSinger'" class="card space-y-3 p-6 text-center">
                    <h1 class="text-xl font-bold text-cream">{{ ended === 'singer_revoked' ? t('beta.notSinger.revoked') : t('beta.notSinger.title') }}</h1>
                    <p class="text-muted">{{ acceptError ?? t('beta.notSinger.body', { name: account.me?.name ?? '' }) }}</p>
                    <button type="button" class="btn-ghost" @click="otherAccount">{{ t('beta.notSinger.otherAccount') }}</button>
                </section>

                <section v-else-if="step === 'welcome'" class="space-y-5">
                    <p class="eyebrow">{{ t('beta.welcome.eyebrow') }}</p>
                    <h1 class="text-3xl font-extrabold tracking-tight text-cream">{{ t('beta.welcome.title', { name: me.name }) }}</h1>
                    <p class="max-w-prose text-cream">{{ t('beta.welcome.who') }}</p>
                    <p class="max-w-prose text-muted">{{ t('beta.welcome.what') }}</p>
                    <p class="max-w-prose rounded-2xl border border-edge p-4 text-cream"><Icon name="info" :size="18" class="me-1 inline align-[-3px] text-accent" /> {{ t('beta.bar.line', { pitch: betaBar.pitchPercent, timing: betaBar.timingPercent }) }} <span class="text-muted">{{ t('beta.bar.checks') }}</span></p>
                    <div v-if="parts && parts.length" class="card p-5">
                        <h2 class="font-bold text-cream">{{ t('beta.welcome.asked') }}</h2>
                        <ul class="mt-2 space-y-1 text-sm text-cream">
                            <li v-for="part in parts" :key="part.id"><span translate="no">{{ part.template.title }}</span>: {{ partName(part) }}</li>
                        </ul>
                    </div>
                    <div v-if="sampleUrl" class="card space-y-2 p-5">
                        <h2 class="font-bold text-cream">{{ t('beta.welcome.sample') }}</h2>
                        <audio :src="sampleUrl" controls preload="none" class="w-full"></audio>
                    </div>
                    <Notice v-if="!canRecord" tone="warn">{{ t('beta.welcome.browser') }}</Notice>
                    <button type="button" class="btn-primary btn-lg" @click="toConsent">{{ t('beta.welcome.continue') }}</button>
                </section>

                <section v-else-if="step === 'consent' && agreement" class="space-y-5">
                    <h1 class="text-2xl font-bold text-cream" translate="no">{{ agreement.title }}</h1>
                    <Notice v-if="!agreement.reviewed" tone="warn">{{ t('beta.consent.draft') }}</Notice>
                    <div class="card max-h-[50dvh] overflow-y-auto p-5" tabindex="0" role="region" :aria-label="t('beta.consent.agreement')"><Agreement :agreement="agreement" /></div>
                    <form class="card space-y-4 p-5" @submit.prevent="consent">
                        <fieldset class="space-y-2">
                            <legend class="label">{{ t('beta.consent.creditAs') }}</legend>
                            <label class="check-row"><input v-model="credit.mode" type="radio" value="name" class="radio" /> <span>{{ t('beta.consent.byProfileName') }} <span class="font-semibold" translate="no">{{ account.me?.name }}</span></span></label>
                            <label class="check-row"><input v-model="credit.mode" type="radio" value="anonymous" class="radio" /> {{ t('beta.consent.anonymous') }}</label>
                            <p class="help">{{ t('beta.credit.fromProfile') }} <a href="/settings" class="link" @click.prevent="navigate('/settings')">{{ t('beta.credit.changeName') }}</a></p>
                        </fieldset>
                        <label class="check-row"><input v-model="adult" type="checkbox" class="checkbox" /> {{ t('beta.consent.adult') }}</label>
                        <p v-if="consentErrors?.adult" class="text-sm text-bad" role="alert">{{ consentErrors.adult.join(' ') }}</p>
                        <p v-if="consentGeneral" class="text-sm text-bad" role="alert">{{ consentGeneral }}</p>
                        <button type="submit" class="btn-primary" :disabled="busy || !adult">{{ t('beta.consent.accept') }}</button>
                    </form>
                </section>

                <!-- My parts (Hillel's 91.png): what needs you first, then what's coming, then what's live,
                     counted at the top; Hillel's note as a note; one status a card; one credit card. -->
                <section v-else-if="step === 'parts'" class="space-y-6">
                    <div class="space-y-3">
                        <h1 class="text-3xl font-extrabold tracking-tight text-cream">{{ t('beta.parts.title') }}</h1>
                        <p v-if="parts?.length" class="flex flex-wrap gap-2 text-xs font-semibold text-cream">
                            <span v-if="groups.retakes" class="flex items-center gap-1.5 rounded-full border border-line px-3 py-1"><span class="size-1.5 rounded-full bg-warn" aria-hidden="true"></span>{{ t('beta.parts.count.retake', { count: groups.retakes }) }}</span>
                            <span v-if="groups.coming.length" class="flex items-center gap-1.5 rounded-full border border-line px-3 py-1"><span class="size-1.5 rounded-full bg-muted" aria-hidden="true"></span>{{ t('beta.parts.count.coming', { count: groups.coming.length }) }}</span>
                            <span v-if="groups.library.length" class="flex items-center gap-1.5 rounded-full border border-line px-3 py-1"><span class="size-1.5 rounded-full bg-good" aria-hidden="true"></span>{{ t('beta.parts.count.library', { count: groups.library.length }) }}</span>
                        </p>
                    </div>
                    <Notice v-if="!canRecord" tone="warn">{{ t('beta.welcome.browser') }}</Notice>
                    <Notice v-else-if="installHint" tone="info">{{ t('beta.parts.install') }}</Notice>
                    <section v-if="outbox.takes.length" class="card space-y-3 p-5" aria-labelledby="outbox-heading">
                        <h2 id="outbox-heading" class="font-bold text-cream">{{ t('beta.outbox.title') }}</h2>
                        <div v-for="take in outbox.takes" :key="take.id" class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="text-cream">{{ titleOf(take) }}</span>
                            <span v-if="take.state === 'refused'" class="flex flex-wrap items-center gap-2"><span class="text-muted">{{ t(REFUSED[take.refusal] ?? REFUSED.validation_failed) }}</span><button type="button" class="btn-ghost btn-sm" @click="discard(take.id)">{{ t('beta.outbox.remove') }}</button></span>
                            <span v-else class="text-muted">{{ take.state === 'sending' || outbox.sending ? t('beta.outbox.sending') : take.refusal === 'offline' ? t('beta.outbox.offline') : take.refusal === 'unauthenticated' ? t('beta.outbox.signIn') : t('beta.outbox.waiting') }}</span>
                        </div>
                        <p v-if="outbox.takes.some((x) => x.state === 'waiting')" class="help">{{ t('beta.outbox.wifiTip') }}</p>
                        <button v-if="outbox.takes.some((x) => x.state !== 'refused') && !outbox.sending" type="button" class="btn-secondary btn-sm" @click="sendNow">{{ t('beta.outbox.sendNow') }}</button>
                    </section>
                    <p v-if="!parts" class="text-muted" role="status">{{ t('beta.loading') }}</p>
                    <EmptyState v-else-if="!parts.length && !clips.length" icon="note" :title="t('beta.parts.none')" :body="t('beta.parts.noneHint')" />
                    <template v-else>
                        <section v-for="group in shownGroups" :key="group.key" class="space-y-3" :aria-labelledby="'parts-' + group.key">
                            <div>
                                <h2 :id="'parts-' + group.key" class="text-xs font-bold uppercase tracking-wider" :class="group.tone">{{ t(group.title) }}</h2>
                                <p v-if="group.hint" class="text-xs text-muted">{{ t(group.hint) }}</p>
                            </div>
                            <ul class="space-y-3">
                                <li v-for="part in group.parts" :key="part.id" class="card space-y-3 p-5" :class="group.key === 'needs' && part.status === 'retake' ? 'border-warn/60' : ''">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h3 class="font-bold text-cream" translate="no">{{ part.template.title }}</h3>
                                            <p class="text-xs text-muted">{{ partName(part) }} · {{ t('beta.parts.detailsKey', { key: keyLabel(part.template.key) }) }}</p>
                                        </div>
                                        <StatusChip :tone="chipOf(part).tone" :label="t(chipOf(part).label)" />
                                    </div>
                                    <!-- Hillel's note, as a note: who, when, and what he said. -->
                                    <div v-if="part.status === 'retake' && part.latestSubmission?.adminNote" class="flex gap-3 rounded-2xl bg-raised p-3">
                                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-panel text-xs font-bold text-cream" aria-hidden="true">H</span>
                                        <div class="min-w-0">
                                            <p class="text-xs text-muted">{{ part.latestSubmission.adminNoteAt ? t('beta.parts.noteFrom', { when: timeAgo(Date.parse(part.latestSubmission.adminNoteAt)) }) : t('beta.parts.hillelSays') }}</p>
                                            <p class="text-sm text-cream" translate="no">{{ part.latestSubmission.adminNote }}</p>
                                        </div>
                                    </div>
                                    <p v-if="part.status === 'rejected' && part.latestSubmission?.adminNote" class="text-sm text-muted" translate="no">{{ part.latestSubmission.adminNote }}</p>
                                    <p v-if="part.status === 'to_record' && part.recordable" class="text-sm text-muted">{{ t('beta.parts.readyToRecord') }}</p>
                                    <!-- How to sing it (the part's style, docs/vibrato-plan.md), while it's still to sing -->
                                    <p v-if="STYLE_WORDS[part.style] && ['to_record', 'retake'].includes(part.status)" class="text-sm text-cream">{{ t(STYLE_WORDS[part.style]) }}</p>
                                    <p v-if="!part.recordable || !part.template.reviewed" class="text-sm text-muted">{{ t('beta.parts.notOpen') }}</p>
                                    <p v-if="['submitted', 'in_review'].includes(part.status) && !processing(part)" class="text-sm text-muted">{{ t('beta.parts.waiting') }}</p>
                                    <p v-if="part.status === 'guide'" class="text-sm text-muted">{{ t('beta.parts.guideLine', { part: partName(part) }) }}</p>
                                    <p v-if="part.status === 'accepted'" class="text-sm text-muted">{{ t('beta.parts.alternateLine') }}</p>
                                    <p v-if="part.status === 'guide' && me.credit.mode === 'name'" class="text-xs text-muted">{{ t('beta.parts.learnersSee', { part: partName(part), name: me.credit.name }) }}</p>
                                    <p v-if="processing(part)" class="text-sm text-muted">{{ t('beta.parts.processing') }}</p>
                                    <Notice v-if="part.latestSubmission?.status === 'failed'" tone="warn">{{ t('beta.parts.failed') }}</Notice>
                                    <div class="flex flex-wrap gap-2">
                                        <button v-if="canRecordPart(part) && part.status !== 'accepted'" type="button" class="btn-sm" :class="group.key === 'needs' ? 'btn-primary' : 'btn-secondary'" @click="record(part)">{{ part.latestSubmission ? t('beta.parts.recordAgain') : t('beta.parts.learnAndRecord') }}</button>
                                        <button v-if="part.status === 'retake' && canRecordPart(part) && phraseOf[part.id]" type="button" class="btn-secondary btn-sm" @click="record(part, { loop: true })">{{ t('beta.parts.practisePhrase', { number: phraseOf[part.id] }) }}</button>
                                        <button v-if="part.status === 'retake' && part.latestSubmission?.url" type="button" class="btn-secondary btn-sm" :aria-pressed="playing?.url === part.latestSubmission.url" @click="play({ url: part.latestSubmission.url })"><Icon :name="playing?.url === part.latestSubmission.url ? 'stop' : 'play'" :size="14" /> {{ playing?.url === part.latestSubmission.url ? t('studio.stop') : t('beta.parts.hearLastTake') }}</button>
                                        <button v-if="ownGuide(part)" type="button" class="btn-secondary btn-sm" :aria-pressed="playing?.url === ownGuide(part).url" @click="play(ownGuide(part))"><Icon :name="playing?.url === ownGuide(part).url ? 'stop' : 'play'" :size="14" /> {{ playing?.url === ownGuide(part).url ? t('studio.stop') : t('beta.parts.hearYourVoice') }}</button>
                                        <button v-if="part.status === 'accepted' && canRecordPart(part)" type="button" class="btn-secondary btn-sm" @click="record(part)">{{ t('beta.parts.tryForGuide') }}</button>
                                    </div>
                                </li>
                            </ul>
                        </section>
                    </template>
                    <!-- Course clips (docs/plus-courses-build-plan.md, section 8): short lines for the Plus courses' listening games, recorded right in their card. -->
                    <section v-if="clips.length" class="space-y-3" aria-labelledby="clips-heading">
                        <div>
                            <h2 id="clips-heading" class="text-xs font-bold uppercase tracking-wider text-cream">{{ t('beta.clips.title') }}</h2>
                            <p class="text-xs text-muted">{{ t('beta.clips.hint') }}</p>
                        </div>
                        <ul class="space-y-3">
                            <BetaClip v-for="clip in clips" :key="clip.id" :item="clip" :can-record="canRecord" @queued="sendNow" />
                        </ul>
                    </section>
                    <section class="space-y-3" aria-labelledby="credit-heading">
                        <h2 id="credit-heading" class="text-xs font-bold uppercase tracking-wider text-muted">{{ t('beta.parts.creditTitle') }}</h2>
                        <div class="card flex items-center gap-3 p-4">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-cream" :translate="me.credit.mode === 'name' ? 'no' : null">{{ me.credit.mode === 'anonymous' ? t('beta.parts.anonymous') : me.credit.name }}</p>
                                <p class="text-xs text-muted">{{ me.credit.mode === 'anonymous' ? t('beta.parts.creditShownAnonymous') : t('beta.parts.creditShown', { part: creditExample, name: me.credit.name }) }}</p>
                                <p v-if="me.credit.mode === 'name' && me.creditLinksProfile && me.hasPublicProfile && me.profilesOpen" class="text-xs text-muted">{{ t('beta.parts.creditLinked') }}</p>
                            </div>
                            <button type="button" class="btn-secondary btn-sm" @click="editingCredit = true; credit = { mode: me.credit.mode ?? 'name', links: !!me.creditLinksProfile }; consentErrors = null">{{ t('beta.parts.editCredit') }}</button>
                        </div>
                    </section>
                </section>
            </div>

            <TellHillel v-if="telling" @close="telling = false" />
            <Modal v-if="editingCredit" :title="t('beta.parts.creditModal')" @close="editingCredit = false">
                <form class="space-y-4" @submit.prevent="saveCredit">
                    <fieldset class="space-y-3">
                        <legend class="label">{{ t('beta.consent.creditAs') }}</legend>
                        <label class="check-row"><input v-model="credit.mode" type="radio" value="name" class="radio" /> <span>{{ t('beta.consent.byProfileName') }} <span class="font-semibold" translate="no">{{ account.me?.name }}</span></span></label>
                        <label class="check-row"><input v-model="credit.mode" type="radio" value="anonymous" class="radio" /> {{ t('beta.consent.anonymous') }}</label>
                        <p class="help">{{ t('beta.credit.fromProfile') }}</p>
                    </fieldset>
                    <!-- Link the credit to their public profile (contract: beta.credit.update's creditLinksProfile): only a credit by name; it can't be turned on until profiles are open and theirs is public. -->
                    <div v-if="credit.mode === 'name'" class="rounded-2xl border border-line py-2 pe-2 ps-4" data-credit-link>
                        <Switch :on="credit.links && canLink" :label="t('beta.credit.link')" :hint="linkHint" :disabled="!canLink" @change="(on) => (credit.links = on)" />
                    </div>
                    <p v-if="consentErrors" class="text-sm text-bad" role="alert">{{ Object.values(consentErrors).flat().join(' ') }}</p>
                    <div class="flex justify-end gap-3"><button type="button" class="btn-secondary" @click="editingCredit = false">{{ t('common.cancel') }}</button><button type="submit" class="btn-primary" :disabled="busy">{{ t('common.save') }}</button></div>
                </form>
            </Modal>
        </div>`,
    watch: {
        // A new step: the page starts at its top, with its heading in focus for screen readers.
        step() {
            this.$nextTick(() => {
                window.scrollTo(0, 0);
                const heading = this.$el.querySelector?.('main h1');
                if (heading) {
                    heading.setAttribute('tabindex', '-1');
                    heading.focus({ preventScroll: true });
                }
            });
        },
    },
    computed: {
        /** My parts sorted by what needs you (91.png): needs you, coming up, live in the library, then not used. */
        groups() {
            const parts = this.parts ?? [];
            const coming = (p) => !p.recordable || !p.template.reviewed || ['submitted', 'in_review'].includes(p.status);
            const library = (p) => ['guide', 'accepted'].includes(p.status);
            return {
                retakes: parts.filter((p) => p.status === 'retake' && !coming(p)).length,
                needs: parts.filter((p) => !coming(p) && !library(p) && p.status !== 'rejected'),
                coming: parts.filter(coming),
                library: parts.filter((p) => library(p) && !coming(p)),
                notUsed: parts.filter((p) => p.status === 'rejected' && !coming(p)),
            };
        },
        shownGroups() {
            const g = this.groups;
            return [
                { key: 'needs', title: tk('beta.parts.needsYou'), hint: tk('beta.parts.needsYouHint'), tone: 'text-warn', parts: g.needs },
                { key: 'coming', title: tk('beta.parts.comingUp'), hint: tk('beta.parts.comingUpHint'), tone: 'text-muted', parts: g.coming },
                { key: 'library', title: tk('beta.parts.library'), hint: tk('beta.parts.libraryHint'), tone: 'text-good', parts: g.library },
                { key: 'notUsed', title: tk('beta.parts.notUsed'), hint: null, tone: 'text-muted', parts: g.notUsed },
            ].filter((group) => group.parts.length);
        },
        /** Whether their credit can link to their profile: profiles are open and theirs is public. */
        canLink() {
            return !!this.me?.profilesOpen && !!this.me?.hasPublicProfile;
        },
        /** Why the link can't be turned on yet, or what it does. */
        linkHint() {
            if (!this.me?.profilesOpen) return t('beta.credit.linkClosed');
            if (!this.me?.hasPublicProfile) return t('beta.credit.linkNoProfile');
            return t('beta.credit.linkHint');
        },
        /** The credit's example: a part they sing, as learners see it. */
        creditExample() {
            const part = (this.parts ?? []).find((p) => p.status === 'guide') ?? this.parts?.[0];
            return part ? this.partName(part) : templatePartName({ key: 'alto', name: 'alto' });
        },
        sampleUrl() {
            return catalogue.list.find((song) => song.previewUrl)?.previewUrl ?? null;
        },
    },
    created() {
        loadCatalogue();
        // The invite's token leaves the address bar at once (it shouldn't linger); it's kept in the
        // tab until it's accepted, through a sign-up or sign-in.
        if (this.token) {
            tabStorage.set('stackapella.invite', this.token);
            history.replaceState({}, '', '/beta');
        }
        this.start();
    },
    mounted() {
        this.onOnline = () => this.sendNow();
        window.addEventListener('online', this.onOnline);
    },
    unmounted() {
        this.playing?.audio.pause();
        window.removeEventListener('online', this.onOnline);
    },
    methods: {
        partName(part) {
            return templatePartName({ key: part.partKey, name: part.partKey });
        },
        /** Which chip a card wears: a part not open yet says so, whatever else it is. */
        chipOf(part) {
            if (!part.recordable || !part.template.reviewed) return { label: tk('beta.status.notOpen'), tone: 'muted' };
            return STATUS[part.status] ?? { label: tk('beta.status.to_record'), tone: 'muted' };
        },
        canRecordPart(part) {
            return part.recordable && part.template.reviewed && this.canRecord && part.status !== 'guide' && !['submitted', 'in_review'].includes(part.status);
        },
        processing(part) {
            return ['processing', 'uploading'].includes(part.latestSubmission?.status);
        },
        /** For each retake with a note pinned to a beat, the phrase that beat is in (Practise phrase N). */
        async findPhrases() {
            const found = {};
            for (const part of (this.parts ?? []).filter((p) => p.status === 'retake' && Number.isFinite(p.latestSubmission?.adminNoteBeat))) {
                try {
                    const template = await loadTemplate(part.template.id);
                    const index = phrases(template).findIndex((ph) => part.latestSubmission.adminNoteBeat >= ph.startBeat && part.latestSubmission.adminNoteBeat < ph.endBeat);
                    if (index >= 0) found[part.id] = index + 1;
                } catch (error) {
                    console.warn('The phrase for a note was not found', part.template.id, error);
                }
            }
            this.phraseOf = found;
        },
        /**
         * Who's here: with an invite, its page (sign up or in, then it's accepted); signed in,
         * the singer's welcome, consent or My parts; signed out, a way to sign in.
         */
        async start() {
            this.step = 'loading';
            this.acceptError = null;
            await loadAccount({ fresh: true });
            const token = tabStorage.get('stackapella.invite');
            if (token) {
                try {
                    this.invite = (await api.beta.invite.show(token)).data;
                    keepBar(this.invite);
                } catch (error) {
                    if (!(error instanceof ApiError && error.code === 'invite_not_found')) {
                        this.handle(error, { retry: true });
                        return;
                    }
                    this.invite = { name: '', status: 'not_found' };
                }
                const status = this.invite.status;
                // An open invite is used only when the person says which account joins.
                if (status === 'open') {
                    this.step = 'invite';
                    return;
                }
                if (status !== 'open') this.forgetInvite();
                if (status !== 'accepted') {
                    this.ended = `invite_${status}`;
                    this.step = 'ended';
                    return;
                }
            }
            if (!account.me) {
                this.step = 'signin';
                return;
            }
            try {
                this.me = (await api.beta.me.show()).data;
                keepBar(this.me);
            } catch (error) {
                this.handle(error, { retry: true });
                return;
            }
            await this.arrive();
        },
        /** Attaches the invite to this account; false (with the reason shown) if it can't. */
        async accept(token) {
            try {
                this.me = (await api.beta.invite.accept(token)).data;
                keepBar(this.me);
                this.forgetInvite();
                await loadAccount({ fresh: true });
                return true;
            } catch (error) {
                const code = error instanceof ApiError ? error.code : null;
                if (code === 'invite_expired' || code === 'invite_revoked' || code === 'invite_not_found') {
                    this.forgetInvite();
                    this.ended = code;
                    this.step = 'ended';
                    return false;
                }
                if (code === 'already_singer') {
                    this.forgetInvite();
                    this.me = (await api.beta.me.show()).data; // this account sings with us already
                    keepBar(this.me);
                    return true;
                }
                if (code === 'invite_used') {
                    this.forgetInvite();
                    this.acceptError = t('beta.invite.used');
                    this.step = 'notSinger';
                    return false;
                }
                if (code === 'email_unverified') {
                    this.acceptError = t('beta.invite.unverified');
                    this.unverified = true;
                    this.step = 'invite';
                    return false;
                }
                this.handle(error, { retry: true });
                return false;
            }
        },
        /** "Join as Ada": this account takes the invite. */
        async join() {
            const token = tabStorage.get('stackapella.invite');
            if (!token) return this.start();
            this.busy = true;
            this.acceptError = null;
            try {
                if (await this.accept(token)) await this.arrive();
            } finally {
                this.busy = false;
            }
        },
        /** Signs out, then signs in (or up) with the account the invite is for. */
        async otherAccount() {
            await signOut().catch((error) => console.warn('Signing out failed', error));
            this.signIn('signin');
        },
        async resendVerification() {
            this.busy = true;
            try {
                await api.auth.verification.resend({ email: account.me.email });
                toast(t('auth.sent.again'));
            } catch (error) {
                this.handle(error);
            } finally {
                this.busy = false;
            }
        },
        forgetInvite() {
            tabStorage.set('stackapella.invite', '');
        },
        /** To the sign-in screen, the invite carried through it, and back here afterwards. */
        signIn(mode) {
            // The invite stays in the tab (the sign-in screen reads it there), never in the address.
            const q = new URLSearchParams({ next: '/beta', ...(mode === 'signup' ? { mode } : {}) });
            navigate(`/signin?${q}`);
        },
        /**
         * Every refusal, in one place: signed out (401) asks to sign in; an account that isn't a
         * singer (or no longer is) says so; a changed agreement goes back to consent; anything else
         * is a message, with Try again when nothing's on screen yet. Returns true when it changed
         * the step.
         */
        handle(error, { retry = false } = {}) {
            if (error instanceof ApiError && (error.status === 401 || error.code === 'unauthenticated')) {
                account.me = null;
                this.step = 'signin';
                return true;
            }
            if (error instanceof ApiError && (error.code === 'not_beta_singer' || error.code === 'singer_revoked')) {
                this.ended = error.code;
                this.step = 'notSinger';
                return true;
            }
            if (error instanceof ApiError && error.code === 'consent_required') {
                this.me.consent.version = null;
                this.toConsent();
                return true;
            }
            console.error('Beta request failed', error);
            const message = error?.code === 'offline' ? t('common.offline') : t('beta.serverError');
            if (retry) {
                this.retryMessage = message;
                this.step = 'retry';
                return true;
            }
            toast(message, { tone: 'error' });
            return false;
        },
        /** Takes kept on this device go now (if the connection allows), then My parts reloads. */
        async sendNow() {
            await sendAll();
            if (this.me) await this.loadParts();
        },
        titleOf(take) {
            const part = this.parts?.find((p) => p.id === take.assignmentId);
            if (part) return `${part.template.title}: ${this.partName(part)}`;
            const clip = this.clips.find((c) => c.id === take.assignmentId);
            if (clip) return t('beta.clips.outboxTitle', { course: clip.clip.courseTitle });
            return take.partKey ? templatePartName({ key: take.partKey, name: take.partKey }) : t('beta.clips.title');
        },
        /** Signed in: the welcome and consent the first time, then My parts. */
        async arrive() {
            await loadOutbox();
            if (!(await this.loadParts())) return;
            if (outbox.takes.some((take) => take.state !== 'refused')) this.sendNow();
            if (this.me.consent.version !== this.me.consent.current) {
                this.credit = { mode: this.me.credit.mode ?? 'name' };
                if (this.me.consent.version === null) this.step = 'welcome';
                else await this.toConsent();
            } else {
                this.step = 'parts';
            }
        },
        /** My parts; false if the page moved on (the link ended, or nothing could load). */
        async loadParts() {
            try {
                // Course clips (kind "clip") have no song; they're kept apart from the parts, which all have one.
                const items = (await api.beta.parts.list()).data;
                this.parts = items.filter((item) => item.kind !== 'clip');
                this.clips = items.filter((item) => item.kind === 'clip');
                this.findPhrases();
                return true;
            } catch (error) {
                this.handle(error, { retry: true });
                return false;
            }
        },
        /** The agreement, then the consent step; on a failure the page stays where it was. */
        async toConsent() {
            try {
                this.agreement = (await api.beta.agreement.show({ lang: locale.lang })).data;
                this.step = 'consent';
            } catch (error) {
                this.handle(error);
            }
        },
        async consent() {
            this.busy = true;
            this.consentErrors = null;
            this.consentGeneral = null;
            try {
                this.me = (await api.beta.consent.create({ version: this.agreement.version, adult: this.adult, creditMode: this.credit.mode })).data;
                keepBar(this.me);
                this.step = 'parts';
            } catch (error) {
                if (error instanceof ApiError && error.code === 'agreement_changed') {
                    await this.toConsent();
                    toast(t('beta.consent.changed'));
                } else if (error instanceof ApiError && error.code === 'validation_failed') {
                    this.consentErrors = error.errors ?? {};
                    // Messages about anything but the age show under the button.
                    this.consentGeneral = Object.entries(this.consentErrors).filter(([field]) => field !== 'adult').flatMap(([, messages]) => messages).join(' ') || null;
                } else {
                    this.handle(error);
                }
            } finally {
                this.busy = false;
            }
        },
        async saveCredit() {
            this.busy = true;
            try {
                this.me = (await api.beta.credit.update({ creditMode: this.credit.mode, creditLinksProfile: !!this.credit.links })).data;
                keepBar(this.me);
                this.editingCredit = false;
                toast(t('beta.parts.creditSaved'));
            } catch (error) {
                if (error instanceof ApiError && error.code === 'validation_failed') {
                    this.consentErrors = error.errors ?? {};
                } else {
                    this.editingCredit = false;
                    this.handle(error);
                }
            } finally {
                this.busy = false;
            }
        },
        /**
         * The singer's own voice for this part, to hear as learners do: the guide marked theirs, or,
         * when they're its default voice ("Now the guide"), the part's guide.
         */
        ownGuide(part) {
            const theirs = part.guides.find((g) => g.mine && g.partKey === part.partKey);
            if (theirs) return theirs;
            const marked = part.guides.some((g) => 'mine' in g);
            return !marked && part.status === 'guide' ? part.guides.find((g) => g.partKey === part.partKey) ?? null : null;
        },
        /** Plays (or stops) a guide: one at a time. Listening only, so a plain audio element. */
        play(guide) {
            const same = this.playing?.url === guide.url;
            this.playing?.audio.pause();
            this.playing = null;
            if (same) return;
            const sound = new Audio(guide.url);
            sound.onended = () => (this.playing = null);
            sound.play().catch((error) => {
                console.warn('Playback', error);
                this.playing = null;
            });
            this.playing = { url: guide.url, audio: sound };
        },
        async record(part, { loop = false } = {}) {
            try {
                await startBetaRecording(part, { loop });
            } catch (error) {
                console.error('The part could not open', error);
                toast(t(error instanceof TemplateChanged ? tk('beta.parts.templateChanged') : tk('beta.parts.openFailed')), { tone: 'error', seconds: 12 });
            }
        },
    },
};
