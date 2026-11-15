// /next-chatgpt (server/resources/views/pages/landing-next-chatgpt.blade.php): a candidate landing page under test
// beside /. The song is js/public/mixer.js's, unchanged; this module only moves the picture and passes presses on:
// - which scene the stage shows (data-scene on the film, from the captions passing: the CSS moves the camera);
// - the header shrinking on the way down and coming back on the way up;
// - the dock's four voices and the story's "Hear the alto alone", which press the lane's own button (the player's);
// - the lane the camera moves in on, and the Learn link, following the voice heard alone or picked;
// - the light or dark switch (js/theme-boot.js keeps the choice, shared with the rest of the site);
// - this page load's one anonymous count, under the page's own kind.
// Scrolling never touches the audio: nothing here calls the player but a press. Nothing here is timed for sound.

import { automated, whenHuman } from '../automated.js';

const film = document.querySelector('[data-lnc-film]');
const top = document.querySelector('[data-lnc-top]');

// The scene on screen: the one whose caption (or section) covers the middle of the window.
const scenes = [...document.querySelectorAll('[data-lnc-scene]')];
const dots = [...document.querySelectorAll('[data-lnc-dot]')];
function showScene(n) {
    // The film keeps its last scene (the bars) once the page moves on to the sections after it.
    if (film) film.dataset.scene = String(Math.min(4, n));
    document.body.dataset.lncScene = String(n);
    for (const dot of dots) {
        if (Number(dot.dataset.lncDot) === n) dot.setAttribute('aria-current', 'true');
        else dot.removeAttribute('aria-current');
    }
}
if (film && 'IntersectionObserver' in window) {
    film.setAttribute('data-lnc-live', '');
    const seen = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting) showScene(Number(entry.target.dataset.lncScene));
        }
    }, { rootMargin: '-50% 0px -50% 0px', threshold: 0 });
    for (const scene of scenes) seen.observe(scene);
}
showScene(Number(film?.dataset.scene ?? 0));

// The header: smaller on the way down, whole again on the way up or at the top. One read a frame.
if (top) {
    let last = window.scrollY;
    let waiting = false;
    window.addEventListener('scroll', () => {
        if (waiting) return;
        waiting = true;
        requestAnimationFrame(() => {
            waiting = false;
            const now = window.scrollY;
            if (Math.abs(now - last) < 8) return;
            top.dataset.lncTop = now > last && now > 120 ? 'down' : 'up';
            last = now;
        });
    }, { passive: true });
}

// The voices. The lane's own button is the player's control; the dock's chips and the story's button press it.
if (film?.hasAttribute('data-player')) {
    const lanes = new Map([...film.querySelectorAll('[data-voice]')].map((lane) => [lane.dataset.voice, lane]));
    const chips = [...film.querySelectorAll('[data-lnc-chip]')];
    const attempt = film.querySelector('[data-lnc-try]');
    const learn = film.querySelector('[data-lnc-learn-link]');
    const parts = JSON.parse(film.querySelector('[data-lnc-learn]')?.textContent ?? '{}');
    let alone = null; // the voice heard alone
    let picked = null; // the voice made "mine"

    const press = (key) => lanes.get(key)?.querySelector('[data-hear-alone]')?.click();
    for (const chip of chips) chip.addEventListener('click', () => press(chip.dataset.lncChip));

    // The lane the story moves in on: the voice picked, else the one heard alone, else as it was.
    function focusOn(key) {
        if (!lanes.has(key)) return;
        film.dataset.focus = key;
        for (const [other, lane] of lanes) lane.classList.toggle('lnc-is-focus', other === key);
        const part = parts[key];
        if (attempt && part) attempt.dataset.part = part.name;
        if (learn && part) {
            learn.href = part.href;
            const label = learn.querySelector('[data-lnc-learn-label]');
            if (label) label.textContent = learn.dataset.label.replace(':part', part.name);
        }
        say();
    }
    function say() {
        for (const chip of chips) chip.setAttribute('aria-pressed', String(chip.dataset.lncChip === alone));
        if (!attempt) return;
        const key = film.dataset.focus;
        const on = alone === key;
        attempt.setAttribute('aria-pressed', String(on));
        attempt.textContent = on ? attempt.dataset.labelBack : attempt.dataset.label.replace(':part', parts[key]?.name ?? '');
    }
    if (attempt) {
        attempt.hidden = false;
        attempt.addEventListener('click', () => press(film.dataset.focus));
    }
    film.addEventListener('mixer-solo', (event) => {
        alone = event.detail?.key ?? null;
        if (alone && !picked) focusOn(alone);
        else say();
    });
    film.addEventListener('mixer-pick', (event) => {
        picked = event.detail?.key ?? null;
        if (picked) focusOn(picked);
    });
    say();
}

// Light or dark: the site's own choice (js/theme-boot.js), shown at once.
const theme = document.querySelector('[data-lnc-theme]');
if (theme && window.stackapellaTheme) {
    const light = () => String(document.documentElement.dataset.theme ?? '').endsWith('-light');
    const label = () => theme.setAttribute('aria-label', light() ? theme.dataset.labelDark : theme.dataset.labelLight);
    theme.hidden = false;
    label();
    theme.addEventListener('click', () => {
        window.stackapellaTheme.choose({ mode: light() ? 'dark' : 'light' });
        label();
    });
    document.addEventListener('stackapella:theme', label);
}

// This page load's one count, by its own kind: a beacon to our own server with no source, no id and no path, held
// until a person has touched the page, and never for a robot (js/automated.js), as the site's pages send theirs.
const visit = automated() ? null : document.querySelector('[data-lnc-visit]');
if (visit) {
    const { kind } = JSON.parse(visit.textContent);
    whenHuman(() => navigator.sendBeacon?.('/api/counts', new Blob([JSON.stringify({ events: [{ kind }], signedIn: false })], { type: 'application/json' })));
}
