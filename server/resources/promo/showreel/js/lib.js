// Maths, easing and DOM helpers for the film. Everything here is a pure function of its arguments:
// the film draws any frame from t alone, so nothing may remember the frame before. (After Event
// Schedule's showreel, resources/promo/showreel/js/lib.js.)

export const Q = new URLSearchParams(location.search);
// The frame: the film (wide), and the launch cuts made from the same source (render.py --launch):
// a vertical and a square cut for social, and the Product Hunt thumbnail (drawn at twice its 240 px).
export const FORMATS = { wide: [1920, 1080], vertical: [1080, 1920], square: [1080, 1080], thumb: [480, 480] };
export const FORMAT = FORMATS[Q.get('format')] ? Q.get('format') : 'wide';
export const [W, H] = FORMATS[FORMAT];
export const CX = W / 2;
export const CY = H / 2;
document.documentElement.dataset.format = FORMAT;
export const RENDER = Q.has('render');
export const LIGHT = document.documentElement.dataset.theme === 'light';

/* ---------- maths ---------- */
export const clamp = (x, a = 0, b = 1) => Math.min(b, Math.max(a, x));
export const lerp = (a, b, t) => a + (b - a) * t;
export const range = (t, a, b) => clamp((t - a) / (b - a));
export const DEG = Math.PI / 180;

function bezier(x1, y1, x2, y2) {
    const cx = 3 * x1, bx = 3 * (x2 - x1) - cx, ax = 1 - cx - bx;
    const cy = 3 * y1, by = 3 * (y2 - y1) - cy, ay = 1 - cy - by;
    const sx = (s) => ((ax * s + bx) * s + cx) * s, sy = (s) => ((ay * s + by) * s + cy) * s;
    const dx = (s) => (3 * ax * s + 2 * bx) * s + cx;
    return (x) => {
        if (x <= 0) return 0;
        if (x >= 1) return 1;
        let s = x;
        for (let i = 0; i < 8; i++) {
            const e = sx(s) - x, d = dx(s);
            if (Math.abs(e) < 1e-6 || !d) break;
            s -= e / d;
        }
        return sy(clamp(s));
    };
}

export const E = {
    lin: (t) => t,
    out: (t) => 1 - Math.pow(1 - t, 3),
    in: (t) => t * t * t,
    io: (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2),
    ioSine: (t) => 0.5 - 0.5 * Math.cos(Math.PI * t),
    outQuint: (t) => 1 - Math.pow(1 - t, 5),
    outExpo: (t) => (t >= 1 ? 1 : 1 - Math.pow(2, -10 * t)),
    inExpo: (t) => (t <= 0 ? 0 : Math.pow(2, 10 * t - 10)),
    ioExpo: (t) => (t <= 0 ? 0 : t >= 1 ? 1 : t < 0.5 ? Math.pow(2, 20 * t - 10) / 2 : (2 - Math.pow(2, -20 * t + 10)) / 2),
    outBack: (t) => { const s = 1.70158; return 1 + (s + 1) * Math.pow(t - 1, 3) + s * Math.pow(t - 1, 2); },
    rise: bezier(0.22, 1, 0.36, 1),
    snap: bezier(0.7, 0, 0.15, 1),
};

/** A damped spring from 0 to 1; `dt` in seconds since release. */
export function spring(dt, freq = 1.6, damp = 0.5) {
    if (dt <= 0) return 0;
    const w = 2 * Math.PI * freq, z = damp, wd = w * Math.sqrt(1 - z * z);
    return 1 - Math.exp(-z * w * dt) * (Math.cos(wd * dt) + ((z * w) / wd) * Math.sin(wd * dt));
}

/** A bump that rises and falls once between a and b. */
export const bump = (t, a, b) => { const u = range(t, a, b); return u <= 0 || u >= 1 ? 0 : Math.sin(Math.PI * u); };

export function rng(seed) {
    let a = seed >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}
export const hash = (n) => rng(n * 9973 + 17)();

/**
 * A keyframed value: keys are [time, value, easeIntoThisKey] in time order. The ease named on a key
 * shapes the stretch that arrives at it, the way an animator reads a dope sheet.
 */
export function track(keys, defEase = 'io') {
    const K = keys.map((k) => [k[0], k[1], typeof k[2] === 'function' ? k[2] : E[k[2] || defEase]]);
    return (t) => {
        if (t <= K[0][0]) return K[0][1];
        for (let i = 1; i < K.length; i++) {
            if (t <= K[i][0]) {
                const a = K[i - 1], b = K[i];
                return lerp(a[1], b[1], b[2]((t - a[0]) / (b[0] - a[0])));
            }
        }
        return K[K.length - 1][1];
    };
}

/* ---------- DOM ---------- */
export const $ = (s) => document.querySelector(s);
export function mk(tag, cls, parent, text) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    if (parent) parent.appendChild(e);
    return e;
}
/** Shows or hides, touching the DOM only on a change. */
export const vis = (e, on) => { const v = on ? '' : 'none'; if (e.style.display !== v) e.style.display = v; };
/** Sets a style property or a custom property, only when it changes. */
export function set(e, prop, value) {
    const key = '_' + prop;
    if (e[key] === value) return;
    e[key] = value;
    if (prop.startsWith('--')) e.style.setProperty(prop, value);
    else e.style[prop] = value;
}
export const rgba = (rgb, a) => `rgba(${rgb[0]},${rgb[1]},${rgb[2]},${Math.max(0, a).toFixed(4)})`;
