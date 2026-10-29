// Celebrating stars when they're new (Hillel: 2 stars a small celebration, 3 the full burst): a
// short burst of confetti on a canvas over the page, in the accent and the part colours, about
// 1.5 s, then gone. With reduced motion, a gentle glow on the stars instead (stronger for 3). Only
// called when a result has just been earned, never when an old one is looked at again. No library.

export const LEVELS = {
    2: { pieces: 22, spread: 0.7, speed: 0.55, sparkle: true, glow: 'celebrate-glow' },
    3: { pieces: 110, spread: 0.95, speed: 1, sparkle: false, glow: 'celebrate-glow-strong' },
};
const SECONDS = 1.5;
const GRAVITY = 1400; // px/s²

const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

/** A small seeded random, so a burst is the same in tests. */
function seeded(seed) {
    let a = seed >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = Math.imul(a ^ (a >>> 15), a | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

/**
 * The pieces of a burst from (`x`, `y`): `{ x, y, vx, vy, spin, angle, size, color, shape }`, in px
 * and px/s. Pure: tests check a bigger level throws more, faster.
 */
export function pieces(level, { x, y, colors, seed = 7 }) {
    const look = LEVELS[level];
    if (!look) return [];
    const random = seeded(seed);
    return Array.from({ length: look.pieces }, () => {
        const angle = -Math.PI / 2 + (random() - 0.5) * Math.PI * look.spread;
        const speed = (520 + random() * 620) * look.speed;
        return {
            x,
            y,
            vx: Math.cos(angle) * speed,
            vy: Math.sin(angle) * speed,
            angle: random() * Math.PI * 2,
            spin: (random() - 0.5) * 14,
            size: 6 + random() * 6,
            color: colors[Math.floor(random() * colors.length)],
            shape: random() < 0.35 ? 'dot' : 'strip',
        };
    });
}

/** The accent and the eight part colours, as the theme has them now. */
function themeColours() {
    const style = getComputedStyle(document.documentElement);
    const read = (name) => style.getPropertyValue(name).trim();
    return [read('--color-accent'), ...Array.from({ length: 8 }, (_, i) => read(`--color-part-${i + 1}`))].filter(Boolean);
}

/**
 * Celebrates a new `level` (2 or 3) from `anchor` (the stars' element): confetti, or with reduced
 * motion a glow on `anchor`. Returns 'confetti', 'glow' or null (nothing for fewer stars).
 */
export function celebrate(level, anchor = null) {
    if (document.documentElement.dataset.skin === 'serious') return null; // a game mode reward (docs/skins-plan.md)
    const look = LEVELS[level];
    if (!look) return null;
    if (reducedMotion()) {
        anchor?.classList.add(look.glow);
        setTimeout(() => anchor?.classList.remove(look.glow), SECONDS * 1000 + 600);
        return 'glow';
    }
    const box = anchor?.getBoundingClientRect();
    const x = box ? box.left + box.width / 2 : innerWidth / 2;
    const y = box ? box.top + box.height / 2 : innerHeight / 3;
    const canvas = document.createElement('canvas');
    canvas.dataset.confetti = String(level);
    canvas.setAttribute('aria-hidden', 'true');
    Object.assign(canvas.style, { position: 'fixed', inset: '0', width: '100%', height: '100%', pointerEvents: 'none', zIndex: '60' });
    const ratio = Math.min(2, devicePixelRatio || 1);
    canvas.width = innerWidth * ratio;
    canvas.height = innerHeight * ratio;
    document.body.append(canvas);
    const g = canvas.getContext('2d');
    g.scale(ratio, ratio);
    const flying = pieces(level, { x, y, colors: themeColours(), seed: Math.floor(Math.random() * 1e9) });
    const start = performance.now();
    let last = start;
    const frame = (now) => {
        const t = (now - start) / 1000;
        const dt = Math.min(0.05, (now - last) / 1000);
        last = now;
        g.clearRect(0, 0, innerWidth, innerHeight);
        const fade = t < SECONDS - 0.4 ? 1 : Math.max(0, (SECONDS - t) / 0.4);
        for (const p of flying) {
            p.vy += GRAVITY * dt * 0.5;
            p.vx *= 0.985;
            p.x += p.vx * dt;
            p.y += p.vy * dt;
            p.angle += p.spin * dt;
            g.save();
            g.globalAlpha = fade;
            g.fillStyle = p.color;
            g.translate(p.x, p.y);
            g.rotate(p.angle);
            if (p.shape === 'dot') {
                g.beginPath();
                g.arc(0, 0, p.size / 2.6, 0, Math.PI * 2);
                g.fill();
            } else {
                g.fillRect(-p.size / 2, -p.size / 5, p.size, p.size / 2.5);
            }
            g.restore();
        }
        if (t < SECONDS) requestAnimationFrame(frame);
        else canvas.remove();
    };
    requestAnimationFrame(frame);
    if (look.sparkle) {
        anchor?.classList.add('celebrate-sparkle');
        setTimeout(() => anchor?.classList.remove('celebrate-sparkle'), 900);
    }
    return 'confetti';
}
