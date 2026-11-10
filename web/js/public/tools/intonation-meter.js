// The just intonation tuner, /tools/just-intonation-tuner (docs/tools-plan.md, batch 4; the review in
// docs/tools/competitors-key-finder-intonation-frequencies.md, section 2), on the tools' one layout
// (js/public/tool-layout.js): is my chord ringing? A drone holds the root; you sing a chord tone over it
// and see how far you are from pure (just) tuning, not from the piano: a major third rings about 14
// cents below the piano's, a barbershop seventh 31. The brand row's key control is the root; Major,
// Minor and Barbershop 7th are the modes; the hero names the chord tone you're on and its cents from
// pure, in the state's colour; the pill says ringing, or which way to move; the readout is a meter
// centred on pure with the piano's pitch marked, so the gap between them is seen; the panel is the
// chord's tones, each lighting as you sing it, a tap to hear it pure over the drone. Behind More: the
// fifth in the drone, the drone's level, A4, the mic.
//
// A thin wrapper: pure targets and the ringing zone from just.js (the barbershop course's own, RING),
// the voice from the tuner's reading (tuner-reading.js stepTuner), the mic from live-mic.js, the drone
// and the reference tones from tones.js held() and strike(), names from notes.js. The mic opens on
// Start; nothing is recorded or sent. The settings stay on this device. The words come from the page
// (:n and :note are filled in here), set as text, never markup.
//
//   import { mountIntonationMeter, WORDS } from '/js/public/tools/intonation-meter.js';
//   const meter = mountIntonationMeter(el, { words, keySlot });
//
// The handle: start(), stop() and element. `open` stands in for the mic in tests. toneOf() is pure.

import { midiToHz } from '../../dsp.js';
import { chordTones, pureOffset, RING } from '../../just.js';
import { pitchName } from '../../notes.js';
import { listen, TRACKER_FILES } from '../live-mic.js';
import { onPicked, savedMic, showPicker } from '../mic-pick.js';
import { held, strike } from '../tones.js';
import { segmented, sheetRow, stepper, toolLayout } from '../tool-layout.js';
import { toolPrefs } from '../tool-prefs.js';
import { REFERENCE, stepTuner, tunerReading } from '../tuner-reading.js';
import { keepAwake } from '../wake-lock.js';

/** The files the tool loads only when the mic opens: its page keeps them, so it works offline before a first Start. */
export const WARM = TRACKER_FILES;

/** The words a page passes, each translated. The chord tones are by semitones above the root (tone4 the major third). */
export const WORDS = [
    'start', 'stop', 'settings', 'settingsTitle', 'reset', 'done', 'idleHint', 'headphones', 'listening', 'listeningHint',
    'ringing', 'ringingHint', 'close', 'high', 'highHint', 'low', 'lowHint', 'notInChord', 'notInChordHint',
    'root', 'lower', 'higher', 'modes', 'maj', 'min', 'dom7', 'chordTones', 'heldFor', 'fromPure', 'pureBelow', 'pureAbove', 'pureSame',
    'pure', 'piano', 'hearTone', 'tone0', 'tone3', 'tone4', 'tone7', 'tone10', 'tone11',
    'fifthInDrone', 'droneLevel', 'referenceLabel', 'micLabel', 'denied', 'noMic',
];

export const QUALITIES = ['maj', 'min', 'dom7'];
export const ROOTS = { low: 36, high: 60, standard: 48 }; // C2 to C4, C3 to start
const SPAN = 50; // the meter's reach, ± cents from pure
const TONE_SECONDS = 1.6;
const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));

/**
 * Where a sung pitch (fractional MIDI) sits in the chord on `root` (MIDI) of `quality`: the chord tone
 * by semitones above the root (0, 3, 4, 7, 10, 11), its pure target's cents from the piano, and the
 * voice's cents from that pure target, in any octave. Null `semis` when the nearest note isn't in the chord.
 */
