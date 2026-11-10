// The ear trainers on the free tools' one layout (js/public/tool-layout.js; docs/design-rules.md, "Free tools
// share one layout"): js/public/tools/interval-trainer.js and chord-trainer.js, with Hillel's two trainer
// designs folded in (shots-review/designs/hillel/interval-trainer and chord-trainer) and the competitor
// review's must-haves kept (docs/tools/competitors-pipe-forks-drone-latency-ear.md sections 5 and 6). The
// rounds are the app's own listen-and-choose rounds (js/ear-rounds.js, the lessons' shape and guide voice),
// rendered ahead and played through the free tools' shared player (js/public/tones.js) on the audio clock.
//
// The slots: the level as the key control in the brand row (a stepper, LEVEL 2, its − and + walking the
// ladder); how it's played as the modes (Up, Down, Together); the hero, the answer big once it's known (the
// tuner's note), with the status pill under it (the question, then "● Right · C4 · G4" or "You said a
// Major 3rd"); the readout, Hillel's level bar (the last 10 answers at this level, 8 right opens the next,
// a miss hollow, not only red) with how close it is, then "Yours" beside "Right", a song to remember it
// by, and the next level once it's open; the panel, labelled with the level and its stat (accuracy, and
// the streak in game mode only: the skins plan keeps serious mode calm), the answers as tiles (two columns,
// the odd one out across both), over a small keyboard lighting a chord's notes. The pinned bar: Play,
// then Play it again, then Next; ⋯ opens the sheet (your own set, toggles, the range, results by answer,
// the keys). Keys: 1 to 9 (then 0, - and =) answer, space plays, A hears it again. The score is for this
// visit; settings and level are kept on this device. Nothing is sent. Hooks for the arcade skin: data-ui
// on the answer-hero, level-bar, level-segment, answer, keyboard and key (the layout's own hooks too).
//
//   mountQuiz(el, { words, keySlot, choices, levels, modes, round, describe, sublabel, reminder, toggles, keyboard, store })
//   round({ key, mode, base, low, high, random, ...toggles }) => an ear-rounds.js round
//
// The handle: next(), replay(), answer(key), yours(), current(), score(), stats(), level(), setLevel(),
// stop(). Tests pass `random` (0 to 1) and `play`.

import { pickKey, renderClip } from '../../ear-rounds.js';
import { midiName } from '../../dsp.js';
import { PIANO } from '../piano.js';
import { segmented, sheetRow, stepper, toolLayout } from '../tool-layout.js';
import { toolPrefs } from '../tool-prefs.js';
import { sound } from '../tones.js';
import { audio } from '../voices.js';
import { arcade, partInk, shareCard, shareUrl } from './share-card.js';

/** The words every trainer passes (:right, :answer, :n, :name, :need, :level, :song, :accuracy, :streak and :last are filled in here). */
export const QUIZ_WORDS = ['prompt', 'readySub', 'play', 'again', 'next', 'answers', 'right', 'youSaid', 'yours', 'rightOne',
    'level', 'levelShort', 'levelDown', 'levelUp', 'toNext', 'topLevel', 'goLevel', 'ownSet', 'statRight', 'statStreak',
    'settings', 'settingsTitle', 'reset', 'done', 'practise', 'fixedRoot', 'range', 'from', 'to', 'needOne', 'results', 'heard', 'missed', 'accuracy', 'keys', 'song', 'playNotes',
    'lastTen', 'share', 'shareText', 'cardTitle', 'cardScore', 'cardLine', 'cardChallenge', 'cardTagline', 'cardSite', 'cardSaved'];

