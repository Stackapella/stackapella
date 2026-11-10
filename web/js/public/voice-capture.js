// The app's note capture (components/voice-capture.js) for the public site's demos (the Course and Beginners
// pages): the same card, classes and arcade hooks (data-ui="capture" and its parts), the same honest hold and
// zoom (js/capture-scale.js, js/listen.js), with no framework, so a public page can load it with no import map.
// The mic opens only when the visitor taps Start (js/public/listen.js: nothing recorded, kept or sent; the readings
// live in the page until it stops). The words come from the page (its Blade view translates them), never from here.
//
//   import { mountCapture } from '/js/public/voice-capture.js';
//   const capture = mountCapture(el, { words, onFound: (midi) => ... });   // words: see WORDS below
//
// `capture.stop()` lets the mic go. onError(reason) hears a refused mic ('denied', 'no-mic' or 'unsupported') so the
// page can show its own fallback. `open` stands in for the mic (tests pass one that only hands back onFrame).

import { easeSpan, scaleTicks, targetSpan, WIDE_SPAN, zoomOf } from '../capture-scale.js';
import { midiName } from '../dsp.js';
import { HOLD_SECONDS, hear, listener } from '../listen.js';
import { listen, reasonOf } from './live-mic.js';

/** The words a page passes (each a translated string; {seconds} and {note} are filled in here). */
export const WORDS = ['start', 'listening', 'listeningSub', 'hearing', 'hearingSub', 'holding', 'holdingSub', 'found', 'foundSub',
    'subListening', 'subHearing', 'subSteady', 'subFound', 'lower', 'higher', 'zoomWide', 'zoomCloser', 'zoomLocked', 'tryAnother', 'noMic', 'mic'];

const RING = 2 * Math.PI * 69;
const still = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
const fill = (text, values) => String(text ?? '').replace(/\{(\w+)\}/g, (_, k) => values[k] ?? '');

// The card: the app component's markup and classes, its words set as text afterwards (never as markup).
const CARD = `
<div class="relative overflow-hidden rounded-3xl border border-line bg-panel px-5 pb-4 pt-5 sm:px-6 text-center" data-ui="capture" data-phase="searching">
  <div class="flex flex-col items-center gap-4">
    <div class="relative size-[9.5rem]" aria-hidden="true" data-ui="capture-hold">
      <svg viewBox="0 0 152 152" class="absolute inset-0 size-full -rotate-90">
        <circle cx="76" cy="76" r="69" fill="none" stroke="var(--color-raised)" stroke-width="8"></circle>
        <circle cx="76" cy="76" r="69" fill="none" stroke-width="8" stroke-linecap="round" data-ring></circle>
      </svg>
      <div class="absolute inset-[0.875rem] flex flex-col items-center justify-center gap-1 rounded-full bg-ink/60">
        <span class="text-[2.625rem] font-extrabold leading-none tabular-nums" translate="no" data-note data-ui="capture-note"></span>
        <span class="text-[0.8125rem] text-muted" data-ui="capture-note-sub"></span>
      </div>
    </div>
    <div class="flex min-h-11 flex-col gap-1" data-ui="capture-status">
      <p class="text-lg font-bold text-cream" data-status></p>
      <p class="text-sm text-muted" data-status-sub></p>
    </div>
    <p class="sr-only" aria-live="polite" data-spoken></p>
    <div class="w-full space-y-1.5">
      <div class="relative h-16 overflow-hidden" style="mask-image:linear-gradient(90deg,transparent,#000 18%,#000 82%,transparent);-webkit-mask-image:linear-gradient(90deg,transparent,#000 18%,#000 82%,transparent)" role="img" data-scale data-ui="capture-strip">
        <div class="absolute inset-x-0 top-5 h-px bg-line"></div>
        <div class="absolute top-0 h-10 -translate-x-1/2 rounded-md" style="left:50%" data-band></div>
        <div data-ticks></div>
        <div class="absolute top-0 h-10 w-0.5 -translate-x-1/2 rounded-full" style="left:50%" data-needle></div>
      </div>
      <div class="flex items-center justify-between text-xs text-muted" aria-hidden="true" data-ui="capture-zoom">
        <span data-lower></span>
        <span class="flex items-center gap-1" data-zoom-dots><span class="size-1.5 rounded-full"></span><span class="size-1.5 rounded-full"></span><span class="size-1.5 rounded-full"></span><span class="ms-1" data-zoom-word></span></span>
        <span data-higher></span>
      </div>
    </div>
    <div class="flex w-full items-center justify-center gap-4 border-t border-line pt-3" data-ui="capture-mic">
      <span class="min-w-0 flex-1 truncate text-start text-[0.8125rem] text-muted" data-mic-name></span>
      <button type="button" class="btn-primary btn-sm" data-start></button>
      <button type="button" class="link-quiet min-h-11 text-[0.8125rem] font-semibold" data-again hidden></button>
    </div>
  </div>
</div>`;

