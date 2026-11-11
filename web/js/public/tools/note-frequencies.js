// The note frequency chart, /tools/note-frequency-chart (docs/tools-plan.md, batch 4; the review in
// docs/tools/competitors-key-finder-intonation-frequencies.md, section 3), on the tools' one layout
// (js/public/tool-layout.js). A reference people look up, so it is quick and serves anyone first
// (producers, students), singers too: a search box takes a note ("F#3", "Bb2") or a frequency ("261.6",
// the nearest note and how many cents off); the answer is big at the top (the note, its Hz, its MIDI
// number); the table fills the panel, C2 to C6 where voices live and every note C0 to B8 one tap away,
// each row a tap to hear it, with middle C, A4, where each voice type starts and ends, and the
// visitor's own saved range (from the range test) named on their rows. "Compare two" says the distance
// between two notes in cents and semitones. Behind More: A4 (415 to 466 Hz, the tuner's presets; a chip
// says it when it isn't 440, and ?a4=432 in the link sets it for the visit), note names (letters, fixed
// do, German), sharps or flats, middle C called C3 (as some software does), and copying the table.
// Middle C is C4 (scientific pitch notation), MIDI 60.
//
// A thin wrapper: the pitch maths from dsp.js (midiToHz and nearestNote with any A4), names and typed
// notes from notes.js (pitchName, parsePitch), the presets from tuner-reading.js, voice types from
// theory.js, the saved range from saved-range.js, the sound from tones.js strike() (a pure tone, on the
// audio clock). The settings stay on this device (tool-prefs.js). The words come from the page (:hz,
// :n, :note, :cents, :type, :low and :high are filled in here), set as text, never markup.
//
//   import { mountNoteFrequencies, WORDS } from '/js/public/tools/note-frequencies.js';
//   const chart = mountNoteFrequencies(el, { words, keySlot });
//
// The handle: pick(midi), find(text), play(), stop() and element. noteRows(), between() and readQuery()
// are pure, for tests and for a page that writes the table out for search engines.

import { midiToHz, nearestNote } from '../../dsp.js';
import { pageNaming, parsePitch, pitchName } from '../../notes.js';
import { VOICE_TYPES } from '../../theory.js';
import { savedRange } from '../saved-range.js';
import { strike } from '../tones.js';
import { sheetRow, stepper, toolLayout } from '../tool-layout.js';
import { toolPrefs } from '../tool-prefs.js';
import { audio } from '../voices.js';
import { REFERENCE } from '../tuner-reading.js';

/** The words a page passes, each translated. */
export const WORDS = [
    'find', 'findHint', 'noMatch', 'compare', 'pickSecond', 'playing', 'play', 'playBoth', 'stop', 'settings', 'settingsTitle', 'reset', 'done',
    'hz', 'midi', 'fromNote', 'noteCol', 'hzCol', 'midiCol', 'allNotes', 'voiceNotes', 'range', 'showAll', 'showVoices',
    'cents', 'semitones', 'middleC', 'tuningA', 'lowest', 'highest', 'yourLowest', 'yourHighest',
    'bass', 'baritone', 'tenor', 'alto', 'mezzo', 'soprano',
    'referenceLabel', 'lower', 'higher', 'referenceChip', 'naming', 'letters', 'fixedDo', 'german', 'accidentals', 'sharps', 'flats',
    'middleCAsC3', 'copyTable', 'copied',
];

export const FIRST = 12; // C0
export const LAST = 119; // B8
export const VOICES = { low: 36, high: 84 }; // C2 to C6, where voices live: the table's first view
const NOTE_SECONDS = 1.6;
const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));

/** Every note from C0 to B8 with A4 at `reference` Hz: [{ midi, name, octave, hz }]. `c3` names middle C C3. */
export function noteRows(reference = REFERENCE.standard, { naming = 'letters', flats = false, c3 = false, from = FIRST, to = LAST } = {}) {
    return Array.from({ length: to - from + 1 }, (_, i) => {
        const midi = from + i;
        const { name, octave } = pitchName(midi, { naming, flats });
        return { midi, name, octave: octave - (c3 ? 1 : 0), hz: midiToHz(midi, reference) };
    });
}

/** A frequency as the chart shows it: two decimals (16.35, 440.00, 7902.13). */
export const hzText = (hz) => hz.toFixed(2);

/** From note `a` to note `b` (MIDI): `{ semitones, cents }`, signed (up is positive). */
export function between(a, b) {
    return { semitones: b - a, cents: 100 * (b - a) };
}

