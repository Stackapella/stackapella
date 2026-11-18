// The Breath and stamina course page's demo (server/resources/views/site/courses/breath-and-stamina.blade.php),
// End on purpose, from lesson br-2-end (course/plus/breath; docs/courses/breath-love-letter.md, section 6),
// made here with voices.js and only when pressed, one at a time through demo-player.js:
// - Listen first, as the lesson's choose step: A and B, six beats of do on "oo" over a drone on do, A
//   steady and stopped on time, B bending from 4.8 beats in to 60 cents flat by its end (the course's
//   "ran out" clip). Which one ran out of air? B.
// - Then hold: press and hold the button (or Space or Enter) and the drone starts; two beats count in,
//   the note sounds from beat 3, written to end on beat 9, and letting go ends it. Held past the end,
//   it sags as a voice does when the air runs out, the same 60-cent bend over 1.2 beats, and keeps
//   sinking; three seconds past, the air is gone. Everything is scheduled on the audio clock when the
//   button goes down, and the release is measured against the clock (never a timer): within
//   RELEASE_ON_TIME of the end glows, otherwise the page says how far off, in tenths of a second. The
//   course gives no timing credit from RELEASE_ZERO off; here that's where "close" stops.
// The words are the view's (data attributes). Space anywhere on the page (not in a control) holds too, while the demo is
// in view. The roll's header says the phase and how long the note has been held, drawn per frame (never timed).
//
// The page's breath, visuals only (requestAnimationFrame and CSS transitions, still under reduced motion, where the
// server's end state stays): the hero's held note breathes in, holds its 9.4 steady seconds and stops clean, again and
// again while it's in view; and each section label's bar fills to its place as it comes into view.

import { audio, hz, line, reducedMotion, voice } from './voices.js';
import { finish, play, when } from './demo-player.js';
// The course's own release rules, copied from web/js/pitch-skills.js (RELEASE_ON_TIME, RELEASE_ZERO): importing
// that module would bring the app's vibrato and synth code onto this page (about 67 KB). Keep them in step.
const RELEASE_ON_TIME = 0.12;
const RELEASE_ZERO = 0.6;

const demo = document.querySelector('[data-br-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-br-say]');
const heardSay = demo?.querySelector('[data-br-heard]');
const button = demo?.querySelector('[data-br-hold]');
const trace = demo?.querySelector('[data-br-trace]');
const head = demo?.querySelector('[data-br-head]');
const phase = demo?.querySelector('[data-br-phase]');
const readout = demo?.querySelector('[data-br-readout]');

const BEAT = 1; // tempo 60
const NOTE = 57; // do: A3, 220 Hz, in the middle of most voices
const DRONE = NOTE - 12; // the drone on do, an octave under: A2, 110 Hz
const START = 3; // the note is written from beat 3...
const END = 9; // ...to beat 9: six beats, after a two-beat count
const SAG_FROM = 4.8; // the course's ran-out clip: steady for 4.8 beats...
const SAG_BY = 6; // ...then bent down by its end...
const SAG = -60; // ...to 60 cents flat
const SINK = SAG / (SAG_BY - SAG_FROM); // cents a beat, and it keeps sinking at that rate
const AIR = 3; // held this many beats past the end, the air is gone
const LEVEL = 0.15;

// The roll: x is beats from the press (0 to 12), y the note's cents (do on y = 100).
const X = (b) => 20 + (b * 560) / 12;
const Y = (cents) => 100 - cents * 0.5;

/** The held note's cents, `b` beats into it: steady to its written end, then sagging. */
function cents(b) {
    const past = b - (END - START);
    return past <= 0 ? 0 : SINK * past;
}

const heard = (c) => c.currentTime - (c.outputLatency || 0); // what's being heard now, on the clock

