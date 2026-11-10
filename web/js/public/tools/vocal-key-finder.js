// The vocal key finder, /tools/vocal-key-finder (docs/tools-plan.md, batch 4; the review in
// docs/tools/competitors-key-finder-intonation-frequencies.md, section 1), on the tools' one layout
// (js/public/tool-layout.js): what key should I sing this song in? It answers both people on stage in
// one line: "Sing it in F, 2 semitones down. Guitar: capo 1, play E shapes". Your voice is your saved
// range (from the range test, on this device) or a voice type, picked at the top; the song is its key (the brand row's
// key control, Major or Minor beside its notes) and its lowest and highest melody notes (each plays as it's
// set), or a song picked from the page's list. The hero is the key to sing in, the pill whether it fits,
// the readout the guitar line and all 12 keys at a glance (fits, a stretch, too far; a tap picks one),
// and Play sings the new bottom and top notes and the key's chord. It fits the song in your own octave
// first (a man singing a woman's song an octave down isn't wrong). Behind More: "the top note is only
// once" (one stretch note doesn't drag the song down), no capo, and copying the answer.
//
// A thin wrapper: the fit from the app's own dsp.js (suggestTranspose(), in each octave), voice types from
// theory.js, names from notes.js, the saved range from saved-range.js, the sound from voices.js voice(),
// scheduled on the audio clock. The settings stay on this device (tool-prefs.js). The words come from
// the page (:key, :n, :shape, :low, :high and :name are filled in here), set as text, never markup.
//
//   import { mountKeyFinder, WORDS } from '/js/public/tools/vocal-key-finder.js';
//   const finder = mountKeyFinder(el, { words, keySlot, songs: [{ name, key: 'G', low: 62, high: 76, href }] });
//
// The handle: play(), result(), stop() and element. keyFor(), keyStrip(), capoFor() and keyName() are pure.

import { midiToHz, suggestTranspose } from '../../dsp.js';
import { parseKey, pitchName } from '../../notes.js';
import { capoFor, VOICE_TYPES } from '../../theory.js';
import { savedRange } from '../saved-range.js';

export { capoFor };
import { segmented, sheetRow, stepper, toolLayout } from '../tool-layout.js';
import { toolPrefs } from '../tool-prefs.js';
import { audio, voice } from '../voices.js';

/** The words a page passes, each translated. */
export const WORDS = [
    'songKey', 'lower', 'higher', 'modes', 'major', 'minor', 'yourVoice', 'yourRange', 'theSong', 'lowest', 'highest', 'pickSong', 'ownNotes',
    'bass', 'baritone', 'tenor', 'alto', 'mezzo', 'soprano', 'singIt', 'asWritten', 'shiftUp', 'shiftDown', 'octaveDown', 'octaveUp',
    'fits', 'fitsHint', 'stretch', 'stretchTop', 'stretchBottom', 'tooFar', 'tooFarHint', 'guitar', 'guitarOpen',
    'learnIt', 'range', 'allKeys', 'keyFits', 'keyStretch', 'keyNo', 'play', 'stop', 'settings', 'settingsTitle', 'reset', 'done', 'topOnce', 'noCapo', 'copy', 'copied', 'copyText',
];

/** Keys as they're usually spelled (a symbol each; the minor ones with their m). */
const MAJOR_KEYS = ['C', 'D♭', 'D', 'E♭', 'E', 'F', 'F♯', 'G', 'A♭', 'A', 'B♭', 'B']; // i18n-ignore: key names are symbols
const MINOR_KEYS = ['Cm', 'C♯m', 'Dm', 'E♭m', 'Em', 'Fm', 'F♯m', 'Gm', 'G♯m', 'Am', 'B♭m', 'Bm']; // i18n-ignore: key names are symbols
const STRETCH = 2; // semitones outside the range that count as a stretch, not out of reach
const TOP_ONCE = 2; // with "the top note is only once", it may go this far over
const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));
const pc = (n) => ((n % 12) + 12) % 12;

/** A key's name: its tonic (0 to 11) in major or minor. */
export const keyName = (tonic, minor = false) => (minor ? MINOR_KEYS : MAJOR_KEYS)[pc(tonic)];

/** How far `range` sits outside `singer` (semitones), with the top allowed `slack` over. */
const outside = (singer, range, slack = 0) => Math.max(0, singer.low - range.low) + Math.max(0, range.high - (singer.high + slack));

