// The drone (/tools/drone), on the tools' one layout (tool-layout.js; docs/design-rules.md, "Free tools share one
// layout"). The key is the key control in the brand row (KEY − C +); the modes are Drone | Tanpura; the hero is the key
// big; the status pill says it's playing and what; the readout picks the sound (the key note, the fifth, major, minor;
// the tanpura's first string instead); the panel lists the notes with their Hz and how far each is from the piano's,
// and what to listen for; over the bar sit the octave and Pure or Equal; the bar's primary is Play / Stop. More's sheet holds the
// volume, more chords (the open fifth, the barbershop seventh), the timbre, fine tuning the key note in cents, the
// reference A, the tanpura's pace, and keeping the screen on. A change while it plays follows at
// once. Tanpura mode plucks four strings round the key note (Sa, called the key note here) on the audio clock. It keeps
// sounding in another tab (where the browser allows), names itself to the device's media controls (play and pause
// from the lock screen or a headset), keeps the screen on while it plays (a phone that locks silences a web page), and
// stops when the page is left. UI and state only: the tunings are just.js's, the frequencies dsp.js's, the drone and
// the plucks tones.js's (held, pluck), and the plucks' timing ticker.js's.
//
//   mountDrone(el, { words, keySlot })   // words: every key in WORDS; returns { stop() }

import { midiToHz } from '../../dsp.js';
import { chordTones, pureOffset } from '../../just.js';
import { pitchName } from '../../notes.js';
import { segmented, stepper, toolLayout } from '../tool-layout.js';
import { held, pluck } from '../tones.js';
import { warmAudio } from '../voices.js';
import { ticker } from '../ticker.js';
import { REFERENCE } from '../tuner-reading.js';
import { chips, choice, fill, mediaControls, num, oneRow, remember, switchRow, toggle, unlock } from './kit.js';
import { keepAwake } from '../wake-lock.js';

/** The words the page passes (lang site.tools.pages.drone.card). */
export const WORDS = [
    'mode', 'modes', 'key', 'key_name', 'keys', 'sharps', 'sound', 'sounds', 'ratios', 'more_chords', 'first', 'firsts', 'pace',
    'paces', 'play', 'stop', 'playing', 'idle', 'volume', 'octave', 'octave_name', 'lower', 'higher', 'tuned', 'tunings', 'timbre',
    'timbres', 'cents', 'cents_now', 'cents_reset', 'tuning', 'roles', 'note', 'under', 'over', 'why', 'notes_label', 'more', 'done',
    'media_title', 'media_tanpura', 'media_artist', 'awake',
    'settingsTitle', 'reset',
];

export const DRONE = {
    key: 0, // C
    octave: 3, // the key note in the octave below middle C
    lowest: 36, // C2: the key note never goes under it,
    highest: 60, // nor over C4
    sound: 'root',
    firstSounds: ['root', 'fifth', 'major', 'minor'], // on the first screen; the rest under More
    moreSounds: ['open', 'seventh'],
    // Each sound: the just.js quality its notes are tuned in, and its notes in semitones over the key note.
    sounds: {
        root: { quality: 'maj', tones: [0] },
        fifth: { quality: 'maj', tones: [0, 7] },
        open: { quality: 'maj', tones: [0, 7, 12] },
        major: { quality: 'maj' },
        minor: { quality: 'min' },
        seventh: { quality: 'dom7' }, // its seventh the barbershop 7:4
    },
    roles: { 0: 'key', 3: 'third', 4: 'third', 7: 'fifth', 10: 'seventh', 12: 'octave' },
    // The keys whose chords are written with sharps (A major's C sharp); the rest with flats (C's barbershop B flat).
    sharpKeys: { min: [1, 4, 6, 8, 9, 11], other: [2, 4, 6, 7, 9, 11] },
    tuning: 'pure',
    timbre: 'ah', // Warm; or 'oo' (Voice), 'triangle' (Soft), 'reed' (Reed): rich, steady tones, never a bare sine
    timbres: ['ah', 'oo', 'triangle', 'reed'],
    volume: 0.8,
    total: 0.4, // every voice together at full volume, well under clipping
    each: 0.16, // no single voice louder than this
    cents: 0, // the key note's fine tuning
    centsLimit: 50,
    reference: 440,
    references: REFERENCE.presets, // the usual A4s, the same list as the tuner, pitch pipe and note chart
    // The tanpura: its first string (a pure interval under the key note, or none), then the key note twice and the
    // key note an octave down; a pluck every `gap` seconds, a little longer after the low one.
    first: 'pa',
    firsts: { pa: { semis: -5, quality: 'maj' }, ma: { semis: -7, quality: 'sus4' }, ni: { semis: -1, quality: 'maj7' }, none: null },
    pace: 'steady',
    paces: { slow: 1.2, steady: 0.9, quick: 0.7 },
    lowGap: 1.4, // after the low key note, as a gap of this many plucks
    pluck: { level: 0.14, decay: 3.5 }, // tones.js's bright, buzzing pluck, ringing about 3.5 s
};

