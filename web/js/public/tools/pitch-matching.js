// The pitch matching game, /tools/pitch-matching (docs/tools-plan.md, batch 3; the review in
// docs/tools/competitors-tap-tempo-mic-test-pitch-memory.md, section 3), on the tools' one layout
// (js/public/tool-layout.js): hear a note, hold it in your head through a silence that grows with the
// level, then sing it back. The note stops before you sing, so a phone speaker is fine: no headphones
// (every sing-back competitor plays while you sing). Its address is pitch-matching, the singers' and
// teachers' term (docs/tools/seo-2.md); it says plainly it isn't a perfect-pitch test. The brand row's
// key control is the level (the wait: none, 2, 5 or 10 seconds); Low voice | High voice are the modes;
// the hero is a ring that fills while the sung note is held steady, with the stage or the result in its
// middle; the pill says what to do, then how close ("Spot on · 6 cents flat"); the readout lights the
// stages in turn (Listen, Wait, Sing now); the panel is the round, ten dots coloured as they're sung,
// with the score. The bar is Play note, then Next. Behind More: strict octave, the mic, and what this
// game is and isn't.
//
// A thin wrapper: the rounds and the judging come from pitch-matching-rounds.js (a note is judged once
// held steady, the scoop ignored, in any octave unless strict), the landing from Perfect your pitch's
// Bullseye (listen.js dart()), the mic and the tracker from live-mic.js (limited to the voice's
// reach, so it can't jump an octave), the note from voices.js, every stage on the audio clock
// (demo-player.js when(); no timers). The mic opens on the first Play; nothing is recorded or sent. The
// level, voice and best scores stay on this device (tool-prefs.js). With a range the range test saved,
// Your range is a third voice, its notes from that range's middle. The words come from the page (:n,
// :total and :best are filled in here), set as text, never markup.
//
//   import { mountPitchMatching, WORDS } from '/js/public/tools/pitch-matching.js';
//   const game = mountPitchMatching(el, { words, keySlot });
//
// The handle: play(), again(), next(), stop() and element. `open` stands in for the mic in tests, and
// `random` for the note picker.

import { when } from '../demo-player.js';
import { dart } from '../listen.js';
import { listen, TRACKER_FILES } from '../live-mic.js';
import { onPicked, savedMic, showPicker } from '../mic-pick.js';
import { savedRange } from '../saved-range.js';
import { segmented, sheetRow, stepper, toolLayout } from '../tool-layout.js';
import { toolPrefs } from '../tool-prefs.js';
import { audio, hz, voice } from '../voices.js';
import { judge, LEVELS, NOTE_SECONDS, pickNote, ROUND, SILENT_SECONDS, steadiness, VOICES } from './pitch-matching-rounds.js';
import { arcade, partInk, SHARE_FILES, shareCard, shareUrl } from './share-card.js';

/** The files the tool loads only when the mic opens: its page keeps them, so it works offline before a first Start. */
export const WARM = [...TRACKER_FILES, ...SHARE_FILES];

/** The words a page passes, each translated. */
export const WORDS = [
    'playNote', 'asking', 'listen', 'wait', 'singNow', 'singHint', 'holdIt', 'next', 'again', 'tryAgain', 'newRound', 'settings', 'settingsTitle', 'reset', 'done',
    'idleHint', 'level', 'easier', 'harder', 'levelNoWait', 'levelWait', 'low', 'high', 'yours', 'voices', 'round', 'score',
    'spot', 'close', 'nearly', 'off', 'centsFlat', 'centsSharp', 'centsOn', 'octaveLower', 'octaveHigher',
    'noNote', 'noNoteHint', 'denied', 'noMic', 'roundDone', 'best', 'noHeadphones', 'privacy', 'honest', 'strict', 'micLabel',
    'share', 'shareText', 'cardTitle', 'cardScore', 'cardLine', 'cardChallenge', 'cardTagline', 'cardSite', 'cardSaved',
];

const RIGHT = new Set(['spot', 'close']); // counts toward the score
const SHARE_FROM = 5; // a round with at least this many right offers its card: a result worth showing, never a nudge
const TONES = { spot: 'good', close: 'good', nearly: 'warn', off: 'warn' };
const DOTS = { spot: 'bg-good', close: 'bg-good/60', nearly: 'bg-warn/70', off: 'bg-warn' };
const LEAD = 0.15; // seconds from the tap to the note, so it starts cleanly
const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));

