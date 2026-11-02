// The Diction for singers course page's demos (server/resources/views/site/courses/diction.blade.php), made
// here with Web Audio and only when pressed, one at a time through demo-player.js, every sound and every lit
// mark on the audio clock. A vowel made of filters: a sawtooth (a bright, buzzy tone full of harmonics) on
// C3 or C5 through two band-pass filters at a vowel's first two formants (Peterson and Barney's averages
// for men: ee 270 and 2290 Hz, ah 730 and 1090, oo 300 and 870), so the note stays and the vowel changes;
// "Hear three vowels" tours ee, ah and oo on C3 and then on C5, and "Hold this vowel" holds the one picked,
// following the picks while it sounds. The consonant before the beat: a click on each of four beats and on
// each a "tah", a short hiss of filtered noise for the t and a tone for the vowel, with the t on the click
// (A) or 90 ms before it (B). No mic. The words are the view's.

import { audio, hz, voice } from './voices.js';
import { finish, label, play, rest, sounding, when } from './demo-player.js';

const page = document.querySelector('[data-di]');
const fill = (text, values) => Object.entries(values).reduce((t, [k, v]) => t.replaceAll(`:${k}`, v), text ?? '');

// ---- A vowel made of filters ---------------------------------------------------------------------------

const VOWELS = { ee: [270, 2290], ah: [730, 1090], oo: [300, 870] };
const NOTES = { C3: hz(48), C5: hz(72) };
const WIDTH = [90, 140]; // each filter's bandwidth in Hz (Q = frequency / bandwidth)
const F2_GAIN = 0.6; // the second formant a little quieter than the first
const LEVEL = 0.5;
const STEP = 1.5; // seconds each vowel sounds in the tour
const TOUR = [['C3', 'ee'], ['C3', 'ah'], ['C3', 'oo'], ['C5', 'ee'], ['C5', 'ah'], ['C5', 'oo']];
const HOLD = 12; // seconds a held vowel sounds

const deck = page?.querySelector('[data-di-demo]');
const words = deck?.dataset ?? {};
const noteNames = JSON.parse(words.notes ?? '{}');
const say = deck?.querySelector('[data-di-say]');
const vowelName = (v) => deck.querySelector(`[data-di-vowel-pick="${v}"]`)?.textContent.trim() ?? v;
let picked = { vowel: 'ah', note: 'C3' };
let held = null; // the sounding tone's nodes, while a vowel is held

// One band-pass filter's gain at f (a resonance with centre c and quality q), and the two together.
const pass = (f, c, q) => 1 / Math.sqrt(1 + q * q * (f / c - c / f) ** 2);
function shape(f, vowel) {
    const [f1, f2] = VOWELS[vowel];
    return Math.min(1, pass(f, f1, f1 / WIDTH[0]) + F2_GAIN * pass(f, f2, f2 / WIDTH[1]));
}

