"""The app's stylesheet source: css/input.css without the rules only the public site can use.

css/input.css stays the one stylesheet everyone edits. tool/tailwind.sh builds it twice: css/site.css
(the public pages, server/resources/views/public/layout.blade.php) from all of it, and css/app.css (the
app, and the server's own pages) from what this writes: the same rules in the same order, less every
rule whose selectors all need a site class (.s-* for the site, .cp-* for the course pages). The app
never renders those classes (tests/app-css.test.js checks), so a rule that needs one can never match
there, and taking it out changes nothing the app shows; the site keeps every rule in its old order.

Each of the two is split once more (docs/css-split-plan.md, step 3): its game skin's rules (every selector needs
<html data-skin="game">) go to their own sheet, css/app-game.css or css/site-game.css, which only game mode loads, linked
right after its own (js/game-css.js), so the cascade is as it was; serious mode never fetches them.

    python3 -I tool/app_css.py css/input.css css/.app-input.css css/.app-game-input.css
    python3 -I tool/app_css.py --site css/input.css css/.site-input.css css/.courses-input.css css/.site-game-input.css
    python3 -I tool/app_css.py --check      # the app's code names no site class (tool/test.sh)
"""
from pathlib import Path
import re
import sys

SITE_CLASS = re.compile(r"\.(?:s|cp)-[A-Za-z0-9_]")
# Block at-rules whose rules are filtered one by one; any other at-rule (@theme, @utility, @keyframes,
# @font-face, @layer's own blocks...) is kept whole.
FILTERED = ("@media", "@supports", "@container")


def statements(text):
    """The top-level statements of `text` as (start, end) slices, each with the comments before it."""
    out, i, n, start, depth = [], 0, len(text), 0, 0
    while i < n:
        if text.startswith("/*", i):
            i = text.index("*/", i) + 2
            continue
        c = text[i]
        if c in "\"'":
            j = i + 1
            while text[j] != c:
                j += 2 if text[j] == "\\" else 1
            i = j + 1
            continue
        if c == "{":
            depth += 1
        elif c == "}":
            depth -= 1
            if depth == 0:
                out.append((start, i + 1))
                start = i + 1
        elif c == ";" and depth == 0:
            out.append((start, i + 1))
            start = i + 1
        i += 1
    return out, start


def without_unrequired(selector):
    """The selector with what doesn't have to match taken out: :not(), :is() and :where() and their contents."""
    out, i = [], 0
    while i < len(selector):
        m = re.match(r":(?:not|is|where)\(", selector[i:])
        if m:
            depth, j = 1, i + m.end()
            while j < len(selector) and depth:
                depth += {"(": 1, ")": -1}.get(selector[j], 0)
                j += 1
            i = j
            continue
        out.append(selector[i])
        i += 1
    return "".join(out)


def split_selectors(head):
    """A selector list split at its top-level commas."""
    parts, depth, cur = [], 0, []
    for ch in head:
        if ch in "([":
            depth += 1
        elif ch in ")]":
            depth -= 1
        if ch == "," and depth == 0:
            parts.append("".join(cur))
            cur = []
        else:
            cur.append(ch)
    parts.append("".join(cur))
    return [p.strip() for p in parts if p.strip()]


def site_only(head, pattern=SITE_CLASS):
    """True when every selector in the list needs a class `pattern` matches (a site class, by default) to match."""
    selectors = split_selectors(re.sub(r"/\*.*?\*/", "", head, flags=re.S))
    return bool(selectors) and all(pattern.search(without_unrequired(s)) for s in selectors)


def blanked(text):
    """`text` with its comments turned to spaces, so positions in it are positions in `text`."""
    return re.sub(r"/\*.*?\*/", lambda m: " " * len(m.group(0)), text, flags=re.S)


