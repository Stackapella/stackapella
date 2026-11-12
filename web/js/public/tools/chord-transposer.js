// The chord transposer, /tools/chord-transposer (docs/tools-plan.md, batch 5; the check in
// docs/tools/competitors-transposer-satb.md, section 1), on the tools' one layout (js/public/tool-layout.js):
// paste a whole chord sheet (chords on their own lines over the lyrics, ChordPro's [G]inline chords, or
// chords alone) and move it up or down, the lyrics, blank lines and section labels kept as they are and
// each chord kept over its syllable (a longer name pushes the rest along, never onto the words). The
// brand row's key control is the shift in semitones; Your chart | Transposed are the modes; the hero says
// the key it was in and the key it's in now ("G → A", guessed from the first chord); the readout is the
// guitar's capo for the new key (the key finder's own rule); the panel is the chart; Copy is pinned. Any
// suffix is kept as written (C7#9/G, Bbmaj9), and the new key is spelled as it's written (flats in flat
// keys), or in sharps or flats as asked. Behind More: straight to a key, sharps or flats, a sample, clear.
//
// A thin wrapper: chord symbols read by notes.js parseChordSymbol() (the root, the suffix as written, the
// slash bass), spelled by noteSpelling() and flatKey(), the capo from theory.js capoFor(). The chart
// stays on this device only (a convenience: it's there when you come back), with the settings, through
// js/public/tool-prefs.js; nothing is sent. The words
// come from the page (:n, :from, :to, :shape and :key are filled in here), set as text, never markup.
//
//   import { mountChordTransposer, WORDS } from '/js/public/tools/chord-transposer.js';
//   const transposer = mountChordTransposer(el, { words, keySlot });
//
// The handle: set(text), shift(by), output() and stop(). readSheet(), transposeSheet() and guessKey() are pure.

import { flatKey, noteSpelling, parseChordSymbol } from '../../notes.js';
import { capoFor } from '../../theory.js';
import { segmented, sheetRow, stepper, toolLayout } from '../tool-layout.js';
import { toolPrefs } from '../tool-prefs.js';
import { arcade, partInk, SHARE_FILES, shareCard, shareUrl } from './share-card.js';

/** The words a page passes, each translated. */
export const WORDS = [
    'transpose', 'lower', 'higher', 'modes', 'yourChart', 'transposed', 'idleHint', 'chordsFound', 'notChords', 'chart', 'placeholder',
    'asWritten', 'shiftUp', 'shiftDown', 'guitar', 'guitarOpen', 'copy', 'copied', 'settings', 'settingsTitle', 'reset', 'done',
    'toKey', 'spelling', 'auto', 'sharps', 'flats', 'sample', 'sampleText', 'clear', 'kept',
    'share', 'shareText', 'cardTitle', 'cardWas', 'cardNow', 'cardChallenge', 'cardTagline', 'cardSite', 'cardSaved',
];

// The share card's drawing (share-card.js), kept offline by the tools' service worker (page.js).
export const WARM = SHARE_FILES;
const CARD_CHORDS = 6; // a card shows the chart's first six different chords, never its words

const RANGE = 11; // up or down by up to 11 semitones
// What may sit between chords on a chord line: bar lines, repeats, rests, "N.C.", "x2", parentheses.
const FILLER = /^(\|+|:?\|\|?:?|\/+|-+|%|\.+|x\d+|\d+x|N\.?C\.?|\(|\))$/i;
const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));
const pc = (n) => ((n % 12) + 12) % 12;

/** Whether a key's chords are spelled with flats: as the key is written (notes.js flatKey), F# major as F#, as charts write it. */
export function flatsFor(tonic, minor = false) {
    return pc(tonic) === 6 && !minor ? false : flatKey(tonic, minor);
}

/** A chord symbol moved `by` semitones, its suffix as written, spelled with flats or sharps. Unchanged when it isn't a chord. */
export function transposeSymbol(symbol, by, flats = false) {
    const chord = parseChordSymbol(symbol);
    if (!chord) return symbol;
    const bass = chord.bass === null ? '' : `/${noteSpelling(chord.bass + by, flats)}`;
    return `${noteSpelling(chord.root + by, flats)}${chord.suffix}${bass}`;
}

// A token that's a chord, perhaps in parentheses ("(Em)"): its chord and what wraps it.
function chordToken(token) {
    const m = /^(\(?)(.*?)(\)?)$/.exec(token);
    const chord = parseChordSymbol(m[2]);
    return chord ? { open: m[1], symbol: m[2], close: m[3] } : null;
}

/**
 * A sheet's lines, each as `{ text, kind }`: 'chords' (every word on it a chord, a bar line or a rest),
 * 'inline' (ChordPro chords in brackets: "[G]Amazing [C]grace"), or 'text' (lyrics, labels, blank).
 */
