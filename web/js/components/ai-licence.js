// The AI voice licence, agreed in the app (docs/ai-voices-build-plan.md, phase 1b; Hillel: "just like singer agreed to
// intiial terms in the app please also build in the terms for AI licensing for voice"; contract: beta.aiLicence.*). Its
// own opt-in, never bundled with the guide agreement, in English only and as simple as it can be (Hillel: "just need
// english for accepting AI terms, keep it as imple as pissble"): offered once where the singer's parts live (My parts), only when
// an admin has offered it (`offered`), never a nag or a banner. "Not now" puts the offer away on this device, leaving
// one quiet line to come back to. The terms screen is the guide agreement's pattern: the plain short version first,
// then the full terms, the credit choice in the label's own words, 18 or over, Accept. Withdraw is a styled
// confirmation that names exactly what goes (design rules: no browser dialogs).

import { api } from '../api.js';
import { formatDate, formatList, t } from '../i18n.js';
import { local } from '../storage.js';
import { templatePartName } from '../templates.js';
import { aiCredit } from '../ai-voices.js';
import { confirmAction } from './confirm.js';
import { Notice } from './form.js';
import { Modal, toast } from './ui.js';
import { Icon } from './icon.js';

const LATER = 'stackapella.aiLicenceLater'; // the version a singer said Not now to, on this device

/** "Silent Night: Alto; Amazing Grace: Tenor and Bass": what the voice sings now, by song. */
export function singsLine(guides) {
    const songs = new Map();
    for (const guide of guides ?? []) {
        if (!songs.has(guide.templateId)) songs.set(guide.templateId, { title: guide.title, parts: [] });
        songs.get(guide.templateId).parts.push(templatePartName({ key: guide.partKey }));
    }
    return [...songs.values()].map((song) => t('beta.ai.songParts', { song: song.title, parts: formatList(song.parts) })).join('; ');
}

/**
 * Where the licence stands: none (offered, not taken), accepted, changed (newer terms to read), or withdrawn. A singer
 * whose beta role ended keeps an accepted licence to change or withdraw "at any time, in the app" (`offered` false
 * then, and nothing new to accept).
 */
export function standing(state) {
    if (state?.accepted && !state.withdrawnAt) return state.offered && state.accepted.version < state.version ? 'changed' : 'accepted';
    if (!state?.offered) return null;
    return state.withdrawnAt ? 'withdrawn' : 'none';
}

const day = (time) => formatDate(time, { day: 'numeric', month: 'short', year: 'numeric' });

/**
 * My parts' AI voice section: the offer (Read the licence, Not now), or the voice they licensed (its label, what it sings,
 * Change credit, Withdraw). Nothing at all while it isn't offered. `name`: the account's name, for the label's preview.
 */