/**
 * The key to sing a song in: `song` is `{ tonic, minor, low, high }` (MIDI), `singer` `{ low, high }`.
 * The smallest key change that fits, in any octave (as written first on a tie); when nothing fits,
 * the shift that leaves the fewest semitones out.
 * Returns `{ tonic, transpose, octave, fits, over, under }`: `over` and `under` are how far the top and
 * bottom then sit outside the range. `topOnce` lets the top note go two over (it's sung only once).
 */
export function keyFor(singer, song, { topOnce = false } = {}) {
    const reach = { low: singer.low, high: singer.high + (topOnce ? TOP_ONCE : 0) };
    // Each octave's smallest shift (dsp.js suggestTranspose); the one that fits with the smallest key
    // change wins, as written first on a tie. Unlike the app's octaveFit (a choir part, already near the
    // voice), a song can be in anyone's range: a tenor sings a soprano's tune an octave down in its own
    // key, not seven semitones down. When nothing fits, the fewest semitones left outside.
    let best = null;
    for (const octave of [0, -12, 12, -24, 24]) {
        const { transpose, fits } = suggestTranspose(reach, { low: song.low + octave, high: song.high + octave });
        const out = outside(reach, { low: song.low + octave + transpose, high: song.high + octave + transpose });
        const better = !best || (fits && !best.fits) || (fits === best.fits && (fits ? Math.abs(transpose) < Math.abs(best.transpose) : out < best.out));
        if (better) best = { transpose, octave, fits, out };
    }
    const { transpose, octave, fits } = best;
    const low = song.low + transpose + octave;
    const high = song.high + transpose + octave;
    return {
        tonic: pc(song.tonic + transpose),
        transpose,
        octave,
        fits,
        over: Math.max(0, high - singer.high),
        under: Math.max(0, singer.low - low),
    };
}

/**
 * All 12 keys for the song and singer, from the song's own: `[{ tonic, transpose, octave, status }]`,
 * status 'fits', 'stretch' (within two semitones) or 'no'; each key in its best octave.
 */
export function keyStrip(singer, song, { topOnce = false } = {}) {
    const slack = topOnce ? TOP_ONCE : 0;
    return Array.from({ length: 12 }, (_, i) => {
        const transpose = i - 6; // -6 to +5
        let best = null;
        for (const o of [0, -12, 12, -24, 24]) {
            const out = outside(singer, { low: song.low + transpose + o, high: song.high + transpose + o }, slack);
            if (!best || out < best.out) best = { out, octave: o };
        }
        return { tonic: pc(song.tonic + transpose), transpose, octave: best.octave, status: best.out === 0 ? 'fits' : best.out <= STRETCH ? 'stretch' : 'no' };
    });
}

/** The settings, remembered on this device (js/public/tool-prefs.js): the song (a song in G, D4 to E5, to start), your voice, the options. */
const DEFAULTS = { tonic: 7, minor: false, low: 62, high: 76, voice: null, topOnce: false, noCapo: false };
const inRange = (lo, hi) => (n) => Number.isInteger(n) && n >= lo && n <= hi;
const VALID = { tonic: inRange(0, 11), low: inRange(24, 96), high: inRange(24, 96), voice: (v) => v === null || v === 'yours' || v in VOICE_TYPES };
// The song's highest note stays over its lowest.
const clean = (s) => (s.high > s.low ? s : { ...s, low: DEFAULTS.low, high: DEFAULTS.high });

const HERO = `
<div class="grid justify-items-center gap-1 pt-1" translate="no">
  <p class="text-base font-bold text-muted [@media(max-height:640px)]:hidden" data-w="singIt"></p>
  <p class="text-[clamp(4rem,22vw,6.5rem)] font-black leading-none tracking-tight text-cream [@media(max-height:640px)]:text-[3.25rem]" data-ui="key-answer" data-answer></p>
  <p class="text-sm font-semibold text-muted" data-shift></p>
</div>`;

const VOICE = `
<label class="block"><span class="sr-only" data-w="yourVoice"></span><select class="input w-full text-center font-bold" data-ui="key-voice" data-voice-pick></select></label>`;

const READOUT = `<p class="text-base font-bold text-cream" data-ui="key-guitar" data-guitar></p>`;

