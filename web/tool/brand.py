"""Writes Stackapella's logo set into web/icons/brand/ (Hillel: "our logo and icon"), matching the
site's header exactly: the mark (web/icons/icon.svg with the header's rounded corners) and the name
"Stackapella" in Figtree ExtraBold (800), -0.01em tracking, the mark 30 px beside 18 px words with a
9 px gap (.s-brand in css/input.css, components/site/header.blade.php).

The words are outlined to paths, so a file needs no font. Headless Chrome lays out the header's own
markup (scaled up a hundred times, so nothing rounds) and says where each glyph and the baseline sit,
kerning and tracking included; the glyphs' outlines are read from the font file here (TrueType
`glyf`, standard library only). Then Chrome renders the PNGs on a transparent background.

    python3 -I web/tool/brand.py

Writes, for light backgrounds (dark words) and dark ones (white words):
- stackapella-wordmark-{light,dark}.svg, the mark beside the name, and .png at 1x (480 wide), @2x and
  -1200 (1200 wide);
- stackapella-stacked-{light,dark}.svg, the mark above the name, and .png at 1x (240 wide), @2x and -1200;
- stackapella-mark.svg and stackapella-mark-1024.png, the mark alone.
"""
import base64
import hashlib
import struct
import sys
import time
import urllib.request
import zlib
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from cdp import Chrome  # noqa: E402

WEB = Path(__file__).resolve().parents[1]
OUT = WEB / "icons" / "brand"
# Figtree 800 as Google Fonts serves it (a static instance), checked like every pinned download.
FONT_URL = "https://fonts.gstatic.com/s/figtree/v9/_Xmz-HUzqDCFdgfMsYiV_F7wfS-Bs_f_R15d.woff"
FONT_SHA256 = "7b8a47b70f5f9a1fbb7fcb3c5965d8cc4bc66570f9bf55b6d24da9d54de1a9fe"
NAME = "Stackapella"
# The header, in px: the mark, the words, the gap, and the tracking (em).
MARK, TEXT, GAP, TRACKING = 30, 18, 9, -0.01
SCALE = 100  # laid out this much bigger, then scaled back
INK = {"light": "#211a15", "dark": "#ffffff"}  # the words: the light theme's ink, the dark theme's
STACK_MARK = 2.6  # the stacked mark's height, in words' sizes
STACK_GAP = 0.6
PNG_WIDTHS = {"wordmark": 480, "stacked": 240}


# ---- The font ------------------------------------------------------------------------------------

def font_tables():
    data = urllib.request.urlopen(FONT_URL).read()
    got = hashlib.sha256(data).hexdigest()
    if got != FONT_SHA256:
        sys.exit(f"the font's sha256 is {got}, not the pinned {FONT_SHA256}")
    if data[:4] != b"wOFF":
        sys.exit("not a WOFF font")
    count = struct.unpack(">H", data[12:14])[0]
    tables = {}
    for i in range(count):
        tag, offset, length, original, _ = struct.unpack(">4sIIII", data[44 + i * 20:64 + i * 20])
        raw = data[offset:offset + length]
        tables[tag.decode()] = zlib.decompress(raw) if length < original else raw
    return data, tables


