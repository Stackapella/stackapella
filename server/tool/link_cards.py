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

# A song collection's card (SitePages::linkCards, a card with a `theme`), in Hillel's "Genre banners redesign"
# (2026-11-11: "1200x630 · shared grid, shared part lanes motif, genre accent kept small"): one grid for every card, the
# mark (in Hillel's Santa hat, hat3e, on the carols' card, the season's), the eyebrow, a big headline with its serif
# words in gold, the page's one promise and "Pick your part" with what's free; at right the part lanes, a real song's
# opening from its file's notes (LinkCardLanes), one part lit; the genre's own mark kept small. No count, so no card goes stale.
COLLECTION = """<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@500;600;800&family=Newsreader:ital,opsz,wght@1,6..72,500&family=JetBrains+Mono:wght@500&display=swap">
<style>
html,body{margin:0}
body{width:1200px;height:630px;position:relative;overflow:hidden;background:var(--bg);color:#f6efe2;font-family:'Figtree',sans-serif}
.accent{position:absolute;inset:0;pointer-events:none}
.brand{position:absolute;left:80px;top:72px;display:flex;align-items:center;gap:14px;font:800 30px/1 Figtree;letter-spacing:-.01em;z-index:2}
.mark{position:relative;width:48px;height:48px}.mark svg{width:48px;height:48px;display:block}
.hat{position:absolute;left:-14px;top:-19px;width:62px;height:67px;max-width:none;filter:drop-shadow(0 1px 1px rgba(0,0,0,.3))}
.words{position:absolute;left:80px;top:150px;bottom:64px;width:580px;display:flex;flex-direction:column;justify-content:center;gap:18px;z-index:2}
.eyebrow{font:800 17px/1 Figtree;letter-spacing:.16em;text-transform:uppercase;color:var(--ac)}
h1{margin:0;font:800 {size}px/1.02 Figtree;letter-spacing:-.025em;text-wrap:balance}
h1 em{display:block;font:italic 500 {emsize}px/1.06 Newsreader,Georgia,serif;letter-spacing:0;color:var(--ac)}
.promise{font:500 24px/1.4 Figtree;color:var(--mu);text-wrap:pretty}
.cta{display:flex;align-items:center;gap:18px;margin-top:4px}
.pill{background:var(--ac);color:var(--bg);font:800 20px/1 Figtree;padding:16px 26px;border-radius:999px;white-space:nowrap}
.free{font:600 18px/1.2 Figtree;color:var(--mu)}
.lanes{position:absolute;right:72px;top:50%;transform:translateY(-50%);width:420px;padding:28px 28px 24px;box-sizing:border-box;border-radius:20px;background:rgba(0,0,0,.2);border:1px solid rgba(246,239,226,.1);display:flex;flex-direction:column;gap:14px;z-index:2}
.lane{display:flex;align-items:center;gap:14px}
.lane .k{width:22px;font:500 16px/1 'JetBrains Mono',monospace}
.lane .track{position:relative;flex:1;height:34px;border-radius:8px;background:rgba(246,239,226,.06)}
.lane .track i{position:absolute;height:10px;border-radius:4px}
.cap{display:flex;justify-content:space-between;gap:16px;font:500 14px/1 'JetBrains Mono',monospace;color:var(--mu);margin-top:6px}
{css}
</style></head><body style="--bg:{bg};--ac:{ac};--mu:{mu}">
<div class="accent">{motif}</div>
<div class="brand"><span class="mark">{hat}{mark}</span>Stackapella</div>
<div class="words">
<div class="eyebrow">{eyebrow}</div>
<h1>{headline}<em>{em}</em></h1>
<div class="promise">{promise}</div>
<div class="cta"><div class="pill">{pick} &#8594;</div><div class="free">{free}</div></div>
</div>
{lanes}
</body></html>"""

# The card's two fixed lines (the site's own: every part page plays for guests and the courses are free; Hillel, 2026-11-08).
PICK = "Pick your part"
FREE = "Free · In your browser · No account"


