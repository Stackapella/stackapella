"""Writes the app's colour themes from the designer's palettes.json (docs/decisions.md, "themes"):
web/css/themes.css, the CSS variables for each [colour x mode] on <html data-theme="...">, and
web/js/theme-boot.js's table of theme colours (the browser bar). In each light theme, the public
site's ink band (`.s-ink`, docs/marketing-redesign-plan.md) takes its dark twin's surfaces, words and
part colours, so the glass and gems in it look as they do in the app's dark theme. Run after the
palettes change:

    python3 -I web/tool/themes.py web/tool/palettes.json

Standard library only (no npm). The default theme (stage-dark, the unchosen dark) is also @theme's values in input.css,
so a page that hasn't chosen yet looks as it always has.
"""
import json
import re
import sys
from pathlib import Path

WEB = Path(__file__).resolve().parents[1]
HEX = re.compile(r"^#[0-9a-f]{6}$")


def mix(a, b, share):
    """`share` of colour a over b, as hex."""
    pa, pb = [int(a[i:i + 2], 16) for i in (1, 3, 5)], [int(b[i:i + 2], 16) for i in (1, 3, 5)]
    return "#" + "".join(f"{round(x * share + y * (1 - share)):02x}" for x, y in zip(pa, pb))


def edge(p):
    """A card's outline (Hillel: borders looked fuzzy on dark): quieter than `line` (which inputs keep,
    for their 3:1 boundary), so surfaces read by their fill: half the way to the panel in dark, a
    little in light."""
    return mix(p["line"].lower(), p["panel"].lower(), 0.45 if p["mode"] == "dark" else 0.8)


def main(source):
    palettes = json.loads(Path(source).read_text())
    blocks = []
    colours = {}
    token_sets = {}
    for pid, p in palettes.items():
        # The four parts' words in one colour per theme (Hillel: "alto A and toggle are black, rest are white").
        assert len(set(p["partText"][:4])) == 1, f"{pid}: the four parts' text colours differ: {p['partText'][:4]}"
        tokens = {
            "ink": p["ink"], "panel": p["panel"], "raised": p["raised"], "line": p["line"], "edge": edge(p),
            "cream": p["cream"], "muted": p["muted"], "accent": p["accent"], "good": p["good"],
            "warn": p["warn"], "bad": p["bad"], "record": p["record"], "record-label": p["recordLabel"],
        }
        for i in range(8):
            tokens[f"part-{i + 1}"] = p["parts"][i]
            tokens[f"part-text-{i + 1}"] = p["partText"][i]
            tokens[f"part-ink-{i + 1}"] = p["partInk"][i]
        for name, value in tokens.items():
            assert HEX.match(value.lower()), f"{pid}.{name}: {value}"
        lines = "\n".join(f"    --color-{name}: {value.lower()};" for name, value in tokens.items())
        blocks.append(f':root[data-theme="{pid}"] {{\n    color-scheme: {p["mode"]};\n{lines}\n}}')
        colours[pid] = p["themeColor"].lower()
        token_sets[pid] = tokens
    # The ink band in a light theme: its dark twin's colours, all but the accent and the record red
    # (a chosen accent, css/accents.css, still holds); the band's own background is the twin's ink.
    for pid, tokens in token_sets.items():
        family, mode = pid.rsplit("-", 1)
        if mode != "light" or f"{family}-dark" not in token_sets:
            continue
        twin = {k: v for k, v in token_sets[f"{family}-dark"].items() if k not in ("accent", "record", "record-label")}
        lines = "\n".join(f"    --color-{name}: {value.lower()};" for name, value in twin.items())
        blocks.append(f':root[data-theme="{pid}"] .s-ink {{\n    color-scheme: dark;\n    --s-ink-bg: {twin["ink"].lower()};\n{lines}\n}}')
    # The default (Stage dark) is also @theme's values, so the app looks right before a theme is set.
    default = next(b for b in blocks if 'data-theme="stage-dark"' in b)
    values = "\n".join(line for line in default.splitlines() if line.strip().startswith("--color-"))
    source = WEB / "css/input.css"
    css = source.read_text()
    css = re.sub(r"(/\* theme:start \*/\n).*?(\s*/\* theme:end \*/)", lambda m: m.group(1) + values + m.group(2), css, flags=re.S)
    source.write_text(css)
    header = "/* Written by web/tool/themes.py from the designer's palettes: don't edit by hand. */\n"
    (WEB / "css/themes.css").write_text(header + "\n\n".join(blocks) + "\n")
    boot = WEB / "js/theme-boot.js"
    text = boot.read_text()
    table = "const COLOURS = " + json.dumps(colours, separators=(", ", ": ")) + ";"
    text = re.sub(r"const COLOURS = \{.*?\};", table, text, flags=re.S)
    boot.write_text(text)
    # The picker's miniatures (components/appearance.js): each family's bg, text and S A T B, dark then light.
    previews = {}
    for pid, p in palettes.items():
        family, mode = pid.rsplit("-", 1)
        previews.setdefault(family, [None, None])[0 if mode == "dark" else 1] = [p["ink"], p["cream"], *p["parts"][:4]]
    picker = WEB / "js/components/appearance.js"
    text = picker.read_text()
    lines = "\n".join(f"    {family}: {json.dumps(pair)}," for family, pair in previews.items()).replace('"', "'")
    text = re.sub(r"const PREVIEWS = \{.*?\n\};", lambda m: "const PREVIEWS = {\n" + lines + "\n};", text, flags=re.S)
    picker.write_text(text)
    print(f"{len(palettes)} themes written")


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else WEB / "tool/palettes.json")
