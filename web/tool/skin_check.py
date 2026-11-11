"""Guards the game skin's selectors (docs/game-style-cleanup-plan.md, step 6; run by tool/test.sh): a skin rule may style
a token, a primitive, a component class or a hook (data-ui, data-*), never reach into a screen by its markup path:
'#app ... >' (a child path from the app's root), [href=...], :nth-child/:nth-of-type, or a Tailwind utility class after
the skin prefix (.text-sm, .flex, .min-h-dvh ...). What remains is listed in ALLOWED below; that list should only shrink.
It also checks each colour theme's inks (the parts', good, warn, bad, muted, cream, accent-strong) reach AA on that theme's
arcade ground, panels and wells (the 8-theme check, 2026-11-02: the arcade's own surfaces made some miss).
"""
import json
import re
import sys
from pathlib import Path

CSS = Path(__file__).resolve().parents[1] / "css" / "input.css"
THEMES_CSS = CSS.with_name("themes.css")
THEMES = [f"{c}-{m}" for m in ("dark", "light") for c in ("stage", "warm", "midnight", "graphite")]
INKS = [f"--color-part-ink-{i}" for i in range(1, 9)] + ["--color-good", "--color-warn", "--color-bad", "--color-muted", "--color-cream", "--color-accent-strong"]
SKIN = re.compile(r"data-skin|data-gs|\.s-arcade")
TAILWIND = re.compile(r"\.(?:text|bg|p[xytrbl]?|m[xytrbl]?|flex|grid|gap|w|h|min|max|font|border|items|justify|space|divide|truncate|leading|tracking|shrink|grow|size|inset|top|start|end|absolute|relative|hidden|block|inline|overflow|z|opacity|shadow|ring|order|col|row|self|place|uppercase|italic|tabular|rounded)(?:-[-a-z0-9\[\]/:.]+)?(?![-\w])")
# What's still allowed, by a piece of its selector (each with why). Only shrink this.
ALLOWED = {
    ".ge-title-stripes i:nth-child": "the title card's own four stripes (its internals, not a screen)",
    ".ge-stat > span:nth-child": "a portrait's own stat row (its internals)",
    ".rounded-full:not(": "the pill rule: a fully rounded button, link or tag with words is square (rounded-full isn't a token)",
}


def problems(text):
    found = []
    for number, line in enumerate(text.splitlines(), 1):
        if "{" not in line or not SKIN.search(line):
            continue
        selector = line.split("{")[0]
        if any(piece in selector for piece in ALLOWED):
            continue
        why = []
        if re.search(r"#app\b(?:(?!\[data-)[^,])*>", selector):
            why.append("a child path from #app (no hook before the >)")
        if "[href=" in selector:
            why.append("[href=...]")
        if re.search(r":nth-(?:child|of-type)", selector):
            why.append(":nth-child")
        after = SKIN.split(selector, 1)
        if len(after) > 1 and TAILWIND.search(after[1]):
            why.append("a Tailwind utility class")
        if why:
            found.append(f"input.css:{number}: {', '.join(why)}: {selector.strip()[:120]}")
    return found


# Game words in the app's own words (the serious skin's leak, 2026-11-11: a guest's Home said "Your 3 stars from this
# course so far" in serious mode, from the course wall). A key whose English names stars, XP, combos or streaks is a game
# word: a line of the app that says one must choose by skin on that line (game(), sk(...)), or live in js/game/ or
# js/admin/. The lines below are what was there when the check began: each is either inside a game-only block (a parent
# v-if="game()", a game-only screen), each saying why (the audit, 2026-11-11, fixed the leaks). Only shrink it; a new
# line guards itself (game() or sk() on the line).
LOCALE = CSS.parents[1] / "locales" / "en.json"
JS = CSS.parents[1] / "js"
GAME_WORD = re.compile(r"\bstars?\b|★|\bXP\b|\bcombos?\b|\bstreaks?\b", re.I)
WORDS_ALLOWED = {
    "components/appearance.js: appearance.gameMode.onHint": "the Game mode switch's own hint names what game mode adds",
    "components/boss.js: boss.xp": "the boss round's end, drawn only with the boss switch on (game mode)",
    "components/home.js: course.xp": "inside LevelBar's v-if=\"game()\"",
    "components/home.js: course.xpLabel": "inside v-if=\"game()\" (LevelBar, the course stats)",
    "components/home.js: results.levelXp": "inside the course stats' v-if=\"game()\"",
    "components/home.js: home.lead.welcomeLast": "the WELCOME table: serious picks its *Serious twin",
    "components/home.js: home.lead.welcomeYesterday": "the WELCOME table: serious picks its *Serious twin",
    "components/results.js: results.levelLabel": "inside the end screen's v-if=\"game()\" level",
    "components/results.js: results.levelXp": "inside the end screen's v-if=\"game()\" level",
    "components/session-wrap.js: wrap.week.keepStars": "keepLine's game branch (serious says lessons)",
    "results.js: results.end.one": "endLine's words, shown only under v-if=\"game()\" (components/results.js)",
    "results.js: results.end.two": "endLine's words, shown only under v-if=\"game()\"",
    "results.js: results.end.three": "endLine's words, shown only under v-if=\"game()\"",
    "results.js: results.end.up": "endLine's words, shown only under v-if=\"game()\"",
    "results.js: results.end.upToThree": "endLine's words, shown only under v-if=\"game()\"",
    "views/course.js: course.hub.toLevel": "inside the course page's v-if=\"game()\" level",
    "views/course.js: course.xpLabel": "inside the course page's v-if=\"game()\" level",
    "views/course.js: results.levelXp": "inside the course page's v-if=\"game()\" level",
}


