// The Counterpoint course page's demos (server/resources/views/site/courses/counterpoint.blade.php),
// made here with voices.js and only when pressed: Row, Row, Row Your Boat as a round for three, with
// one voice to follow; a run of notes taking turns high and low, split into two tunes by speed; the
// same top line over parallel fifths and over contrary motion; an octave melting into one note; a
// round for four whose entries you count; and a short line with a copy you move until it fits.
// Every sound is scheduled on the audio clock, never a timer, and the drawings follow that clock:
// a requestAnimationFrame loop reads currentTime against each demo's scheduled start to light the
// points as they sound. The facts: docs/courses/counterpoint-love-letter.md.

import { audio, hz, line, stopAll, voice } from './voices.js';

const page = document.querySelector('[data-cp]');
const still = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
let playing = null; // { button, done }

function label(button, on) {
    button.setAttribute('aria-pressed', String(on));
    const text = button.querySelector('[data-cp-label]');
    if (text) text.textContent = on ? button.dataset.stop : button.dataset.play;
}

// One demo sounds at a time, as on the Yodeling page: a demo returns its voices (and, when they
// hold, their length), or a promise of its own end (`until`); the end is scheduled on the audio
// clock, and the button resets once it's quiet. A demo's done() hears whether it ran to its end.
function start(button, run) {
    const was = playing?.button;
    finish();
    if (was === button) return;
    audio();
    const { voices, length, done, until } = run();
    if (length !== undefined) voices.forEach((v) => v.stop(length));
    const mine = { button, done };
    playing = mine;
    label(button, true);
    (until ?? Promise.all(voices.map((v) => v.ended))).then(() => playing === mine && finish(true));
}

function finish(whole = false) {
    if (!playing) return;
    const { button, done } = playing;
    playing = null;
    done?.(whole);
    label(button, false);
    stopAll();
}

// Light a drawing's points from the audio clock: each point's data-at and data-len (seconds after
// `from`) say when it sounds; `frame` draws anything else (a playhead) at the same moment. Returns
// the way to stop and unlight it.
function watch(roots, from, frame = null) {
    const c = audio();
    const dots = roots.flatMap((root) => [...root.querySelectorAll('[data-at]')]).map((el) => ({ el, at: Number(el.dataset.at), len: Number(el.dataset.len || 0.3) }));
    roots.forEach((root) => root.setAttribute('data-singing', ''));
    let on = true;
    const tick = () => {
        if (!on) return;
        const t = c.currentTime - from;
        for (const { el, at, len } of dots) {
            el.toggleAttribute('data-lit', t >= at);
            el.toggleAttribute('data-now', t >= at && t < at + len);
        }
        frame?.(t);
        requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
    return () => {
        on = false;
        roots.forEach((root) => {
            root.removeAttribute('data-singing');
            root.querySelectorAll('[data-lit], [data-now]').forEach((el) => { el.removeAttribute('data-lit'); el.removeAttribute('data-now'); });
        });
    };
}

// A readout in both skins' words (the page shows the one for the visitor's skin).
function say(el, serious, game = serious) {
    el.querySelector('.s-skin-s').textContent = serious;
    el.querySelector('.s-skin-g').textContent = game;
}

// Separate sung notes, each its own voice so a repeated note is heard again: [{ at, length, f }]
// in seconds after `from` on the audio clock, each ending on the clock.
function sing(notes, from, { level = 0.1, cents = 0 } = {}) {
    const c = audio();
    return notes.map(({ at, length, f }) => {
        const v = voice(f, { at: from + at, level, cents });
        v.stop(from + at + length * 0.92 - c.currentTime);
        return v;
    });
}

// A silent voice that ends when a demo does: its end on the audio clock is the demo's end.
function clock(from, end) {
    const c = audio();
    const v = voice(1, { level: 0, at: from });
    v.stop(end - c.currentTime);
    return v;
}

// The round: the tune drawn by the canon (its data-tune, in eighths), each voice two bars (12
// eighths) after the last, every voice a few cents apart, as real singers are.
const art = page.querySelector('[data-tune]');
const TUNE = JSON.parse(art.dataset.tune);
const EIGHTH = Number(art.dataset.eighth);
const X0 = Number(art.dataset.x0);
const U = Number(art.dataset.u);
const DO = hz(60);
const SPREAD = [0, 5, -4, 3];
function roundNotes(count) {
    const notes = [];
    for (let v = 0; v < count; v += 1) {
        for (const [at, length, semis] of TUNE) notes.push({ line: v + 1, at: (v * 12 + at) * EIGHTH, length: length * EIGHTH, f: DO * 2 ** (semis / 12), cents: SPREAD[v] });
    }
    return notes;
}
const roundEnd = (notes) => Math.max(...notes.map((n) => n.at + n.length));

// Follow a voice: the one followed comes forward (louder) and the other two step back, in sound and
// on the drawing. During the round, the notes still to come are sung again at the new levels and
// the ones sounding are glided there (voice.level), keeping their scheduled ends.
let followed = 0;
let round = null; // { from, notes }
const LEVEL = 0.09;
const levelOf = (n) => (!followed ? LEVEL : n === followed ? 0.15 : 0.04);
function sound(note, from) {
    const c = audio();
    const v = voice(note.f, { at: from + note.at, level: levelOf(note.line), cents: note.cents });
    v.stop(from + note.at + note.length * 0.92 - c.currentTime);
    return v;
}
function refollow() {
    const now = audio().currentTime;
    for (const note of round.notes) {
        const begin = round.from + note.at;
        const end = begin + note.length * 0.92;
        if (end <= now) continue;
        if (begin > now + 0.03) {
            note.v.stop(0);
            note.v = sound(note, round.from);
        } else {
            note.v.level(levelOf(note.line), 0.12);
            note.v.stop(end - now);
        }
    }
}
for (const button of page.querySelectorAll('[data-cp-follow]')) {
    button.addEventListener('click', () => {
        const n = Number(button.dataset.cpFollow);
        followed = followed === n ? 0 : n;
        page.querySelectorAll('[data-cp-follow]').forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.cpFollow) === followed)));
        page.dataset.follow = String(followed);
        if (round) refollow();
    });
}

