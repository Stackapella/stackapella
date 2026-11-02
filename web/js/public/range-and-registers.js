// The Range and registers course page's demos (server/resources/views/site/courses/range-and-registers.blade.php),
// made here with voices.js and only when pressed, one at a time through demo-player.js, every sound and
// every lit step on the audio clock. Up in chest, down in head (Muller's two series): a scale from C3 to
// C5 and back on voices.js's line, chest timbre below the overlap and head above, the overlap a band the
// visitor moves; and one note sung both ways. Ferrein's cord: a note, its fifth and its octave (2:3 and
// 1:2 of the length). The two ends: Osmin's D2 and the Nunc dimittis basses down to B flat 1, each low
// tone built from its harmonics so a phone's speaker can sound it, then Agujari's C major run to C7 as a
// sine, softer as it climbs, with a hertz meter. No mic. The words are the view's.

import { audio, hz, line, voice } from './voices.js';
import { finish, label, play, sounding, when } from './demo-player.js';

const page = document.querySelector('[data-rg]');
const demo = page?.querySelector('[data-rg-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-rg-say]');

// ---- Up in chest, down in head --------------------------------------------------------------------

const SCALE = [0, 2, 4, 5, 7, 9, 11];
const LETTERS = ['C', 'D', 'E', 'F', 'G', 'A', 'B'];
const ROWS = 15; // C3 to C5 on the white keys
const WIDE = 4; // the band: four notes where either voice will do
const STEP = 0.36; // seconds a note
const midiOf = (row) => 48 + 12 * Math.floor(row / 7) + SCALE[row % 7];
const nameOf = (row) => `${LETTERS[row % 7]}${3 + Math.floor(row / 7)}`;
const rowY = (row) => 216 - row * 13.5;
// Up from C3 to C5, then back down: the row each step sings.
const PATH = [...Array(ROWS).keys(), ...[...Array(ROWS - 1).keys()].reverse()];
const fill = (text, values) => Object.entries(values).reduce((t, [k, v]) => t.replaceAll(`:${k}`, v), text ?? '');

let lo = Number(words.lo ?? 7);

/** Whether each step is sung in head voice, the band at rows lo to lo + 3: up, chest until the band's top; down, head until its bottom. */
const heads = () => PATH.map((row, k) => (k < ROWS ? row > lo + WIDE - 1 : row >= lo));

function drawBand() {
    const box = demo.querySelector('[data-rg-band-box]');
    box.setAttribute('y', String(rowY(lo + WIDE - 1) - 8));
    box.setAttribute('height', String(rowY(lo) - rowY(lo + WIDE - 1) + 16));
    const head = heads();
    demo.querySelectorAll('[data-rg-step]').forEach((bar) => bar.toggleAttribute('data-head', head[Number(bar.dataset.rgStep)]));
    const text = fill(words.band, { low: nameOf(lo), high: nameOf(lo + WIDE - 1) });
    demo.querySelector('[data-rg-band-out]').textContent = text;
    demo.querySelector('[data-rg-band]').setAttribute('aria-valuetext', text);
}

function light(k) {
    demo.querySelectorAll('[data-rg-step]').forEach((bar) => bar.toggleAttribute('data-on', Number(bar.dataset.rgStep) === k));
}

function climb({ live }) {
    const c = audio();
    const at = c.currentTime + 0.08;
    const head = heads();
    const notes = PATH.map((row, k) => ({ at: k * STEP, f: hz(midiOf(row)), head: head[k] }));
    const length = (PATH.length - 1) * STEP + 1.1;
    const sung = line(notes, length, { level: 0.15, at });
    PATH.forEach((row, k) => when(at + k * STEP).then(() => {
        if (!live()) return;
        light(k);
        say.textContent = fill(head[k] ? words.sayHead : words.sayChest, { note: nameOf(row) });
    }));
    return {
        voices: [sung],
        length: at - c.currentTime + length,
        done: () => {
            light(-1);
            say.textContent = fill(words.after, { up: nameOf(lo + WIDE - 1), down: nameOf(lo) });
        },
    };
}

