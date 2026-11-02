// The Sight-singing course page's demo (server/resources/views/site/courses/sight-singing.blade.php),
// made here with voices.js and only when pressed, one at a time through demo-player.js: Find do, after
// lesson 1's Do on the stave (course/plus/sight-singing, ss-1-do). A drone holds do under a short
// original line, so fa mi do re mi, drawn on a stave with its names under the notes. Play sings the
// line over the drone, each note lit on the audio clock (when()); tapping a note sings it over the
// drone and says whether it's do. New key moves the line from C to G and back: the stave and the key
// signature change in CSS (data-key), and the tune sounds the same, a fifth higher. The names can be
// hidden. No mic: nothing is heard or recorded. The words are the view's.

import { audio, hz, voice } from './voices.js';
import { play, finish, sounding, when } from './demo-player.js';

const demo = document.querySelector('[data-ss-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-ss-say]');
const notes = [...(demo?.querySelectorAll('[data-ss-note]') ?? [])];

const DO = { c: 60, g: 67 }; // C4 and G4: the same line, a fifth higher in G
const MAJOR = [0, 2, 4, 5, 7, 9, 11];
const BEAT = 0.62; // seconds a note
const LAST = 2; // the last note's beats
const DRONE = 0.07;
const SUNG = 0.15;

const key = () => demo.dataset.key;
const midi = (degree) => DO[key()] + MAJOR[degree];

function light(i) {
    notes.forEach((n, k) => n.toggleAttribute('data-on', k === i));
}

function unmark() {
    notes.forEach((n) => {
        n.removeAttribute('data-right');
        n.removeAttribute('data-wrong');
    });
}

// The drone on do, an octave below the line, from `at` for `length` seconds.
function drone(at, length, c) {
    const v = voice(hz(DO[key()] - 12), { level: DRONE, at });
    v.stop(at + length - c.currentTime);
    return v;
}

// The line over the drone, each note lit as it sounds.
function line({ live }) {
    const c = audio();
    const at = c.currentTime + 0.08;
    const off = (t) => t - c.currentTime;
    const end = (notes.length - 1 + LAST) * BEAT;
    unmark();
    const voices = [drone(at, end + 0.5, c)];
    notes.forEach((n, i) => {
        const v = voice(hz(midi(Number(n.dataset.degree))), { level: SUNG, at: at + 0.4 + i * BEAT });
        v.stop(off(at + 0.4 + (i + (i === notes.length - 1 ? LAST : 1)) * BEAT - 0.05));
        voices.push(v);
        when(at + 0.4 + i * BEAT).then(() => live() && light(i));
    });
    say.textContent = words.playing;
    return {
        voices,
        length: off(at + 0.4 + end + 0.3),
        done: () => {
            light(-1);
            say.textContent = words.after;
        },
    };
}

// One tapped note over the drone.
function tapped(n) {
    return () => {
        const c = audio();
        const at = c.currentTime + 0.05;
        const v = voice(hz(midi(Number(n.dataset.degree))), { level: SUNG, at: at + 0.15 });
        v.stop(at + 1.5 - c.currentTime);
        return { voices: [drone(at, 1.7, c), v], length: 1.8, done: () => light(-1) };
    };
}

if (demo) {
    const big = demo.querySelector('[data-ss-play]');
    big.addEventListener('click', () => play(big, line));

    for (const n of notes) {
        n.addEventListener('click', () => {
            unmark();
            play(n, tapped(n));
            if (sounding(n)) light(notes.indexOf(n));
            const right = n.dataset.degree === '0';
            n.setAttribute(right ? 'data-right' : 'data-wrong', '');
            const name = n.querySelector('.cp-ss-name').textContent.trim();
            say.textContent = right ? words[key() === 'c' ? 'rightC' : 'rightG'] : words.wrong.replace(':name', name);
        });
    }

    demo.querySelector('[data-ss-key]').addEventListener('click', () => {
        finish();
        unmark();
        demo.dataset.key = key() === 'c' ? 'g' : 'c';
        say.textContent = words[key() === 'c' ? 'movedC' : 'movedG'];
    });

    const names = demo.querySelector('[data-ss-names]');
    names.addEventListener('click', () => {
        const on = names.getAttribute('aria-checked') !== 'true';
        names.setAttribute('aria-checked', String(on));
        demo.dataset.names = on ? 'on' : 'off';
    });
}

// A note's number opens the sources it points at; on a wide screen they, and every unit's lessons, are
// open from the start.
const sources = document.querySelector('[data-ss-sources]');
if (sources) {
    if (matchMedia('(min-width: 900px)').matches) {
        sources.open = true;
        document.querySelectorAll('[data-ss-wide]').forEach((d) => { d.open = true; });
    }
    document.querySelectorAll('.cp-ss .s-cite a').forEach((a) => a.addEventListener('click', () => { sources.open = true; }));
}
