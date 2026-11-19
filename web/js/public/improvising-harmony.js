// The Improvising a harmony course page's player (server/resources/views/site/courses/improvising-harmony.blade.php):
// Michael, Row the Boat Ashore's first line on its solfège rows, the tune from the song's template and lesson 1's part
// above (App\Support\Site\ImprovisingHarmonyPage). Made here with voices.js and only when pressed, one at a time
// through demo-player.js: hear the tune over its chords; hear it with the part above; or sing the part yourself, the
// tune and chords playing while each harmony note lights. Every note starts and stops on the audio clock (never a
// timer); the playhead and the lit notes follow the clock's time, read each frame. The notes are the view's (each
// note's data-at, data-len and data-midi, in beats at the player's data-tempo), so the picture and the sound are one.
// The four lights steps (every note lit, fading, you choose, lights off) restyle the same lane. No mic: nothing is
// heard or recorded. The words are the view's.

import { audio, hz, voice } from './voices.js';
import { play, sounding } from './demo-player.js';

const demo = document.querySelector('[data-im-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-im-say]');
const head = demo?.querySelector('[data-im-ph]');

const notes = (sel) => [...(demo?.querySelectorAll(sel) ?? [])].map((el) => ({
    el,
    at: Number(el.dataset.at),
    len: Number(el.dataset.len),
    midi: Number(el.dataset.midi),
}));

let chords = [];
try {
    chords = JSON.parse(demo?.dataset.chords || '[]');
} catch (error) {
    console.warn('the player has no chords', error);
}

// 'tune', 'both' or 'you' (the tune and chords, the part's notes lit for the visitor to sing).
function run(mode, button) {
    return () => {
        const c = audio();
        const beat = 60 / Number(demo.dataset.tempo || 92);
        const span = Number(demo.dataset.span || 16);
        const start = c.currentTime + 0.1;
        const tune = notes('[data-im-tune]');
        const part = notes('[data-im-part]');
        const lit = [...tune, ...part, ...notes('[data-im-word]')];
        // Each note its own voice, released a hair early, so a repeated note is sung again rather than held.
        const sing = (list, level) => list.map((n) => {
            const v = voice(hz(n.midi), { level, at: start + n.at * beat, attack: 0.03 });
            v.stop(start + (n.at + n.len) * beat - 0.04 - c.currentTime);
            return v;
        });
        const voices = [...sing(tune, mode === 'both' ? 0.13 : 0.15)];
        if (mode === 'both') voices.push(...sing(part, 0.12));
        // The chords, low and soft under it all.
        for (const chord of chords) {
            for (const midi of chord.midis) {
                const v = voice(hz(midi), { level: 0.035, at: start + chord.at * beat, timbre: 'oo' });
                v.stop(start + chord.until * beat - c.currentTime);
                voices.push(v);
            }
        }
        demo.dataset.mode = mode;
        demo.toggleAttribute('data-playing', true);
        say.textContent = words[`say${mode[0].toUpperCase()}${mode.slice(1)}`] ?? '';
        const frame = () => {
            if (!sounding(button)) return;
            const t = (c.currentTime - start) / beat;
            head.style.setProperty('--p', String(Math.min(Math.max(t / span, 0), 1)));
            for (const n of lit) n.el.toggleAttribute('data-on', t >= n.at && t < n.at + n.len);
            requestAnimationFrame(frame);
        };
        requestAnimationFrame(frame);
        return {
            voices,
            length: start - c.currentTime + span * beat + 0.3,
            done: () => {
                demo.removeAttribute('data-playing');
                delete demo.dataset.mode;
                head.style.removeProperty('--p');
                for (const n of lit) n.el.removeAttribute('data-on');
                say.textContent = words.after ?? '';
            },
        };
    };
}

for (const button of demo?.querySelectorAll('[data-im-play]') ?? []) {
    button.addEventListener('click', () => play(button, run(button.dataset.imPlay, button)));
}

// The four lights steps: one pressed at a time, its line shown, the lane restyled (CSS reads data-stage).
const steps = [...(demo?.querySelectorAll('[data-im-stage]') ?? [])];
function stage(n) {
    demo.dataset.stage = String(n);
    for (const b of steps) b.setAttribute('aria-pressed', String(Number(b.dataset.imStage) === n));
    for (const p of demo.querySelectorAll('[data-im-stage-say]')) p.hidden = Number(p.dataset.imStageSay) !== n;
}
for (const b of steps) b.addEventListener('click', () => stage(Number(b.dataset.imStage)));