// The band's second note, sung in chest, then in head.
function both({ live }) {
    const c = audio();
    const at = c.currentTime + 0.08;
    const row = lo + 1;
    const f = hz(midiOf(row));
    const sung = [line([{ at: 0, f }], 1.3, { level: 0.15, at }), line([{ at: 0, f, head: true }], 1.3, { level: 0.15, at: at + 1.7 })];
    sung[0].stop(at - c.currentTime + 1.3);
    say.textContent = fill(words.sayTwo, { note: nameOf(row) });
    const steps = [...demo.querySelectorAll('[data-rg-step]')].filter((bar) => PATH[Number(bar.dataset.rgStep)] === row);
    const show = (on) => steps.forEach((bar) => bar.toggleAttribute('data-on', on));
    when(at).then(() => live() && show(true));
    return { voices: sung, length: at - c.currentTime + 3.0, done: () => show(false) };
}

if (demo) {
    const slider = demo.querySelector('[data-rg-band]');
    slider.addEventListener('input', () => {
        // The band moved: what's sounding was sung for the old band, so it stops.
        if ([...demo.querySelectorAll('[data-rg-play]')].some(sounding)) finish();
        lo = Number(slider.value);
        drawBand();
    });
    drawBand();
}

// ---- Ferrein's cord: the whole, two thirds (a fifth up) and half (an octave up) --------------------

const cords = page?.querySelector('[data-rg-cords]');

function ferrein({ live }) {
    const c = audio();
    const at = c.currentTime + 0.08;
    const base = hz(48);
    const voices = [1, 3 / 2, 2].map((ratio, i) => {
        const v = voice(base * ratio, { level: 0.14, at: at + i * 1.1 });
        v.stop(at - c.currentTime + i * 1.1 + 0.95);
        when(at + i * 1.1).then(() => live() && cords.querySelectorAll('[data-rg-cord]').forEach((li) => li.toggleAttribute('data-on', Number(li.dataset.rgCord) === i)));
        return v;
    });
    return { voices, length: at - c.currentTime + 3.4, done: () => cords.querySelectorAll('[data-rg-cord]').forEach((li) => li.removeAttribute('data-on')) };
}

// ---- The two ends --------------------------------------------------------------------------------

const ends = page?.querySelector('[data-rg-ends]');
const endWords = ends?.dataset ?? {};
const endSay = ends?.querySelector('[data-rg-ends-say]');
const count = new Intl.NumberFormat(document.documentElement.lang || 'en');
const NOTE = { 34: 'B♭1', 36: 'C2', 38: 'D2', 39: 'E♭2', 41: 'F2', 43: 'G2', 45: 'A2', 46: 'B♭2', 72: 'C5', 76: 'E5', 79: 'G5', 84: 'C6', 88: 'E6', 91: 'G6', 96: 'C7' };
// [MIDI, start, seconds], as the view draws them.
const LOW = [[38, 0, 3.2], [46, 3.7, 0.6], [45, 4.3, 0.6], [43, 4.9, 0.6], [41, 5.5, 0.6], [39, 6.1, 0.6], [38, 6.7, 0.6], [36, 7.3, 0.6], [34, 7.9, 2.2]];
const HIGH = [[72, 0, 0.5], [76, 0.5, 0.5], [79, 1, 0.5], [84, 1.5, 0.5], [88, 2, 0.5], [91, 2.5, 0.5], [96, 3, 2]];
// An organ-like tone with a quiet fundamental and strong upper harmonics: a phone's speaker sounds
// the harmonics, and the ear hears the low note they belong to.
const ORGAN = [0, 0.3, 1, 0.85, 0.65, 0.5, 0.36, 0.24, 0.16, 0.1];

/**
 * One tone stepping through `steps` ([MIDI, start, seconds, level]) from `at` on the audio clock, as a
 * sine or with `partials`, silent between steps that don't touch. A voice's handle: `ended`, `stop(after)`.
 */
