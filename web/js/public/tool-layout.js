// The free tools' one layout (docs/design-rules.md, "Free tools share one layout"; Hillel's tuner v3,
// shots-review/designs/hillel/tuner-v3: "apply these style improvements to all tools so they have a
// similar look & feel"). No outer card: the tool fills a centred column under the page's brand row,
// whose right side holds the tool's one key control (the page's [data-tool-key] slot) when it has one
// people change often (a default that rarely changes, like A4 = 440, lives in More), then, after some
// breathing room under the row (Hillel: "a bit more vertical space at the top"):
// - the modes, a full-width segmented control (Voice | Instrument);
// - the hero readout, big and centred, with one status pill under it ("● In tune · Hold it there");
// - a readout row (the tuner's meter and cents) when the tool has one;
// - a small uppercase label with its live stat at the right ("LAST 8 SECONDS ... 27% in tune");
// - one main panel that grows to fill the height that's left (a trace, beat lights, a keyboard);
// On a wide screen (768 px and up) the reading, its readout and the bar sit together in one main card,
// the primary action right under what it controls, and the panel follows below at a modest height
// (Hillel's desktop tuner); on a phone the card dissolves and the bar is pinned at the foot.
// - the bottom bar, pinned on a phone: a row of the tool's own controls when it has some (the metronome's time
//   and subdivisions, Hillel's metronome design), then the primary action, big and full width, with a
//   smaller round ⋯ More beside it that opens the settings sheet.
//   Under them, empty until its moment, one quiet line the page may fill (page.js: the tool's free lesson, once it
//   has a result), so it's in view when it comes.
// A tool fills the slots and keeps its own logic; the look lives here once. Framework-free; tested in
// tests/public-tool-layout.test.js.
//
//   const ui = toolLayout(el, { keySlot, more: words.settings, done: words.done });
//   ui.modes, ui.hero, ui.readout, ui.extra, ui.panel, ui.controls, ui.sheet   // elements to fill
//   ui.setStatus({ tone: 'good' | 'warn' | 'muted', text, hint }); ui.setLabel(label, stat);
//   ui.setPrimary({ label, active }); ui.onPrimary(fn); ui.openSheet(true); ui.onReset(fn)
//   sheetRow(ui.sheet, { label, hint }) -> { side, body }      // a settings row (label, hint, control)

const SHELL = `
<div class="mx-auto flex min-h-[calc(100svh-var(--tool-top,3rem))] w-full max-w-[24.375rem] flex-col gap-5 pt-6 text-center md:min-h-0 md:max-w-[32.5rem] [@media(max-height:640px)]:gap-3 [@media(max-height:640px)]:pt-3" data-ui="tool">
  <div class="flex justify-end empty:hidden" data-own-key></div>
  <div class="empty:hidden" data-ui="tool-modes"></div>
  <div class="contents md:flex md:flex-col md:gap-5 md:rounded-3xl md:border md:border-line md:bg-panel md:p-6" data-ui="tool-main">
  <section class="grid justify-items-center gap-3.5 [@media(max-height:640px)]:gap-2" data-ui="tool-hero-block">
    <div class="w-full" data-ui="tool-hero"></div>
    <p class="flex min-h-9 max-w-full flex-wrap items-center justify-center gap-x-2 rounded-full px-4 py-1 text-base" data-ui="tool-status" hidden>
      <span class="size-2 shrink-0 rounded-full bg-current" aria-hidden="true"></span><b class="font-bold" data-status-text></b><span class="font-medium opacity-75" data-status-hint></span>
    </p>
  </section>
  <section class="grid gap-3 empty:hidden" data-ui="tool-readout"></section>
  <div class="sticky bottom-0 z-10 order-last mt-auto grid grid-cols-[1fr_3rem] items-center gap-x-3 gap-y-3 bg-ink pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-2 md:static md:mt-0 md:bg-transparent md:p-0" data-ui="tool-bar">
    <div class="col-span-2 empty:hidden" data-ui="tool-controls"></div>
    <button type="button" class="btn-primary min-h-16 min-w-0 gap-3 rounded-full text-[1.1875rem] [@media(max-height:640px)]:min-h-14" data-ui="tool-primary"><span class="size-3 shrink-0 rounded-full bg-current" aria-hidden="true" data-primary-icon></span><span data-primary-label></span></button>
    <button type="button" class="btn-secondary size-12 rounded-full p-0 text-lg font-extrabold tracking-[1px]" aria-haspopup="dialog" aria-expanded="false" data-ui="tool-more"><span aria-hidden="true">···</span></button>
    <p class="col-span-2 text-sm text-muted empty:hidden" aria-live="polite" data-ui="tool-next"></p>
  </div>
  </div>
  <section class="grid gap-3 empty:hidden" data-ui="tool-extra"></section>
  <section class="flex min-h-0 flex-1 flex-col gap-2.5 text-start md:flex-none" data-ui="tool-panel-block" hidden>
    <div class="flex items-baseline justify-between gap-2">
      <span class="whitespace-nowrap text-xs font-bold tracking-[0.12em] text-muted uppercase" data-ui="tool-label"></span>
      <span class="text-[0.8125rem] font-semibold tabular-nums text-muted" data-ui="tool-stat"></span>
    </div>
    <div class="relative min-h-[7.5rem] flex-1 overflow-hidden rounded-[20px] bg-raised md:h-40 md:flex-none [@media(max-height:640px)]:min-h-16" data-ui="tool-panel"></div>
  </section>
  <dialog class="fixed inset-x-0 bottom-0 top-auto m-0 mx-auto max-h-[88svh] w-full max-w-[30rem] overflow-y-auto rounded-t-[24px] bg-panel p-0 text-start text-cream shadow-2xl backdrop:bg-black/50 md:inset-0 md:m-auto md:h-fit md:max-h-[85svh] md:max-w-[40rem] md:rounded-[24px]" data-ui="tool-sheet">
    <div class="sticky top-0 z-10 border-b border-line bg-panel">
      <div class="mx-auto mt-2 h-1 w-9 rounded-full bg-line" aria-hidden="true"></div>
      <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2 px-5 py-2.5">
        <button type="button" class="min-h-11 justify-self-start px-1 text-[0.9375rem] font-medium text-muted hover:text-cream" data-sheet-reset hidden></button>
        <h2 class="text-base font-bold text-cream" data-sheet-title></h2>
        <button type="button" class="btn-primary min-h-11 justify-self-end rounded-full px-5" data-sheet-done></button>
      </div>
    </div>
    <div class="grid grid-cols-[minmax(0,1fr)] divide-y divide-line px-5 pb-4 [&>*]:min-w-0 [&>*]:py-5" data-sheet-body></div>
  </dialog>
</div>`;

