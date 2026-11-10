// The First steps to belting course page's demo (server/resources/views/site/courses/belting.blade.php), made here
// with Web Audio and only when pressed, one at a time through demo-player.js, every sound and every lit mark on the
// audio clock. Same note, two colours (docs/courses/belting-love-letter.md, demo 4): one sawtooth on the home note
// (A3, 220 Hz, or A2, 110 Hz), 2 beats at 80 (1.5 s) with a 40 ms attack and a 200 ms release. Veiled: through a
// low-pass filter at 700 Hz. Bright: through a low-pass at 5,000 Hz and a peaking filter at 3,000 Hz (+6 dB, Q 1).
// The two are set to the same level: each one's RMS is worked out from its filters' response to the sawtooth's
// harmonics (what a steady render of it measures), and its gain set so both match. One fixed, moderate level; no
// meter and no volume control. "Hear both colours" plays veiled, a beat's rest, then bright; the two smaller buttons
// play one. The spectrum draws the note's harmonics (in decibels, as the filters and the level set them), over the
// band from 1,000 to 4,000 Hz where hearing is keenest. No mic. The words are the view's.

import { audio } from './voices.js';
import { finish, label, play, when } from './demo-player.js';

const page = document.querySelector('[data-fb]');
const deck = page?.querySelector('[data-fb-demo]');
const words = deck?.dataset ?? {};
const say = deck?.querySelector('[data-fb-say]');

const HOMES = { A3: 220, A2: 110 };
const LENGTH = 1.5; // seconds a colour sounds: 2 beats at 80
const GAP = 0.75; // a beat's rest between the two
const ATTACK = 0.04;
const RELEASE = 0.2;
const TARGET = 0.07; // the RMS both colours are set to: moderate, and the same for both
const RATE = 48000; // the sample rate the filters' response is worked out at (the context's own is near enough)
// A low-pass's Q is in decibels in Web Audio: -3.1 dB is the pack's Q of 0.7, a plain roll-off with no peak.
const LOW_Q = 20 * Math.log10(0.7);
const COLOURS = {
    veiled: [{ type: 'lowpass', f: 700, q: LOW_Q }],
    bright: [{ type: 'lowpass', f: 5000, q: LOW_Q }, { type: 'peaking', f: 3000, q: 1, gain: 6 }],
};

// One biquad's gain at f (the Web Audio spec's formulas), and a colour's filters together.
function biquad({ type, f: f0, q, gain = 0 }, f) {
    const w0 = (2 * Math.PI * f0) / RATE;
    const cos = Math.cos(w0);
    let b;
    let a;
    if (type === 'lowpass') {
        const alpha = Math.sin(w0) / (2 * 10 ** (q / 20));
        b = [(1 - cos) / 2, 1 - cos, (1 - cos) / 2];
        a = [1 + alpha, -2 * cos, 1 - alpha];
    } else {
        const A = 10 ** (gain / 40);
        const alpha = Math.sin(w0) / (2 * q);
        b = [1 + alpha * A, -2 * cos, 1 - alpha * A];
        a = [1 + alpha / A, -2 * cos, 1 - alpha / A];
    }
    const w = (2 * Math.PI * f) / RATE;
    const at = (c) => Math.hypot(c[0] + c[1] * Math.cos(w) + c[2] * Math.cos(2 * w), c[1] * Math.sin(w) + c[2] * Math.sin(2 * w));
    return at(b) / at(a);
}
const response = (colour, f) => COLOURS[colour].reduce((g, filter) => g * biquad(filter, f), 1);

// The sawtooth's harmonics (amplitude 2 / (pi n), up to the Nyquist), each through a colour's filters, at its level.
function harmonics(colour, home) {
    const f0 = HOMES[home];
    const amps = [];
    for (let n = 1; n * f0 < RATE / 2; n += 1) amps.push((2 / (Math.PI * n)) * response(colour, n * f0));
    const rms = Math.sqrt(amps.reduce((sum, a) => sum + (a * a) / 2, 0));
    const gain = TARGET / rms;
    return { gain, amps: amps.map((a) => a * gain) };
}

// ---- The spectrum: 0 to 5,000 Hz across 600 units; each harmonic a line, its height its level in decibels. -----

const SVG = 'http://www.w3.org/2000/svg';
const FLOOR = 54; // decibels from the tallest harmonic to the baseline
let home = 'A3';
let lines = [];

