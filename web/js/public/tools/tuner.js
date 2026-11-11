// The free tuner (/tools/tuner, docs/tools-plan.md): Hillel's tuner v3 (shots-review/designs/hillel/
// tuner-v3), on the tools' one layout (tool-layout.js), over the competitor review's spec
// (docs/tools/competitors-tuner-metronome-range.md, "UX spec (tuner)"). The brand row holds only the
// logo and the Theme button (Hillel: "I think you can assume 440, maybe we move it to behind the more
// button"); Voice | Instrument are the modes; the hero is the note
// with its octave and its neighbours labelled lower and higher (the one you're drifting toward
// brightens); the status pill says the state and what to do ("● In tune · Hold it there"; green in
// tune, amber out, grey paused, never colour alone); the readout is a meter from −50 to +50 cents with
// the in-tune zone and a glowing needle, the cents big in the state's colour beside the Hz; an
// instrument's strings light green when in tune; the panel is the last 8 seconds with how much of it
// was in tune; the bar is Listen / Stop listening and More, whose sheet holds the reference A (a
// stepper and presets; a chip near the readout says it when it isn't 440), transposition, instrument and tuning, note names, sensitivity and the zone. Any setting that isn't
// the default shows as a chip.
//
// A thin wrapper (docs/tools-plan.md, "Shared modules"): the mic and the app's pitch tracker come
// from live-mic.js, what to show from tuner-reading.js, note names from notes.js, the strings'
// notes from voices.js, the screen kept on by wake-lock.js. The mic opens only on Start; nothing is
// recorded, kept or sent. The settings stay on this device. The words come from the page (its view
// translates them), never from here.
//
//   import { mountTuner, WORDS } from '/js/public/tools/tuner.js';
//   const tuner = mountTuner(el, { words, keySlot, onError: (reason) => ... });   // words: every key in WORDS
//
// keySlot is accepted like every tool's (the brand row's [data-tool-key]); the tuner leaves it empty.
//
// `tuner.stop()` lets the mic go. onError(reason) hears 'denied', 'no-mic' or 'unsupported'. `open`
// stands in for the mic in tests: a function like listen() that hands back onFrame and returns { stop }.

import { listen, reasonOf, TRACKER_FILES } from '../live-mic.js';
import { toolPrefs } from '../tool-prefs.js';
import { segmented, sheetRow, stepper, toolLayout } from '../tool-layout.js';
import { audio, voice } from '../voices.js';
import { keepAwake } from '../wake-lock.js';
import { midiToHz } from '../../dsp.js';
import { pageNaming, pitchName } from '../../notes.js';
import { REFERENCE, stepTuner, TUNER, tunerReading } from '../tuner-reading.js';

// The pitch tracker's files, kept offline by the tools' service worker (page.js) before a first Listen or Start.
export const WARM = TRACKER_FILES;

/** The words a page passes, each translated (:cents, :hz, :note and :key are filled in here). */
export const WORDS = [
    'start', 'stop', 'listening', 'paused', 'resume', 'idleHint', 'tooQuiet', 'lastNote', 'inTune', 'hold', 'nearly',
    'sharp', 'flat', 'singDown', 'singUp', 'tuneDown', 'tuneUp', 'loosen', 'tighten',
    'cents', 'hz', 'noMic', 'voice', 'instrument', 'modes', 'settings', 'done', 'trace', 'inTuneShare', 'traceSharp', 'traceFlat', 'lowerNote', 'higherNote',
    'spokenInTune', 'spokenFlat', 'spokenSharp',
    'referenceLabel', 'reference', 'lower', 'higher', 'transposition', 'concert', 'inKey',
    'instrumentLabel', 'chromatic', 'guitar', 'bass4', 'bass5', 'ukulele', 'violin', 'viola', 'cello', 'mandolin', 'banjo',
    'strings', 'stringHint', 'tuningLabel', 'standard', 'dropD', 'halfDown', 'dropC', 'dadgad', 'openG', 'openD', 'openE',
    'naming', 'letters', 'fixedDo', 'german', 'sensitivity', 'sensitivityLow', 'sensitivityHigh', 'zone', 'zoneValue',
    'settingsTitle', 'reset', 'referenceHint', 'soundingPitch', 'writtenPitch', 'sensLow', 'sensMedium', 'sensHigh', 'zoneStrict', 'zoneForgiving', 'zoneShort',
];