def _lights():
    # The carols' string of lights along the top edge, small (Hillel: "genre accent kept small").
    colours = ["#E2513B", "#E9C46A", "#2BA58A", "#7FA6E8", "#F7F1E6"]
    return '<div class="lights">' + "".join(f'<i style="--b:{colours[i % 5]};margin-top:{round(8 + 6 * __import__("math").sin(i / 21 * 3.1416 * 3))}px"></i>' for i in range(22)) + "</div>"


def _stars():
    # The spirituals' few stars, top right.
    return "".join(f'<span class="star" style="left:{x}px;top:{y}px;width:{r}px;height:{r}px"></span>' for x, y, r in [(760, 70, 6), (812, 92, 5), (868, 104, 6), (926, 124, 7), (986, 168, 6), (1050, 150, 5), (1100, 96, 5)])


def _arches():
    # The hymns' arches, a small row at the top right, outline only.
    return '<svg width="1200" height="630" viewBox="0 0 1200 630">' + "".join(f'<path d="M{x} 130 V84 Q{x} 58 {x + 22} 46 Q{x + 44} 58 {x + 44} 84 V130" fill="none" stroke="#E9C46A" stroke-opacity=".4" stroke-width="2.5"/>' for x in (900, 960, 1020, 1080)) + "</svg>"


def _waves():
    # The shanties' waves, two faint lines at the foot.
    return '<svg width="1200" height="630" viewBox="0 0 1200 630">' + "".join(f'<path d="M-40 {y} ' + " ".join(f"Q {-40 + 75 + j * 150} {y - 14} {-40 + 150 + j * 150} {y} T {-40 + 300 + j * 150} {y}" for j in range(0, 10, 2)) + f'" fill="none" stroke="#7FD3C4" stroke-opacity="{o}" stroke-width="3"/>' for y, o in [(596, .25), (616, .35)]) + "</svg>"


def _rings():
    # The rounds' four voices entering, small rings at the top right.
    colours = ["#ff9139", "#ffea6b", "#45eca4", "#968dfd"]
    return '<svg width="1200" height="630" viewBox="0 0 1200 630">' + "".join(f'<circle cx="{1060 + k * 14}" cy="{70 + k * 8}" r="{40 - k * 3}" fill="none" stroke="{colours[k]}" stroke-opacity=".45" stroke-width="5" stroke-dasharray="{round((40 - k * 3) * 6.283 * (0.95 - k * 0.18))} 400"/>' for k in range(4)) + "</svg>"


def _flames():
    # The Jewish songs' nine small lights, the middle one raised, at the top right (the lamp itself isn't drawn).
    out = []
    for k in range(9):
        x = 880 + k * 30
        y = 58 if k == 4 else 72
        out.append(f'<path d="M{x} {y + 30} C {x - 9} {y + 21}, {x - 6} {y + 9}, {x} {y} C {x + 6} {y + 9}, {x + 9} {y + 21}, {x} {y + 30} Z" fill="#F3D27A" opacity="{0.85 if k == 4 else 0.6}"/>')
    return '<svg width="1200" height="630" viewBox="0 0 1200 630">' + "".join(out) + "</svg>"


def _hills():
    # The folk songs' hills and a winding path, faint lines at the top right.
    return ('<svg width="1200" height="630" viewBox="0 0 1200 630">'
            '<path d="M760 120 C 840 82, 910 86, 980 106 S 1120 126, 1200 96" fill="none" stroke="#E9A27A" stroke-opacity=".45" stroke-width="3"/>'
            '<path d="M840 150 C 920 112, 1000 116, 1070 132 S 1170 142, 1200 132" fill="none" stroke="#E9A27A" stroke-opacity=".35" stroke-width="3"/>'
            '<path d="M1040 150 C 1048 136, 1074 130, 1068 118 S 1084 104, 1106 98" fill="none" stroke="#E9A27A" stroke-opacity=".45" stroke-width="2.5" stroke-dasharray="6 6"/></svg>')


def _stripes():
    # Barbershop's pole stripes, a short diagonal band at the top right.
    return '<svg width="1200" height="630" viewBox="0 0 1200 630">' + "".join(f'<path d="M{930 + i * 26} 52 l 20 0 l -18 28 l -20 0 z" fill="#E8C26A" fill-opacity="{.5 if i % 2 == 0 else .25}"/>' for i in range(9)) + "</svg>"


