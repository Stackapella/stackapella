// The public site's header (server/resources/views/components/site/header.blade.php): the light and
// dark switch, which flips what's showing through theme-boot.js's choose() (the one way to change
// the look, kept for this host and shared with the app's), and the phone menu, which closes on
// Escape, a tap outside it, or focus moving out of it.

import { mountGameToggles } from './game-toggle.js';
import { showHats } from './season.js';

// The logo's Santa hat on the visitor's own Christmas Eve and Day (season.js); the carols page wears it all season.
showHats();

// Each skin keeps its own light or dark (game mode's is `gameMode`), as the app's Theme menu does.
for (const button of document.querySelectorAll('[data-theme-toggle]')) {
    button.addEventListener('click', () => {
        const light = (document.documentElement.dataset.theme ?? '').endsWith('-light');
        const game = document.documentElement.dataset.skin !== 'serious';
        window.stackapellaTheme?.choose(game ? { gameMode: light ? 'dark' : 'light' } : { mode: light ? 'dark' : 'light' });
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

// This page load's one count, by its section only (sv_<section>, App\Support\Site\SiteVisit): the footer
// carries the kind only on the public site while counts are on, and it goes as funnel.js's site_visit does,
// a beacon to our own server with no source, no id and no path.
const visit = document.querySelector('[data-site-visit]');
if (visit) {
    const { kind } = JSON.parse(visit.textContent);
    navigator.sendBeacon?.('/api/counts', new Blob([JSON.stringify({ events: [{ kind }], signedIn: false })], { type: 'application/json' }));
}

// Admin > Live's heartbeat for the public pages (docs/realtime-dashboard-plan.md; Hillel: "Allow, cache only"): the same
// section, no ID and no path, once as this page's view and then every 30 seconds while the tab is in view, so "right now"
// counts the people reading the site. Only where the section count goes (counts on, the public site). A page timer, never
// for audio.
if (visit) {
    const section = String(JSON.parse(visit.textContent).kind ?? '').replace(/^sv_/, '');
    const area = section === 'tools' ? 'tools' : 'site';
    const beat = (view) => {
        if (document.visibilityState !== 'visible' || !/^[a-z0-9_-]{1,40}$/.test(section)) return;
        navigator.sendBeacon?.('/api/live/beat', new Blob([JSON.stringify({ area, page: section, view })], { type: 'application/json' }));
    };
    beat(true);
    setInterval(() => beat(false), 30000);
}