/** The settings, remembered on this device (js/public/tool-prefs.js), and the best score per voice and level. */
const DEFAULTS = { level: 1, range: 'high', strict: false, best: {} };
const isPlain = (x) => x !== null && typeof x === 'object' && !Array.isArray(x);
const VALID = {
    level: (v) => Number.isInteger(v) && v >= 0 && v < LEVELS.length,
    range: (v) => ['low', 'high', 'yours'].includes(v),
    best: (v) => isPlain(v) && Object.values(v).every((n) => Number.isInteger(n) && n >= 0 && n <= ROUND),
};

/** Where your own notes come from: the middle of the range the range test saved, a little in from each end. */
export function ownVoice(range) {
    if (!range) return null;
    const low = range.low + 2;
    const high = range.high - 2;
    return high - low >= 7 ? { low, high } : { low: range.low, high: range.high };
}

const RING = 2 * Math.PI * 54;
const HERO = `
<div class="relative mx-auto grid size-56 place-items-center [@media(max-height:640px)]:size-40" data-ui="pitch-ring">
  <svg class="absolute inset-0 size-full -rotate-90" viewBox="0 0 120 120" aria-hidden="true">
    <circle cx="60" cy="60" r="54" fill="none" stroke-width="8" class="stroke-[var(--color-raised)]"></circle>
    <circle cx="60" cy="60" r="54" fill="none" stroke-width="8" stroke-linecap="round" class="stroke-[var(--primary)] transition-[stroke-dashoffset] duration-100 motion-reduce:transition-none" stroke-dasharray="${RING.toFixed(1)}" stroke-dashoffset="${RING.toFixed(1)}" data-ring></circle>
  </svg>
  <div class="relative grid justify-items-center gap-1 px-6 text-center">
    <span class="text-[2.25rem] font-black leading-none tracking-tight text-cream [@media(max-height:640px)]:text-[1.75rem]" aria-live="polite" data-centre></span>
    <span class="text-sm font-semibold tabular-nums text-muted" data-sub></span>
  </div>
</div>`;

const READOUT = `
<ol class="flex items-center justify-center gap-2 text-sm font-bold" data-ui="pitch-stages">
  <li class="rounded-full px-3 py-1" data-stage="listen" data-w="listen"></li>
  <li class="rounded-full px-3 py-1" data-stage="wait" data-w="wait"></li>
  <li class="rounded-full px-3 py-1" data-stage="sing" data-w="singNow"></li>
</ol>`;

const PANEL = `
<div class="absolute inset-0 flex flex-col gap-3 overflow-y-auto p-4 text-sm leading-relaxed">
  <div class="grid grid-cols-10 gap-1.5" data-ui="pitch-dots" data-dots></div>
  <p class="font-semibold text-cream" data-level-name></p>
  <p class="font-bold text-cream" data-summary hidden></p>
  <p class="flex flex-wrap items-center gap-x-3 gap-y-1" data-share-row hidden><button type="button" class="btn-secondary btn-sm" data-ui="pitch-share" data-share data-w="share"></button><span class="text-sm text-good" aria-live="polite" data-share-said></span></p>
  <div data-again-row hidden><button type="button" class="btn-secondary btn-sm" data-ui="pitch-again" data-again data-w="again"></button></div>
  <p class="font-semibold text-cream" data-no-headphones data-w="noHeadphones"></p>
  <p class="text-muted" data-privacy data-w="privacy"></p>
</div>`;

/** More: Hillel's settings sheet (tool-layout.js sheetRow), a hairline row each. */
function buildSheet(ui, w) {
    sheetRow(ui.sheet).body.innerHTML = '<label class="check-row"><input type="checkbox" class="checkbox" data-strict /> <span data-w="strict"></span></label>';
    sheetRow(ui.sheet).body.innerHTML = '<label class="grid gap-1 text-sm" hidden data-picker-label><span class="font-semibold text-cream" data-w="micLabel"></span><select class="input" data-picker></select></label>';
    sheetRow(ui.sheet).body.innerHTML = '<p class="text-sm text-muted" data-w="honest"></p>';
}

