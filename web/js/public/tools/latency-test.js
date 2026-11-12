// The latency test for /tools (docs/tools-plan.md, batch 2; docs/tools/competitors-pipe-forks-drone-latency-ear.md
// section 4): how late this device's sound reaches you, for people who sing or play along. Two ways:
// - Tap along (the first screen): a steady click on the audio clock (the engine's own click sound,
//   js/dsp.js), tap the pad or the space bar on each one; the median of 8 taps, uneven ones dropped,
//   with a plus-or-minus, and more taps asked for while it's wide.
// - Let the mic hear it (one tap away, more exact): the app's own timing check (js/timing-check.js,
//   the same measurement js/audio.js uses), with an earbud held to the mic.
// On the tools' one layout (js/public/tool-layout.js): where you listen as the modes, the delay as the hero with
// its verdict pill, the zones as the readout, the panel (how it works, the tap pad, what to do), Start pinned and
// the mic check behind ⋯. The result: ms and its spread, a bar from 0 to 300 ms in zones, a verdict written for singers, what
// to do with it, and the trap of a Bluetooth headset's own mic. Results for this visit sit side by
// side ("Test another pair"), and copy as a line. No framework; the mic opens only on its own tap and
// nothing is recorded, kept or sent. The words come from the page, set as text, never markup. Hooks for the
// arcade skin: data-ui on the route, start, tap-pad, tap-count, mic-start, result, delay, delay-bar, verdict,
// what-now, headset-warning and this-visit.
//
//   import { mountLatencyTest } from '/js/public/tools/latency-test.js';
//   const test = mountLatencyTest(el, { words, onResult: ({ ms, spreadMs, route, method, verdict }) => ... });
//
// `test.stop()` stops the clicks and lets the mic go. `open` stands in for the engine (tests pass one
// that hands back a known delay); `tap(timeStamp)` and `clickTimes()` let a test tap in time.

import { Engine } from '../../../engine/engine.js';
import { outputDelay } from '../../../engine/clock.js';
import { click, median } from '../../dsp.js';
import { CLAP_CLICKS, CLAP_GAP_SECONDS, measureClaps, measureLatency } from '../../timing-check.js';
import { reasonOf } from '../live-mic.js';
import { segmented, sheetRow, toolLayout } from '../tool-layout.js';
import { toolPrefs } from '../tool-prefs.js';
import { sound } from '../tones.js';
import { audio } from '../voices.js';

/** The words a page passes (each a translated string; :ms, :n, :total, :route and :spread are filled in here). */
export const WORDS = ['routeQuestion', 'howToLabel', 'idleHint', 'wired', 'bluetooth', 'speakers', 'wiredHint', 'bluetoothHint', 'speakersHint', 'wiredRoute', 'bluetoothRoute', 'speakersRoute', 'howTo',
    'start', 'stop', 'tapPad', 'tapHint', 'tapLabel', 'tapCount', 'tapUneven', 'tapLost', 'exact', 'micIntro', 'micStart', 'measuring',
    'ms', 'plusMinus', 'tight', 'fine', 'late', 'veryLate', 'zoneTight', 'zoneFine', 'zoneLate', 'zoneVeryLate',
    'whatNow', 'do1', 'do2', 'do3', 'headsetMic', 'browserSays', 'again', 'thisVisit', 'byTap', 'byMic',
    'roundTrip', 'tapEarly', 'copy', 'copied', 'copyText', 'copyTextMic', 'kept', 'failed', 'failedHint', 'denied', 'noMic', 'settings', 'settingsTitle', 'reset', 'done',
    'clapExact', 'clapIntro', 'clapTip', 'clapStart', 'clapping', 'clapUneven', 'clapFew', 'clapNote', 'byClap'];

