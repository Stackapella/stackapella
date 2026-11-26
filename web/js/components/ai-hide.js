// Hide AI features (Hillel, 2026-11-26: "I'd like instead most new users to see it but have the option to hide forever";
// then: "When we first show a user the AI they can dismiss it. After that the option should only be in profile settings."):
// only on AI's first showing to someone (the first entry point they see), kept for that showing, then never again here:
// one quiet dismiss, "Not for me", styled as the install card's Not now beside the entry point's own button (Hillel:
// "make 'Hide AI' look like 'Not now' and change to 'Not for me'"), on each AI entry point an undecided singer sees, never a modal. It turns AI off for good on this account
// (or device, for a guest), and says once that Settings can bring it back. Shown only while undecided (ai-choice.js
// aiHideOffered); `where` names the entry point for its count.

import { t } from '../i18n.js';
import { aiHideOffered, hideAi, noteFirstShown } from '../ai-choice.js';
import { account } from '../stores/account.js';
import { toast } from './ui.js';

export const AiHide = {
    props: { where: { type: String, required: true } },
    data: () => ({ busy: false, thisShowing: false }),
    template: `
        <button v-if="shown" type="button" class="tap-44 btn-ghost btn-sm" :disabled="busy" :data-ai-hide="where" @click="hide">{{ t('settings.ai.hideButton') }}</button>`,
    computed: {
        shown() {
            return this.thisShowing;
        },
    },
    // The first showing: kept on this entry point while it's on screen, and recorded as seen, so no other offers it again.
    // Decided once the account is known, so a signed-in singer's first showing is theirs, not the device's guest one.
    mounted() {
        if (account.loaded) this.firstShowing();
        else this.$watch(() => account.loaded, (loaded) => loaded && this.firstShowing());
    },
    methods: {
        firstShowing() {
            if (this.thisShowing || !aiHideOffered()) return;
            this.thisShowing = true;
            noteFirstShown();
        },
        async hide() {
            this.busy = true;
            try {
                await hideAi(this.where);
                toast(t('settings.ai.hidden'));
            } catch (error) {
                console.warn('AI features were not hidden', error);
                toast(t('settings.failed'), { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },
    },
};
