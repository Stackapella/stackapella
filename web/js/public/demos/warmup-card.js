// A warm-up card in the shared demo slot (Vocal coaches; docs/demo-pages-plan.md): the app's warm-up
// builder (docs/warmup-builder-plan.md) as one card. Pick a pattern, a speed and how far it climbs,
// and Play sings it as the app's warm-up does: a beat a note, the last held, each round a half step
// higher to the top and back down (warmup.js's warmupRounds), every note on the audio clock and lit as
// it sounds. Open in the builder takes the same warm-up to /warmup#<code>, where a coach sets its sound
// and sends the link; the link carries the whole warm-up and no server sees it. The guide sings on
// "ah" (voices.js); the sound is the builder's. The words are the view's (the frame's JSON).

import { encodeWarmup, warmupRounds } from '../../warmup.js';
import { audio, hz, voice } from '../voices.js';
import { finish, label, play, sounding, when } from '../demo-player.js';

const SVG = 'http://www.w3.org/2000/svg';
const W = 600;
const H = 180;
const HOME = 60; // the demo's comfortable note (C4); in the app it's the singer's own
const HELD = 3; // the last note's beats, as warmup.js holds it
const LOW = -1; // the lane's range in half steps from the first note, the same for every pattern
const HIGH = 13;

const fill = (text, values) => Object.entries(values).reduce((t, [k, v]) => t.replaceAll(`:${k}`, String(v)), text);

export async function mount(stage) {
    const root = stage.querySelector('[data-wu]');
    const data = JSON.parse(root.querySelector('script[data-wu-data]').textContent);
    const { words, patterns, speeds, climbs } = data;
    const lane = root.querySelector('[data-wu-lane]');
    const say = root.querySelector('[data-wu-say]');
    const link = root.querySelector('[data-wu-open]');
    const playButton = root.querySelector('[data-wu-play]');
    let pattern = patterns[0];
    let tempo = speeds[1].tempo;
    let climb = climbs[1].climb;

    const warmup = () => ({ steps: pattern.steps, sound: 'ah', tempo, start: 0, climb, name: pattern.name });
    const beats = () => pattern.steps.length - 1 + HELD;

    function draw() {
        const x = (beat) => 12 + (beat / beats()) * (W - 24);
        const y = (step) => H - 12 - ((step - LOW) / (HIGH - LOW)) * (H - 24);
        lane.replaceChildren();
        pattern.steps.forEach((step, i) => {
            const length = i === pattern.steps.length - 1 ? HELD : 1;
            const bar = document.createElementNS(SVG, 'rect');
            bar.setAttribute('x', x(i).toFixed(1));
            bar.setAttribute('y', (y(step) - 5).toFixed(1));
            bar.setAttribute('width', Math.max(10, x(i + length) - x(i) - 4).toFixed(1));
            bar.setAttribute('height', '10');
            bar.setAttribute('rx', '5');
            bar.setAttribute('class', 's-wu-note');
            bar.dataset.n = String(i);
            lane.append(bar);
        });
        for (const [attr, value] of [['pattern', pattern.key], ['speed', String(tempo)], ['climb', String(climb)]]) {
            root.querySelectorAll(`[data-wu-${attr}]`).forEach((b) => b.setAttribute('aria-pressed', String(b.dataset[`wu${attr[0].toUpperCase()}${attr.slice(1)}`] === value)));
        }
        const code = encodeWarmup(warmup());
        if (code) link.href = `/warmup#${code}`;
    }

    function light(i) {
        lane.querySelectorAll('.s-wu-note').forEach((bar) => bar.toggleAttribute('data-on', Number(bar.dataset.n) === i));
    }

    // Every round once through, a beat's rest between them (in the app, the singer's turn comes there).
    function sing() {
        play(playButton, ({ live }) => {
            const c = audio();
            const beat = 60 / tempo;
            const start = c.currentTime + 0.08;
            const rounds = warmupRounds(warmup());
            const voices = [];
            let at = start;
            rounds.forEach((shift, r) => {
                const roundStart = at;
                pattern.steps.forEach((step, i) => {
                    const length = i === pattern.steps.length - 1 ? HELD : 1;
                    const from = roundStart + i * beat;
                    const v = voice(hz(HOME + step + shift), { level: 0.15, at: from });
                    v.stop(from + length * beat - 0.04 - c.currentTime);
                    voices.push(v);
                    when(from).then(() => {
                        if (!live()) return;
                        light(i);
                        if (i === 0) say.textContent = fill(words.round, { n: r + 1, total: rounds.length });
                    });
                });
                at = roundStart + (beats() + 1) * beat;
            });
            return { voices, length: at - c.currentTime, done: () => { light(-1); say.textContent = words.ready; } };
        });
    }

    root.addEventListener('click', (event) => {
        const p = event.target.closest('[data-wu-pattern]');
        const s = event.target.closest('[data-wu-speed]');
        const k = event.target.closest('[data-wu-climb]');
        if (!p && !s && !k) return;
        if (p) pattern = patterns.find((x) => x.key === p.dataset.wuPattern) ?? pattern;
        if (s) tempo = Number(s.dataset.wuSpeed);
        if (k) climb = Number(k.dataset.wuClimb);
        draw();
        // A change while it sings starts the new warm-up from its first round.
        if (sounding(playButton)) {
            finish();
            sing();
        } else {
            say.textContent = words.ready;
        }
    });
    playButton.addEventListener('click', sing);

    draw();
    label(playButton, false);
    sing();
}
