// An anonymous count from a public page (the free tools), as js/usage.js sends the app's: a beacon to our own server's
// counts.create with nothing about who, no ID and no cookie. The server drops it while counts are off. Its path is
// api.js's operation's (tool/api_check.py holds a public page to the contract's paths); named here so a tool page
// doesn't download all of api.js for one string.
const COUNTS = '/api/counts';

/** Counts one `kind` (one of counts.kinds, such as result_shared) for a visitor the page doesn't know. */
export function countAnon(kind) {
    navigator.sendBeacon?.(COUNTS, new Blob([JSON.stringify({ events: [{ kind }], signedIn: false })], { type: 'application/json' }));
}
