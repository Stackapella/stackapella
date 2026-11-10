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
// Game mode's arcade card (its fonts in css/input.css): Bungee for the word and NEW RECORD, the pixel face (Stackapella Pixel) for the score line.
const DISPLAY = 'Bungee, Figtree, system-ui, sans-serif'; // i18n-ignore: a font stack
const HUD = '"Stackapella Pixel", ui-monospace, monospace'; // i18n-ignore: a font stack

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
        yellow: read('--ge-yellow', '#ffd84a'),
        parts: [1, 2, 3, 4].map((i) => read(`--color-part-ink-${i}`, MARK[i - 1][0])), // the parts' ink: readable on the theme's ground
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
function fitText(g, text, x, y, { size, weight = 800, max, least = size * 0.6, color, align = 'left', family = FONT }) {
    let px = size;
    g.font = `${weight} ${px}px ${family}`;
    while (px > least && g.measureText(text).width > max) {
        px -= 2;
        g.font = `${weight} ${px}px ${family}`;
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

/**
 * A song's take (today's challenge, js/components/daily-share.js): each line's percent as a bar, in the part's colour
 * where it was on pitch, rising from the panel's floor, so the card shows how the song went line by line.
 */
function phraseBars(g, box, phrases, color, colors) {
    const sung = phrases.filter((p) => p.percent !== null && p.percent !== undefined);
    if (!sung.length) return;
    const gap = Math.min(24, box.w / sung.length / 4);
    const w = (box.w - gap * (sung.length - 1)) / sung.length;
    g.save();
    sung.forEach((p, i) => {
        const h = Math.max(12, (box.h * p.percent) / 100);
        g.fillStyle = shades(color).faint.replace(/[\d.]+\)$/, '0.3)');
        rounded(g, box.x + i * (w + gap), box.y, w, box.h, Math.min(14, w / 2));
        g.fill();
        g.fillStyle = p.percent >= 70 ? color : colors.muted;
        rounded(g, box.x + i * (w + gap), box.y + box.h - h, w, h, Math.min(14, w / 2));
        g.fill();
    });
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

/** NEW RECORD in the game's display type, its letters in the four parts' ink in turn (as on screen, game/record.js). */
function recordWord(g, text, cx, y, max, inks) {
    let px = 76;
    g.font = `400 ${px}px ${DISPLAY}`;
    while (px > 40 && g.measureText(text).width > max) g.font = `400 ${(px -= 2)}px ${DISPLAY}`;
    let x = cx - g.measureText(text).width / 2;
    g.save();
    g.textAlign = 'left';
    let n = 0;
    for (const char of text) {
        g.fillStyle = char === ' ' ? 'transparent' : inks[n++ % inks.length];
        g.fillText(char, x, y);
        x += g.measureText(char).width;
    }
    g.restore();
}

/**
 * Draws the card. `words`: { word, line, stats, lesson, unit, challenge, tagline, site } (already in
 * the person's language), and in game mode `arcade` (the game's type and a CRT's faint lines) with
 * `record` (NEW RECORD, only when this take beat an earlier best); `result` the take's result
 * (stars, dots, target, cents); `color` the part's. Returns a PNG Blob.
 */
export async function drawResultCard({ size = 'post', words, result, color }) {
    const { width, height } = SIZES[size];
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const g = canvas.getContext('2d');
    await Promise.all([document.fonts?.load(`800 96px ${FONT}`), document.fonts?.load(`500 32px ${FONT}`)]).catch((error) => console.info('Figtree not ready; the card uses the system font', error?.message ?? error));
    const arcade = !!words.arcade;
    if (arcade) await Promise.all([document.fonts?.load(`400 96px ${DISPLAY}`), document.fonts?.load(`400 30px ${HUD}`)]).catch((error) => console.info('The game fonts are not ready; the card uses Figtree', error?.message ?? error));
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
    if (arcade) {
        // A CRT's faint lines across the ground.
        g.fillStyle = colors.light ? 'rgba(40,25,10,0.035)' : 'rgba(255,255,255,0.03)';
        for (let line = 0; line < height; line += 6) g.fillRect(0, line, width, 2);
    }

    let y = story ? 360 : 170;
    const recordRoom = arcade && words.record ? 130 : 0;
    if (recordRoom) {
        recordWord(g, words.record, width / 2, y - 40, width - pad * 2, colors.parts);
        y += recordRoom;
    }
    // The stars, the middle one biggest (game mode's; serious mode's card is the measure alone).
    if (words.stars !== false) {
        const stars = result.stars ?? 0;
        [[-1, 78], [0, 108], [1, 78]].forEach(([at, r], i) => gemDisc(g, width / 2 + at * 230, y + (i === 1 ? 0 : 30), r, colors.accent, { earned: i < stars, light: colors.light, star: STAR }));
        y += 250;
    } else y += 120;
    if (arcade) fitText(g, words.word.toLocaleUpperCase(), width / 2, y, { size: 112, weight: 400, max: width - pad * 2, color: colors.cream, align: 'center', family: DISPLAY });
    else fitText(g, words.word, width / 2, y, { size: 120, max: width - pad * 2, color: colors.cream, align: 'center' });
    y += 76;
    if (arcade) fitText(g, words.stats.toLocaleUpperCase(), width / 2, y, { size: 30, weight: 400, least: 16, max: width - pad * 2, color: colors.yellow, align: 'center', family: HUD });
    else fitText(g, words.stats, width / 2, y, { size: 44, weight: 600, max: width - pad * 2, color: colors.cream, align: 'center' });
    y += 58;
    fitText(g, words.lesson, width / 2, y, { size: 34, weight: 500, max: width - pad * 2, color: colors.muted, align: 'center' });
    // A streak milestone that day (7, 30, 100 days): one more line, not one more invitation (js/share-moment.js).
    if (words.streak) {
        y += 50;
        fitText(g, words.streak, width / 2, y, { size: 34, weight: 700, max: width - pad * 2, color: colors.yellow, align: 'center' });
    }
    if (words.by) {
        y += 50;
        fitText(g, words.by, width / 2, y, { size: 34, weight: 700, max: width - pad * 2, color: colors.cream, align: 'center' });
    }

    // The pitch trace on a panel.
    y += story ? 90 : 60;
    const panelH = (story ? 660 : 400) - recordRoom;
    g.fillStyle = colors.panel;
    rounded(g, pad, y, width - pad * 2, panelH, 40);
    g.fill();
    const inside = { x: pad + 40, y: y + 40, w: width - pad * 2 - 80, h: panelH - 80 };
    if (result.target?.length) trace(g, inside, result, color, colors);
    else phraseBars(g, inside, result.phrases ?? [], color, colors);
    y += panelH;

    // The challenge, then the brand at the foot.
    if (words.challenge && arcade) fitText(g, words.challenge.toLocaleUpperCase(), width / 2, y + (story ? 190 : 110), { size: 38, weight: 400, least: 18, max: width - pad * 2, color: colors.accent, align: 'center', family: HUD });
    else if (words.challenge) fitText(g, words.challenge, width / 2, y + (story ? 190 : 110), { size: 56, max: width - pad * 2, color: colors.accent, align: 'center' });
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
    const arcade = !!words.arcade;
    if (arcade) await Promise.all([document.fonts?.load(`400 96px ${DISPLAY}`), document.fonts?.load(`400 30px ${HUD}`)]).catch((error) => console.info('The game fonts are not ready; the card uses Figtree', error?.message ?? error));
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

/** Whether `midi` is a black key. */
const black = (midi) => [1, 3, 6, 8, 10].includes(((midi % 12) + 12) % 12);

/**
 * A keyboard from C2 (or the C below `low`) to C6 (or the C above `high`), the range's keys lit in `color`, with the
 * lowest and highest named under them.
 */
function rangeKeys(g, box, { low, high, lowName, highName }, color, colors) {
    const from = Math.min(36, Math.floor(low / 12) * 12);
    const to = Math.max(84, Math.ceil((high + 1) / 12) * 12);
    const whites = [];
    for (let m = from; m <= to; m++) if (!black(m)) whites.push(m);
    const w = box.w / whites.length;
    const keyH = box.h - 60;
    const x = (m) => box.x + whites.indexOf(black(m) ? m - 1 : m) * w + (black(m) ? w * 0.68 : 0);
    const lit = (m) => m >= low && m <= high;
    const s = shades(color, { light: colors.light });
    g.save();
    for (const m of whites) {
        g.fillStyle = lit(m) ? color : colors.light ? '#ffffff' : colors.cream;
        rounded(g, x(m) + 2, box.y, w - 4, keyH, Math.min(10, w / 4));
        g.fill();
    }
    for (let m = from; m <= to; m++) {
        if (!black(m)) continue;
        g.fillStyle = lit(m) ? s.bottom : '#1a1a1a';
        rounded(g, x(m), box.y, w * 0.64, keyH * 0.62, Math.min(8, w / 5));
        g.fill();
    }
    // The lowest and highest notes named under their keys.
    for (const [m, label] of [[low, lowName], [high, highName]]) {
        const cx = x(m) + (black(m) ? w * 0.32 : w / 2);
        fitText(g, label, Math.max(box.x + 50, Math.min(box.x + box.w - 50, cx)), box.y + keyH + 48, { size: 36, weight: 700, max: 160, color: colors.cream, align: 'center' });
    }
    g.restore();
}

/**
 * Your vocal range's card (the free range test's Share my range, js/public/tools/vocal-range-test.js): "My vocal range",
 * the voice type big, the range and its span, a keyboard with the range lit, "What's yours?" and the brand. Made on the
 * device and never sent to us. `words`: { title, type, range, span, lowName, highName, challenge, tagline, site, arcade };
 * `low` and `high` MIDI; `color` the type's part ink. Returns a PNG Blob.
 */
export async function drawRangeCard({ size = 'post', words, low, high, color }) {
    const { width, height } = SIZES[size];
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const g = canvas.getContext('2d');
    await Promise.all([document.fonts?.load(`800 96px ${FONT}`), document.fonts?.load(`500 32px ${FONT}`)]).catch((error) => console.info('Figtree not ready; the card uses the system font', error?.message ?? error));
    const arcade = !!words.arcade;
    if (arcade) await Promise.all([document.fonts?.load(`400 96px ${DISPLAY}`), document.fonts?.load(`400 30px ${HUD}`)]).catch((error) => console.info('The game fonts are not ready; the card uses Figtree', error?.message ?? error));
    const colors = theme();
    const pad = 84;
    const story = size === 'story';
    const top = story ? 420 : 230;
    g.fillStyle = colors.ink;
    g.fillRect(0, 0, width, height);
    const glow = g.createRadialGradient(width / 2, top + 160, 40, width / 2, top + 160, width * 0.7);
    glow.addColorStop(0, shades(color).faint.replace(/[\d.]+\)$/, '0.36)'));
    glow.addColorStop(1, 'rgba(0,0,0,0)');
    g.fillStyle = glow;
    g.fillRect(0, 0, width, height);
    if (arcade) {
        g.fillStyle = colors.light ? 'rgba(40,25,10,0.035)' : 'rgba(255,255,255,0.03)';
        for (let line = 0; line < height; line += 6) g.fillRect(0, line, width, 2);
    }
    let y = top;
    fitText(g, words.title, width / 2, y, { size: 52, weight: 600, max: width - pad * 2, color: colors.muted, align: 'center' });
    y += 180;
    if (arcade) fitText(g, words.type.toLocaleUpperCase(), width / 2, y, { size: 130, weight: 400, max: width - pad * 2, color, align: 'center', family: DISPLAY });
    else fitText(g, words.type, width / 2, y, { size: 160, max: width - pad * 2, color, align: 'center' });
    y += 110;
    fitText(g, words.range, width / 2, y, { size: 72, weight: 800, max: width - pad * 2, color: colors.cream, align: 'center' });
    y += 64;
    fitText(g, words.span, width / 2, y, { size: 38, weight: 500, max: width - pad * 2, color: colors.muted, align: 'center' });
    y += story ? 120 : 70;
    const panelH = story ? 420 : 330;
    g.fillStyle = colors.panel;
    rounded(g, pad, y, width - pad * 2, panelH, 40);
    g.fill();
    rangeKeys(g, { x: pad + 36, y: y + 40, w: width - pad * 2 - 72, h: panelH - 70 }, { low, high, lowName: words.lowName, highName: words.highName }, color, colors);
    y += panelH;
    // The dare in game mode's HUD type, as the lesson result card's (the game agent's pass, 2026-11-08).
    if (arcade) fitText(g, words.challenge.toLocaleUpperCase(), width / 2, y + (story ? 200 : 110), { size: 38, weight: 400, least: 18, max: width - pad * 2, color: colors.accent, align: 'center', family: HUD });
    else fitText(g, words.challenge, width / 2, y + (story ? 200 : 110), { size: 56, max: width - pad * 2, color: colors.accent, align: 'center' });
    brand(g, { x: pad, y: height - pad - 60, colors, tagline: words.tagline, site: words.site });
    return new Promise((resolve, reject) => canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('The card could not be drawn'))), 'image/png'));
}

