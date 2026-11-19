// The Vibrato course page's sounds and waves (server/resources/views/site/courses/vibrato.blade.php), and the same deck
// in the Voice teachers page's demo slot (site/_demo-section.blade.php: its own [data-vi-demo] with a still [data-vi-trace]).
//
// Sounds, made with voices.js only when pressed, one at a time through demo-player.js, each over a quiet drone an octave
// below: the hero's bloom (straight, then the vibrato's width glides up from its second on the audio clock), the three
// surfaces (straight; an even 6 a second at ± 50 cents; a wobble whose speed and width wander, stepped on the audio
// clock), and your own wave from the two sliders or lesson 1's steps, reshaped while it holds (voice.wave). A vibrato is
// the voice's LFO on its pitch. Widths are ± cents, half the full swing, as the app's measure has them; the readout
// names the sliders' wave as the app names a held note (js/vibrato.js kindOf), "natural" inside 5.5 to 7 a second.
//
// Waves: each [data-vi-wave] path carries its spec (shape, box, seconds, rate, width, scale; App\Support\Pages\VibratoPage
// draws its still state on the server with the same sums). While on screen (IntersectionObserver) its phase drifts on
// requestAnimationFrame, for drawing only; under reduced motion each stays still and drawn. The words are the view's.

import { audio, hz, reducedMotion, voice } from './voices.js';
import { play, when } from './demo-player.js';
import { kindOf } from '../vibrato.js';

const demo = document.querySelector('[data-vi-demo]');
const words = demo?.dataset ?? {};
const says = [...(demo?.querySelectorAll('[data-vi-say]') ?? [])];
const trace = demo?.querySelector('[data-vi-trace]'); // the demo slot's still trace
const own = demo?.querySelector('[data-vi-own]'); // the page's moving one
const rate = demo?.querySelector('[data-vi-rate]');
const width = demo?.querySelector('[data-vi-width]');
const verdict = demo?.querySelector('[data-vi-verdict]');
const head = demo?.querySelector('[data-vi-head]');
const status = demo?.querySelector('[data-vi-status]');

const TAU = 2 * Math.PI;
const nums = (text, fallback) => (text ? text.split(',').map(Number) : fallback);
const NATURAL = nums(words.natural, [5.5, 7]);
const WOBBLE_RATE = nums(words.wobbleRate, [3.5, 4.4]);
const WOBBLE_CENTS = nums(words.wobbleCents, [120, 150]);
const [LOW, MID, HIGH] = nums(words.pitches, [50, 57, 64]);
const PITCHES = { low: LOW, mid: MID, high: HIGH };
const BLOOM = { from: Number(words.bloomFrom ?? 0.9), tau: Number(words.bloomTau ?? 0.35), secs: Number(words.bloomSecs ?? 3) };
const WAVE = [6, 50];
const GUIDE_WIDEST = 50; // the widest guide wave the course's steered drills sing (VibratoPage::guides)
const SURFACE = 4; // seconds a surface sounds
const OWN = 30; // seconds your own wave holds unless stopped: time to move the sliders
const STEP = 0.2; // the wobble wanders a step every this many seconds, on the audio clock
const NAMES = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
const PITCH_KEY = 'stackapella.vibrato.pitch';

let held = null; // the voice holding your own wave, so the sliders can reshape it
let bloomAt = null; // when the bloom started, on the audio clock, for its playhead

// ---- The waves -----------------------------------------------------------------------------------------------

const smooth = (x) => {
    const c = Math.max(0, Math.min(1, x));
    return c * c * (3 - 2 * c);
};

/** The wobble at `sec`: its width (± cents) and the waves gone by (VibratoPage::wobble). */
function wobble(sec) {
    const mid = (WOBBLE_RATE[0] + WOBBLE_RATE[1]) / 2;
    const swing = (WOBBLE_RATE[1] - WOBBLE_RATE[0]) / 2;
    const cents = (WOBBLE_CENTS[0] + WOBBLE_CENTS[1]) / 2 + ((WOBBLE_CENTS[1] - WOBBLE_CENTS[0]) / 2) * Math.sin(TAU * 0.45 * sec + 1);
    return [cents, mid * sec - (swing / (TAU * 0.6)) * Math.cos(TAU * 0.6 * sec)];
}

/** The wobble's speed (a second) at `sec`, the rate its waves gone by grow at. */
const wobbleRate = (sec) => (WOBBLE_RATE[0] + WOBBLE_RATE[1]) / 2 + ((WOBBLE_RATE[1] - WOBBLE_RATE[0]) / 2) * Math.sin(TAU * 0.6 * sec);

