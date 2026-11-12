// The four-part harmony voicer, /tools/four-part-harmony (docs/tools-plan.md, batch 5; the check in
// docs/tools/competitors-transposer-satb.md, section 2), on the tools' one layout (js/public/tool-layout.js):
// type chords ("C Am F G7 C") and get four singable parts, soprano, alto, tenor and bass, voiced as the
// app's own arranger voices a song for a choir: voices that hold or move by step, no parallel fifths or
// octaves, the third there, the leading tone and the seventh resolving, each part in its range. The modes
// slot is the chords; the hero is the grand staff, the chord names over their columns (the one sounding
// lit); the pill says it's voiced, or what it couldn't read; the readout is the four parts, each a tap to
// leave it out (hear one part alone, or sing it yourself); the panel names every part's notes; Play is
// pinned. Behind More: the arranger's styles (Simple keeps every root in the bass, as a chord symbol
// means; Warm lets the bass take a first inversion for a smoother line; Bright lifts the alto and tenor),
// the tempo, and copying the parts.
//
// A thin wrapper: the chords read by notes.js parseChordSymbol() and chordQuality() (the arranger's
// qualities; richer chords sung as the chord under them), the soprano chosen here from each chord's tones
// (the smoothest line in the soprano's range, ending on the root), and alto, tenor and bass from
// js/arrange.js arrange() with the chords given, as the template creator voices a song. The staff is
// js/public/staff.js grandStaff(), the sound voices.js voice() on the audio clock. A slash chord keeps its
// bass (G/B: B in the bass, the arranger's slashBass). The settings stay on this device (tool-prefs.js).
// The words come from the page (:n, :chord, :key and :bar are filled in here), set as text.
//
//   import { mountSatbVoicer, WORDS } from '/js/public/tools/satb-voicer.js';
//   const voicer = mountSatbVoicer(el, { words, keySlot });
//
// The handle: set(text), voiced(), play(), stop() and element. readChords(), keyOf() and voiceSatb() are pure.

import { arrange, STYLES, TONES } from '../../arrange.js';
import { midiToHz } from '../../dsp.js';
import { chordQuality, parseChordSymbol, pitchName } from '../../notes.js';
import { grandStaff } from '../staff.js';
import { segmented, sheetRow, toolLayout } from '../tool-layout.js';
import { toolPrefs } from '../tool-prefs.js';
import { audio, voice } from '../voices.js';
import { arcade, partInk, SHARE_FILES, shareCard, shareUrl } from './share-card.js';

/** The words a page passes, each translated. */
export const WORDS = [
    'chords', 'placeholder', 'idleHint', 'voiced', 'voicedHint', 'unknown', 'parallels', 'inKey', 'parts', 'soprano', 'alto', 'tenor', 'bass',
    'partOn', 'play', 'stop', 'settings', 'settingsTitle', 'reset', 'done', 'style', 'simple', 'warm', 'bright', 'tempo', 'slower', 'faster', 'bpm', 'copy', 'copied',
    'staffLabel', 'sample', 'share', 'shareText', 'cardTitle', 'cardChallenge', 'cardTagline', 'cardSite', 'cardSaved',
];

// The share card's drawing (share-card.js), kept offline by the tools' service worker (page.js).
export const WARM = SHARE_FILES;
const CARD_CHORDS = 6; // a card shows the first six chords (and says there are more)

export const PARTS = ['soprano', 'alto', 'tenor', 'bass'];
const SOPRANO = { low: 62, high: 77, home: 67 }; // D4 to F5, around G4 (where a hymnal's soprano sits)
const BEATS = 2; // each chord held two beats
const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));
const pc = (n) => ((n % 12) + 12) % 12;

/** Typed chords ("C | Am F | G7 C", commas too) as `{ chords, unknown }`: each chord `{ symbol, root, quality, bass }`. */
export function readChords(text) {
    const chords = [];
    const unknown = [];
    for (const word of String(text ?? '').split(/[\s,|]+/).filter(Boolean)) {
        const c = parseChordSymbol(word);
        const quality = c ? chordQuality(c.suffix) : null;
        if (c && quality) chords.push({ symbol: word, root: c.root, quality, bass: c.bass });
        else unknown.push(word);
    }
    return { chords, unknown };
}