/** Words broken into at most `lines` lines that fit `max` wide at `px` (the last one shortened with an ellipsis). */
function wrapLines(g, text, max, lines) {
    const out = [];
    let line = '';
    for (const word of String(text).split(/\s+/)) {
        const next = line ? `${line} ${word}` : word;
        if (g.measureText(next).width <= max || !line) line = next;
        else {
            out.push(line);
            line = word;
        }
    }
    if (line) out.push(line);
    if (out.length > lines) {
        const kept = out.slice(0, lines);
        let last = kept[lines - 1];
        while (last && g.measureText(`${last}…`).width > max) last = last.slice(0, -1);
        kept[lines - 1] = `${last.trimEnd()}…`;
        return kept;
    }
    return out;
}

/**
 * A free tool's score to share (pitch matching's round, an ear trainer's last ten): the range card's layout, with the
 * tool's name, the score big, a line under it, and each answer as a gem in a panel (lit when right, dim when not), ten
 * in two rows of five. `marks` is a list of booleans. Made on the device, like every card here.
 */
export async function drawScoreCard({ size = 'post', words, marks = [], color }) {
    const { width, height } = SIZES[size];
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const g = canvas.getContext('2d');
    await Promise.all([document.fonts?.load(`800 96px ${FONT}`), document.fonts?.load(`500 32px ${FONT}`)]).catch((error) => console.info('Figtree not ready; the card uses the system font', error?.message ?? error));
    const arcade = !!words.arcade;
    if (arcade) await Promise.all([document.fonts?.load(`400 96px ${DISPLAY}`), document.fonts?.load(`400 30px ${HUD}`)]).catch((error) => console.info('The game fonts are not ready; the card uses Figtree', error?.message ?? error));
    const colors = theme();
    const pad = 84;
    const story = size === 'story';
    const top = story ? 420 : 230;
    g.fillStyle = colors.ink;
    g.fillRect(0, 0, width, height);
    const glow = g.createRadialGradient(width / 2, top + 160, 40, width / 2, top + 160, width * 0.7);
    glow.addColorStop(0, shades(color).faint.replace(/[\d.]+\)$/, '0.36)'));
    glow.addColorStop(1, 'rgba(0,0,0,0)');
    g.fillStyle = glow;
    g.fillRect(0, 0, width, height);
    if (arcade) {
        g.fillStyle = colors.light ? 'rgba(40,25,10,0.035)' : 'rgba(255,255,255,0.03)';
        for (let line = 0; line < height; line += 6) g.fillRect(0, line, width, 2);
    }
    let y = top;
    fitText(g, words.title, width / 2, y, { size: 52, weight: 600, max: width - pad * 2, color: colors.muted, align: 'center' });
    y += 180;
    if (arcade) fitText(g, words.score.toLocaleUpperCase(), width / 2, y, { size: 130, weight: 400, max: width - pad * 2, color, align: 'center', family: DISPLAY });
    else fitText(g, words.score, width / 2, y, { size: 160, max: width - pad * 2, color, align: 'center' });
    y += 110;
    fitText(g, words.line, width / 2, y, { size: 56, weight: 800, max: width - pad * 2, color: colors.cream, align: 'center' });
    y += 64;
    if (words.sub) fitText(g, words.sub, width / 2, y, { size: 38, weight: 500, max: width - pad * 2, color: colors.muted, align: 'center' });
    y += story ? 120 : 70;
    const panelH = story ? 420 : 330;
    g.fillStyle = colors.panel;
    rounded(g, pad, y, width - pad * 2, panelH, 40);
    g.fill();
    // The answers: two rows of five gems, in the order they came.
    const perRow = 5;
    const rows = Math.max(1, Math.ceil(marks.length / perRow));
    const r = Math.min(58, (panelH - 80) / (rows * 2 + (rows - 1) * 0.5));
    const step = (width - pad * 2 - 160) / (perRow - 1);
    marks.forEach((earned, i) => {
        const row = Math.floor(i / perRow);
        const cx = pad + 80 + (i % perRow) * step;
        const cy = y + panelH / 2 + (row - (rows - 1) / 2) * r * 2.5;
        gemDisc(g, cx, cy, r, color, { earned: !!earned, light: colors.light, star: STAR });
    });
    y += panelH;
    // The dare in game mode's HUD type, as the lesson result card's (the game agent's pass, 2026-11-08).
    if (arcade) fitText(g, words.challenge.toLocaleUpperCase(), width / 2, y + (story ? 200 : 110), { size: 38, weight: 400, least: 18, max: width - pad * 2, color: colors.accent, align: 'center', family: HUD });
    else fitText(g, words.challenge, width / 2, y + (story ? 200 : 110), { size: 56, max: width - pad * 2, color: colors.accent, align: 'center' });
    brand(g, { x: pad, y: height - pad - 60, colors, tagline: words.tagline, site: words.site });
    return new Promise((resolve, reject) => canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('The card could not be drawn'))), 'image/png'));
}