/** Where a wave is at `sec` seconds, `u` of the way across: [width in ± cents, waves gone by] (VibratoPage::at). */
function at(s, sec, u) {
    switch (s.shape) {
        case 'bloom': return [sec < s.from ? 0 : s.cents * (1 - Math.exp(-(sec - s.from) / s.tau)), s.rate * sec];
        case 'grow': return [s.cents * smooth((u - s.from) / Math.max(0.001, s.to - s.from)), s.rate * sec];
        case 'wobble': return wobble(sec);
        case 'steer': return [50 - 30 * u, s.secs * (2 * u + 1.5 * u * u)];
        case 'three': return u < 1 / 3 ? [0, 0] : u < 2 / 3 ? [WAVE[1], WAVE[0] * sec] : wobble(sec);
        default: return [s.cents, s.rate * sec];
    }
}

const spec = (el) => {
    const d = el.dataset;
    return { shape: d.viWave, h: +d.h, x0: +d.x0, x1: +d.x1, secs: +d.secs, rate: +d.rate, cents: +d.cents, scale: +d.scale, from: +d.from, to: +d.to, tau: +d.tau, speed: +d.speed, n: +d.n };
};

/** Draws a wave with its phase drifted by speed × t. */
function draw(el, t = 0) {
    const s = spec(el);
    const cy = s.h / 2;
    let d = '';
    for (let i = 0; i <= s.n; i++) {
        const u = i / s.n;
        const [cents, cycles] = at(s, u * s.secs, u);
        const y = cy - cents * s.scale * Math.sin(TAU * (cycles - s.speed * t));
        d += `${i ? 'L' : 'M'}${(s.x0 + u * (s.x1 - s.x0)).toFixed(1)} ${y.toFixed(1)}`;
    }
    el.setAttribute('d', d);
}

const waves = [...document.querySelectorAll('[data-vi-wave]')];
const seen = new Set();
let frame = 0;

function tick(now) {
    const t = now / 1000;
    seen.forEach((el) => draw(el, t));
    if (head && bloomAt !== null) {
        const along = (audio().currentTime - bloomAt) / BLOOM.secs;
        head.hidden = along < 0 || along > 1;
        head.style.setProperty('--at', `${(Math.max(0, Math.min(1, along)) * 100).toFixed(2)}%`);
    }
    frame = seen.size ? requestAnimationFrame(tick) : 0;
}

function moving() {
    if (!frame && seen.size) frame = requestAnimationFrame(tick);
}

if (waves.length && !reducedMotion() && 'IntersectionObserver' in window) {
    const watch = new IntersectionObserver((entries) => {
        entries.forEach((entry) => (entry.isIntersecting ? seen.add(entry.target) : seen.delete(entry.target)));
        moving();
    });
    waves.forEach((el) => watch.observe(el));
}

// ---- The voice's pitch: low, mid or high, kept on this device -------------------------------------------------

function pitchKey() {
    try {
        const key = localStorage.getItem(PITCH_KEY);
        return key in PITCHES ? key : 'mid';
    } catch {
        return 'mid';
    }
}

