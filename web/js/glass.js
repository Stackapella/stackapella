// Glass gems on a canvas: the .gem recipe in css/input.css (ux-spec 2.6a) drawn for the lanes, so a
// note in Sing, on the Stage or in the creator looks like the star gems and the keyboard's note. A
// body brighter at the top and deeper at the bottom, a darker edge, a gloss along the top, a
// refraction line along the bottom, a coloured glow and a shadow under it. Light themes: less glow,
// more edge and shadow. Also the stage the notes sit on (faint pitch lines, a floor glow, slow specks
// in the accent, a vignette), the playhead as a glowing beam, and the sparkle burst of a note hit.
// Colours are worked out once per colour and kept; nothing here is a per-frame filter.

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

/** The gem's shades for a colour: kept, so a frame only reads them. */
export function shades(color, { light = false } = {}) {
    const key = `${color}|${light}`;
    if (shadeCache.has(key)) return shadeCache.get(key);
    const base = rgbOf(color);
    const grey = mix(base, [128, 128, 128], 0.7);
    const lum = (0.2126 * base[0] + 0.7152 * base[1] + 0.0722 * base[2]) / 255;
    const out = {
        top: css(mix(base, WHITE, 0.42)),
        body: css(base),
        bottom: css(mix(base, BLACK, 0.32)),
        edge: css(mix(base, BLACK, light ? 0.5 : 0.45)),
        band: css(mix(base, WHITE, 0.5), 0.75),
        glow: css(base, light ? 0.35 : 0.7),
        faint: css(base, light ? 0.55 : 0.5),
        dimTop: css(mix(grey, WHITE, 0.25)),
        dimBottom: css(mix(grey, BLACK, 0.3)),
        dimEdge: css(mix(grey, BLACK, 0.4)),
        // A label on the gem: dark on a light colour, white on a deep one (AA either way).
        ink: lum > 0.5 ? '#111111' : '#ffffff',
    };
    shadeCache.set(key, out);
    return out;
}

function rounded(g, x, y, w, h, r) {
    g.beginPath();
    if (g.roundRect) g.roundRect(x, y, w, h, r);
    else g.rect(x, y, w, h);
}

/**
 * A gem bar (a note) at (x, y), `w` by `h`, in `color`. `glow` 0 to 1 (the note now, a hit),
 * `rim`: a bright outline colour (the note now, a selected note), `dim`: missed or past (greyed,
 * never hidden), `lift`: a dragged note's raised shadow (px), `pop`: a scale about its centre (a
 * hit's 1.05), `ghost`: another part's note (faint, flat), `light`: a light theme.
 */
export function gemBar(g, x, y, w, h, color, { glow = 0, rim = null, dim = false, lift = 0, pop = 1, ghost = false, light = false } = {}) {
    if (w <= 0 || h <= 0) return;
    const s = shades(color, { light });
    const r = Math.min(h / 2, 9, w / 2);
    g.save();
    if (pop !== 1) {
        g.translate(x + w / 2, y + h / 2);
        g.scale(pop, pop);
        g.translate(-(x + w / 2), -(y + h / 2));
    }
    if (ghost) {
        // Another part: a faint dashed outline, so yours stay the only gems.
        g.fillStyle = light ? 'rgba(0,0,0,0.03)' : 'rgba(255,255,255,0.03)';
        rounded(g, x, y, w, h, r);
        g.fill();
        g.setLineDash([4, 3]);
        g.lineWidth = 1.25;
        g.strokeStyle = s.faint;
        g.stroke();
        g.setLineDash([]);
        g.restore();
        return;
    }
    // The shadow under it (two soft steps, no blur filter): deeper when lifted.
    g.fillStyle = light ? 'rgba(0,0,0,0.14)' : 'rgba(0,0,0,0.32)';
    rounded(g, x + 1, y + 2.5 + lift, w - 2, h, r);
    g.fill();
    g.fillStyle = light ? 'rgba(0,0,0,0.07)' : 'rgba(0,0,0,0.18)';
    rounded(g, x - 1, y + 4 + lift * 1.6, w + 2, h, r);
    g.fill();
    // The body, glowing when asked (one blurred fill: only the note now and a hit glow).
    const body = g.createLinearGradient(0, y, 0, y + h);
    body.addColorStop(0, dim ? s.dimTop : s.top);
    body.addColorStop(0.48, dim ? s.dimTop : s.body);
    body.addColorStop(1, dim ? s.dimBottom : s.bottom);
    if (glow > 0 && !dim) {
        g.shadowColor = s.glow;
        g.shadowBlur = (light ? 8 : 16) * glow;
    }
    g.fillStyle = body;
    rounded(g, x, y, w, h, r);
    g.fill();
    g.shadowBlur = 0;
    g.lineWidth = 1.5;
    g.strokeStyle = dim ? s.dimEdge : s.edge;
    g.stroke();
    // The gloss along the top, and the refraction line along the bottom.
    if (h >= 8 && w >= 8) {
        const gloss = g.createLinearGradient(0, y + 1, 0, y + h * 0.48);
        gloss.addColorStop(0, `rgba(255,255,255,${dim ? 0.25 : light ? 0.85 : 0.7})`);
        gloss.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = gloss;
        rounded(g, x + 2.5, y + 1.5, Math.max(0, w * 0.78 - 2.5), h * 0.44, Math.max(1, r * 0.8));
        g.fill();
        if (!dim && w > 2 * r + 4) {
            g.strokeStyle = s.band;
            g.lineWidth = 1;
            g.beginPath();
            g.moveTo(x + r, y + h - 2.5);
            g.lineTo(x + w - r, y + h - 2.5);
            g.stroke();
        }
    }
    if (rim) {
        g.strokeStyle = rim;
        g.lineWidth = 2;
        rounded(g, x - 1.5, y - 1.5, w + 3, h + 3, r + 1.5);
        g.stroke();
    }
    g.restore();
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
