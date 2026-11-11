// The reference pages' cards (docs/seo-growth-plan.md, item 4: an interval's page, a chord's, a voice type's;
// marketing builds the pages around them). Each card is something to use rather than read: hear it, see it on a
// keyboard and a staff, and the numbers a singer wants, all from the shared theory (just.js's pure ratios,
// theory.js's voice types, notes.js's names), drawn by the shared keyboard and staff (piano.js, staff.js) and
// sounded by the shared voices (tones.js), on the audio clock.
// - mountIntervalCard: an interval from a root, heard up, down and together, equal-tempered or pure, with its
//   size in cents both ways ("Pure 5:4: 386.3 cents, 13.7 narrower").
// - mountChordCard: a chord quality from a root, heard together or one note at a time, equal or pure, each tone
//   with its interval and how far pure tuning moves it.
// - mountVoiceRangeCard: a voice type's usual range on a keyboard and a staff, its ends and the notes between
//   played, the choir part it sings, and a link to the range test to find your own.
// The root moves up and down by a semitone (the card's stepper) so a page isn't stuck on C. The words come from
// the page (`words`, Laravel's :name placeholders). Framework-free, both skins (theme tokens, the free tools'
// segmented control), hooks for the game skin (data-ui="ref-card", "keyboard", "ref-staff", "ref-play",
// "ref-facts"). Tested in tests/public-reference-cards.test.js and tests/flows/reference_cards.py.

import { INTERVALS, chordTones, intervalCents, pureRatio } from '../just.js';
import { CHOIR_PARTS, VOICE_TYPES } from '../theory.js';
import { pitchName } from '../notes.js';
import { audio, hz } from './voices.js';
import { note } from './tones.js';
import { keyboard, span } from './piano.js';
import { grandStaff } from './staff.js';
import { segmented } from './tool-layout.js';

const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));
const num = (n, digits = 1) => (Math.round(n * 10 ** digits) / 10 ** digits).toFixed(digits);
const named = (midi) => {
    const { name, octave } = pitchName(midi);
    return `${name}${octave}`;
};

// Spelling, as a theory book writes it: each note by its interval's letter (C to E♭ is a minor third, C to D♯ an
// augmented second), so the names and the staff agree with the interval's name.
const LETTERS = 'CDEFGAB';
const NATURAL = [0, 2, 4, 5, 7, 9, 11];
const SIGN = { '-2': '\u{1D12B}', '-1': '♭', 0: '', 1: '♯', 2: '\u{1D12A}' };
// An interval's letters above its root, by its semitones (the tritone as an augmented fourth, 6 as a minor sixth's 8).
const INTERVAL_LETTERS = [0, 1, 1, 2, 2, 3, 3, 4, 5, 5, 6, 6, 7];
// A chord tone's letters above its root, by semitones (a flat fifth and a sharp fifth are both fifths); a diminished
// seventh's 9 is a seventh (B𝄫 over C).
const CHORD_LETTERS = { 0: 0, 2: 1, 3: 2, 4: 2, 5: 3, 6: 4, 7: 4, 8: 4, 9: 5, 10: 6, 11: 6 };
const CHORD_LETTERS_BY = { dim7: { 9: 6 } };

/** A root's spelling: a white key's letter, a black key as a flat (D♭, E♭, A♭, B♭) except F♯. */
export function spellRoot(midi) {
    const pc = ((midi % 12) + 12) % 12;
    const white = NATURAL.indexOf(pc);
    const [letter, accidental] = white >= 0 ? [white, 0] : pc === 6 ? [3, 1] : [NATURAL.indexOf(pc + 1), -1];
    return { midi, letter, accidental, octave: Math.floor((midi - accidental) / 12) - 1 };
}

/** The note `semis` above a spelled `root`, `letters` letter names up: { midi, letter, octave, accidental }. */
export function spellAbove(root, semis, letters) {
    const letter = (root.letter + letters) % 7;
    const octave = root.octave + Math.floor((root.letter + letters) / 7);
    const midi = root.midi + semis;
    return { midi, letter, octave, accidental: midi - ((octave + 1) * 12 + NATURAL[letter]) };
}

