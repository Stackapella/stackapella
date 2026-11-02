// The Vibrato course page's demo (server/resources/views/site/courses/vibrato.blade.php), made here
// with voices.js and only when pressed, one at a time through demo-player.js: Straight, wave, wobble
// (docs/courses/vibrato-love-letter.md, "Demos for the page"). One held note over a drone: straight,
// then 6 waves a second at 50 cents either side, then 4 a second at 150 (a wobble); or your own wave
// from the two sliders, which reshape the note while it holds (voice.wave, on the audio clock). Widths
// are ± cents, half the full swing, as the app's vibrato measure has them (js/vibrato.js). The deck
// draws two seconds of the wave being sung. The three surfaces' cards play the presets, and the live
// deck in Wade in holds your own wave. The words are the view's.

import { hz, voice } from './voices.js';
import { play } from './demo-player.js';

const demo = document.querySelector('[data-vi-demo]');
const words = demo?.dataset ?? {};
const says = [...(demo?.querySelectorAll('[data-vi-say]') ?? [])];
const trace = demo?.querySelector('[data-vi-trace]');
const rate = demo?.querySelector('[data-vi-rate]');
const width = demo?.querySelector('[data-vi-width]');

const NOTE = 57; // A3, in the middle of most voices
const HOLD = 12; // seconds a note holds unless stopped
const PRESETS = { straight: [0, 0], wave: [6, 50], wobble: [4, 150] };
let held = null; // the voice holding now, so the sliders can reshape it

// Two seconds of the wave: x is time, y the pitch in cents from the note (150 either side fills it).
function draw() {
    const r = Number(rate.value);
    const w = Number(width.value);
    let d = '';
    for (let i = 0; i <= 300; i++) {
        const t = (i / 300) * 2;
        d += `${i ? 'L' : 'M'}${(20 + i * (560 / 300)).toFixed(1)} ${(110 - Math.sin(2 * Math.PI * r * t) * w * 0.6).toFixed(1)}`;
    }
    trace.setAttribute('d', d);
    demo.querySelector('[data-vi-rate-out]').textContent = words.rateOut.replace(':n', String(r));
    demo.querySelector('[data-vi-width-out]').textContent = words.widthOut.replace(':n', String(w));
}

function tell(kind) {
    const text = kind === 'own' ? words.own.replace(':rate', rate.value).replace(':width', width.value) : words[kind];
    says.forEach((say) => { say.textContent = text; });
}

function hold(kind) {
    return () => {
        const [r, w] = kind === 'own' ? [Number(rate.value), Number(width.value)] : PRESETS[kind];
        rate.value = String(r);
        width.value = String(w);
        draw();
        tell(kind);
        held = voice(hz(NOTE), { level: 0.15, vibrato: { rate: Math.max(r, 0.01), depth: w } });
        const drone = voice(hz(NOTE - 12), { level: 0.06 });
        demo.dataset.kind = kind;
        return {
            voices: [held, drone],
            length: HOLD,
            done: () => {
                held = null;
                delete demo.dataset.kind;
                says.forEach((say) => { say.textContent = words.after; });
            },
        };
    };
}

if (demo) {
    for (const button of demo.querySelectorAll('[data-vi-play]')) {
        button.addEventListener('click', () => play(button, hold(button.dataset.viPlay)));
    }
    for (const slider of [rate, width]) {
        slider.addEventListener('input', () => {
            draw();
            if (!held) return;
            held.wave(Number(rate.value), Number(width.value));
            tell('own');
        });
    }
    draw();
}