/**
 * A lesson moment's card (docs/design-rules.md, "Sharing in lessons"): a unit or a course finished, or Then and now. A small
 * title ("Unit done"), the unit's or course's name big (up to three lines), a line under it (the course, or "Then 45% · now
 * 85%"), the name if one was typed, the dare, and the brand. Made on the device, never sent to us. `words`: { title, headline,
 * line, by, challenge, tagline, site, arcade }. Returns a PNG Blob.
 */
export async function drawMilestoneCard({ size = 'post', words, color }) {
    const { width, height } = SIZES[size];
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const g = canvas.getContext('2d');
    await Promise.all([document.fonts?.load(`800 96px ${FONT}`), document.fonts?.load(`500 32px ${FONT}`)]).catch((error) => console.info('Figtree not ready; the card uses the system font', error?.message ?? error));
    const arcade = !!words.arcade;
    if (arcade) await document.fonts?.load(`400 96px ${DISPLAY}`).catch((error) => console.info('The game font is not ready; the card uses Figtree', error?.message ?? error));
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
    if (arcade) {
        g.fillStyle = colors.light ? 'rgba(40,25,10,0.035)' : 'rgba(255,255,255,0.03)';
        for (let line = 0; line < height; line += 6) g.fillRect(0, line, width, 2);
    }
    fitText(g, arcade ? words.title.toLocaleUpperCase() : words.title, width / 2, middle - 260, { size: 56, weight: arcade ? 400 : 700, max: width - pad * 2, color, align: 'center', family: arcade ? DISPLAY : FONT });
    // The name, big, on up to three lines, centred on the middle.
    const px = 104;
    g.font = `800 ${px}px ${FONT}`;
    const lines = wrapLines(g, words.headline, width - pad * 2, 3);
    const lead = px * 1.12;
    let y = middle - ((lines.length - 1) * lead) / 2 + px * 0.35;
    g.fillStyle = colors.cream;
    g.textAlign = 'center';
    for (const line of lines) {
        g.fillText(line, width / 2, y);
        y += lead;
    }
    y += 10;
    fitText(g, words.line, width / 2, y, { size: 46, weight: 600, max: width - pad * 2, color: colors.muted, align: 'center' });
    if (words.by) fitText(g, words.by, width / 2, y + 70, { size: 36, weight: 700, max: width - pad * 2, color: colors.accent, align: 'center' });
    if (words.challenge) fitText(g, words.challenge, width / 2, height - pad - 170, { size: 52, max: width - pad * 2, color: colors.accent, align: 'center' });
    brand(g, { x: pad, y: height - pad - 60, colors, tagline: words.tagline, site: words.site });
    return new Promise((resolve, reject) => canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('The card could not be drawn'))), 'image/png'));
}
