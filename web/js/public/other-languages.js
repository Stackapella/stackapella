// The Singing in another language course page's demos (server/resources/views/site/courses/other-languages.blade.php),
// made here with voices.js and only when pressed, one at a time through demo-player.js, every sound and every lit
// mark on the audio clock. The notes are the library's own, in its keys (docs/courses/other-languages-love-letter.md,
// its demos 1 and 5).
//
// One mora, one note: Sakura's "ka-gi-ri" at 66, ka on G4 for a beat, gi on G4 for half a beat sliding on the same
// breath to E♭4 for half a beat, ri on D4 for 1¼ beats, each syllable a fresh start ("Three morae"); then
// "Merged" ("kag-ri"), the G4 held 1½ beats with no new start where gi should be, then the step to E♭4 and ri. Then
// obasan against obāsan on A4 at 90, half a beat a mora: four even taps (o, ba, sa, n) against five slots with ba
// held through two, the n darker, a hum through a low-pass filter.
//
// The figure, faster: Shchedryk's B♭4 (1 beat), A4 (½), B♭4 (½), G4 (1), a beat's rest between rounds, twice at
// 60, four times at 80, four at 96. Drifting flat lowers each round 5 cents more than the last, so the tenth is
// 45 cents low; its last G is held through the rest beat with a true G4 (392.00 Hz) beside it, beating about 10
// times a second against 381.94 Hz (in tune, the two are one note). "At 132" plays the figure four times round at
// the library's tempo, in tune. No mic. The words are the view's.

import { audio, hz, line, voice } from './voices.js';
import { finish, label, play, when } from './demo-player.js';

const page = document.querySelector('[data-ol]');
const fill = (text, values) => Object.entries(values).reduce((t, [k, v]) => t.replaceAll(`:${k}`, v), text ?? '');
const light = (root, attr, key) => root.querySelectorAll(`[${attr}]`).forEach((el) => el.toggleAttribute('data-on', el.getAttribute(attr) === key));

// A short sung syllable: a voice with a quick start, stopped `gap` seconds before its slot ends so the next one
// starts fresh. `at` and `end` are audio-clock seconds.
function syllable(f, at, end, { cents = 0, level = 0.16, gap = 0.05 } = {}) {
    const v = voice(f, { at, cents, level, attack: 0.012, release: 0.03 });
    v.stop(end - gap - audio().currentTime);
    return v;
}

// The hummed n: a buzz through a low-pass filter, darker than a sung vowel. A voice's handle (ended, stop(after)).
function hum(f, at, end, level = 0.2) {
    const c = audio();
    const osc = c.createOscillator();
    osc.type = 'sawtooth';
    osc.frequency.value = f;
    const dark = c.createBiquadFilter();
    dark.type = 'lowpass';
    dark.frequency.value = 600;
    dark.Q.value = 0.7;
    const gain = c.createGain();
    gain.gain.setValueAtTime(0, at);
    gain.gain.setTargetAtTime(level, at, 0.015);
    gain.gain.setTargetAtTime(0, end - 0.04, 0.02);
    osc.connect(dark).connect(gain).connect(c.destination);
    osc.start(at);
    osc.stop(end + 0.2);
    const ended = new Promise((resolve) => { osc.onended = resolve; });
    ended.then(() => gain.disconnect());
    return {
        ended,
        stop(after = 0) {
            const t = c.currentTime + after;
            gain.gain.cancelScheduledValues(t);
            gain.gain.setTargetAtTime(0, t, 0.02);
            try {
                osc.stop(t + 0.2);
            } catch (error) {
                console.warn('the hum had stopped', error);
            }
        },
    };
}

// ---- One mora, one note --------------------------------------------------------------------------------------

const deck = page?.querySelector('[data-ol-demo]');
const words = deck?.dataset ?? {};
const say = deck?.querySelector('[data-ol-say]');
const BEAT = 60 / 66; // Sakura at 66
const SLOT = 60 / 90 / 2; // obasan at 90, half a beat a mora
const G4 = hz(67);
const EB4 = hz(63);
const D4 = hz(62);
const A4 = hz(69);
const SLIDE = 0.015; // gi's step down to E♭4 on the same vowel: a clean step, never a glide (only long enough not to click)
const GAP = 0.9; // seconds between the tour's parts

