// The Live looping course page's loop (server/resources/views/site/courses/live-looping.blade.php): four rings round one
// 8-bar cycle, the course's own Are You Sleeping (Frère Jacques), its bass, pad, tune and the tune again two bars late.
// Pressed, it builds the loop as a looper does: each track joins in turn (its ring's data-from) and plays its notes on
// repeat (data-loop) until its data-to, every note sung with voices.js and started and stopped on the audio clock (never a
// timer), one demo at a time through demo-player.js. The playhead, the track that's in and the note sounding on each ring
// follow the audio clock's time, read each frame. The notes are the view's (each ring's data-notes, [beat, length, midi],
// and each arc's data-pos and data-len), so the picture and the sound are one. Idle, the playhead turns by CSS alone, at
// the song's own speed (still under reduced motion).

import { audio, hz, voice } from './voices.js';
import { play, sounding } from './demo-player.js';

const loop = document.querySelector('[data-ll]');
const button = loop?.querySelector('[data-ll-play]');
const head = loop?.querySelector('[data-ll-ph]');

// Each track's voice: one singer, the bass and the tunes out front, the pad a soft "oo" under them.
const SOUND = {
    bass: { level: 0.12, timbre: 'ah', attack: 0.02, release: 0.07 },
    pad: { level: 0.05, timbre: 'oo', attack: 0.15, release: 0.25 },
    tune: { level: 0.1, timbre: 'ah', attack: 0.03, release: 0.07 },
    round: { level: 0.085, timbre: 'ah', attack: 0.03, release: 0.07 },
};

const rings = () => [...(loop?.querySelectorAll('[data-ll-ring]') ?? [])].map((el) => ({
    el,
    key: el.dataset.llRing,
    from: Number(el.dataset.from),
    to: Number(el.dataset.to),
    every: Number(el.dataset.loop),
    notes: JSON.parse(el.dataset.notes || '[]'),
    arcs: [...el.querySelectorAll('.cp-ll-arc')].map((arc) => ({ arc, pos: Number(arc.dataset.pos), len: Number(arc.dataset.len) })),
}));

function sing() {
    const c = audio();
    const beat = 60 / Number(loop.dataset.tempo || 96);
    const span = Number(loop.dataset.span || 32);
    const length = Number(loop.dataset.length || 48);
    const start = c.currentTime + 0.12;
    const tracks = rings();
    const voices = [];
    for (const track of tracks) {
        const sound = SOUND[track.key] ?? SOUND.tune;
        for (let from = track.from; from < track.to; from += track.every) {
            for (const [at, len, midi] of track.notes) {
                const on = from + at;
                if (on >= track.to) continue;
                const off = Math.min(on + len, track.to);
                const v = voice(hz(midi), { ...sound, at: start + on * beat });
                // Released a hair before the next note, so repeated notes are heard as two.
                v.stop(start + off * beat - 0.03 - c.currentTime);
                voices.push(v);
            }
        }
    }
    loop.toggleAttribute('data-playing', true);
    const frame = () => {
        if (!sounding(button)) return;
        const t = Math.max((c.currentTime - start) / beat, 0);
        const turn = (t % span) / span;
        head.style.setProperty('--p', String(turn));
        for (const track of tracks) {
            const inside = t >= track.from && t < track.to;
            track.el.toggleAttribute('data-in', inside);
            for (const { arc, pos, len } of track.arcs) {
                arc.toggleAttribute('data-on', inside && (((t - pos) % span) + span) % span < len);
            }
        }
        requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
    return {
        voices,
        length: start - c.currentTime + length * beat + 0.3,
        done: () => {
            loop.removeAttribute('data-playing');
            head.style.removeProperty('--p');
            for (const track of tracks) {
                track.el.removeAttribute('data-in');
                track.arcs.forEach(({ arc }) => arc.removeAttribute('data-on'));
            }
        },
    };
}

if (loop && button && head) {
    button.addEventListener('click', () => play(button, sing));
}
