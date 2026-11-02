// The Scat singing course page's demo (server/resources/views/site/courses/scat.blade.php), made here
// with voices.js and only when pressed, one at a time through demo-player.js: Straight or swung?,
// after lesson 2's Long, short (course/plus/scat). One scat line, C4 E4 G4 A4 B flat 4 A4 G4 E4, as
// eighth notes at 120 beats a minute, played twice over a soft C7 chord struck on each bar line. The
// feel sets only the timing (the view's data-feels, the same numbers that place the tiles): Straight
// 250 + 250 ms; Swung 333 + 167 ms, 2 to 1; Soloist 300 + 200 ms with each long note 33 ms late and
// the short one on the swung "and" (the research pack's demo 1, after Friberg and Sundström; the
// 33 ms is ours). In the swung feels the short note leans a little louder, as the course's "doo-BAH"
// teaches. Each note is lit as it sounds (when()), everything on the audio clock. Changing the feel
// while it plays starts it again in the new feel. No mic. The words are the view's.

import { audio, hz, voice } from './voices.js';
import { play, finish, sounding, when } from './demo-player.js';

const demo = document.querySelector('[data-sc-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-sc-say]');
const tiles = [...(demo?.querySelectorAll('[data-sc-note]') ?? [])];

const BEAT = 0.5; // seconds: 120 beats a minute
const BAR = 4 * BEAT;
const PASSES = 2;
const C7 = [48, 52, 55, 58]; // C3 E3 G3 B flat 3
const LINE = 0.15;
const CHORD = 0.035;

const feel = () => demo.dataset.feel;
const playing = (name) => words[`playing${name[0].toUpperCase()}${name.slice(1)}`];

function light(i) {
    tiles.forEach((t, k) => t.toggleAttribute('data-on', k === i));
}

function run({ live }) {
    const c = audio();
    const t0 = c.currentTime + 0.1;
    const off = (t) => t - c.currentTime;
    const name = feel();
    const pair = JSON.parse(words.feels)[name];
    const line = JSON.parse(words.line);
    const end = t0 + PASSES * BAR + BEAT;
    const voices = [];
    for (let bar = 0; bar < PASSES; bar += 1) {
        const at = t0 + bar * BAR;
        for (const m of C7) {
            const v = voice(hz(m), { level: CHORD, at, attack: 0.02, release: 0.25 });
            v.stop(off(bar === PASSES - 1 ? end : at + BAR - 0.02));
            voices.push(v);
        }
    }
    for (let pass = 0; pass < PASSES; pass += 1) {
        line.forEach((m, i) => {
            const [start, length] = pair[i % 2];
            const at = t0 + pass * BAR + Math.floor(i / 2) * BEAT + start / 1000;
            const last = pass === PASSES - 1 && i === line.length - 1;
            const level = name === 'straight' ? LINE : LINE + (i % 2 ? 0.02 : -0.02);
            const v = voice(hz(m), { level, at, attack: 0.012, release: 0.03 });
            v.stop(off(last ? end - 0.1 : at + (length / 1000) * 0.9));
            voices.push(v);
            when(at).then(() => live() && light(i));
        });
    }
    say.textContent = playing(name);
    return {
        voices,
        length: off(end + 0.2),
        done: () => {
            light(-1);
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

// A note's number opens the sources it points at; on a wide screen they, and every unit's lessons, are
// open from the start.
const sources = document.querySelector('[data-sc-sources]');
if (sources) {
    if (matchMedia('(min-width: 900px)').matches) {
        sources.open = true;
        document.querySelectorAll('[data-sc-wide]').forEach((d) => { d.open = true; });
    }
    document.querySelectorAll('.cp-sc .s-cite a').forEach((a) => a.addEventListener('click', () => { sources.open = true; }));
}
