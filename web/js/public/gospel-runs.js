// The Gospel and soul runs course page's demos (server/resources/views/site/courses/gospel-runs.blade.php),
// made here with voices.js and only when pressed, one at a time through demo-player.js, every sound and
// every lit note on the audio clock. One run, three speeds: a synth voice down the major pentatonic from
// C5 to C4 (C5, A4, G4, E4, D4, C4) at 2, 4 and then 8 notes a second, each note gliding into the next
// over 40 ms. Clean, then smeared: the same run at 8 a second, first with a 20 ms glide into each note,
// then gliding across the whole 125 ms of every note. Bend the blue note: a drone on C3 and C4 and a
// voice that bends up from E flat 4 (300 cents above C4) to where the slider stops it, up to E4 (400).
// No mic. The words are the view's.

import { audio, hz, line, voice } from './voices.js';
import { label, play, sounding, when } from './demo-player.js';

const page = document.querySelector('[data-gr]');
const fill = (text, values) => Object.entries(values).reduce((t, [k, v]) => t.replaceAll(`:${k}`, v), text ?? '');
const count = (digits) => new Intl.NumberFormat(document.documentElement.lang || 'en', { minimumFractionDigits: digits, maximumFractionDigits: digits });

// The run, as the view draws it (MIDI), and its names.
const RUN = [72, 69, 67, 64, 62, 60];
const NAMES = { 72: 'C5', 69: 'A4', 67: 'G4', 64: 'E4', 62: 'D4', 60: 'C4' };
const LEVEL = 0.15;

// ---- One run, three speeds ---------------------------------------------------------------------------

const demo = page?.querySelector('[data-gr-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-gr-say]');
const RATES = [2, 4, 8]; // notes a second
const GLIDE = 0.04; // seconds each note glides in
const LAND = 0.5; // the last note, held
const GAP = 0.8; // seconds between the passes

function light(root, attr, key) {
    root.querySelectorAll(`[${attr}]`).forEach((el) => el.toggleAttribute('data-on', el.getAttribute(attr) === key));
}

function speeds({ live }) {
    const c = audio();
    const at = c.currentTime + 0.08;
    const lines = [];
    let start = 0;
    RATES.forEach((rate, g) => {
        const each = 1 / rate;
        const long = (RUN.length - 1) * each + LAND;
        const notes = RUN.map((m, k) => ({ at: k * each, f: hz(m), ...(k ? { slide: GLIDE } : {}) }));
        const sung = line(notes, long, { level: LEVEL, at: at + start });
        sung.stop(at + start + long - c.currentTime);
        lines.push(sung);
        RUN.forEach((m, k) => when(at + start + k * each).then(() => {
            if (!live()) return;
            light(demo, 'data-gr-step', `${g}-${k}`);
            light(demo, 'data-gr-speed', String(g));
            say.textContent = fill(words.say, { rate: String(rate), note: NAMES[m] });
        }));
        start += long + GAP;
    });
    return {
        voices: lines,
        length: at - c.currentTime + start - GAP + 0.1,
        done: () => {
            light(demo, 'data-gr-step', '');
            light(demo, 'data-gr-speed', '');
            say.textContent = words.after;
        },
    };
}

// ---- Clean, then smeared ------------------------------------------------------------------------------

const smearDeck = page?.querySelector('[data-gr-smear]');
const smearWords = smearDeck?.dataset ?? {};
const smearSay = smearDeck?.querySelector('[data-gr-smear-say]');
const EACH = 1 / 8; // 125 ms a note
const HOLD = 3 * EACH; // the last note, held
const PASSES = [['clean', 0.02], ['smear', EACH]]; // each note's glide, in seconds

function smear({ live }) {
    const c = audio();
    const at = c.currentTime + 0.08;
    const long = (RUN.length - 1) * EACH + HOLD;
    const lines = PASSES.map(([kind, glide], p) => {
        const from = at + p * (long + 0.9);
        const notes = RUN.map((m, k) => ({ at: k * EACH, f: hz(m), ...(k ? { slide: glide } : {}) }));
        const sung = line(notes, long, { level: LEVEL, at: from });
        sung.stop(from + long - c.currentTime);
        RUN.forEach((m, k) => when(from + k * EACH).then(() => {
            if (!live()) return;
            light(smearDeck, 'data-gr-seg', `${kind}-${k}`);
            if (k === 0) {
                light(smearDeck, 'data-gr-pass', kind);
                smearSay.textContent = kind === 'clean' ? smearWords.sayClean : smearWords.saySmear;
            }
        }));
        return sung;
    });
    return {
        voices: lines,
        length: at - c.currentTime + long * 2 + 0.9 + 0.1,
        done: () => {
            light(smearDeck, 'data-gr-seg', '');
            light(smearDeck, 'data-gr-pass', '');
            smearSay.textContent = smearWords.after;
        },
    };
}

