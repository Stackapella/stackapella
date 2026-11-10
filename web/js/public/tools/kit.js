// What the free tools share inside tool-layout.js's frame (js/public/tools/<tool>.js, docs/tools-plan.md): pressing and
// holding a button, a group of choices and a row of chips for More's settings sheet (its hairline rows, tool-layout's
// sheetRow), a switch, settings kept on the device (the shared tool-prefs.js store), the display following the sound,
// the device's media controls, keeping the screen on, and filling in words. UI only: every sound is the shared modules'
// (voices.js, tones.js), on the audio clock. Framework-free, safe on a public page (tool/public_imports_check.py).

import { heardTime } from '../../../engine/clock.js';
import { sheetRow } from '../tool-layout.js';
import { toolPrefs } from '../tool-prefs.js';
import { audio } from '../voices.js';

/**
 * A button that sounds while it's held: `down()` the moment a finger, the mouse or Space or Enter goes
 * down, `up(heldSeconds)` once when it lifts, slides off, is cancelled or loses focus. A screen reader's
 * activation (a click with no press before it) is a tap: down, then up at once.
 */
export function press(button, { down, up = () => {} }) {
    let since = null; // performance.now() at the press, while held
    let pressed = false; // a press started, so its click isn't a second tap
    const lift = () => {
        if (since === null) return;
        const held = (performance.now() - since) / 1000;
        since = null;
        up(held);
    };
    const start = () => {
        pressed = true;
        if (since !== null) return;
        since = performance.now();
        down();
    };
    button.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) return;
        // A touch is captured by its button; let it go, so sliding off the note lets go of it.
        if (button.hasPointerCapture?.(event.pointerId)) button.releasePointerCapture(event.pointerId);
        start();
    });
    for (const type of ['pointerup', 'pointerleave', 'pointercancel', 'blur']) button.addEventListener(type, lift);
    button.addEventListener('keydown', (event) => {
        if (event.key !== ' ' && event.key !== 'Enter') return;
        event.preventDefault();
        if (!event.repeat) start();
    });
    button.addEventListener('keyup', (event) => {
        if (event.key === ' ' || event.key === 'Enter') lift();
    });
    button.addEventListener('click', () => {
        if (pressed) {
            pressed = false;
            return;
        }
        since = performance.now();
        down();
        lift();
    });
    // A long press on a phone is a note, never the browser's menu.
    button.addEventListener('contextmenu', (event) => event.preventDefault());
}

/**
 * A group of buttons, one chosen (aria-pressed): `attr` is their data- attribute, `pick(value)` hears a
 * change. Returns `{ value, set(value) }`; the chosen value starts as the server rendered it.
 */
export function choice(root, attr, pick = () => {}) {
    const key = attr.replace(/-([a-z])/g, (_, c) => c.toUpperCase());
    const buttons = [...root.querySelectorAll(`[data-${attr}]`)];
    const state = {
        value: buttons.find((b) => b.getAttribute('aria-pressed') === 'true')?.dataset[key] ?? null,
        set(value) {
            state.value = value;
            buttons.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset[key] === value)));
        },
    };
    buttons.forEach((b) => b.addEventListener('click', () => {
        if (state.value === b.dataset[key]) return;
        state.set(b.dataset[key]);
        pick(b.dataset[key]);
    }));
    return state;
}

/**
 * A row of More's settings sheet holding chips: the label (and a one-line hint) at its head, then one filter-chip per
 * option (data-<attr>, aria-pressed; `attr` in camelCase, as dataset names it).
 */
export function chips(box, attr, label, options, hint = '') {
    box.replaceChildren();
    const { element, body } = sheetRow(box, { label, hint });
    const name = element.querySelector('[data-row-label]');
    name.id = `${attr}-l`;
    const row = document.createElement('div');
    row.className = 'flex flex-wrap gap-1.5';
    row.setAttribute('role', 'group');
    row.setAttribute('aria-labelledby', name.id);
    // An array of [value, words] pairs keeps its order (an object's number-like keys would be sorted).
    row.append(...(Array.isArray(options) ? options : Object.entries(options)).map(([value, text]) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'filter-chip';
        b.dataset[attr] = value;
        b.setAttribute('aria-pressed', 'false');
        b.textContent = text;
        return b;
    }));
    body.append(row);
}

/**
 * A chip row kept on one line (Hillel: never one chip left alone on a second line, the tuner's A4 presets): a narrow
 * phone scrolls it sideways, faded at the edge while there's more.
 */
export function oneRow(group) {
    group.className = 'flex gap-1.5 overflow-x-auto [scrollbar-width:none] data-[overflow]:[mask-image:linear-gradient(to_right,black_85%,transparent)]';
    for (const child of group.children) child.classList.add('shrink-0');
    const fade = () => group.toggleAttribute('data-overflow', group.scrollWidth > group.clientWidth + 1);
    new ResizeObserver(fade).observe(group);
    group.addEventListener('scroll', () => group.toggleAttribute('data-overflow', group.scrollLeft + group.clientWidth < group.scrollWidth - 1));
    return group;
}