/** A spelled note's name: "B♭4" (or "B♭" with `octave: false`). */
export const spelledName = (n, { octave = true } = {}) => `${LETTERS[n.letter]}${SIGN[n.accidental] ?? ''}${octave ? n.octave : ''}`;

// How the cards play: a note's length and the gap to the next, in seconds.
export const CARD = { length: 0.85, step: 0.75, together: 1.8, chordStep: 0.5, rangeStep: 0.26, level: 0.12, levelTogether: 0.09, roots: [48, 72] };

/** An interval's pure ratio [over, under], from just.js's table: `name` picks the seventh (7:4 or 9:5). */
export function pureOf(semis, name = null) {
    if (semis === 0) return [1, 1];
    if (semis === 12) return [2, 1];
    const found = INTERVALS.find((i) => (name ? i.name === name : i.semis === semis && i.name !== 'harmonicSeventh'));
    return found ? found.ratio : null;
}

/** An interval's sizes: { equal, pure, offset } in cents (pure and offset null when it has no pure ratio here). */
export function intervalSizes(semis, name = null) {
    const ratio = pureOf(semis, name);
    if (!ratio) return { equal: semis * 100, pure: null, offset: null, ratio: null };
    const { cents, offset } = intervalCents({ semis, ratio });
    return { equal: semis * 100, pure: cents, offset, ratio };
}

/**
 * The card's frame: the keyboard, the staff, a row of play buttons with the tuning switch, and the facts. In a box 900 px
 * wide or more (its own width, a container query: Hillel's redesign of the reference pages, 2026-11-10, one full-width
 * panel), it lays out across: the row first (the plays at the left, the key and Equal or Pure at the right), then the
 * piano (3/5) beside the staff (2/5), then the facts as one ruled row. Narrower, it stacks as it always has. The
 * controls come in the same order either way, so focus follows what's seen; the piano and the staff take no focus.
 */
