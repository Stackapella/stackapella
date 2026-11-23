// Glass gems on a canvas: the one recipe for a note (docs/glass-spec.md), every number from js/gem-spec.js, which
// css/glass.css carries too, so the home page's DOM gem is this one. A body light at the top, flat across the middle
// (the words sit there) and deep at the bottom; an edge; a gloss pill that ends above the words; a foot line; two
// drops; a glow by state; and round the note being sung, the ring: a knockout and a keyline in the colour under the
// lane with the theme's text colour between (Hillel: "yes, add the ring to the app too"). Also the stage the notes sit
// on, the playhead's beam and a hit's sparkles. Colours are worked out once per colour and kept; nothing here is a
// per-frame filter, and nothing keeps time: callers pass where each envelope is.

import { GEM } from './gem-spec.js';

const shadeCache = new Map();
let probe = null;

/** A CSS colour as [r, g, b] (any form the browser understands). */
function rgbOf(color) {
    probe ??= document.createElement('canvas').getContext('2d');
    probe.fillStyle = '#000';
    probe.fillStyle = color;
    const value = probe.fillStyle;
    if (value.startsWith('#')) return [1, 3, 5].map((i) => parseInt(value.slice(i, i + 2), 16));
    return value.match(/[\d.]+/g).slice(0, 3).map(Number);
}

const css = ([r, g, b], alpha = 1) => `rgba(${Math.round(r)},${Math.round(g)},${Math.round(b)},${alpha})`;
const mix = (a, b, t) => a.map((v, i) => v + (b[i] - v) * t);
const WHITE = [255, 255, 255];
const BLACK = [0, 0, 0];

/** `a` mixed toward `b` by `t` (0 to 1), as an rgb() a canvas can use: a held note's quiet body. */
export function mixColor(a, b, t) {
    return css(mix(rgbOf(a), rgbOf(b), t));
}