/**
 * What a search means: a note ("F#3", with middle C as C3 when `c3`) as `{ midi }`, or a frequency
 * ("261.6", "261.6 Hz", "440hz") as `{ midi, hz, cents }`, the nearest note and how far off it is; null
 * when it's neither, or out of the chart.
 */
export function readQuery(text, reference = REFERENCE.standard, { c3 = false } = {}) {
    const typed = parsePitch(text);
    if (typed !== null) {
        const midi = typed + (c3 ? 12 : 0);
        return midi >= FIRST && midi <= LAST ? { midi } : null;
    }
    const m = /^\s*(\d+(?:[.,]\d+)?)\s*(?:hz)?\s*$/i.exec(text ?? '');
    if (!m) return null;
    const hz = Number(m[1].replace(',', '.'));
    const near = nearestNote(hz, reference);
    if (!(hz > 0) || near.midi < FIRST || near.midi > LAST) return null;
    return { midi: near.midi, hz, cents: Math.round(near.cents * 10) / 10 };
}

/** The settings, remembered on this device (js/public/tool-prefs.js); A4 from the link (?a4=) wins for the visit. */
const DEFAULTS = { reference: REFERENCE.standard, naming: pageNaming(), flats: false, c3: false };
const VALID = { reference: (v) => Number.isFinite(v) && v >= REFERENCE.low && v <= REFERENCE.high, naming: (v) => ['letters', 'fixed-do', 'german'].includes(v) };

const BIG_NOTE = 'text-[clamp(4rem,22vw,6.5rem)] font-black leading-none tracking-tight text-cream [@media(max-height:640px)]:text-[3.25rem]';
const PAIR_NOTE = 'text-[clamp(2.5rem,13vw,4rem)] font-black leading-none tracking-tight text-cream [@media(max-height:640px)]:text-[2.25rem]';

const SEARCH = `
<label class="block"><span class="sr-only" data-w="find"></span>
  <input type="search" class="input w-full text-center" inputmode="text" autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="go" data-ui="frequency-search" data-search>
</label>`;

const HERO = `
<div class="grid justify-items-center gap-1" translate="no">
  <p class="${BIG_NOTE}" data-ui="frequency-note" data-note></p>
  <p class="flex items-baseline gap-1.5 tabular-nums"><span class="text-[2rem] font-extrabold leading-none text-cream [@media(max-height:640px)]:text-2xl" data-ui="frequency-hz" data-hz></span><span class="text-base font-bold text-muted" data-unit></span></p>
  <p class="text-sm font-semibold tabular-nums text-muted" data-sub></p>
</div>`;

const READOUT = `
<div class="flex flex-wrap items-center justify-center gap-2">
  <button type="button" class="filter-chip" aria-pressed="false" data-ui="frequency-compare" data-compare data-w="compare"></button>
  <span class="rounded-full bg-raised px-3 py-1 text-sm font-semibold text-cream" data-chip hidden></span>
</div>`;

const PANEL = `
<div class="absolute inset-0 overflow-y-auto overscroll-contain" data-scroll>
  <table class="w-full border-collapse text-start text-[0.9375rem] tabular-nums" translate="no" data-ui="frequency-table">
    <thead class="sticky top-0 z-10 bg-raised text-xs font-bold tracking-[0.08em] text-muted uppercase">
      <tr><th class="px-4 py-2 text-start font-bold" scope="col" data-w="noteCol"></th><th class="px-4 py-2 text-end font-bold" scope="col" data-w="hzCol"></th><th class="px-4 py-2 text-end font-bold" scope="col" data-w="midiCol"></th></tr>
    </thead>
    <tbody data-rows></tbody>
  </table>
  <div class="p-3"><button type="button" class="btn-secondary btn-sm w-full" data-ui="frequency-show-all" data-show-all></button></div>
</div>`;

/** More: Hillel's settings sheet (tool-layout.js sheetRow), a hairline row each. */
function buildSheet(ui, w) {
    const reference = sheetRow(ui.sheet, { label: w('referenceLabel') });
    reference.side.innerHTML = '<span data-reference-stepper></span>';
    reference.body.innerHTML = '<div class="flex flex-wrap gap-1.5" role="group" data-presets></div>';
    sheetRow(ui.sheet, { label: w('naming') }).body.innerHTML = '<div class="flex flex-wrap gap-1.5" role="group" data-namings></div>';
    sheetRow(ui.sheet, { label: w('accidentals') }).body.innerHTML = '<div class="flex flex-wrap gap-1.5" role="group" data-accidentals></div>';
    sheetRow(ui.sheet).body.innerHTML = '<label class="check-row"><input type="checkbox" class="checkbox" data-c3 /> <span data-w="middleCAsC3"></span></label>';
    sheetRow(ui.sheet).body.innerHTML = '<button type="button" class="btn-secondary btn-sm" data-copy data-w="copyTable"></button>';
}

