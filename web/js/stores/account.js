// Who's signed in (contract/api.md, "Accounts and sign-in"). An account is never needed to learn or
// record (docs/roadmap.md, M3): signed out, `account.me` is null and everything else works. The
// server's session cookie is the sign-in; this only remembers what account.show said.

import { reactive } from 'vue';
import { api, ApiError } from '../api.js';
import { local, session as tabStorage } from '../storage.js';

export const account = reactive({ me: null, loaded: false, config: null });

let loading = null;

/** Loads the signed-in account once (again with `fresh`); signed out leaves `me` null. */
export function loadAccount({ fresh = false } = {}) {
    if (loading && !fresh) return loading;
    loading = (async () => {
        try {
            account.me = (await api.account.show()).data;
        } catch (error) {
            if (error instanceof ApiError && (error.status === 401 || error.code === 'unauthenticated')) account.me = null;
            else console.warn('The account could not be checked', error); // offline: keep what we had
        } finally {
            account.loaded = true;
        }
        return account.me;
    })();
    return loading;
}

/** The sign-in screens' settings: the methods on offer, sign-ups open or paused, shield mode's key. */
export async function loadAuthConfig() {
    if (!account.config) account.config = (await api.auth.config()).data;
    return account.config;
}

/** A self-hosted install (auth.config.selfHosted): every account has Plus, and nothing offers it. */
export function selfHosted() {
    return !!account.config?.selfHosted;
}

/** After any sign-in: the account the server answered with. */
export function signedIn(me, method = null) {
    account.me = me;
    account.loaded = true;
    if (method) local.set('stackapella.lastSignIn', method);
}

export async function signOut({ everywhere = false } = {}) {
    // This device's reminders stop first (the account can't send them once nobody's signed in here).
    await import('../reminders.js').then((r) => r.turnOffHere()).catch((error) => console.warn('Reminders not stopped', error));
    await (everywhere ? api.auth.signout.everywhere() : api.auth.signout());
    account.me = null;
}

/** The sign-in method used last on this device, offered first. */
export function lastMethod() {
    return local.get('stackapella.lastSignIn') || null;
}

/**
 * Takes a link's token (and the reset link's email) out of the address bar at once, keeping them in
 * the tab for the screen that uses them (linkToken()). For /verify, /reset, /signin/link and /email/*.
 */
export function keepLinkToken(path = location.pathname) {
    if (!/^\/(verify|reset|signin\/link|email\/(confirm|revert))\/?$/.test(path)) return;
    const params = new URLSearchParams(location.search);
    if (!params.has('token')) return;
    tabStorage.set('stackapella.link', JSON.stringify({ path, token: params.get('token'), email: params.get('email') ?? '' }));
    params.delete('token');
    params.delete('email');
    const rest = params.toString();
    history.replaceState({}, '', path + (rest ? `?${rest}` : ''));
}

/** The token kept by keepLinkToken() for this screen (once: it's forgotten as it's read). */
export function linkToken(path = location.pathname) {
    try {
        const kept = JSON.parse(tabStorage.get('stackapella.link') || 'null');
        tabStorage.set('stackapella.link', '');
        return kept?.path === path ? kept : null;
    } catch {
        return null;
    }
}

/** Where people came from (`?ref=` on our links), kept for the sign-up. */
export function rememberRef(search = location.search) {
    const ref = new URLSearchParams(search).get('ref');
    if (ref && /^[A-Za-z0-9-]{1,40}$/.test(ref)) local.set('stackapella.ref', ref);
}

export function ref() {
    return local.get('stackapella.ref') || null;
}

/**
 * The XSRF cookie's value, for a form that leaves the app (Google and Apple sign-in): a form can't
 * send the header, so it goes as `_token` (contract/api.md, "Sign-in navigations").
 */
export function csrfToken() {
    const found = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='));
    return found ? decodeURIComponent(found.slice('XSRF-TOKEN='.length)) : '';
}

/**
 * Signs in (or up) with Google or Apple: a form POST that leaves the app and comes back to
 * `intended` with ?signedIn= or ?authError=. `link` adds the method to the signed-in account;
 * `invite` carries a beta invite through a sign-up.
 */
export async function providerSignIn(provider, { intended = location.pathname, link = false, invite = null, early = null } = {}) {
    local.set('stackapella.lastSignIn', provider);
    // The form carries the session's CSRF token: fetched fresh first (auth.config sets the cookie),
    // so a first visit, or a page left open past its session, doesn't come back as "expired" (419).
    try {
        account.config = (await api.auth.config()).data;
    } catch (error) {
        console.warn('The sign-in settings did not refresh; trying with the token there is', error);
    }
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/auth/${provider}`;
    const fields = { _token: csrfToken(), intended, ...(link ? { link: '1' } : {}), ...(invite ? { invite } : {}), ...(early ? { early } : {}) };
    for (const [name, value] of Object.entries(fields)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.append(input);
    }
    document.body.append(form);
    form.submit();
}
