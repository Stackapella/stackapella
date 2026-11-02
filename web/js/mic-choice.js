// The mic someone chose, remembered by its name across the site and the app (docs/decisions.md, "Mic
// choice"). They're different addresses and browsers give each its own device ids, so each keeps its own
// choice by id, and the chosen mic's name goes in the `sa_mic` cookie on the shared domain (as
// theme-boot.js's sa_theme): a year, the name and nothing else. The server never reads or keeps it.
// pickByLabel() finds that mic again among a list of inputs. Tested in tests/mic-choice.test.js.

const COOKIE = 'sa_mic';
const ALIASES = new Set(['default', 'communications']);
// Chrome's copies of a mic ("Default - MacBook Pro Microphone") name the same mic.
const plain = (label) => String(label ?? '').replace(/^(Default|Communications) - /, '').trim();

/** The name of the mic chosen last, on the site or in the app, or '' when there's none. */
export function rememberedMicLabel() {
    // A host-only sa_mic from before the shared domain can sit beside the shared one; a browser lists
    // the older first, so the last is the one chosen most recently.
    const cookie = document.cookie.split('; ').filter((c) => c.startsWith(`${COOKIE}=`)).at(-1);
    if (!cookie) return '';
    try {
        return decodeURIComponent(cookie.slice(COOKIE.length + 1));
    } catch (error) {
        console.warn('The remembered mic name could not be read', error);
        return '';
    }
}

/** Remembers the chosen mic's name for the site and the app, for a year. */
export function rememberMicLabel(label) {
    const name = plain(label);
    if (!name) return;
    const domain = document.querySelector('meta[name="theme-cookie-domain"]')?.getAttribute('content');
    const secure = location.protocol === 'https:' ? '; secure' : '';
    document.cookie = `${COOKIE}=${encodeURIComponent(name)}; path=/; max-age=31536000; samesite=lax${domain ? `; domain=${domain}` : ''}${secure}`;
    if (domain) document.cookie = `${COOKIE}=; path=/; max-age=0; samesite=lax${secure}`; // the host-only one from before, gone
}

/**
 * The deviceId among `mics` (`[{ deviceId, label }]`) whose name is `label`, ignoring case and Chrome's
 * "Default - " copies; null with no name or no match, so the caller keeps the default. Pure.
 */
export function pickByLabel(mics, label) {
    const want = plain(label).toLowerCase();
    if (!want) return null;
    return (mics ?? []).find((m) => !ALIASES.has(m.deviceId) && plain(m.label).toLowerCase() === want)?.deviceId ?? null;
}