export function mountNoteFrequencies(root, { words = {}, keySlot = null, search = location.search } = {}) {
    const w = (key) => words[key] ?? '';
    const ui = toolLayout(root, { keySlot, more: w('settings'), done: w('done'), title: w('settingsTitle'), reset: w('reset') });
    ui.modes.innerHTML = SEARCH;
    ui.hero.innerHTML = HERO;
    ui.readout.innerHTML = READOUT;
    ui.panel.innerHTML = PANEL;
    buildSheet(ui, w);
    const card = ui.element;
    card.dataset.tool = 'note-frequencies';
    const $ = (selector) => card.querySelector(selector);
    const text = (selector, value) => ($(selector).textContent = value ?? '');
    for (const el of card.querySelectorAll('[data-w]')) el.textContent = w(el.dataset.w);
    $('[data-search]').placeholder = w('findHint');

    const prefs = toolPrefs('note-frequency-chart', { defaults: DEFAULTS, valid: VALID });
    // A link with ?a4=432 tunes the chart for this visit (it isn't kept unless the person changes it).
    const linked = Number(new URLSearchParams(search).get('a4'));
    if (linked) prefs.visit({ reference: linked });
    const settings = prefs.value;
    const yours = savedRange();
    let compare = false;
    let first = 69; // A4
    let second = null;
    let typedHz = null; // { hz, cents } when the search was a frequency
    let all = false; // every note, or C2 to C6
    let sounding = [];
    let rows = [];

    const chip = (label, data) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'filter-chip';
        b.textContent = label;
        Object.assign(b.dataset, data);
        return b;
    };
    const reference = stepper($('[data-reference-stepper]'), {
        hook: 'frequency-reference',
        label: 'A4 Hz', // i18n-ignore: a note and a unit
        name: w('referenceLabel'),
        lower: w('lower'),
        higher: w('higher'),
        step: (by) => changed({ reference: Math.max(REFERENCE.low, Math.min(REFERENCE.high, settings.reference + by)) }),
    });
    $('[data-presets]').append(...REFERENCE.presets.map((hz) => chip(String(hz), { preset: hz })));
    $('[data-namings]').append(chip(w('letters'), { naming: 'letters' }), chip(w('fixedDo'), { naming: 'fixed-do' }), chip(w('german'), { naming: 'german' }));
    $('[data-accidentals]').append(chip(w('sharps'), { flats: 'false' }), chip(w('flats'), { flats: 'true' }));
    $('[data-c3]').checked = settings.c3;

    const nameOf = (row) => `${row.name}${row.octave}`;
    const rowOf = (midi) => rows[midi - FIRST];

    /** What's said on a note's row: middle C, A4, where voice types start and end, and the saved range. */
    function marks(midi) {
        const said = [];
        if (midi === 60) said.push(w('middleC'));
        if (midi === 69) said.push(w('tuningA'));
        for (const [type, r] of Object.entries(VOICE_TYPES)) {
            if (r.low === midi) said.push(fill(w('lowest'), { type: w(type) }));
            if (r.high === midi) said.push(fill(w('highest'), { type: w(type) }));
        }
        if (yours?.low === midi) said.push(w('yourLowest'));
        if (yours?.high === midi) said.push(w('yourHighest'));
        return said.join(' · ');
    }

    /** The table, written once per setting change (a tap only moves the marks). */
    function table() {
        rows = noteRows(settings.reference, settings);
        $('[data-rows]').replaceChildren(...rows.map((row) => {
            const tr = document.createElement('tr');
            tr.dataset.midi = String(row.midi);
            // A C starts each octave: a line above it, so the octaves read as blocks.
            if (row.midi % 12 === 0) tr.className = 'border-t border-line';
            const cell = (value, className) => Object.assign(document.createElement('td'), { className, textContent: value });
            const button = Object.assign(document.createElement('button'), { type: 'button', className: 'grid w-full py-2 text-start' });
            button.dataset.pick = String(row.midi);
            button.append(Object.assign(document.createElement('span'), { className: 'font-bold', textContent: nameOf(row) }));
            const said = marks(row.midi);
            if (said) button.append(Object.assign(document.createElement('span'), { className: 'text-xs font-semibold opacity-75', textContent: said }));
            const noteCell = document.createElement('td');
            noteCell.className = 'px-4';
            noteCell.append(button);
            tr.append(noteCell, cell(hzText(row.hz), 'px-4 text-end'), cell(String(row.midi), 'px-4 text-end'));
            return tr;
        }));
        shownRows();
    }

    function shownRows() {
        for (const tr of $('[data-rows]').children) {
            const midi = Number(tr.dataset.midi);
            tr.hidden = !all && (midi < VOICES.low || midi > VOICES.high);
        }
        text('[data-show-all]', all ? fill(w('showVoices'), { low: nameOf(rowOf(VOICES.low)), high: nameOf(rowOf(VOICES.high)) }) : fill(w('showAll'), { low: nameOf(rows[0]), high: nameOf(rows.at(-1)) }));
    }

    function render() {
        card.dataset.compare = String(compare);
        const a = rowOf(first);
        const b = second === null ? null : rowOf(second);
        if (compare && b) {
            const d = between(first, second);
            $('[data-note]').className = PAIR_NOTE;
            text('[data-note]', `${nameOf(a)} → ${nameOf(b)}`);
            text('[data-hz]', `${d.cents > 0 ? '+' : ''}${d.cents}`);
            text('[data-unit]', fill(w('cents'), { n: '' }).trim());
            text('[data-sub]', `${fill(w('semitones'), { n: Math.abs(d.semitones) })} · ${hzText(a.hz)} → ${hzText(b.hz)} ${fill(w('hz'), { hz: '' }).trim()}`);
        } else if (typedHz) {
            $('[data-note]').className = BIG_NOTE;
            text('[data-note]', nameOf(a));
            text('[data-hz]', String(typedHz.hz));
            text('[data-unit]', fill(w('hz'), { hz: '' }).trim());
            const cents = `${typedHz.cents > 0 ? '+' : typedHz.cents < 0 ? '−' : ''}${Math.abs(typedHz.cents)}`;
            text('[data-sub]', fill(w('fromNote'), { cents, note: nameOf(a), hz: hzText(a.hz) }));
        } else {
            $('[data-note]').className = BIG_NOTE;
            text('[data-note]', nameOf(a));
            text('[data-hz]', hzText(a.hz));
            text('[data-unit]', fill(w('hz'), { hz: '' }).trim());
            text('[data-sub]', [fill(w('midi'), { n: a.midi }), marks(first)].filter(Boolean).join(' · '));
        }
        for (const tr of $('[data-rows]').children) {
            const midi = Number(tr.dataset.midi);
            const on = midi === first || midi === second;
            tr.classList.toggle('bg-[var(--primary)]', on);
            tr.classList.toggle('text-[var(--primary-ink)]', on);
            tr.querySelector('button').setAttribute('aria-pressed', String(on));
            tr.lastElementChild.classList.toggle('text-muted', !on); // the MIDI number, quiet unless picked
        }
        $('[data-compare]').setAttribute('aria-pressed', String(compare));
        if (sounding.length) ui.setStatus({ tone: 'good', text: w('playing') });
        else if (compare && second === null) ui.setStatus({ hint: w('pickSecond') });
        else ui.setStatus({}); // idle, the pill steps aside (the search and the rows say what to do)
        ui.setLabel(all ? w('allNotes') : w('voiceNotes'), all ? fill(w('range'), { low: nameOf(rows[0]), high: nameOf(rows.at(-1)) }) : fill(w('range'), { low: nameOf(rowOf(VOICES.low)), high: nameOf(rowOf(VOICES.high)) }));
        const playLabel = compare && second !== null ? w('playBoth') : fill(w('play'), { note: nameOf(a) });
        ui.setPrimary({ label: sounding.length ? w('stop') : playLabel, active: sounding.length > 0 });
        // A changed default shows near the readout, so it's never hidden in More.
        $('[data-chip]').hidden = settings.reference === REFERENCE.standard;
        text('[data-chip]', fill(w('referenceChip'), { hz: settings.reference }));
        reference.set(settings.reference, { atLow: settings.reference <= REFERENCE.low, atHigh: settings.reference >= REFERENCE.high });
        for (const el of card.querySelectorAll('[data-preset]')) el.setAttribute('aria-pressed', String(Number(el.dataset.preset) === settings.reference));
        for (const el of card.querySelectorAll('[data-naming]')) el.setAttribute('aria-pressed', String(el.dataset.naming === settings.naming));
        for (const el of card.querySelectorAll('[data-flats]')) el.setAttribute('aria-pressed', String((el.dataset.flats === 'true') === settings.flats));
    }

    function changed(patch) {
        Object.assign(settings, patch);
        prefs.save();
        typedHz = null;
        table();
        render();
    }

    /** Scrolls the table (only the table, never the page) so `midi`'s row sits in the middle. */
    function reveal(midi) {
        const scroll = $('[data-scroll]');
        const tr = $(`[data-rows] [data-midi="${midi}"]`);
        if (!tr || tr.hidden) return;
        scroll.scrollTop = tr.offsetTop - scroll.clientHeight / 2 + tr.offsetHeight / 2;
    }

    function stop() {
        for (const s of sounding) s.stop();
        sounding = [];
    }

    /** The chosen note (or the typed frequency), or the two: one, then the other, then both, on the audio clock. */
    function play() {
        stop();
        const t = audio().currentTime + 0.05;
        const tone = (hz, at) => strike(hz, { at, level: 0.25, decay: NOTE_SECONDS * 1.5 });
        const hzOf = (midi) => midiToHz(midi, settings.reference);
        sounding = compare && second !== null
            ? [tone(hzOf(first), t), tone(hzOf(second), t + NOTE_SECONDS), tone(hzOf(first), t + 2 * NOTE_SECONDS), tone(hzOf(second), t + 2 * NOTE_SECONDS)]
            : [tone(typedHz ? typedHz.hz : hzOf(first), t)];
        const mine = sounding;
        render();
        Promise.all(mine.map((s) => s.ended)).then(() => {
            if (sounding !== mine) return;
            sounding = [];
            render();
        });
    }

    /** Shows a note (a tap on its row, or a search): the first, or when comparing the second. */
    function show(midi) {
        typedHz = null;
        if (compare && second === null && midi !== first) second = midi;
        else {
            first = midi;
            second = null;
        }
        if (!all && (midi < VOICES.low || midi > VOICES.high)) {
            all = true;
            shownRows();
        }
        render();
        reveal(midi);
    }

    function pick(midi) {
        show(midi);
        play();
    }

    /** A search: a note or a frequency. Returns whether it was understood. */
    function find(query) {
        const found = readQuery(query, settings.reference, settings);
        $('[data-search]').setAttribute('aria-invalid', String(!found && !!query.trim()));
        if (!found) {
            if (query.trim()) ui.setStatus({ tone: 'warn', text: w('noMatch') });
            return false;
        }
        compare = false;
        second = null;
        show(found.midi);
        if (found.hz) {
            typedHz = { hz: found.hz, cents: found.cents };
            render();
        }
        return true;
    }

    // More's Reset: A4, the names and the spelling back to their defaults.
    ui.onReset(() => {
        prefs.reset();
        $('[data-c3]').checked = settings.c3;
        typedHz = null;
        table();
        render();
    });
    card.addEventListener('click', (event) => {
        const b = event.target.closest('button');
        if (!b || !card.contains(b)) return;
        if (b.dataset.pick) pick(Number(b.dataset.pick));
        else if (b.dataset.preset) changed({ reference: Number(b.dataset.preset) });
        else if (b.dataset.naming) changed({ naming: b.dataset.naming });
        else if (b.dataset.flats) changed({ flats: b.dataset.flats === 'true' });
    });
    $('[data-search]').addEventListener('input', (event) => {
        // A note shows as it's typed; a frequency is only read once it looks whole (Enter plays either).
        const value = event.target.value;
        if (readQuery(value, settings.reference, settings)) find(value);
        else $('[data-search]').removeAttribute('aria-invalid');
    });
    $('[data-search]').addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        if (find(event.target.value)) play();
    });
    $('[data-compare]').addEventListener('click', () => {
        compare = !compare;
        second = null;
        typedHz = null;
        render();
    });
    $('[data-show-all]').addEventListener('click', () => {
        all = !all;
        shownRows();
        reveal(first);
    });
    $('[data-c3]').addEventListener('change', (event) => changed({ c3: event.target.checked }));
    $('[data-copy]').addEventListener('click', async () => {
        const lines = [[w('noteCol'), w('hzCol'), w('midiCol')], ...rows.map((r) => [nameOf(r), hzText(r.hz), r.midi])].map((cells) => cells.join('\t'));
        try {
            await navigator.clipboard.writeText(lines.join('\n'));
            text('[data-copy]', w('copied'));
        } catch (error) {
            console.warn('The table was not copied', error);
        }
    });
    ui.onPrimary(() => {
        if (!sounding.length) return play();
        stop();
        render();
    });
    table();
    render();
    requestAnimationFrame(() => reveal(first));
    return {
        pick,
        find,
        play,
        stop() {
            stop();
            reference.destroy();
        },
        element: card,
    };
}
