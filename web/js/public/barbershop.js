// The Barbershop course page's demos (server/resources/views/site/courses/barbershop.blade.php):
// four voices made here (voices.js), only when pressed. Hear the barbershop seventh ring; slide a
// chord's third down from the piano's until the wobble stops (the beats a second worked out from
// the overtones that clash: the root's fifth against the third's fourth); tune the seventh 31 cents
// low, straight or with a wave in every voice; hear the course's own phrase of Down by the Old Mill
// Stream with one seat brought forward; and the swipe, one held word while the baritone moves and
// the chord changes. The ratios are the love-letter pack's (docs/courses/barbershop-love-letter.md):
// the seventh stands at 4:5:6:7.

import { audio, chord, hz, stopAll, voice } from './voices.js';

const page = document.querySelector('[data-bb]');
const C3 = 130.81;
const PURE = { root: C3, third: C3 * 5 / 4, fifth: C3 * 3 / 2, seventh: C3 * 7 / 4 };
const PIANO = { third: 164.81, seventh: 233.08 };
const cents = (from, to) => 1200 * Math.log2(to / from);
let playing = null; // { button, done }

function label(button, on) {
    button.setAttribute('aria-pressed', String(on));
    const text = button.querySelector('[data-bb-label]');
    if (text) text.textContent = on ? button.dataset.stop : button.dataset.play;
}

// One demo sounds at a time: starting one stops the last. A demo returns its voices and how long it
// lasts; their end is scheduled on the audio clock (never a timer), and the button resets when the
// last voice has gone quiet.
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

// Hear it ring: the locked seventh for a few seconds, the ladder's rungs 4 to 7 lit and the spark on.
const ladders = page.querySelectorAll('[data-bb-ladder]');
const ring = page.querySelector('[data-bb-ring]');
ring?.addEventListener('click', () => start(ring, () => {
    ladders.forEach((l) => l.setAttribute('data-ringing', ''));
    return { voices: chord([PURE.root, PURE.third, PURE.fifth, PURE.seventh]), length: 4, done: () => ladders.forEach((l) => l.removeAttribute('data-ringing')) };
}));

// Listen for the wobble: the root and a pure fifth, with the third where the slider puts it.
const tuner = page.querySelector('[data-bb-tuner]');
const third = page.querySelector('[data-bb-third]');
const beatsOut = page.querySelector('[data-bb-beats]');
const tag = page.querySelector('[data-bb-tag]');
const rings = page.querySelector('[data-bb-rings]');
const stars = page.querySelector('[data-bb-stars]');
const wave = page.querySelector('[data-bb-wave]');
let thirdVoice = null;

function beatsAt(c) {
    return Math.abs(5 * C3 - 4 * PIANO.third * 2 ** (c / 1200));
}

function drawBeats(beats) {
    if (!wave) return;
    // Two seconds of the sound's wobble: a fast wave whose loudness swells and dies `beats` times a second.
    let d = 'M0 80';
    for (let x = 0; x <= 600; x += 2) {
        const t = x / 300;
        const swell = beats < 0.05 ? 1 : Math.abs(Math.cos(Math.PI * beats * t));
        d += ` L${x} ${(80 - 66 * swell * Math.sin(x * 0.55)).toFixed(1)}`;
    }
    wave.setAttribute('d', d);
}

function showThird() {
    const c = Number(third.value);
    const beats = beatsAt(c);
    const locked = beats <= 0.45;
    beatsOut.textContent = beats.toFixed(1);
    // The tag riding above the thumb: its cents, and the ring when it locks.
    if (tag) {
        tag.textContent = `${c > 0 ? '+' : c < 0 ? '−' : ''}${Math.abs(c)}¢${locked ? ` ${tag.dataset.ring}` : ''}`;
        tag.style.left = `calc(14px + (100% - 28px) * ${(c + 30) / 40})`;
    }
    rings.hidden = !locked;
    tuner?.toggleAttribute('data-locked', locked);
    // The game's lock meter: a star for close, closer, and locked.
    stars?.setAttribute('data-bb-stars', String(locked ? 3 : beats <= 1.5 ? 2 : beats <= 3 ? 1 : 0));
    drawBeats(beats);
    thirdVoice?.tune(c, 0.08);
}

third?.addEventListener('input', showThird);
if (third) showThird();

const wobble = page.querySelector('[data-bb-wobble]');
wobble?.addEventListener('click', () => start(wobble, () => {
    thirdVoice = voice(PIANO.third, { cents: Number(third.value), level: 0.12 });
    return { voices: [...chord([PURE.root, PURE.fifth]), thirdVoice], length: 30, done: () => { thirdVoice = null; } };
}));

