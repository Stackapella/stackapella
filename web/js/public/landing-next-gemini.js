// The landing's next version, the gemini variant (/next-gemini; pages/landing-next-gemini.blade.php): the page's own
// small script. It makes no sound and holds no timers: js/public/mixer.js is the player (the lanes are its voices, the
// bubble its Play), and this only follows it and the scroll:
// - which scene is in view (data-scene on the film, which the sheet reads to move the stage; the rail's mark);
// - the bar, small on the way down and whole on the way up;
// - the lanes as one group for the keyboard (arrows, Home and End move between them; Enter or Space plays one alone);
// - the lane heard alone as the stage's focus (--f, data-focus), said to a screen reader, and named by Learn;
// - the bar's Stop while the song plays, light or dark, the menus, and the page's one anonymous count.
// Scrolling never touches the audio: nothing here seeks, pauses or starts it but a press.
// Tested in tests/flows/landing_next_gemini.py.

import { countAnon } from './count.js';

const film = document.querySelector('[data-lngm-film]');
const bar = document.querySelector('[data-lngm-bar]');

// The scene in view: the one covering the middle of the window.
const scenes = [...document.querySelectorAll('[data-lngm-scene]')];
const marks = [...document.querySelectorAll('[data-lngm-rail] a')];
if (film && 'IntersectionObserver' in window) {
    const seen = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            const scene = entry.target.dataset.lngmScene;
            film.dataset.scene = scene;
            marks.forEach((mark, i) => (String(i) === scene ? mark.setAttribute('aria-current', 'true') : mark.removeAttribute('aria-current')));
        }
    }, { rootMargin: '-50% 0px -50% 0px' });
    for (const scene of scenes) seen.observe(scene);
}

// The bar: small once the page has moved down, whole again as soon as it moves up (or is back at the top).
if (bar) {
    let last = window.scrollY;
    window.addEventListener('scroll', () => {
        const y = window.scrollY;
        if (Math.abs(y - last) < 6) return;
        bar.toggleAttribute('data-small', y > last && y > 40);
        last = y;
    }, { passive: true });
    // A menu opened from the keyboard, or anything in the bar focused, brings it back whole.
    bar.addEventListener('focusin', () => bar.removeAttribute('data-small'));
}

// Light or dark, the same stored choice as the rest of the site (js/theme-boot.js); this page is the calm skin in either.
for (const button of document.querySelectorAll('[data-lngm-theme]')) {
    button.addEventListener('click', () => {
        const light = (document.documentElement.dataset.theme ?? '').endsWith('-light');
        const game = document.documentElement.dataset.skin !== 'serious';
        window.stackapellaTheme?.choose(game ? { gameMode: light ? 'dark' : 'light' } : { mode: light ? 'dark' : 'light' });
    });
}

// The bar's menus: one open at a time, closed by Escape (focus back on its button) or a press outside.
const menus = [...document.querySelectorAll('[data-lngm-menu]')];
for (const menu of menus) {
    menu.addEventListener('toggle', () => {
        if (menu.open) menus.filter((other) => other !== menu).forEach((other) => (other.open = false));
    });
    menu.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !menu.open) return;
        menu.open = false;
        menu.querySelector('summary')?.focus();
    });
}
document.addEventListener('click', (event) => {
    for (const menu of menus) if (menu.open && !menu.contains(event.target)) menu.open = false;
});

// The lanes: one stop for Tab, the arrows between them.
const lanes = [...document.querySelectorAll('[data-lngm-lane]')];
const hits = lanes.map((lane) => lane.querySelector('[data-hear-alone]'));
hits.forEach((hit, at) => {
    hit?.addEventListener('keydown', (event) => {
        const to = { ArrowDown: at + 1, ArrowRight: at + 1, ArrowUp: at - 1, ArrowLeft: at - 1, Home: 0, End: hits.length - 1 }[event.key];
        if (to === undefined) return;
        event.preventDefault();
        const next = hits[(to + hits.length) % hits.length];
        hits.forEach((other) => (other.tabIndex = other === next ? 0 : -1));
        next.focus();
    });
    hit?.addEventListener('focus', () => hits.forEach((other) => (other.tabIndex = other === hit ? 0 : -1)));
});

// The part heard alone (the mixer says so) is the stage's focus: the lane the Sing along scene pushes into, the part
// Learn names, and what a screen reader hears. All of them again: the focus stays where it was.
const say = document.querySelector('[data-lngm-say]');
const learn = document.querySelector('[data-lngm-learn-link]');
film?.addEventListener('mixer-solo', (event) => {
    const key = event.detail?.key ?? null;
    const lane = key === null ? null : lanes.find((el) => el.dataset.voice === key);
    if (say) say.textContent = lane ? (lane.querySelector('[data-hear-alone]')?.dataset.said ?? '') : (say.dataset.all ?? '');
    if (!lane) return;
    lanes.forEach((el) => el.toggleAttribute('data-focus', el === lane));
    film.style.setProperty('--f', lane.dataset.lngmLane);
    const mine = lane.querySelector('[data-lngm-learn]');
    if (learn && mine) {
        learn.href = mine.href;
        learn.firstChild.textContent = `${mine.dataset.lngmLearn} `;
    }
});

// Stop, in the bar, while the song plays: the stage's own button may be scenes away. It presses that button.
const stop = document.querySelector('[data-lngm-stop]');
const play = film?.querySelector('[data-play]');
if (film && stop && play) {
    new MutationObserver(() => (stop.hidden = !film.hasAttribute('data-playing'))).observe(film, { attributes: true, attributeFilter: ['data-playing'] });
    stop.addEventListener('click', () => {
        if (film.hasAttribute('data-playing')) play.click();
    });
}

// This page load's one anonymous count, its own kind (never a site section), only for a person (js/automated.js).
const visit = document.querySelector('[data-lngm-visit]');
if (visit) {
    const { kind } = JSON.parse(visit.textContent);
    if (typeof kind === 'string') countAnon(kind);
}
