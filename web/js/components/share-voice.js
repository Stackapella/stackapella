// Share your take as the part's guide voice (Be the voice, js/be-the-voice.js; Hillel, 2026-11-12: "they should only need
// legal stuff if they then later decide to share it ... always be pleasant about it, not annoying"; the engagement review,
// shots-review/review-2026-11-12/be-the-voice-engagement-review.md). One gentle step, never a legal wall: three plain
// points, the full agreement one tap away, "I'm 18 or over", how you're credited with its preview, then Share it or Keep
// it private for now (the take is already saved to the account, private, either way). Afterwards: sent, with honest
// timing, or held until the email is confirmed; how it goes shows in Lend your voice (/voices). Signed in only: a guest
// signs in first (Sing). Loaded when Share it is tapped (the agreement's view comes with it).

import { api } from '../api.js';
import { t } from '../i18n.js';
import { navigate } from '../router.js';
import { account } from '../stores/account.js';
import { countVoice, under18 } from '../be-the-voice.js';
import { Agreement } from '../views/beta.js';
import { Modal } from './ui.js';

// A refusal, said kindly, and counted by where the run stopped (bv_stop:<reason>).
const STOPS = { adult_required: 'age', volunteer_blocked: 'check', rate_limited: 'upload', not_found: 'upload', beta_singer: 'beta' };

export const ShareVoice = {
    components: { Modal, Agreement },
    // `part`: the part's name; `song`: the song's; `saved`: the take is on the account; `send`: ({ credit, agreementVersion, adult }) => Promise.
    props: { part: { type: String, required: true }, song: { type: String, default: '' }, partKey: { type: String, default: '' }, saved: Boolean, send: { type: Function, required: true } },
    emits: ['close'],
    data: () => ({ state: 'ask', agreement: null, full: false, credit: 'name', adult: false, held: false }),
    setup: () => ({ account, navigate }),
    computed: {
        /** The credit as the part will show it: "Guide voice: Sarah K." or anonymous. */
        preview() {
            return this.credit === 'anonymous' ? t('voices.be.previewAnonymous') : t('voices.be.preview', { name: account.me?.name ?? '' });
        },
    },
    template: `
        <Modal :title="state === 'sent' ? (held ? t('voices.be.heldTitle') : t('voices.be.sent')) : state === 'young' ? t('voices.be.youngTitle') : t('voices.be.sheetTitle', { part })" @close="$emit('close')">
            <div v-if="state === 'sent'" class="space-y-4" role="status" data-share-voice-sent>
                <p class="text-cream">{{ held ? t('voices.be.heldLine') : t('voices.be.sentLine') }}</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-primary" @click="$emit('close')">{{ t('common.done') }}</button>
                    <button type="button" class="btn-secondary" @click="navigate('/voices')">{{ t('voices.be.sentLink') }}</button>
                </div>
            </div>
            <div v-else-if="state === 'young'" class="space-y-4" role="status" data-share-voice-young>
                <p class="text-cream">{{ t('voices.be.young') }}</p>
                <button type="button" class="btn-primary" @click="$emit('close')">{{ t('common.done') }}</button>
            </div>
            <div v-else class="space-y-4" data-share-voice>
                <p class="text-cream" translate="no">{{ t('voices.be.sheetIntro', { part }) }}</p>
                <ul class="list-disc space-y-1 ps-5 text-sm text-muted">
                    <li>{{ t('voices.be.pointTeam') }}</li>
                    <li>{{ t('voices.be.pointUnpaid') }}</li>
                    <li>{{ t('voices.point.yours') }}</li>
                </ul>
                <button type="button" class="link tap-44 text-sm" :aria-expanded="full ? 'true' : 'false'" data-share-voice-full @click="full = !full">{{ t('voices.be.full') }}</button>
                <div v-if="full && agreement" class="card max-h-[40dvh] overflow-y-auto p-4" tabindex="0" role="region" :aria-label="t('voices.agreement')"><Agreement :agreement="agreement" /></div>
                <label class="check-row"><input v-model="adult" type="checkbox" class="checkbox" data-share-voice-adult /> {{ t('voices.be.adult') }}</label>
                <fieldset class="space-y-1">
                    <legend class="label">{{ t('voices.credit') }}</legend>
                    <label class="check-row"><input v-model="credit" type="radio" class="radio" value="name" /> {{ t('voices.creditName', { name: account.me?.name ?? '' }) }}</label>
                    <label class="check-row"><input v-model="credit" type="radio" class="radio" value="anonymous" /> {{ t('voices.creditAnonymous') }}</label>
                    <p class="text-sm text-muted" translate="no" data-share-voice-preview>{{ preview }}</p>
                </fieldset>
                <p v-if="!saved" class="text-sm text-muted" role="status" data-share-voice-saving>{{ t('voices.be.saving') }}</p>
                <p v-if="state === 'failed'" class="text-sm text-warn" role="alert" data-share-voice-failed>{{ t('voices.be.failed') }}</p>
                <p v-else-if="state === 'beta'" class="text-sm text-muted" role="status" data-share-voice-beta>{{ t('voices.be.betaSinger') }} <a href="/beta" class="link" @click.prevent="navigate('/beta')">{{ t('voices.be.myParts') }}</a></p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-primary" :disabled="state === 'sending' || !agreement || !adult || !saved" data-share-voice-send @click="share">{{ state === 'sending' ? t('voices.be.sending') : t('voices.be.share') }}</button>
                    <button type="button" class="btn-ghost" data-share-voice-private @click="$emit('close')">{{ t('voices.be.private') }}</button>
                </div>
                <button type="button" class="link-quiet tap-44 text-sm" data-share-voice-under18 @click="young">{{ t('voices.be.under18') }}</button>
            </div>
        </Modal>`,
    async created() {
        this.agreement = (await api.beta.agreement.show({ for: 'volunteer' }).catch((error) => (console.warn('The agreement did not load', error), null)))?.data ?? null;
    },
    methods: {
        async share() {
            this.state = 'sending';
            try {
                const result = await this.send({ credit: this.credit, agreementVersion: this.agreement.version, adult: this.adult });
                this.held = result?.data?.status === 'held'; // an email still to confirm: sent once it is
                if (this.held) countVoice('bv_stop:email');
                this.state = 'sent';
            } catch (error) {
                if (error?.code === 'already_sent') {
                    this.state = 'sent'; // offered already: nothing to do again
                    return;
                }
                if (error?.code === 'agreement_changed') {
                    // The terms changed meanwhile: shown again, and Share it sends the new version.
                    this.agreement = (await api.beta.agreement.show({ for: 'volunteer' }).catch(() => null))?.data ?? this.agreement;
                    this.full = true;
                    this.state = 'ask';
                    return;
                }
                if (error?.code === 'beta_singer') {
                    // An invited beta singer records their own parts in My parts, never this way.
                    countVoice('bv_stop:beta');
                    this.state = 'beta';
                    return;
                }
                console.warn('The take was not shared', error);
                countVoice(`bv_stop:${STOPS[error?.code] ?? 'upload'}`);
                this.state = 'failed';
            }
        },
        /** Under 18: thanked, the take kept as theirs, and the call stops on this device. */
        young() {
            under18();
            this.state = 'young';
        },
    },
};