class Font:
    """Just enough TrueType for outlines: cmap (format 4), loca and glyf."""

    def __init__(self, tables):
        self.t = tables
        head = tables["head"]
        self.upm = struct.unpack(">H", head[18:20])[0]
        self.long_loca = struct.unpack(">h", head[50:52])[0] == 1
        self.cmap = self._cmap()

    def _cmap(self):
        cmap = self.t["cmap"]
        count = struct.unpack(">H", cmap[2:4])[0]
        for i in range(count):
            platform, encoding, offset = struct.unpack(">HHI", cmap[4 + i * 8:12 + i * 8])
            if platform == 3 and encoding == 1 and struct.unpack(">H", cmap[offset:offset + 2])[0] == 4:
                return self._format4(cmap[offset:])
        sys.exit("no Unicode cmap")

    @staticmethod
    def _format4(sub):
        segs = struct.unpack(">H", sub[6:8])[0] // 2
        ends = struct.unpack(f">{segs}H", sub[14:14 + segs * 2])
        at = 16 + segs * 2
        starts = struct.unpack(f">{segs}H", sub[at:at + segs * 2])
        deltas = struct.unpack(f">{segs}h", sub[at + segs * 2:at + segs * 4])
        ranges_at = at + segs * 4
        ranges = struct.unpack(f">{segs}H", sub[ranges_at:ranges_at + segs * 2])
        table = {}
        for s in range(segs):
            for code in range(starts[s], ends[s] + 1):
                if code == 0xFFFF:
                    continue
                if ranges[s] == 0:
                    glyph = (code + deltas[s]) & 0xFFFF
                else:
                    where = ranges_at + s * 2 + ranges[s] + (code - starts[s]) * 2
                    glyph = struct.unpack(">H", sub[where:where + 2])[0]
                    glyph = (glyph + deltas[s]) & 0xFFFF if glyph else 0
                table[code] = glyph
        return table

    def _offset(self, glyph):
        loca = self.t["loca"]
        if self.long_loca:
            return struct.unpack(">II", loca[glyph * 4:glyph * 4 + 8])
        a, b = struct.unpack(">HH", loca[glyph * 2:glyph * 2 + 4])
        return a * 2, b * 2

    def contours(self, glyph, matrix=(1, 0, 0, 1, 0, 0)):
        """The glyph's contours as lists of (x, y, on_curve), in font units, through `matrix`."""
        start, end = self._offset(glyph)
        if start == end:
            return []
        g = self.t["glyf"][start:end]
        n = struct.unpack(">h", g[0:2])[0]
        if n < 0:
            return self._compound(g, matrix)
        ends = struct.unpack(f">{n}H", g[10:10 + n * 2])
        points = ends[-1] + 1
        at = 10 + n * 2
        at += 2 + struct.unpack(">H", g[at:at + 2])[0]  # the instructions
        flags = []
        while len(flags) < points:
            flag = g[at]
            at += 1
            flags.append(flag)
            if flag & 8:
                flags.extend([flag] * g[at])
                at += 1
        coords = []
        for short, same in ((2, 16), (4, 32)):
            value, out = 0, []
            for flag in flags:
                if flag & short:
                    delta = g[at]
                    at += 1
                    value += delta if flag & same else -delta
                elif not flag & same:
                    value += struct.unpack(">h", g[at:at + 2])[0]
                    at += 2
                out.append(value)
            coords.append(out)
        a, b, c, d, e, f = matrix
        result, first = [], 0
        for last in ends:
            result.append([(a * coords[0][i] + c * coords[1][i] + e, b * coords[0][i] + d * coords[1][i] + f, bool(flags[i] & 1)) for i in range(first, last + 1)])
            first = last + 1
        return result

    def _compound(self, g, matrix):
        at, result = 10, []
        while True:
            flags, glyph = struct.unpack(">HH", g[at:at + 4])
            at += 4
            if flags & 1:
                dx, dy = struct.unpack(">hh", g[at:at + 4])
                at += 4
            else:
                dx, dy = struct.unpack(">bb", g[at:at + 2])
                at += 2
            a, b, c, d = 1.0, 0.0, 0.0, 1.0
            f2 = lambda raw: raw / 16384  # noqa: E731 (F2Dot14)
            if flags & 8:
                a = d = f2(struct.unpack(">h", g[at:at + 2])[0])
                at += 2
            elif flags & 0x40:
                a, d = (f2(v) for v in struct.unpack(">hh", g[at:at + 4]))
                at += 4
            elif flags & 0x80:
                a, b, c, d = (f2(v) for v in struct.unpack(">hhhh", g[at:at + 8]))
                at += 8
            if not flags & 2:
                sys.exit("point-matched components aren't supported")
            ma, mb, mc, md, me, mf = matrix
            # This component's transform, then the parent's.
            child = (a * ma + b * mc, a * mb + b * md, c * ma + d * mc, c * mb + d * md, dx * ma + dy * mc + me, dx * mb + dy * md + mf)
            result.extend(self.contours(glyph, child))
            if not flags & 0x20:
                return result

    def path(self, char):
        """SVG path data for `char`, in font units with y up."""
        parts = []
        for contour in self.contours(self.cmap[ord(char)]):
            if not contour:
                continue
            # Start on an on-curve point (or the midpoint of two off-curve ones).
            start = next((i for i, p in enumerate(contour) if p[2]), None)
            if start is None:
                p0, p1 = contour[0], contour[1]
                contour = [((p0[0] + p1[0]) / 2, (p0[1] + p1[1]) / 2, True)] + contour
                start = 0
            pts = contour[start:] + contour[:start]
            parts.append(f"M{pts[0][0]:g} {pts[0][1]:g}")
            i, n = 1, len(pts)
            while i <= n:
                p = pts[i % n]
                if p[2]:
                    parts.append(f"L{p[0]:g} {p[1]:g}")
                    i += 1
                    continue
                nxt = pts[(i + 1) % n]
                end = nxt if nxt[2] else ((p[0] + nxt[0]) / 2, (p[1] + nxt[1]) / 2, True)
                parts.append(f"Q{p[0]:g} {p[1]:g} {end[0]:g} {end[1]:g}")
                i += 2 if nxt[2] else 1
            parts.append("Z")
        return "".join(parts)

    def ink(self, char):
        """The glyph's box (xMin, yMin, xMax, yMax), font units."""
        start, _ = self._offset(self.cmap[ord(char)])
        return struct.unpack(">hhhh", self.t["glyf"][start + 2:start + 10])


