// Error reporting (Hillel's Sentry, without its SDK): uncaught errors, unhandled rejections and Vue's
// errors go to Sentry as envelopes through our own tunnel (POST /log/errors, which forwards only our
// browser DSN, so connect-src stays 'self'). What's sent is the error, the build, the browser and
// the screen's address with any token taken out. Never who it was: no account id, name, email or IP
// (Hillel: no IDs kept to follow anyone, and Sentry is a third party), nor words people wrote,
// lyrics or audio. Nothing is sent without a DSN
// (auth.config's sentryDsn), offline, more than MAX_A_PAGE times, or twice for the same error.

import { APP_VERSION } from './api.js';
import { describeBrowser, environmentOf, envelopeFor } from './sentry-envelope.js';
import { account } from './stores/account.js';
import { version } from './version.js';
import { fromSound, noteSound } from './sound-log.js';

export { describeBrowser, envelopeFor };

const MAX_A_PAGE = 10;
const WAITING_MAX = 5; // kept while offline, sent when the connection is back
// Addresses that carry a token or a code: reported as their pattern.
const TOKEN_PATHS = [/^\/(invite|early|join|choir|teacher|beta)\/[^/]+/, /^\/(verify|reset|email\/confirm|email\/revert|signin\/link)(\/.*)?$/];
// `__firefox__` and `__gCrWeb`: Firefox's and Chrome's iOS apps inject their own scripts into every page
// (Sentry JAVASCRIPT-3: `window.__firefox__.reader` undefined, on the page's line 1, no frame of ours).
// The last ones are scripts in-app browsers inject into the page: Firefox's and Chrome's on iPhones,
// and a crypto wallet's browser setting window.ethereum.selectedAddress (Sentry JAVASCRIPT-4).
const NOISE = [/ResizeObserver loop/i, /AbortError/, /The (user|operation) aborted/i, /Load failed$/, /^Script error\.?$/, /__firefox__|__gCrWeb/, /window\.ethereum|\bselectedAddress\b/];
const EXTENSION = /(chrome|moz|safari(-web)?)-extension:\/\//;
// Code of ours: this host, any stackapella.com address, or the pinned CDN (Vue and the libraries). A stack with frames
// but none of these is an extension's or an injected script's, whatever it says (the coordinator, 2026-11-10).
const OURS = /^(?:https?:\/\/(?:[\w-]+\.)*stackapella\.com\/|https:\/\/cdn\.jsdelivr\.net\/npm\/)/;
// The page's own length in lines, read as this module loads (before the app draws into it). We have no inline script,
// so code at a page's own address past its last line is a script an in-app browser injected into the page: Sentry
// JAVASCRIPT-7 was 100 frames of two minified functions on "line 230" of the 69-line /learn/1-match, on iOS. Such an
// event still goes, tagged injected and at the info level, so an odd stack of ours can never vanish (the coordinator).
const PAGE_LINES = typeof document === 'undefined' ? 0 : document.documentElement.outerHTML.split('\n').length + 1;

const sent = new Set();
let count = 0;
const waiting = [];