function showPitch(key) {
    demo.querySelectorAll('[data-vi-pitch]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.viPitch === key)));
    const midi = PITCHES[key];
    demo.querySelectorAll('[data-vi-note]').forEach((el) => { el.textContent = NAMES[midi % 12] + (Math.floor(midi / 12) - 1); });
}

const note = () => PITCHES[pitchKey()] ?? MID;

// ---- Your own wave: the sliders, the readout and the steps ----------------------------------------------------

/**
 * What the app would call the sliders' wave, "natural" when it's a vibrato at classical singers' usual speed, and
 * "steered" for one the app calls vibrato outside that speed, or a slow swing no wider than the course's own guide waves
 * (± 50 at most: lesson 1's first swing is 2 a second at ± 50), which the steered drills sing on purpose.
 */
function named(r, w) {
    const kind = kindOf({ hz: r, width: w, evenness: 0 });
    if (kind === 'vibrato') return r >= NATURAL[0] && r <= NATURAL[1] ? 'natural' : 'steered';
    if (kind === 'wobble' && w <= GUIDE_WIDEST) return 'steered';
    return kind;
}

function show() {
    const r = Number(rate.value);
    const w = Number(width.value);
    demo.querySelector('[data-vi-rate-out]').textContent = words.rateOut.replace(':n', String(r));
    demo.querySelector('[data-vi-width-out]').textContent = words.widthOut.replace(':n', String(w));
    if (own) {
        own.dataset.rate = String(r);
        own.dataset.cents = String(w);
        draw(own, reducedMotion() ? 0 : performance.now() / 1000);
    }
    if (trace) {
        let d = '';
        for (let i = 0; i <= 300; i++) {
            const t = (i / 300) * 2;
            d += `${i ? 'L' : 'M'}${(20 + i * (560 / 300)).toFixed(1)} ${(110 - Math.sin(TAU * r * t) * w * 0.6).toFixed(1)}`;
        }
        trace.setAttribute('d', d);
    }
    if (verdict) {
        const state = named(r, w);
        verdict.querySelectorAll('[data-state]').forEach((el) => { el.hidden = el.dataset.state !== state; });
    }
}

function tell(kind) {
    const text = kind === 'own' ? words.own.replace(':rate', rate.value).replace(':width', width.value) : words[kind];
    if (text) says.forEach((say) => { say.textContent = text; });
}

function reshape() {
    show();
    if (!held) return;
    held.wave(Number(rate.value), Number(width.value));
    tell('own');
}

// ---- The sounds ----------------------------------------------------------------------------------------------

function sound(kind) {
    return ({ live }) => {
        const start = audio().currentTime + 0.03;
        const midi = note();
        const drone = voice(hz(midi - 12), { level: 0.06, at: start });
        let sung;
        let length = SURFACE;
        if (kind === 'bloom') {
            // Straight, then the wave's width glides up from BLOOM.from seconds, as the hero draws it.
            sung = voice(hz(midi), { level: 0.15, at: start, vibrato: { rate: WAVE[0], depth: 0 } });
            when(start + BLOOM.from).then(() => live() && sung.wave(WAVE[0], WAVE[1], BLOOM.tau * 3));
            length = BLOOM.secs + 0.4;
            bloomAt = start;
            moving();
        } else if (kind === 'wobble') {
            sung = voice(hz(midi), { level: 0.15, at: start, vibrato: { rate: wobbleRate(0), depth: wobble(0)[0] } });
            for (let k = 1; k * STEP < SURFACE; k++) {
                const sec = k * STEP;
                when(start + sec).then(() => live() && sung.wave(wobbleRate(sec), wobble(sec)[0], STEP));
            }
        } else {
            const [r, w] = kind === 'own' ? [Number(rate.value), Number(width.value)] : kind === 'wave' ? WAVE : [0.01, 0];
            sung = voice(hz(midi), { level: 0.15, at: start, vibrato: { rate: Math.max(r, 0.01), depth: w } });
            if (kind === 'own') {
                held = sung;
                length = OWN;
                if (status) status.textContent = words.playing;
            }
        }
        tell(kind);
        demo.dataset.kind = kind;
        return {
            voices: [sung, drone],
            length,
            done: () => {
                if (kind === 'own') {
                    held = null;
                    if (status) status.textContent = words.idle;
                }
                if (kind === 'bloom') {
                    bloomAt = null;
                    if (head) head.hidden = true;
                }
                delete demo.dataset.kind;
                says.forEach((say) => { say.textContent = words.after; });
            },
        };
    };
}

// The demo slot's buttons set its sliders to what they play, as they always have.
const PRESETS = { straight: [0, 0], wave: WAVE, wobble: [4, 135] };

if (demo) {
    for (const button of demo.querySelectorAll('[data-vi-play]')) {
        button.addEventListener('click', () => {
            const kind = button.dataset.viPlay;
            if (trace && PRESETS[kind] && rate && width) {
                [rate.value, width.value] = PRESETS[kind].map(String);
                show();
            }
            play(button, sound(kind));
        });
    }
    if (rate && width) {
        for (const slider of [rate, width]) slider.addEventListener('input', reshape);
        for (const step of demo.querySelectorAll('[data-vi-step]')) {
            step.addEventListener('click', () => {
                demo.querySelectorAll('[data-vi-step]').forEach((b) => b.setAttribute('aria-pressed', String(b === step)));
                rate.value = step.dataset.rate;
                width.value = step.dataset.cents;
                reshape();
            });
        }
        show();
    }
    for (const button of demo.querySelectorAll('[data-vi-pitch]')) {
        button.addEventListener('click', () => {
            try {
                localStorage.setItem(PITCH_KEY, button.dataset.viPitch);
            } catch (error) {
                console.debug('The pitch was not kept', error);
            }
            showPitch(button.dataset.viPitch);
        });
    }
    showPitch(pitchKey());
}