// ---- Bend the blue note ---------------------------------------------------------------------------------

const bendDeck = page?.querySelector('[data-gr-bend]');
const bendWords = bendDeck?.dataset ?? {};
const bendSay = bendDeck?.querySelector('[data-gr-bend-say]');
const slider = bendDeck?.querySelector('[data-gr-bend-to]');
const BEND = 1.6; // seconds the first bend takes
const HELD = 10; // seconds the bend is held over the drone
const bendY = (cents) => 170 - (cents - 300) * 1.4;
let bending = null; // the bent voice, once its first bend has arrived

const bendTo = () => Number(slider?.value ?? 400);

function meter() {
    const cents = bendTo();
    const centsText = fill(bendWords.cents, { n: count(0).format(cents) });
    bendDeck.querySelector('[data-gr-cents]').textContent = centsText;
    bendDeck.querySelector('[data-gr-hz]').textContent = fill(bendWords.hz, { n: count(2).format(hz(60, cents)) });
    bendDeck.querySelector('[data-gr-bend-out]').textContent = centsText;
    slider.setAttribute('aria-valuetext', centsText);
    bendDeck.querySelector('[data-gr-bendline]').setAttribute('d', `M20 ${bendY(300)} H90 L250 ${bendY(cents)} H580`);
}

function bend({ live }) {
    const c = audio();
    const at = c.currentTime + 0.08;
    const drone = [voice(hz(48), { level: 0.07, at }), voice(hz(60), { level: 0.055, at })];
    // The voice is C4 detuned by cents: 300 is E flat 4, 400 is E4.
    const top = voice(hz(60), { cents: 300, level: 0.12, at: at + 0.6 });
    top.tune(bendTo(), BEND, at + 0.9);
    const trace = bendDeck.querySelector('[data-gr-bendline]');
    when(at + 0.6).then(() => {
        if (!live()) return;
        trace.setAttribute('data-on', '');
        bendSay.textContent = bendWords.sayBend;
    });
    // Once the first bend arrives, the voice follows the slider (moved meanwhile, it goes there now).
    when(at + 0.9 + BEND).then(() => {
        if (!live()) return;
        bending = top;
        top.tune(bendTo(), 0.25);
        bendSay.textContent = fill(bendWords.sayHold, { n: count(0).format(bendTo()) });
    });
    return {
        voices: [...drone, top],
        length: at - c.currentTime + 0.6 + HELD,
        done: () => {
            bending = null;
            trace.removeAttribute('data-on');
            bendSay.textContent = bendWords.after;
        },
    };
}

if (bendDeck) {
    slider.addEventListener('input', () => {
        meter();
        // While it sounds, the voice follows the slider, glided on the audio clock.
        if (bending && sounding(bendDeck.querySelector('[data-gr-play]'))) {
            bending.tune(bendTo(), 0.25);
            bendSay.textContent = fill(bendWords.sayHold, { n: count(0).format(bendTo()) });
        }
    });
    meter();
}

// ---- The buttons, and the sources ------------------------------------------------------------------------

const DEMOS = { run: speeds, smear, bend };
page?.querySelectorAll('[data-gr-play]').forEach((button) => {
    label(button, false);
    button.addEventListener('click', () => play(button, DEMOS[button.dataset.grPlay]));
});

// The sources, and the archive's and the notes' later rows, are folded on a phone and open on a wide
// screen; the sources open too whenever a note's number is followed to them.
const sources = page?.querySelector('[data-gr-sources]');
if (page) {
    const wide = matchMedia('(min-width: 900px)');
    const unfold = () => {
        if (wide.matches) page.querySelectorAll('[data-gr-wide], [data-gr-sources]').forEach((d) => { d.open = true; });
    };
    unfold();
    wide.addEventListener('change', unfold);
    page.addEventListener('click', (event) => {
        if (sources && event.target.closest('.s-cite a')) sources.open = true;
    });
}
