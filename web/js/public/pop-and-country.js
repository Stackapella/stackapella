// The Pop and country course page's demos (server/resources/views/site/courses/pop-and-country.blade.php, Hillel's
// design of 2026-11-18), made here with voices.js and only when pressed, one at a time through demo-player.js:
// - Now playing, lesson 1's line: the first line of Down in the Valley as the course file has it (its notes, tempo
//   and chords come from the page, App\Support\Pages\PopAndCountryPage::line, so there's one source), as side A (every
//   note straight) and side B (the same notes with the moves the visitor chose: a scoop, a fall-off, a slide and a
//   little cry). The pitch line on the roll is drawn from the same points the guide sings (voices.js styledLine) and
//   redrawn as the moves change; while a side plays, a playhead runs across the words from the audio clock (each frame
//   reads AudioContext.currentTime; with reduced motion it steps from word to word instead) and each word lights as
//   it's sung.
// - The four moves, each on one held note (voices.js ornament).
// The words are the view's (data attributes). Nothing here is timed by a timer: the sound is scheduled on the audio
// clock, and what follows it waits on the clock (demo-player.js `when`).

import { audio, hz, line, ornament, styledLine, voice } from './voices.js';
import { play, when } from './demo-player.js';

const page = document.querySelector('[data-po]');
const demo = page?.querySelector('[data-po-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-po-say]');
const trace = demo?.querySelector('[data-po-trace]');
const head = demo?.querySelector('[data-po-head]');
const still = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

let data = { tempo: 96, notes: [], chords: [], width: 1000, step: 11, middle: 120, tail: 0.4 };
try {
    data = { ...data, ...JSON.parse(words.line ?? '{}') };
} catch (error) {
    console.warn('The line could not be read', error);
}

const MIDI = 60; // the comfortable note, C4: the line runs from G3 to E4
const BEAT = 60 / data.tempo;
const NOTES = data.notes.map(([beat, length, step]) => ({ beat, length, step }));
// The note each move goes on, as the chips name them: the scoop into the first "val-", the fall-off on "ley,", the
// slide from the second "val-" into "ley", and the cry on "low,": the long notes and the line ends, as the course
// teaches.
const MOVES = { scoop: 3, fall: 4, slide: 5, cry: 8 };
const LAST = NOTES[NOTES.length - 1];
const END = LAST ? (LAST.beat + LAST.length) * BEAT + data.tail : 0;
// The chords under it, voiced low and soft, each until the next (or the line's end).
const SHAPES = { maj: [0, 4, 7], min: [0, 3, 7], dom7: [0, 4, 7, 10], min7: [0, 3, 7, 10], maj7: [0, 4, 7, 11] };
const CHORDS = data.chords.map(([beat, root, quality], i, all) => {
    const bass = 48 + root - (root >= 6 ? 12 : 0);
    return { beat, until: all[i + 1]?.[0] ?? END / BEAT + 0.6, midis: (SHAPES[quality] ?? SHAPES.maj).map((s) => bass + s) };
});
// The phrases: a new one after a rest (the breath at the comma).
const STARTS = NOTES.map((n, i) => i === 0 || n.beat > NOTES[i - 1].beat + NOTES[i - 1].length).flatMap((yes, i) => (yes ? [i] : []));

const chosen = () => new Set([...(demo?.querySelectorAll('[data-po-move][aria-pressed="true"]') ?? [])].map((b) => b.dataset.poMove));

function phrases(styled) {
    const moves = styled ? chosen() : new Set();
    const notes = NOTES.map((n, i) => {
        const move = Object.keys(MOVES).find((k) => MOVES[k] === i && moves.has(k));
        return move ? { ...n, style: move } : n;
    });
    return STARTS.map((first, k) => {
        const next = STARTS[k + 1];
        const group = notes.slice(first, next);
        const from = group[0].beat;
        const length = next === undefined ? END - from * BEAT : notes[next].beat * BEAT - 0.06 - from * BEAT;
        return { from, length, points: styledLine(group, { midi: MIDI, beat: BEAT, from }) };
    });
}

// The trace on the roll: x is time, y the pitch in semitones from the comfortable note, on the page's scale (the bars'),
// drawn as line() sings it (each point glides in over its slide, or 12 ms, then holds).
const X = (t) => (t / END) * data.width;
const Y = (f) => data.middle - 12 * Math.log2(f / hz(MIDI)) * data.step;
function draw() {
    if (!trace || !END) return;
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

// The playhead: where the sound is, as a share of the line, read from the audio clock each frame while it plays.
function place(share) {
    if (head) head.style.setProperty('--x', `${Math.max(0, Math.min(1, share)) * 100}%`);
}
function follow(at, live) {
    const c = audio();
    const frame = () => {
        if (!live()) return;
        place((c.currentTime - at) / END);
        requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
}

// Side A or side B: the phrases, a breath at the comma, over the chords; the words light as they're sung, the playhead
// runs, and the button resets when the last chord has faded (demo-player.js).
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
                v.stop(off(at + Math.min(chord.until * BEAT, END + 0.2)));
                voices.push(v);
            }
        }
        say.textContent = styled ? (chosen().size ? words.playingStyled : words.playingNone) : words.playingPlain;
        if (head) {
            place(0);
            head.hidden = false;
        }
        NOTES.forEach((n, i) => when(at + n.beat * BEAT).then(() => {
            if (!live()) return;
            light(i);
            if (still()) place((n.beat * BEAT) / END);
        }));
        if (!still()) follow(at, live);
        return {
            voices,
            length: off(at + END + 0.3),
            done: () => {
                light(-1);
                if (head) head.hidden = true;
                say.textContent = words.after;
            },
        };
    };
}

if (demo && NOTES.length) {
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
