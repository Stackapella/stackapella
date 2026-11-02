// The Yodeling course page's demos (server/resources/views/site/courses/yodeling.blade.php), made
// here with voices.js and only when pressed: the herder's call across the valley, coming back as an
// echo; the same leap in one voice and flipped; flip or slide, a listening round; the alphorn's
// natural notes 8 to 12 over a drone, the 11th between F and F sharp; and a siren whose break you
// move. A yodel is a line that changes voice at the flip (voices.js line), the colour changing in
// the same instant as the pitch. The facts: docs/courses/yodel-love-letter.md.

import { audio, line, stopAll, voice } from './voices.js';

const page = document.querySelector('[data-yo]');
let playing = null; // { button, done }

function label(button, on) {
    button.setAttribute('aria-pressed', String(on));
    const text = button.querySelector('[data-yo-label]');
    if (text) text.textContent = on ? button.dataset.stop : button.dataset.play;
}

// One demo sounds at a time, as on the Barbershop page: a demo returns its voices and its length,
// their end is scheduled on the audio clock (never a timer), and the button resets once they're quiet.
function start(button, run) {
    const was = playing?.button;
    finish();
    if (was === button) return;
    audio();
    const { voices, length, done } = run();
    voices.forEach((v) => v.stop(length));
    const mine = { button, done };
    playing = mine;
    label(button, true);
    Promise.all(voices.map((v) => v.ended)).then(() => playing === mine && finish());
}

function finish() {
    if (!playing) return;
    playing.done?.();
    label(playing.button, false);
    playing = null;
    stopAll();
}

const G3 = 196;
const C4 = 261.63;
const E4 = 329.63;
const G4 = 392;

// The call: low in chest voice, a sixth up into head voice, down, and up to hold. It's drawn by the
// hero's ribbon (the view's $hook), and the valley sends it back twice.
const HOOK = [{ at: 0, f: G3 }, { at: 0.3, f: E4, head: true }, { at: 0.6, f: C4 }, { at: 0.9, f: G4, head: true }];
const valley = page.querySelector('[data-yo-valley]');
for (const call of page.querySelectorAll('[data-yo-call]')) {
    call.addEventListener('click', () => start(call, () => {
        valley?.removeAttribute('data-calling');
        void valley?.getBoundingClientRect(); // restart the drawing
        valley?.setAttribute('data-calling', '');
        return { voices: [line(HOOK, 1.7, { level: 0.15, echo: { time: 0.45, feedback: 0.4 } })], length: 1.7 };
    }));
}

// The same leap, twice: G3 to E4 in chest voice, then with the E4 flipped into head voice.
for (const leap of page.querySelectorAll('[data-yo-leap]')) {
    leap.addEventListener('click', () => start(leap, () => ({
        voices: [line([{ at: 0, f: G3 }, { at: 0.7, f: E4, head: leap.dataset.yoLeap === 'flip' }], 1.8, { level: 0.15 })],
        length: 1.8,
    })));
}

// Flip or slide: the same call twice, one jumping the break in an instant and one sliding through it
// (the pitch and the voice blending over 0.4 seconds). Which clip is the flip is drawn once a visit.
const quiz = page.querySelector('[data-yo-quiz]');
const flipIs = crypto.getRandomValues(new Uint8Array(1))[0] % 2 ? 'a' : 'b';
const CALL = (slide) => [{ at: 0, f: G3 }, { at: 0.8, f: E4, head: true, slide: slide ? 0.4 : undefined }, { at: 1.8, f: C4 }];
for (const clip of page.querySelectorAll('[data-yo-clip]')) {
    clip.addEventListener('click', () => start(clip, () => ({
        voices: [line(CALL(clip.dataset.yoClip !== flipIs), 2.4, { level: 0.15 })],
        length: 2.4,
    })));
}
for (const pick of page.querySelectorAll('[data-yo-pick]')) {
    pick.addEventListener('click', () => {
        quiz.querySelectorAll('[data-yo-pick]').forEach((p) => p.setAttribute('aria-pressed', String(p === pick)));
        const answer = quiz.querySelector('[data-yo-answer]');
        const slideIs = flipIs === 'a' ? 'b' : 'a';
        const name = (key) => quiz.querySelector(`[data-yo-clip="${key}"]`).dataset.play.replace(/^\S+\s/, '');
        answer.textContent = (pick.dataset.yoPick === flipIs ? answer.dataset.right : answer.dataset.wrong).replace(':flip', name(flipIs)).replace(':slide', name(slideIs));
        quiz.querySelector('[data-yo-bonus]').hidden = pick.dataset.yoPick !== flipIs;
        // The two pitch traces: the flip a step, the slide a ramp.
        const at = { a: 20, b: 160 };
        quiz.querySelector(`[data-yo-trace="${flipIs}"]`).setAttribute('d', `M${at[flipIs]} 70 H${at[flipIs] + 50} V30 H${at[flipIs] + 100}`);
        quiz.querySelector(`[data-yo-trace="${slideIs}"]`).setAttribute('d', `M${at[slideIs]} 70 H${at[slideIs] + 36} L${at[slideIs] + 64} 30 H${at[slideIs] + 100}`);
        quiz.querySelector(`[data-yo-trace="${flipIs}"]`).classList.add('cp-yo2-head');
        quiz.querySelector(`[data-yo-trace="${slideIs}"]`).classList.add('cp-yo2-chest');
    });
}