/** The screen's address as reported: no query, no hash, and a token as `:token`. */
export function scrubPath(path) {
    const bare = String(path).split(/[?#]/)[0] || '/';
    for (const pattern of TOKEN_PATHS) {
        const m = bare.match(pattern);
        if (m) return `/${m[1]}/:token`;
    }
    // Ids stay (a song's or a lesson's id says which screen), but a long opaque segment is a token.
    return bare.replace(/\/[A-Za-z0-9_-]{32,}(?=\/|$)/g, '/:token');
}

/** A stack's frames, oldest first as Sentry wants, each its file (without query), function, line and column. */
export function framesOf(stack) {
    const frames = [];
    for (const line of String(stack ?? '').split('\n')) {
        // Chrome: "    at fn (https://host/js/x.js:12:5)" or "    at https://host/js/x.js:12:5"; Firefox and Safari: "fn@https://host/js/x.js:12:5".
        const m = /^\s*at (?:(.+?) \()?(.+?):(\d+):(\d+)\)?$/.exec(line) ?? /^(.*?)@(.+?):(\d+):(\d+)$/.exec(line);
        if (!m) continue;
        const file = m[2].split(/[?#]/)[0];
        frames.push({ function: m[1] || '?', filename: file, abs_path: file, lineno: Number(m[3]), colno: Number(m[4]), in_app: file.includes(location.host) || file.startsWith('/') });
    }
    return frames.reverse();
}

/**
 * Whether a stack is a script injected into the page: every frame (at least one) at an address of this site that is a
 * page, not a script file, and past the page's last line. Our code is always in a script file (or Vue's, on the CDN).
 */
export function injected(stack, { origin = location.origin, lines = PAGE_LINES } = {}) {
    const frames = framesOf(stack);
    return frames.length > 0 && frames.every((f) => {
        let address;
        try {
            address = new URL(f.filename);
        } catch {
            return false; // no address (eval'd code): it can be ours
        }
        return address.origin === origin && !/\.m?js$/.test(address.pathname) && f.lineno > lines;
    });
}

/** The event JSON for an error: its type, message and frames, and what it happened in. */
export function eventFor(error, { mechanism = 'onerror', context = null, path = location.pathname, ua = navigator.userAgent, release = APP_VERSION, build = null, pageLines = PAGE_LINES } = {}) {
    const type = error?.name || 'Error';
    const foreign = injected(error?.stack, { lines: pageLines });
    const value = String(error?.message ?? error ?? '').slice(0, 1000);
    const { browser, os } = describeBrowser(ua);
    return {
        event_id: crypto.randomUUID().replace(/-/g, ''),
        timestamp: Date.now() / 1000,
        platform: 'javascript',
        level: foreign ? 'info' : 'error',
        release: build ? `${release}+${build}` : release,
        environment: environmentOf(location.hostname),
        exception: { values: [{ type, value, stacktrace: { frames: framesOf(error?.stack) }, mechanism: { type: mechanism, handled: false } }] },
        request: { url: `${location.origin}${scrubPath(path)}` },
        contexts: { browser, os },
        tags: { route: scrubPath(path), ...(context ? { vue: context } : {}), ...(foreign ? { injected: 'true' } : {}) },
    };
}

/** Whether an error isn't ours to report: an extension's, a resize loop, an aborted fetch. */
export function isNoise(error) {
    const message = String(error?.message ?? error ?? '');
    const text = `${error?.name ?? ''}: ${message}`;
    // Tested on the message alone too: "Script error." (a cross-origin script's, or an extension's or an
    // in-app browser's, its details hidden) comes as { name: 'Error', message: 'Script error.' }.
    if (NOISE.some((r) => r.test(text) || r.test(message))) return true;
    if (EXTENSION.test(String(error?.stack ?? '')) || EXTENSION.test(String(error?.filename ?? ''))) return true;
    // Frames with an address, and not one of ours: someone else's script on our page. A stack with no address (none, or
    // only <anonymous>, eval'd code's) stays reported: it can be ours.
    const frames = framesOf(error?.stack);
    const located = frames.filter((f) => /^(https?:|[a-z-]+-extension:)/.test(f.filename));
    return located.length > 0 && !frames.some((f) => f.in_app || OURS.test(f.filename) || f.filename.includes('cdn.jsdelivr.net/npm/'));
}

function send(envelope) {
    if (!navigator.onLine) {
        if (waiting.length < WAITING_MAX) waiting.push(envelope);
        return;
    }
    fetch('/log/errors', { method: 'POST', body: envelope, keepalive: true, credentials: 'omit', headers: { 'Content-Type': 'application/x-sentry-envelope' } })
        .catch((error) => console.info('An error report was not sent', error?.name));
}

/** Reports one error (once per page for the same error, and at most MAX_A_PAGE). */
export function report(error, { mechanism = 'onerror', context = null } = {}) {
    const dsn = account.config?.sentryDsn;
    if (!dsn || isNoise(error) || count >= MAX_A_PAGE) return;
    const key = `${error?.name}:${error?.message}:${String(error?.stack ?? '').split('\n')[1] ?? ''}`;
    if (sent.has(key)) return;
    sent.add(key);
    count++;
    send(envelopeFor(eventFor(error, { mechanism, context, build: version.loaded }), dsn));
}

/** Listens for what isn't caught: window errors, unhandled rejections, and Vue's own (with the component). */
export function watchErrors(app) {
    window.addEventListener('error', (event) => {
        if (event.error && fromSound(event.error)) noteSound('error', event.error); // for Send diagnostics, if the person sends it
        if (event.error) report(event.error, { mechanism: 'onerror' });
        else if (event.message) report({ name: 'Error', message: event.message, filename: event.filename, stack: '' }, { mechanism: 'onerror' }); // i18n-ignore: sent to Sentry, never shown
    });
    window.addEventListener('unhandledrejection', (event) => {
        // Not an Error (JAVASCRIPT-6 was just "Na"): its kind too, as "(object Foo)" or "(string)", so the report says what it was.
        const kind = (r) => `${typeof r}${r && typeof r === 'object' && r.constructor?.name && r.constructor.name !== 'Object' ? ` ${r.constructor.name}` : ''}`; // i18n-ignore: sent to Sentry, never shown
        const reason = event.reason instanceof Error ? event.reason : { name: 'UnhandledRejection', message: `${String(event.reason?.message ?? event.reason)} (${kind(event.reason)})`, stack: String(event.reason?.stack ?? '') };
        if (fromSound(reason)) noteSound('error', reason);
        report(reason, { mechanism: 'onunhandledrejection' });
    });
    window.addEventListener('online', () => waiting.splice(0).forEach(send));
    const before = app.config.errorHandler;
    app.config.errorHandler = (error, instance, info) => {
        const name = instance?.$options?.name ?? instance?.$.type?.name ?? null;
        report(error instanceof Error ? error : { name: 'Error', message: String(error), stack: '' }, { mechanism: 'vue', context: [name, info].filter(Boolean).join(' · ') }); // i18n-ignore: sent to Sentry, never shown
        if (before) before(error, instance, info);
        else console.error(error);
    };
}
