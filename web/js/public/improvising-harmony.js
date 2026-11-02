// The Improvising a harmony course page's demo (server/resources/views/site/courses/improvising-harmony.blade.php),
// made here with voices.js and only when pressed, one at a time through demo-player.js: lesson 1's
// A part above, on Michael, Row the Boat Ashore (the course file's notes, course/plus/improvising-harmony,
// imp-1-third) over its chords. Hear the tune; hear it with the part above (the next chord tone up,
// mostly a third); or sing the part yourself, the tune and chords playing while each harmony note
// lights on the audio clock. No mic: nothing is heard or recorded. The words are the view's.

import { audio, hz, voice } from './voices.js';
import { play, when } from './demo-player.js';

const demo = document.querySelector('[data-im-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-im-say]');

const MIDI = 60; // the comfortable note: the tune runs F3 to D4, the part above A3 to F4
const BEAT = 60 / 92;
// [beat, beats, the tune's step, the part's step]
const NOTES = [[0, 1, -7, -3], [1, 1, -3, 0], [2, 1.5, 0, 5], [3.5, 0.5, -3, 0], [4, 1, 0, 5], [5, 1, 2, 5], [6, 2, 0, 5], [8, 1, -3, 0], [9, 1, 0, 5], [10, 4, 2, 5], [14, 2, 0, 5]];
const END = 16 * BEAT;
// F under it all, B flat under "lu-", low and soft.
const CHORDS = [{ beat: 0, until: 10, midis: [41, 45, 48] }, { beat: 10, until: 14, midis: [46, 50, 53] }, { beat: 14, until: 16.4, midis: [41, 45, 48] }];


function light(i, sel) {
    demo.querySelectorAll(sel).forEach((el) => el.toggleAttribute('data-on', Number(el.dataset.imNote) === i));
}

// 'tune', 'both' or 'you' (the tune and chords, the part's notes lit for the visitor to sing).
function run(mode) {
    return ({ live }) => {
        const c = audio();
        const at = c.currentTime + 0.08;
        const off = (t) => t - c.currentTime;
        // Each note its own voice, so a repeated note is sung again rather than held.
        const voices = [];
        const sing = (k, level) => NOTES.forEach(([b, n, ...steps]) => {
            const v = voice(hz(MIDI + steps[k]), { level, at: at + b * BEAT });
            v.stop(off(at + (b + n) * BEAT - 0.05));
            voices.push(v);
        });
        sing(0, mode === 'both' ? 0.13 : 0.15);
        if (mode === 'both') sing(1, 0.12);
        for (const chord of CHORDS) {
            for (const m of chord.midis) {
                const v = voice(hz(m), { level: 0.035, at: at + chord.beat * BEAT });
                v.stop(off(at + chord.until * BEAT));
                voices.push(v);
            }
        }
        demo.dataset.mode = mode;
        say.textContent = words[`say${mode[0].toUpperCase()}${mode.slice(1)}`];
        NOTES.forEach(([b], i) => when(at + b * BEAT).then(() => live() && light(i, '[data-im-note]')));
        return {
            voices,
            length: off(at + END + 0.3),
            done: () => {
                light(-1, '[data-im-note]');
                delete demo.dataset.mode;
                say.textContent = words.after;
            },
        };
    };
}

for (const button of demo?.querySelectorAll('[data-im-play]') ?? []) {
    button.addEventListener('click', () => play(button, run(button.dataset.imPlay)));
}
