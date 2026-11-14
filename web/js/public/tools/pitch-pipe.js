// The pitch pipe (/tools/pitch-pipe), on the tools' one layout (tool-layout.js; docs/design-rules.md, "Free tools
// share one layout"). No key control in the brand row: A4 is assumed 440 and set under More, as the tuner's (Hillel:
// "assume 440, move it behind the more button"), with an "A4 = 442" chip under the status only when it isn't. The hero is
// the round pipe, thirteen notes C to C, the note last sounded named in its middle; the status pill says what's
// sounding and its Hz; over the bar sit the octave (OCTAVE − C4 +) and the Chord button (that note's chord, press and
// hold to keep it); the primary sounds the note again ("Sound B♭4"), for giving the pitch. A quick tap gives a
// two-second note; a press held keeps it sounding until it's let go (or slides off, or the page is hidden). More's
// sheet holds your songs with their keys (a set list kept on this device), the sound, louder for a room, the chord's
// kind and tuning, the reference A, sharps or flats, and keeping a note sounding until it's tapped again. On a
// computer, the keys A to K play it as a piano's do.
// UI and state only: the sound is voices.js's, the frequencies dsp.js's, the chord's tuning just.js's.
//
//   mountPitchPipe(el, { words, keySlot })   // words: every key in WORDS; returns { stop() }

import { midiToHz } from '../../dsp.js';
import { chordTones, pureOffset } from '../../just.js';
import { pitchName } from '../../notes.js';
import { stepper, toolLayout } from '../tool-layout.js';
import { REFERENCE } from '../tuner-reading.js';
import { chord, voice, warmAudio } from '../voices.js';
import { atClock, chips, choice, fill, num, onHidden, oneRow, press, remember, switchRow, toggle, unlock } from './kit.js';

/** The words the page passes (lang site.tools.pages.pitch-pipe.card). */
export const WORDS = [
    'notes', 'spoken', 'sharps', 'spoken_sharps', 'flats', 'spoken_flats', 'note_label', 'notes_label', 'idle', 'sounding', 'hz',
    'octave', 'octave_name', 'lower', 'higher', 'more', 'done', 'sound_note', 'chord_label', 'chords_named', 'sound', 'timbres', 'ref_label', 'reference',
    'louder', 'kind', 'kinds', 'tuning_kind', 'tunings', 'tuning', 'spelling', 'spellings', 'latch', 'keys', 'songs', 'songs_hint',
    'song_name', 'song_save', 'song_play', 'song_remove', 'songs_empty',
    'settingsTitle', 'reset',
];

// Everything the pitch pipe can be set to, in one place.
export const PIPE = {
    timbre: 'reed', // or 'triangle' (Soft) or 'oo' (Voice): chips in More
    timbres: ['reed', 'triangle', 'oo'],
    level: 0.2,
    louder: 0.34, // the louder level in More, for a room
    chordSum: 0.5, // a chord's voices together, well under clipping
    attack: 0.025, // fades in and out in about 100 ms: no click
    release: 0.03,
    tap: 2, // seconds a quick tap sounds
    hold: 0.3, // a press longer than this is a hold: the note stops when it's let go
    octave: 4, // the pipe's lowest C is C4, as a real pipe's
    octaves: [2, 5], // how far the octave stepper goes
    reference: 440,
    referenceRange: [REFERENCE.low, REFERENCE.high], // the A4 stepper's ends, the tuner's own
    references: REFERENCE.presets, // the usual A4s, the same list as the tuner and the note chart
    kind: 'major', // the chord the primary plays
    kinds: { major: 'maj', minor: 'min', seventh: 'dom7' }, // just.js's qualities (dom7's seventh is 7:4)
    tuning: 'pure', // the chord's other notes tuned pure over the note, or 'equal'
    keys: 'awsedftgyhujk', // the computer keys for the thirteen notes
    spelling: 'common', // black keys as keys are usually named (B♭, E♭, F♯); or 'sharps' or 'flats'
    spellings: { common: ['notes', 'spoken'], sharps: ['sharps', 'spoken_sharps'], flats: ['flats', 'spoken_flats'] },
    songs: 40, // songs kept, at most
};

// Under the status: what isn't the default (A4 other than 440), a chip that opens More.
const READOUT = '<div class="flex flex-wrap items-center justify-center gap-1.5 empty:hidden" data-ui="pipe-summary"></div>';

const HERO = `
<div class="s-pipe" role="group" data-pipe-ring>
  <p class="s-pipe-mid" aria-hidden="true" translate="no"><b data-pipe-name></b></p>
</div>`;

