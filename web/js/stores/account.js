// Who's signed in (contract/api.md, "Accounts and sign-in"). An account is never needed to learn or
// record (docs/roadmap.md, M3): signed out, `account.me` is null and everything else works. The
// server's session cookie is the sign-in; this only remembers what account.show said.

import { reactive, watch } from 'vue';
import { api, ApiError } from '../api.js';
import { mockWanted } from '../api-mode.js';
import { local, session as tabStorage } from '../storage.js';

// The signed-in account, kept on the device (Hillel, 2026-11-02: "keep it on the device"), so a screen
// opened offline shows who's signed in. It's what account.show answered (name, plan, settings): never a
// password or a token (the session cookie is the sign-in). Online, the server decides: a 401 clears it.
// Gone the moment no one is signed in (sign-out, a deleted account, every session ended), and with
// Start fresh (device.js clears every stackapella. key). Not with the mock, so test runs never share it.
const KEPT = 'stackapella.account';

function keptAccount() {
    if (mockWanted()) return null;
    try {
        return JSON.parse(local.get(KEPT) || 'null');
    } catch (error) {
        console.info('The kept account was unreadable', error?.message ?? error);
        return null;
    }
}

export const account = reactive({ me: keptAccount(), loaded: false, config: null });

watch(() => account.me, (me) => {
    if (mockWanted()) return;
    if (me) local.set(KEPT, JSON.stringify(me));
    else local.remove(KEPT);
}, { deep: true });

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

let configLoading = null; // the one auth.config on its way: callers at once share it (app start asked twice)

/** The sign-in screens' settings: the methods on offer, sign-ups open or paused, shield mode's key. */
export function loadAuthConfig() {
    if (account.config) return Promise.resolve(account.config);
    configLoading ??= api.auth.config().then(({ data }) => (account.config = data)).finally(() => (configLoading = null));
    return configLoading;
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
 * the tab for the screen that uses them (linkToken()). For /verify, /reset, /signin/link, /email/* and
 * /notice (a statement of reasons sent by email, views/notices.js).
 */
export function keepLinkToken(path = location.pathname) {
    if (!/^\/(verify|reset|signin\/link|email\/(confirm|revert)|notice)\/?$/.test(path)) return;
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

// A ?ref= tag as the counts take it (RecordCounts::source, up to SOURCE_MAX 60): a campaign's tag, or an embed's
// with the framing site's host (embed-choir.example.org, dots and all). The sign-up keeps only the plainer tags
// (RegisterRequest: letters, digits and hyphens, up to 40), so an embed's goes to the counts alone.
const REF = /^[A-Za-z0-9.-]{1,60}$/;
const SIGN_UP_REF = /^[A-Za-z0-9-]{1,40}$/;

/** Where people came from (`?ref=` on our links), kept for the counts and the sign-up. */
export function rememberRef(search = location.search) {
    const ref = new URLSearchParams(search).get('ref');
    if (ref && REF.test(ref)) local.set('stackapella.ref', ref);
}

export function ref() {
    return local.get('stackapella.ref') || null;
}

/** The kept tag as the sign-up takes it, or null (an embed's, with its host, is for the counts only). */
export function signUpRef() {
    const kept = ref();
    return kept && SIGN_UP_REF.test(kept) ? kept : null;
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
