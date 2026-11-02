// Songwriters' chord builder in the shared demo slot (docs/demo-pages-plan.md; the Create page shares
// it): Amazing Grace's first line with a chord a bar, harmonised in 2 to 4 parts by the app's own
// arranger (js/public/chord-builder.js, the arranger's engine). Tap a chord to swap it for the next
// one that still holds the tune: the parts are worked out again at once (only that chord changes), the
// lane redraws, and the bar before to the bar after plays so the change is heard. Play sings the whole
// line; 2, 3 or 4 singers; Back to the arranger's chords. Every sound is on the audio clock
// (demo-player.js); each chord lights as it sounds. The words are the view's (the frame's data-*).

import { TUNE, beatToSeconds, harmonise, play as sing, slotsOf } from '../chord-builder.js';
import { label, play, when } from '../demo-player.js';

const SVG = 'http://www.w3.org/2000/svg';
const W = 600;
const H = 210;
const PARTS = ['soprano', 'alto', 'tenor', 'bass'];
const BAR = 3; // beats a bar (3/4)

const fill = (text, values) => Object.entries(values).reduce((t, [k, v]) => t.replaceAll(`:${k}`, String(v)), text);
const el = (tag, attrs = {}, kids = []) => {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (k === 'text') node.textContent = v;
        else node.setAttribute(k, v);
    }
    node.append(...kids);
    return node;
};

