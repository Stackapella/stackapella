// The tap tempo tool, /tools/tap-tempo (docs/tools-plan.md, batch 3; the review in
// docs/tools/competitors-tap-tempo-mic-test-pitch-memory.md, section 1), on the tools' one layout
// (js/public/tool-layout.js): tap along to a song and read its tempo. The hero is the number, huge; the
// pill says its Italian name and how steady the taps are; the panel is one big pad (Space, Enter or T
// tap; Escape starts again); the brand row's key control is the time (half or double); "Play in
// metronome" is pinned. The reading is the metronome's own (js/public/metronome-beats.js tap() and
// tapReading(), the median gap, so one fumbled tap doesn't move it), kept over the last 32 taps for a
// finer number; a 2-second pause starts a new count, and the number fades to say so. Behind More: what
// each tap is (a beat, or a bar of 2, 3 or 4), a decimal, a click on each tap, ms per beat and per
// eighth, copy, start again; the options stay on this device (tool-prefs.js), nothing is sent. The
// words come from the page (:n, :bpm, :beat,
// :eighth and :name are filled in here), set as text, never markup. Hooks for the arcade skin: data-ui
// on the bpm and the tap-pad, data-phase and data-steadiness on the tool.
//
//   import { mountTapTempo, WORDS } from '/js/public/tools/tap-tempo.js';
//   const tool = mountTapTempo(el, { words, keySlot, metronome: '/tools/metronome' });
//
// The handle: tap(seconds), reset(), reading() and stop(). Tests tap with their own clock.

import { markingOf, TAP, tap, tapReading, tapTempo, TEMPO } from '../metronome-beats.js';
import { clickSound } from '../click-sounds.js';
import { sound } from '../tones.js';
import { audio } from '../voices.js';
import { sheetRow, stepper, toolLayout } from '../tool-layout.js';
import { toolPrefs } from '../tool-prefs.js';

/** The words a page passes, each translated. */
export const WORDS = ['bpm', 'time', 'halve', 'double', 'tap', 'tapHint', 'idleHint', 'padLabel', 'startAgain', 'taps', 'steady', 'fair', 'uneven',
    'metronome', 'more', 'settingsTitle', 'reset', 'done', 'eachTap', 'aBeat', 'aBarOf', 'decimal', 'tapSound', 'perBeat', 'copy', 'copied', 'copyText', 'clearTaps'];

/** The settings, remembered on this device (js/public/tool-prefs.js): what a tap is, a decimal, a click on each tap. */
const DEFAULTS = { perTap: 1, decimal: false, sound: false };
const VALID = { perTap: (v) => [1, 2, 3, 4].includes(v) };

const KEEP = 32; // taps read at most, for a finer number than the metronome's quick six
const FACTORS = [0.25, 0.5, 1, 2, 4]; // the time: a quarter, half, as tapped, double, four times
const FACTOR_SHOWN = { 0.25: '¼×', 0.5: '½×', 1: '1×', 2: '2×', 4: '4×' };
const TONES = { steady: 'good', fair: 'muted', uneven: 'warn' };
const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));

const BIG = 'text-[clamp(5rem,26vw,8.5rem)] font-black leading-[0.9] tracking-[-0.04em] tabular-nums text-cream transition-opacity motion-reduce:transition-none [@media(max-height:640px)]:text-[4.5rem]';
const HERO = `<p class="flex items-baseline justify-center gap-2 pt-2 [@media(max-height:640px)]:pt-0" translate="no"><span class="${BIG}" data-ui="bpm" data-bpm>–</span><span class="text-lg font-bold text-muted" data-bpm-label></span></p>`;

const PANEL = `
<button type="button" class="absolute inset-0 flex touch-manipulation select-none flex-col items-center justify-center gap-1 text-cream transition active:scale-[0.99] active:bg-panel motion-reduce:transition-none" data-ui="tap-pad" data-pad>
  <span class="text-4xl font-extrabold" data-pad-word></span>
  <span class="text-sm text-muted" data-pad-hint></span>
</button>`;

