// The Scat singing course page's demo (server/resources/views/site/courses/scat.blade.php), made here with voices.js
// and only when pressed, one at a time through demo-player.js: Straight or swung?, lesson 2's own line (sc-1-swing,
// "Long, short", in course/plus/scat): "doo-bah" on C and D, a bar of it twice round, landing on "doo", at 120 beats a
// minute over a soft C7 chord struck on each bar line. The feel sets only the timing, from the page's data-feels (each
// note's [start, length] in beats, App\Support\Pages\ScatPage, the same numbers that place the tiles): Straight puts
// the "and" half way through the beat; Swung at two thirds (333 ms of a 500 ms beat, 2 to 1); Soloist leans each beat
// 33 ms late and meets the band on the swung "and" (the research pack's demo, after Friberg and Sundström; the 33 ms is
// ours). In the swung feels the short note leans a little louder, as the course's "doo-BAH" teaches. Every sound is on
// the audio clock; each tile lights as its note sounds (when()), and a playhead crosses the bar, drawn each frame from
// the audio clock (hidden under reduced motion, where the lit tile alone shows the place). Changing the feel while it
// plays starts it again in the new feel. No mic. The words are the view's.

import { audio, hz, voice, reducedMotion } from './voices.js';
import { play, finish, sounding, when } from './demo-player.js';

const demo = document.querySelector('[data-sc-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-sc-say]');
const head = demo?.querySelector('[data-sc-head]');
const tiles = [...(demo?.querySelectorAll('[data-sc-note]') ?? [])];

const BAR_BEATS = 4;
const C7 = [48, 52, 55, 58]; // C3 E3 G3 B flat 3
const LINE = 0.15;
const CHORD = 0.035;

const feel = () => demo.dataset.feel;
const playing = (name) => words[`playing${name[0].toUpperCase()}${name.slice(1)}`];

function light(i) {
    tiles.forEach((t) => t.toggleAttribute('data-on', Number(t.dataset.scNote) === i));
}

/** The playhead, each frame while the demo is the one playing: its place in the bar, read from the audio clock. */
function follow(t0, beat, end, live) {
    if (!head || reducedMotion()) return;
    const c = audio();
    const frame = () => {
        if (!live() || c.currentTime >= end) {
            head.removeAttribute('data-on');
            return;
        }
        const beats = (c.currentTime - t0) / beat;
        head.toggleAttribute('data-on', beats >= 0);
        head.style.setProperty('--at', String((Math.max(0, beats) % BAR_BEATS) / BAR_BEATS * 100));
        requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
}

function run({ live }) {
    const c = audio();
    const beat = 60 / Number(words.tempo || 120);
    const t0 = c.currentTime + 0.1;
    const off = (t) => t - c.currentTime;
    const name = feel();
    const notes = JSON.parse(words.feels)[name];
    const midi = JSON.parse(words.midi);
    const [lastStart, lastLength] = notes[notes.length - 1];
    const end = t0 + (lastStart + lastLength) * beat;
    const bars = Math.floor(lastStart / BAR_BEATS) + 1;
    const voices = [];
    for (let bar = 0; bar < bars; bar += 1) {
        const at = t0 + bar * BAR_BEATS * beat;
        for (const m of C7) {
            const v = voice(hz(m), { level: CHORD, at, attack: 0.02, release: 0.25 });
            v.stop(off(bar === bars - 1 ? end : at + BAR_BEATS * beat - 0.02));
            voices.push(v);
        }
    }
    notes.forEach(([start, length], i) => {
        const at = t0 + start * beat;
        const landing = i === notes.length - 1;
        const level = name === 'straight' || landing ? LINE : LINE + (i % 2 ? 0.02 : -0.02);
        const v = voice(hz(midi[i]), { level, at, attack: 0.012, release: 0.03 });
        v.stop(off(landing ? end - 0.05 : at + length * beat * 0.9));
        voices.push(v);
        // The second time round lights the same tiles; the landing falls on the bar's first beat, its first tile.
        when(at).then(() => live() && light(i % tiles.length));
    });
    say.textContent = playing(name);
    demo.setAttribute('data-sc-played', '');
    follow(t0, beat, end, live);
    return {
        voices,
        length: off(end + 0.2),
        done: () => {
            light(-1);
            head?.removeAttribute('data-on');
            say.textContent = words.after;
        },
    };
}

if (demo) {
    const big = demo.querySelector('[data-sc-play]');
    big.addEventListener('click', () => play(big, run));

    for (const chip of demo.querySelectorAll('[data-sc-feel]')) {
        chip.addEventListener('click', () => {
            const again = sounding(big);
            if (again) finish();
            demo.dataset.feel = chip.dataset.scFeel;
            demo.querySelectorAll('[data-sc-feel]').forEach((b) => b.setAttribute('aria-pressed', String(b === chip)));
            if (again) play(big, run);
            else say.textContent = playing(feel());
        });
    }
}

// A note's number opens the sources it points at; on a wide screen they're open from the start.
const sources = document.querySelector('[data-sc-sources]');
if (sources) {
    if (matchMedia('(min-width: 900px)').matches) sources.open = true;
    document.querySelectorAll('.cp-sc .s-cite a').forEach((a) => a.addEventListener('click', () => { sources.open = true; }));
}
