// The free vocal range test (/tools/vocal-range-test, docs/tools-plan.md), built to the competitor
// review's spec (docs/tools/competitors-tuner-metronome-range.md, "UX spec (range test)"). Two
// guided steps, low then high, each retryable: a note held for about a second counts, a keyboard
// shows the note live, and each found note is played back ("We heard G2") so an octave error is a
// ten-second fix, never a silent one. The result: the range in notes and its span, on the keyboard
// and a staff, the voice type it fits phrased as a guess with one honest line, and the choir part
// that suits it. It sits on the tools' one layout (tool-layout.js, Hillel's tuner v3 look): the hero
// is the step (its words and the live note, big), the status pill says how it's going, the panel is
// the keyboard, the pinned bar carries the step's one action (Start, Done, Sounds right, Start again)
// and More, whose sheet holds the overlap with every type, an optional comfortable range step, note
// names, saving on this device and sharing as text. A manual mode serves anyone without a mic (tap
// your lowest and highest keys).
//
// A thin wrapper (docs/tools-plan.md, "Shared modules"): the mic and the pitch tracker from
// live-mic.js, the held note from listen.js (the app's own Find your note, its range check's one
// second hold), the voice types from theory.js, note names from notes.js, the notes played back
// through voices.js, the staff from staff.js. Nothing is recorded or sent; saved results stay on
// this device. The words come from the page (its view translates them).
//
//   import { mountRangeTest, WORDS } from '/js/public/tools/vocal-range-test.js';
//   mountRangeTest(el, { words, keySlot, onResult: ({ low, high, type }) => ..., onError: (reason) => ... });

import { listen, reasonOf, TRACKER_FILES } from '../live-mic.js';
import { toolLayout } from '../tool-layout.js';
import { toolPrefs } from '../tool-prefs.js';
import { isBlack, PIANO } from '../piano.js';
import { grandStaff } from '../staff.js';
import { audio, voice } from '../voices.js';
import { hzToMidi, midiToHz } from '../../dsp.js';
import { hear, listener } from '../../listen.js';
import { SHARE_FILES, shareCard, shareUrl } from './share-card.js';
import { pageNaming, pitchName } from '../../notes.js';
import { CHOIR_PARTS, VOICE_TYPES, voiceTypes } from '../../theory.js';

// The pitch tracker's files, kept offline by the tools' service worker (page.js) before a first Listen or Start.
// The pitch tracker for the mic, and the share card's drawing (Share my range), kept for offline use.
export const WARM = [...TRACKER_FILES, ...SHARE_FILES];

/** The words a page passes, each translated (:note, :low, :high, :n, :type, :part and the span's pieces are filled in here). */
export const WORDS = [
    'intro', 'warmUp', 'start', 'rangeValue', 'lowTitle', 'lowHow', 'highTitle', 'highHow', 'noStrain', 'listening', 'tooQuiet', 'lowest', 'highest',
    'done', 'again', 'heard', 'playIt', 'right', 'tryAgain', 'falsetto', 'yes', 'no', 'notSure', 'yourRange', 'span', 'spanOctaves',
    'spanNotes', 'octave', 'octaves', 'note', 'notes', 'semitones', 'fits', 'choir', 'caveat', 'whatIs', 'whatIsAnswer',
    'bass', 'baritone', 'tenor', 'alto', 'mezzo', 'soprano', 'bassOrTenor2', 'sopranoOrAlto',
    'more', 'compare', 'comfortTitle', 'comfortHow', 'comfortStart', 'comfortable', 'falsettoNote',
    'options', 'naming', 'letters', 'fixedDo', 'save', 'saved', 'history', 'retake', 'share', 'shareText', 'copied',
    'cardTitle', 'cardChallenge', 'cardTagline', 'cardSite', 'cardSaved',
    'manual', 'manualHow', 'manualLow', 'manualHigh', 'restart', 'keyboard', 'noMic', 'holding', 'settingsTitle', 'reset',
];

