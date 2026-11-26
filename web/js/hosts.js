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

/**
 * The website's home in the app's language (Hillel, 2026-11-26: "users can just click the app logo"): the site's root, or
 * /es/ for Spanish; one host for both, the landing at /?landing.
 */
export function siteHomeUrl(lang = 'en') {
    const site = publicSiteUrl();
    if (site.startsWith('/')) return site;
    return lang === 'es' ? `${site}es/` : site;
}

/**
 * A help page on the public site (/help/<slug>), in the app's language where the site has it (Spanish at /es/help/...):
 * the site's own host, or this one's when one host serves both. `lang` is the app's language.
 */
export function siteHelpUrl(slug, lang = 'en') {
    const site = publicSiteUrl();
    const base = site.startsWith('/') ? location.origin : site.replace(/\/$/, '');
    return `${base}${lang === 'es' ? '/es' : ''}/help/${slug}`;
}
