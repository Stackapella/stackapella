// A song part page's one script (server/resources/views/site/song-part.blade.php): it reads the part and the player's
// words from the page and mounts the frontend's part player (part-player.js), for a free song only. No inline script,
// so the page keeps the site's script policy.
import { mountPartPlayer } from './part-player.js';

const el = document.querySelector('[data-part-player]');
const data = document.querySelector('[data-part-player-data]');

try {
    const boot = JSON.parse(data?.textContent ?? 'null');
    if (el && boot) mountPartPlayer(el, boot);
} catch (error) {
    console.error('The part player could not start', error);
    el?.remove();
}

// One count when the part is first played on this page load (part_play), beside the page's own (sv_part): the kind
// only, no song, part or path, as site.js sends its count, and only where the footer says counts are on. A press
// of Play is the button's own click while it shows Play, caught here before the player handles it.
let played = false;
el?.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-ui="part-play"]') : null;
    if (played || !button || button.dataset.state !== 'paused' || !document.querySelector('[data-site-visit]')) return;
    played = true;
    navigator.sendBeacon?.('/api/counts', new Blob([JSON.stringify({ events: [{ kind: 'part_play' }], signedIn: false })], { type: 'application/json' }));
}, true);
