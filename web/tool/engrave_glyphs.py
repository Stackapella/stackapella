"""Writes web/js/engrave-glyphs.js: the outlines of the music signs the score engraver draws (js/engrave.js), taken
from Bravura (Steinberg, SIL OFL 1.1: web/fonts/OFL-bravura.txt), and the widths of the words' face, so a score is
laid out without measuring text. Only the signs we draw, as SVG path data scaled so a staff space is 10 units.

Run it only to add a sign: it needs fontTools (a scratch venv: pip install fonttools), Bravura.otf and SMuFL's
glyphnames.json in one folder, and a serif to measure (Georgia's widths; Times is narrower, so its words always fit).

    python -I web/tool/engrave_glyphs.py <folder with Bravura.otf and glyphnames.json> <Georgia.ttf>
"""
import json
import sys
from pathlib import Path

from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont

SIGNS = [
    'gClef', 'gClef8vb', 'fClef', 'noteheadBlack', 'noteheadHalf', 'noteheadWhole', 'flag8thUp', 'flag8thDown', 'flag16thUp',
    'flag16thDown', 'flag32ndUp', 'flag32ndDown', 'accidentalSharp', 'accidentalFlat', 'accidentalNatural', 'accidentalDoubleSharp',
    'accidentalDoubleFlat', 'restWhole', 'restHalf', 'restQuarter', 'rest8th', 'rest16th', 'rest32nd', 'augmentationDot',
    'breathMarkComma', 'bracketTop', 'bracketBottom', 'metNoteQuarterUp', 'metAugmentationDot', 'tuplet3',
] + [f'timeSig{n}' for n in range(10)]
SCALE = 10 / 250  # a staff space: 250 font units, 10 of the engraver's; y runs down


def number(value):
    return ('%.2f' % value).rstrip('0').rstrip('.')


def main(folder, serif):
    names = json.loads((Path(folder) / 'glyphnames.json').read_text())
    font = TTFont(str(Path(folder) / 'Bravura.otf'))
    glyphs, cmap = font.getGlyphSet(), font.getBestCmap()
    signs = {}
    for name in SIGNS:
        glyph = glyphs[cmap[int(names[name]['codepoint'][2:], 16)]]
        pen = SVGPathPen(glyphs, ntos=number)
        glyph.draw(TransformPen(pen, (SCALE, 0, 0, -SCALE, 0, 0)))
        signs[name] = {'d': pen.getCommands(), 'w': round(glyph.width * SCALE, 2)}
    face = TTFont(serif)
    codes, advance, em = face.getBestCmap(), face['hmtx'], face['head'].unitsPerEm
    wanted = list(range(32, 127)) + list(range(160, 384)) + [0x2018, 0x2019, 0x201C, 0x201D, 0x2013, 0x2026]
    widths = {chr(c): round(advance[codes[c]][0] * 1000 / em) for c in wanted if c in codes}
    out = Path(__file__).resolve().parents[1] / 'js' / 'engrave-glyphs.js'
    out.write_text(
        "// Written by web/tool/engrave_glyphs.py; don't edit by hand. The music signs' outlines are from Bravura, Copyright (c)\n"
        "// 2015 Steinberg Media Technologies GmbH, under the SIL Open Font License 1.1 (web/fonts/OFL-bravura.txt): a subset,\n"
        "// as SVG paths, a staff space 10 units. WIDTHS: each letter's width per 1000 of the words' size (Georgia's).\n\n"
        f"export const GLYPHS = {json.dumps(signs, separators=(',', ':'), ensure_ascii=False)};\n\n"
        f"export const WIDTHS = {json.dumps(widths, separators=(',', ':'), ensure_ascii=False)};\n"
    )
    print(f'{out}: {len(signs)} signs, {out.stat().st_size} bytes')


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
