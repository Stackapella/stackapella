"""Draws each public page's link card (web/icons/og/<key>.png, 1200 by 630) from one template:
the mark (web/icons/icon.svg), the page's eyebrow and headline in Figtree (`php artisan app:site:link-cards`, the pages' own words),
and the four part colours stacked, as the mark has them. Warm dark behind. Then the landing's own card
(web/icons/og-landing.png): the mark, the headline and its line, and the hero song's four parts as the
mixer draws them. Run it again when a headline or the mark changes:

    cd server && python3 tool/link_cards.py
    ONLY=tools-tuner python3 tool/link_cards.py   # just the named cards

Needs Google Chrome and Pillow (no npm). The cards are committed; the site uses one when it's there.
"""
import html
import json
import os
import subprocess
import sys
import tempfile
from pathlib import Path

from PIL import Image

SERVER = Path(__file__).resolve().parents[1]
OUT = SERVER.parent / "web" / "icons" / "og"
CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"

TEMPLATE = """<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400..900&display=swap">
<style>
html,body{{margin:0}}
body{{width:1200px;height:630px;box-sizing:border-box;padding:72px 80px;background:#17120f;color:#f4eee6;font-family:'Figtree',sans-serif;display:flex;flex-direction:column;overflow:hidden;position:relative}}
.brand{{display:flex;align-items:center;gap:16px;font-weight:800;font-size:34px}}
.brand svg{{width:56px;height:56px}}
.eyebrow{{margin-top:56px;font-size:24px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#4ccfb0}}
h1{{margin:18px 0 0;font-size:{size}px;line-height:1.06;letter-spacing:-.02em;font-weight:800;max-width:900px}}
.stack{{position:absolute;right:80px;bottom:72px;display:grid;gap:12px;width:220px}}
.stack b{{display:block;height:20px;border-radius:12px}}
</style></head><body>
<div class="brand">{mark}Stackapella</div>
<div class="eyebrow">{eyebrow}</div>
<h1>{headline}</h1>
<div class="stack"><b style="width:100%;background:#ff9139"></b><b style="width:74%;background:#ffea6b"></b><b style="width:88%;background:#45eca4"></b><b style="width:62%;background:#968dfd"></b></div>
</body></html>"""


def size_for(headline):
    n = len(headline)
    return 84 if n <= 40 else 72 if n <= 60 else 60 if n <= 80 else 52


def mark():
    """The brand mark from its one source (web/tool/icons.py writes web/icons/icon.svg), rounded."""
    svg = (SERVER.parent / "web" / "icons" / "icon.svg").read_text()
    return svg.replace('<rect width="512" height="512"', '<rect width="512" height="512" rx="86"', 1).replace("{", "{{").replace("}", "}}")


LANDING = """<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400..900&display=swap">
<style>
html,body{margin:0}
body{width:1200px;height:630px;box-sizing:border-box;padding:0 0 0 72px;background:#17120f;color:#f4eee6;font-family:'Figtree',sans-serif;display:grid;grid-template-columns:600px 1fr;align-items:center;overflow:hidden}
.brand{display:flex;align-items:center;gap:16px;font-weight:800;font-size:34px}
.brand svg{width:56px;height:56px}
h1{margin:44px 0 0;font-size:92px;line-height:.94;letter-spacing:-.045em;font-weight:800}
h1 span{display:block}h1 span+span{color:#ffea6b}
p{margin:30px 0 0;font-size:26px;color:#a59c92}
.rows{display:grid;gap:16px}
.row{display:grid;grid-template-columns:60px 120px 1fr;gap:18px;align-items:center;height:94px;padding:0 0 0 20px;border-radius:24px 0 0 24px;background:#211b17;border:1px solid #2e2621;border-right:0}
.disc{display:grid;place-items:center;width:60px;height:60px;border-radius:50%;font-weight:800;font-size:24px;color:#17120f}
.name{font-weight:800;font-size:22px}
.roll svg{display:block;width:100%;height:60px}.roll .b{fill:currentColor}
</style></head><body>
<div><div class="brand">{mark}Stackapella</div><h1><span>{headline}</span><span>{headline_em}</span></h1><p>{lead}</p></div>
<div class="rows">{rows}</div>
</body></html>"""