function frame(el, kind) {
    el.innerHTML = `
<div class="@container">
<section class="grid gap-4 rounded-3xl bg-panel p-4 text-cream sm:p-6 @min-[900px]:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] @min-[900px]:gap-5" data-ui="ref-card" data-kind="${kind}">
  <div class="@min-[900px]:col-start-1 @min-[900px]:row-start-2" data-keys></div>
  <div class="flex justify-center overflow-hidden rounded-2xl bg-raised px-2 py-3 text-cream [&_svg]:h-auto [&_svg]:max-w-full @min-[900px]:col-start-2 @min-[900px]:row-start-2 @min-[900px]:items-center" data-ui="ref-staff"></div>
  <div class="flex flex-wrap items-center gap-2 @min-[900px]:col-span-2 @min-[900px]:row-start-1 @min-[900px]:gap-3" data-row>
    <div class="flex flex-wrap gap-2" data-plays></div>
    <div class="contents @min-[900px]:ms-auto @min-[900px]:flex @min-[900px]:items-center @min-[900px]:gap-3" data-row-end>
      <div class="ms-auto flex items-center gap-2" data-root hidden>
        <button type="button" class="btn-secondary size-11 rounded-full p-0 text-lg" data-root-step="-1">−</button>
        <span class="min-w-12 text-center font-bold tabular-nums" translate="no" data-root-name></span>
        <button type="button" class="btn-secondary size-11 rounded-full p-0 text-lg" data-root-step="1">+</button>
      </div>
      <div class="mt-2 basis-full empty:hidden @min-[900px]:mt-0 @min-[900px]:basis-auto" data-tuning></div>
    </div>
  </div>
  <dl class="grid gap-x-4 gap-y-1.5 text-sm sm:grid-cols-[auto_minmax(0,1fr)] @min-[900px]:col-span-2 @min-[900px]:flex @min-[900px]:flex-wrap @min-[900px]:gap-0 @min-[900px]:border-y @min-[900px]:border-line" data-ui="ref-facts"></dl>
</section>
</div>`;
    const $ = (sel) => el.querySelector(sel);
    const sounding = new Set();
    const stop = () => {
        for (const tone of sounding) tone.stop(0);
        sounding.clear();
    };
    const play = (notes) => {
        stop();
        const c = audio();
        c.resume?.();
        const at = c.currentTime + 0.06;
        for (const n of notes) {
            const tone = note(n.hz, { at: at + n.at, length: n.length, level: n.level ?? CARD.level });
            sounding.add(tone);
            tone.ended.then(() => sounding.delete(tone));
        }
    };
    const button = (key, label, onClick) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn-secondary min-h-11 gap-2 rounded-full px-4 text-[0.9375rem]';
        b.dataset.ui = 'ref-play';
        b.dataset.play = key;
        b.innerHTML = '<span class="h-3 w-2.5 shrink-0 bg-current [clip-path:polygon(0_0,100%_50%,0_100%)]" aria-hidden="true"></span><span></span>';
        b.lastElementChild.textContent = label ?? '';
        b.addEventListener('click', onClick);
        $('[data-plays]').append(b);
        return b;
    };
    const facts = (rows) => {
        $('[data-ui="ref-facts"]').replaceChildren(...rows.flatMap(([term, detail, hook]) => {
            const dt = document.createElement('dt');
            dt.className = 'text-muted';
            dt.textContent = term ?? '';
            const dd = document.createElement('dd');
            dd.className = 'font-semibold tabular-nums';
            dd.textContent = detail ?? '';
            if (hook) dd.dataset.fact = hook;
            const pair = document.createElement('div');
            pair.className = 'contents @min-[900px]:block @min-[900px]:px-5 @min-[900px]:py-3 @min-[900px]:first:ps-0 @min-[900px]:[&+&]:border-s @min-[900px]:[&+&]:border-line';
            pair.append(dt, dd);
            return [pair];
        }));
    };
    const staff = (notes, label) => $('[data-ui="ref-staff"]').replaceChildren(grandStaff(notes, { label }));
    // The root, a semitone at a time within CARD.roots.
    const rooted = (start, words, onChange) => {
        let root = start;
        $('[data-root]').hidden = false;
        const down = $('[data-root-step="-1"]');
        const up = $('[data-root-step="1"]');
        down.setAttribute('aria-label', words.lower ?? '');
        up.setAttribute('aria-label', words.higher ?? '');
        const show = () => {
            $('[data-root-name]').textContent = spelledName(spellRoot(root));
            down.disabled = root <= CARD.roots[0];
            up.disabled = root >= CARD.roots[1];
        };
        for (const b of [down, up]) {
            b.addEventListener('click', () => {
                root = Math.max(CARD.roots[0], Math.min(CARD.roots[1], root + Number(b.dataset.rootStep)));
                stop();
                show();
                onChange(root);
            });
        }
        show();
        return () => root;
    };
    // Equal or pure (Hillel's ear courses: hear the difference); the choice is the card's for this visit.
    const tuning = (words, onChange) => {
        let current = 'equal';
        const s = segmented($('[data-tuning]'), [
            { key: 'equal', label: words.equal ?? '' },
            { key: 'pure', label: words.pure ?? '' },
        ], { current, label: words.tuning ?? '', pick: (key) => { current = key; s.set(key); onChange(key); } });
        return () => current;
    };
    return { $, play, stop, button, facts, staff, rooted, tuning, keys: (options) => keyboard($('[data-keys]'), options) };
}

/**
 * An interval card: `semis` (0 to 12) above `root` (MIDI), `name` to pick the pure seventh (just.js's
 * 'harmonicSeventh' 7:4 or 'minorSeventh' 9:5). Words: up, down, together, tuning, equal, pure, lower, higher,
 * notes (":low to :high"), equalLabel, equalCents (":cents cents"), pureLabel ("Pure, :ratio"), pureCents
 * (":cents cents, :diff narrower" / wider / same: pureNarrower, pureWider, pureSame), keyboard, staff.
 */
