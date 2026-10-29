"""Draws each public page's link card (web/icons/og/<key>.png, 1200 by 630) from one template:
the mark (web/icons/icon.svg), the page's eyebrow and headline in Figtree (`php artisan app:site:link-cards`, the pages' own words),
and the four part colours stacked, as the mark has them. Warm dark behind. Then the landing's own card
(web/icons/og-landing.png): the mark, the headline and its line, and the hero song's four parts as the
mixer draws them. Run it again when a headline or the mark changes:

    cd server && python3 tool/link_cards.py

Needs Google Chrome and Pillow (no npm). The cards are committed; the site uses one when it's there.
"""
import html
import json
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

# The parts' colours, as the mark's bars (web/icons/icon.svg), top to bottom.
COLOURS = {1: "#ff9139", 2: "#ffea6b", 3: "#45eca4", 4: "#968dfd"}

# The landing's words and the hero song's parts, each with its notes drawn (Landing::hero, Pictures::roll).
LANDING_DATA = (
    "$h = App\\Support\\Pages\\Landing::hero(); echo json_encode(["
    "'headline' => __('pages.landing.headline'), 'headline_em' => __('pages.landing.headline_em'), 'lead' => __('pages.landing.lead'), "
    "'parts' => array_map(fn ($p) => ['name' => $p['name'], 'color' => $p['color'], 'roll' => (string) App\\Support\\Site\\Pictures::roll($h, $p, 60)], $h['parts'] ?? [])]);"
)


def shoot(page, out, tmp, name):
    """A page drawn at 1200 by 630 in headless Chrome, kept as a small PNG."""
    shot = Path(tmp) / f"{name}.png"
    # Each run its own profile, so one Chrome never waits on the last one's lock.
    subprocess.run([CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars", "--force-device-scale-factor=1", "--virtual-time-budget=4000",
                    f"--user-data-dir={Path(tmp) / ('profile-' + name)}", f"--screenshot={shot}", "--window-size=1200,630", page.as_uri()],
                   check=True, capture_output=True)
    Image.open(shot).convert("P", palette=Image.ADAPTIVE, colors=96).save(out, optimize=True)


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
    OUT.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory() as tmp:
        for card in cards:
            page = Path(tmp) / f"{card['key']}.html"
            page.write_text(TEMPLATE.replace("{mark}", mark()).format(size=size_for(card["headline"]), eyebrow=html.escape(card["eyebrow"]), headline=html.escape(card["headline"])))
            shoot(page, OUT / f"{card['key']}.png", tmp, card["key"])
            print("drew", card["key"])
        landing(tmp)
    # Cards for pages that no longer exist go.
    keys = {c["key"] for c in cards}
    for old in OUT.glob("*.png"):
        if old.stem not in keys:
            old.unlink()
            print("removed", old.stem)


if __name__ == "__main__":
    sys.exit(main())
