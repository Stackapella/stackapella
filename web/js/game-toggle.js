// Game mode toggle (the design agent's, docs/skins-plan.md): the behaviour for the gt- rules in css/input.css. Framework-free, no build step, no dependencies.
//
//   import { mountGameToggles } from './game-toggle.js';
//   mountGameToggles();                    // every [data-game-toggle] on the page, wired to data-skin
//
// By default the toggle reads <html data-skin> and changes it through theme-boot's
// window.stackapellaTheme.choose({ skin }), and follows data-skin when anything else changes it (the
// theme sheet, another tab's cookie, the account). Pass options to wire it elsewhere (enhance() below).
//
// What it does: owns aria-checked; adds the transient classes the CSS animates (is-wake on mouse
// hover and keyboard focus, is-pressing, is-turning-on / is-turning-off for one run, is-attract for
// a few waiting breaths that stop for good once the person has noticed it); pauses every loop when
// the toggle is off-screen or the tab is hidden; and follows prefers-reduced-motion. A tiny sound
// only when options.sound() says yes (never by default), scheduled on the AudioContext clock.

const REDUCE = window.matchMedia('(prefers-reduced-motion: reduce)');
const ON_RUN = 900; // ms the turning-on classes stay (the longest piece: "Press start" blinks for 800ms) // i18n-ignore
const OFF_RUN = 600; // ms the CRT power-down runs (560ms, plus a frame)

const DEFAULTS = {
    /** Whether game mode is on now. */
    get: () => document.documentElement.dataset.skin === 'game',
    /** Turns it on or off for real (the page's skin). */
    set: (on) => {
        const skin = on ? 'game' : 'serious';
        if (window.stackapellaTheme) window.stackapellaTheme.choose({ skin });
        else document.documentElement.dataset.skin = skin;
    },
    /** The element whose data-skin to follow, so outside changes show here. */
    watch: () => document.documentElement,
    /** A tiny sound on press, only when the person has sound on. Off unless the host says so. */
    sound: () => false,
    /** Reduced motion: the system setting, unless the host has its own. */
    reducedMotion: () => REDUCE.matches,
    /** How long after the press the page changes skin, so it lands with the toggle's moment. */
    commitDelay: { on: 140, off: 260 },
};

const all = new Set();
let observer = null;
let audio = null;

function visibilityObserver() {
    if (observer || !('IntersectionObserver' in window)) return observer;
    observer = new IntersectionObserver((entries) => {
        for (const entry of entries) entry.target.__gameToggle?.seen(entry.isIntersecting);
    });
    document.addEventListener('visibilitychange', () => {
        for (const toggle of all) toggle.pause();
    });
    REDUCE.addEventListener('change', () => {
        for (const toggle of all) toggle.motion();
    });
    return observer;
}

/** Plays the press sound on the audio clock: a two-note coin on, a falling blip off. */
function chirp(on) {
    try {
        audio ??= new (window.AudioContext || window.webkitAudioContext)();
        if (audio.state === 'suspended') audio.resume();
        const t = audio.currentTime + 0.01;
        const gain = audio.createGain();
        const osc = audio.createOscillator();
        osc.connect(gain);
        gain.connect(audio.destination);
        gain.gain.setValueAtTime(0.0001, t);
        if (on) {
            osc.type = 'square';
            osc.frequency.setValueAtTime(988, t); // B5
            osc.frequency.setValueAtTime(1319, t + 0.075); // E6
            gain.gain.exponentialRampToValueAtTime(0.045, t + 0.006);
            gain.gain.setValueAtTime(0.045, t + 0.075);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.38);
            osc.stop(t + 0.4);
        } else {
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(660, t);
            osc.frequency.exponentialRampToValueAtTime(110, t + 0.3);
            gain.gain.exponentialRampToValueAtTime(0.06, t + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.32);
            osc.stop(t + 0.34);
        }
        osc.start(t);
    } catch (error) {
        audio = null; // no Web Audio here: the toggle stays silent
        console.debug('Game mode toggle: no sound', error);
    }
}

/**
 * Brings one toggle button to life. Returns { set(on, animate), sync(), destroy() }.
 * Options: get, set, watch, sound, reducedMotion, commitDelay (see DEFAULTS).
 */
