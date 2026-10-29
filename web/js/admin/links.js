// Hillel's links into the beta, on one page (as he asked): the shared invite link (anyone he hands it
// to can make an account, untracked) and the preview link (a look around with no account), each
// with Copy, Pause and Replace. mudeo's early-access link stays on the mudeo email page.

import { BetaLinkCard } from './mudeo.js';

export const LinksSection = {
    components: { BetaLinkCard },
    props: { me: { type: Object, required: true } },
    template: `
        <div class="space-y-6">
            <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.links') }}</h1>
            <BetaLinkCard kind="invite" :me="me" />
            <BetaLinkCard kind="preview" :me="me" />
        </div>`,
};
