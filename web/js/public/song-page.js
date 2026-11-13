// A song's page (server/resources/views/public/song.blade.php, Hillel's Danny Boy design; the hero's player, option C of
// the song page review, 2026-11-13): "I sing this" on a part's row in the hero's player (js/public/mixer.js, its
// mixer-pick event) makes it yours, and the page follows: Learn, under the rows and at the page's close, names that part
// and opens the app on it. Until a pick, Learn names the melody's part. Nothing is stored or sent. Without the script,
// Learn opens the melody's part.
// A long song's words can hide their meanings (Show / Hide the meaning); they show until then, and without the script.

const page = document.querySelector('[data-sg-page]');
const parts = JSON.parse(page?.querySelector('[data-learn-parts]')?.textContent ?? '{}');
const learns = page ? [...page.querySelectorAll('[data-learn]')] : [];

/** Points every Learn at `key`'s part: its link and its label. */
export function learnPart(key) {
    const part = parts[key];
    if (!part) return;
    for (const learn of learns) {
        learn.href = part.href;
        const label = learn.querySelector('[data-learn-label]');
        if (label && learn.dataset.label) label.textContent = learn.dataset.label.replace(':part', part.name);
    }
}

page?.addEventListener('mixer-pick', (event) => learnPart(event.detail?.key));

const words = document.querySelector('[data-words]');
const toggle = words?.querySelector('[data-meaning-toggle]');

if (toggle) {
    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        const show = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', String(show));
        toggle.textContent = show ? toggle.dataset.labelHide : toggle.dataset.labelShow;
        words.toggleAttribute('data-meaning-hidden', !show);
    });
}