export function mountIntervalCard(el, { semis, name = null, root = 60, words = {} } = {}) {
    const card = frame(el, 'interval');
    const sizes = intervalSizes(semis, name);
    const pureHz = (r) => hz(r) * (sizes.ratio ? sizes.ratio[0] / sizes.ratio[1] : 2 ** (semis / 12));
    const top = (r) => (getTuning() === 'pure' ? pureHz(r) : hz(r + semis));
    const draw = (r) => {
        const bottom = spellRoot(r);
        const upper = spellAbove(bottom, semis, INTERVAL_LETTERS[semis] ?? 0);
        const low = spelledName(bottom);
        const high = spelledName(upper);
        const label = fill(words.notes, { low, high });
        const range = { low: Math.min(r - 2, r + semis - 12), high: Math.max(r + semis + 2, r + 12) };
        card.keys({ ...range, mark: [r, r + semis], names: { [r]: low, [r + semis]: high }, label: fill(words.keyboard, { low, high }) });
        card.staff([bottom, upper, [bottom, upper]], fill(words.staff, { low, high }));
        const rows = [[words.notesLabel, label, 'notes'], [words.equalLabel, fill(words.equalCents, { cents: num(sizes.equal, 0) }), 'equal']];
        if (sizes.pure !== null) {
            const diff = Math.abs(sizes.offset);
            const how = diff < 0.05 ? words.pureSame : sizes.offset < 0 ? words.pureNarrower : words.pureWider;
            rows.push([fill(words.pureLabel, { ratio: `${sizes.ratio[0]}:${sizes.ratio[1]}` }), fill(words.pureCents, { cents: num(sizes.pure), diff: num(diff), how: fill(how, { diff: num(diff) }) }), 'pure']);
        }
        card.facts(rows);
    };
    const getRoot = card.rooted(root, words, draw);
    const getTuning = sizes.pure !== null && semis !== 0 && semis !== 12 ? card.tuning(words, () => card.stop()) : () => 'equal';
    card.button('up', words.up, () => card.play([{ hz: hz(getRoot()), at: 0, length: CARD.length }, { hz: top(getRoot()), at: CARD.step, length: CARD.length }]));
    card.button('down', words.down, () => card.play([{ hz: top(getRoot()), at: 0, length: CARD.length }, { hz: hz(getRoot()), at: CARD.step, length: CARD.length }]));
    card.button('together', words.together, () => card.play([hz(getRoot()), top(getRoot())].map((f) => ({ hz: f, at: 0, length: CARD.together, level: CARD.levelTogether }))));
    draw(root);
    return { stop: card.stop, root: getRoot, sizes };
}

/**
 * A chord card: just.js's `quality` (maj, min, dim, aug, sus2, sus4, maj7, min7, dom7, dim7, hdim7, maj6, min6,
 * add9) from `root`. Words: together, arpeggio, tuning, equal, pure, lower, higher, chord (":root :name"), name
 * (the quality's name), tones (by semitones: { 0: 'Root', 4: 'Major third', ... }), toneCents (":note: :how"),
 * pureSame, pureNarrower, pureWider (":diff cents lower/higher when pure"), keyboard, staff.
 */
