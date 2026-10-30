// Error reporting (Hillel's Sentry, without its SDK): uncaught errors, unhandled rejections and Vue's
// errors go to Sentry as envelopes through our own tunnel (POST /log/errors, which forwards only our
// browser DSN, so connect-src stays 'self'). What's sent is the error, the build, the browser and
// the screen's address with any token taken out. Never who it was: no account id, name, email or IP
// (Hillel: no IDs kept to follow anyone, and Sentry is a third party), nor words people wrote,
// lyrics or audio. Nothing is sent without a DSN
// (auth.config's sentryDsn), offline, more than MAX_A_PAGE times, or twice for the same error.

import { APP_VERSION } from './api.js';
import { account } from './stores/account.js';
import { version } from './version.js';

const MAX_A_PAGE = 10;
const WAITING_MAX = 5; // kept while offline, sent when the connection is back
// Addresses that carry a token or a code: reported as their pattern.
const TOKEN_PATHS = [/^\/(invite|early|join|choir|teacher|beta)\/[^/]+/, /^\/(verify|reset|email\/confirm|email\/revert|signin\/link)(\/.*)?$/];
const NOISE = [/ResizeObserver loop/i, /AbortError/, /The (user|operation) aborted/i, /Load failed$/, /^Script error\.?$/];
const EXTENSION = /(chrome|moz|safari(-web)?)-extension:\/\//;
const BROWSERS = [['Edge', /Edg\/(\d+)/], ['Firefox', /Firefox\/(\d+)/], ['Chrome', /Chrome\/(\d+)/], ['Safari', /Version\/(\d+).*Safari/]]; // i18n-ignore: sent to Sentry, never shown

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

/** "Chrome 131 on macOS": the browser and OS from the user agent, nothing finer. */
export function describeBrowser(ua) {
    const found = BROWSERS.map(([name, pattern]) => [name, pattern.exec(ua)]).find(([, m]) => m);
    const browser = found ? [found[0], found[1][1]] : ['Other', '']; // i18n-ignore: sent to Sentry, never shown
    const os = /iPhone|iPad/.test(ua) ? 'iOS' : /Android/.test(ua) ? 'Android' : /Mac OS X/.test(ua) ? 'macOS' : /Windows/.test(ua) ? 'Windows' : /Linux/.test(ua) ? 'Linux' : 'Other'; // i18n-ignore: sent to Sentry, never shown
    return { browser: { name: browser[0], version: browser[1] }, os: { name: os } };
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

/** The event JSON for an error: its type, message and frames, and what it happened in. */
export function eventFor(error, { mechanism = 'onerror', context = null, path = location.pathname, ua = navigator.userAgent, release = APP_VERSION, build = null } = {}) {
    const type = error?.name || 'Error';
    const value = String(error?.message ?? error ?? '').slice(0, 1000);
    const { browser, os } = describeBrowser(ua);
    return {
        event_id: crypto.randomUUID().replace(/-/g, ''),
        timestamp: Date.now() / 1000,
        platform: 'javascript',
        level: 'error',
        release: build ? `${release}+${build}` : release,
        environment: location.hostname === 'localhost' || location.hostname === '127.0.0.1' ? 'development' : location.hostname.startsWith('beta.') ? 'beta' : 'production',
        exception: { values: [{ type, value, stacktrace: { frames: framesOf(error?.stack) }, mechanism: { type: mechanism, handled: false } }] },
        request: { url: `${location.origin}${scrubPath(path)}` },
        contexts: { browser, os },
        tags: { route: scrubPath(path), ...(context ? { vue: context } : {}) },
    };
}

/** A Sentry envelope: its header (with the DSN the tunnel checks), the item's header, the event. */
export function envelopeFor(event, dsn) {
    const body = JSON.stringify(event);
    return `${JSON.stringify({ event_id: event.event_id, dsn, sent_at: new Date().toISOString() })}\n${JSON.stringify({ type: 'event', length: new TextEncoder().encode(body).length })}\n${body}\n`;
}

/** Whether an error isn't ours to report: an extension's, a resize loop, an aborted fetch. */
export function isNoise(error) {
    const message = String(error?.message ?? error ?? '');
    const text = `${error?.name ?? ''}: ${message}`;
    // Tested on the message alone too: "Script error." (a cross-origin script's, or an extension's or an
    // in-app browser's, its details hidden) comes as { name: 'Error', message: 'Script error.' }.
    if (NOISE.some((r) => r.test(text) || r.test(message))) return true;
    return EXTENSION.test(String(error?.stack ?? '')) || EXTENSION.test(String(error?.filename ?? ''));
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
        if (event.error) report(event.error, { mechanism: 'onerror' });
        else if (event.message) report({ name: 'Error', message: event.message, filename: event.filename, stack: '' }, { mechanism: 'onerror' }); // i18n-ignore: sent to Sentry, never shown
    });
    window.addEventListener('unhandledrejection', (event) => {
        const reason = event.reason instanceof Error ? event.reason : { name: 'UnhandledRejection', message: String(event.reason?.message ?? event.reason), stack: '' };
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
