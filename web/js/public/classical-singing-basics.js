// Classical singing basics' course page (site/courses/classical-singing-basics): the hero's card engraves lesson 1's own
// phrase (the view reads it from the course file) with the song pages' engraver (js/engrave.js), then draws over it the
// one slur that joins it, "legato" over its start, a breath after its last note, and a sample voice line through the
// notes. The line traces in once by CSS (input.css, Classical singing basics), still under reduced motion.
//
// Below it, lesson 1's first round to try (the view reads it from the course file): two takes of the same five notes on
// "ah", one joined and one with small gaps, played on the course pages' voice (voices.js) only when a take is pressed,
// everything on the audio clock; the reader chooses the joined one and hears why, in the course's own words. No mic.

import { engrave, glyphDefs } from '../engrave.js';
import { audio, hz, line } from './voices.js';

const SVG = 'http://www.w3.org/2000/svg';
const HEAD = 11.8; // a notehead's width, as the engraver draws it
const WIDE = 520; // the system's width: three short bars on one line
const NARROW = 400; // on a phone, the same bars drawn larger for the card's width
const phone = window.matchMedia('(max-width: 479px)');

function el(name, attrs, text) {
    const node = document.createElementNS(SVG, name);
    for (const [k, v] of Object.entries(attrs)) node.setAttribute(k, String(v));
    if (text) node.textContent = text;
    return node;
}

/** The voice line: each note held at its height to just before the next, then a short glide into it. */
function voicePath(heads, end) {
    const r = (n) => Math.round(n * 10) / 10;
    let d = `M${r(heads[0].x - 8)} ${r(heads[0].y)}`;
    heads.forEach((h, i) => {
        const next = heads[i + 1];
        if (!next) {
            d += ` H${r(end)}`;
            return;
        }
        const glide = Math.min(9, (next.x - h.x) * 0.35);
        d += ` H${r(next.x - glide)} C${r(next.x - glide / 2)} ${r(h.y)} ${r(next.x - glide / 2)} ${r(next.y)} ${r(next.x)} ${r(next.y)}`;
    });
    return d;
}

function mount(holder) {
    let phrase;
    try {
        phrase = JSON.parse(holder.dataset.notes || '[]');
    } catch {
        return; // a phrase that won't parse: the card keeps its words and shows no score
    }
    if (!phrase.length) return;
    const notes = phrase.map(([beat, length, pitch], i) => ({ beat, length, pitch, ...(i === 0 ? { lyric: 'ah' } : {}) }));
    const template = {
        id: 'classical-lesson-1', title: '', key: holder.dataset.key || 'C', timeSignature: '4/4', pickupBeats: Number(holder.dataset.pickup || 0), tempo: 60, chords: [],
        parts: [{ key: 'voice', name: '', melody: true, guide: { type: 'notes', notes } }],
    };
    const made = engrave(template, { names: false, nameOf: () => '', tempo: false, page: phone.matches ? NARROW : WIDE, perSystem: 4 });
    const system = made.systems[0];
    if (!system) return;
    if (!document.getElementById('eg-gClef')) document.body.insertAdjacentHTML('beforeend', glyphDefs());
    holder.innerHTML = system.svg;
    holder.style.aspectRatio = `${system.width} / ${system.height}`;
    const svg = holder.querySelector('svg');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');

    // Each note's head, from where the engraver put it, and the top of its stem when the stem goes up.
    const heads = [...svg.querySelectorAll('.eg-note')].map((g) => {
        const use = g.querySelector('use');
        const stem = g.querySelector('rect');
        const x = Number(use.getAttribute('x')) + HEAD / 2;
        const y = Number(use.getAttribute('y'));
        const up = stem && Number(stem.getAttribute('y')) < y - 4 ? Number(stem.getAttribute('y')) : null;
        return { x, y, up };
    });
    if (heads.length !== notes.length) return;
    const first = heads[0];
    const last = heads[heads.length - 1];
    // The highest ink between them: a head, a stem going up, or a beam.
    const beams = [...svg.querySelectorAll('[data-staff] > path')].flatMap((p) => (p.getAttribute('d').match(/-?\d+(?:\.\d+)?/g) ?? []).filter((_, i) => i % 2 === 1).map(Number));
    const top = Math.min(...heads.map((h) => h.up ?? h.y - 5), ...beams);

    const over = el('g', { class: 'cp-cl-over' });
    // The voice line, under the notes' ink: a soft band, then the line itself.
    const d = voicePath(heads, last.x + 22);
    over.append(el('path', { d, class: 'cp-cl-trace-halo', pathLength: 1 }), el('path', { d, class: 'cp-cl-trace', pathLength: 1 }));
    svg.insertBefore(over, svg.firstChild);
    // The slur over the whole phrase (one syllable, eight notes), clear of every stem and beam, "legato" over its start,
    // and the breath after the last note.
    const end = (h) => (h.up === null ? h.y - 11 : h.up - 6);
    const peak = top - 44;
    const marks = el('g', { class: 'cp-cl-marks-over' });
    marks.append(
        el('path', { d: `M${first.x} ${end(first)} Q${(first.x + last.x) / 2} ${peak} ${last.x + 4} ${end(last)}`, class: 'cp-cl-slur' }),
        el('text', { x: first.x - 8, y: Math.min(end(first) - 18, top - 10), class: 'cp-cl-legato' }, holder.dataset.legato || 'legato'),
        el('use', { href: '#eg-breathMarkComma', x: last.x + 28, y: 26, class: 'cp-cl-breath' }),
    );
    svg.append(marks);
    holder.dataset.drawn = '';
}

