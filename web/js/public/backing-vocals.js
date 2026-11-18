// The Backing vocals course page's panel (server/resources/views/site/courses/backing-vocals.blade.php): Shenandoah's
// first two lines as four lanes, the lead, the pad, a doo-wop figure and an answer. Pressed, it sings every lane's notes
// with voices.js, each one started and stopped on the audio clock (never a timer), one demo at a time through
// demo-player.js; the playhead and the note sounding in each lane follow the audio clock's time, read each frame. The
// notes are the view's (each segment's data-at, data-len and data-midi, in beats at the panel's data-tempo), so the
// picture and the sound are one. Idle, the playhead sweeps by CSS alone (still under reduced motion).

import { audio, hz, voice } from './voices.js';
import { play, sounding } from './demo-player.js';

const panel = document.querySelector('[data-bv]');
const button = panel?.querySelector('[data-bv-play]');
const head = panel?.querySelector('[data-bv-ph]');

// Each lane's voice: the lead out front, the backing under it ("doo" a rounder vowel than "wah").
const SOUND = {
    lead: { level: 0.13, timbre: 'ah', attack: 0.03, release: 0.08 },
    pad: { level: 0.055, timbre: 'oo', attack: 0.15, release: 0.25 },
    doo: { level: 0.05, timbre: 'oo', attack: 0.015, release: 0.05 },
    answer: { level: 0.07, timbre: 'ah', attack: 0.03, release: 0.08 },
};

const segments = () => [...(panel?.querySelectorAll('[data-bv-lane] .cp-bv-seg') ?? [])].map((el) => ({
    el,
    lane: el.closest('[data-bv-lane]').dataset.bvLane,
    at: Number(el.dataset.at),
    len: Number(el.dataset.len),
    midi: Number(el.dataset.midi),
    word: el.dataset.word,
}));

function sing() {
    const c = audio();
    const beat = 60 / Number(panel.dataset.tempo || 63);
    const span = Number(panel.dataset.span || 16);
    const start = c.currentTime + 0.12;
    const notes = segments();
    const voices = notes.map((n) => {
        const sound = SOUND[n.lane] ?? SOUND.lead;
        const timbre = n.lane === 'doo' && n.word === 'doo' ? 'oo' : sound.timbre;
        const v = voice(hz(n.midi), { ...sound, timbre, at: start + n.at * beat });
        // Released a hair before the next note, so repeated notes are heard as two.
        v.stop(start + (n.at + n.len) * beat - 0.03 - c.currentTime);
        return v;
    });
    panel.toggleAttribute('data-playing', true);
    const frame = () => {
        if (!sounding(button)) return;
        const t = (c.currentTime - start) / beat;
        head.style.setProperty('--p', String(Math.min(Math.max(t / span, 0), 1)));
        for (const n of notes) n.el.toggleAttribute('data-on', t >= n.at && t < n.at + n.len);
        requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
    return {
        voices,
        length: start - c.currentTime + span * beat + 0.3,
        done: () => {
            panel.removeAttribute('data-playing');
            head.style.removeProperty('--p');
            for (const n of notes) n.el.removeAttribute('data-on');
        },
    };
}

if (panel && button && head) {
    button.addEventListener('click', () => play(button, sing));
}

// A note's word shows only when all of it fits in the note (the design rules' "fewer words, never clipped"): measured
// on every set of lanes on the page (the hero's and Amazing Grace's), again when the fonts arrive and the lanes resize.
function fit(tracks) {
    for (const seg of tracks.querySelectorAll('.cp-bv-seg')) {
        const word = seg.firstElementChild;
        if (!word?.textContent) continue;
        const style = getComputedStyle(seg);
        const room = seg.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
        seg.toggleAttribute('data-cut', word.offsetWidth > room + 0.5);
    }
}
const tracks = [...document.querySelectorAll('.cp-bv-tracks')];
if (tracks.length) {
    const watch = new ResizeObserver((entries) => entries.forEach((e) => fit(e.target)));
    tracks.forEach((t) => watch.observe(t));
    document.fonts?.ready.then(() => tracks.forEach(fit));
}