export const ROUTES = ['wired', 'bluetooth', 'speakers'];
const RUNS = 3; // the mic check's runs
const TAPS = 8; // taps for a number
const MOST_TAPS = 16; // asked for while the spread is wide
const STEADY_MS = 25; // a spread at or under this is a number to trust
const BEAT = 0.6; // seconds between clicks (100 a minute)
const CLICKS = 30; // scheduled on the clock at the start: enough for MOST_TAPS with misses
const BAR_MS = 300;

/**
 * How a delay feels to someone singing along: 'tight' (under 10 ms: most people won't notice it, though
 * hearing yourself live some singers still will; in-ear monitors aim for 5 to 10), 'fine' (under 40, fine
 * for recording, noticeably late to hear yourself), 'late' (under 200, Bluetooth's usual, fine for
 * recording in an app that lines takes up, too slow to hear yourself live) or 'veryLate'.
 */
export function verdictOf(ms) {
    if (ms < 10) return 'tight';
    if (ms < 40) return 'fine';
    if (ms < 200) return 'late';
    return 'veryLate';
}

/**
 * Taps (each one's ms after its click) to a number: the far-off ones dropped (beyond 3 robust spreads
 * or 40 ms of the middle, whichever is wider), then the median, and the spread as a robust standard
 * deviation (1.4826 x the median absolute deviation). `steady` when there are 6 kept and it's tight.
 */
export function summarizeTaps(offsets) {
    if (!offsets.length) return null;
    const middle = median(offsets);
    const mad = median(offsets.map((x) => Math.abs(x - middle)));
    const kept = offsets.filter((x) => Math.abs(x - middle) <= Math.max(3 * 1.4826 * mad, 40));
    const ms = median(kept);
    const spreadMs = 1.4826 * median(kept.map((x) => Math.abs(x - ms)));
    return { ms, spreadMs, kept: kept.length, steady: kept.length >= 6 && spreadMs <= STEADY_MS };
}

/**
 * A tap (performance ms) against the clicks (performance ms they were sent to the speakers): its ms
 * after the nearest click it could answer (from 150 ms early to 450 ms late, near `guess`), or null.
 */
export function tapOffset(at, clickTimes, guess = 100) {
    let best = null;
    for (const time of clickTimes) {
        const offset = at - time;
        if (offset < -150 || offset > 450) continue;
        if (best === null || Math.abs(offset - guess) < Math.abs(best - guess)) best = offset;
    }
    return best;
}

/** The engine and its mic, opened from the tap: what the mic check needs, and close(). */
export async function openEngine({ deviceId = '', loopback = false } = {}) {
    const engine = new Engine();
    await engine.unlock();
    await engine.openMic(deviceId);
    if (loopback) engine.setLoopback(true); // tests and demos without a speaker and a mic in the room
    return {
        sampleRate: engine.sampleRate,
        estimateMs: (engine.estimatedLatencyFrames() * 1000) / engine.sampleRate,
        measure: (signal, captureLength) => engine.measure(signal, { captureLength }),
        close() {
            engine.closeMic();
            engine.ctx?.close().catch(() => {});
        },
    };
}

/**
 * Clap along on `device` (openEngine's), for Bluetooth (earbuds stop playing out of the ear, so they can't be held to
 * the mic; docs/wireless-timing-plan.md): `{ ms, spreadMs, claps }`, or `{ ms: null, reason }` ('few' or 'uneven').
 */
export async function measureByClaps(device) {
    const found = await measureClaps({ measure: device.measure, sampleRate: device.sampleRate });
    return found.frames === null ? { ms: null, reason: found.reason } : found;
}

/** The app's timing check on `device` (openEngine's): `{ ms, spreadMs, runs }`, or null when nothing was heard. */
export async function measureDelay(device, { runs = RUNS, onProgress = () => {} } = {}) {
    return measureLatency({ measure: device.measure, sampleRate: device.sampleRate, runs, onProgress });
}

const fill = (text, values) => String(text ?? '').replace(/:(\w+)/g, (match, k) => (k in values ? String(values[k]) : match));
const number = (value) => Math.round(value).toLocaleString(document.documentElement.lang || undefined);
const TONES = { tight: 'good', fine: 'good', late: 'warn', veryLate: 'warn' };

