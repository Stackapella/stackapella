// The public site's header (server/resources/views/components/site/header.blade.php): the light and
// dark switch, which flips what's showing through theme-boot.js's choose() (the one way to change
// the look, kept for this host and shared with the app's), and the phone menu, which closes on
// Escape, a tap outside it, or focus moving out of it.

import { mountGameToggles } from './game-toggle.js';

for (const button of document.querySelectorAll('[data-theme-toggle]')) {
    button.addEventListener('click', () => {
        const light = (document.documentElement.dataset.theme ?? '').endsWith('-light');
        window.stackapellaTheme?.choose({ mode: light ? 'dark' : 'light' });
    });
}

// Game mode on or off: <html data-skin>, set before paint by theme-boot.js (which holds the default),
// the same stored choice as the app's. The design agent's toggle (game-toggle.js) owns aria-checked,
// its own moment and follows outside changes; the page changes skin when it says so. Switching plays
// the hero's moment too (input.css, data-switching): the screen leaving powers down, the other boots
// up, and a second press mid-way starts the new one at once. The four voices stop when the game
// comes on (the cabinet takes over, js/public/cabinet.js).
let switching = 0;
mountGameToggles(document, {
    set(on) {
        const root = document.documentElement;
        const skin = on ? 'game' : 'serious';
        if (root.dataset.skin === skin) return;
        if (on) document.querySelector('#hero[data-playing] [data-play]')?.click();
        clearTimeout(switching);
        root.dataset.switching = `to-${skin}`;
        switching = setTimeout(() => delete root.dataset.switching, 700);
        if (window.stackapellaTheme) window.stackapellaTheme.choose({ skin });
        else root.dataset.skin = skin;
    },
});

for (const menu of document.querySelectorAll('.s-menu')) {
    document.addEventListener('click', (event) => {
        if (menu.open && !menu.contains(event.target)) menu.open = false;
    });
    menu.addEventListener('focusout', (event) => {
        if (menu.open && !menu.contains(event.relatedTarget)) menu.open = false;
    });
    menu.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            menu.querySelector('summary')?.focus();
        }
    });
}