// The spectrum: 0 to 3000 Hz across 600 units; the note's harmonics as lines as tall as the filters let
// them through, and the filters' shape over them.
const X = (f) => (f / 5).toFixed(1);
const Y = (g) => (190 - g * 172).toFixed(1);
function draw({ vowel, note }) {
    if (!deck) return;
    const f0 = NOTES[note];
    const lines = [];
    for (let f = f0; f <= 3000; f += f0) {
        const line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        line.setAttribute('d', `M${X(f)} 190 V${Y(shape(f, vowel))}`);
        line.setAttribute('class', 'cp-di-harm');
        lines.push(line);
    }
    deck.querySelector('[data-di-harmonics]').replaceChildren(...lines);
    let d = '';
    for (let f = 20; f <= 3000; f += 10) d += `${d ? 'L' : 'M'}${X(f)} ${Y(shape(f, vowel))} `;
    deck.querySelector('[data-di-curve]').setAttribute('d', `${d}L600 190 L4 190 Z`);
    const [f1, f2] = VOWELS[vowel];
    deck.querySelector('[data-di-vowel]').textContent = vowelName(vowel);
    deck.querySelector('[data-di-note]').textContent = noteNames[note] ?? note;
    deck.querySelector('[data-di-formants]').textContent = fill(words.read, { f1: String(f1), f2: String(f2) });
    deck.querySelectorAll('[data-di-vowel-pick]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.diVowelPick === vowel)));
    deck.querySelectorAll('[data-di-note-pick]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.diNotePick === note)));
}

// The tone: a sawtooth into two band-pass filters side by side, then one level. Its handle is a voice's
// (ended, stop(after)), so demo-player can stop it on the clock.
function tone(at, { vowel, note }) {
    const c = audio();
    const osc = c.createOscillator();
    osc.type = 'sawtooth';
    osc.frequency.setValueAtTime(NOTES[note], at);
    const out = c.createGain();
    out.gain.setValueAtTime(0, at);
    out.gain.setTargetAtTime(LEVEL, at, 0.04);
    out.connect(c.destination);
    const filters = [0, 1].map((k) => {
        const filter = c.createBiquadFilter();
        filter.type = 'bandpass';
        const gain = c.createGain();
        gain.gain.value = k ? F2_GAIN : 1;
        osc.connect(filter).connect(gain).connect(out);
        return filter;
    });
    // Glides the note and the filters to a vowel, starting `from` on the audio clock.
    const set = ({ vowel: v, note: n }, from, glide = 0.03) => {
        osc.frequency.setTargetAtTime(NOTES[n], from, glide);
        VOWELS[v].forEach((f, k) => {
            filters[k].frequency.setTargetAtTime(f, from, glide);
            filters[k].Q.setTargetAtTime(f / WIDTH[k], from, glide);
        });
    };
    VOWELS[vowel].forEach((f, k) => {
        filters[k].frequency.setValueAtTime(f, at);
        filters[k].Q.setValueAtTime(f / WIDTH[k], at);
    });
    osc.start(at);
    const ended = new Promise((resolve) => { osc.onended = resolve; });
    ended.then(() => out.disconnect());
    return {
        set,
        ended,
        stop(after = 0) {
            const t = c.currentTime + after;
            out.gain.cancelScheduledValues(t);
            out.gain.setTargetAtTime(0, t, 0.05);
            try {
                osc.stop(t + 0.4);
            } catch (error) {
                console.warn('the tone had stopped', error);
            }
        },
    };
}

function tour({ live }) {
    const c = audio();
    const at = c.currentTime + 0.08;
    const [note, vowel] = TOUR[0];
    const sound = tone(at, { vowel, note });
    TOUR.forEach(([n, v], k) => {
        const from = at + k * STEP;
        if (k) sound.set({ vowel: v, note: n }, from);
        when(from).then(() => {
            if (!live()) return;
            picked = { vowel: v, note: n };
            draw(picked);
            const [f1, f2] = VOWELS[v];
            say.textContent = fill(words.say, { vowel: vowelName(v), note: noteNames[n] ?? n, f1: String(f1), f2: String(f2) });
        });
    });
    return { voices: [sound], length: at - c.currentTime + TOUR.length * STEP, done: () => { say.textContent = words.after; } };
}

function hold() {
    const c = audio();
    const sound = tone(c.currentTime + 0.05, picked);
    held = sound;
    holdSay();
    return { voices: [sound], length: HOLD, done: () => { held = null; say.textContent = words.after; } };
}

function holdSay() {
    say.textContent = fill(words.sayHold, { vowel: vowelName(picked.vowel), note: noteNames[picked.note] ?? picked.note });
}

// ---- The consonant before the beat ----------------------------------------------------------------------

const beatDeck = page?.querySelector('[data-di-beat]');
const beatWords = beatDeck?.dataset ?? {};
const beatSay = beatDeck?.querySelector('[data-di-beat-say]');
const BEAT = 0.6; // seconds a beat (100 a minute)
const T = 0.09; // the t's hiss, in seconds
const VOWEL = 0.32; // the vowel's tone
let noise = null;

function light(root, attr, key) {
    root.querySelectorAll(`[${attr}]`).forEach((el) => el.toggleAttribute('data-on', el.getAttribute(attr) === key));
}

// A bus for the clicks and hisses: a gain that a voice-like handle (ended, stop(after)) fades and frees.
function bus() {
    const c = audio();
    const out = c.createGain();
    out.connect(c.destination);
    const mark = rest();
    mark.ended.then(() => out.disconnect());
    return {
        out,
        handle: {
            ended: mark.ended,
            stop(after = 0) {
                const t = c.currentTime + after;
                out.gain.cancelScheduledValues(t);
                out.gain.setTargetAtTime(0, t, 0.02);
                mark.stop(after + 0.15);
            },
        },
    };
}

function click(c, out, at) {
    const osc = c.createOscillator();
    osc.frequency.value = 1800;
    const gain = c.createGain();
    gain.gain.setValueAtTime(0, at);
    gain.gain.linearRampToValueAtTime(0.22, at + 0.002);
    gain.gain.setTargetAtTime(0, at + 0.004, 0.012);
    osc.connect(gain).connect(out);
    osc.start(at);
    osc.stop(at + 0.1);
}

function hiss(c, out, at) {
    if (!noise) {
        noise = c.createBuffer(1, Math.round(c.sampleRate * 0.2), c.sampleRate);
        const data = noise.getChannelData(0);
        for (let i = 0; i < data.length; i += 1) data[i] = Math.random() * 2 - 1;
    }
    const source = c.createBufferSource();
    source.buffer = noise;
    const high = c.createBiquadFilter();
    high.type = 'highpass';
    high.frequency.value = 3000;
    const gain = c.createGain();
    gain.gain.setValueAtTime(0, at);
    gain.gain.linearRampToValueAtTime(0.3, at + 0.006);
    gain.gain.setValueAtTime(0.3, at + T - 0.03);
    gain.gain.linearRampToValueAtTime(0, at + T);
    source.connect(high).connect(gain).connect(out);
    source.start(at);
    source.stop(at + T + 0.02);
}

function beats(lane, { live }) {
    const c = audio();
    const at = c.currentTime + 0.15;
    const { out, handle } = bus();
    const voices = [handle];
    for (let i = 0; i < 4; i += 1) {
        const beat = at + i * BEAT;
        const t = lane === 'a' ? beat : beat - T;
        click(c, out, beat);
        hiss(c, out, t);
        const sung = voice(hz(57), { level: 0.13, at: t + T, attack: 0.012, release: 0.03 });
        sung.stop(t + T + VOWEL - c.currentTime);
        voices.push(sung);
        when(Math.min(t, beat)).then(() => {
            if (!live()) return;
            light(beatDeck, 'data-di-tah', `${lane}-${i}`);
            light(beatDeck, 'data-di-click', String(i));
            if (i === 0) {
                light(beatDeck, 'data-di-lane', lane);
                beatSay.textContent = lane === 'a' ? beatWords.sayA : beatWords.sayB;
            }
        });
    }
    return {
        voices,
        length: at - c.currentTime + 4 * BEAT + 0.2,
        done: () => {
            light(beatDeck, 'data-di-tah', '');
            light(beatDeck, 'data-di-click', '');
            light(beatDeck, 'data-di-lane', '');
            beatSay.textContent = beatWords.after;
        },
    };
}

// ---- The buttons, the picks and the sources ---------------------------------------------------------------

const DEMOS = { tour, hold, a: (o) => beats('a', o), b: (o) => beats('b', o) };
page?.querySelectorAll('[data-di-play]').forEach((button) => {
    label(button, false);
    button.addEventListener('click', () => play(button, DEMOS[button.dataset.diPlay]));
});

if (deck) {
    const holdButton = deck.querySelector('[data-di-play="hold"]');
    const tourButton = deck.querySelector('[data-di-play="tour"]');
    const pick = (change) => {
        picked = { ...picked, ...change };
        draw(picked);
        if (held && sounding(holdButton)) {
            // The held tone follows the pick, glided on the audio clock.
            held.set(picked, audio().currentTime);
            holdSay();
        } else if (sounding(tourButton)) {
            // A pick during the tour holds that vowel instead.
            play(holdButton, hold);
        }
    };
    deck.querySelectorAll('[data-di-vowel-pick]').forEach((b) => b.addEventListener('click', () => pick({ vowel: b.dataset.diVowelPick })));
    deck.querySelectorAll('[data-di-note-pick]').forEach((b) => b.addEventListener('click', () => pick({ note: b.dataset.diNotePick })));
    draw(picked);
}

// Everything stops when the page is hidden (voices.js stops only its own voices).
document.addEventListener('visibilitychange', () => {
    if (document.hidden) finish();
});

// The sources, and the archive's and the notes' later rows, are folded on a phone and open on a wide
// screen; the sources open too whenever a note's number is followed to them.
const sources = page?.querySelector('[data-di-sources]');
if (page) {
    const wide = matchMedia('(min-width: 900px)');
    const unfold = () => {
        if (wide.matches) page.querySelectorAll('[data-di-wide], [data-di-sources]').forEach((d) => { d.open = true; });
    };
    unfold();
    wide.addEventListener('change', unfold);
    page.addEventListener('click', (event) => {
        if (sources && event.target.closest('.s-cite a')) sources.open = true;
    });
}
