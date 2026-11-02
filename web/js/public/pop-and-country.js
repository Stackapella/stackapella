// The Pop and country course page's demos (server/resources/views/site/courses/pop-and-country.blade.php),
// made here with voices.js and only when pressed, one at a time through demo-player.js:
// - Plain to styled, lesson 1's line: the first line of Down in the Valley (the course file's notes,
//   course/plus/pop, pop-1-style) over its I and V7 chords, as side A (every note straight) and side B
//   (the same notes with the moves the visitor chose: a scoop, a fall-off, a slide and a little cry).
//   The trace on the record is drawn from the same points the guide sings (voices.js styledLine), and
//   each word lights on the audio clock as it's sung.
// - The four moves, each on one held note (voices.js ornament).
// The words are the view's (data attributes).

import { audio, hz, line, ornament, styledLine, voice } from './voices.js';
import { play, when } from './demo-player.js';

const page = document.querySelector('[data-po]');
const demo = page?.querySelector('[data-po-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-po-say]');
const trace = demo?.querySelector('[data-po-trace]');

const MIDI = 60; // the comfortable note, C4: the line runs from G3 to E4
const BEAT = 60 / 96; // the lesson's tempo
// Down in the Valley's first line, as lesson 1 has it, and the note each move goes on: the long notes
// and the line ends, as the course teaches.
const NOTES = [
    { beat: 0, length: 1, step: -5 }, { beat: 1, length: 1, step: 0 }, { beat: 2, length: 1, step: 2 },
    { beat: 3, length: 3, step: 4 }, { beat: 6, length: 3, step: 0 },
    { beat: 9, length: 1, step: 4 }, { beat: 10, length: 1, step: 2 }, { beat: 11, length: 1, step: 0 }, { beat: 12, length: 3, step: 2 },
];
const MOVES = { scoop: 3, fall: 4, slide: 5, cry: 8 };
const BREATH = 9; // the comma: the second phrase starts here, after a breath
const END = 15 * BEAT + 0.4;
// The chords under it: I (C) to the last note, then V7 (G7), voiced low and soft.
const CHORDS = [{ beat: 0, until: 12, midis: [48, 52, 55] }, { beat: 12, until: 15.6, midis: [43, 47, 50, 53] }];

const chosen = () => new Set([...(demo?.querySelectorAll('[data-po-move][aria-pressed="true"]') ?? [])].map((b) => b.dataset.poMove));

function phrases(styled) {
    const moves = styled ? chosen() : new Set();
    const notes = NOTES.map((n, i) => {
        const move = Object.keys(MOVES).find((k) => MOVES[k] === i && moves.has(k));
        return move ? { ...n, style: move } : n;
    });
    return [
        { from: 0, length: BREATH * BEAT - 0.06, points: styledLine(notes.slice(0, 5), { midi: MIDI, beat: BEAT }) },
        { from: BREATH, length: END - BREATH * BEAT, points: styledLine(notes.slice(5), { midi: MIDI, beat: BEAT, from: BREATH }) },
    ];
}

// The trace on the record: x is time, y the pitch in semitones from the comfortable note, drawn as
// line() sings it (each point glides in over its slide, or 12 ms, then holds).
const X = (t) => 20 + (t / END) * 560;
const Y = (f) => 110 - 12 * Math.log2(f / hz(MIDI)) * 14;
function draw() {
    if (!trace) return;
    let d = '';
    for (const p of phrases(true)) {
        const at = p.from * BEAT;
        p.points.forEach((pt, i) => {
            const t = at + pt.at;
            if (!i) {
                d += `M${X(t).toFixed(1)} ${Y(pt.f).toFixed(1)}`;
                return;
            }
            d += ` L${X(t).toFixed(1)} ${Y(p.points[i - 1].f).toFixed(1)} L${X(t + (pt.slide ?? 0.012)).toFixed(1)} ${Y(pt.f).toFixed(1)}`;
        });
        d += ` L${X(at + p.length).toFixed(1)} ${Y(p.points[p.points.length - 1].f).toFixed(1)} `;
    }
    trace.setAttribute('d', d.trim());
    const n = chosen().size;
    demo.querySelector('[data-po-count]').textContent = n ? (n === 1 ? words.one : words.many.replace(':n', String(n))) : words.none;
}

function light(i) {
    demo.querySelectorAll('[data-po-note]').forEach((el) => el.toggleAttribute('data-on', Number(el.dataset.poNote) === i));
}

// Side A or side B: the two phrases, a breath at the comma, over the chords; the words light as
// they're sung, and the button resets when the last chord has faded (demo-player.js).
function sing(styled) {
    return ({ live }) => {
        const c = audio();
        const at = c.currentTime + 0.08;
        const off = (t) => t - c.currentTime;
        const voices = phrases(styled).map((p) => {
            const h = line(p.points, p.length, { level: 0.16, at: at + p.from * BEAT });
            h.stop(off(at + p.from * BEAT + p.length));
            return h;
        });
        for (const chord of CHORDS) {
            for (const m of chord.midis) {
                const v = voice(hz(m), { level: 0.035, at: at + chord.beat * BEAT });
                v.stop(off(at + chord.until * BEAT));
                voices.push(v);
            }
        }
        say.textContent = styled ? (chosen().size ? words.playingStyled : words.playingNone) : words.playingPlain;
        NOTES.forEach((n, i) => when(at + n.beat * BEAT).then(() => live() && light(i)));
        return {
            voices,
            length: off(at + END + 0.3),
            done: () => {
                light(-1);
                say.textContent = words.after;
            },
        };
    };
}

if (demo) {
    for (const button of demo.querySelectorAll('[data-po-side]')) {
        button.addEventListener('click', () => play(button, sing(button.dataset.poSide === 'b')));
    }
    for (const move of demo.querySelectorAll('[data-po-move]')) {
        move.addEventListener('click', () => {
            move.setAttribute('aria-pressed', String(move.getAttribute('aria-pressed') !== 'true'));
            draw();
        });
    }
    draw();
}

for (const button of page?.querySelectorAll('[data-po-hear]') ?? []) {
    button.addEventListener('click', () => play(button, () => {
        const kind = button.dataset.poHear;
        const v = ornament(kind, { midi: 64, length: 1.8, to: 67 });
        return { voices: [v], length: 1.9 };
    }));
}

