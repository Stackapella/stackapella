// The free metronome (/tools/metronome, docs/tools-plan.md), on the tools' one layout
// (tool-layout.js, Hillel's tuner v3 look), built to the competitor review's spec
// (docs/tools/competitors-tuner-metronome-range.md, "UX spec (metronome)"), to Hillel's metronome
// design (shots-review/designs/hillel/metronome). The hero is the tempo, the control people change
// most: huge, between round minus and plus that repeat while held, with "BPM · Andante" under it (tap
// the name for the list), then the slider with its scale and Tap. The panel is the bar: a tall bar a
// beat, the accent taller, numbered, with a dot a subdivision under each, lighting as they play (tap
// one: accent, normal, silent). Over the pinned Start sit the time (TIME − 4/4 +) and the subdivisions
// with their names under the notes; the status pill shows only while playing ("● Playing · Bar 3 ·
// Beat 1 of 4", the count-in). The bar is Start / Stop and More, whose sheet holds the metre's note value, the sound and three
// volumes, a count-in, the speed trainer, silent bars, a timer, groupings for odd metres, a soft
// full-screen pulse, presets on this device, and tempos up to 400. Space, T and the arrows work too.
//
// A thin wrapper (docs/tools-plan.md, "Shared modules"): what to play comes from
// metronome-beats.js, the sounds from click-sounds.js, the timing from ticker.js (the audio clock,
// never a timer, so it never drifts and keeps going in a background tab), the screen kept on by
// wake-lock.js. The words come from the page (its view translates them).
//
//   import { mountMetronome, WORDS } from '/js/public/tools/metronome.js';
//   const metronome = mountMetronome(el, { words, keySlot });   // words: every key in WORDS; keySlot: the brand row's [data-tool-key]
//
// `metronome.stop()` stops it; `metronome.playing` says whether it's going.

import { heardTime } from '../../../engine/clock.js';
import { clickSound, CLICK_SOUNDS } from '../click-sounds.js';
import { ticker } from '../ticker.js';
import { toolPrefs } from '../tool-prefs.js';
import { segmented, stepper, toolLayout } from '../tool-layout.js';
import { sound } from '../tones.js';
import { audio, reducedMotion } from '../voices.js';
import { keepAwake } from '../wake-lock.js';
import { unlock } from '../demo-player.js';
import {
    BEATS, defaultAccents, defaultGroups, groupingsOf, markingOf, MARKINGS, nextAccent, nextStep, startPosition, SUBDIVISIONS, tap, tapTempo, TEMPO,
} from '../metronome-beats.js';

/** The words a page passes, each translated (:n, :state and :name are filled in here). */
export const WORDS = [
    'play', 'stop', 'tap', 'tempo', 'bpm', 'slower', 'faster', 'markings', 'beats', 'subdivision', 'subNone', 'subEighths', 'subTriplets',
    'subSixteenths', 'beatDot', 'accent', 'normal', 'silent', 'more', 'done', 'sound', 'woodblock', 'click', 'beep', 'soft', 'cowbell',
    'volume', 'volAccent', 'volBeat', 'volSub', 'countIn', 'off', 'oneBar', 'twoBars', 'counting', 'trainer', 'trainerAdd', 'trainerEvery',
    'trainerMax', 'silentBars', 'silentPlay', 'silentMute', 'timer', 'timerMinutes', 'timeUp', 'timeLeft', 'grouping', 'visual', 'visualDots',
    'visualPulse', 'presets', 'presetName', 'save', 'remove', 'extend', 'fewerBeats', 'moreBeats', 'playing', 'beatOf', 'bar',
    'time', 'beatsPerBar', 'tapBeat', 'subNoneShort', 'subEighthsShort', 'subTripletsShort', 'subSixteenthsShort', 'settingsTitle', 'reset',
];

const PRESETS = 'stackapella.tools.metronome.presets';
const SUB_SIGNS = { none: '♩', eighths: '♫', triplets: '♫³', sixteenths: '♬' }; // i18n-ignore: note symbols
const SUB_WORDS = { none: 'subNone', eighths: 'subEighths', triplets: 'subTriplets', sixteenths: 'subSixteenths' };
const SUB_SHORT = { none: 'subNoneShort', eighths: 'subEighthsShort', triplets: 'subTripletsShort', sixteenths: 'subSixteenthsShort' };
const SCALE = [40, 120, 240]; // the slider's marks (Hillel's design)
const DEFAULTS = {
    bpm: TEMPO.standard, beats: 4, unit: 4, subdivision: 'none', accents: defaultAccents(4), groups: null, sound: 'woodblock',
    volumes: { accent: 1, beat: 1, sub: 0.8 }, countIn: 0, trainer: { on: false, add: 5, every: 4, max: 160 },
    silent: { on: false, play: 3, mute: 1 }, minutes: 0, pulse: false, extended: false,
};
const PULSE_LIMIT = 180; // above this a full-screen pulse only marks the one (three flashes a second, WCAG 2.3.1)