# Each collection's ground, gold and muted line, its small accent and that accent's CSS.
THEMES = {
    "carols": {"bg": "#163D2D", "ac": "#F2C46B", "mu": "#C8D3C6", "motif": _lights, "hat": True,
               "css": ".lights{position:absolute;inset:0 0 auto;height:44px;display:flex;justify-content:space-between;padding:0 40px}"
                      ".lights i{width:12px;height:16px;border-radius:50% 50% 50% 50%/60% 60% 40% 40%;background:var(--b);box-shadow:0 0 14px var(--b)}"},
    "hymns": {"bg": "#2B1B35", "ac": "#F2C46B", "mu": "#D3C6D9", "motif": _arches, "css": ""},
    "spirituals": {"bg": "#14223F", "ac": "#F2C46B", "mu": "#C3CEE3", "motif": _stars, "css": ".star{position:absolute;border-radius:50%;background:#F2C46B}"},
    "sea-shanties": {"bg": "#0E3440", "ac": "#F2C46B", "mu": "#BAD7D1", "motif": _waves, "css": ""},
    "rounds": {"bg": "#3A2416", "ac": "#FFB45C", "mu": "#E3D1C1", "motif": _rings, "css": ""},
    "jewish-songs": {"bg": "#1B2D63", "ac": "#F3D27A", "mu": "#CDD6EF", "motif": _flames, "css": ""},
    "folk-songs": {"bg": "#2F3B21", "ac": "#E9A27A", "mu": "#D6DCC8", "motif": _hills, "css": ""},
    "barbershop-songs": {"bg": "#3A1420", "ac": "#E8C26A", "mu": "#EAD3D7", "motif": _stripes, "css": ""},
}

# The lanes' part colours, soprano to bass (the mark's own, as the songs colour their parts).
LANE_COLOURS = ["#ff9139", "#ffea6b", "#45eca4", "#968dfd"]
LANE_KEYS = ["S", "A", "T", "B"]


def _lanes(lanes):
    if not lanes:
        return ""
    rows = []
    for i, notes in enumerate(lanes["lanes"]):
        colour = LANE_COLOURS[i % 4]
        opacity = 1 if i == lanes["lit"] else 0.32
        bars = "".join(f'<i style="left:{n["x"] * 100:.2f}%;width:{max(n["w"] * 100 - 0.8, 0.8):.2f}%;top:{round(4 + (1 - n["y"]) * 20)}px;background:{colour}"></i>' for n in notes)
        rows.append(f'<div class="lane" style="opacity:{opacity}"><div class="k" style="color:{colour}">{html.escape(lanes.get("keys", LANE_KEYS)[i % 4])}</div><div class="track">{bars}</div></div>')
    caption = f'<div class="cap"><div>{html.escape(lanes["song"])} · {html.escape(lanes["part"])}</div><div>{html.escape(lanes["length"])}</div></div>'
    return '<div class="lanes">' + "".join(rows) + caption + "</div>"


def collection(card, tmp):
    theme = THEMES[card["theme"]]
    hat = ""
    if theme.get("hat"):
        hat = '<img class="hat" alt="" src="' + (SERVER.parent / "web" / "icons" / "santa-hat.svg").as_uri() + '">'
    words = len(card["headline"])
    page = Path(tmp) / f"{card['key']}.html"
    page.write_text(COLLECTION.replace("{css}", theme["css"]).replace("{motif}", theme["motif"]()).replace("{hat}", hat).replace("{mark}", mark().replace("{{", "{").replace("}}", "}"))
                    .replace("{size}", "66" if words <= 28 else "58").replace("{emsize}", "68" if words <= 28 else "60")
                    .replace("{bg}", theme["bg"]).replace("{ac}", theme["ac"]).replace("{mu}", theme["mu"])
                    .replace("{eyebrow}", html.escape(card["eyebrow"])).replace("{headline}", html.escape(card["headline"])).replace("{em}", html.escape(card.get("em", "")))
                    .replace("{promise}", html.escape(card.get("promise", ""))).replace("{pick}", PICK).replace("{free}", FREE).replace("{lanes}", _lanes(card.get("lanes"))))
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
