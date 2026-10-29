// The stage: the camera, the screen-space effects canvas (sparkles, specks, the hum's line), the
// grain, and the palette read from the tokens in film.css, so code never holds a colour of its own.

import { $, CX, CY, DEG, H, LIGHT, W, rgba, rng } from './lib.js';

export const PERSP = 2400;
export const world = $('#world');
// the frame's size is the format's (lib.js): the stage, the lens and the screen-space canvases
Object.assign($('#stage').style, { width: `${W}px`, height: `${H}px` });
$('#lens').style.perspectiveOrigin = `${CX}px ${CY}px`;
for (const c of document.querySelectorAll('.full')) Object.assign(c.style, { width: `${W}px`, height: `${H}px` });
$('#fx').width = W; $('#fx').height = H;
$('#grain').width = W / 2; $('#grain').height = H / 2;
export const fx = $('#fx').getContext('2d');

/* ---------- the palette, from the tokens ---------- */
const probe = document.createElement('canvas').getContext('2d');
function rgbOf(color) {
    probe.fillStyle = '#000';
    probe.fillStyle = color;
    const v = probe.fillStyle;
    if (v.startsWith('#')) return [1, 3, 5].map((i) => parseInt(v.slice(i, i + 2), 16));
    return v.match(/[\d.]+/g).slice(0, 3).map(Number);
}
const cs = getComputedStyle(document.documentElement);
const tok = (name) => rgbOf(cs.getPropertyValue(name).trim());
export const T = {
    ink: tok('--ink'), cream: tok('--cream'), accent: tok('--accent'), muted: tok('--muted'),
    parts: [tok('--p1'), tok('--p2'), tok('--p3'), tok('--p4')],
    // glows add light on the dark cut; on the light one they tint, at about half
    add: LIGHT ? 'source-over' : 'lighter',
    glow: LIGHT ? 0.45 : 1,
};
export const PART_VAR = ['var(--p1)', 'var(--p2)', 'var(--p3)', 'var(--p4)'];
export const PART_INK = ['var(--pt1)', 'var(--pt2)', 'var(--pt3)', 'var(--pt4)'];
export { rgba };

/* ---------- the camera ---------- */
// x, y: the world point at the centre of the frame; zoom; rx, ry, rz in degrees; dz pushes the
// world toward the lens.
let CAM = null;
export function setCam(c) {
    CAM = { x: CX, y: CY, dz: 0, rx: 0, ry: 0, rz: 0, zoom: 1, ...c };
    const k = CAM;
    world.style.transform = `translate3d(${CX}px,${CY}px,0) scale(${k.zoom.toFixed(5)}) rotateZ(${k.rz.toFixed(3)}deg) rotateX(${k.rx.toFixed(3)}deg) rotateY(${k.ry.toFixed(3)}deg) translate3d(${(-k.x).toFixed(2)}px,${(-k.y).toFixed(2)}px,${k.dz.toFixed(2)}px)`;
}
/** Where a world point lands on the frame, and its scale there. */
export function project(x, y, z = 0) {
    const c = CAM, X = x - c.x, Y = y - c.y, Z = z + c.dz;
    const cy = Math.cos(c.ry * DEG), sy = Math.sin(c.ry * DEG);
    const X2 = X * cy + Z * sy, Z2 = -X * sy + Z * cy;
    const cx = Math.cos(c.rx * DEG), sx = Math.sin(c.rx * DEG);
    const Y3 = Y * cx - Z2 * sx, Z3 = Y * sx + Z2 * cx;
    const cz = Math.cos(c.rz * DEG), sz = Math.sin(c.rz * DEG);
    const X4 = X2 * cz - Y3 * sz, Y4 = X2 * sz + Y3 * cz;
    // scale() is 2D: it leaves depth alone, so perspective divides by the unscaled depth
    const s = (PERSP / (PERSP - Z3)) * c.zoom;
    return [CX + X4 * s, CY + Y4 * s, s];
}

export function beginFrame() {
    fx.setTransform(1, 0, 0, 1, 0, 0);
    fx.clearRect(0, 0, W, H);
    fx.globalCompositeOperation = 'source-over';
    fx.globalAlpha = 1;
}

