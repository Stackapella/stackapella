"""Builds game mode's pixel face, web/fonts/stackapella-pixel.woff2, from Press Start 2P's original TrueType (the
coordinator's speed pass, 2026-11-03: the 116 KB TTF on every game page as a 27 KB WOFF2).

The cut keeps every character the original has (all but its private-use glyphs), so nothing that renders in it today
falls back, and keeps its hinting (a pixel face's crisp edges on Windows). The OFL reserves the name "Press Start 2P"
for the original, and a subset is a Modified Version, so it ships as "Stackapella Pixel", with the original's
copyright kept in the font and in web/fonts/OFL-pixel.txt.

A one-off local tool, never part of a build (no npm; fontTools from a throwaway venv):

    python3 -m venv /tmp/ft && /tmp/ft/bin/pip install fonttools brotli
    /tmp/ft/bin/python web/tool/pixel_font.py PressStart2P-Regular.ttf web/fonts/stackapella-pixel.woff2

The original: https://github.com/google/fonts/tree/main/ofl/pressstart2p (PressStart2P-Regular.ttf).
"""
import sys

from fontTools import subset
from fontTools.ttLib import TTFont

FAMILY = "Stackapella Pixel"
UNICODES = "U+0020-D7FF,U+F900-FFFD"  # everything but the private-use area (U+E000-F8FF)


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
    font.flavor = "woff2"
    font.save(target)
    after = set(TTFont(target).getBestCmap())
    dropped = sorted(c for c in before - after if c >= 0x20 and not 0xE000 <= c <= 0xF8FF)  # controls and private use aside
    assert not dropped, f"characters the original renders but the cut drops: {[hex(c) for c in dropped]}"
    import json
    from pathlib import Path
    glyphs = Path(__file__).resolve().parent.parent / "tests" / "flows" / "pixel_glyphs.json"
    glyphs.write_text(json.dumps({"font": "web/fonts/stackapella-pixel.woff2", "codepoints": sorted(c for c in after if c >= 0x20)}))
    print(f"{target}: {len(after)} characters, every one the original has outside private use; {glyphs.name} written")


if __name__ == "__main__":
    build(sys.argv[1], sys.argv[2])