// The page's words fill :names, as Laravel's language files write them (one regex, so :note never eats into :notes).
const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));
const clamp = (n, lo, hi) => Math.max(lo, Math.min(hi, n));

function read(key, fallback) {
    try {
        return JSON.parse(localStorage.getItem(key) ?? 'null') ?? fallback;
    } catch {
        return fallback;
    }
}
function write(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // private browsing: kept until the page closes
    }
}

// Each setting's check (the store keeps a saved value only when it passes, tool-prefs.js), then what
// depends on more than one: a bar's accents and groups to its beats, the tempo to its range.
const VALID = {
    bpm: (v) => Number.isFinite(v) && v >= TEMPO.min && v <= TEMPO.extended,
    beats: (v) => Number.isInteger(v) && v >= BEATS.min && v <= BEATS.max,
    unit: (v) => [4, 8].includes(v),
    subdivision: (v) => typeof v === 'string' && v in SUBDIVISIONS,
    accents: (v) => Array.isArray(v) && v.every((a) => ['accent', 'normal', 'silent'].includes(a)),
    groups: (v) => v === null || (Array.isArray(v) && v.every((g) => Number.isInteger(g) && g > 0)),
    sound: (v) => CLICK_SOUNDS.includes(v),
    countIn: (v) => [0, 1, 2].includes(v),
    minutes: (v) => Number.isFinite(v) && v >= 0 && v <= 600,
};
function mend(s) {
    s.bpm = clamp(Math.round(s.bpm), TEMPO.min, s.extended ? TEMPO.extended : TEMPO.max);
    if (s.accents.length !== s.beats) s.accents = defaultAccents(s.beats);
    if (s.groups && s.groups.reduce((a, b) => a + b, 0) !== s.beats) s.groups = null;
    for (const k of ['accent', 'beat', 'sub']) s.volumes[k] = clamp(s.volumes[k], 0, 1);
    for (const k of ['add', 'every', 'max']) s.trainer[k] = Math.max(1, Math.round(s.trainer[k]));
    for (const k of ['play', 'mute']) s.silent[k] = Math.max(1, Math.round(s.silent[k]));
    s.minutes = Math.round(s.minutes);
    return s;
}

// The hero: the tempo, minus and plus either side, its Italian name under it.
const HERO = `
<div class="grid w-full grid-cols-[auto_1fr_auto] items-center gap-2">
  <button type="button" class="grid size-14 shrink-0 place-items-center rounded-full bg-raised text-3xl font-bold text-cream hover:bg-panel disabled:opacity-35 [@media(max-height:640px)]:size-12" data-nudge="-1" data-ui="metronome-slower">−</button>
  <label class="block min-w-0">
    <span class="sr-only" data-w="tempo"></span>
    <input type="number" inputmode="numeric" class="w-full min-w-0 border-0 bg-transparent p-0 text-center text-[clamp(5rem,26vw,8.5rem)] font-black leading-[0.9] tracking-[-0.04em] tabular-nums text-cream outline-none focus-visible:rounded-2xl focus-visible:ring-2 focus-visible:ring-[var(--ring)] [@media(max-height:640px)]:text-[3.75rem]" data-bpm data-ui="metronome-bpm">
  </label>
  <button type="button" class="grid size-14 shrink-0 place-items-center rounded-full bg-raised text-3xl font-bold text-cream hover:bg-panel disabled:opacity-35 [@media(max-height:640px)]:size-12" data-nudge="1" data-ui="metronome-faster">+</button>
</div>
<p class="text-base text-muted [@media(max-height:640px)]:text-sm"><span data-w="bpm"></span> <span class="text-muted" aria-hidden="true">·</span> <button type="button" class="min-h-11 font-medium text-cream underline decoration-line decoration-dotted underline-offset-4" aria-expanded="false" translate="no" data-marking data-ui="metronome-marking"></button></p>
<div class="mt-1 flex flex-wrap justify-center gap-1.5" role="group" data-markings data-ui="metronome-markings" hidden></div>`;

// The readout: the slider and Tap.
const READOUT = `
<div class="flex items-start gap-3.5">
  <span class="grid min-w-0 flex-1">
    <input type="range" class="range" step="1" data-slider data-ui="metronome-slider">
    <span class="relative -mt-1.5 block h-4 text-[0.6875rem] tabular-nums text-muted" aria-hidden="true" data-scale></span>
  </span>
  <button type="button" class="min-h-12 shrink-0 rounded-full border border-line px-5 text-base font-semibold text-cream hover:bg-raised active:bg-raised" data-tap data-ui="metronome-tap"></button>
</div>`;