const PANEL = `
<div class="absolute inset-0 grid content-start gap-3 overflow-y-auto p-3 text-start">
  <label class="block" hidden data-song-label><span class="sr-only" data-w="pickSong"></span><select class="input" data-ui="key-song" data-song></select></label>
  <a class="justify-self-center text-sm font-bold text-cream underline underline-offset-2" hidden data-ui="key-learn" data-learn data-w="learnIt"></a>
  <div class="flex flex-wrap items-center justify-center gap-2"><span data-low-slot></span><span data-high-slot></span></div>
  <div data-quality></div>
  <div class="grid gap-1.5">
    <p class="text-xs font-bold tracking-[0.12em] text-muted uppercase" data-w="allKeys"></p>
    <div class="grid grid-cols-6 gap-1" role="group" data-ui="key-strip" data-strip></div>
  </div>
</div>`;

/** More: Hillel's settings sheet (tool-layout.js sheetRow), a hairline row each. */
function buildSheet(ui, w) {
    sheetRow(ui.sheet).body.innerHTML = '<label class="check-row"><input type="checkbox" class="checkbox" data-top-once /> <span data-w="topOnce"></span></label>';
    sheetRow(ui.sheet).body.innerHTML = '<label class="check-row"><input type="checkbox" class="checkbox" data-no-capo /> <span data-w="noCapo"></span></label>';
    sheetRow(ui.sheet).body.innerHTML = '<button type="button" class="btn-secondary btn-sm" data-copy data-w="copy"></button>';
}

