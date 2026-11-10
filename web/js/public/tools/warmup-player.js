// The warm-up player for marketing's /warmups/<exercise> pages (docs/seo-growth-plan.md, item 8), on the
// tools' one layout (js/public/tool-layout.js): one warm-up from the courses, played climbing through keys
// the way the app's workout does (js/warmup.js warmupRounds: a half step higher each round to the top,
// then back down), inside the voice you pick (Low, Medium, High, or your own range when the range test
// saved one), at the tempo you set (the brand row's key control), as many times through as you like. The
// hero says the round and the key; the pill what to do now (Listen, Sing); the panel is a small keyboard
// lighting each note as it sounds, with the exercise's one line on how to do it. Sing along has the guide
// sing with you; Hear it, then sing (the workout's way) plays each round, then leaves it to you with the
// notes still lighting. The exercise's "stop if" line sits under its instruction, seen before you sing.
// Behind More: the way, the times through and how far it climbs.
//
// A thin wrapper: the warm-ups are the courses lead's list (course/warmups.json), each one's notes read
// from its course step (loadWarmup; never a copy), cleaned and climbed by js/warmup.js (warmupFromExercise, warmupRounds); the voice from
// voices.js voice() on the audio clock; the keyboard from piano.js keyboard(); the saved range from saved-range.js;
// the settings in tool-prefs.js. Nothing is recorded or sent. The words come from the page (:n, :total
// and :key are filled in here), set as text, never markup.
//
//   import { loadWarmup, mountWarmupPlayer, WORDS } from '/js/public/tools/warmup-player.js';
//   const warmup = await loadWarmup('lip-trills');           // course/warmups.json's entry and its course step
//   const player = mountWarmupPlayer(el, { words, keySlot, warmup });
//
// The list is the courses lead's (course/warmups.json in the content repo: each entry's name, instruction and
// "stop if" line, its sound, bounds, tempo and climb, pointing at a course step for its notes; the course
// check keeps the two in step). The handle: play(), stop(), timeline() and element. timelineOf() is pure.

import { midiToHz } from '../../dsp.js';
import { pitchName } from '../../notes.js';
import { warmupFromExercise, warmupRounds } from '../../warmup.js';
import { keyboard } from '../piano.js';
import { savedRange } from '../saved-range.js';
import { segmented, sheetRow, stepper, toolLayout } from '../tool-layout.js';
import { toolPrefs } from '../tool-prefs.js';
import { ticker } from '../ticker.js';
import { audio, voice } from '../voices.js';

/** The words a page passes, each translated. */
export const WORDS = [
    'start', 'stop', 'settings', 'settingsTitle', 'reset', 'done', 'idleHint', 'voices', 'low', 'medium', 'high', 'yours',
    'tempo', 'slower', 'faster', 'round', 'inKey', 'listen', 'sing', 'singHint', 'finished', 'finishedHint',
    'span', 'way', 'along', 'echo', 'times', 'timesValue', 'fewer', 'oftener', 'climb', 'climbValue', 'lower', 'higher',
];

/** The voices: where the warm-up is centred (`home`, the comfortable note) and the notes it may reach. */
export const VOICES = {
    low: { low: 41, high: 62, home: 48 }, // F2 to D4, around C3
    medium: { low: 48, high: 69, home: 55 }, // C3 to A4, around G3
    high: { low: 57, high: 77, home: 62 }, // A3 to F5, around D4
};
const GAP_BEATS = 1; // a beat's breath between rounds
const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));

/** Your own range (the range test's) as a voice: its notes, and a comfortable note a third of the way up. */
export function ownVoice(range) {
    if (!range) return null;
    return { low: range.low, high: range.high, home: Math.round(range.low + (range.high - range.low) / 3) };
}

/** A word from the list ({ en, ... }) in the page's language, else English. */
const said = (text, lang = document.documentElement.lang || 'en') => (text && typeof text === 'object' ? text[lang] ?? text[lang.split('-')[0]] ?? text.en ?? '' : String(text ?? ''));

/**
 * A warm-up from the courses lead's list (/course/warmups.json) and the course step its notes come from:
 * `{ entry, exercise }`. A free-course step is in /course/course.json, a Plus course's in its own file
 * (/course/plus/courses.json names it). Rejects when the warm-up or its step isn't there.
 */
