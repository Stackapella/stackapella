// A song part page's sheet music (Hillel, 2026-11-18: "there should be an option to change the view to sheet music";
// his choices: your part with the tune cued, and a small toggle on the lane). The part engraved on its own staff by the
// song page's engraver (js/engrave.js), the tune greyed above it when the part isn't the tune, on the song page's paper
// (.s-score-paper: white with dark ink in a light theme, a dark sheet in a dark one). It fills the lane's box, over the
// lane, and follows the lane's time: each note and its word light in the part's colour as they sound, and the sheet
// scrolls to the line being sung. Nothing keeps time here: the lane's 'time' events (the audio clock) drive it.
// Tested in tests/flows/part_player.py.

import { engrave, glyphDefs } from '../engrave.js';

const WIDE = { page: 820, perSystem: 4 }; // a tablet or desktop: four bars a line
const NARROW = { page: 430, perSystem: 2 }; // a phone: two bars a line, at a size to read
const EPSILON = 1e-6;

/** Mounts the sheet in `holder` (inside the lane's box) for `part`, following `lane`. Returns { show(on), shown() }. */
export function mountPartSheet(holder, { lane, part }) {
    const wide = window.matchMedia('(min-width: 640px)');
    const still = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let template = null;
    let marks = []; // { el, from, to, system } in the song's beats
    let current = -1; // the system the music is in
    let on = false;
    let drawing = null;

    function draw() {
        if (!template) return;
        const size = wide.matches ? WIDE : NARROW;
        const made = engrave(template, { partKeys: [part], cue: true, names: false, nameOf: () => '', page: size.page, perSystem: size.perSystem });
        holder.innerHTML = made.systems.map((sys, i) => `<div data-system="${i}">${sys.svg}</div>`).join('');
        for (const svg of holder.querySelectorAll('svg')) {
            svg.style.display = 'block';
            svg.style.width = '100%';
            svg.style.height = 'auto';
        }
        marks = [...holder.querySelectorAll('[data-beat]')].map((el) => {
            const from = Number(el.dataset.beat);
            return { el, from, to: from + Number(el.dataset.length ?? 0), system: Number(el.closest('[data-system]')?.dataset.system ?? 0) };
        });
        current = -1;
        light(lane.time());
    }

    /** The notes sounding at `seconds` lit (while it plays), and the line the song is at in view (paused too). */
    function light(seconds) {
        if (!template || !marks.length) return;
        const beat = seconds / (60 / template.tempo);
        let system = 0;
        for (const mark of marks) {
            const sounding = lane.playing() && beat >= mark.from - EPSILON && beat < mark.to - EPSILON;
            if (sounding !== mark.el.hasAttribute('data-lit')) mark.el.toggleAttribute('data-lit', sounding);
            if (mark.from <= beat + EPSILON) system = Math.max(system, mark.system);
        }
        if (system !== current) {
            current = system;
            const line = holder.querySelector(`[data-system="${system}"]`);
            if (on && line) holder.scrollTo({ top: Math.max(0, line.offsetTop - 12), behavior: still() ? 'auto' : 'smooth' });
        }
    }

    async function ready() {
        drawing ??= lane.template().then((t) => {
            template = t;
            if (!document.getElementById('eg-gClef')) document.body.insertAdjacentHTML('beforeend', glyphDefs());
            // The part's own colour for its lit notes, as the lane colours it (its place in the song, top to bottom).
            const voices = t.parts.filter((p) => p.kind !== 'instrument').map((p) => p.key);
            holder.classList.add(`s-v${Math.min(4, Math.max(0, voices.indexOf(part)) + 1)}`);
            draw();
        });
        return drawing;
    }

    lane.on('time', ({ seconds }) => light(seconds));
    lane.on('state', () => light(lane.time()));
    wide.addEventListener('change', draw);

    return {
        show(next) {
            on = Boolean(next);
            holder.hidden = !on;
            if (!on) return Promise.resolve();
            return ready().then(() => {
                current = -1;
                light(lane.time());
            });
        },
        shown: () => on,
    };
}
