"""Writes the accent colours a person can choose (docs/decisions.md, "the theme picker, refined"): the
palette's own, or coral, amber, green, teal, sky, violet or rose. Each is fitted to each of the ten
themes so its text passes 4.5:1 on the theme's background, panel and raised surfaces (WCAG AA), with
a stronger variant (further from the background, for hover) and the colour of words on it. Writes
web/css/accents.css (on <html data-theme="..." data-accent="...">) and web/js/accent-colours.js (the
picker's previews: each theme's accents and surfaces). The palette's own accent is checked the same way, and fitted only if it fails.
Run after the palettes change:

    python3 -I web/tool/accents.py

Standard library only (no npm).
"""
import json
import math
from pathlib import Path

WEB = Path(__file__).resolve().parents[1]
NEED = 4.5
# The choices: a hue in OKLCH and how colourful (chroma), in the order the picker shows them.
ACCENTS = {
    "coral": (32, 0.17),
    "amber": (70, 0.15),
    "green": (148, 0.16),
    "teal": (190, 0.12),
    "sky": (240, 0.14),
    "indigo": (268, 0.16),
    "violet": (295, 0.17),
    "rose": (355, 0.17),
}


def hex_rgb(value):
    value = value.lstrip("#")
    return [int(value[i:i + 2], 16) / 255 for i in (0, 2, 4)]


def rgb_hex(rgb):
    return "#" + "".join(f"{round(max(0, min(1, c)) * 255):02x}" for c in rgb)


def linear(c):
    return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4


def gamma(c):
    return 12.92 * c if c <= 0.0031308 else 1.055 * c ** (1 / 2.4) - 0.055


def luminance(rgb):
    r, g, b = (linear(c) for c in rgb)
    return 0.2126 * r + 0.7152 * g + 0.0722 * b


def contrast(a, b):
    x, y = sorted((luminance(a), luminance(b)), reverse=True)
    return (x + 0.05) / (y + 0.05)


def oklch_rgb(l, c, h):
    """OKLCH to sRGB (0 to 1), or None outside sRGB."""
    a, b = c * math.cos(math.radians(h)), c * math.sin(math.radians(h))
    l_ = (l + 0.3963377774 * a + 0.2158037573 * b) ** 3
    m_ = (l - 0.1055613458 * a - 0.0638541728 * b) ** 3
    s_ = (l - 0.0894841775 * a - 1.2914855480 * b) ** 3
    lin = [4.0767416621 * l_ - 3.3077115913 * m_ + 0.2309699292 * s_,
           -1.2684380046 * l_ + 2.6097574011 * m_ - 0.3413193965 * s_,
           -0.0041960863 * l_ - 0.7034186147 * m_ + 1.7076147010 * s_]
    if any(v < -1e-4 or v > 1 + 1e-4 for v in lin):
        return None
    return [gamma(max(0, min(1, v))) for v in lin]


def colour(l, c, h):
    """The colour at lightness `l`, as colourful as sRGB allows up to `c`."""
    while c > 0:
        rgb = oklch_rgb(l, c, h)
        if rgb:
            return rgb
        c -= 0.005
    return oklch_rgb(l, 0, h)


def passes(rgb, surfaces):
    return min(contrast(rgb, s) for s in surfaces) >= NEED


def fit(h, c, surfaces, dark):
    """The most colourful lightness that still reads on every surface: as dim as allowed in a dark
    theme (from 0.74 up), as bright as allowed in a light one (from 0.60 down)."""
    steps = [0.74 + i * 0.005 for i in range(51)] if dark else [0.60 - i * 0.005 for i in range(81)]
    for l in steps:
        rgb = colour(l, c, h)
        if passes(rgb, surfaces):
            return l, rgb
    raise SystemExit(f"no lightness passes for hue {h}")


def variants(l, c, h, dark, ink):
    strong = colour(min(0.97, l + 0.08) if dark else max(0.2, l - 0.08), c, h)
    rgb = colour(l, c, h)
    words = ink if contrast(ink, rgb) >= NEED else ([0, 0, 0] if contrast([0, 0, 0], rgb) >= contrast([1, 1, 1], rgb) else [1, 1, 1])
    return rgb, strong, words


def to_oklch(rgb):
    r, g, b = (linear(v) for v in rgb)
    l_ = (0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b) ** (1 / 3)
    m_ = (0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b) ** (1 / 3)
    s_ = (0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b) ** (1 / 3)
    l = 0.2104542553 * l_ + 0.7936177850 * m_ - 0.0040720468 * s_
    a = 1.9779984951 * l_ - 2.4285922050 * m_ + 0.4505937099 * s_
    b2 = 0.0259040371 * l_ + 0.7827717662 * m_ - 0.8086757660 * s_
    return l, math.hypot(a, b2), math.degrees(math.atan2(b2, a)) % 360


