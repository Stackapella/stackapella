// The Humming and lip trills course page's ribbon (server/resources/views/site/courses/humming-and-lip-trills.blade.php):
// the course's four sounds on one ribbon, each drawn from a lesson's own exercise (App\Support\Site\HummingRibbons).
// Pressed, it sings that same ribbon with voices.js, one demo at a time through demo-player.js: each sound a voice of its
// own (a buzzing reed with a fast flutter for the lip trill, a hooty "oo" for the "ng" siren and the hum, an open
// "ah"), its pitch glided along the ribbon's samples and every start and stop on the audio clock, never a timer.
// The line over the ribbon follows the audio clock's time, read each frame. Idle, the line sweeps by CSS alone (never
// under reduced motion), and the pause button holds it still.

import { audio, hz, voice } from './voices.js';
import { play, sounding } from './demo-player.js';

const figure = document.querySelector('[data-hum-ribbon]');
const button = figure?.querySelector('[data-hum-play]');
const still = figure?.querySelector('[data-hum-still]');
const head = figure?.querySelector('[data-hum-ph]');

// Each sound's voice: the trill buzzes (a reed with a flutter), the siren and hum are hooty, the "ah" open.
const SOUND = {
    trill: { timbre: 'reed', level: 0.07, vibrato: { rate: 24, depth: 35 } },
    siren: { timbre: 'oo', level: 0.15 },
    hum: { timbre: 'oo', level: 0.12, vibrato: { rate: 5, depth: 6 } },
    ah: { timbre: 'ah', level: 0.13 },
};

function plan() {
    try {
        const data = JSON.parse(figure.dataset.sing || '{}');
        return { base: Number(data.base) || 55, sounds: Array.isArray(data.sounds) ? data.sounds : [] };
    } catch (error) {
        console.warn('The ribbon has no plan', error);
        return { base: 55, sounds: [] };
    }
}

function sing() {
    const c = audio();
    const { base, sounds } = plan();
    const start = c.currentTime + 0.1;
    const voices = sounds.map((sound) => {
        const kind = SOUND[sound.kind] ?? SOUND.siren;
        const at = start + sound.at;
        const v = voice(hz(base), { ...kind, at, attack: 0.04, release: 0.06 });
        // The pitch along the ribbon: a glide to each sample from the one before, queued on the clock.
        const bend = sound.bend ?? [];
        for (let i = 1; i < bend.length; i++) {
            v.tune(bend[i][1] * 100, bend[i][0] - bend[i - 1][0], at + bend[i - 1][0]);
        }
        v.stop(at + sound.len - c.currentTime);
        return v;
    });
    figure.toggleAttribute('data-playing', true);
    const last = sounds[sounds.length - 1];
    const end = last ? start + last.at + last.len : start;
    const frame = () => {
        if (!sounding(button)) return;
        const now = c.currentTime;
        const t = now - start;
        const sound = sounds.find((s) => t < s.at + s.len) ?? last;
        if (sound) {
            const f = Math.min(Math.max((t - sound.at) / sound.len, 0), 1);
            head.style.setProperty('--p', String(sound.x0 + (sound.x1 - sound.x0) * f));
        }
        if (now < end + 0.2) requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
    return {
        voices,
        length: end - c.currentTime + 0.2,
        done: () => {
            figure.removeAttribute('data-playing');
            head.style.removeProperty('--p');
        },
    };
}

if (figure && button && head) {
    button.addEventListener('click', () => play(button, sing));
}

if (figure && still) {
    still.addEventListener('click', () => {
        const held = still.getAttribute('aria-pressed') !== 'true';
        still.setAttribute('aria-pressed', String(held));
        figure.toggleAttribute('data-still', held);
    });
}
