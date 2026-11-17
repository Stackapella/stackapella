// The public site's languages (Language and appearance's Language group: components/site/look-menu, which
// App\Support\Languages\LanguageMenu fills): a link to this page in each open language. Choosing one remembers it in the
// `sa_lang` cookie (only the language's code, a year, on the theme cookie's domain so the app reads it too), then the
// link is followed. Nothing here ever moves anyone by itself. Opening and closing the menu is js/public/look-menu.js's.
//
//   <div data-lang-menu><ul><li><a data-lang-choice="es" href="/es/…">Español</a></li></ul></div>

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
    }
}

mountLangMenus();