# ---- The layout, from the header's own markup in Chrome --------------------------------------------

def layout(chrome, woff):
    s = SCALE
    font = base64.b64encode(woff).decode()
    page = f"""<!doctype html><style>@font-face {{ font-family: B; font-weight: 800; src: url(data:font/woff;base64,{font}) format('woff'); }}
        body {{ margin: 0 }} a {{ display: flex; align-items: center; gap: {GAP * s}px; font: 800 {TEXT * s}px B; letter-spacing: {TRACKING}em; font-kerning: normal; white-space: nowrap; }}
        svg {{ flex: none }} i {{ display: inline-block; width: 0; height: 0; vertical-align: baseline }}</style>
        <a><svg id=m width={MARK * s} height={MARK * s} viewBox="0 0 512 512"></svg><span id=w><i id=b></i>{NAME}</span></a>"""
    chrome.goto("data:text/html;base64," + base64.b64encode(page.encode()).decode(), "document.fonts.ready.then(() => true)")
    chrome.evaluate("document.fonts.ready")
    time.sleep(0.3)
    return chrome.evaluate(f"""(() => {{
        const text = document.getElementById('w').lastChild; const pens = [];
        for (let i = 0; i < text.length; i++) {{ const r = document.createRange(); r.setStart(text, i); r.setEnd(text, i + 1); pens.push(r.getBoundingClientRect().left / {s}); }}
        const m = document.getElementById('m').getBoundingClientRect();
        return {{ pens, baseline: document.getElementById('b').getBoundingClientRect().bottom / {s}, mark: {{ x: m.left / {s}, y: m.top / {s}, size: m.width / {s} }} }};
    }})()""")


# ---- The files --------------------------------------------------------------------------------------

def mark_svg(x, y, size):
    """The site header's mark (icon.svg with its rounded corners) at x, y, `size` square."""
    icon = (WEB / "icons" / "icon.svg").read_text()
    inner = icon[icon.index(">") + 1:icon.rindex("</svg>")].replace('<rect width="512" height="512"', '<rect width="512" height="512" rx="86"', 1)
    k = size / 512
    return f'<g transform="translate({x:g} {y:g}) scale({k:g})">{inner}</g>'


def words_svg(font, pens, x0, baseline, size, colour):
    k = size / font.upm
    glyphs = "".join(f'<path transform="translate({x0 + pen:.3f} {baseline:.3f}) scale({k:.6f} {-k:.6f})" d="{font.path(c)}"/>' for c, pen in zip(NAME, pens) if c != " ")
    return f'<g fill="{colour}">{glyphs}</g>'


