// The Musical theatre course page's demo (server/resources/views/site/courses/musical-theatre.blade.php),
// made here with voices.js and only when pressed, one at a time through demo-player.js: Two notes,
// fourteen syllables, after unit 2's Two notes, many words (course/plus/musical-theatre, mt-2-two).
// The Major-General's first two lines, read from the Chappell vocal score (No. 13, p. 61): printed in
// the treble clef, so sung, and played here, an octave lower. Each syllable lights as it sounds, on the
// audio clock (when()). The tempo slider sets the syllables a second; smeared runs the notes into each
// other on one line (voices.js line, gliding), crisp gives each its own short note with a small gap
// (voice with a quick attack and release). Changing either while it plays starts it again. No mic:
// nothing is heard or recorded. The words are the view's.

import { audio, hz, line, voice } from './voices.js';
import { play, finish, sounding, when } from './demo-player.js';

const demo = document.querySelector('[data-mt-demo]');
const words = demo?.dataset ?? {};
const say = demo?.querySelector('[data-mt-say]');
const marks = [...(demo?.querySelectorAll('[data-mt-note]') ?? [])];
const WRITTEN = JSON.parse(words.notes ?? '[]'); // MIDI, as printed
const OCTAVE = 12; // a baritone sings the treble clef an octave lower
const LEVEL = 0.16;
const CRISP = 0.55; // a crisp note's share of its syllable

const slider = demo?.querySelector('[data-mt-rate]');
const rate = () => Number(slider.value); // syllables a second

function light(i) {
    marks.forEach((m) => m.toggleAttribute('data-on', Number(m.dataset.mtNote) === i));
}

function patter({ live }) {
    const c = audio();
    const at = c.currentTime + 0.1;
    const off = (t) => t - c.currentTime;
    const step = 1 / rate();
    const crisp = demo.dataset.style === 'crisp';
    const freqs = WRITTEN.map((m) => hz(m - OCTAVE));
    const end = at + freqs.length * step;
    let voices;
    if (crisp) {
        const held = Math.max(0.06, step * CRISP);
        voices = freqs.map((f, i) => {
            const v = voice(f, { level: LEVEL, at: at + i * step, attack: 0.008, release: 0.018 });
            v.stop(off(at + i * step + held));
            return v;
        });
    } else {
        const glide = Math.min(0.07, step * 0.45);
        voices = [line(freqs.map((f, i) => ({ at: i * step, f, slide: glide })), end - at, { level: LEVEL, at })];
    }
    freqs.forEach((_, i) => when(at + i * step).then(() => live() && light(i)));
    say.textContent = crisp ? words.crisp : words.legato;
    return {
        voices,
        length: off(end + 0.05),
        done: () => {
            light(-1);
            say.textContent = words.after;
        },
    };
}

if (demo) {
    const big = demo.querySelector('[data-mt-play]');
    const again = () => {
        if (!sounding(big)) return;
        finish();
        play(big, patter);
    };
    big.addEventListener('click', () => play(big, patter));

    const out = demo.querySelector('[data-mt-rate-out]');
    slider.addEventListener('input', () => { out.textContent = words.rate.replace(':n', String(rate())); });
    slider.addEventListener('change', again);

    const chips = [...demo.querySelectorAll('[data-mt-style]')];
    for (const chip of chips) {
        chip.addEventListener('click', () => {
            demo.dataset.style = chip.dataset.mtStyle;
            chips.forEach((b) => b.setAttribute('aria-pressed', String(b === chip)));
            again();
        });
    }
}

// A note's number opens the sources it points at; on a wide screen they, every unit's lessons and the
// whole company are open from the start.
const sources = document.querySelector('[data-mt-sources]');
if (sources) {
    if (matchMedia('(min-width: 900px)').matches) {
        sources.open = true;
        document.querySelectorAll('[data-mt-wide]').forEach((d) => { d.open = true; });
    }
    document.querySelectorAll('.cp-mt .s-cite a').forEach((a) => a.addEventListener('click', () => { sources.open = true; }));
}