const HERO = `
<p class="text-[clamp(5rem,26vw,8rem)] leading-[0.85] font-black tracking-[-0.04em] text-cream [@media(max-height:640px)]:text-[4rem]" translate="no" aria-live="polite" data-key-name></p>`;

const READOUT = `
<div class="min-w-0" data-for-mode="drone" data-sounds></div>
<div class="min-w-0" data-for-mode="tanpura" data-firsts hidden></div>`;

// Over the bar: the octave, and Pure or Equal.
const CONTROLS = `
<div class="flex items-center gap-2.5">
  <span class="shrink-0" data-octave></span>
  <div class="min-w-0 flex-1" data-for-mode="drone" data-tunings></div>
</div>`;

// The panel takes the height that's left (the layout's), its notes scrolling inside it when a short screen has little:
// it never pushes the bar over itself.
const PANEL = `
<div class="absolute inset-0 grid content-start gap-2.5 overflow-y-auto p-4 text-start" tabindex="0" data-ui="drone-notes">
  <ul class="grid gap-1 text-[0.9375rem] leading-snug tabular-nums" data-drone-notes></ul>
  <p class="help" data-drone-why></p>
</div>`;

const SHEET = `
<label class="block"><span class="label" data-volume-label></span><input type="range" class="range mt-1" min="0" max="100" data-volume></label>
<div data-for-mode="drone" data-chips="more-sounds"></div>
<div data-for-mode="drone" data-chips="timbre"></div>
<div data-for-mode="tanpura" data-chips="pace" hidden></div>
<div data-chips="cents"></div>
<div data-chips="ref"></div>
<div data-switch="awake"></div>`;