def filtered(text, pattern=SITE_CLASS, only=False):
    """`text` (a stylesheet, or a block's inside) less the rules that need a `pattern` class; with `only`, just those."""
    parts, tail = statements(text)
    out = []
    for a, b in parts:
        chunk = text[a:b]
        code = re.sub(r"/\*.*?\*/", "", chunk, flags=re.S).strip()
        if "{" not in code:
            if not only:
                out.append(chunk)
            continue
        head = code.split("{", 1)[0].strip()
        if head.startswith("@"):
            if head.startswith(FILTERED):
                brace = blanked(chunk).index("{")
                inner = filtered(chunk[brace + 1:-1], pattern, only)
                if re.sub(r"/\*.*?\*/", "", inner, flags=re.S).strip():
                    out.append(chunk[:brace + 1] + inner + "}")
            elif not only:
                out.append(chunk)
        elif site_only(head, pattern) == only:
            out.append(chunk)
    if not only:
        out.append(text[tail:])
    return "".join(out)


# The course pages' classes (.cp-*): their rules are css/courses.css, which only a page showing one loads.
COURSE_CLASS = re.compile(r"\.cp-[A-Za-z0-9_]")
# Game mode (theme-boot.js sets <html data-skin="game">; nothing else ever carries data-skin): a rule every selector of
# which needs it can never match in serious mode, so it goes to the game sheet (step 3). @layer, @keyframes, @font-face
# and the like stay whole in the main sheet, and so do the course pages' game rules (in courses.css, per page already).
GAME = re.compile(r"\[data-skin=['\"]?game['\"]?\]")


WEB = Path(__file__).resolve().parent.parent
VIEWS = WEB.parent / "server/resources/views"
# A module's static and literal dynamic imports ("import x from './a.js'", "export * from", "import('./b.js')").
IMPORTS = re.compile(r"""(?:\bimport\s*(?:[^'"`;()]*?\bfrom\s*)?|\bimport\(\s*|\bexport\s+[^'"`;]*?\bfrom\s*)['"]([^'"]+\.js)['"]""")
# An import() of a computed address, which can't be followed: allowed only where it loads a module under js/public, which
# is scanned whole (a demo's data-demo-src, a tool page's and its embed's data-src, all written by the server's views).
COMPUTED_IMPORT = re.compile(r"""\bimport\(\s*(?!['"])|\bimport\(\s*`[^`]*\$\{""")
COMPUTED_OK = {"js/public/demo-slot.js", "js/public/tools/page.js", "js/public/tools/embed.js"}


def public_js():
    """The JS the public pages can load (docs/css-split-plan.md, step 2): every /js/ module a server view names, all of
    js/public and js/embed, and everything they import, followed through. Paths relative to web/, sorted. An import() of
    a computed address anywhere else fails, as the classes of what it loads couldn't be found."""
    seeds = {(WEB / "js" / m.group(1)).resolve() for v in VIEWS.rglob("*.php") for m in re.finditer(r"/js/([A-Za-z0-9_./-]+\.js)", v.read_text(encoding="utf-8", errors="ignore"))}
    seeds |= {p.resolve() for d in ("js/public", "js/embed") for p in (WEB / d).rglob("*.js")}
    seen, todo, computed = set(), [p for p in seeds if p.is_file()], []
    while todo:
        path = todo.pop()
        if path in seen:
            continue
        seen.add(path)
        code = path.read_text(encoding="utf-8", errors="ignore")
        rel = path.relative_to(WEB).as_posix()
        if COMPUTED_IMPORT.search(code) and rel not in COMPUTED_OK:
            computed.append(rel)
        for m in IMPORTS.finditer(code):
            spec = m.group(1)
            target = (WEB / spec.lstrip("/")).resolve() if spec.startswith("/") else (path.parent / spec).resolve()
            if target.is_file() and target.is_relative_to(WEB):
                todo.append(target)
    if computed:
        raise SystemExit("site css: an import() of a computed address in " + ", ".join(sorted(computed))
                         + "; its module's classes can't be found for css/site.css (tool/app_css.py COMPUTED_OK)")
    return sorted(p.relative_to(WEB).as_posix() for p in seen)