export function enhance(button, options = {}) {
    if (button.__gameToggle) return button.__gameToggle;
    const opts = { ...DEFAULTS, ...options };
    let on = Boolean(opts.get());
    let inView = true;
    let wasOut = false;
    let noticed = false; // hovered, focused or pressed once: it never pulses again on this page
    let pointerInside = false;
    const timers = new Set();
    const later = (fn, ms) => {
        const id = setTimeout(() => {
            timers.delete(id);
            fn();
        }, ms);
        timers.add(id);
    };
    const clearTimers = () => {
        for (const id of timers) clearTimeout(id);
        timers.clear();
    };
    const cls = button.classList;

    const reduced = () => Boolean(opts.reducedMotion());
    const api = {
        motion() {
            button.dataset.motion = reduced() ? 'reduce' : 'full';
        },
        pause() {
            cls.toggle('is-paused', !inView || document.hidden);
        },
        seen(visible) {
            inView = visible;
            if (!visible) wasOut = true;
            else if (wasOut) {
                wasOut = false;
                attract(); // back in view: a few more breaths, unless it's been noticed
            }
            api.pause();
        },
        /** Shows a state, with or without the moment. Doesn't call options.set. */
        set(next, animate = false) {
            next = Boolean(next);
            if (next === on && !animate) return;
            on = next;
            clearTimers();
            cls.remove('is-turning-on', 'is-turning-off', 'is-cold');
            if (animate && !reduced()) {
                // Restart the run: a class removed and added in one frame wouldn't replay.
                void button.offsetWidth;
                cls.add(on ? 'is-turning-on' : 'is-turning-off');
                if (on && !cls.contains('is-wake')) cls.add('is-cold'); // from cold: the screen powers on, CRT-style
                later(() => cls.remove('is-turning-on', 'is-turning-off', 'is-cold'), on ? ON_RUN : OFF_RUN);
            }
            button.setAttribute('aria-checked', String(on));
            if (on) cls.remove('is-attract');
        },
        /** Re-reads the real state (after an outside change), quietly. */
        sync() {
            const real = Boolean(opts.get());
            if (real !== on) instant(() => api.set(real));
        },
        destroy() {
            clearTimers();
            mutation?.disconnect();
            observer?.unobserve(button);
            all.delete(api);
            for (const [type, fn] of listeners) button.removeEventListener(type, fn);
            delete button.__gameToggle;
        },
    };

    function instant(fn) {
        cls.add('is-instant');
        fn();
        requestAnimationFrame(() => requestAnimationFrame(() => cls.remove('is-instant')));
    }
    function attract() {
        if (noticed || on || reduced()) return;
        cls.remove('is-attract');
        void button.offsetWidth; // restart the breaths
        cls.add('is-attract');
    }
    function notice() {
        noticed = true;
        cls.remove('is-attract');
    }
    function wake(yes) {
        cls.toggle('is-wake', yes);
        if (yes) notice();
        else cls.remove('is-spent');
    }
    function press(yes) {
        cls.toggle('is-pressing', yes);
    }
    function flip() {
        notice();
        press(false);
        const next = !on;
        api.set(next, true);
        if (pointerInside) cls.add('is-spent'); // the pointer's still here: don't wake again until it leaves
        if (opts.sound()) chirp(next);
        const delay = reduced() ? 0 : opts.commitDelay[next ? 'on' : 'off'];
        later(() => opts.set(next), delay);
    }

    const listeners = [
        ['click', flip],
        ['pointerenter', (e) => {
            pointerInside = true;
            if (e.pointerType === 'mouse') wake(true);
        }],
        ['pointerleave', () => {
            pointerInside = false;
            press(false);
            if (!button.matches(':focus-visible')) wake(false);
        }],
        ['pointerdown', (e) => {
            if (e.button === 0) press(true);
        }],
        ['pointerup', () => press(false)],
        ['pointercancel', () => press(false)],
        ['focus', () => {
            if (button.matches(':focus-visible')) wake(true);
        }],
        ['blur', () => {
            press(false);
            if (!pointerInside) wake(false);
        }],
        ['keydown', (e) => {
            if ((e.key === ' ' || e.key === 'Enter') && !e.repeat) press(true); // i18n-ignore: key names
        }],
        ['keyup', (e) => {
            if (e.key === ' ' || e.key === 'Enter') press(false); // i18n-ignore: key names
        }],
        ['animationend', (e) => {
            if (e.animationName === 'gt-breathe') cls.remove('is-attract'); // five breaths, then rest
        }],
    ];
    for (const [type, fn] of listeners) button.addEventListener(type, fn);

    // Follow outside changes to the skin (the theme sheet, the account, another tab).
    const target = opts.watch?.();
    const mutation = target ? new MutationObserver(() => api.sync()) : null;
    mutation?.observe(target, { attributes: true, attributeFilter: ['data-skin'] });

    button.__gameToggle = api;
    all.add(api);
    instant(() => {
        button.setAttribute('aria-checked', String(on));
        api.motion();
    });
    visibilityObserver()?.observe(button);
    api.pause();
    // Settings rows don't ask for attention (calm screens never blink): only the pill and hero breathe.
    if (!cls.contains('gt--row')) attract();
    return api;
}

/** Enhances every [data-game-toggle] under root. Returns their APIs. */
export function mountGameToggles(root = document, options = {}) {
    return [...root.querySelectorAll('[data-game-toggle]')].map((button) => enhance(button, options));
}
