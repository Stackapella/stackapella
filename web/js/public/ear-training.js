// The Ear training course page's demo (server/resources/views/site/courses/ear-training.blade.php),
// made here with voices.js and only when pressed, one at a time through demo-player.js: Name that
// leap, after lesson 1's Song starts (course/plus/ear, ear-1-songs). Five rounds: the guide sings a
// leap up of three, four or five scale steps (4, 5 or 7 semitones) from a new note each time, and you
// name it; the songs that start with each leap (Oh When the Saints, Amazing Grace, Twinkle, Twinkle)
// play as hints. No mic. The ladder shows the leap once it's named. The words are the view's.

import { audio, hz, voice } from './voices.js';
import { play, finish } from './demo-player.js';

const demo = document.querySelector('[data-ear-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-ear-say]');
const LEAPS = { three: 4, four: 5, five: 7 };
const STEPS = { three: 3, four: 4, five: 5 }; // the rung the leap lands on, counting the first as 1
// The song starts, as lesson 1 sings them: [beat, beats, semitones from the key's home].
const SONGS = {
    three: [[0, 1, 0], [1, 1, 4], [2, 1, 5], [3, 2, 7]],
    four: [[0, 1, -5], [1, 2, 0], [3, 0.5, 4], [3.5, 0.5, 0], [4, 2, 4]],
    five: [[0, 1, 0], [1, 1, 0], [2, 1, 7], [3, 2, 7]],
};
const ROUNDS = 5;
const random = (n) => crypto.getRandomValues(new Uint32Array(1))[0] % n;

let round = 0;
let right = 0;
let leap = null; // { name, from } for this round
let answered = false;

function notes(list, home, beat) {
    const c = audio();
    const at = c.currentTime + 0.06;
    const voices = list.map(([b, n, step]) => {
        const v = voice(hz(home + step), { level: 0.15, at: at + b * beat });
        v.stop(at + (b + n) * beat - 0.04 - c.currentTime);
        return v;
    });
    const end = Math.max(...list.map(([b, n]) => b + n)) * beat + 0.2;
    return { voices, length: end };
}

function ladder(name) {
    demo.querySelectorAll('[data-ear-rung]').forEach((r) => r.toggleAttribute('data-on', name !== null && (r.dataset.earRung === '1' || Number(r.dataset.earRung) === STEPS[name])));
    const jump = demo.querySelector('[data-ear-jump]');
    jump.toggleAttribute('hidden', name === null);
    if (name) jump.setAttribute('y2', String(Number(jump.dataset.base) - (STEPS[name] - 1) * Number(jump.dataset.gap)));
}

function next() {
    round += 1;
    const names = Object.keys(LEAPS);
    leap = { name: names[random(3)], from: 55 + random(8) };
    answered = false;
    ladder(null);
    demo.querySelectorAll('[data-ear-pick]').forEach((b) => {
        b.disabled = false;
        b.removeAttribute('data-right');
        b.removeAttribute('data-wrong');
    });
    demo.querySelector('[data-ear-round]').textContent = words.round.replace(':n', String(round)).replace(':total', String(ROUNDS));
    demo.querySelector('[data-ear-next]').hidden = true;
}

function start() {
    round = 0;
    right = 0;
    next();
    say.textContent = words.listen;
}

if (demo) {
    const leapButton = demo.querySelector('[data-ear-play]');
    leapButton.addEventListener('click', () => play(leapButton, () => notes([[0, 1, 0], [1, 1.5, LEAPS[leap.name]]], leap.from, 0.7)));
    for (const song of demo.querySelectorAll('[data-ear-song]')) {
        song.addEventListener('click', () => play(song, () => notes(SONGS[song.dataset.earSong], 60, 60 / 84)));
    }
    for (const pick of demo.querySelectorAll('[data-ear-pick]')) {
        pick.addEventListener('click', () => {
            if (answered) return;
            answered = true;
            finish();
            const yes = pick.dataset.earPick === leap.name;
            if (yes) right += 1;
            pick.setAttribute(yes ? 'data-right' : 'data-wrong', '');
            demo.querySelector(`[data-ear-pick="${leap.name}"]`).setAttribute('data-right', '');
            demo.querySelectorAll('[data-ear-pick]').forEach((b) => { b.disabled = true; });
            ladder(leap.name);
            say.textContent = (yes ? words.yes : words.no).replace(':leap', words[leap.name]);
            const more = round < ROUNDS;
            const nextButton = demo.querySelector('[data-ear-next]');
            nextButton.hidden = false;
            nextButton.textContent = more ? words.next : words.again;
            if (!more) say.textContent += ` ${words.score.replace(':n', String(right)).replace(':total', String(ROUNDS))}`;
            nextButton.focus();
        });
    }
    demo.querySelector('[data-ear-next]').addEventListener('click', () => {
        if (round < ROUNDS) {
            next();
            say.textContent = words.listen;
        } else {
            start();
        }
    });
    start();
}
