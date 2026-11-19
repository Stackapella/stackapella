// The Blues singing course page's demo (server/resources/views/site/courses/blues-singing.blade.php), made here with
// voices.js and only when pressed, one at a time through demo-player.js: Lean, then land. Lesson 1's line, the first
// line of Shorty George Blues (Chicago, 1924), at the lesson's 80 beats a minute: as printed, each blue note (a step
// of 3 above the key note) leaning just under the bright note (4) that follows it; or, changed by us and said so, with
// the bright note in each blue one's place. A soft chord on the key note holds under the line, and the band's turn
// (from beat 13, the word "air" done) has the chord with its seventh, as the printing's bar 4 does. Each note and word
// is lit on the audio clock (when()), never a timer. No mic. The notes and the words are the view's (data-*), from
// App\Support\Pages\BluesSingingPage.

import { audio, hz, voice } from './voices.js';
import { play, when } from './demo-player.js';

const demo = document.querySelector('[data-bl-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-bl-say]');

const KEY = 60; // C4: the line runs from the key note to the fifth above it
const BEAT = 60 / 80;
const LINE = 0.15;
const CHORD = 0.035;
const TRIAD = [0, 4, 7];
const SEVENTH = [0, 4, 7, 10];

function light(note, word) {
    demo.querySelectorAll('[data-bl-note]').forEach((el) => el.toggleAttribute('data-on', Number(el.dataset.blNote) === note));
    demo.querySelectorAll('[data-bl-word]').forEach((el) => el.toggleAttribute('data-on', Number(el.dataset.blWord) === word));
}

function turn(on) {
    demo.querySelectorAll('[data-bl-turn], [data-bl-turnword]').forEach((el) => el.toggleAttribute('data-on', on));
}

function sing(version) {
    return ({ live }) => {
        const c = audio();
        const t0 = c.currentTime + 0.08;
        const off = (t) => t - c.currentTime;
        const line = JSON.parse(words.line);
        const wordOf = JSON.parse(words.words);
        const blue = Number(words.blue);
        const bright = Number(words.bright);
        const turnAt = t0 + Number(words.turn) * BEAT;
        const end = t0 + Number(words.beats) * BEAT;
        const voices = [];
        line.forEach(([beat, beats, step], i) => {
            const at = t0 + beat * BEAT;
            const sung = version === 'plain' && step === blue ? bright : step;
            const v = voice(hz(KEY + sung), { level: LINE, at, attack: 0.03, release: 0.06 });
            v.stop(off(at + beats * BEAT - 0.05));
            voices.push(v);
            when(at).then(() => live() && light(i, wordOf[i]));
        });
        for (const [from, to, chord] of [[t0, turnAt, TRIAD], [turnAt, end, SEVENTH]]) {
            for (const step of chord) {
                const v = voice(hz(KEY - 12 + step), { level: CHORD, at: from, attack: 0.04, release: 0.3 });
                v.stop(off(to - 0.02));
                voices.push(v);
            }
        }
        when(turnAt).then(() => {
            if (!live()) return;
            light(-1, -1);
            turn(true);
        });
        demo.dataset.version = version;
        say.textContent = words[version];
        return {
            voices,
            length: off(end + 0.4),
            done: () => {
                light(-1, -1);
                turn(false);
                say.textContent = words.after;
            },
        };
    };
}

if (demo) {
    for (const button of demo.querySelectorAll('[data-bl-play]')) {
        button.addEventListener('click', () => play(button, sing(button.dataset.blPlay)));
    }
}

// A note's number opens the sources it points at; on a wide screen they're open from the start.
const sources = document.querySelector('[data-bl-sources]');
if (sources) {
    if (matchMedia('(min-width: 900px)').matches) sources.open = true;
    document.querySelectorAll('.cp-bl .s-cite a').forEach((a) => a.addEventListener('click', () => { sources.open = true; }));
}