export const AiLicenceCard = {
    components: { Icon, Modal, Notice },
    props: { state: { type: Object, default: null }, name: { type: String, default: '' } },
    emits: ['read', 'changed'],
    data: () => ({ later: local.get(LATER), credit: null, busy: false }),
    setup: () => ({ aiCredit, day, singsLine }),
    computed: {
        standing: (vm) => standing(vm.state),
        /** Put away on this device for these terms (Not now): one quiet line instead of the offer. */
        putAway: (vm) => vm.standing === 'none' && vm.later === String(vm.state.version),
    },
    template: `
        <section v-if="standing" class="space-y-3" aria-labelledby="ai-licence-heading" data-ai-licence :data-standing="standing">
            <h2 id="ai-licence-heading" class="text-xs font-bold uppercase tracking-wider text-muted">{{ t('beta.ai.title') }}</h2>
            <div v-if="putAway" class="card flex flex-wrap items-center gap-3 p-4" data-ai-licence-quiet>
                <p class="min-w-0 flex-1 text-sm text-muted">{{ t('beta.ai.notTaken') }}</p>
                <button type="button" class="btn-ghost btn-sm" data-ai-licence-read @click="$emit('read')">{{ t('beta.ai.read') }}</button>
            </div>
            <div v-else-if="standing === 'none' || standing === 'withdrawn'" class="card space-y-3 p-5" data-ai-licence-offer>
                <p class="font-bold text-cream">{{ standing === 'withdrawn' ? t('beta.ai.withdrawnOn', { date: day(state.withdrawnAt) }) : t('beta.ai.offerTitle') }}</p>
                <p class="text-sm text-muted">{{ t('beta.ai.offerLine') }}</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-secondary" data-ai-licence-read @click="$emit('read')">{{ t('beta.ai.read') }}</button>
                    <button v-if="standing === 'none'" type="button" class="btn-ghost" data-ai-licence-later @click="notNow">{{ t('beta.ai.later') }}</button>
                </div>
            </div>
            <div v-else class="card space-y-3 p-5" data-ai-licence-voice>
                <Notice v-if="standing === 'changed'" tone="info">{{ t('beta.ai.changed') }} <button type="button" class="link" data-ai-licence-read @click="$emit('read')">{{ t('beta.ai.read') }}</button></Notice>
                <p class="flex items-start gap-2 text-cream"><Icon name="sparkle" :size="16" class="mt-1 shrink-0 text-accent-strong" /><span data-ai-licence-label>{{ aiCredit(state.accepted.credited ? name : null) }}</span></p>
                <p class="text-sm text-muted">{{ t('beta.ai.acceptedOn', { version: state.accepted.version, date: day(state.accepted.acceptedAt) }) }}</p>
                <p class="text-sm text-muted" data-ai-licence-sings>{{ state.voice?.guides?.length ? t('beta.ai.sings', { songs: singsLine(state.voice.guides) }) : t('beta.ai.singsNothing') }}</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-secondary btn-sm" data-ai-licence-credit @click="credit = state.accepted.credited">{{ t('beta.ai.changeCredit') }}</button>
                    <button type="button" class="btn-ghost btn-sm text-bad" :disabled="busy" data-ai-licence-withdraw @click="withdraw">{{ t('beta.ai.withdraw') }}</button>
                </div>
            </div>
            <Modal v-if="credit !== null" :title="t('beta.ai.creditTitle')" @close="credit = null">
                <form class="space-y-4" @submit.prevent="saveCredit">
                    <fieldset class="space-y-2">
                        <legend class="label">{{ t('beta.ai.creditLegend') }}</legend>
                        <label class="check-row"><input v-model="credit" type="radio" :value="true" class="radio" data-ai-credit="named" /> <span>{{ aiCredit(name) }}</span></label>
                        <label class="check-row"><input v-model="credit" type="radio" :value="false" class="radio" data-ai-credit="plain" /> <span>{{ aiCredit(null) }}</span></label>
                    </fieldset>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="btn-secondary" @click="credit = null">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary" :disabled="busy">{{ t('beta.ai.saveCredit') }}</button>
                    </div>
                </form>
            </Modal>
        </section>`,
    methods: {
        notNow() {
            local.set(LATER, String(this.state.version));
            this.later = String(this.state.version);
        },
        async saveCredit() {
            this.busy = true;
            try {
                const { data } = await api.beta.aiLicence.update({ credited: this.credit });
                this.credit = null;
                this.$emit('changed', data);
                toast(t('beta.ai.creditSaved'));
            } catch (error) {
                console.warn('The AI credit was not saved', error);
                toast(t('beta.ai.failed'), { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },
        /** Withdraw, after a confirmation that names exactly what goes. */
        async withdraw() {
            const guides = this.state.voice?.guides ?? [];
            const yes = await confirmAction({
                title: t('beta.ai.withdrawTitle'),
                body: [guides.length ? t('beta.ai.withdrawSings', { count: guides.length, songs: singsLine(guides) }) : t('beta.ai.withdrawNothing'), t('beta.ai.withdrawDeleted')].join(' '),
                action: t('beta.ai.withdrawAction'),
                cancel: t('beta.ai.keep'),
            });
            if (!yes) return;
            this.busy = true;
            try {
                const { data, meta } = await api.beta.aiLicence.withdraw();
                this.$emit('changed', data);
                toast(t('beta.ai.withdrawn', { count: meta?.retired ?? 0 }));
            } catch (error) {
                if (error?.code === 'not_accepted') {
                    // Already gone (withdrawn twice, or Admin's withdraw came first): show where it stands now.
                    this.$emit('changed', (await api.beta.aiLicence.show().catch(() => ({ data: this.state }))).data);
                    toast(t('beta.ai.alreadyWithdrawn'));
                } else {
                    console.warn('The AI voice was not withdrawn', error);
                    toast(t('beta.ai.failed'), { tone: 'error' });
                }
            } finally {
                this.busy = false;
            }
        },
    },
};

/**
 * The licence itself: the short version, then the full terms (beta.agreement.show?for=ai_voice), the credit choice in
 * the label's own words, 18 or over, Accept. Emits `done` with the new state, or `back`.
 */
export const AiLicenceTerms = {
    components: { Notice },
    // credited: their earlier choice when renewing, else null: nothing is chosen for them (what looks chosen is kept).
    props: { agreement: { type: Object, required: true }, name: { type: String, default: '' }, credited: { type: Boolean, default: null } },
    emits: ['done', 'back', 'changed-terms'],
    data() {
        return { credit: this.credited, busy: false, error: null };
    },
    setup: () => ({ aiCredit }),
    computed: {
        /** The plain short version: the blocks up to the first heading after it (the guide agreement's pattern). */
        split() {
            const blocks = this.agreement.blocks ?? [];
            const next = blocks.findIndex((b, i) => i > 0 && b.type === 'heading');
            return next > 0 ? { short: blocks.slice(0, next), full: blocks.slice(next) } : { short: [], full: blocks };
        },
    },
    template: `
        <section class="space-y-5" data-ai-licence-terms>
            <h1 class="text-2xl font-bold text-cream" translate="no">{{ agreement.title }}</h1>
            <div v-if="split.short.length" class="card p-5" data-ai-licence-short><slot name="blocks" :blocks="split.short" /></div>
            <div class="card max-h-[50dvh] overflow-y-auto p-5" tabindex="0" role="region" :aria-label="t('beta.ai.fullTerms')" data-ai-licence-full><slot name="blocks" :blocks="split.full" /></div>
            <form class="card space-y-4 p-5" @submit.prevent="accept">
                <fieldset class="space-y-2">
                    <legend class="label">{{ t('beta.ai.creditLegend') }}</legend>
                    <label class="check-row"><input v-model="credit" type="radio" :value="true" class="radio" data-ai-credit="named" /> <span>{{ aiCredit(name) }}</span></label>
                    <label class="check-row"><input v-model="credit" type="radio" :value="false" class="radio" data-ai-credit="plain" /> <span>{{ aiCredit(null) }}</span></label>
                    <p class="help">{{ t('beta.ai.creditHelp') }}</p>
                </fieldset>
                <p v-if="error" class="text-sm text-bad" role="alert">{{ error }}</p>
                <!-- One button (Hillel: "keep it as imple as pissble"): it says the 18 or over it confirms. -->
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="btn-primary" :disabled="busy || credit === null" data-ai-licence-accept>{{ t('beta.ai.accept') }}</button>
                    <button type="button" class="btn-secondary" @click="$emit('back')">{{ t('beta.ai.back') }}</button>
                </div>
            </form>
        </section>`,
    methods: {
        async accept() {
            this.busy = true;
            this.error = null;
            try {
                const { data } = await api.beta.aiLicence.accept({ version: this.agreement.version, adult: true, credited: this.credit });
                toast(t('beta.ai.accepted'));
                this.$emit('done', data);
            } catch (error) {
                // Newer terms since they were shown: show those (the guide agreement's agreement_changed).
                if (error?.code === 'agreement_changed') this.$emit('changed-terms');
                else this.error = error?.code === 'not_offered' ? t('beta.ai.notOffered') : t('beta.ai.failed');
            } finally {
                this.busy = false;
            }
        },
    },
};