# A song collection's card (SitePages::linkCards, a card with a `theme`): designed for its page, as Hillel asked
# (2026-11-11: "give these special pages a special meta image"). Its name, its headline with the serif word the page
# sets in gold, and its one-line promise, over its own colour and motif; no song count, so it never goes stale. The
# carols' card is the season's: the green hero's lights and snow, and the mark in Hillel's Santa hat (hat3e).
COLLECTION = """<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400..900&display=swap">
<style>
html,body{margin:0}
body{width:1200px;height:630px;box-sizing:border-box;padding:76px 80px 72px;background:var(--bg);color:#f7f1e6;font-family:'Figtree',sans-serif;display:flex;flex-direction:column;overflow:hidden;position:relative}
.brand{position:relative;display:flex;align-items:center;gap:16px;font-weight:800;font-size:34px;z-index:2}
.mark{position:relative;width:56px;height:56px}.mark svg{width:56px;height:56px;display:block}
.hat{position:absolute;left:-17px;top:-22px;width:73px;height:78px;max-width:none;filter:drop-shadow(0 1px 1px rgba(0,0,0,.3))}
.eyebrow{position:relative;z-index:2;margin-top:auto;font-size:24px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--ac)}
h1{position:relative;z-index:2;margin:16px 0 0;font-size:{size}px;line-height:1.02;letter-spacing:-.025em;font-weight:800;max-width:{width}px}
h1 em{display:block;font-family:ui-serif,'Iowan Old Style','Palatino Linotype',Palatino,Georgia,serif;font-style:italic;font-weight:400;letter-spacing:-.01em;color:var(--ac)}
p{position:relative;z-index:2;margin:22px 0 0;max-width:680px;font-size:27px;line-height:1.35;color:var(--mu)}
.motif{position:absolute;inset:0;z-index:1;pointer-events:none}
{css}
</style></head><body style="--bg:{bg};--ac:{ac};--mu:{mu}">
<div class="motif">{motif}</div>
<div class="brand"><span class="mark">{hat}{mark}</span>Stackapella</div>
<div class="eyebrow">{eyebrow}</div>
<h1>{headline}<em>{em}</em></h1>
<p>{lead}</p>
</body></html>"""


def _lights():
    colours = ["#E2513B", "#E9C46A", "#2BA58A", "#F7F1E6", "#7FA6E8"]
    drops = [6, 14, 19, 11]
    return '<div class="lights">' + "".join(
        f'<i style="--b:{colours[i % 5]};top:{drops[i % 4] + 4}px"></i>' for i in range(22)) + "</div>"


def _snow():
    # A fixed scatter (no randomness, so a re-render draws the same card).
    flakes = []
    for i in range(46):
        x = (i * 263) % 1200
        y = 60 + (i * 137) % 560
        r = 3 + (i * 7) % 6
        o = 0.35 + ((i * 13) % 50) / 100
        flakes.append(f'<i style="left:{x}px;top:{y}px;width:{r}px;height:{r}px;opacity:{o:.2f}"></i>')
    return '<div class="snow">' + "".join(flakes) + "</div>"


def _arches():
    # Three pointed windows at the right, their panes in the part colours, as stained glass.
    panes = ["#ff9139", "#ffea6b", "#45eca4", "#968dfd"]
    out = []
    for i, x in enumerate([890, 990, 1090]):
        h = 340 if i == 1 else 280
        y = 630 - h - 44
        cells = "".join(f'<rect x="{x + 7}" y="{y + 62 + k * ((h - 80) / 4)}" width="66" height="{(h - 80) / 4 - 6}" rx="3" fill="{panes[(k + i) % 4]}" opacity=".22"/>' for k in range(4))
        out.append(f'<path d="M{x} {y + h} V{y + 62} Q{x} {y} {x + 40} {y - 18} Q{x + 80} {y} {x + 80} {y + 62} V{y + h} Z" fill="none" stroke="#E9C46A" stroke-opacity=".55" stroke-width="3"/>{cells}')
    return '<svg width="1200" height="630" viewBox="0 0 1200 630">' + "".join(out) + "</svg>"


def _river():
    # Slow river lines across the foot and a few stars above: the deep river, by night.
    lines = []
    for k in range(5):
        y = 566 + k * 15
        lines.append(f'<path d="M-20 {y} C 180 {y - 22}, 380 {y + 22}, 600 {y} S 1020 {y - 22}, 1220 {y}" fill="none" stroke="#7FA6E8" stroke-opacity="{0.42 - k * 0.05:.2f}" stroke-width="3"/>')
    stars = "".join(f'<circle cx="{700 + (i * 97) % 460}" cy="{70 + (i * 61) % 300}" r="{2 + i % 3}" fill="#F2D38A" opacity="{0.4 + (i % 4) * 0.12:.2f}"/>' for i in range(14))
    return '<svg width="1200" height="630" viewBox="0 0 1200 630">' + stars + "".join(lines) + "</svg>"