export function readSheet(text) {
    return String(text ?? '').split(/\r?\n/).map((line) => {
        const words = line.trim().split(/\s+/).filter(Boolean);
        if (/\[[^\]]+\]/.test(line) && [...line.matchAll(/\[([^\]]+)\]/g)].some((m) => parseChordSymbol(m[1].trim()))) return { text: line, kind: 'inline' };
        if (words.length && words.some((w) => chordToken(w)) && words.every((w) => chordToken(w) || FILLER.test(w))) return { text: line, kind: 'chords' };
        return { text: line, kind: 'text' };
    });
}

/** Every chord in a sheet, in order, as parsed. */
export function chordsOf(text) {
    return readSheet(text).flatMap(({ text: line, kind }) => {
        if (kind === 'inline') return [...line.matchAll(/\[([^\]]+)\]/g)].map((m) => parseChordSymbol(m[1].trim())).filter(Boolean);
        if (kind === 'chords') return line.trim().split(/\s+/).map((w) => chordToken(w)).filter(Boolean).map((t) => parseChordSymbol(t.symbol));
        return [];
    });
}

/** A sheet's chord names as written, in order (inline and on chord lines); its words never. */
export function symbolsOf(text) {
    return readSheet(text).flatMap(({ text: line, kind }) => {
        if (kind === 'inline') return [...line.matchAll(/\[([^\]]+)\]/g)].map((m) => m[1].trim()).filter((symbol) => parseChordSymbol(symbol));
        if (kind === 'chords') return line.trim().split(/\s+/).map((w) => chordToken(w)).filter(Boolean).map((t) => t.symbol);
        return [];
    });
}

/** The key a sheet seems to be in: its first chord's root, minor when that chord is (Am, Am7; not Amaj7). Null with no chords. */
export function guessKey(text) {
    const first = chordsOf(text)[0];
    if (!first) return null;
    return { tonic: first.root, minor: /^(m(?!aj)|min|-)/.test(first.suffix) };
}

/**
 * A sheet moved `by` semitones: chords over lyrics stay in their columns (a longer name pushes the next
 * one along, with a space between), inline chords change in place, every other line is left as it was.
 */
export function transposeSheet(text, by, { flats = false } = {}) {
    return readSheet(text).map(({ text: line, kind }) => {
        if (kind === 'inline') return line.replace(/\[([^\]]+)\]/g, (whole, inside) => (parseChordSymbol(inside.trim()) ? `[${transposeSymbol(inside.trim(), by, flats)}]` : whole));
        if (kind !== 'chords') return line;
        let out = '';
        for (const m of line.matchAll(/\S+/g)) {
            const token = chordToken(m[0]);
            const word = token ? `${token.open}${transposeSymbol(token.symbol, by, flats)}${token.close}` : m[0];
            const gap = out.length ? Math.max(1, m.index - out.length) : m.index;
            out += ' '.repeat(gap) + word;
        }
        return out;
    }).join('\n');
}

/** The settings, remembered on this device (js/public/tool-prefs.js): the chart (a convenience) and its spelling. */
const DEFAULTS = { text: '', spelling: 'auto' };
const VALID = { spelling: (v) => ['auto', 'sharps', 'flats'].includes(v) };

const HERO = `
<div class="grid justify-items-center gap-1" translate="no">
  <p class="text-[clamp(3rem,16vw,4.75rem)] font-black leading-none tracking-tight text-cream [@media(max-height:640px)]:text-[2.75rem]" data-ui="transposer-keys" data-keys></p>
  <p class="text-sm font-semibold text-muted" data-shift></p>
</div>`;

// The guitar's capo, and Share the change once the chart has moved (docs/improvement-log.md, row 5): one quiet button.
const READOUT = `
<div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1">
  <p class="text-base font-bold text-cream" data-ui="transposer-capo" data-capo></p>
  <button type="button" class="btn-secondary btn-sm" data-share data-ui="transposer-share" data-w="share"></button>
  <span class="text-sm text-good" aria-live="polite" data-share-said></span>
</div>`;

// The chart is set in a monospace face so a chord sits over its syllable (the one place a tool uses it).
// Typing wraps; the transposed chart keeps its columns and, when a line is wider than a phone, slides
// sideways inside its own box (never the page).
const PANEL = `
<textarea class="absolute inset-0 size-full resize-none bg-transparent p-4 font-mono text-[0.8125rem] leading-relaxed text-cream outline-none placeholder:text-muted" spellcheck="false" autocapitalize="off" autocomplete="off" wrap="soft" data-ui="transposer-input" data-input></textarea>
<button type="button" class="btn-secondary btn-sm absolute bottom-3 end-3" data-ui="transposer-sample" data-try data-w="sample"></button>
<pre class="absolute inset-0 m-0 overflow-auto whitespace-pre p-4 font-mono text-[0.8125rem] leading-relaxed text-cream [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" translate="no" data-ui="transposer-output" data-output hidden></pre>`;

