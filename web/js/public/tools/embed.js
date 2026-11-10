// A free tool embedded in another site's page (docs/tools-plan.md, "Embeds for other sites"; the
// route /embed/tools/<tool> renders resources/views/embed/tool.blade.php, which calls this). The same
// modules as the tool's own page, in a compact frame for an iframe 300 to 600 px wide:
// - the look from the link (?theme=stage-dark, ?skin=game|serious), set for this frame only and never
//   stored, else the visitor's own as theme-boot gives it;
// - <html data-embed>, which the shared layout (tool-layout.js) reads to go compact: no full-screen
//   height, a short top gap, the bar in the flow rather than pinned;
// - a small credit under the tool ("Free tools by Stackapella", in the page's words), opening in a
//   new tab with ?ref=embed-<the framing site's host>, which the server counts.
// No cookies; nothing stored but the tool's own settings. The mic only on a tap, and only where the
// framing page allows it (<iframe allow="microphone">, the tuner). Tested in tests/flows/tool_embed.py.

const THEME = /^[a-z]+-(light|dark)$/;

/** The framing site's host for the credit's ref ("x.org"), from the referrer; '' when there's none. */
export function framingHost(referrer = document.referrer) {
    try {
        return referrer ? new URL(referrer).hostname.toLowerCase() : '';
    } catch {
        return '';
    }
}

/** The credit's link: the app with ?ref=<refPrefix><host>, or the app alone when the host is unknown. */
export function creditUrl(appUrl, refPrefix, host) {
    if (!host) return appUrl;
    const url = new URL(appUrl, location.href);
    url.searchParams.set('ref', `${refPrefix}${host}`);
    return url.href;
}

/** The look the link asks for, set on <html> for this frame (never saved). */
export function applyLook(search = location.search, root = document.documentElement) {
    const params = new URLSearchParams(search);
    const theme = params.get('theme');
    const skin = params.get('skin');
    if (theme && THEME.test(theme)) root.dataset.theme = theme;
    if (skin === 'game' || skin === 'serious') root.dataset.skin = skin;
}

/**
 * Mounts `src`'s `mount` export in `stage`, compact, with the credit under it. Resolves to the tool's
 * handle (or null when the module can't load: the stage then says nothing rather than break the host page).
 */
export async function embedFrame(stage, { src, mount, words = {}, credit = '', appUrl = '/', refPrefix = 'embed-' } = {}) {
    const root = document.documentElement;
    root.dataset.embed = '';
    applyLook();
    // Game mode asked for by the link on a page that booted serious: its own stylesheet first (docs/css-split-plan.md,
    // step 3), so the tool mounts styled.
    if (root.dataset.skin === 'game') await window.stackapellaTheme?.gameCss?.();
    const tool = document.createElement('div');
    stage.replaceChildren(tool);
    const link = document.createElement('a');
    link.className = 'mx-auto mt-2 block w-fit py-2 text-xs font-semibold text-muted underline-offset-4 hover:text-cream hover:underline';
    link.href = creditUrl(appUrl, refPrefix, framingHost());
    link.target = '_blank';
    link.rel = 'noopener';
    link.textContent = credit;
    link.dataset.ui = 'embed-credit';
    if (credit) stage.append(link);
    try {
        const module = await import(src);
        return module[mount](tool, { words, keySlot: null });
    } catch (error) {
        console.error('The embedded tool could not load', error);
        return null;
    }
}