// The panel: the bar's beat lights, as big as the panel allows.
const PANEL = `<div class="absolute inset-0 flex items-stretch gap-2 px-3.5 pb-3.5 pt-[1.125rem]" role="group" data-dots data-ui="metronome-dots"></div>`;

// More's sheet.
const SHEET = `
<label class="block"><span class="label" data-w="beats"></span><select class="input mt-2" data-metre data-ui="metronome-beats"></select></label>
    <div class="grid gap-3 sm:grid-cols-2">
      <label class="block"><span class="label" data-w="sound"></span><select class="input mt-2" data-sound data-ui="metronome-sound"></select></label>
      <div><p class="label" data-w="countIn"></p><span class="seg mt-2" role="group" data-countins></span></div>
    </div>
    <fieldset><legend class="label" data-w="volume"></legend>
      <div class="mt-1 grid gap-2 sm:grid-cols-3">
        <label class="block text-sm text-muted"><span data-w="volAccent"></span><input type="range" class="range" min="0" max="100" data-volume="accent"></label>
        <label class="block text-sm text-muted"><span data-w="volBeat"></span><input type="range" class="range" min="0" max="100" data-volume="beat"></label>
        <label class="block text-sm text-muted"><span data-w="volSub"></span><input type="range" class="range" min="0" max="100" data-volume="sub"></label>
      </div>
    </fieldset>
    <div data-grouping-field hidden><p class="label" data-w="grouping"></p><div class="mt-2 flex flex-wrap gap-1.5" role="group" data-groupings></div></div>
    <fieldset>
      <label class="check-row"><input type="checkbox" class="checkbox" data-toggle="trainer" data-ui="metronome-trainer"><span class="label" data-w="trainer"></span></label>
      <div class="mt-2 grid grid-cols-3 gap-2">
        <label class="block text-sm text-muted"><span data-w="trainerAdd"></span><input type="number" inputmode="numeric" min="1" max="50" class="input mt-1" data-field="trainer.add"></label>
        <label class="block text-sm text-muted"><span data-w="trainerEvery"></span><input type="number" inputmode="numeric" min="1" max="64" class="input mt-1" data-field="trainer.every"></label>
        <label class="block text-sm text-muted"><span data-w="trainerMax"></span><input type="number" inputmode="numeric" min="20" max="400" class="input mt-1" data-field="trainer.max"></label>
      </div>
    </fieldset>
    <fieldset>
      <label class="check-row"><input type="checkbox" class="checkbox" data-toggle="silent" data-ui="metronome-silent"><span class="label" data-w="silentBars"></span></label>
      <div class="mt-2 grid grid-cols-2 gap-2">
        <label class="block text-sm text-muted"><span data-w="silentPlay"></span><input type="number" inputmode="numeric" min="1" max="32" class="input mt-1" data-field="silent.play"></label>
        <label class="block text-sm text-muted"><span data-w="silentMute"></span><input type="number" inputmode="numeric" min="1" max="32" class="input mt-1" data-field="silent.mute"></label>
      </div>
    </fieldset>
    <div class="grid gap-3 sm:grid-cols-2">
      <label class="block"><span class="label" data-w="timer"></span><span class="mt-2 flex items-center gap-2"><input type="number" inputmode="numeric" min="0" max="600" class="input w-24" data-field="minutes" data-ui="metronome-timer"><span class="text-sm text-muted" data-w="timerMinutes"></span></span></label>
      <div><p class="label" data-w="visual"></p><span class="seg mt-2" role="group"><button type="button" class="seg-option" data-pulse="0"></button><button type="button" class="seg-option" data-pulse="1"></button></span></div>
    </div>
    <label class="check-row"><input type="checkbox" class="checkbox" data-toggle="extended"><span class="text-sm text-cream" data-w="extend"></span></label>
    <div>
      <p class="label" data-w="presets"></p>
      <form class="mt-2 flex gap-2" data-preset-form><label class="block grow"><span class="sr-only" data-w="presetName"></span><input class="input" maxlength="40" data-preset-name></label><button type="submit" class="btn-secondary" data-w="save"></button></form>
      <ul class="mt-2 flex flex-wrap gap-1.5" data-presets></ul>
    </div>
`;