export function mountChordCard(el, { quality, root = 60, words = {} } = {}) {
    const card = frame(el, 'chord');
    const tones = chordTones(quality);
    const freqs = (r) => tones.map((s) => (getTuning() === 'pure' ? hz(r) * (pureRatio(s, quality) ?? 2 ** (s / 12)) : hz(r + s)));
    const draw = (r) => {
        const notes = tones.map((s) => r + s);
        const root = spellRoot(r);
        const spelled = tones.map((s) => spellAbove(root, s, (CHORD_LETTERS_BY[quality] ?? {})[s] ?? CHORD_LETTERS[s] ?? 0));
        const names = Object.fromEntries(spelled.map((n) => [n.midi, spelledName(n)]));
        const listed = spelled.map((n) => spelledName(n)).join(', ');
        const chordLabel = fill(words.chord, { root: spelledName(root, { octave: false }), name: words.name ?? '' });
        card.keys({ low: r - 2, high: Math.max(r + 14, notes.at(-1) + 2), mark: notes, names, label: fill(words.keyboard, { chord: chordLabel, notes: listed }) });
        card.staff([...spelled, spelled], fill(words.staff, { chord: chordLabel, notes: listed }));
        card.facts(tones.map((s, i) => {
            const ratio = pureRatio(s, quality);
            const offset = ratio === null ? 0 : 1200 * Math.log2(ratio) - 100 * s;
            const diff = Math.abs(offset);
            const how = diff < 0.05 ? words.pureSame : offset < 0 ? words.pureNarrower : words.pureWider;
            return [words.tones?.[s] ?? '', fill(words.toneCents, { note: spelledName(spelled[i]), how: fill(how, { diff: num(diff) }) }), `tone-${s}`];
        }));
    };
    const getRoot = card.rooted(root, words, draw);
    const getTuning = card.tuning(words, () => card.stop());
    card.button('together', words.together, () => card.play(freqs(getRoot()).map((f) => ({ hz: f, at: 0, length: CARD.together, level: CARD.levelTogether }))));
    card.button('arpeggio', words.arpeggio, () => {
        const f = freqs(getRoot());
        card.play([
            ...f.map((one, i) => ({ hz: one, at: i * CARD.chordStep, length: CARD.chordStep * 1.1 })),
            ...f.map((one) => ({ hz: one, at: f.length * CARD.chordStep, length: CARD.together, level: CARD.levelTogether })),
        ]);
    });
    draw(root);
    return { stop: card.stop, root: getRoot, tones };
}

/**
 * A voice range card: theory.js's `type` (bass, baritone, tenor, alto, mezzo, soprano), its usual range. Words:
 * lowest, highest, scale (the notes between), range (":low to :high"), rangeLabel, part (the choir part's words
 * by CHOIR_PARTS key), partLabel, find (the link's words), keyboard, staff. `rangeTest` is the range test's address.
 */
export function mountVoiceRangeCard(el, { type, words = {}, rangeTest = '/tools/vocal-range-test' } = {}) {
    const card = frame(el, 'voice-range');
    const { low, high } = VOICE_TYPES[type];
    const range = fill(words.range, { low: named(low), high: named(high) });
    card.keys({ low: low - 4, high: high + 4, mark: [low, high], shade: span(low, high), names: { [low]: named(low), [high]: named(high) }, label: fill(words.keyboard, { range }) });
    card.staff([low, high], fill(words.staff, { range }));
    card.button('lowest', words.lowest, () => card.play([{ hz: hz(low), at: 0, length: 1.4 }]));
    card.button('highest', words.highest, () => card.play([{ hz: hz(high), at: 0, length: 1.4 }]));
    // The white notes from the lowest to the highest (and the ends, sharp or not), one after another.
    const steps = span(low, high).filter((m, i, all) => i === 0 || i === all.length - 1 || ![1, 3, 6, 8, 10].includes(m % 12));
    card.button('scale', words.scale, () => card.play(steps.map((m, i) => ({ hz: hz(m), at: i * CARD.rangeStep, length: CARD.rangeStep * 1.15 }))));
    card.facts([[words.rangeLabel, range, 'range'], [words.partLabel, words.part?.[CHOIR_PARTS[type]] ?? '', 'part']]);
    const link = document.createElement('a');
    link.className = 'btn-primary min-h-12 justify-center rounded-full px-6 text-[1rem] sm:justify-self-start @min-[900px]:col-span-2';
    link.href = rangeTest;
    link.dataset.ui = 'ref-find';
    link.textContent = words.find ?? '';
    card.$('[data-ui="ref-card"]').append(link);
    return { stop: card.stop, range: { low, high } };
}