const TENTHS = new Intl.NumberFormat(document.documentElement.lang || 'en', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
const tenths = (s) => TENTHS.format(Math.round(s * 10) / 10);

// The roll's header: the phase in words (data-phase-*), and the seconds held.
function setPhase(name) {
    const text = words[`phase${name[0].toUpperCase()}${name.slice(1)}`] ?? '';
    if (phase.textContent !== text) phase.textContent = text;
}

// ---- Listen first: A steady, B running out -----------------------------------------------------

function clip(ranOut) {
    return () => {
        const c = audio();
        const at = c.currentTime + 0.06;
        const length = SAG_BY * BEAT;
        const points = [{ at: 0, f: hz(NOTE), head: true }];
        if (ranOut) points.push({ at: SAG_FROM * BEAT, f: hz(NOTE, SAG), slide: (SAG_BY - SAG_FROM) * BEAT, head: true });
        const note = line(points, length, { level: LEVEL, at: at + BEAT });
        note.stop(at + BEAT + length - c.currentTime);
        const drone = voice(hz(DRONE), { level: 0.06, at });
        return { voices: [note, drone], length: at + BEAT + length + 0.5 - c.currentTime };
    };
}

function pick(choice) {
    demo.querySelectorAll('[data-br-pick]').forEach((b) => {
        b.toggleAttribute('data-right', b.dataset.brPick === 'b');
        b.toggleAttribute('data-wrong', b.dataset.brPick === choice && choice !== 'b');
    });
    heardSay.textContent = choice === 'b' ? words.right : words.wrong;
}

// ---- Hold, and let go on the beat ----------------------------------------------------------------

let hold = null; // { t0, released, frame } while the button is down

// The head and the trace, at what's being heard (one frame each while held; a frame draws, never times).
function draw(c, h) {
    const b = Math.max(0, Math.min(heard(c) - h.t0, (END + AIR) * BEAT) / BEAT);
    const into = b - START;
    readout.textContent = into > 0 ? words.second.replace(':n', tenths(into * BEAT)) : '';
    if (!h.released) setPhase(into <= 0 ? 'count' : into > END - START ? 'sag' : 'hold');
    head.setAttribute('cx', X(b).toFixed(1));
    head.setAttribute('cy', Y(into > 0 ? cents(into) : 0).toFixed(1));
    if (into > 0) {
        let d = `M${X(START).toFixed(1)} ${Y(0)}`;
        for (let n = 0.1; n < into; n += 0.1) d += ` L${X(START + n).toFixed(1)} ${Y(cents(n)).toFixed(1)}`;
        trace.setAttribute('d', `${d} L${X(b).toFixed(1)} ${Y(cents(into)).toFixed(1)}`);
    }
    if (!h.released) h.frame = requestAnimationFrame(() => draw(c, h));
}

function reset() {
    trace.setAttribute('d', '');
    readout.textContent = '';
    setPhase('ready');
    head.setAttribute('cx', String(X(0)));
    head.setAttribute('cy', String(Y(0)));
    button.removeAttribute('data-on');
    demo.querySelectorAll('[data-br-count], [data-br-bar], [data-br-band]').forEach((el) => el.removeAttribute('data-on'));
}

function judge(off) {
    const away = Math.abs(off);
    if (away <= RELEASE_ON_TIME) {
        button.setAttribute('data-on', '');
        demo.querySelector('[data-br-band]').setAttribute('data-on', '');
        return words.onTime;
    }
    const near = away < RELEASE_ZERO;
    const text = off < 0 ? (near ? words.earlyNear : words.earlyFar) : (near ? words.lateNear : words.lateFar);
    return text.replace(':s', tenths(away));
}

// The press: everything it will play is scheduled now, on the clock; the drone answers at once.
function sing({ live }) {
    const c = audio();
    const t0 = c.currentTime + 0.03;
    reset();
    const drone = voice(hz(DRONE), { level: 0.06, at: t0, attack: 0.03 });
    const ticks = [1, 2].map((k) => {
        const tick = voice(hz(NOTE + 24), { level: 0.05, at: t0 + k * BEAT, attack: 0.004, release: 0.03 });
        tick.stop(t0 + k * BEAT + 0.06 - c.currentTime);
        return tick;
    });
    const air = (SAG_BY - SAG_FROM) * BEAT;
    const end = (END - START) * BEAT;
    const note = line([
        { at: 0, f: hz(NOTE), head: true },
        { at: end, f: hz(NOTE, SAG), slide: air, head: true },
        { at: end + air, f: hz(NOTE, SINK * AIR), slide: AIR * BEAT - air, head: true },
    ], end + AIR * BEAT, { level: LEVEL, at: t0 + START * BEAT });
    hold = { t0, released: false, frame: 0 };
    const mine = hold;
    say.textContent = words.count;
    demo.querySelectorAll('[data-br-count]').forEach((dot, i) => when(t0 + (i + 1) * BEAT).then(() => live() && dot.setAttribute('data-on', '')));
    when(t0 + START * BEAT).then(() => {
        if (!live()) return;
        demo.querySelector('[data-br-bar]').setAttribute('data-on', '');
        say.textContent = words.holding;
    });
    draw(c, hold);
    return {
        voices: [drone, note, ...ticks],
        length: t0 + (END + AIR) * BEAT + 0.1 - c.currentTime,
        // Run out (held to the end of the air) or stopped another way: the page hidden, another demo.
        done: () => {
            if (mine.released) return;
            mine.released = true;
            cancelAnimationFrame(mine.frame);
            draw(c, mine);
            if (hold === mine) hold = null;
            demo.querySelector('[data-br-bar]').removeAttribute('data-on');
            const ranOut = heard(c) - t0 >= END * BEAT + AIR * BEAT - 0.2;
            say.textContent = ranOut ? words.ranOut : words.ready;
            setPhase(ranOut ? 'sag' : 'ready');
        },
    };
}

// The release, measured against the clock the note was scheduled on.
function letGo() {
    if (!hold || hold.released) return;
    const c = audio();
    const mine = hold;
    const at = heard(c) - mine.t0;
    mine.released = true;
    cancelAnimationFrame(mine.frame);
    hold = null;
    finish();
    draw(c, mine);
    demo.querySelector('[data-br-bar]').removeAttribute('data-on');
    say.textContent = at < START * BEAT ? words.tooSoon : judge(at - END * BEAT);
    setPhase(at < START * BEAT ? 'ready' : 'done');
}

function press() {
    if (hold) return;
    play(button, sing);
}

if (demo) {
    for (const b of demo.querySelectorAll('[data-br-clip]')) {
        b.addEventListener('click', () => play(b, clip(b.dataset.brClip === 'b')));
    }
    for (const b of demo.querySelectorAll('[data-br-pick]')) {
        b.addEventListener('click', () => pick(b.dataset.brPick));
    }
    button.addEventListener('pointerdown', (event) => {
        if (!event.isPrimary || event.button !== 0) return;
        event.preventDefault();
        press();
    });
    for (const type of ['pointerup', 'pointercancel', 'pointerleave', 'blur']) button.addEventListener(type, letGo);
    button.addEventListener('contextmenu', (event) => event.preventDefault());
    button.addEventListener('keydown', (event) => {
        if (event.key !== ' ' && event.key !== 'Enter') return;
        event.preventDefault();
        if (!event.repeat) press();
    });
    button.addEventListener('keyup', (event) => {
        if (event.key !== ' ' && event.key !== 'Enter') return;
        event.preventDefault();
        letGo();
    });
    // Space anywhere outside a control, while the demo is on screen: the page's own shortcut, as the design asks.
    let onScreen = false;
    new IntersectionObserver(([entry]) => { onScreen = entry.isIntersecting; }, { threshold: 0.4 }).observe(demo);
    const free = (event) => event.target === document.body || event.target === document.documentElement;
    addEventListener('keydown', (event) => {
        if (event.key !== ' ' || !onScreen || !free(event)) return;
        event.preventDefault();
        if (!event.repeat) press();
    });
    addEventListener('keyup', (event) => {
        if (event.key !== ' ' || !hold || !free(event)) return;
        event.preventDefault();
        letGo();
    });
}

// ---- The page's breath: the hero's held note, and the labels' bars ---------------------------------

const held = document.querySelector('[data-br-held]');

// Breathe in (1.4 s, the ring opening), hold the note's 9.4 steady seconds along the scale, then rest at the clean stop.
function breathe() {
    const fill = held.querySelector('[data-br-held-fill]');
    const ring = held.querySelector('[data-br-held-ring]');
    const count = held.querySelector('[data-br-held-count]');
    const label = held.querySelector('[data-br-held-phase]');
    const HOLD = Number(held.dataset.hold) || 9.4;
    const IN = 1.4;
    const CYCLE = IN + HOLD + 2.6;
    const at = (HOLD / 15) * 100;
    const names = { in: held.dataset.in, hold: held.dataset.holding, done: held.dataset.done };
    let start = 0;
    let frame = 0;
    const tick = (now) => {
        const t = ((now - start) / 1000) % CYCLE;
        const p = t < IN ? 0 : Math.min(1, (t - IN) / HOLD);
        const name = t < IN ? 'in' : t < IN + HOLD ? 'hold' : 'done';
        fill.style.width = `${(p * at).toFixed(2)}%`;
        ring.style.transform = `scale(${t < IN ? (0.4 + (0.8 * t) / IN).toFixed(3) : 0})`;
        count.textContent = tenths(Math.floor(p * HOLD * 10) / 10);
        if (held.dataset.phase !== name) {
            held.dataset.phase = name;
            label.textContent = names[name];
        }
        frame = requestAnimationFrame(tick);
    };
    new IntersectionObserver(([entry]) => {
        cancelAnimationFrame(frame);
        if (!entry.isIntersecting) return;
        if (!start) start = performance.now();
        frame = requestAnimationFrame(tick);
    }).observe(held);
}

// Each label's bar starts empty below the fold and fills to its place as it comes into view.
function fillBars() {
    const seen = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            entry.target.removeAttribute('data-wait');
            seen.unobserve(entry.target);
        }
    }, { rootMargin: '0px 0px -12% 0px' });
    for (const bar of document.querySelectorAll('.cp-br-kbar')) {
        if (bar.getBoundingClientRect().top < innerHeight) continue;
        bar.setAttribute('data-wait', '');
        seen.observe(bar);
    }
}

if (!reducedMotion() && 'IntersectionObserver' in window) {
    if (held) breathe();
    fillBars();
}