const holders = [...document.querySelectorAll('[data-cl-score]')];
holders.forEach(mount);
phone.addEventListener('change', () => holders.forEach(mount));

// ---- Lesson 1's first round: which take is joined? ----

// A page knows no singer's comfortable note, so the takes sit in the middle of most voices (A3, as the lesson pages'
// rounds do).
const BASE = 57;
const fill = (text, n) => String(text || '').replace(':n', String(n));

/**
 * A take's notes ([beat, beats, step]) as runs: notes with no gap between them sound as one line, gliding from note to
 * note with no break (voices.js line), and a gap starts a new one, so a gapped take is heard with its gaps.
 */
function runsOf(notes) {
    const runs = [];
    notes.forEach((note, k) => {
        const prev = notes[k - 1];
        if (!prev || Math.abs(prev[0] + prev[1] - note[0]) > 1e-6) runs.push([]);
        runs[runs.length - 1].push(note);
    });
    return runs;
}

function mountTry(box) {
    let rounds;
    try {
        rounds = JSON.parse(box.querySelector('[data-cl-rounds]').textContent);
    } catch (error) {
        console.warn('The round on this page is unreadable', error);
        return;
    }
    if (!Array.isArray(rounds) || !rounds.length) return;
    const words = box.dataset;
    const beat = 60 / (Number(words.tempo) || 72);
    const hears = [...box.querySelectorAll('[data-cl-hear]')];
    const picks = [...box.querySelectorAll('[data-cl-pick]')];
    const say = box.querySelector('[data-cl-say]');
    const count = box.querySelector('[data-cl-count]');
    const prompt = box.querySelector('[data-cl-prompt]');
    const next = box.querySelector('[data-cl-next]');
    let index = 0;
    let answered = false;
    let playing = null;
    let handles = [];
    let token = 0;

    const show = (i, on, seconds = 0) => {
        const button = hears[i];
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
        button.querySelector('[data-cl-label]').textContent = on ? words.stop : fill(words.hear, i + 1);
        if (on) {
            button.style.setProperty('--take', `${seconds}s`);
            button.dataset.playing = '';
        } else {
            delete button.dataset.playing;
        }
    };

    const stop = () => {
        token += 1;
        handles.forEach((handle) => handle.stop());
        handles = [];
        if (playing !== null) show(playing, false);
        playing = null;
    };

    const play = (i) => {
        const again = playing === i;
        stop();
        if (again) {
            if (!answered) say.textContent = words.ready;
            return;
        }
        const notes = rounds[index].clips[i];
        const last = notes[notes.length - 1];
        const seconds = (last[0] + last[1]) * beat;
        // The button answers at once; the sound follows on the audio clock.
        playing = i;
        show(i, true, seconds);
        if (!answered) say.textContent = fill(words.playing, i + 1);
        const c = audio();
        const start = c.currentTime + 0.08;
        for (const run of runsOf(notes)) {
            const from = run[0][0];
            const end = run[run.length - 1][0] + run[run.length - 1][1];
            const at = start + from * beat;
            const handle = line(run.map(([b, , step]) => ({ at: (b - from) * beat, f: hz(BASE + step) })), (end - from) * beat, { at, level: 0.15 });
            handle.stop(at + (end - from) * beat - c.currentTime);
            handles.push(handle);
        }
        // The take is over when its last line has faded (the voices' own end, not a timer).
        const mine = token;
        Promise.all(handles.map((handle) => handle.ended)).then(() => {
            if (mine !== token) return;
            show(i, false);
            playing = null;
            handles = [];
            if (!answered) say.textContent = words.ready;
        });
    };

    const pick = (i) => {
        if (answered) return;
        answered = true;
        stop();
        const round = rounds[index];
        picks.forEach((button, k) => {
            button.disabled = true;
            if (k === round.answer) button.dataset.mark = 'right';
            else if (k === i) button.dataset.mark = 'wrong';
        });
        const verdict = i === round.answer ? fill(words.right, round.answer + 1) : fill(words.wrong, round.answer + 1);
        const lastRound = index === rounds.length - 1;
        say.textContent = [verdict, round.why, lastRound ? words.done : ''].filter(Boolean).join(' ');
        next.textContent = lastRound ? words.again : words.next;
        next.hidden = false;
    };

    const turn = () => {
        stop();
        index = (index + 1) % rounds.length;
        answered = false;
        picks.forEach((button) => {
            button.disabled = false;
            delete button.dataset.mark;
        });
        prompt.textContent = rounds[index].prompt;
        count.textContent = String(words.round || '').replace(':n', String(index + 1)).replace(':total', String(rounds.length));
        say.textContent = words.ready;
        next.hidden = true;
        hears[0].focus();
    };

    hears.forEach((button, i) => button.addEventListener('click', () => play(i)));
    picks.forEach((button, i) => button.addEventListener('click', () => pick(i)));
    next.addEventListener('click', turn);
}

document.querySelectorAll('[data-cl-try]').forEach(mountTry);