export async function loadWarmup(id, { get = (url) => fetch(url).then((r) => (r.ok ? r.json() : Promise.reject(new Error(`${url}: ${r.status}`)))) } = {}) {
    const list = await get('/course/warmups.json');
    const entry = (list?.warmups ?? []).find((w) => w.id === id);
    if (!entry) throw new Error(`No warm-up ${id}`);
    let file = '/course/course.json';
    if (entry.from.course !== 'free') {
        const catalogue = await get('/course/plus/courses.json');
        const course = (catalogue?.courses ?? []).find((c) => c.id === entry.from.course);
        if (!course?.file) throw new Error(`No course ${entry.from.course}`);
        file = `/course/plus/${course.file}`;
    }
    const course = await get(file);
    const lesson = (course?.units ?? []).flatMap((u) => u.lessons ?? []).find((l) => l.id === entry.from.lesson);
    const exercise = lesson?.steps?.[entry.from.step]?.exercise;
    if (!exercise) throw new Error(`No step ${entry.from.lesson}/${entry.from.step}`);
    return { entry, exercise };
}

/**
 * The entry's warm-up for js/warmup.js: the step's notes (warmupFromExercise), sung on the entry's sound,
 * at its tempo, climbing as far as `climb` (the entry's default, never over its max). Null when the step
 * can't be one.
 */
export function warmupOf({ entry, exercise }, { climb = null, tempo = null } = {}) {
    const base = warmupFromExercise(exercise);
    if (!base) return null;
    const most = entry.climb?.max ?? base.climb;
    return { ...base, sound: entry.sound ?? base.sound, tempo: tempo ?? entry.tempo?.default ?? base.tempo, climb: Math.max(0, Math.min(most, climb ?? entry.climb?.default ?? base.climb)) };
}

/** Where the notes may go: inside the voice and inside the entry's bounds around its comfortable note. */
export function reachOf(v, bounds = null) {
    if (!bounds) return { low: v.low, high: v.high };
    return { low: Math.max(v.low, v.home + bounds.low), high: Math.min(v.high, v.home + bounds.high) };
}

/**
 * The whole warm-up as timed notes, `[{ at, length, midi, round, guide }]` (beats from the start): each
 * round's notes at its key, `guide` when the voice sings it. In 'echo' each round is heard, then sung
 * (the same notes again, guide false); in 'along' the guide sings once with you. `times` runs it through
 * again. Also `beats`, the total length, and `rounds`, the half steps each round is moved.
 */
export function timelineOf(warmup, { voice: v, way = 'along', times = 1, bounds = null }) {
    const rounds = warmupRounds(warmup, { home: v.home, range: reachOf(v, bounds) });
    const notes = [...warmup.steps.map((step, i) => ({ step: step + warmup.start, beat: i, length: i === warmup.steps.length - 1 ? 3 : 1 }))];
    const roundBeats = notes.at(-1).beat + notes.at(-1).length;
    const out = [];
    let at = 0;
    let index = 0;
    for (let t = 0; t < times; t++) {
        for (const shift of rounds) {
            for (const pass of way === 'echo' ? [true, false] : [true]) {
                for (const n of notes) out.push({ at: at + n.beat, length: n.length, midi: v.home + n.step + shift, round: index, guide: pass });
                at += roundBeats + GAP_BEATS;
            }
            index++;
        }
    }
    return { notes: out, beats: at, rounds: index, shifts: rounds };
}

const HERO = `
<div class="grid justify-items-center gap-1 pt-2 [@media(max-height:640px)]:pt-0" translate="no">
  <p class="text-sm font-bold tracking-[0.12em] text-muted uppercase" data-ui="warmup-round" data-round></p>
  <p class="text-[clamp(3rem,16vw,4.75rem)] font-black leading-none tracking-tight text-cream [@media(max-height:640px)]:text-[2.75rem]" data-ui="warmup-key" data-key></p>
</div>`;

const PANEL = `
<div class="absolute inset-0 flex flex-col gap-3 p-3 md:static">
  <p class="text-sm text-cream" data-how-to></p>
  <p class="text-sm font-semibold text-cream" data-ui="warmup-stop-line" data-stop-line></p>
  <div class="my-auto w-full shrink-0" data-ui="warmup-keyboard" data-keyboard></div>
</div>`;

