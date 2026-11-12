// The public site's language menu (the header's x-site.header, App\Support\Languages\LanguageMenu): a <details> with a
// link to this page in each open language. Choosing one remembers it in the `sa_lang` cookie (only the language's
// code, a year, on the theme cookie's domain so the app reads it too), then the link is followed. Nothing here ever
// moves anyone by itself. It also closes on Escape or a tap outside.
//
//   <details data-lang-menu><summary>…</summary><ul><li><a data-lang-choice="es" href="/es/…">Español</a></li></ul></details>

const YEAR = 60 * 60 * 24 * 365;

/** The cookie for a choice: its code only, shared on the domain the page names (as the theme's). */
export function langCookie(code, { domain = null, secure = false } = {}) {
    return `sa_lang=${encodeURIComponent(code)}; Max-Age=${YEAR}; Path=/; SameSite=Lax${domain ? `; Domain=${domain}` : ''}${secure ? '; Secure' : ''}`;
}

export function mountLangMenus(root = document) {
    const domain = root.querySelector?.('meta[name="theme-cookie-domain"]')?.getAttribute('content') || null;
    for (const menu of root.querySelectorAll('[data-lang-menu]')) {
        if (menu.dataset.mounted !== undefined) continue;
        menu.dataset.mounted = '';
        menu.addEventListener('click', (event) => {
            const choice = event.target.closest?.('[data-lang-choice]');
            if (!choice) return;
            try {
                document.cookie = langCookie(choice.dataset.langChoice, { domain, secure: location.protocol === 'https:' });
            } catch (error) {
                console.warn('The language choice could not be remembered here.', error);
            }
        });
        menu.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && menu.open) {
                menu.open = false;
                menu.querySelector('summary')?.focus();
            }
        });
        document.addEventListener('click', (event) => {
            if (menu.open && !menu.contains(event.target)) menu.open = false;
        });
    }
}

mountLangMenus();