def words_box(font, pens, size):
    """The words' ink box from the first pen at x 0 and the baseline at y 0 (y down)."""
    k = size / font.upm
    boxes = [font.ink(c) for c in NAME]
    left = min(p + b[0] * k for p, b in zip(pens, boxes))
    right = max(p + b[2] * k for p, b in zip(pens, boxes))
    top = min(-b[3] * k for b in boxes)
    bottom = max(-b[1] * k for b in boxes)
    return left, top, right, bottom


def svg(width, height, body, title):
    return (f'<svg xmlns="http://www.w3.org/2000/svg" width="{width:g}" height="{height:g}" viewBox="0 0 {width:g} {height:g}" role="img" aria-label="{title}">'
            f"<title>{title}</title>{body}</svg>\n")


def build(font, laid):
    pens = [p - laid["pens"][0] for p in laid["pens"]]  # from the first glyph's pen
    files = {}
    # Beside: the header as laid out, cropped to the mark and the words' ink.
    left, top, right, bottom = words_box(font, pens, TEXT)
    text_x = laid["pens"][0] - laid["mark"]["x"]  # the words' pen, from the mark's left
    base = laid["baseline"] - laid["mark"]["y"]  # the baseline, from the mark's top
    y0 = min(0, base + top)
    width = text_x + right
    height = max(MARK, base + bottom) - y0
    for look, colour in INK.items():
        body = mark_svg(0, -y0, MARK) + words_svg(font, pens, text_x, base - y0, TEXT, colour)
        files[f"stackapella-wordmark-{look}"] = (svg(width, height, body, NAME), width, height)
    # Stacked: a bigger mark above the words, both centred.
    mark = TEXT * STACK_MARK
    words_w = right - left
    width = max(mark, words_w)
    gap = TEXT * STACK_GAP
    base = mark + gap - top  # the words' ink starts `gap` under the mark
    height = base + bottom
    for look, colour in INK.items():
        body = mark_svg((width - mark) / 2, 0, mark) + words_svg(font, pens, (width - words_w) / 2 - left, base, TEXT, colour)
        files[f"stackapella-stacked-{look}"] = (svg(width, height, body, NAME), width, height)
    files["stackapella-mark"] = (svg(512, 512, mark_svg(0, 0, 512), NAME), 512, 512)
    return files


def png(chrome, svg_text, width, height, path):
    """The SVG drawn `width` x `height` on a transparent background."""
    w, h = round(width), round(height)
    chrome.send("Emulation.setDeviceMetricsOverride", {"width": w, "height": h, "deviceScaleFactor": 1, "mobile": False})
    chrome.send("Emulation.setDefaultBackgroundColorOverride", {"color": {"r": 0, "g": 0, "b": 0, "a": 0}})
    src = "data:image/svg+xml;base64," + base64.b64encode(svg_text.encode()).decode()
    page = f'<!doctype html><style>html, body {{ margin: 0; background: transparent }} img {{ display: block; width: {w}px; height: {h}px }}</style><img src="{src}">'
    chrome.goto("data:text/html;base64," + base64.b64encode(page.encode()).decode(), "document.images[0].complete")
    time.sleep(0.2)
    data = chrome.send("Page.captureScreenshot", {"format": "png", "clip": {"x": 0, "y": 0, "width": w, "height": h, "scale": 1}})["data"]
    path.write_bytes(base64.b64decode(data))


def main():
    woff, tables = font_tables()
    font = Font(tables)
    chrome = Chrome(width=20000, height=4000)  # wider than the header laid out a hundred times bigger
    try:
        laid = layout(chrome, woff)
        files = build(font, laid)
        OUT.mkdir(parents=True, exist_ok=True)
        for name, (text, width, height) in files.items():
            (OUT / f"{name}.svg").write_text(text)
            if name == "stackapella-mark":
                png(chrome, text, 1024, 1024, OUT / f"{name}-1024.png")
                continue
            one = PNG_WIDTHS[name.split("-")[1]]
            for suffix, w in (("", one), ("@2x", one * 2), ("-1200", 1200)):
                png(chrome, text, w, w * height / width, OUT / f"{name}{suffix}.png")
        print(f"brand: {len(list(OUT.iterdir()))} files in {OUT.relative_to(WEB.parent)}")
    finally:
        chrome.close()


if __name__ == "__main__":
    main()