// The hero: the delay, big, with its spread small beside it ("182 ms ± 9"); a quiet dash before.
const HERO = `<p class="flex items-baseline justify-center gap-2" translate="no"><span class="text-[clamp(4rem,22vw,6.5rem)] font-light leading-none tracking-tight tabular-nums text-muted [@media(max-height:640px)]:text-[4rem]" data-ui="delay" data-ms>–</span><span class="text-lg font-bold tabular-nums text-muted" data-unit></span></p>
<p class="sr-only" aria-live="polite" data-spoken></p>`;

// The readout: the bar from 0 to 300 ms in zones, each named (never told by colour alone).
const READOUT = `
<div class="grid gap-2" aria-hidden="true" data-ui="delay-bar" data-bar hidden>
  <div class="relative h-3 overflow-hidden rounded-full bg-raised">
    <div class="absolute inset-y-0 left-0 bg-good/50" style="width:8.33%"></div>
    <div class="absolute inset-y-0 bg-accent/40" style="left:8.33%;width:18.33%"></div>
    <div class="absolute inset-y-0 bg-warn/40" style="left:26.67%;width:40%"></div>
    <div class="absolute inset-y-0 bg-bad/40" style="left:66.67%;right:0"></div>
  </div>
  <div class="relative h-0"><span class="absolute -top-4 h-5 w-1 -translate-x-1/2 rounded-full bg-cream" data-marker></span></div>
  <div class="flex flex-wrap justify-center gap-x-4 gap-y-1 text-xs text-muted"><span class="inline-flex items-center gap-1.5"><i class="size-2.5 rounded-sm bg-good/50"></i><span data-zone-tight></span></span><span class="inline-flex items-center gap-1.5"><i class="size-2.5 rounded-sm bg-accent/40"></i><span data-zone-fine></span></span><span class="inline-flex items-center gap-1.5"><i class="size-2.5 rounded-sm bg-warn/40"></i><span data-zone-late></span></span><span class="inline-flex items-center gap-1.5"><i class="size-2.5 rounded-sm bg-bad/40"></i><span data-zone-very-late></span></span></div>
</div>`;

// The panel: how it works before; the pad while tapping; what to do with the number after.
const PANEL = `
<div class="flex h-full flex-col gap-3 p-4 text-sm text-cream" data-idle>
  <p data-how-to></p>
  <p class="text-muted" data-route-hint></p>
  <p class="rounded-2xl border border-warn/60 p-3" data-ui="headset-warning" data-headset hidden></p>
  <div class="mt-auto space-y-1" data-problem hidden><p class="font-semibold" data-problem-words></p><p class="text-muted" data-problem-hint></p></div>
</div>
<button type="button" class="absolute inset-0 flex touch-manipulation select-none flex-col items-center justify-center gap-1 text-cream active:bg-panel" data-ui="tap-pad" data-pad hidden>
  <span class="text-4xl font-extrabold" data-pad-word></span>
  <span class="px-4 text-sm text-muted" data-pad-hint></span>
</button>
<div class="flex h-full flex-col gap-3 overflow-y-auto p-4 text-sm text-cream" data-ui="what-now" data-after hidden>
  <p class="text-muted" data-ui="latency-method" data-method-note></p>
  <ul class="list-disc space-y-1.5 ps-5" data-what-now-list></ul>
  <p class="rounded-2xl border border-warn/60 p-3" data-headset-after hidden></p>
  <div class="space-y-1" data-ui="this-visit" data-visit hidden><p class="font-semibold" data-visit-title></p><ul class="space-y-1 tabular-nums text-muted" data-visit-list></ul></div>
</div>`;

