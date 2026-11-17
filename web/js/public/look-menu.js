// Language and appearance (components/site/look-menu; Hillel, 2026-11-16: "combine the two buttons"): the behaviour
// for one pill in a header (the site's x-site.header and the home page's top line) that opens a small menu of the
// languages, Light, Dark or Auto, and on a phone Game mode. The markup is a <details>, so its links work with no
// script; this adds:
// - the keys: Enter or Space opens it (the summary's own), focus goes to the current choice, the arrows (and Home and
//   End) move between the options, Tab and Shift+Tab stay inside while it's open, Escape closes it and gives focus
//   back to the pill; a tap anywhere else closes it;
// - Appearance: each skin keeps its own light or dark (game mode's is `gameMode`), through theme-boot's choose(), the
//   one way to change the look, the same stored choice as the app's; Auto is `system`, which follows the device. The
//   menu stays open while the look changes, so the change shows at once;
// - the pill's name with the current values ("Language and appearance: English, Dark") and aria-expanded.
// The languages' links keep their choice in `sa_lang` (js/public/lang-menu.js); Game mode's switch is site.js's.
// Tested in tests/look-menu.test.js and tests/flows/site_keyboard.py.

import './lang-menu.js';

const ITEMS = 'a[href], button:not([disabled])';

/** The light or dark chosen for the skin showing now: 'light', 'dark' or 'system' (until chosen, Auto in serious mode, dark in game mode). */
export function currentMode(theme = window.stackapellaTheme, root = document.documentElement) {
    const choice = theme?.chosen?.() ?? {};
    const mode = root.dataset.skin === 'serious' ? choice.mode : choice.gameMode;
    if (mode === 'light' || mode === 'dark' || mode === 'system') return mode;
    return root.dataset.skin === 'serious' ? 'system' : 'dark';
}

/** The change that picks `mode` for the skin showing now. */
export function modeChange(mode, root = document.documentElement) {
    return root.dataset.skin === 'serious' ? { mode } : { gameMode: mode };
}

/** The pill's name: the menu's, then the current values. */
export function lookLabel(name, values) {
    const now = values.filter(Boolean);
    return now.length ? `${name}: ${now.join(', ')}` : name;
}

/** Wires one menu (a <details data-look-menu>). Returns { close(), destroy() }. */
export function mountLookMenu(menu) {
    const root = document.documentElement;
    const pill = menu.querySelector(':scope > summary');
    const panel = menu.querySelector(':scope > .s-look-panel');
    if (!pill || !panel) return null;
    const shown = (el) => el.getClientRects().length > 0;
    const items = () => [...panel.querySelectorAll(ITEMS)].filter(shown);

    function reflect() {
        const mode = currentMode(window.stackapellaTheme, root);
        let modeWord = '';
        for (const b of panel.querySelectorAll('[data-look-mode]')) {
            const on = b.dataset.lookMode === mode;
            b.setAttribute('aria-pressed', String(on));
            if (on) modeWord = b.textContent.trim();
        }
        const language = panel.querySelector('[data-lang-choice][aria-current="true"]')?.textContent.trim() ?? '';
        pill.setAttribute('aria-label', lookLabel(pill.dataset.lookName ?? '', [language, modeWord]));
        pill.setAttribute('aria-expanded', String(menu.open));
    }

    function close({ focus = true } = {}) {
        if (!menu.open) return;
        menu.open = false;
        if (focus) pill.focus({ preventScroll: true });
    }

    const onToggle = () => {
        reflect();
        if (!menu.open) return;
        // Into the menu, on the current choice (the language, else the look), so the arrows start from it.
        const start = panel.querySelector('[data-lang-choice][aria-current="true"]') ?? panel.querySelector('[data-look-mode][aria-pressed="true"]') ?? items()[0];
        start?.focus({ preventScroll: true });
    };
    const onPanel = (event) => {
        const b = event.target.closest?.('[data-look-mode]');
        if (!b || !panel.contains(b)) return;
        window.stackapellaTheme?.choose(modeChange(b.dataset.lookMode, root));
        reflect(); // it stays open: the next look is one tap away
    };
    const onKey = (event) => {
        if (event.key === 'ArrowDown' && event.target === pill && !menu.open) {
            event.preventDefault();
            menu.open = true;
            return;
        }
        if (!menu.open) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            close();
            return;
        }
        const list = items();
        if (!list.length) return;
        const at = list.indexOf(document.activeElement);
        const step = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[event.key];
        let next = null;
        if (step) next = list[at < 0 ? (step > 0 ? 0 : list.length - 1) : (at + step + list.length) % list.length];
        else if (event.key === 'Home') next = list[0];
        else if (event.key === 'End') next = list[list.length - 1];
        else if (event.key === 'Tab') next = at < 0 ? list[event.shiftKey ? list.length - 1 : 0] : list[(at + (event.shiftKey ? -1 : 1) + list.length) % list.length];
        if (!next) return;
        event.preventDefault();
        next.focus();
    };
    // A tap anywhere else closes it (without taking focus: the tap went somewhere on purpose).
    const onOutside = (event) => {
        if (menu.open && !menu.contains(event.target)) close({ focus: false });
    };

    menu.addEventListener('toggle', onToggle);
    panel.addEventListener('click', onPanel);
    menu.addEventListener('keydown', onKey);
    document.addEventListener('pointerdown', onOutside, true);
    // Follows a change made anywhere (Game mode, another tab's cookie, the account, the device in Auto).
    const watch = new MutationObserver(reflect);
    watch.observe(root, { attributes: true, attributeFilter: ['data-theme', 'data-skin'] });
    reflect();

    return {
        close,
        destroy() {
            menu.removeEventListener('toggle', onToggle);
            panel.removeEventListener('click', onPanel);
            menu.removeEventListener('keydown', onKey);
            document.removeEventListener('pointerdown', onOutside, true);
            watch.disconnect();
        },
    };
}

/** Every Language and appearance on the page (once each). */
export function mountLookMenus(scope = document) {
    const menus = [];
    for (const menu of scope.querySelectorAll('details[data-look-menu]')) {
        if (menu.dataset.lookMounted !== undefined) continue;
        menu.dataset.lookMounted = '';
        const mounted = mountLookMenu(menu);
        if (mounted) menus.push(mounted);
    }
    return menus;
}

mountLookMenus();
