// Colours in the chosen theme (docs/decisions.md, "themes"). A part keeps its colour as its place in
// the eight (stored as today's hex, PART_COLORS, so projects and the account stay as they are); each
// theme paints that place in its own shade (css/themes.css). `paint` gives the shade showing now, for
// a style or a canvas; `partVar` the CSS variable, which follows a theme change by itself. No Vue
// here: the server's pages use it too.

const PART_COLORS = ['#f2a06b', '#c9b456', '#93c26e', '#4ccfb0', '#3ec5e2', '#7db0f7', '#b9a3f5', '#e58fcb'];
const cache = new Map();
let cachedFor = null;

/**
 * The place (0 to 7) of a part colour: a stored one (today's hex), or one the theme showing has
 * already painted (so painting twice is harmless); -1 for any other colour.
 */
export function partIndex(color) {
    const hex = String(color ?? '').toLowerCase();
    const stored = PART_COLORS.indexOf(hex);
    if (stored >= 0 || !hex.startsWith('#')) return stored;
    for (let i = 0; i < 8; i++) if (token(`part-${i + 1}`).toLowerCase() === hex) return i;
    return -1;
}

/** A token's value in the theme showing (e.g. 'record', 'part-3'). */
export function token(name, fallback = '') {
    const theme = document.documentElement.dataset.theme ?? '';
    if (theme !== cachedFor) {
        cache.clear();
        cachedFor = theme;
    }
    if (!cache.has(name)) cache.set(name, getComputedStyle(document.documentElement).getPropertyValue(`--color-${name}`).trim() || fallback);
    return cache.get(name);
}

/** A stored part colour as the theme shows it (a hex); any other colour as it is. */
export function paint(color) {
    const i = partIndex(color);
    return i < 0 ? color : token(`part-${i + 1}`, color);
}

/** The colour for words on a filled part (the theme's pill text). */
export function paintText(color) {
    const i = partIndex(color);
    return i < 0 ? token('ink', '#17120f') : token(`part-text-${i + 1}`, token('ink', '#17120f'));
}

/** A part's colour for small words in it (deeper on a light theme, so they stay readable). */
export function paintInk(color) {
    const i = partIndex(color);
    return i < 0 ? color : token(`part-ink-${i + 1}`, paint(color));
}
