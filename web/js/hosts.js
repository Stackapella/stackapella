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