def _waves():
    # Rolling swells at the foot, a rope's dashed line along the top.
    waves = []
    for k, (y, o) in enumerate([(574, .3), (600, .42), (626, .55)]):
        d = f"M-40 {y} " + " ".join(f"Q {-40 + 75 + j * 150} {y - 18} {-40 + 150 + j * 150} {y} T {-40 + 300 + j * 150} {y}" for j in range(0, 10, 2))
        waves.append(f'<path d="{d}" fill="none" stroke="#7FD3C4" stroke-opacity="{o}" stroke-width="4"/>')
    rope = '<path d="M0 28 H1200" stroke="#F0D9A8" stroke-opacity=".45" stroke-width="6" stroke-dasharray="14 8"/>'
    return '<svg width="1200" height="630" viewBox="0 0 1200 630">' + rope + "".join(waves) + "</svg>"


def _rings():
    # Four voices entering one after another: the same ring, each a step later, in the part colours.
    colours = ["#ff9139", "#ffea6b", "#45eca4", "#968dfd"]
    rings = "".join(f'<circle cx="{960 + k * 34}" cy="{300 + k * 22}" r="{170 - k * 6}" fill="none" stroke="{colours[k]}" stroke-opacity=".5" stroke-width="12" stroke-dasharray="{(170 - k * 6) * 6.283 * (0.95 - k * 0.18):.0f} 2000" transform="rotate({-90 + k * 6} {960 + k * 34} {300 + k * 22})" stroke-linecap="round"/>' for k in range(4))
    return '<svg width="1200" height="630" viewBox="0 0 1200 630">' + rings + "</svg>"


# Each collection's colour, gold and muted line, its motif and that motif's CSS.
THEMES = {
    "carols": {"bg": "#12372A", "ac": "#E9C46A", "mu": "#C9D8CD", "motif": lambda: _snow() + _lights(), "hat": True,
               "css": ".lights{position:absolute;inset:0 0 auto;height:44px;display:flex;justify-content:space-between;padding:0 18px}"
                      ".lights i{position:relative;width:13px;height:19px;border-radius:50% 50% 50% 50%/40% 40% 60% 60%;background:var(--b);box-shadow:0 0 16px 3px var(--b);opacity:.9}"
                      ".lights i::before{content:'';position:absolute;top:-5px;left:3px;width:7px;height:5px;border-radius:1px;background:#3D5A4C}"
                      ".snow i{position:absolute;border-radius:50%;background:radial-gradient(circle,#fff 0 42%,rgba(255,255,255,.55) 62%,rgba(255,255,255,0) 72%)}"
                      "body{padding-top:84px}"},
    "hymns": {"bg": "#2B1B34", "ac": "#E9C46A", "mu": "#D8C9DD", "motif": _arches, "css": ""},
    "spirituals": {"bg": "#14243F", "ac": "#F2B84B", "mu": "#C8D3E6", "motif": _river, "css": ""},
    "sea-shanties": {"bg": "#0D3340", "ac": "#F0D9A8", "mu": "#BFDCD6", "motif": _waves, "css": "body{padding-top:84px}"},
    "rounds": {"bg": "#3A2416", "ac": "#FFB45C", "mu": "#E6D3C2", "motif": _rings, "css": ""},
}


def collection(card, tmp):
    theme = THEMES[card["theme"]]
    hat = ""
    if theme.get("hat"):
        hat = '<img class="hat" alt="" src="' + (SERVER.parent / "web" / "icons" / "santa-hat.svg").as_uri() + '">'
    words = len(card["headline"]) + len(card.get("em", ""))
    page = Path(tmp) / f"{card['key']}.html"
    page.write_text(COLLECTION.replace("{css}", theme["css"]).replace("{motif}", theme["motif"]()).replace("{hat}", hat).replace("{mark}", mark().replace("{{", "{").replace("}}", "}"))
                    .replace("{size}", "76" if words <= 36 else "68").replace("{width}", "720" if card["theme"] in ("hymns", "rounds") else "900")
                    .replace("{bg}", theme["bg"]).replace("{ac}", theme["ac"]).replace("{mu}", theme["mu"])
                    .replace("{eyebrow}", html.escape(card["eyebrow"])).replace("{headline}", html.escape(card["headline"])).replace("{em}", html.escape(card.get("em", "")))
                    .replace("{lead}", html.escape(card.get("lead", ""))))
    shoot(page, OUT / f"{card['key']}.png", tmp, card["key"], colors=0)