export { REFERENCE } from '../tuner-reading.js';
// A transposing instrument reads its written note: a B♭ trumpet's C sounds as concert B♭.
export const TRANSPOSITIONS = { C: 0, 'B♭': 2, 'E♭': 9, F: 7, G: 5, A: 3 };
const FLAT_KEYS = new Set(['B♭', 'E♭', 'F']);
// Each instrument's open strings (concert MIDI, lowest first); a guitar's by tuning.
export const GUITAR_TUNINGS = {
    standard: [40, 45, 50, 55, 59, 64], dropD: [38, 45, 50, 55, 59, 64], halfDown: [39, 44, 49, 54, 58, 63], dropC: [36, 43, 48, 53, 57, 62],
    dadgad: [38, 45, 50, 55, 57, 62], openG: [38, 43, 50, 55, 59, 62], openD: [38, 45, 50, 54, 57, 62], openE: [40, 47, 52, 56, 59, 64],
};
export const INSTRUMENTS = {
    chromatic: null, guitar: 'guitar', bass4: [28, 33, 38, 43], bass5: [23, 28, 33, 38, 43], ukulele: [67, 60, 64, 69],
    violin: [55, 62, 69, 76], viola: [48, 55, 62, 69], cello: [36, 43, 50, 57], mandolin: [55, 62, 69, 76], banjo: [67, 50, 55, 59, 62],
};
// How low and high the tracker looks: a voice from a bass's low notes to a soprano's C6; an instrument
// from a five-string bass's B0 (31 Hz, which needs a longer window) to a flute's or violin's top.
const REACH = { voice: { minHz: 60, maxHz: 1100, window: 2048 }, instrument: { minHz: 28, maxHz: 1500, window: 4096 } };
const SENSITIVITY = { min: -75, max: -35, standard: -60 }; // the quietest level that counts, dBFS
const ZONE = { min: 2, max: 15 };
const DEFAULTS = { mode: 'voice', reference: REFERENCE.standard, transposition: 'C', instrument: 'chromatic', tuning: 'standard', naming: pageNaming(), quietDb: SENSITIVITY.standard, zone: null };

// The page's words fill :names, as Laravel's language files write them (one regex, so :note never eats into :notes).
const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));
const signed = (n) => (n > 0 ? `+${n}` : n < 0 ? `−${-n}` : '±0');

// Each setting's check: the store keeps a saved value only when it passes (tool-prefs.js).
const VALID = {
    mode: (v) => ['voice', 'instrument'].includes(v),
    reference: (v) => Number.isFinite(v) && v >= REFERENCE.low && v <= REFERENCE.high,
    transposition: (v) => typeof v === 'string' && v in TRANSPOSITIONS,
    instrument: (v) => typeof v === 'string' && v in INSTRUMENTS,
    tuning: (v) => typeof v === 'string' && v in GUITAR_TUNINGS,
    naming: (v) => ['letters', 'fixed-do', 'german'].includes(v),
    quietDb: (v) => Number.isFinite(v) && v >= SENSITIVITY.min && v <= SENSITIVITY.max,
    zone: (v) => v === null || (Number.isFinite(v) && v >= ZONE.min && v <= ZONE.max),
};

/** A variant page's preset as the tuner's visit values: an instrument (not chromatic) implies the Instrument mode. */
export function presetOf({ mode = null, instrument = null, tuning = null } = {}) {
    const values = {};
    if (instrument) values.instrument = instrument;
    if (tuning) values.tuning = tuning;
    if (mode) values.mode = mode;
    else if (instrument && instrument !== 'chromatic' && instrument in INSTRUMENTS) values.mode = 'instrument'; // an unknown one changes nothing
    return values;
}

/** An instrument's open strings (concert MIDI), or null for chromatic. */
export function stringsOf(instrument, tuning = 'standard') {
    const strings = INSTRUMENTS[instrument];
    if (strings === 'guitar') return GUITAR_TUNINGS[tuning] ?? GUITAR_TUNINGS.standard;
    return strings ?? null;
}

// The hero: the note, its sharp or flat and octave, the neighbours either side.
const HERO = `
<div class="grid w-full grid-cols-[1fr_auto_1fr] items-center pt-2 [@media(max-height:640px)]:pt-0" translate="no">
  <span class="grid justify-items-start gap-0.5 transition-opacity" data-prev-block>
    <span class="text-[1.75rem] font-extrabold transition-colors" data-prev data-ui="tuner-neighbours"></span>
    <span class="text-[0.6875rem] font-semibold tracking-[0.06em] text-muted uppercase" data-w="lowerNote"></span>
  </span>
  <span class="flex items-end leading-[0.8] transition-opacity" data-ui="tuner-note">
    <span class="my-8 block h-3 w-32 rounded-full bg-raised md:my-12" aria-hidden="true" data-placeholder></span>
    <span class="text-[clamp(5.5rem,30vw,9.375rem)] font-black tracking-[-0.05em] text-cream [@media(max-height:640px)]:text-[5.5rem]" data-name></span>
    <span class="mb-1 ms-1 flex flex-col items-start leading-none">
      <span class="text-[2.25rem] font-extrabold text-cream [@media(max-height:640px)]:text-2xl" data-acc></span>
      <span class="text-[2.25rem] font-extrabold tabular-nums text-muted [@media(max-height:640px)]:text-2xl" data-octave></span>
    </span>
  </span>
  <span class="grid justify-items-end gap-0.5 transition-opacity" data-next-block>
    <span class="text-[1.75rem] font-extrabold transition-colors" data-next data-ui="tuner-neighbours"></span>
    <span class="text-[0.6875rem] font-semibold tracking-[0.06em] text-muted uppercase" data-w="higherNote"></span>
  </span>
</div>
<p class="sr-only" aria-live="polite" data-live></p>`;