/** More: Hillel's settings sheet (tool-layout.js sheetRow), a hairline row each. */
function buildSheet(ui, w) {
    sheetRow(ui.sheet, { label: w('toKey') }).side.innerHTML = '<select class="input w-auto" data-to-key></select>';
    sheetRow(ui.sheet, { label: w('spelling') }).body.innerHTML = '<div class="flex flex-wrap gap-1.5" role="group" data-spellings></div>';
    sheetRow(ui.sheet).body.innerHTML = '<div class="flex flex-wrap gap-2"><button type="button" class="btn-secondary btn-sm" data-sample data-w="sample"></button><button type="button" class="btn-ghost btn-sm" data-clear data-w="clear"></button></div>';
    sheetRow(ui.sheet).body.innerHTML = '<p class="text-sm text-muted" data-w="kept"></p>';
}

const MAJOR_NAMES = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B']; // i18n-ignore: key names

export function mountChordTransposer(root, { words = {}, keySlot = null, onShared = null } = {}) {
    const w = (key) => words[key] ?? '';
    const ui = toolLayout(root, { keySlot, more: w('settings'), done: w('done'), title: w('settingsTitle'), reset: w('reset') });
    ui.hero.innerHTML = HERO;
    ui.readout.innerHTML = READOUT;
    ui.panel.innerHTML = PANEL;
    buildSheet(ui, w);
    const card = ui.element;
    card.dataset.tool = 'chord-transposer';
    const $ = (selector) => card.querySelector(selector);
    const text = (selector, value) => ($(selector).textContent = value ?? '');
    for (const el of card.querySelectorAll('[data-w]')) el.textContent = w(el.dataset.w);
    $('[data-input]').placeholder = w('placeholder');
    $('[data-input]').setAttribute('aria-label', w('yourChart'));

    const prefs = toolPrefs('chord-transposer', { defaults: DEFAULTS, valid: VALID });
    const settings = prefs.value;
    let by = 0;
    let view = 'input';
    $('[data-input]').value = settings.text;

    const shiftStep = stepper(ui.key, { hook: 'transposer-shift', label: w('transpose'), name: w('transpose'), lower: w('lower'), higher: w('higher'), step: (d) => shift(by + d) });
    const modes = segmented(ui.modes, [{ key: 'input', label: w('yourChart') }, { key: 'output', label: w('transposed') }], { current: view, label: w('modes'), pick: (key) => {
        view = key;
        render();
    } });
    $('[data-spellings]').append(...['auto', 'sharps', 'flats'].map((key) => {
        const b = Object.assign(document.createElement('button'), { type: 'button', className: 'filter-chip', textContent: w(key) });
        b.dataset.spelling = key;
        return b;
    }));

    const key = () => guessKey(settings.text);
    /** Flats or sharps for the new key: as asked, or as the new key is written. */
    function flatsNow() {
        if (settings.spelling !== 'auto') return settings.spelling === 'flats';
        const k = key();
        return k ? flatsFor(k.tonic + by, k.minor) : false;
    }
    const keyText = (tonic, minor) => `${noteSpelling(tonic, settings.spelling === 'auto' ? flatsFor(tonic, minor) : settings.spelling === 'flats')}${minor ? 'm' : ''}`;
    const output = () => transposeSheet(settings.text, by, { flats: flatsNow() });

    function render() {
        const k = key();
        const count = chordsOf(settings.text).length;
        card.dataset.view = view;
        card.dataset.shift = String(by);
        modes.set(view);
        shiftStep.set(by > 0 ? `+${by}` : by < 0 ? `−${-by}` : '0', { atLow: by <= -RANGE, atHigh: by >= RANGE });
        text('[data-keys]', k ? (by ? `${keyText(k.tonic, k.minor)} → ${keyText(k.tonic + by, k.minor)}` : keyText(k.tonic, k.minor)) : '–');
        $('[data-keys]').classList.toggle('text-muted', !k);
        text('[data-shift]', !k ? '' : !by ? w('asWritten') : fill(w(by > 0 ? 'shiftUp' : 'shiftDown'), { n: Math.abs(by) }));
        $('[data-try]').hidden = !!settings.text.trim() || view !== 'input';
        // The guitar in the new key: the capo for open shapes.
        const capo = k ? capoFor(pc(k.tonic + by), k.minor) : null;
        text('[data-capo]', !capo ? '' : capo.capo ? fill(w('guitar'), { n: capo.capo, shape: keyText(capo.shape, k.minor) }) : fill(w('guitarOpen'), { key: keyText(k.tonic + by, k.minor) }));
        $('[data-capo]').hidden = !capo;
        $('[data-share]').hidden = !k || !by || !count;
        $('[data-input]').hidden = view !== 'input';
        $('[data-output]').hidden = view !== 'output';
        if (view === 'output') text('[data-output]', output());
        if (!settings.text.trim()) ui.setStatus({ hint: w('idleHint') });
        else if (!count) ui.setStatus({ tone: 'warn', text: w('notChords') });
        else ui.setStatus({ tone: 'good', text: fill(w('chordsFound'), { n: count }) });
        ui.setLabel(w('chart'), '');
        ui.setPrimary({ label: w('copy') });
        ui.primary.disabled = !count;
        for (const b of card.querySelectorAll('[data-spelling]')) b.setAttribute('aria-pressed', String(b.dataset.spelling === settings.spelling));
        const to = $('[data-to-key]');
        if (k) {
            to.replaceChildren(...MAJOR_NAMES.map((_, i) => new Option(keyText(i, k.minor), String(i))));
            to.value = String(pc(k.tonic + by));
        }
        to.disabled = !k;
    }

    /** Moves the chart to `next` semitones from as written (and shows the result). */
    function shift(next) {
        by = Math.max(-RANGE, Math.min(RANGE, next));
        if (settings.text.trim()) view = 'output';
        render();
    }

    function set(value) {
        settings.text = value;
        prefs.save();
        render();
    }

    // More's Reset: the settings back to their defaults; the chart stays (it's the person's, not a setting).
    ui.onReset(() => {
        const kept = settings.text;
        prefs.reset();
        settings.text = kept;
        prefs.save();
        by = 0;
        render();
    });
    $('[data-input]').addEventListener('input', (event) => set(event.target.value));
    $('[data-to-key]').addEventListener('change', (event) => {
        const k = key();
        if (!k) return;
        // Straight to a key: the nearer way, up or down.
        let d = pc(Number(event.target.value) - k.tonic);
        if (d > 6) d -= 12;
        shift(d);
    });
    $('[data-spellings]').addEventListener('click', (event) => {
        const b = event.target.closest('[data-spelling]');
        if (!b) return;
        settings.spelling = b.dataset.spelling;
        prefs.save();
        render();
    });
    $('[data-try]').addEventListener('click', () => $('[data-sample]').click());
    $('[data-sample]').addEventListener('click', () => {
        $('[data-input]').value = w('sampleText');
        view = 'input';
        set(w('sampleText'));
        ui.openSheet(false);
    });
    $('[data-clear]').addEventListener('click', () => {
        $('[data-input]').value = '';
        by = 0;
        view = 'input';
        set('');
        ui.openSheet(false);
    });
    // Share the change: the keys and the first chords, before and after, as a card made on the device (result-card.js
    // drawAnswerCard), with the line and the tool's link (?ref=share-chord-transposer). Never the chart's words.
    $('[data-share]').addEventListener('click', async () => {
        const k = key();
        if (!k || !by) return;
        const button = $('[data-share]');
        const was = [...new Set(symbolsOf(settings.text))];
        const more = was.length > CARD_CHORDS ? ' …' : '';
        const from = keyText(k.tonic, k.minor);
        const to = keyText(k.tonic + by, k.minor);
        button.disabled = true;
        const done = await shareCard({
            fileName: 'chords-in-a-new-key.png',
            text: fill(w('shareText'), { from, to }),
            url: shareUrl('chord-transposer', 'share-chord-transposer'),
            draw: ({ drawAnswerCard }) => drawAnswerCard({
                words: {
                    title: w('cardTitle'), hero: `${from} → ${to}`, line: $('[data-shift]').textContent,
                    rows: [
                        [w('cardWas'), was.slice(0, CARD_CHORDS).join(' ') + more],
                        [w('cardNow'), was.slice(0, CARD_CHORDS).map((symbol) => transposeSymbol(symbol, by, flatsNow())).join(' ') + more],
                        ['', $('[data-capo]').hidden ? '' : $('[data-capo]').textContent],
                    ].filter((row) => row[1]),
                    challenge: w('cardChallenge'), tagline: w('cardTagline'), site: w('cardSite'), arcade: arcade(),
                },
                color: partInk(2),
            }),
        });
        if (done === 'saved') text('[data-share-said]', w('cardSaved'));
        if (done) onShared?.('chord-transposer', done);
        button.disabled = false;
    });
    ui.onPrimary(async () => {
        try {
            await navigator.clipboard.writeText(output());
            ui.setPrimary({ label: w('copied') });
        } catch (error) {
            console.warn('The chart was not copied', error);
        }
    });
    render();
    return {
        set(value) {
            $('[data-input]').value = value;
            set(value);
        },
        shift,
        output,
        stop() {
            shiftStep.destroy();
        },
        element: card,
    };
}