// The sheet: the more exact way (the mic hears the clicks), copy, the browser's own figure, the promise.
/** More: Hillel's settings sheet (tool-layout.js sheetRow), a hairline row each: the mic's more exact check, the browser's own figure, copy, the promise. */
const SHEET_ROWS = [
    `<section class="grid gap-2" data-mic>
  <p class="font-semibold text-cream" data-exact></p>
  <p class="text-sm text-muted" data-mic-intro></p>
  <p class="text-sm text-muted" data-clap-tip hidden></p>
  <button type="button" class="btn-primary justify-self-start" data-ui="mic-start" data-mic-start></button>
  <div class="space-y-2" data-measuring hidden>
    <p class="text-sm text-cream" data-measuring-words></p>
    <div class="h-2 overflow-hidden rounded-full bg-raised" role="progressbar" aria-valuemin="0" aria-valuemax="100" data-progress><div class="h-full rounded-full bg-accent transition-[width] motion-reduce:transition-none" style="width:0%" data-progress-fill></div></div>
  </div>
</section>`,
    `<p class="text-sm text-muted" data-browser></p>`,
    `<button type="button" class="btn-secondary btn-sm justify-self-start" data-copy hidden></button>`,
    `<p class="text-xs text-muted" data-kept></p>`,
];

function buildSheet(ui) {
    for (const html of SHEET_ROWS) sheetRow(ui.sheet).body.innerHTML = html;
}

/** The settings, remembered on this device (js/public/tool-prefs.js): where you listen. */
const DEFAULTS = { route: 'wired' };