/** More: Hillel's settings sheet (tool-layout.js sheetRow), a hairline row each. */
function buildSheet(ui, words) {
    sheetRow(ui.sheet, { label: words.eachTap ?? '' }).side.innerHTML = '<select class="input w-auto" data-per-tap></select>';
    sheetRow(ui.sheet).body.innerHTML = '<label class="check-row"><input type="checkbox" class="checkbox" data-decimal /> <span data-decimal-word></span></label>';
    sheetRow(ui.sheet).body.innerHTML = '<label class="check-row"><input type="checkbox" class="checkbox" data-sound /> <span data-sound-word></span></label>';
    sheetRow(ui.sheet).body.innerHTML = '<p class="text-sm tabular-nums text-muted" translate="no" data-per-beat></p>';
    sheetRow(ui.sheet).body.innerHTML = '<div class="flex flex-wrap gap-2"><button type="button" class="btn-secondary btn-sm" data-copy></button><button type="button" class="btn-ghost btn-sm" data-ui="tap-clear" data-reset></button></div>';
}

export function mountTapTempo(root, { words = {}, keySlot = null, metronome = '/tools/metronome', go = (url) => location.assign(url), clock = () => performance.now() / 1000 } = {}) {
    const ui = toolLayout(root, { keySlot, more: words.more ?? '', done: words.done ?? '', title: words.settingsTitle ?? '', reset: words.reset ?? '' });
    ui.hero.innerHTML = HERO;
    ui.panel.innerHTML = PANEL;
    buildSheet(ui, words);
    const card = ui.element;
    card.dataset.tool = 'tap-tempo';
    const $ = (selector) => card.querySelector(selector);
    const text = (selector, value) => ($(selector).textContent = value ?? '');
    let state = tapTempo();
    let factor = 1; // the time: half, double and so on
    const prefs = toolPrefs('tap-tempo', { defaults: DEFAULTS, valid: VALID });
    const settings = prefs.value; // perTap: a beat, or a bar of 2, 3 or 4
    let faded = 0; // the UI's fade after a pause (a display timer only; no sound waits on it)
    let click = null;

    for (const [selector, key] of [['[data-bpm-label]', 'bpm'], ['[data-pad-word]', 'tap'], ['[data-pad-hint]', 'tapHint'],
        ['[data-decimal-word]', 'decimal'], ['[data-sound-word]', 'tapSound'], ['[data-copy]', 'copy'], ['[data-reset]', 'clearTaps']]) text(selector, words[key]);
    $('[data-per-tap]').append(new Option(words.aBeat ?? '', '1'), ...[2, 3, 4].map((n) => new Option(fill(words.aBarOf, { n }), String(n))));
    const time = stepper(ui.key, { label: words.time ?? '', name: words.time ?? '', lower: words.halve ?? '', higher: words.double ?? '', step: (by) => {
        factor = FACTORS[Math.max(0, Math.min(FACTORS.length - 1, FACTORS.indexOf(factor) + by))];
        shown();
    } });

    /** The tempo now, with the time and a bar's beats counted in; null under 3 taps. */
    function reading() {
        const r = tapReading(state.taps);
        if (!r) return null;
        const bpm = Math.max(TEMPO.min / 2, Math.min(TEMPO.extended * 2, r.bpm * factor * settings.perTap));
        return { ...r, bpm };
    }

    const metronomeAt = (bpm) => `${metronome}?bpm=${Math.round(Math.max(TEMPO.min, Math.min(TEMPO.extended, bpm)))}`;

    function shown() {
        const r = reading();
        const n = state.taps.length;
        const paused = card.dataset.phase === 'paused';
        text('[data-bpm]', r ? (settings.decimal ? r.bpm.toFixed(1) : String(Math.round(r.bpm))) : '–');
        $('[data-bpm]').classList.toggle('opacity-40', paused);
        // No reading yet: a light, muted dash, not a heavy bar.
        for (const [on, off] of [['font-light', 'font-black'], ['text-muted', 'text-cream']]) {
            $('[data-bpm]').classList.toggle(on, !r);
            $('[data-bpm]').classList.toggle(off, !!r);
        }
        card.dataset.steadiness = r?.steadiness ?? '';
        if (r) ui.setStatus({ tone: paused ? 'muted' : TONES[r.steadiness], text: markingOf(Math.round(r.bpm)).name, hint: paused ? words.startAgain : words[r.steadiness] });
        else ui.setStatus({ hint: paused ? words.startAgain : n ? fill(words.taps, { n }) : words.idleHint });
        ui.setLabel(words.padLabel ?? '', n ? fill(words.taps, { n }) : '');
        const i = FACTORS.indexOf(factor);
        time.set(FACTOR_SHOWN[factor], { atLow: !r || i === 0, atHigh: !r || i === FACTORS.length - 1 });
        ui.setPrimary({ label: words.metronome ?? '' });
        ui.primary.disabled = !r;
        ui.primary.dataset.href = r ? metronomeAt(r.bpm) : '';
        text('[data-per-beat]', r ? fill(words.perBeat, { beat: Math.round(60000 / r.bpm), eighth: Math.round(30000 / r.bpm) }) : '');
        $('[data-copy]').disabled = !r;
    }

    /** A tap at `at` seconds (the pad, a key, or a test's clock). */
    function tapAt(at = clock()) {
        const fresh = !state.taps.length || at - state.taps.at(-1) > TAP.resetSeconds;
        if (fresh) factor = 1;
        state = tap(state, at, { keep: KEEP }).state;
        card.dataset.phase = 'tapping';
        if (settings.sound) sound(click ??= clickSound('woodblock', audio().sampleRate));
        shown();
        // After a pause the count starts again on the next tap: the number fades to say so.
        clearTimeout(faded);
        faded = setTimeout(() => {
            card.dataset.phase = 'paused';
            shown();
        }, TAP.resetSeconds * 1000);
    }

    function reset() {
        clearTimeout(faded);
        state = tapTempo();
        factor = 1;
        card.dataset.phase = 'idle';
        shown();
    }

    // The pad answers on the press itself, not the release, so a tap lands when the finger does.
    $('[data-pad]').addEventListener('pointerdown', (event) => {
        event.preventDefault();
        tapAt(event.timeStamp / 1000);
    });
    $('[data-pad]').addEventListener('keydown', (event) => ['Enter', ' '].includes(event.key) && event.preventDefault());
    const sheetOpen = () => card.querySelector('[data-ui=tool-sheet]').open;
    const onKey = (event) => {
        if (event.metaKey || event.ctrlKey || event.altKey || event.repeat || sheetOpen() || !card.isConnected) return;
        if (['INPUT', 'SELECT', 'TEXTAREA'].includes(event.target?.tagName)) return;
        if (event.code === 'Space' || event.code === 'Enter' || event.code === 'KeyT') {
            if (event.target?.tagName === 'BUTTON' && event.target !== $('[data-pad]') && event.code !== 'KeyT') return; // another button's own Space
            event.preventDefault();
            tapAt(event.timeStamp / 1000);
        } else if (event.code === 'Escape') {
            reset();
        }
    };
    document.addEventListener('keydown', onKey);
    ui.onPrimary(() => ui.primary.dataset.href && go(ui.primary.dataset.href));
    // The options shown as remembered, and saved as they change.
    const showOptions = () => {
        $('[data-per-tap]').value = String(settings.perTap);
        $('[data-decimal]').checked = settings.decimal;
        $('[data-sound]').checked = settings.sound;
    };
    $('[data-per-tap]').addEventListener('change', (event) => {
        settings.perTap = Number(event.target.value) || 1;
        prefs.save();
        shown();
    });
    $('[data-decimal]').addEventListener('change', (event) => {
        settings.decimal = event.target.checked;
        prefs.save();
        shown();
    });
    $('[data-sound]').addEventListener('change', (event) => {
        settings.sound = event.target.checked;
        prefs.save();
    });
    // More's Reset: the options back to their defaults (the taps stay).
    ui.onReset(() => {
        prefs.reset();
        showOptions();
        shown();
    });
    $('[data-reset]').addEventListener('click', () => {
        reset();
        ui.openSheet(false);
    });
    $('[data-copy]').addEventListener('click', async () => {
        const r = reading();
        if (!r) return;
        try {
            await navigator.clipboard.writeText(fill(words.copyText, { bpm: Math.round(r.bpm), name: markingOf(Math.round(r.bpm)).name }));
            text('[data-copy]', words.copied);
        } catch (error) {
            console.warn('The tempo was not copied', error);
        }
    });
    showOptions();
    reset();
    return {
        tap: tapAt,
        reset,
        reading,
        stop() {
            clearTimeout(faded);
            document.removeEventListener('keydown', onKey);
            time.destroy();
        },
        element: card,
    };
}
