"""Builds game mode's pixel face, web/fonts/stackapella-pixel.woff2, from Press Start 2P's original TrueType (the
coordinator's speed pass, 2026-11-03: the 116 KB TTF on every game page as a 27 KB WOFF2).

The cut keeps every character the original has (all but its private-use glyphs), so nothing that renders in it today
falls back, and keeps its hinting (a pixel face's crisp edges on Windows). The OFL reserves the name "Press Start 2P"
for the original, and a subset is a Modified Version, so it ships as "Stackapella Pixel", with the original's
copyright kept in the font and in web/fonts/OFL-pixel.txt.

Its accented capitals are redrawn (2026-11-12, before the app speaks Spanish): the original squeezes each into its
8-row cell, so Ó is ó's very glyph and LECCIÓN reads "LECCIóN". Each of À to Ý is now its own capital at full height
(rows 1 to 7, y 125 to 1000; a pixel is 125 units) with its lowercase's mark right on top (rows 8 and 9), or below the
baseline for Ç, as pixel faces with accents draw them. The line's metrics stay as they were (ascent 1000), so nothing
moves: the mark reaches 1.25 em, which clears the line above at the line heights the pixel face is set in (1.25 and up).
An empty row between letter and mark was tried and left: at 1.25 it touches the line above. ẞ, which the original
hasn't, is drawn as its ß, already a capital's height. Latin-1 only (Spanish, Portuguese, French, Italian, German,
Catalan); Latin Extended-A keeps the original's.

A one-off local tool, never part of a build (no npm; fontTools from a throwaway venv):

    python3 -m venv /tmp/ft && /tmp/ft/bin/pip install fonttools brotli
    /tmp/ft/bin/python web/tool/pixel_font.py PressStart2P-Regular.ttf web/fonts/stackapella-pixel.woff2

The original: https://github.com/google/fonts/tree/main/ofl/pressstart2p (PressStart2P-Regular.ttf; sha256
034c77f1f05ec89421e4a63f0e3a4ca1ecf852cc6d2bf611f126f275728e017d rebuilds the 2026-11-03 cut glyph for glyph).
"""
import sys
import unicodedata

from fontTools import subset
from fontTools.pens.pointInsidePen import PointInsidePen
from fontTools.pens.recordingPen import DecomposingRecordingPen
from fontTools.pens.ttGlyphPen import TTGlyphPen
from fontTools.ttLib import TTFont
from fontTools.ttLib.tables import ttProgram

FAMILY = "Stackapella Pixel"
UNICODES = "U+0020-D7FF,U+F900-FFFD"  # everything but the private-use area (U+E000-F8FF)
PX = 125  # one pixel, in font units
CAP_TOP = 7  # capitals fill rows 1 to 7
X_TOP = 5  # a lowercase body fills rows 1 to 5 (x-height 750); its mark sits above
FLAT = "acegijmnopqrsuvwxyz"  # lowercase letters with no ascender, whose own mark can be borrowed
VOWELS = "aeoucnsz"  # else a mark is borrowed from one of these with the same mark