const canons = [...page.querySelectorAll('[data-cp-canon]')];
const scroller = page.querySelector('[data-cp-scroll]');
for (const button of page.querySelectorAll('[data-cp-round]')) {
    button.addEventListener('click', () => start(button, () => {
        const from = audio().currentTime + 0.1;
        const notes = roundNotes(3);
        notes.forEach((note) => { note.v = sound(note, from); });
        round = { from, notes };
        const heads = canons.map((canon) => canon.querySelector('[data-cp-head]'));
        const svg = scroller.querySelector('svg');
        const off = watch(canons, from, (t) => {
            if (still()) return;
            const x = Math.max(0, t / EIGHTH) * U;
            heads.forEach((head) => { head.style.transform = `translateX(${x}px)`; });
            // On a phone the canon scrolls sideways: keep the playhead in view.
            if (scroller.scrollWidth > scroller.clientWidth + 4) {
                const scale = svg.getBoundingClientRect().width / svg.viewBox.baseVal.width;
                scroller.scrollLeft = Math.max(0, (X0 + x) * scale - scroller.clientWidth * 0.4);
            }
        });
        const tick = clock(from, from + roundEnd(notes) + 0.2);
        return {
            voices: [tick],
            until: tick.ended,
            done: () => {
                off();
                round = null;
                heads.forEach((head) => { head.style.transform = ''; });
            },
        };
    }));
}