export const HOLD_SECONDS = 1; // the app's range check holds a note this long (RANGE_HOLD_SECONDS)
const KEYS = { low: 36, high: 84 }; // C2 to C6 on the keyboard strip, widened to fit a range outside it
const COMFORT_SECONDS = 10;

// The page's words fill :names, as Laravel's language files write them (one regex, so :note never eats into :notes).
const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));

// Note names and the saved results, remembered on this device (tool-prefs.js); a result must be a real range.
const DEFAULTS = { naming: pageNaming(['letters', 'fixed-do']), history: [] }; // German pages: letters until it offers German
const VALID = {
    naming: (v) => ['letters', 'fixed-do'].includes(v),
    history: (v) => Array.isArray(v) && v.every((h) => Number.isInteger(h?.low) && Number.isInteger(h?.high) && h.low < h.high),
};

/** A range's span in words: "1 octave and 9 notes (21 semitones)". */
export function spanWords(low, high, w) {
    const semis = high - low;
    const octaves = Math.floor(semis / 12);
    const notes = semis % 12;
    const o = octaves === 1 ? w('octave') : fill(w('octaves'), { n: octaves });
    const n = notes === 1 ? w('note') : fill(w('notes'), { n: notes });
    const s = fill(w('semitones'), { n: semis });
    if (!octaves) return fill(w('spanNotes'), { notes: n, semitones: s });
    if (!notes) return fill(w('spanOctaves'), { octaves: o, semitones: s });
    return fill(w('span'), { octaves: o, notes: n, semitones: s });
}

// The hero: the step's words and its live reading, or the result.
const HERO = `
<section data-panel="intro">
  <p class="mx-auto max-w-prose text-lg text-cream" data-w="intro"></p>
  <p class="mx-auto mt-2 max-w-prose text-sm text-muted" data-w="warmUp"></p>
</section>
<section data-panel="sing" hidden>
  <h3 class="text-xl font-bold text-cream" data-title></h3>
  <p class="mx-auto mt-1 max-w-prose text-sm text-muted" data-how></p>
  <p class="mx-auto mt-1 max-w-prose text-sm font-semibold text-warn" data-strain hidden></p>
  <div class="mt-3 flex items-end justify-center leading-[0.8]" data-ui="range-live">
    <span class="text-[clamp(5rem,26vw,8rem)] font-black tracking-[-0.04em] text-cream [@media(max-height:640px)]:text-[4.5rem]" translate="no" data-live-name>·</span>
    <span class="mb-1 ms-1 text-[2.25rem] font-extrabold tabular-nums text-muted" translate="no" data-live-octave></span>
  </div>
  <div class="mx-auto mt-3 h-1.5 w-40 overflow-hidden rounded-full bg-raised" aria-hidden="true"><div class="h-full rounded-full bg-good transition-[width] duration-100" style="width:0%" data-hold></div></div>
  <div class="mt-3 flex justify-center" data-step-buttons hidden><button type="button" class="btn-secondary min-h-11 rounded-full" data-again data-ui="range-again" data-w="again"></button></div>
</section>
<section data-panel="check" hidden>
  <p class="text-2xl font-extrabold text-cream" data-heard data-ui="range-heard"></p>
  <div class="mt-3 flex flex-wrap justify-center gap-2">
    <button type="button" class="btn-secondary min-h-11 rounded-full" data-play-found data-w="playIt"></button>
    <button type="button" class="btn-secondary min-h-11 rounded-full" data-retry data-ui="range-retry" data-w="tryAgain"></button>
  </div>
  <div class="mt-4" data-falsetto hidden>
    <p class="text-sm font-semibold text-cream" data-w="falsetto"></p>
    <span class="seg mt-2" role="group" data-ui="range-falsetto">
      <button type="button" class="seg-option" data-falsetto-answer="yes" data-w="yes"></button>
      <button type="button" class="seg-option" data-falsetto-answer="no" data-w="no"></button>
      <button type="button" class="seg-option" data-falsetto-answer="unsure" data-w="notSure"></button>
    </span>
  </div>
</section>
<section data-panel="manual" hidden>
  <p class="mx-auto max-w-prose text-sm text-muted" data-w="manualHow"></p>
  <p class="mt-2 text-lg font-bold text-cream" aria-live="polite" data-manual-status></p>
</section>
<section data-panel="result" data-ui="range-result" hidden>
  <p class="text-sm font-semibold text-muted" data-w="yourRange"></p>
  <p class="text-[2.75rem] font-black leading-tight text-cream" translate="no" data-range></p>
  <p class="mt-1 text-sm text-muted" data-span></p>
  <div class="mt-3 flex justify-center text-cream" data-staff data-ui="range-staff"></div>
  <p class="mx-auto mt-3 max-w-prose text-lg text-balance text-cream" data-type data-ui="range-type"></p>
  <p class="mx-auto mt-1 max-w-prose text-balance text-cream" data-choir data-ui="range-choir"></p>
  <p class="mt-3 flex flex-wrap items-center justify-center gap-x-3 gap-y-1"><button type="button" class="btn-secondary btn-sm" data-share data-ui="range-share" data-w="share"></button><span class="text-sm text-good" aria-live="polite" data-share-said></span></p>
  <p class="mx-auto mt-2 max-w-prose text-sm text-muted" data-w="caveat"></p>
  <p class="mx-auto mt-2 max-w-prose text-sm text-muted" data-falsetto-note hidden></p>
  <details class="mx-auto mt-2 max-w-prose text-sm text-muted"><summary class="cursor-pointer font-semibold text-cream" data-w="whatIs"></summary><p class="mt-1" data-w="whatIsAnswer"></p></details>
</section>
<p class="mt-3 text-sm text-muted"><button type="button" class="min-h-11 underline underline-offset-4 hover:text-cream" data-manual data-ui="range-manual" data-w="manual"></button></p>`;