// The alphorn's natural notes: harmonics 8 to 12 of a low C (65.41 Hz), one by one over a drone on C,
// the 11th held longest. The view lights each in turn on the same beat.
const horn = page.querySelector('[data-yo-horn]');
const hornPlay = page.querySelector('[data-yo-horn-play]');
hornPlay?.addEventListener('click', () => start(hornPlay, () => {
    const notes = [8, 9, 10, 11, 12].map((n, i) => ({ at: 0.3 + i * 0.7, f: 65.41 * n, head: true }));
    horn?.setAttribute('data-playing', '');
    return {
        voices: [voice(130.81, { level: 0.07 }), line(notes, 4.4, { level: 0.12 })],
        length: 4.4,
        done: () => horn?.removeAttribute('data-playing'),
    };
}));

// Where does yours flip? The slider sets the break between 220 and 440 Hz; the siren rises from
// 150 to 600 Hz and falls again, sliding in chest voice below the break and in head voice above, and
// jumping across it both ways.
const siren = page.querySelector('[data-yo-siren]');
const breakAt = page.querySelector('[data-yo-break]');
const noteOut = page.querySelector('[data-yo-note]');
const NAMES = ['C', 'C♯', 'D', 'E♭', 'E', 'F', 'F♯', 'G', 'A♭', 'A', 'B♭', 'B'];
const LOW = 150;
const HIGH = 600;
const breakHz = () => 220 * 2 ** (Number(breakAt.value) / 100);

// The siren drawn: time across, pitch up; chest voice (red) rising to the break, a jump into head
// voice (blue) and over the top, and a jump back down on the way down.
const height = (hz) => Math.log2(hz / LOW) / Math.log2(HIGH / LOW);
const y = (hz) => (155 - height(hz) * 150).toFixed(1);

function showBreak() {
    const hz = breakHz();
    const midi = Math.round(69 + 12 * Math.log2(hz / 440));
    noteOut.textContent = `${NAMES[midi % 12]}${Math.floor(midi / 12) - 1}`;
    siren.style.setProperty('--brk', `${height(hz) * (150 / 160) * 100 + (5 / 160) * 100}%`);
    const cross = height(hz) * 150; // x where the rise reaches the break (and 300 - x on the way down)
    const up = hz * 1.06;
    const down = hz * 0.94;
    siren.querySelector('[data-yo-curve="chest"]').setAttribute('d', `M0 ${y(LOW)} L${cross.toFixed(1)} ${y(hz)} M${(300 - cross).toFixed(1)} ${y(down)} V${y(down)} L300 ${y(LOW)}`);
    siren.querySelector('[data-yo-curve="head"]').setAttribute('d', `M${cross.toFixed(1)} ${y(hz)} V${y(up)} L150 ${y(HIGH)} L${(300 - cross).toFixed(1)} ${y(hz)} V${y(down)}`);
    siren.querySelector('[data-yo-curve="break"]').setAttribute('d', `M0 ${y(hz)} H300`);
}
breakAt?.addEventListener('input', showBreak);
if (breakAt) showBreak();

const sirenPlay = page.querySelector('[data-yo-siren-play]');
sirenPlay?.addEventListener('click', () => start(sirenPlay, () => {
    const brk = breakHz();
    const notes = [];
    let head = false;
    for (let i = 0; i <= 60; i += 1) {
        const up = i <= 30;
        const f = LOW * (HIGH / LOW) ** ((up ? i : 60 - i) / 30);
        const nowHead = up ? f > brk * 1.06 : f > brk * 0.94;
        // At the break the voice jumps: the pitch skips a little and the colour changes at once.
        notes.push({ at: i * 0.05, f, head: nowHead, slide: nowHead === head ? 0.05 : undefined });
        head = nowHead;
    }
    return { voices: [line(notes, 3.2, { level: 0.13 })], length: 3.2 };
}));

// The bill and the sources: open on a big screen, a tap away on a phone.
const wide = matchMedia('(min-width: 900px)');
const panels = page.querySelectorAll('[data-yo-open]');
function openPanels() {
    if (wide.matches) panels.forEach((panel) => { panel.open = true; });
}
openPanels();
wide.addEventListener('change', openPanels);

addEventListener('pagehide', finish);
