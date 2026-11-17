// The one recipe for a note gem, in numbers (docs/glass-spec.md; Hillel, 2026-11-16: the home page takes the app's
// glass, "it also unifies the experience", and the app takes the home page's ring, "yes, add the ring to the app too").
// The canvas (glass.js) and the page's CSS (css/glass.css, its --gem-* properties) draw from these same numbers, and
// tests/glass.test.js holds the two equal. No imports, so a public page can load it. `h` is a gem's height and `w` its
// width, in CSS px; a mix is in sRGB (a canvas lerps sRGB channels; CSS: color-mix(in srgb, ...)).
export const GEM = Object.freeze({
    // Corner radius: min(radius x h, w / 2).
    radius: 0.38,
    // Body, top to bottom: c with `top` white at 0, flat c from `flat-from` to `flat-to` (the words sit there), c with
    // `bottom` black at 1.
    body: { top: 0.42, flatFrom: 0.3, flatTo: 0.7, bottom: 0.32 },
    // Edge: `width` px of c with this much black.
    edge: { width: 1.5, dark: 0.45, light: 0.5 },
    // Gloss: a round pill from x = max(1 px, x0 x h) to x1 x w, y0 x h to y1 x h; white at this alpha fading to 0 down.
    gloss: { x0: 0.12, x1: 0.78, y0: 0.07, y1: 0.29, dark: 0.7, light: 0.85 },
    // Foot line: `width` px at h - at x h, from r to w - r; c with `white` white at `alpha`.
    foot: { width: 1, at: 0.11, white: 0.5, alpha: 0.75 },
    // Drops under it, as CSS 0 dy 0 spread in black at the theme's alpha.
    dropA: { dy: 3, spread: -1, dark: 0.32, light: 0.14 },
    dropB: { dy: 4, spread: 1, dark: 0.18, light: 0.07 },
    // Glow: blur px; c at alpha x g, where g is the state's glow level.
    glow: { darkBlur: 14, darkAlpha: 0.6, lightBlur: 10, lightAlpha: 0.4 },
    level: { next: 0.3, now: 0.8, hit: 1 },
    // The ring round the note being sung, outward: a knockout in the colour under the lane, the ring in the theme's
    // text colour (game: --arc-yellow, no glow blur), a keyline in the colour under the lane. px.
    ring: { knockout: 3, width: 2, keyline: 1 },
    // A word's ink: whichever has the higher WCAG contrast on the gem's colour.
    ink: { dark: '#17110a', light: '#ffffff' },
    // Ghost (another part, at `height` x the bar) and waiting (a voice not joined yet): fill c at `fill`, a dashed
    // `edge` px edge of c at `edgeAlpha`; no gloss, shadow or words.
    ghost: { fill: 0.07, edge: 1.5, edgeAlpha: 0.55, height: 0.7 },
    // Settled (past and not hit): c at `body` over the colour under the lane, edge c at `edge` over it, gloss at
    // `gloss`, no foot line, drop A at `dropA` of its alpha and no drop B.
    settled: { body: 0.4, edge: 0.65, gloss: 0.3, dropA: 0.5 },
    // A hit: pop to `pop` and back over popMs, glow from 1 to `now` over flashMs; its tick `tickWidth` px in the ink,
    // min(tickMax, tickOfH x h) px, `tickEnd` px from the gem's end.
    hit: { pop: 1.05, popMs: 250, flashMs: 500, tickWidth: 2, tickMax: 15, tickOfH: 0.42, tickEnd: 6 },
    // The syllable: Figtree `weight`, clamp(min, ofH x h, max) px, start-aligned at max(inset, r). None inside a gem
    // under `minGem` px tall. Above the gem when it won't fit: `aboveSize` px `aboveWeight`, `aboveGap` px over its
    // top, only if it ends `aboveClear` px before the next gem.
    words: { weight: 750, ofH: 0.44, min: 11, max: 20, tracking: 0.005, inset: 6, minGem: 18, aboveSize: 12, aboveWeight: 700, aboveGap: 4, aboveClear: 6 },
    // Motion, from the audio clock: next's glow ramp over the last min(nextS s, 1 beat); ms for the rest. The join's
    // band is `joinDeg` degrees wide.
    motion: { nextS: 0.45, onsetMs: 90, releaseMs: 160, settleMs: 240, joinMs: 900, joinFadeMs: 300, joinDeg: 100 },
    // The home page's lane under its gems: c at `laneTint` over the ground.
    laneTint: 0.14,
});

/** GEM as CSS custom properties: `--gem-` and the path in kebab case ("body.flatFrom" is --gem-body-flat-from). */
export function gemProperties(spec = GEM, prefix = '--gem') {
    const out = {};
    for (const [key, value] of Object.entries(spec)) {
        const name = `${prefix}-${key.replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`)}`;
        if (value && typeof value === 'object') Object.assign(out, gemProperties(value, name));
        else out[name] = String(value);
    }
    return out;
}