// The panel: the keyboard, with the Cs named under it.
const PANEL = `
<div class="absolute inset-0 flex flex-col justify-center overflow-x-hidden px-3 py-3" data-keyboard-wrap>
  <div class="relative mx-auto flex h-24 w-full select-none sm:h-28" role="group" data-keyboard data-ui="range-keyboard"></div>
  <div class="relative mx-auto mt-1 h-4 w-full" aria-hidden="true" data-key-labels></div>
</div>`;

// More's sheet: more about your voice, and the options.
const SHEET = `
<section class="grid gap-4" data-panel="more" data-ui="range-more">
  <div><p class="label" data-w="compare"></p><div class="mt-2 grid gap-1.5" data-compare></div></div>
  <div>
    <p class="label" data-w="comfortTitle"></p>
    <p class="mt-1 text-sm text-muted" data-w="comfortHow"></p>
    <button type="button" class="btn-secondary mt-2" data-comfort data-ui="range-comfort" data-w="comfortStart"></button>
    <p class="mt-1 text-sm text-cream" aria-live="polite" data-comfort-result></p>
  </div>
</section>
<section class="grid gap-4" data-panel="options" data-ui="range-options">
  <div><p class="label" data-w="naming"></p><span class="seg mt-2" role="group"><button type="button" class="seg-option" data-naming="letters" data-w="letters"></button><button type="button" class="seg-option" data-naming="fixed-do" data-w="fixedDo"></button></span></div>
  <div class="flex flex-wrap gap-2" data-result-actions>
    <button type="button" class="btn-secondary" data-save data-ui="range-save" data-w="save"></button>
  </div>
  <p class="text-sm text-good" aria-live="polite" data-said></p>
  <div data-history-wrap hidden><p class="label" data-w="history"></p><ul class="mt-1 grid gap-1 text-sm text-cream" data-history></ul><p class="mt-1 text-xs text-muted" data-w="retake"></p></div>
</section>`;

