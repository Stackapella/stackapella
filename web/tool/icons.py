"""Writes Stackapella's mark from one place (Hillel: four bars, one per SATB voice, as the landing's
closing "same four bars" graphic, .s-stackbig: widths 100/74/88/62% of 140, 14 tall, 8 apart, fully
rounded; the parts' colours from Stage dark, top to bottom soprano, alto, tenor, bass; on a full
black square edge to edge, which iOS, Android and the PWA mask round themselves):

- web/icons/icon.svg: the app icon (512, the bars inside the maskable safe zone);
- web/icons/favicon.svg: the same, the bars bolder, for 16 px;
- web/js/logo-mark.js: the inline mark's SVG for the app's header.

Then render the PNGs (icon-192, icon-512, icon-maskable-512, apple-touch-icon) with
`web/tool/icons.sh`, which draws the SVG in headless Chrome. Standard library only.
"""
import json
from pathlib import Path

WEB = Path(__file__).resolve().parents[1]
WIDTHS = [1.00, 0.74, 0.88, 0.62]  # of the group's width, as .s-stackbig
BAR, GAP = 14, 8  # px in the 140 px graphic


def mark(size, group_width, colours, radius=0, bar=None, gap=None):
    """The square `size` wide, the bars `group_width` wide, centred, left-aligned as on the landing
    (`bar` and `gap` override the landing's proportions, for the favicon)."""
    scale = group_width / 140
    bar, gap = bar or BAR * scale, gap or GAP * scale
    height = 4 * bar + 3 * gap
    x0, y0 = (size - group_width) / 2, (size - height) / 2
    rects = "".join(
        f'<rect x="{x0:.2f}" y="{y0 + i * (bar + gap):.2f}" width="{group_width * w:.2f}" height="{bar:.2f}" rx="{bar / 2:.2f}" fill="{c}"/>'
        for i, (w, c) in enumerate(zip(WIDTHS, colours))
    )
    corner = f' rx="{radius}"' if radius else ""
    return f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {size} {size}"><rect width="{size}" height="{size}"{corner} fill="#000000"/>{rects}</svg>\n'


def main():
    palettes = json.loads((WEB / "tool/palettes.json").read_text())
    colours = palettes["stage-dark"]["parts"][:4]
    # The app icon: the bars 294 wide, so their corners sit inside the maskable safe circle (r 204.8).
    (WEB / "icons/icon.svg").write_text(mark(512, 294, colours))
    # The favicon: bolder bars, so four read at 16 px.
    (WEB / "icons/favicon.svg").write_text(mark(16, 14, colours, bar=2.5, gap=1.1))
    inline = mark(24, 17, colours, radius=4).strip().replace('"', "'")
    (WEB / "js/logo-mark.js").write_text(
        "// Written by web/tool/icons.py: the mark, inline (the black square and four bars), for the header.\n"
        f'export const LOGO_MARK = "{inline}";\n'
    )
    print("icons written")


if __name__ == "__main__":
    main()
