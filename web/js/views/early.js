// The links into the beta before launch, each a page that welcomes and lets you straight in as a
// guest (Hillel: "try the app without creating an account, like normal users will"): Start singing
// opens the app with the beta pass the link gave, no account; making one is the second choice, and
// works even while sign-ups are paused (its code goes with the sign-up, as `early`). Paused, full
// or closed (or a code that was replaced), it says kindly that the link has ended. Never linked from
// anywhere public; the server sends them noindex.
// - /early/<code>: mudeo's early-access link (docs/mudeo-transition-plan.md; earlyAccess.show).
// - /invite/<code>: Hillel's shared invite link, untracked (inviteLink.show).

import { api } from '../api.js';
import { navigate } from '../router.js';
import { account } from '../stores/account.js';
import { tk } from '../i18n.js';
import { InviteAccept, InviteCard } from '../components/invite-card.js';

const WORDS = {
    early: { lead: tk('early.lead'), closedTitle: tk('early.closedTitle'), closedHint: tk('early.closedHint') },
    invite: { lead: tk('invite.lead'), closedTitle: tk('invite.closedTitle'), closedHint: tk('invite.closedHint') },
};

export const EarlyView = {
    components: { InviteAccept, InviteCard },
    props: { code: { type: String, required: true }, kind: { type: String, default: 'early' } },
    data: () => ({ status: null, failed: false, makingAccount: false }),
    setup: () => ({ account, navigate, WORDS }),
    template: `
        <InviteCard :from="t('invite.founder')" :kind="t('invite.earlyAccess')" :lead="t(WORDS[kind].lead)" sign-in="/signin">
            <template v-if="failed || !status || status !== 'open'" #top>
                <p v-if="failed" class="text-lg text-cream" role="alert">{{ t('early.failed') }}</p>
                <p v-else-if="!status" class="text-muted" role="status">{{ t('create.loading') }}</p>
                <template v-else>
                    <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t(WORDS[kind].closedTitle) }}</h1>
                    <p class="text-muted">{{ t(WORDS[kind].closedHint) }}</p>
                    <button type="button" class="btn-secondary" @click="navigate('/')">{{ t('early.look') }}</button>
                </template>
            </template>
            <template v-if="status === 'open'">
                <button v-if="account.me" type="button" class="btn-primary btn-lg w-full" @click="navigate('/')">{{ t('early.in') }}</button>
                <div v-else-if="account.loaded" class="space-y-5">
                    <button type="button" class="btn-primary btn-lg w-full" data-guest @click="navigate('/')">{{ t('early.start') }}</button>
                    <div class="space-y-3 border-t border-line pt-5">
                        <p class="text-sm text-muted">{{ t('early.accountWhy') }}</p>
                        <InviteAccept v-if="makingAccount" next="/" :early="code" />
                        <button v-else type="button" class="btn-secondary w-full" @click="makingAccount = true">{{ t('early.makeAccount') }}</button>
                    </div>
                </div>
            </template>
        </InviteCard>`,
    async created() {
        try {
            this.status = (await (this.kind === 'invite' ? api.inviteLink.show(this.code) : api.earlyAccess.show(this.code))).data.status;
        } catch (error) {
            console.warn('The early-access link did not open', error);
            this.failed = true;
        }
    },
};