const TONES = { good: 'bg-good/15 text-good', warn: 'bg-warn/15 text-warn', muted: 'bg-raised text-muted' };

/**
 * Lays a tool out in `root`. `keySlot` is the page's [data-tool-key] (the brand row's right side), or
 * null to draw the key control at the tool's own top. `more` and `done` name the ⋯ button and the
 * sheet's close button. Returns the slots and the setters.
 */
export function toolLayout(root, { keySlot = null, more = '', done = '', title = '', reset = '' } = {}) {
    root.innerHTML = SHELL;
    const $ = (sel) => root.querySelector(sel);
    // Embedded in another site's iframe (tools/embed.js marks <html data-embed>): compact, no full-screen
    // height, a short top gap, the bar in the flow rather than pinned to a frame that's only as tall as the tool.
    if (document.documentElement.hasAttribute('data-embed')) {
        const tool = $('[data-ui=tool]');
        tool.classList.remove('min-h-[calc(100svh-var(--tool-top,3rem))]', 'pt-6', 'md:max-w-[32.5rem]');
        tool.classList.add('pt-2', 'gap-4');
        $('[data-ui=tool-bar]').classList.remove('sticky', 'bottom-0');
        $('[data-ui=tool-main]').className = 'contents';
    }
    const sheet = $('[data-ui=tool-sheet]');
    const moreButton = $('[data-ui=tool-more]');
    moreButton.setAttribute('aria-label', more);
    $('[data-sheet-done]').textContent = done;
    $('[data-sheet-title]').textContent = title || more;
    $('[data-sheet-reset]').textContent = reset;
    sheet.setAttribute('aria-label', title || more);

    function openSheet(open) {
        if (open && !sheet.open) {
            sheet.showModal();
            moreButton.setAttribute('aria-expanded', 'true');
        } else if (!open && sheet.open) sheet.close();
    }
    sheet.addEventListener('close', () => {
        moreButton.setAttribute('aria-expanded', 'false');
        moreButton.focus({ preventScroll: true });
    });
    // A tap on the dimmed page around the sheet closes it, as Done does.
    sheet.addEventListener('click', (event) => {
        if (event.target === sheet) {
            const r = sheet.getBoundingClientRect();
            const inside = event.clientX >= r.left && event.clientX <= r.right && event.clientY >= r.top && event.clientY <= r.bottom;
            if (!inside) openSheet(false);
        }
    });
    moreButton.addEventListener('click', () => openSheet(!sheet.open));
    $('[data-sheet-done]').addEventListener('click', () => openSheet(false));

    return {
        element: $('[data-ui=tool]'),
        key: keySlot ?? $('[data-own-key]'),
        modes: $('[data-ui=tool-modes]'),
        hero: $('[data-ui=tool-hero]'),
        readout: $('[data-ui=tool-readout]'),
        controls: $('[data-ui=tool-controls]'),
        extra: $('[data-ui=tool-extra]'), // under the main card on a wide screen (an instrument's strings)
        panel: $('[data-ui=tool-panel]'),
        sheet: $('[data-sheet-body]'),
        primary: $('[data-ui=tool-primary]'),
        more: moreButton,
        /** The pill under the hero: a dot and the state in words, with the hint inline, in the state's colour. */
        setStatus({ tone = 'muted', text = '', hint = '' } = {}) {
            const pill = $('[data-ui=tool-status]');
            pill.hidden = !text && !hint;
            pill.dataset.tone = tone;
            // A hint alone (before the tool starts) is plain words, no dot and no tint.
            pill.className = `flex min-h-9 max-w-full flex-wrap items-center justify-center gap-x-2 rounded-full px-4 py-1 text-base ${text ? TONES[tone] ?? TONES.muted : 'text-muted'}`;
            pill.firstElementChild.hidden = !text;
            $('[data-status-text]').textContent = text;
            $('[data-status-hint]').textContent = text && hint ? `· ${hint}` : hint;
        },
        /** The panel's small uppercase label and its live stat (a node, for a coloured number, or text). */
        setLabel(label, stat = '') {
            $('[data-ui=tool-panel-block]').hidden = false;
            $('[data-ui=tool-label]').textContent = label;
            $('[data-ui=tool-stat]').replaceChildren(stat);
        },
        /**
         * The primary action: idle it's the primary colour with its icon (a round dot, or ▶ with `icon:
         * 'play'`); active (playing, listening) a quiet one with a square.
         */
        setPrimary({ label = '', active = false, icon = 'dot' } = {}) {
            const b = $('[data-ui=tool-primary]');
            b.className = `${active ? 'btn-secondary' : 'btn-primary'} min-h-16 min-w-0 gap-3 rounded-full text-[1.1875rem] [@media(max-height:640px)]:min-h-14`;
            b.setAttribute('aria-pressed', String(active));
            const shape = active ? 'size-3.5 rounded-[3px]' : icon === 'play' ? 'h-4 w-3.5 [clip-path:polygon(0_0,100%_50%,0_100%)]' : 'size-3 rounded-full';
            $('[data-primary-icon]').className = `shrink-0 bg-current ${shape}`;
            $('[data-primary-label]').textContent = label;
        },
        onPrimary(fn) {
            $('[data-ui=tool-primary]').addEventListener('click', fn);
        },
        openSheet,
        /** Reset in the sheet's header: shown once a tool says what it does (back to its defaults). */
        onReset(fn) {
            const b = $('[data-sheet-reset]');
            b.hidden = false;
            b.addEventListener('click', fn);
        },
    };
}