function pipe(steps, at, { partials = null } = {}) {
    const c = audio();
    const osc = c.createOscillator();
    if (partials) osc.setPeriodicWave(c.createPeriodicWave(new Float32Array(partials.length), new Float32Array(partials)));
    const gain = c.createGain();
    gain.gain.setValueAtTime(0, at);
    osc.connect(gain).connect(c.destination);
    steps.forEach(([m, start, long, level], i) => {
        const t = at + start;
        osc.frequency.setValueAtTime(hz(m), t);
        gain.gain.setTargetAtTime(level, t, 0.025);
        const next = steps[i + 1];
        if (!next || next[1] > start + long + 0.01) gain.gain.setTargetAtTime(0, t + long - 0.05, 0.04);
    });
    osc.start(at);
    const ended = new Promise((resolve) => { osc.onended = resolve; });
    return {
        ended,
        stop(after = 0) {
            const t = c.currentTime + after;
            gain.gain.cancelScheduledValues(t);
            gain.gain.setTargetAtTime(0, t, 0.05);
            try {
                osc.stop(t + 0.4);
            } catch {
                // already stopped
            }
        },
    };
}

function lightEnd(key) {
    ends.querySelectorAll('[data-rg-end]').forEach((bar) => bar.toggleAttribute('data-on', bar.dataset.rgEnd === key));
}

// The meter: the note sounding and its hertz, or, while nothing sounds, the whole span (the view's words).
const still = ends ? [ends.querySelector('[data-rg-note]').textContent, ends.querySelector('[data-rg-hz]').textContent] : [];
function meter(m) {
    ends.querySelector('[data-rg-note]').textContent = m ? NOTE[m] : still[0];
    ends.querySelector('[data-rg-hz]').textContent = m ? endWords.hz.replace(':n', count.format(Math.round(hz(m)))) : still[1];
}

function end(which) {
    return ({ live }) => {
        const c = audio();
        const at = c.currentTime + 0.08;
        const low = which === 'low';
        const steps = low ? LOW.map((s) => [...s, 0.2]) : HIGH.map((s, i) => [...s, 0.13 - i * 0.012]);
        const tone = pipe(steps, at, { partials: low ? ORGAN : null });
        const last = steps[steps.length - 1];
        steps.forEach(([m, start], i) => when(at + start).then(() => {
            if (!live()) return;
            lightEnd(`${which}-${i}`);
            meter(m);
            if (i === 0) endSay.textContent = low ? endWords.sayHold : endWords.sayClimb;
            if (low && i === 1) endSay.textContent = endWords.sayWalk;
            if (!low && i === steps.length - 1) endSay.textContent = endWords.sayTop;
        }));
        return {
            voices: [tone],
            length: at - c.currentTime + last[1] + last[2] + 0.1,
            done: () => {
                lightEnd('');
                meter(null);
                endSay.textContent = endWords.after;
            },
        };
    };
}

// ---- The buttons, and the sources ------------------------------------------------------------------

const DEMOS = { climb, both, cords: ferrein, low: end('low'), high: end('high') };
page?.querySelectorAll('[data-rg-play]').forEach((button) => {
    label(button, false);
    button.addEventListener('click', () => play(button, DEMOS[button.dataset.rgPlay]));
});

// The sources, and the archive's and the notes' later rows, are folded on a phone and open on a wide
// screen; the sources open too whenever a note's number is followed to them.
const sources = page?.querySelector('[data-rg-sources]');
if (page) {
    const wide = matchMedia('(min-width: 900px)');
    const unfold = () => {
        if (wide.matches) page.querySelectorAll('[data-rg-wide], [data-rg-sources]').forEach((d) => { d.open = true; });
    };
    unfold();
    wide.addEventListener('change', unfold);
    page.addEventListener('click', (event) => {
        if (sources && event.target.closest('.s-cite a')) sources.open = true;
    });
}
