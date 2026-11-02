// Learn a part's demo in the shared slot (the Learn a part feature page; docs/demo-pages-plan.md): a
// song's first line in four parts on one lane (Landing::hero()'s shape, from the frame's JSON). Pick
// your part: the guide sings it over the others, each of its notes lit as it sounds; slow it to 85% or
// 70% at the same pitch (the app's slow practice); or Your turn: your part goes quiet and the others
// carry on, for you to sing it. Sung with voices.js, every note scheduled from one start on the audio
// clock (demo-player.js). The words are the view's (the frame's data-*).

import { audio, hz, voice } from '../voices.js';
import { label, play, when } from '../demo-player.js';

const SVG = 'http://www.w3.org/2000/svg';
const W = 600;
const H = 220;

export async function mount(stage) {
    const root = stage.querySelector('[data-learn]');
    const words = root.dataset;
    const song = JSON.parse(root.querySelector('script[data-song]').textContent);
    const lane = root.querySelector('[data-learn-lane]');
    const say = root.querySelector('[data-learn-say]');
    const playButton = root.querySelector('[data-learn-play]');
    let you = song.you;
    let speed = 1;
    let mode = 'guide';

    const pitches = song.parts.flatMap((p) => p.notes.map((n) => n.pitch));
    const lo = Math.min(...pitches) - 1;
    const hi = Math.max(...pitches) + 1;
    const x = (beat) => 12 + (beat / song.beats) * (W - 24);
    const y = (pitch) => H - 10 - ((pitch - lo) / (hi - lo)) * (H - 20);
    const partName = () => song.parts.find((p) => p.key === you)?.name ?? '';

    // The lane: every part's notes, yours bright and the others quiet.
    function draw() {
        lane.replaceChildren();
        for (const part of song.parts) {
            part.notes.forEach((note, i) => {
                const bar = document.createElementNS(SVG, 'rect');
                bar.setAttribute('x', x(note.beat).toFixed(1));
                bar.setAttribute('y', (y(note.pitch) - 4).toFixed(1));
                bar.setAttribute('width', Math.max(4, x(note.beat + note.length) - x(note.beat) - 2).toFixed(1));
                bar.setAttribute('height', '8');
                bar.setAttribute('rx', '4');
                bar.setAttribute('class', `s-lp-note s-v${part.color}${part.key === you ? ' s-lp-you' : ''}`);
                if (part.key === you) bar.dataset.n = String(i);
                lane.append(bar);
            });
        }
        root.querySelectorAll('[data-learn-part]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.learnPart === you)));
        root.querySelectorAll('[data-learn-speed]').forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.learnSpeed) === speed)));
        root.querySelectorAll('[data-learn-mode]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.learnMode === mode)));
    }

    function light(i) {
        lane.querySelectorAll('.s-lp-you').forEach((bar) => bar.toggleAttribute('data-on', Number(bar.dataset.n) === i));
    }

    // The line once through at this speed: your part louder (the guide) or left out (your turn).
    function sing() {
        play(playButton, ({ live }) => {
            const c = audio();
            const start = c.currentTime + 0.08;
            const beat = 60 / (song.tempo * speed);
            const off = (t) => t - c.currentTime;
            const voices = [];
            for (const part of song.parts) {
                const mine = part.key === you;
                if (mine && mode === 'you') continue;
                for (const note of part.notes) {
                    const v = voice(hz(note.pitch), { level: mine ? 0.15 : 0.05, at: start + note.beat * beat });
                    v.stop(off(start + (note.beat + note.length) * beat - 0.03));
                    voices.push(v);
                }
            }
            const yours = song.parts.find((p) => p.key === you);
            yours?.notes.forEach((note, i) => when(start + note.beat * beat).then(() => live() && light(i)));
            say.textContent = (mode === 'you' ? words.you : words.guide).replace(':part', partName());
            return { voices, length: off(start + song.beats * beat + 0.3), done: () => light(-1) };
        });
    }

    root.addEventListener('click', (event) => {
        const part = event.target.closest('[data-learn-part]');
        const pace = event.target.closest('[data-learn-speed]');
        const turn = event.target.closest('[data-learn-mode]');
        if (part) you = part.dataset.learnPart;
        if (pace) speed = Number(pace.dataset.learnSpeed);
        if (turn) mode = turn.dataset.learnMode;
        if (part || pace || turn) {
            draw();
            say.textContent = (mode === 'you' ? words.you : words.guide).replace(':part', partName());
        }
    });
    playButton.addEventListener('click', sing);

    draw();
    label(playButton, false);
    sing();
}