const LOWEST = 36; // C2
const HIGHEST = 84; // C6
const TEMPO = 60; // a beat a second: the rounds' notes are in seconds
const UNLOCK = { answers: 10, right: 8 }; // the next level opens at 8 right of the last 10 at this one
const KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '-', '='];
const NAMES = ['C', 'C♯', 'D', 'D♯', 'E', 'F', 'F♯', 'G', 'G♯', 'A', 'A♯', 'B'];
const WHITE = [0, 2, 4, 5, 7, 9, 11];
const BLACK = [[1, 0], [3, 1], [6, 3], [8, 4], [10, 5]]; // [pitch class, the white key it follows]
/** A page's word with its :placeholders filled (as the site's language files write them). */
export const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));
/** A note's name in sharps, as the keyboard names it, with its octave if asked: C♯, or C♯4. */
export const noteName = (midi, { octave = false } = {}) => `${NAMES[((midi % 12) + 12) % 12]}${octave ? Math.floor(midi / 12) - 1 : ''}`;
const crypto01 = () => crypto.getRandomValues(new Uint32Array(1))[0] / 2 ** 32;
const make = (tag, className = '', text = '') => Object.assign(document.createElement(tag), { className, textContent: text });
const serious = () => document.documentElement.dataset.skin === 'serious';

let sounding = null;

/** Stops the round playing now, if any. */
export function silence() {
    sounding?.stop();
    sounding = null;
}

/** A round's clip in the guide voice, as samples at the audio context's rate (made ahead, so Play starts at once). */
export function renderRound(round) {
    return renderClip(round.clips[0], round.base, { tempo: TEMPO, sampleRate: audio().sampleRate });
}

/** Plays a round (its samples, if made ahead) through the free tools' shared player, on the audio clock: one at a time. */
export function playRound(round, samples = renderRound(round)) {
    silence();
    const playing = sound(samples, { at: audio().currentTime + 0.05 });
    playing.ended.then(() => sounding === playing && (sounding = null));
    sounding = playing;
}

/** The notes a round sounds, lowest first. */
export const roundNotes = (round) => [...new Set([...round.clips[0].notes, ...(round.clips[0].voices ?? []).flat()].map((n) => round.base + n.step))].sort((a, b) => a - b);

const HERO_CLASS = 'min-h-[4.5rem] text-[clamp(2.5rem,12vw,3.75rem)] font-black leading-none tracking-tight [@media(max-height:640px)]:min-h-12 [@media(max-height:640px)]:text-[2.5rem]';

// The hero: the answer, big, once it's known; a quiet mark before.
const HERO = `<p class="${HERO_CLASS} text-muted" data-ui="answer-hero" data-hero>?</p>
<p class="sr-only" aria-live="polite" data-live></p>`;

const READOUT = `
<div class="grid gap-1.5 text-start" data-ui="level-bar">
  <ol class="grid grid-cols-10 gap-1" aria-hidden="true" data-segments></ol>
  <p class="text-xs text-muted"><span data-level-caption></span><span data-share-wrap hidden> · <button type="button" class="link -my-1 py-1 font-semibold" data-ui="quiz-share" data-share></button></span> <span class="text-good empty:hidden" aria-live="polite" data-share-said></span></p>
</div>
<div class="grid justify-items-center gap-2" data-after>
  <div class="flex flex-wrap justify-center gap-2" data-compare hidden>
    <button type="button" class="btn-secondary btn-sm" data-yours></button>
    <button type="button" class="btn-secondary btn-sm" data-right-one></button>
  </div>
  <p class="text-sm text-muted empty:hidden" data-song></p>
  <button type="button" class="btn-primary btn-sm" data-level-up hidden></button>
</div>`;

const PANEL = `
<div class="flex h-full flex-col gap-3 p-3">
  <div class="[@media(max-height:700px)]:hidden" data-ui="keyboard" data-keyboard hidden>
    <div class="relative h-14 select-none" aria-hidden="true" data-keys></div>
  </div>
  <div class="grid grid-cols-2 gap-2" role="group" data-answers></div>
</div>`;