export function toneOf(midi, root, quality) {
    // The nearest chord tone, measured from its pure pitch, so a pure third (14 cents under the piano's) is never taken for its neighbour.
    let best = null;
    for (const semis of chordTones(quality)) {
        const pure = pureOffset(root + semis, { root: ((root % 12) + 12) % 12, quality });
        const target = root + semis + pure / 100;
        const off = 100 * (midi - target);
        const folded = off - 1200 * Math.round(off / 1200);
        if (!best || Math.abs(folded) < Math.abs(best.cents)) best = { semis, pure, cents: folded };
    }
    if (Math.abs(best.cents) > 60) return { semis: null, pure: null, cents: null };
    return { semis: best.semis, pure: Math.round(best.pure * 10) / 10, cents: Math.round(best.cents) };
}

/** The settings, remembered on this device (js/public/tool-prefs.js). */
const DEFAULTS = { root: ROOTS.standard, quality: 'maj', fifth: true, level: 0.1, reference: REFERENCE.standard };
const VALID = {
    root: (v) => Number.isInteger(v) && v >= ROOTS.low && v <= ROOTS.high,
    quality: (v) => QUALITIES.includes(v),
    level: (v) => Number.isFinite(v) && v >= 0.02 && v <= 0.3,
    reference: (v) => Number.isFinite(v) && v >= REFERENCE.low && v <= REFERENCE.high,
};

const HERO = `
<div class="grid justify-items-center gap-1 pt-2 [@media(max-height:640px)]:pt-0">
  <p class="text-xl font-extrabold text-cream" data-ui="intonation-tone" data-tone-name></p>
  <p class="flex items-baseline gap-1.5 tabular-nums" translate="no"><span class="text-[clamp(4rem,22vw,6.5rem)] font-black leading-none tracking-tight text-muted [@media(max-height:640px)]:text-[3.5rem]" data-ui="intonation-cents" data-cents>–</span><span class="text-base font-bold text-muted" data-unit></span></p>
  <p class="text-sm font-semibold text-muted" data-pure-note></p>
</div>`;

const READOUT = `
<div class="grid gap-1" data-ui="intonation-meter" aria-hidden="true">
  <div class="relative h-10 rounded-full bg-raised">
    <div class="absolute inset-y-0 rounded-full bg-good/20" data-zone></div>
    <div class="absolute inset-y-1 left-1/2 w-0.5 -translate-x-1/2 rounded-full bg-good" data-pure-tick></div>
    <div class="absolute inset-y-1 w-0.5 -translate-x-1/2 rounded-full bg-muted" data-piano-tick></div>
    <div class="absolute top-1/2 size-5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-cream shadow transition-[left] duration-100 motion-reduce:transition-none" data-needle hidden></div>
  </div>
  <div class="relative h-4 text-xs font-bold text-muted">
    <span class="absolute left-1/2 -translate-x-1/2 text-good" data-w="pure"></span>
    <span class="absolute -translate-x-1/2" data-piano-label data-w="piano"></span>
  </div>
</div>`;

const PANEL = `
<div class="absolute inset-0 flex flex-col gap-3 overflow-y-auto p-3">
  <div class="grid flex-1 auto-rows-fr gap-2" data-ui="intonation-tones" data-tones></div>
  <p class="px-1 text-sm text-muted" data-headphones data-w="headphones"></p>
</div>`;

/** More: Hillel's settings sheet (tool-layout.js sheetRow), a hairline row each. */
function buildSheet(ui, w) {
    sheetRow(ui.sheet).body.innerHTML = '<label class="check-row"><input type="checkbox" class="checkbox" data-fifth /> <span data-w="fifthInDrone"></span></label>';
    sheetRow(ui.sheet, { label: w('droneLevel') }).body.innerHTML = '<input type="range" class="range w-full" min="0.02" max="0.3" step="0.01" data-level>';
    sheetRow(ui.sheet, { label: w('referenceLabel') }).side.innerHTML = '<span data-reference-stepper></span>';
    sheetRow(ui.sheet).body.innerHTML = '<label class="grid gap-1 text-sm" hidden data-picker-label><span class="label" data-w="micLabel"></span><select class="input" data-picker></select></label>';
}

