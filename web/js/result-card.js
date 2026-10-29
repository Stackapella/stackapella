// A lesson result to share (Hillel, from a beta singer: "everyone wants to show off how good they
// did"): an image made on the device, on a canvas, in the app's theme and Figtree, never sent to
// us and never with a face. A name only when the person types one ("Put your name on it?",
// onboarding step 6), never filled in for someone under 18. Also your note's card (lesson 1).
// Two sizes: 1080 by 1350 for a post, 1080 by 1920 for a story. On it: the stars as glass gems,
// the word ("Brilliant!"), how it went, the lesson, the pitch trace in the part's colour, and the
// brand (the four-bar mark, "Harmony, made by you.", the site) with "Can you match it?".

import { shades } from './glass.js';

export const SIZES = { post: { width: 1080, height: 1350 }, story: { width: 1080, height: 1920 } };
// The mark's four bars (web/icons/icon.svg): widths of 294 in a 512 box, top to bottom.
const MARK = [['#ff9139', 294], ['#ffea6b', 217.56], ['#45eca4', 258.72], ['#968dfd', 182.28]];
const STAR = 'M12 3.8 14.5 8.9 20.1 9.7 16.1 13.6 17.1 19.2 12 16.5 6.9 19.2 7.9 13.6 3.9 9.7 9.5 8.9Z';
const FONT = 'Figtree, system-ui, sans-serif'; // i18n-ignore: a font stack

/** The theme's colours as the page has them now. */
function theme() {
    const style = getComputedStyle(document.documentElement);
    const read = (name, fallback) => style.getPropertyValue(name).trim() || fallback;
    return {
        ink: read('--color-ink', '#000000'),
        panel: read('--color-panel', '#141414'),
        cream: read('--color-cream', '#f4eee6'),
        muted: read('--color-muted', '#a59c92'),
        accent: read('--color-accent', '#ff79da'),
        light: document.documentElement.dataset.theme === 'light' || (!document.documentElement.dataset.theme && matchMedia('(prefers-color-scheme: light)').matches),
    };
}

/** A round glass gem (the star discs): lit at the top, deeper below, a rim and a gloss; dim when not earned. */
function gemDisc(g, cx, cy, r, color, { earned, light, star }) {
    const s = shades(color, { light });
    g.save();
    if (earned) {
        g.shadowColor = s.glow;
        g.shadowBlur = r * 0.6;
    }
    const body = g.createLinearGradient(0, cy - r, 0, cy + r);
    body.addColorStop(0, earned ? s.top : s.dimTop);
    body.addColorStop(0.55, earned ? s.body : s.dimTop);
    body.addColorStop(1, earned ? s.bottom : s.dimBottom);
    g.beginPath();
    g.arc(cx, cy, r, 0, Math.PI * 2);
    g.fillStyle = body;
    g.fill();
    g.shadowBlur = 0;
    g.lineWidth = r * 0.06;
    g.strokeStyle = earned ? s.edge : s.dimEdge;
    g.stroke();
    // The gloss along the top.
    const gloss = g.createLinearGradient(0, cy - r, 0, cy);
    gloss.addColorStop(0, 'rgba(255,255,255,0.55)');
    gloss.addColorStop(1, 'rgba(255,255,255,0)');
    g.beginPath();
    g.ellipse(cx, cy - r * 0.45, r * 0.62, r * 0.38, 0, 0, Math.PI * 2);
    g.fillStyle = gloss;
    g.fill();
    // The star on it.
    g.translate(cx - r * 0.62, cy - r * 0.64);
    g.scale((r * 1.24) / 24, (r * 1.24) / 24);
    g.fillStyle = earned ? s.ink : 'rgba(255,255,255,0.35)';
    g.fill(new Path2D(star));
    g.restore();
}

function rounded(g, x, y, w, h, r) {
    g.beginPath();
    g.roundRect(x, y, w, h, r);
}

