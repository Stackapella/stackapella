// The Folk and early music course page's Now Playing (server/resources/views/site/courses/folk-and-early-music.blade.php),
// made here with voices.js and only when pressed, one at a time through demo-player.js: The bright note, after lesson
// folk-2-dorian. Drunken Sailor's third line over a drone on its home note, as plain minor would sing it (the dark
// note on "-en", the minor sixth) or as it really goes (the bright note, Dorian's sixth, a half step higher); and the
// sixth held over the drone (voices.js modeDrone), switched between bright and dark while it sounds.
// The notes are the view's, from the song's template (App\Support\Site\FolkShapeNotes): each [data-fo-note] carries its
// beat (data-at), length (data-len) and pitch as the tune goes (data-midi) and as plain minor would sing it (data-dark),
// at the deck's data-tempo; the home note is data-home. Every voice starts and stops on the audio clock, and each note
// lights as it sounds (demo-player.js `when`, a mark on the audio clock, never a timer). The deck's data-sixth says which
// version is drawn (the other version's shapes hide, its sixth stays as a faint ghost) and data-playing turns the drone
// bar red while it sounds. Under reduced motion the CSS swaps states without moving.

import { audio, hz, modeDrone, voice } from './voices.js';
import { play, rest, sounding, when } from './demo-player.js';

const demo = document.querySelector('[data-fo-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-fo-say]');
const toggle = demo?.querySelector('[data-fo-switch]');

const tempo = () => Number(demo.dataset.tempo) || 88;
const home = () => Number(demo.dataset.home) || 64;
const notes = () => [...demo.querySelectorAll('[data-fo-note]')].map((el) => ({
    el,
    at: Number(el.dataset.at),
    len: Number(el.dataset.len),
    bright: Number(el.dataset.midi),
    dark: Number(el.dataset.dark),
}));

let held = null; // the drone and its sixth, while it holds

function light(i) {
    demo.querySelectorAll('[data-fo-note]').forEach((el) => el.toggleAttribute('data-on', Number(el.dataset.foNote) === i));
}

function sounds(on) {
    demo.toggleAttribute('data-playing', on);
}

// The version drawn: its shapes shown, the switch set to it.
function mark(kind) {
    demo.dataset.sixth = kind;
    toggle.setAttribute('aria-checked', String(kind === 'bright'));
}

function sing(kind) {
    return ({ live }) => {
        const c = audio();
        const beat = 60 / tempo();
        const at = c.currentTime + 0.08;
        const line = notes();
        const end = line.reduce((last, n) => Math.max(last, n.at + n.len), 0);
        const voices = line.map((n) => {
            const v = voice(hz(kind === 'bright' ? n.bright : n.dark), { level: 0.15, at: at + n.at * beat });
            // Released a hair before the next note, so repeated notes are heard as separate syllables.
            v.stop(at + (n.at + n.len) * beat - 0.04 - c.currentTime);
            return v;
        });
        const drone = voice(hz(home() - 12), { level: 0.06, at, timbre: 'oo' });
        drone.stop(at + end * beat + 0.3 - c.currentTime);
        voices.push(drone);
        mark(kind);
        sounds(true);
        say.textContent = words[kind];
        line.forEach((n, i) => when(at + n.at * beat).then(() => live() && light(i)));
        return {
            voices,
            length: at + end * beat + 0.5 - c.currentTime,
            done: () => {
                light(-1);
                sounds(false);
                say.textContent = words.after;
            },
        };
    };
}

// The sixth over the drone, until stopped: the switch moves it between bright and dark as it sounds.
function hold() {
    const bright = toggle.getAttribute('aria-checked') === 'true';
    held = modeDrone(home(), { sixth: bright ? 9 : 8 });
    say.textContent = bright ? words.heldBright : words.heldDark;
    light(notes().findIndex((n) => n.el.hasAttribute('data-fo-sixth')));
    sounds(true);
    return {
        voices: [rest()],
        length: 20,
        done: () => {
            held?.stop();
            held = null;
            light(-1);
            sounds(false);
            say.textContent = words.after;
        },
    };
}

if (demo && toggle && say) {
    for (const button of demo.querySelectorAll('[data-fo-play]')) {
        button.addEventListener('click', () => play(button, sing(button.dataset.foPlay)));
    }
    const holdButton = demo.querySelector('[data-fo-hold]');
    holdButton.addEventListener('click', () => play(holdButton, hold));
    toggle.addEventListener('click', () => {
        const bright = toggle.getAttribute('aria-checked') !== 'true';
        const kind = bright ? 'bright' : 'dark';
        // Switched while the line sings: the other version starts, so what's drawn is what sounds.
        if ([...demo.querySelectorAll('[data-fo-play]')].some(sounding)) {
            play(demo.querySelector(`[data-fo-play="${kind}"]`), sing(kind));
            return;
        }
        mark(kind);
        if (!held) return;
        held.setSixth(bright ? 9 : 8);
        say.textContent = bright ? words.heldBright : words.heldDark;
    });
}
