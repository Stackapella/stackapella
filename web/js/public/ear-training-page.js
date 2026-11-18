// The Ear training course page (server/resources/views/site/courses/ear-training.blade.php, Hillel's design of
// 2026-11-18): every sound on it, made here with voices.js and only when pressed, one at a time through demo-player.js,
// each note started and stopped on the audio clock (never a timer). The notes are the view's (App\Support\Site\
// EarTrainingLeaps: lesson 1's song openings, Amazing Grace's from its template), so the picture and the sound are one.
// - The leap ladder: a song's button (or its arc) sings its leap, the two notes in turn, and lights it.
// - The song cards: Play sings the opening, each word lit while its note sounds (read from the audio clock each frame).
// - Bright or dark: the major chord broken then held; the minor plays the major chord and drops its third a half step,
//   on the clock, the third's dot dropping with it (at once under reduced motion, by CSS).
// Name that leap is the shared game (js/public/ear-training.js), wired as it loads.

import './ear-training.js';
import { audio, hz, voice } from './voices.js';
import { play, sounding, when } from './demo-player.js';

const DO = 60;

/** Sings `notes` ([at, len, midi] in seconds from the start) and returns the demo's voices and length. */
function sing(notes, { level = 0.15, lead = 0.08 } = {}) {
    const c = audio();
    const start = c.currentTime + lead;
    const voices = notes.map(([at, len, midi]) => {
        const v = voice(hz(midi), { level, at: start + at, attack: 0.03 });
        // Released a hair early, so a repeated note is heard as two.
        v.stop(start + at + len - 0.04 - c.currentTime);
        return v;
    });
    const end = Math.max(...notes.map(([at, len]) => at + len));
    return { start, voices, length: start - c.currentTime + end + 0.15 };
}

// The ladder.
const ladder = document.querySelector('[data-et-ladder]');
for (const button of ladder?.querySelectorAll('[data-et-leap]') ?? []) {
    const key = button.dataset.etLeap;
    button.addEventListener('click', () => play(button, () => {
        const made = sing([[0, 0.6, Number(button.dataset.from)], [0.65, 1.1, Number(button.dataset.to)]]);
        ladder.dataset.on = key;
        return { voices: made.voices, length: made.length, done: () => { if (ladder.dataset.on === key) delete ladder.dataset.on; } };
    }));
    // The arc plays its leap too, for a pointer; the button is the one control for a keyboard or a screen reader.
    ladder.querySelector(`[data-et-arc="${key}"]`)?.addEventListener('click', () => button.click());
}

// The song cards.
for (const card of document.querySelectorAll('[data-et-card]')) {
    const button = card.querySelector('[data-et-play]');
    const words = [...card.querySelectorAll('.cp-et-word')].map((el) => ({ el, at: Number(el.dataset.at), len: Number(el.dataset.len), midi: Number(el.dataset.midi) }));
    if (!button || !words.length) continue;
    button.addEventListener('click', () => play(button, () => {
        const beat = 60 / Number(card.dataset.tempo || 84);
        const made = sing(words.map((w) => [w.at * beat, w.len * beat, w.midi]));
        card.toggleAttribute('data-playing', true);
        const frame = () => {
            if (!sounding(button)) return;
            const t = (audio().currentTime - made.start) / beat;
            for (const w of words) w.el.toggleAttribute('data-on', t >= w.at && t < w.at + w.len);
            requestAnimationFrame(frame);
        };
        requestAnimationFrame(frame);
        return {
            voices: made.voices,
            length: made.length,
            done: () => {
                card.removeAttribute('data-playing');
                words.forEach((w) => w.el.removeAttribute('data-on'));
            },
        };
    }));
}

// Bright or dark.
function major(figure) {
    // Do, mi, so one at a time, then together.
    const notes = [[0, 0.45, DO], [0.5, 0.45, DO + 4], [1.0, 0.45, DO + 7], [1.6, 1.8, DO], [1.6, 1.8, DO + 4], [1.6, 1.8, DO + 7]];
    const made = sing(notes, { level: 0.11 });
    figure.toggleAttribute('data-playing', true);
    return { voices: made.voices, length: made.length, done: () => figure.removeAttribute('data-playing') };
}

function minor(figure, live) {
    // The major chord, then its third drops a half step to me and the chord goes dark.
    const c = audio();
    const start = c.currentTime + 0.08;
    const drop = start + 1.1;
    const voices = [DO, DO + 4, DO + 7].map((midi) => voice(hz(midi), { level: 0.11, at: start, attack: 0.03 }));
    voices[1].tune(-100, 0.22, drop);
    figure.toggleAttribute('data-playing', true);
    figure.toggleAttribute('data-raised', true);
    when(drop).then(() => { if (live()) figure.removeAttribute('data-raised'); });
    return {
        voices,
        length: drop - c.currentTime + 2.0,
        done: () => {
            figure.removeAttribute('data-playing');
            figure.removeAttribute('data-raised');
        },
    };
}

for (const button of document.querySelectorAll('[data-et-hear]')) {
    const figure = button.closest('[data-et-chord]');
    button.addEventListener('click', () => play(button, ({ live }) => (button.dataset.etHear === 'minor' ? minor(figure, live) : major(figure))));
}
