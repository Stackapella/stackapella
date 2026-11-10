// A guide's panel of the four choir parts' ranges (server/resources/views/site/_guide-ranges.blade.php): each
// button sings its part's usual range on "ah", one voice moving up the white notes from the lowest to the highest
// (and the ends, sharp or not), as the voice type pages' cards do, every note on the audio clock. One sounds at a
// time (demo-player.js): pressing the playing one stops it.

import { hz, line } from './voices.js';
import { play } from './demo-player.js';

const STEP = 0.26; // seconds a note, as the voice range card's scale (reference-cards.js, CARD.rangeStep)
const HOLD = 0.7; // the top note's extra length
const BLACK = [1, 3, 6, 8, 10];

for (const button of document.querySelectorAll('[data-range-play]')) {
    const low = Number(button.dataset.low);
    const high = Number(button.dataset.high);
    const steps = [];
    for (let m = low; m <= high; m++) if (m === low || m === high || !BLACK.includes(m % 12)) steps.push(m);
    button.addEventListener('click', () => play(button, () => {
        const length = steps.length * STEP + HOLD;
        return { voices: [line(steps.map((m, i) => ({ f: hz(m), at: i * STEP })), length, { level: 0.14 })], length };
    }));
}