export function mountRangeTest(root, { words = {}, keySlot = null, onResult = null, onShared = null, onError = null, deviceId = '', open = listen } = {}) {
    const w = (key) => words[key] ?? '';
    const ui = toolLayout(root, { keySlot, more: w('more'), done: w('done'), title: w('settingsTitle'), reset: w('reset') });
    ui.hero.innerHTML = HERO;
    ui.panel.innerHTML = PANEL;
    ui.sheet.innerHTML = SHEET;
    const $ = (sel) => root.querySelector(sel);
    const card = ui.element;
    card.dataset.tool = 'range';
    const prefs = toolPrefs('range', { defaults: DEFAULTS, valid: VALID, clean: (s) => ({ ...s, history: s.history.slice(0, 10) }) });
    let naming = prefs.value.naming;
    let history = prefs.value.history;
    const write = ({ naming: n, history: h }) => {
        prefs.value.naming = n;
        prefs.value.history = h;
        prefs.save();
    };
    let session = null;
    let step = 'intro'; // intro, low, check-low, high, check-high, result, manual
    let heard = listener();
    let lastAt = null;
    let live = null; // the note sounding now (MIDI, fractional), for the keyboard
    let best = null; // this step's lowest or highest held note
    let low = null;
    let high = null;
    let falsetto = null;
    let comfort = null; // { low, high } from the optional step
    let comforting = null; // { until, notes } while it listens
    let ignoreUntil = 0; // while a found note plays back, the mic hears the speaker: ignored
    let tone = null; // the note playing back

    for (const el of root.querySelectorAll('[data-w]')) el.textContent = w(el.dataset.w);
    $('[data-keyboard]').setAttribute('aria-label', w('keyboard'));
    const name = (midi) => {
        const p = pitchName(midi, { naming });
        return `${p.name}${p.octave}`;
    };
    const show = (panel, on) => { root.querySelector(`[data-panel=${panel}]`).hidden = !on; };

    // ---- The keyboard ----
    function keyRange() {
        const notes = [low, high, best, live === null ? null : Math.round(live), comfort?.low, comfort?.high].filter((n) => n !== null && n !== undefined);
        let from = Math.min(KEYS.low, ...notes);
        let to = Math.max(KEYS.high, ...notes);
        while (isBlack(from)) from -= 1;
        while (isBlack(to)) to += 1;
        return { from, to };
    }

    function renderKeyboard() {
        const { from, to } = keyRange();
        const board = $('[data-keyboard]');
        const manual = step === 'manual';
        board.style.minWidth = manual ? `${(to - from) * 14}px` : '';
        // Manual mode's keys are big enough to tap, so the strip scrolls; singing, it fits the width.
        $('[data-keyboard-wrap]').classList.toggle('overflow-x-auto', manual);
        $('[data-keyboard-wrap]').classList.toggle('overflow-x-hidden', !manual);
        const whites = [];
        for (let m = from; m <= to; m++) if (!isBlack(m)) whites.push(m);
        const pitch = live === null ? null : Math.round(live);
        const inRange = (m) => low !== null && high !== null && m >= Math.min(low, high) && m <= Math.max(low, high);
        const inComfort = (m) => comfort && m >= comfort.low && m <= comfort.high;
        // A piano's own colours in every theme (piano.js), the found and shaded notes in the theme's green.
        const keyClass = (m, black) => {
            const marked = m === best || m === low || m === high;
            if (m === pitch) return 'bg-accent';
            if (marked) return black ? PIANO.markBlack : PIANO.markWhite;
            if (inComfort(m)) return black ? PIANO.strongBlack : PIANO.strongWhite;
            if (inRange(m)) return black ? PIANO.shadeBlack : PIANO.shadeWhite;
            return black ? PIANO.black : PIANO.white;
        };
        const children = [];
        whites.forEach((m, i) => {
            const key = document.createElement(manual ? 'button' : 'span');
            if (manual) key.type = 'button';
            key.dataset.key = m;
            key.className = `relative h-full min-w-0 flex-1 rounded-b-md border ${PIANO.edge} ${keyClass(m, false)}`;
            if (manual) key.setAttribute('aria-label', name(m));
            else key.setAttribute('aria-hidden', 'true');
            children.push(key);
            const next = m + 1;
            if (next <= to && isBlack(next)) {
                const black = document.createElement(manual ? 'button' : 'span');
                if (manual) {
                    black.type = 'button';
                    black.setAttribute('aria-label', name(next));
                } else black.setAttribute('aria-hidden', 'true');
                black.dataset.key = next;
                black.className = `absolute top-0 z-10 h-[60%] -translate-x-1/2 rounded-b-md ${keyClass(next, true)}`;
                black.style.left = `${((i + 1) / whites.length) * 100}%`;
                black.style.width = `${(0.62 / whites.length) * 100}%`;
                children.push(black);
            }
        });
        board.replaceChildren(...children);
        // The Cs named under the keys, where there's room for the names.
        $('[data-key-labels]').replaceChildren(...whites.map((m, i) => (m % 12 === 0 ? [m, i] : null)).filter(Boolean).map(([m, i]) => {
            const label = document.createElement('span');
            // Centred under its key, but never past either edge.
            label.className = `absolute text-xs font-semibold text-muted ${i === 0 ? '' : i === whites.length - 1 ? '-translate-x-full' : '-translate-x-1/2'}`;
            label.style.left = `${((i === 0 ? 0 : i === whites.length - 1 ? i + 1 : i + 0.5) / whites.length) * 100}%`;
            label.textContent = name(m);
            label.setAttribute('translate', 'no');
            return label;
        }));
        $('[data-key-labels]').style.minWidth = board.style.minWidth;
        if (pitch !== null && manual === false) {
            const wrap = $('[data-keyboard-wrap]');
            const k = board.querySelector(`[data-key="${pitch}"]`);
            if (k && wrap.scrollWidth > wrap.clientWidth) wrap.scrollLeft = k.offsetLeft - wrap.clientWidth / 2;
        }
    }

    // ---- The steps ----
    function render() {
        card.dataset.step = step;
        const singing = step === 'low' || step === 'high';
        show('intro', step === 'intro');
        show('sing', singing);
        show('check', step === 'check-low' || step === 'check-high');
        show('manual', step === 'manual');
        show('result', step === 'result');
        // Saving and sharing need a result; the rest of More works any time.
        $('[data-result-actions]').hidden = step !== 'result';
        $('[data-compare]').parentElement.hidden = step !== 'result';
        $('[data-step-buttons]').hidden = !singing;
        $('[data-manual]').hidden = step !== 'intro' && step !== 'result';
        if (singing) {
            $('[data-title]').textContent = w(step === 'low' ? 'lowTitle' : 'highTitle');
            $('[data-how]').textContent = w(step === 'low' ? 'lowHow' : 'highHow');
            $('[data-strain]').hidden = step !== 'high';
            $('[data-strain]').textContent = w('noStrain');
            const p = live === null ? null : pitchName(live, { naming });
            $('[data-live-name]').textContent = p ? p.name : '·';
            $('[data-live-octave]').textContent = p ? String(p.octave) : '';
            $('[data-hold]').style.width = `${Math.min(1, heard.held / HOLD_SECONDS) * 100}%`;
            // The pill says how it's going; the lowest or highest so far is the panel's stat.
            ui.setStatus(heard.held > 0.15 ? { tone: 'good', text: w('holding') } : { tone: 'muted', text: w('listening') });
        }
        if (step === 'check-low' || step === 'check-high') {
            $('[data-heard]').textContent = fill(w('heard'), { note: name(best) });
            $('[data-falsetto]').hidden = step !== 'check-high';
            for (const b of root.querySelectorAll('[data-falsetto-answer]')) b.setAttribute('aria-pressed', String(b.dataset.falsettoAnswer === falsetto));
        }
        if (step === 'manual') {
            $('[data-manual-status]').textContent = low === null ? w('manualLow') : w('manualHigh');
        }
        if (!singing) ui.setStatus({});
        for (const b of root.querySelectorAll('[data-naming]')) b.setAttribute('aria-pressed', String(b.dataset.naming === naming));
        if (step === 'result') renderResult();
        // The bar's one action: the step's next move.
        const action = { intro: 'start', low: 'done', high: 'done', 'check-low': 'right', 'check-high': 'right', manual: 'restart', result: 'restart' }[step];
        ui.setPrimary({ label: w(action) });
        ui.primary.dataset.action = action;
        ui.primary.disabled = singing && best === null;
        const stat = step === 'result' ? fill(w('rangeValue'), { low: name(Math.min(low, high)), high: name(Math.max(low, high)) })
            : best !== null && singing ? fill(w(step === 'low' ? 'lowest' : 'highest'), { note: name(best) }) : '';
        ui.setLabel(w('keyboard'), stat);
        renderKeyboard();
    }

    function renderResult() {
        const lo = Math.min(low, high);
        const hi = Math.max(low, high);
        $('[data-range]').textContent = fill(w('rangeValue'), { low: name(lo), high: name(hi) });
        $('[data-span]').textContent = spanWords(lo, hi, w);
        $('[data-staff]').replaceChildren(grandStaff([lo, hi], { label: fill(w('rangeValue'), { low: name(lo), high: name(hi) }) }));
        const ranked = voiceTypes({ low: lo, high: hi });
        const type = ranked[0].type;
        $('[data-type]').textContent = fill(w('fits'), { type: w(type) });
        $('[data-choir]').textContent = fill(w('choir'), { part: w(CHOIR_PARTS[type]) });
        $('[data-falsetto-note]').hidden = falsetto !== 'yes';
        $('[data-falsetto-note]').textContent = w('falsettoNote');
        // Every type's usual range against yours, so a voice between two sees why.
        const span = { from: Math.min(lo, ...Object.values(VOICE_TYPES).map((t) => t.low)) - 1, to: Math.max(hi, ...Object.values(VOICE_TYPES).map((t) => t.high)) + 1 };
        const pct = (m) => `${((m - span.from) / (span.to - span.from)) * 100}%`;
        $('[data-compare]').replaceChildren(...Object.entries(VOICE_TYPES).reverse().map(([key, t]) => {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-[6rem_1fr] items-center gap-2 text-sm';
            const label = document.createElement('span');
            label.className = key === type ? 'font-bold text-cream' : 'text-muted';
            label.textContent = w(key);
            const bar = document.createElement('span');
            bar.className = 'relative block h-4 rounded-full bg-raised';
            bar.setAttribute('role', 'img');
            bar.setAttribute('aria-label', `${w(key)}: ${fill(w('rangeValue'), { low: name(t.low), high: name(t.high) })}`);
            const theirs = document.createElement('span');
            theirs.className = 'absolute inset-y-0 rounded-full bg-line';
            theirs.style.left = pct(t.low);
            theirs.style.width = `calc(${pct(t.high)} - ${pct(t.low)})`;
            const yours = document.createElement('span');
            yours.className = 'absolute inset-y-1 rounded-full bg-good';
            yours.style.left = pct(lo);
            yours.style.width = `calc(${pct(hi)} - ${pct(lo)})`;
            bar.append(theirs, yours);
            row.append(label, bar);
            return row;
        }));
        $('[data-comfort-result]').textContent = comfort ? fill(w('comfortable'), { low: name(comfort.low), high: name(comfort.high) }) : '';
        $('[data-history-wrap]').hidden = !history.length;
        $('[data-history]').replaceChildren(...history.map((h) => {
            const li = document.createElement('li');
            li.textContent = `${new Date(h.at).toLocaleDateString()} · ${fill(w('rangeValue'), { low: name(h.low), high: name(h.high) })}`;
            return li;
        }));
    }

    function feed(frame) {
        const dt = lastAt === null ? 1 / 60 : Math.min(0.1, Math.max(0, frame.at - lastAt));
        lastAt = frame.at;
        if (frame.at < ignoreUntil) return;
        if (comforting) {
            comforting.until ??= frame.at + COMFORT_SECONDS;
            if (frame.hz > 0) comforting.notes.push(hzToMidi(frame.hz));
            if (frame.at >= comforting.until) finishComfort();
            return;
        }
        if (step !== 'low' && step !== 'high') return;
        heard = hear(heard, { hz: frame.hz, loudness: frame.loudness ?? -30 }, dt);
        live = frame.hz > 0 ? hzToMidi(frame.hz) : heard.held > 0 ? live : null;
        if (heard.held >= HOLD_SECONDS && heard.note !== null) {
            const note = Math.round(heard.note);
            if (best === null || (step === 'low' ? note < best : note > best)) best = note;
        }
        render();
    }

    async function ensureMic() {
        if (session) return true;
        try {
            session = await open({ deviceId, onFrame: feed });
            return true;
        } catch (error) {
            session = null;
            onError?.(error?.reason ?? reasonOf(error));
            return false;
        }
    }

    async function begin(which) {
        tone?.stop();
        tone = null;
        ignoreUntil = 0;
        step = which;
        heard = listener();
        live = null;
        best = null;
        render();
        if (!(await ensureMic())) {
            step = 'intro';
            render();
            $('[data-panel=intro] [data-w=warmUp]').textContent = w('noMic');
            ui.setStatus({ tone: 'warn', text: w('noMic') });
        }
    }

    function play(note) {
        // The found note, sung back in an "ah"; the mic's readings meanwhile are the speaker's.
        tone?.stop();
        tone = voice(midiToHz(note), { level: 0.16, attack: 0.04, release: 0.1 });
        tone.stop(1.3);
        ignoreUntil = audio().currentTime + 1.8; // its 1.3 s and the fade, on the clock the readings carry
    }

    function finish() {
        stopMic();
        step = 'result';
        const lo = Math.min(low, high);
        const hi = Math.max(low, high);
        render();
        onResult?.({ low: lo, high: hi, type: voiceTypes({ low: lo, high: hi })[0].type });
    }

    function stopMic() {
        session?.stop?.();
        session = null;
        lastAt = null;
    }

    async function startComfort() {
        if (!(await ensureMic())) return;
        comforting = { until: null, notes: [] }; // ten seconds from the first reading, on the audio clock
        lastAt = null;
        $('[data-comfort]').disabled = true;
        $('[data-comfort-result]').textContent = w('listening');
    }

    function finishComfort() {
        const notes = comforting.notes.sort((a, b) => a - b);
        comforting = null;
        stopMic();
        $('[data-comfort]').disabled = false;
        if (notes.length < 30) {
            $('[data-comfort-result]').textContent = w('tooQuiet');
            return;
        }
        // The easy middle: where most of the singing was (the 20th to the 80th percentile).
        comfort = { low: Math.round(notes[Math.floor(notes.length * 0.2)]), high: Math.round(notes[Math.floor(notes.length * 0.8)]) };
        render();
    }

    /** The voice type's part ink (the four parts' colours as the theme has them), for its card. */
    function typeInk(type) {
        const part = { soprano: 1, mezzo: 1, alto: 2, tenor: 3, baritone: 4, bass: 4 }[type] ?? 1;
        return getComputedStyle(document.documentElement).getPropertyValue(`--color-part-ink-${part}`).trim() || ['#ff9139', '#ffea6b', '#45eca4', '#968dfd'][part - 1];
    }

    function resultText() {
        const lo = Math.min(low, high);
        const hi = Math.max(low, high);
        return fill(w('shareText'), { low: name(lo), high: name(hi), type: w(voiceTypes({ low: lo, high: hi })[0].type) });
    }

    // ---- Controls ----
    $('[data-again]').addEventListener('click', () => begin(step));
    // The pinned bar's action for the step: Start, Done (the note played back), Sounds right, Start again.
    ui.onPrimary(() => {
        const action = ui.primary.dataset.action;
        if (action === 'start') begin('low');
        else if (action === 'done') finishStep();
        else if (action === 'right') confirmFound();
        else restart();
    });
    function finishStep() {
        if (best === null) return;
        step = step === 'low' ? 'check-low' : 'check-high';
        live = null;
        render();
        play(best);
    }
    $('[data-play-found]').addEventListener('click', () => play(best));
    $('[data-retry]').addEventListener('click', () => begin(step === 'check-low' ? 'low' : 'high'));
    function confirmFound() {
        if (step === 'check-low') {
            low = best;
            begin('high');
        } else {
            high = best;
            finish();
        }
    }
    function restart() {
        low = null;
        high = null;
        comfort = null;
        falsetto = null;
        best = null;
        step = 'intro';
        render();
    }
    $('[data-manual]').addEventListener('click', () => {
        stopMic();
        low = null;
        high = null;
        best = null;
        live = null;
        comfort = null;
        step = 'manual';
        render();
    });
    $('[data-comfort]').addEventListener('click', startComfort);
    $('[data-save]').addEventListener('click', () => {
        const lo = Math.min(low, high);
        const hi = Math.max(low, high);
        history = [{ at: Date.now(), low: lo, high: hi }, ...history].slice(0, 10);
        write({ naming, history });
        $('[data-said]').textContent = w('saved');
        renderResult();
    });
    // Share my range: a card made on the device (js/result-card.js drawRangeCard), shared by share-card.js with a line and
    // the tool's link (?ref=share-range, so a friend lands here and tries it). Nothing is uploaded; the page counts it (onShared).
    $('[data-share]').addEventListener('click', async () => {
        const button = $('[data-share]');
        const lo = Math.min(low, high);
        const hi = Math.max(low, high);
        const type = voiceTypes({ low: lo, high: hi })[0].type;
        button.disabled = true;
        const done = await shareCard({
            fileName: 'my-vocal-range.png',
            text: resultText(),
            url: shareUrl('vocal-range-test', 'share-range'),
            draw: ({ drawRangeCard }) => drawRangeCard({
                words: {
                    title: w('cardTitle'), type: w(type).replace(/^./, (c) => c.toLocaleUpperCase()), range: fill(w('rangeValue'), { low: name(lo), high: name(hi) }), span: spanWords(lo, hi, w),
                    lowName: name(lo), highName: name(hi), challenge: w('cardChallenge'), tagline: w('cardTagline'), site: w('cardSite'),
                    arcade: document.documentElement.dataset.skin !== 'serious', // the skin as the theme menu reads it
                },
                low: lo,
                high: hi,
                color: typeInk(type),
            }),
        });
        if (done === 'saved') $('[data-share-said]').textContent = w('cardSaved');
        if (done) onShared?.();
        button.disabled = false;
    });
    root.addEventListener('click', (event) => {
        const b = event.target.closest('button');
        if (!b || !root.contains(b)) return;
        if (b.dataset.falsettoAnswer) {
            falsetto = b.dataset.falsettoAnswer;
            render();
        } else if (b.dataset.naming) {
            naming = b.dataset.naming;
            write({ naming, history });
            render();
        } else if (b.dataset.key !== undefined && step === 'manual') {
            // Manual mode: each key plays its note; the first tap is the lowest, the second the highest.
            const m = Number(b.dataset.key);
            voice(midiToHz(m), { level: 0.16, attack: 0.03 }).stop(0.9);
            if (low === null) {
                low = m;
                render();
            } else {
                high = m;
                finish();
            }
        }
    });
    // Reset: note names back to letters (the saved results stay: they're the singer's own history).
    ui.onReset(() => {
        naming = 'letters';
        write({ naming, history });
        render();
    });

    render();
    return {
        stop: stopMic,
        element: card,
        get result() {
            return low !== null && high !== null ? { low: Math.min(low, high), high: Math.max(low, high) } : null;
        },
    };
}