// The readout: the meter, the cents and Hz, any non-default settings, an instrument's strings.
const READOUT = `
<p class="-mt-2 text-[0.8125rem] font-medium tabular-nums text-muted" translate="no" data-idle-ref hidden></p>
<div class="grid gap-2.5 transition-opacity" data-meter-block>
  <div class="relative h-[4.5rem] [@media(max-height:640px)]:h-12" role="meter" aria-valuemin="-50" aria-valuemax="50" aria-valuenow="0" data-ui="tuner-meter">
    <div class="absolute inset-x-0 top-1/2 h-0.5 -translate-y-1/2 rounded-full bg-raised" aria-hidden="true"></div>
    <div class="absolute inset-y-2 -translate-x-1/2 rounded-[10px] bg-good/15 shadow-[inset_0_0_0_1px_color-mix(in_oklab,var(--color-good)_30%,transparent)] transition-colors" style="left:50%" data-band data-ui="tuner-band"></div>
    <div class="absolute inset-0 flex items-center justify-between" aria-hidden="true" data-ticks></div>
    <div class="absolute inset-y-0 w-1 -translate-x-1/2 rounded-full bg-current text-muted shadow-[0_0_18px_currentColor] transition-[left,color] duration-100" style="left:50%" data-needle data-ui="tuner-needle"></div>
  </div>
  <div class="grid min-h-9 grid-cols-[1fr_auto_1fr] items-baseline tabular-nums">
    <span class="justify-self-start text-[0.8125rem] font-semibold text-muted" aria-hidden="true" translate="no">−50¢ ♭</span>
    <span class="flex items-baseline gap-2.5"><span class="text-[0.8125rem] font-semibold text-muted" aria-hidden="true" data-zero>0</span><b class="min-w-[4.5rem] text-end text-[1.75rem] font-extrabold empty:hidden" data-cents></b><span class="text-[0.9375rem] font-semibold text-muted empty:hidden" data-hz></span></span>
    <span class="justify-self-end text-[0.8125rem] font-semibold text-muted" aria-hidden="true" translate="no">♯ +50¢</span>
  </div>
</div>
<div class="flex flex-wrap items-center justify-center gap-1.5 empty:hidden" data-ui="tuner-summary"></div>`;

// Under the main card on a wide screen: an instrument's strings, each found by itself or locked by a tap.
const EXTRA = `
<div class="grid gap-2 text-start" data-ui="tuner-strings" hidden>
  <span class="text-xs font-bold tracking-[0.12em] text-muted uppercase" data-strings-label></span>
  <div class="grid gap-1.5" role="group" data-strings></div>
  <p class="text-xs text-muted" data-string-hint></p>
</div>`;

// The panel: the last 8 seconds, sharp at the top and flat at the bottom.
const PANEL = `
<canvas class="absolute inset-0 block size-full" role="img" data-trail data-ui="tuner-trace"></canvas>
<span class="absolute start-3.5 top-2.5 text-xs font-bold text-muted" aria-hidden="true" data-w="traceSharp"></span>
<span class="absolute bottom-2.5 start-3.5 text-xs font-bold text-muted" aria-hidden="true" data-w="traceFlat"></span>`;