/** The key the chords are in: the last chord's root (songs end at home) when it's major or minor, else the first's. */
export function keyOf(chords) {
    const home = ['maj', 'min'].includes(chords.at(-1)?.quality) ? chords.at(-1) : chords[0];
    return home ? { tonic: home.root, mode: home.quality === 'min' ? 'minor' : 'major' } : { tonic: 0, mode: 'major' };
}

/**
 * The soprano: one note a chord from its tones in the soprano's range, the smoothest line (steps over
 * leaps, near the middle of the range, no parallel octaves or fifths against the root in the bass),
 * ending on the root when the last chord is home, and resolving as the top line must (the arranger's
 * check: a dominant seventh steps down, and the leading tone on V rises to the tonic on I). A search over
 * the whole line.
 */
export function sopranoLine(chords, key = keyOf(chords)) {
    const leading = pc(key.tonic + 11);
    const options = chords.map((c) => {
        const tones = TONES[c.quality].map((t) => pc(c.root + t));
        const out = [];
        for (let p = SOPRANO.low; p <= SOPRANO.high; p++) if (tones.includes(pc(p))) out.push(p);
        return out;
    });
    // Over a slash chord's bass (G/B), not the same note on top: it's usually the third, and never doubled in the outer parts.
    const standing = (p, i) => 0.08 * Math.abs(p - SOPRANO.home) + (i === chords.length - 1 && pc(p) !== chords[i].root ? 2 : 0)
        + (chords[i].bass !== null && chords[i].bass !== undefined && chords[i].bass !== chords[i].root && pc(p) === chords[i].bass ? 4 : 0);
    const moving = (p, q, i) => {
        const leap = Math.abs(q - p);
        let cost = leap <= 2 ? leap * 0.3 : leap <= 4 ? 0.6 + leap * 0.4 : 2 + leap * 0.6;
        // The bass sings the root: the same fifth or octave over it twice running, both moving, is a parallel.
        const was = pc(p - chords[i - 1].root);
        const now = pc(q - chords[i].root);
        if (chords[i].root !== chords[i - 1].root && p !== q && was === now && (now === 0 || now === 7)) cost += 6;
        // A dominant seventh on top steps down (G7's F to E).
        if (chords[i - 1].quality === 'dom7' && was === 10 && !(q < p && p - q <= 2)) cost += 6;
        // The leading tone on top, V going to I, rises to the tonic (B to C).
        if (pc(p) === leading && chords[i - 1].root === pc(key.tonic + 7) && chords[i].root === key.tonic && q !== p + 1) cost += 6;
        return cost;
    };
    let total = options[0].map((p) => standing(p, 0));
    const back = [];
    for (let i = 1; i < options.length; i++) {
        const from = [];
        total = options[i].map((q) => {
            let best = Infinity;
            let arg = 0;
            options[i - 1].forEach((p, j) => {
                const cost = total[j] + moving(p, q, i);
                if (cost < best) [best, arg] = [cost, j];
            });
            from.push(arg);
            return best + standing(q, i);
        });
        back.push(from);
    }
    let k = total.indexOf(Math.min(...total));
    const picked = [k];
    for (let i = back.length - 1; i >= 0; i--) picked.unshift((k = back[i][k]));
    return picked.map((j, i) => options[i][j]);
}

/**
 * Four parts for the chords: `{ soprano, alto, tenor, bass, key, warnings }`, each part a pitch a chord
 * (MIDI), the lower three from the app's arranger under the soprano. `warnings` are the chords (by
 * index) where the soprano and bass still move in parallel fifths or octaves.
 */
export function voiceSatb(chords, { style = 'simple' } = {}) {
    if (!chords.length) return null;
    const key = keyOf(chords);
    const soprano = sopranoLine(chords, key);
    // Each chord its own syllable ("oo"), so the arranger voices every chord afresh: on no syllable it would
    // hold the alto and tenor over the change, as under a tune's long note.
    const melody = soprano.map((pitch, i) => ({ beat: i * BEATS, length: BEATS, pitch, lyric: 'oo' }));
    // A slash chord's bass (G/B) is kept: the arranger pins it and voices the other three over it.
    const given = chords.map((c, i) => ({ beat: i * BEATS, root: c.root, quality: c.quality, bass: c.bass }));
    // `four`: all four parts always sing (the arranger's textbook voicing: complete chords, the root doubled).
    const result = arrange(melody, { key, timeSignature: '4/4', style, chords: given, phraseEnds: [chords.length * BEATS], four: true, slashBass: true });
    // A part's note at each chord (the arranger may hold a note across chords, or move within one: the first sounding).
    const at = (line, beat) => line.findLast((n) => n.beat <= beat + 1e-6 && n.beat + n.length > beat + 1e-6)?.pitch ?? line.find((n) => n.beat >= beat)?.pitch ?? null;
    const part = (line) => chords.map((_, i) => at(line, i * BEATS));
    const bass = part(result.parts.bass);
    const warnings = result.warnings.map((beat) => Math.round(beat / BEATS));
    return { soprano, alto: part(result.parts.alto), tenor: part(result.parts.tenor), bass, key, warnings };
}