export function mountDrone(el, { words = {}, keySlot = null, preset = null, onLesson = null } = {}) {
    warmAudio(); // the sound's AudioContext made while the page is idle, so the first tap only resumes it (voices.js)
    const config = { ...DRONE };
    const w = (key) => words[key] ?? '';
    const ui = toolLayout(el, { keySlot, more: w('more'), done: w('done'), title: w('settingsTitle'), reset: w('reset') });
    ui.element.dataset.tool = 'drone';
    ui.hero.innerHTML = HERO;
    ui.readout.innerHTML = READOUT;
    ui.panel.innerHTML = PANEL;
    ui.controls.innerHTML = CONTROLS;
    ui.sheet.innerHTML = SHEET;
    const $ = (sel) => el.querySelector(sel);
    const saved = remember('drone', {
        mode: 'drone', key: String(config.key), octave: config.octave, sound: config.sound, tuning: config.tuning, timbre: config.timbre,
        volume: config.volume, cents: config.cents, ref: String(config.reference), first: config.first, pace: config.pace, awake: true,
    });
    // A variant page's preset for this visit (/tools/tanpura: `{ mode: 'tanpura' }`), never saved over the person's own.
    if (preset?.mode === 'drone' || preset?.mode === 'tanpura') saved.visit({ mode: preset.mode });
    const set = saved.value;
    let drone = null; // the held chord, while it sounds
    let plucks = null; // the tanpura's ticker, while it plays
    const ringing = new Set(); // its plucks still sounding, so Stop stops them too

    // The first screen: the modes, the sound (or the tanpura's first string), the octave, the tuning and the volume.
    const options = (all, keys) => keys.map((key) => ({ key, label: all?.[key] ?? key }));
    const modes = segmented(ui.modes, options(words.modes, ['drone', 'tanpura']), { current: set.mode, label: w('mode'), pick: (v) => change({ mode: v }) });
    const sounds = segmented($('[data-sounds]'), options(words.sounds, config.firstSounds), { current: set.sound, label: w('sound'), pick: (v) => change({ sound: v }) });
    const firsts = segmented($('[data-firsts]'), options(words.firsts, Object.keys(config.firsts)), { current: set.first, label: w('first'), pick: (v) => change({ first: v }) });
    const tunings = segmented($('[data-tunings]'), options(words.tunings, ['pure', 'equal']), { current: set.tuning, label: w('tuned'), pick: (v) => change({ tuning: v }) });
    const key = stepper(ui.key, { label: w('key'), name: w('key_name'), lower: w('lower'), higher: w('higher'), step: (by) => change({ key: String((Number(set.key) + by + 12) % 12) }) });
    const octave = stepper($('[data-octave]'), {
        label: w('octave'), name: w('octave_name'), lower: w('lower'), higher: w('higher'),
        step: (by) => {
            if (root() + 12 * by < config.lowest || root() + 12 * by > config.highest) return;
            set.octave += by;
            change({ octave: set.octave });
        },
    });
    const volume = $('[data-volume]');
    $('[data-volume-label]').textContent = w('volume');
    ui.setLabel(w('notes_label'));

    // More's sheet: more chords, the timbre, the key note's fine tuning, the reference, the tanpura's pace, the screen.
    chips($('[data-chips=more-sounds]'), 'sound', w('more_chords'), Object.fromEntries(config.moreSounds.map((k) => [k, words.sounds?.[k] ?? k])));
    chips($('[data-chips=timbre]'), 'timbre', w('timbre'), Object.fromEntries(config.timbres.map((t) => [t, words.timbres?.[t] ?? t])));
    chips($('[data-chips=cents]'), 'centsStep', w('cents'), [['-5', '−5'], ['-1', '−1'], ['1', '+1'], ['5', '+5'], ['0', w('cents_reset')]]);
    chips($('[data-chips=ref]'), 'ref', w('tuning'), Object.fromEntries(config.references.map((hz) => [hz, String(hz)])));
    oneRow($('[data-chips=ref] [role=group]'));
    chips($('[data-chips=pace]'), 'pace', w('pace'), words.paces ?? {});
    const centsNow = document.createElement('output');
    centsNow.className = 'filter-chip pointer-events-none tabular-nums';
    centsNow.setAttribute('aria-live', 'polite');
    $('[data-chips=cents] [role=group]').insertBefore(centsNow, $('[data-cents-step="1"]'));
    const choices = {
        moreSounds: choice($('[data-chips=more-sounds]'), 'sound', (v) => change({ sound: v })),
        timbre: choice(el, 'timbre', (v) => change({ timbre: v })),
        ref: choice(el, 'ref', (v) => change({ ref: v })),
        pace: choice(el, 'pace', (v) => change({ pace: v })),
    };
    for (const b of el.querySelectorAll('[data-cents-step]')) {
        b.removeAttribute('aria-pressed');
        b.addEventListener('click', () => {
            const by = Number(b.dataset.centsStep);
            change({ cents: by === 0 ? 0 : Math.max(-config.centsLimit, Math.min(config.centsLimit, set.cents + by)) });
        });
    }
    // The screen kept on while it sounds, if the switch says so (the shared wake-lock.js asks again when the page comes back).
    let lock = null;
    const wake = {
        set(on) {
            if (on && !lock) lock = keepAwake();
            else if (!on && lock) {
                lock.release();
                lock = null;
            }
        },
    };
    const awake = toggle(switchRow($('[data-switch=awake]'), 'awake', w('awake')), (on) => {
        saved.save({ awake: on });
        wake.set(on && playing());
    });
    awake.set(set.awake);
    // More's Reset: the drone stops and every setting goes back to its default.
    ui.onReset(() => {
        if (playing()) stop();
        saved.reset();
        awake.set(set.awake);
        show();
    });
    const controls = mediaControls({
        artist: w('media_artist'),
        play: () => {
            if (!playing()) start();
            show();
        },
        pause: () => {
            stop();
            show();
        },
    });

    const root = () => 12 * (set.octave + 1) + Number(set.key);
    const sound = () => config.sounds[set.sound] ?? config.sounds.root;
    const tonesOf = (s) => s.tones ?? chordTones(s.quality);
    // A note named as this key writes it (A major's C sharp, C's barbershop B flat).
    const sharpList = () => ((config.sharpKeys[sound().quality] ?? config.sharpKeys.other).includes(Number(set.key)) ? words.sharps : words.keys) ?? [];
    const keyNameOf = (pc) => (words.keys ?? [])[pc] ?? '';
    const name = (midi) => `${sharpList()[((midi % 12) + 12) % 12] ?? ''}${pitchName(midi).octave}`;
    const playing = () => Boolean(drone || plucks);
    let startedAt = 0; // when it started sounding (ms), for the lesson line

    // The drone's notes: each one's pitch, its pure offset from the piano (or 0, tuned equal) and its role.
    function notes() {
        const s = sound();
        return tonesOf(s).map((semis) => {
            const pure = set.tuning === 'pure' ? (pureOffset(root() + semis, { root: root() % 12, quality: s.quality }) ?? 0) : 0;
            return { midi: root() + semis, pure, role: config.roles[semis] };
        });
    }

    // The tanpura's strings, in the order they're plucked, each `{ midi, pure, gap }`.
    function strings() {
        const gap = config.paces[set.pace] ?? config.paces.steady;
        const first = config.firsts[set.first];
        const low = { midi: root() - 12, pure: 0, gap: gap * config.lowGap, role: 'low' };
        const sa = { midi: root(), pure: 0, gap, role: 'key' };
        const opening = first ? [{ midi: root() + first.semis, pure: pureOffset(root() + first.semis, { root: root() % 12, quality: first.quality }) ?? 0, gap, role: set.first }] : [];
        return [...opening, sa, sa, low];
    }

    const hzOf = (n) => midiToHz(n.midi, Number(set.ref), set.cents + n.pure);
    const each = (count) => set.volume * Math.min(config.each, config.total / count);

    function show() {
        const tanpura = set.mode === 'tanpura';
        el.querySelectorAll('[data-for-mode]').forEach((part) => { part.hidden = part.dataset.forMode !== set.mode; });
        modes.set(set.mode);
        sounds.set(set.sound);
        firsts.set(set.first);
        tunings.set(set.tuning);
        choices.moreSounds.set(set.sound);
        choices.timbre.set(set.timbre);
        choices.ref.set(set.ref);
        choices.pace.set(set.pace);
        key.set(keyNameOf(Number(set.key)));
        octave.set(name(root()), { atLow: root() - 12 < config.lowest, atHigh: root() + 12 > config.highest });
        $('[data-key-name]').textContent = keyNameOf(Number(set.key));
        const listed = tanpura ? strings().filter((s, i, all) => all.findIndex((t) => t.midi === s.midi) === i) : notes();
        $('[data-drone-notes]').replaceChildren(...listed.map((n) => {
            const row = document.createElement('li');
            const off = Math.round(n.pure * 10) / 10;
            const away = off < 0 ? fill(w('under'), { n: num(-off, 1) }) : off > 0 ? fill(w('over'), { n: num(off, 1) }) : '';
            row.textContent = fill(w('note'), { role: words.roles?.[n.role] ?? '', note: name(n.midi), hz: num(hzOf(n), 2) }) + (away ? ` · ${away}` : '');
            return row;
        }));
        $('[data-drone-why]').textContent = tanpura ? words.why?.tanpura : set.tuning === 'equal' && set.sound !== 'root' ? words.why?.equal : words.why?.[set.sound];
        centsNow.textContent = fill(w('cents_now'), { n: `${set.cents > 0 ? '+' : ''}${num(set.cents)}` });
        volume.value = String(Math.round(set.volume * 100));
        volume.style.setProperty('--p', `${set.volume * 100}%`);
        const what = tanpura ? words.firsts?.[set.first] ?? '' : words.sounds?.[set.sound] ?? '';
        ui.setLabel(w('notes_label'), tanpura ? '' : words.ratios?.[set.sound] ?? '');
        if (playing()) ui.setStatus({ tone: 'good', text: w('playing'), hint: what });
        else ui.setStatus({ hint: w('idle') });
        ui.setPrimary({ label: playing() ? w('stop') : w('play'), active: playing() });
        controls.show(fill(tanpura ? w('media_tanpura') : w('media_title'), { key: keyNameOf(Number(set.key)) }), playing());
    }

    function start() {
        startedAt = performance.now();
        unlock();
        wake.set(set.awake);
        if (set.mode === 'tanpura') {
            let n = 0;
            plucks = ticker((time, step, late) => {
                const all = strings();
                const s = all[n++ % all.length];
                if (!late) {
                    const string = pluck(hzOf(s), { at: time, level: set.volume * config.pluck.level, decay: config.pluck.decay, keepWhenHidden: true });
                    ringing.add(string);
                    string.ended.then(() => ringing.delete(string));
                }
                return s.gap;
            });
        } else {
            const now = notes();
            drone = held(now.map((n) => midiToHz(n.midi, Number(set.ref), set.cents)), { cents: now.map((n) => n.pure), level: each(now.length), timbre: set.timbre, keepWhenHidden: true });
        }
    }

    function stop() {
        wake.set(false);
        drone?.stop();
        plucks?.stop();
        ringing.forEach((string) => string.stop());
        drone = null;
        plucks = null;
    }

    // A change while it sounds follows at once: the held chord glides to its new notes; a new mode or timbre starts
    // afresh (a crossfade); the tanpura takes it from its next pluck.
    function change(next) {
        const restart = playing() && ('mode' in next || 'timbre' in next);
        if ('key' in next) {
            // The key note stays in its octave (C3 to B3), inside C2 to C4.
            set.key = next.key;
            while (root() > config.highest) set.octave -= 1;
            while (root() < config.lowest) set.octave += 1;
        }
        saved.save({ ...next, octave: set.octave });
        if (restart) stop();
        if (restart) start();
        else if (drone) {
            const now = notes();
            drone.setNotes(now.map((n) => midiToHz(n.midi, Number(set.ref), set.cents)), now.map((n) => n.pure));
            drone.level(each(now.length));
        }
        show();
    }

    ui.onPrimary(() => {
        if (playing()) {
            stop();
            // Sung with for 20 s or more, then stopped: the page's free lesson line (page.js), once.
            if (performance.now() - startedAt >= 20000) onLesson?.();
        } else start();
        show();
    });
    volume.addEventListener('input', () => {
        set.volume = Number(volume.value) / 100;
        saved.save({ volume: set.volume });
        drone?.level(each(notes().length));
        show();
    });

    show();
    // It plays on in another tab; leaving the page stops it.
    const leave = () => stop();
    addEventListener('pagehide', leave);
    return {
        stop() {
            stop();
            controls.clear();
            key.destroy();
            removeEventListener('pagehide', leave);
        },
    };
}