/** A switch in More's sheet (the site's switch-track on a role="switch" button). */
export function switchRow(box, hook, label, hint = '') {
    // A settings row: the label (and hint) at the left, the switch at the right; tapping the words flips it too.
    box.replaceChildren();
    const { element, side } = sheetRow(box, { label, hint });
    side.innerHTML = '<button type="button" role="switch" aria-checked="false" class="s-tool-switch"><span class="switch-track" aria-hidden="true"><span class="switch-knob"></span></span></button>';
    side.classList.add('shrink-0'); // the switch keeps its width; the words wrap instead
    element.querySelector('[data-row-head]')?.classList.replace('items-start', 'items-center');
    const b = side.firstElementChild;
    b.dataset[hook] = '';
    b.setAttribute('aria-label', label);
    const words = element.querySelector('[data-row-label]')?.parentElement;
    if (words) {
        words.classList.add('cursor-pointer');
        words.addEventListener('click', () => b.click());
    }
    return b;
}

/** A switch (role="switch", the site's switch-track): `flip(on)` hears it. Returns `{ on }`. */
export function toggle(button, flip = () => {}) {
    const state = {
        on: button.getAttribute('aria-checked') === 'true',
        set(on) {
            state.on = Boolean(on);
            button.setAttribute('aria-checked', String(state.on));
        },
    };
    button.addEventListener('click', () => {
        state.set(!state.on);
        flip(state.on);
    });
    return state;
}

/**
 * A tool's settings, kept on this device through the shared store (tool-prefs.js: stackapella.tools.<tool>, cleaned
 * against `defaults`, a value from the link kept for the visit). `value` is the settings; `save(change)` merges a
 * change in and keeps it; `reset()` puts the defaults back and forgets (More's Reset). Works without storage.
 */
export function remember(tool, defaults, valid = {}) {
    const prefs = toolPrefs(tool, { defaults, valid });
    const value = prefs.value;
    const visiting = new Map(); // a key a variant page set for the visit -> the person's own value, kept until they change it
    return {
        value,
        save(change = {}) {
            Object.assign(value, change);
            for (const k of Object.keys(change)) visiting.delete(k); // changed (or picked again) by the person: theirs now
            // What the link set and the person hasn't touched is written as their own value, then shown again.
            const shown = Object.fromEntries([...visiting.keys()].map((k) => [k, value[k]]));
            Object.assign(value, Object.fromEntries(visiting));
            prefs.save();
            Object.assign(value, shown);
        },
        reset() {
            visiting.clear();
            prefs.reset();
        },
        /**
         * Values for this visit only (a variant page's preset: /tools/tanpura opens the drone in tanpura mode), each of
         * its default's type, shown but never saved over the person's own until they change it.
         */
        visit(change) {
            for (const [k, v] of Object.entries(change)) {
                if (!(k in defaults) || typeof v !== typeof defaults[k] || (valid[k] && !valid[k](v))) continue;
                if (!visiting.has(k)) visiting.set(k, value[k]);
                value[k] = v;
            }
        },
    };
}

/**
 * Calls `then` once the listener hears audio-clock time `at` (seconds): a tool's display following its sound, late
 * by the output's delay as the sound is (a Bluetooth speaker's 150 to 250 ms). Returns a cancel.
 */
export function atClock(at, then) {
    let frame = 0;
    const step = () => {
        if (heardTime(audio()) >= at) then();
        else frame = requestAnimationFrame(step);
    };
    frame = requestAnimationFrame(step);
    return () => cancelAnimationFrame(frame);
}

/** Runs `then` when the page is hidden (voices.js has already stopped every sound by then). */
export function onHidden(then) {
    const heard = () => document.hidden && then();
    document.addEventListener('visibilitychange', heard);
    addEventListener('pagehide', then);
    return () => {
        document.removeEventListener('visibilitychange', heard);
        removeEventListener('pagehide', then);
    };
}

/**
 * The device's media controls for a tool that keeps sounding (the lock screen, a headset's button, the browser's
 * media hub): `play()` and `pause()` answer their buttons; `show(title, playing)` names what's sounding. Does
 * nothing where the browser has no Media Session.
 */
export function mediaControls({ artist = '', play, pause }) {
    const session = navigator.mediaSession;
    if (!session || typeof MediaMetadata !== 'function') return { show() {}, clear() {} };
    const handle = (action, run) => {
        try {
            session.setActionHandler(action, run);
        } catch (error) {
            console.info(`This browser has no ${action} control`, error);
        }
    };
    handle('play', play);
    handle('pause', pause);
    handle('stop', pause);
    return {
        show(title, playing) {
            session.metadata = new MediaMetadata({ title, artist });
            session.playbackState = playing ? 'playing' : 'paused';
        },
        clear() {
            ['play', 'pause', 'stop'].forEach((action) => handle(action, null));
            session.metadata = null;
            session.playbackState = 'none';
        },
    };
}

/** A line of words with its Laravel-style `:name`s filled from `values`; a name it isn't given stays as it is. */
export const fill = (text, values = {}) => (text ?? '').replace(/:(\w+)/g, (m, k) => (k in values ? String(values[k]) : m));

/** A number in the page's language, with `digits` decimals. */
export const num = (n, digits = 0) => new Intl.NumberFormat(document.documentElement.lang || 'en', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(n);

// Inside the first tap: makes the AudioContext and, on an iPhone, sounds even with the silent switch on.
export { unlock } from '../demo-player.js';