export function mountCapture(root, { words = {}, color = 'var(--color-part-1)', holdSeconds = HOLD_SECONDS, onFound = null, onError = null, deviceId = '', open = listen } = {}) {
    root.innerHTML = CARD;
    const $ = (sel) => root.querySelector(sel);
    const card = $('[data-ui=capture]');
    card.style.setProperty('--capture-live', color);
    card.style.setProperty('--capture-done', 'var(--color-accent)');
    $('[data-lower]').textContent = words.lower ?? '';
    $('[data-higher]').textContent = words.higher ?? '';
    $('[data-mic-name]').textContent = words.mic ?? '';
    $('[data-start]').textContent = words.start ?? '';
    $('[data-again]').textContent = words.tryAnother ?? '';

    let state = listener();
    let phase = 'idle'; // idle, listening, hearing, holding, found
    let shown = null;
    let centre = 57;
    let span = WIDE_SPAN;
    let lastAt = null;
    let found = null;
    let session = null;
    let spokenTimer = 0;
    let spokenTitle = '';

    const accent = () => (phase === 'found' ? 'var(--capture-done)' : 'var(--capture-live)');
    function render() {
        const hold = phase === 'found' ? 1 : Math.min(1, state.held / holdSeconds);
        card.dataset.phase = phase === 'found' ? 'done' : phase === 'holding' ? 'holding' : 'searching';
        card.style.setProperty('--hold', String(hold));
        const ring = $('[data-ring]');
        ring.setAttribute('stroke', accent());
        ring.setAttribute('stroke-dasharray', String(RING));
        ring.setAttribute('stroke-dashoffset', String(RING * (1 - hold)));
        ring.setAttribute('class', still() ? '' : 'transition-[stroke-dashoffset] duration-100 ease-linear');
        // Only the note found is named: never the voice's note while it settles, whose letter would flash
        // (Hillel, 2026-11-02: "dont show the specific note ... until it finished"). The ring and the words
        // say it's hearing you meanwhile.
        const note = found !== null ? Math.round(found) : null;
        const noteEl = $('[data-note]');
        noteEl.textContent = note === null ? '·' : midiName(note);
        noteEl.style.color = phase === 'found' || phase === 'holding' ? accent() : 'var(--color-cream)';
        noteEl.style.opacity = note !== null || phase === 'hearing' || phase === 'holding' ? '1' : '0.35';
        $('[data-ui=capture-note-sub]').textContent = { idle: '', found: words.subFound, holding: words.subSteady, hearing: words.subHearing }[phase] ?? words.subListening ?? '';
        const left = Math.max(1, Math.ceil((1 - hold) * holdSeconds));
        const title = { idle: words.listening, listening: words.listening, hearing: words.hearing, holding: words.holding, found: words.found }[phase] ?? '';
        $('[data-status]').textContent = title;
        $('[data-status-sub]').textContent = phase === 'found' ? fill(words.foundSub, { note: midiName(Math.round(found)) })
            : phase === 'holding' ? fill(words.holdingSub, { seconds: left }) : phase === 'hearing' ? words.hearingSub ?? '' : words.listeningSub ?? '';
        if (title !== spokenTitle) { // words for a screen reader (never audio), once the state has settled
            spokenTitle = title;
            clearTimeout(spokenTimer);
            spokenTimer = setTimeout(() => ($('[data-spoken]').textContent = title), 800);
        }
        // The scale round the fixed needle.
        const zoom = phase === 'found' ? 2 : zoomOf(span);
        const strip = $('[data-scale]');
        strip.dataset.zoom = String(zoom);
        strip.setAttribute('aria-label', note === null ? '' : midiName(note));
        const named = phase === 'found'; // the scale's names too: sliding past the needle, they'd flash
        $('[data-band]').style.cssText = `left:50%;width:${Math.min(30, (0.7 / span) * 100)}%;background:${accent()};opacity:0.12`;
        $('[data-needle]').style.background = accent();
        $('[data-needle]').style.opacity = shown === null ? '0.35' : '1';
        const ticks = $('[data-ticks]');
        ticks.replaceChildren(...scaleTicks(centre, span).map((tick) => {
            const el = document.createElement('div');
            el.className = 'absolute top-0 flex -translate-x-1/2 flex-col items-center gap-1.5';
            el.style.left = `${tick.at * 100}%`;
            el.dataset.tick = '';
            if (tick.active) el.dataset.active = '';
            const line = document.createElement('span');
            line.className = `w-0.5 rounded-full ${tick.active ? 'mt-1.5 h-7' : tick.natural ? 'mt-3 h-[1.125rem] bg-muted/70' : 'mt-4 h-2.5 bg-line'}`;
            if (tick.active) line.style.background = accent();
            el.append(line);
            if (tick.label && named) {
                const label = document.createElement('span');
                label.className = `whitespace-nowrap font-semibold tabular-nums ${tick.active ? 'text-[0.9375rem]' : 'text-[0.6875rem] text-muted'}`;
                label.setAttribute('translate', 'no');
                if (tick.active) label.style.color = accent();
                label.textContent = tick.label;
                el.append(label);
            }
            return el;
        }));
        const dots = root.querySelectorAll('[data-zoom-dots] > span.rounded-full');
        dots.forEach((dot, i) => {
            dot.style.background = i <= zoom ? accent() : 'var(--color-line)';
        });
        $('[data-zoom-word]').textContent = [words.zoomWide, words.zoomCloser, words.zoomLocked][zoom] ?? '';
        $('[data-start]').hidden = phase !== 'idle';
        $('[data-again]').hidden = phase === 'idle';
    }

    /** One pitch reading ({ hz, at } in seconds): the hold, the scale and the note, as the app's capture has them. */
    function feed({ hz, at }) {
        if (phase === 'found' || phase === 'idle') return;
        const dt = lastAt === null ? 0.01 : Math.min(0.1, Math.max(0, at - lastAt));
        lastAt = at;
        state = hear(state, { hz, loudness: hz > 0 ? -20 : -80 }, dt);
        shown = hz > 0 ? 69 + 12 * Math.log2(hz / 440) : null;
        if (shown !== null) centre = shown;
        span = easeSpan(span, targetSpan(state.held, holdSeconds), dt, { still: still() });
        phase = shown === null ? 'listening' : state.held >= 0.5 ? 'holding' : 'hearing';
        if (state.held >= holdSeconds && state.note !== null) {
            found = state.note;
            phase = 'found';
            stop();
            onFound?.(found);
        }
        render();
    }

    async function start() {
        state = listener();
        found = null;
        shown = null;
        span = WIDE_SPAN;
        lastAt = null;
        phase = 'listening';
        render();
        try {
            session = await open({ deviceId, onFrame: (frame) => feed({ hz: frame.hz, at: frame.at }) });
        } catch (error) {
            phase = 'idle';
            render();
            $('[data-status]').textContent = words.noMic ?? '';
            onError?.(error?.reason ?? reasonOf(error));
        }
    }

    function stop() {
        session?.stop?.();
        session = null;
    }

    $('[data-start]').addEventListener('click', start);
    $('[data-again]').addEventListener('click', () => {
        stop();
        start();
    });
    render();
    return { start, stop, element: card };
}