export function mountLatencyTest(root, { words = {}, keySlot = null, route = null, onResult = null, onError = null, deviceId = '', open = openEngine } = {}) {
    const ui = toolLayout(root, { keySlot, more: words.settings, done: words.done, title: words.settingsTitle ?? '', reset: words.reset ?? '' });
    ui.hero.innerHTML = HERO;
    ui.readout.innerHTML = READOUT;
    ui.panel.innerHTML = PANEL;
    buildSheet(ui);
    const card = ui.element;
    card.dataset.latency = '';
    const $ = (selector) => root.querySelector(selector);
    const text = (selector, value) => ($(selector).textContent = value ?? '');
    const say = (value) => text('[data-spoken]', value);
    const prefs = toolPrefs('audio-latency-test', { defaults: DEFAULTS, valid: { route: (v) => ROUTES.includes(v) } });
    if (route) prefs.visit({ route }); // a route the page passes wins for the visit
    let chosen = prefs.value.route;
    let device = null;
    let busy = false;
    let taps = null; // while tapping: { clicks: [ctx seconds], sources, offsets }
    const visit = []; // this visit's results, side by side
    let last = null;

    for (const [selector, key] of [['[data-exact]', 'exact'], ['[data-pad-word]', 'tapPad'], ['[data-pad-hint]', 'tapHint'], ['[data-how-to]', 'howTo'],
        ['[data-mic-intro]', 'micIntro'], ['[data-mic-start]', 'micStart'], ['[data-measuring-words]', 'measuring'], ['[data-zone-tight]', 'zoneTight'],
        ['[data-zone-fine]', 'zoneFine'], ['[data-zone-late]', 'zoneLate'], ['[data-zone-very-late]', 'zoneVeryLate'], ['[data-copy]', 'copy'],
        ['[data-headset]', 'headsetMic'], ['[data-headset-after]', 'headsetMic'], ['[data-visit-title]', 'thisVisit'], ['[data-kept]', 'kept']]) text(selector, words[key]);
    $('[data-what-now-list]').replaceChildren(...['do1', 'do2', 'do3'].filter((k) => words[k]).map((k) => Object.assign(document.createElement('li'), { textContent: words[k] })));
    // Where you listen: the modes.
    const routes = segmented(ui.modes, ROUTES.map((key) => ({ key, label: words[key] ?? key })), { current: chosen, label: words.routeQuestion, pick: (key) => {
        chosen = key;
        prefs.value.route = key;
        prefs.save();
        routes.set(key);
        routed();
    } });
    // More's Reset: back to wired.
    ui.onReset(() => {
        prefs.reset();
        chosen = prefs.value.route;
        routes.set(chosen);
        routed();
    });
    for (const b of routes.element.children) b.dataset.route = b.dataset.segment;
    routes.element.dataset.ui = 'route';

    function routed() {
        text('[data-route-hint]', words[`${chosen}Hint`]);
        // Bluetooth's more exact check is clap along (docs/wireless-timing-plan.md): its words in More.
        const claps = chosen === 'bluetooth';
        text('[data-exact]', claps ? words.clapExact : words.exact);
        text('[data-mic-intro]', claps ? words.clapIntro : words.micIntro);
        text('[data-mic-start]', claps ? words.clapStart : words.micStart);
        text('[data-measuring-words]', claps ? words.clapping : words.measuring);
        text('[data-clap-tip]', words.clapTip);
        $('[data-clap-tip]').hidden = !claps;
        // A Bluetooth headset's own mic switches it to call quality and another delay: sing into the phone or laptop.
        $('[data-headset]').hidden = chosen !== 'bluetooth';
        $('[data-headset-after]').hidden = chosen !== 'bluetooth' || last?.route !== 'bluetooth';
    }

    /** Which part shows: 'idle' (how it works), 'tapping' (the pad), 'done' (what to do), 'failed'. */
    function show(phase) {
        card.dataset.phase = phase;
        $('[data-idle]').hidden = !['idle', 'failed'].includes(phase);
        $('[data-pad]').hidden = phase !== 'tapping';
        $('[data-after]').hidden = phase !== 'done';
        $('[data-problem]').hidden = phase !== 'failed';
        $('[data-bar]').hidden = phase !== 'done';
        for (const b of routes.element.children) b.disabled = phase === 'tapping' || busy;
        ui.setPrimary({ label: phase === 'tapping' ? words.stop : phase === 'done' ? words.again : words.start, active: phase === 'tapping' });
        if (phase === 'idle' || phase === 'failed') ui.setLabel(words.howToLabel, '');
    }

    function problem(title, hint) {
        text('[data-problem-words]', title);
        text('[data-problem-hint]', hint);
        ui.setStatus({ tone: 'warn', text: title });
        say(title ?? '');
        show('failed');
    }

    function result({ ms, spreadMs }, method, browserMs = null) {
        const verdict = verdictOf(ms);
        last = { ms, spreadMs, method, route: chosen, verdict };
        text('[data-ms]', number(ms));
        $('[data-ms]').className = `text-[clamp(4rem,22vw,6.5rem)] font-black leading-none tracking-tight tabular-nums [@media(max-height:640px)]:text-[4rem] ${TONES[verdict] === 'good' ? 'text-good' : 'text-warn'}`;
        text('[data-unit]', `${fill(words.ms, { ms: '' }).trim()} ${fill(words.plusMinus, { ms: number(Math.max(1, spreadMs)) })}`);
        $('[data-marker]').style.left = `${Math.min(100, (100 * ms) / BAR_MS)}%`;
        const zone = { tight: 'zoneTight', fine: 'zoneFine', late: 'zoneLate', veryLate: 'zoneVeryLate' }[verdict];
        ui.setStatus({ tone: TONES[verdict], text: words[zone], hint: words[verdict] });
        text('[data-browser]', Number.isFinite(browserMs) && browserMs > 0 ? fill(words.browserSays, { ms: number(browserMs) }) : '');
        // What the number is: the mic's is the round trip (out to the :route, the route's own phrase mid-sentence, such as
        // "wired headphones", never the button's label lowercased; courses, 2026-11-11), and back in through the mic, what the
        // app lines takes up by; never halved, as Bluetooth's delay is nearly all on the way out); taps run a little early.
        text('[data-method-note]', method === 'clap' ? words.clapNote : method === 'mic' ? fill(words.roundTrip, { route: words[`${chosen}Route`] ?? chosen }) : words.tapEarly);
        card.dataset.verdict = verdict;
        visit.push(last);
        const items = visit.map((r) => Object.assign(document.createElement('li'), { textContent: `${words[r.route] ?? r.route}: ${fill(words.ms, { ms: number(r.ms) })} ${fill(words.plusMinus, { ms: number(Math.max(1, r.spreadMs)) })}, ${r.method === 'tap' ? words.byTap ?? '' : r.method === 'clap' ? words.byClap ?? '' : words.byMic ?? ''}` }));
        $('[data-visit-list]').replaceChildren(...items);
        $('[data-visit]').hidden = visit.length < 2;
        $('[data-copy]').hidden = false;
        text('[data-copy]', words.copy);
        ui.setLabel(words.whatNow, '');
        routed();
        show('done');
        say(`${number(ms)} ${$('[data-unit]').textContent}. ${words[verdict] ?? ''}`);
        onResult?.(last);
    }

    // ---- Tap along -----------------------------------------------------------------------------

    const browserMs = (c) => outputDelay(c) * 1000;

    /**
     * The scheduled clicks' times on the performance clock (what a tap's timeStamp is on), as the
     * moments they're sent out: from the output timestamp's pair where the browser gives one (steadier
     * than currentTime, which moves in whole render buffers), less the delay it already reports, so a
     * tap measures all of it; else from currentTime.
     */
    function clickTimes() {
        if (!taps) return [];
        const c = audio();
        const stamp = c.getOutputTimestamp?.();
        if (stamp?.performanceTime > 0) {
            const known = outputDelay(c) * 1000;
            return taps.clicks.map((t) => stamp.performanceTime + (t - stamp.contextTime) * 1000 - known);
        }
        const now = performance.now();
        return taps.clicks.map((t) => now - (c.currentTime - t) * 1000);
    }

    function startTapping() {
        if (busy) return;
        stopTapping();
        const c = audio(); // made and resumed from this tap
        const samples = click(c.sampleRate);
        const first = c.currentTime + 0.5;
        const clicks = Array.from({ length: CLICKS }, (_, i) => first + i * BEAT);
        const sources = clicks.map((at) => sound(samples, { at })); // the free tools' shared player (tones.js)
        // The last click's end, on the audio clock: out of clicks before enough steady taps.
        sources.at(-1).ended.then(() => taps?.sources === sources && finishTapping());
        taps = { clicks, sources, offsets: [] };
        ui.setStatus({ hint: words.tapHint });
        ui.setLabel(words.tapLabel, fill(words.tapCount, { n: 0, total: TAPS }));
        show('tapping');
        $('[data-pad]').focus({ preventScroll: true });
        say(words.tapHint ?? '');
    }

    function tap(at) {
        if (!taps) return;
        const summary = summarizeTaps(taps.offsets);
        const offset = tapOffset(at, clickTimes(), summary?.ms ?? 100);
        if (offset === null) return;
        taps.offsets.push(offset);
        const now = summarizeTaps(taps.offsets);
        const n = taps.offsets.length;
        ui.setLabel(words.tapLabel, n < TAPS || now.steady ? fill(words.tapCount, { n, total: TAPS }) : words.tapUneven);
        if ((n >= TAPS && now.steady) || n >= MOST_TAPS) finishTapping();
    }

    /** Enough taps (or the clicks ran out): a number from the kept taps, or a plain "try again" under 4. */
    function finishTapping() {
        if (!taps) return;
        const summary = summarizeTaps(taps.offsets);
        stopTapping();
        if (!summary || summary.kept < 4) return problem(words.tapLost);
        result(summary, 'tap', browserMs(audio()));
    }

    function stopTapping() {
        if (!taps) return;
        const { sources } = taps;
        taps = null; // first, so the stopped clicks' `ended` finds nothing to finish
        for (const source of sources) source.stop();
    }

    const onKey = (event) => {
        if (card.dataset.phase !== 'tapping' || event.repeat || !['Space', 'Enter'].includes(event.code)) return;
        event.preventDefault();
        tap(event.timeStamp);
    };
    document.addEventListener('keydown', onKey);
    $('[data-pad]').addEventListener('pointerdown', (event) => tap(event.timeStamp));
    $('[data-pad]').addEventListener('keydown', (event) => event.code === 'Space' && event.preventDefault()); // the document's handler taps

    // ---- Let the mic hear it (in the sheet) ---------------------------------------------------

    function progress(share) {
        const percent = Math.round(share * 100);
        $('[data-progress-fill]').style.width = `${percent}%`;
        $('[data-progress]').setAttribute('aria-valuenow', String(percent));
    }

    async function measureByMic() {
        if (busy) return;
        busy = true;
        stopTapping();
        progress(0);
        $('[data-measuring]').hidden = false;
        $('[data-mic-start]').hidden = true;
        say(words.measuring ?? '');
        const claps = chosen === 'bluetooth';
        // Clap along has no runs to count: the bar follows its clicks (a picture, never a timer for sound).
        const started = performance.now();
        let frame = 0;
        const tick = () => {
            progress(Math.min(1, (performance.now() - started) / 1000 / ((CLAP_CLICKS + 1) * CLAP_GAP_SECONDS)));
            frame = requestAnimationFrame(tick);
        };
        if (claps) frame = requestAnimationFrame(tick);
        try {
            device ??= await open({ deviceId });
            if (claps) {
                const found = await measureByClaps(device);
                ui.openSheet(false);
                if (found.ms === null) {
                    problem(found.reason === 'uneven' ? words.clapUneven : words.clapFew, '');
                    return;
                }
                result(found, 'clap', device.estimateMs);
                return;
            }
            const found = await measureDelay(device, { onProgress: progress });
            ui.openSheet(false);
            if (!found) {
                problem(words.failed, words.failedHint);
                return;
            }
            result(found, 'mic', device.estimateMs);
        } catch (error) {
            const reason = error?.reason ?? reasonOf(error);
            ui.openSheet(false);
            problem(reason === 'denied' ? words.denied : words.noMic, reason === 'denied' ? '' : words.failedHint);
            onError?.(reason);
            letMicGo();
        } finally {
            cancelAnimationFrame(frame);
            busy = false;
            $('[data-measuring]').hidden = true;
            $('[data-mic-start]').hidden = false;
            for (const b of routes.element.children) b.disabled = false; // another pair can be picked now
        }
    }

    /** Lets the mic go (the next check asks again). */
    function letMicGo() {
        device?.close?.();
        device = null;
    }

    ui.onPrimary(() => (card.dataset.phase === 'tapping' ? finishTapping() : ['mic', 'clap'].includes(last?.method) && card.dataset.phase === 'done' ? measureByMic() : startTapping()));
    $('[data-mic-start]').addEventListener('click', measureByMic);
    $('[data-copy]').addEventListener('click', async () => {
        if (!last) return;
        const line = fill(last.method === 'tap' ? words.copyText : words.copyTextMic, { route: words[last.route] ?? last.route, ms: number(last.ms), spread: number(Math.max(1, last.spreadMs)) });
        try {
            await navigator.clipboard.writeText(line);
            text('[data-copy]', words.copied);
        } catch (error) {
            console.warn('The result was not copied', error);
        }
    });
    // Leaving the page (or hiding it) stops the clicks and lets the mic go.
    const hidden = () => {
        if (!document.hidden) return;
        if (taps) {
            stopTapping();
            show('idle');
        }
        if (!busy) letMicGo();
    };
    document.addEventListener('visibilitychange', hidden);
    ui.setStatus({ hint: words.idleHint });
    routed();
    show('idle');
    return {
        start: startTapping,
        measureByMic,
        tap,
        clickTimes,
        stop() {
            document.removeEventListener('visibilitychange', hidden);
            document.removeEventListener('keydown', onKey);
            stopTapping();
            letMicGo();
        },
        element: card,
    };
}