export function mountPitchMatching(root, { words = {}, keySlot = null, onLesson = null, onShared = null, open = listen, random = Math.random } = {}) {
    const w = (key) => words[key] ?? '';
    const ui = toolLayout(root, { keySlot, more: w('settings'), done: w('done'), title: w('settingsTitle'), reset: w('reset') });
    ui.hero.innerHTML = HERO;
    ui.readout.innerHTML = READOUT;
    ui.panel.innerHTML = PANEL;
    buildSheet(ui, w);
    const card = ui.element;
    card.dataset.tool = 'pitch-matching';
    const $ = (selector) => card.querySelector(selector);
    const text = (selector, value) => ($(selector).textContent = value ?? '');
    for (const el of card.querySelectorAll('[data-w]')) el.textContent = w(el.dataset.w);
    const dots = Array.from({ length: ROUND }, () => document.createElement('span'));
    $('[data-dots]').append(...dots);

    const prefs = toolPrefs('pitch-matching', { defaults: DEFAULTS, valid: VALID });
    const settings = prefs.value;
    // Your own range (the range test's, on this device) as a third voice; without one, Low and High.
    const own = ownVoice(savedRange());
    if (settings.range === 'yours' && !own) settings.range = 'high';
    const reach = () => (settings.range === 'yours' ? own : VOICES[settings.range]);
    let stage = 'idle'; // idle, asking, listen, wait, sing, result, silent, problem
    let mic = null;
    let note = null;
    let results = []; // a verdict per note sung this round
    let session = 0; // each played note's own number, so a stale stage step is dropped
    let singFrom = null;
    let lastHeard = null;
    let result = null; // { verdict, cents, octave }
    let sounding = null;
    let waitEnd = 0; // when the wait ends, on the audio clock (its countdown)

    const level = stepper(ui.key, { label: w('level'), name: w('level'), lower: w('easier'), higher: w('harder'), step: (by) => {
        settings.level = Math.max(0, Math.min(LEVELS.length - 1, settings.level + by));
        prefs.save();
        newRound();
    } });
    const voices = segmented(ui.modes, [{ key: 'low', label: w('low') }, { key: 'high', label: w('high') }, ...(own ? [{ key: 'yours', label: w('yours') }] : [])], { current: settings.range, label: w('voices'), pick: (key) => {
        settings.range = key;
        voices.set(key);
        prefs.save();
        // The tracker's reach follows the voice: the mic opens again on the next Play.
        mic?.stop();
        mic = null;
        newRound();
    } });
    $('[data-strict]').checked = settings.strict;

    const gap = () => LEVELS[settings.level].gap;
    const right = () => results.filter((r) => RIGHT.has(r)).length;

    function render() {
        card.dataset.stage = stage;
        const playing = ['listen', 'wait', 'sing', 'silent'].includes(stage);
        card.dataset.verdict = result?.verdict ?? '';
        level.set(settings.level + 1, { atLow: settings.level === 0, atHigh: settings.level === LEVELS.length - 1 });
        text('[data-level-name]', gap() ? fill(w('levelWait'), { n: settings.level + 1, gap: gap() }) : fill(w('levelNoWait'), { n: settings.level + 1 }));
        // The stages light in turn; the wait isn't there at level 1.
        $('[data-stage=wait]').hidden = !gap();
        for (const li of card.querySelectorAll('[data-stage]')) {
            const on = li.dataset.stage === stage;
            li.className = `rounded-full px-3 py-1 ${on ? 'bg-[var(--primary)] text-[var(--primary-ink)]' : 'bg-raised text-muted'}`;
            li.toggleAttribute('aria-current', on);
        }
        dots.forEach((dot, i) => {
            dot.className = `aspect-square rounded-full ${results[i] ? DOTS[results[i]] : i === results.length && playing ? 'bg-cream/40' : 'bg-panel'}`;
        });
        const centre = { idle: '♪', asking: '♪', listen: w('listen'), wait: String(Math.max(1, Math.ceil(waitEnd - audio().currentTime))), sing: w('singNow'), result: w(result?.verdict), silent: w('noNote'), problem: '' }[stage];
        text('[data-centre]', centre);
        text('[data-sub]', stage === 'result' ? centsWords(result) : '');
        if (stage !== 'sing') ring(stage === 'result' ? 1 : 0);
        if (stage === 'idle') ui.setStatus({ hint: w('idleHint') });
        else if (stage === 'asking') ui.setStatus({ tone: 'muted', text: w('asking') });
        // The ring and the stages say where the round is; the pill only says how.
        else if (stage === 'listen') ui.setStatus({});
        else if (stage === 'wait') ui.setStatus({ hint: w('holdIt') });
        else if (stage === 'sing') ui.setStatus({ hint: w('singHint') });
        else if (stage === 'result') ui.setStatus({ tone: TONES[result.verdict], text: w(result.verdict), hint: centsWords(result) });
        else if (stage === 'silent') ui.setStatus({ tone: 'warn', text: w('noNote'), hint: w('noNoteHint') });
        ui.setLabel(fill(w('round'), { n: Math.min(ROUND, results.length + (playing ? 1 : 0)) || 1, total: ROUND }), results.length ? fill(w('score'), { n: right(), total: results.length }) : '');
        const over = results.length >= ROUND;
        $('[data-summary]').hidden = !over;
        text('[data-summary]', over ? `${fill(w('roundDone'), { n: right(), total: ROUND })} ${bestWords()}`.trim() : '');
        $('[data-share-row]').hidden = !over || right() < SHARE_FROM;
        $('[data-again-row]').hidden = !['result', 'silent'].includes(stage);
        $('[data-privacy]').hidden = !!mic;
        $('[data-no-headphones]').hidden = !!mic;
        const busy = ['asking', 'listen', 'wait', 'sing'].includes(stage);
        const primary = stage === 'result' ? w(over ? 'newRound' : 'next') : stage === 'silent' ? w('tryAgain') : w('playNote');
        ui.setPrimary({ label: stage === 'asking' ? w('asking') : busy ? w('playNote') : primary });
        ui.primary.disabled = busy;
    }

    function centsWords(r) {
        if (!r) return '';
        const off = Math.abs(r.cents);
        const cents = off < 3 ? w('centsOn') : fill(w(r.cents < 0 ? 'centsFlat' : 'centsSharp'), { n: off });
        return r.octave ? `${cents}, ${w(r.octave < 0 ? 'octaveLower' : 'octaveHigher')}` : cents;
    }

    function bestWords() {
        const best = settings.best[`${settings.range}${settings.level}`];
        return Number.isFinite(best) ? fill(w('best'), { best, total: ROUND }) : '';
    }

    function ring(share) {
        $('[data-ring]').setAttribute('stroke-dashoffset', (RING * (1 - Math.max(0, Math.min(1, share)))).toFixed(1));
    }

    async function openMic() {
        if (mic) return true;
        const range = reach();
        stage = 'asking';
        render();
        try {
            // The tracker reaches an octave either side of the voice's notes, and no further.
            mic = await open({ onFrame: feed, deviceId: savedMic(), minHz: hz(range.low - 12) * 0.95, maxHz: hz(range.high + 12) * 1.05 });
            await showPicker($('[data-picker]'), mic.deviceId?.() ?? '').catch((error) => console.warn('The mic picker could not be filled', error));
            return true;
        } catch (error) {
            mic = null;
            stage = 'problem';
            render();
            ui.setStatus({ tone: 'warn', text: w(error?.reason === 'denied' ? 'denied' : 'noMic') });
            return false;
        }
    }

    /** Plays this round's note, then the wait, then Sing now, each step on the audio clock. */
    async function play({ same = false } = {}) {
        if (!(await openMic())) return;
        if (!same || note === null) note = pickNote(reach(), note, random);
        const mine = ++session;
        result = null;
        mic.target?.(note);
        const c = audio();
        const t0 = c.currentTime + LEAD;
        sounding?.stop();
        sounding = voice(hz(note), { at: t0, timbre: 'oo', level: 0.2 });
        sounding.stop(t0 + NOTE_SECONDS - c.currentTime);
        stage = 'listen';
        render();
        await when(t0 + NOTE_SECONDS);
        if (mine !== session) return;
        if (gap()) {
            waitEnd = t0 + NOTE_SECONDS + gap();
            stage = 'wait';
            render();
            await when(t0 + NOTE_SECONDS + gap());
            if (mine !== session) return;
        }
        // A short breath after the note's tail, so its ring isn't heard as the voice.
        singFrom = c.currentTime + 0.1;
        lastHeard = singFrom;
        stage = 'sing';
        render();
        when(singFrom + SILENT_SECONDS).then(() => silentCheck(mine));
    }

    // Nothing sung for a while after the cue: say so, and wait for a tap (never skip ahead).
    function silentCheck(mine) {
        if (mine !== session || stage !== 'sing') return;
        const c = audio();
        if (c.currentTime - lastHeard >= SILENT_SECONDS - 0.05) {
            stage = 'silent';
            render();
        } else when(lastHeard + SILENT_SECONDS).then(() => silentCheck(mine));
    }

    function feed(frame) {
        // The wait counts down in the ring as the readings come (each animation frame).
        if (stage === 'wait') text('[data-centre]', String(Math.max(1, Math.ceil(waitEnd - audio().currentTime))));
        if (stage !== 'sing' || frame.at < singFrom) return;
        if (frame.clear) lastHeard = frame.at;
        const frames = mic.since(singFrom);
        const steady = steadiness(frames, { strict: settings.strict });
        ring(steady.share);
        if (steady.cents === null) return;
        // Held: scored as Bullseye scores a landing (any octave), or as sung when strict.
        const landed = settings.strict ? null : dart(frames, note);
        const cents = landed?.cents ?? steady.cents;
        const heard = frames.filter((f) => f.clear && Number.isFinite(f.cents)).slice(-12).map((f) => f.cents).sort((a, b) => a - b);
        const raw = heard[heard.length >> 1] ?? 0;
        const octave = settings.strict ? 0 : Math.round(raw / 1200);
        result = { verdict: judge(cents), cents, octave };
        results.push(result.verdict);
        session++;
        stage = 'result';
        if (results.length >= ROUND) {
            finish();
            onLesson?.(); // a round sung: the page's free lesson line (page.js)
        }
        render();
    }

    function finish() {
        const key = `${settings.range}${settings.level}`;
        settings.best[key] = Math.max(settings.best[key] ?? 0, right());
        prefs.save();
    }

    function newRound() {
        session++;
        sounding?.stop();
        sounding = null;
        results = [];
        text('[data-share-said]', '');
        note = null;
        result = null;
        stage = 'idle';
        render();
    }

    /** The same note again (after a result, or when nothing was heard). */
    function again() {
        if (stage === 'silent') return play({ same: true });
        sounding?.stop();
        sounding = voice(hz(note), { timbre: 'oo', level: 0.2 });
        sounding.stop(NOTE_SECONDS);
    }

    function next() {
        if (results.length >= ROUND) newRound();
        return play();
    }

    // Share my round: the round's card (result-card.js drawScoreCard), each note a gem, shared by share-card.js with the
    // tool's link (?ref=share-pitch-matching). Offered only after a good round; the page counts a share (onShared).
    $('[data-share]').addEventListener('click', async () => {
        const button = $('[data-share]');
        const n = right();
        button.disabled = true;
        const done = await shareCard({
            fileName: 'my-pitch-matching-round.png',
            text: fill(w('shareText'), { n, total: ROUND }),
            url: shareUrl('pitch-matching', 'share-pitch-matching'),
            draw: ({ drawScoreCard }) => drawScoreCard({
                words: {
                    title: w('cardTitle'), score: fill(w('cardScore'), { n, total: ROUND }), line: w('cardLine'),
                    sub: gap() ? fill(w('levelWait'), { n: settings.level + 1, gap: gap() }) : fill(w('levelNoWait'), { n: settings.level + 1 }),
                    challenge: w('cardChallenge'), tagline: w('cardTagline'), site: w('cardSite'), arcade: arcade(),
                },
                marks: results.map((r) => RIGHT.has(r)),
                color: partInk(3),
            }),
        });
        if (done === 'saved') text('[data-share-said]', w('cardSaved'));
        if (done) onShared?.();
        button.disabled = false;
    });

    ui.onPrimary(() => {
        if (stage === 'silent') play({ same: true });
        else if (['idle', 'result', 'problem'].includes(stage)) next();
    });
    // More's Reset: the level, voice and strictness back to their defaults, and the best scores forgotten.
    ui.onReset(() => {
        prefs.reset();
        voices.set(settings.range);
        $('[data-strict]').checked = settings.strict;
        mic?.stop();
        mic = null;
        newRound();
    });
    $('[data-again]').addEventListener('click', again);
    $('[data-strict]').addEventListener('change', (event) => {
        settings.strict = event.target.checked;
        prefs.save();
    });
    onPicked($('[data-picker]'), (id) => mic?.use(id).catch((error) => console.warn('That mic could not be opened', error)));
    render();
    return {
        play: next,
        again,
        next,
        stop() {
            session++;
            sounding?.stop();
            mic?.stop();
            mic = null;
            level.destroy();
            stage = 'idle';
        },
        element: card,
    };
}