// The cornerstone chord: the seventh at the piano's pitch, then tuned 31 cents low while its dot on
// the ladder glides down onto rung 7. Wavy puts a wave in every voice (5.5 a second, 25 cents), which
// smears the overtones so the chord can't lock.
const seventh = page.querySelector('[data-bb-seventh]');
const glide = page.querySelector('[data-bb-glide]');
const tones = page.querySelectorAll('[data-bb-tone]');
for (const tone of tones) {
    tone.addEventListener('click', () => tones.forEach((t) => t.setAttribute('aria-pressed', String(t === tone))));
}
seventh?.addEventListener('click', () => start(seventh, () => {
    const wavy = page.querySelector('[data-bb-tone="wavy"]')?.getAttribute('aria-pressed') === 'true';
    const vibrato = wavy ? { rate: 5.5, depth: 25 } : null;
    const voices = [PURE.root, PURE.third, PURE.fifth].map((f) => voice(f, { level: 0.11, vibrato }));
    const top = voice(PIANO.seventh, { level: 0.11, vibrato });
    top.tune(cents(PIANO.seventh, PURE.seventh), 0.8, audio().currentTime + 1.4);
    glide?.setAttribute('data-gliding', '');
    return { voices: [...voices, top], length: 5, done: () => glide?.removeAttribute('data-gliding') };
}));

// The melody from inside: the course's phrase of Down by the Old Mill Stream (lesson bb-3-lead), its
// four parts as written, in A at 84 a minute. A seat brings its line forward and sets the rest back;
// All four plays them evenly, so the tune hides in the middle. Each note change is a glide queued on
// the audio clock; the playhead crosses the chart in the phrase's time.
const phrase = page.querySelector('[data-bb-phrase]');
const parts = phrase ? JSON.parse(phrase.dataset.bbPhrase) : {};
const BEAT = 60 / 84;
const A3 = 57;
const job = page.querySelector('[data-bb-job]');

function sing(key) {
    const c = audio();
    const at = c.currentTime + 0.08;
    let beats = 0;
    const voices = Object.entries(parts).map(([name, notes]) => {
        const [, , step0, cents0] = notes[0];
        const v = voice(hz(A3 + step0, cents0), { level: key === 'all' ? 0.1 : name === key ? 0.19 : 0.035, at });
        for (const [beat, length, step, c2] of notes.slice(1)) v.tune((step - step0) * 100 + c2 - cents0, 0.03, at + beat * BEAT - 0.03);
        const [beat, length] = notes.at(-1);
        beats = Math.max(beats, beat + length);
        return v;
    });
    return { voices, length: 0.08 + beats * BEAT };
}

for (const seat of page.querySelectorAll('[data-bb-seat]')) {
    seat.addEventListener('click', () => start(seat, () => {
        const key = seat.dataset.bbSeat;
        const run = sing(key);
        for (const line of page.querySelectorAll('[data-bb-line]')) {
            line.classList.toggle('cp-bb2-dim', key !== 'all' && line.dataset.bbLine !== key);
            line.classList.toggle('cp-bb2-solo', line.dataset.bbLine === key);
        }
        phrase.style.setProperty('--bb-len', `${run.length}s`);
        phrase.setAttribute('data-playing', '');
        if (job) job.textContent = seat.dataset.job;
        return {
            ...run,
            done: () => {
                phrase.removeAttribute('data-playing');
                for (const line of page.querySelectorAll('[data-bb-line]')) line.classList.remove('cp-bb2-dim', 'cp-bb2-solo');
            },
        };
    }));
}

// Tonight's bill and the sources: all open on a big screen; on a phone, the first act open and the
// rest (and the sources) a tap away.
const wide = matchMedia('(min-width: 900px)');
const panels = page.querySelectorAll('[data-bb-open]');
function openActs() {
    if (wide.matches) panels.forEach((panel) => { panel.open = true; });
}
openActs();
wide.addEventListener('change', openActs);

// The swipe and the tag: on one held word, the baritone bends from G up to B flat (the triad becomes
// the barbershop seventh), then the chord resolves to F, each voice moving the least it can.
const swipe = page.querySelector('[data-bb-swipe]');
swipe?.addEventListener('click', () => start(swipe, () => {
    const t = audio().currentTime;
    const bass = voice(PURE.root, { level: 0.1 });
    const lead = voice(PURE.third, { level: 0.11 });
    const tenor = voice(PURE.root * 2, { level: 0.08 });
    const bari = voice(PURE.fifth, { level: 0.1 });
    bari.tune(cents(PURE.fifth, PURE.seventh), 1.2, t + 1.1);
    bass.tune(cents(PURE.root, C3 * 2 / 3), 0.25, t + 3.2);
    lead.tune(cents(PURE.third, C3 * 4 / 3), 0.25, t + 3.2);
    bari.tune(cents(PURE.fifth, C3 * 5 / 3), 0.25, t + 3.2);
    // The drawing's playhead crosses it in the swipe's time, and the spark lights as the F chord lands.
    const art = page.querySelector('.cp-bb2-swipe');
    art?.setAttribute('data-swiping', '');
    return { voices: [bass, lead, tenor, bari], length: 5.6, done: () => art?.removeAttribute('data-swiping') };
}));

addEventListener('pagehide', finish);
