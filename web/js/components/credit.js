// The licence's credit (LICENSE, the Attribution Assurance License, clause 2): a self-hosted install
// shows the author's attribution prominently each time it's opened: Hillel Coren, Stackapella and
// https://stackapella.com. Never on stackapella.com itself (Hillel), never a setting to hide it, the
// same in both skins.
// - CreditLine: a readable line at the foot of Home and the sign-in screen.
// - LaunchCredit: a banner at the top of whichever screen the app opens on (Home, a lesson or song
//   link), until the first move elsewhere, so it's in view on every launch.
// Settings keeps its own line too (settings.js, data-credit): the About credit.

import { selfHosted } from '../stores/account.js';
import { route } from '../router.js';

const SITE = 'stackapella.com'; // i18n-ignore: an address
const ADDRESS = 'https://stackapella.com'; // i18n-ignore: an address

export const CreditLine = {
    setup: () => ({ selfHosted, SITE, ADDRESS }),
    template: `
        <p v-if="selfHosted()" class="mx-auto max-w-2xl px-4 py-6 text-center text-sm text-cream" data-credit-line>
            <span translate="no">{{ t('settings.credit') }}</span> · <a :href="ADDRESS" class="link" rel="noopener" translate="no">{{ SITE }}</a>
        </p>`,
};

export const LaunchCredit = {
    data: () => ({ launched: true }),
    setup: () => ({ selfHosted, route, SITE, ADDRESS }),
    watch: {
        // Moving to another screen ends the launch; the credit stays on Home, sign-in and Settings.
        'route.name'() {
            this.launched = false;
        },
        'route.params': {
            deep: true,
            handler() {
                this.launched = false;
            },
        },
    },
    template: `
        <div v-if="selfHosted() && launched" class="border-b border-line bg-panel px-4 py-2 text-center text-sm text-cream" role="note" data-launch-credit>
            <span translate="no">{{ t('settings.credit') }}</span> · <a :href="ADDRESS" class="link" rel="noopener" translate="no">{{ SITE }}</a>
        </div>`,
};