/** More: Hillel's settings sheet (tool-layout.js sheetRow), a hairline row each. */
function buildSheet(ui, words) {
    sheetRow(ui.sheet, { label: words.practise ?? '' }).body.innerHTML = '<div class="flex flex-wrap gap-x-4 gap-y-1" data-choose></div><p class="mt-2 hidden text-sm text-warn" role="alert" data-need-one></p>';
    sheetRow(ui.sheet).body.innerHTML = '<div class="space-y-1" data-toggles></div>';
    sheetRow(ui.sheet, { label: words.range ?? '' }).body.innerHTML = `<div class="flex flex-wrap items-center gap-3">
    <label class="flex items-center gap-2 text-sm text-muted"><span data-from></span><select class="input w-auto" translate="no" data-low></select></label>
    <label class="flex items-center gap-2 text-sm text-muted"><span data-to></span><select class="input w-auto" translate="no" data-high></select></label>
  </div>`;
    sheetRow(ui.sheet).body.innerHTML = `<div data-results hidden>
  <p class="mb-1 text-sm font-semibold text-cream" data-results-title></p>
  <table class="w-full text-sm"><thead><tr class="text-muted"><th class="py-1 text-start font-semibold"></th><th class="py-1 text-end font-semibold" data-col-heard></th><th class="py-1 text-end font-semibold" data-col-missed></th><th class="py-1 text-end font-semibold" data-col-accuracy></th></tr></thead><tbody class="tabular-nums text-cream" data-results-rows></tbody></table>
</div><p class="text-xs text-muted" data-keys-hint></p>`;
}

/** The keys open at `level` (0-based): every level's keys up to it. */
const keysAt = (levels, level) => [...new Set(levels.slice(0, level + 1).flatMap((l) => l.keys))];