# The parts' colours, as the mark's bars (web/icons/icon.svg), top to bottom.
COLOURS = {1: "#ff9139", 2: "#ffea6b", 3: "#45eca4", 4: "#968dfd"}

# The landing's words and the hero song's parts, each with its notes drawn (Landing::hero, Pictures::roll).
LANDING_DATA = (
    "$h = App\\Support\\Pages\\Landing::hero(); echo json_encode(["
    "'headline' => __('pages.landing.headline'), 'headline_em' => __('pages.landing.headline_em'), 'lead' => __('pages.landing.lead'), "
    "'parts' => array_map(fn ($p) => ['name' => $p['name'], 'color' => $p['color'], 'roll' => (string) App\\Support\\Site\\Pictures::roll($h, $p, 60)], $h['parts'] ?? [])]);"
)


def shoot(page, out, tmp, name, colors=96):
    """A page drawn at 1200 by 630 in headless Chrome, kept as a small PNG (a designed card, colors=0, in full colour)."""
    shot = Path(tmp) / f"{name}.png"
    # Each run its own profile, so one Chrome never waits on the last one's lock.
    subprocess.run([CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars", "--force-device-scale-factor=1", "--virtual-time-budget=4000",
                    f"--user-data-dir={Path(tmp) / ('profile-' + name)}", f"--screenshot={shot}", "--window-size=1200,630", page.as_uri()],
                   check=True, capture_output=True)
    image = Image.open(shot)
    # A designed card keeps every colour (colors=0): its motif's lights would otherwise take the mark's palette entries.
    (image.convert("RGB") if colors == 0 else image.convert("P", palette=Image.ADAPTIVE, colors=colors)).save(out, optimize=True)


def landing(tmp):
    data = json.loads(subprocess.check_output(["php", "artisan", "tinker", "--execute", LANDING_DATA], cwd=SERVER).decode().strip().splitlines()[-1])
    if len(data["parts"]) != 4:
        sys.exit("the landing card needs the hero song in the library (php artisan migrate --seed)")
    rows = "".join(
        f'<div class="row"><span class="disc" style="background:{COLOURS[p["color"]]}">{html.escape(p["name"][0])}</span>'
        f'<span class="name">{html.escape(p["name"])}</span><span class="roll" style="color:{COLOURS[p["color"]]}">{p["roll"]}</span></div>'
        for p in data["parts"]
    )
    page = Path(tmp) / "landing.html"
    page.write_text(LANDING.replace("{mark}", mark().replace("{{", "{").replace("}}", "}")).replace("{headline}", html.escape(data["headline"]))
                    .replace("{headline_em}", html.escape(data["headline_em"])).replace("{lead}", html.escape(data["lead"])).replace("{rows}", rows))
    shoot(page, SERVER.parent / "web" / "icons" / "og-landing.png", tmp, "landing")
    print("drew landing")


def main():
    cards = json.loads(subprocess.check_output(["php", "artisan", "app:site:link-cards"], cwd=SERVER))
    # ONLY=tools-tuner,tools (comma-separated keys) draws just those, for a page that's new, and touches no other card.
    only = [k for k in os.environ.get("ONLY", "").split(",") if k]
    if only:
        cards = [c for c in cards if c["key"] in only]
        if len(cards) != len(only):
            raise SystemExit(f"ONLY names a card that isn't one: {only}")
    OUT.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory() as tmp:
        for card in cards:
            if card.get("theme"):
                collection(card, tmp)
                print("drew", card["key"])
                continue
            page = Path(tmp) / f"{card['key']}.html"
            page.write_text(TEMPLATE.replace("{mark}", mark()).format(size=size_for(card["headline"]), eyebrow=html.escape(card["eyebrow"]), headline=html.escape(card["headline"])))
            shoot(page, OUT / f"{card['key']}.png", tmp, card["key"])
            print("drew", card["key"])
        if not only:
            landing(tmp)
    if only:
        return
    # Cards for pages that no longer exist go.
    keys = {c["key"] for c in cards}
    for old in OUT.glob("*.png"):
        if old.stem not in keys:
            old.unlink()
            print("removed", old.stem)


if __name__ == "__main__":
    sys.exit(main())
