// A song page's score (server/resources/views/public/_song-score.blade.php; docs/song-sheet-music-plan.md; Hillel's
// design, 2026-11-16): the song engraved as a choral score (js/engrave.js) inside the player's card, from the notes
// the page carries (SongScore::notes: nothing is read from the API, and a withheld song's page carries none). On a wide screen
// the card shows one system at a time and turns with the music; on a phone every system, one under another. The
// player (js/public/mixer.js) lights each note and word as it sounds: they carry their beats, and this module only
// tells it when new ones are on the page. Each staff's letter is the player's own hear-alone button, placed at its
// staff here. "Full score" opens the whole score under the hero. No timers: nothing here keeps time.

import { engrave, glyphDefs } from '../engrave.js';

const CARD_PAGE = 560; // the card's system, in the engraver's units: two or three bars at a size to read
const PHONE_PAGE = 430;
const FULL_PAGE = 1150;
const LETTERS = 54; // room left of the staves for the letters

const card = document.querySelector('[data-score]');
if (card) setUp(card);

function setUp(card) {
    const root = card.closest('[data-player]');
    const view = card.querySelector('[data-score-view]');
    const holder = card.querySelector('[data-score-systems]');
    const song = JSON.parse(card.querySelector('[data-song]').textContent);
    const voices = new Map([...view.querySelectorAll('[data-voice]')].map((el) => [el.dataset.voice, el]));
    const bars = card.querySelector('[data-score-bars]');
    const [prev, next] = [card.querySelector('[data-score-prev]'), card.querySelector('[data-score-next]')];
    const hearing = card.querySelector('[data-score-hearing]');
    const fullButton = card.querySelector('[data-score-full]');
    const fullView = document.querySelector('[data-score-full-view]');
    const fullHolder = fullView?.querySelector('[data-score-full-systems]');
    const wide = window.matchMedia('(min-width: 900px)');
    const colour = new Map(song.parts.map((p) => [p.key, p.color]));
    const short = (key) => `${(song.parts.find((p) => p.key === key)?.name ?? key).slice(0, 1)}.`;
    const whole = (key) => song.parts.find((p) => p.key === key)?.name ?? key;
    const template = JSON.parse(card.querySelector('[data-score-song]')?.textContent ?? 'null');
    let systems = [];
    let shown = 0;
    let following = -1; // the system the music was last in: the card turns only when that changes
    let alone = null;

    /** Each staff in its part's colour (for its lit notes), and quiet while another part is heard alone. */
    function dress(within) {
        for (const staff of within.querySelectorAll('[data-staff]')) {
            staff.classList.add(`s-v${colour.get(staff.dataset.staff) ?? 1}`);
            staff.toggleAttribute('data-quiet', alone !== null && staff.dataset.staff !== alone);
        }
    }

    /** The letters at their staves: by the system shown (a wide card), or the first one (a phone's column). */
    function place() {
        const sys = systems[wide.matches ? shown : 0];
        const svg = holder.children[wide.matches ? shown : 0];
        if (!sys || !svg) return;
        const box = svg.getBoundingClientRect();
        const frame = view.getBoundingClientRect();
        // The drawing sits whole inside its box (an SVG's own "meet"): its scale, and where it starts.
        const scale = Math.min(box.width / sys.width, box.height / sys.height);
        const [left, top] = [box.left - frame.left + (box.width - sys.width * scale) / 2, box.top - frame.top + (box.height - sys.height * scale) / 2];
        for (const staff of sys.staves) {
            const el = voices.get(staff.key);
            if (!el) continue;
            const pad = 13 * scale;
            el.style.top = `${top + staff.top * scale - pad}px`;
            el.style.height = `${(staff.bottom - staff.top) * scale + pad * 2}px`;
            el.style.setProperty('--at', `${left + 4 * scale}px`);
            el.style.setProperty('--room', `${(LETTERS - 18) * scale}px`);
        }
    }

    /** Which bars the card shows, in words, and whether there are more either way. */
    function say() {
        if (!bars) return;
        const sys = systems[shown];
        const pickup = (template?.pickupBeats ?? 0) > 0 ? 0 : 1;
        const count = systems.at(-1).bars[1] + pickup;
        const [from, to] = [Math.max(1, sys.bars[0] + pickup), sys.bars[1] + pickup];
        bars.textContent = (from === to ? bars.dataset.one : bars.dataset.many).replace(':from', from).replace(':to', to).replace(':count', count);
        prev.disabled = shown === 0;
        next.disabled = shown === systems.length - 1;
    }

    function show(index) {
        shown = Math.max(0, Math.min(systems.length - 1, index));
        [...holder.children].forEach((svg, i) => svg.toggleAttribute('hidden', wide.matches && i !== shown)); // an SVG has no .hidden of its own
        say();
        place();
    }

    function draw() {
        if (!template) return;
        const paged = wide.matches;
        const made = engrave(template, { page: paged ? CARD_PAGE : PHONE_PAGE, perSystem: 3, names: LETTERS, nameOf: (key, first) => (paged || first ? '' : short(key)) });
        systems = made.systems;
        holder.innerHTML = systems.map((sys) => sys.svg).join('');
        dress(holder);
        view.toggleAttribute('data-paged', paged);
        view.setAttribute('data-engraved', '');
        show(paged ? Math.min(shown, systems.length - 1) : 0);
        root.dispatchEvent(new CustomEvent('mixer-marks'));
    }

    function drawFull() {
        if (!template || !fullHolder || fullHolder.childElementCount) return;
        fullHolder.innerHTML = engrave(template, { page: FULL_PAGE, perSystem: 6, nameOf: (key, first) => (first ? whole(key) : short(key)) }).systems.map((sys) => sys.svg).join('');
        dress(fullHolder);
        root.dispatchEvent(new CustomEvent('mixer-marks'));
    }

    prev?.addEventListener('click', () => show(shown - 1));
    next?.addEventListener('click', () => show(shown + 1));

    // The card follows the music: when a note lights in another system than the last, it turns there. Back and on still
    // work while it plays; the next system the music enters takes the card again. A stop returns to the start.
    new MutationObserver((changes) => {
        if (!wide.matches) return;
        for (const change of changes) {
            if (!change.target.hasAttribute('data-lit')) continue;
            const index = Number(change.target.closest('[data-system]')?.dataset.system ?? -1);
            if (index >= 0 && index !== following) {
                following = index;
                show(index);
            }
            return;
        }
    }).observe(holder, { subtree: true, attributes: true, attributeFilter: ['data-lit'] });
    const play = root.querySelector('[data-play]');
    new MutationObserver(() => {
        if (play.getAttribute('aria-label') !== play.dataset.labelPlay) return;
        following = -1;
        if (wide.matches && systems.length) show(0);
    }).observe(play, { attributes: true, attributeFilter: ['aria-label'] });

    // A part heard alone: the card says which, and the other staves go quiet.
    root.addEventListener('mixer-solo', (event) => {
        alone = event.detail?.key ?? null;
        if (hearing) hearing.textContent = alone ? hearing.dataset.one.replace(':part', voices.get(alone)?.dataset.name ?? alone) : hearing.dataset.all;
        dress(holder);
        if (fullHolder) dress(fullHolder);
    });

    // The part picked is the one "Parts" downloads.
    const partsFile = card.querySelector('[data-score-file="parts"]');
    const pickFile = (key) => {
        if (!partsFile || !key) return;
        const url = new URL(partsFile.href);
        url.searchParams.set('part', key);
        partsFile.href = url.href;
    };
    pickFile(song.you);
    root.addEventListener('mixer-pick', (event) => pickFile(event.detail?.key));

    // The whole score, under the hero: opened and closed by its buttons, the reader taken to it and back.
    fullButton?.addEventListener('click', () => {
        const open = fullView.hidden;
        fullView.hidden = !open;
        fullButton.setAttribute('aria-expanded', String(open));
        if (!open) return;
        drawFull();
        fullView.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        fullView.querySelector('[data-score-full-close]')?.focus({ preventScroll: true });
    });
    fullView?.querySelector('[data-score-full-close]')?.addEventListener('click', () => {
        fullView.hidden = true;
        fullButton.setAttribute('aria-expanded', 'false');
        fullButton.focus();
    });

    wide.addEventListener('change', draw);
    new ResizeObserver(place).observe(view);

    // After the page has painted: the signs once, then the score.
    const idle = window.requestIdleCallback ?? ((run) => window.requestAnimationFrame(() => run()));
    idle(() => {
        if (!template) return;
        document.body.insertAdjacentHTML('beforeend', glyphDefs());
        draw();
    });
}