export function mountKeyFinder(root, { words = {}, keySlot = null, songs = [] } = {}) {
    const w = (key) => words[key] ?? '';
    const ui = toolLayout(root, { keySlot, more: w('settings'), done: w('done'), title: w('settingsTitle'), reset: w('reset') });
    ui.modes.innerHTML = VOICE;
    ui.hero.innerHTML = HERO;
    ui.readout.innerHTML = READOUT;
    ui.panel.innerHTML = PANEL;
    buildSheet(ui, w);
    const card = ui.element;
    card.dataset.tool = 'vocal-key-finder';
    const $ = (selector) => card.querySelector(selector);
    const text = (selector, value) => ($(selector).textContent = value ?? '');
    for (const el of card.querySelectorAll('[data-w]')) el.textContent = w(el.dataset.w);

    const prefs = toolPrefs('vocal-key-finder', { defaults: DEFAULTS, valid: VALID, clean });
    const settings = prefs.value;
    const yours = savedRange();
    // Your own range first when the range test saved one; otherwise a voice type (none picked yet: the middle one).
    if (settings.voice === 'yours' && !yours) settings.voice = null;
    settings.voice ??= yours ? 'yours' : 'tenor';
    let sounding = [];
    let picked = null; // a key picked from the strip, { transpose, octave }, until the song or voice changes

    const noteName = (midi) => {
        const { name, octave } = pitchName(midi);
        return `${name}${octave}`;
    };
    const singer = () => (settings.voice === 'yours' ? yours : VOICE_TYPES[settings.voice]);
    const song = () => ({ tonic: settings.tonic, minor: settings.minor, low: settings.low, high: settings.high });

    const keyStep = stepper(ui.key, { hook: 'key-song-key', label: w('songKey'), name: w('songKey'), lower: w('lower'), higher: w('higher'), step: (by) => {
        settings.tonic = pc(settings.tonic + by);
        changed();
    } });
    const modes = segmented(card.querySelector('[data-quality]'), [{ key: 'major', label: w('major') }, { key: 'minor', label: w('minor') }], { current: settings.minor ? 'minor' : 'major', label: w('modes'), pick: (key) => {
        settings.minor = key === 'minor';
        modes.set(key);
        changed();
    } });
    const lowStep = stepper($('[data-low-slot]'), { hook: 'key-lowest', label: w('lowest'), name: w('lowest'), lower: w('lower'), higher: w('higher'), step: (by) => {
        settings.low = Math.max(24, Math.min(settings.high - 1, settings.low + by));
        hear(settings.low);
        changed();
    } });
    const highStep = stepper($('[data-high-slot]'), { hook: 'key-highest', label: w('highest'), name: w('highest'), lower: w('lower'), higher: w('higher'), step: (by) => {
        settings.high = Math.max(settings.low + 1, Math.min(96, settings.high + by));
        hear(settings.high);
        changed();
    } });

    // The voices: your saved range first (when there is one), then the six types.
    const voiceChips = [...(yours ? [['yours', fill(w('yourRange'), { low: noteName(yours.low), high: noteName(yours.high) })]] : []), ...Object.keys(VOICE_TYPES).map((type) => [type, w(type)])];
    $('[data-voice-pick]').append(...voiceChips.map(([key, label]) => new Option(label, key)));
    if (songs.length) {
        $('[data-song-label]').hidden = false;
        $('[data-song]').append(new Option(w('ownNotes'), ''), ...songs.map((s, i) => new Option(s.name, String(i))));
    }

    /** The answer now: the best key, or the one picked from the strip. */
    function result() {
        const best = keyFor(singer(), song(), { topOnce: settings.topOnce });
        if (!picked) return best;
        const shift = picked.transpose + picked.octave;
        const over = Math.max(0, settings.high + shift - singer().high);
        const under = Math.max(0, singer().low - (settings.low + shift));
        return { ...picked, tonic: pc(settings.tonic + picked.transpose), over, under, fits: !under && over <= (settings.topOnce ? TOP_ONCE : 0) };
    }

    function shiftWords(r) {
        const n = Math.abs(r.transpose);
        const shift = !r.transpose ? w('asWritten') : fill(w(r.transpose > 0 ? 'shiftUp' : 'shiftDown'), { n });
        const octave = r.octave < 0 ? w('octaveDown') : r.octave > 0 ? w('octaveUp') : '';
        return [shift, octave].filter(Boolean).join(' · ');
    }

    function guitarWords(r) {
        if (settings.noCapo) return '';
        const capo = capoFor(r.tonic, settings.minor);
        if (!capo) return '';
        if (!capo.capo) return fill(w('guitarOpen'), { key: keyName(r.tonic, settings.minor) });
        return fill(w('guitar'), { n: capo.capo, shape: keyName(capo.shape, settings.minor) });
    }

    function render() {
        const r = result();
        card.dataset.fits = String(r.fits);
        card.dataset.key = keyName(r.tonic, settings.minor);
        keyStep.set(keyName(settings.tonic, settings.minor));
        lowStep.set(noteName(settings.low), { atLow: settings.low <= 24, atHigh: settings.low >= settings.high - 1 });
        highStep.set(noteName(settings.high), { atLow: settings.high <= settings.low + 1, atHigh: settings.high >= 96 });
        text('[data-answer]', keyName(r.tonic, settings.minor));
        text('[data-shift]', shiftWords(r));
        text('[data-guitar]', guitarWords(r));
        $('[data-guitar]').hidden = !$('[data-guitar]').textContent;
        if (r.fits) ui.setStatus({ tone: 'good', text: w('fits'), hint: w('fitsHint') });
        else if (r.over + r.under <= STRETCH) {
            const hint = [r.over && fill(w('stretchTop'), { n: r.over }), r.under && fill(w('stretchBottom'), { n: r.under })].filter(Boolean).join(', ');
            ui.setStatus({ tone: 'muted', text: w('stretch'), hint });
        } else ui.setStatus({ tone: 'warn', text: w('tooFar'), hint: w('tooFarHint') });
        // The 12 keys, the song's own in the middle: each says whether it fits, and a tap sings in it.
        $('[data-strip]').replaceChildren(...keyStrip(singer(), song(), { topOnce: settings.topOnce }).map((k) => {
            const b = Object.assign(document.createElement('button'), { type: 'button', textContent: keyName(k.tonic, settings.minor) });
            b.dataset.strip = String(k.transpose);
            b.dataset.status = k.status;
            b.dataset.octave = String(k.octave);
            const on = k.tonic === r.tonic;
            b.setAttribute('aria-pressed', String(on));
            b.setAttribute('aria-label', `${keyName(k.tonic, settings.minor)}: ${w({ fits: 'keyFits', stretch: 'keyStretch', no: 'keyNo' }[k.status])}`);
            // Fits: the green tint and ring say it; the letter stays in the page's ink, readable in every theme (AA).
            const tone = { fits: 'bg-good/20 text-cream ring-1 ring-inset ring-good', stretch: 'bg-panel text-cream', no: 'bg-panel text-muted line-through' }[k.status];
            b.className = `min-h-9 rounded-lg text-sm font-bold ${on ? 'bg-[var(--primary)] text-[var(--primary-ink)]' : tone}`;
            return b;
        }));
        $('[data-voice-pick]').value = settings.voice;
        ui.setLabel(w('theSong'), fill(w('range'), { low: noteName(settings.low), high: noteName(settings.high) }));
        ui.setPrimary({ label: sounding.length ? w('stop') : fill(w('play'), { key: keyName(r.tonic, settings.minor) }), active: sounding.length > 0 });
        $('[data-top-once]').checked = settings.topOnce;
        $('[data-no-capo]').checked = settings.noCapo;
    }

    function changed({ keep = false } = {}) {
        if (!keep) picked = null;
        prefs.save();
        render();
    }

    function stop() {
        for (const v of sounding) v.stop();
        sounding = [];
    }

    /** One note, briefly (a note picker's new note). */
    function hear(midi) {
        stop();
        const v = voice(midiToHz(midi), { timbre: 'oo', level: 0.18 });
        v.stop(0.7);
    }

    /** The song in the new key, as you'd sing it: its bottom note, its top note, then the key's chord, on the audio clock. */
    function play() {
        stop();
        const r = result();
        const shift = r.transpose + r.octave;
        const low = settings.low + shift;
        const high = settings.high + shift;
        const tonic = low - pc(low - r.tonic); // the key's tonic at or under the bottom note
        const chord = [tonic, tonic + (settings.minor ? 3 : 4), tonic + 7];
        const t = audio().currentTime + 0.05;
        const note = (midi, at, length) => {
            const v = voice(midiToHz(midi), { at, timbre: 'oo', level: 0.16 });
            v.stop(at + length - audio().currentTime);
            return v;
        };
        sounding = [note(low, t, 0.9), note(high, t + 1, 0.9), ...chord.map((m) => note(m, t + 2.1, 1.6))];
        const mine = sounding;
        render();
        Promise.all(mine.map((v) => v.ended)).then(() => {
            if (sounding !== mine) return;
            sounding = [];
            render();
        });
    }

    card.addEventListener('click', (event) => {
        const b = event.target.closest('button');
        if (!b || !card.contains(b)) return;
        if (b.dataset.strip !== undefined) {
            // A key picked from the strip: the answer becomes that key (the song stays as it is).
            picked = { transpose: Number(b.dataset.strip), octave: Number(b.dataset.octave) };
            changed({ keep: true });
        }
    });
    $('[data-voice-pick]').addEventListener('change', (event) => {
        settings.voice = event.target.value;
        changed();
    });
    $('[data-song]').addEventListener('change', (event) => {
        const choice = event.target.value === '' ? null : songs[Number(event.target.value)];
        // A song the page can teach: a link to learn it.
        $('[data-learn]').hidden = !choice?.href;
        if (choice?.href) $('[data-learn]').href = choice.href;
        if (!choice) return;
        const { tonic, minor } = parseKey(choice.key);
        Object.assign(settings, { tonic, minor, low: choice.low, high: choice.high });
        modes.set(settings.minor ? 'minor' : 'major');
        changed();
    });
    // More's Reset: the song, your voice and the options back to their defaults (your own range first again).
    ui.onReset(() => {
        prefs.reset();
        settings.voice = yours ? 'yours' : 'tenor';
        modes.set(settings.minor ? 'minor' : 'major');
        $('[data-song]').value = '';
        changed();
    });
    $('[data-top-once]').addEventListener('change', (event) => {
        settings.topOnce = event.target.checked;
        changed();
    });
    $('[data-no-capo]').addEventListener('change', (event) => {
        settings.noCapo = event.target.checked;
        changed();
    });
    $('[data-copy]').addEventListener('click', async () => {
        const r = result();
        try {
            await navigator.clipboard.writeText(fill(w('copyText'), { key: keyName(r.tonic, settings.minor), shift: shiftWords(r), guitar: guitarWords(r) }).trim());
            text('[data-copy]', w('copied'));
        } catch (error) {
            console.warn('The answer was not copied', error);
        }
    });
    ui.onPrimary(() => {
        if (!sounding.length) return play();
        stop();
        render();
    });
    render();
    return {
        play,
        result,
        stop() {
            stop();
            keyStep.destroy();
        },
        element: card,
    };
}