function show(view) {
    // An SVG group has no hidden property: the attribute itself, which the page's CSS hides.
    deck.querySelectorAll('[data-ol-view]').forEach((g) => g.toggleAttribute('hidden', g.dataset.olView !== view));
    deck.querySelector('[data-ol-mode]').textContent = words[`name${view[0].toUpperCase()}${view.slice(1)}`];
}

// Each part makes its voices from `at` and lights its marks as they sound; returns [voices, length in seconds].
const PARTS = {
    three(at, live) {
        const gi = line([{ at: 0, f: G4 }, { at: BEAT / 2, f: EB4, slide: SLIDE }], BEAT, { at: at + BEAT, level: 0.16 });
        gi.stop(at + 2 * BEAT - 0.05 - audio().currentTime);
        const voices = [syllable(G4, at, at + BEAT), gi, syllable(D4, at + 2 * BEAT, at + 3.25 * BEAT, { gap: 0 })];
        [0, 1, 2].forEach((i) => when(at + i * BEAT).then(() => live() && light(deck, 'data-ol-on', `three-${i}`)));
        return [voices, 3.25 * BEAT];
    },
    merged(at, live) {
        const kag = line([{ at: 0, f: G4 }, { at: 1.5 * BEAT, f: EB4, slide: SLIDE }], 2 * BEAT, { at, level: 0.16 });
        kag.stop(at + 2 * BEAT - 0.05 - audio().currentTime);
        const voices = [kag, syllable(D4, at + 2 * BEAT, at + 3.25 * BEAT, { gap: 0 })];
        [0, 2].forEach((beat, i) => when(at + beat * BEAT).then(() => live() && light(deck, 'data-ol-on', `merged-${i}`)));
        return [voices, 3.25 * BEAT];
    },
    obasan(at, live) {
        // [word, first slot, slots] for each mora; the n is hummed.
        const morae = [['obasan', 0, 1], ['obasan', 1, 1], ['obasan', 2, 1], ['obasan', 3, 1], ['obaasan', 6, 1], ['obaasan', 7, 2], ['obaasan', 9, 1], ['obaasan', 10, 1]];
        const voices = morae.map(([word, slot, slots], i) => {
            const start = at + slot * SLOT;
            const end = start + slots * SLOT;
            const index = i % 4;
            when(start).then(() => live() && light(deck, 'data-ol-on', `${word}-${index}`));
            return index === 3 ? hum(A4, start, end - 0.03) : syllable(A4, start, end, { level: 0.15 });
        });
        return [voices, 11 * SLOT];
    },
};

function part(kind, { live }) {
    const c = audio();
    const at = c.currentTime + 0.1;
    show(kind);
    say.textContent = words[`say${kind[0].toUpperCase()}${kind.slice(1)}`];
    const [voices, length] = PARTS[kind](at, live);
    return { voices, length: at - c.currentTime + length + 0.1, done: () => { light(deck, 'data-ol-on', ''); say.textContent = words.after; } };
}

function tour({ live }) {
    const c = audio();
    let at = c.currentTime + 0.1;
    const voices = [];
    ['three', 'merged', 'obasan'].forEach((kind) => {
        const start = at;
        when(start).then(() => {
            if (!live()) return;
            show(kind);
            say.textContent = words[`say${kind[0].toUpperCase()}${kind.slice(1)}`];
        });
        const [made, length] = PARTS[kind](start, live);
        voices.push(...made);
        at = start + length + GAP;
    });
    return { voices, length: at - GAP - c.currentTime + 0.1, done: () => { light(deck, 'data-ol-on', ''); say.textContent = words.after; } };
}

// ---- The figure, faster ----------------------------------------------------------------------------------------

const ladderDeck = page?.querySelector('[data-ol-ladder]');
const ladderWords = ladderDeck?.dataset ?? {};
const ladderSay = ladderDeck?.querySelector('[data-ol-ladder-say]');
const FIGURE = [[70, 0, 1], [69, 1, 0.5], [70, 1.5, 0.5], [67, 2, 1]]; // [MIDI, beat, beats]: B♭4, A4, B♭4, G4
const LADDER = [60, 60, 80, 80, 80, 80, 96, 96, 96, 96];
const DRIFT = 5; // cents lower each round, drifting flat
let drifting = false;

// The rounds' cells sag with the drift (one unit a cent), or sit on the true key's line.
function sag() {
    ladderDeck.querySelectorAll('[data-ol-round]').forEach((cell) => {
        cell.setAttribute('y', String(138 + (drifting ? Number(cell.dataset.olRound) * DRIFT : 0)));
    });
}