/**
 * One row of the settings sheet (Hillel's tuner settings): a label with a one-line hint at the left,
 * a value or a control at the right (`side`), and anything wider under them (`body`); the sheet draws a
 * hairline between rows. A row with no label is just its body (two settings side by side).
 */
export function sheetRow(container, { label = '', hint = '' } = {}) {
    const row = document.createElement('section');
    row.className = 'grid min-w-0 grid-cols-[minmax(0,1fr)] gap-3';
    row.dataset.ui = 'tool-setting';
    row.innerHTML = `<div class="flex items-start justify-between gap-3" data-row-head>
      <div class="min-w-0"><h3 class="text-[0.9375rem] font-semibold text-cream" data-row-label></h3><p class="text-[0.8125rem] text-muted empty:hidden" data-row-hint></p></div>
      <div class="min-w-0 text-end text-[0.8125rem] text-muted empty:hidden" data-row-side></div>
    </div>
    <div class="empty:hidden" data-row-body></div>`;
    row.querySelector('[data-row-label]').textContent = label;
    row.querySelector('[data-row-hint]').textContent = hint;
    row.querySelector('[data-row-head]').hidden = !label && !hint;
    container.append(row);
    return { element: row, side: row.querySelector('[data-row-side]'), body: row.querySelector('[data-row-body]') };
}

/**
 * The modes: a full-width segmented control, the picked one in the primary colour. `items` is
 * [{ key, label, name, caption }] (`name` read out when the label is a symbol; `caption` a small word under it); `pick(key)` hears a change. Returns { set(key) } to show another as picked.
 */