def fit_custom(hex_value, p):
    """A colour the person picked, fitted to a theme as the palette's own is: kept if it reads on
    every surface, else moved to the nearest lightness that does. Returns (accent, strong, ink, adjusted)."""
    dark = p["mode"] == "dark"
    ink = hex_rgb(p["ink"])
    surfaces = [ink, hex_rgb(p["panel"]), hex_rgb(p["raised"])]
    picked = hex_rgb(hex_value)
    l, c, h = to_oklch(picked)
    if passes(picked, surfaces):
        _, strong, words = variants(l, c, h, dark, ink)
        return rgb_hex(picked), rgb_hex(strong), rgb_hex(words), False
    l, _ = fit(h, c, surfaces, dark)
    rgb, strong, words = variants(l, c, h, dark, ink)
    return rgb_hex(rgb), rgb_hex(strong), rgb_hex(words), True


# Custom colours the vectors check (the apps derive the same): bright, dark, pale, grey, saturated.
VECTOR_COLOURS = ["#ff0000", "#00ff00", "#0000ff", "#ffff00", "#ff00ff", "#00ffff", "#808080", "#1a1a1a", "#f5f5dc", "#e8a87c", "#3d5a80", "#9b5de5", "#00bb77", "#ffffff", "#000000"]


def write_boot(palettes):
    """theme-boot.js's table of each theme's surfaces, for fitting a custom accent before first paint."""
    path = WEB / "js/theme-boot.js"
    surfaces = {pid: [p["ink"].lower(), p["panel"].lower(), p["raised"].lower(), p["mode"]] for pid, p in palettes.items()}
    text = path.read_text()
    start, end = "/* surfaces:start */", "/* surfaces:end */"
    i, j = text.index(start) + len(start), text.index(end)
    path.write_text(text[:i] + " const SURFACES = " + json.dumps(surfaces) + "; " + text[j:])


def main():
    palettes = json.loads((WEB / "tool/palettes.json").read_text())
    blocks, table, notes = [], {}, []
    for pid, p in palettes.items():
        dark = p["mode"] == "dark"
        ink = hex_rgb(p["ink"])
        surfaces = [ink, hex_rgb(p["panel"]), hex_rgb(p["raised"])]
        table[pid] = {}
        own = hex_rgb(p["accent"])
        choices = {"own": None, **ACCENTS}
        for name, spec in choices.items():
            if name == "own":
                l, c, h = to_oklch(own)
                if passes(own, surfaces):
                    rgb, strong, words = variants(l, c, h, dark, ink)
                    rgb = own
                else:
                    l, _ = fit(h, c, surfaces, dark)
                    rgb, strong, words = variants(l, c, h, dark, ink)
                    notes.append(f"{pid}: its own accent {p['accent']} fails {NEED}:1, fitted to {rgb_hex(rgb)}")
            else:
                h, c = spec
                l, _ = fit(h, c, surfaces, dark)
                rgb, strong, words = variants(l, c, h, dark, ink)
            values = {"accent": rgb_hex(rgb), "accent-strong": rgb_hex(strong), "accent-ink": rgb_hex(words)}
            table[pid][name] = values["accent"]
            selector = f':root[data-theme="{pid}"]' + ("" if name == "own" else f'[data-accent="{name}"]')
            lines = "\n".join(f"    --color-{k}: {v};" for k, v in values.items())
            blocks.append(f"{selector} {{\n{lines}\n}}")
    header = "/* Written by web/tool/accents.py from the palettes: don't edit by hand. */\n"
    (WEB / "css/accents.css").write_text(header + "\n".join(blocks) + "\n")
    js = (
        "// Written by web/tool/accents.py from the palettes: don't edit by hand. Each theme's accent\n"
        "// for each choice ('own' is the palette's), and its surfaces, for the theme picker's previews.\n"
        f"export const ACCENT_NAMES = {json.dumps(['own', *ACCENTS])};\n"
        f"export const ACCENT_COLOURS = {json.dumps(table, indent=4)};\n"
        f"export const SURFACES = {json.dumps({pid: {k: p[k].lower() for k in ('ink', 'panel', 'raised', 'line', 'cream', 'muted')} for pid, p in palettes.items()}, indent=4)};\n"
    )
    (WEB / "js/accent-colours.js").write_text(js)
    write_boot(palettes)
    vectors = [{"colour": v, "theme": pid, **dict(zip(("accent", "strong", "ink", "adjusted"), fit_custom(v, p)))} for v in VECTOR_COLOURS for pid, p in palettes.items()]
    (WEB / "tests/vectors/accent.json").write_text(json.dumps(vectors, indent=1) + "\n")
    print(f"{len(blocks)} accents written")
    for note in notes:
        print(note)


if __name__ == "__main__":
    main()
