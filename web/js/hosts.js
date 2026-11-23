// Where the public site is, from the app (docs/app-subdomain-plan.md): the shell's
// <meta name="public-url"> when the server gives it; else the app host without its "app."
// (app.stackapella.com -> stackapella.com); else, one host for both (local), the landing at
// /?landing, which shows it even to someone who has used the app.

export function publicSiteUrl() {
    const meta = document.querySelector('meta[name="public-url"]')?.content;
    if (meta) {
        // One host for both (the meta is this app's own address): /?landing shows the landing.
        const site = new URL(meta, location.href);
        return site.origin === location.origin ? '/?landing' : `${site.origin}/`;
    }
    const { protocol, hostname, port } = location;
    if (hostname.startsWith('app.')) return `${protocol}//${hostname.slice(4)}${port ? `:${port}` : ''}/`;
    return '/?landing';
}

const FROM_SITE = 'stackapella.fromSite';

/**
 * Whether a referrer is the public site (the consultant's UX review, 2026-11-22: "a clear route back to the website"):
 * its origin is the site's, and the site lives on another host than the app. Pure, for its test.
 */
export function siteReferrer(referrer, site = publicSiteUrl(), here = location.origin) {
    if (!referrer || site.startsWith('/')) return false;
    try {
        const from = new URL(referrer).origin;
        return from === new URL(site).origin && from !== here;
    } catch {
        return false;
    }
}

/** At start: an arrival from the public site is remembered on this device, so Home offers the way back. */
export function noteSiteArrival() {
    if (!siteReferrer(document.referrer)) return;
    try {
        localStorage.setItem(FROM_SITE, '1');
    } catch (error) {
        console.info('The site arrival was not kept', error?.message ?? error);
    }
}

/** Someone who came to the app from the public site (on this device). */
export function cameFromSite() {
    try {
        return localStorage.getItem(FROM_SITE) === '1';
    } catch {
        return false; // storage blocked: the logo still goes there
    }
}
