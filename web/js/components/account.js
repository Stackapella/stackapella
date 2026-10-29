// What the app asks of a new account wherever it is: "I'm 13 or over", once, after a first Google
// or Apple sign-in (which can't ask it on the provider's page), and what a sign-in that came back
// from Google or Apple says (?signedIn= or ?authError=, then taken out of the address bar).

import { api } from '../api.js';
import { t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { account, loadAccount, signOut } from '../stores/account.js';
import { Modal, toast } from './ui.js';

const AUTH_ERRORS = {
    cancelled: tk('auth.back.cancelled'),
    expired: tk('auth.back.expired'),
    failed: tk('auth.back.failed'),
    unverified: tk('auth.back.unverified'),
    suspended: tk('auth.error.suspended'),
    blocked: tk('auth.error.blocked'),
    paused: tk('auth.error.paused'),
    linked_elsewhere: tk('auth.back.linkedElsewhere'),
    recent_sign_in_required: tk('auth.back.recentSignIn'),
};

/** Reads ?signedIn= or ?authError= after a Google or Apple sign-in, says so, and tidies the address. */
export async function cameBackFromSignIn() {
    const params = new URLSearchParams(location.search);
    const signedIn = params.get('signedIn');
    const error = params.get('authError');
    if (!signedIn && !error) return;
    params.delete('signedIn');
    params.delete('authError');
    const rest = params.toString();
    history.replaceState({}, '', location.pathname + (rest ? `?${rest}` : ''));
    if (signedIn) {
        const me = await loadAccount({ fresh: true });
        toast(me ? t('auth.back.signedIn', { name: me.name }) : t('auth.back.failed'), { tone: me ? 'info' : 'error' });
        if (me?.betaSinger && location.pathname === '/') navigate('/beta');
    } else {
        toast(t(AUTH_ERRORS[error] ?? AUTH_ERRORS.failed), { tone: 'error', seconds: 10 });
    }
}

/** Once, for an account a provider made: are you 13 or over? Under 13, the account goes. */