def site_source(text):
    """The public pages' stylesheet: all of css/input.css less the course pages' rules, its utilities only those the
    public pages can use (docs/css-split-plan.md, step 2). Tailwind's own detection is off (`source(none)`: otherwise
    it scans all of web/, whatever the @source lines say), and it scans the server's views and the JS the public pages
    load (public_js), not the app's own code: 63 KB less on every public page."""
    out = filtered(text, COURSE_CLASS)
    if out.count("@import 'tailwindcss';") != 1:
        raise SystemExit("site css: css/input.css has no @import 'tailwindcss'; to scan from")
    sources = "".join(f"\n@source '../{rel}';" for rel in public_js())
    out = re.sub(r"^@source [^;]+;\n?", "", out, flags=re.M)
    return out.replace("@import 'tailwindcss';", "@import 'tailwindcss' source(none);\n@source '../../server/resources/views';" + sources, 1)


def game_source(source):
    """A sheet's game skin alone: the rules of `source` (app_source's or site_source's) that need game mode, in their order,
    each in its @media, with css/input.css's theme and @apply names by reference (nothing of it written again)."""
    return '@reference "./input.css";\n' + filtered(source, GAME, only=True)


def without_game(source):
    """A sheet less its game skin's rules, which game_source holds."""
    return filtered(source, GAME)


def courses_source(text):
    """The course pages' own stylesheet: their rules only, in their order, with css/input.css's theme and @apply
    names by reference (`@reference`: nothing of it is written again, so no second reset over the site's rules)."""
    return '@reference "./input.css";\n' + filtered(text, COURSE_CLASS, only=True)


# Nor its utilities: the public pages' sources aren't scanned for the app (the server's own pages are). Nor the
# audio spike (spike_audio/, not part of the app): the public repo's tree leaves it out, and its words made a rule
# (.isolate) that a build of that tree lacks, which failed the deploy's CI there (2026-11-10).
NOT_SCANNED = "\n@source not '../../server/resources/views/public';\n@source not '../js/public';\n@source not '../js/embed';\n@source not '../tests';\n@source not '../spike_audio';"


def app_source(text):
    """The app's stylesheet source from css/input.css's text."""
    out = filtered(text)
    last = list(re.finditer(r"^@source [^;]+;", out, flags=re.M))
    if not last:
        raise SystemExit("css/input.css has no @source line to follow")
    end = last[-1].end()
    return out[:end] + NOT_SCANNED + out[end:]


# A site class written where a class could be: in quotes, a template string, or after a space in a class list.
CLASS_IN_CODE = re.compile(r"""(?<=["'`\s])(?:s|cp)-[a-z][a-z0-9-]*""")


def check():
    """Fails when the app's own code (not js/public or js/embed, which run on the public pages) names a site class."""
    web = Path(__file__).resolve().parent.parent
    sources = [p for p in (web / "js").rglob("*.js") if not p.is_relative_to(web / "js/public") and not p.is_relative_to(web / "js/embed")]
    found = []
    for path in [*sources, web / "index.html"]:
        for n, line in enumerate(path.read_text(encoding="utf-8").splitlines(), 1):
            if line.strip().startswith(("//", "*", "/*")):
                continue
            for m in CLASS_IN_CODE.finditer(line):
                found.append(f"{path.relative_to(web)}:{n}: {m.group(0)}")
    if found:
        print("app css: the app names a site class, which css/app.css leaves out (tool/app_css.py):")
        print("\n".join(found))
        raise SystemExit(1)
    print("app css: the app names no site class")


def main():
    if sys.argv[1:] == ["--check"]:
        check()
        return
    if sys.argv[1] == "--site":
        text = Path(sys.argv[2]).read_text(encoding="utf-8")
        site = site_source(text)
        Path(sys.argv[3]).write_text(without_game(site), encoding="utf-8")
        Path(sys.argv[4]).write_text(courses_source(text), encoding="utf-8")
        Path(sys.argv[5]).write_text(game_source(site), encoding="utf-8")
        return
    text = Path(sys.argv[1]).read_text(encoding="utf-8")
    app = app_source(text)
    Path(sys.argv[2]).write_text(without_game(app), encoding="utf-8")
    Path(sys.argv[3]).write_text(game_source(app), encoding="utf-8")


if __name__ == "__main__":
    main()