// One round of the figure from `at` at `bpm`, `cents` off; the last round holds its G through the rest beat.
function round(at, bpm, cents, live, { last = false, k = null } = {}) {
    const beat = 60 / bpm;
    return FIGURE.map(([midi, b, n], i) => {
        const start = at + b * beat;
        const held = last && i === 3;
        when(start).then(() => {
            if (!live()) return;
            light(ladderDeck, 'data-ol-fig', String(i));
            if (i === 0 && k !== null) {
                light(ladderDeck, 'data-ol-round', String(k));
                ladderSay.textContent = `${fill(ladderWords.round, { n: String(k + 1), bpm: String(bpm) })}${cents ? ` ${fill(ladderWords.low, { c: String(-cents) })}` : ''}`;
            }
        });
        return syllable(hz(midi, cents), start, start + (held ? n + 1 : n) * beat, { level: held ? 0.12 : 0.15 });
    });
}

function climb({ live }) {
    const c = audio();
    const flat = drifting;
    let at = c.currentTime + 0.15;
    const voices = [];
    LADDER.forEach((bpm, k) => {
        const last = k === LADDER.length - 1;
        voices.push(...round(at, bpm, flat ? -DRIFT * k : 0, live, { last, k }));
        if (last) {
            // A true G4 with the last G, held as long.
            const beat = 60 / bpm;
            const from = at + 2 * beat;
            const truth = voice(hz(67), { at: from, level: 0.12, attack: 0.012, release: 0.05 });
            truth.stop(from + 2 * beat - c.currentTime);
            voices.push(truth);
            when(from).then(() => {
                if (!live()) return;
                ladderDeck.querySelector('.cp-ol-true')?.setAttribute('data-on', '');
                ladderSay.textContent = flat ? ladderWords.endFlat : ladderWords.endTrue;
            });
        }
        at += 4 * (60 / bpm);
    });
    return {
        voices,
        length: at - c.currentTime + 0.2,
        done: () => {
            light(ladderDeck, 'data-ol-fig', '');
            light(ladderDeck, 'data-ol-round', '');
            ladderDeck.querySelector('.cp-ol-true')?.removeAttribute('data-on');
        },
    };
}

function fast({ live }) {
    const c = audio();
    const beat = 60 / 132;
    const at = c.currentTime + 0.15;
    const voices = [];
    for (let k = 0; k < 4; k += 1) voices.push(...round(at + k * 4 * beat, 132, 0, live));
    ladderSay.textContent = ladderWords.sayFast;
    return { voices, length: 0.15 + 16 * beat, done: () => { light(ladderDeck, 'data-ol-fig', ''); } };
}

// ---- The buttons, the picks and the sources ---------------------------------------------------------------------

const DEMOS = {
    tour,
    three: (o) => part('three', o),
    merged: (o) => part('merged', o),
    obasan: (o) => part('obasan', o),
    ladder: climb,
    fast,
};
page?.querySelectorAll('[data-ol-play]').forEach((button) => {
    label(button, false);
    button.addEventListener('click', () => play(button, DEMOS[button.dataset.olPlay]));
});

ladderDeck?.querySelectorAll('[data-ol-drift]').forEach((chip) => chip.addEventListener('click', () => {
    drifting = chip.dataset.olDrift === 'flat';
    ladderDeck.querySelectorAll('[data-ol-drift]').forEach((b) => b.setAttribute('aria-pressed', String(b === chip)));
    sag();
}));

// Everything stops when the page is hidden (voices.js stops only its own voices).
document.addEventListener('visibilitychange', () => {
    if (document.hidden) finish();
});

// The sources, and the archive's and the notes' later rows, are folded on a phone and open on a wide screen;
// the sources open too whenever a note's number is followed to them.
const sources = page?.querySelector('[data-ol-sources]');
if (page) {
    const wide = matchMedia('(min-width: 900px)');
    const unfold = () => {
        if (wide.matches) page.querySelectorAll('[data-ol-wide], [data-ol-sources]').forEach((d) => { d.open = true; });
    };
    unfold();
    wide.addEventListener('change', unfold);
    page.addEventListener('click', (event) => {
        if (sources && event.target.closest('.s-cite a')) sources.open = true;
    });
}
