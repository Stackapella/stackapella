// The Folk and early music course page's demo (server/resources/views/site/courses/folk-and-early-music.blade.php),
// made here with voices.js and only when pressed, one at a time through demo-player.js: The bright
// note, after lesson folk-2-dorian (course/plus/folk). Drunken Sailor's third line over its drone,
// as plain minor would sing it (the dark note on "-en", the minor sixth) or as it really goes (the
// bright note, Dorian's sixth, one note higher); and the sixth held over the drone (voices.js
// modeDrone), switched between bright and dark while it sounds. Each word lights on the audio clock.
// The view draws "-en" both ways ([data-fo-sixth]); the one sounding, or switched to, is shown, and the
// switch follows the version played. The words are the view's.

import { audio, hz, modeDrone, voice } from './voices.js';
import { play, rest, sounding, when } from './demo-player.js';

const demo = document.querySelector('[data-fo-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-fo-say]');

const MIDI = 62; // the comfortable note, D4: home is a fifth below, G3
const HOME = MIDI - 7;
const BEAT = 60 / 88;
// [beat, beats, step from the comfortable note]; "-en" (index 7) is the sixth above home.
const LINE = [[0, 1, 0], [1, 0.5, 0], [1.5, 0.5, 0], [2, 1, 0], [3, 0.5, 0], [3.5, 0.5, 0], [4, 1, 0], [5, 1, null], [6, 1, 3], [7, 1.6, 5]];
const SIXTH = { bright: 2, dark: 1 };
const END = 8.6 * BEAT;
let held = null; // the drone and its sixth, while it holds

function light(i) {
    demo.querySelectorAll('[data-fo-note]').forEach((el) => el.toggleAttribute('data-on', Number(el.dataset.foNote) === i));
}

// The sixth that's sounding or chosen: its drawing shown, the other hidden, and the switch set to it.
function mark(kind) {
    demo.dataset.sixth = kind;
    demo.querySelector('[data-fo-switch]').setAttribute('aria-checked', String(kind === 'bright'));
    demo.querySelectorAll('[data-fo-sixth]').forEach((el) => el.toggleAttribute('hidden', el.dataset.foSixth !== kind));
}

function sing(kind) {
    return ({ live }) => {
        const c = audio();
        const at = c.currentTime + 0.08;
        const off = (t) => t - c.currentTime;
        const voices = LINE.map(([b, n, step]) => {
            const v = voice(hz(MIDI + (step ?? SIXTH[kind])), { level: 0.15, at: at + b * BEAT });
            v.stop(off(at + (b + n) * BEAT - 0.04));
            return v;
        });
        const drone = voice(hz(HOME - 12), { level: 0.06, at });
        drone.stop(off(at + END + 0.2));
        voices.push(drone);
        mark(kind);
        say.textContent = words[kind];
        LINE.forEach(([b], i) => when(at + b * BEAT).then(() => live() && light(i)));
        return {
            voices,
            length: off(at + END + 0.4),
            done: () => {
                light(-1);
                say.textContent = words.after;
            },
        };
    };
}

// The sixth over the drone, until stopped: the switch moves it between bright and dark as it sounds.
function hold() {
    const kind = demo.querySelector('[data-fo-switch]').getAttribute('aria-checked') === 'true' ? 'bright' : 'dark';
    held = modeDrone(HOME, { sixth: kind === 'bright' ? 9 : 8 });
    say.textContent = kind === 'bright' ? words.heldBright : words.heldDark;
    light(7);
    return {
        voices: [rest()],
        length: 20,
        done: () => {
            held?.stop();
            held = null;
            light(-1);
            say.textContent = words.after;
        },
    };
}

if (demo) {
    for (const button of demo.querySelectorAll('[data-fo-play]')) {
        button.addEventListener('click', () => play(button, sing(button.dataset.foPlay)));
    }
    const holdButton = demo.querySelector('[data-fo-hold]');
    holdButton.addEventListener('click', () => play(holdButton, hold));
    const toggle = demo.querySelector('[data-fo-switch]');
    toggle.addEventListener('click', () => {
        const bright = toggle.getAttribute('aria-checked') !== 'true';
        const kind = bright ? 'bright' : 'dark';
        // Switched while the line sings: the other version starts, so what's drawn is what sounds.
        if ([...demo.querySelectorAll('[data-fo-play]')].some(sounding)) {
            const other = demo.querySelector(`[data-fo-play="${kind}"]`);
            play(other, sing(kind));
            return;
        }
        mark(kind);
        if (!held) return;
        held.setSixth(bright ? 9 : 8);
        say.textContent = bright ? words.heldBright : words.heldDark;
    });
}
