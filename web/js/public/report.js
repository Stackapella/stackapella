// A note to our Sentry from the public site's pages, which have no error reporting of their own (the app's, errors.js,
// needs the app): once a page for each thing worth knowing, as a song's render that wouldn't play in some browser (the
// coordinator, 2026-11-13: learn about Safari before a singer tells us). Through our own tunnel (POST /log/errors), with
// our browser DSN (auth.config's sentryDsn, read only when there's something to say). What's sent: the note, the
// page's address (a public page, no token in it), the browser and OS. Never who it was: no account, cookie or IP.

import { describeBrowser, environmentOf, envelopeFor } from '../sentry-envelope.js';

const said = new Set();

/** The event for a note: a warning with its words, the page and the browser, and `tags`. Pure (the tests check it). */
export function noteEvent(message, tags, { path, origin, ua, hostname }) {
    const { browser, os } = describeBrowser(ua);
    return {
        event_id: crypto.randomUUID().replace(/-/g, ''),
        timestamp: Date.now() / 1000,
        platform: 'javascript',
        level: 'warning',
        logger: 'site',
        environment: environmentOf(hostname),
        message: { formatted: message },
        request: { url: `${origin}${path}` },
        contexts: { browser, os },
        tags: { route: path, browser: `${browser.name} ${browser.version}`.trim(), os: os.name, ...tags },
    };
}

/** Says `message` (with `tags`) once a page. Never throws and never holds anything up. */
export function note(message, tags = {}) {
    if (said.has(message)) return;
    said.add(message);
    (async () => {
        const response = await fetch('/api/auth/config', { headers: { Accept: 'application/json' }, credentials: 'omit' });
        const dsn = response.ok ? (await response.json()).data?.sentryDsn : null;
        if (!dsn) return;
        const event = noteEvent(message, tags, { path: location.pathname, origin: location.origin, ua: navigator.userAgent, hostname: location.hostname });
        await fetch('/log/errors', { method: 'POST', body: envelopeFor(event, dsn), keepalive: true, credentials: 'omit', headers: { 'Content-Type': 'application/x-sentry-envelope' } });
    })().catch((error) => console.info('A note was not sent', error?.name)); // i18n-ignore
}