function draw(colour) {
    if (!deck) return;
    const f0 = HOMES[home];
    const both = ['veiled', 'bright'].map((c) => harmonics(c, home).amps);
    const top = Math.max(...both.flat());
    const amps = both[colour === 'veiled' ? 0 : 1];
    const count = Math.floor(5000 / f0);
    if (lines.length !== count) {
        lines = Array.from({ length: count }, () => {
            const line = document.createElementNS(SVG, 'path');
            line.setAttribute('class', 'cp-di-harm');
            return line;
        });
        deck.querySelector('[data-fb-harmonics]').replaceChildren(...lines);
    }
    lines.forEach((line, i) => {
        const db = 20 * Math.log10(amps[i] / top);
        const y = db < -FLOOR ? 190 : 14 + (-db / FLOOR) * 176;
        line.setAttribute('d', `M${(((i + 1) * f0) * 0.12).toFixed(1)} 190 V${y.toFixed(1)}`);
    });
    const show = (attr, key) => deck.querySelectorAll(`[${attr}]`).forEach((el) => { el.hidden = el.getAttribute(attr) !== key; });
    show('data-fb-colour', colour);
    show('data-fb-filter', colour);
    show('data-fb-home-name', home);
    deck.querySelectorAll('[data-fb-home]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.fbHome === home)));
}

function light(colour) {
    deck.querySelectorAll('[data-fb-step]').forEach((el) => el.toggleAttribute('data-on', el.dataset.fbStep === colour));
}

// ---- The sound -------------------------------------------------------------------------------------------------

// One colour of the note from `at` (audio clock), LENGTH long, through its filters at its level. Its handle is a
// voice's (ended, stop(after)), so demo-player stops it on the clock.
function tone(at, colour) {
    const c = audio();
    const osc = c.createOscillator();
    osc.type = 'sawtooth';
    osc.frequency.value = HOMES[home];
    let node = osc;
    COLOURS[colour].forEach(({ type, f, q, gain = 0 }) => {
        const filter = c.createBiquadFilter();
        filter.type = type;
        filter.frequency.value = f;
        filter.Q.value = q;
        filter.gain.value = gain;
        node = node.connect(filter);
    });
    const level = harmonics(colour, home).gain;
    const out = c.createGain();
    out.gain.setValueAtTime(0, at);
    out.gain.linearRampToValueAtTime(level, at + ATTACK);
    out.gain.setValueAtTime(level, at + LENGTH - RELEASE);
    out.gain.linearRampToValueAtTime(0, at + LENGTH);
    node.connect(out).connect(c.destination);
    osc.start(at);
    const ended = new Promise((resolve) => { osc.onended = resolve; });
    ended.then(() => out.disconnect());
    return {
        ended,
        stop(after = 0) {
            const t = c.currentTime + after;
            if (out.gain.cancelAndHoldAtTime) out.gain.cancelAndHoldAtTime(t);
            else out.gain.cancelScheduledValues(t);
            out.gain.setTargetAtTime(0, t, 0.03);
            try {
                osc.stop(t + 0.3);
            } catch (error) {
                console.warn('the tone had stopped', error);
            }
        },
    };
}

// Plays the colours in turn from a moment from now, lighting each as it sounds.
function colours(list, { live }) {
    const c = audio();
    const at = c.currentTime + 0.08;
    const voices = list.map((colour, k) => {
        const from = at + k * (LENGTH + GAP);
        when(from).then(() => {
            if (!live()) return;
            draw(colour);
            light(colour);
            say.textContent = colour === 'veiled' ? words.sayVeiled : words.sayBright;
        });
        return tone(from, colour);
    });
    return {
        voices,
        length: at - c.currentTime + list.length * LENGTH + (list.length - 1) * GAP + 0.1,
        done: () => {
            light('');
            say.textContent = words.after;
        },
    };
}

const DEMOS = { both: (o) => colours(['veiled', 'bright'], o), veiled: (o) => colours(['veiled'], o), bright: (o) => colours(['bright'], o) };

if (deck) {
    deck.querySelectorAll('[data-fb-play]').forEach((button) => {
        label(button, false);
        button.addEventListener('click', () => play(button, DEMOS[button.dataset.fbPlay]));
    });
    // Another voice stops what's playing and redraws on the new note.
    deck.querySelectorAll('[data-fb-home]').forEach((button) => button.addEventListener('click', () => {
        finish();
        home = button.dataset.fbHome;
        draw('veiled');
        say.textContent = words.ready;
    }));
    draw('veiled');
}

// Everything stops when the page is hidden (voices.js stops only its own voices).
document.addEventListener('visibilitychange', () => {
    if (document.hidden) finish();
});

// The archive's and the notes' later rows, and the sources, are folded on a phone and open on a wide screen; the
// sources open too whenever a note's number is followed to them.
const sources = page?.querySelector('[data-fb-sources]');
if (page) {
    const wide = matchMedia('(min-width: 900px)');
    const unfold = () => {
        if (wide.matches) page.querySelectorAll('[data-fb-wide], [data-fb-sources]').forEach((d) => { d.open = true; });
    };
    unfold();
    wide.addEventListener('change', unfold);
    page.addEventListener('click', (event) => {
        if (sources && event.target.closest('.s-cite a')) sources.open = true;
    });
}