// Split the tune: notes taking turns, high and low, for four seconds at the slider's speed (notes a
// second). The drawing follows the slider: one zigzag when slow, an ink strand and a red one when
// fast. A change while it plays starts it again at the new speed.
const run = page.querySelector('[data-cp-run]');
const rate = run.querySelector('[data-cp-rate]');
const streams = run.querySelector('[data-cp-streams]');
const runPlay = run.querySelector('[data-cp-run-play]');
const HIGH = JSON.parse(run.dataset.high);
const LOW = JSON.parse(run.dataset.low);
function showRate() {
    const split = Math.min(1, Math.max(0, (Number(rate.value) - 5) / 3));
    run.style.setProperty('--split', split.toFixed(2));
    const two = split >= 0.5;
    say(streams, two ? streams.dataset.two : streams.dataset.one, two ? streams.dataset.twoGame : streams.dataset.oneGame);
}
function playRun() {
    const each = 1 / Number(rate.value);
    const count = Math.round(4 / each);
    const notes = Array.from({ length: count }, (_, i) => ({ at: i * each, f: hz(i % 2 ? LOW[(i >> 1) % LOW.length] : HIGH[(i >> 1) % HIGH.length]) }));
    return { voices: [line(notes, count * each, { level: 0.1 })], length: count * each };
}
rate.addEventListener('input', showRate);
rate.addEventListener('change', () => {
    if (playing?.button !== runPlay) return;
    finish();
    start(runPlay, playRun);
});
runPlay.addEventListener('click', () => start(runPlay, playRun));
showRate();

// One voice or two, in one card: Same way and Opposite ways (the card's two lines, MIDI, a note
// every 0.6 seconds, sung together; then the visitor says what they heard, and the card says what
// most ears hear), and Melt the octave.
const STEP = 0.6;
const fuse = page.querySelector('[data-cp-fuse]');
const FUSE = JSON.parse(fuse.dataset.lines);
const answer = fuse.querySelector('[data-cp-answer]');
const fusePlay = fuse.querySelector('[data-cp-fuse-play]');
const picks = fuse.querySelectorAll('[data-cp-pick]');
fuse.querySelectorAll('[data-cp-note]').forEach((dot) => {
    dot.dataset.at = String(Number(dot.dataset.cpNote) * STEP);
    dot.dataset.len = String(STEP);
});
for (const tab of fuse.querySelectorAll('[data-cp-tab]')) {
    tab.addEventListener('click', () => {
        const kind = tab.dataset.cpTab;
        if (playing && fuse.contains(playing.button)) finish();
        fuse.querySelectorAll('[data-cp-tab]').forEach((t) => t.setAttribute('aria-pressed', String(t === tab)));
        fuse.querySelector('[data-cp-panel="motion"]').hidden = kind === 'melt';
        fuse.querySelector('[data-cp-panel="melt"]').hidden = kind !== 'melt';
        if (kind !== 'melt') fuse.dataset.kind = kind;
        picks.forEach((p) => p.setAttribute('aria-pressed', 'false'));
        answer.textContent = '';
        melting();
    });
}
fusePlay.addEventListener('click', () => start(fusePlay, () => {
    const kind = fuse.dataset.kind;
    const length = FUSE[kind][0].length * STEP + 0.4;
    const from = audio().currentTime + 0.05;
    const off = watch([fuse.querySelector(`g[data-show="${kind}"]`)], from);
    return {
        voices: FUSE[kind].map((midi) => line(midi.map((m, i) => ({ at: i * STEP, f: hz(m) })), length, { level: 0.11, at: from })),
        length,
        done: off,
    };
}));
for (const pick of picks) {
    pick.addEventListener('click', () => {
        picks.forEach((p) => p.setAttribute('aria-pressed', String(p === pick)));
        // The answer's data key: the card's kind and the pick, as data-<kind>-<pick> (say, data-same-one).
        const choice = pick.dataset.cpPick;
        answer.textContent = answer.dataset[fuse.dataset.kind + choice[0].toUpperCase() + choice.slice(1)];
    });
}