export function mountMetronome(root, { words = {}, keySlot = null, bpm = null } = {}) {
    const w = (key) => words[key] ?? '';
    const ui = toolLayout(root, { keySlot, more: w('more'), done: w('done'), title: w('settingsTitle'), reset: w('reset') });
    ui.hero.innerHTML = HERO;
    ui.readout.innerHTML = READOUT;
    ui.panel.innerHTML = PANEL;
    ui.sheet.innerHTML = SHEET;
    ui.primary.dataset.play = '';
    const $ = (sel) => root.querySelector(sel);
    const card = ui.element;
    card.dataset.tool = 'metronome';
    // The settings, remembered on this device (tool-prefs.js; the presets keep their own list).
    const prefs = toolPrefs('metronome', { defaults: DEFAULTS, valid: VALID, clean: mend });
    // A tempo handed over (the tap tempo tool's "Play in metronome": the page passes its ?bpm=) wins
    // for the visit without being saved; over 300 it opens the tempos up to 400, as the own Tap does.
    if (Number.isFinite(bpm) && bpm > 0) {
        const tempo = clamp(Math.round(bpm), TEMPO.min, TEMPO.extended);
        prefs.visit(tempo > TEMPO.max ? { bpm: tempo, extended: true } : { bpm: Math.min(tempo, prefs.value.extended ? TEMPO.extended : TEMPO.max) });
    }
    const settings = prefs.value;
    let presets = read(PRESETS, []);
    if (!Array.isArray(presets)) presets = [];
    let clock = null; // the ticker while playing
    let awake = null;
    let pos = startPosition();
    let queue = []; // [{ time, beat, countIn }] scheduled, for lighting the dots as they're heard
    let raf = 0;
    let lit = -1;
    let taps = tapTempo();
    let sounds = null;
    let soundsFor = '';
    let pulseEl = null;
    let ended = false; // the timer ran out

    for (const el of root.querySelectorAll('[data-w]')) el.textContent = w(el.dataset.w);
    $('[data-tap]').textContent = w('tap');
    $('[data-nudge="-1"]').setAttribute('aria-label', w('slower'));
    $('[data-nudge="1"]').setAttribute('aria-label', w('faster'));
    $('[data-slider]').setAttribute('aria-label', w('tempo'));
    $('[data-dots]').setAttribute('aria-label', w('beats'));
    $('[data-markings]').setAttribute('aria-label', w('markings'));
    $('[data-pulse="0"]').textContent = w('visualDots');
    $('[data-pulse="1"]').textContent = w('visualPulse');
    for (const unit of [4, 8]) {
        const group = document.createElement('optgroup');
        group.label = `/${unit}`;
        for (let n = BEATS.min; n <= BEATS.max; n++) group.append(new Option(`${n}/${unit}`, `${n}/${unit}`));
        $('[data-metre]').append(group);
    }
    // Over Start: the time, then the subdivisions with their names under the notes (Hillel's design).
    // A frame too narrow for both on one row (a small embed) puts the subdivisions under the time.
    ui.controls.innerHTML = '<div class="flex flex-wrap items-center justify-center gap-2.5"><span class="shrink-0" data-beats-slot></span><span class="min-w-[11rem] flex-1" data-subdivision-slot></span></div>';
    const subdivisions = segmented($('[data-subdivision-slot]'), Object.keys(SUBDIVISIONS).map((key) => ({ key, label: SUB_SIGNS[key], name: w(SUB_WORDS[key]), caption: w(SUB_SHORT[key]) })), {
        current: settings.subdivision,
        label: w('subdivision'),
        pick: (key) => {
            settings.subdivision = key;
            save();
            renderBar(); // each beat's subdivision dots
        },
    });
    const metre = stepper($('[data-beats-slot]'), {
        hook: 'metronome-beats-step',
        label: w('time'),
        name: w('beats'),
        lower: w('fewerBeats'),
        higher: w('moreBeats'),
        step: (by) => setMetre(settings.beats + by, settings.unit),
    });
    metre.element.classList.replace('h-11', 'h-14');
    // The slider's scale: its marks where they fall on the slider.
    const placeScale = () => $('[data-scale]').replaceChildren(...SCALE.filter((n) => n <= top()).map((n) => {
        const mark = document.createElement('span');
        mark.className = 'absolute -translate-x-1/2';
        mark.style.left = `calc(11px + (100% - 22px) * ${(n - TEMPO.min) / (top() - TEMPO.min)})`;
        mark.textContent = String(n);
        return mark;
    }));
    $('[data-sound]').append(...CLICK_SOUNDS.map((k) => new Option(w(k), k)));
    const chip = (label, data) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'filter-chip';
        b.textContent = label;
        Object.assign(b.dataset, data);
        return b;
    };
    $('[data-markings]').append(...MARKINGS.map((m) => chip(`${m.name} ${m.jump}`, { jump: m.jump })));
    $('[data-countins]').append(...[[0, 'off'], [1, 'oneBar'], [2, 'twoBars']].map(([n, key]) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'seg-option';
        b.textContent = w(key);
        b.dataset.countin = n;
        return b;
    }));

    const top = () => (settings.extended ? TEMPO.extended : TEMPO.max);
    const save = () => prefs.save();

    function renderTempo() {
        const bpm = $('[data-bpm]');
        if (document.activeElement !== bpm) bpm.value = String(settings.bpm);
        bpm.min = String(TEMPO.min);
        bpm.max = String(top());
        const slider = $('[data-slider]');
        slider.min = String(TEMPO.min);
        slider.max = String(top());
        slider.value = String(settings.bpm);
        slider.style.setProperty('--p', `${((settings.bpm - TEMPO.min) / (top() - TEMPO.min)) * 100}%`);
        $('[data-marking]').textContent = markingOf(settings.bpm).name;
        placeScale();
        $('[data-nudge="-1"]').disabled = settings.bpm <= TEMPO.min;
        $('[data-nudge="1"]').disabled = settings.bpm >= top();
    }

    function renderBar() {
        // A tall bar a beat (the accent taller, a silent one an outline), its number, and a dot for each
        // note of its subdivision under it; the gaps narrow as the beats grow.
        const per = SUBDIVISIONS[settings.subdivision] ?? 1;
        $('[data-dots]').style.gap = settings.beats > 8 ? '0.25rem' : '0.5rem';
        $('[data-dots]').replaceChildren(...settings.accents.map((accent, i) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.dataset.dot = i;
            b.dataset.accent = accent;
            b.setAttribute('aria-label', fill(w('beatDot'), { n: i + 1, state: w(accent) }));
            b.className = 'flex h-full min-w-0 flex-1 flex-col justify-end gap-2.5 rounded-xl';
            const bar = document.createElement('span');
            bar.dataset.bar = '';
            bar.className = `block rounded-xl transition-[background-color,height] duration-[60ms,160ms] ${accent === 'accent' ? 'bg-cream/25' : accent === 'normal' ? 'bg-cream/12' : 'border-2 border-line'}`;
            bar.style.height = accent === 'accent' ? '100%' : '70%';
            const foot = document.createElement('span');
            foot.className = 'grid justify-items-center gap-1.5';
            const n = document.createElement('span');
            n.className = 'text-sm font-semibold tabular-nums text-muted';
            n.textContent = String(i + 1);
            const subs = document.createElement('span');
            subs.className = 'flex h-1 gap-[3px]';
            subs.append(...Array.from({ length: per }, () => Object.assign(document.createElement('span'), { className: 'size-1 rounded-full bg-line' })));
            foot.append(n, subs);
            b.append(bar, foot);
            return b;
        }));
        lit = -1;
        $('[data-metre]').value = `${settings.beats}/${settings.unit}`;
        subdivisions.set(settings.subdivision);
        metre.set(`${settings.beats}/${settings.unit}`, { atLow: settings.beats <= BEATS.min, atHigh: settings.beats >= BEATS.max });
        // The hint gives way on a narrow phone; the bars still answer a tap.
        const hint = Object.assign(document.createElement('span'), { className: 'max-[359px]:hidden', textContent: w('tapBeat') });
        ui.setLabel(fill(w('beatsPerBar'), { n: settings.beats }), hint);
        const options = groupingsOf(settings.beats, settings.unit);
        $('[data-grouping-field]').hidden = options.length < 2;
        const current = (settings.groups ?? defaultGroups(settings.beats, settings.unit)).join('+');
        $('[data-groupings]').replaceChildren(...options.map((g) => {
            const b = chip(g.join('+'), { groups: g.join('+') });
            b.setAttribute('aria-pressed', String(g.join('+') === current));
            return b;
        }));
    }

    function renderMore() {
        $('[data-sound]').value = settings.sound;
        for (const b of root.querySelectorAll('[data-countin]')) b.setAttribute('aria-pressed', String(Number(b.dataset.countin) === settings.countIn));
        for (const input of root.querySelectorAll('[data-volume]')) {
            input.value = String(Math.round(settings.volumes[input.dataset.volume] * 100));
            input.style.setProperty('--p', `${input.value}%`);
        }
        for (const input of root.querySelectorAll('[data-toggle]')) input.checked = Boolean(input.dataset.toggle === 'extended' ? settings.extended : settings[input.dataset.toggle].on);
        for (const input of root.querySelectorAll('[data-field]')) {
            const [a, b] = input.dataset.field.split('.');
            if (document.activeElement !== input) input.value = String(b ? settings[a][b] : settings[a]);
        }
        for (const b of root.querySelectorAll('[data-pulse]')) b.setAttribute('aria-pressed', String((b.dataset.pulse === '1') === settings.pulse));
        $('[data-presets]').replaceChildren(...presets.map((p, i) => {
            const li = document.createElement('li');
            li.className = 'flex items-center gap-1';
            const use = chip(`${p.name} · ${p.bpm}`, { preset: i });
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'grid size-11 place-items-center rounded-full text-muted hover:text-cream';
            remove.textContent = '×';
            remove.dataset.removePreset = i;
            remove.setAttribute('aria-label', fill(w('remove'), { name: p.name }));
            li.append(use, remove);
            return li;
        }));
    }

    function renderPlay() {
        card.dataset.state = clock ? 'playing' : 'stopped';
        ui.setPrimary({ label: clock ? w('stop') : w('play'), active: Boolean(clock), icon: 'play' });
        renderStatus();
    }

    /** With the timer on, how long is left ("4:32 left"), so a practice block shows its end coming (the tools review). */
    function timeLeft(beat) {
        if (!(settings.minutes > 0) || !Number.isFinite(beat?.elapsed)) return '';
        const left = Math.max(0, Math.ceil(settings.minutes * 60 - beat.elapsed));
        return fill(w('timeLeft'), { time: `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}` });
    }

    /** The pill: what's sounding now (the count-in, the beat), the timer's end, or how to start. */
    function renderStatus(beat = null) {
        if (clock && beat?.countIn) ui.setStatus({ tone: 'warn', text: w('counting'), hint: String(beat.beat + 1) });
        else if (clock) ui.setStatus({ tone: 'good', text: w('playing'), hint: beat ? [fill(w('bar'), { n: beat.bar + 1 }), fill(w('beatOf'), { n: beat.beat + 1, total: settings.beats }), timeLeft(beat)].filter(Boolean).join(' · ') : '' });
        else if (ended) ui.setStatus({ tone: 'muted', text: w('timeUp') });
        else ui.setStatus({}); // idle: no pill (Hillel's design)
    }

    function renderAll() {
        renderTempo();
        renderBar();
        renderMore();
        renderPlay();
    }

    function setTempo(bpm) {
        if (!Number.isFinite(bpm)) return;
        settings.bpm = clamp(Math.round(bpm), TEMPO.min, top());
        save();
        renderTempo();
    }

    function setMetre(beats, unit) {
        settings.beats = clamp(beats, BEATS.min, BEATS.max);
        settings.unit = unit;
        settings.accents = defaultAccents(settings.beats);
        settings.groups = null;
        save();
        renderBar();
    }

    // ---- Sound and the clock ----

    function soundsNow() {
        const rate = audio().sampleRate;
        const key = `${settings.sound}:${rate}`;
        if (soundsFor !== key) {
            sounds = { accent: clickSound(settings.sound, rate, { accent: true }), beat: clickSound(settings.sound, rate), sub: clickSound(settings.sound, rate) };
            soundsFor = key;
        }
        return sounds;
    }

    function step(time, n, late) {
        const s = nextStep(pos, { ...settings, groups: settings.groups ?? defaultGroups(settings.beats, settings.unit) });
        pos = s.pos;
        if (s.end) {
            queue.push({ time, end: true });
            return null;
        }
        if (s.sound && !late) {
            const volume = settings.volumes[s.sound === 'accent' ? 'accent' : s.sound] ?? 1;
            if (volume > 0) sound(soundsNow()[s.sound], { at: time, level: s.level * volume });
        }
        if (s.beat !== null) queue.push({ time, beat: s.beat, bar: s.bar, countIn: s.countIn, bpm: s.bpm, sounded: Boolean(s.sound), elapsed: s.pos.elapsed });
        // In a background tab nothing draws: keep only the latest few beats for when it's seen again.
        if (queue.length > 64) queue.splice(0, queue.length - 64);
        return s.gap;
    }

    // The dots light as each beat is heard (the output's delay taken off), on animation frames: only
    // the picture waits on the screen, never the sound.
    function frame() {
        raf = requestAnimationFrame(frame);
        const c = audio();
        const now = heardTime(c); // the beat being heard now, the output's delay taken off
        let current = null;
        while (queue.length && queue[0].time <= now) current = queue.shift();
        if (!current) return;
        if (current.end) {
            ended = true;
            stop();
            return;
        }
        light(current);
    }

    function light(beat) {
        const dots = root.querySelectorAll('[data-dot]');
        unlight(dots[lit]);
        lit = beat.beat;
        const dot = dots[lit];
        if (dot && beat.sounded) {
            dot.dataset.lit = 'true';
            dot.firstElementChild.classList.add(dot.dataset.accent === 'accent' ? 'bg-accent!' : 'bg-cream!');
        }
        renderStatus(beat);
        if (settings.trainer.on && !beat.countIn && beat.bpm !== Number($('[data-bpm]').value) && document.activeElement !== $('[data-bpm]')) $('[data-bpm]').value = String(beat.bpm);
        if (settings.pulse && beat.sounded && !reducedMotion() && (beat.beat === 0 || beat.bpm <= PULSE_LIMIT)) pulse(beat.beat === 0);
    }

    function unlight(dot) {
        if (!dot) return;
        delete dot.dataset.lit;
        dot.firstElementChild?.classList.remove('bg-accent!', 'bg-cream!');
    }

    // A soft full-screen pulse (an option): a faint wash over the page, never a hard flash.
    function pulse(strong) {
        if (!pulseEl) {
            pulseEl = document.createElement('div');
            pulseEl.className = 'pointer-events-none fixed inset-0 z-50 bg-accent opacity-0';
            pulseEl.setAttribute('aria-hidden', 'true');
            document.body.append(pulseEl);
        }
        pulseEl.animate([{ opacity: strong ? 0.18 : 0.1 }, { opacity: 0 }], { duration: 220, easing: 'ease-out' });
    }

    function play() {
        if (clock) return;
        // Inside the tap: on an iPhone it sounds with the silent switch on, as the pitch pipe and drone do (the tools
        // review, 2026-11-14: the metronome alone stayed silent there).
        const c = unlock();
        soundsNow();
        pos = startPosition();
        queue = [];
        ended = false;
        clock = ticker(step, { at: c.currentTime + 0.08 });
        awake = keepAwake();
        raf = requestAnimationFrame(frame);
        renderPlay();
    }

    function stop() {
        clock?.stop();
        clock = null;
        awake?.release();
        awake = null;
        cancelAnimationFrame(raf);
        queue = [];
        unlight(root.querySelectorAll('[data-dot]')[lit]);
        lit = -1;
        if (settings.trainer.on) renderTempo();
        renderPlay();
    }

    function tapNow() {
        const result = tap(taps, performance.now() / 1000);
        taps = result.state;
        if (result.bpm !== null) {
            if (result.bpm > TEMPO.max && !settings.extended) settings.extended = true;
            setTempo(result.bpm);
            renderMore();
        }
    }

    // ---- Controls ----

    ui.onPrimary(() => {
        ended = false;
        if (clock) stop();
        else play();
    });
    $('[data-tap]').addEventListener('click', tapNow);
    $('[data-marking]').addEventListener('click', () => {
        const list = $('[data-markings]');
        list.hidden = !list.hidden;
        $('[data-marking]').setAttribute('aria-expanded', String(!list.hidden));
    });
    // − and + repeat while held: a press steps once at once, then again and again after a pause (only
    // the controls repeat this way; the clicks are all on the audio clock).
    for (const b of root.querySelectorAll('[data-nudge]')) {
        let hold = 0;
        const by = Number(b.dataset.nudge);
        const end = () => clearTimeout(hold);
        b.addEventListener('pointerdown', (event) => {
            if (event.button !== 0) return;
            event.preventDefault();
            setTempo(settings.bpm + by);
            const again = (delay) => { hold = setTimeout(() => { setTempo(settings.bpm + by); again(60); }, delay); };
            again(400);
        });
        for (const type of ['pointerup', 'pointerleave', 'pointercancel']) b.addEventListener(type, end);
        b.addEventListener('click', (event) => {
            // A keyboard press (Enter, Space) has no pointer: step once.
            if (event.detail === 0) setTempo(settings.bpm + by);
        });
    }
    $('[data-bpm]').addEventListener('change', (event) => setTempo(Number(event.target.value)));
    $('[data-bpm]').addEventListener('blur', renderTempo);
    $('[data-slider]').addEventListener('input', (event) => setTempo(Number(event.target.value)));
    $('[data-metre]').addEventListener('change', (event) => {
        const [beats, unit] = event.target.value.split('/').map(Number);
        setMetre(beats, unit);
    });
    $('[data-sound]').addEventListener('change', (event) => {
        settings.sound = event.target.value;
        save();
        if (!clock) {
            unlock();
            sound(soundsNow().accent, { level: settings.volumes.accent });
        }
    });
    for (const input of root.querySelectorAll('[data-volume]')) {
        input.addEventListener('input', () => {
            settings.volumes[input.dataset.volume] = Number(input.value) / 100;
            input.style.setProperty('--p', `${input.value}%`);
            save();
        });
    }
    for (const input of root.querySelectorAll('[data-toggle]')) {
        input.addEventListener('change', () => {
            if (input.dataset.toggle === 'extended') {
                settings.extended = input.checked;
                settings.bpm = Math.min(settings.bpm, top());
                renderTempo();
            } else settings[input.dataset.toggle].on = input.checked;
            save();
        });
    }
    for (const input of root.querySelectorAll('[data-field]')) {
        input.addEventListener('change', () => {
            const [a, b] = input.dataset.field.split('.');
            const value = clamp(Math.round(Number(input.value) || 0), Number(input.min), Number(input.max));
            if (b) settings[a][b] = value;
            else settings[a] = value;
            input.value = String(value);
            save();
        });
    }
    $('[data-preset-form]').addEventListener('submit', (event) => {
        event.preventDefault();
        const name = $('[data-preset-name]').value.trim() || `${settings.bpm} ${settings.beats}/${settings.unit}`;
        const { bpm, beats, unit, subdivision, accents, groups, sound: kind } = settings;
        presets = [{ name, bpm, beats, unit, subdivision, accents: [...accents], groups, sound: kind }, ...presets.filter((p) => p.name !== name)].slice(0, 12);
        write(PRESETS, presets);
        $('[data-preset-name]').value = '';
        renderMore();
    });
    root.addEventListener('click', (event) => {
        const b = event.target.closest('button');
        if (!b || !root.contains(b)) return;
        if (b.dataset.dot !== undefined) {
            const i = Number(b.dataset.dot);
            settings.accents[i] = nextAccent(settings.accents[i]);
            save();
            renderBar();
            root.querySelector(`[data-dot="${i}"]`)?.focus();
        } else if (b.dataset.jump) {
            setTempo(Number(b.dataset.jump));
            $('[data-markings]').hidden = true;
            $('[data-marking]').setAttribute('aria-expanded', 'false');
        } else if (b.dataset.countin !== undefined) {
            settings.countIn = Number(b.dataset.countin);
            save();
            renderMore();
        } else if (b.dataset.groups) {
            settings.groups = b.dataset.groups.split('+').map(Number);
            save();
            renderBar();
        } else if (b.dataset.pulse !== undefined) {
            settings.pulse = b.dataset.pulse === '1';
            save();
            renderMore();
        } else if (b.dataset.preset !== undefined) {
            const p = presets[Number(b.dataset.preset)];
            if (!p) return;
            Object.assign(settings, { bpm: clamp(p.bpm, TEMPO.min, TEMPO.extended), beats: p.beats, unit: p.unit, subdivision: p.subdivision, accents: [...p.accents], groups: p.groups, sound: p.sound });
            if (settings.bpm > TEMPO.max) settings.extended = true;
            save();
            renderAll();
        } else if (b.dataset.removePreset !== undefined) {
            presets.splice(Number(b.dataset.removePreset), 1);
            write(PRESETS, presets);
            renderMore();
        }
    });

    // Space plays and stops, T taps, the arrows nudge the tempo (shift: by 5). Not while typing, and
    // a focused control keeps its own keys (Space on a button presses it).
    function onKey(event) {
        if (!root.isConnected) {
            document.removeEventListener('keydown', onKey);
            return;
        }
        if (event.ctrlKey || event.metaKey || event.altKey) return;
        const t = event.target;
        if (t instanceof HTMLElement && (t.isContentEditable || t.closest('input, select, textarea'))) return;
        // Only on the tool, or with nothing focused: the rest of the page keeps its keys.
        const here = card.contains(t) || t === document.body || t === document.documentElement;
        if (!here) return;
        if (event.key === ' ' || event.code === 'Space') {
            if (t instanceof HTMLElement && t.closest('button, a, summary')) return;
            event.preventDefault();
            ui.primary.click();
        } else if (event.key === 't' || event.key === 'T') {
            tapNow();
        } else if (['ArrowUp', 'ArrowRight', 'ArrowDown', 'ArrowLeft'].includes(event.key) && (card.contains(t) || clock)) {
            // With nothing focused the arrows scroll the page, unless it's playing.
            event.preventDefault();
            const by = (event.key === 'ArrowUp' || event.key === 'ArrowRight' ? 1 : -1) * (event.shiftKey ? 5 : 1);
            setTempo(settings.bpm + by);
        }
    }
    document.addEventListener('keydown', onKey);

    // Reset puts every setting back to its default (the presets stay: they're the singer's own).
    ui.onReset(() => {
        if (clock) stop();
        prefs.reset();
        renderAll();
    });

    renderAll();
    return {
        stop,
        element: card,
        get playing() {
            return Boolean(clock);
        },
        get settings() {
            return structuredClone(settings);
        },
    };
}