export function mountQuiz(root, { words = {}, keySlot = null, choices = [], levels = null, modes = [], mode = modes[0]?.key, low = 48, high = 72, toggles = [], round, describe = () => '', sublabel = () => '', reminder = () => '', keyboard = false, store = null, random = crypto01, play = playRound, onAnswer = null, onLesson = null, onShared = null, share = null } = {}) {
    let answeredHere = 0;
    const ui = toolLayout(root, { keySlot, more: words.settings, done: words.done, title: words.settingsTitle ?? '', reset: words.reset ?? '' });
    ui.hero.innerHTML = HERO;
    ui.readout.innerHTML = READOUT;
    ui.panel.innerHTML = PANEL;
    buildSheet(ui, words);
    const card = ui.element;
    card.dataset.quiz = '';
    card.dataset.phase = 'idle';
    const $ = (selector) => root.querySelector(selector);
    const text = (selector, value) => ($(selector).textContent = value ?? '');
    const order = choices.map((c) => c.key);
    const ladder = levels?.length ? levels : [{ label: '', keys: order }];
    const labelOf = (key) => choices.find((c) => c.key === key)?.label ?? key;

    // Remembered on this device (js/public/tool-prefs.js, under the page's slug): the level, the way it's
    // played, your own set, the range and the toggles.
    const allToggles = [{ key: 'fixedRoot', label: words.fixedRoot }, ...toggles];
    const defaults = { level: 0, own: null, mode, low, high, toggles: Object.fromEntries(allToggles.map((t) => [t.key, !!t.on])) };
    const prefs = store ? toolPrefs(store, {
        defaults,
        valid: {
            level: (v) => Number.isInteger(v) && v >= 0 && v < ladder.length,
            own: (v) => v === null || (Array.isArray(v) && v.some((k) => order.includes(k))),
            mode: (v) => modes.length === 0 || modes.some((m) => m.key === v),
            low: (v) => Number.isInteger(v) && v >= LOWEST && v <= HIGHEST,
            high: (v) => Number.isInteger(v) && v >= LOWEST && v <= HIGHEST,
        },
        // Your own set holds only keys there are; the range stays at least an octave.
        clean: (k) => ({ ...k, own: k.own && order.filter((key) => k.own.includes(key)), ...(k.high - k.low >= 12 ? {} : { low: defaults.low, high: defaults.high }) }),
    }) : null;
    const kept = prefs?.value ?? structuredClone(defaults);
    const state = {
        level: kept.level, own: kept.own, mode: kept.mode, low: kept.low, high: kept.high, toggles: { ...kept.toggles },
        question: null, sound: null, answered: false, right: 0, total: 0, streak: 0, recent: [], stats: {}, picked: null, upcoming: null,
    };
    const save = () => {
        if (!prefs) return;
        Object.assign(prefs.value, { level: state.level, own: state.own, mode: state.mode, low: state.low, high: state.high, toggles: { ...state.toggles } });
        prefs.save();
    };
    const active = () => state.own ?? keysAt(ladder, state.level);

    for (const [selector, key] of [['[data-yours]', 'yours'], ['[data-share]', 'share'], ['[data-right-one]', 'rightOne'], ['[data-results-title]', 'results'], ['[data-col-heard]', 'heard'],
        ['[data-col-missed]', 'missed'], ['[data-col-accuracy]', 'accuracy'],
        ['[data-from]', 'from'], ['[data-to]', 'to'], ['[data-need-one]', 'needOne']]) text(selector, words[key]);
    $('[data-answers]').setAttribute('aria-label', words.answers ?? '');
    // The level bar's ten segments.
    for (let i = 0; i < UNLOCK.answers; i++) {
        const segment = make('li', 'h-1.5 rounded-full');
        segment.dataset.ui = 'level-segment';
        $('[data-segments]').append(segment);
    }
    // The keyboard (chords): two octaves in a piano's colours in every theme (piano.js), lit with the round's notes once answered.
    if (keyboard) {
        $('[data-keyboard]').hidden = false;
        for (let i = 0; i < 14; i++) {
            const key = make('span', `absolute inset-y-0 flex items-end justify-center rounded-b-md border ${PIANO.edge} ${PIANO.white} pb-1 text-[10px] font-bold ${PIANO.ink}`);
            Object.assign(key.style, { left: `${(i * 100) / 14}%`, width: `${100 / 14}%` });
            key.dataset.ui = 'key';
            key.dataset.pc = String(WHITE[i % 7] + 12 * Math.floor(i / 7));
            key.dataset.white = 'true';
            $('[data-keys]').append(key);
        }
        for (let o = 0; o < 2; o++) {
            for (const [pc, after] of BLACK) {
                const key = make('span', `absolute top-0 z-10 h-[60%] rounded-b-md ${PIANO.black}`);
                Object.assign(key.style, { left: `calc(${((o * 7 + after + 1) * 100) / 14}% - 2.2%)`, width: '4.4%' });
                key.dataset.ui = 'key';
                key.dataset.pc = String(pc + 12 * o);
                $('[data-keys]').append(key);
            }
        }
    }

    // The answers, a tile each; the ones not being practised are hidden.
    const buttons = new Map();
    for (const choice of choices) {
        const button = make('button', 'relative flex min-h-14 flex-col items-center justify-center rounded-2xl bg-panel px-3 py-2 text-center text-cream transition disabled:opacity-45 [@media(max-height:700px)]:min-h-12 [@media(max-height:700px)]:py-1');
        button.type = 'button';
        button.dataset.ui = 'answer';
        button.dataset.answer = choice.key;
        const hint = make('span', 'absolute end-2.5 top-1.5 hidden text-[11px] text-muted sm:inline');
        hint.dataset.hotkey = '';
        hint.setAttribute('aria-hidden', 'true');
        const name = make('span', 'flex items-center gap-1.5 font-bold');
        const mark = make('span', 'empty:hidden');
        mark.dataset.mark = '';
        mark.setAttribute('aria-hidden', 'true');
        name.append(mark, make('span', '', choice.label));
        const sub = make('span', 'text-xs text-muted empty:hidden');
        sub.dataset.sub = '';
        button.append(hint, name, sub);
        button.addEventListener('click', () => answer(choice.key));
        buttons.set(choice.key, button);
        $('[data-answers]').append(button);
    }
    // How it's played: the modes. The level: the key control.
    const modeControl = modes.length > 1 ? segmented(ui.modes, modes, { current: state.mode, pick: setMode, label: words.playNotes }) : null;
    if (modeControl) for (const b of modeControl.element.children) b.dataset.mode = b.dataset.segment;
    const levelKey = ladder.length > 1 ? stepper(ui.key, { label: words.levelShort, name: words.levelShort, lower: words.levelDown, higher: words.levelUp, step: (by) => setLevel(state.own ? state.level : state.level + by) }) : null;
    // The sheet: your own set, the toggles, the range, the results, the keys.
    for (const choice of choices) {
        const label = make('label', 'check-row text-sm');
        const box = Object.assign(document.createElement('input'), { type: 'checkbox', className: 'checkbox', value: choice.key });
        box.addEventListener('change', () => {
            const set = new Set(active());
            box.checked ? set.add(choice.key) : set.delete(choice.key);
            if (!set.size) {
                box.checked = true;
                $('[data-need-one]').classList.remove('hidden');
                return;
            }
            $('[data-need-one]').classList.add('hidden');
            state.own = order.filter((key) => set.has(key));
            save();
            shown();
        });
        label.append(box, document.createTextNode(` ${choice.label}`));
        $('[data-choose]').append(label);
    }
    for (const toggle of allToggles) {
        const label = make('label', 'check-row text-sm');
        const box = Object.assign(document.createElement('input'), { type: 'checkbox', className: 'checkbox', checked: state.toggles[toggle.key] });
        box.dataset.toggle = toggle.key;
        box.addEventListener('change', () => {
            state.toggles[toggle.key] = box.checked;
            save();
            shown();
        });
        label.append(box, document.createTextNode(` ${toggle.label ?? ''}`));
        $('[data-toggles]').append(label);
    }
    for (const [select, value] of [[$('[data-low]'), state.low], [$('[data-high]'), state.high]]) {
        for (let midi = LOWEST; midi <= HIGHEST; midi++) select.append(new Option(midiName(midi), String(midi), false, midi === value));
    }
    $('[data-low]').addEventListener('change', (event) => {
        state.low = Math.min(Number(event.target.value), state.high - 12);
        event.target.value = String(state.low);
        save();
    });
    $('[data-high]').addEventListener('change', (event) => {
        state.high = Math.max(Number(event.target.value), state.low + 12);
        event.target.value = String(state.high);
        save();
    });

    // More's Reset: the level, the way it's played, your own set, the range and the toggles back to their defaults.
    ui.onReset(() => {
        prefs?.reset();
        const fresh = prefs?.value ?? defaults;
        Object.assign(state, { level: fresh.level, own: fresh.own, mode: fresh.mode, low: fresh.low, high: fresh.high, toggles: { ...fresh.toggles }, recent: [] });
        modeControl?.set(state.mode);
        for (const box of root.querySelectorAll('[data-toggles] [data-toggle]')) box.checked = state.toggles[box.dataset.toggle];
        $('[data-low]').value = String(state.low);
        $('[data-high]').value = String(state.high);
        $('[data-level-up]').hidden = true;
        shown();
    });

    function setLevel(i) {
        state.level = Math.min(Math.max(0, i), ladder.length - 1);
        state.own = null;
        state.recent = [];
        save();
        $('[data-level-up]').hidden = true;
        shown();
    }

    /** A new way to play it; asked and not answered yet, the same notes are played again that way. */
    function setMode(key) {
        state.mode = key;
        modeControl?.set(key);
        save();
        if (state.question && !state.answered) {
            state.question = round({ key: state.question.key, ...params({ base: state.question.base, inversion: state.question.inversion }) });
            state.sound = null;
            replay();
        }
        shown();
    }

    /** Everything that follows the state. */
    function shown() {
        const open = active();
        const phase = card.dataset.phase;
        let n = 0;
        const visible = [];
        for (const [key, button] of buttons) {
            button.hidden = !open.includes(key);
            button.classList.remove('col-span-2');
            button.querySelector('[data-hotkey]').textContent = button.hidden ? '' : KEYS[n++] ?? '';
            button.querySelector('[data-sub]').textContent = state.toggles.sublabels === false ? '' : sublabel(key) || '';
            button.disabled = phase === 'idle';
            if (!button.hidden) visible.push(button);
        }
        if (visible.length % 2) visible.at(-1).classList.add('col-span-2'); // the odd one out across both columns
        for (const box of $('[data-choose]').querySelectorAll('input')) box.checked = open.includes(box.value);
        // The level: the key control's number, and the panel's label with its stat (the streak in game mode only).
        levelKey?.set(state.own ? '·' : String(state.level + 1), { atLow: !state.own && state.level === 0, atHigh: !state.own && state.level >= ladder.length - 1 });
        const accuracy = state.total ? `${Math.round((100 * state.right) / state.total)}%` : '';
        const stat = state.total ? (serious() ? fill(words.statRight, { accuracy }) : fill(words.statStreak, { accuracy, streak: state.streak })) : '';
        ui.setLabel(state.own ? words.ownSet : fill(words.level, { n: state.level + 1, name: ladder[state.level]?.label ?? '' }), stat);
        // The level bar: the last 10 answers here, right filled and a miss hollow; and how close the next level is.
        [...$('[data-segments]').children].forEach((segment, i) => {
            const answered = state.recent[i];
            segment.className = `h-1.5 rounded-full ${answered === true ? 'bg-good' : answered === false ? 'border border-bad' : 'bg-raised'}`;
            segment.dataset.result = answered === true ? 'right' : answered === false ? 'wrong' : '';
        });
        const top = state.level >= ladder.length - 1;
        $('[data-ui=level-bar]').hidden = !!state.own;
        // Eight of the last ten right here (the bar that opens the next level, the top one too), just answered: a result worth
        // a card. Share it joins the caption, a link costing no height (the answers stay above the fold on a 320 x 568 phone).
        const right = state.recent.filter(Boolean).length;
        const earned = !!share && !state.own && phase === 'answered' && state.recent.length >= UNLOCK.answers && right >= UNLOCK.right;
        const caption = top ? words.topLevel : earned ? fill(words.lastTen, { right }) : fill(words.toNext, { right, need: UNLOCK.right, level: state.level + 2, name: ladder[state.level + 1]?.label ?? '' });
        text('[data-level-caption]', caption);
        if (earned !== !$('[data-share-wrap]').hidden) text('[data-share-said]', '');
        $('[data-share-wrap]').hidden = !earned;
        text('[data-keys-hint]', fill(words.keys, { last: KEYS[Math.min(open.length, KEYS.length) - 1] ?? '' }));
        ui.setPrimary({ label: phase === 'asking' ? words.again : phase === 'answered' ? words.next : words.play });
        const rows = order.filter((key) => state.stats[key]).map((key) => {
            const { heard, missed } = state.stats[key];
            const row = make('tr');
            row.append(make('td', 'py-1', labelOf(key)), make('td', 'py-1 text-end', String(heard)), make('td', 'py-1 text-end', String(missed)), make('td', 'py-1 text-end', `${Math.round((100 * (heard - missed)) / heard)}%`));
            return row;
        });
        $('[data-results-rows]').replaceChildren(...rows);
        $('[data-results]').hidden = !rows.length;
    }

    /** The hero: the answer big once it's known, in the result's colour (the pill says it in words too). */
    function hero(answerKey = null, tone = '') {
        $('[data-hero]').textContent = answerKey ? labelOf(answerKey) : '?';
        $('[data-hero]').className = `${HERO_CLASS} ${tone === 'right' ? 'text-good' : tone === 'wrong' ? 'text-warn' : answerKey ? 'text-cream' : 'text-muted'}`;
    }

    /** The keyboard's keys lit with `notes` (MIDI), named on the white ones; nothing lit for none. */
    function light(notes) {
        if (!keyboard) return;
        const from = notes.length ? 12 * Math.floor(notes[0] / 12) : 0;
        const lit = new Set(notes.map((n) => n - from));
        for (const key of $('[data-keys]').children) {
            const pc = Number(key.dataset.pc);
            const on = lit.has(pc);
            key.dataset.lit = on ? 'true' : '';
            key.style.background = on ? 'var(--color-accent)' : '';
            if (key.dataset.white) key.textContent = on ? NAMES[pc % 12] : '';
        }
    }

    function params(extra = {}) {
        const fixed = state.toggles.fixedRoot ? Math.min(Math.max(60, state.low), state.high - 12) : null;
        return { mode: state.mode, low: state.low, high: state.high, random, ...state.toggles, ...(fixed !== null ? { base: fixed } : {}), ...extra };
    }

    /**
     * The next round, made (and, for the real player, rendered) ahead: while the learner reads the last
     * answer, so Play or Next starts the sound at once, even on a slow phone. Made again if the set, the
     * way it's played or the range changed since.
     */
    const settingsNow = () => JSON.stringify([active(), state.mode, state.low, state.high, state.toggles]);
    function prepare() {
        const missed = Object.fromEntries(Object.entries(state.stats).map(([key, s]) => [key, s.missed]));
        const key = pickKey(active(), { missed, random });
        const made = round({ key, ...params() });
        state.upcoming = { round: made, samples: play === playRound ? renderRound(made) : null, for: settingsNow() };
    }

    function next() {
        if (!state.upcoming || state.upcoming.for !== settingsNow()) prepare();
        const { round: made, samples } = state.upcoming;
        state.upcoming = null;
        state.question = made;
        state.sound = samples; // kept for Play it again and Right
        state.answered = false;
        state.picked = null;
        for (const button of buttons.values()) {
            delete button.dataset.result;
            button.classList.remove('ring-2', 'ring-good', 'ring-bad', 'opacity-45');
            button.removeAttribute('aria-disabled');
            button.querySelector('[data-mark]').textContent = '';
        }
        text('[data-song]', '');
        $('[data-compare]').hidden = true;
        $('[data-level-up]').hidden = true;
        light([]);
        hero();
        card.dataset.phase = 'asking';
        ui.setStatus({ tone: 'muted', text: (!state.own && ladder[state.level]?.prompt) || words.prompt });
        shown();
        play(state.question, state.sound ?? undefined);
    }

    function answer(key) {
        if (!state.question || state.answered || buttons.get(key)?.hidden) return;
        state.answered = true;
        state.picked = key;
        state.total++;
        const right = key === state.question.key;
        if (right) state.right++;
        state.streak = right ? state.streak + 1 : 0;
        const stat = (state.stats[state.question.key] ??= { heard: 0, missed: 0 });
        stat.heard++;
        if (!right) stat.missed++;
        if (!state.own) state.recent = [...state.recent, right].slice(-UNLOCK.answers);
        // Shown by a mark and a word as well as colour (docs/design-rules.md): ✓ on the answer, ✗ on a wrong pick.
        const correct = buttons.get(state.question.key);
        correct.dataset.result = 'right';
        correct.classList.add('ring-2', 'ring-good');
        correct.querySelector('[data-mark]').textContent = '✓';
        if (!right) {
            buttons.get(key).dataset.result = 'wrong';
            buttons.get(key).classList.add('ring-2', 'ring-bad');
            buttons.get(key).querySelector('[data-mark]').textContent = '✗';
        }
        // Answered: the marks stay at full strength, the rest quieter; a tap does nothing.
        for (const button of buttons.values()) {
            button.setAttribute('aria-disabled', 'true');
            if (!button.dataset.result) button.classList.add('opacity-45');
        }
        const notes = roundNotes(state.question);
        const said = right ? words.right : fill(words.youSaid, { answer: labelOf(key) });
        hero(state.question.key, right ? 'right' : 'wrong');
        ui.setStatus({ tone: right ? 'good' : 'warn', text: said, hint: describe(state.question, notes) });
        text('[data-live]', `${said}. ${labelOf(state.question.key)}.`);
        light(notes);
        $('[data-compare]').hidden = right;
        const song = reminder(state.question);
        text('[data-song]', song ? fill(words.song, { song }) : '');
        card.dataset.phase = 'answered';
        // The next level, once 8 of the last 10 here were right: offered, never forced.
        const ready = !state.own && state.level < ladder.length - 1 && state.recent.length >= UNLOCK.answers && state.recent.filter(Boolean).length >= UNLOCK.right;
        $('[data-level-up]').hidden = !ready;
        if (ready) text('[data-level-up]', fill(words.goLevel, { n: state.level + 2, name: ladder[state.level + 1].label }));
        shown();
        onAnswer?.({ answer: state.question.key, picked: key, right });
        // Five answered this visit: the page's free lesson line (page.js), once.
        if (++answeredHere === 5) onLesson?.();
        ui.primary.focus({ preventScroll: true });
        prepare();
    }

    /** The learner's pick, sounded on the same notes as the question, to hear beside the right one. */
    const yours = () => state.picked && play(round({ key: state.picked, ...params({ base: state.question.base, inversion: state.question.inversion, mode: state.question.way }) }));
    const replay = () => state.question && play(state.question, state.sound ?? undefined);

    ui.onPrimary(() => (card.dataset.phase === 'asking' ? replay() : next()));
    $('[data-yours]').addEventListener('click', yours);
    $('[data-right-one]').addEventListener('click', replay);
    $('[data-level-up]').addEventListener('click', () => setLevel(state.level + 1));
    // Share it: the last ten here as a card (result-card.js drawScoreCard), each answer a gem, shared by share-card.js
    // with the tool's link (share.tag, ?ref=share-<tool>). The page counts a share (onShared).
    $('[data-share]').addEventListener('click', async () => {
        const button = $('[data-share]');
        const marks = [...state.recent];
        const right = marks.filter(Boolean).length;
        const level = fill(words.level, { n: state.level + 1, name: ladder[state.level]?.label ?? '' });
        button.disabled = true;
        const done = await shareCard({
            fileName: share.file,
            text: fill(words.shareText, { right, total: marks.length, level }),
            url: shareUrl(share.slug, share.tag),
            draw: ({ drawScoreCard }) => drawScoreCard({
                words: {
                    title: words.cardTitle, score: fill(words.cardScore, { right, total: marks.length }), line: words.cardLine, sub: level,
                    challenge: words.cardChallenge, tagline: words.cardTagline, site: words.cardSite, arcade: arcade(),
                },
                marks,
                color: partInk(share.part),
            }),
        });
        if (done === 'saved') text('[data-share-said]', words.cardSaved);
        if (done) onShared?.();
        button.disabled = false;
    });
    // Keys (Tonedear's, which many learners know): 1 to 9, 0, - and = answer in the order shown; space plays
    // (again while asking); A hears it again. Not while the sheet is open.
    const onKey = (event) => {
        if (event.metaKey || event.ctrlKey || event.altKey || ['INPUT', 'SELECT', 'TEXTAREA'].includes(event.target?.tagName) || !card.isConnected || root.querySelector('dialog[open]')) return;
        const index = KEYS.indexOf(event.key);
        if (index >= 0 && state.question && !state.answered) {
            const visible = [...buttons.values()].filter((b) => !b.hidden);
            if (visible[index]) answer(visible[index].dataset.answer);
            event.preventDefault();
        } else if (event.code === 'Space' && !['BUTTON', 'SUMMARY'].includes(event.target?.tagName)) {
            event.preventDefault();
            state.question && !state.answered ? replay() : next();
        } else if (event.code === 'KeyA') {
            replay();
        }
    };
    document.addEventListener('keydown', onKey);
    // Game mode on or off changes whether the streak shows.
    const skins = new MutationObserver(() => shown());
    skins.observe(document.documentElement, { attributes: true, attributeFilter: ['data-skin'] });
    hero();
    ui.setStatus({ hint: words.readySub });
    shown();
    return {
        next,
        replay,
        answer,
        yours,
        current: () => state.question,
        score: () => ({ right: state.right, total: state.total, streak: state.streak }),
        stats: () => structuredClone(state.stats),
        level: () => (state.own ? null : state.level),
        setLevel,
        stop() {
            silence();
            skins.disconnect();
            document.removeEventListener('keydown', onKey);
            levelKey?.destroy();
        },
        element: card,
    };
}