export function mountIntonationMeter(root, { words = {}, keySlot = null, onLesson = null, open = listen } = {}) {
    const w = (key) => words[key] ?? '';
    const ui = toolLayout(root, { keySlot, more: w('settings'), done: w('done'), title: w('settingsTitle'), reset: w('reset') });
    ui.hero.innerHTML = HERO;
    ui.readout.innerHTML = READOUT;
    ui.panel.innerHTML = PANEL;
    buildSheet(ui, w);
    const card = ui.element;
    card.dataset.tool = 'intonation-meter';
    const $ = (selector) => card.querySelector(selector);
    const text = (selector, value) => ($(selector).textContent = value ?? '');
    for (const el of card.querySelectorAll('[data-w]')) el.textContent = w(el.dataset.w);

    const prefs = toolPrefs('just-intonation-tuner', { defaults: DEFAULTS, valid: VALID });
    const settings = prefs.value;
    let mic = null;
    let drone = null;
    let awake = null;
    let state = tunerReading();
    let now = null; // { semis, pure, cents } while a voice is heard
    let ringSince = null;
    let lastAt = 0;
    let reference = null; // a chord tone played pure, while it sounds

    const rootName = () => {
        const { name, octave } = pitchName(settings.root);
        return `${name}${octave}`;
    };
    const rootStep = stepper(ui.key, { hook: 'intonation-root', label: w('root'), name: w('root'), lower: w('lower'), higher: w('higher'), step: (by) => {
        settings.root = Math.max(ROOTS.low, Math.min(ROOTS.high, settings.root + by));
        changed();
    } });
    const modes = segmented(ui.modes, QUALITIES.map((key) => ({ key, label: w(key) })), { current: settings.quality, label: w('modes'), pick: (key) => {
        settings.quality = key;
        modes.set(key);
        changed();
    } });
    const a4 = stepper($('[data-reference-stepper]'), {
        hook: 'intonation-reference',
        label: 'A4 Hz', // i18n-ignore: a note and a unit
        name: w('referenceLabel'),
        lower: w('lower'),
        higher: w('higher'),
        step: (by) => {
            settings.reference = Math.max(REFERENCE.low, Math.min(REFERENCE.high, settings.reference + by));
            changed();
        },
    });
    $('[data-fifth]').checked = settings.fifth;
    $('[data-level]').value = String(settings.level);

    /** The drone's notes: the root, and its pure fifth (2 cents over the piano's) unless left out. */
    function droneNotes() {
        const notes = [midiToHz(settings.root, settings.reference)];
        const cents = [0];
        if (settings.fifth) {
            notes.push(midiToHz(settings.root + 7, settings.reference));
            cents.push(pureOffset(settings.root + 7, { root: settings.root % 12, quality: 'maj' }));
        }
        return { notes, cents };
    }

    function tiles() {
        $('[data-tones]').replaceChildren(...chordTones(settings.quality).map((semis) => {
            const pure = pureOffset(settings.root + semis, { root: settings.root % 12, quality: settings.quality });
            const b = document.createElement('button');
            b.type = 'button';
            b.dataset.tone = String(semis);
            b.dataset.ui = 'intonation-tone-tile';
            b.setAttribute('aria-label', `${w('hearTone')} ${w(`tone${semis}`)}`);
            const name = Object.assign(document.createElement('span'), { className: 'font-extrabold', textContent: w(`tone${semis}`) });
            const offset = Object.assign(document.createElement('span'), { className: 'text-sm font-semibold tabular-nums opacity-80', textContent: semis ? `${pure > 0 ? '+' : '−'}${Math.abs(pure).toFixed(1)}¢` : '0¢' });
            b.append(name, offset);
            return b;
        }));
        $('[data-tones]').style.gridTemplateColumns = `repeat(${chordTones(settings.quality).length}, minmax(0, 1fr))`;
    }

    function pianoSpot(pure) {
        // The piano's pitch, against pure: a pure third 14 cents low puts the piano 14 cents to the right.
        return 50 + (50 * Math.max(-SPAN, Math.min(SPAN, -pure))) / SPAN;
    }

    function render() {
        const live = !!mic;
        card.dataset.live = String(live);
        card.dataset.tone = now?.semis ?? '';
        const ringing = now?.semis !== null && now?.cents !== null && now && Math.abs(now.cents) <= RING.locked;
        card.dataset.ringing = String(!!ringing);
        rootStep.set(rootName(), { atLow: settings.root <= ROOTS.low, atHigh: settings.root >= ROOTS.high });
        a4.set(settings.reference, { atLow: settings.reference <= REFERENCE.low, atHigh: settings.reference >= REFERENCE.high });
        const heard = now && now.semis !== null;
        text('[data-tone-name]', heard ? w(`tone${now.semis}`) : now ? w('notInChord') : '');
        const cents = $('[data-cents]');
        cents.textContent = heard ? `${now.cents > 0 ? '+' : now.cents < 0 ? '−' : ''}${Math.abs(now.cents)}` : '–';
        const tone = !heard ? 'text-muted' : ringing ? 'text-good' : Math.abs(now.cents) <= RING.near ? 'text-cream' : 'text-warn';
        cents.className = `text-[clamp(4rem,22vw,6.5rem)] font-black leading-none tracking-tight [@media(max-height:640px)]:text-[3.5rem] ${heard ? 'font-black' : 'font-light'} ${tone}`;
        text('[data-unit]', heard ? w('fromPure') : '');
        text('[data-pure-note]', heard && now.semis ? fill(w(now.pure < -0.5 ? 'pureBelow' : now.pure > 0.5 ? 'pureAbove' : 'pureSame'), { n: Math.abs(Math.round(now.pure)) }) : '');
        // The meter: pure in the middle, the ringing zone round it, the piano's pitch marked.
        const zone = (50 * RING.locked) / SPAN;
        Object.assign($('[data-zone]').style, { left: `${50 - zone}%`, width: `${2 * zone}%` });
        const piano = heard ? pianoSpot(now.pure) : 50;
        $('[data-piano-tick]').style.left = `${piano}%`;
        $('[data-piano-label]').style.left = `${piano}%`;
        $('[data-piano-tick]').hidden = !heard || !now.semis || Math.abs(now.pure) < 1;
        $('[data-piano-label]').hidden = $('[data-piano-tick]').hidden;
        $('[data-needle]').hidden = !heard;
        if (heard) $('[data-needle]').style.left = `${50 + (50 * Math.max(-SPAN, Math.min(SPAN, now.cents))) / SPAN}%`;
        for (const b of $('[data-tones]').children) {
            const on = heard && Number(b.dataset.tone) === now.semis;
            b.className = `flex min-h-14 flex-col items-center justify-center gap-0.5 rounded-2xl px-1 text-center transition-colors motion-reduce:transition-none ${on ? (ringing ? 'bg-good text-ink' : 'bg-cream text-ink') : 'bg-panel text-cream'}`;
            b.setAttribute('aria-pressed', String(on));
        }
        $('[data-headphones]').hidden = live;
        const held = ringing && ringSince !== null ? lastAt - ringSince : 0;
        ui.setLabel(w('chordTones'), held >= 1 ? fill(w('heldFor'), { n: held.toFixed(1) }) : '');
        // Rung for 2 s: the page's free lesson line (page.js), once.
        if (held >= 2 && onLesson) {
            onLesson();
            onLesson = null;
        }
        if (!live) ui.setStatus({ hint: w('idleHint') });
        else if (!now) ui.setStatus({ tone: 'muted', text: w('listening'), hint: w('listeningHint') });
        else if (!heard) ui.setStatus({ tone: 'muted', text: w('notInChord'), hint: w('notInChordHint') });
        else if (ringing) ui.setStatus({ tone: 'good', text: w('ringing'), hint: w('ringingHint') });
        else if (Math.abs(now.cents) <= RING.near) ui.setStatus({ tone: 'muted', text: w('close'), hint: w(now.cents > 0 ? 'highHint' : 'lowHint') });
        else ui.setStatus({ tone: 'warn', text: w(now.cents > 0 ? 'high' : 'low'), hint: w(now.cents > 0 ? 'highHint' : 'lowHint') });
        ui.setPrimary({ label: live ? w('stop') : w('start'), active: live });
    }

    function feed(frame) {
        if (!mic) return;
        const view = stepTuner(state, frame, { mode: 'voice', reference: settings.reference });
        lastAt = frame.at;
        if (view.status === 'live' && view.midi !== null) {
            now = toneOf(view.midi, settings.root, settings.quality);
            const ringing = now.semis !== null && Math.abs(now.cents) <= RING.locked;
            if (!ringing) ringSince = null;
            else ringSince ??= frame.at;
        } else {
            now = null;
            ringSince = null;
        }
        render();
    }

    async function start() {
        if (mic || drone) return; // already on, or the mic is being asked for
        const { notes, cents } = droneNotes();
        drone = held(notes, { cents, level: settings.level, timbre: 'reed' });
        awake = keepAwake();
        state = tunerReading();
        try {
            // The browser's echo cancellation only (live-mic's cancelEcho), so it takes the page's own drone back
            // out of the mic; noise suppression and auto gain stay off. Headphones are still the surest.
            const opened = await open({ onFrame: feed, deviceId: savedMic(), cancelEcho: true });
            mic = opened;
            await showPicker($('[data-picker]'), mic.deviceId?.() ?? '').catch((error) => console.warn('The mic picker could not be filled', error));
        } catch (error) {
            stop();
            ui.setStatus({ tone: 'warn', text: w(error?.reason === 'denied' ? 'denied' : 'noMic') });
            return;
        }
        render();
    }

    function stop() {
        mic?.stop();
        mic = null;
        drone?.stop();
        drone = null;
        reference?.stop();
        reference = null;
        awake?.release();
        awake = null;
        now = null;
        ringSince = null;
        render();
    }

    function changed() {
        prefs.save();
        tiles();
        if (drone) {
            const { notes, cents } = droneNotes();
            drone.setNotes(notes, cents);
        }
        state = tunerReading();
        now = null;
        render();
    }

    /** A chord tone played pure over the drone (or alone when the drone is off), an octave above the root. */
    function hearTone(semis) {
        reference?.stop();
        const pure = pureOffset(settings.root + semis, { root: settings.root % 12, quality: settings.quality });
        reference = strike(midiToHz(settings.root + 12 + semis, settings.reference, pure), { level: 0.22, decay: TONE_SECONDS * 2 });
    }

    ui.onPrimary(() => {
        if (mic) stop();
        else start();
    });
    $('[data-tones]').addEventListener('click', (event) => {
        const b = event.target.closest('[data-tone]');
        if (b) hearTone(Number(b.dataset.tone));
    });
    // More's Reset: the root, chord, drone and A4 back to their defaults.
    ui.onReset(() => {
        prefs.reset();
        modes.set(settings.quality);
        $('[data-fifth]').checked = settings.fifth;
        $('[data-level]').value = String(settings.level);
        drone?.level(settings.level);
        changed();
    });
    $('[data-fifth]').addEventListener('change', (event) => {
        settings.fifth = event.target.checked;
        changed();
    });
    $('[data-level]').addEventListener('input', (event) => {
        settings.level = Number(event.target.value);
        prefs.save();
        drone?.level(settings.level);
    });
    onPicked($('[data-picker]'), (id) => mic?.use(id).catch((error) => console.warn('That mic could not be opened', error)));
    tiles();
    render();
    return {
        start,
        stop() {
            stop();
            rootStep.destroy();
        },
        element: card,
    };
}
