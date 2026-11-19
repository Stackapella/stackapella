// The Learn your part for rehearsal course page (server/resources/views/site/courses/learn-your-choir-part.blade.php).
// - Picking a part in the hero's score (soprano to start) lights its line and fades the other three, and turns the
//   headline's "your", the bar numbers, the words and the sample take to it: the page's data-part, each button's
//   aria-pressed, and data-picked on everything marked data-lcp-of that part.
// - Hear it sings the score's lines with voices.js, the picked part out front and the others quieter; Play it sings
//   Amazing Grace's four parts on the dark band, each note lighting as it sounds and staying lit. Every note is started
//   and stopped on the audio clock (never a timer), one demo at a time through demo-player.js; the lit notes follow the
//   audio clock's time, read each frame. The notes are the view's (data-at, data-len and data-midi, in beats at the
//   panel's data-tempo), so the picture and the sound are one.
// - A word under the score shows only when it fits before the next one (never cut or overlapping).

import { audio, hz, voice } from './voices.js';
import { finish, play, sounding } from './demo-player.js';

const page = document.querySelector('[data-lcp]');
const score = page?.querySelector('[data-lcp-score]');
const grace = page?.querySelector('[data-lcp-grace]');

const notesIn = (root) => [...root.querySelectorAll('[data-lane] [data-midi]')].map((el) => ({
    el,
    part: el.closest('[data-lane]').dataset.lane,
    at: Number(el.dataset.at),
    len: Number(el.dataset.len),
    midi: Number(el.dataset.midi),
}));

/** Sings `root`'s notes for `button`'s demo; `sound(part)` is each part's voice. Lights a note while it sounds, and keeps it lit if `keep`. */
function demo(root, button, sound, keep) {
    const c = audio();
    const beat = 60 / Number(root.dataset.tempo || 90);
    const start = c.currentTime + 0.12;
    const notes = notesIn(root);
    const end = Math.max(...notes.map((n) => n.at + n.len));
    const voices = notes.map((n) => {
        const v = voice(hz(n.midi), { ...sound(n.part), at: start + n.at * beat });
        // Released a hair before the next note, so repeated notes are heard as two.
        v.stop(start + (n.at + n.len) * beat - 0.03 - c.currentTime);
        return v;
    });
    root.toggleAttribute('data-playing', true);
    const frame = () => {
        if (!sounding(button)) return;
        const t = (c.currentTime - start) / beat;
        for (const n of notes) {
            n.el.toggleAttribute('data-on', t >= n.at && t < n.at + n.len);
            if (keep && t >= n.at) n.el.toggleAttribute('data-sung', true);
        }
        requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
    return {
        voices,
        length: start - c.currentTime + end * beat + 0.3,
        done: () => {
            root.removeAttribute('data-playing');
            for (const n of notes) {
                n.el.removeAttribute('data-on');
                n.el.removeAttribute('data-sung');
            }
        },
    };
}

/** Turns the page to `key`'s part. */
function pick(key) {
    if (!page || page.dataset.part === key) return;
    page.dataset.part = key;
    for (const b of page.querySelectorAll('[data-lcp-pick]')) b.setAttribute('aria-pressed', String(b.dataset.lcpPick === key));
    for (const el of page.querySelectorAll('[data-lcp-of]')) el.toggleAttribute('data-picked', el.dataset.lcpOf === key);
    // A line already sounding keeps the part it started with: stop it, so what's heard is what's lit.
    const hear = score?.querySelector('[data-lcp-play]');
    if (hear && sounding(hear)) finish();
}

if (page && score) {
    for (const b of page.querySelectorAll('[data-lcp-pick]')) b.addEventListener('click', () => pick(b.dataset.lcpPick));
    const hear = score.querySelector('[data-lcp-play]');
    hear?.addEventListener('click', () => play(hear, () => demo(score, hear, (part) => (part === page.dataset.part
        ? { level: 0.14, timbre: 'ah', attack: 0.03, release: 0.08 }
        : { level: 0.04, timbre: 'oo', attack: 0.05, release: 0.1 }), false)));
}

if (grace) {
    const button = grace.querySelector('[data-lcp-grace-play]');
    button?.addEventListener('click', () => play(button, () => demo(grace, button, (part) => ({
        level: part === 'soprano' ? 0.1 : 0.065,
        timbre: part === 'soprano' ? 'ah' : 'oo',
        attack: 0.04,
        release: 0.1,
    }), true)));
}

// The words under the score: each shows only where it fits before the next word, measured again as the card resizes
// and when the fonts arrive. Every part's row is measured, picked or not (the rest are only hidden).
function fit() {
    for (const row of score.querySelectorAll('.cp-lcp-say')) {
        const words = [...row.children];
        const width = row.clientWidth;
        let edge = -Infinity;
        words.forEach((word, i) => {
            const left = word.offsetLeft;
            // The last word may run a little past the lanes' end, into the card's margin.
            const next = words[i + 1]?.offsetLeft ?? width + 12;
            const fits = left >= edge && left + word.offsetWidth <= next - 3;
            word.toggleAttribute('data-cut', !fits);
            if (fits) edge = left + word.offsetWidth + 3;
        });
    }
}
if (score) {
    new ResizeObserver(fit).observe(score.querySelector('.cp-lcp-words'));
    document.fonts?.ready.then(fit);
}
