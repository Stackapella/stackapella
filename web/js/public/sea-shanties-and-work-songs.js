// The Sea shanties and work songs course page's card (server/resources/views/site/courses/sea-shanties-and-work-songs
// .blade.php): Haul Away, Joe's chorus from its song template, the shantyman's call and then the crew's answer. Pressed,
// it sings the call alone on the tune's notes, then the answer with the crew's four parts under it, every note started
// and stopped on the audio clock with voices.js (never a timer), one demo at a time through demo-player.js. The playhead
// and the note sounding follow the audio clock's time, read each frame: a call note lights while it sounds, an answer
// note lights as it sounds and stays lit. The notes are the view's (each note's data-at, data-len and data-midi, in beats
// at the card's data-tempo; the crew's in its data-crew), so the picture and the sound are one. Nothing plays on load;
// idle, a silent sweep runs by CSS alone, still under reduced motion.

import { audio, hz, voice } from './voices.js';
import { play, sounding } from './demo-player.js';

const card = document.querySelector('[data-sh]');
const button = card?.querySelector('[data-sh-play]');
const head = card?.querySelector('[data-sh-head]');

// The shantyman out front, the tune of the answer a little under him, and the crew's other parts softer below it.
const CALL = { level: 0.13, timbre: 'ah', attack: 0.03, release: 0.08 };
const TUNE = { level: 0.11, timbre: 'ah', attack: 0.03, release: 0.08 };
const CREW = { level: 0.05, timbre: 'ah', attack: 0.03, release: 0.08 };

const notes = () => [...(card?.querySelectorAll('.cp-sh-note') ?? [])].map((el) => ({
    el,
    answer: el.classList.contains('cp-sh-note-answer'),
    at: Number(el.dataset.at),
    len: Number(el.dataset.len),
    midi: Number(el.dataset.midi),
}));

function crew() {
    try {
        const parsed = JSON.parse(card.dataset.crew || '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        console.warn('The crew\'s notes could not be read', error);
        return [];
    }
}

function sing() {
    const c = audio();
    const beat = 60 / Number(card.dataset.tempo || 90);
    const span = Number(card.dataset.span || 16);
    const start = c.currentTime + 0.12;
    const tune = notes();
    // Each note released a hair before its end, so a repeated note is heard as two.
    const sound = (n, kind) => {
        const v = voice(hz(n.midi), { ...kind, at: start + n.at * beat });
        v.stop(start + (n.at + n.len) * beat - 0.03 - c.currentTime);
        return v;
    };
    const voices = [...tune.map((n) => sound(n, n.answer ? TUNE : CALL)), ...crew().map((n) => sound(n, CREW))];
    card.toggleAttribute('data-playing', true);
    const frame = () => {
        if (!sounding(button)) return;
        const t = (c.currentTime - start) / beat;
        head.style.setProperty('--p', String(Math.min(Math.max(t / span, 0), 1)));
        for (const n of tune) {
            n.el.toggleAttribute('data-on', t >= n.at && t < n.at + n.len);
            if (n.answer && t >= n.at) n.el.toggleAttribute('data-sung', true);
        }
        requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
    return {
        voices,
        length: start - c.currentTime + span * beat + 0.3,
        done: () => {
            card.removeAttribute('data-playing');
            head.style.removeProperty('--p');
            for (const n of tune) {
                n.el.removeAttribute('data-on');
                n.el.removeAttribute('data-sung');
            }
        },
    };
}

if (card && button && head) {
    button.addEventListener('click', () => play(button, sing));
}
