// A song's page (server/resources/views/public/song.blade.php, Hillel's Danny Boy design): "Find your part" picks a
// part, and the page follows it: that card lit and marked as yours, and the hero's Learn names it and opens the app on
// it. The pick lives only while the page is open: nothing is stored or sent. Without the script, Learn opens the tune.
// A long song's words can hide their meanings (Show / Hide the meaning); they show until then, and without the script.

const page = document.querySelector('[data-sg-page]');
const learn = page?.querySelector('[data-learn]');
const label = learn?.querySelector('[data-learn-label]');
const picks = page ? [...page.querySelectorAll('[data-pick]')] : [];

/** Picks `button`'s part: its card lit, the others not, and Learn naming it. */
export function pick(button) {
    for (const other of picks) {
        const mine = other === button;
        other.setAttribute('aria-pressed', String(mine));
        other.closest('[data-part]')?.toggleAttribute('data-picked', mine);
    }
    if (learn && button.dataset.href) learn.href = button.dataset.href;
    if (label && learn?.dataset.label) label.textContent = learn.dataset.label.replace(':part', button.dataset.name ?? '');
}

for (const button of picks) {
    button.hidden = false;
    button.addEventListener('click', () => pick(button));
}

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