const SHEET = `
<div class="grid gap-2">
  <p class="label" id="pipe-songs-l" data-w="songs"></p>
  <p class="help" data-w="songs_hint"></p>
  <ul class="s-pipe-list" aria-labelledby="pipe-songs-l" data-song-list></ul>
  <p class="help" data-songs-empty data-w="songs_empty"></p>
  <form class="s-pipe-add" data-song-form>
    <input class="input" type="text" maxlength="60" autocomplete="off" enterkeyhint="done" data-song-name>
    <button type="submit" class="btn-secondary" data-song-save></button>
  </form>
</div>
<div data-chips="timbre"></div>
<div data-switch="louder"></div>
<div data-chips="kind"></div>
<div data-chips="tuning"></div>
<div data-chips="ref"></div>
<div data-chips="spelling"></div>
<div data-switch="latch"></div>
<p class="help" data-w="keys"></p>`;

export function mountPitchPipe(el, { words = {}, keySlot = null } = {}) {
    warmAudio(); // the sound's AudioContext made while the page is idle, so the first tap only resumes it (voices.js)
    const config = { ...PIPE };
    const w = (key) => words[key] ?? '';
    const ui = toolLayout(el, { keySlot, more: w('more'), done: w('done'), title: w('settingsTitle'), reset: w('reset') });
    ui.element.dataset.tool = 'pitch-pipe';
    ui.hero.innerHTML = HERO;
    ui.readout.innerHTML = READOUT;
    ui.sheet.innerHTML = SHEET;
    const $ = (sel) => el.querySelector(sel);
    for (const node of el.querySelectorAll('[data-w]')) node.textContent = w(node.dataset.w);
    chips($('[data-chips=timbre]'), 'timbre', w('sound'), Object.fromEntries(config.timbres.map((t) => [t, words.timbres?.[t] ?? t])));
    chips($('[data-chips=kind]'), 'kind', w('kind'), words.kinds ?? {});
    chips($('[data-chips=tuning]'), 'tuning', w('tuning_kind'), words.tunings ?? {});
    chips($('[data-chips=ref]'), 'ref', w('tuning'), Object.fromEntries(config.references.map((hz) => [hz, String(hz)])));
    oneRow($('[data-chips=ref] [role=group]'));
    chips($('[data-chips=spelling]'), 'spelling', w('spelling'), words.spellings ?? {});
    const louderSwitch = switchRow($('[data-switch=louder]'), 'louder', w('louder'));
    const latchSwitch = switchRow($('[data-switch=latch]'), 'latch', w('latch'));
    const songName = $('[data-song-name]');
    songName.placeholder = w('song_name');
    songName.setAttribute('aria-label', w('song_name'));

    // The ring: thirteen notes, C at the top going clockwise.
    const ring = $('[data-pipe-ring]');
    ring.setAttribute('aria-label', w('notes_label'));
    const keys = Array.from({ length: 13 }, (_, i) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 's-tool-pick s-pipe-key';
        b.dataset.note = String(i);
        b.style.setProperty('--i', String(i));
        b.setAttribute('aria-pressed', 'false');
        return b;
    });
    ring.prepend(...keys);
    const name = $('[data-pipe-name]');

    const saved = remember('pitch-pipe', {
        octave: config.octave, ref: String(config.reference), timbre: config.timbre, kind: config.kind, tuning: config.tuning,
        louder: false, latch: false, spelling: config.spelling, songs: [],
    });
    const set = saved.value;
    let last = keys[0]; // the note the chord builds on
    let heard = false; // a note has sounded, so the middle names it
    let sounding = null; // { button, voices, cancel }

    const choices = {
        timbre: choice(el, 'timbre', (v) => saved.save({ timbre: v })),
        kind: choice(el, 'kind', (v) => keep({ kind: v })),
        tuning: choice(el, 'tuning', (v) => keep({ tuning: v })),
        ref: choice(el, 'ref', (v) => keep({ ref: v })),
        spelling: choice(el, 'spelling', (v) => keep({ spelling: v })),
    };
    const switches = {
        louder: toggle(louderSwitch, (on) => saved.save({ louder: on })),
        latch: toggle(latchSwitch, (on) => {
            saved.save({ latch: on });
            if (!on) quiet();
        }),
    };
    Object.entries(choices).forEach(([k, c]) => c.set(set[k]));
    Object.entries(switches).forEach(([k, s]) => s.set(set[k]));
    // More's Reset: every setting back to its default; your saved songs stay (they're yours, not a setting).
    ui.onReset(() => {
        quiet();
        const songs = set.songs;
        saved.reset();
        saved.save({ songs });
        Object.entries(choices).forEach(([k, c]) => c.set(set[k]));
        Object.entries(switches).forEach(([k, s]) => s.set(set[k]));
        render();
    });

    // The reference A in More, its stepper beside the usual ones; the octave and the Chord button over the bar.
    const refRow = $('[data-chips=ref] [role=group]');
    const a4Slot = document.createElement('span');
    a4Slot.className = 'shrink-0';
    refRow.prepend(a4Slot);
    refRow.classList.add('items-center');
    const a4 = stepper(a4Slot, {
        hook: 'pipe-reference',
        label: w('ref_label'),
        name: w('tuning'),
        lower: w('lower'),
        higher: w('higher'),
        step: (by) => keep({ ref: String(Math.min(config.referenceRange[1], Math.max(config.referenceRange[0], reference() + by))) }),
    });
    ui.controls.innerHTML = '<div class="flex items-center gap-2.5"><span class="shrink-0" data-octave-slot></span><button type="button" class="btn-secondary min-h-11 min-w-0 flex-1" aria-pressed="false" data-chord></button></div>';
    const chordButton = $('[data-chord]');
    const octave = stepper($('[data-octave-slot]'), {
        label: w('octave'),
        name: w('octave_name'),
        lower: w('lower'),
        higher: w('higher'),
        step: (by) => toOctave(set.octave + by),
    });
    // The octave the pipe sounds in, kept on this device: the stepper, Z and X on a computer, and a saved song's own.
    function toOctave(n) {
        quiet();
        set.octave = Math.min(config.octaves[1], Math.max(config.octaves[0], n));
        keep({ octave: set.octave });
    }

    const midiOf = (key) => 12 * (set.octave + 1) + Number(key.dataset.note);
    const reference = () => Number(set.ref);
    const level = () => (set.louder ? config.louder : config.level);
    // A note's name in the chosen spelling, written or spoken, by its pitch class.
    const named = (pc, spoken = false) => {
        const [written, said] = config.spellings[set.spelling] ?? config.spellings.common;
        return (words[spoken ? said : written] ?? [])[((pc % 12) + 12) % 12] ?? '';
    };
    const spell = (midi) => `${named(midi)}${pitchName(midi).octave}`;
    const chordName = () => fill(words.chords_named?.[set.kind] ?? '', { note: named(Number(last.dataset.note)) });

    // The chord on the last note, close above it: each note's frequency, tuned pure over it or equal.
    function chordOf(root) {
        const quality = config.kinds[set.kind];
        return chordTones(quality).map((semis) => midiToHz(root + semis, reference(), set.tuning === 'pure' ? (pureOffset(root + semis, { root: root % 12, quality }) ?? 0) : 0));
    }

    function keep(change) {
        saved.save(change);
        render();
    }

    function render() {
        const button = sounding?.button ?? null;
        const note = button === chordButton ? last : button === ui.primary ? last : button;
        keys.forEach((k) => {
            k.textContent = named(Number(k.dataset.note));
            k.setAttribute('aria-label', fill(w('note_label'), { note: named(Number(k.dataset.note), true), octave: pitchName(midiOf(k)).octave }));
            k.setAttribute('aria-pressed', String(k === button));
        });
        name.textContent = note || heard ? spell(midiOf(note ?? last)) : '';
        if (button === chordButton) ui.setStatus({ tone: 'good', text: chordName(), hint: w('sounding') });
        else if (note) ui.setStatus({ tone: 'good', text: fill(w('hz'), { hz: num(midiToHz(midiOf(note), reference()), 2) }), hint: w('sounding') });
        else ui.setStatus({ hint: w('idle') });
        ui.setPrimary({ label: fill(w('sound_note'), { note: spell(midiOf(last)) }), active: button === ui.primary });
        chordButton.textContent = chordName();
        chordButton.setAttribute('aria-pressed', String(button === chordButton));
        chordButton.setAttribute('aria-label', fill(w('chord_label'), { chord: chordName() }));
        choices.ref.set(set.ref);
        a4.set(set.ref, { atLow: reference() <= config.referenceRange[0], atHigh: reference() >= config.referenceRange[1] });
        const summary = $('[data-ui=pipe-summary]');
        if (reference() === config.reference) summary.replaceChildren();
        else if (!summary.firstElementChild) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'filter-chip';
            b.addEventListener('click', () => ui.openSheet(true));
            summary.replaceChildren(b);
        }
        if (summary.firstElementChild) summary.firstElementChild.textContent = fill(w('reference'), { hz: set.ref });
        octave.set(`${named(0)}${set.octave}`, { atLow: set.octave <= config.octaves[0], atHigh: set.octave >= config.octaves[1] });
        $('[data-song-save]').textContent = fill(w('song_save'), { note: named(Number(last.dataset.note)) });
        songs();
    }

    // Fades what's sounding out `after` seconds from now; the pipe lets go of it when the sound does.
    function quiet(after = 0) {
        if (!sounding) return;
        const was = sounding;
        was.cancel?.();
        was.voices.forEach((v) => v.stop(after));
        was.cancel = atClock(unlock().currentTime + after, () => {
            if (sounding !== was) return;
            sounding = null;
            render();
        });
    }

    function sound(button) {
        unlock();
        const again = sounding?.button === button;
        quiet();
        if (again && set.latch) {
            sounding = null;
            render();
            return; // tapped again: stopped
        }
        const shape = { timbre: set.timbre, attack: config.attack, release: config.release };
        let voices;
        if (button === chordButton) {
            const notes = chordOf(midiOf(last));
            voices = chord(notes, { ...shape, level: Math.min(level(), config.chordSum / notes.length) });
        } else {
            if (button !== ui.primary) last = button;
            heard = true;
            voices = [voice(midiToHz(midiOf(last), reference()), { ...shape, level: level() })];
        }
        sounding = { button, voices };
        render();
    }

    function letGo(button, held) {
        if (set.latch || sounding?.button !== button) return;
        quiet(held >= config.hold ? 0 : config.tap - held);
    }

    [...keys, chordButton, ui.primary].forEach((b) => press(b, { down: () => sound(b), up: (held) => letGo(b, held) }));

    // The set list: each song a button that sounds its key (as a tap), and one to remove it.
    const kept = () => (Array.isArray(set.songs) ? set.songs : []).filter((s) => typeof s?.name === 'string' && Number.isInteger(s?.pc));
    function songs() {
        const list = kept();
        $('[data-songs-empty]').hidden = list.length > 0;
        $('[data-song-list]').replaceChildren(...list.map((song, i) => {
            const row = document.createElement('li');
            const play = document.createElement('button');
            play.type = 'button';
            play.className = 's-tool-pick s-pipe-song';
            // A song keeps its octave too (the tools review, 2026-11-14: a men's group got its note an octave high).
            const kept8 = Number.isInteger(song.octave) ? song.octave : null;
            play.textContent = fill(w('song_play'), { song: song.name, note: `${named(song.pc)}${kept8 ?? ''}` });
            play.addEventListener('click', () => {
                // Past the stepper's top, a top C is the thirteenth key an octave below.
                const top = kept8 !== null && kept8 > config.octaves[1] && song.pc === 0;
                const want = top ? kept8 - 1 : kept8;
                if (want !== null && want !== set.octave) toOctave(want);
                const key = keys[top ? 12 : song.pc];
                sound(key);
                letGo(key, 0);
            });
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn-ghost s-pipe-unsong';
            remove.textContent = '×';
            remove.setAttribute('aria-label', fill(w('song_remove'), { song: song.name }));
            remove.addEventListener('click', () => {
                saved.save({ songs: kept().filter((_, j) => j !== i) });
                songs();
                songName.focus();
            });
            row.append(play, remove);
            return row;
        }));
    }
    $('[data-song-form]').addEventListener('submit', (event) => {
        event.preventDefault();
        const title = songName.value.trim().slice(0, 60);
        songName.setAttribute('aria-invalid', String(!title));
        if (!title) {
            songName.focus();
            return;
        }
        // The note's octave goes with it (the thirteenth key, the top C, is the next octave's C).
        const note = Number(last.dataset.note);
        saved.save({ songs: [...kept(), { name: title, pc: note % 12, octave: set.octave + Math.floor(note / 12) }].slice(-config.songs) });
        songName.value = '';
        songs();
    });
    songName.addEventListener('input', () => songName.removeAttribute('aria-invalid'));

    // A computer's keys, A to K, as a piano's: held, as a press is.
    const down = new Map();
    const onKey = (event) => {
        const typed = event.key.toLowerCase();
        // Z and X: an octave down and up, as on a music app's keyboard (the tools review).
        if ((typed === 'z' || typed === 'x') && event.type === 'keydown' && !event.repeat && !event.metaKey && !event.ctrlKey && !event.altKey
            && !event.target.closest?.('input, textarea, select, [contenteditable], dialog')) {
            toOctave(set.octave + (typed === 'z' ? -1 : 1));
            return;
        }
        const key = keys[config.keys.indexOf(typed)];
        if (!key || event.metaKey || event.ctrlKey || event.altKey || event.target.closest?.('input, textarea, select, [contenteditable], dialog')) return;
        if (event.type === 'keydown' && !event.repeat && !down.has(key)) {
            down.set(key, performance.now());
            sound(key);
        } else if (event.type === 'keyup' && down.has(key)) {
            letGo(key, (performance.now() - down.get(key)) / 1000);
            down.delete(key);
        }
    };
    document.addEventListener('keydown', onKey);
    document.addEventListener('keyup', onKey);

    render();
    const off = onHidden(() => {
        sounding = null;
        down.clear();
        render();
    });
    return {
        stop() {
            quiet();
            off();
            octave.destroy();
            a4.destroy();
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('keyup', onKey);
        },
    };
}