/** More's sheet (Hillel's tuner settings): a row each, label and hint at the left, the control at the right or under. */
function buildSheet(ui, w) {
    const reference = sheetRow(ui.sheet, { label: w('referenceLabel'), hint: w('referenceHint') });
    reference.side.dataset.referenceSide = '';
    // All seven presets on one row (Hillel: never one left alone on a second line); a phone too narrow
    // scrolls the row sideways, faded at the edge.
    reference.body.innerHTML = '<div class="flex gap-1 overflow-x-auto [scrollbar-width:none] data-[overflow]:[mask-image:linear-gradient(to_right,black_85%,transparent)]" role="group" data-presets></div>';
    const transposition = sheetRow(ui.sheet, { label: w('transposition') });
    transposition.side.dataset.transpositionSide = '';
    transposition.body.dataset.transpositions = '';
    sheetRow(ui.sheet).body.innerHTML = `<div class="grid grid-cols-2 gap-3">
      <label class="block min-w-0"><span class="label" data-w="instrumentLabel"></span><select class="input mt-2" data-instrument data-ui="tuner-instrument"></select></label>
      <label class="block min-w-0" data-tuning-field><span class="label" data-w="tuningLabel"></span><select class="input mt-2" data-tuning data-ui="tuner-tuning"></select>
        <span class="mt-1.5 block text-xs font-semibold tracking-[0.25em] text-muted" translate="no" data-tuning-strings></span></label>
    </div>`;
    sheetRow(ui.sheet, { label: w('naming') }).body.dataset.namings = '';
    sheetRow(ui.sheet).body.innerHTML = `<div class="grid gap-5 sm:grid-cols-2">
      <label class="block"><span class="flex items-baseline justify-between gap-2"><span class="label" data-w="sensitivity"></span><b class="text-[0.8125rem] font-semibold text-cream" data-sensitivity-value></b></span>
        <input type="range" class="range" min="${-SENSITIVITY.max}" max="${-SENSITIVITY.min}" step="1" data-sensitivity data-ui="tuner-sensitivity">
        <span class="flex justify-between gap-2 text-xs text-muted"><span data-w="sensitivityLow"></span><span data-w="sensitivityHigh"></span></span></label>
      <label class="block"><span class="flex items-baseline justify-between gap-2"><span class="label" data-w="zone"></span><b class="text-[0.8125rem] font-semibold tabular-nums text-cream" data-zone-value></b></span>
        <input type="range" class="range" min="${ZONE.min}" max="${ZONE.max}" step="1" data-zone data-ui="tuner-zone">
        <span class="flex justify-between gap-2 text-xs text-muted"><span data-w="zoneStrict"></span><span data-w="zoneForgiving"></span></span></label>
    </div>`;
}