/** Text that fits `max` wide: smaller until it does (never below `least`). */
function fitText(g, text, x, y, { size, weight = 800, max, least = size * 0.6, color, align = 'left' }) {
    let px = size;
    g.font = `${weight} ${px}px ${FONT}`;
    while (px > least && g.measureText(text).width > max) {
        px -= 2;
        g.font = `${weight} ${px}px ${FONT}`;
    }
    g.fillStyle = color;
    g.textAlign = align;
    g.fillText(text, x, y);
    return px;
}

/** The take's pitch against the notes (as the result screen draws it), in `box`. */
function trace(g, box, result, color, colors) {
    const notes = result.target ?? [];
    const dots = result.dots ?? [];
    if (!notes.length) return;
    const start = Math.min(notes[0].start, ...dots.map((d) => d.t)) - 0.2;
    const end = Math.max(Math.max(...notes.map((n) => n.end), ...dots.map((d) => d.t)) + 0.2, start + 1);
    const pitches = notes.map((n) => n.pitch);
    const lo = Math.min(...pitches) - 2;
    const hi = Math.max(Math.max(...pitches) + 2, lo + 7);
    const x = (t) => box.x + ((t - start) / (end - start)) * box.w;
    const y = (midi) => box.y + (1 - (Math.max(lo, Math.min(hi, midi)) - lo) / (hi - lo)) * box.h;
    const half = (result.cents ?? 50) / 100;
    g.save();
    for (const n of notes) {
        const top = y(n.pitch + half);
        g.fillStyle = shades(color).faint.replace(/[\d.]+\)$/, '0.3)');
        rounded(g, x(n.start), top, x(n.end) - x(n.start), y(n.pitch - half) - top, 10);
        g.fill();
    }
    for (const d of dots) {
        g.fillStyle = d.in ? color : colors.muted;
        g.globalAlpha = d.in ? 1 : 0.45;
        rounded(g, x(d.t) - 8, y(d.midi) - 7, 16, 14, 7);
        g.fill();
    }
    g.restore();
}

/** The brand at the foot: the four-bar mark, the name and line, and the site. */
function brand(g, { x, y, colors, tagline, site }) {
    const unit = 60 / 512;
    g.save();
    g.fillStyle = '#000000';
    rounded(g, x, y, 60, 60, 12);
    g.fill();
    MARK.forEach(([fill, width], i) => {
        g.fillStyle = fill;
        rounded(g, x + 109 * unit, y + (172 + i * 46.2) * unit, width * unit, 29.4 * unit, 14.7 * unit);
        g.fill();
    });
    g.textAlign = 'left';
    g.fillStyle = colors.cream;
    g.font = `800 34px ${FONT}`;
    g.fillText('Stackapella', x + 80, y + 28); // i18n-ignore: the brand's name
    g.fillStyle = colors.muted;
    g.font = `500 26px ${FONT}`;
    g.fillText(`${tagline} · ${site}`, x + 80, y + 60);
    g.restore();
}

/**
 * Draws the card. `words`: { word, line, stats, lesson, unit, challenge, tagline, site } (already in
 * the person's language); `result` the take's result (stars, dots, target, cents); `color` the part's.
 * Returns a PNG Blob.
 */