/** WCAG relative luminance of [r, g, b]. */
function luminance(rgb) {
    const [r, g, b] = rgb.map((v) => {
        const c = v / 255;
        return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** WCAG contrast of two colours (any CSS form): 1 to 21. */
export function contrast(a, b) {
    const [x, y] = [luminance(rgbOf(a)), luminance(rgbOf(b))].sort((p, q) => q - p);
    return (x + 0.05) / (y + 0.05);
}

/** The words on a gem of `color`: GEM.ink's dark or light, whichever has the higher contrast on it. */
export function gemInk(color) {
    return contrast(color, GEM.ink.dark) >= contrast(color, GEM.ink.light) ? GEM.ink.dark : GEM.ink.light;
}

/** The gem's shades for a colour: kept, so a frame only reads them. `surf`: the colour under the lane (for Settled). */
export function shades(color, { light = false, surf = null } = {}) {
    const key = `${color}|${light}|${surf}`;
    if (shadeCache.has(key)) return shadeCache.get(key);
    const base = rgbOf(color);
    const under = surf ? rgbOf(surf) : light ? WHITE : BLACK;
    const grey = mix(base, [128, 128, 128], 0.7);
    const calm = mix(under, base, GEM.settled.body);
    const out = {
        top: css(mix(base, WHITE, GEM.body.top)),
        body: css(base),
        bottom: css(mix(base, BLACK, GEM.body.bottom)),
        edge: css(mix(base, BLACK, light ? GEM.edge.light : GEM.edge.dark)),
        band: css(mix(base, WHITE, GEM.foot.white), GEM.foot.alpha),
        glow: css(base, light ? GEM.glow.lightAlpha : GEM.glow.darkAlpha),
        faint: css(base, light ? 0.55 : 0.5),
        // Settled: c at 40% over the colour under the lane, its edge at 65%; the same light and depth.
        settledTop: css(mix(calm, WHITE, GEM.body.top)),
        settledBody: css(calm),
        settledBottom: css(mix(calm, BLACK, GEM.body.bottom)),
        settledEdge: css(mix(under, base, GEM.settled.edge)),
        // The result cards' quiet gem (a missed note there), as it was.
        dimTop: css(mix(grey, WHITE, 0.25)),
        dimBottom: css(mix(grey, BLACK, 0.3)),
        dimEdge: css(mix(grey, BLACK, 0.4)),
        ghostFill: css(base, GEM.ghost.fill),
        ghostEdge: css(base, GEM.ghost.edgeAlpha),
        ink: gemInk(color),
        rgb: base,
        settledRgb: calm,
    };
    shadeCache.set(key, out);
    return out;
}

/** The colour under `el` (a gem canvas): its nearest ancestor's own solid background, else `fallback`. */
export function surfaceUnder(el, fallback) {
    for (let node = el?.parentElement; node; node = node.parentElement) {
        const bg = getComputedStyle(node).backgroundColor;
        const alpha = /rgba\([^)]*,\s*([\d.]+)\)/.exec(bg)?.[1];
        if (bg && bg !== 'transparent' && (alpha === undefined || Number(alpha) >= 0.99)) return bg;
    }
    return fallback;
}

/** A gem's corner radius at `h` by `w`. */
export const gemRadius = (w, h) => Math.max(0, Math.min(GEM.radius * h, w / 2));

function rounded(g, x, y, w, h, r) {
    g.beginPath();
    if (g.roundRect) g.roundRect(x, y, Math.max(0, w), Math.max(0, h), Math.max(0, Math.min(r, w / 2, h / 2)));
    else g.rect(x, y, w, h);
}

/** CSS `0 dy 0 spread` round the gem (x, y, w, h, r) as a canvas path. */
const spread = (g, x, y, w, h, r, dy, s) => rounded(g, x - s, y + dy - s, w + 2 * s, h + 2 * s, r + s);

const lerpRgb = (a, b, t) => (t <= 0 ? a : t >= 1 ? b : mix(a, b, t));

/**
 * A gem bar (a note) at (x, y), `w` by `h`, in `color`:
 * - `state`: 'upcoming' (the full recipe; also next, now, hit and kept, told apart by glow, ring and tick), 'settled'
 *   (past, not hit), 'ghost' (another part, or a voice not joined: an outline);
 * - `settle` 0 to 1: how far into Settled (the 240 ms ease from the audio clock; 1 when `state` is 'settled');
 * - `glow`: the state's glow level g (next ramps to GEM.level.next, now GEM.level.now, a hit flashes 1);
 * - `ring`: { color, surf, t } round the note being sung (`t` 0 to 1: its onset or release, from the audio clock);
 * - `surf`: the colour under the lane; `light`: a light theme; `game`: game mode (no soft glow);
 * - `pop`: a scale about its centre (a hit's 1.05); `lift`: a dragged note's raised drop (px).
 * Old names still read: `dim` is Settled, `rim` (a colour) is the ring.
 */
export function gemBar(g, x, y, w, h, color, opts = {}) {
    if (w <= 0 || h <= 0) return;
    const { glow = 0, pop = 1, lift = 0, light = false, game = false } = opts;
    const surf = opts.surf ?? opts.ring?.surf ?? null;
    const ring = opts.ring ?? (opts.rim ? { color: opts.rim, surf, t: 1 } : null);
    const state = opts.state ?? (opts.ghost ? 'ghost' : opts.dim ? 'settled' : 'upcoming');
    const settle = state === 'settled' ? 1 : Math.max(0, Math.min(1, opts.settle ?? 0));
    const s = shades(color, { light, surf });
    const r = gemRadius(w, h);
    g.save();
    if (pop !== 1) {
        g.translate(x + w / 2, y + h / 2);
        g.scale(pop, pop);
        g.translate(-(x + w / 2), -(y + h / 2));
    }
    if (state === 'ghost') {
        g.fillStyle = s.ghostFill;
        rounded(g, x, y, w, h, r);
        g.fill();
        g.setLineDash([4, 3]);
        g.lineWidth = GEM.ghost.edge;
        g.strokeStyle = s.ghostEdge;
        rounded(g, x + GEM.ghost.edge / 2, y + GEM.ghost.edge / 2, w - GEM.ghost.edge, h - GEM.ghost.edge, r - GEM.ghost.edge / 2);
        g.stroke();
        g.setLineDash([]);
        g.restore();
        return;
    }
    // The drops: A (half when Settled), B (none when Settled).
    const dropA = (light ? GEM.dropA.light : GEM.dropA.dark) * (1 - settle * (1 - GEM.settled.dropA));
    const dropB = (light ? GEM.dropB.light : GEM.dropB.dark) * (1 - settle);
    g.fillStyle = `rgba(0,0,0,${dropA})`;
    spread(g, x, y, w, h, r, GEM.dropA.dy + lift, GEM.dropA.spread);
    g.fill();
    if (dropB > 0) {
        g.fillStyle = `rgba(0,0,0,${dropB})`;
        spread(g, x, y, w, h, r, GEM.dropB.dy + lift * 1.6, GEM.dropB.spread);
        g.fill();
    }
    // The body: light, flat, deep; eased toward Settled.
    const top = settle ? css(lerpRgb(rgbOf(s.top), rgbOf(s.settledTop), settle)) : s.top;
    const flat = settle ? css(lerpRgb(s.rgb, s.settledRgb, settle)) : s.body;
    const bottom = settle ? css(lerpRgb(rgbOf(s.bottom), rgbOf(s.settledBottom), settle)) : s.bottom;
    const body = g.createLinearGradient(0, y, 0, y + h);
    body.addColorStop(0, top);
    body.addColorStop(GEM.body.flatFrom, flat);
    body.addColorStop(GEM.body.flatTo, flat);
    body.addColorStop(1, bottom);
    if (glow > 0 && !game) {
        g.shadowColor = css(s.rgb, (light ? GEM.glow.lightAlpha : GEM.glow.darkAlpha) * glow);
        g.shadowBlur = light ? GEM.glow.lightBlur : GEM.glow.darkBlur;
    }
    g.fillStyle = body;
    rounded(g, x, y, w, h, r);
    g.fill();
    g.shadowBlur = 0;
    g.shadowColor = 'transparent';
    // The edge, inside the box (as a CSS border).
    const edgeW = GEM.edge.width;
    g.lineWidth = edgeW;
    g.strokeStyle = settle ? css(lerpRgb(rgbOf(s.edge), rgbOf(s.settledEdge), settle)) : s.edge;
    rounded(g, x + edgeW / 2, y + edgeW / 2, w - edgeW, h - edgeW, r - edgeW / 2);
    g.stroke();
    // The gloss pill: above the words.
    const gx = x + Math.max(1, GEM.gloss.x0 * h);
    const gw = x + GEM.gloss.x1 * w - gx;
    // Whole pixels from the gem's top (as css/glass.css rounds them), so the gloss and the foot are crisp.
    const gy = y + Math.round(GEM.gloss.y0 * h);
    const gh = Math.round((GEM.gloss.y1 - GEM.gloss.y0) * h);
    if (gw > gh && gh >= 1) {
        const strength = (light ? GEM.gloss.light : GEM.gloss.dark) * (1 - settle) + GEM.settled.gloss * settle;
        const gloss = g.createLinearGradient(0, gy, 0, gy + gh);
        gloss.addColorStop(0, `rgba(255,255,255,${strength})`);
        gloss.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = gloss;
        rounded(g, gx, gy, gw, gh, gh / 2);
        g.fill();
    }
    // The foot line (none when Settled).
    if (settle < 1 && w > 2 * r + 2) {
        g.fillStyle = css(mix(s.rgb, WHITE, GEM.foot.white), GEM.foot.alpha * (1 - settle));
        g.fillRect(x + r, y + Math.round(h - GEM.foot.at * h - GEM.foot.width / 2), w - 2 * r, GEM.foot.width);
    }
    // The ring: knockout, ring, keyline, outward; the knockout closes in from 6 to 3 px as it comes on.
    if (ring && (ring.t ?? 1) > 0) {
        const t = Math.min(1, ring.t ?? 1);
        const knock = GEM.ring.knockout * (2 - t);
        const under = ring.surf ?? surf;
        g.globalAlpha = t;
        const stroke = (out, width, colour) => {
            if (!colour) return;
            g.lineWidth = width;
            g.strokeStyle = colour;
            rounded(g, x - out, y - out, w + 2 * out, h + 2 * out, r + out);
            g.stroke();
        };
        stroke(knock / 2, knock, under);
        stroke(knock + GEM.ring.width / 2, GEM.ring.width, ring.color);
        stroke(knock + GEM.ring.width + GEM.ring.keyline / 2, GEM.ring.keyline, under);
        g.globalAlpha = 1;
    }
    g.restore();
}

/** The room a hit's tick takes at a gem's end (its size, its gap from the end, and 4 px before it). */
export const tickRoom = (h) => Math.min(GEM.hit.tickMax, GEM.hit.tickOfH * h) + GEM.hit.tickEnd + 4;

/**
 * A hit's tick: 2 px, min(15, 0.42h), 6 px from the gem's end, in the ink. `outside`: when the gem's words fill it,
 * the tick sits just above the gem's end instead (clear of a ring), in `color` (the theme's text), so a hit is never
 * colour alone and the word never moves for it.
 */
export function gemTick(g, x, y, w, h, ink, { outside = false, color = ink, ringed = false } = {}) {
    const size = Math.min(GEM.hit.tickMax, GEM.hit.tickOfH * h, outside ? GEM.words.aboveSize : Infinity);
    const right = x + w - GEM.hit.tickEnd;
    const ring = ringed ? GEM.ring.knockout + GEM.ring.width + GEM.ring.keyline : 0;
    const cy = outside ? y - ring - GEM.words.aboveGap - size / 2 : y + h / 2;
    g.save();
    g.strokeStyle = outside ? color : ink;
    g.lineWidth = GEM.hit.tickWidth;
    g.lineCap = 'round';
    g.lineJoin = 'round';
    g.beginPath();
    g.moveTo(right - size, cy);
    g.lineTo(right - size * 0.62, cy + size * 0.38);
    g.lineTo(right, cy - size * 0.4);
    g.stroke();
    g.restore();
}

const widths = new Map();
let fontsSeen = -1;
if (typeof document !== 'undefined' && document.fonts?.addEventListener) document.fonts.addEventListener('loadingdone', () => widths.clear());
/** A text's width in `font` with `spacing` (cached; cleared when a font arrives, so a fallback's width never sticks). */
function measure(g, font, text, spacing = '0px') {
    const fonts = typeof document !== 'undefined' && document.fonts ? document.fonts.size : 0;
    if (fonts !== fontsSeen) {
        widths.clear();
        fontsSeen = fonts;
    }
    const key = `${font}|${spacing}|${text}`;
    if (!widths.has(key)) {
        g.save();
        g.font = font;
        if ('letterSpacing' in g) g.letterSpacing = spacing;
        widths.set(key, g.measureText(text).width);
        g.restore();
    }
    return widths.get(key);
}

/** A gem's word as shown: its trailing hyphens dropped ("maz-" is "maz"). */
export const gemWord = (word) => String(word ?? '').replace(/-+$/, '').trim();

/** The syllable's size on a gem `h` tall. */
export const wordSize = (h) => Math.max(GEM.words.min, Math.min(GEM.words.max, GEM.words.ofH * h));

/** Says (once) when Figtree 750 is ready, so a canvas drawn before it can draw again. */
export function whenWordsReady(redraw) {
    if (typeof document === 'undefined' || !document.fonts?.load) return;
    document.fonts.load(`${GEM.words.weight} 12px Figtree`).then(() => redraw(), () => {});
    document.fonts.ready.then(() => redraw(), () => {});
}

/**
 * Where a gem's words go by the fit ladder (docs/glass-spec.md 2), the same in every state: word and note name inside,
 * the word alone inside, the word alone at GEM.words.step px, above the gem (only if it ends GEM.words.aboveClear px before `nextX` and, clear of a ring,
 * stays inside `bounds`), or nothing. Never clipped. `reserve` (a hit's tick) only lets the name give way: the word
 * stays where it is, and the tick goes above the gem when the word fills it. Returns { where, text, font, end, ... }.
 */
export function gemFit(g, { x, y, w, h, word, name = '', nextX = Infinity, bounds = null, reserve = 0, quietName = false }) {
    const said = gemWord(word);
    const r = gemRadius(w, h);
    const inset = Math.max(GEM.words.inset, GEM.words.insetOfR * r);
    const size = wordSize(h);
    const fontAt = (px) => `${GEM.words.weight} ${px}px Figtree, system-ui, sans-serif`;
    const spacingAt = (px) => `${(GEM.words.tracking * px).toFixed(2)}px`;
    const room = w - inset - GEM.words.inset;
    const inside = (text, px, space) => {
        const width = measure(g, fontAt(px), text, spacingAt(px));
        return width <= space ? { where: 'in', text, font: fontAt(px), spacing: spacingAt(px), x: x + inset, end: x + inset + width } : null;
    };
    if (h >= GEM.words.minGem) {
        // Sing's Notes view (Hillel's 2026-11-23 design, quietName): the name after the word, smaller and lighter.
        if (quietName && said && name) {
            const namePx = Math.max(GEM.words.min, Math.round(size * GEM.words.nameOf));
            const nameFont = `${GEM.words.nameWeight} ${namePx}px Figtree, system-ui, sans-serif`;
            const wordWidth = measure(g, fontAt(size), said, spacingAt(size));
            const gap = Math.round(size * 0.4);
            const nameWidth = measure(g, nameFont, name);
            if (wordWidth + gap + nameWidth <= room - Math.max(0, reserve - GEM.words.inset)) {
                return { where: 'in', text: said, font: fontAt(size), spacing: spacingAt(size), x: x + inset, end: x + inset + wordWidth + gap + nameWidth, name, nameFont, nameX: x + inset + wordWidth + gap };
            }
        }
        const both = said && name ? `${said} ${name}` : '';
        const one = said || name;
        // Word and name, the word alone, then the word alone a step smaller (never under the floor), before it goes above.
        const fit = (both && inside(both, size, room - Math.max(0, reserve - GEM.words.inset)))
            || (one && inside(one, size, room))
            || (one && GEM.words.step < size && inside(one, Math.max(GEM.words.min, GEM.words.step), room));
        if (fit) return fit;
    }
    const text = said || name;
    if (text) {
        const small = `${GEM.words.aboveWeight} ${GEM.words.aboveSize}px Figtree, system-ui, sans-serif`;
        const end = x + measure(g, small, text);
        // Inside the lane whether or not a ring is round it, so the choice never changes as the note comes and goes.
        const clear = GEM.ring.knockout + GEM.ring.width + GEM.ring.keyline;
        const fits = !bounds || (y - clear - GEM.words.aboveGap - GEM.words.aboveSize >= bounds.top && end <= bounds.right);
        if (end <= nextX - GEM.words.aboveClear && fits) return { where: 'above', text, font: small, spacing: '0px', x, end };
    }
    return { where: 'none', text: '', end: x };
}

/**
 * The words on a gem (gemFit's choice), drawn: `ink` inside; `above`, the colour above it; `ringed`, a word above
 * clears the ring. Returns the fit.
 */
export function gemWords(g, { x, y, w, h, word, name = '', ink, above, nextX = Infinity, bounds = null, reserve = 0, ringed = false, quietName = false }) {
    const fit = gemFit(g, { x, y, w, h, word, name, nextX, bounds, reserve, quietName });
    if (fit.where === 'none' || (fit.where === 'above' && !above)) return fit;
    g.save();
    g.textBaseline = 'middle';
    g.textAlign = 'left';
    g.font = fit.font;
    if ('letterSpacing' in g) g.letterSpacing = fit.spacing;
    if (fit.where === 'in') {
        g.fillStyle = ink;
        g.fillText(fit.text, fit.x, y + h / 2 + 0.5);
        if (fit.name) {
            g.font = fit.nameFont;
            if ('letterSpacing' in g) g.letterSpacing = '0px';
            g.globalAlpha *= GEM.words.nameAlpha;
            g.fillText(fit.name, fit.nameX, y + h / 2 + 0.5);
        }
    } else {
        const ring = ringed ? GEM.ring.knockout + GEM.ring.width + GEM.ring.keyline : 0;
        g.fillStyle = above;
        g.fillText(fit.text, fit.x, y - ring - GEM.words.aboveGap - GEM.words.aboveSize / 2);
    }
    g.restore();
    return fit;
}

/** A hit's sparkle burst from (cx, cy), `age` seconds in (gone after 0.5 s): small four-point glints. */
export function sparkles(g, cx, cy, age, color, { count = 8, seed = 1 } = {}) {
    if (age < 0 || age > 0.5) return;
    if (document.documentElement.dataset.skin === 'serious') return; // a hit's burst is game mode's; the tick stays
    const t = age / 0.5;
    g.save();
    g.globalAlpha = 1 - t;
    for (let i = 0; i < count; i++) {
        const angle = (i / count) * Math.PI * 2 + seed * 0.7;
        const reach = (14 + ((i * 7 + seed * 13) % 9)) * (0.4 + t);
        const px = cx + Math.cos(angle) * reach;
        const py = cy + Math.sin(angle) * reach * 0.8;
        const size = 4 * (1 - t * 0.6);
        g.fillStyle = i % 2 ? '#ffffff' : color;
        g.beginPath();
        g.moveTo(px, py - size);
        g.lineTo(px + size * 0.3, py - size * 0.3);
        g.lineTo(px + size, py);
        g.lineTo(px + size * 0.3, py + size * 0.3);
        g.lineTo(px, py + size);
        g.lineTo(px - size * 0.3, py + size * 0.3);
        g.lineTo(px - size, py);
        g.lineTo(px - size * 0.3, py - size * 0.3);
        g.closePath();
        g.fill();
    }
    g.restore();
}

/**
 * The stage the notes sit on, from `left` to `width` and `top` to `bottom`: a gentle gradient, a
 * faint line for every semitone shown (`lines`: the y of each, a C a little stronger), a soft floor
 * glow in the accent, and slow specks that drift at a third of the notes' speed (none when `still`).
 */
export function stage(g, { left, top, width, bottom, lines, accent, light, drift = 0, still = false }) {
    const back = g.createLinearGradient(0, top, 0, bottom);
    back.addColorStop(0, light ? 'rgba(0,0,0,0.02)' : 'rgba(255,255,255,0.03)');
    back.addColorStop(1, light ? 'rgba(0,0,0,0.05)' : 'rgba(255,255,255,0)');
    g.fillStyle = back;
    g.fillRect(left, top, width - left, bottom - top);
    for (const { y, strong } of lines) {
        g.fillStyle = light ? `rgba(0,0,0,${strong ? 0.1 : 0.045})` : `rgba(255,255,255,${strong ? 0.09 : 0.04})`;
        g.fillRect(left, Math.round(y), width - left, 1); // on whole pixels: crisp at 1x
    }
    const accentRgb = rgbOf(accent);
    const floor = g.createLinearGradient(0, bottom - (bottom - top) * 0.35, 0, bottom);
    floor.addColorStop(0, css(accentRgb, 0));
    floor.addColorStop(1, css(accentRgb, light ? 0.08 : 0.12));
    g.fillStyle = floor;
    g.fillRect(left, top, width - left, bottom - top);
    if (still) return;
    // The specks: placed once by a fixed pattern, drifting left with the song (parallax).
    const span = width - left;
    for (let i = 0; i < 14; i++) {
        const px = left + ((((i * 97.3) % span) - drift * (0.25 + (i % 3) * 0.08)) % span + span) % span;
        const py = top + ((i * 53.7) % (bottom - top));
        g.fillStyle = css(accentRgb, light ? 0.18 : 0.22 + (i % 4) * 0.05);
        g.beginPath();
        g.arc(px, py, 1 + (i % 3) * 0.6, 0, Math.PI * 2);
        g.fill();
    }
}

/** The vignette over the stage: the edges a touch darker, so the middle reads as lit. */
export function vignette(g, { left, top, width, bottom, light }) {
    const cx = (left + width) / 2;
    const cy = (top + bottom) / 2;
    const v = g.createRadialGradient(cx, cy, Math.min(width - left, bottom - top) * 0.3, cx, cy, Math.max(width - left, bottom - top) * 0.75);
    v.addColorStop(0, 'rgba(0,0,0,0)');
    v.addColorStop(1, light ? 'rgba(0,0,0,0.06)' : 'rgba(0,0,0,0.35)');
    g.fillStyle = v;
    g.fillRect(left, top, width - left, bottom - top);
}

/** The playhead as a glowing beam at `x`, in `color`. */
export function beam(g, x, top, bottom, color, { light = false } = {}) {
    const rgb = rgbOf(color);
    const halo = g.createLinearGradient(x - 12, 0, x + 12, 0);
    halo.addColorStop(0, css(rgb, 0));
    halo.addColorStop(0.5, css(rgb, light ? 0.18 : 0.28));
    halo.addColorStop(1, css(rgb, 0));
    g.fillStyle = halo;
    g.fillRect(x - 12, top, 24, bottom - top);
    g.fillStyle = color;
    g.fillRect(Math.round(x) - 1, top, 3, bottom - top);
    g.fillStyle = 'rgba(255,255,255,0.8)';
    g.fillRect(Math.round(x), top, 1, bottom - top);
}