/* ---------- screen-space bits ---------- */
/** A hit's sparkle burst (glass.js sparkles(), larger): eight four-point glints. */
export function sparkles(cx, cy, age, rgb, s = 1, seed = 1, count = 8) {
    if (age < 0 || age > 0.6) return;
    const t = age / 0.6, g = fx;
    g.save();
    g.globalAlpha = 1 - t * t;
    for (let i = 0; i < count; i++) {
        const angle = (i / count) * Math.PI * 2 + seed * 0.7;
        const reach = (40 + ((i * 7 + seed * 13) % 9) * 3) * (0.35 + 1.1 * (1 - Math.pow(1 - t, 3))) * s;
        const px = cx + Math.cos(angle) * reach, py = cy + Math.sin(angle) * reach * 0.8;
        const size = 11 * (1 - t * 0.6) * s;
        g.fillStyle = i % 2 ? rgba(T.cream, 1) : rgba(rgb, 1);
        g.beginPath();
        g.moveTo(px, py - size); g.lineTo(px + size * 0.3, py - size * 0.3); g.lineTo(px + size, py);
        g.lineTo(px + size * 0.3, py + size * 0.3); g.lineTo(px, py + size); g.lineTo(px - size * 0.3, py + size * 0.3);
        g.lineTo(px - size, py); g.lineTo(px - size * 0.3, py - size * 0.3);
        g.closePath(); g.fill();
    }
    g.restore();
}

/** A soft round glow in screen space. */
export function glow(x, y, r, rgb, a) {
    if (a <= 0.003 || r <= 1) return;
    const g = fx;
    g.save();
    g.globalCompositeOperation = T.add;
    const gr = g.createRadialGradient(x, y, 0, x, y, r);
    gr.addColorStop(0, rgba(rgb, a * T.glow));
    gr.addColorStop(0.45, rgba(rgb, a * 0.35 * T.glow));
    gr.addColorStop(1, rgba(rgb, 0));
    g.fillStyle = gr;
    g.fillRect(x - r, y - r, r * 2, r * 2);
    g.restore();
}

// The specks in the accent (the lanes' stage, ux-spec 5.7c): motes on closed loops, so the last
// frame is the first. They live in the world, so they drift with the camera's depth.
const MOTES = (() => {
    const r = rng(31), out = [];
    for (let i = 0; i < 40; i++) out.push({ x: r() * 3600 - 800, y: r() * 1500 - 200, z: -600 + r() * 900, ax: 30 + r() * 80, ay: 20 + r() * 50, kx: 1 + Math.floor(r() * 2), ky: 1 + Math.floor(r() * 3), ph: r() * 6.283, s: 1.6 + r() * 2.6, a: 0.35 + r() * 0.65 });
    return out;
})();
export function specks(t, dur, k = 1, ox = 0) {
    if (k <= 0.003) return;
    const g = fx, w = (2 * Math.PI) / dur;
    g.save();
    g.globalCompositeOperation = T.add;
    for (const m of MOTES) {
        const [sx, sy, s] = project(ox + m.x + Math.sin(t * w * m.kx + m.ph) * m.ax, m.y + Math.cos(t * w * m.ky + m.ph * 1.7) * m.ay, m.z);
        if (sx < -60 || sx > W + 60 || sy < -60 || sy > H + 60) continue;
        const r = m.s * s * 2.4, a = (LIGHT ? 0.22 : 0.34) * m.a * k * (0.55 + 0.45 * Math.sin(t * w * 3 + m.ph * 2.3));
        const gr = g.createRadialGradient(sx, sy, 0, sx, sy, r);
        gr.addColorStop(0, rgba(T.accent, a));
        gr.addColorStop(1, rgba(T.accent, 0));
        g.fillStyle = gr;
        g.beginPath(); g.arc(sx, sy, r, 0, 6.2832); g.fill();
    }
    g.restore();
}

/* ---------- grain: just enough to stop the wide gradients banding in the encode ---------- */
const grc = $('#grain').getContext('2d');
const grainFrames = (() => {
    const out = [], r = rng(42);
    for (let f = 0; f < 4; f++) {
        const id = grc.createImageData(W / 2, H / 2), d = id.data;
        for (let i = 0; i < d.length; i += 4) { const v = r() * 255; d[i] = d[i + 1] = d[i + 2] = v; d[i + 3] = 255; }
        out.push(id);
    }
    return out;
})();
export const grain = (t) => grc.putImageData(grainFrames[Math.floor(t * 30) % 4], 0, 0);