export async function mount(stage) {
    const frame = stage.querySelector('[data-demo-frame]');
    const words = frame.querySelector('[data-cb]').dataset;
    const slots = slotsOf(TUNE);
    const picks = slots.map((s) => s.pick);
    const end = Math.max(...TUNE.melody.map((n) => n.beat + n.length));
    let singers = 4;
    let arrangement = null;

    // The live builder, replacing the still frame.
    const lane = document.createElementNS(SVG, 'svg');
    lane.setAttribute('viewBox', `0 0 ${W} ${H}`);
    lane.setAttribute('preserveAspectRatio', 'none');
    lane.setAttribute('aria-hidden', 'true');
    lane.classList.add('s-cb-lane');
    const lyric = el('p', { class: 's-cb-words', 'aria-hidden': 'true' });
    for (const note of TUNE.melody) if (note.lyric) lyric.append(el('span', { text: note.lyric }));
    const chips = el('div', { class: 's-cb-chips', role: 'group', 'aria-label': words.chordsLabel });
    // The colours' key: only the parts singing (2 singers: the tune and alto; 3 add the bass).
    const legend = el('p', { class: 's-cb-legend', 'aria-hidden': 'true' }, PARTS.map((key) => el('span', { 'data-part': key }, [el('i', { class: `s-cb-${key}` }), words[`part${key[0].toUpperCase()}${key.slice(1)}`]])));
    const playButton = el('button', { type: 'button', class: 's-cb-play', 'data-play': words.play, 'data-stop': words.stop, 'aria-pressed': 'false' }, [
        el('span', { class: 's-cb-play-icon', 'aria-hidden': 'true' }),
        el('span', { 'data-label': '', text: words.play }),
    ]);
    const counts = el('div', { class: 's-cb-singers', role: 'group', 'aria-label': words.singers }, [
        el('span', { class: 's-cb-singers-h', 'aria-hidden': 'true', text: words.singers }),
        ...[2, 3, 4].map((n) => el('button', { type: 'button', 'data-singers': String(n), 'aria-pressed': String(n === singers), text: String(n) })),
    ]);
    const reset = el('button', { type: 'button', class: 's-cb-reset', text: words.reset });
    const say = el('p', { class: 's-cb-say', role: 'status', text: words.ready });
    frame.replaceChildren(el('div', { class: 's-cb' }, [
        el('div', { class: 's-cb-roll' }, [lane, lyric]),
        legend,
        chips,
        el('div', { class: 's-cb-controls' }, [playButton, counts, reset]),
        say,
    ]));

    // The parts as bars: x is the beat, y the pitch across all the parts' range.
    function draw() {
        arrangement = harmonise(TUNE, slots.map((s, i) => s.options[picks[i]]), { singers });
        const pitches = arrangement.parts.flatMap((p) => p.notes.map((n) => n.pitch));
        const lo = Math.min(...pitches) - 1;
        const hi = Math.max(...pitches) + 1;
        const x = (beat) => 12 + (beat / end) * (W - 24);
        const y = (pitch) => H - 10 - ((pitch - lo) / (hi - lo)) * (H - 20);
        const singing = new Set(arrangement.parts.map((p) => p.key));
        legend.querySelectorAll('[data-part]').forEach((item) => { item.hidden = !singing.has(item.dataset.part); });
        lane.replaceChildren();
        for (const s of slots) {
            const line = document.createElementNS(SVG, 'path');
            line.setAttribute('d', `M${x(s.beat).toFixed(1)} 0 V${H}`);
            line.setAttribute('class', 's-cb-barline');
            lane.append(line);
        }
        for (const part of arrangement.parts) {
            for (const note of part.notes) {
                const bar = document.createElementNS(SVG, 'rect');
                bar.setAttribute('x', x(note.beat).toFixed(1));
                bar.setAttribute('y', (y(note.pitch) - 4).toFixed(1));
                bar.setAttribute('width', Math.max(4, x(note.beat + note.length) - x(note.beat) - 2).toFixed(1));
                bar.setAttribute('height', '8');
                bar.setAttribute('rx', '4');
                bar.setAttribute('class', `s-cb-note s-cb-${part.key}`);
                lane.append(bar);
            }
        }
        chips.replaceChildren(...slots.map((s, i) => {
            const chord = s.options[picks[i]];
            const where = i === 0 ? words.pickup : fill(words.bar, { n: i });
            return el('button', {
                type: 'button',
                class: 's-cb-chip',
                'data-slot': String(i),
                'aria-label': fill(words.chipLabel, { where, name: chord.name, numeral: chord.numeral }),
                ...(picks[i] !== s.pick ? { 'data-changed': '' } : {}),
            }, [el('b', { text: chord.name }), el('small', { text: chord.numeral })]);
        }));
    }

    function light(i) {
        chips.querySelectorAll('[data-slot]').forEach((chip) => chip.toggleAttribute('data-on', Number(chip.dataset.slot) === i));
    }

    // Sings from `from` to `to` (beats), each chord lighting as it sounds.
    function hear(from = 0, to = end) {
        play(playButton, ({ live }) => {
            const sound = sing(arrangement, { from, to });
            slots.forEach((s, i) => {
                if (s.endBeat <= from || s.beat >= to) return;
                when(sound.start + beatToSeconds(Math.max(0, s.beat - from))).then(() => live() && light(i));
            });
            return { voices: sound.voices, length: sound.length, done: () => light(-1) };
        });
    }

    chips.addEventListener('click', (event) => {
        const chip = event.target.closest('[data-slot]');
        if (!chip) return;
        const i = Number(chip.dataset.slot);
        const was = slots[i].options[picks[i]];
        picks[i] = (picks[i] + 1) % slots[i].options.length;
        draw();
        const now = slots[i].options[picks[i]];
        const where = i === 0 ? words.pickup : fill(words.bar, { n: i });
        say.textContent = fill(words.swapped, { where, from: was.name, to: now.name });
        chips.querySelector(`[data-slot="${i}"]`)?.focus();
        hear(Math.max(0, slots[i].beat - BAR), Math.min(end, slots[i].endBeat + BAR));
    });
    playButton.addEventListener('click', () => hear());
    counts.addEventListener('click', (event) => {
        const pick = event.target.closest('[data-singers]');
        if (!pick) return;
        singers = Number(pick.dataset.singers);
        counts.querySelectorAll('[data-singers]').forEach((b) => b.setAttribute('aria-pressed', String(b === pick)));
        draw();
        say.textContent = fill(words.singersNow, { n: singers });
    });
    reset.addEventListener('click', () => {
        slots.forEach((s, i) => { picks[i] = s.pick; });
        draw();
        say.textContent = words.ready;
    });

    draw();
    label(playButton, false);
    hear();
}
