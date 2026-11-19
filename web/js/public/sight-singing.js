// The Sight-singing course page's sounds (server/resources/views/site/courses/sight-singing.blade.php, Hillel's design of
// 2026-11-18), made here with voices.js and only when pressed, one at a time through demo-player.js, each note started
// and stopped on the audio clock (never a timer).
// - The sol-fa ladder: a rung sings its note (do is middle C) and the dot moves to it.
// - Find do: the course's own line (lesson 4, A line, read cold; the view reads it from the course file) on a stave, a
//   drone holding do an octave below it. Play sings the line over the drone at the lesson's tempo, each note lit as it
//   sounds (when(), on the clock). Tapping a note sings it over the drone and says whether it's do. New key moves the
//   line C, D, F, G and back (the stave's notes and key signature change in CSS, data-key); the names can be hidden. No
//   mic: nothing is heard or recorded.
// - Hear do, in the close: the drone, then do over it.
// The words are the view's.

import { audio, hz, voice } from './voices.js';
import { finish, play, sounding, when } from './demo-player.js';

const KEYS = ['c', 'd', 'f', 'g'];
const DO = { c: 60, d: 62, f: 65, g: 67 };
const DRONE = 0.07;
const SUNG = 0.15;

// The ladder.
const ladder = document.querySelector('[data-ss-ladder]');
for (const rung of ladder?.querySelectorAll('[data-ss-rung]') ?? []) {
    rung.addEventListener('click', () => play(rung, () => {
        const c = audio();
        const semis = Number(rung.dataset.ssRung);
        const v = voice(hz(60 + semis), { level: SUNG, at: c.currentTime + 0.03 });
        ladder.dataset.rung = String(semis);
        ladder.style.setProperty('--dot', String((12 - semis) / 12));
        return { voices: [v], length: 0.9 };
    }));
}

// Find do.
const demo = document.querySelector('[data-ss-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-ss-say]');
const notes = [...(demo?.querySelectorAll('[data-ss-note]') ?? [])];
const cap = (text) => text.charAt(0).toUpperCase() + text.slice(1);

const key = () => demo.dataset.key;
const midi = (n) => DO[key()] + Number(n.dataset.semis);

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

// The line over the drone, a beat a note at the lesson's tempo, the last held for two, each lit as it sounds.
function line({ live }) {
    const c = audio();
    const beat = 60 / Number(demo.dataset.tempo || 66);
    const at = c.currentTime + 0.08;
    const from = at + 0.6;
    const end = from + (notes.length + 1) * beat;
    unmark();
    const voices = [drone(at, end - at + 0.3, c)];
    notes.forEach((n, i) => {
        const start = from + i * beat;
        const v = voice(hz(midi(n)), { level: SUNG, at: start });
        v.stop(start + (i === notes.length - 1 ? 2 : 1) * beat - 0.05 - c.currentTime);
        voices.push(v);
        when(start).then(() => live() && light(i));
    });
    say.textContent = words.playing;
    return {
        voices,
        length: end - c.currentTime + 0.4,
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
        const at = c.currentTime + 0.04;
        const v = voice(hz(midi(n)), { level: SUNG, at: at + 0.12 });
        v.stop(at + 1.4 - c.currentTime);
        return { voices: [drone(at, 1.6, c), v], length: 1.7, done: () => light(-1) };
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
            say.textContent = right ? words[`right${cap(key())}`] : words.wrong.replace(':name', name);
        });
    }

    demo.querySelector('[data-ss-key]').addEventListener('click', () => {
        finish();
        unmark();
        demo.dataset.key = KEYS[(KEYS.indexOf(key()) + 1) % KEYS.length];
        say.textContent = words[`moved${cap(key())}`];
    });

    const names = demo.querySelector('[data-ss-names]');
    names.addEventListener('click', () => {
        const on = names.getAttribute('aria-checked') !== 'true';
        names.setAttribute('aria-checked', String(on));
        demo.dataset.names = on ? 'on' : 'off';
    });
}

// Hear do: the drone on do, and do sung over it.
const hearDo = document.querySelector('[data-ss-hear-do]');
hearDo?.addEventListener('click', () => play(hearDo, () => {
    const c = audio();
    const at = c.currentTime + 0.04;
    const low = voice(hz(48), { level: 0.1, at });
    low.stop(2.3);
    const high = voice(hz(60), { level: SUNG, at: at + 0.3 });
    high.stop(2.2);
    return { voices: [low, high], length: 2.4 };
}));

// A note's number opens the sources it points at; on a wide screen they're open from the start.
const sources = document.querySelector('[data-ss-sources]');
if (sources) {
    if (matchMedia('(min-width: 900px)').matches) sources.open = true;
    document.querySelectorAll('.cp-ss .s-cite a').forEach((a) => a.addEventListener('click', () => { sources.open = true; }));
}