def _strings(node, path=""):
    if isinstance(node, dict):
        for key, value in node.items():
            yield from _strings(value, f"{path}.{key}" if path else key)
    elif isinstance(node, str):
        yield path, node


def words(locale_text, js_files):
    """A game word said in the app on a line that doesn't choose by skin (both skins would show it)."""
    keys = {k for k, s in _strings(json.loads(locale_text)) if GAME_WORD.search(s) and not k.startswith(("admin.", "game.", "badges."))}
    found = []
    for name, text in js_files:
        if name.startswith(("admin/", "game/")):
            continue
        for number, line in enumerate(text.splitlines(), 1):
            if "game()" in line or "sk(" in line:
                continue
            for key in re.findall(r"""\b(?:t|tk)\(['"]([\w.]+)['"]""", line):
                if key in keys and f"{name}: {key}" not in WORDS_ALLOWED:
                    found.append(f"js/{name}:{number}: {key} names a game word in both skins: use sk() or game()")
    return found


def _block(text, selector):
    match = re.search(re.escape(selector) + r"\s*\{(.*?)\}", text, re.S)
    return dict(re.findall(r"(--[\w-]+):\s*(#[0-9a-fA-F]{6})\b", match.group(1))) if match else {}


def _contrast(a, b):
    def lum(hex_):
        r, g, b_ = (int(hex_[i:i + 2], 16) / 255 for i in (1, 3, 5))
        f = lambda v: v / 12.92 if v <= 0.04045 else ((v + 0.055) / 1.055) ** 2.4  # noqa: E731
        return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b_)
    high, low = sorted((lum(a), lum(b)), reverse=True)
    return (high + 0.05) / (low + 0.05)


def inks(text, themes_text):
    """Each theme's inks on its arcade surfaces: AA (4.5) or a problem."""
    tokens = text[text.index("arcade-tokens:start"):text.index("arcade-tokens:end")]
    found = []
    for theme in THEMES:
        light = theme.endswith("-light")
        arcade = _block(tokens, ":root[data-skin='game'][data-theme$='-light']" if light else ":root[data-skin='game']")
        own = _block(tokens, f":root[data-skin='game'][data-theme='{theme}']")
        arcade.update(own)
        ink = _block(themes_text, f':root[data-theme="{theme}"]')
        ink.update({k: v for k, v in own.items() if k in INKS})
        for name in INKS:
            if name not in ink:
                continue
            for surface in ("--arc-bg", "--arc-panel", "--arc-well"):
                ratio = _contrast(ink[name], arcade[surface])
                if ratio < 4.5:
                    found.append(f"{theme}: {name} {ink[name]} on {surface} {arcade[surface]} is {ratio:.2f}, under AA")
    return found


if __name__ == "__main__":
    js_files = [(str(f.relative_to(JS)), f.read_text()) for f in sorted(JS.rglob("*.js"))]
    found = problems(CSS.read_text()) + inks(CSS.read_text(), THEMES_CSS.read_text()) + words(LOCALE.read_text(), js_files)
    for line in found:
        print(line)
    print(f"skin: {len(found)} problems")
    sys.exit(1 if found else 0)