export function mountTuner(root, { words = {}, keySlot = null, onError = null, onLesson = null, deviceId = '', open = listen, a4 = null, preset = null } = {}) {
    const w = (key) => words[key] ?? '';
    const ui = toolLayout(root, { keySlot, more: w('settings'), done: w('done'), title: w('settingsTitle'), reset: w('reset') });
    ui.hero.innerHTML = HERO;
    ui.readout.innerHTML = READOUT;
    ui.extra.innerHTML = EXTRA;
    ui.panel.innerHTML = PANEL;
    buildSheet(ui, w);
    ui.primary.dataset.start = '';
    const $ = (sel) => root.querySelector(sel);
    const card = ui.element;
    card.dataset.tool = 'tuner';
    // The settings, remembered on this device (tool-prefs.js); A4 from the link (?a4=) wins for the visit.
    const prefs = toolPrefs('tuner', { defaults: DEFAULTS, valid: VALID });
    if (a4 !== null) prefs.visit({ reference: Number(a4) });
    // A variant page's preset (docs/seo-growth-plan.md, item 3: the ukulele tuner, the violin tuner, the voice tuner):
    // `{ mode, instrument, tuning }` shown first for this visit, never over what the person saved (an instrument
    // implies the Instrument mode). A value the tuner doesn't know is ignored.
    if (preset) prefs.visit(presetOf(preset));
    const settings = prefs.value;
    let session = null;
    let awake = null;
    let state = tunerReading();
    let view = null;
    let target = null; // a string tapped: the tuner measures from it
    let quietUntil = 0; // while a string's note plays, the mic's readings are its own sound: ignored
    let started = false; // listened once: stopped now reads as paused
    let spokenAt = -Infinity;
    let tunedSince = null; // a sung note in tune since (s, the mic's clock), for the page's lesson line
    let spoken = '';

    for (const el of root.querySelectorAll('[data-w]')) el.textContent = w(el.dataset.w);
    const modes = segmented(ui.modes, [{ key: 'voice', label: w('voice') }, { key: 'instrument', label: w('instrument') }], {
        current: settings.mode,
        label: w('modes'),
        pick: (mode) => {
            settings.mode = mode;
            changed({ restart: true });
        },
    });
    const reference = stepper($('[data-reference-side]'), {
        hook: 'tuner-reference',
        label: '', // the row says Reference pitch / Frequency of A4: the stepper says only "440 Hz"
        name: w('referenceLabel'),
        lower: w('lower'),
        higher: w('higher'),
        step: (by) => {
            settings.reference = Math.max(REFERENCE.low, Math.min(REFERENCE.high, settings.reference + by));
            changed();
        },
    });
    $('[data-trail]').setAttribute('aria-label', w('trace'));
    $('[data-string-hint]').textContent = w('stringHint');
    $('[data-ui=tuner-strings] [role=group]').setAttribute('aria-label', w('strings'));
    // A tick every 10 cents: the centre tallest, the ends next, the rest short.
    $('[data-ticks]').append(...Array.from({ length: 11 }, (_, i) => {
        const tick = document.createElement('span');
        tick.className = i === 5 ? 'h-14 w-0.5 rounded-full bg-cream/80' : i === 0 || i === 10 ? 'h-9 w-0.5 rounded-full bg-line' : 'h-5 w-0.5 rounded-full bg-line/60';
        return tick;
    }));
    const chip = (label, data) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'filter-chip';
        b.textContent = label;
        Object.assign(b.dataset, data);
        return b;
    };
    $('[data-presets]').append(...REFERENCE.presets.map((hz) => {
        const b = chip(String(hz), { preset: hz });
        b.classList.add('shrink-0', 'px-2.5', 'tabular-nums');
        return b;
    }));
    // The fade shows only when the row runs past the edge.
    const fadePresets = () => {
        const row = $('[data-presets]');
        row.toggleAttribute('data-overflow', row.scrollWidth > row.clientWidth + 1);
    };
    ui.more.addEventListener('click', () => requestAnimationFrame(fadePresets));
    const transpositions = segmented($('[data-transpositions]'), Object.keys(TRANSPOSITIONS).map((key) => ({ key, label: key === 'C' ? w('concert') : key })), {
        current: settings.transposition,
        label: w('transposition'),
        pick: (key) => {
            settings.transposition = key;
            changed();
        },
    });
    const namings = segmented($('[data-namings]'), [{ key: 'letters', label: w('letters') }, { key: 'fixed-do', label: w('fixedDo') }, { key: 'german', label: w('german') }], {
        current: settings.naming,
        label: w('naming'),
        pick: (naming) => {
            settings.naming = naming;
            changed();
        },
    });
    // The transpositions and note names keep their own handles for tests and the arcade skin.
    for (const b of $('[data-transpositions]').querySelectorAll('[data-segment]')) b.dataset.key = b.dataset.segment;
    for (const b of $('[data-namings]').querySelectorAll('[data-segment]')) b.dataset.naming = b.dataset.segment;
    // Reset puts every setting back to its default; the mode stays as it is.
    // Reset forgets the saved settings: everything back to its default.
    ui.onReset(() => {
        const was = settings.mode;
        prefs.reset();
        changed({ restart: settings.mode !== was, keep: true });
    });
    $('[data-instrument]').append(...Object.keys(INSTRUMENTS).map((key) => new Option(w(key), key)));
    $('[data-tuning]').append(...Object.keys(GUITAR_TUNINGS).map((key) => new Option(w(key), key)));

    const zoneOf = () => settings.zone ?? TUNER.tolerance[settings.mode];
    /** A concert note as the player reads it: its name in their naming, transposed for their instrument. */
    const named = (midi) => pitchName(midi + TRANSPOSITIONS[settings.transposition], { naming: settings.naming, flats: FLAT_KEYS.has(settings.transposition) });
    const strings = () => (settings.mode === 'instrument' ? stringsOf(settings.instrument, settings.tuning) : null);

    function renderSettings() {
        modes.set(settings.mode);
        reference.set(fill(w('hz'), { hz: settings.reference }), { atLow: settings.reference <= REFERENCE.low, atHigh: settings.reference >= REFERENCE.high });
        for (const b of root.querySelectorAll('[data-preset]')) b.setAttribute('aria-pressed', String(Number(b.dataset.preset) === settings.reference));
        transpositions.set(settings.transposition);
        namings.set(settings.naming);
        $('[data-transposition-side]').textContent = w(settings.transposition === 'C' ? 'soundingPitch' : 'writtenPitch');
        $('[data-instrument]').value = settings.instrument;
        $('[data-tuning]').value = settings.tuning;
        $('[data-tuning-field]').hidden = settings.instrument !== 'guitar';
        // The strings the instrument is tuned to, small under its tuning (E A D G B E).
        $('[data-tuning-strings]').textContent = (stringsOf(settings.instrument, settings.tuning) ?? []).map((m) => named(m).name).join(' ');
        const sens = $('[data-sensitivity]');
        sens.value = String(-settings.quietDb);
        sens.style.setProperty('--p', `${((-settings.quietDb - -SENSITIVITY.max) / (SENSITIVITY.max - SENSITIVITY.min)) * 100}%`);
        const zone = $('[data-zone]');
        zone.value = String(zoneOf());
        zone.style.setProperty('--p', `${((zoneOf() - ZONE.min) / (ZONE.max - ZONE.min)) * 100}%`);
        $('[data-zone-value]').textContent = fill(w('zoneShort'), { cents: zoneOf() });
        // Sensitivity in a word: the quietest third of the slider, the middle, the loudest.
        const share = (-settings.quietDb - -SENSITIVITY.max) / (SENSITIVITY.max - SENSITIVITY.min);
        $('[data-sensitivity-value]').textContent = w(share < 1 / 3 ? 'sensLow' : share < 2 / 3 ? 'sensMedium' : 'sensHigh');
        // The first screen's row: only what isn't the default, so a changed setting is never hidden (a
        // reference A other than 440 included; an instrument names itself over its strings).
        const summary = [];
        if (settings.reference !== REFERENCE.standard) summary.push(fill(w('reference'), { hz: settings.reference }));
        if (settings.transposition !== 'C') summary.push(fill(w('inKey'), { key: settings.transposition }));
        if (settings.naming !== 'letters') summary.push(w(settings.naming === 'fixed-do' ? 'fixedDo' : 'german'));
        if (settings.quietDb !== SENSITIVITY.standard) summary.push(`${w('sensitivity')} ${Math.round(((-settings.quietDb + SENSITIVITY.max) / (SENSITIVITY.max - SENSITIVITY.min)) * 100)}%`);
        if (settings.zone !== null) summary.push(fill(w('zoneValue'), { cents: settings.zone }));
        $('[data-ui=tuner-summary]').replaceChildren(...summary.map((text) => {
            const b = chip(text, { summary: '' });
            b.addEventListener('click', () => ui.openSheet(true));
            return b;
        }));
        // The strings, for a stringed instrument: each named as the player reads it.
        const list = strings();
        $('[data-ui=tuner-strings]').hidden = !list;
        $('[data-strings-label]').textContent = settings.instrument === 'guitar' ? `${w('guitar')} · ${w(settings.tuning)}` : w(settings.instrument);
        if (list) {
            if (target !== null && !list.includes(target)) target = null;
            $('[data-strings]').replaceChildren(...list.map((midi) => {
                const n = named(midi);
                const b = chip(`${n.name}${n.octave}`, { string: midi });
                b.setAttribute('translate', 'no');
                b.classList.add('min-h-11', 'justify-center', 'px-1', 'tabular-nums');
                return b;
            }));
            // Every string on one row, a phone's six included.
            $('[data-strings]').style.gridTemplateColumns = `repeat(${list.length}, minmax(0, 1fr))`;
        } else target = null;
    }

    /** A note's name split for the big face: the letter (or syllable) and its sharp or flat. */
    const split = (name) => (/[♯♭]$/.test(name) ? [name.slice(0, -1), name.slice(-1)] : [name, '']);

    function render() {
        const listening = session !== null;
        const live = view?.status === 'live';
        const shown = view && view.midi !== null;
        const cents = shown ? Math.round(view.cents) : 0;
        const inTune = shown && view.inTune;
        const settling = shown && view.settling;
        card.dataset.state = !listening ? (started ? 'paused' : 'idle') : !shown ? 'listening' : inTune ? 'in-tune' : settling ? 'settling' : cents < 0 ? 'flat' : 'sharp';
        card.dataset.reading = view?.status ?? 'none';
        const midi = shown ? view.note : target;
        const note = midi !== null && midi !== undefined ? named(midi) : null;
        const [letter, acc] = note ? split(note.name) : ['', ''];
        // Idle, a quiet bar holds the note's place (Hillel's desktop tuner), with the reference under the hint.
        $('[data-placeholder]').hidden = Boolean(note);
        $('[data-name]').textContent = letter;
        $('[data-idle-ref]').hidden = listening;
        $('[data-idle-ref]').textContent = fill(w('reference'), { hz: fill(w('hz'), { hz: settings.reference }) });
        $('[data-zero]').hidden = Boolean(shown);
        $('[data-name]').classList.toggle('text-[4.5rem]', letter.length > 1); // a syllable (Sol) or a German name (Cis)
        $('[data-acc]').textContent = acc;
        $('[data-octave]').textContent = note ? String(note.octave) : '';
        $('[data-ui=tuner-note]').style.opacity = live ? '1' : shown ? '0.5' : '0.35';
        // The neighbours either side; the one the pitch is drifting toward brightens.
        const toward = live && !inTune && Math.abs(cents) > 10 ? Math.sign(cents) : 0;
        for (const [sel, step] of [['[data-prev]', -1], ['[data-next]', 1]]) {
            const el = $(sel);
            el.textContent = note ? named(midi + step).name : '';
            el.className = el.className.replace(/\btext-(muted|line|cream)\b/g, '').trim() + (toward === step ? ' text-cream' : ' text-muted');
            $(step < 0 ? '[data-prev-block]' : '[data-next-block]').style.opacity = !note ? '0' : toward === step ? '1' : '0.55';
        }
        // The state in words, a hint of what to do, and a colour that only repeats the words.
        const voiced = settings.mode === 'voice';
        const stringed = Boolean(strings());
        let status = '';
        let hint = '';
        let tone = 'muted';
        if (!listening) [status, hint] = started ? [w('paused'), w('resume')] : ['', w('idleHint')];
        else if (view?.status === 'held') status = w('lastNote');
        else if (!shown) [status, tone] = view?.quiet ? [w('tooQuiet'), 'warn'] : [w('listening'), 'muted'];
        else if (inTune) [status, hint, tone] = [w('inTune'), w('hold'), 'good'];
        else if (settling) [status, hint, tone] = [w('nearly'), w('hold'), 'good'];
        else {
            const sharp = cents > 0;
            status = w(sharp ? 'sharp' : 'flat');
            hint = w(voiced ? (sharp ? 'singDown' : 'singUp') : stringed ? (sharp ? 'loosen' : 'tighten') : sharp ? 'tuneDown' : 'tuneUp');
            tone = 'warn';
        }
        ui.setStatus({ tone, text: status, hint });
        const colour = !listening || !shown ? 'text-muted' : inTune || settling ? 'text-good' : 'text-warn';
        const band = $('[data-band]');
        band.style.width = `${zoneOf() * 2}%`;
        band.classList.toggle('bg-good/15', !(inTune && live));
        band.classList.toggle('bg-good/25', Boolean(inTune && live));
        const needle = $('[data-needle]');
        needle.style.left = `${50 + Math.max(-50, Math.min(50, cents))}%`;
        needle.className = needle.className.replace(/\btext-(muted|good|warn)\b/, colour);
        needle.style.opacity = live || !shown ? '1' : '0.6';
        const meter = $('[data-ui=tuner-meter]');
        meter.setAttribute('aria-valuenow', String(Math.max(-50, Math.min(50, cents))));
        meter.setAttribute('aria-valuetext', shown ? `${note.name}${note.octave} ${fill(w('cents'), { cents: signed(cents) })}` : '');
        $('[data-cents]').textContent = shown ? fill(w('cents'), { cents: signed(cents) }) : '';
        $('[data-cents]').className = `min-w-[4.5rem] text-end text-[1.75rem] font-extrabold empty:hidden ${colour}`;
        // Paused, the reading and the meter step back.
        $('[data-meter-block]').style.opacity = listening ? '1' : '0.45';
        $('[data-hz]').textContent = shown ? fill(w('hz'), { hz: view.hz.toFixed(1) }) : '';
        // One big action: Listen, then Stop listening (a quiet one, the reading being what matters).
        ui.setPrimary({ label: listening ? w('stop') : w('start'), active: listening });
        for (const b of root.querySelectorAll('[data-string]')) {
            const s = Number(b.dataset.string);
            // The string being tuned: the one tapped, or else the one nearest the note heard; green once in tune.
            const nearest = target === null && shown && strings()?.reduce((best, x) => (Math.abs(x - view.midi) < Math.abs(best - view.midi) ? x : best));
            const current = s === target || s === nearest;
            b.setAttribute('aria-pressed', String(current));
            b.classList.toggle('bg-good!', Boolean(current && inTune && live));
            b.classList.toggle('text-ink!', Boolean(current && inTune && live));
        }
        // The panel's label and its live stat: how much of the last 8 s was in tune, the number in green.
        const share = inTuneShare();
        let stat = '';
        if (share !== null) {
            const [before, after = ''] = w('inTuneShare').split(':percent');
            const number = document.createElement('b');
            number.className = 'font-extrabold text-good';
            const pct = after.startsWith('%') ? '%' : '';
            number.textContent = `${share}${pct}`;
            stat = document.createElement('span');
            stat.append(before, number, after.slice(pct.length));
        }
        ui.setLabel(w('trace'), stat);
        speak(note, shown, cents, inTune);
        drawTrail(colour);
    }

    // A screen reader hears the reading at most once a second, and only when it changes.
    function speak(note, shown, cents, inTune) {
        const now = performance.now();
        if (now - spokenAt < 1000) return;
        const text = !shown || view.status !== 'live' ? '' : inTune ? fill(w('spokenInTune'), { note: `${note.name}${note.octave}` })
            : fill(w(cents < 0 ? 'spokenFlat' : 'spokenSharp'), { note: `${note.name}${note.octave}`, cents: Math.abs(cents) });
        if (!text || text === spoken) return;
        spoken = text;
        spokenAt = now;
        $('[data-live]').textContent = text;
    }

    /** The share of the last 8 s sung or played inside the in-tune zone (a whole percent), null before any. */
    function inTuneShare() {
        if (!state.trail.length) return null;
        const inside = state.trail.filter((p) => Math.abs((p.midi - (target ?? Math.round(p.midi))) * 100) <= zoneOf()).length;
        return Math.round((100 * inside) / state.trail.length);
    }

    // The pitch over time: the last few seconds in cents from the note shown, ±50 tall, over the
    // in-tune band, in the state's colour.
    function drawTrail(colour) {
        const canvas = $('[data-trail]');
        const ratio = window.devicePixelRatio || 1;
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;
        if (!width || !height) return;
        if (canvas.width !== Math.round(width * ratio)) canvas.width = Math.round(width * ratio);
        if (canvas.height !== Math.round(height * ratio)) canvas.height = Math.round(height * ratio);
        const g = canvas.getContext('2d');
        g.setTransform(ratio, 0, 0, ratio, 0, 0);
        g.clearRect(0, 0, width, height);
        const css = getComputedStyle(card);
        const token = (name, fallback) => css.getPropertyValue(name).trim() || fallback;
        const y = (c) => height / 2 - (Math.max(-50, Math.min(50, c)) / 100) * height;
        g.fillStyle = token('--color-good', '#3c3');
        g.globalAlpha = 0.12;
        g.fillRect(0, y(zoneOf()), width, y(-zoneOf()) - y(zoneOf()));
        g.globalAlpha = 1;
        g.strokeStyle = token('--color-line', '#555');
        g.lineWidth = 1;
        g.beginPath();
        g.moveTo(0, height / 2);
        g.lineTo(width, height / 2);
        g.stroke();
        const centre = view?.note ?? null;
        if (centre === null || !state.trail.length) return;
        const now = state.at;
        g.strokeStyle = token(colour === 'text-good' ? '--color-good' : colour === 'text-warn' ? '--color-warn' : '--color-muted', '#3c3');
        g.lineWidth = 2.5;
        g.lineJoin = 'round';
        g.lineCap = 'round';
        g.beginPath();
        let last = null;
        for (const p of state.trail) {
            const x = width - ((now - p.at) / TUNER.trailSeconds) * width;
            const py = y((p.midi - centre) * 100);
            if (last === null || p.at - last > 0.2) g.moveTo(x, py);
            else g.lineTo(x, py);
            last = p.at;
        }
        g.stroke();
    }

    function feed(frame) {
        if (!session) return;
        if (frame.at < quietUntil) return;
        view = stepTuner(state, frame, { mode: settings.mode, reference: settings.reference, target, tolerance: zoneOf(), quietDb: settings.quietDb });
        render();
        // A sung note held in tune for 2 s (the mic's own clock) is the moment for the page's free lesson (page.js), once.
        if (view?.midi !== null && view?.inTune && view.status === 'live' && settings.mode === 'voice') {
            tunedSince ??= frame.at;
            if (onLesson && frame.at - tunedSince >= 2) {
                onLesson();
                onLesson = null;
            }
        } else tunedSince = null;
    }

    async function start() {
        if (session) return;
        session = { stop() {} }; // marked listening at once (the tap answers within a frame)
        started = true;
        awake = keepAwake();
        state = tunerReading();
        view = null;
        render();
        const reach = REACH[settings.mode];
        try {
            const opened = await open({ deviceId, ...reach, quietDb: -90, onFrame: feed });
            if (!session) {
                opened.stop(); // stopped while the mic was being asked for
                return;
            }
            session = opened;
        } catch (error) {
            session = null;
            awake?.release();
            awake = null;
            started = false;
            render();
            ui.setStatus({ tone: 'warn', text: w('noMic') });
            onError?.(error?.reason ?? reasonOf(error));
            return;
        }
        render();
    }

    function stop() {
        session?.stop?.();
        session = null;
        awake?.release();
        awake = null;
        view = null;
        render();
    }

    function changed({ restart = false, keep = false } = {}) {
        if (!keep) prefs.save();
        renderSettings();
        state = tunerReading();
        view = null;
        if (restart && session) {
            stop();
            start(); // the tracker's reach changes with the mode
        } else render();
    }


    ui.onPrimary(() => (session ? stop() : start()));
    root.addEventListener('click', (event) => {
        const b = event.target.closest('button');
        if (!b || !root.contains(b)) return;
        if (b.dataset.preset) {
            settings.reference = Number(b.dataset.preset);
            changed();
        } else if (b.dataset.string) {
            const midi = Number(b.dataset.string);
            target = target === midi ? null : midi;
            if (target !== null) {
                // Its note, a pitch pipe's reed so a low string is heard on a phone's speaker too; the
                // mic's readings are ignored while it sounds.
                voice(midiToHz(midi, settings.reference), { timbre: 'reed', level: 0.12, attack: 0.02, release: 0.1 }).stop(1.4);
                quietUntil = audio().currentTime + 1.9; // its 1.4 s and the fade, on the clock the readings carry
            }
            state = tunerReading();
            view = null;
            render();
        }
    });
    $('[data-instrument]').addEventListener('change', (event) => {
        settings.instrument = event.target.value;
        const restart = settings.instrument !== 'chromatic' && settings.mode !== 'instrument';
        if (restart) settings.mode = 'instrument';
        changed({ restart });
    });
    $('[data-tuning]').addEventListener('change', (event) => {
        settings.tuning = event.target.value;
        changed();
    });
    $('[data-sensitivity]').addEventListener('input', (event) => {
        settings.quietDb = -Number(event.target.value);
        changed();
    });
    $('[data-zone]').addEventListener('input', (event) => {
        const cents = Number(event.target.value);
        settings.zone = cents === TUNER.tolerance[settings.mode] ? null : cents;
        changed();
    });
    renderSettings();
    render();
    return {
        stop,
        element: card,
        get reference() {
            return settings.reference;
        },
        get settings() {
            return { ...settings };
        },
    };
}
