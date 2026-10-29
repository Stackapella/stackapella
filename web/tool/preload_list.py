"""Writes lesson 1's modules into js/preload.js (the coordinator's first-load step, 2026-10-30): every
module js/main.js imports, and everything those import, nearest first, read from the import
statements themselves. A first visit then fetches them all at once (modulepreload) instead of
finding them one import at a time, a round trip each level down. No bundling and no build tool:
the files stay as they are. Run by tool/test.sh with --check, which fails when the list is stale.

    python3 -I web/tool/preload_list.py           # rewrite the list
    python3 -I web/tool/preload_list.py --check   # fail if it's out of date

LearnView is one of main.js's own imports, so the landing's Start (/learn/<lesson 1>) loads exactly
this graph before the lesson shows; the views loaded with import() (studio, settings and the rest)
aren't in it. Python standard library only.
"""
import re
import sys
from pathlib import Path

WEB = Path(__file__).resolve().parent.parent
ENTRY = WEB / "js" / "main.js"
PRELOAD = WEB / "js" / "preload.js"
# A static import or re-export (`import x from './y.js'`, `import './y.js'`, `export { x } from './y.js'`),
# never import() and never a bare specifier (vue, which preload.js preloads on its own).
STATIC = re.compile(r"""^\s*(?:import|export)\s+(?:[^'"`;()]*?\s+from\s+)?['"](\.{1,2}/[^'"]+|/[^'"]+)['"]""", re.M)
BEGIN, END = "    // preload_list.py: begin", "    // preload_list.py: end"


def imports(path):
    for spec in STATIC.findall(path.read_text()):
        target = (WEB / spec.lstrip("/")) if spec.startswith("/") else (path.parent / spec)
        target = target.resolve()
        if not target.is_file():
            sys.exit(f"{path.relative_to(WEB)} imports {spec}, which isn't there")
        yield target


def graph():
    """Every module main.js reaches by static imports, breadth first (what the browser asks for first, first)."""
    seen, order, level = {ENTRY}, [], [ENTRY]
    while level:
        following = []
        for module in level:
            for target in imports(module):
                if target not in seen:
                    seen.add(target)
                    order.append(target)
                    following.append(target)
        level = following
    return [f"/{p.relative_to(WEB).as_posix()}" for p in order]


def written(modules):
    lines = [BEGIN, "    const MODULES = ["]
    lines += [f"        '{m}'," for m in modules]
    lines += ["    ];", END]
    text = PRELOAD.read_text()
    start, end = text.index(BEGIN), text.index(END) + len(END)
    return text[:start] + "\n".join(lines) + text[end:]


def main():
    new = written(graph())
    if "--check" in sys.argv:
        if new != PRELOAD.read_text():
            sys.exit("FAIL js/preload.js's module list is out of date: run python3 -I web/tool/preload_list.py")
        print(f"preload: {new.count(chr(10) + '        ' + chr(39))} modules, up to date")
        return
    PRELOAD.write_text(new)
    print(f"preload: wrote {new.count(chr(10) + '        ' + chr(39))} modules into js/preload.js")


if __name__ == "__main__":
    main()