export function mountWarmupPlayer(root, { words = {}, keySlot = null, warmup: given } = {}) {
    const w = (key) => words[key] ?? '';
    const { entry } = given;
    const slug = entry.id;
    const tempoRange = [entry.tempo?.min ?? 50, entry.tempo?.max ?? 120];
    const climbMax = entry.climb?.max ?? 4;
    const instruction = said(entry.instruction);
    const stopIf = said(entry.stop);
    const ui = toolLayout(root, { keySlot, more: w('settings'), done: w('done'), title: w('settingsTitle'), reset: w('reset') });
    ui.hero.innerHTML = HERO;
    ui.panel.innerHTML = PANEL;
    // The layout's desktop panel is a fixed 10rem, for a trace; the instruction, stop line and keys need their own height.
    ui.panel.classList.add('md:!h-auto');
    const card = ui.element;
    card.dataset.tool = 'warmup-player';
    card.dataset.warmup = slug;
    const $ = (selector) => card.querySelector(selector);
    const text = (selector, value) => ($(selector).textContent = value ?? '');
    text('[data-how-to]', instruction);
    $('[data-how-to]').hidden = !instruction;
    // When to stop, seen before anyone sings (the courses lead: most never open More); in the page's ink, AA in every theme.
    text('[data-stop-line]', stopIf);
    $('[data-stop-line]').hidden = !stopIf;

    const own = ownVoice(savedRange());
    const tempoFits = (v) => Number.isInteger(v) && v >= tempoRange[0] && v <= tempoRange[1];
    const first = warmupOf(given);
    const defaults = { voice: own ? 'yours' : 'medium', tempo: first.tempo, climb: first.climb, way: 'along', times: 1 };
    // The settings, remembered on this device per warm-up (tool-prefs.js).
    const prefs = toolPrefs(`warmups-${slug}`, {
        defaults,
        valid: { voice: (v) => ['low', 'medium', 'high', 'yours'].includes(v), tempo: tempoFits, climb: (v) => Number.isInteger(v) && v >= 0 && v <= climbMax, way: (v) => ['along', 'echo'].includes(v), times: (v) => Number.isInteger(v) && v >= 1 && v <= 5 },
    });
    const settings = prefs.value;
    if (settings.voice === 'yours' && !own) settings.voice = 'medium';
    const voiceNow = () => (settings.voice === 'yours' ? own : VOICES[settings.voice]);
    const warmup = () => warmupOf(given, { climb: settings.climb, tempo: settings.tempo });

    let sounding = [];
    let started = null; // { t0, beat, line }
    let raf = 0;
    let keys = null;

    const voices = segmented(ui.modes, [
        { key: 'low', label: w('low') }, { key: 'medium', label: w('medium') }, { key: 'high', label: w('high') },
        ...(own ? [{ key: 'yours', label: w('yours') }] : []),
    ], { current: settings.voice, label: w('voices'), pick: (key) => {
        settings.voice = key;
        voices.set(key);
        prefs.save();
        restart();
    } });
    const tempo = stepper(ui.key, { hook: 'warmup-tempo', label: w('tempo'), name: w('tempo'), lower: w('slower'), higher: w('faster'), step: (by) => {
        settings.tempo = Math.max(tempoRange[0], Math.min(tempoRange[1], settings.tempo + by * 4));
        prefs.save();
        restart();
    } });

    // More: the way it's sung, the times through, and when to stop.
    const wayRow = sheetRow(ui.sheet, { label: w('way') });
    const ways = segmented(wayRow.body, [{ key: 'along', label: w('along') }, { key: 'echo', label: w('echo') }], { current: settings.way, label: w('way'), pick: (key) => {
        settings.way = key;
        ways.set(key);
        prefs.save();
        restart();
    } });
    const timesRow = sheetRow(ui.sheet, { label: w('times') });
    const timesStep = stepper(timesRow.side, { hook: 'warmup-times', label: '', name: w('times'), lower: w('fewer'), higher: w('oftener'), step: (by) => {
        settings.times = Math.max(1, Math.min(5, settings.times + by));
        prefs.save();
        render();
    } });
    const climbRow = sheetRow(ui.sheet, { label: w('climb') });
    const climbStep = stepper(climbRow.side, { hook: 'warmup-climb', label: '', name: w('climb'), lower: w('lower'), higher: w('higher'), step: (by) => {
        settings.climb = Math.max(0, Math.min(climbMax, settings.climb + by));
        prefs.save();
        restart();
    } });
    if (stopIf) sheetRow(ui.sheet).body.innerHTML = '<p class="text-sm text-warn" data-ui="warmup-stop-if" data-stop-if></p>';
    if (stopIf) text('[data-stop-if]', stopIf);

    /** The keyboard, spanning the voice's notes. */
    function drawKeys() {
        const v = voiceNow();
        $('[data-keyboard]').replaceChildren();
        keys = keyboard($('[data-keyboard]'), { low: v.low, high: v.high });
        // Shorter on a short screen, so the instruction, the stop line and the keys all fit.
        keys.element.classList.add('[@media(max-height:640px)]:h-16');
    }

    const noteName = (midi) => {
        const { name, octave } = pitchName(midi);
        return `${name}${octave}`;
    };
    const keyName = (midi) => pitchName(midi, { flats: [1, 3, 8, 10].includes(((midi % 12) + 12) % 12) }).name;

    /** The controls, the label and the primary: on a change of settings or of playing, never per frame. */
    function render() {
        card.dataset.playing = String(!!started);
        ui.setLabel(w(settings.voice), fill(w('span'), { low: noteName(voiceNow().low), high: noteName(voiceNow().high) }));
        tempo.set(settings.tempo, { atLow: settings.tempo <= tempoRange[0], atHigh: settings.tempo >= tempoRange[1] });
        climbStep.set(fill(w('climbValue'), { n: settings.climb }), { atLow: settings.climb <= 0, atHigh: settings.climb >= climbMax });
        timesStep.set(fill(w('timesValue'), { n: settings.times }), { atLow: settings.times <= 1, atHigh: settings.times >= 5 });
        ui.setPrimary({ label: started && !started.finished ? w('stop') : w('start'), active: !!started && !started.finished });
        show(started?.now ?? null, started?.line ?? timeline());
    }

    /** The round, the key and the pill for the note sounding (`now`), from the line being played. */
    function show(now, line) {
        const round = now ? now.round : 0;
        const first = line.notes.find((n) => n.round === round);
        text('[data-round]', fill(w('round'), { n: round + 1, total: line.rounds }));
        text('[data-key]', first ? fill(w('inKey'), { key: keyName(first.midi) }) : '');
        if (!started) ui.setStatus({ hint: w('idleHint') });
        else if (started.finished) ui.setStatus({ tone: 'good', text: w('finished'), hint: w('finishedHint') });
        else if (now && !now.guide) ui.setStatus({ tone: 'good', text: w('sing'), hint: w('singHint') });
        else ui.setStatus({ tone: 'muted', text: settings.way === 'echo' ? w('listen') : w('sing'), hint: settings.way === 'echo' ? '' : w('singHint') });
    }

    /** The warm-up as timed notes for the settings now. */
    function timeline() {
        return timelineOf(warmup(), { voice: voiceNow(), way: settings.way, times: settings.times, bounds: entry.bounds ?? null });
    }

    function stop() {
        cancelAnimationFrame(raf);
        started?.pulse.stop();
        for (const v of sounding) v.stop();
        sounding = [];
        started = null;
        keys?.set({});
        render();
    }

    function restart() {
        const was = !!started && !started.finished;
        stop();
        drawKeys();
        if (was) play();
        else render();
    }

    /**
     * The guide's notes on the audio clock, a few at a time just ahead of when they sound (ticker.js, the
     * metronome's lookahead: never a timer, and only the next half second's voices exist at once); the
     * keyboard and the pill follow the clock, redrawn only when the note changes.
     */
    function play() {
        stop();
        const c = audio();
        const line = timeline();
        const beat = 60 / settings.tempo;
        const guide = line.notes.filter((n) => n.guide);
        const t0 = c.currentTime + 0.1;
        const pulse = ticker((time, i) => {
            const n = guide[i];
            const v = voice(midiToHz(n.midi), { at: time, timbre: 'oo', level: 0.14, attack: 0.04, release: 0.1 });
            v.stop(time + n.length * beat - 0.05 - c.currentTime);
            sounding.push(v);
            v.ended.then(() => (sounding = sounding.filter((x) => x !== v)));
            return i + 1 < guide.length ? (guide[i + 1].at - n.at) * beat : null;
        }, { at: t0, ahead: 0.5 });
        started = { t0, beat, line, pulse, now: null, cursor: 0, shown: -2, finished: false };
        render();
        const follow = () => {
            if (!started) return;
            const at = (c.currentTime - started.t0) / started.beat;
            if (at >= started.line.beats) {
                started.finished = true;
                keys?.set({});
                render();
                return;
            }
            // The notes come in time order: move on past the ones that are over.
            const notes = started.line.notes;
            while (started.cursor < notes.length && notes[started.cursor].at + notes[started.cursor].length <= at) started.cursor += 1;
            const playing = started.cursor < notes.length && notes[started.cursor].at <= at ? started.cursor : -1;
            if (playing !== started.shown) {
                started.shown = playing;
                const now = playing >= 0 ? notes[playing] : null;
                if (now) started.now = now;
                keys?.set(now ? { mark: [now.midi], names: { [now.midi]: noteName(now.midi) } } : {});
                show(started.now, started.line);
            }
            raf = requestAnimationFrame(follow);
        };
        raf = requestAnimationFrame(follow);
    }

    // A hidden page silences every voice (voices.js); the warm-up stops with it rather than run on unheard.
    const onHidden = () => document.hidden && stop();
    document.addEventListener('visibilitychange', onHidden);

    ui.onPrimary(() => {
        if (started && !started.finished) stop();
        else play();
    });
    ui.onReset(() => {
        prefs.reset();
        settings.voice = own ? 'yours' : 'medium';
        voices.set(settings.voice);
        ways.set(settings.way);
        restart();
    });
    drawKeys();
    render();
    return {
        play,
        stop,
        /** Stops, and takes the brand row's tempo control with it (the page is done with the player). */
        destroy() {
            stop();
            document.removeEventListener('visibilitychange', onHidden);
            tempo.destroy();
        },
        timeline,
        element: card,
    };
}