def cells(glyphs, name, rows):
    """The glyph's pixels in those rows, as (column, row), read at each pixel's centre."""
    out = set()
    for row in rows:
        for col in range(8):
            pen = PointInsidePen(glyphs, (col * PX + PX // 2, row * PX + PX // 2))
            glyphs[name].draw(pen)
            if pen.getResult():
                out.add((col, row))
    return out


def mark_from(cmap, base, mark):
    """The lowercase letter carrying the same mark: the capital's own lowercase when it has no ascender, else a vowel's."""
    for letter in ([base.lower()] if base.lower() in FLAT else []) + list(VOWELS):
        composed = unicodedata.normalize("NFC", letter + mark)
        if len(composed) == 1 and ord(composed) in cmap:
            return composed
    return None


def draw_runs(pen, pixels, shift):
    """One rectangle for each horizontal run of pixels, drawn clockwise as TrueType outlines are."""
    rows = {}
    for col, row in pixels:
        rows.setdefault(row + shift, []).append(col)
    for row, cols in rows.items():
        cols.sort()
        start = cols[0]
        for i, col in enumerate(cols):
            if i + 1 < len(cols) and cols[i + 1] == col + 1:
                continue
            x0, x1, y0, y1 = start * PX, (col + 1) * PX, row * PX, (row + 1) * PX
            pen.moveTo((x0, y0))
            pen.lineTo((x0, y1))
            pen.lineTo((x1, y1))
            pen.lineTo((x1, y0))
            pen.closePath()
            if i + 1 < len(cols):
                start = cols[i + 1]


def full_height_capitals(font):
    """Redraws À to Ý as their capital at full height with their mark, and maps ẞ to ß. Returns what it redrew."""
    cmap = font.getBestCmap()
    glyphs = font.getGlyphSet()
    redrawn = []
    for code in range(0xC0, 0xDE):
        parts = unicodedata.normalize("NFD", chr(code))
        if code not in cmap or len(parts) != 2 or ord(parts[0]) not in cmap:
            continue
        base, mark = parts
        above = unicodedata.combining(mark) == 230
        source = mark_from(cmap, base, mark)
        assert source, f"no lowercase carries the mark of {chr(code)}"
        marks = cells(glyphs, cmap[ord(source)], range(X_TOP + 1, 11) if above else range(-3, 1))
        assert marks, f"{source} has no mark where it should"
        shift = CAP_TOP + 1 - min(row for _, row in marks) if above else 0
        pen = TTGlyphPen(glyphs)
        letter = DecomposingRecordingPen(glyphs)
        glyphs[cmap[ord(base)]].draw(letter)
        letter.replay(pen)
        draw_runs(pen, marks, shift)
        glyph = pen.glyph()
        glyph.program = ttProgram.Program()
        glyph.program.fromBytecode(b"")
        font["glyf"][cmap[code]] = glyph
        redrawn.append(chr(code))
    if 0x1E9E not in cmap:
        for table in font["cmap"].tables:
            if table.isUnicode():
                table.cmap[0x1E9E] = cmap[0xDF]
        redrawn.append("ẞ")
    font["head"].yMax = max(font["head"].yMax, (CAP_TOP + 3) * PX)
    return redrawn


def check_capitals(font, redrawn):
    """Each redrawn capital is its plain capital, row for row, with its mark clear of it and no longer its lowercase."""
    cmap = font.getBestCmap()
    glyphs = font.getGlyphSet()
    body = range(1, CAP_TOP + 1)
    for ch in redrawn:
        if ch == "ẞ":
            continue
        base, mark = unicodedata.normalize("NFD", ch)
        assert cells(glyphs, cmap[ord(ch)], body) == cells(glyphs, cmap[ord(base)], body), f"{ch} isn't {base} at full height"
        if unicodedata.combining(mark) == 230:
            assert cells(glyphs, cmap[ord(ch)], range(CAP_TOP + 1, 11)), f"{ch} has no mark above"
        lower = ch.lower()
        assert cmap[ord(ch)] != cmap.get(ord(lower)) and cells(glyphs, cmap[ord(ch)], range(-3, 11)) != cells(glyphs, cmap[ord(lower)], range(-3, 11)), f"{ch} is still drawn as {lower}"


def build(source, target):
    options = subset.Options()
    options.layout_features = ["*"]
    options.hinting = True
    options.flavor = "woff2"
    options.name_IDs = ["*"]
    options.name_languages = ["*"]
    options.notdef_outline = True
    font = TTFont(source)
    before = set(font.getBestCmap())
    subsetter = subset.Subsetter(options)
    subsetter.populate(unicodes=subset.parse_unicodes(UNICODES))
    subsetter.subset(font)
    name = font["name"]
    for name_id in (1, 3, 4, 6, 16, 17, 21, 22):
        name.removeNames(nameID=name_id)
    for name_id, value in ((1, FAMILY), (2, "Regular"), (3, f"{FAMILY} Regular; a subset of Press Start 2P"), (4, f"{FAMILY} Regular"), (6, "StackapellaPixel-Regular")):
        name.setName(value, name_id, 3, 1, 0x409)
        name.setName(value, name_id, 1, 0, 0)
    redrawn = full_height_capitals(font)
    font.flavor = "woff2"
    font.save(target)
    saved = TTFont(target)
    check_capitals(saved, redrawn)
    after = set(saved.getBestCmap())
    dropped = sorted(c for c in before - after if c >= 0x20 and not 0xE000 <= c <= 0xF8FF)  # controls and private use aside
    assert not dropped, f"characters the original renders but the cut drops: {[hex(c) for c in dropped]}"
    import json
    from pathlib import Path
    glyphs = Path(__file__).resolve().parent.parent / "tests" / "flows" / "pixel_glyphs.json"
    glyphs.write_text(json.dumps({"font": "web/fonts/stackapella-pixel.woff2", "codepoints": sorted(c for c in after if c >= 0x20)}))
    print(f"{target}: {len(after)} characters, every one the original has outside private use; {len(redrawn)} capitals at full height ({''.join(redrawn)}); {glyphs.name} written")


if __name__ == "__main__":
    build(sys.argv[1], sys.argv[2])