export function segmented(container, items, { current, pick, label = '' } = {}) {
    const group = document.createElement('div');
    // Each segment at least as wide as its words, so a label never wraps (Hillel: "Concert (C)" spilled
    // over B♭); the room left is shared out evenly, and a row too long for a narrow phone scrolls.
    group.className = 'grid gap-1 overflow-x-auto rounded-full bg-raised p-1 [scrollbar-width:none]';
    group.style.gridTemplateColumns = `repeat(${items.length}, minmax(max-content, 1fr))`;
    group.setAttribute('role', 'group');
    if (label) group.setAttribute('aria-label', label);
    group.append(...items.map(({ key, label: text, name = '', caption = '', short = '' }) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.dataset.segment = key;
        if (name) b.setAttribute('aria-label', name); // a symbol's words (♫: Eighths)
        else if (short) b.setAttribute('aria-label', text); // the full words, whichever shows
        b.className = `min-h-10 whitespace-nowrap text-[0.9375rem] font-bold text-muted transition-colors hover:text-cream aria-pressed:bg-[var(--primary)] aria-pressed:text-[var(--primary-ink)] ${caption ? 'grid place-items-center gap-px rounded-2xl px-2 py-1 leading-none' : 'rounded-full px-3'}`;
        if (caption) {
            // An icon with its word under it (Hillel's metronome: ♩ over Quarter).
            const icon = document.createElement('span');
            icon.className = 'text-[1.0625rem]';
            icon.textContent = text;
            const under = document.createElement('span');
            under.className = 'text-[0.625rem] font-semibold max-[359px]:hidden'; // a narrow phone keeps the icons (the words stay their names)
            under.textContent = caption;
            b.append(icon, under);
        } else if (short) {
            // A shorter word on a narrow phone (under 400 px), where the full row would not fit unwrapped
            // (the song part player's "With the others" in the game skin's wider type).
            const full = document.createElement('span');
            full.className = 'max-[399px]:hidden';
            full.textContent = text;
            const brief = document.createElement('span');
            brief.className = 'hidden max-[399px]:inline';
            brief.textContent = short;
            b.append(full, brief);
        } else b.textContent = text;
        b.addEventListener('click', () => key !== current && pick?.(key));
        return b;
    }));
    container.replaceChildren(group);
    const set = (key) => {
        current = key;
        for (const b of group.children) b.setAttribute('aria-pressed', String(b.dataset.segment === key));
    };
    set(current);
    return { set, element: group };
}

/**
 * The key control: a round stepper with a small label over its value ("A4 HZ / 440", "BEATS / 4/4").
 * `step(by)` hears − (-1) and + (+1). Returns { set(value, { atLow, atHigh }) }.
 */
export function stepper(container, { label = '', lower = '', higher = '', name = '', step, hook = 'tool-key' } = {}) {
    const box = document.createElement('div');
    box.className = 'flex h-11 shrink-0 items-center gap-0.5 rounded-full bg-raised p-1';
    box.setAttribute('role', 'group');
    if (name) box.setAttribute('aria-label', name);
    box.dataset.ui = hook;
    box.innerHTML = `<button type="button" class="grid size-9 place-items-center rounded-full text-lg text-muted hover:bg-panel hover:text-cream disabled:opacity-35" data-step="-1">−</button>
      <span class="grid min-w-11 justify-items-center leading-none" translate="no"><span class="text-[0.625rem] font-semibold tracking-[0.06em] text-muted uppercase" data-step-label></span><span class="mt-[3px] text-[0.9375rem] font-bold tabular-nums text-cream" data-step-value></span></span>
      <button type="button" class="grid size-9 place-items-center rounded-full text-lg text-muted hover:bg-panel hover:text-cream disabled:opacity-35" data-step="1">+</button>`;
    box.querySelector('[data-step-label]').textContent = label;
    // No caption when the row already says what it is (Hillel: say a thing once in a row).
    box.querySelector('[data-step-label]').hidden = !label;
    if (!label) box.querySelector('[data-step-value]').classList.remove('mt-[3px]');
    box.querySelector('[data-step="-1"]').setAttribute('aria-label', lower);
    box.querySelector('[data-step="1"]').setAttribute('aria-label', higher);
    for (const b of box.querySelectorAll('[data-step]')) b.addEventListener('click', () => step?.(Number(b.dataset.step)));
    container.append(box);
    return {
        element: box,
        set(value, { atLow = false, atHigh = false } = {}) {
            box.querySelector('[data-step-value]').textContent = String(value);
            box.querySelector('[data-step="-1"]').disabled = atLow;
            box.querySelector('[data-step="1"]').disabled = atHigh;
        },
        destroy() {
            box.remove();
        },
    };
}