export async function drawResultCard({ size = 'post', words, result, color }) {
    const { width, height } = SIZES[size];
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const g = canvas.getContext('2d');
    await Promise.all([document.fonts?.load(`800 96px ${FONT}`), document.fonts?.load(`500 32px ${FONT}`)]).catch((error) => console.info('Figtree not ready; the card uses the system font', error?.message ?? error));
    const colors = theme();
    const pad = 84;
    const story = size === 'story';

    // The ground: the theme's ink, with a soft glow of the part's colour behind the stars.
    g.fillStyle = colors.ink;
    g.fillRect(0, 0, width, height);
    const glow = g.createRadialGradient(width / 2, story ? 520 : 380, 40, width / 2, story ? 520 : 380, width * 0.7);
    glow.addColorStop(0, shades(color).faint.replace(/[\d.]+\)$/, '0.32)'));
    glow.addColorStop(1, 'rgba(0,0,0,0)');
    g.fillStyle = glow;
    g.fillRect(0, 0, width, height);

    let y = story ? 360 : 170;
    // The stars, the middle one biggest (game mode's; serious mode's card is the measure alone).
    if (words.stars !== false) {
        const stars = result.stars ?? 0;
        [[-1, 78], [0, 108], [1, 78]].forEach(([at, r], i) => gemDisc(g, width / 2 + at * 230, y + (i === 1 ? 0 : 30), r, colors.accent, { earned: i < stars, light: colors.light, star: STAR }));
        y += 250;
    } else y += 120;
    fitText(g, words.word, width / 2, y, { size: 120, max: width - pad * 2, color: colors.cream, align: 'center' });
    y += 76;
    fitText(g, words.stats, width / 2, y, { size: 44, weight: 600, max: width - pad * 2, color: colors.cream, align: 'center' });
    y += 58;
    fitText(g, words.lesson, width / 2, y, { size: 34, weight: 500, max: width - pad * 2, color: colors.muted, align: 'center' });
    if (words.by) {
        y += 50;
        fitText(g, words.by, width / 2, y, { size: 34, weight: 700, max: width - pad * 2, color: colors.cream, align: 'center' });
    }

    // The pitch trace on a panel.
    y += story ? 90 : 60;
    const panelH = story ? 660 : 400;
    g.fillStyle = colors.panel;
    rounded(g, pad, y, width - pad * 2, panelH, 40);
    g.fill();
    trace(g, { x: pad + 40, y: y + 40, w: width - pad * 2 - 80, h: panelH - 80 }, result, color, colors);
    y += panelH;

    // The challenge, then the brand at the foot.
    if (words.challenge) fitText(g, words.challenge, width / 2, y + (story ? 190 : 110), { size: 56, max: width - pad * 2, color: colors.accent, align: 'center' });
    brand(g, { x: pad, y: height - pad - 60, colors, tagline: words.tagline, site: words.site });

    return new Promise((resolve, reject) => canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('The card could not be drawn'))), 'image/png'));
}

/**
 * Your note's card (lesson 1's end, onboarding step 6): "My voice sits around", the note big in a
 * glow of your colour, what it suits, your name if you typed one, and the brand. `words`: { title,
 * note, line, by, tagline, site }. Returns a PNG Blob.
 */
export async function drawNoteCard({ size = 'post', words, color }) {
    const { width, height } = SIZES[size];
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const g = canvas.getContext('2d');
    await Promise.all([document.fonts?.load(`800 96px ${FONT}`), document.fonts?.load(`500 32px ${FONT}`)]).catch((error) => console.info('Figtree not ready; the card uses the system font', error?.message ?? error));
    const colors = theme();
    const pad = 84;
    const middle = size === 'story' ? 820 : 600;
    g.fillStyle = colors.ink;
    g.fillRect(0, 0, width, height);
    const glow = g.createRadialGradient(width / 2, middle, 40, width / 2, middle, width * 0.7);
    glow.addColorStop(0, shades(color).faint.replace(/[\d.]+\)$/, '0.4)'));
    glow.addColorStop(1, 'rgba(0,0,0,0)');
    g.fillStyle = glow;
    g.fillRect(0, 0, width, height);
    fitText(g, words.title, width / 2, middle - 230, { size: 56, weight: 600, max: width - pad * 2, color: colors.muted, align: 'center' });
    fitText(g, words.note, width / 2, middle + 110, { size: 300, max: width - pad * 2, color: colors.cream, align: 'center' });
    fitText(g, words.line, width / 2, middle + 230, { size: 46, weight: 600, max: width - pad * 2, color: colors.cream, align: 'center' });
    if (words.by) fitText(g, words.by, width / 2, middle + 300, { size: 36, weight: 700, max: width - pad * 2, color: colors.accent, align: 'center' });
    brand(g, { x: pad, y: height - pad - 60, colors, tagline: words.tagline, site: words.site });
    return new Promise((resolve, reject) => canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('The card could not be drawn'))), 'image/png'));
}
