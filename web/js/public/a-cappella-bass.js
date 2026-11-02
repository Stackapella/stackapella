// The A cappella bass course page's demos (server/resources/views/site/courses/a-cappella-bass.blade.php),
// made here with Web Audio on voices.js's clock and only when pressed, one at a time through
// demo-player.js. Same notes, new root, after lesson 3's Bright or dark? (acb-1-hear): C4 and E4 held
// in a soft organ tone, alone, then over a bass C3, then A2, each chord lit as it sounds (when()); each
// chord is also a button that plays it alone. The bass is an organ tone with a quiet fundamental and
// strong harmonics 2 to 9, so a phone's speaker, which can't sound a low fundamental, still gives the
// ear its pitch. The note that isn't there: G2 from its first six harmonics as sines (98 to 588 Hz),
// then without the 98 Hz, twice. No mic: nothing is heard or recorded. The words are the view's.

import { audio, hz } from './voices.js';
import { play, when } from './demo-player.js';

// A bass organ: harmonic 1 quiet, 2 to 9 strong and falling away (the Range page's organ).
const LOW = [0, 0.3, 1, 0.85, 0.65, 0.5, 0.36, 0.24, 0.16, 0.1];
// The held top notes: a soft organ, mostly the note itself.
const SOFT = [0, 1, 0.35, 0.16, 0.07];
const TOP = [60, 64]; // C4, E4
const BASS = [null, 48, 45]; // no bass, C3, A2
const TOP_LEVEL = 0.06;
const BASS_LEVEL = 0.15;
// Same notes, new root: [start, seconds] of each chord on the audio clock; the top holds through all three.
const PARTS = [[0, 2.2], [2.4, 2.6], [5.2, 2.6]];
const HELD = 2.6; // a tapped chord's seconds
// The note that isn't there: G2's harmonics 1 to 6, their levels, and the four parts (with, without, twice).
const G2 = hz(43);
const HARMONICS = [0.1, 0.085, 0.07, 0.055, 0.045, 0.035];
const TURNS = [[0, 2, true], [2.3, 2, false], [4.6, 2, true], [6.9, 2, false]];

const waves = new Map();
function shape(c, partials) {
    if (!waves.has(partials)) waves.set(partials, c.createPeriodicWave(new Float32Array(partials.length), new Float32Array(partials)));
    return waves.get(partials);
}

/**
 * One tone through `steps` ([hz, start, seconds, level], from `at` on the audio clock), a sine or with
 * `partials`, silent between steps that don't touch. A voice's handle for demo-player: `ended`, `stop(after)`.
 */
function tone(steps, at, partials = null) {
    const c = audio();
    const osc = c.createOscillator();
    if (partials) osc.setPeriodicWave(shape(c, partials));
    const gain = c.createGain();
    gain.gain.setValueAtTime(0, at);
    osc.connect(gain).connect(c.destination);
    steps.forEach(([f, start, long, level], i) => {
        const t = at + start;
        osc.frequency.setValueAtTime(f, t);
        gain.gain.setTargetAtTime(level, t, 0.03);
        const next = steps[i + 1];
        if (!next || next[1] > start + long + 0.01) gain.gain.setTargetAtTime(0, t + long - 0.08, 0.05);
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
            } catch (error) {
                console.warn('the tone had stopped', error);
            }
        },
    };
}

const demo = document.querySelector('[data-acb-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-acb-say]');
const chords = [...(demo?.querySelectorAll('[data-acb-chord]') ?? [])];

function light(i) {
    chords.forEach((b, k) => b.toggleAttribute('data-on', k === i));
}

function tell(i) {
    light(i);
    say.textContent = words[`say-${i}`];
}

// The top two notes over each bass in turn, each chord lit as it sounds.
function change({ live }) {
    const c = audio();
    const at = c.currentTime + 0.08;
    const [lastStart, lastLong] = PARTS[PARTS.length - 1];
    const end = lastStart + lastLong;
    const voices = TOP.map((m) => tone([[hz(m), 0, end, TOP_LEVEL]], at, SOFT));
    const steps = PARTS.map(([start, long], i) => (BASS[i] === null ? null : [hz(BASS[i]), start, long, BASS_LEVEL])).filter(Boolean);
    voices.push(tone(steps, at, LOW));
    PARTS.forEach(([start], i) => when(at + start).then(() => live() && tell(i)));
    return {
        voices,
        length: at + end + 0.3 - c.currentTime,
        done: () => {
            light(-1);
            say.textContent = words.after;
        },
    };
}

// One chord on its own: the top two notes, and its bass if it has one.
function chord(i) {
    return ({ live }) => {
        const c = audio();
        const at = c.currentTime + 0.05;
        const voices = TOP.map((m) => tone([[hz(m), 0, HELD, TOP_LEVEL]], at, SOFT));
        if (BASS[i] !== null) voices.push(tone([[hz(BASS[i]), 0.1, HELD - 0.1, BASS_LEVEL]], at, LOW));
        when(at).then(() => live() && tell(i));
        return { voices, length: at + HELD + 0.2 - c.currentTime, done: () => light(-1) };
    };
}

if (demo) {
    const big = demo.querySelector('[data-acb-play]');
    big.addEventListener('click', () => play(big, change));
    chords.forEach((b, i) => b.addEventListener('click', () => play(b, chord(i))));
}

// The note that isn't there: the six harmonics, the first only in the "with" parts.
const missing = document.querySelector('[data-acb-missing]');
if (missing) {
    const button = missing.querySelector('[data-acb-missing-play]');
    const fund = missing.querySelector('[data-acb-fund]');
    const parts = missing.querySelectorAll('[data-acb-part]');
    const said = missing.querySelector('[data-acb-missing-say]');
    const mark = (part) => {
        parts.forEach((p) => p.toggleAttribute('data-on', p.dataset.acbPart === part));
        fund.toggleAttribute('data-off', part === 'without');
    };
    button.addEventListener('click', () => play(button, ({ live }) => {
        const c = audio();
        const at = c.currentTime + 0.08;
        const voices = HARMONICS.map((level, k) => {
            const steps = TURNS.filter(([, , withIt]) => k > 0 || withIt).map(([start, long]) => [G2 * (k + 1), start, long, level]);
            return tone(steps, at);
        });
        TURNS.forEach(([start, , withIt]) => when(at + start).then(() => live() && mark(withIt ? 'with' : 'without')));
        said.textContent = '';
        const [lastStart, lastLong] = TURNS[TURNS.length - 1];
        return {
            voices,
            length: at + lastStart + lastLong + 0.3 - c.currentTime,
            done: () => {
                mark(null);
                said.textContent = missing.dataset.after;
            },
        };
    }));
}

// A note's number opens the sources it points at; on a wide screen they, and every unit's lessons, are
// open from the start.
const sources = document.querySelector('[data-acb-sources]');
if (sources) {
    if (matchMedia('(min-width: 900px)').matches) {
        sources.open = true;
        document.querySelectorAll('[data-acb-wide]').forEach((d) => { d.open = true; });
    }
    document.querySelectorAll('.cp-acb .s-cite a').forEach((a) => a.addEventListener('click', () => { sources.open = true; }));
}
