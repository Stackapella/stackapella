// What the app's error reporting (errors.js) and the public pages' notes (public/report.js) share to speak to Sentry
// through our tunnel (POST /log/errors): the browser and OS from the user agent, nothing finer, the environment, and
// the envelope. No imports, so a public page can load it.

const BROWSERS = [['Edge', /Edg\/(\d+)/], ['Firefox', /Firefox\/(\d+)/], ['Chrome', /Chrome\/(\d+)/], ['Safari', /Version\/(\d+).*Safari/]]; // i18n-ignore: sent to Sentry, never shown

/** "Chrome 131 on macOS": the browser and OS from the user agent, nothing finer. */
export function describeBrowser(ua) {
    const found = BROWSERS.map(([name, pattern]) => [name, pattern.exec(ua)]).find(([, m]) => m);
    const browser = found ? [found[0], found[1][1]] : ['Other', '']; // i18n-ignore: sent to Sentry, never shown
    const os = /iPhone|iPad/.test(ua) ? 'iOS' : /Android/.test(ua) ? 'Android' : /Mac OS X/.test(ua) ? 'macOS' : /Windows/.test(ua) ? 'Windows' : /Linux/.test(ua) ? 'Linux' : 'Other'; // i18n-ignore: sent to Sentry, never shown
    return { browser: { name: browser[0], version: browser[1] }, os: { name: os } };
}

/** Which Sentry environment a host is. */
export function environmentOf(hostname) {
    return hostname === 'localhost' || hostname === '127.0.0.1' ? 'development' : hostname.startsWith('beta.') ? 'beta' : 'production';
}

/** A Sentry envelope: its header (with the DSN the tunnel checks), the item's header, the event. */
export function envelopeFor(event, dsn) {
    const body = JSON.stringify(event);
    return `${JSON.stringify({ event_id: event.event_id, dsn, sent_at: new Date().toISOString() })}\n${JSON.stringify({ type: 'event', length: new TextEncoder().encode(body).length })}\n${body}\n`;
}