// Melt the octave: A3 and A4 held, the top one tuned by the slider in cents, live while it sounds.
// At 0 the red thread lies on the ink one; out of tune it lifts away by the cents and wobbles at
// the beat rate, 2·f·(2^(c/1200) − 1) with f the A3 (its second overtone against the A4). With
// reduced motion it only lifts.
const melt = page.querySelector('[data-cp-melt]');
const detune = melt.querySelector('[data-cp-detune]');
const centsOut = melt.querySelector('[data-cp-cents]');
const melted = melt.querySelector('[data-cp-melted]');
const meltPlay = melt.querySelector('[data-cp-melt-play]');
const meltThread = melt.querySelector('[data-cp-melt-thread]');
const meltDot = melt.querySelector('[data-cp-melt-dot]');
const LOW_A = 220;
let upper = null;
function drawMelt(time) {
    const cents = Number(detune.value);
    const off = Math.abs(cents);
    const y = 44 - off * 0.6;
    const beat = Math.abs(2 * LOW_A * (2 ** (cents / 1200) - 1));
    const swing = still() || !off ? 0 : Math.min(6, 2 + off / 10) * Math.sin(2 * Math.PI * beat * time);
    let d = `M10 ${y.toFixed(1)}`;
    for (let x = 16; x <= 262; x += 6) d += ` L${x} ${(y + swing * Math.sin(((x - 10) / 252) * 3 * Math.PI)).toFixed(1)}`;
    meltThread.setAttribute('d', d);
    meltDot.setAttribute('cy', y.toFixed(1));
}
function showMelt() {
    const cents = Number(detune.value);
    centsOut.textContent = cents > 0 ? `+${cents}` : String(cents);
    const one = Math.abs(cents) <= 3;
    melt.toggleAttribute('data-merged', one);
    say(melted, one ? melted.dataset.one : melted.dataset.two, one ? melted.dataset.oneGame : melted.dataset.twoGame);
    upper?.tune(cents, 0.08);
    drawMelt(performance.now() / 1000);
}
// The wobble is drawn only while the octave is on screen.
let meltSeen = false;
let meltLoop = false;
function melting() {
    if (meltLoop || !meltSeen || melt.hidden || still()) return;
    meltLoop = true;
    const frame = (ms) => {
        if (!meltSeen || melt.hidden) { meltLoop = false; return; }
        drawMelt(ms / 1000);
        requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
}
new IntersectionObserver(([entry]) => { meltSeen = entry.isIntersecting; melting(); }).observe(fuse);
detune.addEventListener('input', showMelt);
meltPlay.addEventListener('click', () => start(meltPlay, () => {
    const low = voice(LOW_A, { level: 0.11 });
    upper = voice(LOW_A * 2, { level: 0.08, cents: Number(detune.value) });
    return { voices: [low, upper], length: 14, done: () => { upper = null; } };
}));
showMelt();

// Count the voices: the round for four, building up; at rest only the time line shows, and each
// lane is drawn as its voice comes in. New voice catches an entry when it's pressed from just
// before that voice comes in until two seconds after (the entries are 2.16 seconds apart). Taps
// are marked on the time line; the score and the 1989 study come once it's over.
const count = page.querySelector('[data-cp-count]');
const countPlay = count.querySelector('[data-cp-count-play]');
const tap = count.querySelector('[data-cp-tap]');
const taps = count.querySelector('[data-cp-taps]');
const caught = count.querySelector('[data-cp-caught]');
const countNote = count.querySelector('[data-cp-count-note]');
const entries = [...count.querySelectorAll('[data-cp-entry]')];
const LANE = 580 / 84;
let session = null; // { from, times, caught }
countPlay.addEventListener('click', () => start(countPlay, () => {
    const from = audio().currentTime + 0.1;
    const notes = roundNotes(4);
    const voices = notes.map((note) => sing([note], from, { level: 0.07, cents: note.cents })[0]);
    session = { from, times: [0, 1, 2, 3].map((v) => from + v * 12 * EIGHTH), caught: new Set() };
    taps.replaceChildren();
    caught.hidden = true;
    countNote.hidden = true;
    tap.disabled = false;
    const off = watch([count], from, (t) => entries.forEach((entry, k) => entry.toggleAttribute('data-in', t >= k * 12 * EIGHTH)));
    return {
        voices,
        done: () => {
            off();
            const n = String(session.caught.size);
            const total = String(session.times.length);
            say(caught, caught.dataset.say.replace(':n', n).replace(':total', total), caught.dataset.sayGame.replace(':n', n).replace(':total', total));
            entries.forEach((entry) => entry.setAttribute('data-in', ''));
            caught.hidden = false;
            countNote.hidden = false;
            tap.disabled = true;
            session = null;
        },
    };
}));
tap.addEventListener('click', () => {
    if (!session) return;
    const now = audio().currentTime;
    const hit = session.times.findIndex((t, i) => !session.caught.has(i) && now >= t - 0.15 && now <= t + 2);
    if (hit >= 0) session.caught.add(hit);
    const mark = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    mark.setAttribute('cx', (10 + Math.min(84, (now - session.from) / EIGHTH) * LANE).toFixed(1));
    mark.setAttribute('cy', '106');
    mark.setAttribute('r', '5');
    mark.setAttribute('class', hit >= 0 ? 'cp-cp2-tapmark cp-cp2-hit' : 'cp-cp2-tapmark');
    taps.append(mark);
});

// Seek and you shall find: the line, and a copy coming in after the slider's beats. A delay fits
// when every pair of notes sounding together is smooth in the course's style (unison or octave,
// thirds, the fifth, sixths). Nothing is given away: a delay's mark, its links and its verdict
// show only once it has been heard to the end.
const seek = page.querySelector('[data-cp-seek]');
const LINE = JSON.parse(seek.dataset.line);
const delay = seek.querySelector('[data-cp-delay]');
const fit = seek.querySelector('[data-cp-fit]');
const legend = seek.querySelector('[data-cp-legend]');
const seekPlay = seek.querySelector('[data-cp-seek-play]');
const copy = seek.querySelector('[data-cp-copy]');
const links = seek.querySelector('[data-cp-links]');
const BEAT = 0.42;
const SMOOTH = new Set([0, 3, 4, 7, 8, 9]);
const smooth = (a, b) => SMOOTH.has(Math.abs(a - b) % 12);
const clashes = (d) => LINE.slice(d).filter((m, i) => !smooth(m, LINE[i])).length;
const x = (beat) => 24 + beat * 36;
const y = (m) => 112 - (m - 60) * 10;
const heard = new Set();
seek.querySelectorAll('[data-cp-note]').forEach((dot) => { dot.dataset.len = String(BEAT); });
function showSeek() {
    const d = Number(delay.value);
    seek.querySelector('[data-cp-beats]').textContent = String(d);
    copy.style.transform = `translate(${d * 36}px, 0)`;
    seek.querySelectorAll('[data-cp-note]').forEach((dot) => { dot.dataset.at = String((Number(dot.dataset.cpNote) + (copy.contains(dot) ? d : 0)) * BEAT); });
    const known = heard.has(d);
    links.replaceChildren(...(known ? LINE.slice(d).map((m, i) => {
        const link = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        link.setAttribute('d', `M${x(d + i)} ${y(m)} V${y(LINE[i])}`);
        link.setAttribute('class', smooth(m, LINE[i]) ? 'cp-cp2-link' : 'cp-cp2-link cp-cp2-rub');
        return link;
    }) : []));
    const n = clashes(d);
    fit.hidden = !known;
    seek.toggleAttribute('data-fits', known && n === 0);
    if (known) say(fit, n === 0 ? fit.dataset.smooth : (n === 1 ? fit.dataset.one : fit.dataset.many.replace(':n', String(n))), n === 0 ? fit.dataset.smoothGame : fit.dataset.rubGame.replace(':n', String(n)));
}
delay.addEventListener('input', showSeek);
seekPlay.addEventListener('click', () => start(seekPlay, () => {
    const d = Number(delay.value);
    const from = audio().currentTime + 0.1;
    const notes = (after, last) => LINE.map((m, i) => ({ at: (after + i) * BEAT, length: (i === LINE.length - 1 ? last : 1) * BEAT, f: hz(m) }));
    const off = watch([seek], from);
    return {
        voices: [...sing(notes(0, 1), from, { level: 0.12 }), ...sing(notes(d, 2), from, { level: 0.12, cents: 4 })],
        done: (whole) => {
            off();
            if (!whole) return;
            heard.add(d);
            const tick = seek.querySelector(`[data-cp-tick="${d}"]`);
            tick.setAttribute('data-heard', '');
            tick.toggleAttribute('data-fits', clashes(d) === 0);
            legend.hidden = false;
            showSeek();
        },
    };
}));
showSeek();

// The books, the years, the pack and the sources: open on a big screen, a tap away on a phone.
const wide = matchMedia('(min-width: 900px)');
const panels = page.querySelectorAll('[data-cp-open]');
function openPanels() {
    if (wide.matches) panels.forEach((panel) => { panel.open = true; });
}
openPanels();
wide.addEventListener('change', openPanels);

addEventListener('pagehide', () => finish());
