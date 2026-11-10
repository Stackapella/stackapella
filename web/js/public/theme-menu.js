// The public pages' Theme menu (the free tools' brand row, docs/tools-plan.md "Tool page frame"):
// the behaviour for the markup in the site's x-site.theme-menu. A button opens a small panel that
// holds the Game mode row, light or dark, and the palettes; it stays open while the look changes, so
// a person can try each, and closes on Escape, a tap outside or tabbing past its end. Every choice
// goes through theme-boot's window.stackapellaTheme.choose(), the same as the app's Appearance, so
// the site, the tools and the app share one look. Framework-free; tested in tests/public-theme-menu.test.js.
//
//   <button type="button" data-theme-menu-button aria-expanded="false" aria-controls="theme-menu">…</button>
//   <div id="theme-menu" data-theme-menu hidden> [data-game-toggle] row, [data-theme-mode] and [data-theme-colour] buttons </div>
//
//   import { mountThemeMenus } from '/js/public/theme-menu.js';
//   mountThemeMenus();

/** The look now, from <html>: { skin, mode, colour } ('stage-dark' is colour 'stage', mode 'dark'). */
export function currentLook(root = document.documentElement) {
    const [colour = 'stage', mode = 'dark'] = String(root.dataset.theme ?? '').split('-');
    return { skin: root.dataset.skin === 'serious' ? 'serious' : 'game', mode, colour };
}

function choose(change) {
    if (window.stackapellaTheme) window.stackapellaTheme.choose(change);
}

/** Wires one button and its panel. Returns { open(), close(), destroy() }. */
export function mountThemeMenu(button, panel) {
    const root = document.documentElement;

    function reflect() {
        const look = currentLook(root);
        for (const b of panel.querySelectorAll('[data-theme-mode]')) b.setAttribute('aria-pressed', String(b.dataset.themeMode === look.mode));
        for (const b of panel.querySelectorAll('[data-theme-colour]')) b.setAttribute('aria-pressed', String(b.dataset.themeColour === look.colour));
    }

    const isOpen = () => !panel.hidden;
    function open() {
        panel.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        button.dataset.open = '';
        reflect();
    }
    function close({ focus = true } = {}) {
        if (!isOpen()) return;
        panel.hidden = true;
        button.setAttribute('aria-expanded', 'false');
        delete button.dataset.open;
        if (focus) button.focus({ preventScroll: true });
    }

    const onButton = () => (isOpen() ? close() : open());
    const onPanel = (event) => {
        const b = event.target.closest('button');
        if (!b || !panel.contains(b)) return;
        if (b.dataset.themeMode) choose({ mode: b.dataset.themeMode });
        else if (b.dataset.themeColour) choose({ colour: b.dataset.themeColour });
        reflect(); // the panel stays open: the next palette is one tap away
    };
    const onKey = (event) => {
        if (event.key === 'Escape' && isOpen()) {
            event.stopPropagation();
            close();
        }
    };
    // A tap anywhere else closes it (without taking focus: the tap went somewhere on purpose).
    const onOutside = (event) => {
        if (isOpen() && !panel.contains(event.target) && !button.contains(event.target)) close({ focus: false });
    };
    // Tabbing past its last control (or back before the button) closes it.
    const onFocusOut = (event) => {
        const next = event.relatedTarget;
        if (isOpen() && next instanceof Node && !panel.contains(next) && !button.contains(next)) close({ focus: false });
    };

    button.addEventListener('click', onButton);
    panel.addEventListener('click', onPanel);
    panel.addEventListener('keydown', onKey);
    button.addEventListener('keydown', onKey);
    panel.addEventListener('focusout', onFocusOut);
    document.addEventListener('pointerdown', onOutside, true);
    // Follows a change made anywhere (the Game mode row, another tab's cookie, the account).
    const watch = new MutationObserver(reflect);
    watch.observe(root, { attributes: true, attributeFilter: ['data-theme', 'data-skin'] });
    reflect();

    return {
        open,
        close,
        destroy() {
            button.removeEventListener('click', onButton);
            panel.removeEventListener('click', onPanel);
            panel.removeEventListener('keydown', onKey);
            button.removeEventListener('keydown', onKey);
            panel.removeEventListener('focusout', onFocusOut);
            document.removeEventListener('pointerdown', onOutside, true);
            watch.disconnect();
        },
    };
}

/**
 * Every Theme menu on the page (a button with data-theme-menu-button and its aria-controls panel).
 * The Game mode row inside is wired by js/public/site.js like every toggle on the page, not here.
 */
export function mountThemeMenus(scope = document) {
    const menus = [];
    for (const button of scope.querySelectorAll('[data-theme-menu-button]')) {
        const panel = document.getElementById(button.getAttribute('aria-controls') ?? '');
        if (!panel || panel.dataset.themeMenuMounted !== undefined) continue;
        panel.dataset.themeMenuMounted = '';
        menus.push(mountThemeMenu(button, panel));
    }
    return menus;
}