/** The settings, remembered on this device (js/public/tool-prefs.js): the chords typed (a convenience), the style and tempo. */
const DEFAULTS = { text: '', style: 'simple', tempo: 72 };
const VALID = { style: (v) => STYLES.includes(v), tempo: (v) => Number.isInteger(v) && v >= 40 && v <= 160 };

const INPUT = `
<label class="block"><span class="sr-only" data-w="chords"></span>
  <input type="text" class="input w-full text-center font-bold" autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="done" data-ui="satb-chords" data-chords>
</label>`;

const HERO = `<div class="overflow-x-auto pb-1 text-cream [&>svg]:mx-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" data-ui="satb-staff" data-staff></div>`;

// The parts, each a tap to leave it out; under them, Share the parts once they're voiced (docs/improvement-log.md, row 5).
const READOUT = `
<div class="grid grid-cols-4 gap-1.5" role="group" data-ui="satb-parts" data-part-toggles></div>
<p class="mt-2 flex flex-wrap items-center justify-center gap-x-3 gap-y-1"><button type="button" class="btn-secondary btn-sm" data-share data-ui="satb-share" data-w="share"></button><span class="text-sm text-good" aria-live="polite" data-share-said></span></p>`;

const PANEL = `
<div class="absolute inset-0 overflow-auto p-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
  <table class="border-separate border-spacing-x-3 border-spacing-y-1 text-start text-[0.9375rem] tabular-nums" translate="no" data-ui="satb-table">
    <thead><tr data-head></tr></thead>
    <tbody data-rows></tbody>
  </table>
</div>`;

/** More: Hillel's settings sheet (tool-layout.js sheetRow), a hairline row each. */
function buildSheet(ui, w) {
    sheetRow(ui.sheet, { label: w('style') }).body.dataset.styles = '';
    const tempo = sheetRow(ui.sheet, { label: w('tempo') });
    tempo.side.innerHTML = '<span class="tabular-nums text-cream" data-tempo-value></span>';
    tempo.body.innerHTML = '<input type="range" class="range w-full" min="40" max="160" step="2" data-tempo>';
    sheetRow(ui.sheet).body.innerHTML = '<button type="button" class="btn-secondary btn-sm" data-copy data-w="copy"></button>';
}

