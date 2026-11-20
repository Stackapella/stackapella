// "Hear yourself as a choir, coming soon" on a song's page (docs/ai-voices-build-plan.md, phase 2, behind
// own_choir_teaser): a tap shows, at once and in place, what it will be, and counts choir_teaser once for the page, an
// anonymous count with nothing about who (js/public/count.js). A second tap hides the line again and counts nothing.
import { countAnon } from './count.js';

const teaser = document.querySelector('[data-choir-teaser]');
const button = teaser?.querySelector('button');
const line = document.getElementById('choir-soon');
let counted = false;

button?.addEventListener('click', () => {
    const open = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(open));
    if (line) line.hidden = !open;
    if (open && !counted) {
        counted = true;
        countAnon('choir_teaser');
    }
});