export function mountSatbVoicer(root, { words = {}, keySlot = null, onShared = null } = {}) {
    const w = (key) => words[key] ?? '';
    const ui = toolLayout(root, { keySlot, more: w('settings'), done: w('done'), title: w('settingsTitle'), reset: w('reset') });
    ui.modes.innerHTML = INPUT;
    ui.hero.innerHTML = HERO;
    ui.readout.innerHTML = READOUT;
    ui.panel.innerHTML = PANEL;
    buildSheet(ui, w);
    const card = ui.element;
    card.dataset.tool = 'satb-voicer';
    const $ = (selector) => card.querySelector(selector);
    const text = (selector, value) => ($(selector).textContent = value ?? '');
    for (const el of card.querySelectorAll('[data-w]')) el.textContent = w(el.dataset.w);
    $('[data-chords]').placeholder = w('placeholder');

    const prefs = toolPrefs('four-part-harmony', { defaults: DEFAULTS, valid: VALID });
    const settings = prefs.value;
    if (!settings.text) settings.text = w('sample');
    $('[data-chords]').value = settings.text;
    const on = { soprano: true, alto: true, tenor: true, bass: true };
    let read = { chords: [], unknown: [] };
    let parts = null;
    let sounding = [];
    let lit = -1;
    let raf = 0;

    $('[data-part-toggles]').append(...PARTS.map((part) => {
        const b = Object.assign(document.createElement('button'), { type: 'button', className: 'filter-chip justify-center', textContent: w(part) });
        b.dataset.part = part;
        b.setAttribute('aria-label', fill(w('partOn'), { part: w(part) }));
        return b;
    }));
    const styles = segmented($('[data-styles]'), STYLES.map((key) => ({ key, label: w(key) })), { current: settings.style, label: w('style'), pick: (key) => {
        settings.style = key;
        styles.set(key);
        prefs.save();
        compute();
    } });
    $('[data-tempo]').value = String(settings.tempo);

    const noteName = (midi) => {
        if (midi === null) return '–';
        const { name, octave } = pitchName(midi);
        return `${name}${octave}`;
    };

    /** The key the chords are in ("C", "Am"), or nothing before they're voiced. */
    function keyText() {
        return parts ? `${['C', 'D♭', 'D', 'E♭', 'E', 'F', 'F♯', 'G', 'A♭', 'A', 'B♭', 'B'][parts.key.tonic]}${parts.key.mode === 'minor' ? 'm' : ''}` : ''; // i18n-ignore: key names
    }

    function compute() {
        stop();
        read = readChords(settings.text);
        parts = read.chords.length ? voiceSatb(read.chords, { style: settings.style }) : null;
        render();
    }

    function render() {
        card.dataset.voiced = String(!!parts);
        const names = read.chords.map((c) => c.symbol);
        // The staff: each chord's four notes in a column, its name over it.
        // Soprano and alto on the treble staff, tenor and bass on the bass staff, as a choir's score has them.
        const shown = PARTS.filter((p) => on[p]);
        const columns = parts ? read.chords.map((_, i) => shown.map((p) => parts[p][i]).filter((n) => n !== null)) : [];
        const upper = shown.filter((p) => p === 'soprano' || p === 'alto').length;
        $('[data-staff]').replaceChildren(grandStaff(columns, { names, upper, label: fill(w('staffLabel'), { n: columns.length }) }));
        // The parts by name, chord by chord.
        const head = [Object.assign(document.createElement('th'), { className: 'text-start text-xs font-bold tracking-[0.08em] text-muted uppercase', textContent: '' }), ...names.map((n) => Object.assign(document.createElement('th'), { className: 'text-start font-extrabold text-cream', textContent: n }))];
        $('[data-head]').replaceChildren(...head);
        $('[data-rows]').replaceChildren(...(parts ? PARTS.map((p) => {
            const tr = document.createElement('tr');
            tr.dataset.part = p;
            tr.className = on[p] ? 'text-cream' : 'text-muted opacity-50';
            tr.append(Object.assign(document.createElement('th'), { scope: 'row', className: 'text-start text-xs font-bold tracking-[0.08em] text-muted uppercase', textContent: w(p) }),
                ...parts[p].map((n) => Object.assign(document.createElement('td'), { textContent: noteName(n) })));
            return tr;
        }) : []));
        for (const b of $('[data-part-toggles]').children) b.setAttribute('aria-pressed', String(on[b.dataset.part]));
        if (!settings.text.trim()) ui.setStatus({ hint: w('idleHint') });
        else if (read.unknown.length) ui.setStatus({ tone: 'warn', text: fill(w('unknown'), { chord: read.unknown[0] }) });
        else if (parts?.warnings.length) ui.setStatus({ tone: 'muted', text: w('voiced'), hint: fill(w('parallels'), { bar: parts.warnings[0] + 1 }) });
        else if (parts) ui.setStatus({ tone: 'good', text: w('voiced'), hint: w('voicedHint') });
        else ui.setStatus({ hint: w('idleHint') });
        ui.setLabel(w('parts'), parts ? fill(w('inKey'), { key: keyText() }) : '');
        ui.setPrimary({ label: sounding.length ? w('stop') : w('play'), active: sounding.length > 0 });
        ui.primary.disabled = !parts;
        $('[data-share]').hidden = !parts || read.unknown.length > 0;
        text('[data-tempo-value]', fill(w('bpm'), { n: settings.tempo }));
        light(-1);
    }

    /** Lights the chord sounding now on the staff and in the table (the display only). */
    function light(i) {
        if (i === lit) return;
        lit = i;
        for (const g of $('[data-staff]').querySelectorAll('[data-column]')) g.setAttribute('fill', Number(g.dataset.column) === i ? 'var(--primary)' : 'currentColor');
    }

    function stop() {
        cancelAnimationFrame(raf);
        for (const v of sounding) v.stop();
        sounding = [];
    }

    /** The parts that are on, each chord held its two beats, on the audio clock; the staff follows. */
    function play() {
        if (!parts) return;
        stop();
        const c = audio();
        const beat = 60 / settings.tempo;
        const t0 = c.currentTime + 0.08;
        for (const p of PARTS.filter((x) => on[x])) {
            parts[p].forEach((midi, i) => {
                if (midi === null) return;
                const at = t0 + i * BEATS * beat;
                const v = voice(midiToHz(midi), { at, timbre: 'oo', level: 0.07, attack: 0.05, release: 0.12 });
                v.stop(at + BEATS * beat - 0.04 - c.currentTime);
                sounding.push(v);
            });
        }
        const mine = sounding;
        const length = read.chords.length * BEATS * beat;
        const follow = () => {
            if (sounding !== mine) return;
            const i = Math.floor((c.currentTime - t0) / (BEATS * beat));
            light(i >= 0 && i < read.chords.length ? i : -1);
            raf = requestAnimationFrame(follow);
        };
        raf = requestAnimationFrame(follow);
        render();
        Promise.all(mine.map((v) => v.ended)).then(() => {
            if (sounding !== mine) return;
            cancelAnimationFrame(raf);
            sounding = [];
            render();
        });
        return length;
    }

    // More's Reset: the style and tempo back to their defaults; the chords typed stay.
    ui.onReset(() => {
        const kept = settings.text;
        prefs.reset();
        settings.text = kept;
        prefs.save();
        styles.set(settings.style);
        $('[data-tempo]').value = String(settings.tempo);
        compute();
    });
    $('[data-chords]').addEventListener('input', (event) => {
        settings.text = event.target.value;
        prefs.save();
        compute();
    });
    $('[data-part-toggles]').addEventListener('click', (event) => {
        const b = event.target.closest('[data-part]');
        if (!b) return;
        on[b.dataset.part] = !on[b.dataset.part];
        // Never all four off: the last one stays.
        if (!Object.values(on).some(Boolean)) on[b.dataset.part] = true;
        stop();
        render();
    });
    $('[data-tempo]').addEventListener('input', (event) => {
        settings.tempo = Number(event.target.value);
        prefs.save();
        stop();
        render();
    });
    // Share the parts: the chords and each part's notes as a card made on the device (result-card.js drawAnswerCard), with
    // the line and the tool's link (?ref=share-four-part-harmony). The first six chords; nothing about who.
    $('[data-share]').addEventListener('click', async () => {
        if (!parts) return;
        const button = $('[data-share]');
        const more = read.chords.length > CARD_CHORDS ? ' …' : '';
        const first = (list) => list.slice(0, CARD_CHORDS).join(' ') + more;
        const chords = first(read.chords.map((c) => c.symbol));
        button.disabled = true;
        const done = await shareCard({
            fileName: 'four-part-harmony.png',
            text: fill(w('shareText'), { chords: read.chords.map((c) => c.symbol).join(' ') }),
            url: shareUrl('four-part-harmony', 'share-four-part-harmony'),
            draw: ({ drawAnswerCard }) => drawAnswerCard({
                words: {
                    title: w('cardTitle'), hero: chords, line: fill(w('inKey'), { key: keyText() }),
                    rows: PARTS.map((p) => [w(p), first(parts[p].map(noteName))]),
                    challenge: w('cardChallenge'), tagline: w('cardTagline'), site: w('cardSite'), arcade: arcade(),
                },
                color: partInk(4),
            }),
        });
        if (done === 'saved') text('[data-share-said]', w('cardSaved'));
        if (done) onShared?.('four-part-harmony', done);
        button.disabled = false;
    });
    $('[data-copy]').addEventListener('click', async () => {
        if (!parts) return;
        const lines = [['', ...read.chords.map((c) => c.symbol)], ...PARTS.map((p) => [w(p), ...parts[p].map(noteName)])].map((cells) => cells.join('\t'));
        try {
            await navigator.clipboard.writeText(lines.join('\n'));
            text('[data-copy]', w('copied'));
        } catch (error) {
            console.warn('The parts were not copied', error);
        }
    });
    ui.onPrimary(() => {
        if (!sounding.length) return play();
        stop();
        render();
    });
    compute();
    return {
        set(value) {
            $('[data-chords]').value = value;
            settings.text = value;
            prefs.save();
            compute();
        },
        voiced: () => parts,
        play,
        stop,
        element: card,
    };
}
